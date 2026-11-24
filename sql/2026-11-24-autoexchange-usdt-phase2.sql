-- Autoexchange Phase 2: opt LMT into conversion, keep NEB held.
--
-- This does NOT start any queue worker and does NOT change deposit addresses.
-- The generic SellCoinsJob remains a no-op for LMT because sellthreshold is
-- left at its disabled value (10000). Use the controlled command instead:
--
--   php yii market/deposit LMT 'nestex USDT' <REAL_EXCHANGE_DEPOSIT_ADDRESS>
--   php yii market/sell LMT <AMOUNT> --dryRun=1
--   php yii market/sell LMT <AMOUNT>
--
-- NEB stays with dontsell=1 until its payout/RPC cycle is ready.

UPDATE `coins`
SET `dontsell` = 0,
    `market` = 'nestex USDT',
    `sellthreshold` = 10000
WHERE `symbol` = 'LMT';

UPDATE `coins`
SET `dontsell` = 1,
    `sellthreshold` = 10000
WHERE `symbol` = 'NEB';
