<?php
/**
 * Stato della relazione con uno o più utenti (`target_id` oppure
 * `user_ids=1,2,3`, al massimo 50).
 */
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $ids = [];
    if (!empty($_GET['target_id'])) {
        $ids[] = (int)$_GET['target_id'];
    } elseif (isset($_GET['user_ids'])) {
        foreach (explode(',', (string)$_GET['user_ids']) as $part) {
            $ids[] = (int)trim($part);
        }
    }

    $ids = array_slice(array_values(array_unique(array_filter($ids, static fn($id) => $id > 0 && $id !== $userId))), 0, 50);

    $relations = [];
    foreach ($ids as $id) {
        $relations[$id] = sc_relationship($mysqli, $userId, $id);
    }

    send_api_success(['relations' => $relations]);
});
