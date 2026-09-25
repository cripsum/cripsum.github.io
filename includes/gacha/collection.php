<?php

/**
 * Inventario e collezione.
 *
 * Tutto quello che serve alla pagina dell'inventario arriva in un solo
 * payload, costruito con poche query in blocco (prima erano quattro chiamate
 * e fino a quattro query per ogni personaggio posseduto). Qui stanno anche
 * le azioni: preferiti, "visto", wishlist, frammenti, negozio, collezioni,
 * destino.
 *
 * Chi non possiede un personaggio ne riceve solo cio' che il catalogo
 * permette: con `segreto` nemmeno l'id, cosi' i "???" non si svelano
 * guardando la risposta.
 */

require_once __DIR__ . '/public.php';
require_once __DIR__ . '/../game_helpers.php';

class GachaActionException extends RuntimeException
{
    public int $http;

    public function __construct(string $message, int $http = 400)
    {
        parent::__construct($message, $http);
        $this->http = $http;
    }
}

/* ── Letture in blocco ────────────────────────────────────────────────── */

/** Righe dell'inventario di un utente, per id personaggio. */
function gacha_owned_rows(mysqli $mysqli, int $userId, bool $forUpdate = false): array
{
    $schema = gacha_schema($mysqli);
    $cols = ['personaggio_id', '`quantità` AS quantita', 'data'];
    if ($schema['inv_livello']) $cols[] = 'livello';
    if ($schema['inv_visto']) $cols[] = 'visto';
    if ($schema['inv_preferito']) $cols[] = 'preferito';
    if ($schema['inv_ultima']) $cols[] = 'ultima_copia_il';
    if ($schema['inv_usate']) $cols[] = 'copie_usate';

    $stmt = $mysqli->prepare('SELECT ' . implode(', ', $cols) . ' FROM utenti_personaggi WHERE utente_id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[(int)$row['personaggio_id']] = [
            'quantita' => max(0, (int)$row['quantita']),
            'data' => $row['data'] ?? null,
            'livello' => max(1, min(6, (int)($row['livello'] ?? 1))),
            'visto' => (int)($row['visto'] ?? 1) === 1,
            'preferito' => (int)($row['preferito'] ?? 0) === 1,
            'ultima' => $row['ultima_copia_il'] ?? ($row['data'] ?? null),
            'usate' => max(0, (int)($row['copie_usate'] ?? 0)),
        ];
    }
    $stmt->close();
    return $out;
}

function gacha_wishlist_ids(mysqli $mysqli, int $userId): array
{
    if (!gacha_schema($mysqli)['wishlist']) {
        return [];
    }
    $stmt = $mysqli->prepare('SELECT personaggio_id FROM utenti_wishlist WHERE utente_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $ids = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_NUM) as $row) {
        $ids[(int)$row[0]] = true;
    }
    $stmt->close();
    return $ids;
}

/** Righe di game_card_stats dei personaggi indicati, in una query. */
function gacha_card_stats_rows(mysqli $mysqli, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids || !gacha_has_table($mysqli, 'game_card_stats')) {
        return [];
    }
    $out = [];
    try {
        $res = $mysqli->query('SELECT personaggio_id, hp, attack, defense, speed, max_energy, special_name, special_cost, special_cooldown FROM game_card_stats WHERE personaggio_id IN (' . implode(',', $ids) . ')');
        while ($res && ($row = $res->fetch_assoc())) {
            $out[(int)$row['personaggio_id']] = $row;
        }
    } catch (Throwable $e) {
        error_log('[gacha] game_card_stats: ' . $e->getMessage());
    }
    return $out;
}

/** Il personaggio nella forma che si aspettano le funzioni del gioco. */
function gacha_game_row(array $c): array
{
    return [
        'id' => $c['id'],
        'nome' => $c['nome'],
        'rarita' => $c['rarita'],
        'categoria' => $c['categoria'] ?? '',
        'limitato' => $c['limitato'] ? 1 : 0,
        'ruolo' => $c['ruolo'] ?: 'DPS',
    ];
}

/** Copie che mancano per portare un personaggio al MAX dal livello attuale. */
function gacha_copies_to_max(array $c, int $level): int
{
    $total = 0;
    $marker = gd_limited_marker(gacha_game_row($c));
    for ($lvl = max(1, $level); $lvl < 6; $lvl++) {
        $total += gd_get_upgrade_requirement($c['rarita'], $lvl, $marker);
    }
    return $total;
}

/** Copie che non servono piu' a nulla: oltre la base e oltre il MAX. */
function gacha_excess_copies(array $c, int $quantity, int $level): int
{
    return max(0, $quantity - 1 - gacha_copies_to_max($c, $level));
}

/* ── Negozio dei frammenti ────────────────────────────────────────────── */

function gacha_week_key(?int $ts = null): string
{
    return date('o-\WW', $ts ?? time());
}

/** Fine della settimana corrente (lunedi' prossimo a mezzanotte). */
function gacha_week_end(): int
{
    return strtotime('monday next week 00:00');
}

/**
 * Il negozio della settimana di un utente.
 *
 * Si genera la prima volta che lo apre nella settimana e poi resta fisso
 * (tabella gacha_frammenti_negozio): comprare un personaggio non ne fa
 * comparire un altro al suo posto. Per ogni rarita' vengono prima i
 * personaggi che l'utente non ha ancora, poi gli altri; l'ordine dipende da
 * settimana e utente, quindi cambia ogni lunedi' e non e' uguale per tutti.
 * Solo personaggi del pool standard: i limitati non si comprano.
 */
function gacha_frammenti_rotation(mysqli $mysqli, int $userId, ?array $owned = null, ?string $week = null): array
{
    $week ??= gacha_week_key();
    $stored = gacha_has_table($mysqli, 'gacha_frammenti_negozio');
    $chars = gacha_characters($mysqli);

    if ($stored) {
        try {
            $stmt = $mysqli->prepare('SELECT personaggio_id, rarita, prezzo FROM gacha_frammenti_negozio WHERE utente_id = ? AND settimana = ? ORDER BY posizione ASC');
            $stmt->bind_param('is', $userId, $week);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if ($rows) {
                return array_values(array_filter(array_map(static fn($r) => [
                    'personaggio_id' => (int)$r['personaggio_id'],
                    'rarita' => (string)$r['rarita'],
                    'prezzo' => (int)$r['prezzo'],
                ], $rows), static fn($r) => isset($chars[$r['personaggio_id']])));
            }
        } catch (Throwable $e) {
            error_log('[gacha negozio] ' . $e->getMessage());
        }
    }

    $owned ??= $userId > 0 ? gacha_owned_rows($mysqli, $userId) : [];
    $slots = gacha_frammenti_slot();
    $prices = gacha_frammenti_prezzi();
    $byRarity = [];
    foreach ($chars as $c) {
        if ($c['in_pool_standard'] && !$c['limitato'] && $c['rarita_valida'] && $c['catalogo'] !== 'nascosto' && isset($slots[$c['rarita']])) {
            $byRarity[$c['rarita']][] = $c;
        }
    }

    $items = [];
    foreach ($slots as $rarity => $count) {
        $list = $byRarity[$rarity] ?? [];
        usort($list, static function ($a, $b) use ($owned, $week, $userId) {
            $missingA = isset($owned[$a['id']]) ? 1 : 0;
            $missingB = isset($owned[$b['id']]) ? 1 : 0;
            return [$missingA, crc32("$week#$userId#{$a['id']}")] <=> [$missingB, crc32("$week#$userId#{$b['id']}")];
        });
        foreach (array_slice($list, 0, $count) as $c) {
            $items[] = ['personaggio_id' => $c['id'], 'rarita' => $rarity, 'prezzo' => $prices[$rarity]];
        }
    }

    if ($stored && $userId > 0 && $items) {
        try {
            // Le settimane passate non servono piu'.
            $stmt = $mysqli->prepare('DELETE FROM gacha_frammenti_negozio WHERE utente_id = ? AND settimana <> ?');
            $stmt->bind_param('is', $userId, $week);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('INSERT IGNORE INTO gacha_frammenti_negozio (utente_id, settimana, personaggio_id, rarita, prezzo, posizione) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as $i => $item) {
                $pos = $i;
                $stmt->bind_param('isisii', $userId, $week, $item['personaggio_id'], $item['rarita'], $item['prezzo'], $pos);
                $stmt->execute();
            }
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[gacha negozio] ' . $e->getMessage());
        }
    }

    return $items;
}

/* ── Payload dell'inventario ──────────────────────────────────────────── */

function gacha_collection_payload(mysqli $mysqli, int $userId, string $lang): array
{
    $schema = gacha_schema($mysqli);
    $chars = gacha_characters($mysqli);
    $categories = gacha_categories($mysqli);
    $owned = gacha_owned_rows($mysqli, $userId);
    $wishlist = gacha_wishlist_ids($mysqli, $userId);
    $cardRows = gacha_card_stats_rows($mysqli, array_keys($owned));

    // Dove si trova ora ogni personaggio: featured nei banner attivi o
    // annunciati, e il pool standard.
    $inBanners = [];
    $bannerNames = [];
    foreach (gacha_banners($mysqli) as $banner) {
        $status = gacha_banner_status($banner);
        if ($status !== 'attivo' && $status !== 'prossimamente') {
            continue;
        }
        $bannerNames[$banner['key']] = [
            'key' => $banner['key'],
            'nome' => ($lang === 'en' && $banner['nome_en']) ? $banner['nome_en'] : $banner['nome'],
            'stato' => $status,
            'tipo' => $banner['tipo'],
            'img' => gacha_media($banner['thumb'] ?: ($banner['sfondo'] ?: ($banner['featured'] ? ($chars[$banner['featured'][0]['id']]['img_url'] ?? null) : null))),
            'arte' => gacha_media($banner['arte'] ?: ($banner['featured'] ? ($chars[$banner['featured'][0]['id']]['img_url'] ?? null) : null)),
            'costo' => $banner['costo'],
            'data_inizio' => gacha_iso($banner['data_inizio']),
            'data_fine' => gacha_iso($banner['data_fine']),
        ];
        foreach ($banner['featured'] as $entry) {
            $inBanners[$entry['id']][] = $banner['key'];
        }
    }

    $entries = [];
    $maskedIndex = 0;
    $totals = ['visibili' => 0, 'posseduti' => 0, 'standard' => 0, 'standard_posseduti' => 0, 'duplicati' => 0, 'potenziabili' => 0, 'eccesso' => 0, 'nuovi' => 0];

    foreach ($chars as $id => $c) {
        if (!$c['rarita_valida']) {
            continue;
        }
        $own = $owned[$id] ?? null;
        $announced = isset($inBanners[$id]);
        $mode = $announced && $c['catalogo'] !== 'visibile' ? 'visibile' : $c['catalogo'];

        if (!$own && $mode === 'nascosto') {
            continue;
        }

        $totals['visibili']++;
        if ($c['in_pool_standard']) {
            $totals['standard']++;
        }

        if (!$own) {
            $base = [
                'rarita' => $c['rarita'],
                'categoria' => $c['categoria'],
                'limitato' => $c['limitato'],
                'posseduto' => false,
                'standard' => $c['in_pool_standard'],
            ];
            if ($mode === 'segreto') {
                $entries[] = $base + ['key' => 'm' . (++$maskedIndex), 'id' => null, 'mascherato' => true];
            } else {
                $entries[] = $base + [
                    'key' => 'c' . $id,
                    'id' => $id,
                    'mascherato' => false,
                    'nome' => $c['nome'],
                    'img' => gacha_media($c['img_url']),
                    'wishlist' => isset($wishlist[$id]),
                    'banner' => $inBanners[$id] ?? [],
                ];
            }
            continue;
        }

        $level = $own['livello'];
        $game = gacha_game_row($c);
        $marker = gd_limited_marker($game);
        $required = $level < 6 ? gd_get_upgrade_requirement($c['rarita'], $level, $marker) : 0;
        $duplicates = max(0, $own['quantita'] - 1);
        $excess = gacha_excess_copies($c, $own['quantita'], $level);
        $upgradable = $level < 6 && $required > 0 && $duplicates >= $required;

        $totals['posseduti']++;
        if ($c['in_pool_standard']) $totals['standard_posseduti']++;
        if ($duplicates > 0) $totals['duplicati']++;
        if ($upgradable) $totals['potenziabili']++;
        if (!$own['visto']) $totals['nuovi']++;
        $totals['eccesso'] += $excess;

        $entries[] = [
            'key' => 'c' . $id,
            'id' => $id,
            'mascherato' => false,
            'posseduto' => true,
            'nome' => $c['nome'],
            'rarita' => $c['rarita'],
            'categoria' => $c['categoria'],
            'limitato' => $c['limitato'],
            'standard' => $c['in_pool_standard'],
            'img' => gacha_media($c['img_url']),
            'audio' => gacha_media($c['audio_url'], '/audio/'),
            'video' => gacha_media($c['video_url'], '/vid/'),
            'descrizione' => gacha_text($c, 'descrizione', $lang) ?? '',
            'caratteristiche' => gacha_text($c, 'caratteristiche', $lang) ?? '',
            'quantita' => $own['quantita'],
            'livello' => $level,
            'richieste' => $required,
            'potenziabile' => $upgradable,
            'eccesso' => $excess,
            'valore_frammenti' => gacha_frammenti_valori()[$c['rarita']] ?? 0,
            'data' => $own['data'],
            'ultima' => $own['ultima'],
            'nuovo' => !$own['visto'],
            'preferito' => $own['preferito'],
            'banner' => $inBanners[$id] ?? [],
            'stats' => gd_stats_build($id, $game, $cardRows[$id] ?? null, $level),
            'stats_next' => $level < 6 ? gd_stats_build($id, $game, $cardRows[$id] ?? null, $level + 1) : null,
        ];
    }

    // Casse aperte: copie possedute piu' quelle spese (potenziamenti, frammenti).
    $boxes = 0;
    foreach ($owned as $own) {
        $boxes += $own['quantita'] + $own['usate'];
    }

    return [
        'rarita' => gacha_rarity_public($lang),
        'categorie' => gacha_collections_public($mysqli, $userId, $categories, $chars, $owned, $lang),
        'personaggi' => $entries,
        'banner' => array_values($bannerNames),
        'totali' => $totals + ['casse' => $boxes],
        'frammenti' => gacha_frammenti_public($mysqli, $userId, $owned),
        'funzioni' => [
            'wishlist' => $schema['wishlist'],
            'preferiti' => $schema['inv_preferito'],
            'nuovi' => $schema['inv_visto'],
            'frammenti' => $schema['frammenti'],
            'collezioni' => $schema['collezioni'],
        ],
    ];
}

function gacha_rarity_public(string $lang): array
{
    $out = [];
    foreach (gacha_rarity_defs() as $key => $def) {
        $out[] = ['key' => $key, 'label' => $lang === 'en' ? $def['en'] : $def['it'], 'colore' => $def['color']];
    }
    return $out;
}

/** Categorie con il loro stato di collezione (quanti, premio, riscosso). */
function gacha_collections_public(mysqli $mysqli, int $userId, array $categories, array $chars, array $owned, string $lang): array
{
    $claimed = [];
    if (gacha_schema($mysqli)['collezioni']) {
        try {
            $stmt = $mysqli->prepare('SELECT categoria_id FROM utenti_collezioni_premi WHERE utente_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_NUM) as $row) {
                $claimed[(int)$row[0]] = true;
            }
            $stmt->close();
        } catch (Throwable $e) {
            $claimed = [];
        }
    }

    $out = [];
    foreach ($categories as $cat) {
        $name = mb_strtolower($cat['nome'], 'UTF-8');
        $total = 0;
        $have = 0;
        foreach ($chars as $id => $c) {
            if ($c['categoria'] === null || mb_strtolower($c['categoria'], 'UTF-8') !== $name) {
                continue;
            }
            if ($c['catalogo'] === 'nascosto' && !isset($owned[$id])) {
                continue;
            }
            $total++;
            if (isset($owned[$id])) {
                $have++;
            }
        }
        if ($total === 0) {
            continue;
        }
        $out[] = [
            'id' => $cat['id'],
            'nome' => $cat['nome'],
            'label' => ($lang === 'en' && $cat['nome_en']) ? $cat['nome_en'] : $cat['nome'],
            'slug' => $cat['slug'],
            'colore' => $cat['colore'],
            'icona' => $cat['icona'],
            'totale' => $total,
            'posseduti' => $have,
            'premio_godos' => $cat['premio_godos'],
            'premio_badge' => $cat['premio_badge_id'] !== null,
            'riscosso' => $cat['id'] !== null && isset($claimed[$cat['id']]),
        ];
    }
    return $out;
}

function gacha_frammenti_public(mysqli $mysqli, int $userId, array $owned): ?array
{
    if (!gacha_schema($mysqli)['frammenti']) {
        return null;
    }

    $balance = 0;
    $bought = [];
    $week = gacha_week_key();
    try {
        $stmt = $mysqli->prepare('SELECT frammenti FROM utenti WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $balance = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();

        $stmt = $mysqli->prepare('SELECT personaggio_id FROM gacha_frammenti_acquisti WHERE utente_id = ? AND settimana = ?');
        $stmt->bind_param('is', $userId, $week);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_NUM) as $row) {
            $bought[(int)$row[0]] = true;
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[gacha frammenti] ' . $e->getMessage());
    }

    $chars = gacha_characters($mysqli);
    $items = [];
    foreach (gacha_frammenti_rotation($mysqli, $userId, $owned, $week) as $item) {
        $c = $chars[$item['personaggio_id']] ?? null;
        if (!$c) {
            continue;
        }
        $items[] = [
            'id' => $c['id'],
            'nome' => $c['nome'],
            'rarita' => $c['rarita'],
            'img' => gacha_media($c['img_url']),
            'prezzo' => $item['prezzo'],
            'comprato' => isset($bought[$c['id']]),
            'posseduti' => $owned[$c['id']]['quantita'] ?? 0,
            'nuovo' => !isset($owned[$c['id']]),
        ];
    }

    return [
        'saldo' => $balance,
        'valori' => gacha_frammenti_valori(),
        'negozio' => $items,
        'settimana' => $week,
        'rinnovo' => date('c', gacha_week_end()),
    ];
}

/* ── Azioni ───────────────────────────────────────────────────────────── */

function gacha_require_owned(mysqli $mysqli, int $userId, int $characterId): void
{
    $stmt = $mysqli->prepare('SELECT 1 FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $characterId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$ok) {
        throw new GachaActionException('Non possiedi questo personaggio.', 403);
    }
}

/** Segna come visti dei personaggi (spegne il badge NEW). */
function gacha_action_seen(mysqli $mysqli, int $userId, array $ids): int
{
    if (!gacha_schema($mysqli)['inv_visto']) {
        return 0;
    }
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 500);
    if (!$ids) {
        $stmt = $mysqli->prepare('UPDATE utenti_personaggi SET visto = 1 WHERE utente_id = ? AND visto = 0');
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = $mysqli->prepare('UPDATE utenti_personaggi SET visto = 1 WHERE utente_id = ? AND visto = 0 AND personaggio_id IN (' . implode(',', $ids) . ')');
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    return max(0, $n);
}

function gacha_action_favorite(mysqli $mysqli, int $userId, int $characterId, bool $on): bool
{
    if (!gacha_schema($mysqli)['inv_preferito']) {
        throw new GachaActionException('Funzione non ancora disponibile.', 503);
    }
    gacha_require_owned($mysqli, $userId, $characterId);
    $value = $on ? 1 : 0;
    $stmt = $mysqli->prepare('UPDATE utenti_personaggi SET preferito = ? WHERE utente_id = ? AND personaggio_id = ?');
    $stmt->bind_param('iii', $value, $userId, $characterId);
    $stmt->execute();
    $stmt->close();
    return $on;
}

/**
 * Wishlist: solo personaggi che non hai e che puoi vedere (catalogo
 * visibile o annunciati in un banner). Massimo 30.
 */
function gacha_action_wishlist(mysqli $mysqli, int $userId, int $characterId, bool $on): bool
{
    if (!gacha_schema($mysqli)['wishlist']) {
        throw new GachaActionException('Funzione non ancora disponibile.', 503);
    }
    $c = gacha_characters($mysqli)[$characterId] ?? null;
    if (!$c) {
        throw new GachaActionException('Personaggio non trovato.', 404);
    }

    if (!$on) {
        $stmt = $mysqli->prepare('DELETE FROM utenti_wishlist WHERE utente_id = ? AND personaggio_id = ?');
        $stmt->bind_param('ii', $userId, $characterId);
        $stmt->execute();
        $stmt->close();
        return false;
    }

    $announced = false;
    foreach (gacha_banners($mysqli) as $banner) {
        $status = gacha_banner_status($banner);
        if (($status === 'attivo' || $status === 'prossimamente') && in_array($characterId, array_column($banner['featured'], 'id'), true)) {
            $announced = true;
        }
    }
    if ($c['catalogo'] !== 'visibile' && !$announced) {
        throw new GachaActionException('Questo personaggio è ancora un segreto.', 403);
    }

    $stmt = $mysqli->prepare('SELECT 1 FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $characterId);
    $stmt->execute();
    $has = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if ($has) {
        throw new GachaActionException('Ce l\'hai già.', 409);
    }

    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM utenti_wishlist WHERE utente_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($count >= 30) {
        throw new GachaActionException('La wishlist può contenere al massimo 30 personaggi.', 409);
    }

    $stmt = $mysqli->prepare('INSERT IGNORE INTO utenti_wishlist (utente_id, personaggio_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $userId, $characterId);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Converte in frammenti le copie in eccesso: di un personaggio, o di tutti
 * se $characterId e' 0. Tiene sempre la copia base e quelle che servono
 * ancora ai potenziamenti fino al MAX.
 */
function gacha_action_convert(mysqli $mysqli, int $userId, int $characterId = 0, ?int $copies = null, array $rarities = []): array
{
    if (!gacha_schema($mysqli)['frammenti']) {
        throw new GachaActionException('Funzione non ancora disponibile.', 503);
    }
    $chars = gacha_characters($mysqli);
    $values = gacha_frammenti_valori();

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare('SELECT id FROM utenti WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $owned = gacha_owned_rows($mysqli, $userId, true);
        $targets = $characterId > 0 ? [$characterId => $owned[$characterId] ?? null] : $owned;
        // Senza personaggio si puo' scegliere quali rarita' convertire.
        $rarities = array_values(array_filter(array_map('gacha_rarity_key', $rarities)));
        if ($characterId <= 0 && $rarities) {
            $targets = array_filter($targets, static fn($own, $id) => isset($chars[$id]) && in_array($chars[$id]['rarita'], $rarities, true), ARRAY_FILTER_USE_BOTH);
        }
        if ($characterId > 0 && !$targets[$characterId]) {
            throw new GachaActionException('Non possiedi questo personaggio.', 403);
        }

        $gained = 0;
        $converted = [];
        // Le copie convertite restano nelle "casse aperte" (copie_usate).
        $upd = gacha_schema($mysqli)['inv_usate']
            ? $mysqli->prepare('UPDATE utenti_personaggi SET `quantità` = `quantità` - ?, copie_usate = copie_usate + ? WHERE utente_id = ? AND personaggio_id = ? AND `quantità` > ?')
            : $mysqli->prepare('UPDATE utenti_personaggi SET `quantità` = `quantità` - ? WHERE utente_id = ? AND personaggio_id = ? AND `quantità` > ?');
        foreach ($targets as $id => $own) {
            $c = $chars[$id] ?? null;
            if (!$c || !$own || !isset($values[$c['rarita']])) {
                continue;
            }
            $excess = gacha_excess_copies($c, $own['quantita'], $own['livello']);
            $n = $copies !== null && $characterId > 0 ? min(max(0, $copies), $excess) : $excess;
            if ($n <= 0) {
                continue;
            }
            if (gacha_schema($mysqli)['inv_usate']) {
                $upd->bind_param('iiiii', $n, $n, $userId, $id, $n);
            } else {
                $upd->bind_param('iiii', $n, $userId, $id, $n);
            }
            gacha_exec($upd, 'conversione');
            if ($upd->affected_rows > 0) {
                $gained += $n * $values[$c['rarita']];
                $converted[] = ['id' => $id, 'copie' => $n, 'quantita' => $own['quantita'] - $n];
            }
        }
        $upd->close();

        if ($gained <= 0) {
            throw new GachaActionException('Non ci sono copie in eccesso da convertire.', 409);
        }

        $stmt = $mysqli->prepare('UPDATE utenti SET frammenti = frammenti + ? WHERE id = ?');
        $stmt->bind_param('ii', $gained, $userId);
        gacha_exec($stmt, 'frammenti');
        $stmt->close();

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }

    return ['frammenti' => $gained, 'personaggi' => $converted, 'saldo' => gacha_frammenti_balance($mysqli, $userId)];
}

function gacha_frammenti_balance(mysqli $mysqli, int $userId): int
{
    $stmt = $mysqli->prepare('SELECT frammenti FROM utenti WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $v = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $v;
}

/** Compra un personaggio della rotazione di questa settimana. */
function gacha_action_buy(mysqli $mysqli, int $userId, int $characterId): array
{
    if (!gacha_schema($mysqli)['frammenti']) {
        throw new GachaActionException('Funzione non ancora disponibile.', 503);
    }
    $week = gacha_week_key();
    $item = null;
    foreach (gacha_frammenti_rotation($mysqli, $userId, null, $week) as $candidate) {
        if ($candidate['personaggio_id'] === $characterId) {
            $item = $candidate;
        }
    }
    if (!$item) {
        throw new GachaActionException('Questo personaggio non è nel negozio di questa settimana.', 404);
    }

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare('SELECT frammenti FROM utenti WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $balance = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        if ($balance < $item['prezzo']) {
            throw new GachaActionException('Frammenti insufficienti.', 402);
        }

        $price = $item['prezzo'];
        $stmt = $mysqli->prepare('INSERT IGNORE INTO gacha_frammenti_acquisti (utente_id, personaggio_id, settimana, costo) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iisi', $userId, $characterId, $week, $price);
        gacha_exec($stmt, 'acquisto');
        $inserted = $stmt->affected_rows > 0;
        $stmt->close();
        if (!$inserted) {
            throw new GachaActionException('L\'hai già comprato questa settimana.', 409);
        }

        $stmt = $mysqli->prepare('UPDATE utenti SET frammenti = frammenti - ? WHERE id = ? AND frammenti >= ?');
        $stmt->bind_param('iii', $price, $userId, $price);
        gacha_exec($stmt, 'frammenti');
        $stmt->close();

        $isNew = !isset(gacha_owned_rows($mysqli, $userId)[$characterId]);
        require_once __DIR__ . '/../gacha_helpers.php';
        gacha_add_character_to_inventory($mysqli, $userId, $characterId);

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }

    if ($isNew) {
        gacha_wishlist_remove($mysqli, $userId, [$characterId]);
    }

    return ['saldo' => gacha_frammenti_balance($mysqli, $userId), 'nuovo' => $isNew, 'personaggio' => gacha_character_public(gacha_characters($mysqli)[$characterId])];
}

/**
 * Riscuote il premio di una collezione completa. Il premio arriva nella
 * posta, come tutti i premi del sito: da li' si riscuotono Godos e badge.
 */
function gacha_action_claim_collection(mysqli $mysqli, int $userId, int $categoryId): array
{
    if (!gacha_schema($mysqli)['collezioni']) {
        throw new GachaActionException('Funzione non ancora disponibile.', 503);
    }
    $category = null;
    foreach (gacha_categories($mysqli) as $cat) {
        if ($cat['id'] === $categoryId) {
            $category = $cat;
        }
    }
    if (!$category) {
        throw new GachaActionException('Collezione non trovata.', 404);
    }
    if ($category['premio_godos'] <= 0 && $category['premio_badge_id'] === null) {
        throw new GachaActionException('Questa collezione non ha un premio.', 409);
    }

    $owned = gacha_owned_rows($mysqli, $userId);
    $public = gacha_collections_public($mysqli, $userId, [$category], gacha_characters($mysqli), $owned, 'it')[0] ?? null;
    if (!$public || $public['posseduti'] < $public['totale']) {
        throw new GachaActionException('La collezione non è ancora completa.', 409);
    }

    $stmt = $mysqli->prepare('INSERT IGNORE INTO utenti_collezioni_premi (utente_id, categoria_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $userId, $categoryId);
    gacha_exec($stmt, 'collezione');
    $inserted = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$inserted) {
        throw new GachaActionException('Hai già riscosso questo premio.', 409);
    }

    require_once __DIR__ . '/../reward_mail.php';
    $rewards = [];
    if ($category['premio_godos'] > 0) {
        $rewards[] = ['type' => 'points', 'value' => (string)$category['premio_godos'], 'quantity' => 1];
    }
    if ($category['premio_badge_id'] !== null) {
        $rewards[] = ['type' => 'badge', 'value' => (string)$category['premio_badge_id'], 'quantity' => 1];
    }
    $name = $category['nome'];
    $nameEn = $category['nome_en'] ?: $category['nome'];
    $mail = cripsum_send_reward_mail(
        $mysqli, null, [$userId],
        "Collezione completata: $name",
        "Collection completed: $nameEn",
        "Hai trovato tutti i personaggi della collezione «$name». Ecco il tuo premio!",
        "You found every character in the «$nameEn» collection. Here is your reward!",
        $rewards
    );
    if (!$mail['ok']) {
        // Il premio non e' partito: si libera la riscossione per riprovare.
        $stmt = $mysqli->prepare('DELETE FROM utenti_collezioni_premi WHERE utente_id = ? AND categoria_id = ?');
        $stmt->bind_param('ii', $userId, $categoryId);
        $stmt->execute();
        $stmt->close();
        error_log('[gacha collezioni] posta non inviata: ' . ($mail['error'] ?? ''));
        throw new GachaActionException('Non sono riuscito a inviare il premio. Riprova tra poco.', 500);
    }

    return ['riscosso' => true];
}

/** Sceglie il bersaglio del destino su un banner (o lo toglie con 0). */
function gacha_action_destino(mysqli $mysqli, int $userId, string $bannerKey, int $characterId): array
{
    if (!gacha_schema($mysqli)['destino']) {
        throw new GachaActionException('Funzione non ancora disponibile.', 503);
    }
    $banner = gacha_banner_get($mysqli, $bannerKey);
    if (!$banner || !$banner['id'] || gacha_banner_status($banner) !== 'attivo') {
        throw new GachaActionException('Banner non trovato.', 404);
    }
    $bannerId = (int)$banner['id'];

    if ($characterId <= 0) {
        $stmt = $mysqli->prepare('DELETE FROM gacha_destino WHERE utente_id = ? AND banner_id = ?');
        $stmt->bind_param('ii', $userId, $bannerId);
        $stmt->execute();
        $stmt->close();
        return ['bersaglio' => null, 'punti' => 0];
    }

    $public = gacha_banner_public($mysqli, $banner, 'it', []);
    if (!$public['destino'] || !in_array($characterId, $public['destino']['scelte'], true)) {
        throw new GachaActionException('Non puoi scegliere questo personaggio su questo banner.', 400);
    }

    // Cambiare bersaglio azzera i punti, come nei gacha da cui viene l'idea.
    $stmt = $mysqli->prepare(
        'INSERT INTO gacha_destino (utente_id, banner_id, personaggio_id, punti) VALUES (?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE punti = IF(personaggio_id = VALUES(personaggio_id), punti, 0), personaggio_id = VALUES(personaggio_id)'
    );
    $stmt->bind_param('iii', $userId, $bannerId, $characterId);
    gacha_exec($stmt, 'destino');
    $stmt->close();

    $row = gacha_destino_read($mysqli, $userId, $bannerId);
    return ['bersaglio' => $row['bersaglio'] ?? $characterId, 'punti' => $row['punti'] ?? 0];
}
