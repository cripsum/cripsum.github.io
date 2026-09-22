<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../config/discord_oauth.php';

checkBan($mysqli);

/**
 * Riduce un indirizzo a un percorso interno del sito, o a stringa vuota.
 *
 * Serve perche' dopo l'autorizzazione si viene rimandati qui: senza questo
 * controllo un link con return_url esterno porterebbe l'utente fuori da
 * cripsum.com al termine di un OAuth perfettamente legittimo.
 */
$localPath = static function ($value): string {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }

    // Percorso gia' interno ("//host" no: e' un indirizzo assoluto travestito).
    if ($value[0] === '/' && strncmp($value, '//', 2) !== 0) {
        return $value;
    }

    $parts = parse_url($value);
    if (!$parts || empty($parts['host'])) {
        return '';
    }

    $host = strtolower($parts['host']);
    if ($host !== 'cripsum.com' && $host !== 'www.cripsum.com') {
        return '';
    }

    return ($parts['path'] ?? '/')
        . (isset($parts['query']) ? '?' . $parts['query'] : '')
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
};

// Dove tornare a collegamento fatto: quello che ci hanno passato, altrimenti
// la pagina da cui e' partito il click (impostazioni o edit-profile), e in
// ultima istanza la sezione Connessioni.
$returnUrl = $localPath($_GET['return_url'] ?? '');
if ($returnUrl === '') {
    $returnUrl = $localPath($_SERVER['HTTP_REFERER'] ?? '');
}
if ($returnUrl === '') {
    $returnUrl = '/it/impostazioni#connections';
}

if (!isLoggedIn()) {
    // Dopo l'accesso si rientra qui, non su una pagina fissa: l'autorizzazione
    // riparte da sola e l'utente torna esattamente dove aveva cominciato,
    // che sia impostazioni o edit-profile.
    $resumeQuery = ['return_url' => $returnUrl];
    if (isset($_GET['target_user_id'])) {
        $resumeQuery['target_user_id'] = (int)$_GET['target_user_id'];
    }

    $_SESSION['redirect_after_login'] = '/auth/discord_connect.php?' . http_build_query($resumeQuery);
    $_SESSION['login_message'] = 'To connect your Discord account, you need to be logged in.';
    header('Location: /en/accedi');
    exit;
}

if (CRIPSUM_DISCORD_CLIENT_ID === 'INSERISCI_CLIENT_ID' || CRIPSUM_DISCORD_CLIENT_SECRET === 'INSERISCI_CLIENT_SECRET') {
    http_response_code(500);
    exit('Discord OAuth not configured. Set client id and secret.');
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_GET['target_user_id']) && profile_is_staff() ? (int)$_GET['target_user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    http_response_code(403);
    exit('Access denied.');
}

$state = bin2hex(random_bytes(32));
$_SESSION['discord_oauth_state'] = $state;
$_SESSION['discord_oauth_target_user_id'] = $targetUserId;

// Solo percorsi interni: $returnUrl e' gia' passato da $localPath.
$_SESSION['discord_oauth_return_url'] = $returnUrl;

$params = http_build_query([
    'client_id' => CRIPSUM_DISCORD_CLIENT_ID,
    'redirect_uri' => CRIPSUM_DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'identify',
    'state' => $state,
    'prompt' => 'consent',
]);

header('Location: https://discord.com/oauth2/authorize?' . $params);
exit;
