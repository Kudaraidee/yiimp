-- Allow small NestEx LMT sell orders during USDT autoexchange phase 2.
-- NestEx pairs are USDT-quoted, so the threshold is in USDT, not BTC.
INSERT INTO `settings` (`param`, `value`)
VALUES ('nestex-trade_min_usdt', '0.001')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
