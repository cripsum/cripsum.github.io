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
        $error = 'Sessione scaduta. Riprova.';
    } elseif ($action === 'save_settings') {
        $newUsername = strtolower(trim($_POST['username'] ?? ''));
        $newEmail = trim($_POST['email'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $newNsfw = isset($_POST['nsfw']) ? 1 : 0;
        $newRichpresence = isset($_POST['richpresence']) ? 1 : 0;

        $emailChanged = strcasecmp($newEmail, $email) !== 0;
        $passwordChanged = $newPassword !== '';

        if ($newUsername === '' || $newEmail === '') {
            $error = 'Username ed email sono obbligatori.';
        } elseif (!auth_is_valid_username($newUsername)) {
            $error = 'Username non valido.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email non valida.';
        } elseif ($passwordChanged && strlen($newPassword) < 8) {
            $error = 'La nuova password deve avere almeno 8 caratteri.';
        } elseif (($emailChanged || $passwordChanged) && !auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Inserisci la password attuale per modificare email o password.';
        } else {
            $result = updateUserSettings($mysqli, $userId, $newUsername, $newEmail, $newPassword, $newNsfw, $newRichpresence);

            if ($result === true) {
                if ($emailChanged) {
                    session_destroy();
                    session_start();
                    $_SESSION['login_message'] = 'Email modificata. Controlla la nuova casella per verificare l’account.';
                    header('Location: accedi');
                    exit();
                }

                $success = 'Impostazioni salvate.';
                $username = $newUsername;
                $email = $newEmail;
                $nsfw = $newNsfw;
                $richpresence = $newRichpresence;
            } else {
                $error = is_string($result) ? $result : 'Errore durante il salvataggio.';
            }
        }
    } elseif ($action === 'start_2fa_setup') {
        if (!$twofaStatus['has_columns']) {
            $error = 'Prima esegui le query SQL per aggiungere la 2FA.';
        } elseif ($twofaStatus['enabled']) {
            $error = 'La 2FA è già attiva.';
        } else {
            $_SESSION['twofa_setup_secret'] = totp_generate_secret();
            $twofaSetupSecret = $_SESSION['twofa_setup_secret'];
            $success = 'Scansiona il QR code e conferma il codice.';
        }
    } elseif ($action === 'enable_2fa') {
        $code = trim($_POST['twofa_code'] ?? '');
        $twofaSetupSecret = $_SESSION['twofa_setup_secret'] ?? null;

        if (!$twofaStatus['has_columns']) {
            $error = 'Campi 2FA mancanti nel database.';
        } elseif (!$twofaSetupSecret) {
            $error = 'Setup 2FA non avviato.';
        } elseif (!totp_verify($twofaSetupSecret, $code)) {
            $error = 'Codice non valido.';
        } else {
            $result = auth_enable_2fa($mysqli, $userId, $twofaSetupSecret);

            if (!empty($result['ok'])) {
                unset($_SESSION['twofa_setup_secret']);
                $twofaSetupSecret = null;
                $twofaStatus = auth_twofa_status($mysqli, $userId);
                $backupCodesNew = $result['backup_codes'] ?? [];
                $success = '2FA attivata. Salva i backup codes.';
            } else {
                $error = $result['message'] ?? 'Non sono riuscito ad attivare la 2FA.';
            }
        }
    } elseif ($action === 'disable_2fa') {
        $currentPassword = $_POST['current_password'] ?? '';
        $code = trim($_POST['twofa_code'] ?? '');

        if (!$twofaStatus['enabled']) {
            $error = 'La 2FA non è attiva.';
        } elseif (!auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Password attuale non valida.';
        } elseif (!auth_verify_2fa_or_backup($mysqli, $userId, $code)) {
            $error = 'Codice 2FA non valido.';
        } elseif (auth_disable_2fa($mysqli, $userId)) {
            unset($_SESSION['twofa_setup_secret']);
            $twofaStatus = auth_twofa_status($mysqli, $userId);
            $success = '2FA disattivata.';
        } else {
            $error = 'Non sono riuscito a disattivare la 2FA.';
        }
    } elseif ($action === 'regenerate_backup_codes') {
        $currentPassword = $_POST['current_password'] ?? '';
        $code = trim($_POST['twofa_code'] ?? '');

        if (!$twofaStatus['enabled']) {
            $error = 'Attiva prima la 2FA.';
        } elseif (!auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Password attuale non valida.';
        } elseif (!auth_verify_2fa_or_backup($mysqli, $userId, $code)) {
            $error = 'Codice 2FA non valido.';
        } else {
            $backupCodesNew = auth_generate_backup_codes(8);
            if (auth_store_backup_codes($mysqli, $userId, $backupCodesNew)) {
                $success = 'Backup codes rigenerati. Salvali ora.';
            } else {
                $error = 'Tabella backup codes mancante o errore salvataggio.';
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
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Impostazioni</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.0-2fa">
    <script src="/assets/auth/auth.js?v=1.0-2fa" defer></script>
</head>

<body class="auth-page settings-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <main class="settings-shell">
        <header class="settings-hero auth-reveal">
            <img src="<?php echo auth_h($profilePic); ?>" alt="">
            <div>
                <span class="auth-pill">Account</span>
                <h1>Impostazioni</h1>
                <p>Gestisci profilo, preferenze e sicurezza.</p>
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

        <section class="settings-grid">
            <article class="settings-panel auth-reveal">
                <div class="settings-panel__head">
                    <h2>Profilo</h2>
                    <p>Dati base e preferenze.</p>
                </div>

                <form method="POST" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_settings">

                    <label class="auth-field">
                        <span>Username</span>
                        <input type="text" name="username" value="<?php echo auth_h($username); ?>" required maxlength="20">
                    </label>

                    <label class="auth-field">
                        <span>Email</span>
                        <input type="email" name="email" value="<?php echo auth_h($email); ?>" required>
                    </label>

                    <label class="auth-field">
                        <span>Password attuale</span>
                        <div class="auth-password">
                            <input type="password" name="current_password" autocomplete="current-password" data-password-input>
                            <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -5px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small>Serve solo per cambiare email o password.</small>
                    </label>

                    <label class="auth-field">
                        <span>Nuova password</span>
                        <div class="auth-password">
                            <input type="password" name="password" autocomplete="new-password" minlength="8" data-password-input>
                            <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -5px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <div class="settings-checks">
                        <label class="auth-check">
                            <input type="checkbox" name="nsfw" <?php echo $nsfw ? 'checked' : ''; ?>>
                            <span>Mostra NSFW</span>
                        </label>

                        <label class="auth-check">
                            <input type="checkbox" name="richpresence" <?php echo $richpresence ? 'checked' : ''; ?>>
                            <span>Rich Presence</span>
                        </label>
                    </div>

                    <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Salva">
                        <span>Salva</span>
                    </button>
                </form>
            </article>

            <article class="settings-panel auth-reveal">
                <div class="settings-panel__head">
                    <h2>Sicurezza</h2>
                    <p>Proteggi l’account con app autenticatore.</p>
                </div>

                <?php if (!$twofaStatus['has_columns']): ?>
                    <div class="auth-alert auth-alert--info">
                        <i class="fas fa-database"></i>
                        <span>2FA non installata nel database. Esegui il file SQL incluso.</span>
                    </div>
                <?php else: ?>
                    <div class="twofa-status <?php echo $twofaStatus['enabled'] ? 'is-enabled' : ''; ?>">
                        <i class="fas <?php echo $twofaStatus['enabled'] ? 'fa-shield-halved' : 'fa-shield'; ?>"></i>
                        <div>
                            <strong><?php echo $twofaStatus['enabled'] ? '2FA attiva' : '2FA non attiva'; ?></strong>
                            <span><?php echo $twofaStatus['enabled'] ? 'Il login richiede un codice.' : 'Consigliata per proteggere l’account.'; ?></span>
                        </div>
                    </div>

                    <?php if (!$twofaStatus['enabled'] && !$twofaSetupSecret): ?>
                        <form method="POST" class="auth-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="start_2fa_setup">
                            <button class="auth-btn auth-btn--primary" type="submit">
                                <i class="fas fa-qrcode"></i>
                                <span>Attiva 2FA</span>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$twofaStatus['enabled'] && $twofaSetupSecret): ?>
                        <div class="twofa-setup">
                            <img src="<?php echo auth_h($qrUrl); ?>" alt="QR code 2FA">
                            <div>
                                <strong>Scansiona il QR code</strong>
                                <p>Usa Google Authenticator, Authy, Microsoft Authenticator o simili.</p>
                                <code><?php echo auth_h($twofaSetupSecret); ?></code>
                            </div>
                        </div>

                        <form method="POST" class="auth-form" data-auth-form>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="enable_2fa">

                            <label class="auth-field">
                                <span>Codice a 6 cifre</span>
                                <input type="text" name="twofa_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
                            </label>

                            <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Conferma 2FA">
                                <span>Conferma 2FA</span>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($backupCodesNew): ?>
                        <div class="backup-codes">
                            <strong>Backup codes</strong>
                            <p>Salvali ora. Non verranno mostrati di nuovo.</p>
                            <div class="backup-codes__grid">
                                <?php foreach ($backupCodesNew as $code): ?>
                                    <code><?php echo auth_h($code); ?></code>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($twofaStatus['enabled']): ?>
                        <details class="security-details">
                            <summary>Rigenera backup codes</summary>
                            <form method="POST" class="auth-form" data-auth-form>
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="regenerate_backup_codes">

                                <label class="auth-field">
                                    <span>Password attuale</span>
                                    <input type="password" name="current_password" autocomplete="current-password" required>
                                </label>

                                <label class="auth-field">
                                    <span>Codice 2FA</span>
                                    <input type="text" name="twofa_code" required>
                                </label>

                                <button class="auth-btn auth-btn--soft" type="submit" data-submit-text="Rigenera">
                                    <span>Rigenera</span>
                                </button>
                            </form>
                        </details>

                        <details class="security-details security-details--danger">
                            <summary>Disattiva 2FA</summary>
                            <form method="POST" class="auth-form" data-auth-form>
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="disable_2fa">

                                <label class="auth-field">
                                    <span>Password attuale</span>
                                    <input type="password" name="current_password" autocomplete="current-password" required>
                                </label>

                                <label class="auth-field">
                                    <span>Codice 2FA o backup code</span>
                                    <input type="text" name="twofa_code" required>
                                </label>

                                <button class="auth-btn auth-btn--danger" type="submit" data-submit-text="Disattiva">
                                    <span>Disattiva 2FA</span>
                                </button>
                            </form>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>