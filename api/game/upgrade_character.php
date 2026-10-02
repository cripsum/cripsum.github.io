<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $uid = gd_require_login();
    gd_require_csrf();
    $input = gd_input();
    $characterId = (int)($input['character_id'] ?? 0);

    if ($characterId <= 0) {
        gd_fail('ID personaggio non valido.');
    }

    // 1. Recupera informazioni sul personaggio
    $limitedSelect = gd_has_col($mysqli, 'personaggi', 'limitato') ? 'limitato' : 'NULL AS limitato';
    $stmt = $mysqli->prepare('SELECT id, nome, rarità, categoria, ' . $limitedSelect . ' FROM personaggi WHERE id = ? LIMIT 1');
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
    $category = gd_limited_marker($char);

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
    $requiredCopies = gd_get_upgrade_requirement($rarity, $currentLevel, $category);

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

        // Le copie consumate restano nelle "casse aperte" (copie_usate).
        if (gd_has_col($mysqli, 'utenti_personaggi', 'copie_usate')) {
            $upd = $mysqli->prepare('UPDATE utenti_personaggi SET livello = ?, quantità = ?, copie_usate = copie_usate + ? WHERE utente_id = ? AND personaggio_id = ?');
            $upd->bind_param('iiiii', $nextLevel, $newQuantity, $requiredCopies, $uid, $characterId);
        } else {
            $upd = $mysqli->prepare('UPDATE utenti_personaggi SET livello = ?, quantità = ? WHERE utente_id = ? AND personaggio_id = ?');
            $upd->bind_param('iiii', $nextLevel, $newQuantity, $uid, $characterId);
        }
        $upd->execute();
        $upd->close();

        $mysqli->commit();

        // Missioni: «potenzia 1 personaggio». Dopo il commit e non bloccante,
        // il potenziamento è già andato a buon fine.
        try {
            trackMissionProgress($mysqli, $uid, 'upgrade_character');
        } catch (Throwable $trackErr) {
            error_log('[MissionTracking upgrade_character] ' . $trackErr->getMessage());
        }

        // Achievement dei potenziamenti (primo potenziamento, personaggi al
        // MAX): li conta e li assegna il motore. Il premio in Godos non si
        // accredita più qui: si riscuote dalla pagina degli achievement.
        require_once __DIR__ . '/../../includes/achievements.php';
        $unlockedAchievements = ach_sync($mysqli, $uid, ['collection']);

        // 5. Ricalcola le statistiche attuali e del prossimo livello per inviarle al client
        $statsNow = gd_stats($mysqli, $characterId, $nextLevel);
        $statsNext = ($nextLevel < 6) ? gd_stats($mysqli, $characterId, $nextLevel + 1) : null;
        $requiredNext = ($nextLevel < 6) ? gd_get_upgrade_requirement($rarity, $nextLevel, $category) : 0;

        gd_ok([
            'ok' => true,
            'message' => 'Personaggio potenziato con successo!',
            'character_id' => $characterId,
            'level' => $nextLevel,
            'quantity' => $newQuantity,
            'required_next' => $requiredNext,
            'stats' => $statsNow,
            'stats_next' => $statsNext,
            'unlocked_achievements' => $unlockedAchievements
        ]);

    } catch (Exception $e) {
        $mysqli->rollback();
        gd_fail('Errore durante il potenziamento: ' . $e->getMessage(), 500);
    }

} catch (Exception $e) {
    gd_fail($e->getMessage(), 400);
}
