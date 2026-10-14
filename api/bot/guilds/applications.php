<?php
declare(strict_types=1);

/**
 * Candidature fatte da Discord, in qualsiasi server.
 *
 * Il sito tiene le risposte e l'esito, per due motivi: una persona ha una
 * sola candidatura in attesa per modulo, e dopo un rifiuto aspetta i giorni
 * scelti dal server prima di riprovare. Chi puo' decidere lo controlla il
 * bot su Discord, o il pannello del sito.
 *
 * Gli orari sono secondi Unix scritti da PHP: confrontati sempre fra loro,
 * non dipendono dal fuso del database.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_applications')) {
    bot_json(['ok' => false, 'available' => false, 'error' => 'Applications are not installed on the website yet.'], 503);
}

$action = bot_text('action', 10, true);
$guildId = bot_snowflake('guild_id');
$body = bot_input();

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function app_public(array $row): array
{
    $answers = json_decode((string)$row['answers'], true);

    return [
        'id' => (int)$row['id'],
        'form_id' => (string)$row['form_id'],
        'form_title' => (string)$row['form_title'],
        'user_id' => (string)$row['user_discord_id'],
        'answers' => is_array($answers) ? array_values($answers) : [],
        'status' => (string)$row['status'],
        'reviewer_id' => $row['reviewer_discord_id'] !== null ? (string)$row['reviewer_discord_id'] : null,
        'reason' => (string)($row['reason'] ?? ''),
        'review_channel_id' => $row['review_channel_id'] !== null ? (string)$row['review_channel_id'] : null,
        'review_message_id' => $row['review_message_id'] !== null ? (string)$row['review_message_id'] : null,
        'created_unix' => (int)$row['created_unix'],
        'decided_unix' => $row['decided_unix'] !== null ? (int)$row['decided_unix'] : null,
    ];
}

/**
 * Si puo' fare domanda? null se si', altrimenti il motivo.
 *
 * @return array{reason: string, retry_unix?: int}|null
 */
function app_blocked(mysqli $mysqli, string $guildId, string $formId, string $userId, int $cooldownDays): ?array
{
    $stmt = $mysqli->prepare(
        "SELECT status, decided_unix FROM discord_applications
         WHERE guild_id = ? AND form_id = ? AND user_discord_id = ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('sss', $guildId, $formId, $userId);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$last) {
        return null;
    }

    if ($last['status'] === 'pending') {
        return ['reason' => 'pending'];
    }

    if ($last['status'] === 'rejected' && $cooldownDays > 0) {
        $retry = (int)$last['decided_unix'] + $cooldownDays * 86400;
        if ($retry > time()) {
            return ['reason' => 'cooldown', 'retry_unix' => $retry];
        }
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function app_require(mysqli $mysqli, int $id, string $guildId): array
{
    $stmt = $mysqli->prepare('SELECT * FROM discord_applications WHERE id = ? AND guild_id = ? LIMIT 1');
    $stmt->bind_param('is', $id, $guildId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        bot_fail('Application not found.', 404);
    }

    return $row;
}

switch ($action) {
    case 'check': {
        $blocked = app_blocked(
            $mysqli,
            $guildId,
            bot_text('form_id', 12, true),
            bot_discord_id('user_discord_id'),
            max(0, min(365, (int)($body['cooldown_days'] ?? 0)))
        );

        bot_json(['ok' => true, 'reason' => $blocked['reason'] ?? null, 'retry_unix' => $blocked['retry_unix'] ?? null]);
    }

    case 'create': {
        $formId = bot_text('form_id', 12, true);
        $userId = bot_discord_id('user_discord_id');
        $blocked = app_blocked($mysqli, $guildId, $formId, $userId, max(0, min(365, (int)($body['cooldown_days'] ?? 0))));

        if ($blocked !== null) {
            bot_json(['ok' => true, 'rejected' => $blocked['reason'], 'retry_unix' => $blocked['retry_unix'] ?? null]);
        }

        // Al massimo cinque domande, testi gia' limitati dal bot: qui si
        // ripete, perche' finiscono nel pannello del sito.
        $answers = [];
        foreach (array_slice(is_array($body['answers'] ?? null) ? $body['answers'] : [], 0, 5) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $answers[] = [
                'question' => mb_substr(trim((string)($entry['question'] ?? '')), 0, 100),
                'answer' => mb_substr(trim((string)($entry['answer'] ?? '')), 0, 1000),
            ];
        }

        if ($answers === []) {
            bot_fail('Missing answers.', 400);
        }

        $title = bot_text('form_title', 80, true);
        $json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();

        $stmt = $mysqli->prepare(
            'INSERT INTO discord_applications (guild_id, form_id, form_title, user_discord_id, answers, created_unix)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssi', $guildId, $formId, $title, $userId, $json, $now);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();

        bot_json(['ok' => true, 'application' => app_public(app_require($mysqli, $id, $guildId))]);
    }

    case 'attach': {
        $id = (int)($body['id'] ?? 0);
        $channelId = bot_snowflake('channel_id');
        $messageId = bot_snowflake('message_id');

        $stmt = $mysqli->prepare(
            'UPDATE discord_applications SET review_channel_id = ?, review_message_id = ? WHERE id = ? AND guild_id = ?'
        );
        $stmt->bind_param('ssis', $channelId, $messageId, $id, $guildId);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true]);
    }

    /* Una decisione sola: due persone che premono insieme non decidono due volte. */
    case 'decide': {
        $row = app_require($mysqli, (int)($body['id'] ?? 0), $guildId);
        $decision = bot_text('decision', 10, true);

        if (!in_array($decision, ['accepted', 'rejected'], true)) {
            bot_fail('Invalid decision.', 400);
        }

        $id = (int)$row['id'];
        $reviewer = bot_discord_id('reviewer_discord_id');
        $reason = bot_text('reason', 500);
        $reasonOrNull = $reason !== '' ? $reason : null;
        $now = time();

        $stmt = $mysqli->prepare(
            "UPDATE discord_applications
             SET status = ?, reviewer_discord_id = ?, reason = ?, decided_unix = ?
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->bind_param('sssii', $decision, $reviewer, $reasonOrNull, $now, $id);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        $after = app_public(app_require($mysqli, $id, $guildId));

        bot_json($changed
            ? ['ok' => true, 'application' => $after]
            : ['ok' => true, 'rejected' => 'already', 'application' => $after]);
    }

    default:
        bot_fail('Unknown action.', 400);
}
