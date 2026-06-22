<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/cursor_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato per caricare file.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Get user profile to check premium status
$profile = profile_get_edit_profile($mysqli, $userId);
if (!$profile || (int)($profile['is_premium'] ?? 0) !== 1) {
    echo json_encode(['ok' => false, 'message' => 'Questa funzionalità richiede un account Premium.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'message' => 'Nessun file ricevuto o errore di caricamento (Codice: ' . $errCode . ').']);
    exit;
}

$file = $_FILES['file'];

$purpose = trim($_POST['purpose'] ?? '');

if ($purpose === 'cursor') {
    $maxBytes = 2 * 1024 * 1024; // 2 MB for cursors
} else {
    $maxBytes = 25 * 1024 * 1024; // 25 MB
}

if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
    $maxMb = $purpose === 'cursor' ? '2MB' : '25MB';
    echo json_encode(['ok' => false, 'message' => "Il file è troppo pesante. Il limite massimo è {$maxMb}."]);
    exit;
}

$tmpPath = $file['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    echo json_encode(['ok' => false, 'message' => 'File non valido.']);
    exit;
}

// Check mime type / extension
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

$origName = strtolower($file['name']);
$origExt = pathinfo($origName, PATHINFO_EXTENSION);

// SVG fallback check (sometimes mime comes out as text/plain or text/xml for SVGs depending on OS configuration)
$ext = '';
if ($mimeType === 'text/plain' || $mimeType === 'text/xml' || $mimeType === 'image/svg') {
    if (str_ends_with($origName, '.svg')) {
        $mimeType = 'image/svg+xml';
    }
}

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
} else {
    if (!array_key_exists($mimeType, $allowedMimes)) {
        echo json_encode(['ok' => false, 'message' => 'Formato file non supportato. Formati validi: JPG, PNG, WEBP, GIF, SVG. Mime rilevato: ' . $mimeType]);
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
    $success = false;
    $errorMessage = 'Impossibile salvare il cursore.';

    if ($ext === 'cur') {
        if (cursor_process_cur_file($tmpPath, $targetPath)) {
            $success = true;
        } else {
            $errorMessage = 'File .cur non valido o errore nel salvataggio.';
        }
    } elseif ($ext === 'ani') {
        $res = cursor_convert_ani_to_gif($tmpPath, $targetPath);
        if ($res['ok']) {
            $success = true;
            $ext = $res['ext'];
            $fileName = 'cursor_' . $randomHash . '.' . $ext;
        } else {
            $errorMessage = $res['error'] ?? 'Errore nella conversione del file .ani.';
        }
    } else {
        // Standard image, resize to 64x64 and save as PNG
        $ext = 'png';
        $fileName = 'cursor_' . $randomHash . '.png';
        $targetPath = $uploadDir . '/' . $fileName;

        if (cursor_resize_image($tmpPath, $mimeType, $targetPath, 64)) {
            $success = true;
        } else {
            $errorMessage = 'Errore durante il ridimensionamento dell\'immagine del cursore.';
        }
    }

    if ($success) {
        $relativeUrl = '/uploads/profile_media/user_' . $userId . '/' . $fileName;
        echo json_encode(['ok' => true, 'url' => $relativeUrl]);
    } else {
        echo json_encode(['ok' => false, 'message' => $errorMessage]);
    }
} else {
    if (move_uploaded_file($tmpPath, $targetPath)) {
        // Return relative URL that starts with /uploads/profile_media/
        $relativeUrl = '/uploads/profile_media/user_' . $userId . '/' . $fileName;
        echo json_encode(['ok' => true, 'url' => $relativeUrl]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Impossibile salvare il file sul server.']);
    }
}

