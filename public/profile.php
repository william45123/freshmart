<?php
/**
 * Customer profile + saved addresses (R-APP-31).
 * Max 5 addresses per user.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

require_login();
$userId = auth_id();
$user   = auth_user();
$errors = [];

const MAX_ADDRESSES = 5;

if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {
        $action = (string) input('action', '');

        if ($action === 'create' || $action === 'update') {
            $aid = (int) input('address_id', 0);
            $data = [
                'label'          => trim((string) input('label', '')),
                'recipient_name' => trim((string) input('recipient_name', '')),
                'phone'          => trim((string) input('phone', '')),
                'line1'          => trim((string) input('line1', '')),
                'line2'          => trim((string) input('line2', '')),
                'city'           => trim((string) input('city', '')),
                'state'          => trim((string) input('state', '')),
                'postcode'       => trim((string) input('postcode', '')),
                'is_default'     => input('is_default') ? 1 : 0,
            ];

            foreach (['label','recipient_name','phone','line1','city','state','postcode'] as $f) {
                if ($data[$f] === '') $errors[] = ucfirst(str_replace('_',' ',$f)) . ' is required.';
            }
            if (!preg_match('/^\d{5}$/', $data['postcode'])) {
                $errors[] = 'Postcode must be 5 digits.';
            }

            // Limit check
            if (empty($errors) && $action === 'create') {
                $count = (int) db_scalar('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$userId]);
                if ($count >= MAX_ADDRESSES) {
                    $errors[] = "You can save up to " . MAX_ADDRESSES . " addresses.";
                }
            }

            if (empty($errors)) {
                db_transaction(function () use ($action, $aid, $userId, $data) {
                    if ($data['is_default']) {
                        db_run('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
                    }
                    if ($action === 'create') {
                        db_run(
                            "INSERT INTO addresses
                                (user_id, label, recipient_name, phone, line1, line2, city, state, postcode, is_default)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [$userId, $data['label'], $data['recipient_name'], $data['phone'],
                             $data['line1'], $data['line2'], $data['city'], $data['state'],
                             $data['postcode'], $data['is_default']]
                        );
                    } else {
                        db_run(
                            "UPDATE addresses SET
                                label=?, recipient_name=?, phone=?, line1=?, line2=?,
                                city=?, state=?, postcode=?, is_default=?
                             WHERE id=? AND user_id=?",
                            [$data['label'], $data['recipient_name'], $data['phone'],
                             $data['line1'], $data['line2'], $data['city'], $data['state'],
                             $data['postcode'], $data['is_default'], $aid, $userId]
                        );
                    }
                });
                flash_set('success', $action === 'create' ? 'Address added.' : 'Address updated.');
                redirect('/profile.php');
            }
        } elseif ($action === 'delete') {
            $aid = (int) input('address_id');
            db_run('DELETE FROM addresses WHERE id = ? AND user_id = ?', [$aid, $userId]);
            flash_set('info', 'Address removed.');
            redirect('/profile.php');
        } elseif ($action === 'set_default') {
            $aid = (int) input('address_id');
            db_transaction(function () use ($aid, $userId) {
                db_run('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
                db_run('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?', [$aid, $userId]);
            });
            flash_set('success', 'Default address set.');
            redirect('/profile.php');
        }
    }
}

$addresses = db_all(
    'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
    [$userId]
);

// Address being edited?
$editId = (int) input('edit', 0);
$edit = $editId > 0
    ? db_one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$editId, $userId])
    : null;

// Personal sustainability impact — how much this customer rescued from waste
$myKgRescued = (float) db_scalar(
    "SELECT COALESCE(SUM(oi.quantity), 0)
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.user_id = ? AND oi.freshness_at_order = 'LAST_CHANCE'",
    [$userId]
);

$pageTitle = 'My Profile — FreshMart';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="container u-page-head u-maxw-800">

    <h1>My profile</h1>

    <?php if ($myKgRescued > 0): ?>
        <div class="impact-callout u-mt-4">
            <div class="impact-figure"><?= number_format($myKgRescued, 1) ?> kg</div>
            <div class="impact-text">
                of food <strong>you&rsquo;ve rescued from waste</strong> by choosing Last Chance items. 🌱
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="panel u-p-5 u-mb-6">
        <h3 class="u-mt-0">Account</h3>
        <p class="u-m-0 u-muted">
            <strong class="u-ink"><?= e($user['full_name'] ?? '') ?></strong><br>
            <?= e($user['email']) ?><br>
            <?php if (!empty($user['phone'])): ?><?= e($user['phone']) ?><br><?php endif; ?>
            Member since <?= format_date($user['created_at']) ?>
        </p>
    </div>

    <div class="u-flex u-jc-between u-ai-baseline u-mb-3">
        <h2 class="u-m-0 u-t-20">Saved addresses</h2>
        <span class="u-muted u-t-14">
            <?= count($addresses) ?> / <?= MAX_ADDRESSES ?>
        </span>
    </div>

    <?php if (empty($addresses)): ?>
        <div class="empty-state u-p-6">
            📍 No saved addresses. Add one below.
        </div>
    <?php else: ?>
        <div class="u-flex u-col u-gap-3 u-mb-6">
            <?php foreach ($addresses as $a): ?>
                <div class="address-card u-p-4<?= $a['is_default'] ? ' is-default' : '' ?>">
                    <div class="u-flex u-jc-between u-ai-start">
                        <div>
                            <strong><?= e($a['label']) ?></strong>
                            <?php if ($a['is_default']): ?>
                                <span class="u-bg-primary u-fg-white u-t-11 u-p-pill-xs u-r-sm u-ml-1">DEFAULT</span>
                            <?php endif; ?>
                            <div class="u-mt-1 u-muted u-t-15">
                                <?= e($a['recipient_name']) ?> · <?= e($a['phone']) ?><br>
                                <?= e($a['line1']) ?><?= !empty($a['line2']) ? ', ' . e($a['line2']) : '' ?><br>
                                <?= e($a['city']) ?>, <?= e($a['state']) ?> <?= e($a['postcode']) ?>
                            </div>
                        </div>
                        <div class="u-flex u-gap-2">
                            <a href="<?= url('/profile.php?edit=' . $a['id']) ?>" class="btn btn-ghost btn-sm">Edit</a>
                            <?php if (!$a['is_default']): ?>
                                <form method="post" class="u-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="set_default">
                                    <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit">Set default</button>
                                </form>
                                <form method="post" class="u-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
                                    <button class="btn btn-ghost btn-sm u-fg-danger" type="submit"
                                           
                                            onclick="return confirm('Delete address?')">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($edit || count($addresses) < MAX_ADDRESSES): ?>
        <h3 class="u-t-17"><?= $edit ? 'Edit address' : '+ Add new address' ?></h3>
        <form method="post" class="panel u-p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?>
                <input type="hidden" name="address_id" value="<?= $edit['id'] ?>">
            <?php endif; ?>

            <div class="u-grid u-cols-2 u-gap-3">
                <div class="form-group">
                    <label>Label *</label>
                    <input type="text" name="label" required class="form-control"
                           value="<?= attr($edit['label'] ?? '') ?>" placeholder="Home, Office, etc.">
                </div>
                <div class="form-group">
                    <label>Recipient name *</label>
                    <input type="text" name="recipient_name" required class="form-control"
                           value="<?= attr($edit['recipient_name'] ?? $user['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="tel" name="phone" required class="form-control"
                           value="<?= attr($edit['phone'] ?? $user['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Postcode *</label>
                    <input type="text" name="postcode" required class="form-control"
                           pattern="\d{5}" maxlength="5"
                           value="<?= attr($edit['postcode'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address line 1 *</label>
                <input type="text" name="line1" required class="form-control"
                       value="<?= attr($edit['line1'] ?? '') ?>"
                       placeholder="No. 12, Persiaran Cyberia">
            </div>
            <div class="form-group">
                <label>Address line 2</label>
                <input type="text" name="line2" class="form-control"
                       value="<?= attr($edit['line2'] ?? '') ?>" placeholder="Optional">
            </div>
            <div class="u-grid u-cols-2 u-gap-3">
                <div class="form-group">
                    <label>City *</label>
                    <input type="text" name="city" required class="form-control"
                           value="<?= attr($edit['city'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>State *</label>
                    <input type="text" name="state" required class="form-control"
                           value="<?= attr($edit['state'] ?? '') ?>">
                </div>
            </div>
            <label class="u-flex u-ai-center u-gap-2 u-mt-3 u-pointer">
                <input type="checkbox" name="is_default" value="1"
                       <?= !empty($edit['is_default']) ? 'checked' : '' ?>>
                Set as default address
            </label>
            <div class="u-flex u-gap-2 u-mt-4">
                <button type="submit" class="btn btn-primary"><?= $edit ? 'Save' : 'Add address' ?></button>
                <?php if ($edit): ?>
                    <a href="<?= url('/profile.php') ?>" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
