<?php
declare(strict_types=1);

/**
 * Avvisi in privato su Discord: Poppy scrive a chi ha l'account collegato
 * quando succede qualcosa che lo riguarda (lo staff ha risposto al suo
 * ticket, c'e' un premio da riscattare nella posta).
 *
 * Valgono le stesse regole del resto del sito:
 * - solo un Discord collegato con l'accesso Discord, mai un ID scritto a mano;
 * - chi li ha spenti dalle impostazioni non li riceve (`discord_dm_optout`);
 * - il bot scrive solo a chi e' nel server di Cripsum, e lo decide lui;
 * - un avviso non deve mai rallentare la pagina che lo fa partire: tempi
 *   brevissimi, e se il bot non risponde l'avviso si perde, la posta resta.
 */

require_once __DIR__ . '/bot_client.php';
require_once __DIR__ . '/bot_auth.php';

/** Vero se la persona non ha spento gli avvisi in privato. */
function cripsum_discord_dm_enabled(mysqli $mysqli, int $userId): bool
{
    if (!auth_table_exists($mysqli, 'discord_dm_optout')) {
        return true;
    }

    $stmt = $mysqli->prepare('SELECT 1 FROM discord_dm_optout WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $off = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return !$off;
}

/** Accende o spegne gli avvisi in privato di una persona. */
function cripsum_discord_dm_set(mysqli $mysqli, int $userId, bool $enabled): bool
{
    if (!auth_table_exists($mysqli, 'discord_dm_optout')) {
        return false;
    }

    $stmt = $mysqli->prepare($enabled
        ? 'DELETE FROM discord_dm_optout WHERE user_id = ?'
        : 'INSERT IGNORE INTO discord_dm_optout (user_id) VALUES (?)');

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Manda un avviso in privato.
 *
 * @param array{kind: string, title: string, text: string, url?: string} $notice
 *   in inglese, come tutto quello che scrive Poppy; `url` e' un indirizzo del sito.
 */
function cripsum_discord_dm(mysqli $mysqli, int $userId, array $notice): bool
{
    if ($userId <= 0 || !function_exists('curl_init') || !cripsum_discord_dm_enabled($mysqli, $userId)) {
        return false;
    }

    // L'ID vale solo se il collegamento e' passato dall'accesso Discord.
    $stmt = $mysqli->prepare(
        'SELECT ' . cripsum_linked_discord_sql('u') . ' AS discord_id FROM utenti u WHERE u.id = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $discordId = (string)($stmt->get_result()->fetch_row()[0] ?? '');
    $stmt->close();

    if (!preg_match('/^\d{15,25}$/', $discordId)) {
        return false;
    }

    $path = '/v1/notify/dm';
    $body = (string)json_encode([
        'discord_id' => $discordId,
        'kind' => (string)($notice['kind'] ?? 'info'),
        'title' => mb_substr((string)($notice['title'] ?? ''), 0, 200),
        'text' => mb_substr((string)($notice['text'] ?? ''), 0, 1500),
        'url' => (string)($notice['url'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init(cripsum_bot_endpoint($path));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => cripsum_bot_headers([], $path, $body),
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS => 2000,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response !== false && $status >= 200 && $status < 300;
}
