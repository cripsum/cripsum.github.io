<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/cursor_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_json_response(['ok' => false, 'message' => 'Metodo non consentito.'], 405);
}

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Devi essere loggato per caricare file.'], 401);
}

if (!profile_validate_csrf($_POST['csrf_token'] ?? null)) {
    profile_json_response(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.'], 419);
}

$currentUserId = (int)$_SESSION['user_id'];

// Staff editing another profile must upload into THAT profile's folder,
// otherwise the file lives under the staff member's id and the media garbage
// collector treats it as orphaned the next time the staff member saves.
$requestTargetUserId = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
$userId = ($requestTargetUserId > 0 && profile_is_staff()) ? $requestTargetUserId : $currentUserId;

if (!profile_can_edit($userId)) {
    profile_json_response(['ok' => false, 'message' => 'Non puoi modificare questo profilo.'], 403);
}

// Authentication is done; nothing below writes to the session. Release the lock
// so the upload does not block (or get blocked by) the editor's draft saves and
// preview reloads happening at the same time.
cripsum_release_session();

// Get target profile to check premium status
$profile = profile_get_edit_profile($mysqli, $userId);
if (!$profile || (int)($profile['is_premium'] ?? 0) !== 1) {
    echo json_encode(['ok' => false, 'message' => 'Questa funzionalità richiede un account Premium.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMessages = [
        UPLOAD_ERR_INI_SIZE => 'Il file supera il limite di upload del server.',
        UPLOAD_ERR_FORM_SIZE => 'Il file supera il limite consentito dal modulo.',
        UPLOAD_ERR_PARTIAL => 'Il caricamento si è interrotto. Riprova.',
        UPLOAD_ERR_NO_FILE => 'Nessun file selezionato.',
        UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea non disponibile sul server.',
        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file sul server.',
        UPLOAD_ERR_EXTENSION => 'Caricamento bloccato da un\'estensione del server.',
    ];
    $message = $errMessages[$errCode] ?? ('Errore di caricamento (codice ' . (int)$errCode . ').');
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

$file = $_FILES['file'];

// `cursor`: immagini, GIF animate e .cur/.ani (vedi includes/cursor_helpers.php).
// `block`: i media dei blocchi custom, che possono essere anche video.
// Qualsiasi altro valore: solo immagini (icone, copertine, miniature).
$purpose = trim($_POST['purpose'] ?? '');

$allowedVideoMimes = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
];

$tmpPath = $file['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    echo json_encode(['ok' => false, 'message' => 'File non valido.']);
    exit;
}

// Il tipo si legge prima del limite di peso: il limite dei video vale solo
// per i file che sono davvero video.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpPath);
finfo_close($finfo);
$isBlockVideo = $purpose === 'block' && array_key_exists($mimeType, $allowedVideoMimes);

if ($purpose === 'cursor') {
    $maxBytes = 2 * 1024 * 1024; // 2 MB for cursors
} elseif ($isBlockVideo) {
    $maxBytes = 50 * 1024 * 1024; // 50 MB, come gli sfondi video
} else {
    $maxBytes = 25 * 1024 * 1024; // 25 MB
}

if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
    $maxMb = profile_format_bytes($maxBytes);
    echo json_encode(['ok' => false, 'message' => "Il file è troppo pesante. Il limite massimo è {$maxMb}."]);
    exit;
}

// Check mime type / extension
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif'
];

$origName = strtolower($file['name']);
$origExt = pathinfo($origName, PATHINFO_EXTENSION);

$ext = '';

if ($purpose === 'cursor') {
    $allowedCursorMimes = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/x-icon' => 'cur',
        'image/vnd.microsoft.icon' => 'cur',
        'application/octet-stream' => 'octet', // verified by ext
        'application/x-navi-animation' => 'ani'
    ];

    if (!array_key_exists($mimeType, $allowedCursorMimes)) {
        if (!in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'cur', 'ani'])) {
            echo json_encode(['ok' => false, 'message' => 'Formato file non supportato per il cursore. Formati validi: JPG, PNG, WEBP, GIF, CUR, ANI.']);
            exit;
        }
    }

    // Determine working extension
    if (in_array($origExt, ['cur', 'ani'])) {
        $ext = $origExt;
    } elseif (in_array($mimeType, ['image/x-icon', 'image/vnd.microsoft.icon'])) {
        $ext = 'cur';
    } elseif ($mimeType === 'application/x-navi-animation') {
        $ext = 'ani';
    } elseif ($mimeType === 'application/octet-stream') {
        if ($origExt === 'cur' || $origExt === 'ani') {
            $ext = $origExt;
        } else {
            echo json_encode(['ok' => false, 'message' => 'Mime type generico non supportato per questa estensione.']);
            exit;
        }
    } else {
        $ext = 'png'; // resized images will be png
    }
} elseif ($isBlockVideo) {
    // Il contenitore dichiarato dal nome deve combaciare con quello letto dal
    // file: un .mp4 che in realta' e' altro non passa.
    if (!in_array($origExt, ['mp4', 'webm'], true)) {
        echo json_encode(['ok' => false, 'message' => 'Formato video non supportato. Formati validi: MP4, WEBM.']);
        exit;
    }
    $ext = $allowedVideoMimes[$mimeType];
} else {
    if (!array_key_exists($mimeType, $allowedMimes)) {
        $message = $purpose === 'block'
            ? 'Formato file non supportato. Formati validi: JPG, PNG, WEBP, GIF, MP4, WEBM.'
            : 'Formato file non supportato. Formati validi: JPG, PNG, WEBP, GIF.';
        echo json_encode(['ok' => false, 'message' => $message]);
        exit;
    }
    $ext = $allowedMimes[$mimeType];
}

// Create target directory
$uploadDir = __DIR__ . '/../uploads/profile_media/user_' . $userId;
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['ok' => false, 'message' => 'Impossibile creare la cartella di destinazione. Contattare l\'amministratore.']);
        exit;
    }
}

// Generate randomized secure filename
$randomHash = bin2hex(random_bytes(16));
$prefix = ($purpose === 'cursor') ? 'cursor_' : 'media_';
$fileName = $prefix . $randomHash . '.' . $ext;
$targetPath = $uploadDir . '/' . $fileName;

if ($purpose === 'cursor') {
    // Il file tenuto e' l'immagine "madre" (vedi includes/cursor_helpers.php):
    // la misura sul profilo la sceglie chi lo modifica, senza ricaricare.
    if (!cursor_gd_available()) {
        echo json_encode(['ok' => false, 'message' => 'Il server non può elaborare immagini in questo momento.']);
        exit;
    }

    $basePath = $uploadDir . '/cursor_' . $randomHash;
    $result = ['ok' => false, 'error' => 'Impossibile salvare il cursore.'];

    if ($ext === 'cur') {
        $result = cursor_prepare_cur($tmpPath, $basePath . '.png') + ['ext' => 'png'];
    } elseif ($ext === 'ani') {
        $result = cursor_convert_ani_to_gif($tmpPath, $basePath . '.gif');
    } elseif (cursor_is_animated($tmpPath, $mimeType)) {
        // GD non sa ridimensionare le animazioni: si tengono come sono e le
        // rimpicciolisce la pagina.
        $dims = @getimagesize($tmpPath);
        $animatedExt = $mimeType === 'image/webp' ? 'webp' : 'gif';
        if (!$dims || max((int)$dims[0], (int)$dims[1]) > CURSOR_ANIMATED_MAX) {
            $result = ['ok' => false, 'error' => "L'animazione è troppo grande: il massimo è " . CURSOR_ANIMATED_MAX . '×' . CURSOR_ANIMATED_MAX . ' pixel.'];
        } elseif (move_uploaded_file($tmpPath, $basePath . '.' . $animatedExt)) {
            $result = ['ok' => true, 'ext' => $animatedExt, 'animated' => true, 'width' => (int)$dims[0], 'height' => (int)$dims[1]];
        }
    } else {
        $result = cursor_prepare_image($tmpPath, $mimeType, $basePath . '.png') + ['ext' => 'png'];
    }

    if (empty($result['ok'])) {
        echo json_encode(['ok' => false, 'message' => $result['error'] ?? 'Impossibile salvare il cursore.']);
        exit;
    }

    $fileName = 'cursor_' . $randomHash . '.' . $result['ext'];
    echo json_encode([
        'ok' => true,
        'url' => '/uploads/profile_media/user_' . $userId . '/' . $fileName,
        'size' => @filesize($uploadDir . '/' . $fileName) ?: 0,
        'name' => $fileName,
        'width' => (int)($result['width'] ?? 0),
        'height' => (int)($result['height'] ?? 0),
        // La punta scritta nei .cur e .ani, "x,y" in percentuale.
        'hotspot' => $result['hotspot'] ?? null,
        'animated' => !empty($result['animated']),
    ]);
} else {
    if (move_uploaded_file($tmpPath, $targetPath)) {
        // Return relative URL that starts with /uploads/profile_media/
        $relativeUrl = '/uploads/profile_media/user_' . $userId . '/' . $fileName;
        echo json_encode([
            'ok' => true,
            'url' => $relativeUrl,
            'size' => @filesize($targetPath) ?: 0,
            'name' => $fileName,
            // Il blocco usa questo per mostrare <video> o <img> senza
            // indovinare dall'estensione.
            'media_type' => $isBlockVideo ? 'video' : ($ext === 'gif' ? 'gif' : 'image'),
        ]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Impossibile salvare il file sul server.']);
    }
}
