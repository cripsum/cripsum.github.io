<?php

/**
 * Cripsum™ — Cripsum Rewind
 * Pagina a sé, raggiungibile su /it/rewind e /en/rewind.
 *
 * È una presa di schermo intera: niente navbar, niente footer. Il racconto
 * deve stare tutto dentro il riquadro, come in una storia.
 */

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/rewind_helpers.php';

$lang = str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '/en/') ? 'en' : 'it';
$isEn = $lang === 'en';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $isEn
        ? 'You need to be logged in to see your Rewind.'
        : 'Per vedere il tuo Rewind devi essere loggato.';
    header('Location: accedi');
    exit();
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

// Accesso anticipato: finché il Rewind non è aperto a tutti, la pagina
// risponde solo allo staff. Le statistiche intanto continuano a essere
// raccolte per chiunque, così all'apertura non sarà vuota.
if (!rewind_user_can_view()) {
    $_SESSION['error_message'] = rewind_locked_message($lang);
    header('Location: /' . $lang . '/home');
    exit();
}

$pageTitle = 'Cripsum Rewind';
$ogDescription = $isEn
    ? 'Your year on Cripsum, in one story.'
    : 'Il tuo anno su Cripsum, raccontato in una storia.';

function rw_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ — <?php echo rw_h($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="description" content="<?php echo rw_h($ogDescription); ?>">
    <meta name="theme-color" content="#05070d">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo rw_h($pageTitle); ?> — Cripsum™">
    <meta property="og:description" content="<?php echo rw_h($ogDescription); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="robots" content="noindex">

    <link rel="stylesheet" href="<?php echo rewind_asset('/assets/rewind/rewind.css'); ?>">
    <script>
        window.CRIPSUM_LANG = '<?php echo $lang; ?>';
        window.CRIPSUM_CSRF = '<?php echo rw_h(function_exists('csrf_token') ? csrf_token() : ''); ?>';
    </script>
    <script src="<?php echo rewind_asset('/assets/rewind/rewind.js'); ?>" defer></script>
</head>

<body class="rw-page">

    <!-- Schermata di attesa: sostituita o riempita di errori dal JS. -->
    <div class="rw-boot" data-rw-boot>
        <div>
            <div class="rw-boot__spinner" aria-hidden="true"></div>
            <p class="rw-boot__text"><?php echo $isEn ? 'Replaying your year...' : 'Sto ripercorrendo il tuo anno...'; ?></p>
        </div>
    </div>

    <main class="rw-stage" data-rw-root>
        <div class="rw-bg-layer" data-rw-bg>
            <span class="rw-orb rw-orb--a" aria-hidden="true"></span>
            <span class="rw-orb rw-orb--b" aria-hidden="true"></span>
        </div>
        <div class="rw-grain" aria-hidden="true"></div>

        <div class="rw-progress" data-rw-progress aria-hidden="true"></div>

        <div class="rw-topbar">
            <span class="rw-topbar__brand">
                <i class="fa-solid fa-clock-rotate-left"></i> Cripsum Rewind
            </span>
            <!-- Il controllo audio viene montato qui dal JS: se la
                 musica non e' disponibile lo slot resta vuoto. -->
            <div class="rw-topbar__actions" data-rw-audio-slot>
                <a class="rw-iconbtn" href="/<?php echo $lang; ?>/home"
                   aria-label="<?php echo $isEn ? 'Close' : 'Chiudi'; ?>">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>
        </div>

        <!-- Le zone laterali coprono l'intera altezza e stanno sotto ai
             contenuti interattivi: un pulsante dentro una schermata resta
             cliccabile perché il suo z-index è più alto. -->
        <button type="button" class="rw-tap rw-tap--prev" data-rw-prev
                aria-label="<?php echo $isEn ? 'Previous' : 'Precedente'; ?>"></button>
        <button type="button" class="rw-tap rw-tap--next" data-rw-next
                aria-label="<?php echo $isEn ? 'Next' : 'Successiva'; ?>"></button>

        <div class="rw-slides" data-rw-slides></div>

        <p class="rw-hint" data-rw-hint>
            <?php echo $isEn ? 'Tap or use the arrows to continue' : 'Tocca o usa le frecce per continuare'; ?>
        </p>
    </main>

</body>

</html>
