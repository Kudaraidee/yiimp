<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use app\models\Renters;
use app\models\RenterTxs;
use app\models\Jobs;
use app\models\Coins;
use app\components\rpc\WalletRPC;

/**
 * Renting portal — renters buy hash power FROM the pool and direct it to a third-party pool.
 * Ported from web/yaamp/modules/renting/RentingController.php.
 *
 * Session key 'renting-deposit' stores the authenticated renter's deposit address.
 */
class RentingController extends BaseController
{
    public function actions(): array
    {
        return [
            'captcha' => [
                'class'           => 'yii\captcha\CaptchaAction',
                'backColor'       => 0xeeeeee,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    // ── Auth helpers ──────────────────────────────────────────────────────────

    private function depositAddress(): string
    {
        return (string) Yii::$app->session->get('renting-deposit', '');
    }

    private function isAdmin(): bool
    {
        return !Yii::$app->user->isGuest && (Yii::$app->user->identity?->is_admin ?? false);
    }

    private function findRenter(string $address): ?Renters
    {
        if (empty($address)) return null;
        $address = trim(substr($address, 0, 35));
        return Renters::find()->where(['address' => $address])->one();
    }

    private function renterAccount(Renters $renter): string
    {
        return (defined('YIIMP_PRODUCTION') && YIIMP_PRODUCTION)
            ? "renter-prod-{$renter->id}"
            : "renter-dev-{$renter->id}";
    }

    /** Verify the request address matches the session deposit (or user is admin). */
    private function verifyAddress(string $address): bool
    {
        return $this->isAdmin() || $this->depositAddress() === $address;
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function actionLogin(): mixed
    {
        $deposit  = substr((string) Yii::$app->request->post('deposit_address', ''), 0, 35);
        $password = substr((string) Yii::$app->request->post('deposit_password', ''), 0, 64);

        if (!Yii::$app->request->isPost) {
            return $this->render('login');
        }

        $renter = $this->findRenter($deposit);
        if (!$renter) {
            return $this->render('login');
        }

        if (!empty($renter->password) && $renter->password !== md5($password)) {
            Yii::$app->session->setFlash('error', 'Login failed.');
            return $this->render('login');
        }

        Yii::$app->session->set('renting-deposit', $renter->address);
        return $this->redirect(['/renting']);
    }

    public function actionIndex(): mixed
    {
        $deposit = $this->depositAddress();

        if ($this->isAdmin()) {
            $address = Yii::$app->request->get('address', '');
            if (!empty($address)) {
                $deposit = $address;
                Yii::$app->session->set('renting-deposit', $deposit);
            }
        }

        if (empty($deposit) && !$this->isAdmin()) {
            return $this->render('login');
        }

        $renter = $this->findRenter($deposit);
        if (!$renter) {
            return $this->render('login');
        }

        // Save profile changes
        if (Yii::$app->request->isPost) {
            $changed = false;

            if (Yii::$app->request->post('deposit_email') !== null) {
                $renter->email = Yii::$app->request->post('deposit_email');
                $changed = true;
            }

            $pw  = Yii::$app->request->post('deposit_password', '');
            $pw2 = Yii::$app->request->post('deposit_confirm', '');
            if (!empty($pw)) {
                if ($pw !== $pw2) {
                    Yii::$app->session->setFlash('error', 'Confirm different from password.');
                    return $this->redirect(['/renting']);
                }
                $renter->password = md5($pw);
                $changed = true;
            }

            if ($changed) {
                Yii::$app->db->createCommand()->update('renters',
                    ['email' => $renter->email, 'password' => $renter->password],
                    ['id' => $renter->id]
                )->execute();
                Yii::$app->session->setFlash('message', 'Settings saved.');
                return $this->redirect(['/renting']);
            }
        }

        return $this->render('index', ['renter' => $renter]);
    }

    public function actionSettings(): string
    {
        $deposit = $this->depositAddress();
        $renter  = $this->findRenter($deposit);
        if (!$renter) return $this->render('login');
        return $this->render('settings', ['renter' => $renter]);
    }

    public function actionAdmin(): string
    {
        if (!$this->isAdmin()) return $this->goHome();
        return $this->render('admin');
    }

    public function actionCreate(): mixed
    {
        $code = Yii::$app->request->post('create_code', '');
        if (empty($code)) {
            return $this->redirect(['/renting']);
        }

        // Validate captcha
        $captchaAction = $this->createAction('captcha');
        if (!$captchaAction->validate($code, false)) {
            return $this->redirect(['/renting']);
        }

        $btc = Coins::find()->where(['symbol' => 'BTC'])->one();
        if (!$btc) return $this->redirect(['/renting']);

        $remote = new WalletRPC($btc);
        $renter  = new Renters();
        $renter->created     = time();
        $renter->updated     = time();
        $renter->balance     = 0;
        $renter->unconfirmed = 0;
        $renter->save();

        $renter = Renters::findOne($renter->id);
        $renter->address = $remote->getaccountaddress($this->renterAccount($renter));
        $renter->apikey  = hash('sha256', $renter->address . time() . rand());
        $renter->save();

        // Move any pre-existing balance on that account
        $received = (float) $remote->getbalance($this->renterAccount($renter), 1);
        if ($received > 0) {
            $remote->move($this->renterAccount($renter), '', $received);
        }

        Yii::$app->session->set('renting-deposit', $renter->address);
        return $this->redirect(['/renting/settings']);
    }

    public function actionLogout(): \yii\web\Response
    {
        Yii::$app->session->remove('renting-deposit');
        return $this->redirect(['/renting']);
    }

    public function actionTx(): string
    {
        $address = Yii::$app->request->get('address', '');
        $renter  = $this->findRenter($address);
        if (!$renter) return '';

        $btc = Coins::find()->where(['symbol' => 'BTC'])->one();
        if (!$btc) return '';

        $remote = new WalletRPC($btc);
        $txs    = $remote->listtransactions($this->renterAccount($renter), 10);

        return $this->renderPartial('tx', ['renter' => $renter, 'txs' => $txs]);
    }

    // ── Job management ────────────────────────────────────────────────────────

    public function actionJobs_stop(): \yii\web\Response
    {
        $job    = Jobs::findOne((int) Yii::$app->request->get('id'));
        $renter = $job ? Renters::findOne($job->renterid) : null;
        if (!$renter || $renter->address !== $this->depositAddress()) {
            return $this->redirect(['/renting']);
        }
        $job->active = false;
        $job->ready  = false;
        $job->time   = time();
        $job->save(false);
        return $this->redirect(Yii::$app->request->referrer ?: ['/renting']);
    }

    public function actionJobs_start(): \yii\web\Response
    {
        $job    = Jobs::findOne((int) Yii::$app->request->get('id'));
        $renter = $job ? Renters::findOne($job->renterid) : null;
        if (!$renter || $renter->balance <= 0.00001 || $renter->address !== $this->depositAddress()) {
            return $this->redirect(['/renting']);
        }
        $rent = (float) Yii::$app->db->createCommand(
            "SELECT rent FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $job->algo]
        )->queryScalar();
        if ($job->price > $rent) $job->active = true;
        $job->ready = true;
        $job->time  = time();
        $job->save(false);
        return $this->redirect(Yii::$app->request->referrer ?: ['/renting']);
    }

    public function actionJobs_startall(): \yii\web\Response
    {
        $renter = $this->findRenter($this->depositAddress());
        if (!$renter || $renter->balance <= 0.00001) return $this->redirect(['/renting']);
        foreach (Jobs::find()->where(['renterid' => $renter->id])->all() as $job) {
            $rent = (float) Yii::$app->db->createCommand(
                "SELECT rent FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $job->algo]
            )->queryScalar();
            if ($job->price > $rent) $job->active = true;
            $job->ready = true;
            $job->time  = time();
            $job->save(false);
        }
        return $this->redirect(Yii::$app->request->referrer ?: ['/renting']);
    }

    public function actionJobs_stopall(): \yii\web\Response
    {
        $renter = $this->findRenter($this->depositAddress());
        if (!$renter) return $this->redirect(['/renting']);
        foreach (Jobs::find()->where(['renterid' => $renter->id])->all() as $job) {
            $job->active = false;
            $job->ready  = false;
            $job->time   = time();
            $job->save(false);
        }
        return $this->redirect(Yii::$app->request->referrer ?: ['/renting']);
    }

    // ── AJAX partials ─────────────────────────────────────────────────────────

    public function actionBalance_results(): string
    {
        $address = Yii::$app->request->get('address', '');
        if (!$this->verifyAddress($address)) return '';
        $renter = $this->findRenter($address);
        if (!$renter) return '';
        return $this->renderPartial('balance_results', ['renter' => $renter, 'isAdmin' => $this->isAdmin()]);
    }

    public function actionOrders_results(): string
    {
        $address = Yii::$app->request->get('address', '');
        if (!$this->verifyAddress($address)) return '';
        $renter = $this->findRenter($address);
        if (!$renter) return '';
        return $this->renderPartial('orders_results', ['renter' => $renter, 'isAdmin' => $this->isAdmin()]);
    }

    public function actionAll_orders_results(): string
    {
        $address = Yii::$app->request->get('address', '');
        $renter  = $address ? $this->findRenter($address) : null;
        return $this->renderPartial('all_orders_results', ['renter' => $renter, 'isAdmin' => $this->isAdmin()]);
    }

    public function actionStatus_results(): string
    {
        return $this->renderPartial('status_results');
    }

    public function actionGraph_price_results(): void
    {
        $this->renderPartial('graph_price_results');
    }

    public function actionGraph_job_results(): void
    {
        $this->renderPartial('graph_job_results');
    }

    // ── Order management ──────────────────────────────────────────────────────

    public function actionOrderDialog(): string
    {
        $address = Yii::$app->request->get('address', '');
        $renter  = $this->findRenter($address);
        if (!$renter) return '';
        $job = Jobs::findOne((int) Yii::$app->request->get('id', 0));
        return $this->renderPartial('order_dialog', ['renter' => $renter, 'job' => $job, 'isAdmin' => $this->isAdmin()]);
    }

    public function actionOrderDelete(): \yii\web\Response
    {
        $job    = Jobs::findOne((int) Yii::$app->request->get('id'));
        $renter = $job ? Renters::findOne($job->renterid) : null;
        if (!$renter || $renter->address !== $this->depositAddress()) {
            return $this->redirect(['/renting']);
        }
        $job->delete();
        return $this->redirect(['/renting', 'address' => $renter->address]);
    }

    public function actionOrderSave(): \yii\web\Response
    {
        $r         = Yii::$app->request;
        $renterid  = (int) $r->post('order_renterid');
        $renter    = Renters::findOne($renterid);
        if (!$renter || $renter->address !== $this->depositAddress()) {
            return $this->redirect(['/renting']);
        }

        $jobId = (int) $r->post('order_id');
        $job   = $jobId ? Jobs::findOne($jobId) : new Jobs();
        if (!$job) $job = new Jobs();
        if (!$jobId) $job->renterid = $renterid;

        $job->algo     = substr((string) $r->post('order_algo', ''), 0, 32);
        $job->username = substr((string) $r->post('order_username', ''), 0, 200);
        $job->password = substr((string) $r->post('order_password', ''), 0, 200);
        $job->percent  = (float) $r->post('order_percent', 0);
        $job->price    = (float) $r->post('order_price', 0);
        $job->speed    = (int)   ($r->post('order_speed', 0) * 1_000_000);

        $hostRaw = str_replace('stratum+tcp://', '', (string) $r->post('order_host', ''));
        $parts   = explode(':', $hostRaw, 2);
        if (count($parts) < 2 || empty($parts[0]) || empty($parts[1])) {
            Yii::$app->session->setFlash('error', 'Invalid server URL');
            return $this->redirect(['/renting']);
        }

        $job->host = trim($parts[0]);
        $job->port = (int) $parts[1];

        if (empty($job->algo) || empty($job->username) || empty($job->password)
            || empty($job->price) || $job->speed < 100_000) {
            return $this->redirect(['/renting']);
        }

        if (defined('YIIMP_STRATUM_URL') && stripos($job->host, YIIMP_STRATUM_URL) !== false) {
            Yii::$app->session->setFlash('error', 'Invalid server URL');
            return $this->redirect(['/renting']);
        }

        $rent = (float) Yii::$app->db->createCommand(
            "SELECT rent FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $job->algo]
        )->queryScalar();

        if ($job->price > $rent && $job->ready) $job->active = true;
        elseif ($job->price < $rent)             $job->active = false;

        $job->time = time();
        $job->save();

        return $this->redirect(['/renting', 'address' => $r->post('order_address', '')]);
    }

    public function actionResetSpent(): \yii\web\Response
    {
        $address = Yii::$app->request->get('address', '');
        $renter  = $this->findRenter($address);
        if (!$renter) return $this->redirect(['/renting']);
        $renter->custom_start = 0;
        $renter->spent        = $renter->custom_balance;
        $renter->save(false);
        return $this->redirect(Yii::$app->request->referrer ?: ['/renting']);
    }

    public function actionWithdraw(): \yii\web\Response
    {
        $deposit = $this->depositAddress();
        $renter  = $this->findRenter($deposit);
        if (!$renter) return $this->redirect(['/renting']);

        $fees   = defined('YIIMP_TXFEE_RENTING_WD') ? (float) YIIMP_TXFEE_RENTING_WD : 0.002;
        $mini   = defined('YIIMP_PAYMENTS_MINI')     ? (float) YIIMP_PAYMENTS_MINI     : 0.001;
        $cu     = Yii::$app->ConversionUtils;

        $amount  = (float) $cu->bitcoinvaluetoa(min(
            (float) Yii::$app->request->post('withdraw_amount', 0),
            $renter->balance - $fees
        ));
        $address = Yii::$app->request->post('withdraw_address', '');

        if ($amount < $mini) {
            Yii::$app->session->setFlash('error', "Minimum withdraw is {$mini}");
            return $this->redirect(['/renting']);
        }

        $btc = Coins::find()->where(['symbol' => 'BTC'])->one();
        if (!$btc) return $this->redirect(['/renting']);

        $remote = new WalletRPC($btc);
        $res    = $remote->validateaddress($address);
        if (!$res || empty($res['isvalid'])) {
            Yii::$app->session->setFlash('error', 'Invalid address');
            return $this->redirect(['/renting']);
        }

        $tx              = new RenterTxs();
        $tx->renterid    = $renter->id;
        $tx->time        = time();
        $tx->amount      = $amount;
        $tx->type        = 'withdraw';
        $tx->address     = $address;
        $tx->tx          = 'scheduled';
        $tx->save();

        Yii::info("withdraw scheduled renter={$renter->id} addr={$renter->address} amount={$amount} to={$address}", __CLASS__);

        Yii::$app->session->setFlash('message', 'Withdraw scheduled');
        return $this->redirect(['/renting']);
    }
}
