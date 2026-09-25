<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/gacha/public.php';

/**
 * Banner del gacha dal pannello.
 *
 *   GET  ?action=list                  tutti i banner, con stato e statistiche
 *   GET  ?action=get&id=6              un banner con pool e pesi
 *   GET  ?action=characters            elenco personaggi per l'editor del pool
 *   POST {action: preview, ...banner}  probabilita' calcolate dal motore, senza salvare
 *   POST {action: save, ...banner}     crea o modifica
 *   POST {action: toggle, id, attivo}
 *   POST {action: delete, id}          (lo standard non si cancella)
 *   POST {action: reorder, order: []}
 *
 * Le probabilita' dell'anteprima vengono dalle stesse funzioni che usa la
 * pull (gacha_banner_rates): quello che si vede e' quello che succede.
 */

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];
$schema = gacha_schema($mysqli);

if (!$schema['v2']) {
    admin_ok([
        'ready' => false,
        'message' => 'Applica la migration migrations/2026_09_25_gacha_banner.sql per gestire i banner da qui. Fino ad allora i banner restano quelli di banner_eventi e funzionano come prima.',
    ]);
}

/* ── Lettura e validazione ────────────────────────────────────────────── */

function gb_admin_datetime(array $input, string $key, string $label): ?string
{
    $raw = trim((string)($input[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    $time = strtotime(str_replace('T', ' ', $raw));
    if ($time === false) {
        admin_fail($label . ': data non valida.');
    }
    return date('Y-m-d H:i:s', $time);
}

function gb_admin_image(array $input, string $key, string $label): ?string
{
    $value = trim((string)($input[$key] ?? ''));
    if ($value === '') {
        return null;
    }
    if (mb_strlen($value) > 255) {
        admin_fail($label . ': percorso troppo lungo.');
    }
    if (preg_match('~^https?://~i', $value)) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            admin_fail($label . ': indirizzo non valido.');
        }
        return $value;
    }
    if (str_contains($value, '..') || str_contains($value, "\0") || str_starts_with($value, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $value)) {
        admin_fail($label . ': usa un nome file di /img, un percorso del sito o un indirizzo https.');
    }
    // I banner salvano il percorso dentro img/ (come "cpk.gif"): le pagine ci
    // mettono davanti /img/. Un /img/... incollato si riporta a quella forma.
    if (str_starts_with($value, '/img/')) {
        $value = substr($value, 5);
    }
    if (!preg_match('~^/?[a-zA-Z0-9_\-./ ()%]+$~', $value)) {
        admin_fail($label . ': il percorso contiene caratteri non validi.');
    }
    return $value;
}

function gb_admin_int(array $input, string $key, string $label, int $min, int $max, bool $nullable = true): ?int
{
    $raw = $input[$key] ?? null;
    if ($raw === null || $raw === '') {
        if ($nullable) {
            return null;
        }
        admin_fail($label . ': campo obbligatorio.');
    }
    if (!is_numeric($raw) || (int)$raw != $raw) {
        admin_fail($label . ': serve un numero intero.');
    }
    $v = (int)$raw;
    if ($v < $min || $v > $max) {
        admin_fail("$label: deve stare tra $min e $max.");
    }
    return $v;
}

/**
 * Legge il banner dal form e lo porta nella forma normalizzata del motore,
 * piu' i campi da scrivere nel database. Non salva nulla.
 */
function gb_admin_read(mysqli $mysqli, array $input, ?array $existing): array
{
    $isStandard = $existing && ($existing['tipo'] ?? '') === 'standard';

    $nome = trim((string)($input['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 120) {
        admin_fail('Nome: obbligatorio, al massimo 120 caratteri.');
    }
    $nomeEn = trim((string)($input['nome_en'] ?? '')) ?: null;
    if ($nomeEn !== null && mb_strlen($nomeEn) > 120) {
        admin_fail('Nome (EN): al massimo 120 caratteri.');
    }
    $desc = trim((string)($input['descrizione'] ?? '')) ?: null;
    $descEn = trim((string)($input['descrizione_en'] ?? '')) ?: null;
    foreach ([$desc, $descEn] as $text) {
        if ($text !== null && mb_strlen($text) > 2000) {
            admin_fail('Descrizione: al massimo 2000 caratteri.');
        }
    }

    $slug = $isStandard ? 'standard' : admin_shop_slugify(trim((string)($input['slug'] ?? '')) ?: $nome);
    if (strlen($slug) < 2) {
        admin_fail('Slug: servono almeno 2 lettere o numeri.');
    }
    $slug = substr($slug, 0, 80);

    $tipo = $isStandard ? 'standard' : admin_shop_enum($input, 'tipo', 'Tipo', ['evento', 'selezione', 'principiante'], 'evento');

    $poolModo = $isStandard ? 'standard' : admin_shop_enum($input, 'pool_modo', 'Pool', ['standard', 'lista', 'categoria'], 'standard');
    $poolCategoria = null;
    if ($poolModo === 'categoria') {
        $poolCategoria = trim((string)($input['pool_categoria'] ?? ''));
        $known = array_map(static fn($c) => mb_strtolower($c['nome'], 'UTF-8'), gacha_categories($mysqli));
        if ($poolCategoria === '' || !in_array(mb_strtolower($poolCategoria, 'UTF-8'), $known, true)) {
            admin_fail('Pool per categoria: scegli una categoria esistente.');
        }
    }

    // Gruppo di pity: lo standard ha il suo; gli altri condividono il pity
    // evento (come i banner di sempre) oppure ne hanno uno tutto loro.
    $pityMode = $isStandard ? 'standard' : admin_shop_enum($input, 'pity_modo', 'Pity', ['evento', 'dedicato'], 'evento');
    $pitySoft = $pityHard = null;
    $pitySoglia = null;
    if ($pityMode === 'dedicato') {
        $pityHard = gb_admin_int($input, 'pity_hard', 'Hard pity', 1, 1000, false);
        $pitySoft = gb_admin_int($input, 'pity_soft', 'Soft pity', 1, $pityHard, false);
        $pitySoglia = gacha_rarity_key($input['pity_soglia'] ?? '') ?: admin_fail('Rarità garantita dal pity non valida.');
    }

    $quota = gb_admin_int($input, 'quota_featured', 'Quota del rate-up', 0, 100);

    $dataInizio = gb_admin_datetime($input, 'data_inizio', 'Inizio');
    $dataFine = gb_admin_datetime($input, 'data_fine', 'Fine');
    if ($dataInizio && $dataFine && strtotime($dataFine) <= strtotime($dataInizio)) {
        admin_fail('La fine deve venire dopo l\'inizio.');
    }

    $chars = gacha_characters($mysqli);
    $links = [];
    $seen = [];
    foreach ((array)($input['personaggi'] ?? []) as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int)($row['id'] ?? 0);
        if (!isset($chars[$id]) || isset($seen[$id])) {
            continue;
        }
        $ruolo = in_array($row['ruolo'] ?? '', ['featured', 'pool', 'escluso'], true) ? $row['ruolo'] : 'pool';
        $peso = round((float)($row['peso'] ?? 1), 2);
        if ($peso <= 0 || $peso > 100) {
            admin_fail('Peso di ' . $chars[$id]['nome'] . ': deve stare tra 0,01 e 100.');
        }
        $seen[$id] = true;
        $links[] = ['id' => $id, 'ruolo' => $ruolo, 'peso' => $peso, 'ordine' => (int)$i];
    }
    if (count($links) > 400) {
        admin_fail('Troppi personaggi collegati al banner.');
    }
    if ($isStandard) {
        // Lo standard pesca dal pool standard: niente rate-up.
        $links = array_values(array_filter($links, static fn($l) => $l['ruolo'] !== 'featured'));
    }

    $rarity = [];
    foreach ((array)($input['rarita'] ?? []) as $key => $row) {
        $key = gacha_rarity_key((string)$key);
        if ($key === '' || !is_array($row)) {
            continue;
        }
        $peso = ($row['peso'] ?? '') === '' || $row['peso'] === null ? null : (float)$row['peso'];
        $q = ($row['quota'] ?? '') === '' || $row['quota'] === null ? null : (int)$row['quota'];
        if ($peso !== null && ($peso < 0 || $peso > 1000)) {
            admin_fail('Peso della rarità ' . $key . ': tra 0 e 1000.');
        }
        if ($q !== null && ($q < 0 || $q > 100)) {
            admin_fail('Quota rate-up della rarità ' . $key . ': tra 0 e 100.');
        }
        if ($peso !== null || $q !== null) {
            $rarity[$key] = ['peso' => $peso, 'quota' => $q];
        }
    }

    $featured = array_values(array_filter($links, static fn($l) => $l['ruolo'] === 'featured'));

    $db = [
        'slug' => $slug,
        'tipo' => $tipo,
        'nome' => $nome,
        'nome_en' => $nomeEn,
        'descrizione' => $desc,
        'descrizione_en' => $descEn,
        'id_personaggio_rateup' => $featured[0]['id'] ?? null,
        'banner_img_url' => gb_admin_image($input, 'banner_img_url', 'Sfondo'),
        'thumb_url' => gb_admin_image($input, 'thumb_url', 'Miniatura'),
        'arte_url' => gb_admin_image($input, 'arte_url', 'Arte'),
        'colore' => preg_match('/^#[0-9a-f]{6}$/i', (string)($input['colore'] ?? '')) ? strtolower($input['colore']) : null,
        'costo_punti' => gb_admin_int($input, 'costo_punti', 'Costo', 0, 1000000, false),
        'attivo' => admin_shop_bool($input, 'attivo'),
        'data_inizio' => $isStandard ? null : $dataInizio,
        'data_fine' => $isStandard ? null : $dataFine,
        'pool_modo' => $poolModo,
        'pool_categoria' => $poolCategoria,
        'pity_gruppo' => $pityMode === 'dedicato' ? ($existing && str_starts_with((string)$existing['pity_gruppo'], 'banner-') ? $existing['pity_gruppo'] : 'dedicato') : $pityMode,
        'pity_soft' => $pitySoft,
        'pity_hard' => $pityHard,
        'pity_soglia' => $pitySoglia,
        'quota_featured' => $quota,
        'garanzia_multi' => admin_shop_bool($input, 'garanzia_multi'),
        'limite_pull_utente' => gb_admin_int($input, 'limite_pull_utente', 'Limite pull per utente', 1, 100000),
        'limite_pull_giorno' => gb_admin_int($input, 'limite_pull_giorno', 'Limite pull al giorno', 1, 100000),
        'pull_gratis_giorno' => gb_admin_int($input, 'pull_gratis_giorno', 'Pull gratis al giorno', 0, 10, false),
        'solo_premium' => admin_shop_bool($input, 'solo_premium'),
        'anteprima' => $isStandard ? 0 : admin_shop_bool($input, 'anteprima'),
        'destino_max' => gb_admin_int($input, 'destino_max', 'Destino', 0, 10, false),
        'ordine' => gb_admin_int($input, 'ordine', 'Ordine', -1000, 1000, false),
    ];

    // La forma normalizzata, per anteprima e controlli.
    $row = $db + ['id' => $existing['id'] ?? 0, 'avvisi_inviati' => 1];
    if ($row['pity_gruppo'] === 'dedicato') {
        $row['pity_gruppo'] = 'banner-anteprima';
    }
    $normalized = gacha_banner_normalize($row, array_map(static fn($l) => $l, $links), $rarity);
    if ($isStandard) {
        $normalized['key'] = 'standard';
    }

    return ['db' => $db, 'links' => $links, 'rarita' => $rarity, 'banner' => $normalized];
}

/** Avvisi (non bloccanti) su un banner: cose che probabilmente non si volevano. */
function gb_admin_warnings(mysqli $mysqli, array $banner): array
{
    $warnings = [];
    $pool = gacha_banner_pool($mysqli, $banner);
    $size = gacha_pool_size($pool);
    $profile = gacha_banner_pity_profile($banner);

    if ($size === 0) {
        $warnings[] = 'Il pool è vuoto: il banner non può dare nessun personaggio.';
        return $warnings;
    }
    $missing = [];
    foreach (gacha_rarity_keys() as $key) {
        if (empty($pool[$key])) {
            $missing[] = gacha_rarity_label($key);
        }
    }
    if ($missing) {
        $warnings[] = 'Nel pool mancano: ' . implode(', ', $missing) . '. Le loro probabilità vengono ridistribuite sulle altre rarità.';
    }
    $above = false;
    foreach (gacha_rarity_keys() as $key) {
        if (!empty($pool[$key]) && gacha_rarity_rank($key) >= gacha_rarity_rank($profile['soglia'])) {
            $above = true;
        }
    }
    if (!$above) {
        $warnings[] = 'Nel pool non c\'è nessun ' . gacha_rarity_label($profile['soglia']) . ' o superiore: l\'hard pity non ha niente da garantire.';
    }
    if (!$banner['featured'] && $banner['tipo'] === 'evento') {
        $warnings[] = 'Banner evento senza rate-up: nessun 50/50, esce come uno standard a pagamento.';
    }
    $pityTier = gacha_rarity_tier($profile['soglia']);
    $chars = gacha_characters($mysqli);
    foreach ($banner['featured'] as $f) {
        $c = $chars[$f['id']] ?? null;
        if ($c && gacha_rarity_tier($c['rarita']) !== $pityTier) {
            $warnings[] = $c['nome'] . ' è ' . gacha_rarity_label($c['rarita']) . ': il suo rate-up vale quando esce la sua rarità, senza garantito.';
        }
    }
    if ($banner['costo'] === 0 && $banner['tipo'] !== 'standard' && !$banner['limite_pull_utente'] && !$banner['limite_pull_giorno']) {
        $warnings[] = 'Costo zero e nessun limite: si può pullare all\'infinito gratis.';
    }
    return $warnings;
}

function gb_admin_rates_public(mysqli $mysqli, array $banner): array
{
    $rates = gacha_banner_rates($mysqli, $banner);
    $chars = gacha_characters($mysqli);
    $featured = [];
    foreach ($banner['featured'] as $f) {
        if (isset($chars[$f['id']])) {
            $p = $rates['personaggi'][$f['id']] ?? 0;
            $hard = $rates['pity']['hard'];
            $featured[] = [
                'id' => $f['id'],
                'nome' => $chars[$f['id']]['nome'],
                'rarita' => $chars[$f['id']]['rarita'],
                'prob' => round($p, 5),
                // Probabilita' di averlo almeno una volta entro l'hard pity,
                // col solo tasso base (il pity la fa solo salire).
                'entro_hard' => round((1 - pow(1 - $p / 100, $hard + 1)) * 100, 2),
            ];
        }
    }
    $counts = [];
    foreach ($rates['pool'] as $key => $entries) {
        $counts[$key] = count($entries);
    }
    return [
        'rarita' => array_map(static fn($v) => round($v, 4), $rates['rarita']),
        'featured' => $featured,
        'pity' => $rates['pity'],
        'pool' => $counts,
        'pool_totale' => array_sum($counts),
        'avvisi' => gb_admin_warnings($mysqli, $banner),
    ];
}

function gb_admin_row(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM gacha_banner WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Banner non trovato.', 404);
    }
    return $row;
}

function gb_admin_banner_payload(mysqli $mysqli, array $row): array
{
    $links = [];
    foreach (admin_shop_rows($mysqli, 'SELECT personaggio_id AS id, ruolo, peso, ordine FROM gacha_banner_personaggi WHERE banner_id = ? ORDER BY ordine ASC, personaggio_id ASC', 'i', [(int)$row['id']]) as $l) {
        $links[] = ['id' => (int)$l['id'], 'ruolo' => $l['ruolo'], 'peso' => (float)$l['peso']];
    }
    $rarity = [];
    foreach (admin_shop_rows($mysqli, 'SELECT rarita, peso, quota_featured FROM gacha_banner_rarita WHERE banner_id = ?', 'i', [(int)$row['id']]) as $r) {
        $rarity[$r['rarita']] = ['peso' => $r['peso'] === null ? null : (float)$r['peso'], 'quota' => $r['quota_featured'] === null ? null : (int)$r['quota_featured']];
    }
    $row['personaggi'] = $links;
    $row['rarita'] = $rarity;
    $row['pity_modo'] = in_array($row['pity_gruppo'], ['standard', 'evento'], true) ? $row['pity_gruppo'] : 'dedicato';
    return $row;
}

try {
    if ($action === 'list') {
        $stats = [];
        foreach (admin_shop_rows(
            $mysqli,
            "SELECT banner_id, COUNT(*) AS pull, COUNT(DISTINCT utente_id) AS utenti,
                    COALESCE(SUM(esito_50_50 = 1), 0) AS vinti, COALESCE(SUM(esito_50_50 = 0), 0) AS persi,
                    COALESCE(SUM(featured = 1), 0) AS featured, COALESCE(SUM(costo), 0) AS spesa,
                    MAX(created_at) AS ultima
             FROM gacha_pull_history GROUP BY banner_id"
        ) as $row) {
            $stats[(string)$row['banner_id']] = $row;
        }

        $chars = gacha_characters($mysqli);
        $banners = [];
        foreach (gacha_banners($mysqli, true) as $b) {
            if ($b['id'] === null) {
                continue;
            }
            $banners[] = [
                'id' => $b['id'],
                'key' => $b['key'],
                'slug' => $b['slug'],
                'tipo' => $b['tipo'],
                'nome' => $b['nome'],
                'stato' => gacha_banner_status($b),
                'attivo' => $b['attivo'],
                'costo' => $b['costo'],
                'pool_modo' => $b['pool_modo'],
                'pity_gruppo' => $b['pity_gruppo'],
                'data_inizio' => $b['data_inizio'],
                'data_fine' => $b['data_fine'],
                'ordine' => $b['ordine'],
                'thumb' => gacha_media($b['thumb'] ?: ($b['sfondo'] ?: ($b['featured'] ? ($chars[$b['featured'][0]['id']]['img_url'] ?? null) : null))),
                'featured' => array_values(array_map(static fn($f) => ['id' => $f['id'], 'nome' => $chars[$f['id']]['nome'] ?? ('#' . $f['id'])], $b['featured'])),
                'stats' => $stats[$b['key']] ?? null,
            ];
        }

        admin_ok(['ready' => true, 'banners' => $banners]);
    }

    if ($action === 'get') {
        $row = gb_admin_row($mysqli, (int)($_GET['id'] ?? 0));
        admin_ok(['ready' => true, 'banner' => gb_admin_banner_payload($mysqli, $row)]);
    }

    if ($action === 'characters') {
        $out = [];
        foreach (gacha_characters($mysqli) as $c) {
            $out[] = [
                'id' => $c['id'],
                'nome' => $c['nome'],
                'rarita' => $c['rarita'],
                'categoria' => $c['categoria'],
                'img' => gacha_media($c['img_url']),
                'standard' => $c['in_pool_standard'],
                'limitato' => $c['limitato'],
            ];
        }
        $categories = array_map(static fn($c) => $c['nome'], gacha_categories($mysqli));
        $rarities = [];
        foreach (gacha_rarity_defs() as $key => $def) {
            $rarities[] = ['key' => $key, 'label' => $def['it'], 'colore' => $def['color'], 'peso' => $def['peso']];
        }
        admin_ok(['characters' => $out, 'categories' => $categories, 'rarities' => $rarities, 'profiles' => gacha_pity_profiles(), 'godos_per_shard' => gacha_godos_per_shard($mysqli)]);
    }

    admin_shop_require_post();
    $input = admin_input();

    if ($action === 'preview') {
        $id = (int)($input['id'] ?? 0);
        $existing = $id > 0 ? gb_admin_row($mysqli, $id) : null;
        $read = gb_admin_read($mysqli, $input, $existing);
        admin_ok(['preview' => gb_admin_rates_public($mysqli, $read['banner'])]);
    }

    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $existing = $id > 0 ? gb_admin_row($mysqli, $id) : null;
        $read = gb_admin_read($mysqli, $input, $existing);
        $db = $read['db'];

        $mysqli->begin_transaction();
        try {
            $cols = array_keys($db);
            $types = '';
            $params = [];
            foreach ($db as $key => $value) {
                $types .= is_int($value) ? 'i' : 's';
                $params[] = $value;
            }

            if ($existing) {
                $sets = implode(', ', array_map(static fn($c) => "`$c` = ?", $cols));
                admin_shop_exec($mysqli, "UPDATE gacha_banner SET $sets WHERE id = ?", $types . 'i', array_merge($params, [$id]), 'Non sono riuscito a salvare il banner.')->close();
            } else {
                $place = implode(', ', array_fill(0, count($cols), '?'));
                $stmt = admin_shop_exec($mysqli, 'INSERT INTO gacha_banner (`' . implode('`, `', $cols) . "`) VALUES ($place)", $types, $params, 'Non sono riuscito a creare il banner.');
                $id = (int)$mysqli->insert_id;
                $stmt->close();
            }

            // Un pity dedicato prende il nome dal banner: e' suo e basta.
            if ($db['pity_gruppo'] === 'dedicato') {
                $group = 'banner-' . $id;
                admin_shop_exec($mysqli, 'UPDATE gacha_banner SET pity_gruppo = ? WHERE id = ?', 'si', [$group, $id], 'Pity non salvato.')->close();
            }

            admin_shop_exec($mysqli, 'DELETE FROM gacha_banner_personaggi WHERE banner_id = ?', 'i', [$id], 'Pool non salvato.')->close();
            foreach ($read['links'] as $link) {
                admin_shop_exec(
                    $mysqli,
                    'INSERT INTO gacha_banner_personaggi (banner_id, personaggio_id, ruolo, peso, ordine) VALUES (?, ?, ?, ?, ?)',
                    'iisdi',
                    [$id, $link['id'], $link['ruolo'], $link['peso'], $link['ordine']],
                    'Pool non salvato.'
                )->close();
            }

            admin_shop_exec($mysqli, 'DELETE FROM gacha_banner_rarita WHERE banner_id = ?', 'i', [$id], 'Rarità non salvate.')->close();
            foreach ($read['rarita'] as $key => $r) {
                admin_shop_exec(
                    $mysqli,
                    'INSERT INTO gacha_banner_rarita (banner_id, rarita, peso, quota_featured) VALUES (?, ?, ?, ?)',
                    'isdi',
                    [$id, $key, $r['peso'], $r['quota']],
                    'Rarità non salvate.'
                )->close();
            }

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        if ($existing) {
            admin_media_cleanup($mysqli, [$existing['banner_img_url'] ?? null, $existing['thumb_url'] ?? null, $existing['arte_url'] ?? null], $adminId);
        }
        admin_log($mysqli, $adminId, $existing ? 'update_gacha_banner' : 'create_gacha_banner', null, ['banner_id' => $id, 'nome' => $db['nome']]);

        // Se il banner e' gia' partito, chi lo ha in wishlist riceve l'avviso.
        gacha_banners($mysqli, true);
        gacha_wishlist_dispatch($mysqli);

        admin_ok(['message' => 'Banner salvato.', 'id' => $id, 'avvisi' => gb_admin_warnings($mysqli, gacha_banner_get($mysqli, (string)$id) ?? $read['banner'])]);
    }

    if ($action === 'toggle') {
        $row = gb_admin_row($mysqli, (int)($input['id'] ?? 0));
        $on = admin_shop_bool($input, 'attivo');
        admin_shop_exec($mysqli, 'UPDATE gacha_banner SET attivo = ? WHERE id = ?', 'ii', [$on, (int)$row['id']], 'Stato non salvato.')->close();
        admin_log($mysqli, $adminId, 'toggle_gacha_banner', null, ['banner_id' => (int)$row['id'], 'attivo' => $on]);
        gacha_banners($mysqli, true);
        gacha_wishlist_dispatch($mysqli);
        admin_ok(['message' => $on ? 'Banner acceso.' : 'Banner spento.']);
    }

    if ($action === 'delete') {
        $row = gb_admin_row($mysqli, (int)($input['id'] ?? 0));
        if ($row['tipo'] === 'standard') {
            admin_fail('Il banner standard non si può eliminare: al massimo si spegne.');
        }
        admin_shop_exec($mysqli, 'DELETE FROM gacha_banner WHERE id = ?', 'i', [(int)$row['id']], 'Banner non eliminato.')->close();
        admin_media_cleanup($mysqli, [$row['banner_img_url'] ?? null, $row['thumb_url'] ?? null, $row['arte_url'] ?? null], $adminId);
        admin_log($mysqli, $adminId, 'delete_gacha_banner', null, ['banner_id' => (int)$row['id'], 'nome' => $row['nome']]);
        admin_ok(['message' => 'Banner eliminato. Lo storico delle pull resta.']);
    }

    if ($action === 'reorder') {
        admin_shop_reorder($mysqli, 'gacha_banner', 'ordine', admin_shop_ids($input));
        admin_ok(['message' => 'Ordine salvato.']);
    }

    admin_fail('Azione non valida.');
} catch (Throwable $e) {
    error_log('[admin gacha_banners] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    admin_fail('Errore nel salvataggio del banner.', 500);
}
