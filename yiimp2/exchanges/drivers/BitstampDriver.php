<?php

namespace app\exchanges\drivers;

use app\exchanges\ExchangeDriver;
use app\models\Balances;

class BitstampDriver extends ExchangeDriver
{
    public function name(): string { return 'bitstamp'; }
    public function supportsBalance(): bool { return true; }

    public function syncBalance(): void
    {
        if ($this->isDisabled()) return;

        if (!defined('EXCH_BITSTAMP_ID') || !defined('EXCH_BITSTAMP_KEY') || !defined('EXCH_BITSTAMP_SECRET')) return;
        if (empty(EXCH_BITSTAMP_ID) || empty(EXCH_BITSTAMP_KEY) || empty(EXCH_BITSTAMP_SECRET)) return;

        $row = Balances::find()->where(['name' => $this->name()])->one();
        if (!$row) return;

        $data = $this->authenticatedPost('balance');
        if (!is_array($data)) return;

        $row->balance = (float)($data['btc_balance']  ?? 0) - (float)($data['btc_reserved'] ?? 0);
        $row->onsell  = (float)($data['btc_reserved'] ?? 0);
        $row->save();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Nonce: unix timestamp + 6-digit microseconds suffix. */
    private function nonce(): string
    {
        $mt = explode(' ', microtime());
        return $mt[1] . substr($mt[0], 2, 6);
    }

    /**
     * Signed POST to the Bitstamp private API (v1 endpoint).
     * Auth: HMAC-SHA256(nonce + customerId + apiKey, secret) → uppercase hex.
     */
    private function authenticatedPost(string $method, string $param = ''): mixed
    {
        $nonce  = $this->nonce();
        $msg    = $nonce . EXCH_BITSTAMP_ID . EXCH_BITSTAMP_KEY;
        $sign   = strtoupper(hash_hmac('sha256', $msg, EXCH_BITSTAMP_SECRET));

        $url    = "https://www.bitstamp.net/api/{$method}/";
        if ($param !== '') $url .= "{$param}/";

        $body   = http_build_query([
            'key'       => EXCH_BITSTAMP_KEY,
            'signature' => $sign,
            'nonce'     => $nonce,
        ]);

        $raw = $this->curlRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $body);

        return $raw ? json_decode($raw, true) : null;
    }

    /** Unsigned GET for public Bitstamp v2 endpoints. */
    private function publicGet(string $method, string $param = ''): mixed
    {
        $url = "https://www.bitstamp.net/api/v2/{$method}/";
        if ($param !== '') $url .= "{$param}/";
        $raw = $this->curlRequest('GET', $url);
        return $raw ? json_decode($raw, true) : null;
    }

    public function btcEur(): float
    {
        $ticker = $this->publicGet('ticker', 'btceur');
        return is_array($ticker) ? (float)($ticker['last'] ?? 0) : 0.0;
    }

    public function supportsBtcEur(): bool { return true; }
}
