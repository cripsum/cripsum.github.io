<?php
declare(strict_types=1);

/**
 * Client condiviso per le chiamate dal sito al Presence Bot (api.cripsum.com).
 *
 * Gli endpoint POST del bot (/v1/tickets, /v1/tickets/reply, /v1/candidature,
 * /v1/logs) richiedono la stessa chiave che il bot usa per parlare con
 * /api/bot/*, inviata nell'header X-Cripsum-Bot-Key. Passare da qui evita di
 * dimenticare l'header in uno dei punti di chiamata.
 */

require_once __DIR__ . '/../config/discord_oauth.php';

if (!function_exists('cripsum_bot_endpoint')) {
    /**
     * URL completo di un endpoint del bot, con fallback sul dominio pubblico.
     */
    function cripsum_bot_endpoint(string $path = ''): string
    {
        $base = defined('CRIPSUM_BOT_ENDPOINT')
            ? rtrim((string)CRIPSUM_BOT_ENDPOINT, '/')
            : 'https://api.cripsum.com';

        if ($base === '') {
            $base = 'https://api.cripsum.com';
        }

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('cripsum_bot_headers')) {
    /**
     * Header cURL per una richiesta autenticata al bot.
     *
     * Se la chiave non è configurata l'header viene omesso: il bot risponderà
     * 401 e il chiamante registrerà l'errore, invece di inviare una stringa
     * vuota che sembrerebbe una chiave valida.
     *
     * @param string[] $extra
     * @return string[]
     */
    function cripsum_bot_headers(array $extra = []): array
    {
        $headers = ['Content-Type: application/json'];
        $key = defined('CRIPSUM_BOT_API_KEY') ? trim((string)CRIPSUM_BOT_API_KEY) : '';

        if ($key !== '') {
            $headers[] = 'X-Cripsum-Bot-Key: ' . $key;
        }

        return array_merge($headers, $extra);
    }
}
