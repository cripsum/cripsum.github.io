<?php
/**
 * Cripsum™ — posta del sito (inbox).
 *
 * Un messaggio sta in `site_messages`; ogni destinatario ha la sua riga in
 * `site_message_recipients` con lo stato (letto, importante, archiviato,
 * premi riscattati). Qui stanno elenco, conteggi e azioni: li usano
 * api/inbox.php, la pagina della posta e il menu delle notifiche in navbar.
 *
 * I premi li consegna sempre claimMessageRewards() (includes/functions.php),
 * che è l'unico punto protetto dal doppio riscatto.
 */

require_once __DIR__ . '/realtime.php';

if (!function_exists('ib_available')) {

    define('IB_PAGE', 30);

    function ib_available(mysqli $mysqli): bool
    {
        static $ready = null;
        if ($ready === null) {
            $ready = false;
            try {
                $result = $mysqli->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('site_messages', 'site_message_recipients')");
                $ready = $result instanceof mysqli_result && (int)($result->fetch_row()[0] ?? 0) === 2;
            } catch (Throwable $e) {
                error_log('[inbox] tabelle della posta: ' . $e->getMessage());
            }
        }
        return $ready;
    }

    /**
     * La tabella dei premi esiste? Lo si chiede al database, una volta per
     * richiesta, e non alla cache di realtime.php: se la risposta fosse
     * sbagliata i messaggi comparirebbero senza i loro regali, che è peggio
     * di una query in più.
     */
    function ib_has_rewards(mysqli $mysqli): bool
    {
        static $has = null;
        if ($has === null) {
            $has = false;
            try {
                $result = $mysqli->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_message_rewards' LIMIT 1");
                $has = $result instanceof mysqli_result && $result->num_rows > 0;
            } catch (Throwable $e) {
                error_log('[inbox] tabella premi: ' . $e->getMessage());
            }
        }
        return $has;
    }

    /** Le categorie «di servizio» stanno sotto un'unica voce. */
    function ib_system_categories(): array
    {
        return ['system', 'changelog', 'security', 'moderation'];
    }

    /**
     * Condizioni comuni a elenco e azioni in blocco.
     * `category`: '' | system | social | rewards | special.
     * `status`: '' | unread | important | archived.
     *
     * @return array{0: string, 1: string, 2: array}
     */
    function ib_filter_sql(int $userId, array $filters): array
    {
        $where = ['r.recipient_id = ?'];
        $types = 'i';
        $params = [$userId];

        $category = (string)($filters['category'] ?? '');
        if ($category === 'system') {
            $where[] = "m.category IN ('" . implode("','", ib_system_categories()) . "')";
        } elseif ($category !== '') {
            $where[] = 'm.category = ?';
            $types .= 's';
            $params[] = $category;
        }

        switch ((string)($filters['status'] ?? '')) {
            case 'unread':
                $where[] = 'r.is_read = 0 AND r.is_archived = 0';
                break;
            case 'important':
                $where[] = 'r.is_important = 1 AND r.is_archived = 0';
                break;
            case 'archived':
                $where[] = 'r.is_archived = 1';
                break;
            default:
                $where[] = 'r.is_archived = 0';
        }

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $like = '%' . addcslashes(mb_substr($query, 0, 80, 'UTF-8'), '\\%_') . '%';
            $where[] = '(m.title_it LIKE ? OR m.title_en LIKE ? OR m.content_it LIKE ? OR m.content_en LIKE ?)';
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }

        return [implode(' AND ', $where), $types, $params];
    }

    /** Premi di un gruppo di messaggi, in una query sola. */
    function ib_rewards(mysqli $mysqli, array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        if (!$messageIds || !ib_has_rewards($mysqli)) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $mysqli->prepare("SELECT message_id, reward_type, reward_value, quantity FROM site_message_rewards WHERE message_id IN ($marks)");
        $stmt->bind_param(str_repeat('i', count($messageIds)), ...$messageIds);
        $stmt->execute();
        $out = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $out[(int)$row['message_id']][] = [
                'reward_type' => (string)$row['reward_type'],
                'reward_value' => (string)$row['reward_value'],
                'quantity' => (int)$row['quantity'],
            ];
        }
        $stmt->close();
        return $out;
    }

    function ib_shape(array $row, array $rewards): array
    {
        return [
            'id' => (int)$row['message_id'],
            'message_id' => (int)$row['message_id'],
            'category' => (string)$row['category'],
            'title_it' => (string)$row['title_it'],
            'title_en' => (string)($row['title_en'] ?: $row['title_it']),
            'content_it' => (string)$row['content_it'],
            'content_en' => (string)($row['content_en'] ?: $row['content_it']),
            'is_read' => (int)$row['is_read'],
            'is_archived' => (int)$row['is_archived'],
            'is_important' => (int)$row['is_important'],
            'claimed_at' => $row['claimed_at'],
            'created_at' => (string)$row['created_at'],
            'ts' => (int)$row['ts'],
            'has_rewards' => count($rewards),
            'rewards' => $rewards,
        ];
    }

    /**
     * Una pagina di messaggi, dal più recente. `before` è l'id dell'ultimo
     * già mostrato.
     *
     * @return array{messages: array, has_more: bool}
     */
    function ib_list(mysqli $mysqli, int $userId, array $filters = [], int $limit = IB_PAGE, int $before = 0): array
    {
        if (!ib_available($mysqli)) {
            return ['messages' => [], 'has_more' => false];
        }

        $limit = max(1, min(60, $limit));
        [$where, $types, $params] = ib_filter_sql($userId, $filters);
        if ($before > 0) {
            $where .= ' AND m.id < ?';
            $types .= 'i';
            $params[] = $before;
        }
        if (!empty($filters['id'])) {
            $where = 'r.recipient_id = ? AND m.id = ?';
            $types = 'ii';
            $params = [$userId, (int)$filters['id']];
        }

        $take = $limit + 1;
        $stmt = $mysqli->prepare("
            SELECT m.id AS message_id, m.title_it, m.title_en, m.content_it, m.content_en, m.category,
                   m.created_at, UNIX_TIMESTAMP(m.created_at) AS ts,
                   r.is_read, r.is_archived, r.is_important, r.claimed_at
            FROM site_message_recipients r
            INNER JOIN site_messages m ON m.id = r.message_id
            WHERE $where
            ORDER BY m.id DESC
            LIMIT $take
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $rewards = ib_rewards($mysqli, array_column($rows, 'message_id'));

        $messages = [];
        foreach ($rows as $row) {
            $messages[] = ib_shape($row, $rewards[(int)$row['message_id']] ?? []);
        }
        return ['messages' => $messages, 'has_more' => $hasMore];
    }

    /**
     * Numeri per le linguette e le categorie: quanti da leggere in ognuna,
     * quanti importanti, quanti archiviati, quanti premi ancora da riscattare.
     */
    function ib_counts(mysqli $mysqli, int $userId): array
    {
        $out = [
            'unread' => 0, 'important' => 0, 'archived' => 0, 'total' => 0, 'rewards' => 0,
            'categories' => ['system' => 0, 'social' => 0, 'rewards' => 0, 'special' => 0],
            // Quanti messaggi (letti o no) ha ogni categoria: una categoria
            // vuota non ha bisogno della sua linguetta.
            'totals' => ['system' => 0, 'social' => 0, 'rewards' => 0, 'special' => 0],
        ];
        if (!ib_available($mysqli)) {
            return $out;
        }

        $pending = ib_has_rewards($mysqli)
            ? 'SUM(r.is_archived = 0 AND r.claimed_at IS NULL AND EXISTS (SELECT 1 FROM site_message_rewards w WHERE w.message_id = m.id))'
            : '0';
        $stmt = $mysqli->prepare("
            SELECT m.category,
                   SUM(r.is_archived = 0) AS total,
                   SUM(r.is_read = 0 AND r.is_archived = 0) AS unread,
                   SUM(r.is_important = 1 AND r.is_archived = 0) AS important,
                   SUM(r.is_archived = 1) AS archived,
                   $pending AS pending
            FROM site_message_recipients r
            INNER JOIN site_messages m ON m.id = r.message_id
            WHERE r.recipient_id = ?
            GROUP BY m.category
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $category = (string)$row['category'];
            $group = in_array($category, ib_system_categories(), true) ? 'system' : $category;
            $out['total'] += (int)$row['total'];
            $out['unread'] += (int)$row['unread'];
            $out['important'] += (int)$row['important'];
            $out['archived'] += (int)$row['archived'];
            $out['rewards'] += (int)$row['pending'];
            if (isset($out['categories'][$group])) {
                $out['categories'][$group] += (int)$row['unread'];
                $out['totals'][$group] += (int)$row['total'];
            }
        }
        $stmt->close();
        return $out;
    }

    /**
     * Cambia lo stato di uno o più messaggi dell'utente.
     * Azioni: read, unread, star, unstar, archive, unarchive, delete.
     */
    function ib_update(mysqli $mysqli, int $userId, array $messageIds, string $action): int
    {
        $messageIds = array_slice(array_values(array_unique(array_filter(array_map('intval', $messageIds)))), 0, 200);
        if (!$messageIds || !ib_available($mysqli)) {
            return 0;
        }

        $marks = implode(',', array_fill(0, count($messageIds), '?'));
        $sql = match ($action) {
            'read' => "UPDATE site_message_recipients SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE recipient_id = ? AND message_id IN ($marks)",
            'unread' => "UPDATE site_message_recipients SET is_read = 0 WHERE recipient_id = ? AND message_id IN ($marks)",
            'star' => "UPDATE site_message_recipients SET is_important = 1 WHERE recipient_id = ? AND message_id IN ($marks)",
            'unstar' => "UPDATE site_message_recipients SET is_important = 0 WHERE recipient_id = ? AND message_id IN ($marks)",
            'archive' => "UPDATE site_message_recipients SET is_archived = 1 WHERE recipient_id = ? AND message_id IN ($marks)",
            'unarchive' => "UPDATE site_message_recipients SET is_archived = 0 WHERE recipient_id = ? AND message_id IN ($marks)",
            'delete' => "DELETE FROM site_message_recipients WHERE recipient_id = ? AND message_id IN ($marks)",
            default => '',
        };
        if ($sql === '') {
            return 0;
        }

        $params = array_merge([$userId], $messageIds);
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $affected = max(0, (int)$stmt->affected_rows);
        $stmt->close();
        return $affected;
    }

    /** Segna come letti tutti i messaggi non archiviati (di una categoria, se indicata). */
    function ib_read_all(mysqli $mysqli, int $userId, string $category = ''): int
    {
        if (!ib_available($mysqli)) {
            return 0;
        }
        [$where, $types, $params] = ib_filter_sql($userId, ['category' => $category, 'status' => 'unread']);
        $stmt = $mysqli->prepare("
            UPDATE site_message_recipients r
            INNER JOIN site_messages m ON m.id = r.message_id
            SET r.is_read = 1, r.read_at = COALESCE(r.read_at, NOW())
            WHERE $where
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $affected = max(0, (int)$stmt->affected_rows);
        $stmt->close();
        return $affected;
    }

    /**
     * Riscatta i premi di tutti i messaggi che ne hanno ancora, uno alla
     * volta con la funzione di sempre. Si ferma a quaranta per richiesta.
     *
     * @return array{claimed: int, rewards: array, message_ids: int[]}
     */
    function ib_claim_all(mysqli $mysqli, int $userId): array
    {
        $out = ['claimed' => 0, 'rewards' => [], 'message_ids' => []];
        if (!ib_available($mysqli) || !ib_has_rewards($mysqli) || !function_exists('claimMessageRewards')) {
            return $out;
        }

        $stmt = $mysqli->prepare('
            SELECT r.message_id
            FROM site_message_recipients r
            WHERE r.recipient_id = ? AND r.is_archived = 0 AND r.claimed_at IS NULL
              AND EXISTS (SELECT 1 FROM site_message_rewards w WHERE w.message_id = r.message_id)
            ORDER BY r.message_id ASC
            LIMIT 40
        ');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'message_id'));
        $stmt->close();

        foreach ($ids as $messageId) {
            $result = claimMessageRewards($mysqli, $userId, $messageId);
            if (!empty($result['ok'])) {
                $out['claimed']++;
                $out['message_ids'][] = $messageId;
                foreach ((array)($result['rewards'] ?? []) as $reward) {
                    $out['rewards'][] = $reward;
                }
            }
        }
        return $out;
    }

    /** Gli ultimi messaggi non archiviati, in forma breve: per il menu in navbar. */
    function ib_latest(mysqli $mysqli, int $userId, int $limit = 6): array
    {
        if (!ib_available($mysqli)) {
            return [];
        }
        $limit = max(1, min(12, $limit));
        $pending = ib_has_rewards($mysqli)
            ? '(r.claimed_at IS NULL AND EXISTS (SELECT 1 FROM site_message_rewards w WHERE w.message_id = m.id))'
            : '0';
        $stmt = $mysqli->prepare("
            SELECT m.id, m.title_it, m.title_en, m.category, UNIX_TIMESTAMP(m.created_at) AS ts, r.is_read, $pending AS pending
            FROM site_message_recipients r
            INNER JOIN site_messages m ON m.id = r.message_id
            WHERE r.recipient_id = ? AND r.is_archived = 0
            ORDER BY m.id DESC
            LIMIT $limit
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $english = rt_lang() === 'en';
        $out = [];
        foreach ($rows as $row) {
            $category = (string)$row['category'];
            $out[] = [
                'id' => (int)$row['id'],
                'title' => (string)($english ? ($row['title_en'] ?: $row['title_it']) : $row['title_it']),
                'category' => in_array($category, ib_system_categories(), true) ? 'system' : $category,
                'ts' => (int)$row['ts'],
                'unread' => (int)$row['is_read'] === 0,
                'rewards' => (int)$row['pending'] === 1,
            ];
        }
        return $out;
    }
}
