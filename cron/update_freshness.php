<?php
/**
 * Freshness Cron — Run every 5-15 minutes
 *
 * Recalculates freshness for all active stock batches and:
 *   - Marks batches as EXPIRED when shelf life is up
 *   - Auto-discounts LAST_CHANCE batches by 15%
 *   - Notifies retailers when batches expire
 *
 * Usage (Windows Task Scheduler):
 *   Program:    C:\php\php.exe
 *   Arguments:  C:\path\to\freshmart\cron\update_freshness.php
 *   Trigger:    Repeat every 5 minutes, indefinitely
 *
 * Usage (Linux cron):
 *   /usr/bin/php /path/to/freshmart/cron/update_freshness.php
 */

// Restrict to CLI only — refuse if accessed via browser
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

require_once __DIR__ . '/../includes/freshness.php';

$startedAt = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Running freshness automation...\n";

try {
    $summary = freshness_run_automation();
    $elapsed = round((microtime(true) - $startedAt) * 1000, 1);

    echo "  Scanned:       {$summary['scanned']} batches\n";
    echo "  Expired:       {$summary['expired']} batches\n";
    echo "  Discounted:    {$summary['discounted']} batches\n";
    echo "  Alerts sent:   {$summary['alerts']} notifications\n";
    echo "  Elapsed:       {$elapsed}ms\n";
    echo "[" . date('Y-m-d H:i:s') . "] Done.\n\n";
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
