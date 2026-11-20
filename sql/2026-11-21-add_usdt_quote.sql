-- Autoexchange step 1-2: configure USDT as the payout quote and seed an
-- inert quote row. The row is not enabled for mining or payments until a
-- USDT payout rail (exchange withdrawal or hosted RPC + signer) is chosen.
INSERT IGNORE INTO settings (param, value, type) VALUES ('payout_quote', 'USDT', 'string');
UPDATE settings SET value = 'USDT', type = 'string' WHERE param = 'payout_quote';

INSERT INTO coins (name, symbol, algo, price, enable, visible, auto_exchange, installed, txfee)
SELECT 'Tether USD (quote)', 'USDT', 'token',
       (SELECT CAST(value AS DECIMAL(30,18)) FROM settings WHERE param = 'usdt_btc'),
       0, 0, 1, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM coins WHERE symbol = 'USDT');

UPDATE coins c
JOIN settings s ON s.param = 'usdt_btc'
SET c.price = CAST(s.value AS DECIMAL(30,18))
WHERE c.symbol = 'USDT' AND c.auto_exchange = 1;
