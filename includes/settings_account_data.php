<?php
// Settings panels: "your data" (export) and "delete account".
// Included by it/impostazioni.php and en/impostazioni.php, which prepare
// $accountFeaturesReady, $latestExport, $exportCooldown, $deletionState and
// $reauthMethod beforehand. $accountFeaturesReady is false until the SQL in
// migrations/2026_09_account_data_and_deletion.sql has been applied.

$settingsLanguage = ($settingsLanguage ?? 'it') === 'en' ? 'en' : 'it';
$isEn = $settingsLanguage === 'en';

$copy = $isEn
    ? [
        'data_title' => 'Your data',
        'data_desc' => 'Request a copy of everything your account holds, then download it as a ZIP archive.',
        'includes_title' => 'What the archive contains',
        'includes' => [
            'Your profile and account settings',
            'Posts, comments, links, projects and custom blocks',
            'Messages, friendships, achievements and characters',
            'The files you uploaded (avatar, background, images, music)',
        ],
        'excludes' => 'Passwords, 2FA secrets, backup codes and session tokens are never included: they are credentials and never leave the server.',
        'ready_title' => 'Archive ready',
        'ready_note' => 'The download link expires on %s.',
        'download' => 'Download my data',
        'size' => 'Size',
        'requested' => 'Requested',
        'downloads' => 'Downloads',
        'request' => 'Request my data',
        'request_again' => 'Generate a new archive',
        'cooldown' => 'You can request a new archive in %s.',
        'confirm_hint' => 'Confirm it is really you before we generate the archive.',
        'unavailable' => 'This feature is temporarily unavailable. Please try again later.',

        'delete_title' => 'Delete account',
        'delete_desc' => 'Deleting is permanent. You get %d days to change your mind.',
        'how_title' => 'How it works',
        'how' => [
            'Your account is deactivated immediately and stops being visible to everyone else.',
            'You are signed out on every device.',
            'You have %d days to change your mind: just sign in again and the deletion is cancelled.',
            'After that, your account and all your data are permanently erased. This cannot be undone.',
        ],
        'download_first' => 'Want to keep a copy? Request your data export first — it will not be available afterwards.',
        'pending_title' => 'Deletion scheduled',
        'pending_text' => 'Your account will be permanently deleted on %s (%s left).',
        'pending_cancel' => 'Keep my account',
        'ack' => 'I understand this is permanent and that all my data will be deleted.',
        'delete_btn' => 'Delete my account',
        'confirm_delete_hint' => 'Confirm it is really you before scheduling the deletion.',
        'cancelled_notice' => 'Welcome back — the scheduled deletion of your account has been cancelled.',

        'label_password' => 'Your password',
        'label_code' => '2FA code (or a backup code)',
        'label_username' => 'Type your username to confirm',
        'username_hint' => 'This account signs in with Google and has no password, so type <strong>%s</strong> to confirm.',
    ]
    : [
        'data_title' => 'I tuoi dati',
        'data_desc' => 'Richiedi una copia di tutto quello che il tuo account contiene e scaricala come archivio ZIP.',
        'includes_title' => 'Cosa contiene l\'archivio',
        'includes' => [
            'Il tuo profilo e le impostazioni dell\'account',
            'Post, commenti, link, progetti e blocchi personalizzati',
            'Messaggi, amicizie, achievement e personaggi',
            'I file che hai caricato (avatar, sfondo, immagini, musica)',
        ],
        'excludes' => 'Password, segreti 2FA, codici di backup e token di sessione non sono mai inclusi: sono credenziali e non lasciano mai il server.',
        'ready_title' => 'Archivio pronto',
        'ready_note' => 'Il link per il download scade il %s.',
        'download' => 'Scarica i miei dati',
        'size' => 'Dimensione',
        'requested' => 'Richiesto',
        'downloads' => 'Download',
        'request' => 'Richiedi i miei dati',
        'request_again' => 'Genera un nuovo archivio',
        'cooldown' => 'Puoi richiedere un nuovo archivio tra %s.',
        'confirm_hint' => 'Conferma di essere davvero tu prima di generare l\'archivio.',
        'unavailable' => 'Funzionalità non disponibile al momento. Riprova più tardi.',

        'delete_title' => 'Elimina account',
        'delete_desc' => 'L\'eliminazione è definitiva. Hai %d giorni per ripensarci.',
        'how_title' => 'Come funziona',
        'how' => [
            'Il tuo account viene disattivato subito e smette di essere visibile agli altri.',
            'Vieni disconnesso da tutti i dispositivi.',
            'Hai %d giorni per ripensarci: ti basta accedere di nuovo e la cancellazione viene annullata.',
            'Dopodiché account e dati vengono eliminati definitivamente. L\'operazione non è reversibile.',
        ],
        'download_first' => 'Vuoi conservare una copia? Richiedi prima l\'esportazione dei dati: dopo non sarà più disponibile.',
        'pending_title' => 'Cancellazione programmata',
        'pending_text' => 'Il tuo account verrà eliminato definitivamente il %s (mancano %s).',
        'pending_cancel' => 'Mantieni il mio account',
        'ack' => 'Ho capito che l\'operazione è definitiva e che tutti i miei dati verranno eliminati.',
        'delete_btn' => 'Elimina il mio account',
        'confirm_delete_hint' => 'Conferma di essere davvero tu prima di programmare la cancellazione.',
        'cancelled_notice' => 'Bentornato: la cancellazione programmata del tuo account è stata annullata.',

        'label_password' => 'La tua password',
        'label_code' => 'Codice 2FA (o un codice di backup)',
        'label_username' => 'Scrivi il tuo username per confermare',
        'username_hint' => 'Questo account accede con Google e non ha una password, quindi scrivi <strong>%s</strong> per confermare.',
    ];

/**
 * Renders the identity-confirmation fields shared by both forms.
 * $idPrefix keeps the input ids unique between the two panels.
 */
$renderConfirmFields = static function (string $idPrefix) use ($copy, $reauthMethod, $currentUser) {
    $needsPassword = in_array($reauthMethod, ['password', 'password_2fa'], true);
    $needsCode = in_array($reauthMethod, ['2fa', 'password_2fa'], true);
    $needsUsername = $reauthMethod === 'username';
    ?>
    <?php if ($needsPassword): ?>
        <label class="auth-field">
            <span><?php echo auth_h($copy['label_password']); ?></span>
            <input type="password" name="confirm_password" id="<?php echo auth_h($idPrefix); ?>Password" autocomplete="current-password" required>
        </label>
    <?php endif; ?>
    <?php if ($needsCode): ?>
        <label class="auth-field">
            <span><?php echo auth_h($copy['label_code']); ?></span>
            <input type="text" name="confirm_code" id="<?php echo auth_h($idPrefix); ?>Code" inputmode="text" autocomplete="one-time-code" maxlength="32" required>
        </label>
    <?php endif; ?>
    <?php if ($needsUsername): ?>
        <label class="auth-field">
            <span><?php echo auth_h($copy['label_username']); ?></span>
            <input type="text" name="confirm_username" id="<?php echo auth_h($idPrefix); ?>Username" autocomplete="off" maxlength="20" required>
            <small style="opacity:.7;">
                <?php echo sprintf($copy['username_hint'], '<code>' . auth_h((string)($currentUser['username'] ?? '')) . '</code>'); ?>
            </small>
        </label>
    <?php endif; ?>
    <?php
};

$pendingDeletion = !empty($deletionState);
?>

<!-- Tab: Your data -->
<div class="settings-tab-content" id="tab-data">
    <article class="settings-panel auth-reveal">
        <div class="settings-panel__head">
            <h2><?php echo auth_h($copy['data_title']); ?></h2>
            <p><?php echo auth_h($copy['data_desc']); ?></p>
        </div>

        <?php if (!$accountFeaturesReady): ?>
            <div class="auth-alert auth-alert--error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo auth_h($copy['unavailable']); ?></span>
            </div>
        <?php else: ?>
            <div class="account-data-box">
                <h3><i class="fa-solid fa-box-archive"></i> <?php echo auth_h($copy['includes_title']); ?></h3>
                <ul class="account-data-list">
                    <?php foreach ($copy['includes'] as $item): ?>
                        <li><?php echo auth_h($item); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="account-data-note"><i class="fa-solid fa-shield-halved"></i> <?php echo auth_h($copy['excludes']); ?></p>
            </div>

            <?php if ($latestExport): ?>
                <div class="account-data-ready">
                    <div class="account-data-ready__info">
                        <strong><i class="fa-solid fa-circle-check"></i> <?php echo auth_h($copy['ready_title']); ?></strong>
                        <small>
                            <?php echo auth_h($copy['size']); ?>:
                            <?php echo auth_h(number_format(((int)$latestExport['file_size']) / 1048576, 2)); ?> MB
                            &bull; <?php echo auth_h($copy['requested']); ?>:
                            <?php echo auth_h(date('d/m/Y H:i', strtotime((string)$latestExport['requested_at']))); ?>
                            &bull; <?php echo auth_h($copy['downloads']); ?>: <?php echo (int)$latestExport['download_count']; ?>
                        </small>
                        <small><?php echo auth_h(sprintf($copy['ready_note'], date('d/m/Y', strtotime((string)$latestExport['expires_at'])))); ?></small>
                    </div>
                    <a class="auth-btn auth-btn--primary account-data-download" href="/api/download_data_export.php">
                        <i class="fa-solid fa-download"></i>
                        <span><?php echo auth_h($copy['download']); ?></span>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($exportCooldown > 0): ?>
                <p class="account-data-note account-data-note--muted">
                    <i class="fa-solid fa-clock"></i>
                    <?php echo auth_h(sprintf($copy['cooldown'], account_format_duration($exportCooldown, $settingsLanguage))); ?>
                </p>
            <?php else: ?>
                <form method="POST" action="#data" class="auth-form account-data-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="request_data_export">
                    <p class="account-data-note account-data-note--muted"><?php echo auth_h($copy['confirm_hint']); ?></p>
                    <?php $renderConfirmFields('export'); ?>
                    <button class="auth-btn auth-btn--primary" type="submit" style="width:auto;padding:10px 22px;">
                        <i class="fa-solid fa-file-zipper"></i>
                        <span><?php echo auth_h($latestExport ? $copy['request_again'] : $copy['request']); ?></span>
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </article>
</div>

<!-- Tab: Delete account -->
<div class="settings-tab-content" id="tab-delete">
    <article class="settings-panel auth-reveal account-danger-panel">
        <div class="settings-panel__head">
            <h2><?php echo auth_h($copy['delete_title']); ?></h2>
            <p><?php echo auth_h(sprintf($copy['delete_desc'], ACCOUNT_DELETION_GRACE_DAYS)); ?></p>
        </div>

        <?php if (!$accountFeaturesReady): ?>
            <div class="auth-alert auth-alert--error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo auth_h($copy['unavailable']); ?></span>
            </div>
        <?php elseif ($pendingDeletion): ?>
            <div class="account-pending">
                <strong><i class="fa-solid fa-hourglass-half"></i> <?php echo auth_h($copy['pending_title']); ?></strong>
                <p><?php echo auth_h(sprintf(
                        $copy['pending_text'],
                        date('d/m/Y', strtotime((string)$deletionState['scheduled_for'])),
                        account_format_duration((int)$deletionState['seconds_left'], $settingsLanguage)
                    )); ?></p>
                <form method="POST" action="#delete" class="auth-form" style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel_account_deletion">
                    <button class="auth-btn auth-btn--primary" type="submit" style="width:auto;padding:10px 22px;">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span><?php echo auth_h($copy['pending_cancel']); ?></span>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="account-data-box account-data-box--danger">
                <h3><i class="fa-solid fa-circle-info"></i> <?php echo auth_h($copy['how_title']); ?></h3>
                <ol class="account-data-list">
                    <?php foreach ($copy['how'] as $step): ?>
                        <li><?php echo auth_h(sprintf($step, ACCOUNT_DELETION_GRACE_DAYS)); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <p class="account-data-note account-data-note--muted">
                <i class="fa-solid fa-download"></i> <?php echo auth_h($copy['download_first']); ?>
            </p>

            <form method="POST" action="#delete" class="auth-form account-data-form"
                data-confirm-delete="<?php echo auth_h($copy['ack']); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="request_account_deletion">
                <p class="account-data-note account-data-note--muted"><?php echo auth_h($copy['confirm_delete_hint']); ?></p>
                <?php $renderConfirmFields('delete'); ?>
                <label class="auth-check" style="margin-top:.4rem;">
                    <input type="checkbox" name="confirm_understand" value="1" required>
                    <span><?php echo auth_h($copy['ack']); ?></span>
                </label>
                <button class="auth-btn auth-btn--danger" type="submit" style="width:auto;padding:10px 22px;margin-top:1rem;">
                    <i class="fa-solid fa-user-slash"></i>
                    <span><?php echo auth_h($copy['delete_btn']); ?></span>
                </button>
            </form>
        <?php endif; ?>
    </article>
</div>
