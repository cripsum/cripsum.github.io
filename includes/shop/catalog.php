<?php

/**
 * Lettura del catalogo finto: Negozio e collezioni del Merch.
 *
 * Tutto quello che esce da qui e' gia' pronto per la pagina: testi nella
 * lingua giusta, immagini con il percorso assoluto, prezzi come numeri.
 * Le pagine non toccano mai le righe grezze del database.
 */

require_once __DIR__ . '/shop_common.php';

const SHOP_CATALOG_TABLES = ['shop_vetrine', 'shop_categorie', 'shop_prodotti', 'shop_faq'];

function shop_catalog_ready(?mysqli $mysqli): bool
{
    return shop_tables_ready($mysqli, SHOP_CATALOG_TABLES);
}

/**
 * Lo stato che conta davvero: una collezione "in arrivo" con la data di
 * lancio gia' passata e' semplicemente aperta, senza che nessuno debba
 * ricordarsi di cambiarla dal pannello il giorno del drop.
 */
function shop_vetrina_state(array $row): string
{
    $state = (string)($row['stato'] ?? 'attiva');

    if ($state === 'in_arrivo' && !empty($row['lancio_at'])) {
        $launch = strtotime((string)$row['lancio_at']);
        if ($launch !== false && $launch <= time()) {
            return 'attiva';
        }
    }

    return in_array($state, ['attiva', 'nascosta', 'in_arrivo'], true) ? $state : 'attiva';
}

function shop_vetrina_view(array $row, string $lang): array
{
    $state = shop_vetrina_state($row);

    return [
        'id' => (int)$row['id'],
        'tipo' => (string)$row['tipo'],
        'slug' => (string)$row['slug'],
        'name' => shop_pick($row, 'nome', $lang),
        'title' => shop_pick($row, 'titolo', $lang) ?: shop_pick($row, 'nome', $lang),
        'subtitle' => shop_pick($row, 'sottotitolo', $lang),
        'emoji' => trim((string)($row['emoji'] ?? '')),
        'logo' => shop_asset_url($row['logo'] ?? ''),
        'cover' => shop_asset_url($row['copertina'] ?? ''),
        'accent' => shop_hex($row['colore_accento'] ?? null, '#6d5dfc'),
        'style' => shop_theme_style($row),
        'state' => $state,
        'launch_at' => $state === 'in_arrivo' && !empty($row['lancio_at']) ? (string)$row['lancio_at'] : null,
        'is_new' => $state === 'attiva' && shop_is_recent($row['created_at'] ?? null, 30),
        'url' => $row['tipo'] === 'merch' ? '/' . $lang . '/merch/' . rawurlencode((string)$row['slug']) : '/' . $lang . '/negozio',
    ];
}

/**
 * I colori del Negozio. Li usano anche le pagine del Merch che non sono di
 * una collezione (l'elenco, gli errori): i colori propri li hanno solo le
 * collezioni. Vuoto se il catalogo non c'e', e restano quelli di base.
 */
function shop_store_style(?mysqli $mysqli, string $lang): string
{
    if (!shop_catalog_ready($mysqli)) {
        return '';
    }

    $store = shop_vetrina($mysqli, 'negozio', 'negozio', $lang);
    return $store['style'] ?? '';
}

function shop_vetrina(mysqli $mysqli, string $tipo, string $slug, string $lang): ?array
{
    $row = shop_fetch_one(
        $mysqli,
        'SELECT * FROM shop_vetrine WHERE tipo = ? AND slug = ? LIMIT 1',
        'ss',
        [$tipo, $slug]
    );

    return $row ? shop_vetrina_view($row, $lang) : null;
}

/**
 * Le collezioni del Merch che il pubblico puo' vedere (aperte e in arrivo),
 * con quanti prodotti hanno e le prime tre immagini: servono alla card
 * dell'elenco quando la collezione non ha una copertina sua.
 */
function shop_merch_collections(mysqli $mysqli, string $lang): array
{
    $rows = shop_fetch_all(
        $mysqli,
        "SELECT * FROM shop_vetrine WHERE tipo = 'merch' AND stato <> 'nascosta' ORDER BY posizione ASC, id ASC"
    );

    $collections = [];
    foreach ($rows as $row) {
        $view = shop_vetrina_view($row, $lang);
        if ($view['state'] === 'nascosta') {
            continue;
        }

        // Di un drop non ancora uscito non si mostra niente: ne' quanti
        // prodotti ha, ne' le loro foto.
        if ($view['state'] === 'in_arrivo') {
            $view['count'] = 0;
            $view['preview'] = [];
            $collections[] = $view;
            continue;
        }

        $stats = shop_fetch_one(
            $mysqli,
            'SELECT COUNT(*) AS n FROM shop_prodotti WHERE vetrina_id = ? AND attivo = 1',
            'i',
            [$view['id']]
        );
        $images = shop_fetch_all(
            $mysqli,
            "SELECT immagine FROM shop_prodotti
             WHERE vetrina_id = ? AND attivo = 1 AND immagine IS NOT NULL AND immagine <> ''
             ORDER BY in_evidenza DESC, posizione ASC, id ASC LIMIT 3",
            'i',
            [$view['id']]
        );

        $view['count'] = (int)($stats['n'] ?? 0);
        $view['preview'] = array_map(static fn(array $r): string => shop_asset_url($r['immagine']), $images);
        $collections[] = $view;
    }

    return $collections;
}

function shop_categories(mysqli $mysqli, string $tipo, string $lang): array
{
    $rows = shop_fetch_all(
        $mysqli,
        'SELECT id, slug, nome, nome_en FROM shop_categorie WHERE tipo = ? ORDER BY posizione ASC, id ASC',
        's',
        [$tipo]
    );

    $categories = [];
    foreach ($rows as $row) {
        $categories[(int)$row['id']] = [
            'id' => (int)$row['id'],
            'slug' => (string)$row['slug'],
            'name' => shop_pick($row, 'nome', $lang),
        ];
    }

    return $categories;
}

/**
 * Indirizzo della pagina di un prodotto: /it/negozio/rtx-4090 per il Negozio,
 * /it/merch/simonetussi/felpa-big-logo per una collezione del Merch.
 */
function shop_product_url(string $lang, string $tipo, string $vetrinaSlug, string $productSlug): string
{
    if ($tipo === 'merch') {
        return '/' . $lang . '/merch/' . rawurlencode($vetrinaSlug) . '/' . rawurlencode($productSlug);
    }

    return '/' . $lang . '/negozio/' . rawurlencode($productSlug);
}

/**
 * Le immagini della galleria, gia' con il percorso giusto. La prima e'
 * sempre l'immagine principale del prodotto, senza doppioni.
 */
function shop_product_gallery(array $row): array
{
    $images = [];
    $main = shop_asset_url($row['immagine'] ?? '');
    if ($main !== '') {
        $images[] = $main;
    }

    $extra = json_decode((string)($row['galleria'] ?? ''), true);
    if (is_array($extra)) {
        foreach ($extra as $path) {
            $url = is_string($path) ? shop_asset_url($path) : '';
            if ($url !== '' && !in_array($url, $images, true)) {
                $images[] = $url;
            }
        }
    }

    return array_slice($images, 0, 12);
}

function shop_product_view(array $row, string $lang, array $categories = [], ?array $vetrina = null): array
{
    $price = (float)$row['prezzo'];
    $full = $row['prezzo_pieno'] !== null ? (float)$row['prezzo_pieno'] : null;
    $categoryId = $row['categoria_id'] !== null ? (int)$row['categoria_id'] : null;
    $category = $categoryId !== null && isset($categories[$categoryId]) ? $categories[$categoryId] : null;
    $sizes = array_values(array_filter(array_map('trim', explode(',', (string)($row['taglie'] ?? ''))), 'strlen'));
    $slug = (string)$row['slug'];

    return [
        'id' => (int)$row['id'],
        'slug' => $slug,
        'vetrina_id' => (int)$row['vetrina_id'],
        'name' => shop_pick($row, 'nome', $lang),
        'variant' => shop_pick($row, 'variante', $lang),
        'description' => shop_pick($row, 'descrizione', $lang),
        // Le colonne dei dettagli arrivano con la migrazione
        // 2026_09_23b: prima di applicarla la pagina mostra quello che c'e'.
        'long_description' => shop_pick($row, 'descrizione_lunga', $lang),
        'specs' => shop_localized_pairs($row['specifiche'] ?? null, $lang),
        'gallery' => shop_product_gallery($row),
        'badge' => shop_pick($row, 'badge', $lang),
        'price' => $price,
        'price_label' => shop_price($price, $lang),
        // Il prezzo pieno ha senso solo se e' piu' alto di quello scontato.
        'full_price' => $full !== null && $full > $price ? $full : null,
        'full_price_label' => $full !== null && $full > $price ? shop_price($full, $lang) : '',
        'image' => shop_asset_url($row['immagine'] ?? ''),
        'sizes' => $sizes,
        'featured' => (int)$row['in_evidenza'] === 1,
        'active' => (int)$row['attivo'] === 1,
        'orders' => (int)$row['ordini_finti'],
        'position' => (int)$row['posizione'],
        'created' => strtotime((string)$row['created_at']) ?: 0,
        'category' => $category['slug'] ?? '',
        'category_name' => $category['name'] ?? '',
        'url' => $vetrina ? shop_product_url($lang, $vetrina['tipo'], $vetrina['slug'], $slug) : '',
    ];
}

function shop_products(mysqli $mysqli, array $vetrina, string $lang, array $categories): array
{
    $rows = shop_fetch_all(
        $mysqli,
        'SELECT * FROM shop_prodotti WHERE vetrina_id = ? AND attivo = 1 ORDER BY posizione ASC, id ASC',
        'i',
        [$vetrina['id']]
    );

    return array_map(static fn(array $row): array => shop_product_view($row, $lang, $categories, $vetrina), $rows);
}

/**
 * Un prodotto con la sua vetrina, per la pagina del prodotto.
 *
 * Il pubblico vede solo prodotti accesi di vetrine aperte; lo staff vede
 * anche quelli spenti o di collezioni non ancora uscite, per provarli
 * ('preview' => true).
 */
function shop_product_page(mysqli $mysqli, string $slug, string $lang, bool $isStaff): ?array
{
    $row = shop_fetch_one($mysqli, 'SELECT * FROM shop_prodotti WHERE slug = ? LIMIT 1', 's', [$slug]);
    if (!$row) {
        return null;
    }

    $vetrinaRow = shop_fetch_one($mysqli, 'SELECT * FROM shop_vetrine WHERE id = ? LIMIT 1', 'i', [(int)$row['vetrina_id']]);
    if (!$vetrinaRow) {
        return null;
    }

    $vetrina = shop_vetrina_view($vetrinaRow, $lang);
    $public = (int)$row['attivo'] === 1 && $vetrina['state'] === 'attiva';

    if (!$public && !$isStaff) {
        return null;
    }

    $categories = shop_categories($mysqli, $vetrina['tipo'], $lang);

    return [
        'product' => shop_product_view($row, $lang, $categories, $vetrina),
        'vetrina' => $vetrina,
        'categories' => $categories,
        'preview' => !$public,
    ];
}

/**
 * Altri prodotti della stessa vetrina da proporre in fondo alla pagina:
 * prima quelli della stessa categoria, poi gli altri in evidenza.
 */
function shop_related_products(mysqli $mysqli, array $product, array $vetrina, array $categories, string $lang, int $limit = 4): array
{
    $all = shop_products($mysqli, $vetrina, $lang, $categories);
    $others = array_values(array_filter($all, static fn(array $p): bool => $p['id'] !== $product['id']));

    usort($others, static function (array $a, array $b) use ($product): int {
        $sameA = $product['category'] !== '' && $a['category'] === $product['category'];
        $sameB = $product['category'] !== '' && $b['category'] === $product['category'];
        return ($sameB <=> $sameA) ?: ($b['featured'] <=> $a['featured']) ?: ($a['position'] <=> $b['position']);
    });

    return array_slice($others, 0, $limit);
}

/**
 * Un prodotto per il checkout finto, insieme alla vetrina a cui appartiene
 * (serve al tema e a sapere se l'acquisto vale l'achievement del merch).
 * Si trovano solo prodotti attivi di vetrine visibili.
 */
function shop_product_for_checkout(mysqli $mysqli, string $slug, string $lang): ?array
{
    $row = shop_fetch_one($mysqli, 'SELECT * FROM shop_prodotti WHERE slug = ? AND attivo = 1 LIMIT 1', 's', [$slug]);
    if (!$row) {
        return null;
    }

    $vetrinaRow = shop_fetch_one($mysqli, 'SELECT * FROM shop_vetrine WHERE id = ? LIMIT 1', 'i', [(int)$row['vetrina_id']]);
    if (!$vetrinaRow) {
        return null;
    }

    $vetrina = shop_vetrina_view($vetrinaRow, $lang);
    if ($vetrina['state'] !== 'attiva') {
        return null;
    }

    return [
        'product' => shop_product_view($row, $lang, shop_categories($mysqli, $vetrina['tipo'], $lang), $vetrina),
        'vetrina' => $vetrina,
    ];
}

/**
 * Gli ultimi arrivi di tutte le collezioni aperte, per la fascia in fondo
 * all'elenco del Merch.
 */
function shop_latest_merch(mysqli $mysqli, string $lang, int $limit = 8): array
{
    $rows = shop_fetch_all(
        $mysqli,
        "SELECT p.*, v.slug AS vetrina_slug, v.nome AS vetrina_nome, v.nome_en AS vetrina_nome_en,
                v.stato AS vetrina_stato, v.lancio_at AS vetrina_lancio
         FROM shop_prodotti p
         JOIN shop_vetrine v ON v.id = p.vetrina_id
         WHERE v.tipo = 'merch' AND p.attivo = 1 AND v.stato <> 'nascosta'
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 40"
    );

    $products = [];
    foreach ($rows as $row) {
        if (shop_vetrina_state(['stato' => $row['vetrina_stato'], 'lancio_at' => $row['vetrina_lancio']]) !== 'attiva') {
            continue;
        }

        $view = shop_product_view($row, $lang);
        $view['collection'] = shop_pick(['nome' => $row['vetrina_nome'], 'nome_en' => $row['vetrina_nome_en']], 'nome', $lang);
        $view['url'] = shop_product_url($lang, 'merch', (string)$row['vetrina_slug'], $view['slug']);
        $products[] = $view;

        if (count($products) >= $limit) {
            break;
        }
    }

    return $products;
}

function shop_faq(mysqli $mysqli, string $pagina, ?int $vetrinaId, string $lang): array
{
    if ($vetrinaId !== null) {
        $rows = shop_fetch_all(
            $mysqli,
            'SELECT * FROM shop_faq WHERE vetrina_id = ? AND attiva = 1 ORDER BY posizione ASC, id ASC',
            'i',
            [$vetrinaId]
        );
    } else {
        $rows = shop_fetch_all(
            $mysqli,
            'SELECT * FROM shop_faq WHERE pagina = ? AND vetrina_id IS NULL AND attiva = 1 ORDER BY posizione ASC, id ASC',
            's',
            [$pagina]
        );
    }

    $faq = [];
    foreach ($rows as $row) {
        $question = shop_pick($row, 'domanda', $lang);
        $answer = shop_pick($row, 'risposta', $lang);
        if ($question !== '' && $answer !== '') {
            $faq[] = ['q' => $question, 'a' => $answer];
        }
    }

    return $faq;
}

/**
 * Testata di una pagina che non e' una vetrina (Download, elenco del Merch).
 * Le chiavi assenti restano stringhe vuote, cosi' il template non deve
 * controllare niente.
 */
function shop_page_texts(?mysqli $mysqli, string $pagina, string $lang): array
{
    $texts = [
        'title' => '', 'subtitle' => '',
        'note_title' => '', 'note' => '', 'link_text' => '', 'link_url' => '',
    ];

    if (!$mysqli instanceof mysqli || !auth_table_exists($mysqli, 'shop_pagine')) {
        return $texts;
    }

    $row = shop_fetch_one($mysqli, 'SELECT * FROM shop_pagine WHERE pagina = ? LIMIT 1', 's', [$pagina]);
    if (!$row) {
        return $texts;
    }

    $link = trim((string)($row['link_url'] ?? ''));

    return [
        'title' => shop_pick($row, 'titolo', $lang),
        'subtitle' => shop_pick($row, 'sottotitolo', $lang),
        'note_title' => shop_pick($row, 'nota_titolo', $lang),
        'note' => shop_pick($row, 'nota', $lang),
        'link_text' => shop_pick($row, 'link_testo', $lang),
        'link_url' => $link !== '' && shop_valid_link($link) ? shop_localize_link($link, $lang) : '',
    ];
}
