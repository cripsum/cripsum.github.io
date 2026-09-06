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
        'data_desc' => 'Download a copy of everything your account holds.',
        'ready_title' => 'Archive ready',
        'ready_note' => 'The download link expires on %s.',
        'download' => 'Download my data',
        'size' => 'Size',
        'requested' => 'Requested',
        'downloads' => 'Downloads',
        'format' => 'Format',
        'format_txt' => 'Text',
        'format_txt_hint' => 'Readable, to look through your data.',
        'format_json' => 'JSON',
        'format_json_hint' => 'Structured, to import it elsewhere.',
        'request' => 'Request my data',
        'request_again' => 'Generate a new archive',
        'cooldown' => 'You can request a new archive in %s.',
        'unavailable' => 'This feature is temporarily unavailable. Please try again later.',

        'delete_title' => 'Delete account',
        'delete_desc' => 'You have %d days to change your mind.',
        'how' => [
            'Your account is hidden right away and you are signed out everywhere.',
            'Sign in again within %d days and the deletion is cancelled.',
            'After that everything is erased for good.',
        ],
        'download_first' => 'Download your data first if you want to keep a copy.',
        'pending_title' => 'Deletion scheduled',
        'pending_text' => 'Your account will be permanently deleted on %s (%s left).',
        'pending_cancel' => 'Keep my account',
        'ack' => 'I understand this is permanent and that all my data will be deleted.',
        'delete_btn' => 'Delete my account',
        'cancelled_notice' => 'Welcome back — the scheduled deletion of your account has been cancelled.',

        'label_password' => 'Your password',
        'label_code' => '2FA code (or a backup code)',
        'label_username' => 'Type your username to confirm',
        'username_hint' => 'This account signs in with Google and has no password, so type <strong>%s</strong> to confirm.',
    ]
    : [
        'data_title' => 'I tuoi dati',
        'data_desc' => 'Scarica una copia di tutto quello che il tuo account contiene.',
        'ready_title' => 'Archivio pronto',
        'ready_note' => 'Il link per il download scade il %s.',
        'download' => 'Scarica i miei dati',
        'size' => 'Dimensione',
        'requested' => 'Richiesto',
        'downloads' => 'Download',
        'format' => 'Formato',
        'format_txt' => 'Testo',
        'format_txt_hint' => 'Leggibile, per consultare i tuoi dati.',
        'format_json' => 'JSON',
        'format_json_hint' => 'Strutturato, per importarli altrove.',
        'request' => 'Richiedi i miei dati',
        'request_again' => 'Genera un nuovo archivio',
        'cooldown' => 'Puoi richiedere un nuovo archivio tra %s.',
        'unavailable' => 'Funzionalità non disponibile al momento. Riprova più tardi.',

        'delete_title' => 'Elimina account',
        'delete_desc' => 'Hai %d giorni per ripensarci.',
        'how' => [
            'Il tuo account viene nascosto subito e vieni disconnesso ovunque.',
            'Accedi di nuovo entro %d giorni e la cancellazione viene annullata.',
            'Dopo, tutto viene eliminato per sempre.',
        ],
        'download_first' => 'Scarica prima i tuoi dati se vuoi conservarne una copia.',
        'pending_title' => 'Cancellazione programmata',
        'pending_text' => 'Il tuo account verrà eliminato definitivamente il %s (mancano %s).',
        'pending_cancel' => 'Mantieni il mio account',
        'ack' => 'Ho capito che l\'operazione è definitiva e che tutti i miei dati verranno eliminati.',
        'delete_btn' => 'Elimina il mio account',
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
                            &bull; <?php echo auth_h(account_export_format_of((string)$latestExport['file_name']) === 'json' ? $copy['format_json'] : $copy['format_txt']); ?>
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

                    <fieldset class="account-format">
                        <legend><?php echo auth_h($copy['format']); ?></legend>
                        <label class="account-format__option">
                            <input type="radio" name="export_format" value="txt" checked>
                            <span>
                                <strong><?php echo auth_h($copy['format_txt']); ?></strong>
                                <small><?php echo auth_h($copy['format_txt_hint']); ?></small>
                            </span>
                        </label>
                        <label class="account-format__option">
                            <input type="radio" name="export_format" value="json">
                            <span>
                                <strong><?php echo auth_h($copy['format_json']); ?></strong>
                                <small><?php echo auth_h($copy['format_json_hint']); ?></small>
                            </span>
                        </label>
                    </fieldset>

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
