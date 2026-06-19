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

// Dynamically adjust success/cancel URLs for language localization
$successUrl = str_replace('/it/', '/' . $lang . '/', STRIPE_SUCCESS_URL);
$cancelUrl = str_replace('/it/', '/' . $lang . '/', STRIPE_CANCEL_URL);

// Prep Stripe session parameters
$postData = http_build_query([
    'line_items[0][price]' => STRIPE_PRICE_ID,
    'line_items[0][quantity]' => 1,
    'mode' => 'payment',
    'client_reference_id' => $userId,
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
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
        <p>Impossibile avviare la sessione di pagamento con Stripe.</p>
        <p>Verifica che le credenziali (Secret Key e Price ID) siano corrette in <code>config/stripe_config.php</code>.</p>
        <?php
        if ($response) {
            $errObj = json_decode($response, true);
            if (isset($errObj['error']['message'])) {
                echo '<p style="color: #a8b0c7; font-size: 0.9rem;">Dettaglio: ' . htmlspecialchars($errObj['error']['message']) . '</p>';
            }
        }
        ?>
        <hr style="border: 0; border-top: 1px dashed rgba(255,255,255,0.1); margin: 1.5rem 0;">
        <a href="/it/edit-profile">Torna all'Editor</a>
    </div>
</body>
</html>
