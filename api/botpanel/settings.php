<?php
declare(strict_types=1);

/**
 * Salva le impostazioni di un modulo.
 *
 * E' il punto piu' delicato del pannello, quindi l'ordine conta:
 * chi sei, che la richiesta parta dal sito, che tu possa gestire quel server
 * *adesso* (richiesto al bot, non preso dalla sessione), e solo alla fine il
 * contenuto, controllato campo per campo contro i canali e i ruoli veri del
 * server.
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_require_user($mysqli);
botpanel_require_write();
botpanel_rate('save', 30, 60);

$input = botpanel_input();
$guildId = botpanel_request_guild();
$moduleId = is_string($input['module'] ?? null) ? $input['module'] : '';
$definition = botpanel_modules()[$moduleId] ?? null;

if ($definition === null || empty($definition['ready'])) {
    botpanel_fail('unknown_module', 'Modulo sconosciuto.', 400);
}

$access = botpanel_access($user, $guildId, true);
cripsum_release_session();

botpanel_require_home_admin($user, $access);

$enabled = !empty($definition['toggle']) ? !empty($input['enabled']) : true;
$meta = botpanel_meta($guildId);

$check = botpanel_validate_module(
    $moduleId,
    $input['config'] ?? [],
    botpanel_validation_context($meta, $access, $enabled)
);

if (!$check['ok']) {
    botpanel_fail('invalid', 'Alcuni valori non vanno bene.', 422, ['errors' => $check['errors']]);
}

$result = botpanel_save_settings(
    $mysqli,
    $guildId,
    $moduleId,
    $enabled,
    $check['config'],
    (int)($input['version'] ?? 0),
    $user
);

if (!$result['ok']) {
    botpanel_fail(
        'conflict',
        'Qualcun altro ha salvato nel frattempo: ricarica per vedere le sue modifiche.',
        409,
        ['version' => $result['version']]
    );
}

botpanel_json([
    'ok' => true,
    'module' => $moduleId,
    'enabled' => $enabled,
    'version' => $result['version'],
    'config' => botpanel_with_defaults($definition['fields'], $check['config']),
    // false = il bot non ha confermato: applichera' le modifiche da solo
    // entro mezz'ora, e la pagina lo dice.
    'applied' => botpanel_notify_reload($guildId),
]);
