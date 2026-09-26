<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Dati dell'account dal pannello. Si scrivono solo i campi che arrivano e che
 * sono davvero cambiati: prima il form mandava tutto, anche i valori che non
 * aveva mai letto, e salvare un utente gli azzerava i soldi e gli accendeva
 * NSFW, rich presence e una 2FA senza secret.
 *
 * Le valute non passano di qui (un campo `soldi` viene ignorato): le cambia
 * adjust_user_balance.php, che somma e toglie sul valore del momento.
 */

const ADMIN_PREMIUM_BONUS_GODOS = 25000;
const ADMIN_PREMIUM_BADGE_ID = 5;

try {
    $input = admin_input();
    $userId = (int)($input['id'] ?? 0);
    if ($userId <= 0) admin_fail('ID utente non valido.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);

    $isSelf = (int)$adminUser['id'] === $userId;
    if (!admin_can_manage_user($adminUser, $target, $isSelf)) admin_fail('Non puoi modificare questo utente.', 403);

    $sets = [];
    $types = '';
    $params = [];
    $changes = [];

    $change = static function (string $column, $value, string $type, $old) use (&$sets, &$types, &$params, &$changes): void {
        $sets[] = admin_qcol($column) . ' = ?';
        $types .= $type;
        $params[] = $value;
        $changes[$column] = ['da' => $old, 'a' => $value];
    };
    $has = static fn(string $key): bool => array_key_exists($key, $input);
    $flag = static fn($value): int => (int)filter_var($value, FILTER_VALIDATE_BOOLEAN);

    if ($has('username')) {
        $username = trim((string)$input['username']);
        if ($username !== (string)$target['username']) {
            if (!admin_validate_username($username)) admin_fail('Username non valido. Usa 3-20 caratteri: lettere, numeri o underscore.');
            $conflictSql = admin_column_exists($mysqli, 'utenti', 'custom_alias')
                ? 'SELECT id FROM utenti WHERE (LOWER(username) = LOWER(?) OR LOWER(custom_alias) = LOWER(?)) AND id <> ? LIMIT 1'
                : 'SELECT id FROM utenti WHERE LOWER(username) = LOWER(?) AND id <> ? LIMIT 1';
            $stmt = $mysqli->prepare($conflictSql);
            if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Controllo username non riuscito.'), 500);
            if (admin_column_exists($mysqli, 'utenti', 'custom_alias')) $stmt->bind_param('ssi', $username, $username, $userId);
            else $stmt->bind_param('si', $username, $userId);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) admin_fail('Username già in uso da un altro account.');
            $change('username', $username, 's', $target['username']);
        }
    }

    if ($has('email')) {
        $email = trim((string)$input['email']);
        if ($email !== (string)$target['email']) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) admin_fail('Email non valida.');
            $stmt = $mysqli->prepare('SELECT id FROM utenti WHERE LOWER(email) = LOWER(?) AND id <> ? LIMIT 1');
            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) admin_fail('Email già usata da un altro account.');
            $change('email', $email, 's', $target['email']);
        }
    }

    if ($has('display_name') && array_key_exists('display_name', $target)) {
        $displayName = trim(preg_replace('/\s+/u', ' ', (string)$input['display_name']) ?? '');
        $displayName = mb_substr($displayName, 0, 40, 'UTF-8');
        $displayName = $displayName !== '' ? $displayName : null;
        if ($displayName !== ($target['display_name'] ?? null)) {
            $change('display_name', $displayName, 's', $target['display_name'] ?? null);
        }
    }

    if ($has('ruolo')) {
        $role = (string)$input['ruolo'];
        if (!in_array($role, ['utente', 'admin', 'owner'], true)) admin_fail('Ruolo non valido.');
        if ($role !== (string)$target['ruolo']) {
            if (!admin_can_set_role($adminUser, $target, $role)) admin_fail('Non puoi assegnare questo ruolo.', 403);
            $change('ruolo', $role, 's', $target['ruolo']);
        }
    }

    if ($has('data_creazione') && trim((string)$input['data_creazione']) !== '') {
        $created = admin_parse_datetime((string)$input['data_creazione']);
        if ($created === null) admin_fail('Data di registrazione non valida.');
        if ($created !== (string)$target['data_creazione']) {
            $change('data_creazione', $created, 's', $target['data_creazione']);
        }
    }

    foreach (['email_verificata', 'nsfw', 'richpresence'] as $column) {
        if (!$has($column) || !array_key_exists($column, $target)) continue;
        $value = $flag($input[$column]);
        if ($value !== (int)$target[$column]) $change($column, $value, 'i', (int)$target[$column]);
    }

    // La 2FA dal pannello si puo' solo spegnere: accenderla senza il secret
    // che l'utente ha scansionato non protegge niente e confonde il profilo.
    $resetTwofa = ($has('twofa_reset') && $flag($input['twofa_reset']) === 1)
        || ($has('twofa_enabled') && $flag($input['twofa_enabled']) === 0);
    $twofaOn = (int)($target['twofa_enabled'] ?? 0) === 1 || (int)($target['twofa_has_secret'] ?? 0) === 1;
    if ($resetTwofa && $twofaOn && array_key_exists('twofa_enabled', $target)) {
        $change('twofa_enabled', 0, 'i', (int)$target['twofa_enabled']);
        if (admin_column_exists($mysqli, 'utenti', 'twofa_secret')) $sets[] = 'twofa_secret = NULL';
        if (admin_column_exists($mysqli, 'utenti', 'twofa_enabled_at')) $sets[] = 'twofa_enabled_at = NULL';
    }

    $premiumGranted = false;
    $premiumRevoked = false;
    if ($has('is_premium') && array_key_exists('is_premium', $target)) {
        $premium = $flag($input['is_premium']);
        $wasPremium = (int)$target['is_premium'];
        if ($premium !== $wasPremium) {
            $change('is_premium', $premium, 'i', $wasPremium);
            $premiumGranted = $premium === 1;
            $premiumRevoked = $premium === 0;
        }
    }
    // Il bonus di benvenuto e' quello di un acquisto vero; dal pannello si
    // puo' togliere, per esempio quando si ridà un Premium tolto per errore.
    $premiumBonus = $premiumGranted && (!$has('premium_bonus') || $flag($input['premium_bonus']) === 1)
        && admin_column_exists($mysqli, 'utenti', 'soldi');
    $premiumEmail = $premiumGranted && (!$has('premium_email') || $flag($input['premium_email']) === 1);

    if (!$sets) {
        admin_ok(['message' => 'Nessuna modifica da salvare.', 'changed' => [], 'user' => $target]);
    }

    if (admin_column_exists($mysqli, 'utenti', 'updated_at')) $sets[] = 'updated_at = NOW()';
    if ($premiumBonus) $sets[] = 'soldi = COALESCE(soldi, 0) + ' . ADMIN_PREMIUM_BONUS_GODOS;

    $params[] = $userId;
    $types .= 'i';

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare('UPDATE utenti SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
        if (!$stmt) throw new RuntimeException(admin_prepare_error($mysqli, 'Query di aggiornamento non valida.'));
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new RuntimeException('Aggiornamento non riuscito: ' . $stmt->error);
        $stmt->close();

        if (($premiumGranted || $premiumRevoked) && admin_table_exists($mysqli, 'user_custom_badges')) {
            $badgeId = ADMIN_PREMIUM_BADGE_ID;
            $stmt = $premiumGranted
                ? $mysqli->prepare('INSERT INTO user_custom_badges (utente_id, badge_id, is_visible)
                    SELECT ?, ?, 1 FROM DUAL
                    WHERE NOT EXISTS (SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = ?)')
                : $mysqli->prepare('DELETE FROM user_custom_badges WHERE utente_id = ? AND badge_id = ?');
            if ($stmt) {
                if ($premiumGranted) $stmt->bind_param('iiii', $userId, $badgeId, $userId, $badgeId);
                else $stmt->bind_param('ii', $userId, $badgeId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }

    if ($isSelf && ($premiumGranted || $premiumRevoked)) {
        $_SESSION['is_premium'] = $premiumGranted ? 1 : 0;
    }

    // L'email parte solo a salvataggio riuscito, e un errore di posta non
    // deve far sembrare fallito un aggiornamento che e' andato.
    if ($premiumEmail && function_exists('sendPremiumGiftEmail')) {
        try {
            sendPremiumGiftEmail($changes['email']['a'] ?? $target['email'], $changes['username']['a'] ?? $target['username'], $adminUser['username']);
        } catch (Throwable $e) {
            error_log('admin update_user: email premium non inviata: ' . $e->getMessage());
        }
    }

    $log = $changes;
    if ($premiumBonus) $log['premium_bonus_godos'] = ADMIN_PREMIUM_BONUS_GODOS;
    admin_log($mysqli, (int)$adminUser['id'], 'update_user', $userId, $log);

    $fresh = admin_fetch_user($mysqli, $userId) ?: $target;
    admin_ok([
        'message' => 'Utente aggiornato.',
        'changed' => array_keys($changes),
        'user' => $fresh,
    ]);
} catch (Throwable $e) {
    admin_fail('Errore aggiornamento utente. Dettaglio: ' . $e->getMessage(), 500);
}
