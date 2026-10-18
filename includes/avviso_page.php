<?php
declare(strict_types=1);

/**
 * La pagina d'avviso del sito: la 404 e gli avvisi dei profili che non si
 * possono vedere (privato, solo amici, solo utenti registrati).
 *
 * Una pagina sola, col tema del sito: la barra col logo, una scheda al centro
 * e i bottoni del sito. Gli avvisi dei profili prima erano la pagina del
 * profilo stesso, con i bagliori ai lati e i colori, il tema e lo sfondo di
 * chi lo possiede; ora sono uguali per tutti i profili e uguali alla 404.
 *
 * La usano 404.php e profile.php. Manda lei il codice HTTP: dopo la chiamata
 * non va stampato altro.
 */

require_once __DIR__ . '/theme.php';

/**
 * @param array $a
 *   - status   codice HTTP (404 se manca)
 *   - lang     'it' o 'en'
 *   - title    titolo della scheda del browser, dopo "Cripsum™ — "
 *   - number   il numero grande in cima (la 404), oppure
 *   - icon     un'icona Font Awesome nel riquadro in cima ('fa-lock')
 *   - headline, text
 *   - meme     ['src' => ..., 'alt' => ...], facoltativa
 *   - alert    la riga d'avviso in rosso, facoltativa
 *   - actions  [['href' => ..., 'icon' => ..., 'label' => ..., 'primary' => bool], ...]
 *   - og       l'anteprima dei link (vedi cripsum_og_print), facoltativa
 */
function cripsum_avviso_page(array $a): void
{
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $lang = ($a['lang'] ?? 'it') === 'en' ? 'en' : 'it';

    http_response_code((int)($a['status'] ?? 404));
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>"<?= cripsum_theme_html_attr() ?>>
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.2.0/css/all.min.css" />
        <link rel="stylesheet" href="/css/style.css" />
        <link rel="stylesheet" href="/css/style-dark.css" />
        <link rel="stylesheet" href="/assets/static/static.css?v=2.0-fuso" />
        <script src="/assets/static/static.js?v=1.0-static" defer></script>
        <title>Cripsum™ — <?= $h($a['title'] ?? '') ?></title>
        <?php
        if (!empty($a['og'])) {
            require_once __DIR__ . '/cripsum_og.php';
            cripsum_og_print($a['og']);
        }
        ?>

        <style>
            .err-shell {
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 2rem 1rem;
            }

            .err-card {
                width: 100%;
                max-width: 540px;
                padding: clamp(2rem, 6vw, 3.5rem) clamp(1.5rem, 5vw, 3rem);
                border-radius: var(--nx-radius);
                background: var(--nx-surface);
                text-align: center;
            }

            /* Il numero grande della 404. */
            .err-number {
                display: block;
                margin: 0 0 0.3rem;
                font-family: "Poppins", sans-serif;
                font-size: clamp(5.5rem, 20vw, 9rem);
                font-weight: 600;
                line-height: 0.85;
                letter-spacing: -0.04em;
                color: var(--nx-text);
            }

            /* Al posto del numero, negli avvisi dei profili. */
            .err-icon {
                display: inline-grid;
                place-items: center;
                width: 4rem;
                height: 4rem;
                margin-bottom: 1.1rem;
                border-radius: var(--nx-radius);
                background: var(--nx-surface-2);
                color: var(--nx-text);
                font-size: 1.55rem;
            }

            .err-headline {
                margin: 0.5rem 0 0.25rem;
                font-size: clamp(1.25rem, 4vw, 1.75rem);
                font-weight: 600;
                letter-spacing: -0.02em;
                color: var(--nx-text);
            }

            .err-sub {
                margin-bottom: 1.5rem;
                font-size: 0.95rem;
                line-height: 1.55;
                color: var(--nx-text-2);
            }

            .err-meme {
                display: block;
                width: clamp(100px, 38%, 160px);
                margin: 0 auto 1.75rem;
                border: 1px solid transparent;
                border-radius: 18px;
                animation: err-bob 3.8s ease-in-out infinite;
            }

            @keyframes err-bob {
                0%,
                100% {
                    transform: translateY(0) rotate(-1.5deg);
                }
                50% {
                    transform: translateY(-8px) rotate(1.5deg);
                }
            }

            /* L'avviso resta rosso: lo dice il testo, non il riquadro. */
            .err-alert {
                display: flex;
                gap: 0.65rem;
                align-items: center;
                margin-bottom: 1.5rem;
                padding: 0.75rem 1rem;
                border: 1px solid transparent;
                border-radius: 999px;
                background: var(--nx-surface-2);
                color: var(--nx-text-2);
                font-size: 0.82rem;
                font-weight: 500;
            }

            .err-alert i {
                flex-shrink: 0;
                color: var(--nx-danger);
            }

            .err-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.65rem;
                justify-content: center;
            }

            /* La barra in cima e' una versione ridotta della navbar: solo il logo.
               Prende la superficie del tema. Queste regole devono pesare piu' di
               quelle di css/style.css e style-dark.css, da qui il prefisso. */
            html[data-theme="next"] body.static-page > .navbarutenti {
                border-color: transparent;
                background: var(--nx-surface);
                box-shadow: none;
                -webkit-backdrop-filter: none;
                backdrop-filter: none;
            }

            /* Le regole della navbar che servono anche a questa versione ridotta. La navbar
               vera le ha nel suo foglio (css/navbar.css), che questa pagina non carica. */
            html[data-theme="next"] .navbarutenti .navbar-brand span {
                font-weight: 600;
                letter-spacing: -0.01em;
            }

            html[data-theme="next"] .navbar-brand:hover,
            html[data-theme="next"] .navbar-brand:hover img {
                transform: none;
                box-shadow: none;
            }
        </style>
        <?php cripsum_theme_head(); ?>
    </head>
    <body class="static-page">

        <nav class="navbarutenti navbar navbar-expand-xl">
            <div class="container-fluid">
                <a class="navbar-brand" href="/<?= $lang ?>/home">
                    <img src="/img/amongus-logo.jpg" alt="" height="40" style="border-radius: 4px" class="d-inline-block align-middle" />
                    <span class="align-middle ms-3 fw-bold" style="color: white">Cripsum™</span>
                </a>
            </div>
        </nav>

        <div class="err-shell">
            <div class="err-card static-reveal">
                <?php if (!empty($a['number'])): ?>
                    <span class="err-number"><?= $h($a['number']) ?></span>
                <?php elseif (!empty($a['icon'])): ?>
                    <span class="err-icon" aria-hidden="true"><i class="fa-solid <?= $h($a['icon']) ?>"></i></span>
                <?php endif; ?>

                <h1 class="err-headline"><?= $h($a['headline'] ?? '') ?></h1>
                <p class="err-sub"><?= $h($a['text'] ?? '') ?></p>

                <?php if (!empty($a['meme'])): ?>
                    <img src="<?= $h($a['meme']['src']) ?>" alt="<?= $h($a['meme']['alt'] ?? '') ?>" class="err-meme" />
                <?php endif; ?>

                <?php if (!empty($a['alert'])): ?>
                    <div class="err-alert">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span><?= $h($a['alert']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="err-actions">
                    <?php foreach ($a['actions'] ?? [] as $action): ?>
                        <a href="<?= $h($action['href']) ?>" class="static-btn<?= !empty($action['primary']) ? ' static-btn--primary' : '' ?>">
                            <i class="fa-solid <?= $h($action['icon']) ?>" aria-hidden="true"></i>
                            <span><?= $h($action['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </body>
</html>
<?php
}
