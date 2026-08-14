-- ============================================================
-- F1 ROLLBACK — remove the freshness cache from stock_batches
-- ------------------------------------------------------------
-- No data loss: these three columns are a derived cache of the
-- freshness formula, not source data. Re-applying
-- f1_freshness_cache.sql and running cron/update_freshness.php
-- rebuilds them exactly.
--
-- ORDER MATTERS. The index must go first — MariaDB will not drop a
-- column that an index still references.
--
-- BEFORE YOU RUN THIS: if F2 is already deployed, revert the F2 code
-- commit as well. F2's browse query reads freshness_level directly,
-- and will fatal on "Unknown column" once these are gone. F1 alone
-- rolls back cleanly; F1 with F2 still live does not.
-- ============================================================

ALTER TABLE stock_batches DROP INDEX idx_freshness;

ALTER TABLE stock_batches DROP COLUMN freshness_synced_at;
ALTER TABLE stock_batches DROP COLUMN freshness_level;
ALTER TABLE stock_batches DROP COLUMN freshness_pct;


-- ------------------------------------------------------------
-- Verification — expect 0 rows from each
-- ------------------------------------------------------------
-- SHOW COLUMNS FROM stock_batches LIKE 'freshness%';
-- SHOW INDEX FROM stock_batches WHERE Key_name = 'idx_freshness';
