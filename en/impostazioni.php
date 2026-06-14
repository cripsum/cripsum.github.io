<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

if (!isLoggedIn()) {
    header('Location: accedi');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$success = '';
$error = '';
$backupCodesNew = [];

$currentUser = auth_get_user_by_id($mysqli, $userId);

if (!$currentUser) {
    session_destroy();
    header('Location: accedi');
    exit();
}

$username = (string)($currentUser['username'] ?? ($_SESSION['username'] ?? ''));
$email = (string)($currentUser['email'] ?? ($_SESSION['email'] ?? ''));
$nsfw = (int)($currentUser['nsfw'] ?? ($_SESSION['nsfw'] ?? 0));
$richpresence = (int)($currentUser['richpresence'] ?? ($_SESSION['richpresence'] ?? 0));
$twofaStatus = auth_twofa_status($mysqli, $userId);
$twofaSetupSecret = $_SESSION['twofa_setup_secret'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } elseif ($action === 'update_profile') {
        $newUsername = strtolower(trim($_POST['username'] ?? ''));
        $newNsfw = isset($_POST['nsfw']) ? 1 : 0;
        $newRichpresence = isset($_POST['richpresence']) ? 1 : 0;

        if ($newUsername === '') {
            $error = 'Username is required.';
        } elseif (!auth_is_valid_username($newUsername)) {
            $error = 'Invalid username.';
        } else {
            $result = updateUserSettings($mysqli, $userId, $newUsername, $email, '', $newNsfw, $newRichpresence);

            if ($result === true) {
                $success = 'Profile updated successfully.';
                $username = $newUsername;
                $nsfw = $newNsfw;
                $richpresence = $newRichpresence;
            } else {
                $error = is_string($result) ? $result : 'Error saving settings.';
            }
        }
    } elseif ($action === 'update_email') {
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $hasPassword = !empty($currentUser['password']);

        if ($newEmail === '') {
            $error = 'Email is required.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } elseif (strcasecmp($newEmail, $email) === 0) {
            $error = 'The new email is the same as the current email.';
        } elseif ($hasPassword && !auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Invalid current password.';
        } else {
            $result = updateUserSettings($mysqli, $userId, $username, $newEmail, '', $nsfw, $richpresence);

            if ($result === true) {
                session_destroy();
                session_start();
                $_SESSION['login_message'] = 'Email changed. Check the new inbox to verify your account.';
                header('Location: accedi');
                exit();
            } else {
                $error = is_string($result) ? $result : 'Error saving settings.';
            }
        }
    } elseif ($action === 'update_password') {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $hasPassword = !empty($currentUser['password']);

        if ($newPassword === '') {
            $error = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'The new password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } elseif ($hasPassword && !auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Invalid current password.';
        } else {
            $result = updateUserSettings($mysqli, $userId, $username, $email, $newPassword, $nsfw, $richpresence);

            if ($result === true) {
                $success = 'Password successfully updated.';
            } else {
                $error = is_string($result) ? $result : 'Error saving settings.';
            }
        }
    } elseif ($action === 'start_2fa_setup') {
        if (!$twofaStatus['has_columns']) {
            $error = 'First, run the SQL queries to add 2FA.';
        } elseif ($twofaStatus['enabled']) {
            $error = '2FA is already enabled.';
        } else {
            $_SESSION['twofa_setup_secret'] = totp_generate_secret();
            $twofaSetupSecret = $_SESSION['twofa_setup_secret'];
            $success = 'Scan the QR code and confirm the code.';
        }
    } elseif ($action === 'enable_2fa') {
        $code = trim($_POST['twofa_code'] ?? '');
        $twofaSetupSecret = $_SESSION['twofa_setup_secret'] ?? null;

        if (!$twofaStatus['has_columns']) {
            $error = 'Missing 2FA fields in the database.';
        } elseif (!$twofaSetupSecret) {
            $error = '2FA setup not started.';
        } elseif (!totp_verify($twofaSetupSecret, $code)) {
            $error = 'Invalid code.';
        } else {
            $result = auth_enable_2fa($mysqli, $userId, $twofaSetupSecret);

            if (!empty($result['ok'])) {
                unset($_SESSION['twofa_setup_secret']);
                $twofaSetupSecret = null;
                $twofaStatus = auth_twofa_status($mysqli, $userId);
                $backupCodesNew = $result['backup_codes'] ?? [];
                $success = '2FA enabled. Save the backup codes.';
            } else {
                $error = $result['message'] ?? 'Failed to enable 2FA.';
            }
        }
    } elseif ($action === 'disable_2fa') {
        $currentPassword = $_POST['current_password'] ?? '';
        $code = trim($_POST['twofa_code'] ?? '');

        if (!$twofaStatus['enabled']) {
            $error = '2FA is not enabled.';
        } elseif (!auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Invalid current password.';
        } elseif (!auth_verify_2fa_or_backup($mysqli, $userId, $code)) {
            $error = 'Invalid 2FA code.';
        } elseif (auth_disable_2fa($mysqli, $userId)) {
            unset($_SESSION['twofa_setup_secret']);
            $twofaStatus = auth_twofa_status($mysqli, $userId);
            $success = '2FA disabled.';
        } else {
            $error = 'Failed to disable 2FA.';
        }
    } elseif ($action === 'regenerate_backup_codes') {
        $currentPassword = $_POST['current_password'] ?? '';
        $code = trim($_POST['twofa_code'] ?? '');

        if (!$twofaStatus['enabled']) {
            $error = 'First, enable 2FA.';
        } elseif (!auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Invalid current password.';
        } elseif (!auth_verify_2fa_or_backup($mysqli, $userId, $code)) {
            $error = 'Invalid 2FA code.';
        } else {
            $backupCodesNew = auth_generate_backup_codes(8);
            if (auth_store_backup_codes($mysqli, $userId, $backupCodesNew)) {
                $success = 'Backup codes regenerated. Save them now.';
            } else {
                $error = 'Missing backup codes table or save error.';
            }
        }
    }

    if ($error === '') {
        $currentUser = auth_get_user_by_id($mysqli, $userId);
        $twofaStatus = auth_twofa_status($mysqli, $userId);
    }
}

$profilePic = "/includes/get_pfp.php?id=" . $userId;
$otpauthUri = '';
$qrUrl = '';

if ($twofaSetupSecret) {
    $otpauthUri = totp_otpauth_uri('Cripsum', $username ?: $email, $twofaSetupSecret);
    $qrUrl = totp_qr_url($otpauthUri, 220);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Account settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.1">
    <script src="/assets/auth/auth.js?v=1.1" defer></script>
</head>

<body class="auth-page settings-page">
    <?php include '../includes/navbar.php'; ?>


    <main class="settings-shell">
        <header class="settings-hero auth-reveal">
            <img src="<?php echo auth_h($profilePic); ?>" alt="">
            <div>
                <span class="auth-pill">Account</span>
                <h1>Account settings</h1>
                <p>Manage your profile, preferences, and security.</p>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="auth-alert auth-alert--error">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?php echo auth_h($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert--success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo auth_h($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($currentUser['password'])): ?>
            <div class="auth-alert auth-reveal" style="background: rgba(255, 193, 7, 0.15); border: 1px solid #ffc107; color: #ffc107; padding: 1rem; border-radius: 20px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-key"></i>
                <span style="flex-grow: 1;">Set a password to access without Google.</span>
                <a href="#password" class="auth-btn" style="background: #ffc107; color: #000; width: auto; padding: 5px 15px;" onclick="document.querySelector('.settings-tab-btn[data-tab=\'password\']').click();">Configure</a>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <aside class="settings-sidebar auth-reveal">
                <button class="settings-tab-btn active" data-tab="profile">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </button>
                <button class="settings-tab-btn" data-tab="email">
                    <i class="fas fa-envelope"></i>
                    <span>Email</span>
                </button>
                <button class="settings-tab-btn" data-tab="password">
                    <i class="fas fa-key"></i>
                    <span>Password</span>
                </button>
                <button class="settings-tab-btn" data-tab="twofa">
                    <i class="fas fa-shield-halved"></i>
                    <span>Security 2FA</span>
                </button>
            </aside>

            <div class="settings-main">
                <!-- Tab: Profile -->
                <div class="settings-tab-content active" id="tab-profile">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Profile Information</h2>
                            <p>Manage your public details and viewing preferences.</p>
                        </div>

                        <form method="POST" action="#profile" class="auth-form" data-auth-form>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_profile">

                            <label class="auth-field">
                                <span>Username</span>
                                <input type="text" name="username" value="<?php echo auth_h($username); ?>" required maxlength="20">
                            </label>

                            <div class="settings-checks">
                                <label class="auth-check">
                                    <input type="checkbox" name="nsfw" <?php echo $nsfw ? 'checked' : ''; ?>>
                                    <span>Show NSFW</span>
                                </label>
                            </div>

                            <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Save">
                                <span>Save Changes</span>
                            </button>
                        </form>
                    </article>
                </div>

                <!-- Tab: Email -->
                <div class="settings-tab-content" id="tab-email">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Email Address</h2>
                            <p>Modify the email address associated with your account.</p>
                        </div>

                        <form method="POST" action="#email" class="auth-form" data-auth-form>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_email">

                            <label class="auth-field">
                                <span>Current Email</span>
                                <input type="email" value="<?php echo auth_h($email); ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                            </label>

                            <label class="auth-field">
                                <span>New Email</span>
                                <input type="email" name="email" required placeholder="new.email@example.com">
                            </label>

                            <?php if (!empty($currentUser['password'])): ?>
                                <label class="auth-field">
                                    <span>Current password</span>
                                    <div class="auth-password">
                                        <input type="password" name="current_password" autocomplete="current-password" required data-password-input>
                                        <button type="button" data-toggle-password aria-label="Show password" style="margin-top: -18px;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small>Required to confirm email changes.</small>
                                </label>
                            <?php endif; ?>

                            <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Save">
                                <span>Update Email</span>
                            </button>
                        </form>
                    </article>
                </div>

                <!-- Tab: Password -->
                <div class="settings-tab-content" id="tab-password">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Password</h2>
                            <p><?php echo empty($currentUser['password']) ? 'Set a password for your account.' : 'Update your login password.'; ?></p>
                        </div>

                        <form method="POST" action="#password" class="auth-form" data-auth-form>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_password">

                            <?php if (!empty($currentUser['password'])): ?>
                                <label class="auth-field">
                                    <span>Current password</span>
                                    <div class="auth-password">
                                        <input type="password" name="current_password" autocomplete="current-password" required data-password-input>
                                        <button type="button" data-toggle-password aria-label="Show password" style="margin-top: -18px;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </label>
                            <?php endif; ?>

                            <label class="auth-field">
                                <span>New password</span>
                                <div class="auth-password">
                                    <input type="password" name="password" autocomplete="new-password" minlength="8" required data-password-input>
                                    <button type="button" data-toggle-password aria-label="Show password" style="margin-top: -18px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </label>

                            <label class="auth-field">
                                <span>Confirm new password</span>
                                <div class="auth-password">
                                    <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required data-password-input>
                                    <button type="button" data-toggle-password aria-label="Show password" style="margin-top: -18px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </label>

                            <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Save">
                                <span><?php echo empty($currentUser['password']) ? 'Set Password' : 'Change Password'; ?></span>
                            </button>
                        </form>
                    </article>
                </div>

                <!-- Tab: 2FA -->
                <div class="settings-tab-content" id="tab-twofa">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Two-Factor Authentication</h2>
                            <p>Protect your account by requiring an additional security code.</p>
                        </div>

                        <?php if (!$twofaStatus['has_columns']): ?>
                            <div class="auth-alert auth-alert--info">
                                <i class="fas fa-database"></i>
                                <span>2FA not installed in the database. Run the included SQL file.</span>
                            </div>
                        <?php else: ?>
                            <div class="twofa-status <?php echo $twofaStatus['enabled'] ? 'is-enabled' : ''; ?>">
                                <i class="fas <?php echo $twofaStatus['enabled'] ? 'fa-shield-halved' : 'fa-shield'; ?>"></i>
                                <div>
                                    <strong><?php echo $twofaStatus['enabled'] ? '2FA enabled' : '2FA disabled'; ?></strong>
                                    <span><?php echo $twofaStatus['enabled'] ? 'Login requires a code.' : 'Recommended to protect your account.'; ?></span>
                                </div>
                            </div>

                            <?php if (!$twofaStatus['enabled'] && !$twofaSetupSecret): ?>
                                <form method="POST" action="#twofa" class="auth-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="start_2fa_setup">
                                    <button class="auth-btn auth-btn--primary" type="submit">
                                        <i class="fas fa-qrcode"></i>
                                        <span>Enable 2FA</span>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$twofaStatus['enabled'] && $twofaSetupSecret): ?>
                                <div class="twofa-setup">
                                    <img src="<?php echo auth_h($qrUrl); ?>" alt="QR code 2FA">
                                    <div>
                                        <strong>Scan the QR code</strong>
                                        <p>Use Google Authenticator, Authy, Microsoft Authenticator, or something similar.</p>
                                        <code><?php echo auth_h($twofaSetupSecret); ?></code>
                                    </div>
                                </div>

                                <form method="POST" action="#twofa" class="auth-form" data-auth-form>
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="enable_2fa">

                                    <label class="auth-field">
                                        <span>6-digit code</span>
                                        <input type="text" name="twofa_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
                                    </label>

                                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Confirm 2FA">
                                        <span>Confirm 2FA</span>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($backupCodesNew): ?>
                                <div class="backup-codes">
                                    <strong>Backup codes</strong>
                                    <p>Save them now. They will not be shown again.</p>
                                    <div class="backup-codes__grid">
                                        <?php foreach ($backupCodesNew as $code): ?>
                                            <code><?php echo auth_h($code); ?></code>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($twofaStatus['enabled']): ?>
                                <details class="security-details">
                                    <summary>Regenerate backup codes</summary>
                                    <form method="POST" action="#twofa" class="auth-form" data-auth-form>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="regenerate_backup_codes">

                                        <label class="auth-field">
                                            <span>Current password</span>
                                            <input type="password" name="current_password" autocomplete="current-password" required>
                                        </label>

                                        <label class="auth-field">
                                            <span>6-digit code</span>
                                            <input type="text" name="twofa_code" required>
                                        </label>

                                        <button class="auth-btn auth-btn--soft" type="submit" data-submit-text="Regenerate">
                                            <span>Regenerate</span>
                                        </button>
                                    </form>
                                </details>

                                <details class="security-details security-details--danger">
                                    <summary>Disable 2FA</summary>
                                    <form method="POST" action="#twofa" class="auth-form" data-auth-form>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="disable_2fa">

                                        <label class="auth-field">
                                            <span>Current password</span>
                                            <input type="password" name="current_password" autocomplete="current-password" required>
                                        </label>

                                        <label class="auth-field">
                                            <span>6-digit code or backup code</span>
                                            <input type="text" name="twofa_code" required>
                                        </label>

                                        <button class="auth-btn auth-btn--danger" type="submit" data-submit-text="Disable">
                                            <span>Disable 2FA</span>
                                        </button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>