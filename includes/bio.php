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
 * Il nome di un profilo nell'indirizzo di bio: l'alias se c'e', altrimenti lo
 * username. bio.cripsum.com/<nome> li cerca nello stesso ordine.
 */
function cripsum_bio_profile_name(array $profile): string
{
    $alias = trim((string)($profile['custom_alias'] ?? ''));

    return $alias !== '' ? $alias : strtolower((string)($profile['username'] ?? ''));
}
