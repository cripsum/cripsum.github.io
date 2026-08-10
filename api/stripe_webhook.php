<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe_config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo non consentito.');
}

if (STRIPE_WEBHOOK_SECRET === '') {
    error_log('Stripe webhook disabled: STRIPE_WEBHOOK_SECRET is missing.');
    http_response_code(503);
    exit('Webhook non configurato.');
}

function stripe_claim_event(mysqli $mysqli, string $eventId, string $eventType): bool
{
    $stmt = $mysqli->prepare(
        "INSERT IGNORE INTO payment_webhook_events (event_id, provider, event_type, processing_started_at)
         VALUES (?, 'stripe', ?, NOW())"
    );
    if (!$stmt) throw new RuntimeException('Tabella idempotenza pagamenti non disponibile.');
    $stmt->bind_param('ss', $eventId, $eventType);
    if (!$stmt->execute()) throw new RuntimeException('Impossibile registrare evento pagamento.');
    $claimed = $stmt->affected_rows === 1;
    $stmt->close();
    return $claimed;
}

function stripe_complete_event(mysqli $mysqli, string $eventId): void
{
    $stmt = $mysqli->prepare(
        "UPDATE payment_webhook_events SET processed_at = NOW() WHERE event_id = ? AND provider = 'stripe'"
    );
    if (!$stmt) throw new RuntimeException('Impossibile completare evento pagamento.');
    $stmt->bind_param('s', $eventId);
    if (!$stmt->execute()) throw new RuntimeException('Impossibile completare evento pagamento.');
    $stmt->close();
}

function stripe_release_event(mysqli $mysqli, string $eventId): void
{
    $stmt = $mysqli->prepare(
        "DELETE FROM payment_webhook_events WHERE event_id = ? AND provider = 'stripe' AND processed_at IS NULL"
    );
    if (!$stmt) return;
    $stmt->bind_param('s', $eventId);
    $stmt->execute();
    $stmt->close();
}

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

$eventId = (string)($event['id'] ?? '');
if (!preg_match('/^evt_[a-zA-Z0-9_]{8,250}$/', $eventId)) {
    http_response_code(400);
    exit('ID evento non valido.');
}

$eventType = $event['type'] ?? '';

if ($eventType === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $metadata = $session['metadata'] ?? [];
    $purchaseType = $metadata['type'] ?? 'premium';

    if (($session['payment_status'] ?? '') !== 'paid' || strtolower((string)($session['currency'] ?? '')) !== 'eur') {
        http_response_code(400);
        exit('Pagamento non completato.');
    }

    try {
        if (!stripe_claim_event($mysqli, $eventId, $eventType)) {
            http_response_code(200);
            exit('Evento già elaborato.');
        }
    } catch (Throwable $e) {
        error_log('[Stripe Webhook] Idempotency unavailable: ' . $e->getMessage());
        http_response_code(500);
        exit('Migrazione pagamenti richiesta.');
    }

    if ($purchaseType === 'shards') {
        $userId = (int)($metadata['user_id'] ?? 0);
        $packageId = $metadata['package_id'] ?? '';

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

        if ($userId <= 0 || !isset($packages[$packageId])) {
            stripe_release_event($mysqli, $eventId);
            http_response_code(400);
            exit('Metadati pacchetto non validi.');
        }

        $expectedAmount = (int)round($packages[$packageId]['price'] * 100);
        if ((int)($session['amount_total'] ?? -1) !== $expectedAmount) {
            stripe_release_event($mysqli, $eventId);
            http_response_code(400);
            exit('Importo pagamento non valido.');
        }

        if ($userId > 0 && isset($packages[$packageId])) {
            $package = $packages[$packageId];
            $baseShards = $package['shards'];

            $mysqli->begin_transaction();
            try {
                // Serialize all value credits for the same account so two
                // simultaneous purchases cannot both receive a first-buy bonus.
                $stmtUserLock = $mysqli->prepare('SELECT id FROM utenti WHERE id = ? LIMIT 1 FOR UPDATE');
                if (!$stmtUserLock) throw new RuntimeException('Blocco utente non disponibile.');
                $stmtUserLock->bind_param('i', $userId);
                if (!$stmtUserLock->execute()) throw new RuntimeException('Blocco utente non riuscito.');
                $userExists = (bool)$stmtUserLock->get_result()->fetch_row();
                $stmtUserLock->close();
                if (!$userExists) throw new RuntimeException('Utente pagamento non trovato.');

                // Check if first purchase bonus is used
                $stmtBonus = $mysqli->prepare("
                    SELECT first_purchase_bonus_used 
                    FROM first_purchase_bonuses 
                    WHERE user_id = ? AND package_id = ? 
                    LIMIT 1 FOR UPDATE
                ");
                if (!$stmtBonus) throw new RuntimeException('Verifica bonus non disponibile.');
                $stmtBonus->bind_param('is', $userId, $packageId);
                if (!$stmtBonus->execute()) throw new RuntimeException('Verifica bonus non riuscita.');
                $resBonus = $stmtBonus->get_result();
                $bonusRow = $resBonus->fetch_assoc();
                $stmtBonus->close();

                $isFirstPurchase = !$bonusRow || (int)($bonusRow['first_purchase_bonus_used'] ?? 0) === 0;
                $finalShards = $isFirstPurchase ? ($baseShards * 2) : $baseShards;

                // Credit Godo Shards
                $stmtCredit = $mysqli->prepare("UPDATE utenti SET godoshards_balance = godoshards_balance + ? WHERE id = ?");
                if (!$stmtCredit) throw new RuntimeException('Accredito non disponibile.');
                $stmtCredit->bind_param('ii', $finalShards, $userId);
                if (!$stmtCredit->execute()) throw new RuntimeException('Accredito non riuscito.');
                $stmtCredit->close();

                // Save first purchase bonus as used
                $stmtSaveBonus = $mysqli->prepare("
                    INSERT INTO first_purchase_bonuses (user_id, package_id, first_purchase_bonus_used) 
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE first_purchase_bonus_used = 1
                ");
                if (!$stmtSaveBonus) throw new RuntimeException('Salvataggio bonus non disponibile.');
                $stmtSaveBonus->bind_param('is', $userId, $packageId);
                if (!$stmtSaveBonus->execute()) throw new RuntimeException('Salvataggio bonus non riuscito.');
                $stmtSaveBonus->close();

                // Record activity
                if (function_exists('profile_record_activity')) {
                    $activityMsg = "Acquistato pacchetto {$packageId} (" . ($isFirstPurchase ? ($baseShards . "x2") : $baseShards) . " Godo Shards)";
                    profile_record_activity($mysqli, $userId, 'shards_purchase', $activityMsg);
                }

                stripe_complete_event($mysqli, $eventId);
                $mysqli->commit();
                error_log("[Stripe Webhook] Accreditate {$finalShards} Shards all'utente ID {$userId} (Bonus x2: " . ($isFirstPurchase ? 'SI' : 'NO') . ")");
            } catch (Exception $e) {
                $mysqli->rollback();
                stripe_release_event($mysqli, $eventId);
                error_log("[Stripe Webhook] Errore accreditamento shards: " . $e->getMessage());
                http_response_code(500);
                echo "Errore database.";
                exit;
            }
        }
    } else {
        $userId = (int)($session['client_reference_id'] ?? 0);
        if ((int)($session['amount_total'] ?? -1) !== 299) {
            stripe_release_event($mysqli, $eventId);
            http_response_code(400);
            exit('Importo pagamento non valido.');
        }
        if ($userId > 0) {
            $stmt = $mysqli->prepare("UPDATE utenti SET is_premium = 1 WHERE id = ? AND is_premium = 0");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $premiumActivated = $stmt->affected_rows === 1;
                $stmt->close();

                if (!$premiumActivated) {
                    stripe_complete_event($mysqli, $eventId);
                    http_response_code(200);
                    exit('Premium già attivo.');
                }
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

                // Bonus Premium: aggiungi 25.000 soldi per pullare
                $stmtSoldi = $mysqli->prepare("UPDATE utenti SET soldi = soldi + 25000 WHERE id = ?");
                if ($stmtSoldi) {
                    $stmtSoldi->bind_param('i', $userId);
                    $stmtSoldi->execute();
                    $stmtSoldi->close();
                    error_log("[Stripe Webhook] Aggiunti 25.000 soldi bonus all'utente ID {$userId}.");
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

                    // Assegna il badge donatore (ID 11) al mittente (buyerId) se non già presente
                    $stmtGiverBadge = $mysqli->prepare("
                    INSERT INTO user_custom_badges (utente_id, badge_id, is_visible)
                    SELECT ?, 11, 1
                    FROM DUAL
                    WHERE NOT EXISTS (
                        SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = 11
                    )
                ");
                    if ($stmtGiverBadge) {
                        $stmtGiverBadge->bind_param("ii", $buyerId, $buyerId);
                        $stmtGiverBadge->execute();
                        $stmtGiverBadge->close();
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

                    // Invia notifica inbox premium regalo
                    sendPremiumUpgradeNotification($mysqli, $userId, $senderUsername);
                } else {
                    // Invia notifica inbox premium acquisto personale
                    sendPremiumUpgradeNotification($mysqli, $userId, null);
                }

                stripe_complete_event($mysqli, $eventId);
            } else {
                stripe_release_event($mysqli, $eventId);
                http_response_code(500);
                echo "Errore database query.";
                exit;
            }
        } else {
            stripe_release_event($mysqli, $eventId);
            http_response_code(400);
            exit('Utente pagamento non valido.');
        }
    }
}

http_response_code(200);
echo "Ok";
