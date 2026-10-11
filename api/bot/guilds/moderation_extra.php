<?php
declare(strict_types=1);

/**
 * Due cose in piu' per la moderazione di un server: i ban a tempo e le note
 * dello staff su un membro.
 *
 * Un ban a tempo e' un ban normale con una scadenza scritta qui: Discord non
 * li conosce, quindi e' il bot a tornare a chiedere quali sono scaduti e a
 * toglierli. Le note sono appunti che vede solo lo staff: non avvisano
 * nessuno e non contano come warn.
 *
 * Chi puo' usarle lo ha gia' controllato il bot su Discord. Qui ogni riga
 * resta legata al server da cui arriva la richiesta.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_timers') || !auth_table_exists($mysqli, 'discord_mod_notes')) {
    bot_json(['ok' => true, 'available' => false, 'due' => [], 'notes' => []]);
}

const MOD_NOTES_PER_MEMBER = 50;

$action = bot_text('action', 20, true);
$body = bot_input();

/* I ban scaduti di tutti i server: li chiede il timer del bot. */
if ($action === 'timers_due') {
    $rows = $mysqli->query(
        "SELECT id, guild_id, kind, target_id FROM discord_timers
         WHERE done_at IS NULL AND due_at <= NOW() ORDER BY due_at ASC LIMIT 50"
    );

    bot_json(['ok' => true, 'available' => true, 'due' => $rows ? $rows->fetch_all(MYSQLI_ASSOC) : []]);
}

$guildId = bot_snowflake('guild_id');

switch ($action) {
    case 'timer_add': {
        $targetId = bot_discord_id('target_id');
        $seconds = (int)($body['seconds'] ?? 0);

        if ($seconds < 60 || $seconds > 60 * 60 * 24 * 365) {
            bot_fail('Duration must be between 1 minute and 1 year.', 400);
        }

        // Un ban nuovo sostituisce la scadenza di quello vecchio.
        $stmt = $mysqli->prepare(
            "UPDATE discord_timers SET done_at = NOW()
             WHERE guild_id = ? AND kind = 'unban' AND target_id = ? AND done_at IS NULL"
        );
        $stmt->bind_param('ss', $guildId, $targetId);
        $stmt->execute();
        $stmt->close();

        // La scadenza la calcola il database, che e' lo stesso a confrontarla
        // con l'ora quando il bot chiede quali sono scadute: PHP e MySQL
        // possono avere fusi orari diversi, e un ban di un'ora durerebbe
        // tre ore, o nessuna.
        $stmt = $mysqli->prepare(
            "INSERT INTO discord_timers (guild_id, kind, target_id, due_at)
             VALUES (?, 'unban', ?, (NOW() + INTERVAL ? SECOND))"
        );
        $stmt->bind_param('ssi', $guildId, $targetId, $seconds);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true, 'available' => true, 'due_unix' => time() + $seconds]);
    }

    /* Tolto il ban a mano: la scadenza non serve piu'. */
    case 'timer_cancel': {
        $targetId = bot_discord_id('target_id');

        $stmt = $mysqli->prepare(
            "UPDATE discord_timers SET done_at = NOW()
             WHERE guild_id = ? AND kind = 'unban' AND target_id = ? AND done_at IS NULL"
        );
        $stmt->bind_param('ss', $guildId, $targetId);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true]);
    }

    case 'timer_done': {
        $id = (int)($body['id'] ?? 0);

        $stmt = $mysqli->prepare('UPDATE discord_timers SET done_at = NOW() WHERE id = ? AND guild_id = ?');
        $stmt->bind_param('is', $id, $guildId);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true]);
    }

    case 'note_add': {
        $targetId = bot_discord_id('target_id');
        $authorId = bot_discord_id('author_id');
        $note = bot_text('note', 500, true);

        $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM discord_mod_notes WHERE guild_id = ? AND target_discord_id = ?');
        $stmt->bind_param('ss', $guildId, $targetId);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        if ($total >= MOD_NOTES_PER_MEMBER) {
            bot_json(['ok' => true, 'available' => true, 'rejected' => 'too_many', 'limit' => MOD_NOTES_PER_MEMBER]);
        }

        $stmt = $mysqli->prepare(
            'INSERT INTO discord_mod_notes (guild_id, target_discord_id, author_discord_id, note) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $guildId, $targetId, $authorId, $note);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();

        bot_json(['ok' => true, 'available' => true, 'id' => $id, 'total' => $total + 1]);
    }

    case 'note_list': {
        $targetId = bot_discord_id('target_id');

        $stmt = $mysqli->prepare(
            'SELECT id, author_discord_id, note, created_at FROM discord_mod_notes
             WHERE guild_id = ? AND target_discord_id = ? ORDER BY id DESC LIMIT 15'
        );
        $stmt->bind_param('ss', $guildId, $targetId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        bot_json([
            'ok' => true,
            'available' => true,
            'notes' => array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'author_id' => (string)$row['author_discord_id'],
                'note' => (string)$row['note'],
                'when' => strtotime((string)$row['created_at']),
            ], $rows),
        ]);
    }

    case 'note_remove': {
        $id = (int)($body['id'] ?? 0);

        // Il numero lo scrive chi lancia il comando: senza il server ne
        // basterebbe uno indovinato per cancellare la nota di un altro.
        $stmt = $mysqli->prepare('DELETE FROM discord_mod_notes WHERE id = ? AND guild_id = ?');
        $stmt->bind_param('is', $id, $guildId);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();

        bot_json(['ok' => true, 'available' => true, 'removed' => $removed]);
    }

    default:
        bot_fail('Unknown action.', 400);
}
