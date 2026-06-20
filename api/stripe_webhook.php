<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe_config.php';
require_once __DIR__ . '/../includes/functions.php';

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($sigHeader)) {
    http_response_code(400);
    echo "Firma Stripe mancante.";
    exit;
}

$parts = explode(',', $sigHeader);
$timestamp = 0;
$signatures = [];

foreach ($parts as $part) {
    $subparts = explode('=', $part, 2);
    if (count($subparts) === 2) {
        $key = trim($subparts[0]);
        $val = trim($subparts[1]);
        if ($key === 't') {
            $timestamp = (int)$val;
        } elseif ($key === 'v1') {
            $signatures[] = $val;
        }
    }
}

if ($timestamp === 0 || empty($signatures)) {
    http_response_code(400);
    echo "Formato firma non valido.";
    exit;
}

if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    echo "Firma scaduta.";
    exit;
}

$signedPayload = $timestamp . '.' . $payload;
$expectedSignature = hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET);

$verified = false;
foreach ($signatures as $signature) {
    if (hash_equals($expectedSignature, $signature)) {
        $verified = true;
        break;
    }
}

if (!$verified) {
    http_response_code(400);
    echo "Verifica firma fallita.";
    exit;
}

$event = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo "Payload JSON non valido.";
    exit;
}

$eventType = $event['type'] ?? '';

if ($eventType === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $userId = (int)($session['client_reference_id'] ?? 0);
    
    if ($userId > 0) {
        $stmt = $mysqli->prepare("UPDATE utenti SET is_premium = 1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            error_log("[Stripe Webhook] Utente ID {$userId} attivato come PREMIUM.");
            
            // Assegna il badge custom ID 5 (Premium Badge) se non già assegnato
            $stmtBadge = $mysqli->prepare("
                INSERT INTO user_custom_badges (utente_id, badge_id, is_visible)
                SELECT ?, 5, 1
                FROM DUAL
                WHERE NOT EXISTS (
                    SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = 5
                )
            ");
            if ($stmtBadge) {
                $stmtBadge->bind_param('ii', $userId, $userId);
                $stmtBadge->execute();
                $stmtBadge->close();
                error_log("[Stripe Webhook] Assegnato badge ID 5 all'utente ID {$userId}.");
            }

            // Bonus Premium: aggiungi 200.000 soldi per pullare
            $stmtSoldi = $mysqli->prepare("UPDATE utenti SET soldi = soldi + 200000 WHERE id = ?");
            if ($stmtSoldi) {
                $stmtSoldi->bind_param('i', $userId);
                $stmtSoldi->execute();
                $stmtSoldi->close();
                error_log("[Stripe Webhook] Aggiunti 200.000 soldi bonus all'utente ID {$userId}.");
            }

            // Gestione Regalo: salva in premium_gifts e invia l'email se applicabile
            $isGift = isset($session['metadata']['is_gift']) && (int)$session['metadata']['is_gift'] === 1;
            $buyerId = isset($session['metadata']['buyer_id']) ? (int)$session['metadata']['buyer_id'] : 0;
            $paymentOrderId = $session['id'] ?? null;

            if ($isGift && $buyerId > 0 && $buyerId !== $userId) {
                // Registra il regalo nella tabella premium_gifts
                $stmtGift = $mysqli->prepare("INSERT INTO premium_gifts (sender_id, recipient_id, payment_gateway, payment_order_id) VALUES (?, ?, 'stripe', ?)");
                if ($stmtGift) {
                    $stmtGift->bind_param("iis", $buyerId, $userId, $paymentOrderId);
                    $stmtGift->execute();
                    $stmtGift->close();
                }

                // Trova gli username e l'email del destinatario
                $senderUsername = 'Un utente';
                $recipientUsername = '';
                $recipientEmail = '';

                // Mittente
                $stmtSender = $mysqli->prepare("SELECT username FROM utenti WHERE id = ? LIMIT 1");
                if ($stmtSender) {
                    $stmtSender->bind_param("i", $buyerId);
                    $stmtSender->execute();
                    $resSender = $stmtSender->get_result()->fetch_assoc();
                    if ($resSender) {
                        $senderUsername = $resSender['username'];
                    }
                    $stmtSender->close();
                }

                // Destinatario
                $stmtRecipient = $mysqli->prepare("SELECT username, email FROM utenti WHERE id = ? LIMIT 1");
                if ($stmtRecipient) {
                    $stmtRecipient->bind_param("i", $userId);
                    $stmtRecipient->execute();
                    $resRecipient = $stmtRecipient->get_result()->fetch_assoc();
                    if ($resRecipient) {
                        $recipientUsername = $resRecipient['username'];
                        $recipientEmail = $resRecipient['email'];
                    }
                    $stmtRecipient->close();
                }

                if (!empty($recipientEmail)) {
                    sendPremiumGiftEmail($recipientEmail, $recipientUsername, $senderUsername);
                }
            }
        } else {
            http_response_code(500);
            echo "Errore database query.";
            exit;
        }
    }
}

http_response_code(200);
echo "Ok";
