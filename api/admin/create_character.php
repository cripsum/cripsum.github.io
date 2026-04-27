<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Accetta sia URL http/https sia nomi file semplici.
 * Esempi validi:
 * - nome.jpg
 * - nome.png
 * - personaggi/nome.webp
 * - audio.mp3
 * - https://example.com/nome.jpg
 *
 * Non accetta:
 * - ../file.jpg
 * - javascript:...
 * - file senza estensione valida
 */
function admin_normalize_media_file($value, array $allowedExtensions, string $fieldLabel): string
{
    $value = trim((string)($value ?? ''));

    if ($value === '') {
        return '';
    }

    $value = str_replace("\0", '', $value);

    // URL completo, se mai ti serve.
    if (preg_match('~^https?://~i', $value)) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            admin_fail($fieldLabel . ' non valido.');
        }

        $path = parse_url($value, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            admin_fail($fieldLabel . ' ha un formato non supportato.');
        }

        return $value;
    }

    // Blocca path pericolosi.
    if (strpos($value, '..') !== false || preg_match('~^[a-z]+:~i', $value)) {
        admin_fail($fieldLabel . ' non valido.');
    }

    // Permette nomi file e sottocartelle semplici.
    if (!preg_match('~^[a-zA-Z0-9_\-./ ()]+$~', $value)) {
        admin_fail($fieldLabel . ' contiene caratteri non validi.');
    }

    $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions, true)) {
        admin_fail($fieldLabel . ' ha un formato non supportato.');
    }

    return $value;
}

function admin_bind_stmt_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $bindParams = [$types];

    foreach ($params as $key => &$value) {
        $bindParams[] = &$value;
    }

    if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
        admin_fail('Parametri query non validi.', 500);
    }
}

try {
    $input = admin_input();
    $cols = admin_character_columns($mysqli);

    $nameCol = $cols['name'] ?: admin_fail('Campo nome personaggio mancante.', 500);

    $name = trim((string)($input['nome'] ?? ''));

    if ($name === '' || mb_strlen($name) > 80) {
        admin_fail('Nome personaggio non valido.');
    }

    $description = trim((string)($input['descrizione'] ?? ''));
    $features = trim((string)($input['caratteristiche'] ?? ''));

    // Accetta sia "rarità" sia "rarita", nel caso il JS mandi il campo senza accento.
    $rarity = trim((string)($input['rarità'] ?? $input['rarita'] ?? $input['rarity'] ?? ''));
    $category = trim((string)($input['categoria'] ?? $input['category'] ?? ''));

    // Qui ora puoi mettere solo il nome del file, tipo nome.jpg / nome.mp3.
    $imageValue = admin_normalize_media_file(
        $input['img_url'] ?? $input['image'] ?? '',
        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'Immagine'
    );

    $audioValue = admin_normalize_media_file(
        $input['audio_url'] ?? $input['audio'] ?? '',
        ['mp3', 'wav', 'ogg', 'm4a', 'aac'],
        'Audio'
    );

    $fields = [admin_qcol($nameCol)];
    $placeholders = ['?'];
    $types = 's';
    $params = [$name];

    $map = [
        'description' => $description,
        'features' => $features,
        'image' => $imageValue,
        'rarity' => $rarity,
        'audio' => $audioValue,
        'category' => $category,
    ];

    foreach ($map as $key => $value) {
        if (!empty($cols[$key])) {
            $fields[] = admin_qcol($cols[$key]);
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $value;
        }
    }

    $sql = 'INSERT INTO personaggi (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        error_log('create_character prepare error: ' . $mysqli->error . ' | SQL: ' . $sql);
        admin_fail('Query creazione personaggio non valida.', 500);
    }

    admin_bind_stmt_params($stmt, $types, $params);

    if (!$stmt->execute()) {
        error_log('create_character execute error: ' . $stmt->error . ' | SQL: ' . $sql);
        admin_fail('Non sono riuscito a creare il personaggio.', 500);
    }

    $id = $stmt->insert_id;
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'create_character', null, [
        'character_id' => $id,
        'name' => $name,
    ]);

    admin_ok([
        'message' => 'Personaggio creato.',
        'id' => $id,
    ]);
} catch (Throwable $e) {
    error_log('create_character fatal error: ' . $e->getMessage());
    admin_fail('Errore creazione personaggio.', 500);
}
