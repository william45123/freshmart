<?php
/**
 * Register page — supports Customer & Retailer signup.
 * Retailer accounts go to PENDING status pending admin approval.
 */

require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/helpers.php';

auth_init();
if (auth_check()) redirect('/');

$errors = [];
$old    = ['email' => '', 'full_name' => '', 'phone' => '',
           'company_name' => '', 'business_reg_no' => '', 'business_address' => ''];
$preselect = input('as') === 'retailer' ? 'RETAILER' : 'CUSTOMER';

if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'Security token mismatch. Please try again.';
    } else {
        foreach ($old as $k => $_) {
            $old[$k] = trim((string) input($k, ''));
        }
        $email     = $old['email'];
        $password  = (string) input('password', '');
        $confirm   = (string) input('password_confirm', '');
        $fullName  = $old['full_name'];
        $phone     = $old['phone'];
        $role      = input('role') === 'RETAILER' ? 'RETAILER' : 'CUSTOMER';
        $preselect = $role;

        if ($email === '')                 $errors[] = 'Email is required.';
        if ($fullName === '')               $errors[] = 'Full name is required.';
        if ($password === '')                $errors[] = 'Password is required.';
        if ($password !== $confirm)          $errors[] = 'Passwords do not match.';

        if ($role === 'RETAILER') {
            if ($old['company_name'] === '')    $errors[] = 'Company name is required.';
            if ($old['business_reg_no'] === '') $errors[] = 'SSM business registration number is required.';
        }

        if (empty($errors)) {
            try {
                $userId = auth_register(
                    $email, $password, $fullName, $role, $phone,
                    [
                        'company_name'     => $old['company_name'],
                        'business_reg_no'  => $old['business_reg_no'],
                        'business_address' => $old['business_address'],
                        'contact_phone'    => $phone,
                    ]
                );

                if ($role === 'RETAILER') {
                    flash_set('info',
                        'Retailer account created. Your application is pending admin approval — '
                        . 'you will be able to log in once approved.');
                    redirect('/auth/login.php');
                }

                // Customers auto-login
                auth_attempt($email, $password);
                flash_set('success', 'Welcome to FreshMart, ' . $fullName . '!');
                redirect('/');
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Sign up — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Create your account</h1>
        <p class="subtitle">Join FreshMart — fresh produce, transparently.</p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= url('/auth/register.php') ?>" novalidate id="registerForm">
            <?= csrf_field() ?>

            <div class="role-toggle">
                <input type="radio" id="role-customer" name="role" value="CUSTOMER"
                    <?= $preselect === 'CUSTOMER' ? 'checked' : '' ?>>
                <label for="role-customer"><?= icon('cart', 16) ?> Customer</label>
                <input type="radio" id="role-retailer" name="role" value="RETAILER"
                    <?= $preselect === 'RETAILER' ? 'checked' : '' ?>>
                <label for="role-retailer"><?= icon('leaf', 16) ?> Retailer</label>
            </div>

            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required
                       class="form-control" value="<?= attr($old['full_name']) ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       class="form-control" value="<?= attr($old['email']) ?>">
            </div>

            <div class="form-group">
                <label for="phone">Phone (optional)</label>
                <input type="tel" id="phone" name="phone" placeholder="+60 12-345 6789"
                       class="form-control" value="<?= attr($old['phone']) ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       class="form-control" minlength="8">
                <div class="form-help">At least 8 characters.</div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" required
                       class="form-control" minlength="8">
            </div>

            <div class="retailer-fields<?= $preselect === 'RETAILER' ? ' is-open' : '' ?>" id="retailer-fields">
                <h3 class="u-t-16 u-m-4-0-3">
                    Business details
                </h3>
                <div class="form-group">
                    <label for="company_name">Company name</label>
                    <input type="text" id="company_name" name="company_name"
                           class="form-control" value="<?= attr($old['company_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="business_reg_no">SSM registration number</label>
                    <input type="text" id="business_reg_no" name="business_reg_no" placeholder="202301012345"
                           class="form-control" value="<?= attr($old['business_reg_no']) ?>">
                </div>
                <div class="form-group">
                    <label for="business_address">Business address</label>
                    <textarea id="business_address" name="business_address" rows="2"
                              class="form-control"><?= e($old['business_address']) ?></textarea>
                </div>
                <p class="form-help">⏳ Retailer accounts require admin approval before you can log in.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">Create account</button>
            </div>
        </form>

        <div class="auth-divider">
            Already have an account? <a href="<?= url('/auth/login.php') ?>">Log in</a>
        </div>
    </div>
</div>

<script>
    // Toggle retailer fields visibility
    const fields = document.getElementById('retailer-fields');
    const cust   = document.getElementById('role-customer');
    const ret    = document.getElementById('role-retailer');
    function toggle() { fields.classList.toggle('is-open', ret.checked); }
    cust.addEventListener('change', toggle);
    ret.addEventListener('change', toggle);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
