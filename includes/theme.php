<?php
declare(strict_types=1);

/**
 * Tema del sito.
 *
 * Il tema del sito e' quello nuovo ("next"). Quello di prima ("classic") resta
 * disponibile ancora per un po': chi lo preferisce lo sceglie dalle
 * impostazioni. La scelta sta in un cookie e non nel database: e' una
 * preferenza di come si vuole vedere il sito su questo dispositivo, e cosi'
 * non serve nessuna migrazione per tenerla o per toglierla.
 *
 * Fino al passo 1 della chiusura era il contrario: classico per tutti, nuovo
 * solo per chi lo accendeva. Chi ha ancora nel browser il cookie "next" di
 * allora continua a vedere il tema nuovo, come tutti.
 *
 * Il tema nuovo arriva una pagina alla volta. Una pagina che e' gia' stata
 * portata lo dichiara in due punti: stampa cripsum_theme_html_attr() dentro il
 * tag <html>, e chiama cripsum_theme_head() nell'<head> subito dopo
 * l'include di head-import.php.
 *
 * Le pagine che non lo dichiarano (profili, editor del profilo, apertura
 * della Lootbox) restano com'erano per tutti: tutte le regole del tema sono
 * scritte sotto html[data-theme="next"], quindi senza quell'attributo non
 * toccano nulla.
 */

const CRIPSUM_THEME_COOKIE = 'cripsum_theme';
const CRIPSUM_THEME_CLASSIC = 'classic';
const CRIPSUM_THEME_NEXT = 'next';

/** Un anno: e' una preferenza, non una sessione. */
const CRIPSUM_THEME_COOKIE_LIFETIME = 31536000;

function cripsum_theme(): string
{
    // Classico solo per chi lo ha scelto: senza cookie, o con quello vecchio
    // "next", il tema e' quello nuovo. Vale anche per chi non e' loggato.
    return ($_COOKIE[CRIPSUM_THEME_COOKIE] ?? '') === CRIPSUM_THEME_CLASSIC
        ? CRIPSUM_THEME_CLASSIC
        : CRIPSUM_THEME_NEXT;
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
    $theme = $theme === CRIPSUM_THEME_CLASSIC ? CRIPSUM_THEME_CLASSIC : CRIPSUM_THEME_NEXT;

    if (headers_sent()) {
        return false;
    }

    // $isHttps lo calcola config/session_init.php, con le stesse regole del
    // cookie di sessione.
    $secure = !empty($GLOBALS['isHttps']);

    // Il tema nuovo e' quello di partenza: per tornarci basta togliere il
    // cookie. Si salva solo la scelta del classico.
    $saved = setcookie(CRIPSUM_THEME_COOKIE, $theme, [
        'expires' => $theme === CRIPSUM_THEME_CLASSIC ? time() + CRIPSUM_THEME_COOKIE_LIFETIME : time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if ($saved) {
        if ($theme === CRIPSUM_THEME_CLASSIC) {
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

    $wantsClassic = !empty($_POST['theme_classic']);
    $ok = cripsum_theme_set($wantsClassic ? CRIPSUM_THEME_CLASSIC : CRIPSUM_THEME_NEXT);

    if (!$ok) {
        return [
            'ok' => false,
            'message' => $isEn ? 'Could not save. Try again.' : 'Non è stato possibile salvare. Riprova.',
        ];
    }

    return [
        'ok' => true,
        'message' => $wantsClassic
            ? ($isEn ? 'Classic theme turned on for this browser.' : 'Tema classico attivato su questo browser.')
            : ($isEn ? 'Back to the site theme.' : 'Sei tornato al tema del sito.'),
    ];
}

/** Attributo da stampare dentro il tag <html> delle pagine gia' portate. */
function cripsum_theme_html_attr(): string
{
    return cripsum_theme_is_next() ? ' data-theme="next"' : '';
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
 * Fogli di stile del tema nuovo, da stampare nell'<head> dopo head-import.php
 * (devono arrivare dopo style.css e style-dark.css, che sovrascrivono).
 *
 * Oltre a theme.css, comune a tutte le pagine, stampa i fogli delle pagine
 * chiesti per nome: cripsum_theme_head('auth') aggiunge
 * assets/theme-next/pages/auth.css. Sono fogli di sole correzioni a quello
 * che la pagina carica gia' di suo.
 */
function cripsum_theme_head(string ...$pages): void
{
    if (!cripsum_theme_is_next()) {
        return;
    }

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
