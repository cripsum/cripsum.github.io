<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $uid = gd_require_login();
    $input = gd_input();
    $characterId = (int)($input['character_id'] ?? 0);

    if ($characterId <= 0) {
        gd_fail('ID personaggio non valido.');
    }

    // 1. Recupera informazioni sul personaggio
    $stmt = $mysqli->prepare('SELECT id, nome, rarità FROM personaggi WHERE id = ? LIMIT 1');
    if (!$stmt) {
        gd_fail('Database non disponibile.');
    }
    $stmt->bind_param('i', $characterId);
    $stmt->execute();
    $char = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$char) {
        gd_fail('Personaggio non trovato.', 404);
    }

    $rarity = $char['rarità'] ?? 'comune';

    // 2. Recupera l'ownership, quantità e livello corrente dall'inventario utente
    $stmt = $mysqli->prepare('SELECT quantità, livello FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
    $stmt->bind_param('ii', $uid, $characterId);
    $stmt->execute();
    $owned = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$owned) {
        gd_fail('Non possiedi questo personaggio.', 403);
    }

    $currentLevel = (int)($owned['livello'] ?? 1);
    $quantity = (int)($owned['quantità'] ?? 1);

    if ($currentLevel >= 6) {
        gd_fail('Questo personaggio ha già raggiunto il livello MAX.', 400);
    }

    // 3. Calcola il costo in copie per il livello successivo
    $requiredCopies = gd_get_upgrade_requirement($rarity, $currentLevel);

    // Controlla se l'utente ha abbastanza copie extra (deve rimanere almeno la copia base, quindi quantità - 1)
    $availableDuplicates = $quantity - 1;
    if ($availableDuplicates < $requiredCopies) {
        gd_fail("Copie insufficienti. Ti servono {$requiredCopies} duplicati extra (ne hai {$availableDuplicates}).", 400);
    }

    // 4. Esegui la transazione di potenziamento
    $mysqli->begin_transaction();
    try {
        $nextLevel = $currentLevel + 1;
        $newQuantity = $quantity - $requiredCopies;

        $upd = $mysqli->prepare('UPDATE utenti_personaggi SET livello = ?, quantità = ? WHERE utente_id = ? AND personaggio_id = ?');
        $upd->bind_param('iiii', $nextLevel, $newQuantity, $uid, $characterId);
        $upd->execute();
        $upd->close();

        $mysqli->commit();

        // 5. Ricalcola le statistiche attuali e del prossimo livello per inviarle al client
        $statsNow = gd_stats($mysqli, $characterId, $nextLevel);
        $statsNext = ($nextLevel < 6) ? gd_stats($mysqli, $characterId, $nextLevel + 1) : null;
        $requiredNext = ($nextLevel < 6) ? gd_get_upgrade_requirement($rarity, $nextLevel) : 0;

        gd_ok([
            'ok' => true,
            'message' => 'Personaggio potenziato con successo!',
            'character_id' => $characterId,
            'level' => $nextLevel,
            'quantity' => $newQuantity,
            'required_next' => $requiredNext,
            'stats' => $statsNow,
            'stats_next' => $statsNext
        ]);

    } catch (Exception $e) {
        $mysqli->rollback();
        gd_fail('Errore durante il potenziamento: ' . $e->getMessage(), 500);
    }

} catch (Exception $e) {
    gd_fail($e->getMessage(), 400);
}
