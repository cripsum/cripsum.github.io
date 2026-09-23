<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/paypal_config.php';
require_once __DIR__ . '/../includes/shop/gacha_catalog.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.']);
    exit;
}
$orderId = isset($input['orderID']) ? trim($input['orderID']) : '';
$packageId = isset($input['package_id']) ? trim($input['package_id']) : '';

if (!preg_match('/^[a-zA-Z0-9-]{6,64}$/', $orderId) || empty($packageId)) {
    echo json_encode(['ok' => false, 'message' => 'ID ordine o pacchetto mancante.']);
    exit;
}

// Prezzo e Shards visti da chi paga, anche se il pacchetto nel frattempo e'
// stato cambiato, spento o archiviato dal pannello admin.
$package = gacha_paypal_package_for_capture($mysqli, $orderId, $packageId);

if ($package === null) {
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
$url = PAYPAL_MODE === 'live'
    ? "https://api-m.paypal.com/v2/checkout/orders/" . rawurlencode($orderId) . "/capture"
    : "https://api-m.sandbox.paypal.com/v2/checkout/orders/" . rawurlencode($orderId) . "/capture";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status === 200 || $status === 201) {
    $resJson = json_decode($response, true);
    $purchaseUnit = $resJson['purchase_units'][0] ?? [];
    $capture = $purchaseUnit['payments']['captures'][0] ?? [];
    $capturedAmount = number_format((float)($capture['amount']['value'] ?? -1), 2, '.', '');
    $expectedAmount = number_format($package['price_cents'] / 100, 2, '.', '');
    $currency = strtoupper((string)($capture['amount']['currency_code'] ?? ''));
    $customId = (string)($purchaseUnit['custom_id'] ?? '');
    $expectedCustomId = $userId . ':' . $packageId;

    if (($resJson['status'] ?? '') === 'COMPLETED'
        && ($capture['status'] ?? '') === 'COMPLETED'
        && hash_equals($expectedAmount, $capturedAmount)
        && hash_equals(PAYPAL_CURRENCY, $currency)
        && hash_equals($expectedCustomId, $customId)) {
        $paymentVerified = true;
    }
}

if (!$paymentVerified) {
    gacha_order_error($mysqli, $userId, $packageId, 'paypal', $orderId, (int)$package['price_cents'], 'Capture PayPal non verificata (HTTP ' . (int)$status . ').');
    echo json_encode(['ok' => false, 'message' => 'La transazione PayPal non è stata completata con successo.']);
    exit;
}

$baseShards = (int)$package['shards'];

// Attivazione nel database in transazione
$credited = false;
$mysqli->begin_transaction();
try {
    $stmtUserLock = $mysqli->prepare('SELECT id FROM utenti WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmtUserLock) throw new RuntimeException('Blocco utente non disponibile.');
    $stmtUserLock->bind_param('i', $userId);
    if (!$stmtUserLock->execute()) throw new RuntimeException('Blocco utente non riuscito.');
    $userExists = (bool)$stmtUserLock->get_result()->fetch_row();
    $stmtUserLock->close();
    if (!$userExists) throw new RuntimeException('Utente non trovato.');

    // 1. Check if first purchase bonus is used
    $stmtBonus = $mysqli->prepare("
        SELECT first_purchase_bonus_used 
        FROM first_purchase_bonuses 
        WHERE user_id = ? AND package_id = ? 
        LIMIT 1 FOR UPDATE
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
    $credited = true;
    gacha_paypal_forget_order($orderId);

    echo json_encode([
        'ok' => true,
        'message' => $isFirstPurchase
            ? "Pacchetto acquistato con successo! Hai ricevuto {$finalShards} Godo Shards (Doppie grazie al Bonus x2!)"
            : "Pacchetto acquistato con successo! Hai ricevuto {$finalShards} Godo Shards."
    ]);
} catch (Exception $e) {
    $mysqli->rollback();
    error_log('PayPal shard capture persistence failed: ' . $e->getMessage());
    gacha_order_error($mysqli, $userId, $packageId, 'paypal', $orderId, (int)$package['price_cents'], 'Pagato su PayPal ma accredito non riuscito: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Impossibile accreditare l\'acquisto. Contatta il supporto indicando l\'ID ordine.']);
}

// Storico per l'assistenza, a Shards gia' accreditate e fuori dalla
// transazione: se questa scrittura fallisce l'accredito resta.
if ($credited) {
    gacha_order_paid($mysqli, $userId, $packageId, 'paypal', $orderId, (int)$package['price_cents'], $baseShards, $finalShards, $isFirstPurchase);
}
exit;

function getPayPalAccessToken()
{
    $clientId = PAYPAL_CLIENT_ID;
    $clientSecret = PAYPAL_CLIENT_SECRET;
    $mode = PAYPAL_MODE;

    if ($clientId === '' || $clientSecret === '') {
        return null;
    }

    $url = $mode === 'live' ? 'https://api-m.paypal.com/v1/oauth2/token' : 'https://api-m.sandbox.paypal.com/v1/oauth2/token';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
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
