<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $uid = gd_require_login();
    gd_require_csrf();

    $limitedSelect = gd_has_col($mysqli, 'personaggi', 'limitato') ? 'p.limitato' : 'NULL AS limitato';
    $stmt = $mysqli->prepare(
        'SELECT p.id, p.nome, p.rarità, p.categoria, ' . $limitedSelect . ', up.quantità, up.livello
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
    $hasUsedColumn = gd_has_col($mysqli, 'utenti_personaggi', 'copie_usate');

    $mysqli->begin_transaction();

    try {
        foreach ($ownedRows as $row) {
            $characterId = (int)($row['id'] ?? 0);
            $rarity = (string)($row['rarità'] ?? 'comune');
            $category = gd_limited_marker($row);
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

            // Le copie consumate restano nelle "casse aperte" (copie_usate).
            $used = max(1, (int)($row['quantità'] ?? 1)) - $quantity;
            if ($hasUsedColumn) {
                $upd = $mysqli->prepare('UPDATE utenti_personaggi SET livello = ?, quantità = ?, copie_usate = copie_usate + ? WHERE utente_id = ? AND personaggio_id = ?');
                if (!$upd) {
                    throw new RuntimeException('Query aggiornamento non valida.');
                }
                $upd->bind_param('iiiii', $level, $quantity, $used, $uid, $characterId);
            } else {
                $upd = $mysqli->prepare('UPDATE utenti_personaggi SET livello = ?, quantità = ? WHERE utente_id = ? AND personaggio_id = ?');
                if (!$upd) {
                    throw new RuntimeException('Query aggiornamento non valida.');
                }
                $upd->bind_param('iiii', $level, $quantity, $uid, $characterId);
            }
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

    // Achievement dei potenziamenti (primo potenziamento, personaggi al MAX):
    // li conta e li assegna il motore, dopo il commit.
    $unlockedAchievements = [];
    if ($levelsGained > 0) {
        require_once __DIR__ . '/../../includes/achievements.php';
        $unlockedAchievements = ach_sync($mysqli, $uid, ['collection']);
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
