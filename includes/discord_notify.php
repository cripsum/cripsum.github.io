<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/discord_oauth.php';
require_once __DIR__ . '/bot_client.php';

function notifyDiscordNewPost($mysqli, $postId, $type)
{
    $postId = (int)$postId;
    $type = ($type === 'rimasto') ? 'rimasto' : 'shitpost';
    
    // Le costanti dei webhook non servono piu': pubblica il bot. Restano
    // definite in config/discord_oauth.php solo per compatibilita'.

    // Fetch post details from database
    if ($type === 'rimasto') {
        $stmt = $mysqli->prepare("
            SELECT t.id, t.titolo, t.descrizione, u.username 
            FROM toprimasti t 
            LEFT JOIN utenti u ON t.id_utente = u.id 
            WHERE t.id = ? AND t.approvato = 1 
            LIMIT 1
        ");
    } else {
        $stmt = $mysqli->prepare("
            SELECT s.id, s.titolo, s.descrizione, u.username 
            FROM shitposts s 
            LEFT JOIN utenti u ON s.id_utente = u.id 
            WHERE s.id = ? AND s.approvato = 1 
            LIMIT 1
        ");
    }
    
    if (!$stmt) return false;
    $stmt->bind_param('i', $postId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    
    $res = $stmt->get_result();
    $post = $res->fetch_assoc();
    $stmt->close();
    
    if (!$post) return false; // Post not found or not approved
    
    $title = $post['titolo'] ?: 'Nuovo Post';
    $desc = $post['descrizione'] ?: '';
    $author = $post['username'] ?: 'Utente';
    
    // Clean description to avoid Markdown breaking or too long content
    if (mb_strlen($desc) > 300) {
        $desc = mb_substr($desc, 0, 297) . '...';
    }
    
    $postTypeLabel = ($type === 'rimasto') ? 'Top Rimasti' : 'Shitpost';
    $postUrl = "https://cripsum.com/it/" . ($type === 'rimasto' ? 'rimasti' : 'shitpost') . "?post=" . $postId;
    $mediaUrl = "https://cripsum.com/api/content/get_media.php?id=" . $postId . "&type=" . $type;
    
    // L'annuncio lo pubblica il bot, non piu' un webhook anonimo: cosi' porta
    // nome e immagine di Poppy e resta modificabile come ogni suo messaggio.
    // Il canale lo risolve il bot dal nome logico, con la sua configurazione.
    $payload = [
        'target' => ($type === 'rimasto') ? 'rimasto' : 'shitpost',
        'title' => $title,
        'description' => $desc,
        'url' => $postUrl,
        'color' => ($type === 'rimasto') ? 10070784 : 15728895,
        'author_name' => 'Nuovo ' . $postTypeLabel . ' da @' . $author,
        'image' => $mediaUrl,
        'footer_text' => 'Cripsum.com • ' . date('d/m/Y H:i'),
    ];

    $ch = curl_init(cripsum_bot_endpoint('/v1/announce'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => cripsum_bot_headers(),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log('[Discord Announce] Annuncio non pubblicato (' . $status . '): ' . (is_string($response) ? substr($response, 0, 300) : 'nessuna risposta'));
        return false;
    }

    return true;
}

/**
 * Notifica su Discord per Log di Sicurezza e Staff (Canale #site-logs, ID: 1535798916656668782)
 */
function notifyDiscordSiteLogs(string $type, string $title, string $description, array $fields = [], ?int $userId = null, ?string $discordId = null): bool
{
    $endpoint = cripsum_bot_endpoint('/v1/logs');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $username = $_SESSION['username'] ?? null;

    $payload = [
        'type' => $type,
        'title' => $title,
        'description' => $description,
        'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
        'username' => $username,
        'discord_id' => $discordId,
        'ip' => $ip,
        'fields' => $fields,
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => cripsum_bot_headers(),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($status >= 200 && $status < 300);
}

/**
 * Notifica su Discord per Candidature ricevute dal sito (Chi Siamo)
 */
function notifyDiscordCandidatura(array $data): bool
{
    $endpoint = cripsum_bot_endpoint('/v1/candidature');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $payload = array_merge([
        'ip' => $ip,
        'user_id' => $_SESSION['user_id'] ?? null,
        'discord_id' => $_SESSION['discord_id'] ?? null,
    ], $data);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => cripsum_bot_headers(),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($status >= 200 && $status < 300);
}

/**
 * Invia le segnalazioni usando lo stesso relay dei ticket di supporto.
 * L'endpoint /v1/tickets pubblica nel canale Discord #website-support.
 */
function notifyDiscordSupportReport(string $reportType, array $data): bool
{
    $limit = static function ($value, int $max, string $fallback = 'N/D'): string {
        $value = trim((string)$value);
        if ($value === '') return $fallback;
        return mb_strlen($value, 'UTF-8') > $max
            ? mb_substr($value, 0, max(1, $max - 3), 'UTF-8') . '...'
            : $value;
    };

    $discordSafe = static function (string $value): string {
        return str_replace(
            ['@', "```"],
            ['@' . "\u{200B}", "` ` `"],
            $value
        );
    };

    $labels = [
        'profile' => 'Profilo utente',
        'rimasto' => 'Top Rimasti',
        'shitpost' => 'Shitpost',
        'chat' => 'Messaggio chat',
    ];

    $typeLabel = $labels[$reportType] ?? ucfirst($limit($reportType, 40, 'Segnalazione'));
    $reporterUserId = (int)($data['reporter_id'] ?? ($_SESSION['user_id'] ?? 0));
    $reporterUsername = $limit($data['reporter_username'] ?? ($_SESSION['username'] ?? ''), 80, 'Utente sconosciuto');
    $reporterRole = $limit($data['reporter_role'] ?? ($_SESSION['ruolo'] ?? ''), 40, 'Utente');
    $reporterDiscordId = $limit($data['reporter_discord_id'] ?? ($_SESSION['discord_id'] ?? ''), 30, 'Non collegato');

    $targetId = (int)($data['target_id'] ?? 0);
    $targetName = $limit($data['target_name'] ?? '', 180);
    $targetAuthor = $limit($data['target_author'] ?? '', 80, 'N/D');
    $targetAuthorId = (int)($data['target_author_id'] ?? 0);
    $reason = $limit($data['reason'] ?? '', 500, 'Non specificato');
    $detail = $limit($data['detail'] ?? '', 500, 'Nessun dettaglio aggiuntivo');
    $contentSnippet = $limit($data['content_snippet'] ?? '', 500, 'Nessun testo disponibile');
    $targetUrl = filter_var((string)($data['target_url'] ?? ''), FILTER_VALIDATE_URL) ?: '';
    $mediaUrl = filter_var((string)($data['media_url'] ?? ''), FILTER_VALIDATE_URL) ?: '';
    $ip = $limit($_SERVER['REMOTE_ADDR'] ?? '', 64);

    $reportCode = 'RP-' . strtoupper(substr(hash(
        'sha256',
        $reportType . '|' . microtime(true) . '|' . mt_rand()
    ), 0, 8));

    $lines = [
        "**Tipo:** {$typeLabel}",
        "**Segnalato da:** `@{$reporterUsername}` · ID sito `" . ($reporterUserId ?: 'N/D') . "` · Ruolo `{$reporterRole}`",
        "**Discord del segnalante:** `{$reporterDiscordId}`",
        "**" . ($reportType === 'profile' ? 'Profilo segnalato' : ($reportType === 'chat' ? 'Messaggio segnalato' : 'Post segnalato')) . ":** {$targetName}",
        "**ID segnalato:** `" . ($targetId ?: 'N/D') . "`",
        "**" . ($reportType === 'profile' ? 'Username profilo' : 'Autore') . ":** `@{$targetAuthor}`" . ($targetAuthorId > 0 ? " · ID sito `{$targetAuthorId}`" : ''),
        "**Motivo:** {$reason}",
        "**Dettagli inseriti:** {$detail}",
    ];

    if ($reportType !== 'profile') {
        $lines[] = "**Contenuto:** {$contentSnippet}";
    }
    if ($targetUrl !== '') {
        $lines[] = "**Link diretto:** {$targetUrl}";
    }

    $message = $discordSafe(implode("\n", $lines));
    $message = $limit($message, 1900, 'Dati segnalazione non disponibili.');

    $payload = [
        // Campi nativi già usati da it/en/supporto.php.
        'ticket_id' => $reportCode,
        'username' => $reporterUsername,
        'user_id' => $reporterUserId ?: 'N/A',
        'role' => $reporterRole,
        'contact' => 'Account Cripsum',
        'discord_id' => $reporterDiscordId === 'Non collegato' ? '' : $reporterDiscordId,
        'title' => $limit("Segnalazione {$typeLabel}: {$targetName}", 180),
        'topic' => "Segnalazione · {$typeLabel}",
        'message' => $message,
        'attachment_url' => $mediaUrl !== '' ? $mediaUrl : null,
        'ip' => $ip,

        // Contesto strutturato aggiuntivo, utile al bot senza perdere dati.
        'report_type' => $reportType,
        'target_id' => $targetId ?: null,
        'target_name' => $targetName,
        'target_author' => $targetAuthor,
        'target_author_id' => $targetAuthorId ?: null,
        'reason' => $reason,
        'detail' => $detail,
        'content_snippet' => $contentSnippet,
        'target_url' => $targetUrl !== '' ? $targetUrl : null,
    ];

    $ch = curl_init(cripsum_bot_endpoint('/v1/tickets'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ),
        CURLOPT_HTTPHEADER => cripsum_bot_headers(),
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = is_string($response) ? json_decode($response, true) : null;
    $sent = $status >= 200
        && $status < 300
        && (!is_array($decoded) || !array_key_exists('success', $decoded) || !empty($decoded['success']));

    if (!$sent) {
        $responseText = is_string($response) ? $limit($response, 300, 'empty response') : 'empty response';
        error_log('[Discord Website Support] Ticket relay failed (' . $status . '): ' . ($curlError ?: $responseText));
    }

    return $sent;
}

/**
 * Ringraziamento su Discord per chi ha appena comprato il Premium.
 *
 * Il testo e il canale li decide il modello personalizzabile sul bot
 * (`/premiumthanks edit`): qui si dice soltanto chi e quando. Da chiamare solo
 * quando l'attivazione e' avvenuta davvero in questa richiesta, altrimenti si
 * ringrazia due volte la stessa persona.
 */
function notifyDiscordPremiumPurchase(mysqli $mysqli, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $stmt = $mysqli->prepare('SELECT username, discord_id FROM utenti WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return false;
    }

    if (!function_exists('curl_init')) {
        return false;
    }

    $payload = [
        'kind' => 'premium',
        'username' => (string)$user['username'],
        'discord_id' => (string)($user['discord_id'] ?? ''),
    ];

    $ch = curl_init(cripsum_bot_endpoint('/v1/celebrate'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => cripsum_bot_headers(),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log('[Discord Premium] Ringraziamento non pubblicato (' . $status . '): '
            . (is_string($response) ? substr($response, 0, 200) : 'nessuna risposta'));
        return false;
    }

    return true;
}
