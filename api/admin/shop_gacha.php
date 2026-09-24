<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/shop/gacha_catalog.php';

/**
 * Shop Gacha dal pannello: pacchetti di Godo Shards e oggetti in vendita per
 * Godos.
 *
 * I pacchetti si pagano con soldi veri, quindi li puo' modificare solo
 * l'owner; gli admin li vedono in sola lettura. Gli oggetti Godos li gestisce
 * qualunque admin.
 *
 * Il flusso di pagamento non passa di qui: questo file scrive solo le righe
 * che la pagina e gli endpoint di pagamento leggono.
 */

$action = admin_shop_action();
$isOwner = admin_is_owner_role($adminUser['ruolo'] ?? null);
$adminId = (int)$adminUser['id'];

$packagesReady = admin_table_exists($mysqli, 'shop_pacchetti_shards');
$itemsReady = admin_table_exists($mysqli, 'godos_shop_items');
$itemsHaveArchive = $itemsReady && admin_column_exists($mysqli, 'godos_shop_items', 'archiviato_at');
$itemsHavePosition = $itemsReady && admin_column_exists($mysqli, 'godos_shop_items', 'posizione');
$itemsHaveWindow = $itemsReady && admin_column_exists($mysqli, 'godos_shop_items', 'disponibile_dal');
$ordersReady = admin_table_exists($mysqli, 'shop_ordini');
$settingsReady = admin_table_exists($mysqli, 'shop_impostazioni');

function gacha_admin_require_owner(bool $isOwner, string $what = 'I pacchetti a pagamento li'): void
{
    if (!$isOwner) {
        admin_fail($what . ' può modificare solo l\'owner.', 403);
    }
}

function gacha_admin_require_packages(bool $ready): void
{
    if (!$ready) {
        admin_fail('Tabella shop_pacchetti_shards mancante: applica la migrazione dello shop.', 409);
    }
}

function gacha_admin_package(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM shop_pacchetti_shards WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Pacchetto non trovato.', 404);
    }
    return $row;
}

/**
 * Data e ora dal campo datetime-local del pannello, oppure null se vuoto.
 */
function gacha_admin_datetime(array $input, string $key, string $label): ?string
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

function gacha_admin_item(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM godos_shop_items WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Oggetto non trovato.', 404);
    }
    return $row;
}

try {
    if ($action === 'list') {
        $packages = [];
        if ($packagesReady) {
            // Chi ha gia' usato il bonus x2 di quel pacchetto: e' il numero
            // di persone che l'hanno comprato almeno una volta.
            $buyers = admin_table_exists($mysqli, 'first_purchase_bonuses')
                ? '(SELECT COUNT(*) FROM first_purchase_bonuses b WHERE b.package_id = p.slug AND b.first_purchase_bonus_used = 1)'
                : '0';
            $packages = admin_shop_rows(
                $mysqli,
                "SELECT p.*, {$buyers} AS acquirenti
                 FROM shop_pacchetti_shards p
                 ORDER BY (p.archiviato_at IS NOT NULL) ASC, p.posizione ASC, p.prezzo_cent ASC, p.id ASC"
            );
        }

        $items = [];
        if ($itemsReady) {
            $order = $itemsHavePosition ? 'i.posizione ASC, i.price_godos ASC' : 'i.price_godos ASC';
            $archiveSort = $itemsHaveArchive ? '(i.archiviato_at IS NOT NULL) ASC, ' : '';
            $purchases = admin_table_exists($mysqli, 'user_godos_shop_purchases')
                ? '(SELECT COUNT(*) FROM user_godos_shop_purchases p WHERE p.item_id = i.id)'
                : '0';
            $items = admin_shop_rows(
                $mysqli,
                "SELECT i.*, cb.name AS badge_name, cb.image_url AS badge_image, cb.color AS badge_color,
                        {$purchases} AS acquisti
                 FROM godos_shop_items i
                 LEFT JOIN custom_badges cb ON cb.id = CAST(i.item_value AS UNSIGNED) AND i.item_type = 'badge'
                 ORDER BY {$archiveSort}{$order}, i.id ASC"
            );
        }

        $badges = admin_table_exists($mysqli, 'custom_badges')
            ? admin_shop_rows($mysqli, 'SELECT id, slug, name, name_en, descrizione, descrizione_en, image_url, color FROM custom_badges ORDER BY name ASC')
            : [];

        admin_ok([
            'packages_ready' => $packagesReady,
            'items_ready' => $itemsReady,
            'items_archive' => $itemsHaveArchive,
            'items_position' => $itemsHavePosition,
            'items_window' => $itemsHaveWindow,
            'orders_ready' => $ordersReady,
            'settings_ready' => $settingsReady,
            'godos_per_shard' => gacha_godos_per_shard($mysqli),
            'can_edit_packages' => $isOwner,
            'packages' => $packages,
            'items' => $items,
            'badges' => $badges,
        ]);
    }

    if ($action === 'orders') {
        if (!$ordersReady) {
            admin_ok(['ready' => false, 'orders' => [], 'total' => 0, 'page' => 1, 'pages' => 1]);
        }

        // Solo lettura, per l'assistenza: chi ha comprato cosa e se le Shards
        // sono arrivate.
        $where = ['1 = 1'];
        $types = '';
        $params = [];

        $stato = (string)($_GET['stato'] ?? '');
        if (in_array($stato, ['in_attesa', 'pagato', 'errore'], true)) {
            $where[] = 'o.stato = ?';
            $types .= 's';
            $params[] = $stato;
        }

        $provider = (string)($_GET['provider'] ?? '');
        if (in_array($provider, ['stripe', 'paypal'], true)) {
            $where[] = 'o.provider = ?';
            $types .= 's';
            $params[] = $provider;
        }

        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.username LIKE ? OR o.provider_ref LIKE ? OR o.pacchetto LIKE ?)';
            $like = '%' . $q . '%';
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)(admin_shop_row($mysqli, "SELECT COUNT(*) AS n FROM shop_ordini o LEFT JOIN utenti u ON u.id = o.user_id WHERE $whereSql", $types, $params)['n'] ?? 0);
        $limit = 30;
        $pages = max(1, (int)ceil($total / $limit));
        $page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
        $offset = ($page - 1) * $limit;

        $rows = admin_shop_rows(
            $mysqli,
            "SELECT o.*, u.username,
                    TIMESTAMPDIFF(SECOND, o.created_at, NOW()) AS eta_secondi
             FROM shop_ordini o
             LEFT JOIN utenti u ON u.id = o.user_id
             WHERE $whereSql
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT $limit OFFSET $offset",
            $types,
            $params
        );

        $totals = admin_shop_row($mysqli, "SELECT COUNT(*) AS n, COALESCE(SUM(importo_cent), 0) AS cent FROM shop_ordini WHERE stato = 'pagato' AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

        admin_ok([
            'ready' => true,
            'orders' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'last30' => ['count' => (int)($totals['n'] ?? 0), 'cents' => (int)($totals['cent'] ?? 0)],
        ]);
    }

    admin_shop_require_post();
    $input = admin_input();

    switch ($action) {
        /* ── Pacchetti di Godo Shards (solo owner) ─────────────────────── */

        case 'save_package':
            gacha_admin_require_owner($isOwner);
            gacha_admin_require_packages($packagesReady);

            $id = (int)($input['id'] ?? 0);
            $nome = admin_shop_text($input, 'nome', 'Nome', 80, true);
            $shards = admin_shop_int($input, 'shards', 'Shards', 1, 1000000);
            // Sotto i 50 centesimi Stripe rifiuta il pagamento.
            $cents = admin_shop_price_cents($input, 'prezzo', 'Prezzo', 50, 99999);
            $evidenza = admin_shop_enum($input, 'evidenza', 'Evidenza', ['nessuna', 'pity', 'best'], 'nessuna');
            $attivo = admin_shop_bool($input, 'attivo');

            if ($id > 0) {
                $before = gacha_admin_package($mysqli, $id);
                admin_shop_exec(
                    $mysqli,
                    'UPDATE shop_pacchetti_shards SET nome = ?, shards = ?, prezzo_cent = ?, evidenza = ?, attivo = ? WHERE id = ? LIMIT 1',
                    'siisii',
                    [$nome, $shards, $cents, $evidenza, $attivo, $id],
                    'Non sono riuscito a salvare il pacchetto.'
                )->close();

                admin_log($mysqli, $adminId, 'shop_update_package', null, [
                    'slug' => $before['slug'],
                    'prezzo_cent' => [(int)$before['prezzo_cent'], $cents],
                    'shards' => [(int)$before['shards'], $shards],
                ]);
                admin_ok(['message' => 'Pacchetto salvato.', 'id' => $id]);
            }

            // Lo slug di un pacchetto nuovo non si cambia piu': e' la chiave
            // del bonus x2 e viaggia nei pagamenti.
            $slug = strtolower(trim((string)($input['slug'] ?? '')));
            if ($slug === '') {
                $slug = 'shards_' . $shards;
            }
            if (!preg_match('/^[a-z0-9_]{3,50}$/', $slug)) {
                admin_fail('Codice: usa solo lettere minuscole, numeri e trattino basso (es. shards_150).');
            }

            $position = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_pacchetti_shards');
            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO shop_pacchetti_shards (slug, nome, shards, prezzo_cent, evidenza, attivo, posizione) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'ssiisii',
                [$slug, $nome, $shards, $cents, $evidenza, $attivo, $position],
                'Non sono riuscito a creare il pacchetto.'
            );
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'shop_create_package', null, ['slug' => $slug, 'prezzo_cent' => $cents, 'shards' => $shards]);
            admin_ok(['message' => 'Pacchetto creato.', 'id' => $newId]);

        case 'toggle_package':
            gacha_admin_require_owner($isOwner);
            gacha_admin_require_packages($packagesReady);
            $package = gacha_admin_package($mysqli, (int)($input['id'] ?? 0));
            $attivo = admin_shop_bool($input, 'attivo');
            admin_shop_exec($mysqli, 'UPDATE shop_pacchetti_shards SET attivo = ? WHERE id = ? LIMIT 1', 'ii', [$attivo, (int)$package['id']], 'Aggiornamento non riuscito.')->close();
            admin_log($mysqli, $adminId, 'shop_toggle_package', null, ['slug' => $package['slug'], 'attivo' => $attivo]);
            admin_ok(['message' => $attivo ? 'Pacchetto in vendita.' : 'Pacchetto ritirato dalla vendita.']);

        case 'archive_package':
            // Un pacchetto non si cancella mai: un pagamento partito prima
            // dell'archiviazione deve ancora trovarlo.
            gacha_admin_require_owner($isOwner);
            gacha_admin_require_packages($packagesReady);
            $package = gacha_admin_package($mysqli, (int)($input['id'] ?? 0));
            admin_shop_exec($mysqli, 'UPDATE shop_pacchetti_shards SET attivo = 0, archiviato_at = NOW() WHERE id = ? LIMIT 1', 'i', [(int)$package['id']], 'Archiviazione non riuscita.')->close();
            admin_log($mysqli, $adminId, 'shop_archive_package', null, ['slug' => $package['slug']]);
            admin_ok(['message' => 'Pacchetto archiviato.']);

        case 'restore_package':
            gacha_admin_require_owner($isOwner);
            gacha_admin_require_packages($packagesReady);
            $package = gacha_admin_package($mysqli, (int)($input['id'] ?? 0));
            admin_shop_exec($mysqli, 'UPDATE shop_pacchetti_shards SET archiviato_at = NULL WHERE id = ? LIMIT 1', 'i', [(int)$package['id']], 'Ripristino non riuscito.')->close();
            admin_log($mysqli, $adminId, 'shop_restore_package', null, ['slug' => $package['slug']]);
            admin_ok(['message' => 'Pacchetto ripristinato (spento: riaccendilo quando vuoi).']);

        case 'reorder_packages':
            gacha_admin_require_owner($isOwner);
            gacha_admin_require_packages($packagesReady);
            admin_shop_reorder($mysqli, 'shop_pacchetti_shards', 'posizione', admin_shop_ids($input));
            admin_log($mysqli, $adminId, 'shop_reorder_packages');
            admin_ok(['message' => 'Ordine aggiornato.']);

        /* ── Oggetti in vendita per Godos ───────────────────────────────── */

        case 'save_item':
            if (!$itemsReady) {
                admin_fail('Tabella godos_shop_items mancante.', 409);
            }

            $id = (int)($input['id'] ?? 0);
            $badgeId = admin_shop_int($input, 'badge_id', 'Badge', 1, PHP_INT_MAX);
            $badge = admin_shop_row($mysqli, 'SELECT id, name, name_en, image_url FROM custom_badges WHERE id = ? LIMIT 1', 'i', [$badgeId]);
            if (!$badge) {
                admin_fail('Badge: quello scelto non esiste più.');
            }

            $nameIt = admin_shop_text($input, 'name_it', 'Nome (IT)', 100) ?? (string)$badge['name'];
            $nameEn = admin_shop_text($input, 'name_en', 'Nome (EN)', 100) ?? ((string)($badge['name_en'] ?? '') ?: $nameIt);
            $descIt = admin_shop_text($input, 'description_it', 'Descrizione (IT)', 500) ?? '';
            $descEn = admin_shop_text($input, 'description_en', 'Descrizione (EN)', 500) ?? $descIt;
            $price = admin_shop_int($input, 'price_godos', 'Prezzo in Godos', 1, 100000000);
            $availabilityRaw = trim((string)($input['availability'] ?? ''));
            $availability = $availabilityRaw === '' ? null : admin_shop_int($input, 'availability', 'Pezzi disponibili', 0, 1000000);
            $active = admin_shop_bool($input, 'active');
            $image = admin_shop_image($input, 'image_url', 'Immagine') ?? ((string)($badge['image_url'] ?? '') ?: null);
            $itemValue = (string)$badgeId;

            $from = $itemsHaveWindow ? gacha_admin_datetime($input, 'disponibile_dal', 'In vendita dal') : null;
            $until = $itemsHaveWindow ? gacha_admin_datetime($input, 'disponibile_fino', 'In vendita fino al') : null;
            if ($from !== null && $until !== null && strtotime($until) <= strtotime($from)) {
                admin_fail('In vendita fino al: deve venire dopo la data di inizio.');
            }

            if ($id > 0) {
                $previous = gacha_admin_item($mysqli, $id);
                admin_shop_exec(
                    $mysqli,
                    "UPDATE godos_shop_items
                     SET name_it = ?, name_en = ?, description_it = ?, description_en = ?, price_godos = ?,
                         item_type = 'badge', item_value = ?, availability = ?, active = ?, image_url = ?
                     WHERE id = ? LIMIT 1",
                    'ssssisiisi',
                    [$nameIt, $nameEn, $descIt, $descEn, $price, $itemValue, $availability, $active, $image, $id],
                    'Non sono riuscito a salvare l\'oggetto.'
                )->close();

                if ($itemsHaveWindow) {
                    admin_shop_exec($mysqli, 'UPDATE godos_shop_items SET disponibile_dal = ?, disponibile_fino = ? WHERE id = ? LIMIT 1', 'ssi', [$from, $until, $id], 'Non sono riuscito a salvare le date.')->close();
                }

                admin_media_cleanup($mysqli, [$previous['image_url'] ?? null], $adminId);

                admin_log($mysqli, $adminId, 'shop_update_godos_item', null, ['item_id' => $id, 'badge_id' => $badgeId, 'price' => $price]);
                admin_ok(['message' => 'Oggetto salvato.', 'id' => $id]);
            }

            if ($itemsHavePosition) {
                $position = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM godos_shop_items');
                $stmt = admin_shop_exec(
                    $mysqli,
                    "INSERT INTO godos_shop_items
                        (name_it, name_en, description_it, description_en, price_godos, item_type, item_value, availability, active, image_url, posizione)
                     VALUES (?, ?, ?, ?, ?, 'badge', ?, ?, ?, ?, ?)",
                    'ssssisiisi',
                    [$nameIt, $nameEn, $descIt, $descEn, $price, $itemValue, $availability, $active, $image, $position],
                    'Non sono riuscito a creare l\'oggetto.'
                );
            } else {
                $stmt = admin_shop_exec(
                    $mysqli,
                    "INSERT INTO godos_shop_items
                        (name_it, name_en, description_it, description_en, price_godos, item_type, item_value, availability, active, image_url)
                     VALUES (?, ?, ?, ?, ?, 'badge', ?, ?, ?, ?)",
                    'ssssisiis',
                    [$nameIt, $nameEn, $descIt, $descEn, $price, $itemValue, $availability, $active, $image],
                    'Non sono riuscito a creare l\'oggetto.'
                );
            }
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            if ($itemsHaveWindow) {
                admin_shop_exec($mysqli, 'UPDATE godos_shop_items SET disponibile_dal = ?, disponibile_fino = ? WHERE id = ? LIMIT 1', 'ssi', [$from, $until, $newId], 'Non sono riuscito a salvare le date.')->close();
            }

            admin_log($mysqli, $adminId, 'shop_create_godos_item', null, ['item_id' => $newId, 'badge_id' => $badgeId, 'price' => $price]);
            admin_ok(['message' => 'Oggetto creato.', 'id' => $newId]);

        case 'toggle_item':
            $item = gacha_admin_item($mysqli, (int)($input['id'] ?? 0));
            $active = admin_shop_bool($input, 'active');
            admin_shop_exec($mysqli, 'UPDATE godos_shop_items SET active = ? WHERE id = ? LIMIT 1', 'ii', [$active, (int)$item['id']], 'Aggiornamento non riuscito.')->close();
            admin_log($mysqli, $adminId, 'shop_toggle_godos_item', null, ['item_id' => (int)$item['id'], 'active' => $active]);
            admin_ok(['message' => $active ? 'Oggetto in vendita.' : 'Oggetto ritirato dalla vendita.']);

        case 'delete_item':
            $item = gacha_admin_item($mysqli, (int)($input['id'] ?? 0));
            $itemId = (int)$item['id'];
            $purchases = admin_table_exists($mysqli, 'user_godos_shop_purchases')
                ? (int)(admin_shop_row($mysqli, 'SELECT COUNT(*) AS n FROM user_godos_shop_purchases WHERE item_id = ?', 'i', [$itemId])['n'] ?? 0)
                : 0;

            // Con acquisti alle spalle si archivia: la FK ON DELETE CASCADE
            // cancellerebbe anche lo storico di chi l'ha comprato. I badge
            // gia' assegnati restano comunque agli utenti.
            if ($purchases > 0) {
                if (!$itemsHaveArchive) {
                    admin_fail('Questo oggetto ha ' . $purchases . ' acquisti: per non perdere lo storico spegnilo, oppure applica la migrazione dello shop per poterlo archiviare.');
                }
                admin_shop_exec($mysqli, 'UPDATE godos_shop_items SET active = 0, archiviato_at = NOW() WHERE id = ? LIMIT 1', 'i', [$itemId], 'Archiviazione non riuscita.')->close();
                admin_log($mysqli, $adminId, 'shop_archive_godos_item', null, ['item_id' => $itemId, 'acquisti' => $purchases]);
                admin_ok(['message' => 'Oggetto archiviato: aveva ' . $purchases . ' acquisti, lo storico resta.', 'archived' => true]);
            }

            admin_shop_exec($mysqli, 'DELETE FROM godos_shop_items WHERE id = ? LIMIT 1', 'i', [$itemId], 'Eliminazione non riuscita.')->close();
            admin_media_cleanup($mysqli, [$item['image_url'] ?? null], $adminId);
            admin_log($mysqli, $adminId, 'shop_delete_godos_item', null, ['item_id' => $itemId, 'nome' => $item['name_it']]);
            admin_ok(['message' => 'Oggetto eliminato.', 'archived' => false]);

        case 'restore_item':
            if (!$itemsHaveArchive) {
                admin_fail('Applica la migrazione dello shop.', 409);
            }
            $item = gacha_admin_item($mysqli, (int)($input['id'] ?? 0));
            admin_shop_exec($mysqli, 'UPDATE godos_shop_items SET archiviato_at = NULL WHERE id = ? LIMIT 1', 'i', [(int)$item['id']], 'Ripristino non riuscito.')->close();
            admin_log($mysqli, $adminId, 'shop_restore_godos_item', null, ['item_id' => (int)$item['id']]);
            admin_ok(['message' => 'Oggetto ripristinato (spento: riaccendilo quando vuoi).']);

        case 'save_settings':
            // Il cambio decide quanto valgono le Shards comprate con soldi
            // veri rispetto a quelle ottenute giocando: solo l'owner.
            gacha_admin_require_owner($isOwner, 'Il cambio Godos/Shards lo');
            if (!$settingsReady) {
                admin_fail('Tabella shop_impostazioni mancante: applica la migrazione dello shop.', 409);
            }
            $rate = admin_shop_int($input, 'godos_per_shard', 'Godos per una Shard', 1, 1000000);
            $before = gacha_godos_per_shard($mysqli);
            $value = (string)$rate;
            admin_shop_exec(
                $mysqli,
                "INSERT INTO shop_impostazioni (chiave, valore) VALUES ('godos_per_shard', ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)",
                's',
                [$value],
                'Non sono riuscito a salvare il cambio.'
            )->close();
            admin_log($mysqli, $adminId, 'shop_update_settings', null, ['godos_per_shard' => [$before, $rate]]);
            admin_ok(['message' => 'Cambio salvato: ' . $rate . ' Godos = 1 Shard.']);

        case 'reorder_items':
            if (!$itemsHavePosition) {
                admin_fail('Per riordinare gli oggetti applica la migrazione dello shop.', 409);
            }
            admin_shop_reorder($mysqli, 'godos_shop_items', 'posizione', admin_shop_ids($input));
            admin_log($mysqli, $adminId, 'shop_reorder_godos_items');
            admin_ok(['message' => 'Ordine aggiornato.']);
    }

    admin_fail('Azione non riconosciuta.', 400);
} catch (Throwable $e) {
    error_log('[admin shop_gacha] ' . $e->getMessage());
    admin_fail('Errore del server sullo Shop Gacha.', 500);
}
