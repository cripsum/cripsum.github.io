<?php
$settingsLanguage = ($settingsLanguage ?? 'it') === 'en' ? 'en' : 'it';
$copy = $settingsLanguage === 'en'
    ? [
        'title' => 'Connected devices',
        'description' => 'Review the active sessions on your account and disconnect any device you do not recognize.',
        'unavailable_title' => 'Device management is not available yet',
        'unavailable_text' => 'The session registry must be initialized on the server before this section can be used.',
        'empty' => 'No active devices were found.',
        'current' => 'This device',
        'browser' => 'Browser',
        'system' => 'System',
        'ip' => 'IP address',
        'location' => 'Approximate location',
        'location_note' => 'Estimated from the IP address; it is not GPS and may be inaccurate.',
        'location_unavailable' => 'Location unavailable',
        'local_network' => 'Local network',
        'network' => 'Network',
        'first_access' => 'Signed in',
        'last_activity' => 'Last activity',
        'active_now' => 'Active now',
        'minutes_ago' => '%d min ago',
        'hours_ago' => '%d hr ago',
        'days_ago' => '%d days ago',
        'disconnect' => 'Disconnect',
        'disconnect_confirm' => 'Disconnect this device from your account?',
        'disconnect_others' => 'Disconnect all other devices',
        'disconnect_others_confirm' => 'Disconnect all other devices from your account?',
        'security_note' => 'A disconnected device will be signed out on its next request. If you do not recognize a session, also change your password.',
    ]
    : [
        'title' => 'Dispositivi collegati',
        'description' => 'Controlla le sessioni attive sul tuo account e scollega i dispositivi che non riconosci.',
        'unavailable_title' => 'Gestione dispositivi non ancora disponibile',
        'unavailable_text' => 'Il registro delle sessioni deve essere inizializzato sul server prima di poter usare questa sezione.',
        'empty' => 'Non sono stati trovati dispositivi attivi.',
        'current' => 'Questo dispositivo',
        'browser' => 'Browser',
        'system' => 'Sistema',
        'ip' => 'Indirizzo IP',
        'location' => 'Posizione approssimativa',
        'location_note' => 'Stimata dall’indirizzo IP: non è GPS e potrebbe essere imprecisa.',
        'location_unavailable' => 'Posizione non disponibile',
        'local_network' => 'Rete locale',
        'network' => 'Rete',
        'first_access' => 'Accesso effettuato',
        'last_activity' => 'Ultima attività',
        'active_now' => 'Attivo ora',
        'minutes_ago' => '%d min fa',
        'hours_ago' => '%d h fa',
        'days_ago' => '%d giorni fa',
        'disconnect' => 'Scollega',
        'disconnect_confirm' => 'Vuoi scollegare questo dispositivo dal tuo account?',
        'disconnect_others' => 'Scollega tutti gli altri dispositivi',
        'disconnect_others_confirm' => 'Vuoi scollegare tutti gli altri dispositivi dal tuo account?',
        'security_note' => 'Un dispositivo scollegato verrà disconnesso alla richiesta successiva. Se non riconosci una sessione, cambia anche la password.',
    ];

$deviceSessionsAvailable = (bool)($deviceSessionsAvailable ?? false);
$deviceSessions = is_array($deviceSessions ?? null) ? $deviceSessions : [];
$otherDeviceCount = count(array_filter($deviceSessions, static fn(array $device): bool => empty($device['is_current'])));
$dateFormat = $settingsLanguage === 'en' ? 'M j, Y H:i' : 'd/m/Y H:i';

$formatActivity = static function (?string $value) use ($copy, $dateFormat): array {
    $timestamp = $value ? strtotime($value) : false;
    if ($timestamp === false) {
        return ['relative' => '—', 'exact' => ''];
    }

    $elapsed = max(0, time() - $timestamp);
    if ($elapsed < 120) {
        $relative = $copy['active_now'];
    } elseif ($elapsed < 3600) {
        $relative = sprintf($copy['minutes_ago'], max(2, (int)floor($elapsed / 60)));
    } elseif ($elapsed < 86400) {
        $relative = sprintf($copy['hours_ago'], (int)floor($elapsed / 3600));
    } elseif ($elapsed < 604800) {
        $relative = sprintf($copy['days_ago'], (int)floor($elapsed / 86400));
    } else {
        $relative = date($dateFormat, $timestamp);
    }

    return ['relative' => $relative, 'exact' => date($dateFormat, $timestamp)];
};

$formatLocation = static function (?array $location) use ($copy): array {
    if (!$location) {
        return ['label' => $copy['location_unavailable'], 'flag' => '', 'network' => '—'];
    }
    if (!empty($location['is_local'])) {
        return ['label' => $copy['local_network'], 'flag' => '', 'network' => '—'];
    }

    $parts = [];
    foreach ([$location['city'] ?? '', $location['region'] ?? '', $location['country'] ?? ''] as $part) {
        $part = trim((string)$part);
        if ($part !== '' && !in_array(mb_strtolower($part), array_map('mb_strtolower', $parts), true)) {
            $parts[] = $part;
        }
    }

    $countryCode = strtoupper((string)($location['country_code'] ?? ''));
    $flag = '';
    if (preg_match('/^[A-Z]{2}$/', $countryCode) && function_exists('mb_chr')) {
        $flag = mb_chr(127397 + ord($countryCode[0]), 'UTF-8') . mb_chr(127397 + ord($countryCode[1]), 'UTF-8');
    }

    return [
        'label' => $parts ? implode(', ', $parts) : $copy['location_unavailable'],
        'flag' => $flag,
        'network' => trim((string)($location['network'] ?? '')) ?: '—',
    ];
};
?>
<article class="settings-panel auth-reveal">
    <div class="settings-panel__head settings-panel__head--devices">
        <div>
            <h2><?php echo auth_h($copy['title']); ?></h2>
            <p><?php echo auth_h($copy['description']); ?></p>
        </div>

        <?php if ($deviceSessionsAvailable && $otherDeviceCount > 0): ?>
            <form method="POST" action="#devices" data-auth-form>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="revoke_other_devices">
                <button
                    class="auth-btn auth-btn--danger device-disconnect-all"
                    type="submit"
                    data-submit-text="<?php echo auth_h($copy['disconnect_others']); ?>"
                    onclick="return confirm('<?php echo auth_h($copy['disconnect_others_confirm']); ?>');"
                >
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span><?php echo auth_h($copy['disconnect_others']); ?></span>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!$deviceSessionsAvailable): ?>
        <div class="device-empty-state device-empty-state--warning">
            <i class="fa-solid fa-database"></i>
            <div>
                <strong><?php echo auth_h($copy['unavailable_title']); ?></strong>
                <span><?php echo auth_h($copy['unavailable_text']); ?></span>
            </div>
        </div>
    <?php elseif (!$deviceSessions): ?>
        <div class="device-empty-state">
            <i class="fa-solid fa-display"></i>
            <span><?php echo auth_h($copy['empty']); ?></span>
        </div>
    <?php else: ?>
        <div class="device-list">
            <?php foreach ($deviceSessions as $device): ?>
                <?php
                $deviceType = (string)($device['device_type'] ?? 'desktop');
                $deviceIcon = $deviceType === 'mobile'
                    ? 'fa-mobile-screen-button'
                    : ($deviceType === 'tablet' ? 'fa-tablet-screen-button' : 'fa-display');
                $isCurrent = !empty($device['is_current']);
                $activity = $formatActivity($device['last_seen_at'] ?? null);
                $location = $formatLocation(is_array($device['location'] ?? null) ? $device['location'] : null);
                ?>
                <section class="device-card<?php echo $isCurrent ? ' is-current' : ''; ?>">
                    <div class="device-card__identity">
                        <span class="device-card__icon" aria-hidden="true">
                            <i class="fa-solid <?php echo auth_h($deviceIcon); ?>"></i>
                        </span>
                        <div>
                            <div class="device-card__title">
                                <h3><?php echo auth_h($device['device_name'] ?? 'Dispositivo'); ?></h3>
                                <?php if ($isCurrent): ?>
                                    <span class="device-current-badge">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <?php echo auth_h($copy['current']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="device-card__activity">
                                <?php echo auth_h($copy['last_activity']); ?>:
                                <time datetime="<?php echo auth_h($device['last_seen_at'] ?? ''); ?>" title="<?php echo auth_h($activity['exact']); ?>">
                                    <?php echo auth_h($activity['relative']); ?>
                                </time>
                            </span>
                        </div>
                    </div>

                    <dl class="device-card__details">
                        <div>
                            <dt><i class="fa-solid fa-globe"></i><?php echo auth_h($copy['browser']); ?></dt>
                            <dd><?php echo auth_h($device['browser'] ?? '—'); ?></dd>
                        </div>
                        <div>
                            <dt><i class="fa-solid fa-microchip"></i><?php echo auth_h($copy['system']); ?></dt>
                            <dd><?php echo auth_h($device['os'] ?? '—'); ?></dd>
                        </div>
                        <div>
                            <dt><i class="fa-solid fa-network-wired"></i><?php echo auth_h($copy['ip']); ?></dt>
                            <dd><code><?php echo auth_h($device['ip_address'] ?? '—'); ?></code></dd>
                        </div>
                        <div>
                            <dt><i class="fa-solid fa-location-dot"></i><?php echo auth_h($copy['location']); ?></dt>
                            <dd title="<?php echo auth_h($copy['location_note']); ?>">
                                <?php if ($location['flag'] !== ''): ?><span aria-hidden="true"><?php echo auth_h($location['flag']); ?></span><?php endif; ?>
                                <?php echo auth_h($location['label']); ?>
                            </dd>
                        </div>
                        <div>
                            <dt><i class="fa-solid fa-tower-broadcast"></i><?php echo auth_h($copy['network']); ?></dt>
                            <dd><?php echo auth_h($location['network']); ?></dd>
                        </div>
                        <div>
                            <dt><i class="fa-regular fa-calendar"></i><?php echo auth_h($copy['first_access']); ?></dt>
                            <dd>
                                <time datetime="<?php echo auth_h($device['created_at'] ?? ''); ?>">
                                    <?php echo auth_h(date($dateFormat, strtotime((string)$device['created_at']))); ?>
                                </time>
                            </dd>
                        </div>
                    </dl>

                    <?php if (!$isCurrent): ?>
                        <form method="POST" action="#devices" class="device-card__action" data-auth-form>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="revoke_device">
                            <input type="hidden" name="device_session_id" value="<?php echo (int)$device['id']; ?>">
                            <button
                                class="auth-btn auth-btn--danger"
                                type="submit"
                                data-submit-text="<?php echo auth_h($copy['disconnect']); ?>"
                                onclick="return confirm('<?php echo auth_h($copy['disconnect_confirm']); ?>');"
                            >
                                <i class="fa-solid fa-link-slash"></i>
                                <span><?php echo auth_h($copy['disconnect']); ?></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <p class="device-security-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span><?php echo auth_h($copy['security_note']); ?> <?php echo auth_h($copy['location_note']); ?></span>
        </p>
    <?php endif; ?>
</article>
