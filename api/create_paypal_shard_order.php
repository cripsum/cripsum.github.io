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
$packageId = isset($input['package_id']) ? trim($input['package_id']) : '';

// Solo i pacchetti in vendita adesso.
$package = $packageId !== '' ? gacha_package_for_checkout($mysqli, $packageId) : null;

if ($package === null) {
    echo json_encode(['ok' => false, 'message' => 'Pacchetto non valido.']);
    exit;
}

$price = number_format($package['price_cents'] / 100, 2, '.', '');

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
        'custom_id' => (int)$_SESSION['user_id'] . ':' . $packageId,
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
    // La capture confrontera' l'importo con quello di adesso, anche se nel
    // frattempo l'admin cambia il prezzo del pacchetto.
    gacha_paypal_remember_order((string)($resJson['id'] ?? ''), $packageId, $package);
    gacha_order_pending($mysqli, (int)$_SESSION['user_id'], $packageId, 'paypal', (string)($resJson['id'] ?? ''), $package);
    echo json_encode([
        'ok' => true,
        'id' => $resJson['id']
    ]);
} else {
    error_log('PayPal create order failed with HTTP ' . $status);
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
