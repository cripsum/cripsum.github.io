<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) cv2_ok();

    if (cv2_table_exists($mysqli, 'content_views')) {
        $userId = (int)($currentUser['id'] ?? 0) ?: null;
        $ip = cv2_client_ip();

        $stmt = $mysqli->prepare("INSERT IGNORE INTO content_views (content_type, post_id, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('siis', $type, $id, $userId, $ip);
            $stmt->execute();
            $inserted = $stmt->affected_rows > 0;
            $stmt->close();

            if (!$inserted) cv2_ok();
        }
    }

    if (cv2_column_exists($mysqli, $meta['table'], 'views')) {
        $table = cv2_qcol($meta['table']);
        $stmt = $mysqli->prepare("UPDATE $table SET `views` = COALESCE(`views`, 0) + 1 WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    cv2_ok();
} catch (Throwable $e) {
    cv2_ok();
}
