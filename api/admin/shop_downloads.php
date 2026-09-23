<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/shop/downloads.php';

/**
 * Download dal pannello.
 *
 * GET  ?action=list
 * POST action = save | delete | set_state | reorder | upload (multipart, campo "file")
 */

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!admin_table_exists($mysqli, 'download_items')) {
    if ($action === 'list') {
        admin_ok(['ready' => false, 'message' => 'Tabella download_items mancante: applica la migrazione 2026_09_23_shop_catalog.sql.']);
    }
    admin_fail('Tabella download_items mancante: applica la migrazione.', 409);
}

function downloads_parse_meta(?string $text): array
{
    return admin_shop_pairs($text, 'Dettagli', 8);
}

function downloads_parse_steps(?string $text): array
{
    $steps = [];
    foreach (preg_split('/\n/', (string)$text) as $line) {
        // Si tollerano gli elenchi scritti a mano: "1. ...", "- ...".
        $line = trim(preg_replace('/^\s*(\d+[.)]|[-*•])\s*/u', '', $line) ?? '');
        if ($line !== '') {
            $steps[] = mb_substr($line, 0, 200);
        }
    }

    if (count($steps) > 10) {
        admin_fail('Passaggi: al massimo 10 righe.');
    }

    return $steps;
}

/**
 * La sorgente di un download: per un file, un percorso dentro le cartelle
 * ammesse che esista davvero; per un link, un indirizzo http(s) o un
 * percorso del sito.
 */
function downloads_source(array $input, string $key, string $label, string $type, bool $required): ?string
{
    $value = trim((string)($input[$key] ?? ''));

    if ($value === '') {
        if ($required) {
            admin_fail($label . ': campo obbligatorio.');
        }
        return null;
    }

    if ($type === 'link') {
        return admin_shop_link($input, $key, $label, $required);
    }

    $value = '/' . ltrim(rawurldecode($value), '/');
    if (shop_download_file_path($value) === null) {
        admin_fail($label . ': il file non esiste oppure sta fuori dalle cartelle dei download (uploads/downloads, random stuff).');
    }

    return $value;
}

try {
    if ($action === 'list') {
        $rows = admin_shop_rows($mysqli, 'SELECT * FROM download_items ORDER BY posizione ASC, id ASC');

        foreach ($rows as &$row) {
            $view = shop_download_view($row, 'it');
            $row['file_ok'] = $row['tipo'] === 'link' ? true : $view['size'] !== null;
            $row['size_label'] = $view['size_label'];
            $row['host'] = $view['host'];
            $row['meta_it'] = implode("\n", array_map(static fn($p) => $p[0] . ': ' . $p[1], shop_download_json($row['meta'])['it'] ?? []));
            $row['meta_en'] = implode("\n", array_map(static fn($p) => $p[0] . ': ' . $p[1], shop_download_json($row['meta'])['en'] ?? []));
            $row['passi_it'] = implode("\n", shop_download_json($row['passi'])['it'] ?? []);
            $row['passi_en'] = implode("\n", shop_download_json($row['passi'])['en'] ?? []);
        }
        unset($row);

        admin_ok([
            'ready' => true,
            'items' => $rows,
            'extensions' => SHOP_DOWNLOAD_EXTENSIONS,
            'max_upload_mb' => 200,
        ]);
    }

    admin_shop_require_post();
    $input = admin_input();

    switch ($action) {
        case 'save':
            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0 ? admin_shop_row($mysqli, 'SELECT * FROM download_items WHERE id = ? LIMIT 1', 'i', [$id]) : null;
            if ($id > 0 && !$existing) {
                admin_fail('Download non trovato.', 404);
            }

            $nome = admin_shop_text($input, 'nome', 'Nome (IT)', 120, true);
            $slug = admin_shop_slug($input, 'slug', $nome, 80);
            $tipo = admin_shop_enum($input, 'tipo', 'Tipo', ['file', 'link'], 'link');
            $stato = admin_shop_enum($input, 'stato', 'Stato', ['disponibile', 'presto', 'nascosto'], 'disponibile');

            // Un download "in arrivo" puo' non avere ancora il file.
            $sourceRequired = $stato === 'disponibile';

            $meta = [
                'it' => downloads_parse_meta($input['meta_it'] ?? ''),
                'en' => downloads_parse_meta($input['meta_en'] ?? ''),
            ];
            $steps = [
                'it' => downloads_parse_steps($input['passi_it'] ?? ''),
                'en' => downloads_parse_steps($input['passi_en'] ?? ''),
            ];

            $nomeFile = admin_shop_text($input, 'nome_file', 'Nome del file scaricato', 160);
            if ($nomeFile !== null && preg_match('~[\\\\/:*?"<>|\x00-\x1f]~', $nomeFile)) {
                admin_fail('Nome del file scaricato: niente barre né caratteri speciali (\\ / : * ? " < > |).');
            }

            $fields = [
                'slug' => $slug,
                'nome' => $nome,
                'nome_en' => admin_shop_text($input, 'nome_en', 'Nome (EN)', 120),
                'descrizione_breve' => admin_shop_text($input, 'descrizione_breve', 'Descrizione breve (IT)', 300),
                'descrizione_breve_en' => admin_shop_text($input, 'descrizione_breve_en', 'Descrizione breve (EN)', 300),
                'descrizione' => admin_shop_text($input, 'descrizione', 'Descrizione completa (IT)', 4000),
                'descrizione_en' => admin_shop_text($input, 'descrizione_en', 'Descrizione completa (EN)', 4000),
                'immagine' => admin_shop_image($input, 'immagine', 'Immagine'),
                'badge' => admin_shop_text($input, 'badge', 'Badge (IT)', 40),
                'badge_en' => admin_shop_text($input, 'badge_en', 'Badge (EN)', 40),
                'stato' => $stato,
                'tipo' => $tipo,
                'sorgente' => downloads_source($input, 'sorgente', $tipo === 'file' ? 'File' : 'Link', $tipo, $sourceRequired),
                'sorgente_en' => downloads_source($input, 'sorgente_en', $tipo === 'file' ? 'File inglese' : 'Link inglese', $tipo, false),
                'nome_file' => $tipo === 'file' ? $nomeFile : null,
                'testo_bottone' => admin_shop_text($input, 'testo_bottone', 'Testo bottone (IT)', 60),
                'testo_bottone_en' => admin_shop_text($input, 'testo_bottone_en', 'Testo bottone (EN)', 60),
                'nota' => admin_shop_text($input, 'nota', 'Avviso (IT)', 400),
                'nota_en' => admin_shop_text($input, 'nota_en', 'Avviso (EN)', 400),
                'nota_tono' => admin_shop_enum($input, 'nota_tono', 'Tipo di avviso', ['info', 'avviso'], 'info'),
                'meta' => ($meta['it'] || $meta['en']) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'passi' => ($steps['it'] || $steps['en']) ? json_encode($steps, JSON_UNESCAPED_UNICODE) : null,
                'in_evidenza' => admin_shop_bool($input, 'in_evidenza'),
            ];

            $columns = array_keys($fields);
            $values = array_values($fields);
            $types = str_repeat('s', count($values) - 1) . 'i';

            if ($existing) {
                $set = implode(', ', array_map(static fn($c) => "`$c` = ?", $columns));
                admin_shop_exec($mysqli, "UPDATE download_items SET $set WHERE id = ? LIMIT 1", $types . 'i', array_merge($values, [$id]), 'Non sono riuscito a salvare il download.')->close();
                admin_log($mysqli, $adminId, 'shop_update_download', null, ['download_id' => $id, 'slug' => $slug]);
                admin_ok(['message' => 'Download salvato.', 'id' => $id, 'slug' => $slug]);
            }

            $columns[] = 'posizione';
            $values[] = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM download_items');
            $types .= 'i';

            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO download_items (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $types,
                $values,
                'Non sono riuscito a creare il download.'
            );
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'shop_create_download', null, ['download_id' => $newId, 'slug' => $slug]);
            admin_ok(['message' => 'Download creato.', 'id' => $newId, 'slug' => $slug]);

        case 'set_state':
            $id = (int)($input['id'] ?? 0);
            $item = admin_shop_row($mysqli, 'SELECT * FROM download_items WHERE id = ? LIMIT 1', 'i', [$id]);
            if (!$item) {
                admin_fail('Download non trovato.', 404);
            }
            $stato = admin_shop_enum($input, 'stato', 'Stato', ['disponibile', 'presto', 'nascosto'], 'disponibile');
            if ($stato === 'disponibile' && trim((string)$item['sorgente']) === '') {
                admin_fail('Prima di renderlo disponibile aggiungi il file o il link.');
            }
            admin_shop_exec($mysqli, 'UPDATE download_items SET stato = ? WHERE id = ? LIMIT 1', 'si', [$stato, $id], 'Aggiornamento non riuscito.')->close();
            admin_log($mysqli, $adminId, 'shop_state_download', null, ['download_id' => $id, 'stato' => $stato]);
            admin_ok(['message' => 'Stato aggiornato.']);

        case 'delete':
            // Il file caricato resta sul disco: potrebbe servire a un altro
            // download, e cancellarlo per errore non si puo' annullare.
            $id = (int)($input['id'] ?? 0);
            $item = admin_shop_row($mysqli, 'SELECT slug FROM download_items WHERE id = ? LIMIT 1', 'i', [$id]);
            if (!$item) {
                admin_fail('Download non trovato.', 404);
            }
            admin_shop_exec($mysqli, 'DELETE FROM download_items WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            admin_log($mysqli, $adminId, 'shop_delete_download', null, ['slug' => $item['slug']]);
            admin_ok(['message' => 'Download eliminato.']);

        case 'reorder':
            admin_shop_reorder($mysqli, 'download_items', 'posizione', admin_shop_ids($input));
            admin_log($mysqli, $adminId, 'shop_reorder_downloads');
            admin_ok(['message' => 'Ordine aggiornato.']);

        case 'upload':
            $file = $_FILES['file'] ?? null;
            if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $code = is_array($file) ? (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $message = match ($code) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Il file supera il limite di caricamento del server (' . ini_get('upload_max_filesize') . ').',
                    UPLOAD_ERR_NO_FILE => 'Nessun file ricevuto.',
                    default => 'Caricamento non riuscito (codice ' . $code . ').',
                };
                admin_fail($message);
            }

            if ((int)$file['size'] <= 0 || (int)$file['size'] > 200 * 1024 * 1024) {
                admin_fail('Il file deve pesare meno di 200 MB.');
            }

            $original = basename((string)$file['name']);
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if (!in_array($ext, SHOP_DOWNLOAD_EXTENSIONS, true)) {
                admin_fail('Formato .' . $ext . ' non ammesso. Per programmi ed eseguibili usa un link esterno alla fonte ufficiale.');
            }

            $base = admin_shop_slugify(pathinfo($original, PATHINFO_FILENAME)) ?: 'download';
            $base = substr($base, 0, 80);
            $dir = shop_site_root() . '/uploads/downloads';

            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                admin_fail('Non riesco a creare la cartella uploads/downloads sul server.', 500);
            }

            $name = $base . '.' . $ext;
            $n = 2;
            while (file_exists($dir . '/' . $name)) {
                $name = $base . '-' . $n++ . '.' . $ext;
            }

            if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
                admin_fail('Non sono riuscito a salvare il file sul server.', 500);
            }

            admin_log($mysqli, $adminId, 'shop_upload_download', null, ['file' => $name, 'bytes' => (int)$file['size']]);
            admin_ok([
                'message' => 'File caricato.',
                'path' => '/uploads/downloads/' . $name,
                'suggested_name' => $original,
                'size_label' => shop_format_bytes((int)$file['size'], 'it'),
            ]);
    }

    admin_fail('Azione non riconosciuta.', 400);
} catch (Throwable $e) {
    error_log('[admin shop_downloads] ' . $e->getMessage());
    admin_fail('Errore del server sui download.', 500);
}
