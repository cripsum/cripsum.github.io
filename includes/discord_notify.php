<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/discord_oauth.php';

function notifyDiscordNewPost($mysqli, $postId, $type)
{
    $postId = (int)$postId;
    $type = ($type === 'rimasto') ? 'rimasto' : 'shitpost';
    
    // Choose correct webhook URL
    $webhookUrl = ($type === 'rimasto') ? CRIPSUM_DISCORD_RIMASTI_WEBHOOK : CRIPSUM_DISCORD_SHITPOST_WEBHOOK;
    
    if (empty($webhookUrl)) {
        return false; // Webhook not configured
    }
    
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
    
    // Construct Discord Embed Payload
    $payload = [
        'embeds' => [
            [
                'title' => $title,
                'description' => $desc,
                'url' => $postUrl,
                'color' => ($type === 'rimasto') ? 10070784 : 15728895, // Rimasto: Violet, Shitpost: Pink/Gold
                'author' => [
                    'name' => "Nuovo " . $postTypeLabel . " da @" . $author,
                ],
                'image' => [
                    'url' => $mediaUrl,
                ],
                'footer' => [
                    'text' => "Cripsum.com • " . date('d/m/Y H:i'),
                ]
            ]
        ]
    ];
    
    // Send to Discord via cURL
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $status === 204 || $status === 200;
}

/**
 * Notifica su Discord per Log di Sicurezza e Staff (Canale #site-logs, ID: 1535798916656668782)
 */
function notifyDiscordSiteLogs(string $type, string $title, string $description, array $fields = [], ?int $userId = null, ?string $discordId = null): bool
{
    $endpoint = defined('CRIPSUM_BOT_ENDPOINT') ? CRIPSUM_BOT_ENDPOINT . '/v1/logs' : 'https://api.cripsum.com/v1/logs';
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
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
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
    $endpoint = defined('CRIPSUM_BOT_ENDPOINT') ? CRIPSUM_BOT_ENDPOINT . '/v1/candidature' : 'https://api.cripsum.com/v1/candidature';
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
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
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
 * Notifica su Discord nel canale #website-support per le segnalazioni dal sito (Channel ID: 1521100942668206110)
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

    $labels = [
        'profile' => 'Profilo utente',
        'rimasto' => 'Top Rimasti',
        'shitpost' => 'Shitpost',
        'chat' => 'Messaggio chat',
    ];
    $typeLabel = $labels[$reportType] ?? ucfirst($limit($reportType, 40, 'Segnalazione'));

    $reporterUserId = (int)($data['reporter_id'] ?? ($_SESSION['user_id'] ?? 0));
    $reporterUsername = $limit($data['reporter_username'] ?? ($_SESSION['username'] ?? ''), 80, 'Utente sconosciuto');
    $targetId = (int)($data['target_id'] ?? 0);
    $targetName = $limit($data['target_name'] ?? '', 220);
    $targetAuthor = $limit($data['target_author'] ?? '', 80, '');
    $targetAuthorId = (int)($data['target_author_id'] ?? 0);
    $reason = $limit($data['reason'] ?? '', 900, 'Non specificato');
    $detail = $limit($data['detail'] ?? '', 900, 'Nessun dettaglio aggiuntivo');
    $contentSnippet = $limit($data['content_snippet'] ?? '', 900, 'Nessun testo disponibile');
    $targetUrl = filter_var((string)($data['target_url'] ?? ''), FILTER_VALIDATE_URL) ?: '';
    $mediaUrl = filter_var((string)($data['media_url'] ?? ''), FILTER_VALIDATE_URL) ?: '';
    $ip = $limit($_SERVER['REMOTE_ADDR'] ?? '', 64);
    $createdAt = date('c');

    $reporterValue = "@{$reporterUsername}" . ($reporterUserId > 0 ? " · ID sito `{$reporterUserId}`" : '');
    $targetValue = $targetName . ($targetId > 0 ? "\nID contenuto/utente: `{$targetId}`" : '');
    $authorValue = $targetAuthor !== ''
        ? "@{$targetAuthor}" . ($targetAuthorId > 0 ? " · ID sito `{$targetAuthorId}`" : '')
        : 'N/D';

    $targetFieldLabel = $reportType === 'profile'
        ? 'Profilo segnalato'
        : ($reportType === 'chat' ? 'Messaggio segnalato' : 'Post segnalato');
    $authorFieldLabel = $reportType === 'profile'
        ? 'Username del profilo'
        : ($reportType === 'chat' ? 'Autore del messaggio' : 'Autore del post');

    $embedFields = [
        ['name' => 'Tipo', 'value' => $typeLabel, 'inline' => true],
        ['name' => 'Segnalato da', 'value' => $limit($reporterValue, 1024), 'inline' => true],
        ['name' => $targetFieldLabel, 'value' => $limit($targetValue, 1024), 'inline' => false],
        ['name' => $authorFieldLabel, 'value' => $limit($authorValue, 1024), 'inline' => true],
        ['name' => 'Motivo', 'value' => $limit($reason, 1024), 'inline' => false],
    ];

    if ($reportType !== 'profile') {
        $embedFields[] = ['name' => 'Contenuto del post', 'value' => $limit($contentSnippet, 1024), 'inline' => false];
    }
    $embedFields[] = ['name' => 'Dettagli inseriti', 'value' => $limit($detail, 1024), 'inline' => false];
    if ($targetUrl !== '') {
        $embedFields[] = ['name' => 'Link diretto', 'value' => "[Apri su Cripsum]({$targetUrl})", 'inline' => false];
    }

    $embed = [
        'title' => "Nuova segnalazione · {$typeLabel}",
        'description' => 'Tutti i dati raccolti dal modulo di segnalazione del sito.',
        'color' => 15158332,
        'fields' => $embedFields,
        'footer' => ['text' => "Cripsum Website Support · IP {$ip}"],
        'timestamp' => $createdAt,
    ];
    if ($targetUrl !== '') $embed['url'] = $targetUrl;
    if ($mediaUrl !== '' && $reportType !== 'profile') $embed['image'] = ['url' => $mediaUrl];

    $discordPayload = [
        'username' => 'Cripsum Website Support',
        'allowed_mentions' => ['parse' => []],
        'embeds' => [$embed],
    ];

    $webhookUrl = defined('CRIPSUM_DISCORD_SUPPORT_WEBHOOK')
        ? trim((string)CRIPSUM_DISCORD_SUPPORT_WEBHOOK)
        : trim((string)(getenv('DISCORD_SUPPORT_WEBHOOK') ?: ''));

    // A Discord webhook is tied to exactly one channel, so this cannot accidentally
    // land in #site-logs. The webhook must belong to #website-support.
    if ($webhookUrl !== '') {
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($discordPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($status >= 200 && $status < 300) return true;
        error_log('[Discord Website Support] Webhook failed (' . $status . '): ' . ($curlError ?: $limit($response, 300, 'empty response')));
        return false;
    }

    // Optional dedicated bot relay. It must be a Website Support route; /v1/logs
    // is deliberately not used because it always posts in #site-logs.
    $supportEndpoint = defined('CRIPSUM_BOT_SUPPORT_ENDPOINT')
        ? trim((string)CRIPSUM_BOT_SUPPORT_ENDPOINT)
        : trim((string)(getenv('CRIPSUM_BOT_SUPPORT_ENDPOINT') ?: ''));
    if ($supportEndpoint !== '') {
        $relayPayload = [
            'channel_id' => defined('CRIPSUM_DISCORD_SUPPORT_CHANNEL_ID') ? CRIPSUM_DISCORD_SUPPORT_CHANNEL_ID : '1521100942668206110',
            'channel' => 'website-support',
            'report_type' => $reportType,
            'reporter_id' => $reporterUserId ?: null,
            'reporter_username' => $reporterUsername,
            'target_id' => $targetId ?: null,
            'target_name' => $targetName,
            'target_author' => $targetAuthor ?: null,
            'target_author_id' => $targetAuthorId ?: null,
            'reason' => $reason,
            'detail' => $detail,
            'content_snippet' => $contentSnippet,
            'target_url' => $targetUrl ?: null,
            'media_url' => $mediaUrl ?: null,
            'ip' => $ip,
            'created_at' => $createdAt,
            'discord_payload' => $discordPayload,
        ];

        $ch = curl_init($supportEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Bot-Api-Key: ' . (defined('CRIPSUM_BOT_API_KEY') ? CRIPSUM_BOT_API_KEY : ''),
            ],
            CURLOPT_POSTFIELDS => json_encode($relayPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($status >= 200 && $status < 300) return true;
        error_log('[Discord Website Support] Relay failed (' . $status . '): ' . ($curlError ?: $limit($response, 300, 'empty response')));
        return false;
    }

    error_log('[Discord Website Support] Missing DISCORD_SUPPORT_WEBHOOK or CRIPSUM_BOT_SUPPORT_ENDPOINT configuration.');
    return false;
}

