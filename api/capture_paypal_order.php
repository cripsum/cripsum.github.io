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
$isGift = !empty($input['is_gift']);
$giftTo = isset($input['recipient_username']) ? trim($input['recipient_username']) : '';

if (empty($orderId)) {
    echo json_encode(['ok' => false, 'message' => 'ID ordine mancante.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$recipientId = $userId;

if ($isGift) {
    if (empty($giftTo)) {
        echo json_encode(['ok' => false, 'message' => 'Specificare il destinatario del regalo.']);
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
}

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

// Attivazione Premium nel database
$mysqli->begin_transaction();
try {
    // 1. Aggiorna lo stato premium
    $stmt = $mysqli->prepare("UPDATE utenti SET is_premium = 1 WHERE id = ?");
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $stmt->close();
    
    // 2. Aggiungi il bonus di 200k soldi per pullare
    $stmtSoldi = $mysqli->prepare("UPDATE utenti SET soldi = soldi + 200000 WHERE id = ?");
    $stmtSoldi->bind_param("i", $recipientId);
    $stmtSoldi->execute();
    $stmtSoldi->close();
    
    // 3. Assegna il badge custom ID 5 (Premium Badge) se non già presente
    $stmtBadge = $mysqli->prepare("
        INSERT INTO user_custom_badges (utente_id, badge_id, is_visible)
        SELECT ?, 5, 1
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = 5
        )
    ");
    $stmtBadge->bind_param("ii", $recipientId, $recipientId);
    $stmtBadge->execute();
    $stmtBadge->close();
    
    // 4. Registra l'attività se applicabile
    if (function_exists('profile_record_activity')) {
        $activityMsg = $isGift 
            ? "Ricevuto Cripsum Premium in regalo dall'utente {$_SESSION['username']}" 
            : "Acquistato Cripsum Premium";
        profile_record_activity($mysqli, $recipientId, 'premium_upgrade', $activityMsg);
        
        if ($isGift) {
            profile_record_activity($mysqli, $userId, 'premium_gift', "Regalato Cripsum Premium all'utente {$giftTo}");
        }
    }

    // 5. Se si tratta di un regalo, registra il regalo nel DB e invia l'email di notifica al destinatario
    if ($isGift) {
        $stmtGift = $mysqli->prepare("INSERT INTO premium_gifts (sender_id, recipient_id, payment_gateway, payment_order_id) VALUES (?, ?, 'paypal', ?)");
        if ($stmtGift) {
            $stmtGift->bind_param("iis", $userId, $recipientId, $orderId);
            $stmtGift->execute();
            $stmtGift->close();
        }

        // Assegna il badge donatore (ID 11) al mittente (colui che fa il regalo) se non già presente
        $stmtGiverBadge = $mysqli->prepare("
            INSERT INTO user_custom_badges (utente_id, badge_id, is_visible)
            SELECT ?, 11, 1
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = 11
            )
        ");
        if ($stmtGiverBadge) {
            $stmtGiverBadge->bind_param("ii", $userId, $userId);
            $stmtGiverBadge->execute();
            $stmtGiverBadge->close();
        }

        // Recupera i dettagli del destinatario (username ed email)
        $stmtDetails = $mysqli->prepare("SELECT username, email FROM utenti WHERE id = ? LIMIT 1");
        if ($stmtDetails) {
            $stmtDetails->bind_param("i", $recipientId);
            $stmtDetails->execute();
            $recipientDetails = $stmtDetails->get_result()->fetch_assoc();
            $stmtDetails->close();

            if ($recipientDetails && !empty($recipientDetails['email'])) {
                sendPremiumGiftEmail($recipientDetails['email'], $recipientDetails['username'], $_SESSION['username']);
            }
        }
    }
    
    $mysqli->commit();
    
    // Se l'utente loggato è colui che ha ricevuto il premium, aggiorna la sessione attiva
    if ($recipientId === $userId) {
        $_SESSION['is_premium'] = 1;
    }
    
    echo json_encode([
        'ok' => true, 
        'message' => $isGift 
            ? 'Regalo inviato con successo! Il tuo amico ha ricevuto 200.000 soldi e il Premium!'
            : 'Premium attivato con successo! Hai ricevuto 200.000 soldi bonus!'
    ]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['ok' => false, 'message' => 'Errore nel salvataggio dei dati: ' . $e->getMessage()]);
}
exit;

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
