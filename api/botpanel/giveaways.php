<?php
declare(strict_types=1);

/**
 * I giveaway di un server, dal pannello.
 *
 * GET  ?guild=…                                 in corso e appena finiti
 * POST {guild, action: start, …}                ne lancia uno
 * POST {guild, action: end|reroll|cancel, code} chiude, rifa' l'estrazione, annulla
 *
 * Il bot fa esattamente quello che fa /giveaway: qui si controlla quello che
 * arriva dalla pagina, il bot ricontrolla su Discord.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/reward_mail.php';

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

const GIVEAWAY_REWARD_TYPES = ['points', 'godoshards', 'character', 'badge', 'premium'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ['access' => $access, 'input' => $input] = botpanel_begin_action($user, $guildId, 'giveaways', 15);
    $action = (string)($input['action'] ?? '');

    if (in_array($action, ['end', 'reroll', 'cancel'], true)) {
        $code = strtolower(trim((string)($input['code'] ?? '')));
        if (!preg_match('/^(c|#)?\d{1,12}$/', $code)) {
            botpanel_fail('bad_request', 'Giveaway non valido.', 400);
        }

        $response = botpanel_bot_action('/v1/panel/giveaways/manage', $user, $guildId, ['code' => $code, 'action' => $action]);
        botpanel_audit($mysqli, $guildId, $user, 'giveaway', 'giveaway_' . $action, null, ['code' => $code]);

        botpanel_json([
            'ok' => true,
            'outcome' => (string)($response['outcome'] ?? ''),
            'reason' => (string)($response['reason'] ?? ''),
            'winners' => array_values(array_filter(array_map('strval', (array)($response['winners'] ?? [])), 'botpanel_is_snowflake')),
        ]);
    }

    if ($action !== 'start') {
        botpanel_fail('bad_action', 'Azione sconosciuta.', 400);
    }

    $ctx = botpanel_validation_context(botpanel_meta($guildId), $access, true);
    $errors = [];

    $title = trim((string)preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($input['title'] ?? '')));
    if ($title === '' || mb_strlen($title) > 200) {
        $errors['title'] = $title === '' ? 'required' : 'too_long';
    }

    $description = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)($input['description'] ?? '')));
    if (mb_strlen($description) > 1000) {
        $errors['description'] = 'too_long';
    }

    // Si pubblica solo dove il bot puo' scrivere: lo dice l'elenco del bot.
    $channelId = (string)($input['channel_id'] ?? '');
    $channel = $ctx['channels'][$channelId] ?? null;
    if (!$channel || !in_array($channel['kind'] ?? '', ['text', 'announcement'], true)) {
        $errors['channel_id'] = $channelId === '' ? 'required' : 'channel';
    } elseif (empty($channel['bot']['view']) || empty($channel['bot']['send']) || empty($channel['bot']['embed'])) {
        $errors['channel_id'] = 'bot_cannot:send';
    }

    $minutes = (int)($input['duration_minutes'] ?? 0);
    if ($minutes < 1 || $minutes > 60 * 24 * 30) {
        $errors['duration_minutes'] = 'range';
    }

    $winners = (int)($input['winners'] ?? 1);
    if ($winners < 1 || $winners > 20) {
        $errors['winners'] = 'range';
    }

    // Ruoli del server, e nient'altro: non vengono dati, ma finiscono nel
    // messaggio come requisito.
    foreach (['required_role_id', 'bonus_role_id'] as $key) {
        $roleId = (string)($input[$key] ?? '');
        if ($roleId !== '' && !isset($ctx['roles'][$roleId])) {
            $errors[$key] = 'role';
        }
    }

    $bonusEntries = (int)($input['bonus_entries'] ?? 1);
    if ($bonusEntries < 1 || $bonusEntries > 10) {
        $errors['bonus_entries'] = 'range';
    }

    // Premi del sito: solo nel server di Cripsum (dove salva solo un admin).
    $rewards = [];
    $rawRewards = array_slice(is_array($input['rewards'] ?? null) ? $input['rewards'] : [], 0, 3);

    if ($rawRewards !== []) {
        if (empty($access['is_home'])) {
            botpanel_fail('invalid', 'I premi del sito si regalano solo nel server di Cripsum.', 422, ['errors' => ['rewards' => 'home_only']]);
        }

        foreach ($rawRewards as $index => $reward) {
            $type = (string)($reward['type'] ?? '');
            $value = trim((string)($reward['value'] ?? ''));
            $quantity = (int)($reward['quantity'] ?? 1);

            if (!in_array($type, GIVEAWAY_REWARD_TYPES, true) || $value === '' || mb_strlen($value) > 190 || $quantity < 1 || $quantity > 999) {
                $errors['rewards.' . $index] = 'reward';
                continue;
            }

            $rewards[] = ['type' => $type, 'value' => $value, 'quantity' => $quantity];
        }

        // Gli stessi controlli della posta: un premio che il sito non sa
        // consegnare si ferma qui, non all'estrazione.
        if ($rewards !== []) {
            $normalized = cripsum_normalize_rewards($rewards);
            if (!$normalized['ok']) {
                $errors['rewards'] = 'reward';
            }
        }
    }

    if ($errors !== []) {
        botpanel_fail('invalid', 'Alcuni valori non vanno bene.', 422, ['errors' => $errors]);
    }

    $response = botpanel_bot_action('/v1/panel/giveaways/start', $user, $guildId, [
        'channel_id' => $channelId,
        'title' => $title,
        'description' => $description,
        'winners' => $winners,
        'duration_seconds' => $minutes * 60,
        'required_role_id' => (string)($input['required_role_id'] ?? ''),
        'bonus_role_id' => (string)($input['bonus_role_id'] ?? ''),
        'bonus_entries' => $bonusEntries,
        'repeats' => !empty($input['repeats']) && $rewards === [],
        'rewards' => $rewards,
    ]);

    botpanel_audit($mysqli, $guildId, $user, 'giveaway', 'giveaway_start', null, [
        'code' => (string)($response['code'] ?? ''),
        'title' => $title,
        'channel_id' => $channelId,
        'rewards' => $rewards,
    ]);

    botpanel_json([
        'ok' => true,
        'code' => (string)($response['code'] ?? ''),
        'ends' => (int)($response['endsUnix'] ?? 0),
        'url' => botpanel_discord_url($response['url'] ?? ''),
    ]);
}

$access = botpanel_access($user, $guildId);
cripsum_release_session();

$list = [];

if (auth_table_exists($mysqli, 'discord_guild_giveaways')) {
    $stmt = $mysqli->prepare(
        "SELECT g.id, g.channel_id, g.message_id, g.prize, g.winners_count, g.repeats, g.status, g.ends_at,
                g.required_role_id, g.bonus_role_id, g.bonus_entries,
                (SELECT COUNT(*) FROM discord_guild_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries,
                (SELECT GROUP_CONCAT(e.discord_user_id) FROM discord_guild_giveaway_entries e WHERE e.giveaway_id = g.id AND e.won = 1) AS winners
         FROM discord_guild_giveaways g
         WHERE g.guild_id = ? AND (g.status = 'running' OR (g.status = 'ended' AND g.drawn_at > (NOW() - INTERVAL 14 DAY)))
         ORDER BY (g.status = 'running') DESC, g.ends_at DESC LIMIT 30"
    );

    if ($stmt) {
        $stmt->bind_param('s', $guildId);
        $stmt->execute();

        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $list[] = [
                'code' => 'c' . (int)$row['id'],
                'title' => (string)$row['prize'],
                'site' => false,
                'running' => (string)$row['status'] === 'running',
                'repeats' => (int)$row['repeats'] === 1,
                'winners_count' => (int)$row['winners_count'],
                'winners' => $row['winners'] !== null ? array_values(array_filter(explode(',', (string)$row['winners']), 'botpanel_is_snowflake')) : [],
                'entries' => (int)$row['entries'],
                'required_role_id' => $row['required_role_id'] !== null ? (string)$row['required_role_id'] : null,
                'url' => $row['message_id'] !== null ? 'https://discord.com/channels/' . $guildId . '/' . $row['channel_id'] . '/' . $row['message_id'] : null,
                // ends_at lo scrive PHP: si rilegge con PHP.
                'ends' => (int)strtotime((string)$row['ends_at']),
            ];
        }

        $stmt->close();
    }
}

// Nel server di Cripsum ci sono anche quelli con i premi del sito.
if ($access['is_home'] && auth_table_exists($mysqli, 'discord_giveaways')) {
    $stmt = $mysqli->prepare(
        "SELECT g.id, g.channel_id, g.message_id, g.title, g.winners_count, g.ends_at, g.rewards,
                (SELECT COUNT(*) FROM discord_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries
         FROM discord_giveaways g
         WHERE g.guild_id = ? AND g.status = 'running'
         ORDER BY g.ends_at ASC LIMIT 25"
    );

    if ($stmt) {
        $stmt->bind_param('s', $guildId);
        $stmt->execute();

        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $rewards = cripsum_normalize_rewards(json_decode((string)$row['rewards'], true) ?: []);

            $list[] = [
                'code' => '#' . (int)$row['id'],
                'title' => (string)$row['title'],
                'site' => true,
                'running' => true,
                'repeats' => false,
                'winners_count' => (int)$row['winners_count'],
                'winners' => [],
                'entries' => (int)$row['entries'],
                'prizes' => $rewards['ok'] ? array_map('cripsum_reward_label', $rewards['rewards']) : [],
                'url' => $row['message_id'] !== null ? 'https://discord.com/channels/' . $guildId . '/' . $row['channel_id'] . '/' . $row['message_id'] : null,
                'ends' => (int)strtotime((string)$row['ends_at']),
            ];
        }

        $stmt->close();
    }
}

// I nomi dei vincitori, dalla memoria del bot.
$winnerIds = [];
foreach ($list as $entry) {
    foreach ($entry['winners'] as $id) {
        $winnerIds[$id] = true;
    }
}

$users = [];
if ($winnerIds !== []) {
    $response = botpanel_bot('/v1/panel/users', ['guild_id' => $guildId, 'ids' => array_map('strval', array_keys($winnerIds))], 5);
    foreach ((array)($response['users'] ?? []) as $id => $entry) {
        if (is_array($entry)) {
            $avatar = (string)($entry['avatar'] ?? '');
            $users[(string)$id] = [
                'name' => mb_substr((string)($entry['name'] ?? ''), 0, 80),
                'avatar' => str_starts_with($avatar, 'https://cdn.discordapp.com/') ? $avatar : null,
            ];
        }
    }
}

botpanel_json([
    'ok' => true,
    'available' => auth_table_exists($mysqli, 'discord_guild_giveaways'),
    'giveaways' => $list,
    'users' => (object)$users,
    'site_prizes' => $access['is_home'],
]);
