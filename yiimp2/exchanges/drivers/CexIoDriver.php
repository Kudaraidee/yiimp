<?php

namespace app\exchanges\drivers;

use app\exchanges\ExchangeDriver;
use app\models\Balances;

class CexIoDriver extends ExchangeDriver
{
    public function name(): string { return 'cexio'; }
    public function marketUrl(string $symbol, string $base = 'BTC'): string { return "https://cex.io/trade/{$symbol}-{$base}"; }
    public function supportsBalance(): bool { return true; }

    public function syncBalance(): void
    {
        if ($this->isDisabled()) return;

        if (!defined('EXCH_CEXIO_ID') || !defined('EXCH_CEXIO_KEY') || !defined('EXCH_CEXIO_SECRET')) return;
        if (empty(EXCH_CEXIO_ID) || empty(EXCH_CEXIO_KEY) || empty(EXCH_CEXIO_SECRET)) return;

        $row = Balances::find()->where(['name' => $this->name()])->one();
        if (!$row) return;

        $data = $this->authenticatedPost('balance');
        if (!is_array($data)) return;

        $btc = $data['BTC'] ?? [];
        $row->balance = (float)($btc['available'] ?? 0);
        $row->onsell  = (float)($btc['orders']    ?? 0);
        $row->save();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Signed POST to the CEX.io private API.
     * Nonce: unix timestamp (seconds).
     * Auth: HMAC-SHA256(nonce + userId + apiKey, secret) → uppercase hex.
     */
    private function authenticatedPost(string $method, array $extra = []): mixed
    {
        $nonce = (string) time();
        $msg   = $nonce . EXCH_CEXIO_ID . EXCH_CEXIO_KEY;
        $sign  = strtoupper(hash_hmac('sha256', $msg, EXCH_CEXIO_SECRET));

        $params = array_merge([
            'key'       => EXCH_CEXIO_KEY,
            'signature' => $sign,
            'nonce'     => $nonce,
        ], $extra);

        $url = "https://cex.io/api/{$method}/";
        $raw = $this->curlRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query($params, '', '&'));

        return $raw ? json_decode($raw, true) : null;
    }

    /** Unsigned GET for public CEX.io endpoints. */
    private function publicGet(string $method, string $param = ''): mixed
    {
        $url = "https://cex.io/api/{$method}/";
        if ($param !== '') $url .= $param;
        $raw = $this->curlRequest('GET', $url);
        return $raw ? json_decode($raw, true) : null;
    }

    public function btcEur(): float
    {
        $ticker = $this->publicGet('ticker', 'BTC/EUR');
        return is_array($ticker) ? (float)($ticker['last'] ?? 0) : 0.0;
    }

    public function supportsBtcEur(): bool { return true; }
}
