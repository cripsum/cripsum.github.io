<?php
/**
 * Google OAuth 2.0 – helper functions
 *
 * Dipendenze attese in config (es. config/app_config.php):
 *   define('GOOGLE_CLIENT_ID',     'xxx.apps.googleusercontent.com');
 *   define('GOOGLE_CLIENT_SECRET', 'xxx');
 *   define('GOOGLE_REDIRECT_URI',  SITE_URL . '/it/google-callback');
 */

// ──────────────────────────────────────────────
// 1. Genera l'URL di autorizzazione Google
// ──────────────────────────────────────────────
function google_auth_url(): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',   // mostra sempre la selezione account
        'access_type'   => 'online',
    ]);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

// ──────────────────────────────────────────────
// 2. Scambia il codice con un access token
// ──────────────────────────────────────────────
function google_exchange_code(string $code): ?array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$body) {
        error_log('google_exchange_code curl error: ' . $err);
        return null;
    }

    $data = json_decode($body, true);
    return (!empty($data['access_token'])) ? $data : null;
}

// ──────────────────────────────────────────────
// 3. Recupera le info dell'utente Google
// ──────────────────────────────────────────────
function google_get_userinfo(string $access_token): ?array
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$body) {
        error_log('google_get_userinfo curl error: ' . $err);
        return null;
    }

    $data = json_decode($body, true);

    // Campi minimi obbligatori
    if (empty($data['sub']) || empty($data['email'])) {
        return null;
    }

    return $data;
}

// ──────────────────────────────────────────────
// 4. Login / registrazione con dati Google
//    Restituisce ['ok'=>true] oppure ['error'=>'...']
// ──────────────────────────────────────────────
function google_handle_login(mysqli $mysqli, array $gUser): array
{
    $googleId = $gUser['sub'];
    $email    = strtolower(trim($gUser['email']));
    $name     = $gUser['name']          ?? '';
    $picture  = $gUser['picture']       ?? null;

    // ── Caso A: utente già collegato con questo google_id ──
    $stmt = $mysqli->prepare(
        "SELECT id, username, email, ruolo, isBannato, email_verificata
         FROM utenti WHERE google_id = ? LIMIT 1"
    );
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        return _google_start_session($user);
    }

    // ── Caso B: esiste un account con la stessa email ──
    $stmt = $mysqli->prepare(
        "SELECT id, username, email, ruolo, isBannato, email_verificata
         FROM utenti WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Collega il google_id all'account esistente e segna l'email verificata
        $stmt = $mysqli->prepare(
            "UPDATE utenti SET google_id = ?, email_verificata = 1 WHERE id = ?"
        );
        $stmt->bind_param('si', $googleId, $user['id']);
        $stmt->execute();
        $stmt->close();

        return _google_start_session($user);
    }

    // ── Caso C: nuovo utente → registrazione automatica ──
    $username = _google_unique_username($mysqli, $name, $email);

    $stmt = $mysqli->prepare(
        "INSERT INTO utenti
            (username, email, password, google_id, data_creazione, ruolo, email_verificata, profile_pic)
         VALUES (?, ?, '', ?, NOW(), 'utente', 1, ?)"
    );
    $stmt->bind_param('ssss', $username, $email, $googleId, $picture);

    if (!$stmt->execute()) {
        error_log('google_handle_login INSERT error: ' . $stmt->error);
        $stmt->close();
        return ['error' => 'Errore durante la creazione dell\'account.'];
    }

    $newId = $mysqli->insert_id;
    $stmt->close();

    $newUser = [
        'id'               => $newId,
        'username'         => $username,
        'email'            => $email,
        'ruolo'            => 'utente',
        'isBannato'        => 0,
        'email_verificata' => 1,
    ];

    return _google_start_session($newUser);
}

// ──────────────────────────────────────────────
// Helpers privati
// ──────────────────────────────────────────────

/** Avvia la sessione esattamente come fa auth_start_password_login */
function _google_start_session(array $user): array
{
    if (!empty($user['isBannato'])) {
        return ['error' => 'Il tuo account è stato sospeso.'];
    }

    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['ruolo']    = $user['ruolo'];

    return ['ok' => true];
}

/** Genera uno username unico partendo dal nome Google */
function _google_unique_username(mysqli $mysqli, string $name, string $email): string
{
    // Prova prima col nome, poi con la parte locale dell'email
    $base = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $name)));

    if (strlen($base) < 3) {
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower(explode('@', $email)[0]));
    }

    $base = substr($base ?: 'user', 0, 16);

    // Verifica unicità, aggiunge suffisso numerico se serve
    $candidate = $base;
    $i = 1;
    while (true) {
        $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            return $candidate;
        }

        $candidate = $base . $i;
        $i++;
    }
}
