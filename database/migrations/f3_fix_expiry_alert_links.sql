-- ============================================================
-- F3 — repoint existing expiry-alert notifications
-- ------------------------------------------------------------
-- freshness.php linked expiry alerts to /retailer/batches.php?id=,
-- a file that does not exist. The code now writes
-- /retailer/inventory.php?batch=, but notifications already in the
-- table still carry the dead URL, so every historical alert stays
-- broken until they are repointed.
--
-- Idempotent: rows already using the new path do not match.
--
-- Run in phpMyAdmin: select `freshmart` -> SQL -> paste -> Go
-- ============================================================

UPDATE notifications
   SET link = REPLACE(link, '/retailer/batches.php?id=', '/retailer/inventory.php?batch=')
 WHERE link LIKE '/retailer/batches.php?id=%';

-- Verify — expect 0
-- SELECT COUNT(*) AS dead_links FROM notifications
--  WHERE link LIKE '/retailer/batches.php%';
