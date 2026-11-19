<?php

namespace app\jobs\coins;

/**
 * @deprecated This job was misnamed. BackendUpdateServices() is NiceHash-specific.
 * @see \app\jobs\nicehash\NicehashSyncJob — correct replacement
 */
class UpdateServicesJob extends \app\jobs\nicehash\NicehashSyncJob
{
}
