<?php
/**
 * Login page.
 */

require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/helpers.php';

auth_init();

// Already logged in? Redirect by role.
if (auth_check()) {
    $role = auth_role();
    if ($role === 'ADMIN')        redirect('/admin/dashboard.php');
    if ($role === 'RETAILER')     redirect('/retailer/dashboard.php');
    redirect('/');
}

$errors = [];
$email  = '';

if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        $email    = trim((string) input('email', ''));
        $password = (string) input('password', '');

        if ($email === '' || $password === '') {
            $errors[] = 'Both email and password are required.';
        } else {
            $user = auth_attempt($email, $password);
            if ($user === null) {
                $errors[] = 'Invalid credentials, or your account is not active yet.';
            } else {
                $intended = $_SESSION['intended_url'] ?? null;
                unset($_SESSION['intended_url']);
                flash_set('success', 'Welcome back, ' . ($user['full_name'] ?? $user['email']) . '!');
                if ($intended) {
                    redirect($intended);
                }
                $role = $user['role'];
                if ($role === 'ADMIN')    redirect('/admin/dashboard.php');
                if ($role === 'RETAILER') redirect('/retailer/dashboard.php');
                redirect('/');
            }
        }
    }
}

$pageTitle = 'Log in — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Welcome back <?= icon('user', 16) ?></h1>
        <p class="subtitle">Log in to continue shopping fresh produce.</p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= url('/auth/login.php') ?>" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       class="form-control" value="<?= attr($email) ?>" autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="form-control">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">Log in</button>
            </div>
        </form>

        <div class="auth-divider">
            New here? <a href="<?= url('/auth/register.php') ?>">Create an account</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
