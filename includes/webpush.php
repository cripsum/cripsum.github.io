<?php
/**
 * Cripsum™ — notifiche push del browser (Web Push).
 *
 * Una pagina aperta può mostrare notifiche solo finché il browser la lascia
 * lavorare: una scheda in secondo piano viene rallentata, su telefono
 * sospesa, e gli avvisi arrivavano tardi o mai. Qui il server manda un
 * segnale al servizio push del browser (di Google, Mozilla, Microsoft o
 * Apple, secondo il browser), che lo consegna al dispositivo anche a sito
 * chiuso; lì lo riceve /sw.js, che chiede al sito cosa mostrare.
 *
 * Il segnale non porta il contenuto: solo il numero dell'evento e l'id
 * dell'utente, cifrati per quel browser (RFC 8291). Titolo e testo li
 * chiede il dispositivo a api/notify/feed.php, con la sessione dell'utente:
 * gli stessi controlli degli avvisi in pagina.
 *
 * Niente librerie e niente database. Le iscrizioni dei dispositivi stanno
 * nel timbro dell'utente (includes/realtime.php), la coppia di chiavi del
 * sito (VAPID, RFC 8292) in un timbro a parte, creato al primo uso. Se
 * quella cartella viene svuotata nasce una chiave nuova e i browser si
 * reiscrivono da soli alla prossima visita.
 *
 * L'invio avviene a richiesta finita (wp_flush): chi scrive un messaggio
 * non aspetta i server di Google, e la connessione MySQL viene chiusa prima.
 */

require_once __DIR__ . '/realtime.php';

if (!defined('CRIPSUM_WP_MAX_SUBS')) {
    define('CRIPSUM_WP_MAX_SUBS', 6);         // dispositivi per utente
    define('CRIPSUM_WP_TTL', 43200);          // il servizio push riprova per dodici ore
    define('CRIPSUM_WP_SUBJECT', 'https://cripsum.com');
}

if (!function_exists('wp_available')) {

    function wp_b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    function wp_unb64(string $text): string
    {
        $raw = base64_decode(strtr($text, '-_', '+/'), true);
        return is_string($raw) ? $raw : '';
    }

    function wp_available(): bool
    {
        return rt_dir() !== ''
            && function_exists('openssl_pkey_derive')
            && function_exists('curl_multi_init')
            && function_exists('hash_hkdf');
    }

    /** Punto pubblico P-256 (65 byte, non compresso) di una chiave openssl. */
    function wp_public_point($key): string
    {
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (!is_array($details) || empty($details['ec']['x']) || empty($details['ec']['y'])) {
            return '';
        }
        return "\x04"
            . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** Chiave pubblica openssl a partire dal punto che manda il browser. */
    function wp_point_to_key(string $point)
    {
        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            return false;
        }
        // Intestazione fissa di una chiave pubblica P-256 (SubjectPublicKeyInfo).
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($pem);
    }

    function wp_new_key()
    {
        return openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    }

    /**
     * La coppia di chiavi con cui il sito si presenta ai servizi push.
     * ['pem' => chiave privata, 'public' => punto pubblico in base64url],
     * oppure null se qui il push non si può usare.
     */
    function wp_keys(): ?array
    {
        static $keys = false;
        if ($keys !== false) {
            return $keys;
        }
        if (!wp_available()) {
            return $keys = null;
        }

        $stored = rt_read('vapid');
        if (!empty($stored['pem']) && !empty($stored['public'])) {
            return $keys = ['pem' => (string)$stored['pem'], 'public' => (string)$stored['public']];
        }

        // Creata sotto blocco: due richieste insieme non devono generare due
        // chiavi diverse, o metà dei browser si iscriverebbe a quella sbagliata.
        $data = rt_update('vapid', static function (array $data): ?array {
            if (!empty($data['pem']) && !empty($data['public'])) {
                return null;
            }
            $key = wp_new_key();
            $pem = '';
            if (!$key || !openssl_pkey_export($key, $pem)) {
                return null;
            }
            $point = wp_public_point($key);
            if ($point === '') {
                return null;
            }
            return ['pem' => $pem, 'public' => wp_b64($point), 'at' => time()];
        });

        if (empty($data['pem']) || empty($data['public'])) {
            error_log('[webpush] impossibile creare la chiave del sito');
            return $keys = null;
        }
        return $keys = ['pem' => (string)$data['pem'], 'public' => (string)$data['public']];
    }

    /** Firma ECDSA di openssl (DER) nella forma a 64 byte che vuole un JWT. */
    function wp_der_to_raw(string $der): string
    {
        $offset = 2;
        if ((ord($der[1] ?? "\0") & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7f;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i += 1) {
            if (($der[$offset] ?? '') !== "\x02") {
                return '';
            }
            $length = ord($der[$offset + 1]);
            $value = ltrim(substr($der, $offset + 2, $length), "\0");
            $parts[] = str_pad($value, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }
        return $parts[0] . $parts[1];
    }

    /** Intestazione Authorization per un servizio push (uno per origine, valido dodici ore). */
    function wp_vapid_header(array $keys, string $endpoint): string
    {
        static $cache = [];
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (isset($cache[$audience])) {
            return $cache[$audience];
        }

        $head = wp_b64('{"typ":"JWT","alg":"ES256"}');
        $body = wp_b64(json_encode(['aud' => $audience, 'exp' => time() + 43200, 'sub' => CRIPSUM_WP_SUBJECT], JSON_UNESCAPED_SLASHES));
        $signature = '';
        if (!openssl_sign($head . '.' . $body, $signature, $keys['pem'], OPENSSL_ALGO_SHA256)) {
            return '';
        }
        $raw = wp_der_to_raw($signature);
        if ($raw === '') {
            return '';
        }
        return $cache[$audience] = 'vapid t=' . $head . '.' . $body . '.' . wp_b64($raw) . ', k=' . $keys['public'];
    }

    /**
     * Cifra un messaggio per un browser (RFC 8291, aes128gcm).
     *
     * `$p256dh` e `$auth` sono le due chiavi dell'iscrizione, in base64url.
     * Gli ultimi due parametri servono solo alle prove, per rifare l'esempio
     * dell'RFC: di norma chiave effimera e sale sono nuovi a ogni messaggio.
     */
    function wp_encrypt(string $payload, string $p256dh, string $auth, $ephemeral = null, ?string $salt = null): ?string
    {
        $uaPoint = wp_unb64($p256dh);
        $authSecret = wp_unb64($auth);
        $uaKey = wp_point_to_key($uaPoint);
        if (!$uaKey || strlen($authSecret) < 16) {
            return null;
        }

        $ephemeral = $ephemeral ?: wp_new_key();
        if (!$ephemeral) {
            return null;
        }
        $asPoint = wp_public_point($ephemeral);
        $shared = openssl_pkey_derive($uaKey, $ephemeral, 32);
        if ($asPoint === '' || !is_string($shared) || $shared === '') {
            return null;
        }
        $shared = str_pad($shared, 32, "\0", STR_PAD_LEFT);

        $salt = $salt ?? random_bytes(16);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPoint . $asPoint, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // 0x02 chiude l'unico blocco del messaggio.
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            return null;
        }

        return $salt . pack('N', 4096) . chr(strlen($asPoint)) . $asPoint . $cipher . $tag;
    }

    /**
     * Un indirizzo push è un URL scelto dal browser, e il server ci farà una
     * richiesta: si accettano solo i servizi push veri, mai altri indirizzi.
     */
    function wp_endpoint_allowed(string $endpoint): bool
    {
        if (strlen($endpoint) > 800) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
            return false;
        }
        $host = strtolower((string)$parts['host']);
        foreach (['fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'] as $allowed) {
            if ($host === $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
                return true;
            }
        }
        return false;
    }

    // ── Iscrizioni (nel timbro dell'utente) ────────────────────────────────

    /** Dispositivi iscritti di un utente: righe ['e' => indirizzo, 'p' => chiave, 'a' => segreto, 'at' => quando]. */
    function wp_subs(array $stamp): array
    {
        $list = isset($stamp['push']) && is_array($stamp['push']) ? $stamp['push'] : [];
        return array_values(array_filter($list, static fn($row) => is_array($row) && !empty($row['e']) && !empty($row['p']) && !empty($row['a'])));
    }

    /** Iscrive un dispositivo. Restituisce falso se i dati non sono quelli di un'iscrizione vera. */
    function wp_subscribe(int $userId, string $endpoint, string $p256dh, string $auth): bool
    {
        if ($userId <= 0 || !wp_endpoint_allowed($endpoint)
            || strlen(wp_unb64($p256dh)) !== 65 || strlen(wp_unb64($auth)) !== 16) {
            return false;
        }

        rt_update('u:' . $userId, static function (array $data) use ($endpoint, $p256dh, $auth): array {
            $list = array_values(array_filter(wp_subs($data), static fn($row) => $row['e'] !== $endpoint));
            $list[] = ['e' => $endpoint, 'p' => $p256dh, 'a' => $auth, 'at' => time()];
            $data['push'] = array_slice($list, -CRIPSUM_WP_MAX_SUBS);
            return $data;
        });
        return true;
    }

    /** Toglie un dispositivo (o più) dall'elenco di un utente. */
    function wp_unsubscribe(int $userId, array $endpoints): void
    {
        if ($userId <= 0 || !$endpoints) {
            return;
        }
        $stamp = rt_read('u:' . $userId);
        if (!array_intersect(array_column(wp_subs($stamp), 'e'), $endpoints)) {
            return;
        }
        rt_update('u:' . $userId, static function (array $data) use ($endpoints): ?array {
            $list = wp_subs($data);
            $next = array_values(array_filter($list, static fn($row) => !in_array($row['e'], $endpoints, true)));
            if (count($next) === count($list)) {
                return null;
            }
            $data['push'] = $next;
            return $data;
        });
    }

    // ── Invio ──────────────────────────────────────────────────────────────

    /**
     * Mette in coda un segnale per i dispositivi di un utente. Parte a
     * richiesta finita. `$topic` fa sì che, a dispositivo spento, i segnali
     * della stessa chat non si accumulino: resta l'ultimo.
     */
    function wp_queue(int $userId, array $subs, array $payload, string $topic = ''): void
    {
        if (!$subs || !wp_available()) {
            return;
        }
        static $registered = false;
        $GLOBALS['cripsum_wp_queue'][] = ['user' => $userId, 'subs' => $subs, 'payload' => $payload, 'topic' => $topic];
        if (!$registered) {
            $registered = true;
            register_shutdown_function('wp_flush');
        }
    }

    function wp_flush(): void
    {
        $jobs = $GLOBALS['cripsum_wp_queue'] ?? [];
        $GLOBALS['cripsum_wp_queue'] = [];
        if (!$jobs) {
            return;
        }

        // Prima si libera chi aspetta la risposta, poi la sessione e la
        // connessione MySQL (ce ne sono venti in tutto): parlare coi servizi
        // push può richiedere qualche secondo e non deve tenerle occupate.
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
            try {
                @$GLOBALS['mysqli']->close();
            } catch (Throwable $e) {
                /* era già chiusa */
            }
        }

        try {
            wp_send($jobs);
        } catch (Throwable $e) {
            error_log('[webpush] ' . $e->getMessage());
        }
    }

    /** Manda i segnali, tutti insieme. Le iscrizioni che il servizio dichiara scadute vengono tolte. */
    function wp_send(array $jobs): array
    {
        $keys = wp_keys();
        if (!$keys) {
            return [];
        }

        $multi = curl_multi_init();
        $handles = [];
        foreach ($jobs as $job) {
            $json = json_encode($job['payload'], JSON_UNESCAPED_SLASHES);
            foreach ($job['subs'] as $sub) {
                if (!wp_endpoint_allowed((string)$sub['e'])) {
                    continue;
                }
                $body = wp_encrypt((string)$json, (string)$sub['p'], (string)$sub['a']);
                $auth = wp_vapid_header($keys, (string)$sub['e']);
                if ($body === null || $auth === '') {
                    continue;
                }

                $headers = [
                    'Content-Type: application/octet-stream',
                    'Content-Encoding: aes128gcm',
                    'TTL: ' . CRIPSUM_WP_TTL,
                    'Urgency: high',
                    'Authorization: ' . $auth,
                ];
                if ($job['topic'] !== '') {
                    $headers[] = 'Topic: ' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', $job['topic']), 0, 32);
                }

                $handle = curl_init((string)$sub['e']);
                curl_setopt_array($handle, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                ]);
                curl_multi_add_handle($multi, $handle);
                $handles[] = ['handle' => $handle, 'user' => (int)$job['user'], 'endpoint' => (string)$sub['e']];
            }
        }

        if (!$handles) {
            curl_multi_close($multi);
            return [];
        }

        // Un messaggio dello staff a tutti gli utenti produce centinaia di
        // segnali: poche connessioni alla volta, e un po' più di tempo.
        if (defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')) {
            curl_multi_setopt($multi, CURLMOPT_MAX_TOTAL_CONNECTIONS, 24);
        }
        $deadline = microtime(true) + min(30, 8 + count($handles) / 25);
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.5);
            }
        } while ($running && $status === CURLM_OK && microtime(true) < $deadline);

        $results = [];
        $dead = [];
        foreach ($handles as $entry) {
            $code = (int)curl_getinfo($entry['handle'], CURLINFO_RESPONSE_CODE);
            $results[] = ['user' => $entry['user'], 'endpoint' => $entry['endpoint'], 'code' => $code];
            // 404/410: il browser ha tolto l'iscrizione. 401/403: è legata a
            // una chiave del sito che non è più questa. In tutti i casi non
            // arriverà più nulla finché il browser non si reiscrive.
            if (in_array($code, [401, 403, 404, 410], true)) {
                $dead[$entry['user']][] = $entry['endpoint'];
            } elseif ($code < 200 || $code >= 300) {
                error_log('[webpush] ' . parse_url($entry['endpoint'], PHP_URL_HOST) . ' ha risposto ' . $code);
            }
            curl_multi_remove_handle($multi, $entry['handle']);
            curl_close($entry['handle']);
        }
        curl_multi_close($multi);

        foreach ($dead as $userId => $endpoints) {
            wp_unsubscribe((int)$userId, $endpoints);
        }

        return $results;
    }
}
