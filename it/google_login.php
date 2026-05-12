<?php
// google_login.php
require_once '../auth/google_config.php';

$oauthUrl = "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account'
]);

header("Location: $oauthUrl");
exit();
