<?php
declare(strict_types=1);

/**
 * Tutto quello che serve per disegnare il pannello di un server: canali e
 * ruoli (dal bot), moduli con le loro impostazioni, e l'elenco di cio' che
 * non va e impedirebbe a un modulo di funzionare.
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();
$access = botpanel_access($user, $guildId);
$csrf = csrf_token();
cripsum_release_session();

$meta = botpanel_meta($guildId);
$saved = botpanel_settings($mysqli, $guildId);
$isHome = $access['is_home'];

$modules = [];
$problems = [];

foreach (botpanel_modules() as $id => $definition) {
    if (empty($definition['ready'])) {
        continue;
    }

    $definition['id'] = $id;
    $row = $saved[$id] ?? null;
    $enabled = botpanel_module_enabled($definition, $row, $isHome);

    $modules[$id] = [
        'id' => $id,
        'icon' => $definition['icon'],
        'toggle' => !empty($definition['toggle']),
        'name' => $definition['name'],
        'lead' => $definition['lead'],
        'fields' => botpanel_fields_for($definition['fields'], $isHome),
        'enabled' => $enabled,
        // false = mai salvato dal pannello. Nel server di Cripsum vuol dire
        // che il bot sta ancora usando il suo .env per questo modulo.
        'configured' => $row !== null,
        'version' => $row['version'] ?? 0,
        'config' => botpanel_with_defaults($definition['fields'], $row['config'] ?? []),
    ];

    // Le impostazioni salvate si ricontrollano contro il server di adesso:
    // un canale cancellato o un permesso tolto dopo il salvataggio diventano
    // una voce di "da sistemare", non una sorpresa.
    if ($row !== null && $enabled) {
        $check = botpanel_validate_module(
            $id,
            $row['config'],
            botpanel_validation_context($meta, ['owner' => true] + $access, true)
        );

        foreach ($check['errors'] as $path => $code) {
            $problems[] = ['module' => $id, 'path' => (string)$path, 'code' => $code];
        }
    }
}

// Permessi del bot sull'intero server di cui un modulo acceso non puo' fare a meno.
$botPermissions = (array)($meta['bot']['permissions'] ?? []);
$needs = [
    'moderation' => ['moderate', 'kick', 'ban'],
    'restricted' => ['manage_messages'],
    'tickets' => ['manage_threads'],
    'voice' => ['manage_channels', 'move_members'],
    'roles' => ['manage_roles'],
];

foreach ($needs as $id => $permissions) {
    if (empty($modules[$id]['enabled'])) {
        continue;
    }

    foreach ($permissions as $permission) {
        if (empty($botPermissions[$permission])) {
            $problems[] = ['module' => $id, 'path' => '', 'code' => 'bot_permission:' . $permission];
        }
    }
}

if (!empty($modules['messages']['enabled'])
    && !empty($modules['messages']['config']['autorole_ids'])
    && empty($botPermissions['manage_roles'])
) {
    $problems[] = ['module' => 'messages', 'path' => 'autorole_ids', 'code' => 'bot_permission:manage_roles'];
}

botpanel_json([
    'ok' => true,
    'csrf' => $csrf,
    'guild' => [
        'id' => (string)$meta['id'],
        'name' => (string)$meta['name'],
        'icon' => $meta['icon'] ?? null,
        'member_count' => (int)($meta['member_count'] ?? 0),
        'is_home' => $isHome,
    ],
    'access' => [
        'owner' => $access['owner'],
        'top_role_position' => $access['top_role_position'],
        // Nel server di Cripsum salva solo un amministratore del sito.
        'can_save' => !$isHome || in_array($user['ruolo'], ['admin', 'owner'], true),
    ],
    'channels' => $meta['channels'] ?? [],
    'roles' => $meta['roles'] ?? [],
    'emojis' => $meta['emojis'] ?? [],
    'bot' => $meta['bot'] ?? null,
    'modules' => $modules,
    'problems' => $problems,
]);
