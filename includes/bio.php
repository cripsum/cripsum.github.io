<?php
declare(strict_types=1);

/**
 * bio.cripsum.com: lo stesso sito su un altro indirizzo.
 *
 * In hPanel il sottodominio usa la stessa cartella del sito (public_html),
 * quindi gli stessi file rispondono a cripsum.com e a bio.cripsum.com. Qui si
 * decide su quale dei due siamo e si costruiscono gli indirizzi verso l'uno e
 * l'altro: le pagine non leggono HTTP_HOST per conto loro. Cosa si apre su bio
 * lo decide .htaccess, nel blocco «bio.cripsum.com».
 *
 * Finche' su Cloudflare restano le due regole di redirect di bio, a
 * bio.cripsum.com non arriva niente: si prova su bio-prova.cripsum.com (stessa
 * cartella in hPanel, record CNAME su Cloudflare senza regole) e in locale su
 * bio.localhost.
 */

/** Gli host che sono bio. */
const CRIPSUM_BIO_HOSTS = ['bio.cripsum.com', 'bio-prova.cripsum.com', 'bio.localhost'];

/*
 * I due interruttori dell'apertura (fase 5 del piano, 18 ottobre 2026).
 *
 * ATTENZIONE all'ordine. Con questi a true, cripsum.com manda i profili su
 * bio.cripsum.com: se su Cloudflare ci sono ancora le due regole che rimandano
 * bio.cripsum.com a cripsum.com, i profili con un alias girano in tondo.
 * Quindi: prima si tolgono le regole su Cloudflare, poi si carica questo file
 * con true. Per tornare indietro, al contrario: prima false qui, poi le regole.
 */

/**
 * bio.cripsum.com e' aperto a tutti: il sito ci manda la gente (la voce «La
 * tua pagina» nel menu dell'account). Con false la voce c'e' solo quando si e'
 * gia' su bio.
 */
const CRIPSUM_BIO_OPEN = true;

/**
 * I profili vivono su bio: l'indirizzo vero di un profilo e'
 * bio.cripsum.com/<alias o username>; cripsum.com/u/<nome>, /<alias> e gli
 * altri vecchi indirizzi portano li' con un 301, l'editor di cripsum.com porta
 * a bio.cripsum.com/editor, e anteprime dei link e canonical usano bio.
 * Rimetterlo a false riporta i profili su cripsum.com/u/ senza toccare altro.
 */
const CRIPSUM_BIO_PROFILES = true;

// bio-prova serve solo a provare: che i motori di ricerca non lo indicizzino.
if (PHP_SAPI !== 'cli' && !headers_sent() && cripsum_request_host() === 'bio-prova.cripsum.com') {
    header('X-Robots-Tag: noindex, nofollow');
}

/**
 * L'indirizzo pieno di un profilo, quello da linkare e condividere: su bio se
 * i profili vivono li' o se siamo gia' su bio, altrimenti cripsum.com/u/.
 * Basta lo username; con l'alias si evita un passaggio.
 */
function cripsum_profile_url(array $profile): string
{
    if (CRIPSUM_BIO_PROFILES || cripsum_site() === 'bio') {
        return cripsum_bio_url(rawurlencode(cripsum_bio_profile_name($profile)));
    }

    return cripsum_main_url('u/' . rawurlencode(strtolower((string)($profile['username'] ?? ''))));
}

/**
 * Dove cripsum.com manda un profilo col 301: l'indirizzo vero con una barra in
 * fondo (bio.cripsum.com/<nome>/).
 *
 * Il 17 e 18 ottobre 2026 su Cloudflare c'erano due regole 301 che portavano
 * bio.cripsum.com/<x> su cripsum.com/<x>, e i browser che le hanno viste se le
 * ricordano. Mandandoli su bio.cripsum.com/<nome> esatto, il browser tornerebbe
 * su cripsum.com/<nome> senza chiedere niente al server, e cripsum.com lo
 * rimanderebbe su bio: un giro senza fine. Con la barra l'indirizzo e' uno mai
 * visto; bio lo accetta e profile.php toglie la barra dalla barra degli
 * indirizzi senza ricaricare.
 */
function cripsum_profile_redirect_url(array $profile): string
{
    return cripsum_profile_url($profile) . '/';
}

/** L'host da mostrare davanti al nome di un profilo («bio.cripsum.com/» o «cripsum.com/u/»). */
function cripsum_profile_url_prefix(): string
{
    if (CRIPSUM_BIO_PROFILES || cripsum_site() === 'bio') {
        return (string)parse_url(cripsum_bio_url(), PHP_URL_HOST) . '/';
    }

    return (string)parse_url(cripsum_main_url(), PHP_URL_HOST) . '/u/';
}

/** Dove porta «La tua pagina» nel menu dell'account, o null se non va mostrata. */
function cripsum_bio_menu_url(): ?string
{
    if (cripsum_site() === 'bio') {
        return cripsum_bio_url('/');
    }

    return CRIPSUM_BIO_OPEN ? cripsum_bio_url('/') : null;
}

/** L'host della richiesta, minuscolo e senza porta. */
function cripsum_request_host(): string
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));

    return (string)preg_replace('/:\d+$/', '', $host);
}

/** 'bio' su bio.cripsum.com (e sui suoi host di prova), 'main' altrove. */
function cripsum_site(): string
{
    return in_array(cripsum_request_host(), CRIPSUM_BIO_HOSTS, true) ? 'bio' : 'main';
}

/**
 * Schema e host di uno dei due siti. In locale si resta in locale, sulla
 * stessa porta; su bio-prova si resta su bio-prova.
 */
function cripsum_site_origin(string $site): string
{
    $host = cripsum_request_host();

    if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.localhost')) {
        $port = preg_match('/:(\d+)$/', (string)($_SERVER['HTTP_HOST'] ?? ''), $m) ? ':' . $m[1] : '';

        return 'http://' . ($site === 'bio' ? 'bio.localhost' : 'localhost') . $port;
    }

    if ($site === 'bio') {
        return 'https://' . (cripsum_site() === 'bio' ? $host : 'bio.cripsum.com');
    }

    return 'https://cripsum.com';
}

function cripsum_bio_url(string $path = '/'): string
{
    return cripsum_site_origin('bio') . '/' . ltrim($path, '/');
}

function cripsum_main_url(string $path = '/'): string
{
    return cripsum_site_origin('main') . '/' . ltrim($path, '/');
}

/**
 * Un indirizzo su un host di bio preciso, da cripsum.com: quello da cui e'
 * partita la richiesta (bio-prova o bio). Solo host dell'elenco; un host
 * sconosciuto diventa bio.cripsum.com.
 */
function cripsum_bio_url_on(string $bioHost, string $path = '/'): string
{
    $bioHost = strtolower(trim($bioHost));
    if (!in_array($bioHost, CRIPSUM_BIO_HOSTS, true)) {
        $bioHost = 'bio.cripsum.com';
    }
    if ($bioHost === 'bio.localhost') {
        return cripsum_site_origin('bio') . '/' . ltrim($path, '/');
    }

    return 'https://' . $bioHost . '/' . ltrim($path, '/');
}

/**
 * Un percorso relativo di questo sito, buono per un redirect: inizia con una
 * sola "/", niente host, niente "\" o caratteri di controllo. Tutto il resto
 * diventa $fallback, cosi' un ?return= non puo' mandare nessuno altrove.
 */
function cripsum_safe_return_path(string $path, string $fallback = '/'): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || strlen($path) > 255) {
        return $fallback;
    }
    if (str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return $fallback;
    }

    return $path;
}

/** L'indizio per l'accesso automatico su bio (includes/bio_sso.php). */
const CRIPSUM_SSO_HINT_COOKIE = 'cripsum_li';

/** «Non riprovare l'accesso automatico per un po'», solo su bio. */
const CRIPSUM_BIO_NOAUTO_COOKIE = 'bio_noauto';

/**
 * Accende o spegne l'indizio «in questo browser c'e' probabilmente qualcuno
 * dentro su cripsum.com». Vale per cripsum.com e per tutti i sottodomini, cosi'
 * bio lo vede. Non contiene niente: non fa entrare nessuno, dice solo a bio
 * che vale la pena chiedere a cripsum.com. In locale non si puo' condividere
 * tra localhost e bio.localhost e resta sull'host.
 */
function cripsum_sso_hint_cookie(bool $on): void
{
    $host = cripsum_request_host();
    $shared = $host === 'cripsum.com' || str_ends_with($host, '.cripsum.com');

    setcookie(CRIPSUM_SSO_HINT_COOKIE, $on ? '1' : '', [
        'expires' => $on ? time() + 1209600 : time() - 3600,
        'path' => '/',
        'domain' => $shared ? '.cripsum.com' : '',
        'secure' => $shared,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if ($on) {
        $_COOKIE[CRIPSUM_SSO_HINT_COOKIE] = '1';
    } else {
        unset($_COOKIE[CRIPSUM_SSO_HINT_COOKIE]);
    }
}

/** Ferma l'accesso automatico su bio per dieci minuti (dopo un tentativo a vuoto o un'uscita). */
function cripsum_bio_noauto(): void
{
    setcookie(CRIPSUM_BIO_NOAUTO_COOKIE, '1', [
        'expires' => time() + 600,
        'path' => '/',
        'secure' => !empty(session_get_cookie_params()['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[CRIPSUM_BIO_NOAUTO_COOKIE] = '1';
}

/**
 * Il nome di un profilo nell'indirizzo di bio: l'alias se c'e', altrimenti lo
 * username. bio.cripsum.com/<nome> li cerca nello stesso ordine.
 */
function cripsum_bio_profile_name(array $profile): string
{
    $alias = trim((string)($profile['custom_alias'] ?? ''));

    return $alias !== '' ? $alias : strtolower((string)($profile['username'] ?? ''));
}
