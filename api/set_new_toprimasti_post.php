<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non sei autenticato']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    $titolo = $_POST['titolo'] ?? '';
    $descrizione = $_POST['descrizione'] ?? '';
    $motivazione = $_POST['motivazione'] ?? '';
    $data_creazione = date('Y-m-d H:i:s');

    if (empty($titolo) || empty($descrizione) || empty($motivazione)) {
        echo json_encode(['success' => false, 'error' => 'Tutti i campi sono obbligatori']);
        exit();
    }

    if (!isset($_FILES['foto_rimasto']) || $_FILES['foto_rimasto']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Errore nel caricamento della foto: ' . ($_FILES['foto_rimasto']['error'] ?? 'File non trovato')]);
        exit();
    }

    $foto_rimasto_blob = file_get_contents($_FILES['foto_rimasto']['tmp_name']);
    $foto_rimasto_mime = mime_content_type($_FILES['foto_rimasto']['tmp_name']);

    if ($foto_rimasto_blob === false || empty($foto_rimasto_mime)) {
        echo json_encode(['success' => false, 'error' => 'Errore nel leggere il contenuto del file']);
        exit();
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($foto_rimasto_mime, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Tipo di file non consentito. Usa solo JPEG, PNG, GIF o WebP']);
        exit();
    }

    if ($_FILES['foto_rimasto']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File troppo grande. Massimo 5MB']);
        exit();
    }

    error_log("Tentativo di inserimento post: Titolo={$titolo}, User={$userId}, FileSize=" . strlen($foto_rimasto_blob) . ", MIME={$foto_rimasto_mime}");

    $stmt = $mysqli->prepare("INSERT INTO toprimasti (id_utente, titolo, descrizione, motivazione, foto_rimasto, tipo_foto_rimasto, data_creazione, approvato, reazioni) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)");

    $null = null;
    $stmt->bind_param("isssbss", $userId, $titolo, $descrizione, $motivazione, $null, $foto_rimasto_mime, $data_creazione);
    
    $stmt->send_long_data(4, $foto_rimasto_blob);

    if ($stmt->execute()) {
        $insertId = $mysqli->insert_id;
        error_log("Post inserito con successo: ID={$insertId}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Post aggiunto con successo, sarà visibile dopo l\'approvazione dell\'admin',
            'post_id' => $insertId
        ]);
    } else {
        error_log("Errore nell'esecuzione della query: " . $stmt->error);
        echo json_encode(['success' => false, 'error' => 'Errore nell\'inserimento del post: ' . $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Errore in set_new_toprimasti_post.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno del server: ' . $e->getMessage()]);
}
?>