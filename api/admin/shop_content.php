<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';

/**
 * Testi delle pagine shop: FAQ (di una vetrina o di una pagina) e testata
 * delle pagine che non sono vetrine (Download, elenco del Merch).
 *
 * GET  ?action=faq&pagina=download            FAQ di una pagina
 * GET  ?action=faq&vetrina_id=3               FAQ di una vetrina
 * GET  ?action=page&pagina=download           testata di una pagina
 * POST action = save_faq | delete_faq | reorder_faq | save_page
 */

const SHOP_CONTENT_PAGES = ['download', 'merch'];

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!admin_table_exists($mysqli, 'shop_faq') || !admin_table_exists($mysqli, 'shop_pagine')) {
    admin_fail('Tabelle dello shop mancanti: applica la migrazione 2026_09_23_shop_catalog.sql.', 409);
}

function content_page_key(array $source): string
{
    $page = (string)($source['pagina'] ?? '');
    if (!in_array($page, SHOP_CONTENT_PAGES, true)) {
        admin_fail('Pagina non valida.');
    }
    return $page;
}

try {
    if ($action === 'faq') {
        $vetrinaId = (int)($_GET['vetrina_id'] ?? 0);
        if ($vetrinaId > 0) {
            $rows = admin_shop_rows($mysqli, 'SELECT * FROM shop_faq WHERE vetrina_id = ? ORDER BY posizione ASC, id ASC', 'i', [$vetrinaId]);
        } else {
            $page = content_page_key($_GET);
            $rows = admin_shop_rows($mysqli, 'SELECT * FROM shop_faq WHERE pagina = ? AND vetrina_id IS NULL ORDER BY posizione ASC, id ASC', 's', [$page]);
        }
        admin_ok(['faq' => $rows]);
    }

    if ($action === 'page') {
        $page = content_page_key($_GET);
        $row = admin_shop_row($mysqli, 'SELECT * FROM shop_pagine WHERE pagina = ? LIMIT 1', 's', [$page]);
        admin_ok(['page' => $row ?: ['pagina' => $page]]);
    }

    admin_shop_require_post();
    $input = admin_input();

    switch ($action) {
        case 'save_faq':
            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0 ? admin_shop_row($mysqli, 'SELECT * FROM shop_faq WHERE id = ? LIMIT 1', 'i', [$id]) : null;
            if ($id > 0 && !$existing) {
                admin_fail('Domanda non trovata.', 404);
            }

            $domanda = admin_shop_text($input, 'domanda', 'Domanda (IT)', 255, true);
            $domandaEn = admin_shop_text($input, 'domanda_en', 'Domanda (EN)', 255);
            $risposta = admin_shop_text($input, 'risposta', 'Risposta (IT)', 2000, true);
            $rispostaEn = admin_shop_text($input, 'risposta_en', 'Risposta (EN)', 2000);
            $attiva = admin_shop_bool($input, 'attiva');

            if ($existing) {
                admin_shop_exec(
                    $mysqli,
                    'UPDATE shop_faq SET domanda = ?, domanda_en = ?, risposta = ?, risposta_en = ?, attiva = ? WHERE id = ? LIMIT 1',
                    'ssssii',
                    [$domanda, $domandaEn, $risposta, $rispostaEn, $attiva, $id],
                    'Non sono riuscito a salvare la domanda.'
                )->close();
                admin_log($mysqli, $adminId, 'shop_update_faq', null, ['faq_id' => $id]);
                admin_ok(['message' => 'Domanda salvata.', 'id' => $id]);
            }

            $vetrinaId = (int)($input['vetrina_id'] ?? 0);
            if ($vetrinaId > 0) {
                if (!admin_shop_row($mysqli, 'SELECT id FROM shop_vetrine WHERE id = ? LIMIT 1', 'i', [$vetrinaId])) {
                    admin_fail('Vetrina non trovata.', 404);
                }
                $page = 'vetrina';
                $position = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_faq WHERE vetrina_id = ?', 'i', [$vetrinaId]);
            } else {
                $page = content_page_key($input);
                $vetrinaId = null;
                $position = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM shop_faq WHERE pagina = ? AND vetrina_id IS NULL', 's', [$page]);
            }

            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO shop_faq (pagina, vetrina_id, domanda, domanda_en, risposta, risposta_en, attiva, posizione) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                'sissssii',
                [$page, $vetrinaId, $domanda, $domandaEn, $risposta, $rispostaEn, $attiva, $position],
                'Non sono riuscito a creare la domanda.'
            );
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'shop_create_faq', null, ['faq_id' => $newId, 'pagina' => $page]);
            admin_ok(['message' => 'Domanda aggiunta.', 'id' => $newId]);

        case 'delete_faq':
            $id = (int)($input['id'] ?? 0);
            if (!admin_shop_row($mysqli, 'SELECT id FROM shop_faq WHERE id = ? LIMIT 1', 'i', [$id])) {
                admin_fail('Domanda non trovata.', 404);
            }
            admin_shop_exec($mysqli, 'DELETE FROM shop_faq WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            admin_log($mysqli, $adminId, 'shop_delete_faq', null, ['faq_id' => $id]);
            admin_ok(['message' => 'Domanda eliminata.']);

        case 'reorder_faq':
            admin_shop_reorder($mysqli, 'shop_faq', 'posizione', admin_shop_ids($input));
            admin_ok(['message' => 'Ordine aggiornato.']);

        case 'save_page':
            $page = content_page_key($input);
            $fields = [
                'kicker' => admin_shop_text($input, 'kicker', 'Etichetta (IT)', 80),
                'kicker_en' => admin_shop_text($input, 'kicker_en', 'Etichetta (EN)', 80),
                'titolo' => admin_shop_text($input, 'titolo', 'Titolo (IT)', 120, true),
                'titolo_en' => admin_shop_text($input, 'titolo_en', 'Titolo (EN)', 120),
                'sottotitolo' => admin_shop_text($input, 'sottotitolo', 'Sottotitolo (IT)', 400),
                'sottotitolo_en' => admin_shop_text($input, 'sottotitolo_en', 'Sottotitolo (EN)', 400),
                'nota_titolo' => admin_shop_text($input, 'nota_titolo', 'Titolo avviso (IT)', 120),
                'nota_titolo_en' => admin_shop_text($input, 'nota_titolo_en', 'Titolo avviso (EN)', 120),
                'nota' => admin_shop_text($input, 'nota', 'Avviso (IT)', 600),
                'nota_en' => admin_shop_text($input, 'nota_en', 'Avviso (EN)', 600),
                'link_testo' => admin_shop_text($input, 'link_testo', 'Testo bottone (IT)', 60),
                'link_testo_en' => admin_shop_text($input, 'link_testo_en', 'Testo bottone (EN)', 60),
                'link_url' => admin_shop_link($input, 'link_url', 'Link del bottone'),
            ];

            $columns = array_keys($fields);
            $placeholders = implode(', ', array_fill(0, count($columns) + 1, '?'));
            $updates = implode(', ', array_map(static fn($c) => "`$c` = VALUES(`$c`)", $columns));

            admin_shop_exec(
                $mysqli,
                'INSERT INTO shop_pagine (pagina, `' . implode('`, `', $columns) . "`) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates",
                str_repeat('s', count($columns) + 1),
                array_merge([$page], array_values($fields)),
                'Non sono riuscito a salvare i testi.'
            )->close();

            admin_log($mysqli, $adminId, 'shop_update_page', null, ['pagina' => $page]);
            admin_ok(['message' => 'Testi salvati.']);
    }

    admin_fail('Azione non riconosciuta.', 400);
} catch (Throwable $e) {
    error_log('[admin shop_content] ' . $e->getMessage());
    admin_fail('Errore del server sui testi dello shop.', 500);
}
