<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/stripe_config.php';

if (!isLoggedIn()) {
    $lang = 'it';
    if (isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') {
        $lang = 'en';
    }
    header("Location: /{$lang}/accedi");
    exit;
}

$lang = 'it';
if (isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') {
    $lang = 'en';
}

$userId = (int)$_SESSION['user_id'];
$packageId = isset($_REQUEST['package_id']) ? trim((string)$_REQUEST['package_id']) : '';

$packages = [
    'shards_5' => ['price' => 0.59, 'shards' => 5, 'name' => '5 Godo Shards'],
    'shards_10' => ['price' => 0.99, 'shards' => 10, 'name' => '10 Godo Shards'],
    'shards_25' => ['price' => 1.99, 'shards' => 25, 'name' => '25 Godo Shards'],
    'shards_45' => ['price' => 2.99, 'shards' => 45, 'name' => '45 Godo Shards'],
    'shards_80' => ['price' => 4.99, 'shards' => 80, 'name' => '80 Godo Shards (Pity Completo)'],
    'shards_180' => ['price' => 9.99, 'shards' => 180, 'name' => '180 Godo Shards'],
    'shards_400' => ['price' => 19.99, 'shards' => 400, 'name' => '400 Godo Shards'],
    'shards_1200' => ['price' => 49.99, 'shards' => 1200, 'name' => '1200 Godo Shards'],
];

if (empty($packageId) || !isset($packages[$packageId])) {
    header("Location: /{$lang}/shop.php?error=invalid_package");
    exit;
}

$package = $packages[$packageId];
$priceInCents = (int)round($package['price'] * 100);

$successUrl = "https://cripsum.com/{$lang}/shop.php?payment=success&package_id=" . urlencode($packageId);
$cancelUrl = "https://cripsum.com/{$lang}/shop.php?payment=cancel";

// Prep Stripe session parameters using inline price_data
$postData = http_build_query([
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][unit_amount]' => $priceInCents,
    'line_items[0][price_data][product_data][name]' => $package['name'],
    'line_items[0][quantity]' => 1,
    'mode' => 'payment',
    'client_reference_id' => $userId,
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'metadata[type]' => 'shards',
    'metadata[package_id]' => $packageId,
    'metadata[user_id]' => $userId,
]);

// Call Stripe API using standard PHP cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpStatus === 200) {
    $session = json_decode($response, true);
    if (!empty($session['url'])) {
        header('Location: ' . $session['url']);
        exit;
    }
}

// Error Fallback
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Errore Pagamento - Cripsum</title>
    <style>
        body { font-family: sans-serif; background: #08050e; color: #fff; text-align: center; padding: 5rem 1rem; }
        .card { max-width: 500px; margin: 0 auto; background: rgba(255,255,255,0.05); padding: 2rem; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); }
        h1 { color: #ef4444; }
        a { color: #0f5bff; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Errore di Connessione</h1>
        <p>Impossibile avviare la sessione di pagamento con Stripe per il pacchetto di Shards.</p>
        <p>Verifica che la Secret Key sia corretta in <code>config/stripe_config.php</code>.</p>
        <?php
        if ($response) {
            $errObj = json_decode($response, true);
            if (isset($errObj['error']['message'])) {
                echo '<p style="color: #a8b0c7; font-size: 0.9rem;">Dettaglio: ' . htmlspecialchars($errObj['error']['message']) . '</p>';
            }
        }
        ?>
        <hr style="border: 0; border-top: 1px dashed rgba(255,255,255,0.1); margin: 1.5rem 0;">
        <a href="/<?php echo $lang; ?>/shop.php">Torna allo Shop</a>
    </div>
</body>
</html>
