<?php
declare(strict_types=1);

/**
 * Riconoscimento delle chiamate fra il sito e il Presence Bot.
 *
 * Fino a ieri bastava la chiave condivisa (X-Cripsum-Bot-Key): un valore fisso
 * che viaggia in ogni richiesta, e che chi lo legge una volta puo' usare per
 * sempre e per qualsiasi cosa. La firma invece non trasmette il segreto: prova
 * che chi chiama lo conosce, e vale solo per quella richiesta (metodo,
 * indirizzo, corpo) e solo per pochi minuti.
 *
 * Costanti lette da config/discord_oauth.php, tutte facoltative:
 *
 *   CRIPSUM_BOT_SIGNING_SECRET     con cui firma il bot quando chiama /api/bot/*
 *   CRIPSUM_SITE_SIGNING_SECRET    con cui firma il sito quando chiama il bot
 *   CRIPSUM_BOT_REQUIRE_SIGNATURE  true = la sola chiave condivisa non basta piu'
 *
 * Gli stessi due segreti vanno nel .env del bot, con gli stessi nomi. Finche'
 * mancano tutto funziona come prima, con la chiave condivisa.
 *
 * Lo stesso calcolo sta in presence-bot/src/signing.js: se cambia qui deve
 * cambiare la'.
 */

if (!defined('CRIPSUM_BOT_SIGNATURE_VERSION')) {
    define('CRIPSUM_BOT_SIGNATURE_VERSION', 'v1');
}

if (!defined('CRIPSUM_BOT_SIGNATURE_MAX_SKEW')) {
    /** Quanto puo' essere vecchia (o in anticipo) una richiesta firmata, in secondi. */
    define('CRIPSUM_BOT_SIGNATURE_MAX_SKEW', 300);
}

if (!function_exists('cripsum_linked_discord_sql')) {
    /**
     * Espressione SQL per l'ID Discord di un utente, valida solo se l'account
     * e' stato collegato con l'autorizzazione di Discord.
     *
     * Nella colonna discord_id finiscono due cose diverse: l'ID confermato
     * da Discord, e quello che chi non collega l'account puo' scrivere a mano
     * nell'editor del profilo per mostrare la propria attivita'. Il secondo e'
     * solo una dichiarazione: chiunque puo' scrivere l'ID di un altro. Vale
     * per disegnare il profilo, non per decidere chi e' quella persona su
     * Discord — cioe' per tutto quello che fa il bot: comandi, menzioni,
     * ingressi nei thread dei ticket, premi, ruoli.
     *
     * Solo il collegamento vero scrive anche discord_username, ed e' da li'
     * che si distinguono i due casi. Da usare al posto della colonna:
     *
     *   'SELECT ' . cripsum_linked_discord_sql('u') . ' AS discord_id FROM utenti u …'
     */
    function cripsum_linked_discord_sql(string $alias = ''): string
    {
        // L'alias finisce dentro la query: solo lettere, mai un valore esterno.
        $prefix = preg_match('/^[a-z_]{1,20}$/i', $alias) ? $alias . '.' : '';

        return 'IF(COALESCE(LENGTH(' . $prefix . 'discord_username), 0) > 0, ' . $prefix . 'discord_id, NULL)';
    }
}

if (!function_exists('cripsum_bot_config_string')) {
    /**
     * Valore di una costante di configurazione, o stringa vuota se manca.
     */
    function cripsum_bot_config_string(string $constant): string
    {
        return defined($constant) ? trim((string)constant($constant)) : '';
    }
}

if (!function_exists('cripsum_bot_signature_required')) {
    /**
     * Vero quando la sola chiave condivisa non basta piu'.
     */
    function cripsum_bot_signature_required(): bool
    {
        return defined('CRIPSUM_BOT_REQUIRE_SIGNATURE') && CRIPSUM_BOT_REQUIRE_SIGNATURE === true;
    }
}

if (!function_exists('cripsum_bot_signature')) {
    /**
     * Firma di una richiesta: HMAC-SHA256 su versione, orario, numero di
     * richiesta, metodo, indirizzo e impronta del corpo.
     */
    function cripsum_bot_signature(
        string $secret,
        string $timestamp,
        string $nonce,
        string $method,
        string $path,
        string $body
    ): string {
        $canonical = implode("\n", [
            CRIPSUM_BOT_SIGNATURE_VERSION,
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            hash('sha256', $body),
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }
}

if (!function_exists('cripsum_bot_signature_headers')) {
    /**
     * Intestazioni di firma per una richiesta dal sito al bot.
     *
     * @return string[] vuoto se il segreto non e' configurato
     */
    function cripsum_bot_signature_headers(string $method, string $path, string $body): array
    {
        $secret = cripsum_bot_config_string('CRIPSUM_SITE_SIGNING_SECRET');
        if ($secret === '') {
            return [];
        }

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));

        return [
            'X-Cripsum-Timestamp: ' . $timestamp,
            'X-Cripsum-Nonce: ' . $nonce,
            'X-Cripsum-Signature: ' . CRIPSUM_BOT_SIGNATURE_VERSION . '='
                . cripsum_bot_signature($secret, $timestamp, $nonce, $method, $path, $body),
        ];
    }
}

if (!function_exists('cripsum_bot_check_signature')) {
    /**
     * Controlla la firma del bot sulla richiesta in corso.
     *
     * Il motivo del rifiuto serve a chi configura, non a chi chiama: esce
     * solo verso un chiamante gia' riconosciuto (vedi sotto).
     *
     * @return array{ok: bool, reason: string}
     */
    function cripsum_bot_check_signature(string $secret): array
    {
        $header = (string)($_SERVER['HTTP_X_CRIPSUM_SIGNATURE'] ?? '');
        $timestamp = (string)($_SERVER['HTTP_X_CRIPSUM_TIMESTAMP'] ?? '');
        $nonce = (string)($_SERVER['HTTP_X_CRIPSUM_NONCE'] ?? '');

        $parts = explode('=', $header, 2);
        $provided = $parts[1] ?? '';

        if ($parts[0] !== CRIPSUM_BOT_SIGNATURE_VERSION || !preg_match('/^[0-9a-f]{64}$/', $provided)) {
            return ['ok' => false, 'reason' => 'firma assente o in un formato sconosciuto'];
        }

        if (!preg_match('/^\d{9,11}$/', $timestamp)) {
            return ['ok' => false, 'reason' => 'orario assente'];
        }

        if (!preg_match('/^[0-9a-f]{16,64}$/', $nonce)) {
            return ['ok' => false, 'reason' => 'numero di richiesta assente'];
        }

        $skew = abs(time() - (int)$timestamp);
        if ($skew > CRIPSUM_BOT_SIGNATURE_MAX_SKEW) {
            return ['ok' => false, 'reason' => 'orario fuori finestra di ' . $skew . 's'];
        }

        $expected = cripsum_bot_signature(
            $secret,
            $timestamp,
            $nonce,
            (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string)($_SERVER['REQUEST_URI'] ?? ''),
            (string)file_get_contents('php://input')
        );

        if (!hash_equals($expected, $provided)) {
            return ['ok' => false, 'reason' => 'la firma non corrisponde'];
        }

        return ['ok' => true, 'reason' => ''];
    }
}

if (!function_exists('cripsum_bot_request_authentic')) {
    /**
     * Vero se la richiesta in corso arriva davvero dal bot.
     *
     * Due modi di farsi riconoscere: la firma, quando il segreto e'
     * configurato, e la chiave condivisa di sempre finche' la firma non e'
     * obbligatoria.
     *
     * Nel periodo di passaggio una firma sbagliata accompagnata dalla chiave
     * giusta passa lo stesso, ma la risposta lo dice nell'intestazione
     * X-Cripsum-Auth: il bot la legge e lo scrive nei suoi log. Cosi' un
     * errore di configurazione si vede prima di rendere la firma
     * obbligatoria, non dopo, quando i comandi avrebbero gia' smesso di
     * funzionare.
     */
    function cripsum_bot_request_authentic(): bool
    {
        $secret = cripsum_bot_config_string('CRIPSUM_BOT_SIGNING_SECRET');
        $required = cripsum_bot_signature_required();
        $signed = (string)($_SERVER['HTTP_X_CRIPSUM_SIGNATURE'] ?? '') !== '';
        $problem = '';

        if ($secret !== '' && $signed) {
            $check = cripsum_bot_check_signature($secret);

            if ($check['ok']) {
                if (!headers_sent()) {
                    header('X-Cripsum-Auth: signature');
                }
                return true;
            }

            $problem = $check['reason'];
        } elseif ($required) {
            $problem = $secret !== '' ? 'richiesta senza firma' : 'segreto di firma non configurato sul sito';
        }

        if ($required) {
            error_log('[Bot] Richiesta rifiutata su ' . ($_SERVER['REQUEST_URI'] ?? '') . ': ' . $problem);
            return false;
        }

        $expected = cripsum_bot_config_string('CRIPSUM_BOT_API_KEY');
        $provided = (string)($_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return false;
        }

        if (!headers_sent()) {
            // Il motivo esce solo qui, dopo che la chiave ha gia' detto chi sta
            // chiamando: a un estraneo non si spiega cosa non torna.
            header('X-Cripsum-Auth: key' . ($problem !== '' ? '; signature-problem="' . $problem . '"' : ''));
        }

        return true;
    }
}
