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

        // Controlla e assegna gli achievement per i personaggi a livello MAX
        $cnt_stmt = $mysqli->prepare('SELECT COUNT(*) AS max_lvl_count FROM utenti_personaggi WHERE utente_id = ? AND livello = 6');
        $cnt_stmt->bind_param('i', $uid);
        $cnt_stmt->execute();
        $cnt_row = $cnt_stmt->get_result()->fetch_assoc();
        $cnt_stmt->close();

        $maxLvlCount = (int)($cnt_row['max_lvl_count'] ?? 0);
        $unlockedAchievements = [];

        if ($maxLvlCount >= 1) {
            $aid = gd_award_achievement_by_name($mysqli, $uid, 'Massimo Splendore I');
            if ($aid !== null) $unlockedAchievements[] = $aid;
        }
        if ($maxLvlCount >= 5) {
            $aid = gd_award_achievement_by_name($mysqli, $uid, 'Massimo Splendore V');
            if ($aid !== null) $unlockedAchievements[] = $aid;
        }
        if ($maxLvlCount >= 10) {
            $aid = gd_award_achievement_by_name($mysqli, $uid, 'Massimo Splendore X');
            if ($aid !== null) $unlockedAchievements[] = $aid;
        }
        if ($maxLvlCount >= 50) {
            $aid = gd_award_achievement_by_name($mysqli, $uid, 'Esercito Dorato');
            if ($aid !== null) $unlockedAchievements[] = $aid;
        }

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

function gd_award_achievement_by_name(mysqli $mysqli, int $userId, string $name): ?int {
    // 1. Cerca l'achievement nel DB
    $stmt = $mysqli->prepare('SELECT id, punti FROM achievement WHERE nome = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $ach = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ach) return null;

    $achId = (int)$ach['id'];
    $punti = (int)$ach['punti'];

    // 2. Controlla se l'utente lo possiede già
    $stmt = $mysqli->prepare('SELECT 1 FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $achId);
    $stmt->execute();
    $hasIt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($hasIt) return null;

    // 3. Assegna l'achievement
    $stmt = $mysqli->prepare('INSERT INTO utenti_achievement (utente_id, achievement_id, data) VALUES (?, ?, NOW())');
    $stmt->bind_param('ii', $userId, $achId);
    $stmt->execute();
    $stmt->close();

    // 4. Assegna i soldi/punti all'utente
    $stmt = $mysqli->prepare('UPDATE utenti SET soldi = soldi + ? WHERE id = ?');
    $stmt->bind_param('ii', $punti, $userId);
    $stmt->execute();
    $stmt->close();

    return $achId;
}
