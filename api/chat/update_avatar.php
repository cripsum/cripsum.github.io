<?php
/** Immagine di un gruppo. La può cambiare chi può modificare le informazioni del gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ChatError(rt_t('Metodo non consentito.', 'Method not allowed.'), 405);
    }

    $chatId = (int)($_POST['chat_id'] ?? 0);
    $member = cg_require($mysqli, $chatId, $userId);
    if (!cg_can_edit_info($mysqli, $member)) {
        throw new ChatError(rt_t('Non puoi modificare questo gruppo.', 'You cannot edit this group.'), 403);
    }

    $file = $_FILES['avatar'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        throw new ChatError(rt_t('Caricamento non riuscito.', 'Upload failed.'), 422);
    }
    if ((int)$file['size'] > 5 * 1024 * 1024) {
        throw new ChatError(rt_t('L\'immagine non può superare i 5 MB.', 'The image cannot exceed 5 MB.'), 422);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string)finfo_file($finfo, (string)$file['tmp_name']);
    finfo_close($finfo);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime]) || @getimagesize((string)$file['tmp_name']) === false) {
        throw new ChatError(rt_t('Il file non è un\'immagine valida.', 'The file is not a valid image.'), 422);
    }

    $dir = __DIR__ . '/../../uploads/chat_avatars/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new ChatError(rt_t('Impossibile salvare l\'immagine.', 'Could not save the image.'), 500);
    }
    $name = 'group_' . $chatId . '_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . $name)) {
        throw new ChatError(rt_t('Impossibile salvare l\'immagine.', 'Could not save the image.'), 500);
    }
    cc_strip_image_metadata($dir . $name, $mime);

    $path = '/uploads/chat_avatars/' . $name;
    $previous = cg_safe_avatar($member['avatar_url']);

    $stmt = $mysqli->prepare('UPDATE chats SET avatar_url = ? WHERE id = ?');
    $stmt->bind_param('si', $path, $chatId);
    $stmt->execute();
    $stmt->close();

    // L'immagine sostituita non serve più a nessuno.
    if ($previous && is_file(__DIR__ . '/../..' . $previous)) {
        @unlink(__DIR__ . '/../..' . $previous);
    }

    $messageId = cg_system($mysqli, $chatId, 'avatar', ['username' => cg_username($mysqli, $userId)]);
    cg_signal($mysqli, $chatId, ['t' => 'gm', 'm' => $messageId, 'f' => 0, 'q' => 1]);
    cg_signal($mysqli, $chatId, ['t' => 'gx']);

    send_success(['avatar_url' => $path]);
});
