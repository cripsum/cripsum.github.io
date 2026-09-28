<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/edits/edits.php';

/**
 * Edits dal pannello (Pagine > Edits).
 *
 * GET  ?action=list                 edit, categorie e quanti hanno il video sparito
 * GET  ?action=streamable&code=xxx  proporzioni e copertina di un video Streamable
 * POST action = save_edit | set_state | reorder | delete_edit
 *             | recheck | hide_dead
 *             | save_category | delete_category | reorder_categories
 *
 * Streamable: api.streamable.com/videos/{codice} da' larghezza, altezza e
 * copertina. La copertina e' un link firmato che scade, quindi si scarica in
 * img/edits/. Chiamato tante volte di fila risponde vuoto: il ricontrollo
 * lo fa il pannello un edit alla volta.
 */

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!admin_table_exists($mysqli, 'edits') || !admin_table_exists($mysqli, 'edits_categorie')) {
    if ($action === 'list') {
        admin_ok(['ready' => false, 'message' => 'Tabelle degli edit mancanti: applica migrations/2026_09_28_chisiamo_edits.sql e ricarica.']);
    }
    admin_fail('Tabelle degli edit mancanti: applica la migrazione 2026_09_28_chisiamo_edits.sql.', 409);
}

/* ── Streamable ─────────────────────────────────────────────────────── */

function edits_admin_http_get(string $url, int $maxBytes, ?int &$status = null): ?string
{
    $status = 0;
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'Cripsum-Admin/1.0 (+https://cripsum.com)',
        CURLOPT_BUFFERSIZE => 65536,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => static fn($ch, $total, $done): int => $done > $maxBytes ? 1 : 0,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return is_string($body) && $status >= 200 && $status < 300 ? $body : null;
}

/**
 * Le informazioni di un video: ['state' => 'ok'|'dead'|'error', 'width',
 * 'height', 'thumbnail']. 'error' = Streamable non ha risposto: meglio non
 * decidere niente.
 */
function edits_admin_streamable_meta(string $code): array
{
    $status = 0;
    $raw = edits_admin_http_get('https://api.streamable.com/videos/' . rawurlencode($code), 512 * 1024, $status);

    if ($status === 404) {
        return ['state' => 'dead'];
    }

    $data = $raw !== null ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return ['state' => 'error'];
    }

    $file = $data['files']['mp4'] ?? $data['files']['mp4-mobile'] ?? [];
    $thumb = (string)($data['thumbnail_url'] ?? '');
    if (str_starts_with($thumb, '//')) {
        $thumb = 'https:' . $thumb;
    }

    return [
        'state' => 'ok',
        'width' => (int)($file['width'] ?? 0) ?: null,
        'height' => (int)($file['height'] ?? 0) ?: null,
        'thumbnail' => $thumb,
    ];
}

/**
 * Scarica la copertina di Streamable in img/edits/{codice}.jpg. Se c'e'
 * gia' la riusa. Null se non si riesce: l'edit resta con la GIF.
 */
function edits_admin_import_cover(string $code, string $thumbnail): ?string
{
    $dir = __DIR__ . '/../../img/edits';
    foreach (['jpg', 'png', 'webp'] as $ext) {
        if (is_file("$dir/$code.$ext")) {
            return "/img/edits/$code.$ext";
        }
    }

    $host = strtolower((string)parse_url($thumbnail, PHP_URL_HOST));
    if ($thumbnail === '' || !preg_match('~(^|\.)streamable\.com$~', $host)) {
        return null;
    }

    $raw = edits_admin_http_get($thumbnail, 4 * 1024 * 1024);
    $info = $raw !== null ? @getimagesizefromstring($raw) : false;
    $ext = match ($info['mime'] ?? '') {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    if ($raw === null || $ext === null) {
        return null;
    }

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return null;
    }
    if (file_put_contents("$dir/$code.$ext", $raw, LOCK_EX) === false) {
        return null;
    }

    return "/img/edits/$code.$ext";
}

/* ── Pezzi comuni ───────────────────────────────────────────────────── */

function edits_admin_row(mysqli $mysqli, int $id): array
{
    $row = admin_shop_row($mysqli, 'SELECT * FROM edits WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) {
        admin_fail('Edit non trovato.', 404);
    }

    return $row;
}

function edits_admin_dimension(array $input, string $key): ?int
{
    $raw = trim((string)($input[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^\d{1,5}$/', $raw) || (int)$raw < 1 || (int)$raw > 10000) {
        admin_fail('Proporzioni del video non valide: ricarica il file.');
    }

    return (int)$raw;
}

/** Dove e' stato pubblicato l'edit: solo un indirizzo https (TikTok, YouTube...). */
function edits_admin_post_link(array $input): ?string
{
    $value = trim((string)($input['link_post'] ?? ''));
    if ($value === '') {
        return null;
    }
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }
    if (mb_strlen($value) > 255 || edits_post_link($value) === null) {
        admin_fail('Link del post: serve un indirizzo https completo, come https://www.tiktok.com/@cripsum/video/...');
    }

    return $value;
}

/** I tipi per bind_param, dai nomi delle colonne: interi questi, testo il resto. */
function edits_admin_types(array $fields): string
{
    $ints = ['categoria_id', 'streamable_ok', 'larghezza', 'altezza', 'in_evidenza', 'posizione'];

    return implode('', array_map(static fn(string $c): string => in_array($c, $ints, true) ? 'i' : 's', array_keys($fields)));
}

function edits_admin_icon(array $input): string
{
    $icon = trim((string)($input['icona'] ?? ''));
    if ($icon === '') {
        return 'fa-solid fa-film';
    }
    if (!preg_match('/^fa-(?:solid|regular|brands) fa-[a-z0-9-]{1,40}$/', $icon)) {
        admin_fail('Icona: scegline una dall\'elenco.');
    }

    return $icon;
}

try {
    if ($action === 'list') {
        $rows = admin_shop_rows(
            $mysqli,
            'SELECT e.*, c.nome AS categoria_nome, c.icona AS categoria_icona
             FROM edits e
             LEFT JOIN edits_categorie c ON c.id = e.categoria_id
             ORDER BY e.posizione ASC, e.id DESC'
        );
        foreach ($rows as &$row) {
            $row['copertina_url'] = shop_asset_url($row['copertina'] ?? '');
            $row['gif_url'] = shop_asset_url($row['gif_presence'] ?? '');
            $row['video_morto'] = ($row['video'] ?? '') === '' && (string)$row['streamable_ok'] === '0' ? 1 : 0;
        }
        unset($row);

        $categories = admin_shop_rows(
            $mysqli,
            'SELECT c.*, (SELECT COUNT(*) FROM edits e WHERE e.categoria_id = c.id) AS edit_count
             FROM edits_categorie c
             ORDER BY c.posizione ASC, c.id ASC'
        );

        admin_ok([
            'ready' => true,
            'edits' => $rows,
            'categories' => $categories,
            'dead' => count(array_filter($rows, static fn(array $r): bool => (int)$r['video_morto'] === 1)),
            'dead_visible' => count(array_filter($rows, static fn(array $r): bool => (int)$r['video_morto'] === 1 && $r['stato'] === 'pubblicato')),
        ]);
    }

    if ($action === 'streamable') {
        $code = edits_streamable_code((string)($_GET['code'] ?? ''));
        if ($code === null) {
            admin_fail('Link Streamable non valido: incolla un indirizzo come https://streamable.com/abc123.');
        }
        $meta = edits_admin_streamable_meta($code);
        if ($meta['state'] === 'dead') {
            admin_fail('Streamable non ha questo video (404): controlla il link.', 404);
        }
        if ($meta['state'] !== 'ok') {
            admin_fail('Streamable non risponde: riprova tra qualche secondo.', 502);
        }
        $cover = edits_admin_import_cover($code, $meta['thumbnail']);
        admin_ok(['code' => $code, 'width' => $meta['width'], 'height' => $meta['height'], 'cover' => $cover]);
    }

    admin_shop_require_post();
    $input = admin_input();

    switch ($action) {
        case 'save_edit':
            // Serie, descrizione e link del post sono arrivati dopo la prima
            // versione della migrazione: chi l'aveva gia' applicata la rilancia.
            if (!admin_column_exists($mysqli, 'edits', 'serie') || !admin_column_exists($mysqli, 'edits', 'link_post')) {
                admin_fail('Applica di nuovo migrations/2026_09_28_chisiamo_edits.sql: aggiunge serie, descrizione e link del post.', 409);
            }

            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0 ? edits_admin_row($mysqli, $id) : null;

            $categoryId = (int)($input['categoria_id'] ?? 0);
            if (!admin_shop_row($mysqli, 'SELECT id FROM edits_categorie WHERE id = ? LIMIT 1', 'i', [$categoryId])) {
                admin_fail('Categoria: scegline una.');
            }

            $video = trim((string)($input['video'] ?? ''));
            if ($video !== '') {
                $relative = admin_media_video_relative($video);
                if ($relative === null) {
                    admin_fail('Video: caricalo dal pannello (finisce in vid/edits/).');
                }
                $video = '/vid/' . $relative;
            }

            $streamableRaw = trim((string)($input['streamable'] ?? ''));
            $code = $streamableRaw !== '' ? edits_streamable_code($streamableRaw) : null;
            if ($streamableRaw !== '' && $code === null) {
                admin_fail('Link Streamable non valido: incolla un indirizzo come https://streamable.com/abc123.');
            }
            if ($video === '' && $code === null) {
                admin_fail('Video: carica il file o incolla il link di Streamable.');
            }

            $fields = [
                'titolo' => admin_shop_text($input, 'titolo', 'Titolo (IT)', 120, true),
                'titolo_en' => admin_shop_text($input, 'titolo_en', 'Titolo (EN)', 120),
                'serie' => admin_shop_text($input, 'serie', 'Gioco, anime o serie', 120),
                'descrizione' => admin_shop_text($input, 'descrizione', 'Descrizione (IT)', 2000),
                'descrizione_en' => admin_shop_text($input, 'descrizione_en', 'Descrizione (EN)', 2000),
                'categoria_id' => $categoryId,
                'musica' => admin_shop_text($input, 'musica', 'Musica', 160),
                'video' => $video !== '' ? $video : null,
                'streamable' => $code,
                'streamable_ok' => $existing['streamable_ok'] ?? null,
                'larghezza' => edits_admin_dimension($input, 'larghezza'),
                'altezza' => edits_admin_dimension($input, 'altezza'),
                'copertina' => admin_shop_image($input, 'copertina', 'Copertina'),
                'gif_presence' => admin_shop_image($input, 'gif_presence', 'GIF per la rich presence'),
                'collab_nome' => admin_shop_text($input, 'collab_nome', 'Collab con', 60),
                'collab_link' => admin_shop_link($input, 'collab_link', 'Link della collab'),
                'link_post' => edits_admin_post_link($input),
                'etichetta' => admin_shop_text($input, 'etichetta', 'Etichetta (IT)', 30),
                'etichetta_en' => admin_shop_text($input, 'etichetta_en', 'Etichetta (EN)', 30),
                'in_evidenza' => admin_shop_bool($input, 'in_evidenza'),
                'stato' => admin_shop_enum($input, 'stato', 'Stato', ['pubblicato', 'nascosto'], 'pubblicato'),
            ];

            // Un embed nuovo (o mai controllato) si chiede a Streamable: se
            // esiste, da dove prendere proporzioni e copertina.
            $note = '';
            if ($code !== null && $video === '') {
                $changed = !$existing || ($existing['streamable'] ?? '') !== $code;
                if ($changed || $existing['streamable_ok'] === null || $fields['larghezza'] === null) {
                    $meta = edits_admin_streamable_meta($code);
                    if ($meta['state'] === 'ok') {
                        $fields['streamable_ok'] = 1;
                        $fields['larghezza'] = $meta['width'] ?? $fields['larghezza'];
                        $fields['altezza'] = $meta['height'] ?? $fields['altezza'];
                        if ($fields['copertina'] === null) {
                            $fields['copertina'] = edits_admin_import_cover($code, $meta['thumbnail']);
                        }
                    } elseif ($meta['state'] === 'dead') {
                        $fields['streamable_ok'] = 0;
                        $note = ' Attenzione: Streamable non ha questo video.';
                    } else {
                        $fields['streamable_ok'] = $changed ? null : $fields['streamable_ok'];
                        $note = ' Streamable non ha risposto: proporzioni e copertina le prendo al prossimo ricontrollo.';
                    }
                }
            }

            $publishedAt = $existing['pubblicato_at'] ?? null;
            if ($fields['stato'] === 'pubblicato' && $publishedAt === null && (!$existing || $existing['stato'] === 'nascosto')) {
                $publishedAt = date('Y-m-d H:i:s');
            }
            $fields['pubblicato_at'] = $publishedAt;

            $types = edits_admin_types($fields);

            $mysqli->begin_transaction();
            if ($existing) {
                $sets = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($fields)));
                admin_shop_exec($mysqli, "UPDATE edits SET $sets WHERE id = ? LIMIT 1", $types . 'i', [...array_values($fields), $id], 'Salvataggio non riuscito.')->close();
            } else {
                // Un edit nuovo va in cima: e' il piu' recente.
                $top = admin_shop_row($mysqli, 'SELECT MIN(posizione) AS p FROM edits');
                $fields['posizione'] = (int)($top['p'] ?? 10) - 10;
                $columns = '`' . implode('`, `', array_keys($fields)) . '`';
                $marks = implode(', ', array_fill(0, count($fields), '?'));
                $stmt = admin_shop_exec($mysqli, "INSERT INTO edits ($columns) VALUES ($marks)", edits_admin_types($fields), array_values($fields), 'Creazione non riuscita.');
                $id = (int)$stmt->insert_id;
                $stmt->close();
            }
            if ($fields['in_evidenza'] === 1) {
                admin_shop_exec($mysqli, 'UPDATE edits SET in_evidenza = 0 WHERE id <> ?', 'i', [$id], 'Salvataggio non riuscito.')->close();
            }
            $mysqli->commit();

            if ($existing) {
                admin_media_cleanup($mysqli, [$existing['video'], $existing['copertina'], $existing['gif_presence']], $adminId);
            }
            admin_log($mysqli, $adminId, $existing ? 'edits_update' : 'edits_create', null, ['edit_id' => $id, 'titolo' => $fields['titolo']]);
            admin_ok(['message' => ($existing ? 'Edit salvato.' : 'Edit pubblicato.') . $note, 'id' => $id]);

        case 'set_state':
            $id = (int)($input['id'] ?? 0);
            $row = edits_admin_row($mysqli, $id);
            $stato = admin_shop_enum($input, 'stato', 'Stato', ['pubblicato', 'nascosto'], 'pubblicato');
            $publishedAt = $row['pubblicato_at'] ?? null;
            if ($stato === 'pubblicato' && $publishedAt === null && $row['stato'] === 'nascosto') {
                $publishedAt = date('Y-m-d H:i:s');
            }
            admin_shop_exec($mysqli, 'UPDATE edits SET stato = ?, pubblicato_at = ? WHERE id = ? LIMIT 1', 'ssi', [$stato, $publishedAt, $id], 'Aggiornamento non riuscito.')->close();
            admin_log($mysqli, $adminId, 'edits_state', null, ['edit_id' => $id, 'stato' => $stato]);
            admin_ok(['message' => $stato === 'pubblicato' ? 'Edit pubblicato.' : 'Edit nascosto.']);

        case 'reorder':
            $ids = admin_shop_ids($input);
            $own = array_map('intval', array_column(admin_shop_rows($mysqli, 'SELECT id FROM edits'), 'id'));
            if (array_diff($ids, $own)) {
                admin_fail('Ordine non valido: ricarica la pagina.');
            }
            admin_shop_reorder($mysqli, 'edits', 'posizione', $ids);
            admin_log($mysqli, $adminId, 'edits_reorder');
            admin_ok(['message' => 'Ordine aggiornato.']);

        case 'delete_edit':
            $id = (int)($input['id'] ?? 0);
            $row = edits_admin_row($mysqli, $id);
            admin_shop_exec($mysqli, 'DELETE FROM edits WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            admin_media_cleanup($mysqli, [$row['video'], $row['copertina'], $row['gif_presence']], $adminId);
            admin_log($mysqli, $adminId, 'edits_delete', null, ['edit_id' => $id, 'titolo' => $row['titolo']]);
            admin_ok(['message' => 'Edit eliminato.']);

        case 'recheck':
            $id = (int)($input['id'] ?? 0);
            $row = edits_admin_row($mysqli, $id);
            $code = edits_streamable_code((string)($row['streamable'] ?? ''));
            if ($code === null) {
                admin_ok(['state' => 'skip']);
            }

            $meta = edits_admin_streamable_meta($code);
            if ($meta['state'] === 'error') {
                admin_ok(['state' => 'error']);
            }

            if ($meta['state'] === 'dead') {
                admin_shop_exec($mysqli, 'UPDATE edits SET streamable_ok = 0 WHERE id = ? LIMIT 1', 'i', [$id], 'Aggiornamento non riuscito.')->close();
                admin_ok(['state' => 'dead']);
            }

            $cover = $row['copertina'] ?: edits_admin_import_cover($code, $meta['thumbnail']);
            $hasFile = ($row['video'] ?? '') !== '';
            admin_shop_exec(
                $mysqli,
                'UPDATE edits SET streamable_ok = 1, larghezza = ?, altezza = ?, copertina = ? WHERE id = ? LIMIT 1',
                'iisi',
                [
                    $hasFile ? $row['larghezza'] : ($meta['width'] ?? $row['larghezza']),
                    $hasFile ? $row['altezza'] : ($meta['height'] ?? $row['altezza']),
                    $cover,
                    $id,
                ],
                'Aggiornamento non riuscito.'
            )->close();
            admin_ok(['state' => 'ok', 'cover' => $cover]);

        case 'hide_dead':
            $stmt = admin_shop_exec(
                $mysqli,
                "UPDATE edits SET stato = 'nascosto' WHERE (video IS NULL OR video = '') AND streamable_ok = 0 AND stato = 'pubblicato'",
                '',
                [],
                'Aggiornamento non riuscito.'
            );
            $count = $stmt->affected_rows;
            $stmt->close();
            admin_log($mysqli, $adminId, 'edits_hide_dead', null, ['nascosti' => $count]);
            admin_ok(['message' => $count === 1 ? '1 edit nascosto.' : $count . ' edit nascosti.']);

        case 'save_category':
            $id = (int)($input['id'] ?? 0);
            if ($id > 0 && !admin_shop_row($mysqli, 'SELECT id FROM edits_categorie WHERE id = ? LIMIT 1', 'i', [$id])) {
                admin_fail('Categoria non trovata.', 404);
            }
            $name = admin_shop_text($input, 'nome', 'Nome (IT)', 40, true);
            $fields = [
                'nome' => $name,
                'nome_en' => admin_shop_text($input, 'nome_en', 'Nome (EN)', 40),
                'icona' => edits_admin_icon($input),
                'slug' => admin_shop_slug($input, 'slug', (string)$name, 40),
            ];
            if ($id > 0) {
                admin_shop_exec($mysqli, 'UPDATE edits_categorie SET nome = ?, nome_en = ?, icona = ?, slug = ? WHERE id = ? LIMIT 1', 'ssssi', [...array_values($fields), $id], 'Salvataggio non riuscito.')->close();
            } else {
                $position = admin_shop_next_position($mysqli, 'SELECT MAX(posizione) FROM edits_categorie');
                $stmt = admin_shop_exec($mysqli, 'INSERT INTO edits_categorie (nome, nome_en, icona, slug, posizione) VALUES (?, ?, ?, ?, ?)', 'ssssi', [...array_values($fields), $position], 'Creazione non riuscita.');
                $id = (int)$stmt->insert_id;
                $stmt->close();
            }
            admin_log($mysqli, $adminId, 'edits_save_category', null, ['category_id' => $id, 'nome' => $name]);
            admin_ok(['message' => 'Categoria salvata.', 'id' => $id]);

        case 'delete_category':
            $id = (int)($input['id'] ?? 0);
            $category = admin_shop_row($mysqli, 'SELECT * FROM edits_categorie WHERE id = ? LIMIT 1', 'i', [$id]);
            if (!$category) {
                admin_fail('Categoria non trovata.', 404);
            }
            $used = admin_shop_row($mysqli, 'SELECT COUNT(*) AS n FROM edits WHERE categoria_id = ?', 'i', [$id]);
            if ((int)($used['n'] ?? 0) > 0) {
                admin_fail('La categoria ha ancora ' . (int)$used['n'] . ' edit: spostali in un\'altra categoria prima di eliminarla.');
            }
            admin_shop_exec($mysqli, 'DELETE FROM edits_categorie WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            admin_log($mysqli, $adminId, 'edits_delete_category', null, ['nome' => $category['nome']]);
            admin_ok(['message' => 'Categoria eliminata.']);

        case 'reorder_categories':
            $ids = admin_shop_ids($input);
            $own = array_map('intval', array_column(admin_shop_rows($mysqli, 'SELECT id FROM edits_categorie'), 'id'));
            if (array_diff($ids, $own)) {
                admin_fail('Ordine non valido: ricarica la pagina.');
            }
            admin_shop_reorder($mysqli, 'edits_categorie', 'posizione', $ids);
            admin_log($mysqli, $adminId, 'edits_reorder_categories');
            admin_ok(['message' => 'Ordine aggiornato.']);
    }

    admin_fail('Azione non riconosciuta.', 400);
} catch (Throwable $e) {
    error_log('[admin edits] ' . $e->getMessage());
    admin_fail('Errore del server sugli edit.', 500);
}
