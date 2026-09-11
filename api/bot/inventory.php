<?php
declare(strict_types=1);

/**
 * Collezione personaggi di un utente, per il comando /inventory del bot.
 *
 * Restituisce solo dati gia' visibili sul profilo pubblico del sito: nessuna
 * statistica di combattimento, nessun dato di account.
 */

require_once __DIR__ . '/bootstrap.php';

bot_require_key();
bot_require_method('GET');

$requesterDiscordId = bot_discord_id('requester_discord_id');
$targetDiscordId = bot_discord_id('target_discord_id', false) ?: $requesterDiscordId;

$user = bot_find_user($mysqli, $targetDiscordId);
if (!$user) {
    bot_json(['ok' => true, 'linked' => false]);
}

$isSelf = hash_equals($requesterDiscordId, $targetDiscordId);
if (bot_profile_is_private($user, $isSelf)) {
    bot_json([
        'ok' => true,
        'linked' => true,
        'private' => true,
        'username' => (string)$user['username'],
    ]);
}

$rarityOrder = ['theone', 'segreto', 'leggendario', 'speciale', 'epico', 'raro', 'comune'];
$rarityFilter = strtolower(trim((string)($_GET['rarity'] ?? '')));
if ($rarityFilter !== '' && !in_array($rarityFilter, $rarityOrder, true)) {
    $rarityFilter = '';
}

$userId = (int)$user['id'];

// `livello` esiste solo dove la migration del gioco e' stata applicata.
$levelSelect = auth_column_exists($mysqli, 'utenti_personaggi', 'livello')
    ? 'up.livello'
    : '1';

$sql = 'SELECT p.id, p.nome, p.`rarità` AS rarita, p.categoria, p.img_url,
               up.`quantità` AS quantita, ' . $levelSelect . ' AS livello, up.data
        FROM utenti_personaggi up
        JOIN personaggi p ON p.id = up.personaggio_id
        WHERE up.utente_id = ?';

if ($rarityFilter !== '') {
    $sql .= ' AND LOWER(p.`rarità`) = ?';
}

$sql .= " ORDER BY FIELD(LOWER(p.`rarità`), 'theone','segreto','leggendario','speciale','epico','raro','comune'), p.nome ASC
          LIMIT 500";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    bot_fail('Unable to prepare the inventory query.', 500);
}

if ($rarityFilter !== '') {
    $stmt->bind_param('is', $userId, $rarityFilter);
} else {
    $stmt->bind_param('i', $userId);
}

$stmt->execute();
$result = $stmt->get_result();

$characters = [];
$byRarity = [];
$totalCopies = 0;

while ($row = $result->fetch_assoc()) {
    $rarity = strtolower((string)($row['rarita'] ?? ''));
    $quantity = max(1, (int)($row['quantita'] ?? 1));
    $totalCopies += $quantity;
    $byRarity[$rarity] = ($byRarity[$rarity] ?? 0) + 1;

    $characters[] = [
        'id' => (int)$row['id'],
        'name' => (string)$row['nome'],
        'rarity' => $rarity,
        'category' => (string)($row['categoria'] ?? ''),
        'image_url' => $row['img_url'] ?? null,
        'quantity' => $quantity,
        'level' => max(1, (int)($row['livello'] ?? 1)),
        'obtained_at' => $row['data'] ?? null,
    ];
}

$stmt->close();

// Il totale reale non e' filtrato ne' tagliato dal LIMIT: serve a dire
// all'utente quanto gli manca alla collezione completa.
$totals = ['unique' => count($characters), 'copies' => $totalCopies];
$stmtTotals = $mysqli->prepare(
    'SELECT COUNT(DISTINCT personaggio_id) AS unique_characters,
            COALESCE(SUM(`quantità`), 0) AS total_copies
     FROM utenti_personaggi WHERE utente_id = ?'
);

if ($stmtTotals) {
    $stmtTotals->bind_param('i', $userId);
    $stmtTotals->execute();
    $row = $stmtTotals->get_result()->fetch_assoc();
    $stmtTotals->close();
    $totals = [
        'unique' => (int)($row['unique_characters'] ?? 0),
        'copies' => (int)($row['total_copies'] ?? 0),
    ];
}

$catalogTotal = 0;
$catalogResult = $mysqli->query('SELECT COUNT(*) AS total FROM personaggi');
if ($catalogResult) {
    $catalogTotal = (int)($catalogResult->fetch_assoc()['total'] ?? 0);
    $catalogResult->free();
}

$rarityCounts = [];
foreach ($rarityOrder as $rarity) {
    if (!empty($byRarity[$rarity])) {
        $rarityCounts[$rarity] = (int)$byRarity[$rarity];
    }
}

bot_json([
    'ok' => true,
    'linked' => true,
    'private' => false,
    'username' => (string)$user['username'],
    'display_name' => bot_display_name($user),
    'is_premium' => (int)($user['is_premium'] ?? 0) === 1,
    'is_self' => $isSelf,
    'filter' => $rarityFilter !== '' ? $rarityFilter : null,
    'totals' => [
        'unique' => $totals['unique'],
        'copies' => $totals['copies'],
        'catalog' => $catalogTotal,
        'listed' => count($characters),
        'truncated' => count($characters) >= 500,
    ],
    'by_rarity' => (object)$rarityCounts,
    'characters' => $characters,
    'profile_url' => '/u/' . rawurlencode((string)$user['username']),
]);
