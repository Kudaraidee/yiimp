-- Recalculate stored block effort using the same share window as reward
-- distribution (shares marked with blockrewarded = this block's height).
--
-- The previous calculation looked up the previous block by height/time, but
-- stratum inserts the current block row with solo=0 before the backend runs.
-- That made the lookup return the current block itself, so effort was computed
-- from shares mined *after* the block (near 0%). On blocks without a prior
-- candidate it fell back to time>=0 and accumulated every share ever seen
-- (hundreds of percent).

UPDATE blocks b
JOIN (
    SELECT s.coinid, s.blockrewarded AS height, SUM(s.difficulty) AS total_difficulty
    FROM shares s
    WHERE s.valid = 1 AND s.blockrewarded IS NOT NULL
    GROUP BY s.coinid, s.blockrewarded
) x ON x.coinid = b.coin_id AND x.height = b.height
SET b.effort = ROUND(x.total_difficulty * 100 / b.difficulty, 2)
WHERE b.difficulty > 0;
