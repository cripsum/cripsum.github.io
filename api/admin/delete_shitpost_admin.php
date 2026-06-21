<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID shitpost non valido.');

    $mysqli->begin_transaction();

    // Recupera autore e titolo prima dell'eliminazione
    $stmt = $mysqli->prepare("SELECT id_utente, titolo FROM shitposts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $postData = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (admin_table_exists($mysqli, 'commenti_shitpost')) {
        $stmt = $mysqli->prepare("DELETE FROM commenti_shitpost WHERE id_shitpost = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $mysqli->prepare("DELETE FROM shitposts WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $mysqli->rollback();
        admin_fail(admin_prepare_error($mysqli, 'Query eliminazione shitpost non valida.'), 500);
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $mysqli->rollback();
        admin_fail('Non sono riuscito a eliminare lo shitpost.', 500);
    }
    $deleted = $stmt->affected_rows;
    $stmt->close();

    $mysqli->commit();

    if ($postData && $deleted > 0) {
        $currentTime = date('d/m/Y H:i:s');
        $recipientId = (int)$postData['id_utente'];
        $postTitle = $postData['titolo'];
        
        $titleIt = "Contenuto rimosso: Violazione linee guida";
        $titleEn = "Content removed: Guidelines violation";
        
        $contentIt = "Il tuo shitpost intitolato \"" . $postTitle . "\" è stato rimosso dai moderatori in data " . $currentTime . " per violazione delle linee guida della community.\n\n" .
                     "Ti invitiamo a rispettare le regole per evitare ulteriori provvedimenti sul tuo account.";
                     
        $contentEn = "Your shitpost titled \"" . $postTitle . "\" has been removed by moderators on " . $currentTime . " for violation of the community guidelines.\n\n" .
                     "Please follow the rules to avoid further action on your account.";
                     
        $sent = sendSecurityInboxMessage($mysqli, $recipientId, $titleIt, $titleEn, $contentIt, $contentEn, 'system');
        @error_log("[" . date('Y-m-d H:i:s') . "] delete_shitpost_admin: post_id=" . $id . ", author=" . $recipientId . ", msg_sent=" . ($sent ? 'SUCCEEDED' : 'FAILED') . "\n", 3, __DIR__ . '/../../inbox_errors.log');
    } else {
        $hasPostData = $postData ? 'FOUND' : 'NOT FOUND';
        @error_log("[" . date('Y-m-d H:i:s') . "] delete_shitpost_admin info: postData=" . $hasPostData . ", deleted=" . $deleted . "\n", 3, __DIR__ . '/../../inbox_errors.log');
    }

    admin_log($mysqli, (int)$adminUser['id'], 'delete_shitpost', null, ['post_id' => $id]);
    admin_ok(['message' => $deleted > 0 ? 'Shitpost eliminato.' : 'Shitpost non trovato.']);
} catch (Throwable $e) {
    if ($mysqli->errno) @$mysqli->rollback();
    admin_fail('Errore eliminazione shitpost. Dettaglio: ' . $e->getMessage(), 500);
}
