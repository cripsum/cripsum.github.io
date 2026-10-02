<?php
/**
 * Posta del sito: elenco e azioni.
 *
 * GET  ?category=&status=&q=&before=&limit=   una pagina di messaggi
 *      ?id=N                                  un messaggio solo
 * POST { action, message_id | ids[], category? }
 *      read, unread, toggle_important, toggle_archive, archive, delete,
 *      claim_rewards, read_all, claim_all
 *
 * La logica sta in includes/inbox_core.php. I nomi delle azioni di prima
 * (read, toggle_important, toggle_archive, claim_rewards, delete) rispondono
 * come prima.
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inbox_core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$reply = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
};

if (!isLoggedIn()) {
    $reply(['ok' => false, 'error' => rt_t('Non autenticato.', 'Not signed in.')], 401);
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

try {
    if ($method === 'GET') {
        cripsum_release_session();

        $filters = [
            'category' => (string)($_GET['category'] ?? ''),
            'status' => (string)($_GET['status'] ?? ''),
            'q' => (string)($_GET['q'] ?? ''),
            'id' => (int)($_GET['id'] ?? 0),
        ];
        $page = ib_list($mysqli, $userId, $filters, (int)($_GET['limit'] ?? IB_PAGE), (int)($_GET['before'] ?? 0));

        $reply([
            'ok' => true,
            'messages' => $page['messages'],
            'has_more' => $page['has_more'],
            'counts' => ib_counts($mysqli, $userId),
            'unread_count' => (int)getUnreadMessagesCount($mysqli, $userId),
        ]);
    }

    if ($method !== 'POST') {
        $reply(['ok' => false, 'error' => rt_t('Metodo non consentito.', 'Method not allowed.')], 405);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Ogni azione qui cambia qualcosa (riscatta premi, archivia, elimina):
    // senza token una pagina esterna poteva farle fare all'utente.
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
    if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
        $reply(['ok' => false, 'error' => rt_t('Sessione scaduta. Ricarica la pagina.', 'Session expired. Reload the page.'), 'code' => 'CSRF_FAILED'], 419);
    }

    $action = (string)($input['action'] ?? '');
    $ids = [];
    if (isset($input['ids']) && is_array($input['ids'])) {
        $ids = $input['ids'];
    } elseif (!empty($input['message_id'])) {
        $ids = [(int)$input['message_id']];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $first = $ids[0] ?? 0;

    $done = static function (array $extra = []) use ($mysqli, $userId, $reply): void {
        // Le altre schede aperte riallineano i loro numeri.
        rt_push_user($userId, ['t' => 'ls']);
        $reply(['ok' => true] + $extra + [
            'counts' => ib_counts($mysqli, $userId),
            'unread_count' => (int)getUnreadMessagesCount($mysqli, $userId),
        ]);
    };

    if ($action === 'read_all') {
        $done(['updated' => ib_read_all($mysqli, $userId, (string)($input['category'] ?? ''))]);
    }

    if ($action === 'claim_all') {
        $done(ib_claim_all($mysqli, $userId));
    }

    if (!$ids) {
        $reply(['ok' => false, 'error' => rt_t('ID messaggio mancante o non valido.', 'Missing or invalid message ID.')], 422);
    }

    $state = static function () use ($mysqli, $userId, $first): array {
        $stmt = $mysqli->prepare('SELECT is_important, is_archived FROM site_message_recipients WHERE recipient_id = ? AND message_id = ?');
        $stmt->bind_param('ii', $userId, $first);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: ['is_important' => 0, 'is_archived' => 0];
        $stmt->close();
        return $row;
    };

    switch ($action) {
        case 'read':
        case 'unread':
        case 'archive':
        case 'unarchive':
        case 'star':
        case 'unstar':
        case 'delete':
            $done(['updated' => ib_update($mysqli, $userId, $ids, $action)]);
            // no break

        case 'toggle_important':
            ib_update($mysqli, $userId, [$first], (int)$state()['is_important'] === 1 ? 'unstar' : 'star');
            $done(['is_important' => (int)$state()['is_important']]);
            // no break

        case 'toggle_archive':
            ib_update($mysqli, $userId, [$first], (int)$state()['is_archived'] === 1 ? 'unarchive' : 'archive');
            $done(['is_archived' => (int)$state()['is_archived']]);
            // no break

        case 'claim_rewards':
            $result = claimMessageRewards($mysqli, $userId, $first);
            if (empty($result['ok'])) {
                $reply($result, 200);
            }
            $done($result);
            // no break

        default:
            $reply(['ok' => false, 'error' => rt_t('Azione non supportata.', 'Unsupported action.')], 422);
    }
} catch (Throwable $e) {
    error_log('[api/inbox] ' . $e->getMessage());
    $reply(['ok' => false, 'error' => rt_t('Qualcosa è andato storto. Riprova.', 'Something went wrong. Try again.')], 500);
}
