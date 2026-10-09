<?php
declare(strict_types=1);

/**
 * Tema del sito.
 *
 * Il sito ha un tema solo. Per qualche giorno ce ne sono stati due, quello di
 * prima ("classico") e quello di adesso, e si sceglieva dalle impostazioni:
 * la scelta e' stata tolta. Il cookie cripsum_theme rimasto in qualche browser
 * non viene piu' letto, e scade da solo.
 *
 * Una pagina dichiara il tema in due punti: stampa cripsum_theme_html_attr()
 * dentro il tag <html>, e chiama cripsum_theme_head() nell'<head>, dopo
 * l'include di head-import.php e dopo i fogli suoi.
 *
 * L'attributo data-theme="next" serve ancora: i fogli in assets/theme-next/
 * sono correzioni ai fogli di prima, scritte tutte sotto
 * html[data-theme="next"]. Le pagine che non lo dichiarano (profili, editor
 * del profilo, apertura della Lootbox) restano com'erano: senza
 * quell'attributo le correzioni non toccano nulla.
 */

/** Attributo da stampare dentro il tag <html>. */
function cripsum_theme_html_attr(): string
{
    return ' data-theme="next"';
}

/**
 * Indirizzo di un foglio del tema, con la data del file come versione: cosi'
 * una modifica arriva a tutti senza dover alzare un numero a mano. Alcune
 * pagine (reset_password) non caricano functions.php, dove sta
 * cripsum_asset(): per loro la versione si calcola qui.
 */
function cripsum_theme_asset(string $path): string
{
    if (function_exists('cripsum_asset')) {
        return cripsum_asset($path);
    }

    $stamp = @filemtime(dirname(__DIR__) . $path);

    return $path . '?v=' . ($stamp !== false ? $stamp : '1');
}

/**
 * Fogli di stile del tema, da stampare nell'<head> dopo head-import.php
 * (devono arrivare dopo style.css e style-dark.css, che sovrascrivono).
 *
 * Oltre a theme.css, comune a tutte le pagine, stampa i fogli delle pagine
 * chiesti per nome: cripsum_theme_head('auth') aggiunge
 * assets/theme-next/pages/auth.css. Sono fogli di sole correzioni a quello
 * che la pagina carica gia' di suo.
 */
function cripsum_theme_head(string ...$pages): void
{
    // head-import.php carica gia' Poppins 400: qui arrivano i pesi che al tema
    // servono in piu', cosi' i grassetti sono veri e non simulati dal browser.
    $sheets = [cripsum_theme_asset('/assets/theme-next/theme.css')];
    foreach ($pages as $page) {
        if (preg_match('/^[a-z0-9-]+$/', $page)) {
            $sheets[] = cripsum_theme_asset('/assets/theme-next/pages/' . $page . '.css');
        }
    }
    ?>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,500;0,600;1,500&amp;display=swap">
    <?php foreach ($sheets as $sheet): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($sheet, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <?php
}
