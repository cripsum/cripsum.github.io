<?php
declare(strict_types=1);

/**
 * Riscatto di un codice dal bot Discord (/redeem).
 *
 * Stessa logica del riscatto dal sito: vive in includes/redeem_codes.php, qui
 * cambia solo come si identifica l'utente (Discord ID collegato invece di
 * sessione + CSRF).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/redeem_codes.php';

bot_require_key();
bot_require_method('POST');

$body = bot_input();
$discordId = bot_discord_id('discord_id');
$code = strtolower(trim((string)($body['code'] ?? $body['codice'] ?? '')));
$lang = ($body['lang'] ?? 'en') === 'it' ? 'it' : 'en';

if ($code === '') {
    bot_fail('Missing code.', 400);
}

if (mb_strlen($code) > 64) {
    bot_fail('Invalid code.', 400);
}

$user = bot_find_user($mysqli, $discordId);
if (!$user) {
    bot_json(['ok' => true, 'linked' => false]);
}

$result = cripsum_redeem_code_apply($mysqli, (int)$user['id'], $code, $lang);

// Del personaggio si restituiscono solo i campi che servono all'embed, non
// tutta la riga della tabella.
if (($result['status'] ?? '') === 'success' && isset($result['personaggio']) && is_array($result['personaggio'])) {
    $character = $result['personaggio'];
    $result['personaggio'] = [
        'id' => (int)($character['id'] ?? 0),
        'nome' => (string)($character['nome'] ?? ''),
        'rarità' => (string)($character['rarità'] ?? ''),
        'categoria' => (string)($character['categoria'] ?? ''),
        'img_url' => $character['img_url'] ?? null,
        'descrizione' => $character['descrizione_en'] ?? $character['descrizione'] ?? null,
    ];
}

bot_json([
    'ok' => true,
    'linked' => true,
    'username' => (string)$user['username'],
    'result' => $result,
]);
