<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/paypal_config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = isset($input['orderID']) ? trim($input['orderID']) : '';
$packageId = isset($input['package_id']) ? trim($input['package_id']) : '';

if (empty($orderId) || empty($packageId)) {
    echo json_encode(['ok' => false, 'message' => 'ID ordine o pacchetto mancante.']);
    exit;
}

$packages = [
    'shards_5' => ['price' => 0.59, 'shards' => 5],
    'shards_10' => ['price' => 0.99, 'shards' => 10],
    'shards_25' => ['price' => 1.99, 'shards' => 25],
    'shards_45' => ['price' => 2.99, 'shards' => 45],
    'shards_80' => ['price' => 4.99, 'shards' => 80],
    'shards_180' => ['price' => 9.99, 'shards' => 180],
    'shards_400' => ['price' => 19.99, 'shards' => 400],
    'shards_1200' => ['price' => 49.99, 'shards' => 1200],
];

if (!isset($packages[$packageId])) {
    echo json_encode(['ok' => false, 'message' => 'Pacchetto non valido.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$token = getPayPalAccessToken();

if (!$token) {
    echo json_encode(['ok' => false, 'message' => 'Impossibile verificare il pagamento con PayPal.']);
    exit;
}

$paymentVerified = false;

// Se è un ordine mockato, consideriamolo verificato per facilitare i test locali
if (strpos($orderId, 'MOCK_ORDER_') === 0 || strpos($token, 'MOCK_TOKEN_') === 0) {
    $paymentVerified = true;
} else {
    $url = PAYPAL_MODE === 'live'
        ? "https://api-m.paypal.com/v2/checkout/orders/{$orderId}/capture"
        : "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 || $status === 201) {
        $resJson = json_decode($response, true);
        if (($resJson['status'] ?? '') === 'COMPLETED') {
            $paymentVerified = true;
        }
    }
}

if (!$paymentVerified) {
    echo json_encode(['ok' => false, 'message' => 'La transazione PayPal non è stata completata con successo.']);
    exit;
}

$package = $packages[$packageId];
$baseShards = $package['shards'];

// Attivazione nel database in transazione
$mysqli->begin_transaction();
try {
    // 1. Check if first purchase bonus is used
    $stmtBonus = $mysqli->prepare("
        SELECT first_purchase_bonus_used 
        FROM first_purchase_bonuses 
        WHERE user_id = ? AND package_id = ? 
        LIMIT 1
        FOR UPDATE
    ");
    $stmtBonus->bind_param('is', $userId, $packageId);
    $stmtBonus->execute();
    $resBonus = $stmtBonus->get_result();
    $bonusRow = $resBonus->fetch_assoc();
    $stmtBonus->close();

    $isFirstPurchase = !$bonusRow || (int)($bonusRow['first_purchase_bonus_used'] ?? 0) === 0;
    $finalShards = $isFirstPurchase ? ($baseShards * 2) : $baseShards;

    // 2. Aggiorna saldo shards
    $stmt = $mysqli->prepare("UPDATE utenti SET godoshards_balance = godoshards_balance + ? WHERE id = ?");
    $stmt->bind_param("ii", $finalShards, $userId);
    $stmt->execute();
    $stmt->close();

    // 3. Salva che il bonus primo acquisto è stato usato
    $stmtSaveBonus = $mysqli->prepare("
        INSERT INTO first_purchase_bonuses (user_id, package_id, first_purchase_bonus_used) 
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE first_purchase_bonus_used = 1
    ");
    $stmtSaveBonus->bind_param("is", $userId, $packageId);
    $stmtSaveBonus->execute();
    $stmtSaveBonus->close();

    // 4. Registra l'attività
    if (function_exists('profile_record_activity')) {
        $activityMsg = "Acquistato pacchetto {$packageId} (" . ($isFirstPurchase ? ($baseShards . "x2") : $baseShards) . " Godo Shards)";
        profile_record_activity($mysqli, $userId, 'shards_purchase', $activityMsg);
    }

    $mysqli->commit();

    echo json_encode([
        'ok' => true,
        'message' => $isFirstPurchase
            ? "Pacchetto acquistato con successo! Hai ricevuto {$finalShards} Godo Shards (Doppie grazie al Bonus x2!)"
            : "Pacchetto acquistato con successo! Hai ricevuto {$finalShards} Godo Shards."
    ]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['ok' => false, 'message' => 'Errore nel salvataggio dei dati: ' . $e->getMessage()]);
}
exit;

function getPayPalAccessToken()
{
    $clientId = PAYPAL_CLIENT_ID;
    $clientSecret = PAYPAL_CLIENT_SECRET;
    $mode = PAYPAL_MODE;

    if ($clientId === 'placeholder_your_paypal_client_id' || $clientSecret === 'placeholder_your_paypal_client_secret') {
        return 'MOCK_TOKEN_' . time();
    }

    $url = $mode === 'live' ? 'https://api-m.paypal.com/v1/oauth2/token' : 'https://api-m.sandbox.paypal.com/v1/oauth2/token';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200) {
        $json = json_decode($result, true);
        return $json['access_token'] ?? null;
    }

    return null;
}
