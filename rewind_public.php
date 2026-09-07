<?php

/**
 * Cripsum™ — Rewind pubblico
 *
 * Serve un Rewind condiviso tramite il suo token: cripsum.com/rewind/<token>
 *
 * Il payload passa da rewind_public_payload(), che toglie tutto ciò che non
 * riguarda solo chi condivide — il nome dell'amico più chattato, il saldo
 * Godos, il calendario giorno per giorno. Chi apre il link vede il racconto,
 * non la vita di qualcun altro.
 *
 * La pagina non richiede una sessione: il token è l'unica autorizzazione, ed
 * è revocabile in qualsiasi momento dal proprietario.
 */

require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/rewind_helpers.php';
require_once __DIR__ . '/includes/cripsum_og.php';

$token = (string)($_GET['token'] ?? '');
// Un link condiviso porta gia' il prefisso di lingua di chi lo ha creato
// (/it/rewind/... oppure /en/rewind/...). Per i link vecchi o accorciati
// che ne sono privi si ricade sulla preferenza del browser di chi apre,
// che e' comunque meglio dell'italiano d'ufficio.
$lang = rewind_request_lang();
$isEn = $lang === 'en';

$payload = null;
if ($token !== '') {
    $payload = rewind_load_shared($mysqli, $token);
}

// La sessione non serve a questa pagina: liberiamo subito il lock.
cripsum_release_session();

if ($payload === null) {
    http_response_code(404);
}

function rwp_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$displayName = (string)($payload['user']['display_name'] ?? '');
$personaName = (string)($payload['persona'][$isEn ? 'name_en' : 'name_it'] ?? '');
$hours = (int)($payload['time']['hours'] ?? 0);

$ogTitle = $payload
    ? ($isEn
        ? $displayName . ' — Cripsum Rewind'
        : 'Il Rewind di ' . $displayName)
    : 'Cripsum Rewind';

$ogDescription = $payload
    ? ($isEn
        ? sprintf('%s · %d hours on Cripsum this year.', $personaName, $hours)
        : sprintf('%s · %d ore su Cripsum quest\'anno.', $personaName, $hours))
    : ($isEn ? 'This Rewind is not available.' : 'Questo Rewind non è disponibile.');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo rwp_h($ogTitle); ?></title>
    <meta name="description" content="<?php echo rwp_h($ogDescription); ?>">
    <meta name="theme-color" content="#05070d">

    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo rwp_h($ogTitle); ?>">
    <meta property="og:description" content="<?php echo rwp_h($ogDescription); ?>">
    <meta property="og:url" content="<?php echo rwp_h(cripsum_og_current_url()); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php if ($payload !== null): ?>
        <?php
        // L'immagine di anteprima è generata da api/rewind/card.php. Se sul
        // server manca GD quell'endpoint reindirizza da solo all'immagine
        // statica, quindi il tag resta valido in ogni caso.
        $cardUrl = 'https://cripsum.com/api/rewind/card.php?token=' . rawurlencode($token) . '&lang=' . $lang;
        ?>
        <meta property="og:image" content="<?php echo rwp_h($cardUrl); ?>">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:image" content="<?php echo rwp_h($cardUrl); ?>">
    <?php endif; ?>

    <!-- Un Rewind condiviso è personale: non deve finire nei motori di ricerca. -->
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="/img/Susremaster.png" type="image/png">
    <link href="https://fonts.googleapis.com/css?family=Poppins:400,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.2.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/rewind/rewind.css?v=2">

    <script>
        window.CRIPSUM_LANG = '<?php echo $lang; ?>';
        window.CRIPSUM_REWIND_PUBLIC = true;
        window.CRIPSUM_REWIND_DATA = <?php
            // JSON_HEX_* impedisce che una stringa nel payload chiuda il tag
            // <script> o apra markup, anche se un nome contenesse "</script>".
            echo $payload
                ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                : 'null';
        ?>;
    </script>
    <script src="/assets/rewind/rewind.js?v=2" defer></script>
</head>

<body class="rw-page">

<?php if ($payload === null): ?>

    <div class="rw-boot" style="position:fixed">
        <div>
            <p class="rw-title" style="font-size:1.5rem">
                <?php echo $isEn ? 'This Rewind is not available.' : 'Questo Rewind non è disponibile.'; ?>
            </p>
            <p class="rw-lead">
                <?php echo $isEn
                    ? 'The link may have been revoked by its owner.'
                    : 'Il link potrebbe essere stato revocato da chi lo ha creato.'; ?>
            </p>
            <div class="rw-actions">
                <a class="rw-btn" href="/<?php echo $lang; ?>/home">
                    <?php echo $isEn ? 'Go to Cripsum' : 'Vai su Cripsum'; ?>
                </a>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="rw-boot" data-rw-boot>
        <div>
            <div class="rw-boot__spinner" aria-hidden="true"></div>
            <p class="rw-boot__text"><?php echo $isEn ? 'Loading...' : 'Caricamento...'; ?></p>
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
                <i class="fa-solid fa-clock-rotate-left"></i>
                <?php echo rwp_h($displayName); ?>
            </span>
            <!-- Il controllo audio viene montato qui dal JS: se la
                 musica non e' disponibile lo slot resta vuoto. -->
            <div class="rw-topbar__actions" data-rw-audio-slot>
                <a class="rw-iconbtn" href="/<?php echo $lang; ?>/home" aria-label="Cripsum">
                    <i class="fa-solid fa-house"></i>
                </a>
            </div>
        </div>

        <button type="button" class="rw-tap rw-tap--prev" data-rw-prev
                aria-label="<?php echo $isEn ? 'Previous' : 'Precedente'; ?>"></button>
        <button type="button" class="rw-tap rw-tap--next" data-rw-next
                aria-label="<?php echo $isEn ? 'Next' : 'Successiva'; ?>"></button>

        <div class="rw-slides" data-rw-slides></div>

        <p class="rw-hint" data-rw-hint>
            <?php echo $isEn ? 'Tap to continue' : 'Tocca per continuare'; ?>
        </p>
    </main>

<?php endif; ?>

</body>

</html>
