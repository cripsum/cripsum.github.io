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
