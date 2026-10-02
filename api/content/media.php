<?php
// Media dei post: ?type=shitpost|rimasto &id=<post> [&n=<posizione>] [&v=thumb|poster]
//
// Le richieste ripetute per un post approvato si chiudono qui: niente
// sessione e niente database. Vedi includes/content_media_cache.php.
require_once __DIR__ . '/../../includes/content_media_cache.php';

$mediaType = (string)($_GET['type'] ?? 'shitpost');
$mediaId = (int)($_GET['id'] ?? 0);
$mediaN = max(0, (int)($_GET['n'] ?? 0));
$mediaVariant = (string)($_GET['v'] ?? '');

content_media_cache_serve($mediaType, $mediaId, $mediaN, $mediaVariant);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/community/media.php';

// Chi chiede è già noto: la sessione si lascia libera, un video può durare.
cripsum_release_session();

try {
    cm_media_respond($mysqli, cv2_normalize_type($mediaType), $mediaId, $mediaN, $mediaVariant, $currentUser);
} catch (Throwable $e) {
    error_log('[community] media: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
