<?php
/**
 * search_users.php
 * Endpoint AJAX per la ricerca utenti in tempo reale.
 * Posizionarlo in: /includes/search_users.php
 *
 * Risponde a GET ?q=<query>
 * Restituisce un JSON array di utenti corrispondenti (max 6).
 */

session_start();
require_once __DIR__ . '/../config/db.php'; // Adatta il path alla tua connessione $mysqli

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// — Sicurezza: solo richieste AJAX —
if (
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// — Validazione input —
$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

if (mb_strlen($q) > 30) {
    echo json_encode(['error' => 'Query troppo lunga']);
    exit;
}

// — Rate limiting base: max 30 richieste/minuto per IP —
$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$key = 'search_rl_' . md5($ip);

if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = ['count' => 0, 'start' => time()];
}

if (time() - $_SESSION[$key]['start'] > 60) {
    $_SESSION[$key] = ['count' => 0, 'start' => time()];
}

$_SESSION[$key]['count']++;

if ($_SESSION[$key]['count'] > 30) {
    http_response_code(429);
    echo json_encode(['error' => 'Troppe richieste, riprova tra poco']);
    exit;
}

// — Query al DB —
$search = '%' . $q . '%';

$stmt = $mysqli->prepare("
    SELECT id, username, ruolo
    FROM utenti
    WHERE username LIKE ?
      AND isBannato = 0
    ORDER BY 
        CASE WHEN username LIKE ? THEN 0 ELSE 1 END, -- priorità match esatto all'inizio
        username ASC
    LIMIT 6
");

$startsWith = $q . '%'; // per il CASE di priorità
$stmt->bind_param('ss', $search, $startsWith);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id'       => (int) $row['id'],
        'username' => $row['username'],
        'ruolo'    => $row['ruolo'],
        'pfp'      => '/includes/get_pfp.php?id=' . (int) $row['id'],
    ];
}

$stmt->close();

echo json_encode($users, JSON_UNESCAPED_UNICODE);
exit;
