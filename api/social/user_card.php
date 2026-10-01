<?php
/**
 * Dati della card utente (il popup che si apre cliccando un nome).
 *
 * Quello che esce dipende da chi guarda: di un profilo privato, o di qualcuno
 * con cui c'è un blocco, restano solo nome e avatar. Bio, ultimo accesso,
 * banner e amici in comune seguono la visibilità del profilo.
 */
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $targetId = (int)($_GET['target_id'] ?? 0);
    $username = trim((string)($_GET['username'] ?? ''));

    if ($targetId <= 0 && $username === '') {
        throw new SocialError(rt_t('Utente non valido.', 'Invalid user.'), 'INVALID_INPUT');
    }

    $columns = "id, username, display_name, ruolo, is_premium, bio, profile_banner_type,
                TIMESTAMPDIFF(SECOND, ultimo_accesso, NOW()) AS idle_seconds,
                UNIX_TIMESTAMP(ultimo_accesso) AS last_seen_ts,
                accent_color, profile_secondary_color, profile_card_color, profile_text_color,
                avatar_ring_enabled, avatar_ring_style, avatar_ring_color";
    foreach (['profile_card_opacity', 'profile_card_blur', 'profile_font', 'profile_ui_shape', 'custom_status'] as $optional) {
        if (rt_has_col($mysqli, 'utenti', $optional)) {
            $columns .= ', ' . $optional;
        }
    }

    if ($targetId > 0) {
        $stmt = $mysqli->prepare("SELECT $columns FROM utenti WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $targetId);
    } else {
        $stmt = $mysqli->prepare("SELECT $columns FROM utenti WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
    }
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$profile) {
        throw new SocialError(rt_t('Utente non trovato.', 'User not found.'), 'USER_NOT_FOUND', 404);
    }

    $targetId = (int)$profile['id'];
    $rel = sc_relationship($mysqli, $userId, $targetId);
    $visible = $rel['is_self'] || $rel['can_view_profile'];

    $data = [
        'id' => $targetId,
        'username' => (string)$profile['username'],
        'display_name' => (string)($profile['display_name'] ?: $profile['username']),
        'ruolo' => (string)$profile['ruolo'],
        'is_premium' => (int)$profile['is_premium'] === 1,
        'bio' => '',
        'custom_status' => '',
        'is_online' => false,
        'last_seen_ts' => null,
        'profile_banner_url' => null,
        'profile_banner_type' => null,
        'profile_hidden' => !$visible,
        'stats' => ['followers_count' => 0, 'following_count' => 0, 'friends_count' => 0, 'mutual_friends_count' => 0],
        'mutual_friends' => [],
        'relationship' => $rel,
        'style' => [
            'accent_color' => null, 'secondary_color' => null, 'card_color' => null, 'text_color' => null,
            'card_opacity' => null, 'card_blur' => null, 'font' => null,
            'avatar_ring_enabled' => false, 'avatar_ring_style' => null, 'avatar_ring_color' => null, 'ui_shape' => null,
        ],
    ];

    if ($visible) {
        $idle = $profile['idle_seconds'];
        $online = $idle !== null && (int)$idle < SC_ONLINE_WINDOW;

        $stmt = $mysqli->prepare('SELECT COUNT(*) FROM friendships WHERE user_one_id = ? OR user_two_id = ?');
        $stmt->bind_param('ii', $targetId, $targetId);
        $stmt->execute();
        $friendsCount = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();

        $mutual = $rel['is_self'] ? ['count' => 0, 'users' => []] : sc_mutual_friends($mysqli, $userId, $targetId, 5);

        $data['bio'] = (string)($profile['bio'] ?? '');
        $data['custom_status'] = (string)($profile['custom_status'] ?? '');
        $data['is_online'] = $online;
        $data['last_seen_ts'] = (!$online && !empty($profile['last_seen_ts'])) ? (int)$profile['last_seen_ts'] : null;
        $data['profile_banner_url'] = !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . $targetId : null;
        $data['profile_banner_type'] = $profile['profile_banner_type'] ?: null;
        $data['stats']['friends_count'] = $friendsCount;
        $data['stats']['mutual_friends_count'] = $mutual['count'];
        $data['mutual_friends'] = $mutual['users'];
        $data['style'] = [
            'accent_color' => $profile['accent_color'] ?? null,
            'secondary_color' => $profile['profile_secondary_color'] ?? null,
            'card_color' => $profile['profile_card_color'] ?? null,
            'text_color' => $profile['profile_text_color'] ?? null,
            'card_opacity' => $profile['profile_card_opacity'] ?? null,
            'card_blur' => $profile['profile_card_blur'] ?? null,
            'font' => $profile['profile_font'] ?? null,
            'avatar_ring_enabled' => !empty($profile['avatar_ring_enabled']),
            'avatar_ring_style' => $profile['avatar_ring_style'] ?? null,
            'avatar_ring_color' => $profile['avatar_ring_color'] ?? null,
            'ui_shape' => $profile['profile_ui_shape'] ?? null,
        ];
    }

    send_api_success($data);
});
