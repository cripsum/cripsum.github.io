<?php
$discord_id = $discordProfileId ?? ($_GET['discordId'] ?? '');
$discord_id = trim((string) $discord_id);

function ds_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ds_starts_with(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function getDiscordPresence(string $discord_id): ?array
{
    if (!preg_match('/^\d{15,25}$/', $discord_id)) {
        return null;
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init('https://api.lanyard.rest/v1/users/' . rawurlencode($discord_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'CripsumBio/2.0',
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $statusCode < 200 || $statusCode >= 300) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function discordStatusLabel(string $status): string
{
    return match ($status) {
        'online' => 'Online',
        'idle' => 'Away',
        'dnd' => 'Do Not Disturb',
        'offline' => 'Offline',
        default => 'Unknown',
    };
}

function discordActivityVerb(array $activity): string
{
    return match ((int) ($activity['type'] ?? 0)) {
        0 => 'Playing',
        1 => 'Streaming',
        2 => 'Listening to',
        3 => 'Watching',
        5 => 'Competing in',
        default => 'Playing',
    };
}

function discordAvatarUrl(array $user): string
{
    $id = (string) ($user['id'] ?? '0');
    $avatar = $user['avatar'] ?? null;

    if ($avatar) {
        $extension = ds_starts_with((string) $avatar, 'a_') ? 'gif' : 'png';
        return "https://cdn.discordapp.com/avatars/{$id}/{$avatar}.{$extension}?size=128";
    }

    $fallbackIndex = ((int) substr($id, -1)) % 5;
    return "https://cdn.discordapp.com/embed/avatars/{$fallbackIndex}.png";
}

function discordActivityIcon(array $activity): ?string
{
    $assets = $activity['assets'] ?? [];
    $largeImage = $assets['large_image'] ?? null;

    if (!$largeImage) {
        return null;
    }

    $largeImage = (string) $largeImage;

    if (($activity['name'] ?? '') === 'Spotify' && ds_starts_with($largeImage, 'spotify:')) {
        return 'https://i.scdn.co/image/' . rawurlencode(str_replace('spotify:', '', $largeImage));
    }

    if (ds_starts_with($largeImage, 'mp:external/')) {
        return str_replace('mp:', 'https://media.discordapp.net/', $largeImage);
    }

    $applicationId = $activity['application_id'] ?? null;
    if (!$applicationId) {
        return null;
    }

    return 'https://cdn.discordapp.com/app-assets/' . rawurlencode((string) $applicationId) . '/' . rawurlencode($largeImage) . '.png';
}

function renderTimestampText(array $timestamps): string
{
    if (isset($timestamps['start'])) {
        $elapsed = time() - ((int) $timestamps['start'] / 1000);
        $elapsed = max(0, (int) $elapsed);
        $hours = floor($elapsed / 3600);
        $minutes = floor(($elapsed % 3600) / 60);
        $seconds = $elapsed % 60;

        return $hours > 0
            ? sprintf('%02d:%02d:%02d elapsed', $hours, $minutes, $seconds)
            : sprintf('%02d:%02d elapsed', $minutes, $seconds);
    }

    if (isset($timestamps['end'])) {
        $remaining = ((int) $timestamps['end'] / 1000) - time();
        $remaining = max(0, (int) $remaining);
        $hours = floor($remaining / 3600);
        $minutes = floor(($remaining % 3600) / 60);
        $seconds = $remaining % 60;

        return $hours > 0
            ? sprintf('%02d:%02d:%02d left', $hours, $minutes, $seconds)
            : sprintf('%02d:%02d left', $minutes, $seconds);
    }

    return '';
}

$data = getDiscordPresence($discord_id);
$payload = $data['data'] ?? null;

if (!$payload || empty($payload['discord_user'])): ?>
    <div class="ds-card ds-error">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Stato Discord non disponibile.</span>
    </div>
<?php
    return;
endif;

$user = $payload['discord_user'];
$status = (string) ($payload['discord_status'] ?? 'offline');
$activities = is_array($payload['activities'] ?? null) ? $payload['activities'] : [];

$customStatus = null;
$visibleActivities = [];

foreach ($activities as $activity) {
    if (!is_array($activity)) {
        continue;
    }

    if ((int) ($activity['type'] ?? -1) === 4) {
        $customStatus = $activity;
        continue;
    }

    $visibleActivities[] = $activity;
}

$statusLine = discordStatusLabel($status);

if ($customStatus && !empty($customStatus['state'])) {
    $statusLine = (string) $customStatus['state'];
} elseif (!empty($visibleActivities)) {
    $firstActivity = $visibleActivities[0];
    if (!empty($firstActivity['name'])) {
        $statusLine = discordActivityVerb($firstActivity) . ' ' . $firstActivity['name'];
    }
}

$avatarUrl = discordAvatarUrl($user);
$discordUsername = $user['username'] ?? 'discord';
$discordUserId = $user['id'] ?? $discord_id;
?>
<div class="ds-card">
    <div class="ds-profile">
        <div class="ds-avatar-wrap">
            <img class="ds-avatar" src="<?= ds_e($avatarUrl); ?>" alt="Avatar Discord" loading="lazy">
            <span class="ds-status-dot ds-status-<?= ds_e($status); ?>" title="<?= ds_e(discordStatusLabel($status)); ?>"></span>
        </div>

        <div class="ds-main">
            <div class="ds-name">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.73 19.73 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.1 13.1 0 0 1-1.872-.892.077.077 0 0 1-.008-.128c.126-.094.25-.191.372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.195.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.84 19.84 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                </svg>
                <span>@<?= ds_e($discordUsername); ?></span>
            </div>
            <div class="ds-status-text"><?= ds_e($statusLine); ?></div>
        </div>

        <a class="ds-open" href="https://discord.com/users/<?= ds_e($discordUserId); ?>" target="_blank" rel="noopener noreferrer" aria-label="Apri profilo Discord">
            <i class="fas fa-arrow-up-right-from-square"></i>
        </a>
    </div>

    <?php if (!empty($visibleActivities)): ?>
        <div class="ds-activities">
            <?php foreach ($visibleActivities as $index => $activity): ?>
                <?php $icon = discordActivityIcon($activity); ?>
                <div class="ds-activity js-activity-item <?= $index === 0 ? 'is-active' : ''; ?>">
                    <?php if ($icon): ?>
                        <img class="ds-activity-icon" src="<?= ds_e($icon); ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="ds-activity-icon-fallback"><i class="fas fa-gamepad"></i></span>
                    <?php endif; ?>

                    <div class="ds-activity-content">
                        <div class="ds-activity-name"><?= ds_e($activity['name'] ?? 'Attività'); ?></div>
                        <?php if (!empty($activity['details'])): ?>
                            <div class="ds-activity-details"><?= ds_e($activity['details']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($activity['state'])): ?>
                            <div class="ds-activity-state"><?= ds_e($activity['state']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($activity['timestamps']) && is_array($activity['timestamps'])): ?>
                            <div
                                class="ds-activity-time js-activity-timestamp"
                                data-start="<?= ds_e($activity['timestamps']['start'] ?? ''); ?>"
                                data-end="<?= ds_e($activity['timestamps']['end'] ?? ''); ?>"
                            ><?= ds_e(renderTimestampText($activity['timestamps'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
