<?php

/**
 * I banner come li vede chi gioca: la lootbox, le API e il bot.
 *
 * Qui si decide cosa si mostra (niente segreti non ancora trovati nei pool)
 * e si calcolano le probabilita' dei dettagli con le stesse funzioni che
 * usa il motore, cosi' i numeri mostrati sono quelli veri.
 */

require_once __DIR__ . '/engine.php';

/** Data del DB in ISO 8601 con fuso, per i conti alla rovescia del JS. */
function gacha_iso(?string $datetime): ?string
{
    $ts = gacha_ts($datetime);
    return $ts === null ? null : date('c', $ts);
}

function gacha_text(array $row, string $field, string $lang): ?string
{
    if ($lang === 'en' && !empty($row[$field . '_en'])) {
        return (string)$row[$field . '_en'];
    }
    return isset($row[$field]) && $row[$field] !== '' ? (string)$row[$field] : null;
}

/** Il personaggio featured come lo mostra la lootbox (e' annunciato, si vede). */
function gacha_featured_public(array $c, string $lang): array
{
    return [
        'id' => $c['id'],
        'nome' => $c['nome'],
        'rarita' => $c['rarita'],
        'img' => gacha_media($c['img_url']),
        'img_url' => $c['img_url'],
        'descrizione' => gacha_text($c, 'descrizione', $lang) ?? '',
        'limitato' => $c['limitato'],
    ];
}

/**
 * Un banner per la lootbox. $pity e' lo stato del suo gruppo, $usage le
 * pull gia' fatte dall'utente (o null se non serve).
 */
function gacha_banner_public(mysqli $mysqli, array $banner, string $lang, array $pity, ?array $usage = null, ?array $destino = null): array
{
    $chars = gacha_characters($mysqli);
    $pool = gacha_banner_pool($mysqli, $banner);
    $profile = gacha_banner_pity_profile($banner);
    $gps = gacha_godos_per_shard($mysqli);

    $featured = [];
    foreach ($banner['featured'] as $entry) {
        if (isset($chars[$entry['id']])) {
            $featured[] = gacha_featured_public($chars[$entry['id']], $lang);
        }
    }

    // Il destino ha senso solo con piu' featured nella fascia del pity.
    $pityTier = gacha_rarity_tier($profile['soglia']);
    $topChoices = array_values(array_filter($featured, static fn($f) => gacha_rarity_tier($f['rarita']) === $pityTier));
    $destinoPublic = null;
    if (count($topChoices) > 1 && $banner['destino_max'] > 0 && $banner['id'] && gacha_schema($mysqli)['destino']) {
        $destinoPublic = [
            'max' => $banner['destino_max'],
            'bersaglio' => $destino['bersaglio'] ?? null,
            'punti' => $destino['punti'] ?? 0,
            'scelte' => array_map(static fn($f) => $f['id'], $topChoices),
        ];
    }

    $art = $banner['arte'] ? gacha_media($banner['arte']) : ($featured[0]['img'] ?? '/img/cassa.png');

    return [
        'key' => $banner['key'],
        'id' => $banner['id'],
        'tipo' => $banner['tipo'],
        'stato' => gacha_banner_status($banner),
        'nome' => ($lang === 'en' && $banner['nome_en']) ? $banner['nome_en'] : $banner['nome'],
        'descrizione' => ($lang === 'en' && $banner['descrizione_en']) ? $banner['descrizione_en'] : ($banner['descrizione'] ?? ''),
        'costo' => $banner['costo'],
        'costo_shards' => $banner['costo'] > 0 ? (int)ceil($banner['costo'] / $gps) : 0,
        'sfondo' => gacha_media($banner['sfondo']),
        'thumb' => gacha_media($banner['thumb'] ?: ($banner['sfondo'] ?: ($featured[0]['img_url'] ?? null))),
        'arte' => $art,
        'colore' => $banner['colore'],
        'featured' => $featured,
        'pool_modo' => $banner['pool_modo'],
        'pool_categoria' => $banner['pool_categoria'],
        'pool_count' => gacha_pool_size($pool),
        'pity_gruppo' => $banner['pity_gruppo'],
        'pity' => [
            'soft' => $profile['soft'],
            'hard' => $profile['hard'],
            'soglia' => $profile['soglia'],
            'contatore' => (int)($pity['contatore'] ?? 0),
            'garantito' => (bool)($pity['garantito'] ?? false),
            'condiviso' => $banner['pity_gruppo'] === 'evento',
        ],
        'garanzia_multi' => $banner['garanzia_multi'],
        'solo_premium' => $banner['solo_premium'],
        'uso' => $usage ? gacha_usage_public($banner, $usage) : gacha_usage_public($banner, ['totale' => 0, 'oggi' => 0, 'gratis_oggi' => 0]),
        'destino' => $destinoPublic,
        'data_inizio' => gacha_iso($banner['data_inizio']),
        'data_fine' => gacha_iso($banner['data_fine']),
    ];
}

/**
 * Tutto quello che serve alla lootbox: i banner da mostrare (attivi e in
 * arrivo annunciati), il pity di ogni gruppo, i saldi.
 */
function gacha_lootbox_state(mysqli $mysqli, int $userId, string $lang): array
{
    gacha_wishlist_dispatch($mysqli);

    $visible = [];
    foreach (gacha_banners($mysqli) as $banner) {
        $status = gacha_banner_status($banner);
        if ($status === 'attivo' || $status === 'prossimamente') {
            $visible[] = $banner;
        }
    }

    $pityAll = gacha_pity_all($mysqli, $userId, array_map(static fn($b) => $b['pity_gruppo'], $visible));

    $destini = [];
    if (gacha_schema($mysqli)['destino']) {
        try {
            $stmt = $mysqli->prepare('SELECT banner_id, personaggio_id, punti FROM gacha_destino WHERE utente_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $destini[(int)$row['banner_id']] = ['bersaglio' => (int)$row['personaggio_id'], 'punti' => (int)$row['punti']];
            }
            $stmt->close();
        } catch (Throwable $e) {
            $destini = [];
        }
    }

    $banners = [];
    foreach ($visible as $banner) {
        $needsUsage = $banner['limite_pull_utente'] || $banner['limite_pull_giorno'] || $banner['pull_gratis_giorno'];
        $usage = $needsUsage ? gacha_banner_usage($mysqli, $userId, $banner['key']) : null;
        $banners[] = gacha_banner_public(
            $mysqli, $banner, $lang,
            $pityAll[$banner['pity_gruppo']] ?? [],
            $usage,
            $banner['id'] ? ($destini[(int)$banner['id']] ?? null) : null
        );
    }

    return [
        'banners' => $banners,
        'pity' => $pityAll,
        'godos_per_shard' => gacha_godos_per_shard($mysqli),
    ];
}

/* ── Dettagli e probabilita' ──────────────────────────────────────────── */

/**
 * Probabilita' di un banner senza pity: per rarita', per featured e per
 * gli altri personaggi, piu' le regole del pity. La stessa matematica del
 * motore: rarita' vuote a zero, quota dei featured nella loro fascia.
 */
function gacha_banner_rates(mysqli $mysqli, array $banner): array
{
    $pool = gacha_banner_pool($mysqli, $banner);
    $weights = gacha_banner_weights($banner, $pool);
    $total = array_sum($weights) ?: 1;
    $profile = gacha_banner_pity_profile($banner);
    $pityTier = gacha_rarity_tier($profile['soglia']);

    $rarities = [];
    foreach ($weights as $key => $weight) {
        $rarities[$key] = $weight / $total * 100;
    }

    $perCharacter = [];
    $featuredTiers = [];
    foreach ($pool as $key => $entries) {
        foreach ($entries as $entry) {
            if ($entry['featured']) {
                $featuredTiers[gacha_rarity_tier($key)] = true;
            }
        }
    }

    foreach (array_keys($featuredTiers) as $tier) {
        $tierRarities = gacha_tier_rarities($tier);
        $tierPct = 0.0;
        $featured = [];
        foreach ($tierRarities as $key) {
            $tierPct += $rarities[$key] ?? 0;
            foreach ($pool[$key] ?? [] as $entry) {
                if ($entry['featured']) {
                    $featured[] = $entry;
                }
            }
        }
        $quota = gacha_banner_featured_quota($banner, $tierRarities[0]) / 100;
        $sumPeso = array_sum(array_column($featured, 'peso')) ?: 1;
        foreach ($featured as $entry) {
            $perCharacter[$entry['id']] = $tierPct * $quota * $entry['peso'] / $sumPeso;
        }
    }

    foreach ($pool as $key => $entries) {
        $others = array_values(array_filter($entries, static fn($e) => !$e['featured']));
        if (!$others) {
            continue;
        }
        $share = isset($featuredTiers[gacha_rarity_tier($key)])
            ? 1 - gacha_banner_featured_quota($banner, $key) / 100
            : 1;
        $sumPeso = array_sum(array_column($others, 'peso')) ?: 1;
        foreach ($others as $entry) {
            $perCharacter[$entry['id']] = ($rarities[$key] ?? 0) * $share * $entry['peso'] / $sumPeso;
        }
    }

    $hasTopFeatured = isset($featuredTiers[$pityTier]);
    $quotaTop = gacha_banner_featured_quota($banner, gacha_tier_rarities($pityTier)[0] ?? $profile['soglia']);

    return [
        'rarita' => $rarities,
        'personaggi' => $perCharacter,
        'pity' => [
            'soft' => $profile['soft'],
            'hard' => $profile['hard'],
            'soglia' => $profile['soglia'],
            'gruppo' => $banner['pity_gruppo'],
            'condiviso' => $banner['pity_gruppo'] === 'evento',
            'quota' => $hasTopFeatured ? $quotaTop : null,
            // Nel caso peggiore: hard pity perso al 50/50, poi garantito.
            'featured_entro' => $hasTopFeatured ? ($quotaTop >= 100 ? $profile['hard'] + 1 : 2 * ($profile['hard'] + 1)) : null,
        ],
        'garanzia_multi' => $banner['garanzia_multi'] ? GACHA_MULTI_GUARANTEE_RARITY : null,
        'pool' => $pool,
    ];
}

/**
 * Dettagli di un banner per la modale "Dettagli e probabilita'". I
 * personaggi del pool che l'utente non ha e che il catalogo tiene segreti
 * escono come "???" con la sola rarita'; i featured si vedono sempre.
 */
function gacha_banner_details(mysqli $mysqli, array $banner, int $userId, string $lang): array
{
    $rates = gacha_banner_rates($mysqli, $banner);
    $chars = gacha_characters($mysqli);

    $owned = [];
    try {
        $stmt = $mysqli->prepare('SELECT personaggio_id FROM utenti_personaggi WHERE utente_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_row()) {
            $owned[(int)$row[0]] = true;
        }
        $stmt->close();
    } catch (Throwable $e) {
        $owned = [];
    }

    $pool = [];
    foreach (array_reverse($rates['pool'], true) as $key => $entries) {
        foreach ($entries as $entry) {
            $c = $chars[$entry['id']] ?? null;
            if (!$c) {
                continue;
            }
            $isOwned = isset($owned[$c['id']]);
            $mode = $c['catalogo'];
            if (!$entry['featured'] && !$isOwned && $mode === 'nascosto') {
                continue;
            }
            $masked = !$entry['featured'] && !$isOwned && $mode !== 'visibile';
            $pool[] = [
                'id' => $masked ? null : $c['id'],
                'nome' => $masked ? '???' : $c['nome'],
                'rarita' => $key,
                'img' => $masked ? null : gacha_media($c['img_url']),
                'featured' => $entry['featured'],
                'posseduto' => $isOwned,
                'limitato' => $c['limitato'],
                'prob' => round($rates['personaggi'][$c['id']] ?? 0, 5),
            ];
        }
    }

    $rarities = [];
    foreach (array_reverse($rates['rarita'], true) as $key => $pct) {
        if ($pct <= 0) {
            continue;
        }
        $rarities[] = [
            'rarita' => $key,
            'label' => gacha_rarity_label($key, $lang),
            'colore' => gacha_rarity_defs()[$key]['color'],
            'prob' => round($pct, 4),
            'count' => count($rates['pool'][$key] ?? []),
        ];
    }

    $public = gacha_banner_public($mysqli, $banner, $lang, []);

    return [
        'banner' => [
            'key' => $public['key'],
            'nome' => $public['nome'],
            'descrizione' => $public['descrizione'],
            'pool_modo' => $public['pool_modo'],
            'pool_categoria' => $public['pool_categoria'],
            'costo' => $public['costo'],
            'costo_shards' => $public['costo_shards'],
            'data_inizio' => $public['data_inizio'],
            'data_fine' => $public['data_fine'],
            'featured' => $public['featured'],
            'uso' => $public['uso'],
        ],
        'rarita' => $rarities,
        'pity' => $rates['pity'],
        'garanzia_multi' => $rates['garanzia_multi'],
        'pool' => $pool,
    ];
}

/* ── Forma storica di api_gacha_banners (usata anche dal bot) ─────────── */

function gacha_banners_legacy_payload(mysqli $mysqli, int $userId): array
{
    $state = gacha_lootbox_state($mysqli, $userId, 'it');
    $chars = gacha_characters($mysqli);

    $userRow = ['soldi' => 0, 'godoshards_balance' => 0, 'pity_standard' => 0, 'pity_evento' => 0, 'garantito_evento' => 0];
    try {
        $cols = 'soldi, pity_standard, pity_evento, garantito_evento' . (gacha_schema($mysqli)['shards'] ? ', godoshards_balance' : '');
        $stmt = $mysqli->prepare("SELECT $cols FROM utenti WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $userRow = array_merge($userRow, $stmt->get_result()->fetch_assoc() ?: []);
        $stmt->close();
    } catch (Throwable $e) {
        // Resta a zero.
    }

    $standard = null;
    $eventi = [];
    foreach ($state['banners'] as $b) {
        if ($b['stato'] !== 'attivo') {
            continue;
        }
        $bannerRow = gacha_banner_get($mysqli, $b['key']);
        $rateup = $b['featured'][0] ?? null;
        $rateupChar = $rateup ? ($chars[$rateup['id']] ?? null) : null;
        $item = [
            'tipo' => $b['tipo'] === 'standard' ? 'standard' : 'evento',
            'id' => $b['tipo'] === 'standard' ? 'standard' : (int)$b['id'],
            'slug' => $bannerRow['slug'] ?? '',
            'nome' => $b['nome'],
            'descrizione' => $b['descrizione'],
            // Lo standard non aveva immagine: il bot mette la cassa.
            'banner_img_url' => $b['tipo'] === 'standard' ? null : ($bannerRow['sfondo'] ?? null),
            'costo' => $b['costo'],
            'pity_soft' => $b['pity']['soft'],
            'pity_hard' => $b['pity']['hard'],
            'data_inizio' => $bannerRow['data_inizio'] ?? null,
            'data_fine' => $bannerRow['data_fine'] ?? null,
            'attivo' => true,
            'personaggio_rateup' => $rateupChar ? [
                'id' => $rateupChar['id'],
                'nome' => $rateupChar['nome'],
                'descrizione' => $rateupChar['descrizione'],
                'rarità' => $rateupChar['rarita'],
                'img_url' => $rateupChar['img_url'],
                'video_url' => $rateupChar['video_url'],
                'caratteristiche' => $rateupChar['caratteristiche'],
            ] : null,
            'personaggi_rateup' => array_map(static fn($f) => ['id' => $f['id'], 'nome' => $f['nome'], 'rarità' => $f['rarita'], 'img_url' => $f['img_url']], $b['featured']),
            'pity_gruppo' => $b['pity_gruppo'],
            'pity_utente' => $b['pity']['contatore'],
            'pity_condiviso' => $b['pity']['contatore'],
            'garantito_attivo' => $b['pity']['garantito'],
        ];
        if ($b['tipo'] === 'standard') {
            $standard = $item;
        } else {
            $eventi[] = $item;
        }
    }

    return [
        'status' => 'success',
        'soldi' => (int)$userRow['soldi'],
        'shards' => (int)$userRow['godoshards_balance'],
        'pity_standard' => (int)$userRow['pity_standard'],
        'pity_evento' => (int)$userRow['pity_evento'],
        'garantito' => (bool)$userRow['garantito_evento'],
        'standard' => $standard,
        'eventi' => $eventi,
    ];
}
