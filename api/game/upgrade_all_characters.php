<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $uid = gd_require_login();

    $stmt = $mysqli->prepare(
        'SELECT p.id, p.nome, p.rarità, p.categoria, up.quantità, up.livello
           FROM utenti_personaggi up
           JOIN personaggi p ON p.id = up.personaggio_id
          WHERE up.utente_id = ?
          ORDER BY p.id ASC'
    );

    if (!$stmt) {
        gd_fail('Database non disponibile.', 500);
    }

    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $ownedRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $updatedCharacters = [];
    $levelsGained = 0;

    $mysqli->begin_transaction();

    try {
        foreach ($ownedRows as $row) {
            $characterId = (int)($row['id'] ?? 0);
            $rarity = (string)($row['rarità'] ?? 'comune');
            $category = (string)($row['categoria'] ?? '');
            $level = max(1, (int)($row['livello'] ?? 1));
            $quantity = max(1, (int)($row['quantità'] ?? 1));
            $startLevel = $level;

            while ($level < 6) {
                $requiredCopies = gd_get_upgrade_requirement($rarity, $level, $category);
                if ($requiredCopies <= 0 || ($quantity - 1) < $requiredCopies) {
                    break;
                }

                $quantity -= $requiredCopies;
                $level++;
            }

            if ($level === $startLevel) {
                continue;
            }

            $upd = $mysqli->prepare('UPDATE utenti_personaggi SET livello = ?, quantità = ? WHERE utente_id = ? AND personaggio_id = ?');
            if (!$upd) {
                throw new RuntimeException('Query aggiornamento non valida.');
            }

            $upd->bind_param('iiii', $level, $quantity, $uid, $characterId);
            $upd->execute();
            $upd->close();

            $gained = $level - $startLevel;
            $levelsGained += $gained;

            $updatedCharacters[] = [
                'character_id' => $characterId,
                'name' => (string)($row['nome'] ?? ''),
                'level' => $level,
                'quantity' => $quantity,
                'levels_gained' => $gained,
                'required_next' => ($level < 6) ? gd_get_upgrade_requirement($rarity, $level, $category) : 0,
                'stats' => gd_stats($mysqli, $characterId, $level),
                'stats_next' => ($level < 6) ? gd_stats($mysqli, $characterId, $level + 1) : null,
            ];
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        gd_fail('Errore durante il potenziamento: ' . $e->getMessage(), 500);
    }

    $unlockedAchievements = [];
    if ($levelsGained > 0) {
        $cntStmt = $mysqli->prepare('SELECT COUNT(*) AS max_lvl_count FROM utenti_personaggi WHERE utente_id = ? AND livello = 6');
        if ($cntStmt) {
            $cntStmt->bind_param('i', $uid);
            $cntStmt->execute();
            $cntRow = $cntStmt->get_result()->fetch_assoc();
            $cntStmt->close();

            $maxLvlCount = (int)($cntRow['max_lvl_count'] ?? 0);
            foreach ([
                1 => 'Massimo Splendore I',
                5 => 'Massimo Splendore V',
                10 => 'Massimo Splendore X',
                50 => 'Esercito Dorato',
            ] as $threshold => $achievementName) {
                if ($maxLvlCount >= $threshold) {
                    $achievementId = gd_award_achievement_by_name($mysqli, $uid, $achievementName);
                    if ($achievementId !== null) {
                        $unlockedAchievements[] = $achievementId;
                    }
                }
            }
        }
    }

    gd_ok([
        'ok' => true,
        'message' => $levelsGained > 0 ? 'Personaggi potenziati con successo!' : 'Nessun personaggio potenziabile.',
        'upgraded_count' => count($updatedCharacters),
        'levels_gained' => $levelsGained,
        'characters' => $updatedCharacters,
        'unlocked_achievements' => $unlockedAchievements,
    ]);
} catch (Throwable $e) {
    gd_fail($e->getMessage(), 400);
}

function gd_award_achievement_by_name(mysqli $mysqli, int $userId, string $name): ?int
{
    $stmt = $mysqli->prepare('SELECT id, punti FROM achievement WHERE nome = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $ach = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ach) return null;

    $achId = (int)$ach['id'];
    $points = (int)$ach['punti'];

    $stmt = $mysqli->prepare('SELECT 1 FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('ii', $userId, $achId);
    $stmt->execute();
    $alreadyUnlocked = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($alreadyUnlocked) return null;

    $stmt = $mysqli->prepare('INSERT INTO utenti_achievement (utente_id, achievement_id, data) VALUES (?, ?, NOW())');
    if (!$stmt) return null;
    $stmt->bind_param('ii', $userId, $achId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare('UPDATE utenti SET soldi = soldi + ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $points, $userId);
        $stmt->execute();
        $stmt->close();
    }

    return $achId;
}
