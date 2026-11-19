-- yii2-queue DB driver table (required for the Yii2 background job system).
-- Run once with a privileged account before starting the container.
-- These five migrations represent the full schema as of yii2-queue 2.3.x.

CREATE TABLE IF NOT EXISTS `queue` (
  `id`          INT(11)          NOT NULL AUTO_INCREMENT,
  `channel`     VARCHAR(255)     NOT NULL,
  `job`         LONGBLOB         NOT NULL,
  `pushed_at`   INT(11)          NOT NULL,
  `ttr`         INT(11)          NOT NULL,
  `delay`       INT(11)          NOT NULL DEFAULT 0,
  `priority`    INT(10) UNSIGNED NOT NULL DEFAULT 1024,
  `reserved_at` INT(11)          DEFAULT NULL,
  `attempt`     INT(11)          DEFAULT NULL,
  `done_at`     INT(11)          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `channel`     (`channel`),
  KEY `reserved_at` (`reserved_at`),
  KEY `priority`    (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mark all five yii2-queue internal migrations as applied so that
-- running "php yii migrate" does not try to re-run them.
INSERT IGNORE INTO `migration` (`version`, `apply_time`) VALUES
  ('M161119140200Queue',       UNIX_TIMESTAMP()),
  ('M170307170300Later',       UNIX_TIMESTAMP()),
  ('M170509001400Retry',       UNIX_TIMESTAMP()),
  ('M170601155600Priority',    UNIX_TIMESTAMP()),
  ('M211218163000JobQueueSize', UNIX_TIMESTAMP());
