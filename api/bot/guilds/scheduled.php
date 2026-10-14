<?php
declare(strict_types=1);

/**
 * Messaggi programmati: le scadenze che il bot viene a chiedere.
 *
 *   due   quelli da pubblicare adesso, e quando cade il prossimo
 *   sent  com'e' andata; un messaggio che si ripete passa alla volta dopo
 *
 * Si scrivono e si cancellano dal pannello (api/botpanel/schedule.php). Gli
 * orari sono secondi Unix: la ripetizione si calcola nel fuso di chi l'ha
 * programmata, cosi' "ogni lunedi' alle 18" resta alle 18 anche quando cambia
 * l'ora legale.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/botpanel/schedule.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_scheduled_posts')) {
    bot_json(['ok' => true, 'available' => false, 'due' => []]);
}

const SCHEDULED_PAGE = 20;

$action = bot_text('action', 10, true);
$body = bot_input();
$now = time();

if ($action === 'due') {
    $limit = SCHEDULED_PAGE;
    $stmt = $mysqli->prepare(
        'SELECT id, guild_id, template_name, channel_id, next_unix FROM discord_scheduled_posts
         WHERE enabled = 1 AND next_unix <= ? ORDER BY next_unix ASC LIMIT ?'
    );
    $stmt->bind_param('ii', $now, $limit);
    $stmt->execute();
    $due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT MIN(next_unix) AS next_unix FROM discord_scheduled_posts WHERE enabled = 1 AND next_unix > ?');
    $stmt->bind_param('i', $now);
    $stmt->execute();
    $next = $stmt->get_result()->fetch_assoc()['next_unix'] ?? null;
    $stmt->close();

    bot_json([
        'ok' => true,
        'available' => true,
        'page' => SCHEDULED_PAGE,
        'due' => array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'guild_id' => (string)$row['guild_id'],
            'template_name' => (string)$row['template_name'],
            'channel_id' => (string)$row['channel_id'],
        ], $due),
        'next_unix' => $next !== null ? (int)$next : null,
    ]);
}

if ($action !== 'sent') {
    bot_fail('Unknown action.', 400);
}

$guildId = bot_snowflake('guild_id');
$id = (int)($body['id'] ?? 0);

$stmt = $mysqli->prepare('SELECT * FROM discord_scheduled_posts WHERE id = ? AND guild_id = ? LIMIT 1');
$stmt->bind_param('is', $id, $guildId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    bot_fail('Scheduled message not found.', 404);
}

$ok = !empty($body['ok']);
$error = $ok ? null : mb_substr(preg_replace('/[^a-z_]/', '', (string)($body['error'] ?? 'failed')), 0, 40);

// Dopo la pubblicazione (riuscita o no) la scadenza va avanti: un messaggio
// che non si riesce a pubblicare non deve essere ritentato a ogni giro.
$next = botpanel_schedule_next((int)$post['next_unix'], (string)$post['repeat_kind'], (string)$post['tz'], $now);
$enabled = $next !== null ? 1 : 0;
$nextUnix = $next ?? (int)$post['next_unix'];

$stmt = $mysqli->prepare(
    'UPDATE discord_scheduled_posts
     SET next_unix = ?, enabled = ?, last_sent_unix = ?, last_error = ?
     WHERE id = ?'
);
$stmt->bind_param('iiisi', $nextUnix, $enabled, $now, $error, $id);
$stmt->execute();
$stmt->close();

bot_json(['ok' => true, 'next_unix' => $next]);
