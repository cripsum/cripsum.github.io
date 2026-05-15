<?php

if (!defined('CRIPSUM_GACHA_HELPERS')) {
    define('CRIPSUM_GACHA_HELPERS', true);
}

class GachaApiException extends RuntimeException
{
    public $status;
    public $extra;

    public function __construct(string $message, int $status = 400, array $extra = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->extra = $extra;
    }
}

function gacha_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gacha_throw(string $message, int $status = 400, array $extra = []): void
{
    throw new GachaApiException($message, $status, $extra);
}

function gacha_read_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '{}', true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function gacha_qcol(string $column): string
{
    return '`' . str_replace('`', '``', $column) . '`';
}

function gacha_qfield(string $alias, string $column): string
{
    return gacha_qcol($alias) . '.' . gacha_qcol($column);
}

function gacha_normalize_column_name(string $value): string
{
    $value = strtr($value, [
        'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
        'À' => 'a', 'Á' => 'a', 'È' => 'e', 'É' => 'e', 'Ì' => 'i', 'Í' => 'i', 'Ò' => 'o', 'Ó' => 'o', 'Ù' => 'u', 'Ú' => 'u',
    ]);
    $value = strtolower($value);
    return preg_replace('/[^a-z0-9_]+/', '', $value) ?: '';
}

function gacha_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = false;

    $stmt = $mysqli->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = ? LIMIT 1');
    if (!$stmt) return $cache[$table] = false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $cache[$table] = $exists;
}

function gacha_table_columns(mysqli $mysqli, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = [];

    $result = $mysqli->query('SHOW COLUMNS FROM ' . gacha_qcol($table));
    if (!$result) return $cache[$table] = [];

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['Field'])) $columns[] = $row['Field'];
    }
    $result->free();

    return $cache[$table] = $columns;
}

function gacha_first_existing_column(mysqli $mysqli, string $table, array $columns): ?string
{
    $existingColumns = gacha_table_columns($mysqli, $table);
    if (!$existingColumns) return null;

    foreach ($columns as $wanted) {
        foreach ($existingColumns as $real) {
            if ($real === $wanted) return $real;
        }
    }

    foreach ($columns as $wanted) {
        foreach ($existingColumns as $real) {
            if (strtolower($real) === strtolower($wanted)) return $real;
        }
    }

    foreach ($columns as $wanted) {
        $wantedNorm = gacha_normalize_column_name($wanted);
        foreach ($existingColumns as $real) {
            if (gacha_normalize_column_name($real) === $wantedNorm) return $real;
        }
    }

    return null;
}

function gacha_user_columns(mysqli $mysqli): array
{
    return [
        'id' => 'id',
        'money' => gacha_first_existing_column($mysqli, 'utenti', ['soldi', 'punti', 'points']),
        'role' => gacha_first_existing_column($mysqli, 'utenti', ['ruolo', 'role']),
        'pity_standard' => gacha_first_existing_column($mysqli, 'utenti', ['pity_standard']),
        'pity_evento' => gacha_first_existing_column($mysqli, 'utenti', ['pity_evento']),
        'garantito_evento' => gacha_first_existing_column($mysqli, 'utenti', ['garantito_evento']),
    ];
}

function gacha_character_columns(mysqli $mysqli): array
{
    return [
        'id' => 'id',
        'name' => gacha_first_existing_column($mysqli, 'personaggi', ['nome', 'name']),
        'description' => gacha_first_existing_column($mysqli, 'personaggi', ['descrizione', 'description']),
        'features' => gacha_first_existing_column($mysqli, 'personaggi', ['caratteristiche', 'features', 'traits']),
        'image' => gacha_first_existing_column($mysqli, 'personaggi', ['img_url', 'immagine', 'image_url', 'img']),
        'rarity' => gacha_first_existing_column($mysqli, 'personaggi', ['rarità', 'rarita', 'rarity']),
        'audio' => gacha_first_existing_column($mysqli, 'personaggi', ['audio_url', 'audio']),
        'category' => gacha_first_existing_column($mysqli, 'personaggi', ['categoria', 'category']),
        'video' => gacha_first_existing_column($mysqli, 'personaggi', ['video_url', 'video']),
        'pool_event' => gacha_first_existing_column($mysqli, 'personaggi', ['pool_evento', 'pool_event', 'event_pool']),
        'pool_standard' => gacha_first_existing_column($mysqli, 'personaggi', ['in_pool_standard', 'pool_standard', 'standard_pool']),
    ];
}

function gacha_inventory_columns(mysqli $mysqli): array
{
    return [
        'quantity' => gacha_first_existing_column($mysqli, 'utenti_personaggi', ['quantità', 'quantita', 'quantity']),
        'date' => gacha_first_existing_column($mysqli, 'utenti_personaggi', ['data', 'created_at', 'updated_at']),
    ];
}

function gacha_event_columns(mysqli $mysqli): array
{
    return [
        'id' => 'id',
        'slug' => gacha_first_existing_column($mysqli, 'banner_eventi', ['slug', 'codice', 'code']),
        'name' => gacha_first_existing_column($mysqli, 'banner_eventi', ['nome', 'name', 'titolo']),
        'description' => gacha_first_existing_column($mysqli, 'banner_eventi', ['descrizione', 'description']),
        'rateup' => gacha_first_existing_column($mysqli, 'banner_eventi', ['id_personaggio_rateup', 'personaggio_rateup_id', 'rateup_character_id']),
        'image' => gacha_first_existing_column($mysqli, 'banner_eventi', ['banner_img_url', 'img_url', 'image_url', 'immagine']),
        'cost' => gacha_first_existing_column($mysqli, 'banner_eventi', ['costo_punti', 'costo', 'cost']),
        'active' => gacha_first_existing_column($mysqli, 'banner_eventi', ['attivo', 'active', 'is_active']),
        'starts' => gacha_first_existing_column($mysqli, 'banner_eventi', ['data_inizio', 'starts_at', 'start_at']),
        'ends' => gacha_first_existing_column($mysqli, 'banner_eventi', ['data_fine', 'ends_at', 'end_at']),
    ];
}

function gacha_schema_report(mysqli $mysqli): array
{
    $missing = [];

    foreach (['utenti', 'personaggi', 'utenti_personaggi'] as $table) {
        if (!gacha_table_exists($mysqli, $table)) $missing[] = 'tabella ' . $table;
    }

    $user = gacha_user_columns($mysqli);
    foreach (['money', 'pity_standard', 'pity_evento', 'garantito_evento'] as $key) {
        if (empty($user[$key])) $missing[] = 'utenti.' . $key;
    }

    $character = gacha_character_columns($mysqli);
    foreach (['name', 'rarity', 'image', 'video', 'pool_event', 'pool_standard'] as $key) {
        if (empty($character[$key])) $missing[] = 'personaggi.' . $key;
    }

    $eventMissing = [];
    if (!gacha_table_exists($mysqli, 'banner_eventi')) {
        $eventMissing[] = 'tabella banner_eventi';
    } else {
        $event = gacha_event_columns($mysqli);
        foreach (['rateup'] as $key) {
            if (empty($event[$key])) $eventMissing[] = 'banner_eventi.' . $key;
        }
    }

    return [
        'core_ready' => empty($missing),
        'event_ready' => empty($eventMissing),
        'missing' => array_merge($missing, $eventMissing),
        'core_missing' => $missing,
        'event_missing' => $eventMissing,
    ];
}

function gacha_media_url(?string $path, string $base = '/img/'): ?string
{
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('~^https?://~i', $path) || strpos($path, '/') === 0) return $path;

    $segments = array_map('rawurlencode', explode('/', str_replace('\\', '/', $path)));
    return rtrim($base, '/') . '/' . implode('/', $segments);
}

function gacha_character_select_sql(mysqli $mysqli, string $alias = 'p'): string
{
    $cols = gacha_character_columns($mysqli);
    $select = gacha_qfield($alias, 'id') . ' AS id';
    $select .= $cols['name'] ? ', ' . gacha_qfield($alias, $cols['name']) . ' AS nome' : ", CONCAT('Personaggio #', " . gacha_qfield($alias, 'id') . ') AS nome';
    $select .= $cols['description'] ? ', ' . gacha_qfield($alias, $cols['description']) . ' AS descrizione' : ', NULL AS descrizione';
    $select .= $cols['features'] ? ', ' . gacha_qfield($alias, $cols['features']) . ' AS caratteristiche' : ', NULL AS caratteristiche';
    $select .= $cols['image'] ? ', ' . gacha_qfield($alias, $cols['image']) . ' AS img_url' : ', NULL AS img_url';
    $select .= $cols['rarity'] ? ', ' . gacha_qfield($alias, $cols['rarity']) . ' AS rarita' : ", 'comune' AS rarita";
    $select .= $cols['audio'] ? ', ' . gacha_qfield($alias, $cols['audio']) . ' AS audio_url' : ', NULL AS audio_url';
    $select .= $cols['category'] ? ', ' . gacha_qfield($alias, $cols['category']) . ' AS categoria' : ', NULL AS categoria';
    $select .= $cols['video'] ? ', ' . gacha_qfield($alias, $cols['video']) . ' AS video_url' : ', NULL AS video_url';
    $select .= $cols['pool_event'] ? ', ' . gacha_qfield($alias, $cols['pool_event']) . ' AS pool_evento' : ', 0 AS pool_evento';
    $select .= $cols['pool_standard'] ? ', ' . gacha_qfield($alias, $cols['pool_standard']) . ' AS in_pool_standard' : ', 1 AS in_pool_standard';

    return $select;
}

function gacha_public_character(?array $row): ?array
{
    if (!$row) return null;

    $rarity = (string)($row['rarita'] ?? $row['rarità'] ?? 'comune');

    return [
        'id' => (int)($row['id'] ?? 0),
        'nome' => (string)($row['nome'] ?? 'Personaggio'),
        'descrizione' => $row['descrizione'] ?? null,
        'caratteristiche' => $row['caratteristiche'] ?? null,
        'rarita' => $rarity,
        'rarità' => $rarity,
        'categoria' => $row['categoria'] ?? null,
        'img_url' => $row['img_url'] ?? null,
        'image_url' => gacha_media_url($row['img_url'] ?? null, '/img/'),
        'audio_url' => $row['audio_url'] ?? null,
        'video_url' => $row['video_url'] ?? null,
        'video_src' => gacha_media_url($row['video_url'] ?? null, '/vid/'),
        'pool_evento' => (int)($row['pool_evento'] ?? 0),
        'in_pool_standard' => (int)($row['in_pool_standard'] ?? 1),
    ];
}

function gacha_get_user_state(mysqli $mysqli, int $userId): array
{
    $cols = gacha_user_columns($mysqli);
    $select = 'id';
    $select .= $cols['money'] ? ', ' . gacha_qcol($cols['money']) . ' AS soldi' : ', 0 AS soldi';
    $select .= $cols['pity_standard'] ? ', ' . gacha_qcol($cols['pity_standard']) . ' AS pity_standard' : ', 0 AS pity_standard';
    $select .= $cols['pity_evento'] ? ', ' . gacha_qcol($cols['pity_evento']) . ' AS pity_evento' : ', 0 AS pity_evento';
    $select .= $cols['garantito_evento'] ? ', ' . gacha_qcol($cols['garantito_evento']) . ' AS garantito_evento' : ', 0 AS garantito_evento';
    $select .= $cols['role'] ? ', ' . gacha_qcol($cols['role']) . ' AS ruolo' : ", 'utente' AS ruolo";

    $stmt = $mysqli->prepare("SELECT $select FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) gacha_throw('Query utente non valida.', 500);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) gacha_throw('Utente non trovato.', 404);

    return [
        'id' => (int)$row['id'],
        'punti' => (int)$row['soldi'],
        'soldi' => (int)$row['soldi'],
        'pity_standard' => (int)$row['pity_standard'],
        'pity_evento' => (int)$row['pity_evento'],
        'garantito_evento' => (int)$row['garantito_evento'],
        'ruolo' => (string)($row['ruolo'] ?? 'utente'),
    ];
}

function gacha_get_user_for_update(mysqli $mysqli, int $userId): array
{
    $cols = gacha_user_columns($mysqli);
    $select = 'id, ' . gacha_qcol($cols['money']) . ' AS soldi, ' . gacha_qcol($cols['pity_standard']) . ' AS pity_standard, ' . gacha_qcol($cols['pity_evento']) . ' AS pity_evento, ' . gacha_qcol($cols['garantito_evento']) . ' AS garantito_evento';
    $select .= $cols['role'] ? ', ' . gacha_qcol($cols['role']) . ' AS ruolo' : ", 'utente' AS ruolo";

    $stmt = $mysqli->prepare("SELECT $select FROM utenti WHERE id = ? LIMIT 1 FOR UPDATE");
    if (!$stmt) gacha_throw('Query utente non valida.', 500);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) gacha_throw('Utente non trovato.', 404);

    return [
        'id' => (int)$row['id'],
        'soldi' => (int)$row['soldi'],
        'pity_standard' => (int)$row['pity_standard'],
        'pity_evento' => (int)$row['pity_evento'],
        'garantito_evento' => (int)$row['garantito_evento'],
        'ruolo' => (string)($row['ruolo'] ?? 'utente'),
    ];
}

function gacha_update_user_after_pull(mysqli $mysqli, int $userId, array $values): void
{
    $cols = gacha_user_columns($mysqli);
    $sets = [];
    $types = '';
    $params = [];

    $map = [
        'soldi' => 'money',
        'pity_standard' => 'pity_standard',
        'pity_evento' => 'pity_evento',
        'garantito_evento' => 'garantito_evento',
    ];

    foreach ($map as $valueKey => $columnKey) {
        if (array_key_exists($valueKey, $values) && !empty($cols[$columnKey])) {
            $sets[] = gacha_qcol($cols[$columnKey]) . ' = ?';
            $types .= 'i';
            $params[] = (int)$values[$valueKey];
        }
    }

    if (!$sets) return;

    $types .= 'i';
    $params[] = $userId;

    $stmt = $mysqli->prepare('UPDATE utenti SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) gacha_throw('Query aggiornamento utente non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) gacha_throw('Non sono riuscito ad aggiornare il profilo gacha.', 500);
    $stmt->close();
}

function gacha_get_active_event_banner(mysqli $mysqli): ?array
{
    if (!gacha_table_exists($mysqli, 'banner_eventi')) return null;

    $eventCols = gacha_event_columns($mysqli);
    if (empty($eventCols['rateup'])) return null;

    $select = gacha_qfield('b', 'id') . ' AS banner_id';
    $select .= $eventCols['slug'] ? ', ' . gacha_qfield('b', $eventCols['slug']) . ' AS banner_slug' : ", CONCAT('evento-', " . gacha_qfield('b', 'id') . ') AS banner_slug';
    $select .= $eventCols['name'] ? ', ' . gacha_qfield('b', $eventCols['name']) . ' AS banner_nome' : ", CONCAT('Banner evento #', " . gacha_qfield('b', 'id') . ') AS banner_nome';
    $select .= $eventCols['description'] ? ', ' . gacha_qfield('b', $eventCols['description']) . ' AS banner_descrizione' : ', NULL AS banner_descrizione';
    $select .= $eventCols['image'] ? ', ' . gacha_qfield('b', $eventCols['image']) . ' AS banner_img_url' : ', NULL AS banner_img_url';
    $select .= $eventCols['cost'] ? ', ' . gacha_qfield('b', $eventCols['cost']) . ' AS costo_punti' : ', 100 AS costo_punti';
    $select .= ', ' . gacha_qfield('b', $eventCols['rateup']) . ' AS id_personaggio_rateup';
    $select .= ', ' . gacha_character_select_sql($mysqli, 'p');

    $where = [];
    if ($eventCols['active']) $where[] = gacha_qfield('b', $eventCols['active']) . ' = 1';
    if ($eventCols['starts']) $where[] = '(' . gacha_qfield('b', $eventCols['starts']) . ' IS NULL OR ' . gacha_qfield('b', $eventCols['starts']) . ' <= NOW())';
    if ($eventCols['ends']) $where[] = '(' . gacha_qfield('b', $eventCols['ends']) . ' IS NULL OR ' . gacha_qfield('b', $eventCols['ends']) . ' >= NOW())';

    $sql = 'SELECT ' . $select . ' FROM banner_eventi b JOIN personaggi p ON p.id = ' . gacha_qfield('b', $eventCols['rateup']);
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY ' . ($eventCols['starts'] ? gacha_qfield('b', $eventCols['starts']) . ' DESC, ' : '') . gacha_qfield('b', 'id') . ' DESC LIMIT 1';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    $rateup = gacha_public_character($row);
    $image = $row['banner_img_url'] ?: ($rateup['image_url'] ?? null);

    return [
        'id' => (int)$row['banner_id'],
        'type' => 'evento',
        'slug' => (string)$row['banner_slug'],
        'nome' => (string)$row['banner_nome'],
        'descrizione' => $row['banner_descrizione'] ?: ($rateup['descrizione'] ?? 'Banner limitato Cripsum.'),
        'costo' => max(0, (int)$row['costo_punti']),
        'available' => true,
        'image' => gacha_media_url($image, '/img/'),
        'rateup' => $rateup,
    ];
}

function gacha_get_public_state(mysqli $mysqli, int $userId): array
{
    $schema = gacha_schema_report($mysqli);
    $user = gacha_get_user_state($mysqli, $userId);
    $eventBanner = $schema['event_ready'] ? gacha_get_active_event_banner($mysqli) : null;

    $banners = [
        'standard' => [
            'id' => 'standard',
            'type' => 'standard',
            'slug' => 'standard',
            'nome' => 'Banner Standard',
            'descrizione' => 'Pool permanente con personaggi base e pity a 80 pull.',
            'costo' => 0,
            'available' => $schema['core_ready'],
            'image' => '/img/cassa.png',
            'rateup' => null,
        ],
        'evento' => $eventBanner ?: [
            'id' => 'evento',
            'type' => 'evento',
            'slug' => 'evento',
            'nome' => 'Banner Evento',
            'descrizione' => 'Nessun banner evento attivo.',
            'costo' => 100,
            'available' => false,
            'image' => '/img/cassa.png',
            'rateup' => null,
        ],
    ];

    return [
        'schema' => $schema,
        'user' => [
            'punti' => $user['punti'],
            'ruolo' => $user['ruolo'],
        ],
        'pity' => [
            'standard' => $user['pity_standard'],
            'evento' => $user['pity_evento'],
            'garantito_evento' => (bool)$user['garantito_evento'],
            'max' => 80,
        ],
        'banners' => $banners,
        'active' => !empty($banners['evento']['available']) ? 'evento' : 'standard',
    ];
}

function gacha_draw_weighted_rarity(): string
{
    $weights = [
        'comune' => 5100,
        'raro' => 2800,
        'epico' => 1300,
        'leggendario' => 599,
        'speciale' => 180,
        'segreto' => 20,
        'theone' => 1,
    ];

    $roll = random_int(1, array_sum($weights));
    foreach ($weights as $rarity => $weight) {
        if ($roll <= $weight) return $rarity;
        $roll -= $weight;
    }

    return 'comune';
}

function gacha_normalize_rarity(?string $rarity): string
{
    $rarity = strtolower(trim((string)$rarity));
    $rarity = strtr($rarity, ['à' => 'a', ' ' => '', '_' => '', '-' => '']);
    return $rarity ?: 'comune';
}

function gacha_is_high_rarity(string $rarity): bool
{
    return in_array(gacha_normalize_rarity($rarity), ['segreto', 'theone'], true);
}

function gacha_execute_character_query(mysqli $mysqli, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) gacha_throw('Query pool personaggi non valida.', 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function gacha_select_random_character(mysqli $mysqli, array $options = []): ?array
{
    $cols = gacha_character_columns($mysqli);
    $where = [];
    $types = '';
    $params = [];

    if (!empty($options['rarity'])) {
        $where[] = 'LOWER(' . gacha_qfield('p', $cols['rarity']) . ') = ?';
        $types .= 's';
        $params[] = gacha_normalize_rarity($options['rarity']);
    }

    if (!empty($options['high_only'])) {
        $where[] = 'LOWER(' . gacha_qfield('p', $cols['rarity']) . ") IN ('segreto', 'theone')";
    }

    if (!empty($options['standard_only'])) {
        $where[] = gacha_qfield('p', $cols['pool_standard']) . ' = 1';
    }

    if (!empty($options['category']) && !empty($cols['category'])) {
        $where[] = 'LOWER(' . gacha_qfield('p', $cols['category']) . ') = ?';
        $types .= 's';
        $params[] = strtolower((string)$options['category']);
    }

    $sql = 'SELECT ' . gacha_character_select_sql($mysqli, 'p') . ' FROM personaggi p';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY RAND() LIMIT 1';

    return gacha_execute_character_query($mysqli, $sql, $types, $params);
}

function gacha_select_character_with_fallback(mysqli $mysqli, string $rarity, ?string $category = null): array
{
    $attempts = [];
    if ($category) $attempts[] = ['rarity' => $rarity, 'standard_only' => true, 'category' => $category];
    $attempts[] = ['rarity' => $rarity, 'standard_only' => true];

    foreach (['leggendario', 'epico', 'raro', 'comune'] as $fallbackRarity) {
        if ($fallbackRarity !== gacha_normalize_rarity($rarity)) {
            $attempts[] = ['rarity' => $fallbackRarity, 'standard_only' => true];
        }
    }

    $attempts[] = ['standard_only' => true];

    foreach ($attempts as $attempt) {
        $row = gacha_select_random_character($mysqli, $attempt);
        if ($row) return $row;
    }

    gacha_throw('Il pool standard non contiene personaggi estraibili.', 500);
}

function gacha_select_standard_high_character(mysqli $mysqli): array
{
    $row = gacha_select_random_character($mysqli, ['standard_only' => true, 'high_only' => true]);
    if ($row) return $row;

    foreach (['leggendario', 'epico', 'raro', 'comune'] as $fallbackRarity) {
        $row = gacha_select_random_character($mysqli, ['standard_only' => true, 'rarity' => $fallbackRarity]);
        if ($row) return $row;
    }

    gacha_throw('Nessun personaggio standard disponibile per il pity.', 500);
}

function gacha_get_character_by_id(mysqli $mysqli, int $characterId): ?array
{
    $stmt = $mysqli->prepare('SELECT ' . gacha_character_select_sql($mysqli, 'p') . ' FROM personaggi p WHERE p.id = ? LIMIT 1');
    if (!$stmt) gacha_throw('Query personaggio non valida.', 500);
    $stmt->bind_param('i', $characterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function gacha_user_has_character(mysqli $mysqli, int $userId, int $characterId): bool
{
    $stmt = $mysqli->prepare('SELECT 1 FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
    if (!$stmt) gacha_throw('Query inventario non valida.', 500);
    $stmt->bind_param('ii', $userId, $characterId);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

function gacha_add_character_to_inventory(mysqli $mysqli, int $userId, int $characterId): bool
{
    $wasNew = !gacha_user_has_character($mysqli, $userId, $characterId);
    $cols = gacha_inventory_columns($mysqli);
    $quantityCol = $cols['quantity'];
    $dateCol = $cols['date'];

    if ($quantityCol) {
        $fields = ['utente_id', 'personaggio_id', $quantityCol];
        $values = ['?', '?', '1'];
        $update = [gacha_qcol($quantityCol) . ' = ' . gacha_qcol($quantityCol) . ' + 1'];

        if ($dateCol) {
            $fields[] = $dateCol;
            $values[] = 'NOW()';
            $update[] = gacha_qcol($dateCol) . ' = NOW()';
        }

        $sql = 'INSERT INTO utenti_personaggi (' . implode(', ', array_map('gacha_qcol', $fields)) . ') VALUES (' . implode(', ', $values) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $update);
    } else {
        $fields = ['utente_id', 'personaggio_id'];
        $values = ['?', '?'];
        if ($dateCol) {
            $fields[] = $dateCol;
            $values[] = 'NOW()';
        }
        $sql = 'INSERT IGNORE INTO utenti_personaggi (' . implode(', ', array_map('gacha_qcol', $fields)) . ') VALUES (' . implode(', ', $values) . ')';
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) gacha_throw('Query aggiunta inventario non valida.', 500);
    $stmt->bind_param('ii', $userId, $characterId);
    if (!$stmt->execute()) gacha_throw('Non sono riuscito ad aggiungere il personaggio all inventario.', 500);
    $stmt->close();

    return $wasNew;
}

