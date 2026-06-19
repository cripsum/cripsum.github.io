<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato.']);
    exit;
}

$currentUserId = profile_current_user_id();
if ($currentUserId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Utente non trovato.']);
    exit;
}

$requestTargetUserId = isset($_REQUEST['target_user_id']) ? (int)$_REQUEST['target_user_id'] : 0;
$targetUserId = ($requestTargetUserId > 0 && profile_is_staff()) ? $requestTargetUserId : $currentUserId;

// Action dispatcher
$action = $_GET['action'] ?? '';

function profile_build_preset_data(mysqli $mysqli, int $targetUserId): ?array
{
    // Columns representing all customizations & style tokens
    $columnsToSave = [
        'display_name', 'bio', 'profile_banner_type', 'accent_color', 'profile_secondary_color',
        'profile_card_color', 'profile_text_color', 'profile_link_style', 'profile_button_shape',
        'profile_theme', 'profile_layout', 'profile_visibility', 'profile_status', 'profile_show_stats',
        'profile_show_socials', 'profile_show_links', 'profile_show_projects', 'profile_show_contents',
        'profile_show_blocks',
        'profile_show_badges', 'profile_show_activity', 'profile_show_discord', 'profile_music_url',
        'profile_music_mime', 'profile_music_title', 'profile_music_artist', 'profile_show_audio_player',
        'profile_effect', 'profile_show_characters', 'avatar_ring_enabled', 'avatar_ring_style',
        'avatar_ring_color', 'profile_enter_text', 'profile_click_to_enter', 'profile_socials_style',
        'profile_show_embeds', 'profile_sections_order', 'profile_sections_config', 'profile_badges_display', 'profile_badges_position',
        'discord_server_invite', 'profile_font', 'profile_border_radius', 'profile_card_opacity',
        'profile_card_blur', 'profile_border_opacity', 'profile_border_color', 'profile_border_width',
        'profile_name_style', 'profile_ui_shape', 'profile_avatar_shape', 'profile_social_size',
        'profile_icon_spacing', 'profile_badge_size', 'profile_button_size', 'profile_avatar_border',
        'tilt_enabled', 'tilt_max', 'tilt_glare', 'tilt_zoom', 'tilt_speed', 'profile_tags_json',
        'profile_tab_title', 'profile_tab_animation', 'profile_tab_animation_speed', 'profile_tab_animation_text',
        'profile_corner_style', 'profile_corner_style_custom', 'profile_border_style'
    ];

    $profile = profile_get_edit_profile($mysqli, $targetUserId);
    if (!$profile) {
        return null;
    }

    $presetData = [];
    foreach ($columnsToSave as $col) {
        if (isset($_POST[$col])) {
            $presetData[$col] = $_POST[$col];
        } else {
            $presetData[$col] = $profile[$col] ?? null;
        }
    }

    // Checkbox values override (unchecked checkboxes are not present in POST payload)
    $booleans = [
        'tilt_enabled', 'avatar_ring_enabled', 'profile_avatar_border', 'profile_show_stats',
        'profile_show_socials', 'profile_show_links', 'profile_show_projects', 'profile_show_contents',
        'profile_show_blocks',
        'profile_show_badges', 'profile_show_activity', 'profile_show_discord', 'profile_show_audio_player',
        'profile_click_to_enter', 'profile_show_embeds', 'profile_show_characters'
    ];
    foreach ($booleans as $boolCol) {
        $presetData[$boolCol] = isset($_POST[$boolCol]) ? (int)$_POST[$boolCol] : 0;
    }

    // Save layout structures as serialized lists
    $presetData['socials_json'] = $_POST['socials_json'] ?? '[]';
    $presetData['links_json'] = $_POST['links_json'] ?? '[]';
    $presetData['projects_json'] = $_POST['projects_json'] ?? '[]';
    $presetData['contents_json'] = $_POST['contents_json'] ?? '[]';
    $presetData['blocks_json'] = $_POST['blocks_json'] ?? '[]';
    $presetData['embeds_json'] = $_POST['embeds_json'] ?? '[]';
    $presetData['badges_json'] = $_POST['badges_json'] ?? '[]';
    $presetData['characters_json'] = $_POST['characters_json'] ?? '[]';

    // Fetch media blobs from the database
    $mediaStmt = $mysqli->prepare("SELECT profile_pic, profile_pic_type, profile_banner, profile_banner_type, profile_music_blob, profile_music_mime FROM utenti WHERE id = ? LIMIT 1");
    $mediaStmt->bind_param('i', $targetUserId);
    $mediaStmt->execute();
    $mediaRow = $mediaStmt->get_result()->fetch_assoc();
    $mediaStmt->close();

    if ($mediaRow) {
        $presetData['profile_pic_b64'] = $mediaRow['profile_pic'] ? base64_encode($mediaRow['profile_pic']) : null;
        $presetData['profile_pic_type'] = $mediaRow['profile_pic_type'];
        $presetData['profile_banner_b64'] = $mediaRow['profile_banner'] ? base64_encode($mediaRow['profile_banner']) : null;
        $presetData['profile_banner_type'] = $mediaRow['profile_banner_type'];
        $presetData['profile_music_b64'] = $mediaRow['profile_music_blob'] ? base64_encode($mediaRow['profile_music_blob']) : null;
        $presetData['profile_music_mime'] = $mediaRow['profile_music_mime'];
    } else {
        $presetData['profile_pic_b64'] = null;
        $presetData['profile_pic_type'] = null;
        $presetData['profile_banner_b64'] = null;
        $presetData['profile_banner_type'] = null;
        $presetData['profile_music_b64'] = null;
        $presetData['profile_music_mime'] = null;
    }

    return $presetData;
}

switch ($action) {
    case 'list':
        $stmt = $mysqli->prepare("SELECT id, nome, created_at, preset_data FROM utenti_presets WHERE utente_id = ? OR utente_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('ii', $targetUserId, $currentUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        $presets = [];
        while ($row = $res->fetch_assoc()) {
            $presets[] = [
                'id' => (int)$row['id'],
                'nome' => $row['nome'],
                'created_at' => $row['created_at'],
                'preset_data' => $row['preset_data']
            ];
        }
        $stmt->close();
        echo json_encode(['ok' => true, 'presets' => $presets]);
        break;

    case 'save':
        $nome = trim($_POST['preset_name'] ?? '');
        if ($nome === '') {
            echo json_encode(['ok' => false, 'message' => 'Il nome del preset non può essere vuoto.']);
            exit;
        }

        // Limit count check (3 presets max)
        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM utenti_presets WHERE utente_id = ?");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $cntRow = $stmt->get_result()->fetch_assoc();
        $count = (int)($cntRow['count'] ?? 0);
        $stmt->close();

        $profile = profile_get_edit_profile($mysqli, $targetUserId);
        $isPremium = $profile && (int)($profile['is_premium'] ?? 0) === 1;

        if (!$isPremium) {
            echo json_encode(['ok' => false, 'message' => 'Il salvataggio dei preset personalizzati richiede un account Premium.']);
            exit;
        }

        if ($count >= 10) {
            echo json_encode(['ok' => false, 'message' => 'Hai raggiunto il limite massimo di 10 preset.']);
            exit;
        }

        $presetData = profile_build_preset_data($mysqli, $targetUserId);
        if (!$presetData) {
            echo json_encode(['ok' => false, 'message' => 'Profilo non trovato.']);
            exit;
        }

        $serialized = json_encode($presetData, JSON_UNESCAPED_UNICODE);

        $stmt = $mysqli->prepare("INSERT INTO utenti_presets (utente_id, nome, preset_data) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $targetUserId, $nome, $serialized);
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Preset salvato con successo!']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Errore nel salvataggio del preset.']);
        }
        $stmt->close();
        break;

    case 'update':
        $profile = profile_get_edit_profile($mysqli, $targetUserId);
        $isPremium = $profile && (int)($profile['is_premium'] ?? 0) === 1;
        if (!$isPremium) {
            echo json_encode(['ok' => false, 'message' => 'L\'aggiornamento dei preset personalizzati richiede un account Premium.']);
            exit;
        }

        $presetId = (int)($_POST['preset_id'] ?? 0);
        if ($presetId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Preset ID non valido.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT id FROM utenti_presets WHERE id = ? AND (utente_id = ? OR utente_id = ?) LIMIT 1");
        $stmt->bind_param('iii', $presetId, $targetUserId, $currentUserId);
        $stmt->execute();
        $presetRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$presetRow) {
            echo json_encode(['ok' => false, 'message' => 'Preset non trovato.']);
            exit;
        }

        $presetData = profile_build_preset_data($mysqli, $targetUserId);
        if (!$presetData) {
            echo json_encode(['ok' => false, 'message' => 'Profilo non trovato.']);
            exit;
        }

        $serialized = json_encode($presetData, JSON_UNESCAPED_UNICODE);
        $stmt = $mysqli->prepare("UPDATE utenti_presets SET preset_data = ? WHERE id = ? AND (utente_id = ? OR utente_id = ?)");
        $stmt->bind_param('siii', $serialized, $presetId, $targetUserId, $currentUserId);
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Preset aggiornato con successo!']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Errore nell\'aggiornamento del preset.']);
        }
        $stmt->close();
        break;

    case 'load':
        $profile = profile_get_edit_profile($mysqli, $targetUserId);
        $isPremium = $profile && (int)($profile['is_premium'] ?? 0) === 1;
        if (!$isPremium) {
            echo json_encode(['ok' => false, 'message' => 'Il caricamento dei preset personalizzati richiede un account Premium.']);
            exit;
        }

        $presetId = (int)($_POST['preset_id'] ?? 0);
        if ($presetId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Preset ID non valido.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT preset_data FROM utenti_presets WHERE id = ? AND (utente_id = ? OR utente_id = ?) LIMIT 1");
        $stmt->bind_param('iii', $presetId, $targetUserId, $currentUserId);
        $stmt->execute();
        $presetRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$presetRow) {
            echo json_encode(['ok' => false, 'message' => 'Preset non trovato.']);
            exit;
        }

        $presetData = json_decode($presetRow['preset_data'], true);
        if (!is_array($presetData)) {
            echo json_encode(['ok' => false, 'message' => 'Errore di decodifica dei dati del preset.']);
            exit;
        }

        $columnsToUpdate = [
            'display_name', 'bio', 'profile_banner_type', 'accent_color', 'profile_secondary_color', 
            'profile_card_color', 'profile_text_color', 'profile_link_style', 'profile_button_shape', 
            'profile_theme', 'profile_layout', 'profile_visibility', 'profile_status', 'profile_show_stats', 
            'profile_show_socials', 'profile_show_links', 'profile_show_projects', 'profile_show_contents', 
            'profile_show_blocks', 
            'profile_show_badges', 'profile_show_activity', 'profile_show_discord', 'profile_music_url', 
            'profile_music_mime', 'profile_music_title', 'profile_music_artist', 'profile_show_audio_player', 
            'profile_effect', 'profile_show_characters', 'avatar_ring_enabled', 'avatar_ring_style', 
            'avatar_ring_color', 'profile_enter_text', 'profile_click_to_enter', 'profile_socials_style', 
            'profile_show_embeds', 'profile_sections_order', 'profile_sections_config', 'profile_badges_display', 'profile_badges_position', 
            'discord_server_invite', 'profile_font', 'profile_border_radius', 'profile_card_opacity', 
            'profile_card_blur', 'profile_border_opacity', 'profile_border_color', 'profile_border_width', 
            'profile_name_style', 'profile_ui_shape', 'profile_avatar_shape', 'profile_social_size', 
            'profile_icon_spacing', 'profile_badge_size', 'profile_button_size', 'profile_avatar_border',
            'tilt_enabled', 'tilt_max', 'tilt_glare', 'tilt_zoom', 'tilt_speed', 'profile_tags_json',
            'profile_tab_title', 'profile_tab_animation', 'profile_tab_animation_speed', 'profile_tab_animation_text',
            'profile_corner_style', 'profile_corner_style_custom', 'profile_border_style'
        ];

        $setParts = [];
        $types = '';
        $params = [];
        foreach ($columnsToUpdate as $col) {
            if (array_key_exists($col, $presetData)) {
                $setParts[] = "`$col` = ?";
                $val = $presetData[$col];
                if (is_int($val) || is_bool($val)) {
                    $types .= 'i';
                    $params[] = (int)$val;
                } elseif (is_float($val)) {
                    $types .= 'd';
                    $params[] = (float)$val;
                } else {
                    $types .= 's';
                    $params[] = $val !== null ? (string)$val : null;
                }
            }
        }

        if (empty($setParts)) {
            echo json_encode(['ok' => false, 'message' => 'Nessun dato da aggiornare.']);
            exit;
        }

        $sql = "UPDATE utenti SET " . implode(', ', $setParts) . ", profile_updated_at = NOW() WHERE id = ?";
        $types .= 'i';
        $params[] = $targetUserId;

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo json_encode(['ok' => false, 'message' => 'Errore compilazione query di aggiornamento.']);
            exit;
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            echo json_encode(['ok' => false, 'message' => 'Errore nell\'aggiornamento del profilo.']);
            exit;
        }
        $stmt->close();

        // 1. Restore Avatar (profile_pic)
        if (array_key_exists('profile_pic_b64', $presetData)) {
            if ($presetData['profile_pic_b64'] !== null) {
                $picBlob = base64_decode($presetData['profile_pic_b64']);
                $picMime = $presetData['profile_pic_type'] ?? 'image/png';
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_pic = ?, profile_pic_type = ? WHERE id = ?");
                $null = null;
                $stmt->bind_param('bsi', $null, $picMime, $targetUserId);
                $stmt->send_long_data(0, $picBlob);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_pic = NULL, profile_pic_type = NULL WHERE id = ?");
                $stmt->bind_param('i', $targetUserId);
                $stmt->execute();
                $stmt->close();
            }
        }

        // 2. Restore Banner (profile_banner)
        if (array_key_exists('profile_banner_b64', $presetData)) {
            if ($presetData['profile_banner_b64'] !== null) {
                $bannerBlob = base64_decode($presetData['profile_banner_b64']);
                $bannerMime = $presetData['profile_banner_type'] ?? 'image/png';
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_banner = ?, profile_banner_type = ? WHERE id = ?");
                $null = null;
                $stmt->bind_param('bsi', $null, $bannerMime, $targetUserId);
                $stmt->send_long_data(0, $bannerBlob);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_banner = NULL, profile_banner_type = NULL WHERE id = ?");
                $stmt->bind_param('i', $targetUserId);
                $stmt->execute();
                $stmt->close();
            }
        }

        // 3. Restore Music (profile_music_blob)
        if (array_key_exists('profile_music_b64', $presetData)) {
            if ($presetData['profile_music_b64'] !== null) {
                $musicBlob = base64_decode($presetData['profile_music_b64']);
                $musicMime = $presetData['profile_music_mime'] ?? 'audio/mpeg';
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_music_blob = ?, profile_music_mime = ?, profile_music_url = NULL WHERE id = ?");
                $null = null;
                $stmt->bind_param('bsi', $null, $musicMime, $targetUserId);
                $stmt->send_long_data(0, $musicBlob);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_music_blob = NULL, profile_music_mime = NULL WHERE id = ?");
                $stmt->bind_param('i', $targetUserId);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Restore child relations within transactional execution context
        try {
            $mysqli->begin_transaction();

            // 1. Socials list restoration
            $mysqli->query("DELETE FROM utenti_social WHERE utente_id = " . $targetUserId);
            $socialRows = json_decode($presetData['socials_json'] ?? '[]', true) ?: [];
            $insertSocial = $mysqli->prepare("INSERT INTO utenti_social (utente_id, platform, label, display_username, url, sort_order, is_visible, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $allowedPlatforms = ['tiktok', 'instagram', 'youtube', 'twitch', 'github', 'discord', 'telegram', 'x', 'spotify', 'soundcloud', 'steam', 'reddit', 'pinterest', 'snapchat', 'facebook', 'linkedin', 'paypal', 'patreon', 'kick', 'bluesky', 'threads', 'behance', 'dribbble', 'website', 'email', 'other'];
            foreach ($socialRows as $i => $row) {
                $platform = strtolower(profile_clean_text($row['platform'] ?? 'other', 32));
                $platform = in_array($platform, $allowedPlatforms, true) ? $platform : 'other';
                $label = profile_clean_text($row['label'] ?? $platform, 40);
                $displayUsername = profile_clean_text($row['display_username'] ?? '', 60);
                $displayUsernameDb = $displayUsername !== '' ? $displayUsername : null;
                $url = trim((string)($row['url'] ?? ''));
                $visible = !empty($row['is_visible']) ? 1 : 0;
                $icon = isset($row['icon']) ? profile_clean_text($row['icon'], 255) : null;
                if ($url === '') continue;
                $insertSocial->bind_param('issssiis', $targetUserId, $platform, $label, $displayUsernameDb, $url, $i, $visible, $icon);
                $insertSocial->execute();
            }
            $insertSocial->close();

            // 2. Links list restoration
            $mysqli->query("DELETE FROM utenti_links WHERE utente_id = " . $targetUserId);
            $linkRows = json_decode($presetData['links_json'] ?? '[]', true) ?: [];
            $insertLink = $mysqli->prepare("INSERT INTO utenti_links (utente_id, title, description, url, icon, button_style, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($linkRows as $i => $row) {
                $title = profile_clean_text($row['title'] ?? '', 60);
                $description = profile_clean_text($row['description'] ?? '', 160);
                $url = trim((string)($row['url'] ?? ''));
                $icon = profile_clean_text($row['icon'] ?? 'fa-solid fa-link', 255);
                if (!$isPremium && (preg_match('/^https?:\/\//i', $icon) || str_starts_with($icon, '/uploads/') || str_contains($icon, '.'))) {
                    $icon = 'fa-solid fa-link';
                }
                $buttonStyle = profile_allowed_value((string)($row['button_style'] ?? 'card'), ['card', 'compact', 'icon'], 'card');
                $featured = !empty($row['is_featured']) ? 1 : 0;
                $visible = !empty($row['is_visible']) ? 1 : 0;
                if ($title === '' && $url === '') continue;
                $insertLink->bind_param('isssssiii', $targetUserId, $title, $description, $url, $icon, $buttonStyle, $featured, $visible, $i);
                $insertLink->execute();
            }
            $insertLink->close();

            // 3. Projects list restoration
            $mysqli->query("DELETE FROM utenti_projects WHERE utente_id = " . $targetUserId);
            $projectRows = json_decode($presetData['projects_json'] ?? '[]', true) ?: [];
            $insertProject = $mysqli->prepare("INSERT INTO utenti_projects (utente_id, title, description, url, image_url, tech_stack, status, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $allowedStatuses = ['active', 'paused', 'finished', 'idea'];
            foreach ($projectRows as $i => $row) {
                $title = profile_clean_text($row['title'] ?? '', 70);
                $description = profile_clean_text($row['description'] ?? '', 260);
                $url = trim((string)($row['url'] ?? ''));
                $imageUrl = trim((string)($row['image_url'] ?? ''));
                $techStack = profile_clean_text($row['tech_stack'] ?? '', 160);
                $status = profile_allowed_value((string)($row['status'] ?? 'active'), $allowedStatuses, 'active');
                $featured = !empty($row['is_featured']) ? 1 : 0;
                $visible = !empty($row['is_visible']) ? 1 : 0;
                if ($title === '') continue;
                $insertProject->bind_param('issssssiii', $targetUserId, $title, $description, $url, $imageUrl, $techStack, $status, $featured, $visible, $i);
                $insertProject->execute();
            }
            $insertProject->close();

            // 4. Contents list restoration
            $mysqli->query("DELETE FROM utenti_contents WHERE utente_id = " . $targetUserId);
            $contentRows = json_decode($presetData['contents_json'] ?? '[]', true) ?: [];
            $insertContent = $mysqli->prepare("INSERT INTO utenti_contents (utente_id, content_type, title, description, url, thumbnail_url, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $allowedTypes = ['edit', 'video', 'game', 'post', 'other'];
            foreach ($contentRows as $i => $row) {
                $cType = profile_allowed_value((string)($row['content_type'] ?? 'edit'), $allowedTypes, 'edit');
                $title = profile_clean_text($row['title'] ?? '', 70);
                $description = profile_clean_text($row['description'] ?? '', 220);
                $url = trim((string)($row['url'] ?? ''));
                $thumbUrl = trim((string)($row['thumbnail_url'] ?? ''));
                $featured = !empty($row['is_featured']) ? 1 : 0;
                $visible = !empty($row['is_visible']) ? 1 : 0;
                if ($title === '') continue;
                $insertContent->bind_param('isssssiii', $targetUserId, $cType, $title, $description, $url, $thumbUrl, $featured, $visible, $i);
                $insertContent->execute();
            }
            $insertContent->close();

            // 5. Blocks list restoration
            $mysqli->query("DELETE FROM utenti_profile_blocks WHERE utente_id = " . $targetUserId);
            $blockRows = json_decode($presetData['blocks_json'] ?? '[]', true) ?: [];
            $insertBlock = $mysqli->prepare("INSERT INTO utenti_profile_blocks (utente_id, block_type, title, body, media_url, media_type, is_featured, is_visible, sort_order, no_card_style, media_position, text_align, media_align, media_fit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $allowedBTypes = ['text', 'image', 'gif', 'video'];
            if ($isPremium) {
                $allowedBTypes[] = 'markdown';
                $allowedBTypes[] = 'html';
            }
            foreach ($blockRows as $i => $row) {
                $bType = profile_allowed_value((string)($row['block_type'] ?? 'text'), $allowedBTypes, 'text');
                $title = profile_clean_text($row['title'] ?? '', 80);
                $body = trim((string)($row['body'] ?? ''));
                $maxLen = ($isPremium && in_array($bType, ['markdown', 'html'], true)) ? 5000 : 700;
                if ($isPremium && in_array($bType, ['markdown', 'html'], true)) {
                    $body = mb_substr($body, 0, $maxLen, 'UTF-8');
                } else {
                    $body = profile_clean_text($body, $maxLen);
                }
                $mediaUrl = trim((string)($row['media_url'] ?? ''));
                $mType = profile_allowed_value((string)($row['media_type'] ?? $row['block_type'] ?? 'image'), $allowedBTypes, 'image');
                $featured = !empty($row['is_featured']) ? 1 : 0;
                $visible = !empty($row['is_visible']) ? 1 : 0;
                if ($title === '' && $body === '' && $mediaUrl === '') continue;
                $noCardStyle = (!empty($row['no_card_style']) && $isPremium) ? 1 : 0;
                $allowedMPos = ['top', 'bottom'];
                $allowedTAlign = ['left', 'center', 'right'];
                $allowedMAlign = ['left', 'center', 'right'];
                $allowedMFit = ['cover', 'contain', 'original'];
                $mediaPosition = (isset($row['media_position']) && in_array($row['media_position'], $allowedMPos, true)) ? $row['media_position'] : 'top';
                $textAlign = (isset($row['text_align']) && in_array($row['text_align'], $allowedTAlign, true)) ? $row['text_align'] : 'left';
                $mediaAlign = (isset($row['media_align']) && in_array($row['media_align'], $allowedMAlign, true)) ? $row['media_align'] : 'center';
                $mediaFit = (isset($row['media_fit']) && in_array($row['media_fit'], $allowedMFit, true)) ? $row['media_fit'] : 'cover';
                $insertBlock->bind_param('isssssiiiissss', $targetUserId, $bType, $title, $body, $mediaUrl, $mType, $featured, $visible, $i, $noCardStyle, $mediaPosition, $textAlign, $mediaAlign, $mediaFit);
                $insertBlock->execute();
            }
            $insertBlock->close();

            // 6. Embeds list restoration
            $mysqli->query("DELETE FROM utenti_embeds WHERE utente_id = " . $targetUserId);
            $embedRows = json_decode($presetData['embeds_json'] ?? '[]', true) ?: [];
            $insertEmbed = $mysqli->prepare("INSERT INTO utenti_embeds (utente_id, type, title, url, sort_order, is_visible) VALUES (?, ?, ?, ?, ?, ?)");
            $allowedETypes = ['spotify', 'youtube', 'custom'];
            foreach ($embedRows as $i => $row) {
                $eType = profile_allowed_value((string)($row['type'] ?? 'spotify'), $allowedETypes, 'spotify');
                $title = profile_clean_text($row['title'] ?? '', 100);
                $titleDb = $title !== '' ? $title : null;
                $url = trim((string)($row['url'] ?? ''));
                $visible = !empty($row['is_visible']) ? 1 : 0;
                if ($url === '') continue;
                $insertEmbed->bind_param('isssii', $targetUserId, $eType, $titleDb, $url, $i, $visible);
                $insertEmbed->execute();
            }
            $insertEmbed->close();

            // 7. Badges list restoration
            $mysqli->query("DELETE FROM utenti_profile_badges WHERE utente_id = " . $targetUserId);
            $mysqli->query("UPDATE user_custom_badges SET is_visible = 0, sort_order = 999 WHERE utente_id = " . $targetUserId);
            $badgeRows = json_decode($presetData['badges_json'] ?? '[]', true) ?: [];
            
            $insertAchievement = $mysqli->prepare("
                INSERT INTO utenti_profile_badges (utente_id, achievement_id, sort_order, is_visible)
                SELECT ?, ua.achievement_id, ?, 1
                FROM utenti_achievement ua
                WHERE ua.utente_id = ? AND ua.achievement_id = ?
                LIMIT 1
            ");
            
            $updateCustom = $mysqli->prepare("
                UPDATE user_custom_badges
                SET is_visible = 1, sort_order = ?
                WHERE utente_id = ? AND badge_id = ?
            ");
            
            foreach ($badgeRows as $i => $badgeCompoundId) {
                $badgeCompoundId = trim((string)$badgeCompoundId);
                if ($badgeCompoundId === '') continue;
                
                if (strpos($badgeCompoundId, 'custom_') === 0) {
                    $badgeId = (int)substr($badgeCompoundId, 7);
                    if ($badgeId > 0 && $updateCustom) {
                        $updateCustom->bind_param('iii', $i, $targetUserId, $badgeId);
                        $updateCustom->execute();
                    }
                } else {
                    $badgeId = $badgeCompoundId;
                    if (strpos($badgeCompoundId, 'achievement_') === 0) {
                        $badgeId = substr($badgeCompoundId, 12);
                    }
                    $badgeId = (int)$badgeId;
                    if ($badgeId > 0 && $insertAchievement) {
                        $insertAchievement->bind_param('iiii', $targetUserId, $i, $targetUserId, $badgeId);
                        $insertAchievement->execute();
                    }
                }
            }
            if ($insertAchievement) $insertAchievement->close();
            if ($updateCustom) $updateCustom->close();

            // 8. Characters list restoration
            $mysqli->query("DELETE FROM utenti_profile_characters WHERE utente_id = " . $targetUserId);
            $charRows = json_decode($presetData['characters_json'] ?? '[]', true) ?: [];
            $insertCharacter = $mysqli->prepare("
                INSERT INTO utenti_profile_characters (utente_id, personaggio_id, sort_order, is_visible)
                SELECT ?, up.personaggio_id, ?, 1
                FROM utenti_personaggi up
                WHERE up.utente_id = ? AND up.personaggio_id = ?
                LIMIT 1
            ");
            foreach ($charRows as $i => $charId) {
                $charId = (int)$charId;
                if ($charId <= 0) continue;
                $insertCharacter->bind_param('iiii', $targetUserId, $i, $targetUserId, $charId);
                $insertCharacter->execute();
            }
            if ($insertCharacter) $insertCharacter->close();

            $mysqli->commit();
            echo json_encode(['ok' => true, 'message' => 'Preset caricato con successo!']);
        } catch (Exception $e) {
            $mysqli->rollback();
            echo json_encode(['ok' => false, 'message' => 'Errore nel caricamento delle relazioni: ' . $e->getMessage()]);
        }
        break;

    case 'duplicate':
        $presetId = (int)($_POST['preset_id'] ?? 0);
        if ($presetId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Preset ID non valido.']);
            exit;
        }

        // Count limit check (3 presets max)
        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM utenti_presets WHERE utente_id = ?");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $cntRow = $stmt->get_result()->fetch_assoc();
        $count = (int)($cntRow['count'] ?? 0);
        $stmt->close();

        $profile = profile_get_edit_profile($mysqli, $targetUserId);
        $isPremium = $profile && (int)($profile['is_premium'] ?? 0) === 1;

        if (!$isPremium) {
            echo json_encode(['ok' => false, 'message' => 'La duplicazione dei preset personalizzati richiede un account Premium.']);
            exit;
        }

        if ($count >= 10) {
            echo json_encode(['ok' => false, 'message' => 'Hai raggiunto il limite massimo di 10 preset.']);
            exit;
        }
        $stmt = $mysqli->prepare("SELECT nome, preset_data FROM utenti_presets WHERE id = ? AND (utente_id = ? OR utente_id = ?) LIMIT 1");
        $stmt->bind_param('iii', $presetId, $targetUserId, $currentUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            echo json_encode(['ok' => false, 'message' => 'Preset non trovato.']);
            exit;
        }

        $newName = $row['nome'] . ' (Copia)';
        $stmt = $mysqli->prepare("INSERT INTO utenti_presets (utente_id, nome, preset_data) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $targetUserId, $newName, $row['preset_data']);
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Preset duplicato con successo!']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Errore nella duplicazione del preset.']);
        }
        $stmt->close();
        break;

    case 'rename':
        $profile = profile_get_edit_profile($mysqli, $targetUserId);
        $isPremium = $profile && (int)($profile['is_premium'] ?? 0) === 1;
        if (!$isPremium) {
            echo json_encode(['ok' => false, 'message' => 'La rinomina dei preset personalizzati richiede un account Premium.']);
            exit;
        }

        $presetId = (int)($_POST['preset_id'] ?? 0);
        $newName = trim($_POST['preset_name'] ?? '');
        if ($presetId <= 0 || $newName === '') {
            echo json_encode(['ok' => false, 'message' => 'Parametri non validi.']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE utenti_presets SET nome = ? WHERE id = ? AND (utente_id = ? OR utente_id = ?)");
        $stmt->bind_param('siii', $newName, $presetId, $targetUserId, $currentUserId);
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Preset rinominato!']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Errore nella rinomina del preset.']);
        }
        $stmt->close();
        break;

    case 'delete':
        $profile = profile_get_edit_profile($mysqli, $targetUserId);
        $isPremium = $profile && (int)($profile['is_premium'] ?? 0) === 1;
        if (!$isPremium) {
            echo json_encode(['ok' => false, 'message' => 'L\'eliminazione dei preset personalizzati richiede un account Premium.']);
            exit;
        }

        $presetId = (int)($_POST['preset_id'] ?? 0);
        if ($presetId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Preset ID non valido.']);
            exit;
        }

        $stmt = $mysqli->prepare("DELETE FROM utenti_presets WHERE id = ? AND (utente_id = ? OR utente_id = ?)");
        $stmt->bind_param('iii', $presetId, $targetUserId, $currentUserId);
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Preset eliminato.']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Errore nell\'eliminazione del preset.']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['ok' => false, 'message' => 'Azione non supportata.']);
        break;
}
