<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/gacha/banners.php';

/**
 * Categorie dei personaggi.
 *
 *   GET  ?action=list
 *   POST {action: save, id?, nome, nome_en, colore, icona, premio_godos, premio_badge_id}
 *   POST {action: delete, id}      solo se nessun personaggio la usa
 *   POST {action: reorder, order: []}
 *
 * La categoria di un personaggio resta il testo in personaggi.categoria (lo
 * leggono bot, profilo e gioco): rinominare una categoria qui aggiorna anche
 * i personaggi e i banner "per categoria" che la usano.
 */

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!gacha_schema($mysqli)['categorie']) {
    admin_ok([
        'ready' => false,
        'message' => 'Applica la migration migrations/2026_09_25_gacha_banner.sql per gestire le categorie.',
    ]);
}

function gc_admin_row(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM personaggi_categorie WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Categoria non trovata.', 404);
    }
    return $row;
}

try {
    if ($action === 'list') {
        $counts = [];
        foreach (admin_shop_rows($mysqli, 'SELECT LOWER(categoria) AS c, COUNT(*) AS n, SUM(limitato = 1) AS limitati FROM personaggi WHERE categoria IS NOT NULL GROUP BY LOWER(categoria)') as $row) {
            $counts[$row['c']] = ['n' => (int)$row['n'], 'limitati' => (int)$row['limitati']];
        }
        $categories = [];
        foreach (admin_shop_rows($mysqli, 'SELECT * FROM personaggi_categorie ORDER BY ordine ASC, nome ASC') as $row) {
            $key = mb_strtolower($row['nome'], 'UTF-8');
            $row['personaggi'] = $counts[$key]['n'] ?? 0;
            $row['limitati'] = $counts[$key]['limitati'] ?? 0;
            unset($counts[$key]);
            $categories[] = $row;
        }
        // Categorie scritte nei personaggi ma senza una riga qui (vecchi dati).
        $orphans = [];
        foreach ($counts as $name => $c) {
            $orphans[] = ['nome' => $name, 'personaggi' => $c['n']];
        }
        $badges = admin_table_exists($mysqli, 'custom_badges')
            ? admin_shop_rows($mysqli, 'SELECT id, name FROM custom_badges ORDER BY name ASC')
            : [];
        admin_ok(['ready' => true, 'categories' => $categories, 'orphans' => $orphans, 'badges' => $badges]);
    }

    admin_shop_require_post();
    $input = admin_input();

    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $existing = $id > 0 ? gc_admin_row($mysqli, $id) : null;

        $nome = trim((string)($input['nome'] ?? ''));
        if ($nome === '' || mb_strlen($nome) > 100) {
            admin_fail('Nome: obbligatorio, al massimo 100 caratteri.');
        }
        $nomeEn = trim((string)($input['nome_en'] ?? '')) ?: null;
        $slug = gacha_slugify($nome);
        $colore = preg_match('/^#[0-9a-f]{6}$/i', (string)($input['colore'] ?? '')) ? strtolower($input['colore']) : null;
        $icona = trim((string)($input['icona'] ?? ''));
        $icona = preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/', $icona) ? $icona : null;
        $premio = max(0, min(1000000, (int)($input['premio_godos'] ?? 0)));
        $badge = (int)($input['premio_badge_id'] ?? 0) ?: null;

        $mysqli->begin_transaction();
        try {
            if ($existing) {
                admin_shop_exec(
                    $mysqli,
                    'UPDATE personaggi_categorie SET nome = ?, nome_en = ?, slug = ?, colore = ?, icona = ?, premio_godos = ?, premio_badge_id = ? WHERE id = ?',
                    'sssssiii',
                    [$nome, $nomeEn, $slug, $colore, $icona, $premio, $badge, $id],
                    'Categoria non salvata.'
                )->close();

                if ($existing['nome'] !== $nome) {
                    admin_shop_exec($mysqli, 'UPDATE personaggi SET categoria = ? WHERE LOWER(categoria) = LOWER(?)', 'ss', [$nome, $existing['nome']], 'Personaggi non aggiornati.')->close();
                    admin_shop_exec($mysqli, 'UPDATE gacha_banner SET pool_categoria = ? WHERE LOWER(pool_categoria) = LOWER(?)', 'ss', [$nome, $existing['nome']], 'Banner non aggiornati.')->close();
                }
            } else {
                $next = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(ordine), 0) FROM personaggi_categorie');
                admin_shop_exec(
                    $mysqli,
                    'INSERT INTO personaggi_categorie (nome, nome_en, slug, colore, icona, premio_godos, premio_badge_id, ordine) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'sssssiii',
                    [$nome, $nomeEn, $slug, $colore, $icona, $premio, $badge, $next],
                    'Categoria non creata.'
                )->close();
                $id = (int)$mysqli->insert_id;
            }
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        admin_log($mysqli, $adminId, $existing ? 'update_character_category' : 'create_character_category', null, ['categoria_id' => $id, 'nome' => $nome]);
        admin_ok(['message' => 'Categoria salvata.', 'id' => $id]);
    }

    if ($action === 'delete') {
        $row = gc_admin_row($mysqli, (int)($input['id'] ?? 0));
        $used = admin_safe_count($mysqli, 'SELECT COUNT(*) AS total FROM personaggi WHERE LOWER(categoria) = LOWER(?)', 's', [$row['nome']]);
        if ($used > 0) {
            admin_fail("La usano ancora $used personaggi: spostali in un'altra categoria prima di eliminarla.");
        }
        admin_shop_exec($mysqli, 'DELETE FROM personaggi_categorie WHERE id = ?', 'i', [(int)$row['id']], 'Categoria non eliminata.')->close();
        admin_log($mysqli, $adminId, 'delete_character_category', null, ['categoria_id' => (int)$row['id'], 'nome' => $row['nome']]);
        admin_ok(['message' => 'Categoria eliminata.']);
    }

    if ($action === 'reorder') {
        admin_shop_reorder($mysqli, 'personaggi_categorie', 'ordine', admin_shop_ids($input));
        admin_ok(['message' => 'Ordine salvato.']);
    }

    admin_fail('Azione non valida.');
} catch (Throwable $e) {
    error_log('[admin gacha_categories] ' . $e->getMessage());
    admin_fail('Errore nel salvataggio della categoria.', 500);
}
