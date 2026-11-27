-- Use the canonical LAX coin mark from https://safetrade.com/logos/lax.svg.
-- The symbol-path resolver would find /images/coin-LAX.png even with image=NULL,
-- but storing the path keeps legacy views, admin pages and exported coin rows
-- consistent.
UPDATE `coins`
SET `image` = '/images/coin-LAX.png'
WHERE `symbol` = 'LAX' AND (`image` IS NULL OR `image` = '');

-- After applying, expire yaamp_coin_list and api_status (raw + md5 keys).
