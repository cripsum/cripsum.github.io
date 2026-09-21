<?php

/**
 * Elimina subito l'MP3 caricato sul profilo (e la sua copertina).
 *
 * Prima l'MP3 si toglieva con un interruttore che faceva effetto solo alla
 * pubblicazione: restava acceso in mezzo alle altre impostazioni e non era
 * chiaro se il file fosse ancora li'. Adesso c'e' un pulsante che cancella
 * e basta, e l'editor aggiorna la card del file con la risposta.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/profile_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_json_response(['ok' => false, 'message' => 'Metodo non consentito.'], 405);
}

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Devi essere loggato.'], 401);
}

if (!profile_validate_csrf($_POST['csrf_token'] ?? null)) {
    profile_json_response(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.'], 419);
}

$currentUserId = profile_current_user_id();
if ($currentUserId <= 0) {
    profile_json_response(['ok' => false, 'message' => 'Utente non trovato.'], 401);
}

$requestTargetUserId = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
$targetUserId = ($requestTargetUserId > 0 && profile_is_staff()) ? $requestTargetUserId : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    profile_json_response(['ok' => false, 'message' => 'Non puoi modificare questo profilo.'], 403);
}

// L'anteprima legge il profilo dalla bozza: se li' resta scritto che un file
// c'e' ancora, il player continuerebbe a mostrarlo fino al ricaricamento.
// Va fatto prima di lasciare la sessione, che dopo non si scrive piu'.
if (isset($_SESSION['profile_draft'][$targetUserId]) && is_array($_SESSION['profile_draft'][$targetUserId])) {
    unset($_SESSION['profile_draft'][$targetUserId]['profile_music_cover']);
    $_SESSION['profile_draft'][$targetUserId]['remove_profile_music_upload'] = '1';
}

// Fatti i controlli, niente qui sotto scrive nella sessione: l'editor sta
// salvando la bozza e ricaricando l'anteprima nello stesso momento.
cripsum_release_session();

$stmt = $mysqli->prepare('UPDATE utenti SET profile_music_blob = NULL, profile_music_mime = NULL WHERE id = ?');
if (!$stmt) {
    profile_json_response(['ok' => false, 'message' => 'Non riesco a cancellare il brano ora.'], 500);
}
$stmt->bind_param('i', $targetUserId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    profile_json_response(['ok' => false, 'message' => 'Non riesco a cancellare il brano ora.'], 500);
}

// La copertina senza il brano non ha piu' niente da illustrare.
if (profile_v7_column_available($mysqli, 'profile_music_cover')) {
    if ($coverStmt = $mysqli->prepare('UPDATE utenti SET profile_music_cover = NULL WHERE id = ?')) {
        $coverStmt->bind_param('i', $targetUserId);
        $coverStmt->execute();
        $coverStmt->close();
    }
}

profile_json_response(['ok' => true, 'message' => 'MP3 eliminato.']);
