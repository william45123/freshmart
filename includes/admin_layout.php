<?php
/**
 * Admin area shared layout.
 *
 * The role-based <header> already provides nav. This layout now just wraps
 * the page content in a centered container with a clean page header.
 */

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

function admin_check(): void
{
    require_role(['ADMIN']);
}

function admin_layout_start(string $activeKey, string $pageTitle, ?string $action = null): void
{
    admin_check();
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

function admin_layout_end(): void
{
    echo '</div>';
}
