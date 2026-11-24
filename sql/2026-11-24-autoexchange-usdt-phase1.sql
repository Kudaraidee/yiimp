-- Autoexchange Phase 1: credit mined earnings in USDT.
--
-- This only changes accounting. It does NOT send coins to an exchange and
-- does NOT create USDT withdrawals. Leave dontsell=1 until Phase 2.
--
-- payout_quote is already USDT on the isolated pool; keep it idempotent.
UPDATE `settings` SET `value` = 'USDT' WHERE `param` = 'payout_quote';

-- Only coins with a live, non-deleted USDT market are converted. NEB uses
-- safetrade USDT; LMT uses nestex USDT. Other mined coins stay native.
UPDATE `coins` c
SET c.`auto_exchange` = 1
WHERE c.`symbol` IN ('NEB', 'LMT')
  AND EXISTS (
      SELECT 1 FROM `markets` m
      WHERE m.`coinid` = c.`id`
        AND m.`base_coin` = 'USDT'
        AND m.`price` > 0
        AND m.`disabled` = 0
        AND m.`deleted` = 0
  );

-- Expire after applying:
--   api_status, yaamp_algo_list, yaamp_coin_list, YIIMP_coin_list,
--   payout quote cache (new web/console process), PHP opcache.
