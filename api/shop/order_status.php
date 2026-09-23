<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/shop/gacha_catalog.php';

/**
 * Stato di un acquisto di Shards appena pagato con Stripe, per la pagina
 * dello shop che aspetta il webhook ("accredito in corso...").
 *
 * Solo lettura: non accredita niente e non parla con Stripe. Risponde anche
 * con il saldo attuale, cosi' la pagina lo aggiorna senza ricaricarsi.
 *
 * GET ?session_id=cs_...
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$sessionId = (string)($_GET['session_id'] ?? '');

// La sessione PHP non serve piu': la pagina chiede ogni due secondi e non
// deve bloccare le altre richieste dello stesso utente.
session_write_close();

if (!preg_match('/^cs_[A-Za-z0-9_]{8,250}$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$state = 'sconosciuto';
$credited = null;
$bonus = false;

if (gacha_orders_ready($mysqli)) {
    try {
        // Solo gli ordini di chi chiede: l'id della sessione Stripe non basta
        // per vedere l'acquisto di qualcun altro.
        $stmt = $mysqli->prepare(
            "SELECT stato, shards_accreditate, bonus FROM shop_ordini
             WHERE provider = 'stripe' AND provider_ref = ? AND user_id = ? LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('si', $sessionId, $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $state = (string)$row['stato'];
                $credited = $row['shards_accreditate'] !== null ? (int)$row['shards_accreditate'] : null;
                $bonus = (int)$row['bonus'] === 1;
            }
        }
    } catch (Throwable $e) {
        error_log('[order_status] ' . $e->getMessage());
    }
}

$balance = ['soldi' => null, 'shards' => null];
try {
    $stmt = $mysqli->prepare('SELECT soldi, godoshards_balance FROM utenti WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $balance = ['soldi' => (int)$row['soldi'], 'shards' => (int)$row['godoshards_balance']];
        }
    }
} catch (Throwable $e) {
    error_log('[order_status] saldo: ' . $e->getMessage());
}

echo json_encode([
    'ok' => true,
    'stato' => $state,
    'shards_accreditate' => $credited,
    'bonus' => $bonus,
    'soldi' => $balance['soldi'],
    'shards' => $balance['shards'],
]);
