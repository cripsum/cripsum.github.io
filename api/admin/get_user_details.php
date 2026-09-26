<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/gacha/config.php';

/*
 * Tutto quello che serve alla scheda utente del pannello, letto dal database
 * al momento: la scheda non riusa mai la riga della lista, che non ha tutte
 * le colonne (da li' veniva il saldo a 0).
 */

$fetchAll = static function (mysqli $mysqli, string $sql, string $types = '', array $params = []): array {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
};

try {
    $userId = (int)($_GET['id'] ?? 0);
    if ($userId <= 0) admin_fail('ID utente non valido.');

    $user = admin_fetch_user($mysqli, $userId);
    if (!$user) admin_fail('Utente non trovato.', 404);

    $user['avatar_url'] = admin_avatar_url($userId);
    $user['profile_url'] = '/u/' . rawurlencode(strtolower((string)$user['username']));
    $secondsSince = $user['seconds_since_active'] ?? null;
    $user['seconds_since_active'] = $secondsSince === null ? null : max(0, (int)$secondsSince);
    $user['is_online'] = $secondsSince !== null && (int)$secondsSince < USER_ONLINE_WINDOW_SECONDS;
    $user['banned_by_username'] = null;
    if (!empty($user['banned_by'])) {
        $row = $fetchAll($mysqli, 'SELECT username FROM utenti WHERE id = ? LIMIT 1', 'i', [(int)$user['banned_by']]);
        $user['banned_by_username'] = $row[0]['username'] ?? null;
    }

    $isSelf = (int)$adminUser['id'] === $userId;
    $roles = [];
    foreach (['utente', 'admin', 'owner'] as $role) {
        $roles[$role] = $role === $user['ruolo'] || admin_can_set_role($adminUser, $user, $role);
    }
    $permissions = [
        'is_self' => $isSelf,
        'can_manage' => admin_can_manage_user($adminUser, $user, $isSelf),
        'can_ban' => !$isSelf && admin_can_manage_user($adminUser, $user, false),
        'roles' => $roles,
    ];

    $currencies = [];
    foreach (admin_user_currencies_public($mysqli) as $currency) {
        $currencies[] = $currency + ['value' => (int)($user[$currency['key']] ?? 0)];
    }

    // Inventario completo, dalla rarita' piu' alta: nella scheda si filtra
    // sul posto, quindi niente pagine.
    $inventory = [];
    $inventoryStats = ['characters' => 0, 'copies' => 0];
    if (admin_table_exists($mysqli, 'utenti_personaggi') && admin_table_exists($mysqli, 'personaggi')) {
        $cols = admin_character_columns($mysqli);
        $qtyCol = admin_inventory_quantity_column($mysqli);
        $select = 'p.id, ' . ($cols['name'] ? 'p.' . admin_qcol($cols['name']) : "CONCAT('Personaggio #', p.id)") . ' AS nome';
        $select .= $cols['image'] ? ', p.' . admin_qcol($cols['image']) . ' AS img_url' : ', NULL AS img_url';
        $select .= $cols['rarity'] ? ', p.' . admin_qcol($cols['rarity']) . ' AS rarita' : ', NULL AS rarita';
        $select .= $cols['category'] ? ', p.' . admin_qcol($cols['category']) . ' AS categoria' : ', NULL AS categoria';
        $select .= $cols['limitato'] ? ', p.' . admin_qcol($cols['limitato']) . ' AS limitato' : ', 0 AS limitato';
        $select .= $qtyCol ? ', COALESCE(up.' . admin_qcol($qtyCol) . ', 1) AS quantita' : ', 1 AS quantita';
        $select .= admin_column_exists($mysqli, 'utenti_personaggi', 'livello') ? ', up.livello' : ', NULL AS livello';
        $select .= admin_column_exists($mysqli, 'utenti_personaggi', 'data') ? ', up.data AS ottenuto_il' : ', NULL AS ottenuto_il';

        foreach ($fetchAll($mysqli, "SELECT $select FROM utenti_personaggi up INNER JOIN personaggi p ON p.id = up.personaggio_id WHERE up.utente_id = ? LIMIT 2000", 'i', [$userId]) as $row) {
            $row['rarita'] = gacha_rarity_key($row['rarita']) ?: (string)$row['rarita'];
            $row['image_url'] = admin_asset_url($row['img_url'] ?? null);
            $row['quantita'] = max(1, (int)$row['quantita']);
            $inventoryStats['characters']++;
            $inventoryStats['copies'] += $row['quantita'];
            $inventory[] = $row;
        }
        usort($inventory, static fn($a, $b) => [gacha_rarity_rank($b['rarita']), $a['nome']] <=> [gacha_rarity_rank($a['rarita']), $b['nome']]);
    }

    $achievements = [];
    if (admin_table_exists($mysqli, 'utenti_achievement') && admin_table_exists($mysqli, 'achievement')) {
        $cols = admin_achievement_columns($mysqli);
        $select = 'a.id, ' . ($cols['name'] ? 'a.' . admin_qcol($cols['name']) : "CONCAT('Achievement #', a.id)") . ' AS nome';
        $select .= $cols['description'] ? ', a.' . admin_qcol($cols['description']) . ' AS descrizione' : ', NULL AS descrizione';
        $select .= $cols['image'] ? ', a.' . admin_qcol($cols['image']) . ' AS img_url' : ', NULL AS img_url';
        $select .= $cols['points'] ? ', a.' . admin_qcol($cols['points']) . ' AS punti' : ', 0 AS punti';
        $select .= admin_column_exists($mysqli, 'utenti_achievement', 'data') ? ', ua.data AS unlocked_at' : ', NULL AS unlocked_at';
        $order = admin_column_exists($mysqli, 'utenti_achievement', 'data') ? 'ua.data DESC' : 'ua.achievement_id DESC';

        foreach ($fetchAll($mysqli, "SELECT $select FROM utenti_achievement ua INNER JOIN achievement a ON a.id = ua.achievement_id WHERE ua.utente_id = ? ORDER BY $order LIMIT 500", 'i', [$userId]) as $row) {
            $row['image_url'] = admin_asset_url($row['img_url'] ?? null);
            $achievements[] = $row;
        }
    }

    // Tutti i badge del sito, con quelli dell'utente segnati: nella scheda
    // si accendono e spengono come interruttori.
    $badges = null;
    if (admin_table_exists($mysqli, 'custom_badges') && admin_table_exists($mysqli, 'user_custom_badges')) {
        $select = 'b.id, b.name';
        foreach (['color', 'image_url', 'icon'] as $column) {
            $select .= admin_column_exists($mysqli, 'custom_badges', $column) ? ", b.$column" : ", NULL AS $column";
        }
        $select .= ', (ub.badge_id IS NOT NULL) AS owned';
        $select .= admin_column_exists($mysqli, 'user_custom_badges', 'is_visible') ? ', ub.is_visible' : ', 1 AS is_visible';
        $badges = [];
        foreach ($fetchAll($mysqli, "SELECT $select FROM custom_badges b LEFT JOIN user_custom_badges ub ON ub.badge_id = b.id AND ub.utente_id = ? ORDER BY owned DESC, b.name ASC", 'i', [$userId]) as $row) {
            $row['owned'] = (int)$row['owned'] === 1;
            $row['image_url'] = admin_asset_url($row['image_url'] ?? null);
            $badges[] = $row;
        }
    }

    $gacha = null;
    if (admin_table_exists($mysqli, 'gacha_pull_history') || admin_table_exists($mysqli, 'gacha_pity')) {
        $gacha = ['pulls_total' => 0, 'pity' => [], 'recent' => []];
        if (admin_table_exists($mysqli, 'gacha_pity')) {
            $gacha['pity'] = $fetchAll($mysqli, 'SELECT gruppo, contatore, garantito FROM gacha_pity WHERE utente_id = ? ORDER BY gruppo ASC', 'i', [$userId]);
        }
        if (admin_table_exists($mysqli, 'gacha_pull_history')) {
            $row = $fetchAll($mysqli, 'SELECT COUNT(*) AS total FROM gacha_pull_history WHERE utente_id = ?', 'i', [$userId]);
            $gacha['pulls_total'] = (int)($row[0]['total'] ?? 0);
            $nameCol = admin_character_columns($mysqli)['name'];
            $rarityCol = admin_first_existing_column($mysqli, 'gacha_pull_history', ['rarità', 'rarita', 'rarity']);
            $sql = 'SELECT h.personaggio_id, h.banner_id, h.created_at'
                . ($rarityCol ? ', h.' . admin_qcol($rarityCol) . ' AS rarita' : ', NULL AS rarita')
                . ($nameCol ? ', p.' . admin_qcol($nameCol) . ' AS nome' : ", CONCAT('Personaggio #', h.personaggio_id) AS nome")
                . ' FROM gacha_pull_history h LEFT JOIN personaggi p ON p.id = h.personaggio_id WHERE h.utente_id = ? ORDER BY h.id DESC LIMIT 12';
            foreach ($fetchAll($mysqli, $sql, 'i', [$userId]) as $pull) {
                $pull['rarita'] = gacha_rarity_key($pull['rarita']) ?: (string)$pull['rarita'];
                $gacha['recent'][] = $pull;
            }
        }
    }
    // Schema vecchio: il pity sta nelle colonne di `utenti`.
    if ($gacha === null || (!$gacha['pity'] && !admin_table_exists($mysqli, 'gacha_pity'))) {
        foreach (gacha_legacy_pity_groups() as $group => $legacy) {
            if (!admin_column_exists($mysqli, 'utenti', $legacy['contatore'])) continue;
            $row = $fetchAll($mysqli, 'SELECT ' . admin_qcol($legacy['contatore']) . ' AS contatore'
                . ($legacy['garantito'] && admin_column_exists($mysqli, 'utenti', $legacy['garantito']) ? ', ' . admin_qcol($legacy['garantito']) . ' AS garantito' : ', 0 AS garantito')
                . ' FROM utenti WHERE id = ?', 'i', [$userId]);
            if ($row) {
                $gacha ??= ['pulls_total' => null, 'pity' => [], 'recent' => []];
                $gacha['pity'][] = ['gruppo' => $group, 'contatore' => (int)$row[0]['contatore'], 'garantito' => (int)$row[0]['garantito']];
            }
        }
    }

    $logs = [];
    if (admin_table_exists($mysqli, 'admin_logs')) {
        $logs = $fetchAll($mysqli, '
            SELECT l.id, l.action, l.details, l.created_at, l.admin_id, a.username AS admin_username
            FROM admin_logs l
            LEFT JOIN utenti a ON a.id = l.admin_id
            WHERE l.target_user_id = ?
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT 30', 'i', [$userId]);
        foreach ($logs as &$log) {
            $log['details_parsed'] = $log['details'] ? json_decode($log['details'], true) : null;
        }
        unset($log);
    }

    $rarities = [];
    foreach (gacha_rarity_defs() as $key => $def) {
        $rarities[$key] = ['label' => $def['it'], 'color' => $def['color']];
    }

    admin_ok([
        'user' => $user,
        'permissions' => $permissions,
        'currencies' => $currencies,
        'inventory' => $inventory,
        'inventory_stats' => $inventoryStats,
        'achievements' => $achievements,
        'badges' => $badges,
        'gacha' => $gacha,
        'logs' => $logs,
        'rarities' => $rarities,
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento dettagli utente. Dettaglio: ' . $e->getMessage(), 500);
}
