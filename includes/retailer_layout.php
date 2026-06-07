<?php
/**
 * Retailer area shared layout.
 *
 * The role-based <header> already provides nav. This layout now just wraps
 * the page content in a centered container with a clean page header.
 */

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function retailer_current(): array
{
    require_role(['RETAILER']);

    $row = db_one(
        'SELECT r.*, u.email, p.full_name
         FROM retailers r
         JOIN users u    ON u.id = r.user_id
         LEFT JOIN profiles p ON p.user_id = r.user_id
         WHERE r.user_id = ?',
        [auth_id()]
    );

    if (!$row) {
        http_response_code(403);
        die('Retailer record not found.');
    }
    if ($row['approval_status'] !== 'APPROVED') {
        http_response_code(403);
        die('Your retailer account is not yet approved.');
    }

    return $row;
}

function retailer_layout_start(string $activeKey, string $pageTitle, ?string $action = null): void
{
    retailer_current();  // Verify approved retailer
    ?>
    <div class="page-wrap">
        <div class="page-header">
            <h1><?= e($pageTitle) ?></h1>
            <?php if ($action): ?>
                <div class="page-header-action"><?= $action ?></div>
            <?php endif; ?>
        </div>
    <?php
}

function retailer_layout_end(): void
{
    echo '</div>';
}
