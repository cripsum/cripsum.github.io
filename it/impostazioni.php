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

if (!empty($_SESSION['profile_flash_success'])) {
    $success = $_SESSION['profile_flash_success'];
    unset($_SESSION['profile_flash_success']);
}
if (!empty($_SESSION['profile_flash_error'])) {
    $error = $_SESSION['profile_flash_error'];
    unset($_SESSION['profile_flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Sessione scaduta. Riprova.';
    } elseif ($action === 'update_discord_settings') {
        $useAvatar = isset($_POST['discord_use_avatar']) ? 1 : 0;
        $useDisplayName = isset($_POST['discord_use_display_name']) ? 1 : 0;
        $stmt = $mysqli->prepare("UPDATE utenti SET discord_use_avatar = ?, discord_use_display_name = ?, profile_updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('iii', $useAvatar, $useDisplayName, $userId);
            if ($stmt->execute()) {
                $success = 'Impostazioni Discord aggiornate con successo.';
            } else {
                $error = 'Errore durante l’aggiornamento.';
            }
            $stmt->close();
        }
    } elseif ($action === 'update_profile') {
        $newUsername = strtolower(trim($_POST['username'] ?? ''));
        $newNsfw = isset($_POST['nsfw']) ? 1 : 0;
        $newRichpresence = isset($_POST['richpresence']) ? 1 : 0;

        if ($newUsername === '') {
            $error = 'Lo username è obbligatorio.';
        } elseif (!auth_is_valid_username($newUsername)) {
            $error = 'Username non valido.';
        } else {
            $result = updateUserSettings($mysqli, $userId, $newUsername, $email, '', $newNsfw, $newRichpresence);

            if ($result === true) {
                $success = 'Profilo aggiornato con successo.';
                $username = $newUsername;
                $nsfw = $newNsfw;
                $richpresence = $newRichpresence;
            } else {
                $error = is_string($result) ? $result : 'Errore durante il salvataggio.';
            }
        }
    } elseif ($action === 'update_email') {
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $hasPassword = !empty($currentUser['password']);

        if ($newEmail === '') {
            $error = 'L’email è obbligatoria.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email non valida.';
        } elseif (strcasecmp($newEmail, $email) === 0) {
            $error = 'La nuova email è uguale a quella attuale.';
        } elseif ($hasPassword && !auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Password attuale non valida.';
        } else {
            $result = updateUserSettings($mysqli, $userId, $username, $newEmail, '', $nsfw, $richpresence);

            if ($result === true) {
                session_destroy();
                session_start();
                $_SESSION['login_message'] = 'Email modificata. Controlla la nuova casella per verificare l’account.';
                header('Location: accedi');
                exit();
            } else {
                $error = is_string($result) ? $result : 'Errore durante il salvataggio.';
            }
        }
    } elseif ($action === 'update_password') {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $hasPassword = !empty($currentUser['password']);

        if ($newPassword === '') {
            $error = 'La nuova password è obbligatoria.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'La nuova password deve avere almeno 8 caratteri.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Le due password non coincidono.';
        } elseif ($hasPassword && !auth_verify_user_password($mysqli, $userId, $currentPassword)) {
            $error = 'Password attuale non valida.';
        } else {
            $result = updateUserSettings($mysqli, $userId, $username, $email, $newPassword, $nsfw, $richpresence);

            if ($result === true) {
                $success = 'Password modificata con successo.';
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

$discordConnected = !empty($currentUser['discord_id']) && !empty($currentUser['discord_username']);
$discordId = (string)($currentUser['discord_id'] ?? '');
$discordUsername = (string)($currentUser['discord_username'] ?? '');
$discordGlobalName = (string)($currentUser['discord_global_name'] ?? '');
$discordAvatar = (string)($currentUser['discord_avatar'] ?? '');
$discordConnectedAt = $currentUser['discord_connected_at'] ?? null;
$discordUseAvatar = (int)($currentUser['discord_use_avatar'] ?? 0);
$discordUseDisplayName = (int)($currentUser['discord_use_display_name'] ?? 0);

$discordAvatarUrl = null;
if ($discordConnected && !empty($discordAvatar)) {
    if (function_exists('profile_discord_avatar_url')) {
        $discordAvatarUrl = profile_discord_avatar_url($discordId, $discordAvatar, 128);
    } else {
        $ext = (strpos($discordAvatar, 'a_') === 0) ? 'gif' : 'png';
        $discordAvatarUrl = "https://cdn.discordapp.com/avatars/{$discordId}/{$discordAvatar}.{$ext}?size=128";
    }
}
$discordDisplayName = trim($discordGlobalName) ?: trim($discordUsername);
$connectDiscordUrl = '/auth/discord_connect.php?return_url=' . urlencode('/it/impostazioni#connections');
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Impostazioni</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/auth/auth.css?v=1.2">
    <script src="/assets/auth/auth.js?v=1.2" defer></script>
</head>

<body class="auth-page settings-page">
    <?php include '../includes/navbar.php'; ?>


    <main class="settings-shell">
        <header class="settings-hero auth-reveal">
            <img src="<?php echo auth_h($profilePic); ?>" alt="">
            <div>
                <h1>Impostazioni</h1>
                <p>Gestisci profilo, preferenze e sicurezza.</p>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="auth-alert auth-alert--error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo auth_h($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert--success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo auth_h($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($currentUser['password'])): ?>
            <div class="auth-alert auth-reveal" style="background: rgba(255, 193, 7, 0.15); border: 1px solid #ffc107; color: #ffc107; padding: 1rem; border-radius: 20px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 15px;">
                <i class="fa-solid fa-key"></i>
                <span style="flex-grow: 1;">Imposta una password per accedere anche senza Google.</span>
                <a href="#password" class="auth-btn" style="background: #ffc107; color: #000; width: auto; padding: 5px 15px;" onclick="document.querySelector('.settings-tab-btn[data-tab=\'password\']').click();">Configura</a>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <aside class="settings-sidebar auth-reveal">
                <button class="settings-tab-btn active" data-tab="profile">
                    <i class="fa-solid fa-user-circle"></i>
                    <span>Profilo</span>
                </button>
                <button class="settings-tab-btn" data-tab="email">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Email</span>
                </button>
                <button class="settings-tab-btn" data-tab="password">
                    <i class="fa-solid fa-key"></i>
                    <span>Password</span>
                </button>
                <button class="settings-tab-btn" data-tab="twofa">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Sicurezza 2FA</span>
                </button>
                <button class="settings-tab-btn" data-tab="connections">
                    <i class="fa-brands fa-discord"></i>
                    <span>Connessioni</span>
                </button>
            </aside>

            <div class="settings-main">
                <!-- Tab: Profilo -->
                <div class="settings-tab-content active" id="tab-profile">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Informazioni Profilo</h2>
                            <p>Gestisci i dati pubblici e le preferenze di visualizzazione.</p>
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
                                    <span>Mostra NSFW</span>
                                </label>
                            </div>

                            <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Salva">
                                <span>Salva Modifiche</span>
                            </button>
                        </form>
                    </article>
                </div>

                <!-- Tab: Email -->
                <div class="settings-tab-content" id="tab-email">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Indirizzo Email</h2>
                            <p>Modifica l'indirizzo email associato al tuo account.</p>
                        </div>

                        <form method="POST" action="#email" class="auth-form" data-auth-form>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_email">

                            <label class="auth-field">
                                <span>Email Attuale</span>
                                <input type="email" value="<?php echo auth_h($email); ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                            </label>

                            <label class="auth-field">
                                <span>Nuova Email</span>
                                <input type="email" name="email" required placeholder="nuovo.indirizzo@esempio.com">
                            </label>

                            <?php if (!empty($currentUser['password'])): ?>
                                <label class="auth-field">
                                    <span>Password attuale</span>
                                    <div class="auth-password">
                                        <input type="password" name="current_password" autocomplete="current-password" required data-password-input>
                                        <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -18px;">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                    <small>Necessaria per confermare la modifica dell'email.</small>
                                </label>
                            <?php endif; ?>

                            <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Salva">
                                <span>Aggiorna Email</span>
                            </button>
                        </form>
                    </article>
                </div>

                <!-- Tab: Password -->
                <div class="settings-tab-content" id="tab-password">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Password</h2>
                            <p><?php echo empty($currentUser['password']) ? 'Imposta una password per il tuo account.' : 'Aggiorna la tua password di accesso.'; ?></p>
                        </div>

                        <form method="POST" action="#password" class="auth-form" data-auth-form>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_password">

                            <?php if (!empty($currentUser['password'])): ?>
                                <label class="auth-field">
                                    <span>Password attuale</span>
                                    <div class="auth-password">
                                        <input type="password" name="current_password" autocomplete="current-password" required data-password-input>
                                        <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -18px;">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </label>
                            <?php endif; ?>

                            <label class="auth-field">
                                <span>Nuova password</span>
                                <div class="auth-password">
                                    <input type="password" name="password" autocomplete="new-password" minlength="8" required data-password-input>
                                    <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -18px;">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </label>

                            <label class="auth-field">
                                <span>Conferma nuova password</span>
                                <div class="auth-password">
                                    <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required data-password-input>
                                    <button type="button" data-toggle-password aria-label="Mostra password" style="margin-top: -18px;">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </label>

                            <button class="auth-btn auth-btn--primary" type="submit" data-submit-text="Salva">
                                <span><?php echo empty($currentUser['password']) ? 'Imposta Password' : 'Cambia Password'; ?></span>
                            </button>
                        </form>
                    </article>
                </div>

                <!-- Tab: 2FA -->
                <div class="settings-tab-content" id="tab-twofa">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Autenticazione a Due Fattori</h2>
                            <p>Proteggi il tuo account richiedendo un codice di sicurezza aggiuntivo.</p>
                        </div>

                        <?php if (!$twofaStatus['has_columns']): ?>
                            <div class="auth-alert auth-alert--info">
                                <i class="fa-solid fa-database"></i>
                                <span>2FA non installata nel database. Esegui il file SQL incluso.</span>
                            </div>
                        <?php else: ?>
                            <div class="twofa-status <?php echo $twofaStatus['enabled'] ? 'is-enabled' : ''; ?>">
                                <i class="fa-solid <?php echo $twofaStatus['enabled'] ? 'fa-shield-halved' : 'fa-shield'; ?>"></i>
                                <div>
                                    <strong><?php echo $twofaStatus['enabled'] ? '2FA attiva' : '2FA non attiva'; ?></strong>
                                    <span><?php echo $twofaStatus['enabled'] ? 'Il login richiede un codice.' : 'Consigliata per proteggere l’account.'; ?></span>
                                </div>
                            </div>

                            <?php if (!$twofaStatus['enabled'] && !$twofaSetupSecret): ?>
                                <form method="POST" action="#twofa" class="auth-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="start_2fa_setup">
                                    <button class="auth-btn auth-btn--primary" type="submit">
                                        <i class="fa-solid fa-qrcode"></i>
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

                                <form method="POST" action="#twofa" class="auth-form" data-auth-form>
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
                                    <form method="POST" action="#twofa" class="auth-form" data-auth-form>
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
                                    <form method="POST" action="#twofa" class="auth-form" data-auth-form>
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
                </div>

                <!-- Tab: Connessioni -->
                <div class="settings-tab-content" id="tab-connections">
                    <article class="settings-panel auth-reveal">
                        <div class="settings-panel__head">
                            <h2>Account Collegati</h2>
                            <p>Collega e gestisci l'integrazione con i tuoi account social e piattaforme esterne.</p>
                        </div>

                        <div class="connections-list" style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <!-- Discord Connection Card -->
                            <div class="connection-card" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                                <div class="connection-card__header" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="width: 48px; height: 48px; border-radius: 12px; background: #5865F2; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff;">
                                            <i class="fa-brands fa-discord"></i>
                                        </div>
                                        <div>
                                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Discord</h3>
                                            <p style="margin: 2px 0 0 0; font-size: 0.85rem; opacity: 0.7;">Sincronizza stato, avatar e dati con il tuo profilo Cripsum.</p>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="auth-badge <?php echo $discordConnected ? 'auth-badge--success' : 'auth-badge--muted'; ?>" style="padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; background: <?php echo $discordConnected ? 'rgba(34, 197, 94, 0.15)' : 'rgba(255, 255, 255, 0.08)'; ?>; color: <?php echo $discordConnected ? '#4ade80' : 'rgba(255, 255, 255, 0.6)'; ?>; border: 1px solid <?php echo $discordConnected ? 'rgba(34, 197, 94, 0.3)' : 'rgba(255, 255, 255, 0.12)'; ?>;">
                                            <i class="fa-solid <?php echo $discordConnected ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                            <?php echo $discordConnected ? 'Collegato' : 'Non collegato'; ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($discordConnected): ?>
                                    <div class="connection-card__body" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <?php if ($discordAvatarUrl): ?>
                                                <img src="<?php echo auth_h($discordAvatarUrl); ?>" alt="Discord Avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 48px; height: 48px; border-radius: 50%; background: #5865F2; display: flex; align-items: center; justify-content: center; color: #fff;">
                                                    <i class="fa-brands fa-discord"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="display: block; font-size: 1rem;"><?php echo auth_h($discordDisplayName); ?></strong>
                                                <small style="display: block; opacity: 0.7; font-size: 0.85rem;">@<?php echo auth_h($discordUsername); ?> &bull; ID: <?php echo auth_h($discordId); ?></small>
                                                <?php if ($discordConnectedAt): ?>
                                                    <small style="display: block; opacity: 0.5; font-size: 0.78rem;">Collegato il <?php echo date('d/m/Y H:i', strtotime($discordConnectedAt)); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <a href="<?php echo auth_h($connectDiscordUrl); ?>" class="auth-btn auth-btn--soft" style="width: auto; padding: 8px 16px; font-size: 0.85rem;">
                                                <i class="fa-solid fa-arrows-rotate me-1"></i> Ricollega
                                            </a>
                                            <form method="POST" action="../auth/discord_disconnect.php" style="margin: 0;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="return_url" value="/it/impostazioni#connections">
                                                <button type="submit" class="auth-btn auth-btn--danger" style="width: auto; padding: 8px 16px; font-size: 0.85rem;" onclick="return confirm('Sei sicuro di voler scollegare l\'account Discord?');">
                                                    <i class="fa-solid fa-link-slash me-1"></i> Scollega
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <form method="POST" action="#connections" class="auth-form" style="margin-top: 0.5rem;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="update_discord_settings">

                                        <div class="settings-checks" style="display: flex; flex-direction: column; gap: 0.75rem;">
                                            <label class="auth-check">
                                                <input type="checkbox" name="discord_use_display_name" <?php echo $discordUseDisplayName ? 'checked' : ''; ?>>
                                                <span>Usa il nome Discord come nome sul profilo</span>
                                            </label>
                                            <label class="auth-check">
                                                <input type="checkbox" name="discord_use_avatar" <?php echo $discordUseAvatar ? 'checked' : ''; ?>>
                                                <span>Usa l'avatar Discord sul tuo profilo</span>
                                            </label>
                                        </div>

                                        <button class="auth-btn auth-btn--primary" type="submit" style="margin-top: 1rem; width: auto; padding: 8px 20px;">
                                            <span>Salva Preferenze Discord</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; gap: 1rem; align-items: flex-start;">
                                        <p style="margin: 0; font-size: 0.9rem; opacity: 0.8;">Collegando il tuo account Discord potrai sincronizzare l'avatar, il nome utente e mostrare la tua presenza Discord direttamente sul tuo profilo Cripsum™.</p>
                                        <a href="<?php echo auth_h($connectDiscordUrl); ?>" class="auth-btn auth-btn--primary" style="width: auto; padding: 10px 24px; background: #5865F2; border: none; display: inline-flex; align-items: center; gap: 10px;">
                                            <i class="fa-brands fa-discord" style="font-size: 1.2rem;"></i>
                                            <span>Collega Account Discord</span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>