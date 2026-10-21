<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/gacha/config.php';
require_once __DIR__ . '/../../includes/gacha/upgrades.php';

/**
 * Personaggi dal pannello (sezione Personaggi).
 *
 * GET  ?action=list     tutti i personaggi, con quanti utenti li hanno, i
 *                       banner in cui compaiono e il tipo di kit nei duelli
 * GET  ?action=kit      il kit da duello coi valori del form (id, nome,
 *                       rarita, ruolo, limitato): statistiche a Lv. 1 e MAX,
 *                       abilita' e copie per potenziarlo
 * GET  ?action=options  id, nome e rarita' di tutti (premi del Centro messaggi)
 * POST action = save | set_flag | delete
 *
 * Le abilita' dei duelli non si scrivono qui: i kit si scrivono in Duelli ›
 * Kit personaggi (o stanno nel codice, includes/duel/), gli altri nascono da
 * ruolo e rarita'. Qui si vedono come le calcola il gioco.
 *
 * I file si salvano relativi alla loro cartella (personaggi/x.jpg, x.mp3,
 * x.mp4): lootbox e animazione ci mettono davanti /img/, /audio/ e /vid/
 * senza controllare.
 */

const ADMIN_CHARACTER_ROLES = ['Tank', 'Bruiser', 'DPS', 'Burst DPS', 'Sub DPS', 'Support', 'Healer', 'Controller', 'Debuffer', 'Buffer'];
const ADMIN_CHARACTER_CATALOG = ['visibile', 'segreto', 'nascosto'];

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!admin_table_exists($mysqli, 'personaggi')) {
    admin_fail('Tabella personaggi mancante.', 500);
}

$cols = admin_character_columns($mysqli);
if (!$cols['name']) {
    admin_fail('Campo nome personaggio mancante.', 500);
}
// «Kit unico» vale anche per i kit scritti nel pannello Duelli.
gd_use_duel_values($mysqli);

/** SELECT con gli alias fissi, qualunque sia il nome vero delle colonne. */
function ac_select(array $cols): string
{
    $aliases = [
        'nome' => 'name', 'descrizione' => 'description', 'descrizione_en' => 'description_en',
        'caratteristiche' => 'features', 'caratteristiche_en' => 'features_en', 'img_url' => 'image',
        'rarita' => 'rarity', 'audio_url' => 'audio', 'categoria' => 'category', 'video_url' => 'video_url',
        'in_pool_standard' => 'in_pool_standard', 'ruolo' => 'ruolo', 'catalogo' => 'catalogo', 'aggiunto_il' => 'aggiunto_il',
    ];
    $parts = ['p.id'];
    foreach ($aliases as $alias => $key) {
        $parts[] = !empty($cols[$key]) ? 'p.' . admin_qcol($cols[$key]) . ' AS ' . $alias : 'NULL AS ' . $alias;
    }
    // Prima della migration del gacha il limitato era la categoria "limited".
    $parts[] = $cols['limitato']
        ? 'p.' . admin_qcol($cols['limitato']) . ' AS limitato'
        : "(LOWER(COALESCE(" . ($cols['category'] ? 'p.' . admin_qcol($cols['category']) : "''") . ", '')) = 'limited') AS limitato";

    return implode(', ', $parts);
}

/** Il personaggio ha un kit suo nei Duelli 2.0 (scritto nel pannello o nel codice)? */
function ac_unique_kit(int $id): bool
{
    return $id > 0 && (isset(duel_overrides()['kits'][$id]) || isset(duel_unique_kits()[$id]));
}

function ac_row(array $row, array $owners, array $banners): array
{
    $id = (int)$row['id'];
    $rarity = gacha_rarity_key($row['rarita'] ?? '') ?: (string)($row['rarita'] ?? '');

    return [
        'id' => $id,
        'nome' => (string)$row['nome'],
        'rarita' => $rarity,
        'ruolo' => $row['ruolo'] !== null ? (string)$row['ruolo'] : null,
        'categoria' => (string)($row['categoria'] ?? ''),
        'limitato' => (int)$row['limitato'] === 1 ? 1 : 0,
        'catalogo' => $row['catalogo'] !== null ? (string)$row['catalogo'] : null,
        'in_pool_standard' => $row['in_pool_standard'] !== null ? (int)$row['in_pool_standard'] : null,
        'descrizione' => (string)($row['descrizione'] ?? ''),
        'descrizione_en' => (string)($row['descrizione_en'] ?? ''),
        'caratteristiche' => (string)($row['caratteristiche'] ?? ''),
        'caratteristiche_en' => (string)($row['caratteristiche_en'] ?? ''),
        'img_url' => (string)($row['img_url'] ?? ''),
        'audio_url' => (string)($row['audio_url'] ?? ''),
        'video_url' => (string)($row['video_url'] ?? ''),
        'aggiunto_il' => $row['aggiunto_il'],
        'image_url' => admin_asset_url($row['img_url'] ?? null),
        'utenti' => $owners[$id] ?? 0,
        'banner' => $banners[$id] ?? [],
        'kit' => ['unique' => ac_unique_kit($id), 'role' => null, 'ultimate' => true],
    ];
}

/** Utenti che hanno ogni personaggio (almeno una copia). */
function ac_owners(mysqli $mysqli): array
{
    if (!admin_table_exists($mysqli, 'utenti_personaggi')) {
        return [];
    }
    $qty = admin_inventory_quantity_column($mysqli);
    $where = $qty ? ' WHERE ' . admin_qcol($qty) . ' > 0' : '';
    $out = [];
    foreach (admin_shop_rows($mysqli, 'SELECT personaggio_id, COUNT(*) AS n FROM utenti_personaggi' . $where . ' GROUP BY personaggio_id') as $row) {
        $out[(int)$row['personaggio_id']] = (int)$row['n'];
    }
    return $out;
}

/** Banner in cui un personaggio e' in evidenza (lista o vecchio rate-up), con lo stato di oggi. */
function ac_banners(mysqli $mysqli): array
{
    if (!admin_table_exists($mysqli, 'gacha_banner')) {
        return [];
    }
    $links = admin_table_exists($mysqli, 'gacha_banner_personaggi')
        ? 'SELECT banner_id, personaggio_id FROM gacha_banner_personaggi UNION '
        : '';
    $sql = 'SELECT x.personaggio_id, b.id, b.nome, b.attivo, b.data_inizio, b.data_fine FROM ('
        . $links . 'SELECT id AS banner_id, id_personaggio_rateup AS personaggio_id FROM gacha_banner WHERE id_personaggio_rateup IS NOT NULL'
        . ') x JOIN gacha_banner b ON b.id = x.banner_id ORDER BY b.id DESC';

    $now = date('Y-m-d H:i:s');
    $out = [];
    foreach (admin_shop_rows($mysqli, $sql) as $row) {
        if ((int)$row['attivo'] !== 1) {
            $state = 'spento';
        } elseif ($row['data_fine'] && $row['data_fine'] < $now) {
            $state = 'finito';
        } elseif ($row['data_inizio'] && $row['data_inizio'] > $now) {
            $state = 'in arrivo';
        } else {
            $state = 'attivo';
        }
        $out[(int)$row['personaggio_id']][] = ['id' => (int)$row['id'], 'nome' => (string)$row['nome'], 'stato' => $state];
    }
    return $out;
}

function ac_find(mysqli $mysqli, array $cols, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT ' . ac_select($cols) . ' FROM personaggi p WHERE p.id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Personaggio non trovato: forse l\'ha eliminato qualcun altro.', 404);
    }
    return $row;
}

/**
 * Il kit da duello come lo costruisce il gioco (Duelli 2.0, coi kit e i
 * valori del pannello), a Lv. 1 e al MAX, piu' le copie per potenziarlo.
 */
function ac_kit(mysqli $mysqli, int $id, string $name, string $rarity, string $role, int $limited): array
{
    gd_use_duel_values($mysqli);
    $ch = ['nome' => $name, 'rarita' => $rarity, 'ruolo' => $role, 'limitato' => $limited, 'categoria' => ''];
    $max = duel_rules()['level_max'];
    $base = gd_stats_build($id, $ch, null, 1);
    $top = gd_stats_build($id, $ch, null, $max);
    $marker = gd_limited_marker($ch);

    $costs = [];
    for ($lvl = 1; $lvl < $max; $lvl++) {
        $costs[] = gd_get_upgrade_requirement($rarity, $lvl, $marker);
    }

    $pick = static fn(array $s): array => [
        'hp' => (int)$s['hp'], 'attack' => (int)$s['attack'], 'defense' => (int)$s['defense'], 'speed' => (int)$s['speed'],
        'crit_rate' => (int)$s['crit_rate'], 'crit_dmg' => (int)$s['crit_dmg'],
    ];

    return [
        'unique' => ac_unique_kit($id),
        'kit_role' => null,
        'role' => $role,
        'overrides' => false,
        'stats' => ['base' => $pick($base), 'max' => $pick($top)],
        'level_step' => (int)duel_rules()['level_step'],
        'passive' => ['name' => (string)$base['passive_name'], 'desc' => (string)$base['passive_desc']],
        'attack' => ['name' => (string)$base['attack_name'], 'desc' => (string)$base['attack_desc']],
        'special' => [
            'name' => (string)$base['special_name'],
            'desc' => (string)$base['special_desc'],
            'cost' => (int)$base['special_cost'],
            'cooldown' => (int)$base['special_cooldown'],
            'meta' => (string)$base['special_meta'],
        ],
        'ultimate' => ['name' => (string)$base['ultimate_name'], 'desc' => (string)$base['ultimate_desc']],
        'ultimate_audio' => $id > 0 && is_file(__DIR__ . '/../../audio/ultimates/' . $id . '.mp3'),
        'upgrade' => ['costs' => $costs, 'total' => array_sum($costs), 'limited' => $marker !== ''],
    ];
}

/**
 * Caratteristiche: una per riga del pannello, salvate separate da "; "
 * come le legge l'inventario.
 */
function ac_traits_join(string $raw): string
{
    $raw = str_replace(["\r\n", "\n"], ';', $raw);
    return implode('; ', array_filter(array_map('trim', explode(';', $raw)), static fn($v) => $v !== ''));
}

function ac_traits(array $input, string $key, string $label): string
{
    $value = ac_traits_join((string)($input[$key] ?? ''));
    if (mb_strlen($value, 'UTF-8') > 300) {
        admin_fail($label . ': massimo 300 caratteri in tutto (ora sono ' . mb_strlen($value, 'UTF-8') . ').');
    }
    return $value;
}

/** Un file del pannello: nome relativo alla sua cartella, senza /img/, /audio/ o /vid/ davanti. */
function ac_media(array $input, string $key, array $extensions, string $label, string $folder): ?string
{
    $value = trim((string)($input[$key] ?? ''));
    if ($value !== '' && !preg_match('~^https?://~i', $value)) {
        $value = preg_replace('~^/?' . preg_quote($folder, '~') . '/~', '', $value);
    }
    $value = admin_normalize_media_file($value, $extensions, $label);
    return $value !== '' ? $value : null;
}

try {
    if ($action === 'list') {
        $owners = ac_owners($mysqli);
        $banners = ac_banners($mysqli);
        $characters = [];
        foreach (admin_shop_rows($mysqli, 'SELECT ' . ac_select($cols) . ' FROM personaggi p ORDER BY p.id DESC') as $row) {
            $characters[] = ac_row($row, $owners, $banners);
        }

        $categories = admin_table_exists($mysqli, 'personaggi_categorie')
            ? admin_shop_rows($mysqli, 'SELECT id, nome, colore, icona FROM personaggi_categorie ORDER BY ordine ASC, nome ASC')
            : [];

        $rarities = [];
        foreach (gacha_rarity_defs() as $key => $def) {
            $rarities[] = ['key' => $key, 'label' => $def['it'], 'color' => $def['color']];
        }

        admin_ok([
            'characters' => $characters,
            'categories' => $categories,
            'rarities' => $rarities,
            'roles' => ADMIN_CHARACTER_ROLES,
            // Cosa c'e' nello schema: il pannello nasconde i campi che non ci sono.
            'schema' => [
                'limitato' => (bool)$cols['limitato'],
                'catalogo' => (bool)$cols['catalogo'],
                'pool' => (bool)$cols['in_pool_standard'],
                'ruolo' => (bool)$cols['ruolo'],
                'audio' => (bool)$cols['audio'],
                'video' => (bool)$cols['video_url'],
                'en' => (bool)$cols['description_en'],
            ],
        ]);
    }

    if ($action === 'kit') {
        $id = max(0, (int)($_GET['id'] ?? 0));
        $name = trim((string)($_GET['nome'] ?? '')) ?: 'Personaggio';
        $rarity = gacha_rarity_key((string)($_GET['rarita'] ?? '')) ?: 'comune';
        $role = in_array($_GET['ruolo'] ?? '', ADMIN_CHARACTER_ROLES, true) ? (string)$_GET['ruolo'] : 'DPS';
        $limited = ($_GET['limitato'] ?? '') === '1' ? 1 : 0;
        admin_ok(['kit' => ac_kit($mysqli, $id, $name, $rarity, $role, $limited)]);
    }

    if ($action === 'options') {
        $rows = admin_shop_rows($mysqli, 'SELECT p.id, p.' . admin_qcol($cols['name']) . ' AS nome'
            . ($cols['rarity'] ? ', p.' . admin_qcol($cols['rarity']) . ' AS rarita' : ", '' AS rarita")
            . ' FROM personaggi p ORDER BY nome ASC');
        admin_ok(['characters' => array_map(static fn($r) => ['id' => (int)$r['id'], 'nome' => $r['nome'], 'rarita' => gacha_rarity_key($r['rarita']) ?: $r['rarita']], $rows)]);
    }

    admin_shop_require_post();
    $input = admin_input();

    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $old = $id > 0 ? ac_find($mysqli, $cols, $id) : null;

        $name = admin_shop_text($input, 'nome', 'Nome', 80, true);
        $rarity = admin_character_rarity($input);
        $role = (string)($input['ruolo'] ?? '');
        if ($cols['ruolo'] && !in_array($role, ADMIN_CHARACTER_ROLES, true)) {
            admin_fail('Ruolo: scegline uno dall\'elenco.');
        }
        $catalog = admin_shop_enum($input, 'catalogo', 'Catalogo', ADMIN_CHARACTER_CATALOG, in_array($rarity, ['segreto', 'theone'], true) ? 'segreto' : 'visibile');

        $values = [
            'name' => $name,
            'rarity' => $rarity,
            'ruolo' => $role,
            'category' => admin_shop_text($input, 'categoria', 'Categoria', 100),
            'limitato' => admin_shop_bool($input, 'limitato'),
            'in_pool_standard' => admin_shop_bool($input, 'in_pool_standard'),
            'catalogo' => $catalog,
            // Descrizioni e caratteristiche sono NOT NULL: vuote restano stringhe vuote.
            'description' => admin_shop_text($input, 'descrizione', 'Descrizione (IT)', 500) ?? '',
            'description_en' => admin_shop_text($input, 'descrizione_en', 'Descrizione (EN)', 500) ?? '',
            'features' => ac_traits($input, 'caratteristiche', 'Caratteristiche (IT)'),
            'features_en' => ac_traits($input, 'caratteristiche_en', 'Caratteristiche (EN)'),
            'image' => ac_media($input, 'img_url', ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'Immagine', 'img'),
            'audio' => ac_media($input, 'audio_url', ['mp3', 'wav', 'ogg', 'm4a', 'aac'], 'Audio', 'audio'),
            'video_url' => ac_media($input, 'video_url', ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'], 'Video', 'vid'),
        ];
        if (!$old) {
            $values += ['rarity_en' => '', 'pool_evento' => 0, 'aggiunto_il' => date('Y-m-d H:i:s')];
        }

        $sets = [];
        $types = '';
        $params = [];
        foreach ($values as $key => $value) {
            if (empty($cols[$key])) {
                continue;
            }
            $sets[] = admin_qcol($cols[$key]);
            $types .= in_array($key, ['limitato', 'in_pool_standard', 'pool_evento'], true) ? 'i' : 's';
            $params[] = $value;
        }

        if ($old) {
            $params[] = $id;
            admin_shop_exec($mysqli, 'UPDATE personaggi SET ' . implode(' = ?, ', $sets) . ' = ? WHERE id = ? LIMIT 1', $types . 'i', $params, 'Non sono riuscito a salvare il personaggio.')->close();

            // Nel log solo quello che e' cambiato; i testi lunghi solo per nome.
            // Chiave del log => chiave di $values / $cols.
            $changes = [];
            $compare = ['nome' => 'name', 'rarita' => 'rarity', 'ruolo' => 'ruolo', 'categoria' => 'category', 'limitato' => 'limitato', 'in_pool_standard' => 'in_pool_standard', 'catalogo' => 'catalogo', 'img_url' => 'image', 'audio_url' => 'audio', 'video_url' => 'video_url'];
            foreach ($compare as $key => $colKey) {
                if (empty($cols[$colKey])) {
                    continue;
                }
                $before = (string)($old[$key] ?? '');
                if ($key === 'rarita') {
                    $before = gacha_rarity_key($before) ?: $before;
                }
                if ($before !== (string)($values[$colKey] ?? '')) {
                    $changes[$key] = ['da' => $before, 'a' => $values[$colKey]];
                }
            }
            $texts = [];
            foreach (['descrizione' => ['description', 'descrizione'], 'descrizione_en' => ['description_en', 'descrizione EN'], 'caratteristiche' => ['features', 'caratteristiche'], 'caratteristiche_en' => ['features_en', 'caratteristiche EN']] as $key => [$colKey, $label]) {
                $before = (string)($old[$key] ?? '');
                $before = $colKey === 'features' || $colKey === 'features_en' ? ac_traits_join($before) : trim(str_replace("\r\n", "\n", $before));
                if (!empty($cols[$colKey]) && $before !== $values[$colKey]) {
                    $texts[] = $label;
                }
            }
            if ($texts) {
                $changes['testi'] = implode(', ', $texts);
            }

            if ((string)$old['img_url'] !== (string)$values['image']) {
                // Immagine sostituita: quella vecchia se ne va, se sta in img/personaggi/.
                admin_media_cleanup($mysqli, [$old['img_url']], $adminId);
            }
            if ($changes) {
                admin_log($mysqli, $adminId, 'update_character', null, ['character_id' => $id, 'nome' => $name] + $changes);
            }
            admin_ok(['message' => $changes ? "«{$name}» salvato." : 'Nessuna modifica da salvare.', 'id' => $id]);
        }

        $stmt = admin_shop_exec($mysqli, 'INSERT INTO personaggi (' . implode(', ', $sets) . ') VALUES (' . implode(', ', array_fill(0, count($sets), '?')) . ')', $types, $params, 'Non sono riuscito a creare il personaggio.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        admin_log($mysqli, $adminId, 'create_character', null, ['character_id' => $id, 'nome' => $name, 'rarita' => $rarity]);
        admin_ok(['message' => "«{$name}» creato con l'ID {$id}.", 'id' => $id]);
    }

    if ($action === 'set_flag') {
        $id = (int)($input['id'] ?? 0);
        $old = ac_find($mysqli, $cols, $id);
        $field = (string)($input['field'] ?? '');

        if ($field === 'in_pool_standard' && $cols['in_pool_standard']) {
            $value = admin_shop_bool($input, 'value');
            $message = $value ? "«{$old['nome']}» ora esce anche nel banner standard." : "«{$old['nome']}» ora esce solo nei banner che lo scelgono.";
        } elseif ($field === 'catalogo' && $cols['catalogo']) {
            $value = admin_shop_enum($input, 'value', 'Catalogo', ADMIN_CHARACTER_CATALOG, 'visibile');
            $message = [
                'visibile' => "«{$old['nome']}» si vede nel catalogo anche da chi non ce l'ha.",
                'segreto' => "«{$old['nome']}» compare come ??? a chi non ce l'ha.",
                'nascosto' => "«{$old['nome']}» è nascosto dal catalogo finché qualcuno non lo trova.",
            ][$value];
        } else {
            admin_fail('Campo non modificabile da qui.');
        }

        $column = $field === 'catalogo' ? $cols['catalogo'] : $cols['in_pool_standard'];
        admin_shop_exec($mysqli, 'UPDATE personaggi SET ' . admin_qcol($column) . ' = ? WHERE id = ? LIMIT 1', $field === 'catalogo' ? 'si' : 'ii', [$value, $id], 'Non sono riuscito a salvare.')->close();
        admin_log($mysqli, $adminId, 'update_character', null, ['character_id' => $id, 'nome' => $old['nome'], $field => ['da' => $old[$field], 'a' => $value]]);
        admin_ok(['message' => $message]);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $old = ac_find($mysqli, $cols, $id);
        $owners = ac_owners($mysqli)[$id] ?? 0;

        // Le tabelle del gacha hanno ON DELETE CASCADE; inventario e
        // statistiche dei duelli no su tutti i database, quindi a mano.
        foreach (['utenti_personaggi', 'game_card_stats'] as $table) {
            if (admin_table_exists($mysqli, $table)) {
                admin_shop_exec($mysqli, "DELETE FROM `$table` WHERE personaggio_id = ?", 'i', [$id], 'Non sono riuscito a eliminare il personaggio.')->close();
            }
        }
        admin_shop_exec($mysqli, 'DELETE FROM personaggi WHERE id = ? LIMIT 1', 'i', [$id], 'Non sono riuscito a eliminare il personaggio.')->close();
        admin_media_cleanup($mysqli, [$old['img_url']], $adminId);
        admin_log($mysqli, $adminId, 'delete_character', null, ['character_id' => $id, 'nome' => $old['nome'], 'utenti' => $owners]);
        admin_ok(['message' => $owners ? "«{$old['nome']}» eliminato e tolto a {$owners} " . ($owners === 1 ? 'utente' : 'utenti') . '.' : "«{$old['nome']}» eliminato."]);
    }

    admin_fail('Azione non valida.');
} catch (Throwable $e) {
    error_log('admin characters: ' . $e->getMessage());
    admin_fail('Errore nella sezione Personaggi.', 500);
}
