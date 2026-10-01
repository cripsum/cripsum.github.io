<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    send_api_success(['suggestions' => sc_suggestions($mysqli, $userId, 12)]);
});
