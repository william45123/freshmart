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
    [$bg, $color, $label] = match($level) {
        'out'      => ['#fee2e2', '#7f1d1d', 'Out of stock'],
        'critical' => ['#ffedd5', '#7c2d12', "Only $intAvail left!"],
        'low'      => ['#fef3c7', '#78350f', "Low stock · $intAvail"],
    };

    return '<span style="display:inline-block; background:' . $bg . '; color:' . $color
         . '; padding:2px 8px; border-radius:999px; font-size:0.6875rem; font-weight:600; letter-spacing:0.02em;">'
         . htmlspecialchars($label) . '</span>';
}
