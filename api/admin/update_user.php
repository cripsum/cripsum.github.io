<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $userId = (int)($input['id'] ?? 0);
    $username = trim((string)($input['username'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $role = admin_normalize_role((string)($input['ruolo'] ?? 'utente'));

    if ($userId <= 0) admin_fail('ID utente non valido.');
    if (!admin_validate_username($username)) admin_fail('Username non valido. Usa 3-20 caratteri, lettere, numeri o underscore.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) admin_fail('Email non valida.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);

    $allowSelf = (int)$adminUser['id'] === $userId;
    if (!admin_can_manage_user($adminUser, $target, $allowSelf)) admin_fail('Non puoi modificare questo utente.', 403);
    if (!admin_can_set_role($adminUser, $target, $role)) admin_fail('Non puoi assegnare questo ruolo.', 403);

    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE username = ? AND id <> ? LIMIT 1");
    $stmt->bind_param('si', $username, $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) admin_fail('Username già in uso.');

    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE email = ? AND id <> ? LIMIT 1");
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) admin_fail('Email già in uso.');

    $extra = admin_update_user_timestamp_sql($mysqli);

    // Parse additional fields
    $soldi = isset($input['soldi']) ? (int)$input['soldi'] : null;
    $data_creazione = isset($input['data_creazione']) ? trim((string)$input['data_creazione']) : null;
    $email_verificata = isset($input['email_verificata']) ? (int)$input['email_verificata'] : null;
    $nsfw = isset($input['nsfw']) ? (int)$input['nsfw'] : null;
    $richpresence = isset($input['richpresence']) ? (int)$input['richpresence'] : null;
    $twofa_enabled = isset($input['twofa_enabled']) ? (int)$input['twofa_enabled'] : null;
    $is_premium = isset($input['is_premium']) ? (int)$input['is_premium'] : null;

    $sets = ['username = ?', 'email = ?', 'ruolo = ?'];
    $types = 'sss';
    $params = [$username, $email, $role];

    if ($is_premium !== null && admin_column_exists($mysqli, 'utenti', 'is_premium')) {
        $sets[] = 'is_premium = ?';
        $types .= 'i';
        $params[] = $is_premium;
        
        // Se abilitiamo il premium e l'utente non lo era
        if ($is_premium === 1 && (int)($target['is_premium'] ?? 0) !== 1) {
            // Aggiungi 200.000 soldi
            $stmtSoldi = $mysqli->prepare("UPDATE utenti SET soldi = soldi + 200000 WHERE id = ?");
            if ($stmtSoldi) {
                $stmtSoldi->bind_param('i', $userId);
                $stmtSoldi->execute();
                $stmtSoldi->close();
            }
            
            // Aggiungi Badge Premium ID 5
            $stmtBadge = $mysqli->prepare("
                INSERT INTO user_custom_badges (utente_id, badge_id, is_visible)
                SELECT ?, 5, 1
                FROM DUAL
                WHERE NOT EXISTS (
                    SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = 5
                )
            ");
            if ($stmtBadge) {
                $stmtBadge->bind_param('ii', $userId, $userId);
                $stmtBadge->execute();
                $stmtBadge->close();
            }
        }
        
        // Se disabilitiamo il premium e lo era
        if ($is_premium === 0 && (int)($target['is_premium'] ?? 0) === 1) {
            // Rimuovi Badge Premium ID 5
            $stmtBadgeRem = $mysqli->prepare("DELETE FROM user_custom_badges WHERE utente_id = ? AND badge_id = 5");
            if ($stmtBadgeRem) {
                $stmtBadgeRem->bind_param('i', $userId);
                $stmtBadgeRem->execute();
                $stmtBadgeRem->close();
            }
        }
    }

    if ($soldi !== null && admin_column_exists($mysqli, 'utenti', 'soldi')) {
        $sets[] = 'soldi = ?';
        $types .= 'i';
        $params[] = $soldi;
    }
    if ($data_creazione !== null && admin_column_exists($mysqli, 'utenti', 'data_creazione')) {
        if ($data_creazione !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$/', $data_creazione)) {
                admin_fail('Formato data di creazione non valido. Usa YYYY-MM-DD HH:MM:SS');
            }
            $sets[] = 'data_creazione = ?';
            $types .= 's';
            $params[] = $data_creazione;
        }
    }
    if ($email_verificata !== null && admin_column_exists($mysqli, 'utenti', 'email_verificata')) {
        $sets[] = 'email_verificata = ?';
        $types .= 'i';
        $params[] = $email_verificata;
    }
    if ($nsfw !== null && admin_column_exists($mysqli, 'utenti', 'nsfw')) {
        $sets[] = 'nsfw = ?';
        $types .= 'i';
        $params[] = $nsfw;
    }
    if ($richpresence !== null && admin_column_exists($mysqli, 'utenti', 'richpresence')) {
        $sets[] = 'richpresence = ?';
        $types .= 'i';
        $params[] = $richpresence;
    }
    if ($twofa_enabled !== null && admin_column_exists($mysqli, 'utenti', 'twofa_enabled')) {
        $sets[] = 'twofa_enabled = ?';
        $types .= 'i';
        $params[] = $twofa_enabled;
        
        if ($twofa_enabled === 0 && admin_column_exists($mysqli, 'utenti', 'twofa_secret')) {
            $sets[] = 'twofa_secret = NULL';
        }
    }

    $sql_sets = implode(', ', $sets);
    if ($extra !== '') {
        $sql_sets .= $extra;
    }

    $params[] = $userId;
    $types .= 'i';

    $stmt = $mysqli->prepare("UPDATE utenti SET $sql_sets WHERE id = ? LIMIT 1");
    if (!$stmt) admin_fail('Query aggiornamento non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito ad aggiornare l’utente.', 500);
    $stmt->close();

    $logPayload = ['username' => $username, 'email' => $email, 'role' => $role];
    if ($soldi !== null) $logPayload['soldi'] = $soldi;
    if ($data_creazione !== null) $logPayload['data_creazione'] = $data_creazione;
    if ($twofa_enabled !== null) $logPayload['twofa_enabled'] = $twofa_enabled;
    if ($is_premium !== null) $logPayload['is_premium'] = $is_premium;

    admin_log($mysqli, (int)$adminUser['id'], 'update_user', $userId, $logPayload);
    admin_ok(['message' => 'Utente aggiornato.']);
} catch (Throwable $e) {
    admin_fail('Errore aggiornamento utente. Dettaglio: ' . $e->getMessage(), 500);
}
