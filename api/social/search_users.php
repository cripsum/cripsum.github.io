<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $query = trim((string)($_GET['q'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 20);
    send_api_success(['users' => sc_search($mysqli, $userId, $query, $limit)]);
});
