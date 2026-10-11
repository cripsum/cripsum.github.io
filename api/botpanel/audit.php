<?php
declare(strict_types=1);

/**
 * Registro di un server: chi ha cambiato cosa, e quando.
 *
 * GET  → le ultime modifiche, a pagine.
 * POST → ripristina: riporta un modulo a come era prima di una modifica.
 *
 * Ogni riga del registro conserva il prima e il dopo, ed e' questo che
 * permette di tornare indietro. Un ripristino e' un salvataggio come gli
 * altri: stessi controlli, e finisce a sua volta nel registro.
 */

require_once __DIR__ . '/bootstrap.php';

const AUDIT_PAGE_SIZE = 25;

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $access = botpanel_access($user, $guildId);
    cripsum_release_session();

    $modules = botpanel_modules();
    $moduleFilter = (string)($_GET['module'] ?? '');
    $page = max(0, min(200, (int)($_GET['page'] ?? 0)));
    $offset = $page * AUDIT_PAGE_SIZE;
    $limit = AUDIT_PAGE_SIZE + 1;

    $sql =
        'SELECT a.id, a.actor_discord_id, a.source, a.module, a.action, a.before_json, a.after_json,
                a.created_at, u.username
         FROM discord_guild_audit a
         LEFT JOIN utenti u ON u.id = a.actor_user_id
         WHERE a.guild_id = ?';

    if ($moduleFilter !== '' && isset($modules[$moduleFilter])) {
        $stmt = $mysqli->prepare($sql . ' AND a.module = ? ORDER BY a.id DESC LIMIT ? OFFSET ?');
        if ($stmt) {
            $stmt->bind_param('ssii', $guildId, $moduleFilter, $limit, $offset);
        }
    } else {
        $stmt = $mysqli->prepare($sql . ' ORDER BY a.id DESC LIMIT ? OFFSET ?');
        if ($stmt) {
            $stmt->bind_param('sii', $guildId, $limit, $offset);
        }
    }

    if (!$stmt) {
        botpanel_fail('database', 'Impossibile leggere il registro.', 500);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $more = count($rows) > AUDIT_PAGE_SIZE;
    $rows = array_slice($rows, 0, AUDIT_PAGE_SIZE);

    botpanel_json([
        'ok' => true,
        'page' => $page,
        'more' => $more,
        'entries' => array_map(static function (array $row): array {
            $before = $row['before_json'] !== null ? json_decode((string)$row['before_json'], true) : null;
            $after = $row['after_json'] !== null ? json_decode((string)$row['after_json'], true) : null;

            return [
                'id' => (int)$row['id'],
                'when' => strtotime((string)$row['created_at']),
                'who' => $row['username'] ?: null,
                'discord_id' => $row['actor_discord_id'],
                'source' => (string)$row['source'],
                'module' => (string)$row['module'],
                'action' => (string)$row['action'],
                'before' => is_array($before) ? $before : null,
                'after' => is_array($after) ? $after : null,
                // Si puo' tornare indietro solo se c'era qualcosa prima.
                'restorable' => is_array($before) && is_array($before['config'] ?? null),
            ];
        }, $rows),
    ]);
}

botpanel_require_write();
botpanel_rate('save', 30, 60);

$access = botpanel_access($user, $guildId, true);
cripsum_release_session();
botpanel_require_home_admin($user, $access);

$auditId = (int)(botpanel_input()['audit_id'] ?? 0);

// La riga deve essere di questo server: il numero da solo non basta, o si
// potrebbe ripristinare qui la configurazione di un altro.
$stmt = $mysqli->prepare(
    'SELECT module, before_json FROM discord_guild_audit WHERE id = ? AND guild_id = ? LIMIT 1'
);

if (!$stmt) {
    botpanel_fail('database', 'Impossibile leggere il registro.', 500);
}

$stmt->bind_param('is', $auditId, $guildId);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

$before = $entry && $entry['before_json'] !== null ? json_decode((string)$entry['before_json'], true) : null;
$moduleId = (string)($entry['module'] ?? '');
$definition = botpanel_modules()[$moduleId] ?? null;

if (!$entry || !is_array($before) || !is_array($before['config'] ?? null) || $definition === null || empty($definition['ready'])) {
    botpanel_fail('not_restorable', 'Questa modifica non si può annullare.', 404);
}

$enabled = !empty($definition['toggle']) ? !empty($before['enabled']) : true;
$meta = botpanel_meta($guildId);

// Nel frattempo un canale o un ruolo possono essere spariti: si tolgono,
// invece di rifiutare tutto il ripristino.
$check = botpanel_validate_module(
    $moduleId,
    $before['config'],
    botpanel_validation_context($meta, $access, $enabled, true)
);

if (!$check['ok']) {
    // Senza un valore obbligatorio (di solito un canale cancellato) il modulo
    // torna com'era ma spento: lo riaccende chi sceglie un canale nuovo.
    $enabled = false;
    $check = botpanel_validate_module(
        $moduleId,
        $before['config'],
        botpanel_validation_context($meta, $access, false, true)
    );

    if (!$check['ok']) {
        botpanel_fail('invalid', 'Le impostazioni di allora non sono più valide.', 422, ['errors' => $check['errors']]);
    }
}

$current = botpanel_settings($mysqli, $guildId)[$moduleId] ?? null;

$result = botpanel_save_settings(
    $mysqli,
    $guildId,
    $moduleId,
    $enabled,
    $check['config'],
    $current['version'] ?? 0,
    $user,
    'restore'
);

if (!$result['ok']) {
    botpanel_fail('conflict', 'Qualcun altro ha salvato nel frattempo: riprova.', 409);
}

botpanel_json([
    'ok' => true,
    'module' => $moduleId,
    'enabled' => $enabled,
    'version' => $result['version'],
    'applied' => botpanel_notify_reload($guildId),
]);
