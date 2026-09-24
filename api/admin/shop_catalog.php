<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';

/**
 * Negozio e Merch dal pannello: vetrine (il Negozio e le collezioni del
 * Merch), categorie e prodotti. Sono acquisti finti, qui non passano soldi.
 *
 * GET  ?action=list&tipo=negozio|merch
 * POST action = save_vetrina | delete_vetrina | reorder_vetrine
 *             | save_category | delete_category | reorder_categories
 *             | save_product | toggle_product | duplicate_product
 *             | delete_product | reorder_products
 */

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!admin_table_exists($mysqli, 'shop_vetrine') || !admin_table_exists($mysqli, 'shop_prodotti') || !admin_table_exists($mysqli, 'shop_categorie')) {
    if ($action === 'list') {
        admin_ok(['ready' => false, 'message' => 'Tabelle dello shop mancanti: applica la migrazione 2026_09_23_shop_catalog.sql.']);
    }
    admin_fail('Tabelle dello shop mancanti: applica la migrazione.', 409);
}

/**
 * Le colonne della pagina prodotto (descrizione lunga, specifiche, galleria)
 * arrivano con la migrazione 2026_09_23b: prima, il pannello non le mostra e
 * il salvataggio le salta.
 */
function catalog_details_ready(mysqli $mysqli): bool
{
    return admin_column_exists($mysqli, 'shop_prodotti', 'specifiche')
        && admin_column_exists($mysqli, 'shop_prodotti', 'galleria')
        && admin_column_exists($mysqli, 'shop_prodotti', 'descrizione_lunga');
}

function catalog_tipo(array $source): string
{
    $tipo = (string)($source['tipo'] ?? '');
    if (!in_array($tipo, ['negozio', 'merch'], true)) {
        admin_fail('Tipo di vetrina non valido.');
    }
    return $tipo;
}

function catalog_vetrina(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM shop_vetrine WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Vetrina non trovata.', 404);
    }
    return $row;
}

function catalog_product(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM shop_prodotti WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Prodotto non trovato.', 404);
    }
    return $row;
}

/**
 * Uno slug libero per un prodotto: se "felpa-poppy" c'e' gia' si prova
 * "felpa-poppy-2", "-3"... Serve a Duplica e a chi crea senza scriverlo.
 */
function catalog_free_product_slug(mysqli $mysqli, string $base, int $exceptId = 0): string
{
    $base = trim(substr($base, 0, 72), '-') ?: 'prodotto';
    $slug = $base;
    $n = 2;

    while (admin_shop_row($mysqli, 'SELECT id FROM shop_prodotti WHERE slug = ? AND id <> ? LIMIT 1', 'si', [$slug, $exceptId])) {
        $slug = $base . '-' . $n++;
        if ($n > 200) {
            admin_fail('Non trovo uno slug libero: scrivine uno a mano.');
        }
    }

    return $slug;
}

try {
    if ($action === 'list') {
        $tipo = catalog_tipo($_GET);

        $vetrine = admin_shop_rows(
            $mysqli,
            'SELECT v.*, (SELECT COUNT(*) FROM shop_prodotti p WHERE p.vetrina_id = v.id) AS prodotti,
                    (SELECT COUNT(*) FROM shop_faq f WHERE f.vetrina_id = v.id) AS faq
             FROM shop_vetrine v WHERE v.tipo = ? ORDER BY v.posizione ASC, v.id ASC',
            's',
            [$tipo]
        );

        $categorie = admin_shop_rows(
            $mysqli,
            'SELECT c.*, (SELECT COUNT(*) FROM shop_prodotti p WHERE p.categoria_id = c.id) AS prodotti
             FROM shop_categorie c WHERE c.tipo = ? ORDER BY c.posizione ASC, c.id ASC',
            's',
            [$tipo]
        );

        $prodotti = admin_shop_rows(
            $mysqli,
            'SELECT p.* FROM shop_prodotti p JOIN shop_vetrine v ON v.id = p.vetrina_id
             WHERE v.tipo = ? ORDER BY v.posizione ASC, p.posizione ASC, p.id ASC',
            's',
            [$tipo]
        );

        // Specifiche e galleria tornano testo, pronte per i campi del pannello.
        $details = catalog_details_ready($mysqli);
        if ($details) {
            foreach ($prodotti as &$prodotto) {
                $prodotto['specifiche_it'] = admin_shop_pairs_text($prodotto['specifiche'] ?? null, 'it');
                $prodotto['specifiche_en'] = admin_shop_pairs_text($prodotto['specifiche'] ?? null, 'en');
                $gallery = json_decode((string)($prodotto['galleria'] ?? ''), true);
                $prodotto['galleria_testo'] = is_array($gallery) ? implode("\n", $gallery) : '';
            }
            unset($prodotto);
        }

        admin_ok(['ready' => true, 'details' => $details, 'vetrine' => $vetrine, 'categorie' => $categorie, 'prodotti' => $prodotti]);
    }

    admin_shop_require_post();
    $input = admin_input();

    switch ($action) {
        /* ── Vetrine: il Negozio e le collezioni del Merch ─────────────── */

        case 'save_vetrina':
            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0 ? catalog_vetrina($mysqli, $id) : null;
            $tipo = $existing ? (string)$existing['tipo'] : catalog_tipo($input);

            // Il Negozio e' uno solo: si modifica, non se ne creano altri.
            if (!$existing && $tipo !== 'merch') {
                admin_fail('Si possono creare solo collezioni del Merch.');
            }

            $nome = admin_shop_text($input, 'nome', 'Nome', 80, true);
            $slug = $tipo === 'negozio' ? 'negozio' : admin_shop_slug($input, 'slug', $nome, 60);

            // /it/merch/checkout e simili non devono mai diventare collezioni.
            if ($tipo === 'merch' && in_array($slug, ['checkout', 'confirm', 'conferma', 'tutto', 'all'], true)) {
                admin_fail('Slug: "' . $slug . '" è riservato, scegline un altro.');
            }

            $fields = [
                'nome' => $nome,
                'nome_en' => admin_shop_text($input, 'nome_en', 'Nome (EN)', 80),
                'titolo' => admin_shop_text($input, 'titolo', 'Titolo (IT)', 120),
                'titolo_en' => admin_shop_text($input, 'titolo_en', 'Titolo (EN)', 120),
                'sottotitolo' => admin_shop_text($input, 'sottotitolo', 'Sottotitolo (IT)', 400),
                'sottotitolo_en' => admin_shop_text($input, 'sottotitolo_en', 'Sottotitolo (EN)', 400),
                'emoji' => admin_shop_text($input, 'emoji', 'Emoji', 32),
                'logo' => admin_shop_image($input, 'logo', 'Logo'),
                'copertina' => admin_shop_image($input, 'copertina', 'Copertina'),
                'colore_accento' => admin_shop_color($input, 'colore_accento', 'Colore principale', '#6d5dfc'),
                'colore_sfondo' => admin_shop_color($input, 'colore_sfondo', 'Sfondo 1', '#05070d'),
                'colore_sfondo_2' => admin_shop_color($input, 'colore_sfondo_2', 'Sfondo 2', '#0b1020'),
                'stato' => $tipo === 'negozio' ? 'attiva' : admin_shop_enum($input, 'stato', 'Stato', ['attiva', 'nascosta', 'in_arrivo'], 'attiva'),
                'lancio_at' => null,
            ];

            if ($fields['stato'] === 'in_arrivo') {
                $launch = trim((string)($input['lancio_at'] ?? ''));
                if ($launch !== '') {
                    $time = strtotime(str_replace('T', ' ', $launch));
                    if ($time === false) {
                        admin_fail('Data di lancio non valida.');
                    }
                    $fields['lancio_at'] = date('Y-m-d H:i:s', $time);
                }
            }

            $columns = array_keys($fields);
            $values = array_values($fields);
            $types = str_repeat('s', count($values));

            if ($existing) {
                $set = implode(', ', array_map(static fn($c) => "`$c` = ?", $columns));
                admin_shop_exec(
                    $mysqli,
                    "UPDATE shop_vetrine SET $set, slug = ? WHERE id = ? LIMIT 1",
                    $types . 'si',
                    array_merge($values, [$slug, $id]),
                    'Non sono riuscito a salvare la vetrina.'
                )->close();

                // Logo o copertina sostituiti: il file vecchio se ne va.
                admin_media_cleanup($mysqli, [$existing['logo'] ?? null, $existing['copertina'] ?? null], $adminId);

                admin_log($mysqli, $adminId, 'shop_update_vetrina', null, ['vetrina_id' => $id, 'slug' => $slug]);
                admin_ok(['message' => $tipo === 'negozio' ? 'Negozio salvato.' : 'Collezione salvata.', 'id' => $id, 'slug' => $slug]);
            }

            $position = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_vetrine WHERE tipo = ?', 's', [$tipo]);
            $placeholders = implode(', ', array_fill(0, count($columns) + 3, '?'));
            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO shop_vetrine (`' . implode('`, `', $columns) . "`, tipo, slug, posizione) VALUES ($placeholders)",
                $types . 'ssi',
                array_merge($values, [$tipo, $slug, $position]),
                'Non sono riuscito a creare la collezione.'
            );
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'shop_create_vetrina', null, ['vetrina_id' => $newId, 'slug' => $slug]);
            admin_ok(['message' => 'Collezione creata.', 'id' => $newId, 'slug' => $slug]);

        case 'delete_vetrina':
            $vetrina = catalog_vetrina($mysqli, (int)($input['id'] ?? 0));
            if ($vetrina['tipo'] !== 'merch') {
                admin_fail('Il Negozio non si può eliminare.');
            }

            $vetrinaId = (int)$vetrina['id'];
            $count = (int)(admin_shop_row($mysqli, 'SELECT COUNT(*) AS n FROM shop_prodotti WHERE vetrina_id = ?', 'i', [$vetrinaId])['n'] ?? 0);
            $mode = (string)($input['mode'] ?? 'delete');

            // Le immagini da ripulire dopo: quelle della collezione e, se i
            // prodotti se ne vanno con lei, anche le loro.
            $images = [$vetrina['logo'] ?? null, $vetrina['copertina'] ?? null];
            if ($count > 0 && $mode !== 'move') {
                foreach (admin_shop_rows($mysqli, 'SELECT * FROM shop_prodotti WHERE vetrina_id = ?', 'i', [$vetrinaId]) as $row) {
                    $images[] = $row['immagine'] ?? null;
                    $images[] = $row['galleria'] ?? null;
                }
            }

            $mysqli->begin_transaction();

            // Con "sposta" i prodotti passano a un'altra collezione prima che
            // la FK li cancelli insieme alla loro.
            if ($count > 0 && $mode === 'move') {
                $target = catalog_vetrina($mysqli, (int)($input['target_id'] ?? 0));
                if ($target['tipo'] !== 'merch' || (int)$target['id'] === $vetrinaId) {
                    $mysqli->rollback();
                    admin_fail('Scegli un\'altra collezione in cui spostare i prodotti.');
                }
                $offset = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_prodotti WHERE vetrina_id = ?', 'i', [(int)$target['id']]);
                admin_shop_exec(
                    $mysqli,
                    'UPDATE shop_prodotti SET vetrina_id = ?, posizione = posizione + ? WHERE vetrina_id = ?',
                    'iii',
                    [(int)$target['id'], $offset, $vetrinaId],
                    'Non sono riuscito a spostare i prodotti.'
                )->close();
            }

            admin_shop_exec($mysqli, 'DELETE FROM shop_vetrine WHERE id = ? LIMIT 1', 'i', [$vetrinaId], 'Eliminazione non riuscita.')->close();
            $mysqli->commit();

            admin_media_cleanup($mysqli, $images, $adminId);

            admin_log($mysqli, $adminId, 'shop_delete_vetrina', null, ['slug' => $vetrina['slug'], 'prodotti' => $count, 'mode' => $mode]);
            admin_ok(['message' => 'Collezione eliminata.']);

        case 'reorder_vetrine':
            admin_shop_reorder($mysqli, 'shop_vetrine', 'posizione', admin_shop_ids($input));
            admin_log($mysqli, $adminId, 'shop_reorder_vetrine');
            admin_ok(['message' => 'Ordine aggiornato.']);

        /* ── Categorie ─────────────────────────────────────────────────── */

        case 'save_category':
            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0 ? admin_shop_row($mysqli, 'SELECT * FROM shop_categorie WHERE id = ? LIMIT 1', 'i', [$id]) : null;
            if ($id > 0 && !$existing) {
                admin_fail('Categoria non trovata.', 404);
            }
            $tipo = $existing ? (string)$existing['tipo'] : catalog_tipo($input);
            $nome = admin_shop_text($input, 'nome', 'Nome (IT)', 60, true);
            $nomeEn = admin_shop_text($input, 'nome_en', 'Nome (EN)', 60);
            $slug = admin_shop_slug($input, 'slug', $nome, 60);

            if ($existing) {
                admin_shop_exec($mysqli, 'UPDATE shop_categorie SET nome = ?, nome_en = ?, slug = ? WHERE id = ? LIMIT 1', 'sssi', [$nome, $nomeEn, $slug, $id], 'Non sono riuscito a salvare la categoria.')->close();
                admin_log($mysqli, $adminId, 'shop_update_category', null, ['category_id' => $id, 'nome' => $nome]);
                admin_ok(['message' => 'Categoria salvata.', 'id' => $id]);
            }

            $position = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_categorie WHERE tipo = ?', 's', [$tipo]);
            $stmt = admin_shop_exec($mysqli, 'INSERT INTO shop_categorie (tipo, slug, nome, nome_en, posizione) VALUES (?, ?, ?, ?, ?)', 'ssssi', [$tipo, $slug, $nome, $nomeEn, $position], 'Non sono riuscito a creare la categoria.');
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'shop_create_category', null, ['category_id' => $newId, 'nome' => $nome]);
            admin_ok(['message' => 'Categoria creata.', 'id' => $newId]);

        case 'delete_category':
            // I prodotti restano: la FK li lascia semplicemente senza categoria.
            $id = (int)($input['id'] ?? 0);
            $category = admin_shop_row($mysqli, 'SELECT * FROM shop_categorie WHERE id = ? LIMIT 1', 'i', [$id]);
            if (!$category) {
                admin_fail('Categoria non trovata.', 404);
            }
            admin_shop_exec($mysqli, 'DELETE FROM shop_categorie WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            admin_log($mysqli, $adminId, 'shop_delete_category', null, ['nome' => $category['nome']]);
            admin_ok(['message' => 'Categoria eliminata.']);

        case 'reorder_categories':
            admin_shop_reorder($mysqli, 'shop_categorie', 'posizione', admin_shop_ids($input));
            admin_log($mysqli, $adminId, 'shop_reorder_categories');
            admin_ok(['message' => 'Ordine aggiornato.']);

        /* ── Prodotti ──────────────────────────────────────────────────── */

        case 'save_product':
            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0 ? catalog_product($mysqli, $id) : null;

            $vetrina = catalog_vetrina($mysqli, (int)($input['vetrina_id'] ?? 0));
            $vetrinaId = (int)$vetrina['id'];

            $categoriaId = (int)($input['categoria_id'] ?? 0);
            if ($categoriaId > 0) {
                $category = admin_shop_row($mysqli, 'SELECT tipo FROM shop_categorie WHERE id = ? LIMIT 1', 'i', [$categoriaId]);
                if (!$category || $category['tipo'] !== $vetrina['tipo']) {
                    admin_fail('Categoria: non appartiene a questa vetrina.');
                }
            }
            $categoriaId = $categoriaId > 0 ? $categoriaId : null;

            $nome = admin_shop_text($input, 'nome', 'Nome (IT)', 120, true);
            $slugRaw = trim((string)($input['slug'] ?? ''));
            $slug = $slugRaw !== ''
                ? admin_shop_slug($input, 'slug', $nome, 80)
                : catalog_free_product_slug($mysqli, admin_shop_slugify($nome . ' ' . (string)($input['variante'] ?? '')), $id);

            $prezzo = admin_shop_price_cents($input, 'prezzo', 'Prezzo', 0, 99999999);
            $prezzoPieno = admin_shop_price_cents($input, 'prezzo_pieno', 'Prezzo pieno', 0, 99999999, false);
            if ($prezzoPieno !== null && $prezzoPieno <= $prezzo) {
                admin_fail('Prezzo pieno: deve essere più alto del prezzo di vendita (è quello che appare barrato).');
            }

            // Taglie: "s, m , L,xl" -> "S,M,L,XL", senza doppioni.
            $taglieRaw = admin_shop_text($input, 'taglie', 'Taglie', 120);
            $taglie = null;
            if ($taglieRaw !== null) {
                $list = array_values(array_unique(array_filter(array_map(
                    static fn($s) => mb_strtoupper(trim($s), 'UTF-8'),
                    explode(',', $taglieRaw)
                ), 'strlen')));
                $taglie = $list ? implode(',', $list) : null;
            }

            $fields = [
                'vetrina_id' => $vetrinaId,
                'categoria_id' => $categoriaId,
                'slug' => $slug,
                'nome' => $nome,
                'nome_en' => admin_shop_text($input, 'nome_en', 'Nome (EN)', 120),
                'variante' => admin_shop_text($input, 'variante', 'Variante (IT)', 80),
                'variante_en' => admin_shop_text($input, 'variante_en', 'Variante (EN)', 80),
                'descrizione' => admin_shop_text($input, 'descrizione', 'Descrizione (IT)', 600),
                'descrizione_en' => admin_shop_text($input, 'descrizione_en', 'Descrizione (EN)', 600),
                'badge' => admin_shop_text($input, 'badge', 'Badge (IT)', 40),
                'badge_en' => admin_shop_text($input, 'badge_en', 'Badge (EN)', 40),
                'prezzo' => number_format($prezzo / 100, 2, '.', ''),
                'prezzo_pieno' => $prezzoPieno !== null ? number_format($prezzoPieno / 100, 2, '.', '') : null,
                'immagine' => admin_shop_image($input, 'immagine', 'Immagine'),
                'taglie' => $taglie,
                'attivo' => admin_shop_bool($input, 'attivo'),
                'in_evidenza' => admin_shop_bool($input, 'in_evidenza'),
            ];

            $columns = array_keys($fields);
            $values = array_values($fields);
            $types = 'ii' . str_repeat('s', 13) . 'ii';

            if (catalog_details_ready($mysqli)) {
                $specs = [
                    'it' => admin_shop_pairs($input['specifiche_it'] ?? '', 'Specifiche (IT)'),
                    'en' => admin_shop_pairs($input['specifiche_en'] ?? '', 'Specifiche (EN)'),
                ];
                $gallery = admin_shop_image_list($input, 'galleria', 'Altre foto');
                $details = [
                    'descrizione_lunga' => admin_shop_text($input, 'descrizione_lunga', 'Descrizione completa (IT)', 5000),
                    'descrizione_lunga_en' => admin_shop_text($input, 'descrizione_lunga_en', 'Descrizione completa (EN)', 5000),
                    'specifiche' => ($specs['it'] || $specs['en']) ? json_encode($specs, JSON_UNESCAPED_UNICODE) : null,
                    'galleria' => $gallery ? json_encode($gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ];
                foreach ($details as $column => $value) {
                    $columns[] = $column;
                    $values[] = $value;
                    $types .= 's';
                }
            }

            if ($existing) {
                // Un prodotto spostato in un'altra collezione va in fondo.
                if ((int)$existing['vetrina_id'] !== $vetrinaId) {
                    $columns[] = 'posizione';
                    $values[] = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_prodotti WHERE vetrina_id = ?', 'i', [$vetrinaId]);
                    $types .= 'i';
                }
                $set = implode(', ', array_map(static fn($c) => "`$c` = ?", $columns));
                admin_shop_exec(
                    $mysqli,
                    "UPDATE shop_prodotti SET $set WHERE id = ? LIMIT 1",
                    $types . 'i',
                    array_merge($values, [$id]),
                    'Non sono riuscito a salvare il prodotto.'
                )->close();

                // Foto sostituite o tolte dalla galleria: i file vecchi se ne
                // vanno (quelle rimaste sono ancora in uso e restano).
                admin_media_cleanup($mysqli, [$existing['immagine'] ?? null, $existing['galleria'] ?? null], $adminId);

                admin_log($mysqli, $adminId, 'shop_update_product', null, ['product_id' => $id, 'slug' => $slug]);
                admin_ok(['message' => 'Prodotto salvato.', 'id' => $id, 'slug' => $slug]);
            }

            $columns[] = 'posizione';
            $values[] = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_prodotti WHERE vetrina_id = ?', 'i', [$vetrinaId]);
            $types .= 'i';

            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO shop_prodotti (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $types,
                $values,
                'Non sono riuscito a creare il prodotto.'
            );
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'shop_create_product', null, ['product_id' => $newId, 'slug' => $slug]);
            admin_ok(['message' => 'Prodotto creato.', 'id' => $newId, 'slug' => $slug]);

        case 'toggle_product':
            $product = catalog_product($mysqli, (int)($input['id'] ?? 0));
            $field = ($input['field'] ?? 'attivo') === 'in_evidenza' ? 'in_evidenza' : 'attivo';
            $value = admin_shop_bool($input, 'value');
            admin_shop_exec($mysqli, "UPDATE shop_prodotti SET `$field` = ? WHERE id = ? LIMIT 1", 'ii', [$value, (int)$product['id']], 'Aggiornamento non riuscito.')->close();
            admin_log($mysqli, $adminId, 'shop_toggle_product', null, ['product_id' => (int)$product['id'], $field => $value]);
            admin_ok(['message' => 'Prodotto aggiornato.']);

        case 'duplicate_product':
            // La copia nasce spenta e subito dopo l'originale: si sistema e
            // si accende quando e' pronta.
            $product = catalog_product($mysqli, (int)($input['id'] ?? 0));
            $slug = catalog_free_product_slug($mysqli, (string)$product['slug'] . '-copia');

            $copy = $product;
            unset($copy['id'], $copy['created_at'], $copy['updated_at']);
            $copy['slug'] = $slug;
            $copy['attivo'] = 0;
            $copy['ordini_finti'] = 0;
            $copy['posizione'] = (int)$product['posizione'] + 5;

            $columns = array_keys($copy);
            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO shop_prodotti (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                str_repeat('s', count($columns)),
                array_map(static fn($v) => $v === null ? null : (string)$v, array_values($copy)),
                'Non sono riuscito a duplicare il prodotto.'
            );
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'shop_duplicate_product', null, ['from' => (int)$product['id'], 'product_id' => $newId]);
            admin_ok(['message' => 'Copia creata (spenta): modificala e accendila.', 'id' => $newId]);

        case 'delete_product':
            $product = catalog_product($mysqli, (int)($input['id'] ?? 0));
            admin_shop_exec($mysqli, 'DELETE FROM shop_prodotti WHERE id = ? LIMIT 1', 'i', [(int)$product['id']], 'Eliminazione non riuscita.')->close();
            // Una copia duplicata usa le stesse foto: in quel caso restano.
            admin_media_cleanup($mysqli, [$product['immagine'] ?? null, $product['galleria'] ?? null], $adminId);
            admin_log($mysqli, $adminId, 'shop_delete_product', null, ['slug' => $product['slug'], 'nome' => $product['nome']]);
            admin_ok(['message' => 'Prodotto eliminato.']);

        case 'reorder_products':
            admin_shop_reorder($mysqli, 'shop_prodotti', 'posizione', admin_shop_ids($input));
            admin_log($mysqli, $adminId, 'shop_reorder_products');
            admin_ok(['message' => 'Ordine aggiornato.']);
    }

    admin_fail('Azione non riconosciuta.', 400);
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->rollback();
    }
    error_log('[admin shop_catalog] ' . $e->getMessage());
    admin_fail('Errore del server sul catalogo.', 500);
}
