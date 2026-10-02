<?php
/**
 * Cripsum™ — amici, blocchi e privacy: le regole in un posto solo.
 *
 * Prima ogni endpoint si rifaceva i conti da sé e i risultati non
 * coincidevano: la pagina nascondeva un bottone che il server avrebbe
 * comunque accettato, le impostazioni di privacy venivano salvate ma non
 * lette da nessuno, il blocco esisteva in due versioni diverse.
 *
 * Qui c'è una sola risposta alla domanda «A può fare questa cosa a B?»,
 * e la usano sia gli endpoint degli amici sia quelli delle chat.
 *
 * Nessuna query dà per scontate tabelle o colonne facoltative: lo schema si
 * migra a mano (vedi rt_has_table / rt_has_col in includes/realtime.php).
 */

require_once __DIR__ . '/realtime.php';

if (!defined('SC_ONLINE_WINDOW')) {
    define('SC_ONLINE_WINDOW', 180);           // secondi: sotto questa soglia l'utente è «online»
    define('SC_REQUESTS_PER_HOUR', 20);        // richieste di amicizia nuove all'ora
    define('SC_REQUESTS_PENDING_MAX', 100);    // richieste in sospeso contemporaneamente
    define('SC_REQUEST_COOLDOWN_HOURS', 24);   // attesa dopo un rifiuto, verso la stessa persona
}

if (!function_exists('sc_settings')) {

    /** Errore di dominio: il messaggio è già pensato per l'utente. */
    class SocialError extends RuntimeException
    {
        public string $errorCode;
        public int $status;

        public function __construct(string $message, string $errorCode = 'BAD_REQUEST', int $status = 400)
        {
            parent::__construct($message);
            $this->errorCode = $errorCode;
            $this->status = $status;
        }
    }

    function sc_pair(int $a, int $b): array
    {
        return [min($a, $b), max($a, $b)];
    }

    /** Filtro SQL per gli account utilizzabili: non bannati e non in cancellazione. */
    function sc_active_sql(mysqli $mysqli, string $alias = 'u'): string
    {
        $sql = '`' . $alias . '`.isBannato = 0';
        if (rt_has_col($mysqli, 'utenti', 'deletion_requested_at')) {
            $sql .= ' AND `' . $alias . '`.deletion_requested_at IS NULL';
        }
        return $sql;
    }

    function sc_user_brief(mysqli $mysqli, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $extra = rt_has_col($mysqli, 'utenti', 'deletion_requested_at') ? ', deletion_requested_at' : '';
        $stmt = $mysqli->prepare("SELECT id, username, display_name, ruolo, is_premium, isBannato, profile_visibility$extra FROM utenti WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'display_name' => (string)($row['display_name'] ?: $row['username']),
            'ruolo' => (string)$row['ruolo'],
            'is_premium' => (int)$row['is_premium'] === 1,
            'active' => (int)$row['isBannato'] === 0 && empty($row['deletion_requested_at']),
            'profile_visibility' => (string)($row['profile_visibility'] ?: 'public'),
        ];
    }

    // ── Impostazioni di privacy ────────────────────────────────────────────

    function sc_default_settings(): array
    {
        return [
            'dm_from' => 'all',            // all | friends
            'requests_from' => 'all',      // all | none
            'read_receipts' => true,
            'typing' => true,
        ];
    }

    /**
     * Impostazioni di privacy di più utenti in una query sola.
     * Chi non ha una riga (quasi tutti) ha i valori predefiniti.
     */
    function sc_settings_many(mysqli $mysqli, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $out = [];
        foreach ($userIds as $id) {
            $out[$id] = sc_default_settings();
        }
        if (!$userIds || !rt_has_table($mysqli, 'private_user_settings')) {
            return $out;
        }

        $hasRequests = rt_has_col($mysqli, 'private_user_settings', 'friend_requests_from');
        $marks = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $mysqli->prepare(
            'SELECT user_id, privacy_receive_from, disable_read_receipts, disable_typing_status'
            . ($hasRequests ? ', friend_requests_from' : '')
            . " FROM private_user_settings WHERE user_id IN ($marks)"
        );
        $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $from = (string)$row['privacy_receive_from'];
            $out[(int)$row['user_id']] = [
                // I valori storici «none» e «verified» non avevano interfaccia:
                // il più restrittivo dei due diventa «solo amici».
                'dm_from' => in_array($from, ['friends', 'none'], true) ? 'friends' : 'all',
                'requests_from' => ($hasRequests && (string)$row['friend_requests_from'] === 'none') ? 'none' : 'all',
                'read_receipts' => (int)$row['disable_read_receipts'] === 0,
                'typing' => (int)$row['disable_typing_status'] === 0,
            ];
        }
        $stmt->close();

        return $out;
    }

    function sc_settings(mysqli $mysqli, int $userId): array
    {
        return sc_settings_many($mysqli, [$userId])[$userId] ?? sc_default_settings();
    }

    function sc_save_settings(mysqli $mysqli, int $userId, array $input): array
    {
        if (!rt_has_table($mysqli, 'private_user_settings')) {
            throw new SocialError(rt_t(
                'Le impostazioni di privacy non sono ancora attive su questo server.',
                'Privacy settings are not available on this server yet.'
            ), 'NOT_AVAILABLE', 503);
        }

        $current = sc_settings($mysqli, $userId);
        $dmFrom = in_array($input['dm_from'] ?? null, ['all', 'friends'], true) ? $input['dm_from'] : $current['dm_from'];
        $requestsFrom = in_array($input['requests_from'] ?? null, ['all', 'none'], true) ? $input['requests_from'] : $current['requests_from'];
        $receipts = array_key_exists('read_receipts', $input) ? !empty($input['read_receipts']) : $current['read_receipts'];
        $typing = array_key_exists('typing', $input) ? !empty($input['typing']) : $current['typing'];

        $noReceipts = $receipts ? 0 : 1;
        $noTyping = $typing ? 0 : 1;

        if (rt_has_col($mysqli, 'private_user_settings', 'friend_requests_from')) {
            $stmt = $mysqli->prepare('
                INSERT INTO private_user_settings (user_id, privacy_receive_from, disable_read_receipts, disable_typing_status, friend_requests_from)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE privacy_receive_from = VALUES(privacy_receive_from),
                    disable_read_receipts = VALUES(disable_read_receipts),
                    disable_typing_status = VALUES(disable_typing_status),
                    friend_requests_from = VALUES(friend_requests_from)
            ');
            $stmt->bind_param('isiis', $userId, $dmFrom, $noReceipts, $noTyping, $requestsFrom);
        } else {
            $requestsFrom = 'all';
            $stmt = $mysqli->prepare('
                INSERT INTO private_user_settings (user_id, privacy_receive_from, disable_read_receipts, disable_typing_status)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE privacy_receive_from = VALUES(privacy_receive_from),
                    disable_read_receipts = VALUES(disable_read_receipts),
                    disable_typing_status = VALUES(disable_typing_status)
            ');
            $stmt->bind_param('isii', $userId, $dmFrom, $noReceipts, $noTyping);
        }
        $stmt->execute();
        $stmt->close();

        return [
            'dm_from' => $dmFrom,
            'requests_from' => $requestsFrom,
            'read_receipts' => $receipts,
            'typing' => $typing,
            'requests_setting_available' => rt_has_col($mysqli, 'private_user_settings', 'friend_requests_from'),
        ];
    }

    // ── Relazioni ──────────────────────────────────────────────────────────

    function sc_are_friends(mysqli $mysqli, int $a, int $b): bool
    {
        [$one, $two] = sc_pair($a, $b);
        $stmt = $mysqli->prepare('SELECT 1 FROM friendships WHERE user_one_id = ? AND user_two_id = ? LIMIT 1');
        $stmt->bind_param('ii', $one, $two);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    function sc_is_blocked(mysqli $mysqli, int $a, int $b): bool
    {
        $stmt = $mysqli->prepare('SELECT 1 FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1');
        $stmt->bind_param('iiii', $a, $b, $b, $a);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    /** Id degli utenti che non devo vedere: quelli che ho bloccato e quelli che mi hanno bloccato. */
    function sc_hidden_ids(mysqli $mysqli, int $userId): array
    {
        $stmt = $mysqli->prepare('SELECT IF(blocker_id = ?, blocked_id, blocker_id) AS other FROM blocked_users WHERE blocker_id = ? OR blocked_id = ?');
        $stmt->bind_param('iii', $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_row()) {
            $ids[] = (int)$row[0];
        }
        $stmt->close();
        return array_values(array_unique($ids));
    }

    /**
     * Ricalcola chi va nascosto a un utente nella chat globale (bloccati nei
     * due versi, più i mutati) e lo scrive nel suo timbro: il controllo
     * senza database lo trova lì, senza una query a ogni giro.
     */
    function sc_refresh_hidden(mysqli $mysqli, int $userId): array
    {
        $ids = sc_hidden_ids($mysqli, $userId);
        if (rt_has_table($mysqli, 'chat_mutes')) {
            $stmt = $mysqli->prepare('SELECT muted_id FROM chat_mutes WHERE muter_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_row()) {
                $ids[] = (int)$row[0];
            }
            $stmt->close();
        }
        $ids = array_values(array_unique($ids));

        rt_update('u:' . $userId, static function (array $data) use ($ids): array {
            $data['hidden'] = $ids;
            $data['hidden_at'] = time();
            return $data;
        });

        return $ids;
    }

    function sc_friend_ids(mysqli $mysqli, int $userId): array
    {
        $stmt = $mysqli->prepare('SELECT IF(user_one_id = ?, user_two_id, user_one_id) FROM friendships WHERE user_one_id = ? OR user_two_id = ?');
        $stmt->bind_param('iii', $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_row()) {
            $ids[] = (int)$row[0];
        }
        $stmt->close();
        return $ids;
    }

    /**
     * Stato completo della relazione fra chi guarda e un altro utente, con i
     * permessi già calcolati. Le chiavi storiche restano (le usa il client).
     */
    function sc_relationship(mysqli $mysqli, int $viewerId, int $targetId): array
    {
        $status = [
            'is_self' => $viewerId === $targetId,
            'is_following' => false,
            'is_followed_by' => false,
            'is_mutual_follow' => false,
            'is_friend' => false,
            'friend_request_sent' => false,
            'friend_request_received' => false,
            'is_blocked_by_viewer' => false,
            'has_blocked_viewer' => false,
            'can_follow' => false,
            'can_send_friend_request' => false,
            'can_message' => false,
            'message_as_request' => false,
            'can_view_profile' => true,
            'target_exists' => true,
        ];

        if ($status['is_self']) {
            return $status;
        }

        $target = sc_user_brief($mysqli, $targetId);
        if (!$target) {
            $status['target_exists'] = false;
            $status['can_view_profile'] = false;
            return $status;
        }

        if ($viewerId <= 0) {
            $status['can_view_profile'] = $target['profile_visibility'] === 'public';
            return $status;
        }

        [$one, $two] = sc_pair($viewerId, $targetId);
        $stmt = $mysqli->prepare("
            SELECT
                EXISTS(SELECT 1 FROM friendships WHERE user_one_id = ? AND user_two_id = ?) AS is_friend,
                EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending') AS sent,
                EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending') AS received,
                EXISTS(SELECT 1 FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?) AS blocked_by_viewer,
                EXISTS(SELECT 1 FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?) AS blocked_viewer
        ");
        $stmt->bind_param('iiiiiiiiii', $one, $two, $viewerId, $targetId, $targetId, $viewerId, $viewerId, $targetId, $targetId, $viewerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $status['is_friend'] = !empty($row['is_friend']);
        $status['friend_request_sent'] = !empty($row['sent']);
        $status['friend_request_received'] = !empty($row['received']);
        $status['is_blocked_by_viewer'] = !empty($row['blocked_by_viewer']);
        $status['has_blocked_viewer'] = !empty($row['blocked_viewer']);

        if ($status['is_blocked_by_viewer'] || $status['has_blocked_viewer']) {
            $status['can_view_profile'] = false;
            return $status;
        }

        if ($target['profile_visibility'] === 'private'
            || ($target['profile_visibility'] === 'friends' && !$status['is_friend'])) {
            $status['can_view_profile'] = false;
        }

        if (!$target['active']) {
            return $status;
        }

        $settings = sc_settings($mysqli, $targetId);

        if (!$status['is_friend'] && !$status['friend_request_sent'] && !$status['friend_request_received']) {
            $status['can_send_friend_request'] = $settings['requests_from'] !== 'none';
        }

        if ($status['is_friend']) {
            $status['can_message'] = true;
        } elseif ($settings['dm_from'] === 'all') {
            $status['can_message'] = true;
            $status['message_as_request'] = true;
        }

        return $status;
    }

    /**
     * Il mittente può scrivere al destinatario? Una sola regola per testo,
     * GIF, allegati e inoltri.
     */
    function sc_can_message(mysqli $mysqli, int $fromId, int $toId): array
    {
        if ($fromId === $toId || $toId <= 0) {
            return ['ok' => false, 'request' => false, 'message' => rt_t('Destinatario non valido.', 'Invalid recipient.')];
        }

        $rel = sc_relationship($mysqli, $fromId, $toId);
        if (!$rel['target_exists']) {
            return ['ok' => false, 'request' => false, 'message' => rt_t('Utente non trovato.', 'User not found.')];
        }
        if ($rel['is_blocked_by_viewer']) {
            return ['ok' => false, 'request' => false, 'message' => rt_t('Hai bloccato questo utente: sbloccalo per scrivergli.', 'You blocked this user: unblock them to send a message.')];
        }
        if ($rel['has_blocked_viewer'] || !$rel['can_message']) {
            // Stesso testo per blocco, privacy e account non attivo: chi è
            // stato bloccato non deve poterlo capire dalla risposta.
            return ['ok' => false, 'request' => false, 'message' => rt_t('Questo utente non accetta messaggi da te.', 'This user is not accepting messages from you.')];
        }

        return ['ok' => true, 'request' => $rel['message_as_request'], 'message' => ''];
    }

    // ── Notifiche ──────────────────────────────────────────────────────────

    /**
     * Avvisa un utente con un evento nel timbro (arriva subito a chi ha una
     * pagina aperta) e, solo se viene passato `$inbox`, con un messaggio
     * nella posta.
     *
     * Richieste di amicizia e inviti non passano più dalla posta: restano
     * visibili finché sono in sospeso nel menu delle notifiche in navbar
     * (notify_panel), con i pulsanti per rispondere, e la posta non si
     * riempie di messaggi che dicono «vai a vedere altrove». La posta resta
     * per ciò che non lascia altra traccia.
     */
    function sc_notify(mysqli $mysqli, int $recipientId, array $event, ?array $inbox = null): void
    {
        if ($inbox && function_exists('sendSecurityInboxMessage')) {
            try {
                sendSecurityInboxMessage($mysqli, $recipientId, $inbox['title_it'], $inbox['title_en'], $inbox['content_it'], $inbox['content_en'], 'social');
            } catch (Throwable $e) {
                error_log('[social notify] ' . $e->getMessage());
            }
        }
        rt_push_user($recipientId, $event);
    }

    // ── Richieste di amicizia ──────────────────────────────────────────────

    /** Blocca la coppia per la durata dell'operazione: niente doppie accettazioni. */
    function sc_lock_pair(mysqli $mysqli, int $a, int $b): string
    {
        [$one, $two] = sc_pair($a, $b);
        $name = 'cripsum:pair:' . $one . ':' . $two;
        $stmt = $mysqli->prepare('SELECT GET_LOCK(?, 4)');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $got = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($got !== 1) {
            throw new SocialError(rt_t('Operazione già in corso, riprova.', 'Another operation is in progress, try again.'), 'BUSY', 409);
        }
        return $name;
    }

    function sc_unlock(mysqli $mysqli, string $name): void
    {
        try {
            $stmt = $mysqli->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            // Il lock cade comunque alla chiusura della connessione.
        }
    }

    function sc_request_rows(mysqli $mysqli, int $a, int $b): array
    {
        $stmt = $mysqli->prepare('
            SELECT sender_id, receiver_id, status, responded_at,
                   TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS age
            FROM friendship_requests
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ');
        $stmt->bind_param('iiii', $a, $b, $b, $a);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Crea l'amicizia a partire da una richiesta in sospeso. Restituisce
     * true se l'amicizia è nata adesso.
     *
     * La missione «aggiungi un amico» e la statistica del Rewind contano
     * una volta sola per coppia: `responded_at` resta valorizzato anche
     * dopo una rimozione, quindi togliersi e riaggiungersi non le fa
     * avanzare di nuovo.
     */
    function sc_accept_pending(mysqli $mysqli, int $receiverId, int $senderId): bool
    {
        $rows = sc_request_rows($mysqli, $receiverId, $senderId);
        $everAccepted = false;
        foreach ($rows as $row) {
            if (!empty($row['responded_at'])) {
                $everAccepted = true;
            }
        }

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("
                UPDATE friendship_requests
                SET status = 'accepted', responded_at = COALESCE(responded_at, NOW())
                WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
            ");
            $stmt->bind_param('ii', $senderId, $receiverId);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed < 1) {
                $mysqli->rollback();
                throw new SocialError(rt_t('Questa richiesta non è più in sospeso.', 'This request is no longer pending.'), 'REQUEST_NOT_FOUND', 404);
            }

            // Se c'era una richiesta anche nel verso opposto, non ha più senso.
            $stmt = $mysqli->prepare("UPDATE friendship_requests SET status = 'cancelled' WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
            $stmt->bind_param('ii', $receiverId, $senderId);
            $stmt->execute();
            $stmt->close();

            [$one, $two] = sc_pair($receiverId, $senderId);
            $stmt = $mysqli->prepare('INSERT IGNORE INTO friendships (user_one_id, user_two_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $one, $two);
            $stmt->execute();
            $created = $stmt->affected_rows > 0;
            $stmt->close();

            $mysqli->commit();
        } catch (SocialError $e) {
            throw $e;
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        if ($created && !$everAccepted) {
            try {
                if (function_exists('stats_track')) {
                    stats_track($mysqli, $receiverId, 'friends_added');
                    stats_track($mysqli, $senderId, 'friends_added');
                }
                if (function_exists('trackMissionProgress')) {
                    trackMissionProgress($mysqli, $receiverId, 'add_friend');
                    trackMissionProgress($mysqli, $senderId, 'add_friend');
                }
                // Achievement «stringi N amicizie», per tutti e due.
                require_once __DIR__ . '/achievements.php';
                ach_sync($mysqli, $receiverId, ['friends']);
                ach_sync($mysqli, $senderId, ['friends']);
            } catch (Throwable $e) {
                error_log('[social accept tracking] ' . $e->getMessage());
            }
        }

        sc_notify($mysqli, $senderId, ['t' => 'fa', 'f' => $receiverId]);
        rt_push_user($receiverId, ['t' => 'sl']);

        return $created;
    }

    /** Invia una richiesta. Restituisce 'pending' oppure 'accepted' (se l'altro l'aveva già mandata). */
    function sc_request_send(mysqli $mysqli, int $senderId, int $receiverId): string
    {
        if ($receiverId <= 0 || $receiverId === $senderId) {
            throw new SocialError(rt_t('Destinatario non valido.', 'Invalid recipient.'), 'INVALID_INPUT');
        }

        $lock = sc_lock_pair($mysqli, $senderId, $receiverId);
        try {
            $rel = sc_relationship($mysqli, $senderId, $receiverId);
            if (!$rel['target_exists']) {
                throw new SocialError(rt_t('Utente non trovato.', 'User not found.'), 'USER_NOT_FOUND', 404);
            }
            if ($rel['is_blocked_by_viewer']) {
                throw new SocialError(rt_t('Hai bloccato questo utente.', 'You blocked this user.'), 'BLOCKED', 403);
            }
            if ($rel['is_friend']) {
                return 'accepted';
            }
            if ($rel['friend_request_sent']) {
                return 'pending';
            }
            if ($rel['friend_request_received']) {
                sc_accept_pending($mysqli, $senderId, $receiverId);
                return 'accepted';
            }
            if (!$rel['can_send_friend_request']) {
                throw new SocialError(rt_t('Questo utente non accetta richieste di amicizia.', 'This user is not accepting friend requests.'), 'NOT_ALLOWED', 403);
            }

            foreach (sc_request_rows($mysqli, $senderId, $receiverId) as $row) {
                if ((int)$row['sender_id'] === $senderId && $row['status'] === 'declined'
                    && (int)$row['age'] < SC_REQUEST_COOLDOWN_HOURS * 3600) {
                    throw new SocialError(rt_t(
                        'Hai già mandato una richiesta a questo utente da poco. Riprova più avanti.',
                        'You already sent this user a request recently. Try again later.'
                    ), 'COOLDOWN', 429);
                }
            }

            $stmt = $mysqli->prepare("
                SELECT
                    SUM(status = 'pending') AS pending,
                    SUM(status = 'pending' AND updated_at > NOW() - INTERVAL 1 HOUR) AS recent
                FROM friendship_requests WHERE sender_id = ?
            ");
            $stmt->bind_param('i', $senderId);
            $stmt->execute();
            $load = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            if ((int)($load['recent'] ?? 0) >= SC_REQUESTS_PER_HOUR) {
                throw new SocialError(rt_t('Stai mandando troppe richieste. Riprova tra un po\'.', 'You are sending too many requests. Try again in a while.'), 'RATE_LIMIT', 429);
            }
            if ((int)($load['pending'] ?? 0) >= SC_REQUESTS_PENDING_MAX) {
                throw new SocialError(rt_t('Hai troppe richieste in sospeso: annullane qualcuna.', 'You have too many pending requests: cancel some first.'), 'RATE_LIMIT', 429);
            }

            // created_at e responded_at non si toccano: il primo dice quando
            // i due si sono cercati la prima volta, il secondo se sono mai
            // stati amici (vedi sc_accept_pending).
            $stmt = $mysqli->prepare("
                INSERT INTO friendship_requests (sender_id, receiver_id, status)
                VALUES (?, ?, 'pending')
                ON DUPLICATE KEY UPDATE status = 'pending', updated_at = NOW()
            ");
            $stmt->bind_param('ii', $senderId, $receiverId);
            $stmt->execute();
            $stmt->close();
        } finally {
            sc_unlock($mysqli, $lock);
        }

        sc_notify($mysqli, $receiverId, ['t' => 'fr', 'f' => $senderId]);
        rt_push_user($senderId, ['t' => 'sl']);

        return 'pending';
    }

    function sc_request_accept(mysqli $mysqli, int $receiverId, int $senderId): void
    {
        $lock = sc_lock_pair($mysqli, $receiverId, $senderId);
        try {
            if (sc_is_blocked($mysqli, $receiverId, $senderId)) {
                throw new SocialError(rt_t('C\'è un blocco attivo con questo utente.', 'There is an active block with this user.'), 'BLOCKED', 403);
            }
            sc_accept_pending($mysqli, $receiverId, $senderId);
        } finally {
            sc_unlock($mysqli, $lock);
        }
    }

    /** Rifiuta (chi la riceve) o annulla (chi l'ha mandata) una richiesta in sospeso. */
    function sc_request_close(mysqli $mysqli, int $senderId, int $receiverId, string $status): bool
    {
        $status = $status === 'declined' ? 'declined' : 'cancelled';
        $stmt = $mysqli->prepare("UPDATE friendship_requests SET status = ? WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
        $stmt->bind_param('sii', $status, $senderId, $receiverId);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        if ($changed) {
            rt_push_users([$senderId, $receiverId], ['t' => 'sl']);
        }
        return $changed;
    }

    function sc_remove_friend(mysqli $mysqli, int $userId, int $friendId): bool
    {
        $lock = sc_lock_pair($mysqli, $userId, $friendId);
        try {
            [$one, $two] = sc_pair($userId, $friendId);
            $mysqli->begin_transaction();
            try {
                $stmt = $mysqli->prepare('DELETE FROM friendships WHERE user_one_id = ? AND user_two_id = ?');
                $stmt->bind_param('ii', $one, $two);
                $stmt->execute();
                $removed = $stmt->affected_rows > 0;
                $stmt->close();

                // Le righe delle richieste restano come storico (stato
                // «cancelled»): servono a non ricontare la missione.
                $stmt = $mysqli->prepare("
                    UPDATE friendship_requests SET status = 'cancelled'
                    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                      AND status IN ('pending', 'accepted')
                ");
                $stmt->bind_param('iiii', $userId, $friendId, $friendId, $userId);
                $stmt->execute();
                $stmt->close();
                $mysqli->commit();
            } catch (Throwable $e) {
                $mysqli->rollback();
                throw $e;
            }
        } finally {
            sc_unlock($mysqli, $lock);
        }

        rt_push_users([$userId, $friendId], ['t' => 'sl']);
        return $removed;
    }

    // ── Blocchi ────────────────────────────────────────────────────────────

    function sc_block(mysqli $mysqli, int $blockerId, int $blockedId): void
    {
        if ($blockedId <= 0 || $blockedId === $blockerId) {
            throw new SocialError(rt_t('Utente non valido.', 'Invalid user.'), 'INVALID_INPUT');
        }
        if (!sc_user_brief($mysqli, $blockedId)) {
            throw new SocialError(rt_t('Utente non trovato.', 'User not found.'), 'USER_NOT_FOUND', 404);
        }

        [$one, $two] = sc_pair($blockerId, $blockedId);
        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $blockerId, $blockedId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('DELETE FROM friendships WHERE user_one_id = ? AND user_two_id = ?');
            $stmt->bind_param('ii', $one, $two);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare("
                UPDATE friendship_requests SET status = 'cancelled'
                WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                  AND status IN ('pending', 'accepted')
            ");
            $stmt->bind_param('iiii', $blockerId, $blockedId, $blockedId, $blockerId);
            $stmt->execute();
            $stmt->close();

            // La tabella dei follow è stata ritirata: se c'è ancora la si
            // pulisce, se non c'è il blocco deve riuscire lo stesso.
            if (rt_has_table($mysqli, 'user_follows')) {
                $stmt = $mysqli->prepare('DELETE FROM user_follows WHERE (follower_id = ? AND followed_id = ?) OR (follower_id = ? AND followed_id = ?)');
                $stmt->bind_param('iiii', $blockerId, $blockedId, $blockedId, $blockerId);
                $stmt->execute();
                $stmt->close();
            }

            // La chat privata con quella persona esce dalla lista di chi blocca.
            $stmt = $mysqli->prepare('
                UPDATE private_conversation_participants mine
                INNER JOIN private_conversations c ON c.id = mine.conversation_id AND c.is_group = 0
                INNER JOIN private_conversation_participants theirs ON theirs.conversation_id = c.id AND theirs.user_id = ?
                SET mine.is_archived = 1
                WHERE mine.user_id = ?
            ');
            $stmt->bind_param('ii', $blockedId, $blockerId);
            $stmt->execute();
            $stmt->close();

            // Gli inviti ai gruppi ancora in sospeso fra i due decadono.
            if (rt_has_table($mysqli, 'chat_invites')) {
                $stmt = $mysqli->prepare("
                    UPDATE chat_invites SET status = 'cancelled', responded_at = NOW()
                    WHERE status = 'pending' AND ((inviter_id = ? AND invitee_id = ?) OR (inviter_id = ? AND invitee_id = ?))
                ");
                $stmt->bind_param('iiii', $blockerId, $blockedId, $blockedId, $blockerId);
                $stmt->execute();
                $stmt->close();
            }

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        sc_refresh_hidden($mysqli, $blockerId);
        sc_refresh_hidden($mysqli, $blockedId);
        rt_push_user($blockerId, ['t' => 'sl']);
        rt_push_user($blockerId, ['t' => 'ls']);
        // A chi viene bloccato arriva solo «la lista è cambiata»: l'amicizia
        // sparisce senza che nulla gli dica perché.
        rt_push_user($blockedId, ['t' => 'sl']);
    }

    function sc_unblock(mysqli $mysqli, int $blockerId, int $blockedId): void
    {
        $stmt = $mysqli->prepare('DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->bind_param('ii', $blockerId, $blockedId);
        $stmt->execute();
        $stmt->close();
        sc_refresh_hidden($mysqli, $blockerId);
        sc_refresh_hidden($mysqli, $blockedId);
        rt_push_user($blockerId, ['t' => 'sl']);
    }

    // ── Liste ──────────────────────────────────────────────────────────────

    /** Colonne comuni a tutte le liste di utenti, già pronte per il client. */
    function sc_user_select(string $alias = 'u'): string
    {
        return "$alias.id, $alias.username, $alias.display_name, $alias.ruolo, $alias.is_premium,"
            . " TIMESTAMPDIFF(SECOND, $alias.ultimo_accesso, NOW()) AS idle_seconds,"
            . " UNIX_TIMESTAMP($alias.ultimo_accesso) AS last_seen_ts";
    }

    function sc_user_row(array $row, bool $showPresence = true): array
    {
        $idle = $row['idle_seconds'] ?? null;
        $online = $showPresence && $idle !== null && (int)$idle < SC_ONLINE_WINDOW;
        return [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'display_name' => (string)(($row['display_name'] ?? '') ?: $row['username']),
            'ruolo' => (string)($row['ruolo'] ?? 'utente'),
            'is_premium' => (int)($row['is_premium'] ?? 0) === 1,
            'is_online' => $online,
            'last_seen_ts' => ($showPresence && !$online && !empty($row['last_seen_ts'])) ? (int)$row['last_seen_ts'] : null,
        ];
    }

    function sc_friends(mysqli $mysqli, int $userId): array
    {
        $stmt = $mysqli->prepare('
            SELECT ' . sc_user_select('u') . ', UNIX_TIMESTAMP(f.created_at) AS since_ts
            FROM friendships f
            INNER JOIN utenti u ON u.id = IF(f.user_one_id = ?, f.user_two_id, f.user_one_id)
            WHERE f.user_one_id = ? OR f.user_two_id = ?
            ORDER BY (TIMESTAMPDIFF(SECOND, u.ultimo_accesso, NOW()) < ' . (int)SC_ONLINE_WINDOW . ') DESC, u.username ASC
        ');
        $stmt->bind_param('iii', $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $friends = [];
        while ($row = $result->fetch_assoc()) {
            $friend = sc_user_row($row);
            $friend['since_ts'] = $row['since_ts'] !== null ? (int)$row['since_ts'] : null;
            $friend['is_friend'] = true;
            $friends[] = $friend;
        }
        $stmt->close();
        return $friends;
    }

    function sc_requests(mysqli $mysqli, int $userId): array
    {
        $out = ['received' => [], 'sent' => []];
        foreach (['received' => ['receiver_id', 'sender_id'], 'sent' => ['sender_id', 'receiver_id']] as $kind => [$mine, $other]) {
            $stmt = $mysqli->prepare('
                SELECT ' . sc_user_select('u') . ", UNIX_TIMESTAMP(r.updated_at) AS sent_ts
                FROM friendship_requests r
                INNER JOIN utenti u ON u.id = r.$other
                WHERE r.$mine = ? AND r.status = 'pending'
                ORDER BY r.updated_at DESC
                LIMIT 200
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $user = sc_user_row($row, false);
                $user['user_id'] = $user['id'];
                $user['sent_ts'] = $row['sent_ts'] !== null ? (int)$row['sent_ts'] : null;
                $out[$kind][] = $user;
            }
            $stmt->close();
        }
        return $out;
    }

    function sc_blocked(mysqli $mysqli, int $userId): array
    {
        $stmt = $mysqli->prepare('
            SELECT u.id, u.username, u.display_name, u.ruolo, u.is_premium, UNIX_TIMESTAMP(b.created_at) AS blocked_ts
            FROM blocked_users b
            INNER JOIN utenti u ON u.id = b.blocked_id
            WHERE b.blocker_id = ?
            ORDER BY b.created_at DESC
        ');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $list = [];
        while ($row = $result->fetch_assoc()) {
            $user = sc_user_row($row, false);
            $user['blocked_ts'] = $row['blocked_ts'] !== null ? (int)$row['blocked_ts'] : null;
            $list[] = $user;
        }
        $stmt->close();
        return $list;
    }

    /**
     * Aggiunge a una lista di utenti lo stato della relazione con chi guarda,
     * in una query sola per tutta la lista.
     */
    function sc_attach_relations(mysqli $mysqli, int $viewerId, array $users): array
    {
        $ids = array_values(array_unique(array_map(static fn($u) => (int)$u['id'], $users)));
        if (!$ids) {
            return $users;
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $mysqli->prepare("
            SELECT u.id,
                EXISTS(SELECT 1 FROM friendships WHERE user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id)) AS is_friend,
                EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = u.id AND status = 'pending') AS sent,
                EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = u.id AND receiver_id = ? AND status = 'pending') AS received,
                EXISTS(SELECT 1 FROM blocked_users WHERE blocker_id = ? AND blocked_id = u.id) AS blocked
            FROM utenti u WHERE u.id IN ($marks)
        ");
        $params = array_merge([$viewerId, $viewerId, $viewerId, $viewerId, $viewerId], $ids);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[(int)$row['id']] = $row;
        }
        $stmt->close();

        $settings = sc_settings_many($mysqli, $ids);

        foreach ($users as &$user) {
            $row = $map[(int)$user['id']] ?? [];
            $isFriend = !empty($row['is_friend']);
            $sent = !empty($row['sent']);
            $received = !empty($row['received']);
            $user['is_friend'] = $isFriend;
            $user['friend_request_sent'] = $sent;
            $user['friend_request_received'] = $received;
            $user['is_blocked_by_viewer'] = !empty($row['blocked']);
            $user['can_send_friend_request'] = !$isFriend && !$sent && !$received && empty($row['blocked'])
                && ($settings[(int)$user['id']]['requests_from'] ?? 'all') !== 'none';
            $user['can_message'] = $isFriend || (($settings[(int)$user['id']]['dm_from'] ?? 'all') === 'all');
        }
        unset($user);

        return $users;
    }

    function sc_search(mysqli $mysqli, int $viewerId, string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }
        $query = mb_substr($query, 0, 30, 'UTF-8');
        // % e _ scritti dall'utente valgono come caratteri, non come jolly.
        $escaped = addcslashes($query, '\\%_');
        $like = '%' . $escaped . '%';
        $starts = $escaped . '%';
        $limit = max(1, min(30, $limit));

        $stmt = $mysqli->prepare('
            SELECT ' . sc_user_select('u') . '
            FROM utenti u
            WHERE (u.username LIKE ? OR u.display_name LIKE ?) AND u.id <> ? AND ' . sc_active_sql($mysqli, 'u') . '
              AND NOT EXISTS (
                  SELECT 1 FROM blocked_users b
                  WHERE (b.blocker_id = ? AND b.blocked_id = u.id) OR (b.blocker_id = u.id AND b.blocked_id = ?)
              )
            ORDER BY (u.username LIKE ?) DESC, (u.display_name LIKE ?) DESC, u.username ASC
            LIMIT ?
        ');
        $stmt->bind_param('ssiiissi', $like, $like, $viewerId, $viewerId, $viewerId, $starts, $starts, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = sc_user_row($row, false);
        }
        $stmt->close();

        return sc_attach_relations($mysqli, $viewerId, $users);
    }

    /**
     * Persone che potresti conoscere: prima gli amici degli amici, poi chi è
     * stato attivo di recente. Si parte dalle proprie amicizie invece che da
     * tutta la tabella utenti (la versione precedente faceva una sottoquery
     * per ogni utente del sito).
     */
    function sc_suggestions(mysqli $mysqli, int $userId, int $limit = 12): array
    {
        $exclude = '
            AND u.id <> ? AND ' . sc_active_sql($mysqli, 'u') . "
            AND COALESCE(u.profile_visibility, 'public') <> 'private'
            AND NOT EXISTS (SELECT 1 FROM friendships x WHERE x.user_one_id = LEAST(?, u.id) AND x.user_two_id = GREATEST(?, u.id))
            AND NOT EXISTS (
                SELECT 1 FROM friendship_requests r
                WHERE r.status = 'pending' AND ((r.sender_id = ? AND r.receiver_id = u.id) OR (r.sender_id = u.id AND r.receiver_id = ?))
            )
            AND NOT EXISTS (
                SELECT 1 FROM blocked_users b
                WHERE (b.blocker_id = ? AND b.blocked_id = u.id) OR (b.blocker_id = u.id AND b.blocked_id = ?)
            )
        ";

        $stmt = $mysqli->prepare('
            SELECT ' . sc_user_select('u') . ', COUNT(*) AS mutual
            FROM (
                SELECT IF(user_one_id = ?, user_two_id, user_one_id) AS fid
                FROM friendships WHERE user_one_id = ? OR user_two_id = ?
            ) mine
            INNER JOIN friendships f2 ON (f2.user_one_id = mine.fid OR f2.user_two_id = mine.fid)
            INNER JOIN utenti u ON u.id = IF(f2.user_one_id = mine.fid, f2.user_two_id, f2.user_one_id)
            WHERE 1 = 1 ' . $exclude . '
            GROUP BY u.id
            ORDER BY mutual DESC, u.ultimo_accesso DESC
            LIMIT ?
        ');
        $stmt->bind_param('iiiiiiiiiii', $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $user = sc_user_row($row, false);
            $user['mutual_connections'] = (int)$row['mutual'];
            $users[$user['id']] = $user;
        }
        $stmt->close();

        $missing = $limit - count($users);
        if ($missing > 0) {
            $stmt = $mysqli->prepare('
                SELECT ' . sc_user_select('u') . '
                FROM utenti u
                WHERE u.ultimo_accesso >= NOW() - INTERVAL 30 DAY ' . $exclude . '
                ORDER BY u.ultimo_accesso DESC
                LIMIT ?
            ');
            $take = $missing + count($users);
            $stmt->bind_param('iiiiiiii', $userId, $userId, $userId, $userId, $userId, $userId, $userId, $take);
            $stmt->execute();
            $result = $stmt->get_result();
            while (($row = $result->fetch_assoc()) && count($users) < $limit) {
                if (isset($users[(int)$row['id']])) {
                    continue;
                }
                $user = sc_user_row($row, false);
                $user['mutual_connections'] = 0;
                $users[$user['id']] = $user;
            }
            $stmt->close();
        }

        $users = array_values($users);
        $settings = sc_settings_many($mysqli, array_column($users, 'id'));
        $users = array_values(array_filter($users, static fn($u) => ($settings[$u['id']]['requests_from'] ?? 'all') !== 'none'));
        foreach ($users as &$user) {
            $user['is_friend'] = false;
            $user['friend_request_sent'] = false;
            $user['friend_request_received'] = false;
            $user['can_send_friend_request'] = true;
        }
        unset($user);

        return $users;
    }

    function sc_mutual_friends(mysqli $mysqli, int $a, int $b, int $limit = 6): array
    {
        $stmt = $mysqli->prepare('
            SELECT u.id, u.username, u.display_name, u.ruolo, u.is_premium
            FROM (SELECT IF(user_one_id = ?, user_two_id, user_one_id) AS fid FROM friendships WHERE user_one_id = ? OR user_two_id = ?) x
            INNER JOIN (SELECT IF(user_one_id = ?, user_two_id, user_one_id) AS fid FROM friendships WHERE user_one_id = ? OR user_two_id = ?) y ON y.fid = x.fid
            INNER JOIN utenti u ON u.id = x.fid
            ORDER BY u.username ASC
        ');
        $stmt->bind_param('iiiiii', $a, $a, $a, $b, $b, $b);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'username' => (string)$row['username'],
                'display_name' => (string)($row['display_name'] ?: $row['username']),
            ];
        }
        return ['count' => count($list), 'users' => array_slice($list, 0, $limit)];
    }
}
