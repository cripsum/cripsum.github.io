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
 * L'attributo data-theme="next" distingue queste pagine da quelle che devono
 * restare com'erano (profili, editor del profilo, apertura della Lootbox): le
 * regole comuni del tema, in assets/global/theme.css, sono scritte tutte sotto
 * html[data-theme="next"], e senza quell'attributo non toccano nulla. Le
 * regole di una pagina stanno invece nel foglio della pagina.
 */

/** Attributo da stampare dentro il tag <html>. */
function cripsum_theme_html_attr(): string
{
    return ' data-theme="next"';
}

/**
 * Indirizzo del foglio del tema, con la data del file come versione: cosi'
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
 * Il foglio comune del tema e i pesi del carattere che gli servono, da
 * stampare nell'<head> dopo head-import.php (deve arrivare dopo style.css e
 * style-dark.css, che sovrascrive) e dopo i fogli della pagina.
 */
function cripsum_theme_head(): void
{
    // head-import.php carica gia' Poppins 400: qui arrivano i pesi che al tema
    // servono in piu', cosi' i grassetti sono veri e non simulati dal browser.
    ?>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,500;0,600;1,500&amp;display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars(cripsum_theme_asset('/assets/global/theme.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php
}
