<?php
declare(strict_types=1);

/**
 * Tema del sito.
 *
 * Oltre al tema di sempre ("classic") esiste un tema nuovo, sperimentale, che
 * si accende dalle impostazioni. La scelta sta in un cookie e non nel database:
 * e' una preferenza di come si vuole vedere il sito su questo dispositivo, e
 * cosi' non serve nessuna migrazione per provarlo o per toglierlo.
 *
 * Il tema nuovo arriva una pagina alla volta. Una pagina che e' gia' stata
 * portata lo dichiara in due punti: stampa cripsum_theme_html_attr() dentro il
 * tag <html>, e chiama cripsum_theme_head() nell'<head> subito dopo
 * l'include di head-import.php.
 *
 * Le pagine non ancora portate non fanno niente e restano com'erano, anche per
 * chi ha il tema nuovo acceso: tutte le regole del tema sono scritte sotto
 * html[data-theme="next"], quindi senza quell'attributo non toccano nulla.
 */

const CRIPSUM_THEME_COOKIE = 'cripsum_theme';
const CRIPSUM_THEME_CLASSIC = 'classic';
const CRIPSUM_THEME_NEXT = 'next';

/** Un anno: e' una preferenza, non una sessione. */
const CRIPSUM_THEME_COOKIE_LIFETIME = 31536000;

function cripsum_theme(): string
{
    return ($_COOKIE[CRIPSUM_THEME_COOKIE] ?? '') === CRIPSUM_THEME_NEXT
        ? CRIPSUM_THEME_NEXT
        : CRIPSUM_THEME_CLASSIC;
}

function cripsum_theme_is_next(): bool
{
    return cripsum_theme() === CRIPSUM_THEME_NEXT;
}

/**
 * Salva la scelta. Va chiamata prima di stampare qualsiasi cosa, come ogni
 * setcookie(). Aggiorna anche $_COOKIE, cosi' la pagina che ha appena salvato
 * mostra gia' lo stato nuovo senza aspettare la richiesta successiva.
 */
function cripsum_theme_set(string $theme): bool
{
    $theme = $theme === CRIPSUM_THEME_NEXT ? CRIPSUM_THEME_NEXT : CRIPSUM_THEME_CLASSIC;

    if (headers_sent()) {
        return false;
    }

    // $isHttps lo calcola config/session_init.php, con le stesse regole del
    // cookie di sessione.
    $secure = !empty($GLOBALS['isHttps']);

    $saved = setcookie(CRIPSUM_THEME_COOKIE, $theme, [
        'expires' => $theme === CRIPSUM_THEME_NEXT ? time() + CRIPSUM_THEME_COOKIE_LIFETIME : time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if ($saved) {
        if ($theme === CRIPSUM_THEME_NEXT) {
            $_COOKIE[CRIPSUM_THEME_COOKIE] = $theme;
        } else {
            unset($_COOKIE[CRIPSUM_THEME_COOKIE]);
        }
    }

    return $saved;
}

/**
 * Azione del pannello "Aspetto" delle impostazioni. Stessa forma di
 * rewind_settings_handle_post(): null se l'azione non e' sua, altrimenti
 * l'esito con il messaggio da mostrare.
 *
 * @return array{ok:bool, message:string}|null
 */
function cripsum_theme_settings_handle_post(string $action, bool $isEn = false): ?array
{
    if ($action !== 'update_appearance') {
        return null;
    }

    $wantsNext = !empty($_POST['theme_next']);
    $ok = cripsum_theme_set($wantsNext ? CRIPSUM_THEME_NEXT : CRIPSUM_THEME_CLASSIC);

    if (!$ok) {
        return [
            'ok' => false,
            'message' => $isEn ? 'Could not save. Try again.' : 'Non è stato possibile salvare. Riprova.',
        ];
    }

    return [
        'ok' => true,
        'message' => $wantsNext
            ? ($isEn ? 'New theme turned on. Open the homepage to see it.' : 'Tema nuovo attivato. Apri la homepage per vederlo.')
            : ($isEn ? 'Back to the classic theme.' : 'Sei tornato al tema classico.'),
    ];
}

/** Attributo da stampare dentro il tag <html> delle pagine gia' portate. */
function cripsum_theme_html_attr(): string
{
    return cripsum_theme_is_next() ? ' data-theme="next"' : '';
}

/**
 * Fogli di stile del tema nuovo, da stampare nell'<head> dopo head-import.php
 * (devono arrivare dopo style.css e style-dark.css, che sovrascrivono).
 */
function cripsum_theme_head(): void
{
    if (!cripsum_theme_is_next()) {
        return;
    }

    $css = function_exists('cripsum_asset') ? cripsum_asset('/assets/theme-next/theme.css') : '/assets/theme-next/theme.css';
    ?>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400..600;1,14..32,500&amp;display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">
    <?php
}
