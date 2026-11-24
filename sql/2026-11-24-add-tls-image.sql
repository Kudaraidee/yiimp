-- Use the canonical TLS coin icon shipped at /images/coin-TLS.png.
-- The symbol-path resolver would find it even with image=NULL, but storing the
-- path keeps legacy views, admin pages and exported coin rows consistent.
UPDATE `coins`
SET `image` = '/images/coin-TLS.png'
WHERE `symbol` = 'TLS' AND (`image` IS NULL OR `image` = '');

-- After applying, expire yaamp_coin_list and api_status (raw + md5 keys).
