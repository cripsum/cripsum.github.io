<?php
declare(strict_types=1);

/**
 * /daily su Discord: una manciata di Godos al giorno, di piu' se si torna
 * tutti i giorni.
 *
 * Il giorno e' quello del sito (lo stesso delle missioni giornaliere, ora di
 * Roma). Saltare un giorno riporta la serie a 1. Ogni settimana piena di
 * fila vale un premio in piu'.
 *
 * Gli importi stanno qui sotto, tutti insieme, per poterli cambiare senza
 * cercarli.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/mission_tracker.php';

const DAILY_BASE = 25;          // il primo giorno
const DAILY_PER_DAY = 5;        // in piu' per ogni giorno di fila…
const DAILY_MAX_EXTRA = 35;     // …fino a questo tetto (25 + 35 = 60 al giorno)
const DAILY_WEEK_BONUS = 100;   // al settimo, quattordicesimo… giorno di fila

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_daily')) {
    bot_json(['ok' => true, 'available' => false]);
}

$user = bot_find_user($mysqli, bot_discord_id('discord_id'));

if (!$user) {
    bot_json(['ok' => true, 'available' => true, 'linked' => false]);
}

if ((int)($user['isBannato'] ?? 0) === 1) {
    bot_json(['ok' => true, 'available' => true, 'linked' => true, 'banned' => true]);
}

$userId = (int)$user['id'];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$nextReset = getDailyResetTimestamp();

$mysqli->begin_transaction();

try {
    // La riga si blocca: due /daily nello stesso istante non pagano due volte.
    $stmt = $mysqli->prepare('SELECT streak, best_streak, last_claim, total_claims FROM discord_daily WHERE user_id = ? FOR UPDATE');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && (string)$row['last_claim'] === $today) {
        $mysqli->rollback();
        bot_json([
            'ok' => true,
            'available' => true,
            'linked' => true,
            'claimed' => false,
            'streak' => (int)$row['streak'],
            'best_streak' => (int)$row['best_streak'],
            'next_unix' => $nextReset,
        ]);
    }

    $streak = $row && (string)$row['last_claim'] === $yesterday ? (int)$row['streak'] + 1 : 1;
    $best = max($streak, (int)($row['best_streak'] ?? 0));
    $total = (int)($row['total_claims'] ?? 0) + 1;

    $amount = DAILY_BASE + min(DAILY_MAX_EXTRA, DAILY_PER_DAY * ($streak - 1));
    $bonus = $streak % 7 === 0 ? DAILY_WEEK_BONUS : 0;

    $stmt = $mysqli->prepare('UPDATE utenti SET soldi = soldi + ? WHERE id = ?');
    $gain = $amount + $bonus;
    $stmt->bind_param('ii', $gain, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare(
        'INSERT INTO discord_daily (user_id, streak, best_streak, last_claim, total_claims) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE streak = VALUES(streak), best_streak = VALUES(best_streak),
                                 last_claim = VALUES(last_claim), total_claims = VALUES(total_claims)'
    );
    $stmt->bind_param('iiisi', $userId, $streak, $best, $today, $total);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT soldi FROM utenti WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $balance = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();

    $mysqli->commit();
} catch (Throwable $error) {
    $mysqli->rollback();
    error_log('[daily] ' . $error->getMessage());
    bot_fail('Unable to claim the daily reward.', 500);
}

// Dopo il pagamento, fuori dalla transazione: una missione che non si
// aggiorna non deve togliere i Godos appena dati.
try {
    trackMissionProgress($mysqli, $userId, 'discord_daily');
} catch (Throwable $error) {
    error_log('[daily] missione: ' . $error->getMessage());
}

bot_json([
    'ok' => true,
    'available' => true,
    'linked' => true,
    'claimed' => true,
    'username' => (string)$user['username'],
    'amount' => $amount,
    'bonus' => $bonus,
    'streak' => $streak,
    'best_streak' => $best,
    'balance' => $balance,
    'next_unix' => $nextReset,
]);
