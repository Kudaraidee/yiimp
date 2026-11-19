<?php

namespace app\components;

/**
 * Central registry of all recurring queue job classes.
 * Used by both the console QueueController and the web JobsController
 * so the authoritative list lives in exactly one place.
 */
class JobRegistry
{
    /** @return string[] Fully-qualified class names in display order. */
    public static function jobClasses(): array
    {
        return [
            // Block pipeline
            \app\jobs\blocks\ProcessNewBlocksJob::class,
            \app\jobs\blocks\UpdateBlockConfirmationsJob::class,
            \app\jobs\blocks\ScanTransactionsJob::class,
            \app\jobs\blocks\MonitorBtcJob::class,
            // Earnings
            \app\jobs\earnings\ClearEarningsJob::class,
            \app\jobs\earnings\PaymentsJob::class,
            // Coins
            \app\jobs\coins\UpdateCoinStatsJob::class,
            \app\jobs\coins\UpdateRawCoinsJob::class,
            // Stats
            \app\jobs\stats\UpdatePoolStatsJob::class,
            \app\jobs\stats\UpdateExtendedStatsJob::class,
            \app\jobs\stats\UpdateUserStatsJob::class,
            // Market
            \app\jobs\market\UpdatePricesJob::class,
            \app\jobs\market\ExchangeBalancesJob::class,
            \app\jobs\market\TradingJob::class,
            \app\jobs\market\SellCoinsJob::class,
            // Renting (pool rents OUT hash power)
            \app\jobs\renting\RentingUpdateJob::class,
            \app\jobs\renting\RentingPayoutJob::class,
            \app\jobs\renting\ProcessRentingJobsJob::class,
            // NiceHash (pool BUYS hash power from NiceHash)
            \app\jobs\nicehash\NicehashSyncJob::class,
            // System
            \app\jobs\system\NotificationsJob::class,
            \app\jobs\system\BenchUpdateJob::class,
            \app\jobs\system\MaintenanceJob::class,
        ];
    }

    /** Return the short class name (without namespace). */
    public static function shortName(string $fqcn): string
    {
        return substr(strrchr($fqcn, '\\'), 1);
    }

    /**
     * Return the domain group derived from the second-to-last namespace segment.
     * e.g. app\jobs\blocks\ProcessNewBlocksJob → 'blocks'
     */
    public static function domain(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return $parts[count($parts) - 2] ?? 'other';
    }

    /** Cache key used to mark a job as paused. */
    public static function pauseKey(string $shortName): string
    {
        return "job_paused_{$shortName}";
    }

    /** Cache key used to store a job's last-run Unix timestamp. */
    public static function lastRunKey(string $shortName): string
    {
        return "job_last_run_{$shortName}";
    }
}
