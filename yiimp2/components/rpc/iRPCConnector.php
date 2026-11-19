<?php
namespace app\components\rpc;

interface iRPCConnector
{
    /**
     * Execute an RPC method and return the result, or false on error.
     */
    public function call(string $method, array $params = []): mixed;

    /**
     * Return the last error message, or null if no error occurred.
     */
    public function getError(): ?string;
}

