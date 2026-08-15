-- ============================================================
-- Phase 3 / C3 — freshness scale colours
-- ------------------------------------------------------------
-- Moves the four admin-editable freshness colours from the v4
-- earthy palette to the new cold-chain scale.
--
-- These are DATA, not CSS. freshness_config.color_hex is edited by
-- the colour picker in admin/settings.php and injected at render time
-- as style="--fresh: <hex>". Nothing hard-codes the scale in the
-- stylesheet, so changing a value here changes the site immediately
-- and the picker keeps working.
--
-- Run in phpMyAdmin:
--   1. select the `freshmart` database
--   2. Import (or SQL tab) -> this file -> Go
--
-- Idempotent: re-running sets the same values again. Safe to repeat.
--
-- The EXPIRED level has no row here — it is not admin-editable and
-- its colour is the fallback constant in includes/freshness.php,
-- changed from #dc2626 to #B3341F in the same commit as this file.
-- ============================================================

UPDATE freshness_config SET color_hex = '#1FB574' WHERE level_name = 'VERY_FRESH';
UPDATE freshness_config SET color_hex = '#8CC63F' WHERE level_name = 'FRESH';
UPDATE freshness_config SET color_hex = '#F0A81E' WHERE level_name = 'ENJOY_SOON';
UPDATE freshness_config SET color_hex = '#F0522D' WHERE level_name = 'LAST_CHANCE';


-- ------------------------------------------------------------
-- Verification — expect exactly these four rows
-- ------------------------------------------------------------
-- SELECT level_name, color_hex FROM freshness_config
--  ORDER BY FIELD(level_name,'VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE');
--
--   VERY_FRESH   #1FB574
--   FRESH        #8CC63F
--   ENJOY_SOON   #F0A81E
--   LAST_CHANCE  #F0522D
--
-- Then load /help/freshness.php and confirm the four badge dots
-- render in the new colours. If they do not, the page is reading a
-- hard-coded scale somewhere instead of --fresh, which is the bug
-- C3 exists to prevent.


-- ------------------------------------------------------------
-- ROLLBACK — the v4 earthy scale
-- ------------------------------------------------------------
-- UPDATE freshness_config SET color_hex = '#4a5a3a' WHERE level_name = 'VERY_FRESH';
-- UPDATE freshness_config SET color_hex = '#7a8467' WHERE level_name = 'FRESH';
-- UPDATE freshness_config SET color_hex = '#c9a55a' WHERE level_name = 'ENJOY_SOON';
-- UPDATE freshness_config SET color_hex = '#b85c38' WHERE level_name = 'LAST_CHANCE';
