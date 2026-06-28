<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Utente non autenticato.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Ricevi parametri POST
$input = json_decode(file_get_contents('php://input'), true);
$itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID oggetto non valido.']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // 1. Recupera l'oggetto dello shop e bloccalo per la lettura
    $stmtItem = $mysqli->prepare('SELECT * FROM godos_shop_items WHERE id = ? AND active = 1 FOR UPDATE');
    if (!$stmtItem) throw new Exception('Prepare select item fallito.');
    $stmtItem->bind_param('i', $itemId);
    $stmtItem->execute();
    $item = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    if (!$item) {
        throw new Exception('Oggetto non trovato o non attivo.');
    }

    $costo = (int)$item['price_godos'];
    $tipo = $item['item_type'];
    $valore = $item['item_value'];
    $disponibilita = $item['availability'];

    // 2. Controlla disponibilità residua se limitata
    if ($disponibilita !== null && $disponibilita <= 0) {
        throw new Exception('Oggetto non più disponibile (esaurito).');
    }

    // 3. Recupera bilancio utente e bloccalo
    $stmtUser = $mysqli->prepare('SELECT soldi FROM utenti WHERE id = ? FOR UPDATE');
    if (!$stmtUser) throw new Exception('Prepare select user fallito.');
    $stmtUser->bind_param('i', $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$user) {
        throw new Exception('Utente non trovato.');
    }

    $godosAttuali = (int)$user['soldi'];
    if ($godosAttuali < $costo) {
        throw new Exception('Punti Godos insufficienti per questo acquisto.');
    }

    // 4. Se l'oggetto è un badge, controlla se l'utente lo possiede già
    if ($tipo === 'badge') {
        $badgeId = (int)$valore;
        $stmtOwned = $mysqli->prepare('SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = ?');
        if (!$stmtOwned) throw new Exception('Prepare check badge fallito.');
        $stmtOwned->bind_param('ii', $userId, $badgeId);
        $stmtOwned->execute();
        $giaPosseduto = $stmtOwned->get_result()->fetch_assoc();
        $stmtOwned->close();

        if ($giaPosseduto) {
            throw new Exception('Possiedi già questo Badge!');
        }
    }

    // 5. Esegui la detrazione dei punti Godos
    $nuoviGodos = $godosAttuali - $costo;
    $stmtUpdateUser = $mysqli->prepare('UPDATE utenti SET soldi = ? WHERE id = ?');
    if (!$stmtUpdateUser) throw new Exception('Prepare update user fallito.');
    $stmtUpdateUser->bind_param('ii', $nuoviGodos, $userId);
    $stmtUpdateUser->execute();
    $stmtUpdateUser->close();

    // 6. Esegui la consegna dell'oggetto
    if ($tipo === 'badge') {
        $badgeId = (int)$valore;
        // Assegna il badge impostando is_visible = 1 per farlo apparire
        $stmtGrantBadge = $mysqli->prepare('INSERT INTO user_custom_badges (utente_id, badge_id, is_visible) VALUES (?, ?, 1)');
        if (!$stmtGrantBadge) throw new Exception('Prepare grant badge fallito.');
        $stmtGrantBadge->bind_param('ii', $userId, $badgeId);
        $stmtGrantBadge->execute();
        $stmtGrantBadge->close();
    }

    // 7. Decrementa disponibilità se limitata
    if ($disponibilita !== null) {
        $nuovaDisp = $disponibilita - 1;
        $stmtUpdateItem = $mysqli->prepare('UPDATE godos_shop_items SET availability = ? WHERE id = ?');
        if (!$stmtUpdateItem) throw new Exception('Prepare update item fallito.');
        $stmtUpdateItem->bind_param('ii', $nuovaDisp, $itemId);
        $stmtUpdateItem->execute();
        $stmtUpdateItem->close();
    }

    // 8. Registra l'acquisto nel log
    $stmtLog = $mysqli->prepare('INSERT INTO user_godos_shop_purchases (user_id, item_id) VALUES (?, ?)');
    if (!$stmtLog) throw new Exception('Prepare log purchase fallito.');
    $stmtLog->bind_param('ii', $userId, $itemId);
    $stmtLog->execute();
    $stmtLog->close();

    $mysqli->commit();

    // Recupera dettagli del badge se applicabile per la reveal animation
    $badgeDetails = null;
    if ($tipo === 'badge') {
        $badgeId = (int)$valore;
        $stmtBadgeDetails = $mysqli->prepare('SELECT name, name_en, image_url, color, glow, animation FROM custom_badges WHERE id = ?');
        if ($stmtBadgeDetails) {
            $stmtBadgeDetails->bind_param('i', $badgeId);
            $stmtBadgeDetails->execute();
            $badgeDetails = $stmtBadgeDetails->get_result()->fetch_assoc();
            $stmtBadgeDetails->close();
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Acquisto completato con successo!',
        'soldi_rimasti' => $nuoviGodos,
        'item_type' => $tipo,
        'item_value' => $valore,
        'badge' => $badgeDetails,
        'availability_left' => ($disponibilita !== null) ? ($disponibilita - 1) : null
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
