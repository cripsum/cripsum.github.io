<?php
declare(strict_types=1);

/**
 * Ticket nati e vissuti su Discord, in qualsiasi server.
 *
 * Non sono i ticket del supporto del sito (api/bot/tickets/), che esistono
 * solo nel server di Cripsum e hanno la loro chat sul sito. Di questi qui si
 * tiene il minimo che su Discord non si puo' ricavare: chi l'ha aperto,
 * quanti ne ha aperti, a chi e' assegnato, quando e' stato chiuso e con che
 * voto. La conversazione resta nel thread e la trascrizione nel canale dei
 * log di quel server.
 *
 * Chi puo' fare cosa lo decide il bot, che conosce i ruoli di Discord: qui
 * si controlla soltanto che il ticket sia del server da cui arriva la
 * richiesta e che lo stato lo permetta.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_tickets')) {
    bot_json(['ok' => false, 'error' => 'Tickets are not installed on the website yet.', 'available' => false], 503);
}

const DTICKET_OPEN_WINDOW_MINUTES = 30;
const DTICKET_OPEN_WINDOW_LIMIT = 5;

$action = bot_text('action', 20, true);
$guildId = bot_snowflake('guild_id');

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function dticket_public(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'number' => (int)$row['number'],
        'thread_id' => $row['thread_id'] !== null ? (string)$row['thread_id'] : null,
        'opener_id' => (string)$row['opener_discord_id'],
        'category_id' => (string)$row['category_id'],
        'category_label' => (string)$row['category_label'],
        'subject' => (string)$row['subject'],
        'status' => (string)$row['status'],
        'claimed_by' => $row['claimed_by'] !== null ? (string)$row['claimed_by'] : null,
        'closed_by' => $row['closed_by'] !== null ? (string)$row['closed_by'] : null,
        'close_reason' => $row['close_reason'] !== null ? (string)$row['close_reason'] : null,
        'rating' => $row['rating'] !== null ? (int)$row['rating'] : null,
        'opened_at' => strtotime((string)$row['opened_at']),
        'closed_at' => $row['closed_at'] !== null ? strtotime((string)$row['closed_at']) : null,
    ];
}

/**
 * Il ticket di un thread, sempre dentro il server della richiesta.
 *
 * @return array<string, mixed>
 */
function dticket_by_thread(mysqli $mysqli, string $guildId): array
{
    $threadId = bot_snowflake('thread_id');

    $stmt = $mysqli->prepare('SELECT * FROM discord_tickets WHERE guild_id = ? AND thread_id = ? LIMIT 1');
    if (!$stmt) {
        bot_fail('Database error.', 500);
    }

    $stmt->bind_param('ss', $guildId, $threadId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        bot_fail('Ticket not found.', 404);
    }

    return $row;
}

/**
 * @return array<string, mixed>
 */
function dticket_reload(mysqli $mysqli, int $id): array
{
    $stmt = $mysqli->prepare('SELECT * FROM discord_tickets WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: [];
}

switch ($action) {
    /*
     * Apertura. Si prenota il numero prima di creare il thread, cosi' il
     * limite di ticket aperti vale davvero: due finestre inviate insieme non
     * passano entrambe.
     */
    case 'open': {
        $openerId = bot_discord_id('opener_discord_id');
        $maxOpen = max(1, min(10, (int)(bot_input()['max_open'] ?? 3)));
        $categoryId = bot_text('category_id', 12);
        $categoryLabel = bot_text('category_label', 80);
        $subject = bot_text('subject', 120);

        $mysqli->begin_transaction();

        try {
            // Le righe del server si bloccano finche' non si e' deciso: il
            // conteggio e il numero progressivo non si accavallano.
            $stmt = $mysqli->prepare(
                'SELECT COALESCE(MAX(number), 0) AS last_number,
                        COALESCE(SUM(status = \'open\' AND opener_discord_id = ?), 0) AS open_count,
                        COALESCE(SUM(opener_discord_id = ? AND opened_at > (NOW() - INTERVAL ' . DTICKET_OPEN_WINDOW_MINUTES . ' MINUTE)), 0) AS recent
                 FROM discord_tickets WHERE guild_id = ? FOR UPDATE'
            );
            $stmt->bind_param('sss', $openerId, $openerId, $guildId);
            $stmt->execute();
            $counts = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();

            if ((int)($counts['open_count'] ?? 0) >= $maxOpen) {
                $mysqli->rollback();
                bot_json(['ok' => true, 'rejected' => 'too_many_open', 'limit' => $maxOpen]);
            }

            if ((int)($counts['recent'] ?? 0) >= DTICKET_OPEN_WINDOW_LIMIT) {
                $mysqli->rollback();
                bot_json(['ok' => true, 'rejected' => 'rate_limited', 'minutes' => DTICKET_OPEN_WINDOW_MINUTES]);
            }

            $number = (int)($counts['last_number'] ?? 0) + 1;

            $stmt = $mysqli->prepare(
                'INSERT INTO discord_tickets (guild_id, number, opener_discord_id, category_id, category_label, subject)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sissss', $guildId, $number, $openerId, $categoryId, $categoryLabel, $subject);
            $stmt->execute();
            $id = (int)$stmt->insert_id;
            $stmt->close();

            $mysqli->commit();
        } catch (Throwable $error) {
            $mysqli->rollback();
            error_log('[dtickets] open: ' . $error->getMessage());
            bot_fail('Unable to open the ticket.', 500);
        }

        bot_json(['ok' => true, 'ticket' => dticket_public(dticket_reload($mysqli, $id))]);
    }

    /* Il thread e' stato creato: da qui il ticket si ritrova da li'. */
    case 'attach': {
        $id = (int)(bot_input()['id'] ?? 0);
        $threadId = bot_snowflake('thread_id');

        $stmt = $mysqli->prepare(
            'UPDATE discord_tickets SET thread_id = ? WHERE id = ? AND guild_id = ? AND thread_id IS NULL'
        );
        $stmt->bind_param('sis', $threadId, $id, $guildId);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        bot_json(['ok' => $changed]);
    }

    /* Il thread non si e' potuto creare: il numero prenotato si libera. */
    case 'cancel': {
        $id = (int)(bot_input()['id'] ?? 0);

        $stmt = $mysqli->prepare('DELETE FROM discord_tickets WHERE id = ? AND guild_id = ? AND thread_id IS NULL');
        $stmt->bind_param('is', $id, $guildId);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true]);
    }

    case 'get': {
        bot_json(['ok' => true, 'ticket' => dticket_public(dticket_by_thread($mysqli, $guildId))]);
    }

    /*
     * Assegnazione. `assignee` vuoto libera il ticket. Con `only_if_free` il
     * ticket si prende solo se non e' di nessuno: e' il bottone «Claim», che
     * non deve togliere il ticket a chi ci sta gia' lavorando.
     */
    case 'assign': {
        $row = dticket_by_thread($mysqli, $guildId);
        $assignee = bot_discord_id('assignee', false);
        $onlyIfFree = !empty(bot_input()['only_if_free']);

        if ($row['status'] !== 'open') {
            bot_json(['ok' => true, 'rejected' => 'closed', 'ticket' => dticket_public($row)]);
        }

        if ($onlyIfFree && $row['claimed_by'] !== null && (string)$row['claimed_by'] !== $assignee) {
            bot_json(['ok' => true, 'rejected' => 'taken', 'ticket' => dticket_public($row)]);
        }

        $id = (int)$row['id'];
        $value = $assignee !== '' ? $assignee : null;

        $stmt = $mysqli->prepare('UPDATE discord_tickets SET claimed_by = ? WHERE id = ?');
        $stmt->bind_param('si', $value, $id);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true, 'ticket' => dticket_public(dticket_reload($mysqli, $id))]);
    }

    case 'close': {
        $row = dticket_by_thread($mysqli, $guildId);
        $actorId = bot_discord_id('actor_discord_id', false);
        $reason = in_array(bot_text('reason', 10), ['auto', 'deleted'], true) ? bot_text('reason', 10) : 'manual';
        $id = (int)$row['id'];

        if ($row['status'] === 'closed') {
            // Chiuso due volte (due persone, o il bottone e il comando
            // insieme): il secondo non cambia chi l'ha chiuso ne' quando.
            if ($reason === 'deleted') {
                $stmt = $mysqli->prepare("UPDATE discord_tickets SET close_reason = 'deleted' WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }

            bot_json(['ok' => true, 'already' => true, 'ticket' => dticket_public(dticket_reload($mysqli, $id))]);
        }

        $closedBy = $actorId !== '' ? $actorId : null;

        $stmt = $mysqli->prepare(
            "UPDATE discord_tickets SET status = 'closed', closed_by = ?, close_reason = ?, closed_at = NOW()
             WHERE id = ? AND status = 'open'"
        );
        $stmt->bind_param('ssi', $closedBy, $reason, $id);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true, 'ticket' => dticket_public(dticket_reload($mysqli, $id))]);
    }

    case 'reopen': {
        $row = dticket_by_thread($mysqli, $guildId);
        $id = (int)$row['id'];

        $stmt = $mysqli->prepare(
            "UPDATE discord_tickets
             SET status = 'open', closed_by = NULL, close_reason = NULL, closed_at = NULL, rating = NULL
             WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true, 'ticket' => dticket_public(dticket_reload($mysqli, $id))]);
    }

    /* Il voto lo da' solo chi ha aperto il ticket, una volta, a ticket chiuso. */
    case 'rate': {
        $id = (int)(bot_input()['id'] ?? 0);
        $openerId = bot_discord_id('opener_discord_id');
        $rating = (int)(bot_input()['rating'] ?? 0);

        if ($rating < 1 || $rating > 5) {
            bot_fail('Invalid rating.', 400);
        }

        $stmt = $mysqli->prepare(
            "UPDATE discord_tickets SET rating = ?
             WHERE id = ? AND guild_id = ? AND opener_discord_id = ? AND status = 'closed' AND rating IS NULL"
        );
        $stmt->bind_param('iiss', $rating, $id, $guildId, $openerId);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        $row = dticket_reload($mysqli, $id);

        bot_json([
            'ok' => true,
            'saved' => $changed,
            'ticket' => $row && (string)$row['guild_id'] === $guildId ? dticket_public($row) : null,
        ]);
    }

    /* I ticket aperti di un server: servono alla chiusura automatica. */
    case 'list': {
        $stmt = $mysqli->prepare(
            "SELECT * FROM discord_tickets
             WHERE guild_id = ? AND status = 'open' AND thread_id IS NOT NULL
             ORDER BY opened_at ASC LIMIT 200"
        );
        $stmt->bind_param('s', $guildId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        bot_json(['ok' => true, 'tickets' => array_map('dticket_public', $rows)]);
    }

    default:
        bot_fail('Unknown action.', 400);
}
