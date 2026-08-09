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
    $reporterUserId = $_SESSION['user_id'] ?? ($data['reporter_id'] ?? null);
    $reporterUsername = $_SESSION['username'] ?? ($data['reporter_username'] ?? 'Utente Sconosciuto');

    $typeLabel = ucfirst($reportType);
    if ($reportType === 'profile') $typeLabel = 'Profilo Utente';
    elseif ($reportType === 'rimasto') $typeLabel = 'Top Rimasti';
    elseif ($reportType === 'shitpost') $typeLabel = 'Shitpost';
    elseif ($reportType === 'chat') $typeLabel = 'Messaggio Chat';

    $targetName = $data['target_name'] ?? 'N/D';
    $targetAuthor = $data['target_author'] ?? null;
    $reason = $data['reason'] ?? 'Non specificato';
    $detail = $data['detail'] ?? null;
    $contentSnippet = $data['content_snippet'] ?? null;
    $targetUrl = $data['target_url'] ?? 'N/D';

    $fields = [
        'Tipo Segnalazione' => $typeLabel,
        'Segnalato da' => "@{$reporterUsername} (ID: " . ($reporterUserId ?? 'N/D') . ")",
        'Oggetto / Utente Segnalato' => $targetName . ($targetAuthor ? " (Autore: @{$targetAuthor})" : ""),
        'Motivo' => $reason
    ];

    if (!empty($contentSnippet)) {
        $fields['Contenuto Segnalato'] = mb_strlen($contentSnippet) > 300 ? mb_substr($contentSnippet, 0, 297) . '...' : $contentSnippet;
    }
    if (!empty($detail)) {
        $fields['Dettagli Aggiuntivi'] = $detail;
    }
    if (!empty($targetUrl) && $targetUrl !== 'N/D') {
        $fields['Link'] = $targetUrl;
    }

    $payload = [
        'channel_id' => '1521100942668206110',
        'channel' => 'website-support',
        'type' => 'support_report',
        'report_type' => $reportType,
        'title' => "🚨 Nuova Segnalazione ({$typeLabel})",
        'description' => "Segnalazione inviata nel canale #website-support",
        'reporter_id' => $reporterUserId,
        'reporter_username' => $reporterUsername,
        'target_id' => $data['target_id'] ?? null,
        'target_name' => $targetName,
        'target_author' => $targetAuthor,
        'target_url' => $targetUrl,
        'reason' => $reason,
        'detail' => $detail,
        'content_snippet' => $contentSnippet,
        'fields' => $fields,
        'ip' => $ip,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $sent = false;

    // Call bot endpoint for channel 1521100942668206110 (#website-support)
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
            ['name' => '📌 Tipo', 'value' => $typeLabel, 'inline' => true],
            ['name' => '👤 Segnalato da', 'value' => "@{$reporterUsername} (ID: " . ($reporterUserId ?? 'N/D') . ")", 'inline' => true],
            ['name' => '🎯 Oggetto / Utente', 'value' => $targetName . ($targetAuthor ? " (@{$targetAuthor})" : ""), 'inline' => true],
            ['name' => '📝 Motivo', 'value' => $reason, 'inline' => false],
        ];

        if (!empty($contentSnippet)) {
            $snippetText = mb_strlen($contentSnippet) > 450 ? mb_substr($contentSnippet, 0, 447) . '...' : $contentSnippet;
            $embedFields[] = ['name' => '💬 Contenuto', 'value' => "```\n" . str_replace('```', '` ` `', $snippetText) . "\n```", 'inline' => false];
        }

        if (!empty($detail)) {
            $embedFields[] = ['name' => '🔍 Dettagli Aggiuntivi', 'value' => $detail, 'inline' => false];
        }

        if (!empty($targetUrl) && $targetUrl !== 'N/D') {
            $embedFields[] = ['name' => '🔗 Link Diretto', 'value' => "[Apri su Cripsum]({$targetUrl})", 'inline' => false];
        }

        $webhookPayload = [
            'content' => "<@&1521100942668206110> 🚨 **Nuova Segnalazione Website Support**",
            'embeds' => [
                [
                    'title' => "🚨 Segnalazione: {$typeLabel}",
                    'color' => 15158332, // Red
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




