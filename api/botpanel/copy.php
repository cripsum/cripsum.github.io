<?php
declare(strict_types=1);

/**
 * Copia le impostazioni da un altro server.
 *
 * Servono i permessi su tutti e due, richiesti al bot al momento. Si copiano
 * le regole (soglie, testi, categorie, domande); canali e ruoli no, perche'
 * quelli dell'altro server qui non esistono: vanno scelti di nuovo, e un
 * modulo che senza il suo canale non puo' funzionare arriva spento.
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_require_user($mysqli);
botpanel_require_write();
botpanel_rate('copy', 5, 60);

$input = botpanel_input();
$targetId = botpanel_request_guild();
$sourceId = is_string($input['from'] ?? null) ? $input['from'] : '';

if (!botpanel_is_snowflake($sourceId) || $sourceId === $targetId) {
    botpanel_fail('bad_guild', 'Server di partenza non valido.', 400);
}

$sourceAccess = botpanel_access($user, $sourceId, true);
$targetAccess = botpanel_access($user, $targetId, true);
cripsum_release_session();
botpanel_require_home_admin($user, $targetAccess);

$definitions = botpanel_modules();
$wanted = array_values(array_filter(
    is_array($input['modules'] ?? null) ? $input['modules'] : [],
    static fn($id): bool => is_string($id) && !empty($definitions[$id]['ready'])
));

if ($wanted === []) {
    botpanel_fail('nothing', 'Scegli almeno un modulo da copiare.', 400);
}

$source = botpanel_settings($mysqli, $sourceId);
$target = botpanel_settings($mysqli, $targetId);
$meta = botpanel_meta($targetId);

$copied = [];
$skipped = [];

foreach ($wanted as $moduleId) {
    if (!isset($source[$moduleId])) {
        $skipped[] = $moduleId;
        continue;
    }

    $definition = $definitions[$moduleId];
    $enabled = !empty($definition['toggle']) ? $source[$moduleId]['enabled'] : true;

    // Primo passaggio: via canali e ruoli che qui non esistono.
    $clean = botpanel_validate_module(
        $moduleId,
        $source[$moduleId]['config'],
        botpanel_validation_context($meta, $targetAccess, false, true)
    );

    if (!$clean['ok']) {
        $skipped[] = $moduleId;
        continue;
    }

    // Secondo passaggio: cosi' ripulito puo' stare acceso? Se manca un
    // canale obbligatorio no, e arriva spento.
    if ($enabled) {
        $strict = botpanel_validate_module(
            $moduleId,
            $clean['config'],
            botpanel_validation_context($meta, $targetAccess, true)
        );
        $enabled = $strict['ok'];
    }

    $result = botpanel_save_settings(
        $mysqli,
        $targetId,
        $moduleId,
        $enabled,
        $clean['config'],
        $target[$moduleId]['version'] ?? 0,
        $user,
        'copy'
    );

    if ($result['ok']) {
        $copied[] = ['module' => $moduleId, 'enabled' => $enabled];
    } else {
        $skipped[] = $moduleId;
    }
}

botpanel_json([
    'ok' => true,
    'copied' => $copied,
    'skipped' => $skipped,
    'applied' => $copied !== [] && botpanel_notify_reload($targetId),
]);
