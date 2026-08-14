-- ============================================================
-- F1 — Materialise freshness onto stock_batches
-- ------------------------------------------------------------
-- Adds a derived cache of the freshness calculation so browse can
-- filter and sort in SQL instead of in PHP after LIMIT/OFFSET
-- (which is what makes ?freshness=LAST_CHANCE return only the
-- matches that happen to land on the current page).
--
-- These three columns hold NO source data. They are written by
-- freshness_sync_batches() from the existing PHP formula and can be
-- rebuilt at any time by running cron/update_freshness.php. Dropping
-- them loses nothing.
--
-- Run in phpMyAdmin:
--   1. select the `freshmart` database
--   2. SQL tab -> paste this file -> Go
--   3. then run:  php cron/update_freshness.php
--
-- Safe to run once. Re-running errors with "Duplicate column name",
-- which is harmless — it means the migration is already applied.
-- ============================================================

ALTER TABLE stock_batches
  ADD COLUMN freshness_pct       DECIMAL(5,2) NULL AFTER status,
  ADD COLUMN freshness_level     ENUM('VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE','EXPIRED') NULL AFTER freshness_pct,
  ADD COLUMN freshness_synced_at TIMESTAMP NULL AFTER freshness_level,
  ADD INDEX idx_freshness (freshness_level, expiry_date);


-- ------------------------------------------------------------
-- Verification — run after cron/update_freshness.php
-- ------------------------------------------------------------

-- 1. Columns and index exist. Expect 3 rows, then 2 rows
--    (idx_freshness spans two columns, so it lists once per column).
-- SHOW COLUMNS FROM stock_batches LIKE 'freshness%';
-- SHOW INDEX FROM stock_batches WHERE Key_name = 'idx_freshness';

-- 2. No ACTIVE batch left unsynced. Expect 0.
-- SELECT COUNT(*) AS unsynced
--   FROM stock_batches
--  WHERE status = 'ACTIVE' AND freshness_level IS NULL;

-- 3. Cache agrees with the level boundaries in freshness_config.
--    Expect 0 rows; any row here means the cache is stale or wrong.
-- SELECT sb.id, sb.freshness_pct, sb.freshness_level
--   FROM stock_batches sb
--   JOIN freshness_config fc ON fc.level_name = sb.freshness_level
--  WHERE sb.status = 'ACTIVE'
--    AND sb.freshness_pct NOT BETWEEN fc.min_percent AND fc.max_percent;

-- 4. Distribution, as a sanity read.
-- SELECT freshness_level, COUNT(*) AS batches
--   FROM stock_batches
--  WHERE status = 'ACTIVE'
--  GROUP BY freshness_level
--  ORDER BY FIELD(freshness_level,
--                 'VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE','EXPIRED');
