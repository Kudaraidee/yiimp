-- Phase 3A: direct USDT payouts through SafeTrade (BEP20 by default).
-- Disabled until ExchangeController sets safetrade-usdt_payout_enabled=1 and
-- EXCH_SAFETRADE_KEY / EXCH_SAFETRADE_SECRET are configured in runtime config.
INSERT IGNORE INTO `settings` (`param`, `value`) VALUES
    ('safetrade-usdt_payout_enabled', '0'),
    ('safetrade-withdraw_min_usdt', '3'),
    ('safetrade-withdraw_fee_usdt', '1'),
    ('safetrade-withdraw_usdt_blockchain_key', 'bsc-tokens');
