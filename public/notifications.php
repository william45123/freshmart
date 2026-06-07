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

<section class="container" style="padding: var(--space-6) 0 var(--space-12); max-width: 720px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
        <h1 style="margin: 0;">Notifications</h1>
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
        <div style="display: flex; flex-direction: column; gap: var(--space-2);">
            <?php foreach ($notifs as $n): ?>
                <div style="background: <?= $n['is_read'] ? 'var(--color-surface)' : 'var(--color-primary-light)' ?>;
                            border: 1px solid var(--color-border); border-radius: var(--radius-lg);
                            padding: var(--space-4);">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: var(--space-3);">
                        <div style="flex: 1;">
                            <div style="display: flex; gap: var(--space-2); align-items: center; margin-bottom: var(--space-1);">
                                <?php if (!$n['is_read']): ?>
                                    <span style="width: 8px; height: 8px; background: var(--color-primary); border-radius: 50%; display: inline-block;"></span>
                                <?php endif; ?>
                                <strong><?= e($n['title']) ?></strong>
                            </div>
                            <p style="margin: 0 0 var(--space-2); color: var(--color-text-muted);">
                                <?= e($n['body']) ?>
                            </p>
                            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
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
