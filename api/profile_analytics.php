<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/profile_v3_helpers.php';

checkBan($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) $payload = $_POST;

    $profileId = (int)($payload['profile_id'] ?? 0);
    $eventType = profile_allowed_value((string)($payload['event_type'] ?? 'click'), ['click', 'share', 'qr', 'contact'], 'click');
    $linkId = isset($payload['link_id']) ? (int)$payload['link_id'] : null;

    if ($profileId <= 0) {
        profile_json_response(['ok' => false, 'message' => 'Missing profile.'], 400);
    }

    if (!profile_v3_rate_limit($mysqli, 'analytics_' . $eventType, $eventType === 'contact' ? 5 : 90, 300, profile_current_user_id())) {
        profile_json_response(['ok' => false, 'message' => 'Too many events.'], 429);
    }

    if ($linkId) {
        $stmt = $mysqli->prepare('SELECT id FROM utenti_links WHERE id = ? AND utente_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $linkId, $profileId);
            $stmt->execute();
            $ok = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$ok) $linkId = null;
        }
    }

    $metadata = null;
    if (!empty($payload['metadata']) && is_array($payload['metadata'])) {
        $metadata = json_encode(array_slice($payload['metadata'], 0, 20, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    profile_v3_track_event($mysqli, $profileId, $eventType, $linkId, $metadata);

    if ($eventType === 'click' && $linkId && profile_v3_column_exists($mysqli, 'utenti_links', 'click_count')) {
        $stmt = $mysqli->prepare('UPDATE utenti_links SET click_count = click_count + 1 WHERE id = ? AND utente_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $linkId, $profileId);
            $stmt->execute();
            $stmt->close();
        }
    }

    profile_json_response(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    profile_json_response(['ok' => false, 'message' => 'Invalid method.'], 405);
}

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Login required.'], 401);
}

$profileId = (int)($_GET['profile_id'] ?? $_SESSION['user_id'] ?? 0);
if ($profileId <= 0 || !profile_can_edit($profileId)) {
    profile_json_response(['ok' => false, 'message' => 'Access denied.'], 403);
}

if (!profile_v3_table_exists($mysqli, 'profile_analytics_events')) {
    $profile = profile_get_edit_profile($mysqli, $profileId);
    profile_json_response([
        'ok' => true,
        'summary' => ['views' => (int)($profile['profile_views'] ?? 0), 'clicks' => 0, 'reactions' => 0],
        'days' => [],
        'referrers' => [],
        'devices' => [],
        'links' => [],
    ]);
}

$days = [];
$stmt = $mysqli->prepare("
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM profile_analytics_events
    WHERE profile_id = ? AND event_type = 'view' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$stmt->bind_param('i', $profileId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();
$map = [];
foreach ($rows as $row) $map[(string)$row['day']] = (int)$row['total'];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime('-' . $i . ' days'));
    $days[] = ['day' => $day, 'total' => $map[$day] ?? 0];
}

$summary = ['views' => 0, 'clicks' => 0, 'reactions' => 0];
$stmt = $mysqli->prepare("SELECT event_type, COUNT(*) AS total FROM profile_analytics_events WHERE profile_id = ? GROUP BY event_type");
$stmt->bind_param('i', $profileId);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();
foreach ($events as $event) {
    if ($event['event_type'] === 'view') $summary['views'] = (int)$event['total'];
    if ($event['event_type'] === 'click') $summary['clicks'] = (int)$event['total'];
    if ($event['event_type'] === 'reaction') $summary['reactions'] = (int)$event['total'];
}

$referrers = [];
$stmt = $mysqli->prepare("SELECT referrer, COUNT(*) AS total FROM profile_analytics_events WHERE profile_id = ? AND referrer IS NOT NULL AND referrer != '' GROUP BY referrer ORDER BY total DESC LIMIT 12");
$stmt->bind_param('i', $profileId);
$stmt->execute();
$refRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();
foreach ($refRows as $row) {
    $host = parse_url((string)$row['referrer'], PHP_URL_HOST) ?: 'direct';
    $host = preg_replace('/^www\./i', '', $host);
    $referrers[$host] = ($referrers[$host] ?? 0) + (int)$row['total'];
}
arsort($referrers);
$referrers = array_map(fn($host, $total) => ['label' => $host, 'total' => $total], array_keys($referrers), array_values($referrers));

$devices = [];
$stmt = $mysqli->prepare("SELECT device_type, COUNT(*) AS total FROM profile_analytics_events WHERE profile_id = ? GROUP BY device_type ORDER BY total DESC");
$stmt->bind_param('i', $profileId);
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();

$links = [];
$stmt = $mysqli->prepare("
    SELECT l.id, l.title, COUNT(e.id) AS total
    FROM utenti_links l
    LEFT JOIN profile_analytics_events e ON e.link_id = l.id AND e.event_type = 'click'
    WHERE l.utente_id = ?
    GROUP BY l.id, l.title
    ORDER BY total DESC, l.sort_order ASC
    LIMIT 12
");
$stmt->bind_param('i', $profileId);
$stmt->execute();
$links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$stmt->close();

profile_json_response([
    'ok' => true,
    'summary' => $summary,
    'days' => $days,
    'referrers' => array_slice($referrers, 0, 8),
    'devices' => $devices,
    'links' => $links,
]);
