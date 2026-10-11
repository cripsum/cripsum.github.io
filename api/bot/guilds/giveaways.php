<?php
declare(strict_types=1);

/**
 * Giveaway di un server Discord, con un premio descritto a parole.
 *
 * Sono un'altra cosa rispetto ai giveaway di api/bot/giveaway/, che regalano
 * Godos, personaggi e Premium del sito e per questo esistono solo nel server
 * di Cripsum, lanciati da un amministratore del sito. Qui il premio lo
 * consegna chi organizza: il sito tiene solo chi partecipa e chi ha vinto,
 * quindi partecipa chiunque sia nel server, anche senza account Cripsum.
 *
 * Chi puo' lanciarne uno lo decide il bot, che conosce i ruoli di Discord.
 * Qui si controlla che ogni giveaway venga toccato solo dal suo server.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_guild_giveaways')) {
    bot_json(['ok' => true, 'available' => false, 'due' => [], 'running' => [], 'recent' => []]);
}

const GGW_MAX_RUNNING_PER_GUILD = 10;
const GGW_MAX_ENTRIES_READ = 50000;

$action = bot_text('action', 20, true);
$body = bot_input();

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function ggw_public(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'guild_id' => (string)$row['guild_id'],
        'channel_id' => (string)$row['channel_id'],
        'message_id' => $row['message_id'] !== null ? (string)$row['message_id'] : null,
        'prize' => (string)$row['prize'],
        'description' => (string)($row['description'] ?? ''),
        'winners_count' => (int)$row['winners_count'],
        'required_role_id' => $row['required_role_id'] !== null ? (string)$row['required_role_id'] : null,
        'bonus_role_id' => $row['bonus_role_id'] !== null ? (string)$row['bonus_role_id'] : null,
        'bonus_entries' => (int)$row['bonus_entries'],
        'repeats' => (int)$row['repeats'] === 1,
        'status' => (string)$row['status'],
        'ends_unix' => strtotime((string)$row['ends_at']),
        'entries' => isset($row['entries']) ? (int)$row['entries'] : null,
        'created_by' => (string)$row['created_by_discord_id'],
    ];
}

/**
 * Un giveaway, sempre dentro il server della richiesta: da fuori risulta
 * inesistente, cosi' non si scopre nemmeno quali numeri sono in uso altrove.
 *
 * @return array<string, mixed>
 */
function ggw_require(mysqli $mysqli, int $id, string $guildId): array
{
    $stmt = $mysqli->prepare(
        'SELECT g.*, (SELECT COUNT(*) FROM discord_guild_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries
         FROM discord_guild_giveaways g WHERE g.id = ? AND g.guild_id = ? LIMIT 1'
    );

    if (!$stmt) {
        bot_fail('Database error.', 500);
    }

    $stmt->bind_param('is', $id, $guildId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        bot_fail('Giveaway not found.', 404);
    }

    return $row;
}

/**
 * Inserisce un giveaway e ne restituisce l'identificativo.
 *
 * @param array<string, mixed> $g
 */
function ggw_insert(mysqli $mysqli, array $g): int
{
    $stmt = $mysqli->prepare(
        'INSERT INTO discord_guild_giveaways
            (guild_id, channel_id, prize, description, winners_count, required_role_id, bonus_role_id,
             bonus_entries, duration_seconds, repeats, ends_at, created_by_discord_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        bot_fail('Unable to create the giveaway.', 500);
    }

    $endsAt = date('Y-m-d H:i:s', time() + (int)$g['duration_seconds']);

    $stmt->bind_param(
        'ssssissiiiss',
        $g['guild_id'],
        $g['channel_id'],
        $g['prize'],
        $g['description'],
        $g['winners_count'],
        $g['required_role_id'],
        $g['bonus_role_id'],
        $g['bonus_entries'],
        $g['duration_seconds'],
        $g['repeats'],
        $endsAt,
        $g['created_by_discord_id']
    );

    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id;
}

/* Le scadenze di tutti i server: le legge il timer del bot. */
if ($action === 'pending') {
    $rows = $mysqli->query(
        "SELECT g.*, (SELECT COUNT(*) FROM discord_guild_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries
         FROM discord_guild_giveaways g
         WHERE g.status = 'running'
         ORDER BY g.ends_at ASC LIMIT 200"
    );

    $due = [];
    $running = [];
    $now = time();

    foreach ($rows ? $rows->fetch_all(MYSQLI_ASSOC) : [] as $row) {
        $entry = ggw_public($row);
        if ($entry['ends_unix'] <= $now) {
            $due[] = $entry;
        } else {
            $running[] = $entry;
        }
    }

    bot_json(['ok' => true, 'available' => true, 'due' => $due, 'running' => $running]);
}

$guildId = bot_snowflake('guild_id');

switch ($action) {
    case 'create': {
        $duration = (int)($body['duration_seconds'] ?? 0);

        if ($duration < 60 || $duration > 60 * 60 * 24 * 30) {
            bot_fail('Duration must be between 1 minute and 30 days.', 400);
        }

        $stmt = $mysqli->prepare(
            "SELECT COUNT(*) AS total FROM discord_guild_giveaways WHERE guild_id = ? AND status = 'running'"
        );
        $stmt->bind_param('s', $guildId);
        $stmt->execute();
        $running = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        if ($running >= GGW_MAX_RUNNING_PER_GUILD) {
            bot_json(['ok' => true, 'available' => true, 'rejected' => 'too_many', 'limit' => GGW_MAX_RUNNING_PER_GUILD]);
        }

        $description = bot_text('description', 1000);
        $requiredRole = bot_snowflake('required_role_id', false);
        $bonusRole = bot_snowflake('bonus_role_id', false);
        $bonusEntries = $bonusRole !== '' ? max(1, min(10, (int)($body['bonus_entries'] ?? 1))) : 0;

        $id = ggw_insert($mysqli, [
            'guild_id' => $guildId,
            'channel_id' => bot_snowflake('channel_id'),
            'prize' => bot_text('prize', 200, true),
            'description' => $description !== '' ? $description : null,
            'winners_count' => max(1, min(20, (int)($body['winners'] ?? 1))),
            'required_role_id' => $requiredRole !== '' ? $requiredRole : null,
            'bonus_role_id' => $bonusRole !== '' ? $bonusRole : null,
            'bonus_entries' => $bonusEntries,
            'duration_seconds' => $duration,
            'repeats' => !empty($body['repeats']) ? 1 : 0,
            'created_by_discord_id' => bot_discord_id('actor_discord_id'),
        ]);

        bot_json(['ok' => true, 'available' => true, 'giveaway' => ggw_public(ggw_require($mysqli, $id, $guildId))]);
    }

    case 'attach': {
        $id = (int)($body['id'] ?? 0);
        $messageId = bot_snowflake('message_id');

        $stmt = $mysqli->prepare('UPDATE discord_guild_giveaways SET message_id = ? WHERE id = ? AND guild_id = ?');
        $stmt->bind_param('sis', $messageId, $id, $guildId);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true]);
    }

    /*
     * Partecipazione. I ruoli del membro li manda il bot, che li legge da
     * Discord: qui si decide se bastano e quante partecipazioni valgono.
     */
    case 'enter': {
        $giveaway = ggw_require($mysqli, (int)($body['id'] ?? 0), $guildId);
        $discordId = bot_discord_id('discord_id');
        $id = (int)$giveaway['id'];

        if ((string)$giveaway['status'] !== 'running' || strtotime((string)$giveaway['ends_at']) <= time()) {
            bot_json(['ok' => true, 'entered' => false, 'reason' => 'closed']);
        }

        if (!empty($body['leave'])) {
            $stmt = $mysqli->prepare('DELETE FROM discord_guild_giveaway_entries WHERE giveaway_id = ? AND discord_user_id = ?');
            $stmt->bind_param('is', $id, $discordId);
            $stmt->execute();
            $stmt->close();

            $after = ggw_require($mysqli, $id, $guildId);
            bot_json([
                'ok' => true,
                'entered' => false,
                'reason' => 'left',
                'entries' => (int)$after['entries'],
                'message_id' => $after['message_id'] !== null ? (string)$after['message_id'] : null,
            ]);
        }

        $roles = array_map('strval', is_array($body['role_ids'] ?? null) ? $body['role_ids'] : []);

        if ($giveaway['required_role_id'] !== null && !in_array((string)$giveaway['required_role_id'], $roles, true)) {
            bot_json(['ok' => true, 'entered' => false, 'reason' => 'missing_role', 'role_id' => (string)$giveaway['required_role_id']]);
        }

        $weight = 1;
        if ($giveaway['bonus_role_id'] !== null && in_array((string)$giveaway['bonus_role_id'], $roles, true)) {
            $weight += (int)$giveaway['bonus_entries'];
        }

        $stmt = $mysqli->prepare(
            'INSERT IGNORE INTO discord_guild_giveaway_entries (giveaway_id, discord_user_id, weight) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('isi', $id, $discordId, $weight);
        $stmt->execute();
        $inserted = $stmt->affected_rows > 0;
        $stmt->close();

        $after = ggw_require($mysqli, $id, $guildId);

        bot_json([
            'ok' => true,
            'entered' => $inserted,
            'reason' => $inserted ? null : 'already_entered',
            'weight' => $weight,
            'entries' => (int)$after['entries'],
        ]);
    }

    /*
     * Estrazione. Alla scadenza la chiede il timer del bot; prima (`force`) o
     * di nuovo (`reroll`) la chiede lo staff, e il bot ha gia' controllato
     * che possa. Chi ha piu' partecipazioni pesa di piu'; nessuno vince due
     * volte lo stesso giveaway.
     */
    case 'draw': {
        $giveaway = ggw_require($mysqli, (int)($body['id'] ?? 0), $guildId);
        $id = (int)$giveaway['id'];
        $reroll = !empty($body['reroll']);
        $force = !empty($body['force']);
        $status = (string)$giveaway['status'];

        if ($status === 'cancelled') {
            bot_json(['ok' => true, 'drawn' => false, 'reason' => 'cancelled']);
        }

        if ($status === 'ended' && !$reroll) {
            bot_json(['ok' => true, 'drawn' => false, 'reason' => 'already_drawn']);
        }

        if ($status === 'running' && $reroll) {
            bot_json(['ok' => true, 'drawn' => false, 'reason' => 'still_running']);
        }

        if ($status === 'running' && !$force && strtotime((string)$giveaway['ends_at']) > time()) {
            bot_json(['ok' => true, 'drawn' => false, 'reason' => 'still_running']);
        }

        $stmt = $mysqli->prepare(
            'SELECT discord_user_id, weight FROM discord_guild_giveaway_entries
             WHERE giveaway_id = ? AND won = 0 LIMIT ' . GGW_MAX_ENTRIES_READ
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $pool = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Chi non e' piu' nel server non puo' ritirare niente: il bot manda
        // l'elenco di chi escludere quando gli serve un sostituto.
        $exclude = array_flip(array_map('strval', is_array($body['exclude'] ?? null) ? $body['exclude'] : []));
        $pool = array_values(array_filter($pool, static fn(array $entry): bool => !isset($exclude[(string)$entry['discord_user_id']])));

        $wanted = $reroll ? max(1, min(20, (int)($body['count'] ?? 1))) : (int)$giveaway['winners_count'];
        $winners = [];

        while ($pool !== [] && count($winners) < $wanted) {
            $total = array_sum(array_map(static fn(array $entry): int => max(1, (int)$entry['weight']), $pool));
            $pick = random_int(1, $total);

            foreach ($pool as $index => $entry) {
                $pick -= max(1, (int)$entry['weight']);

                if ($pick <= 0) {
                    $winners[] = (string)$entry['discord_user_id'];
                    array_splice($pool, $index, 1);
                    break;
                }
            }
        }

        foreach ($winners as $winnerId) {
            $stmt = $mysqli->prepare('UPDATE discord_guild_giveaway_entries SET won = 1 WHERE giveaway_id = ? AND discord_user_id = ?');
            $stmt->bind_param('is', $id, $winnerId);
            $stmt->execute();
            $stmt->close();
        }

        $next = null;

        if (!$reroll) {
            $stmt = $mysqli->prepare("UPDATE discord_guild_giveaways SET status = 'ended', drawn_at = NOW() WHERE id = ? AND status = 'running'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $closedNow = $stmt->affected_rows > 0;
            $stmt->close();

            // Un giveaway che si ripete riparte da solo, con le stesse
            // regole, appena finisce quello prima. Chiuso a mano o annullato
            // non riparte: e' il modo per fermare la serie.
            if ($closedNow && (int)$giveaway['repeats'] === 1 && !$force) {
                $nextId = ggw_insert($mysqli, [
                    'guild_id' => $guildId,
                    'channel_id' => (string)$giveaway['channel_id'],
                    'prize' => (string)$giveaway['prize'],
                    'description' => $giveaway['description'],
                    'winners_count' => (int)$giveaway['winners_count'],
                    'required_role_id' => $giveaway['required_role_id'],
                    'bonus_role_id' => $giveaway['bonus_role_id'],
                    'bonus_entries' => (int)$giveaway['bonus_entries'],
                    'duration_seconds' => (int)$giveaway['duration_seconds'],
                    'repeats' => 1,
                    'created_by_discord_id' => (string)$giveaway['created_by_discord_id'],
                ]);

                $next = ggw_public(ggw_require($mysqli, $nextId, $guildId));
            }
        }

        bot_json([
            'ok' => true,
            'drawn' => true,
            'reroll' => $reroll,
            'giveaway' => ggw_public(ggw_require($mysqli, $id, $guildId)),
            'winners' => $winners,
            'next' => $next,
        ]);
    }

    case 'cancel': {
        $giveaway = ggw_require($mysqli, (int)($body['id'] ?? 0), $guildId);
        $id = (int)$giveaway['id'];

        if ((string)$giveaway['status'] !== 'running') {
            bot_json(['ok' => true, 'cancelled' => false, 'reason' => (string)$giveaway['status'], 'giveaway' => ggw_public($giveaway)]);
        }

        $stmt = $mysqli->prepare("UPDATE discord_guild_giveaways SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true, 'cancelled' => true, 'giveaway' => ggw_public($giveaway)]);
    }

    /* Quelli in corso e gli ultimi finiti: per /giveaway list e per scegliere quale chiudere. */
    case 'list': {
        $stmt = $mysqli->prepare(
            "SELECT g.*, (SELECT COUNT(*) FROM discord_guild_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries
             FROM discord_guild_giveaways g
             WHERE g.guild_id = ? AND (g.status = 'running' OR (g.status = 'ended' AND g.drawn_at > (NOW() - INTERVAL 14 DAY)))
             ORDER BY (g.status = 'running') DESC, g.ends_at DESC LIMIT 25"
        );
        $stmt->bind_param('s', $guildId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $all = array_map('ggw_public', $rows);

        bot_json([
            'ok' => true,
            'available' => true,
            'running' => array_values(array_filter($all, static fn(array $g): bool => $g['status'] === 'running')),
            'recent' => array_values(array_filter($all, static fn(array $g): bool => $g['status'] !== 'running')),
        ]);
    }

    default:
        bot_fail('Unknown action.', 400);
}
