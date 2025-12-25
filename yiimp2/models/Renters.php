<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Renters model
 * 
 * @property int $id
 * @property int $created
 * @property int $updated
 * @property string $address
 * @property string $email
 * @property string $password
 * @property string $apikey
 * @property double $received
 * @property double $balance
 * @property double $unconfirmed
 * @property double $spent
 * @property double $custom_start
 * @property double $custom_balance
 * @property double $custom_accept
 * @property double $custom_reject
 * @property string $custom_address
 * @property string $custom_server
 */
class Renters extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'renters';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['address'], 'required'],
            [['created', 'updated'], 'integer'],
            [['received', 'balance', 'unconfirmed', 'spent', 'custom_start', 'custom_balance', 'custom_accept', 'custom_reject'], 'number'],
            [['address', 'email', 'custom_address', 'custom_server'], 'string', 'max' => 1024],
            [['password'], 'string', 'max' => 64],
            [['apikey'], 'string', 'max' => 1024],
            [['email'], 'email'],
            [['balance', 'received', 'unconfirmed', 'spent'], 'default', 'value' => 0],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created' => 'Created',
            'updated' => 'Updated',
            'address' => 'Address',
            'email' => 'Email',
            'password' => 'Password',
            'apikey' => 'API Key',
            'received' => 'Received',
            'balance' => 'Balance',
            'unconfirmed' => 'Unconfirmed',
            'spent' => 'Spent',
            'custom_start' => 'Custom Start',
            'custom_balance' => 'Custom Balance',
            'custom_accept' => 'Custom Accept',
            'custom_reject' => 'Custom Reject',
            'custom_address' => 'Custom Address',
            'custom_server' => 'Custom Server',
        ];
    }

    /**
     * Get jobs (rental orders) relation
     */
    public function getJobs()
    {
        return $this->hasMany(Jobs::class, ['renterid' => 'id']);
    }

    /**
     * Get transactions relation
     */
    public function getTransactions()
    {
        return $this->hasMany(Rentertxs::class, ['renterid' => 'id']);
    }

    /**
     * Get hashrate records relation
     */
    public function getHashrenter()
    {
        return $this->hasMany(Hashrenter::class, ['renterid' => 'id']);
    }

    /**
     * Generate unique API key
     */
    public function generateApiKey()
    {
        $this->apikey = Yii::$app->security->generateRandomString(32);
    }

    /**
     * Hash password
     */
    public function setPassword($password)
    {
        $this->password = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Validate password
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password);
    }

    /**
     * Get available balance (balance - unconfirmed)
     */
    public function getAvailableBalance()
    {
        return max(0, $this->balance - $this->unconfirmed);
    }

    /**
     * Add to balance
     */
    public function addBalance($amount)
    {
        $this->balance += $amount;
        $this->received += $amount;
        $this->updated = time();
        return $this->save(false);
    }

    /**
     * Deduct from balance
     */
    public function deductBalance($amount)
    {
        if ($this->balance < $amount) {
            return false;
        }
        $this->balance -= $amount;
        $this->spent += $amount;
        $this->updated = time();
        return $this->save(false);
    }

    /**
     * Before save
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created = time();
            }
            $this->updated = time();
            return true;
        }
        return false;
    }
}
