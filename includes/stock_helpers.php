<?php
/**
 * Stock alert helper (R-APP-27).
 * Returns one of: out | critical | low | ok
 * Thresholds: 0 = out, 1-5 = critical, 6-threshold = low
 */

require_once __DIR__ . '/db.php';

function stock_alert_level(float $available, float $threshold = 10.0): string
{
    if ($available <= 0)   return 'out';
    if ($available <= 5)   return 'critical';
    if ($available <= $threshold) return 'low';
    return 'ok';
}

function stock_alert_badge_html(float $available, float $threshold = 10.0): string
{
    $level = stock_alert_level($available, $threshold);
    if ($level === 'ok') return '';

    $intAvail = (int) floor($available);
    $label = match($level) {
        'out'      => 'Out of stock',
        'critical' => "Only $intAvail left!",
        'low'      => "Low stock · $intAvail",
    };

    return '<span class="stock-badge stock-badge-' . $level . '">'
         . htmlspecialchars($label) . '</span>';
}
