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
$packageId = isset($input['package_id']) ? trim($input['package_id']) : '';

$packages = [
    'shards_5' => ['price' => '0.59', 'name' => '5 Godo Shards'],
    'shards_10' => ['price' => '0.99', 'name' => '10 Godo Shards'],
    'shards_25' => ['price' => '1.99', 'name' => '25 Godo Shards'],
    'shards_45' => ['price' => '2.99', 'name' => '45 Godo Shards'],
    'shards_80' => ['price' => '4.99', 'name' => '80 Godo Shards'],
    'shards_180' => ['price' => '9.99', 'name' => '180 Godo Shards'],
    'shards_400' => ['price' => '19.99', 'name' => '400 Godo Shards'],
    'shards_1200' => ['price' => '49.99', 'name' => '1200 Godo Shards'],
];

if (empty($packageId) || !isset($packages[$packageId])) {
    echo json_encode(['ok' => false, 'message' => 'Pacchetto non valido.']);
    exit;
}

$package = $packages[$packageId];
$price = $package['price'];

// Chiamata a PayPal per creare l'ordine
$token = getPayPalAccessToken();

if (!$token) {
    echo json_encode(['ok' => false, 'message' => 'Impossibile connettersi a PayPal. Riprova più tardi.']);
    exit;
}

// Se il token è mockato, restituiamo un ordine mockato per test veloci
if (strpos($token, 'MOCK_TOKEN_') === 0) {
    echo json_encode([
        'ok' => true,
        'id' => 'MOCK_ORDER_' . $packageId . '_' . bin2hex(random_bytes(8)),
        'mock' => true
    ]);
    exit;
}

$url = PAYPAL_MODE === 'live' ? 'https://api-m.paypal.com/v2/checkout/orders' : 'https://api-m.sandbox.paypal.com/v2/checkout/orders';

$orderData = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'amount' => [
            'currency_code' => PAYPAL_CURRENCY,
            'value' => $price
        ],
        'description' => "Acquisto " . $package['name'] . " per account"
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
    echo json_encode([
        'ok' => false,
        'message' => 'Errore durante la creazione dell\'ordine PayPal.',
        'details' => json_decode($response, true)
    ]);
}

function getPayPalAccessToken() {
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
