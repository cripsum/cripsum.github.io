<?php
function getDiscordPresence($discord_id) {
    $ch = curl_init("https://api.lanyard.rest/v1/users/$discord_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$discord_id = '963536045180350474'; // ← Inserisci qui il tuo ID
$data = getDiscordPresence($discord_id);
?>

<?php if ($data && isset($data['data'])): 
    $user = $data['data']['discord_user'];
    $status = $data['data']['discord_status'];
    $activities = $data['data']['activities'];
?>
    <div class="discord-header">
        <strong><?php echo htmlspecialchars($user['username']); ?></strong> è <span class="status-<?php echo $status; ?>"><?php echo $status; ?></span>
    </div>

    <div class="activity-carousel">
        <?php foreach ($activities as $index => $activity): ?>
            <div class="activity-slide" style="<?php echo $index !== 0 ? 'display:none;' : ''; ?>">
                <?php
                    $icon = null;
                    if (isset($activity['assets']['large_image'])) {
                        $key = $activity['assets']['large_image'];
                        if (str_starts_with($key, 'mp:external/')) {
                            $icon = str_replace('mp:', 'https://media.discordapp.net/', $key);
                        } else {
                            $icon = "https://cdn.discordapp.com/app-assets/{$activity['application_id']}/$key.png";
                        }
                    }
                ?>
                <?php if ($icon): ?>
                    <img src="<?php echo $icon; ?>" alt="Icona" class="activity-icon">
                <?php endif; ?>
                <div class="activity-info">
                    <p class="activity-name"><?php echo htmlspecialchars($activity['name']); ?></p>
                    <?php if (!empty($activity['details'])): ?>
                        <p class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($activity['state'])): ?>
                        <p class="activity-state"><?php echo htmlspecialchars($activity['state']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>Errore nel recupero dello stato Discord.</p>
<?php endif; ?>