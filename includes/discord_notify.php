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
    $endpoint = defined('CRIPSUM_BOT_ENDPOINT') ? CRIPSUM_BOT_ENDPOINT . '/v1/logs' : 'https://api.cripsum.com/v1/logs';
    $webhookUrl = defined('CRIPSUM_DISCORD_SUPPORT_WEBHOOK') ? CRIPSUM_DISCORD_SUPPORT_WEBHOOK : getenv('DISCORD_SUPPORT_WEBHOOK');

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $reporterUserId = $_SESSION['user_id'] ?? null;
    $reporterUsername = $_SESSION['username'] ?? 'Utente';

    $typeLabel = ucfirst($reportType);
    if ($reportType === 'profile') $typeLabel = 'Profilo Utente';
    elseif ($reportType === 'rimasto') $typeLabel = 'Top Rimasti';
    elseif ($reportType === 'shitpost') $typeLabel = 'Shitpost';
    elseif ($reportType === 'chat') $typeLabel = 'Messaggio Chat';

    $payload = [
        'channel_id' => '1521100942668206110',
        'channel' => 'website-support',
        'type' => 'support_report',
        'report_type' => $reportType,
        'title' => "🚨 Nuova Segnalazione ({$typeLabel})",
        'description' => "Segnalazione inviata da @" . $reporterUsername . " (ID: " . ($reporterUserId ?? 'N/D') . ")",
        'reporter_id' => $reporterUserId,
        'reporter_username' => $reporterUsername,
        'target_id' => $data['target_id'] ?? null,
        'target_name' => $data['target_name'] ?? null,
        'target_url' => $data['target_url'] ?? null,
        'reason' => $data['reason'] ?? '',
        'detail' => $data['detail'] ?? '',
        'fields' => [
            'Tipo' => $typeLabel,
            'Segnalato da' => "@{$reporterUsername} (ID: {$reporterUserId})",
            'Motivo' => $data['reason'] ?: 'Non specificato',
            'Dettagli' => $data['detail'] ?: 'Nessun dettaglio',
            'Oggetto' => $data['target_name'] ?: 'N/D',
            'URL' => $data['target_url'] ?: 'N/D'
        ],
        'ip' => $ip,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $sent = false;

    // Call bot endpoint /v1/logs for channel 1521100942668206110 (#website-support)
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Bot-Api-Key: ' . (defined('CRIPSUM_BOT_API_KEY') ? CRIPSUM_BOT_API_KEY : '')
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        $sent = true;
    }

    // Direct Discord Webhook if configured
    if (!empty($webhookUrl)) {
        $embedFields = [
            ['name' => 'Tipo Segnalazione', 'value' => $typeLabel, 'inline' => true],
            ['name' => 'Segnalato da', 'value' => "@{$reporterUsername} (ID: {$reporterUserId})", 'inline' => true],
            ['name' => 'Motivo', 'value' => $data['reason'] ?: 'Non specificato', 'inline' => false],
        ];

        if (!empty($data['detail'])) {
            $embedFields[] = ['name' => 'Dettagli', 'value' => $data['detail'], 'inline' => false];
        }
        if (!empty($data['target_name'])) {
            $embedFields[] = ['name' => 'Contenuto / Oggetto Segnalato', 'value' => $data['target_name'], 'inline' => true];
        }
        if (!empty($data['target_url'])) {
            $embedFields[] = ['name' => 'Link', 'value' => "[Apri Contenuto]({$data['target_url']})", 'inline' => true];
        }

        $webhookPayload = [
            'content' => "🚨 **Nuova Segnalazione nel canale website-support**",
            'embeds' => [
                [
                    'title' => "Segnalazione: {$typeLabel}",
                    'color' => 15158332,
                    'fields' => $embedFields,
                    'footer' => ['text' => 'Cripsum Website Support • ' . date('d/m/Y H:i')]
                ]
            ]
        ];

        $chW = curl_init($webhookUrl);
        curl_setopt_array($chW, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($webhookPayload),
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        curl_exec($chW);
        $wStatus = curl_getinfo($chW, CURLINFO_HTTP_CODE);
        curl_close($chW);

        if ($wStatus >= 200 && $wStatus < 300) {
            $sent = true;
        }
    }

    return $sent;
}




