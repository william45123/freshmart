<?php
/**
 * Notifications page (and mark-as-read action).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

require_login();
$userId = auth_id();

if (is_post() && csrf_verify()) {
    if (input('action') === 'mark_read') {
        $id = (int) input('notif_id', 0);
        if ($id > 0) {
            db_run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?',
                   [$id, $userId]);
        }
    } elseif (input('action') === 'mark_all_read') {
        db_run('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
               [$userId]);
    }
    redirect('/notifications.php');
}

$notifs = db_all(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50',
    [$userId]
);

$unreadCount = 0;
foreach ($notifs as $n) if (!$n['is_read']) $unreadCount++;

$pageTitle = 'Notifications — FreshMart';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="container u-page-head u-maxw-720">
    <div class="u-flex u-jc-between u-ai-center u-mb-4">
        <h1 class="u-m-0">Notifications</h1>
        <?php if ($unreadCount > 0): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-ghost btn-sm">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifs)): ?>
        <div class="empty-state">📭 No notifications yet.</div>
    <?php else: ?>
        <div class="u-flex u-col u-gap-2">
            <?php foreach ($notifs as $n): ?>
                <div class="notice-card<?= $n['is_read'] ? '' : ' is-unread' ?>"
                            padding: var(--space-4);">
                    <div class="u-flex u-jc-between u-ai-start u-gap-3">
                        <div class="u-flex-1">
                            <div class="u-flex u-gap-2 u-ai-center u-mb-1">
                                <?php if (!$n['is_read']): ?>
                                    <span class="u-w-8 u-h-8 u-bg-primary u-r-circle u-inline-block"></span>
                                <?php endif; ?>
                                <strong><?= e($n['title']) ?></strong>
                            </div>
                            <p class="u-m-0-0-2 u-muted">
                                <?= e($n['body']) ?>
                            </p>
                            <div class="u-t-13 u-muted">
                                <?= format_datetime($n['created_at']) ?>
                                <?php if (!empty($n['link'])): ?>
                                    · <a href="<?= url($n['link']) ?>">View</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$n['is_read']): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">Mark read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
