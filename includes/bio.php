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

/**
 * bio.cripsum.com e' aperto a tutti? Finche' e' false, su Cloudflare ci sono
 * ancora le regole che rimandano bio.cripsum.com al profilo di cripsum, e il
 * sito non ci manda nessuno (la voce «La tua pagina» del menu c'e' solo
 * quando si e' gia' su bio, cioe' su bio-prova). Si mette a true il giorno in
 * cui si tolgono quelle regole (fase 5 del piano).
 */
const CRIPSUM_BIO_OPEN = false;

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
