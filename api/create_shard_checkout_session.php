<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/stripe_config.php';
require_once __DIR__ . '/../includes/shop/gacha_catalog.php';

if (STRIPE_SECRET_KEY === '') {
    error_log('Stripe shard checkout disabled: missing runtime credentials.');
    http_response_code(503);
    exit('Pagamento temporaneamente non disponibile.');
}

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

// Solo i pacchetti in vendita adesso: quelli spenti dall'admin non si
// possono piu' comprare, anche con un link vecchio.
$package = $packageId !== '' ? gacha_package_for_checkout($mysqli, $packageId) : null;

if ($package === null) {
    header("Location: /{$lang}/shop.php?error=invalid_package");
    exit;
}

$priceInCents = (int)$package['price_cents'];
$productName = $package['name'] . ($package['highlight'] === 'pity' ? ' (Pity Completo)' : '');

// {CHECKOUT_SESSION_ID} lo sostituisce Stripe: al ritorno la pagina sa quale
// ordine aspettare e mostra "accredito in corso" finche' non arriva il webhook.
$successUrl = "https://cripsum.com/{$lang}/shop.php?payment=success&package_id=" . urlencode($packageId) . '&session_id={CHECKOUT_SESSION_ID}';
$cancelUrl = "https://cripsum.com/{$lang}/shop.php?payment=cancel";

// Prep Stripe session parameters using inline price_data
$postData = http_build_query([
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][unit_amount]' => $priceInCents,
    'line_items[0][price_data][product_data][name]' => $productName,
    'line_items[0][quantity]' => 1,
    'mode' => 'payment',
    'client_reference_id' => $userId,
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'metadata[type]' => 'shards',
    'metadata[package_id]' => $packageId,
    'metadata[user_id]' => $userId,
    // Prezzo e Shards di questo momento: se l'admin li cambia mentre la
    // sessione e' aperta, il webhook accredita quello che e' stato pagato.
    'metadata[price_cents]' => $priceInCents,
    'metadata[shards]' => (int)$package['shards'],
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
        // Storico: l'ordine nasce "in attesa", lo chiude il webhook.
        gacha_order_pending($mysqli, $userId, $packageId, 'stripe', (string)($session['id'] ?? ''), $package);
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
        <p>Verifica la configurazione sicura Stripe sul server.</p>
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
