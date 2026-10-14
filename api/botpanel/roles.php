<?php
declare(strict_types=1);

/**
 * Pubblicazione dei menu dei ruoli a scelta.
 *
 * GET  ?guild=…                              dove sono pubblicati i menu
 * POST {guild, menu, channel_id}             pubblica un menu, o lo riscrive
 *                                            sul posto se e' gia' in quel canale
 *
 * I menu si scrivono nelle impostazioni del modulo; qui si decide solo dove
 * esce ciascuno.
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ['access' => $access, 'input' => $input] = botpanel_begin_action($user, $guildId, 'send', 15);

    $saved = botpanel_settings($mysqli, $guildId)['roles'] ?? null;
    if ($saved === null || !$saved['enabled']) {
        botpanel_fail('not_saved', 'Salva e accendi il modulo prima di pubblicare.', 409);
    }

    $menuId = (string)($input['menu'] ?? '');
    $menus = array_column((array)($saved['config']['menus'] ?? []), null, 'id');
    if (!preg_match('/^[a-z0-9]{6,12}$/', $menuId) || !isset($menus[$menuId])) {
        botpanel_fail('not_saved', 'Salva il menu prima di pubblicarlo.', 409);
    }

    $ctx = botpanel_validation_context(botpanel_meta($guildId), $access, true);
    $channelId = (string)($input['channel_id'] ?? '');
    $channel = $ctx['channels'][$channelId] ?? null;

    if (!$channel || !in_array($channel['kind'] ?? '', ['text', 'announcement'], true) || empty($channel['bot']['send']) || empty($channel['bot']['embed'])) {
        botpanel_fail('invalid', 'Scegli un canale in cui Poppy può scrivere.', 422, ['errors' => ['channel_id' => 'channel']]);
    }

    // Lo stesso canale di prima: si riscrive il messaggio che c'e' gia'.
    $key = 'roles:' . $menuId;
    $previous = botpanel_posts($mysqli, $guildId)[$key] ?? null;
    $sameChannel = $previous !== null && $previous['channel_id'] === $channelId;

    $response = botpanel_bot_action('/v1/panel/roles/publish', $user, $guildId, [
        'menu_id' => $menuId,
        'channel_id' => $channelId,
        'message_id' => $sameChannel ? $previous['message_id'] : '',
    ]);

    botpanel_post_save($mysqli, $guildId, $key, (string)($response['channel_id'] ?? ''), (string)($response['message_id'] ?? ''));
    botpanel_audit($mysqli, $guildId, $user, 'roles', !empty($response['updated']) ? 'menu_update' : 'menu_publish', null, [
        'menu' => $menuId,
        'channel_id' => $channelId,
    ]);

    botpanel_json(['ok' => true, 'updated' => !empty($response['updated']), 'url' => botpanel_discord_url($response['url'] ?? '')]);
}

botpanel_access($user, $guildId);
cripsum_release_session();

$posts = [];
foreach (botpanel_posts($mysqli, $guildId) as $key => $post) {
    if (str_starts_with($key, 'roles:')) {
        $posts[substr($key, 6)] = $post + ['url' => 'https://discord.com/channels/' . $guildId . '/' . $post['channel_id'] . '/' . $post['message_id']];
    }
}

botpanel_json(['ok' => true, 'posts' => (object)$posts]);
