<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/paypal_config.php';

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
$isGift = !empty($input['is_gift']);
$giftTo = isset($input['recipient_username']) ? trim($input['recipient_username']) : '';

$userId = (int)$_SESSION['user_id'];
$recipientId = $userId;

if ($isGift) {
    if (empty($giftTo)) {
        echo json_encode(['ok' => false, 'message' => 'Specificare l\'utente a cui regalare il premium.']);
        exit;
    }
    
    $stmt = $mysqli->prepare("SELECT id, is_premium FROM utenti WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $giftTo);
    $stmt->execute();
    $res = $stmt->get_result();
    $recipient = $res->fetch_assoc();
    $stmt->close();
    
    if (!$recipient) {
        echo json_encode(['ok' => false, 'message' => 'Utente destinatario non trovato.']);
        exit;
    }
    
    if ((int)($recipient['is_premium'] ?? 0) === 1) {
        echo json_encode(['ok' => false, 'message' => 'L\'utente selezionato è già premium.']);
        exit;
    }
    
    $recipientId = (int)$recipient['id'];
} else {
    // Verifica se l'utente stesso è già premium
    $stmt = $mysqli->prepare("SELECT is_premium FROM utenti WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $currUser = $res->fetch_assoc();
    $stmt->close();
    
    if ($currUser && (int)($currUser['is_premium'] ?? 0) === 1) {
        echo json_encode(['ok' => false, 'message' => 'Hai già un account Premium.']);
        exit;
    }
}

// Chiamata a PayPal per creare l'ordine
$token = getPayPalAccessToken();

if (!$token) {
    echo json_encode(['ok' => false, 'message' => 'Impossibile connettersi a PayPal. Riprova più tardi.']);
    exit;
}

$url = PAYPAL_MODE === 'live' ? 'https://api-m.paypal.com/v2/checkout/orders' : 'https://api-m.sandbox.paypal.com/v2/checkout/orders';

$orderData = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'custom_id' => $userId . ':' . $recipientId . ':' . ($isGift ? '1' : '0'),
        'amount' => [
            'currency_code' => PAYPAL_CURRENCY,
            'value' => PAYPAL_PRICE
        ],
        'description' => $isGift ? "Regalo Cripsum Premium per {$giftTo}" : "Cripsum Premium per account"
    ]]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status === 201) {
    $resJson = json_decode($response, true);
    echo json_encode([
        'ok' => true,
        'id' => $resJson['id']
    ]);
} else {
    error_log('PayPal premium create order failed with HTTP ' . $status);
    echo json_encode([
        'ok' => false,
        'message' => 'Errore durante la creazione dell\'ordine PayPal.'
    ]);
}

function getPayPalAccessToken() {
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
