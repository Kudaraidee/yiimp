-- aurum was removed from stratum (x86-only SSE2 code; Bitnet, its only coin, is inactive).
-- Hide it instead of deleting, so existing coin rows keep a valid algo reference.
UPDATE `algos` SET `visible` = 0 WHERE `name` = 'aurum';
