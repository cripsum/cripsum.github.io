<?php
/**
 * /it/candidatura-chisiamo: il form per entrare nella pagina Chi siamo.
 *
 * Prima la candidatura partiva per email. Ora si salva in team_candidature
 * e la si guarda dal pannello (Chi siamo > Candidature): chi si candida
 * riceve la risposta nella posta del sito, quindi l'email non si chiede
 * piu'. Lo staff riceve comunque l'avviso su Discord dal bot.
 *
 * Il form manda qui stesso (POST), poi si torna in GET con il risultato:
 * ricaricare la pagina non rimanda la candidatura.
 *
 * Variabili attese: $cLang ('it' | 'en'), $mysqli.
 */

require_once __DIR__ . '/chisiamo.php';
require_once __DIR__ . '/strings.php';
require_once __DIR__ . '/../theme.php';

$S = chisiamo_strings($cLang);
$cSelf = '/' . $cLang . '/candidatura-chisiamo';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $cSelf;
    $_SESSION['login_message'] = $S['apply_login'];
    header('Location: /' . $cLang . '/accedi');
    exit();
}

$cUserId = (int)$_SESSION['user_id'];
$cUsername = (string)($_SESSION['username'] ?? '');
$cReady = chisiamo_candidature_ready($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cError = null;
    $cName = trim((string)($_POST['nome'] ?? ''));
    $cDescription = trim(str_replace("\r\n", "\n", (string)($_POST['descrizione'] ?? '')));
    $cSocialName = trim((string)($_POST['social_username'] ?? ''));
    $cSocialLink = trim((string)($_POST['social_link'] ?? ''));

    if ($cSocialLink !== '' && !preg_match('~^[a-z][a-z0-9+.-]*://~i', $cSocialLink)) {
        $cSocialLink = 'https://' . ltrim($cSocialLink, '/');
    }

    // Un file oltre post_max_size svuota $_POST: senza questo controllo
    // sembrerebbe una sessione scaduta.
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $cError = 'apply_err_photo_too_big';
    } elseif (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $cError = 'apply_err_csrf';
    } elseif (!$cReady) {
        $cError = 'apply_err_closed';
    } elseif ($cName === '' || mb_strlen($cName) > 60) {
        $cError = 'apply_err_name';
    } elseif ($cDescription === '' || mb_strlen($cDescription) > 700) {
        $cError = 'apply_err_description';
    } elseif ($cSocialLink !== '' && (mb_strlen($cSocialLink) > 255 || !preg_match('~^https://[^\s]+$~i', $cSocialLink) || filter_var($cSocialLink, FILTER_VALIDATE_URL) === false)) {
        $cError = 'apply_err_link';
    } elseif (chisiamo_candidatura_pending($mysqli, $cUserId)) {
        $_SESSION['chisiamo_candidatura'] = ['type' => 'info', 'key' => 'apply_pending'];
        header('Location: ' . $cSelf);
        exit();
    }

    $cPhoto = null;
    if ($cError === null) {
        $stored = chisiamo_candidatura_store_foto($_FILES['foto'] ?? [], $cUserId);
        if (str_starts_with($stored, '!')) {
            $cError = 'apply_err_' . substr($stored, 1);
        } else {
            $cPhoto = $stored;
        }
    }

    if ($cError === null) {
        try {
            $stmt = $mysqli->prepare(
                'INSERT INTO team_candidature (utente_id, nome, descrizione, foto, social_nome, social_link) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $socialName = $cSocialName !== '' ? mb_substr($cSocialName, 0, 80) : null;
            $socialLink = $cSocialLink !== '' ? $cSocialLink : null;
            $stmt->bind_param('isssss', $cUserId, $cName, $cDescription, $cPhoto, $socialName, $socialLink);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[candidatura] ' . $e->getMessage());
            chisiamo_candidatura_delete_foto($cPhoto);
            $cError = 'apply_err_server';
        }
    }

    if ($cError !== null) {
        $_SESSION['chisiamo_candidatura'] = [
            'type' => 'error',
            'key' => $cError,
            'old' => ['nome' => $cName, 'descrizione' => $cDescription, 'social_username' => $cSocialName, 'social_link' => $cSocialLink],
        ];
        header('Location: ' . $cSelf);
        exit();
    }

    // L'avviso allo staff su Discord: se il bot e' spento la candidatura
    // resta comunque nel pannello. Il bot vuole un campo email, che non
    // si chiede piu'.
    try {
        require_once __DIR__ . '/../discord_notify.php';
        $photoPath = chisiamo_candidatura_foto_path($cPhoto);
        notifyDiscordCandidatura([
            'username' => $cUsername !== '' ? $cUsername : $cName,
            'user_id' => $cUserId,
            'email' => 'nessuna: si risponde nella posta del sito',
            'descrizione' => $cName . "\n\n" . $cDescription,
            'social_username' => $cSocialName,
            'social_link' => $cSocialLink,
            'attachment' => $photoPath !== null ? [
                'base64' => base64_encode((string)file_get_contents($photoPath)),
                'name' => 'candidatura.' . pathinfo($photoPath, PATHINFO_EXTENSION),
            ] : null,
        ]);
    } catch (Throwable $e) {
        error_log('[candidatura discord] ' . $e->getMessage());
    }

    $_SESSION['chisiamo_candidatura'] = ['type' => 'success', 'key' => 'apply_ok'];
    header('Location: ' . $cSelf);
    exit();
}

$cFlash = $_SESSION['chisiamo_candidatura'] ?? null;
unset($_SESSION['chisiamo_candidatura']);
$cOld = is_array($cFlash['old'] ?? null) ? $cFlash['old'] : [];
$cPending = $cReady && chisiamo_candidatura_pending($mysqli, $cUserId);
$cAlert = null;
if ($cFlash && isset($S[$cFlash['key'] ?? ''])) {
    $cAlert = ['type' => $cFlash['type'], 'text' => $S[$cFlash['key']]];
} elseif ($cPending) {
    $cAlert = ['type' => 'info', 'text' => $S['apply_pending']];
}
$cAlertIcon = ['success' => 'fa-circle-check', 'error' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
$cOldValue = static fn(string $key, string $fallback = ''): string => shop_h($cOld[$key] ?? $fallback);
?>
<!DOCTYPE html>
<html lang="<?php echo shop_h($cLang); ?>"<?php echo cripsum_theme_html_attr(); ?>>

<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title><?php echo shop_h('Cripsum™ - ' . $S['apply_title'] . ' ' . $S['page_title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo shop_h(cripsum_asset('/assets/forms/forms.css')); ?>">
    <?php cripsum_theme_head(); ?>
    <script src="<?php echo shop_h(cripsum_asset('/assets/forms/forms.js')); ?>" defer></script>
</head>

<body class="form-page">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>

    <main class="form-shell form-shell--medium">
        <section class="form-card form-reveal">
            <div class="form-card__header">
                <h1><?php echo shop_h($S['apply_title']); ?></h1>
                <p><?php echo shop_h($S['apply_intro']); ?></p>
            </div>

            <?php if ($cAlert): ?>
                <div class="form-alert form-alert--<?php echo shop_h($cAlert['type']); ?>" role="<?php echo $cAlert['type'] === 'error' ? 'alert' : 'status'; ?>">
                    <i class="fa-solid <?php echo shop_h($cAlertIcon[$cAlert['type']] ?? 'fa-circle-info'); ?>" aria-hidden="true"></i>
                    <span><?php echo shop_h($cAlert['text']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$cPending && ($cFlash['type'] ?? '') !== 'success'): ?>
                <form method="POST" action="<?php echo shop_h($cSelf); ?>" enctype="multipart/form-data" id="candidaturaForm" data-form-loading>
                    <?php echo csrf_field(); ?>

                    <label class="form-field">
                        <span><?php echo shop_h($S['apply_name']); ?></span>
                        <input type="text" name="nome" value="<?php echo $cOldValue('nome', $cUsername); ?>" maxlength="60" required>
                    </label>

                    <label class="form-field">
                        <span><?php echo shop_h($S['apply_description']); ?></span>
                        <textarea name="descrizione" placeholder="<?php echo shop_h($S['apply_description_placeholder']); ?>" rows="4" maxlength="700" required><?php echo $cOldValue('descrizione'); ?></textarea>
                        <small><?php echo shop_h($S['apply_description_hint']); ?></small>
                    </label>

                    <label class="form-field">
                        <span><?php echo shop_h($S['apply_photo']); ?></span>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required>
                        <small><?php echo shop_h($S['apply_photo_hint']); ?></small>
                    </label>

                    <div class="form-grid form-grid--2">
                        <label class="form-field">
                            <span><?php echo shop_h($S['apply_social_name']); ?></span>
                            <input type="text" name="social_username" value="<?php echo $cOldValue('social_username'); ?>" maxlength="80" placeholder="<?php echo shop_h($S['optional']); ?>">
                        </label>

                        <label class="form-field">
                            <span><?php echo shop_h($S['apply_social_link']); ?></span>
                            <input type="url" name="social_link" value="<?php echo $cOldValue('social_link'); ?>" maxlength="255" placeholder="https://...">
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="form-btn form-btn--primary form-btn--wide" type="submit" data-loading-text="<?php echo shop_h($S['apply_sending']); ?>">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            <span><?php echo shop_h($S['apply_send']); ?></span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="form-links">
                <a href="/<?php echo shop_h($cLang); ?>/chisiamo"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php echo shop_h($S['apply_back']); ?></a>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../' . ($cLang === 'en' ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
