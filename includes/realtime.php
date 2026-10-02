<?php
/**
 * Cripsum™ — tempo reale senza database.
 *
 * Il problema che risolve: le chat chiedevano «c'è qualcosa di nuovo?» ogni
 * due secondi e mezzo, e ogni domanda apriva una connessione MySQL. L'account
 * ne regge venti in tutto, quindi bastavano poche schede aperte per mandare
 * il resto del sito in errore 500.
 *
 * Qui la domanda trova risposta in un file. Chi scrive un messaggio, lo
 * modifica, reagisce o manda una richiesta di amicizia aggiunge una riga a un
 * piccolo registro («timbro»): uno per la chat globale, uno per ogni utente.
 * Chi controlla (api/rt/poll.php) legge solo quel file, senza includere
 * config/database.php: se non è cambiato nulla, MySQL non viene toccato.
 *
 * Cosa finisce nei timbri:
 *   - globale: i messaggi della chat globale (sono pubblici per chi è
 *     collegato), chi sta scrivendo, chi è online, le impostazioni dello staff;
 *   - per utente: solo «è successo qualcosa» con gli id (conversazione,
 *     messaggio, mittente). Mai il testo dei messaggi privati: quello resta nel
 *     database e il browser lo chiede con una richiesta autenticata.
 *
 * I file stanno in uploads/realtime/. Sono protetti tre volte: hanno
 * estensione .php e cominciano con `exit` (la regola di .htaccess che vieta
 * i .php sotto uploads/ li copre già, e se anche venissero eseguiti non
 * stamperebbero nulla), la cartella ha un .htaccess suo, e i timbri degli
 * utenti hanno un nome che non si indovina.
 *
 * Se la cartella non è scrivibile tutte le funzioni diventano mute e il
 * browser ripiega su un controllo lento che passa dal database: le chat
 * funzionano lo stesso, solo con meno prontezza.
 */

if (!defined('CRIPSUM_RT_GUARD')) {
    define('CRIPSUM_RT_GUARD', "<?php exit; ?>\n");
    define('CRIPSUM_RT_RING_GLOBAL', 45);
    define('CRIPSUM_RT_RING_USER', 60);
    define('CRIPSUM_RT_MENTIONS_MAX', 120);
    define('CRIPSUM_RT_TYPING_TTL', 6);
}

if (!function_exists('rt_dir')) {

    /** Cartella dei timbri, creata al primo uso. Stringa vuota se non si può scrivere. */
    function rt_dir(): string
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }

        $base = dirname(__DIR__) . '/uploads/realtime';
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        if (!is_dir($base) || !is_writable($base)) {
            return $dir = '';
        }

        $guard = $base . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents(
                $guard,
                "# Timbri del tempo reale: niente da servire via web.\n"
                . "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [F,L]\n</IfModule>\n"
                . "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            );
        }
        if (!is_file($base . '/index.php')) {
            @file_put_contents($base . '/index.php', CRIPSUM_RT_GUARD);
        }

        return $dir = $base;
    }

    /**
     * Chiave per i nomi dei timbri personali. Deriva dalle credenziali già
     * presenti sul server, così non c'è un segreto nuovo da configurare.
     */
    function rt_secret(): string
    {
        static $secret = null;
        if ($secret !== null) {
            return $secret;
        }

        $db_pass = '';
        $db_name = '';
        $config = dirname(__DIR__) . '/secure/config.php';
        if (is_file($config)) {
            // Non _once: le variabili servono in questo scope anche se il
            // file è già stato incluso altrove.
            include $config;
        }

        return $secret = hash('sha256', 'cripsum-rt|' . $db_name . '|' . $db_pass);
    }

    function rt_path(string $key): string
    {
        $dir = rt_dir();
        if ($dir === '') {
            return '';
        }

        if (strncmp($key, 'u:', 2) === 0) {
            $userId = (int)substr($key, 2);
            $name = substr(hash_hmac('sha256', 'user-' . $userId, rt_secret()), 0, 28);
            $bucket = $dir . '/u/' . sprintf('%02d', $userId % 100);
            if (!is_dir($bucket)) {
                @mkdir($bucket, 0755, true);
            }
            return $bucket . '/' . $name . '.php';
        }

        return $dir . '/' . preg_replace('/[^a-z0-9_-]/', '', strtolower($key)) . '.php';
    }

    function rt_decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        if (strncmp($raw, CRIPSUM_RT_GUARD, strlen(CRIPSUM_RT_GUARD)) === 0) {
            $raw = substr($raw, strlen(CRIPSUM_RT_GUARD));
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /** Legge un timbro. Array vuoto se non esiste. */
    function rt_read(string $key): array
    {
        $path = rt_path($key);
        if ($path === '' || !is_file($path)) {
            return [];
        }

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return [];
        }
        @flock($handle, LOCK_SH);
        $raw = stream_get_contents($handle);
        @flock($handle, LOCK_UN);
        fclose($handle);

        return rt_decode(is_string($raw) ? $raw : '');
    }

    /**
     * Modifica un timbro tenendolo bloccato dall'inizio alla fine: due invii
     * nello stesso istante non si pestano i piedi e nessun evento va perso.
     * La funzione riceve i dati attuali e restituisce quelli nuovi (oppure
     * null per lasciare tutto com'è).
     */
    function rt_update(string $key, callable $change): array
    {
        $path = rt_path($key);
        if ($path === '') {
            return [];
        }

        $handle = @fopen($path, 'c+b');
        if (!$handle) {
            return [];
        }

        $data = [];
        try {
            @flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $data = rt_decode(is_string($raw) ? $raw : '');
            $next = $change($data);
            if (is_array($next)) {
                $data = $next;
                $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($encoded !== false) {
                    rewind($handle);
                    ftruncate($handle, 0);
                    fwrite($handle, CRIPSUM_RT_GUARD . $encoded);
                    fflush($handle);
                }
            }
        } catch (Throwable $e) {
            error_log('[realtime] ' . $key . ': ' . $e->getMessage());
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $data;
    }

    /**
     * Numero d'ordine del prossimo evento.
     *
     * Parte dall'orologio e non da zero: se un timbro viene cancellato e
     * ricreato, i numeri nuovi restano comunque più alti di quelli che i
     * browser ricordano. `floor` segna il punto sotto il quale il registro
     * non garantisce più nulla (eventi usciti dalla coda, o mai esistiti in
     * un timbro appena nato): chi arriva con un numero più basso ricarica.
     */
    function rt_next_seq(array &$data): int
    {
        if (empty($data['seq'])) {
            $data['seq'] = (int)floor(microtime(true) * 1000);
            $data['floor'] = $data['seq'];
        }
        $data['seq'] = (int)$data['seq'] + 1;
        return $data['seq'];
    }

    /** Taglia la coda alla lunghezza massima ricordando fin dove è arrivato il taglio. */
    function rt_trim_events(array &$data, array $events, int $max): void
    {
        $extra = count($events) - $max;
        if ($extra > 0) {
            $dropped = array_slice($events, 0, $extra);
            $last = end($dropped);
            $data['floor'] = max((int)($data['floor'] ?? 0), (int)($last['s'] ?? 0));
            $events = array_slice($events, $extra);
        }
        $data['events'] = array_values($events);
    }

    /**
     * Aggiunge un evento al registro personale di un utente. `$mutate`
     * permette di cambiare nello stesso passaggio anche il resto del timbro.
     */
    function rt_push_user(int $userId, array $event, ?callable $mutate = null): void
    {
        if ($userId <= 0 || empty($event['t'])) {
            return;
        }

        rt_update('u:' . $userId, static function (array $data) use ($event, $mutate): array {
            $seq = rt_next_seq($data);
            $event['s'] = $seq;
            $event['at'] = time();

            $events = isset($data['events']) && is_array($data['events']) ? $data['events'] : [];

            // «Sta scrivendo» e «ha letto» non si accumulano: conta solo
            // l'ultimo per conversazione, il resto sarebbe rumore.
            if (in_array($event['t'], ['ty', 'rd'], true)) {
                $events = array_values(array_filter($events, static function ($old) use ($event) {
                    return !(($old['t'] ?? '') === $event['t']
                        && ($old['c'] ?? null) === ($event['c'] ?? null)
                        && ($old['g'] ?? null) === ($event['g'] ?? null)
                        && ($old['f'] ?? null) === ($event['f'] ?? null));
                }));
            }

            $events[] = $event;
            rt_trim_events($data, $events, CRIPSUM_RT_RING_USER);

            if ($mutate !== null) {
                $changed = $mutate($data);
                if (is_array($changed)) {
                    $data = $changed;
                }
            }
            return $data;
        });
    }

    function rt_push_users(array $userIds, array $event): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            rt_push_user($userId, $event);
        }
    }

    // ── Menzioni nella chat globale ancora da vedere ───────────────────────
    //
    // La chat globale non ha un «letto fin qui» per utente come le chat
    // private. Gli id dei messaggi in cui si è stati menzionati stanno nel
    // timbro personale, fuori dalla coda degli eventi (che si accorcia da
    // sola e li perderebbe): si contano senza database e si svuotano quando
    // la chat globale viene aperta.

    /** Id dei messaggi con una menzione non ancora vista, dal più vecchio. */
    function rt_mentions(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $data = rt_read('u:' . $userId);
        $list = isset($data['mentions']) && is_array($data['mentions']) ? $data['mentions'] : [];
        return array_values(array_unique(array_map('intval', $list)));
    }

    /** Segna una menzione da vedere e avvisa l'utente (evento `mn`). */
    function rt_mention_add(int $userId, int $messageId, int $senderId): void
    {
        rt_push_user($userId, ['t' => 'mn', 'm' => $messageId, 'f' => $senderId], static function (array $data) use ($messageId): array {
            $list = isset($data['mentions']) && is_array($data['mentions']) ? array_map('intval', $data['mentions']) : [];
            if (!in_array($messageId, $list, true)) {
                $list[] = $messageId;
            }
            $data['mentions'] = array_slice($list, -CRIPSUM_RT_MENTIONS_MAX);
            return $data;
        });
    }

    /**
     * Toglie le menzioni viste: tutte, oppure solo quelle indicate (un
     * messaggio eliminato). Se cambia qualcosa lo dice alle schede aperte,
     * che rifanno i contatori. Restituisce vero se c'era qualcosa da togliere.
     */
    function rt_mentions_clear(int $userId, ?array $only = null): bool
    {
        // Letto prima senza bloccare: quasi sempre l'elenco è vuoto, e così
        // non nasce un timbro per chi non ne ha mai avuto bisogno.
        $pending = rt_mentions($userId);
        if (!$pending || ($only !== null && !array_intersect($pending, array_map('intval', $only)))) {
            return false;
        }

        $cleared = false;
        rt_update('u:' . $userId, static function (array $data) use ($only, &$cleared): ?array {
            $list = isset($data['mentions']) && is_array($data['mentions']) ? array_map('intval', $data['mentions']) : [];
            $next = $only === null ? [] : array_values(array_diff($list, array_map('intval', $only)));
            if (count($next) === count($list)) {
                return null;
            }
            $cleared = true;
            $data['mentions'] = $next;

            $events = isset($data['events']) && is_array($data['events']) ? $data['events'] : [];
            $events[] = ['t' => 'mr', 's' => rt_next_seq($data), 'at' => time()];
            rt_trim_events($data, $events, CRIPSUM_RT_RING_USER);
            return $data;
        });

        return $cleared;
    }

    /**
     * Aggiunge un evento alla chat globale. `$mutate` permette di cambiare
     * nello stesso passaggio anche il resto del timbro (impostazioni, online).
     */
    function rt_push_global(?array $event, ?callable $mutate = null): int
    {
        $seq = 0;
        rt_update('g', static function (array $data) use ($event, $mutate, &$seq): array {
            if ($event !== null) {
                $seq = rt_next_seq($data);
                $event['s'] = $seq;
                $events = isset($data['events']) && is_array($data['events']) ? $data['events'] : [];

                // Un messaggio modificato più volte tiene solo l'ultima
                // versione: il registro resta corto e chi arriva dopo non
                // applica aggiornamenti già superati.
                if (($event['t'] ?? '') === 'upd' && isset($event['m']['id'])) {
                    $targetId = (int)$event['m']['id'];
                    $events = array_values(array_filter($events, static function ($old) use ($targetId) {
                        return !(($old['t'] ?? '') === 'upd' && (int)($old['m']['id'] ?? 0) === $targetId);
                    }));
                    foreach ($events as &$old) {
                        if (($old['t'] ?? '') === 'msg' && (int)($old['m']['id'] ?? 0) === $targetId) {
                            $old['m'] = $event['m'];
                        }
                    }
                    unset($old);
                }

                $events[] = $event;
                rt_trim_events($data, $events, CRIPSUM_RT_RING_GLOBAL);
            }

            if ($mutate !== null) {
                $changed = $mutate($data);
                if (is_array($changed)) {
                    $data = $changed;
                    // Tutto ciò che non è un evento (chi scrive, chi è
                    // online, le impostazioni) viaggia con un contatore suo.
                    $data['aux'] = (int)($data['aux'] ?? 0) + 1;
                }
            }

            if (empty($data['seq'])) {
                $data['seq'] = (int)floor(microtime(true) * 1000);
                $data['floor'] = $data['seq'];
            }
            $seq = (int)$data['seq'];
            return $data;
        });

        return $seq;
    }

    /** Chi sta scrivendo nella chat globale: solo nel timbro, mai nel database. */
    function rt_global_typing(int $userId, string $username, bool $typing): void
    {
        rt_update('g', static function (array $data) use ($userId, $username, $typing): ?array {
            $now = time();
            $list = isset($data['typing']) && is_array($data['typing']) ? $data['typing'] : [];
            $before = $list;

            foreach ($list as $id => $row) {
                if ((int)($row['until'] ?? 0) < $now) {
                    unset($list[$id]);
                }
            }

            if ($typing) {
                $list[(string)$userId] = ['name' => $username, 'until' => $now + CRIPSUM_RT_TYPING_TTL];
            } else {
                unset($list[(string)$userId]);
            }

            if (array_keys($list) === array_keys($before) && !$typing) {
                return null;
            }

            $data['typing'] = $list;
            $data['aux'] = (int)($data['aux'] ?? 0) + 1;
            return $data;
        });
    }

    /**
     * Risposta per chi controlla un timbro partendo da un certo numero.
     *
     *   cursore < 0      → saluto: restituisce solo il numero attuale;
     *   cursore attuale  → niente (`null`);
     *   cursore vecchio  → gli eventi successivi, con `resync` se nel
     *                      frattempo qualcuno è uscito dal registro.
     */
    function rt_events_since(array $data, int $cursor): ?array
    {
        $seq = (int)($data['seq'] ?? 0);
        if ($cursor < 0) {
            return ['seq' => $seq, 'events' => []];
        }
        if ($seq === 0 || $seq === $cursor) {
            return null;
        }

        $events = isset($data['events']) && is_array($data['events']) ? $data['events'] : [];
        $fresh = [];
        foreach ($events as $event) {
            if ((int)($event['s'] ?? 0) > $cursor) {
                $fresh[] = $event;
            }
        }

        // Numero più basso di quello ricordato: il timbro è stato ricreato.
        // Numero sotto `floor`: nel frattempo qualche evento è uscito dalla coda.
        $resync = $seq < $cursor || $cursor < (int)($data['floor'] ?? 0);

        return ['seq' => $seq, 'events' => $fresh, 'resync' => $resync];
    }

    /**
     * Com'è fatto il database, letto una volta ogni dieci minuti.
     *
     * Lo schema di questo progetto si migra a mano, quindi il codice deve
     * funzionare anche prima che una colonna nuova esista. Chiederlo a MySQL
     * a ogni richiesta (lo faceva la chat globale, sei volte per controllo)
     * costa più della richiesta stessa: qui la risposta sta in un file.
     */
    /** Le tabelle di cui si tiene la struttura in cache. */
    function rt_schema_tables(): array
    {
        return [
            'utenti', 'messages', 'chat_reactions', 'chat_mutes', 'chat_reports', 'chat_typing',
            'chat_word_filters', 'game_emojis', 'utenti_profile_badges', 'achievement',
            'chats', 'chat_members', 'chat_messages', 'chat_invites', 'chat_settings', 'group_chat_reactions',
            'private_conversations', 'private_conversation_participants', 'private_messages',
            'private_message_attachments', 'private_message_deleted', 'private_message_reactions',
            'private_pinned_messages', 'private_conversation_pins', 'private_favorites', 'private_user_settings',
            'friendships', 'friendship_requests', 'blocked_users', 'user_follows',
            'site_messages', 'site_message_recipients', 'site_message_rewards',
            'site_tickets', 'site_ticket_messages', 'admin_logs',
        ];
    }

    function rt_schema(mysqli $mysqli): array
    {
        static $schema = null;
        if ($schema !== null) {
            return $schema;
        }

        // La cache vale solo per l'elenco di tabelle con cui è stata scritta.
        // Senza questo controllo, aggiungere una tabella all'elenco la faceva
        // risultare «inesistente» finché la cache vecchia non scadeva: così
        // sono spariti per qualche minuto i premi dalla posta.
        $tables = rt_schema_tables();
        $signature = md5(implode(',', $tables));

        $cached = rt_read('schema');
        if (!empty($cached['at']) && (time() - (int)$cached['at']) < 600 && isset($cached['tables'])
            && ($cached['sig'] ?? '') === $signature) {
            return $schema = $cached['tables'];
        }

        $schema = [];
        try {
            $list = "'" . implode("','", $tables) . "'";
            $result = $mysqli->query(
                "SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($list)"
            );
            if ($result) {
                while ($row = $result->fetch_row()) {
                    // Il valore è la lunghezza massima delle colonne di
                    // testo (serve per le reazioni), 1 per tutte le altre.
                    $schema[strtolower($row[0])][strtolower($row[1])] = max(1, (int)$row[2]);
                }
            }
        } catch (Throwable $e) {
            error_log('[realtime] schema: ' . $e->getMessage());
            return $schema = [];
        }

        if ($schema) {
            rt_update('schema', static fn() => ['at' => time(), 'sig' => $signature, 'tables' => $schema]);
        }

        return $schema;
    }

    function rt_has_table(mysqli $mysqli, string $table): bool
    {
        $name = strtolower($table);
        if (isset(rt_schema($mysqli)[$name])) {
            return true;
        }
        if (in_array($name, rt_schema_tables(), true)) {
            return false;
        }

        // Una tabella fuori dall'elenco non è «assente»: semplicemente non è
        // in cache. Si chiede al database, una volta per richiesta.
        static $direct = [];
        if (!array_key_exists($name, $direct)) {
            $direct[$name] = false;
            try {
                $stmt = $mysqli->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $direct[$name] = $stmt->get_result()->num_rows > 0;
                $stmt->close();
            } catch (Throwable $e) {
                error_log('[realtime] tabella ' . $name . ': ' . $e->getMessage());
            }
        }
        return $direct[$name];
    }

    function rt_has_col(mysqli $mysqli, string $table, string $column): bool
    {
        return isset(rt_schema($mysqli)[strtolower($table)][strtolower($column)]);
    }

    /** Lunghezza massima di una colonna di testo, 0 se la colonna non esiste. */
    function rt_col_len(mysqli $mysqli, string $table, string $column): int
    {
        return (int)(rt_schema($mysqli)[strtolower($table)][strtolower($column)] ?? 0);
    }

    /** Dimentica lo schema in cache: da chiamare dopo aver applicato una migration. */
    function rt_schema_forget(): void
    {
        $path = rt_path('schema');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /** Lingua delle risposte: le API non hanno /it/ o /en/ nell'indirizzo. */
    function rt_lang(): string
    {
        static $lang = null;
        if ($lang !== null) {
            return $lang;
        }
        $header = strtolower((string)($_SERVER['HTTP_X_CRIPSUM_LANG'] ?? ''));
        if ($header === 'it' || $header === 'en') {
            return $lang = $header;
        }
        if (function_exists('cripsum_preferred_lang')) {
            return $lang = cripsum_preferred_lang();
        }
        return $lang = 'it';
    }

    /** Testo nella lingua di chi chiama. */
    function rt_t(string $it, string $en): string
    {
        return rt_lang() === 'en' ? $en : $it;
    }
}
