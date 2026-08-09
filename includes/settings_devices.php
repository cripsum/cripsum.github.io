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
        'first_access' => 'Signed in',
        'last_activity' => 'Last activity',
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
        'first_access' => 'Accesso effettuato',
        'last_activity' => 'Ultima attività',
        'disconnect' => 'Scollega',
        'disconnect_confirm' => 'Vuoi scollegare questo dispositivo dal tuo account?',
        'disconnect_others' => 'Scollega tutti gli altri dispositivi',
        'disconnect_others_confirm' => 'Vuoi scollegare tutti gli altri dispositivi dal tuo account?',
        'security_note' => 'Un dispositivo scollegato verrà disconnesso alla richiesta successiva. Se non riconosci una sessione, cambia anche la password.',
    ];

$deviceSessionsAvailable = (bool)($deviceSessionsAvailable ?? false);
$deviceSessions = is_array($deviceSessions ?? null) ? $deviceSessions : [];
$otherDeviceCount = count(array_filter($deviceSessions, static fn(array $device): bool => empty($device['is_current'])));
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
                $dateFormat = $settingsLanguage === 'en' ? 'M j, Y H:i' : 'd/m/Y H:i';
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
                                <time datetime="<?php echo auth_h($device['last_seen_at'] ?? ''); ?>">
                                    <?php echo auth_h(date($dateFormat, strtotime((string)$device['last_seen_at']))); ?>
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
                            <dd><?php echo auth_h($device['ip_address'] ?? '—'); ?></dd>
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
            <span><?php echo auth_h($copy['security_note']); ?></span>
        </p>
    <?php endif; ?>
</article>
