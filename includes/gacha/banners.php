<?php

/**
 * Personaggi e banner del gacha.
 *
 * Qui si leggono dal database e si portano tutti nella stessa forma, sia che
 * la migration sia stata applicata (tabella `gacha_banner` con pool, pesi e
 * gruppi di pity) sia che no (vecchia `banner_eventi`, un rate-up a banner).
 * Il motore delle pull, la lootbox, il bot e il pannello leggono solo questa
 * forma normalizzata e non sanno quale schema c'e' sotto.
 */

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/../shop/gacha_catalog.php';

/* ── Personaggi ───────────────────────────────────────────────────────── */

/**
 * Tutti i personaggi, per id. Una query sola, ripetuta al massimo una volta
 * per richiesta: sono circa duecento righe.
 */
function gacha_characters(mysqli $mysqli, bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $available = gacha_cols($mysqli, 'personaggi');
    if (!$available) {
        return $cache = [];
    }

    $wanted = [
        'id', 'nome', 'descrizione', 'descrizione_en', 'rarità', 'categoria', 'img_url', 'audio_url',
        'video_url', 'caratteristiche', 'caratteristiche_en', 'in_pool_standard', 'limitato', 'catalogo',
        'aggiunto_il', 'ruolo',
    ];
    $select = [];
    foreach ($wanted as $col) {
        if (in_array($col, $available, true)) {
            $select[] = '`' . $col . '`';
        }
    }

    $out = [];
    try {
        $res = $mysqli->query('SELECT ' . implode(', ', $select) . ' FROM personaggi ORDER BY id ASC');
        while ($res && ($row = $res->fetch_assoc())) {
            $out[(int)$row['id']] = gacha_character_normalize($row);
        }
        if ($res) {
            $res->free();
        }
    } catch (Throwable $e) {
        error_log('[gacha] personaggi: ' . $e->getMessage());
    }

    return $cache = $out;
}

function gacha_character_normalize(array $row): array
{
    $clean = static fn($v) => ($v === null || trim((string)$v) === '') ? null : trim((string)$v);
    $rarity = gacha_rarity_key((string)($row['rarità'] ?? $row['rarita'] ?? 'comune'));

    return [
        'id' => (int)$row['id'],
        'nome' => (string)($row['nome'] ?? ''),
        'rarita' => $rarity ?: 'comune',
        'rarita_valida' => $rarity !== '',
        'categoria' => $clean($row['categoria'] ?? null),
        'img_url' => $clean($row['img_url'] ?? null),
        'audio_url' => $clean($row['audio_url'] ?? null),
        'video_url' => $clean($row['video_url'] ?? null),
        'descrizione' => (string)($row['descrizione'] ?? ''),
        'descrizione_en' => (string)($row['descrizione_en'] ?? ''),
        'caratteristiche' => (string)($row['caratteristiche'] ?? ''),
        'caratteristiche_en' => (string)($row['caratteristiche_en'] ?? ''),
        'in_pool_standard' => (int)($row['in_pool_standard'] ?? 1) === 1,
        'limitato' => gacha_is_limited($row),
        'catalogo' => gacha_catalog_mode($row),
        'aggiunto_il' => $row['aggiunto_il'] ?? null,
        'ruolo' => $row['ruolo'] ?? null,
    ];
}

/**
 * Il personaggio nella forma che le API di pull hanno sempre restituito
 * (`rarità` con l'accento, nomi file nudi): gacha.js e il bot la leggono.
 */
function gacha_character_public(array $c): array
{
    return [
        'id' => (int)$c['id'],
        'nome' => $c['nome'],
        'rarità' => $c['rarita'],
        'img_url' => $c['img_url'],
        'audio_url' => $c['audio_url'],
        'video_url' => $c['video_url'],
        'descrizione' => $c['descrizione'],
        'caratteristiche' => $c['caratteristiche'],
    ];
}

/* ── Categorie ────────────────────────────────────────────────────────── */

function gacha_categories(mysqli $mysqli, bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    if (!gacha_schema($mysqli)['categorie']) {
        // Senza tabella le categorie sono solo i nomi usati dai personaggi.
        $names = [];
        foreach (gacha_characters($mysqli) as $c) {
            if ($c['categoria'] !== null) {
                $names[mb_strtolower($c['categoria'], 'UTF-8')] = $c['categoria'];
            }
        }
        $out = [];
        $i = 0;
        foreach ($names as $name) {
            $out[] = [
                'id' => null, 'nome' => $name, 'nome_en' => null, 'slug' => gacha_slugify($name),
                'colore' => null, 'icona' => null, 'premio_godos' => 0, 'premio_badge_id' => null, 'ordine' => $i++,
            ];
        }
        return $cache = $out;
    }

    $out = [];
    try {
        $res = $mysqli->query('SELECT * FROM personaggi_categorie ORDER BY ordine ASC, nome ASC');
        while ($res && ($row = $res->fetch_assoc())) {
            $out[] = [
                'id' => (int)$row['id'],
                'nome' => (string)$row['nome'],
                'nome_en' => $row['nome_en'] ?? null,
                'slug' => (string)$row['slug'],
                'colore' => $row['colore'] ?? null,
                'icona' => $row['icona'] ?? null,
                'premio_godos' => (int)($row['premio_godos'] ?? 0),
                'premio_badge_id' => isset($row['premio_badge_id']) ? (int)$row['premio_badge_id'] ?: null : null,
                'ordine' => (int)($row['ordine'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        error_log('[gacha] categorie: ' . $e->getMessage());
    }

    return $cache = $out;
}

function gacha_slugify(string $value): string
{
    if (function_exists('iconv')) {
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    }
    $value = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value));
    return trim($value, '-') ?: 'categoria';
}

/* ── Banner ───────────────────────────────────────────────────────────── */

/** Righe grezze dei banner, di qualsiasi stato. */
function gacha_banner_rows(mysqli $mysqli): array
{
    $schema = gacha_schema($mysqli);
    $table = $schema['banner_table'];
    if ($table === null) {
        return [];
    }

    $rows = [];
    try {
        $res = $mysqli->query('SELECT * FROM `' . $table . '` ORDER BY id ASC');
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[(int)$row['id']] = $row;
        }
    } catch (Throwable $e) {
        error_log('[gacha] banner: ' . $e->getMessage());
    }

    return $rows;
}

/** Personaggi collegati ai banner (featured, pool, esclusi), per banner. */
function gacha_banner_links(mysqli $mysqli): array
{
    if (!gacha_schema($mysqli)['banner_personaggi']) {
        return [];
    }

    $out = [];
    try {
        $res = $mysqli->query('SELECT banner_id, personaggio_id, ruolo, peso, ordine FROM gacha_banner_personaggi ORDER BY banner_id ASC, ordine ASC, personaggio_id ASC');
        while ($res && ($row = $res->fetch_assoc())) {
            $out[(int)$row['banner_id']][] = [
                'id' => (int)$row['personaggio_id'],
                'ruolo' => (string)$row['ruolo'],
                'peso' => max(0.0, (float)$row['peso']),
                'ordine' => (int)$row['ordine'],
            ];
        }
    } catch (Throwable $e) {
        error_log('[gacha] banner_personaggi: ' . $e->getMessage());
    }

    return $out;
}

/** Pesi e quote per rarita' dei banner che li personalizzano. */
function gacha_banner_rarity_rows(mysqli $mysqli): array
{
    if (!gacha_schema($mysqli)['banner_rarita']) {
        return [];
    }

    $out = [];
    try {
        $res = $mysqli->query('SELECT banner_id, rarita, peso, quota_featured FROM gacha_banner_rarita');
        while ($res && ($row = $res->fetch_assoc())) {
            $key = gacha_rarity_key($row['rarita']);
            if ($key === '') {
                continue;
            }
            $out[(int)$row['banner_id']][$key] = [
                'peso' => $row['peso'] === null ? null : max(0.0, (float)$row['peso']),
                'quota' => $row['quota_featured'] === null ? null : max(0, min(100, (int)$row['quota_featured'])),
            ];
        }
    } catch (Throwable $e) {
        error_log('[gacha] banner_rarita: ' . $e->getMessage());
    }

    return $out;
}

/** Testi del banner standard quando non ha (ancora) una riga sua. */
function gacha_standard_banner_defaults(): array
{
    return [
        'id' => null,
        'key' => 'standard',
        'slug' => 'standard',
        'tipo' => 'standard',
        'nome' => 'Banner Standard',
        'nome_en' => 'Standard Banner',
        'descrizione' => 'Il banner classico di Cripsum™ dove puoi trovare tutti i personaggi originali delle vecchie lootbox',
        'descrizione_en' => 'The classic Cripsum™ banner where you can find all the original characters from the old lootboxes',
        'costo' => 0,
        'pool_modo' => 'standard',
        'pool_categoria' => null,
        'pity_gruppo' => 'standard',
        'pity_soft' => null,
        'pity_hard' => null,
        'pity_soglia' => null,
        'quota_featured' => null,
        'garanzia_multi' => true,
        'limite_pull_utente' => null,
        'limite_pull_giorno' => null,
        'pull_gratis_giorno' => 0,
        'solo_premium' => false,
        'anteprima' => false,
        'destino_max' => 0,
        'sfondo' => 'banner_standard_bg.jpg',
        'thumb' => null,
        'arte' => 'cassa.png',
        'colore' => null,
        'ordine' => -100,
        'attivo' => true,
        'data_inizio' => null,
        'data_fine' => null,
        'featured' => [],
        'pool' => [],
        'esclusi' => [],
        'rarita' => [],
        'rateup_id' => null,
        'avvisi_inviati' => true,
    ];
}

function gacha_banner_normalize(array $row, array $links, array $rarityRows): array
{
    $isStandard = (string)($row['tipo'] ?? '') === 'standard';
    $base = $isStandard ? gacha_standard_banner_defaults() : [];
    $int = static fn($v) => ($v === null || $v === '') ? null : (int)$v;
    $text = static fn($v) => ($v === null || trim((string)$v) === '') ? null : trim((string)$v);

    $featured = [];
    $pool = [];
    $esclusi = [];
    foreach ($links as $link) {
        if ($link['ruolo'] === 'featured') {
            $featured[] = ['id' => $link['id'], 'peso' => $link['peso'] > 0 ? $link['peso'] : 1.0];
        } elseif ($link['ruolo'] === 'pool') {
            $pool[] = ['id' => $link['id'], 'peso' => $link['peso'] > 0 ? $link['peso'] : 1.0];
        } else {
            $esclusi[] = $link['id'];
        }
    }

    // Vecchio schema, o banner salvato senza righe di collegamento: il
    // rate-up e' la colonna storica.
    $rateup = $int($row['id_personaggio_rateup'] ?? null);
    if (!$links && $rateup) {
        $featured[] = ['id' => $rateup, 'peso' => 1.0];
    }

    $group = $text($row['pity_gruppo'] ?? null) ?? ($isStandard ? 'standard' : 'evento');

    return [
        'id' => (int)$row['id'],
        'key' => $isStandard ? 'standard' : (string)(int)$row['id'],
        'slug' => (string)($row['slug'] ?? ''),
        'tipo' => in_array($row['tipo'] ?? '', ['standard', 'evento', 'selezione', 'principiante'], true) ? $row['tipo'] : 'evento',
        'nome' => (string)($row['nome'] ?? ($base['nome'] ?? 'Banner')),
        'nome_en' => $text($row['nome_en'] ?? null) ?? ($base['nome_en'] ?? null),
        'descrizione' => $text($row['descrizione'] ?? null) ?? ($base['descrizione'] ?? null),
        'descrizione_en' => $text($row['descrizione_en'] ?? null) ?? ($base['descrizione_en'] ?? null),
        'costo' => max(0, (int)($row['costo_punti'] ?? 100)),
        'pool_modo' => in_array($row['pool_modo'] ?? '', ['standard', 'lista', 'categoria'], true) ? $row['pool_modo'] : 'standard',
        'pool_categoria' => $text($row['pool_categoria'] ?? null),
        'pity_gruppo' => $group,
        'pity_soft' => $int($row['pity_soft'] ?? null),
        'pity_hard' => $int($row['pity_hard'] ?? null),
        'pity_soglia' => gacha_rarity_key($row['pity_soglia'] ?? null) ?: null,
        'quota_featured' => $int($row['quota_featured'] ?? null),
        'garanzia_multi' => array_key_exists('garanzia_multi', $row) ? (int)$row['garanzia_multi'] === 1 : true,
        'limite_pull_utente' => ($v = $int($row['limite_pull_utente'] ?? null)) && $v > 0 ? $v : null,
        'limite_pull_giorno' => ($v = $int($row['limite_pull_giorno'] ?? null)) && $v > 0 ? $v : null,
        'pull_gratis_giorno' => max(0, (int)($row['pull_gratis_giorno'] ?? 0)),
        'solo_premium' => (int)($row['solo_premium'] ?? 0) === 1,
        'anteprima' => (int)($row['anteprima'] ?? 0) === 1,
        'destino_max' => array_key_exists('destino_max', $row) ? max(0, (int)$row['destino_max']) : 0,
        'sfondo' => $text($row['banner_img_url'] ?? null) ?? ($base['sfondo'] ?? null),
        'thumb' => $text($row['thumb_url'] ?? null),
        'arte' => $text($row['arte_url'] ?? null) ?? ($base['arte'] ?? null),
        'colore' => $text($row['colore'] ?? null),
        'ordine' => (int)($row['ordine'] ?? ($base['ordine'] ?? 0)),
        'attivo' => (int)($row['attivo'] ?? 1) === 1,
        'data_inizio' => $text($row['data_inizio'] ?? null),
        'data_fine' => $text($row['data_fine'] ?? null),
        'featured' => $featured,
        'pool' => $pool,
        'esclusi' => $esclusi,
        'rarita' => $rarityRows,
        'rateup_id' => $featured[0]['id'] ?? $rateup,
        'avvisi_inviati' => (int)($row['avvisi_inviati'] ?? 1) === 1,
    ];
}

/**
 * Tutti i banner normalizzati, per chiave ('standard' o l'id). Lo standard
 * c'e' sempre: se non ha una riga (schema vecchio) si usano i suoi valori
 * di sempre.
 */
function gacha_banners(mysqli $mysqli, bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $rows = gacha_banner_rows($mysqli);
    $links = gacha_banner_links($mysqli);
    $rarity = gacha_banner_rarity_rows($mysqli);

    $out = [];
    foreach ($rows as $id => $row) {
        $banner = gacha_banner_normalize($row, $links[$id] ?? [], $rarity[$id] ?? []);
        if ($banner['key'] === 'standard' && isset($out['standard'])) {
            // Un solo standard: gli altri valgono come banner normali.
            $banner['key'] = (string)$id;
            $banner['tipo'] = 'selezione';
        }
        $out[$banner['key']] = $banner;
    }

    if (!isset($out['standard'])) {
        $out = ['standard' => gacha_standard_banner_defaults()] + $out;
    }

    uasort($out, static function (array $a, array $b): int {
        return [$a['ordine'], $a['id'] ?? 0] <=> [$b['ordine'], $b['id'] ?? 0];
    });

    return $cache = $out;
}

function gacha_banner_get(mysqli $mysqli, $key): ?array
{
    $key = trim((string)$key);
    if ($key === '') {
        return null;
    }
    $banners = gacha_banners($mysqli);
    if (isset($banners[$key])) {
        return $banners[$key];
    }
    // L'id numerico dello standard porta allo standard.
    foreach ($banners as $banner) {
        if ($banner['id'] !== null && (string)$banner['id'] === $key) {
            return $banner;
        }
    }
    return null;
}

function gacha_ts(?string $datetime): ?int
{
    if ($datetime === null || $datetime === '') {
        return null;
    }
    $ts = strtotime($datetime);
    return $ts === false ? null : $ts;
}

/**
 * attivo | prossimamente (annunciato, non ancora iniziato) | programmato
 * (futuro, nascosto) | scaduto | bozza (spento).
 */
function gacha_banner_status(array $banner, ?int $now = null): string
{
    $now ??= time();
    if (!$banner['attivo']) {
        return 'bozza';
    }
    $start = gacha_ts($banner['data_inizio']);
    $end = gacha_ts($banner['data_fine']);
    if ($end !== null && $end < $now) {
        return 'scaduto';
    }
    if ($start !== null && $start > $now) {
        return $banner['anteprima'] ? 'prossimamente' : 'programmato';
    }
    return 'attivo';
}

/* ── Pool ─────────────────────────────────────────────────────────────── */

/**
 * I personaggi che un banner puo' dare, divisi per rarita'.
 *
 * Ogni voce ha il peso del personaggio dentro la sua rarita' e se e' un
 * featured (rate-up). Il pool dipende dalla modalita':
 *  - standard: i personaggi del pool standard, piu' i featured;
 *  - lista: solo quelli collegati al banner;
 *  - categoria: tutta la categoria (i personaggi aggiunti dopo entrano da soli).
 * Gli esclusi non escono mai.
 */
function gacha_banner_pool(mysqli $mysqli, array $banner): array
{
    $chars = gacha_characters($mysqli);
    $weights = [];
    $featured = [];

    foreach ($banner['pool'] as $entry) {
        $weights[$entry['id']] = $entry['peso'];
    }
    foreach ($banner['featured'] as $entry) {
        $weights[$entry['id']] = $entry['peso'];
        $featured[$entry['id']] = true;
    }
    $excluded = array_flip($banner['esclusi']);

    $ids = [];
    switch ($banner['pool_modo']) {
        case 'lista':
            $ids = array_keys($weights);
            break;
        case 'categoria':
            $wanted = mb_strtolower((string)$banner['pool_categoria'], 'UTF-8');
            foreach ($chars as $id => $c) {
                if ($wanted !== '' && $c['categoria'] !== null && mb_strtolower($c['categoria'], 'UTF-8') === $wanted) {
                    $ids[] = $id;
                }
            }
            $ids = array_merge($ids, array_keys($weights));
            break;
        default:
            foreach ($chars as $id => $c) {
                if ($c['in_pool_standard']) {
                    $ids[] = $id;
                }
            }
            $ids = array_merge($ids, array_keys($featured));
    }

    $byRarity = array_fill_keys(gacha_rarity_keys(), []);
    $seen = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if (isset($seen[$id]) || isset($excluded[$id]) || !isset($chars[$id])) {
            continue;
        }
        $seen[$id] = true;
        $c = $chars[$id];
        if (!$c['rarita_valida']) {
            continue;
        }
        $byRarity[$c['rarita']][] = [
            'id' => $id,
            'peso' => $weights[$id] ?? 1.0,
            'featured' => isset($featured[$id]),
        ];
    }

    return $byRarity;
}

function gacha_pool_size(array $pool): int
{
    $n = 0;
    foreach ($pool as $entries) {
        $n += count($entries);
    }
    return $n;
}

/** Il profilo di pity di un banner (gruppo storico o dedicato). */
function gacha_banner_pity_profile(array $banner): array
{
    return gacha_pity_profile($banner['pity_gruppo'], $banner['pity_soft'], $banner['pity_hard'], $banner['pity_soglia']);
}

/** La fascia su cui lavorano 50/50 e garantito: quella della soglia del pity. */
function gacha_banner_pity_tier(array $banner): int
{
    return gacha_rarity_tier(gacha_banner_pity_profile($banner)['soglia']);
}

/**
 * Probabilita' (0-100) che, uscita una rarita', arrivi un featured.
 * Una riga per rarita' vince; poi la quota del banner per la fascia del
 * pity; poi il 50% di sempre.
 */
function gacha_banner_featured_quota(array $banner, string $rarity): int
{
    if (isset($banner['rarita'][$rarity]['quota']) && $banner['rarita'][$rarity]['quota'] !== null) {
        return (int)$banner['rarita'][$rarity]['quota'];
    }
    if ($banner['quota_featured'] !== null && gacha_rarity_tier($rarity) === gacha_banner_pity_tier($banner)) {
        return max(0, min(100, (int)$banner['quota_featured']));
    }
    return GACHA_DEFAULT_FEATURED_QUOTA;
}

/**
 * Pesi base delle rarita' per un banner: quelli personalizzati, altrimenti i
 * centrali. Le rarita' senza personaggi nel pool valgono zero, cosi' un banner
 * esclusivo non "ricade" su rarita' che non ha.
 */
function gacha_banner_weights(array $banner, array $pool): array
{
    $weights = [];
    foreach (gacha_base_weights() as $key => $weight) {
        $custom = $banner['rarita'][$key]['peso'] ?? null;
        $weights[$key] = empty($pool[$key]) ? 0.0 : (float)($custom ?? $weight);
    }
    return $weights;
}

/**
 * Personaggi featured nella stessa fascia di una rarita'. Sono quelli che
 * il 50/50 puo' dare quando esce quella rarita' (per la fascia top: un
 * segreto o un theone fanno scattare il rate-up del banner evento).
 */
function gacha_pool_featured_for(array $pool, string $rarity): array
{
    $tier = gacha_rarity_tier($rarity);
    $out = [];
    foreach (gacha_tier_rarities($tier) as $key) {
        foreach ($pool[$key] ?? [] as $entry) {
            if ($entry['featured']) {
                $out[] = $entry;
            }
        }
    }
    return $out;
}
