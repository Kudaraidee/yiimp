-- Phase 3B: aggregate miner USDT quote wallets into one SafeTrade withdrawal.
-- The pool pays a single BEP20 beneficiary; operators distribute externally.
INSERT IGNORE INTO `settings` (`param`, `value`) VALUES
    ('safetrade-withdraw_usdt_address', '0xd12449e7cd89f0ffab34c4fae471bcabd9e3383d'),
    ('safetrade-autosell_symbols', 'NEB');
