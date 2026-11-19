<?php
namespace app\components\rpc;

use app\components\rpc\JsonRpc;
use app\components\rpc\RpcException;
use app\components\rpc\iRPCConnector;

/**
 * Ethereum JSON-RPC client — updated for Geth v1.14+ and Core-geth (ETC).
 *
 * Compatibility matrix:
 *   ETH mainnet (Geth)   — post-Merge PoS; mining methods always return 0/false
 *   Ethereum Classic     — PoW (Ethash); mining methods are functional
 *
 * Removed namespaces (no longer present in any modern node):
 *   - db_*  : removed in Geth ~1.6 (2017)
 *   - shh_* : Whisper removed in Geth 1.10.0 (2021); repo archived
 *   - eth_compile* / eth_getCompilers : removed in Geth ~1.6 (EIP-209)
 *
 * PoW-only methods (functional on ETC, dead on ETH mainnet):
 *   eth_coinbase (deprecated Geth 1.14+), eth_mining, eth_hashrate,
 *   eth_getWork, eth_submitWork, eth_submitHashrate
 *
 * New methods since the legacy codebase (2018):
 *   EIP-1559 (London 2021) : eth_feeHistory, eth_maxPriorityFeePerGas
 *   EIP-2930 (Berlin 2021) : eth_createAccessList
 *   EIP-4844 (Cancun 2024) : eth_blobBaseFee, eth_getBlockReceipts (also Geth ext.)
 *   Geth extensions        : eth_getHeaderByHash, eth_getHeaderByNumber
 *   Always-standard        : eth_chainId, eth_sendRawTransaction
 *
 * ETC-specific (Core-geth only):
 *   trace_* namespace (aliases to debug_*) — use via call() / etherRequest()
 */
class EthereumRPC extends JsonRpc implements iRPCConnector
{
    // iRPCConnector -----------------------------------------------------------

    public function call(string $method, array $params = []): mixed
    {
        return $this->etherRequest($method, $params);
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    // Helpers -----------------------------------------------------------------

    /**
     * Execute any JSON-RPC method and return the `result` field, or false on error.
     */
    public function etherRequest(string $method, array $params = []): mixed
    {
        try {
            $ret = $this->request($method, $params);
            return $ret->result ?? null;
        } catch (RpcException $e) {
            $this->error = (string) $e;
            return false;
        }
    }

    /**
     * Decode a hex string (with or without 0x prefix) to an integer/float.
     * Returns the original input if it does not look like a hex value.
     */
    public function decodeHex(string $input): mixed
    {
        if (str_starts_with($input, '0x')) {
            $input = substr($input, 2);
        }
        if ($input !== '' && preg_match('/^[a-f0-9]+$/i', $input)) {
            return hexdec($input);
        }
        return $input;
    }

    // web3_* ------------------------------------------------------------------

    public function web3_clientVersion(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    public function web3_sha3(string $input): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$input]);
    }

    // net_* -------------------------------------------------------------------

    public function net_version(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    public function net_listening(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    public function net_peerCount(bool $decodeHex = true): mixed
    {
        $peers = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($peers)) ? $this->decodeHex($peers) : $peers;
    }

    // eth_* — chain / protocol ------------------------------------------------

    /** Returns the chain ID (ETH=1, ETC=61, etc.). */
    public function eth_chainId(bool $decodeHex = true): mixed
    {
        $id = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($id)) ? $this->decodeHex($id) : $id;
    }

    public function eth_protocolVersion(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    public function eth_syncing(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    // eth_* — accounts / balances ---------------------------------------------

    public function eth_accounts(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    public function eth_getBalance(string $address, string $block = 'latest', bool $decodeHex = true): mixed
    {
        $balance = $this->etherRequest(__FUNCTION__, [$address, $block]);
        return ($decodeHex && is_string($balance)) ? $this->decodeHex($balance) : $balance;
    }

    public function eth_getTransactionCount(string $address, string $block = 'latest', bool $decodeHex = true): mixed
    {
        $count = $this->etherRequest(__FUNCTION__, [$address, $block]);
        return ($decodeHex && is_string($count)) ? $this->decodeHex($count) : $count;
    }

    public function eth_getCode(string $address, string $block = 'latest'): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$address, $block]);
    }

    public function eth_getStorageAt(string $address, string $slot, string $block = 'latest'): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$address, $slot, $block]);
    }

    public function eth_sign(string $address, string $data): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$address, $data]);
    }

    // eth_* — blocks ----------------------------------------------------------

    public function eth_blockNumber(bool $decodeHex = true): mixed
    {
        $block = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($block)) ? $this->decodeHex($block) : $block;
    }

    public function eth_getBlockByHash(string $hash, bool $fullTx = true): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$hash, $fullTx]);
    }

    public function eth_getBlockByNumber(mixed $block = 'latest', bool $fullTx = true): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$block, $fullTx]);
    }

    /** Geth extension: returns just the block header (lighter than full block). */
    public function eth_getHeaderByHash(string $hash): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$hash]);
    }

    /** Geth extension: returns just the block header by number or tag. */
    public function eth_getHeaderByNumber(mixed $block = 'latest'): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$block]);
    }

    /** Returns all transaction receipts for a block in a single call (Geth 1.13+). */
    public function eth_getBlockReceipts(mixed $block = 'latest'): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$block]);
    }

    public function eth_getBlockTransactionCountByHash(string $blockHash): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$blockHash]);
    }

    public function eth_getBlockTransactionCountByNumber(mixed $block = 'latest'): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$block]);
    }

    public function eth_getUncleCountByBlockHash(string $blockHash): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$blockHash]);
    }

    public function eth_getUncleCountByBlockNumber(mixed $block = 'latest'): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$block]);
    }

    public function eth_getUncleByBlockHashAndIndex(string $hash, int $index): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$hash, $index]);
    }

    public function eth_getUncleByBlockNumberAndIndex(mixed $block, int $index): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$block, $index]);
    }

    // eth_* — transactions ----------------------------------------------------

    public function eth_getTransactionByHash(string $hash): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$hash]);
    }

    public function eth_getTransactionByBlockHashAndIndex(string $hash, int $index): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$hash, $index]);
    }

    public function eth_getTransactionByBlockNumberAndIndex(mixed $block, int $index): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$block, $index]);
    }

    public function eth_getTransactionReceipt(string $txHash): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$txHash]);
    }

    /**
     * Send a transaction object (requires node to hold the key or Clef).
     * On public endpoints use eth_sendRawTransaction with a pre-signed tx.
     */
    public function eth_sendTransaction(EthereumTransaction $transaction): mixed
    {
        return $this->etherRequest(__FUNCTION__, $transaction->toArray());
    }

    /**
     * Broadcast a pre-signed, RLP-encoded transaction.
     * This is the standard production path for all transaction types (0–3).
     */
    public function eth_sendRawTransaction(string $signedTxHex): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$signedTxHex]);
    }

    /**
     * Execute a call without creating a transaction (read-only simulation).
     *
     * @param EthereumMessage|array $transaction  Call object or assoc array
     */
    public function eth_call(EthereumMessage|array $transaction, string $block = 'latest'): mixed
    {
        $tx = ($transaction instanceof EthereumMessage)
            ? $transaction->toArray()[0]
            : $transaction;
        return $this->etherRequest(__FUNCTION__, [$tx, $block]);
    }

    /**
     * Estimate the gas required to execute a transaction.
     *
     * @param EthereumMessage|array $transaction  Call object or assoc array
     */
    public function eth_estimateGas(EthereumMessage|array $transaction, string $block = 'latest'): mixed
    {
        $tx = ($transaction instanceof EthereumMessage)
            ? $transaction->toArray()[0]
            : $transaction;
        return $this->etherRequest(__FUNCTION__, [$tx, $block]);
    }

    /**
     * Generate an EIP-2930 access list for a transaction (reduces gas on state access).
     *
     * @param array $transaction  Call object (same shape as eth_call)
     */
    public function eth_createAccessList(array $transaction, string $block = 'latest'): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$transaction, $block]);
    }

    // eth_* — gas / fee market ------------------------------------------------

    /**
     * Returns the current gas price in wei (legacy; still accurate for ETC).
     * On EIP-1559 chains prefer eth_maxPriorityFeePerGas + eth_feeHistory.
     */
    public function eth_gasPrice(bool $decodeHex = true): mixed
    {
        $gas = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($gas)) ? $this->decodeHex($gas) : $gas;
    }

    /**
     * EIP-1559: returns the suggested miner tip (priority fee) per gas in wei.
     * Not meaningful on ETC (no EIP-1559).
     */
    public function eth_maxPriorityFeePerGas(bool $decodeHex = true): mixed
    {
        $tip = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($tip)) ? $this->decodeHex($tip) : $tip;
    }

    /**
     * EIP-1559: returns historical base fee and reward percentile data.
     *
     * @param int    $blockCount       Number of blocks to include (1–1024)
     * @param string $newestBlock      Block tag or hex number
     * @param array  $rewardPercentiles Percentiles for priority fee reward samples (e.g. [25, 75])
     */
    public function eth_feeHistory(int $blockCount, string $newestBlock = 'latest', array $rewardPercentiles = []): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$blockCount, $newestBlock, $rewardPercentiles]);
    }

    /**
     * EIP-4844: returns the expected blob base fee for the next block (Geth 1.13+).
     * Not present on ETC.
     */
    public function eth_blobBaseFee(bool $decodeHex = true): mixed
    {
        $fee = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($fee)) ? $this->decodeHex($fee) : $fee;
    }

    // eth_* — filters / logs --------------------------------------------------

    public function eth_newFilter(EthereumFilter $filter, bool $decodeHex = true): mixed
    {
        $id = $this->etherRequest(__FUNCTION__, $filter->toArray());
        return ($decodeHex && is_string($id)) ? $this->decodeHex($id) : $id;
    }

    public function eth_newBlockFilter(bool $decodeHex = true): mixed
    {
        $id = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($id)) ? $this->decodeHex($id) : $id;
    }

    public function eth_newPendingTransactionFilter(bool $decodeHex = true): mixed
    {
        $id = $this->etherRequest(__FUNCTION__);
        return ($decodeHex && is_string($id)) ? $this->decodeHex($id) : $id;
    }

    public function eth_uninstallFilter(mixed $id): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$id]);
    }

    public function eth_getFilterChanges(mixed $id): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$id]);
    }

    public function eth_getFilterLogs(mixed $id): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$id]);
    }

    public function eth_getLogs(EthereumFilter $filter): mixed
    {
        return $this->etherRequest(__FUNCTION__, $filter->toArray());
    }

    // eth_* — PoW (ETC only; dead/deprecated on ETH mainnet post-Merge) --------

    /**
     * @note Deprecated in Geth 1.14+; still functional on ETC (Core-geth).
     *       On ETH mainnet always returns the zero address.
     */
    public function eth_coinbase(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    /**
     * @note Always returns false on ETH mainnet (PoS). Functional on ETC.
     */
    public function eth_mining(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    /**
     * @note Always returns 0x0 on ETH mainnet (PoS). Functional on ETC.
     */
    public function eth_hashrate(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    /**
     * @note Dead on ETH mainnet post-Merge. Functional on ETC.
     */
    public function eth_getWork(): mixed
    {
        return $this->etherRequest(__FUNCTION__);
    }

    /**
     * @note Dead on ETH mainnet post-Merge. Functional on ETC.
     */
    public function eth_submitWork(string $nonce, string $powHash, string $mixDigest): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$nonce, $powHash, $mixDigest]);
    }

    /**
     * @note Dead on ETH mainnet post-Merge. Functional on ETC.
     */
    public function eth_submitHashrate(string $hashrate, string $clientId): mixed
    {
        return $this->etherRequest(__FUNCTION__, [$hashrate, $clientId]);
    }
}

// ---------------------------------------------------------------------------
// Value-object helpers
// ---------------------------------------------------------------------------

/**
 * Ethereum transaction.  Supports both legacy (Type 0) and EIP-1559 (Type 2).
 *
 * Legacy (Type 0)  — set $gasPrice, leave $maxFeePerGas / $maxPriorityFeePerGas null
 * EIP-1559 (Type 2) — set $maxFeePerGas + $maxPriorityFeePerGas, leave $gasPrice null
 */
class EthereumTransaction
{
    public function __construct(
        private string  $from,
        private string  $to,
        private string  $gas,
        private ?string $gasPrice             = null,
        private string  $value                = '0x0',
        private string  $data                 = '',
        private ?string $nonce                = null,
        private ?string $maxFeePerGas         = null,
        private ?string $maxPriorityFeePerGas = null,
        private ?array  $accessList           = null
    ) {}

    public function toArray(): array
    {
        if ($this->maxFeePerGas !== null) {
            // EIP-1559 Type 2
            $tx = [
                'from'                  => $this->from,
                'to'                    => $this->to,
                'gas'                   => $this->gas,
                'value'                 => $this->value,
                'data'                  => $this->data,
                'maxFeePerGas'          => $this->maxFeePerGas,
                'maxPriorityFeePerGas'  => $this->maxPriorityFeePerGas,
            ];
        } else {
            // Legacy Type 0
            $tx = [
                'from'     => $this->from,
                'to'       => $this->to,
                'gas'      => $this->gas,
                'gasPrice' => $this->gasPrice,
                'value'    => $this->value,
                'data'     => $this->data,
            ];
        }

        if ($this->nonce !== null) {
            $tx['nonce'] = $this->nonce;
        }
        if ($this->accessList !== null) {
            $tx['accessList'] = $this->accessList;
        }

        return [$tx];
    }
}

/** Same structure as EthereumTransaction but used for eth_call / eth_estimateGas (no broadcast). */
class EthereumMessage extends EthereumTransaction {}

/**
 * Filter object used for eth_newFilter / eth_getLogs.
 * Supports address as a string (single) or array (multiple).
 */
class EthereumFilter
{
    public function __construct(
        private string       $fromBlock,
        private string       $toBlock,
        private string|array $address,
        private array        $topics
    ) {}

    public function toArray(): array
    {
        return [[
            'fromBlock' => $this->fromBlock,
            'toBlock'   => $this->toBlock,
            'address'   => $this->address,
            'topics'    => $this->topics,
        ]];
    }
}
