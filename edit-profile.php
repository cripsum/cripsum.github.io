<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/profile_helpers.php';
require_once __DIR__ . '/includes/profile_v3_helpers.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'Per modificare il profilo devi essere loggato';
    header('Location: /it/accedi');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_GET['user_id']) && profile_is_staff() ? (int)$_GET['user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    http_response_code(403);
    exit('Accesso negato.');
}

$profile = profile_get_edit_profile($mysqli, $targetUserId);
if (!$profile) {
    http_response_code(404);
    exit('Profilo non trovato.');
}
$profile = profile_v3_apply_extras($profile, $mysqli);

$socials = profile_list_socials($mysqli, $targetUserId, false);
$links = profile_list_links($mysqli, $targetUserId, false);
$projects = profile_list_projects($mysqli, $targetUserId, false);
$contents = profile_list_contents($mysqli, $targetUserId, false);
$blocks = function_exists('profile_list_blocks') ? profile_list_blocks($mysqli, $targetUserId, false) : [];
$badges = profile_list_unlocked_badges($mysqli, $targetUserId);
$csrf = profile_csrf_token();
$profileFlashSuccess = $_SESSION['profile_flash_success'] ?? '';
$profileFlashError = $_SESSION['profile_flash_error'] ?? '';
unset($_SESSION['profile_flash_success'], $_SESSION['profile_flash_error']);
$accent = profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff');
$secondaryColor = profile_normalize_hex_color($profile['profile_secondary_color'] ?? $accent);
$cardColor = profile_optional_hex_color($profile['profile_card_color'] ?? '') ?: '';
$textColor = profile_optional_hex_color($profile['profile_text_color'] ?? '') ?: '';
$linkStyle = profile_allowed_value((string)($profile['profile_link_style'] ?? 'glass'), ['glass', 'solid', 'outline', 'neon'], 'glass');
$buttonShape = profile_allowed_value((string)($profile['profile_button_shape'] ?? 'pill'), ['pill', 'rounded', 'sharp'], 'pill');
$theme = profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
if ($theme === 'auto') $theme = 'dark';
$displayName = profile_display_name($profile);
$discordConnected = !empty($profile['discord_id']) && !empty($profile['discord_username']);
$discordAvatarUrl = $discordConnected ? profile_discord_avatar_url((string)$profile['discord_id'], $profile['discord_avatar'] ?? null, 128) : null;
$discordDisplayName = trim((string)($profile['discord_global_name'] ?? '')) ?: trim((string)($profile['discord_username'] ?? ''));
$connectDiscordUrl = '/auth/discord_connect.php' . (profile_is_staff() && $targetUserId !== $currentUserId ? '?target_user_id=' . (int)$targetUserId : '');
$backgroundUrl = !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] : '/vid/Shorekeeper Wallpaper 4K Loop.mp4';
$backgroundType = !empty($profile['profile_banner_type']) ? (string)$profile['profile_banner_type'] : 'video/mp4';
$backgroundIsVideo = str_starts_with($backgroundType, 'video/');
$backgroundIsImage = str_starts_with($backgroundType, 'image/');
$themePreset = profile_v3_allowed_preset((string)($profile['profile_theme_preset'] ?? 'cyber'));
$fontFamily = profile_v3_allowed_font((string)($profile['profile_font_family'] ?? 'inter'));
$canvasEffect = profile_v3_allowed_canvas((string)($profile['profile_canvas_effect'] ?? 'none'));
$canvasConfig = profile_v3_json_array($profile['profile_canvas_config'] ?? '', ['speed' => 1, 'density' => 55, 'color' => '#ffffff', 'opacity' => 0.55, 'fps' => 40]);
$backgroundConfig = profile_v3_json_array($profile['profile_background_config'] ?? '', ['colors' => ['#05070d', $accent, $secondaryColor], 'direction' => '135deg', 'animated' => 0, 'parallax' => 1, 'blur' => 0]);
$builder = profile_v3_get_builder($profile);
$completionPercent = profile_v3_completion_percent($profile, $links, $socials, $blocks);

function profile_json_script(string $id, array $data): void
{
    echo '<script type="application/json" id="' . profile_h($id) . '">';
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    echo '</script>';
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <title>Cripsum™ - Modifica profilo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/profile.css?v=3.2.0">
    <link rel="stylesheet" href="/assets/css/edit-profile.css?v=3.2.0">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js" defer></script>
    <script src="/assets/js/profile.js?v=3.2.0" defer></script>
    <script src="/assets/js/edit-profile.js?v=3.2.0" defer></script>
</head>

<body class="bio-v2-body profile-editor-shell" data-theme="<?php echo profile_h($theme); ?>" data-accent="<?php echo profile_h($accent); ?>" data-theme-preset="<?php echo profile_h($themePreset); ?>" data-profile-link-style="<?php echo profile_h($linkStyle); ?>" data-profile-button-shape="<?php echo profile_h($buttonShape); ?>" data-profile-url="https://cripsum.com/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>" style="--profile-ring: <?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>; --accent-2: <?php echo profile_h($secondaryColor); ?>; --profile-card-color: <?php echo profile_h($cardColor ?: 'var(--card)'); ?>; --profile-text-color: <?php echo profile_h($textColor ?: 'var(--text)'); ?>; --profile-font-family: <?php echo profile_h(profile_v3_font_stack($fontFamily)); ?>;">
    <?php
    if (file_exists(__DIR__ . '/includes/navbar-bio.php')) {
        include __DIR__ . '/includes/navbar-bio.php';
    } else {
        include __DIR__ . '/includes/navbar.php';
    }
    if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php';
    ?>

    <?php if ($discordConnected): ?>
        <form id="disconnectDiscordForm" method="post" action="/auth/discord_disconnect.php" class="profile-hidden-form">
            <input type="hidden" name="csrf_token" value="<?php echo profile_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo (int)$targetUserId; ?>">
        </form>
    <?php endif; ?>

    <div class="bio-background" aria-hidden="true">
        <?php if ($backgroundIsVideo): ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster="">
                <source src="<?php echo profile_h($backgroundUrl); ?>" type="<?php echo profile_h($backgroundType); ?>">
            </video>
        <?php elseif ($backgroundIsImage): ?>
            <img class="bio-background__media" src="<?php echo profile_h($backgroundUrl); ?>" alt="" loading="eager">
        <?php else: ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster="">
                <source src="/vid/Shorekeeper Wallpaper 4K Loop.mp4" type="video/mp4">
            </video>
        <?php endif; ?>
        <div class="bio-background__overlay"></div>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
        <div class="bio-grid-glow"></div>
    </div>

    <main class="profile-edit-layout">
        <?php if ($profileFlashSuccess || $profileFlashError): ?>
            <div class="bio-card profile-flash <?php echo $profileFlashError ? 'is-error' : 'is-success'; ?>">
                <i class="<?php echo $profileFlashError ? 'fas fa-triangle-exclamation' : 'fas fa-check'; ?>"></i>
                <span><?php echo profile_h($profileFlashError ?: $profileFlashSuccess); ?></span>
            </div>
        <?php endif; ?>

        <header class="bio-card profile-edit-hero js-reveal">
            <div>
                <span class="bio-pill">Profile editor</span>
                <h1>Modifica profilo</h1>
            </div>
            <div class="profile-edit-hero-actions">
                <a class="bio-button" href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>"><i class="fas fa-eye"></i>Vedi profilo</a>
                <button class="bio-button profile-preview-toggle" type="button" data-preview-toggle><i class="fas fa-mobile-screen"></i>Preview</button>
                <button class="bio-icon-button js-theme-toggle" type="button" aria-label="Cambia tema"><i class="fas fa-moon"></i></button>
            </div>
        </header>

        <form id="profileEditForm" class="profile-edit-grid" method="post" enctype="multipart/form-data" action="/api/update_profile.php">
            <input type="hidden" name="csrf_token" value="<?php echo profile_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo (int)$targetUserId; ?>">
            <input type="hidden" name="socials_json" id="socialsJson">
            <input type="hidden" name="links_json" id="linksJson">
            <input type="hidden" name="projects_json" id="projectsJson">
            <input type="hidden" name="contents_json" id="contentsJson">
            <input type="hidden" name="blocks_json" id="blocksJson">
            <input type="hidden" name="badges_json" id="badgesJson">
            <input type="hidden" name="profile_builder_json" id="profileBuilderJson">
            <input type="hidden" name="profile_canvas_config" id="profileCanvasConfigJson">
            <input type="hidden" name="profile_background_config" id="profileBackgroundConfigJson">
            <input type="hidden" name="profile_plugins_json" id="profilePluginsJson">

            <section class="bio-card profile-edit-panel js-reveal">
                <div class="profile-editor-tabs" role="tablist">
                    <button type="button" class="is-active" data-edit-tab="identity">Identità</button>
                    <button type="button" data-edit-tab="studio">Studio</button>
                    <button type="button" data-edit-tab="builder">Builder</button>
                    <button type="button" data-edit-tab="links">Link</button>
                    <button type="button" data-edit-tab="projects">Progetti</button>
                    <button type="button" data-edit-tab="content">Contenuti</button>
                    <button type="button" data-edit-tab="custom">Custom</button>
                    <button type="button" data-edit-tab="effects">Effetti</button>
                    <button type="button" data-edit-tab="badges">Badge</button>
                    <button type="button" data-edit-tab="analytics">Analytics</button>
                    <button type="button" data-edit-tab="visibility">Visibilità</button>
                </div>

                <div class="profile-edit-section is-active" data-edit-section="identity">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-id-card"></i> Identità</span>
                        </div>
                    </div>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Nome visualizzato</span><input type="text" name="display_name" id="displayNameInput" maxlength="40" value="<?php echo profile_h($profile['display_name'] ?? ''); ?>" placeholder="Es. Godo"></label>
                        <label class="profile-field"><span>Username</span><input type="text" name="username" id="usernameInput" maxlength="20" required value="<?php echo profile_h($profile['username']); ?>" placeholder="username"><small>3-20 caratteri. Lettere, numeri e underscore.</small></label>
                    </div>
                    <div class="profile-field-grid two profile-i18n-row">
                        <label class="profile-field"><span>Display name EN</span><input type="text" name="display_name_en" maxlength="40" value="<?php echo profile_h($profile['display_name_en'] ?? ''); ?>" placeholder="English display name"></label>
                        <label class="profile-field"><span>Lingua default</span><select name="profile_locale" id="localeInput"><?php foreach (['it' => 'Italiano', 'en' => 'English'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_locale'] ?? 'it') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                    </div>

                    <label class="profile-field"><span>Bio</span><textarea name="bio" id="bioInput" maxlength="280" rows="5" placeholder="Scrivi qualcosa di tuo..."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea><small><span id="bioCounter">0</span>/280</small></label>
                    <label class="profile-field profile-i18n-row"><span>Bio EN</span><textarea name="bio_en" maxlength="280" rows="4" placeholder="English bio..."><?php echo profile_h($profile['bio_en'] ?? ''); ?></textarea></label>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Stato breve</span><input type="text" name="profile_status" id="statusInput" maxlength="60" value="<?php echo profile_h($profile['profile_status'] ?? ''); ?>" placeholder="editing, gaming, busy..."><small>Appare vicino al nome se non sei online.</small></label>
                        <label class="profile-field profile-i18n-row"><span>Stato EN</span><input type="text" name="profile_status_en" maxlength="60" value="<?php echo profile_h($profile['profile_status_en'] ?? ''); ?>" placeholder="English status"></label>
                        <label class="profile-field"><span>Discord user ID</span><input type="text" name="discord_id" id="discordIdInput" maxlength="25" value="<?php echo profile_h($profile['discord_id'] ?? ''); ?>" placeholder="Es. 8239582304530540"><small>Serve solo per Lanyard/Rich Presence.</small></label>
                    </div>

                    <div class="profile-discord-connect-card">
                        <div class="profile-discord-connect-main">
                            <?php if ($discordConnected): ?>
                                <?php if ($discordAvatarUrl): ?><img src="<?php echo profile_h($discordAvatarUrl); ?>" alt="" loading="lazy"><?php else: ?><span class="profile-discord-avatar-fallback"><i class="fab fa-discord"></i></span><?php endif; ?>
                                <div>
                                    <strong><?php echo profile_h($discordDisplayName ?: $profile['discord_username']); ?></strong>
                                    <small>@<?php echo profile_h($profile['discord_username']); ?> · ID <?php echo profile_h($profile['discord_id']); ?></small>
                                </div>
                            <?php else: ?>
                                <span class="profile-discord-avatar-fallback"><i class="fab fa-discord"></i></span>
                                <div>
                                    <strong>Discord non collegato</strong>
                                    <small>Collega Discord per salvare ID, username e avatar.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-discord-actions">
                            <a class="bio-button bio-button--primary" href="<?php echo profile_h($connectDiscordUrl); ?>"><i class="fab fa-discord"></i><?php echo $discordConnected ? 'Ricollega' : 'Collega Discord'; ?></a>
                            <?php if ($discordConnected): ?>
                                <button class="bio-button profile-discord-disconnect" type="submit" form="disconnectDiscordForm"><i class="fas fa-link-slash"></i>Scollega</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-discord-note">
                        <i class="fas fa-circle-info"></i>
                        <span>Il login Discord salva solo ID, username e avatar. se vuoi abilitare la Rich Presence ti basta entrare nel <a href="https://discord.com/invite/lanyard" target="_blank" rel="noopener noreferrer">server discord Lanyard</a>.</span>
                    </div>

                    <div class="profile-field-grid two" style="margin-top: 2%;">
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_display_name" value="0"><input type="checkbox" name="discord_use_display_name" id="discordUseNameInput" value="1" <?php echo (int)($profile['discord_use_display_name'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fab fa-discord"></i>Usa nome Discord</span></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_avatar" value="0"><input type="checkbox" name="discord_use_avatar" id="discordUseAvatarInput" value="1" <?php echo (int)($profile['discord_use_avatar'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fab fa-discord"></i>Usa avatar Discord</span></label>
                    </div>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Avatar</span><input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif"><small>Max 2MB. JPG, PNG, WEBP o GIF.</small></label>
                        <label class="profile-field"><span>Sfondo profilo</span><input type="file" name="banner" id="bannerInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"><small>Max 12MB. Foto, GIF o video. Cambia lo sfondo della pagina.</small></label>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Accent principale</span><input type="color" name="accent_color" id="accentInput" value="<?php echo profile_h($accent); ?>"></label>
                        <label class="profile-field"><span>Accent secondario</span><input type="color" name="profile_secondary_color" id="secondaryColorInput" value="<?php echo profile_h($secondaryColor); ?>"></label>
                        <label class="profile-field"><span>Tema</span><select name="profile_theme" id="themeInput"><?php foreach (['dark' => 'Scuro', 'light' => 'Chiaro', 'auto' => 'Auto'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_theme'] ?? 'dark') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Layout</span><select name="profile_layout" id="layoutInput"><?php foreach (['standard' => 'Standard', 'compact' => 'Compatto', 'showcase' => 'Showcase'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_layout'] ?? 'standard') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Colore card</span><input type="color" name="profile_card_color" id="cardColorInput" value="<?php echo profile_h($cardColor ?: '#080c18'); ?>"><small>Lascia il default se vuoi il glass classico.</small></label>
                        <label class="profile-field"><span>Colore testo</span><input type="color" name="profile_text_color" id="textColorInput" value="<?php echo profile_h($textColor ?: ($theme === 'light' ? '#111827' : '#f7f8ff')); ?>"></label>
                        <label class="profile-field"><span>Stile link</span><select name="profile_link_style" id="linkStyleInput"><?php foreach (['glass' => 'Glass', 'solid' => 'Pieno', 'outline' => 'Outline', 'neon' => 'Neon'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $linkStyle === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Forma bottoni</span><select name="profile_button_shape" id="buttonShapeInput"><?php foreach (['pill' => 'Pill', 'rounded' => 'Rounded', 'sharp' => 'Squadrato'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $buttonShape === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                    </div>

                    <label class="profile-field"><span>Privacy profilo</span><select name="profile_visibility" id="visibilityInput"><?php foreach (['public' => 'Pubblico', 'logged_in' => 'Solo utenti loggati', 'private' => 'Privato'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_visibility'] ?? 'public') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>

                    <div class="bio-section-heading profile-mt">
                        <div><span><i class="fas fa-music"></i> Audio profilo</span>
                            <p>Carica un MP3 oppure usa un URL audio pubblico.</p>
                        </div>
                    </div>
                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Carica MP3</span><input type="file" name="profile_music_file" id="musicFileInput" accept="audio/mpeg,audio/mp3,.mp3"><small>Max 12MB. Se carichi un MP3, sostituisce l’URL.</small></label>
                        <label class="profile-field"><span>URL canzone</span><input type="url" name="profile_music_url" id="musicUrlInput" maxlength="255" value="<?php echo profile_h($profile['profile_music_url'] ?? ''); ?>" placeholder="https://.../audio.mp3"><small>Usalo solo se non carichi un file.</small></label>
                        <label class="profile-field"><span>Titolo canzone</span><input type="text" name="profile_music_title" id="musicTitleInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_title'] ?? ''); ?>" placeholder="Nome canzone"></label>
                        <label class="profile-field"><span>Artista / nota</span><input type="text" name="profile_music_artist" id="musicArtistInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_artist'] ?? ''); ?>" placeholder="Artista o fonte"></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_show_audio_player" value="0"><input type="checkbox" name="profile_show_audio_player" value="1" <?php echo (int)($profile['profile_show_audio_player'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-sliders"></i>Mostra player</span></label>
                        <?php if (!empty($profile['profile_music_mime'])): ?>
                            <label class="profile-toggle-card profile-inline-toggle"><input type="checkbox" name="remove_profile_music_upload" value="1"><span><i class="fas fa-trash"></i>Rimuovi MP3 caricato</span></label>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-edit-section" data-edit-section="studio">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-layer-group"></i> Studio creativo</span>
                            <p>Ingresso, tema, sfondo, canvas, font e comportamento mobile.</p>
                        </div>
                        <span class="profile-completion-pill"><i class="fas fa-bolt"></i><?php echo (int)$completionPercent; ?>%</span>
                    </div>

                    <div class="profile-onboarding-strip">
                        <span style="--p: <?php echo (int)$completionPercent; ?>%"></span>
                        <strong>Profile completion</strong>
                        <small>Più il profilo è completo, più la preview assomiglia al risultato finale.</small>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Theme preset</span><select name="profile_theme_preset" id="themePresetInput">
                                <?php foreach (['cyber' => 'Cyber', 'rose' => 'Rose', 'onyx' => 'Onyx', 'toxic' => 'Toxic', 'vaporwave' => 'Vaporwave', 'crimson' => 'Crimson', 'midnight' => 'Midnight', 'sakura' => 'Sakura'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $themePreset === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>Font</span><select name="profile_font_family" id="fontFamilyInput">
                                <?php foreach (['inter' => 'Inter', 'space-grotesk' => 'Space Grotesk', 'jetbrains' => 'JetBrains Mono', 'vcr' => 'VCR OSD', 'poppins' => 'Poppins', 'playfair' => 'Playfair Display', 'orbitron' => 'Orbitron', 'bebas' => 'Bebas Neue'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $fontFamily === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>Background mode</span><select name="profile_background_mode" id="backgroundModeInput">
                                <?php foreach (['upload' => 'Upload', 'image' => 'Image URL', 'video' => 'Video URL', 'youtube' => 'YouTube', 'gradient' => 'Gradient'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($profile['profile_background_mode'] ?? 'upload') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select></label>
                    </div>

                    <div class="profile-field-grid two">
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_enter_enabled" value="0"><input type="checkbox" name="profile_enter_enabled" id="enterEnabledInput" value="1" <?php echo (int)($profile['profile_enter_enabled'] ?? 0) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-door-open"></i>Click to enter</span></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_enter_remember" value="0"><input type="checkbox" name="profile_enter_remember" id="enterRememberInput" value="1" <?php echo (int)($profile['profile_enter_remember'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-memory"></i>Ricorda ingresso</span></label>
                        <label class="profile-field"><span>Testo ingresso</span><input type="text" name="profile_enter_text" maxlength="80" value="<?php echo profile_h($profile['profile_enter_text'] ?? ''); ?>" placeholder="Clicca per entrare"></label>
                        <label class="profile-field"><span>Testo ingresso EN</span><input type="text" name="profile_enter_text_en" maxlength="80" value="<?php echo profile_h($profile['profile_enter_text_en'] ?? ''); ?>" placeholder="Click to enter"></label>
                        <label class="profile-field"><span>Bottone ingresso</span><input type="text" name="profile_enter_button" maxlength="40" value="<?php echo profile_h($profile['profile_enter_button'] ?? ''); ?>" placeholder="Entra"></label>
                        <label class="profile-field"><span>Bottone ingresso EN</span><input type="text" name="profile_enter_button_en" maxlength="40" value="<?php echo profile_h($profile['profile_enter_button_en'] ?? ''); ?>" placeholder="Enter"></label>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>YouTube background</span><input type="url" name="profile_youtube_url" id="youtubeBackgroundInput" maxlength="255" value="<?php echo profile_h($profile['profile_youtube_url'] ?? ''); ?>" placeholder="https://youtube.com/watch?v=..."></label>
                        <label class="profile-field"><span>Fallback image</span><input type="url" name="profile_fallback_image_url" maxlength="255" value="<?php echo profile_h($profile['profile_fallback_image_url'] ?? ''); ?>" placeholder="https://.../poster.webp"></label>
                        <label class="profile-field"><span>Blur background</span><input type="range" min="0" max="20" step="1" data-bg-field="blur" value="<?php echo (int)($backgroundConfig['blur'] ?? 0); ?>"></label>
                        <label class="profile-field"><span>Gradient 1</span><input type="color" data-bg-color="0" value="<?php echo profile_h(profile_normalize_hex_color($backgroundConfig['colors'][0] ?? '#05070d')); ?>"></label>
                        <label class="profile-field"><span>Gradient 2</span><input type="color" data-bg-color="1" value="<?php echo profile_h(profile_normalize_hex_color($backgroundConfig['colors'][1] ?? $accent)); ?>"></label>
                        <label class="profile-field"><span>Gradient 3</span><input type="color" data-bg-color="2" value="<?php echo profile_h(profile_normalize_hex_color($backgroundConfig['colors'][2] ?? $secondaryColor)); ?>"></label>
                        <label class="profile-field"><span>Direzione</span><select data-bg-field="direction">
                                <?php foreach (['135deg', '90deg', '120deg', '160deg', '180deg', 'circle'] as $direction): ?><option value="<?php echo $direction; ?>" <?php echo ($backgroundConfig['direction'] ?? '135deg') === $direction ? 'selected' : ''; ?>><?php echo $direction; ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="checkbox" data-bg-field="animated" <?php echo !empty($backgroundConfig['animated']) ? 'checked' : ''; ?>><span><i class="fas fa-wave-square"></i>Gradient shift</span></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="checkbox" data-bg-field="parallax" <?php echo !empty($backgroundConfig['parallax']) ? 'checked' : ''; ?>><span><i class="fas fa-arrows-up-down-left-right"></i>Parallax</span></label>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Canvas effect</span><select name="profile_canvas_effect" id="canvasEffectInput">
                                <?php foreach (['none' => 'Nessuno', 'snow' => 'Snow', 'sparks' => 'Sparks', 'matrix' => 'Matrix rain', 'stars' => 'Stars', 'rain' => 'Rain', 'orbs' => 'Floating orbs', 'fireflies' => 'Fireflies', 'confetti' => 'Confetti', 'sakura' => 'Sakura petals', 'smoke' => 'Smoke'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $canvasEffect === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>Canvas color</span><input type="color" data-canvas-field="color" value="<?php echo profile_h(profile_normalize_hex_color($canvasConfig['color'] ?? '#ffffff')); ?>"></label>
                        <label class="profile-field"><span>Opacity</span><input type="range" min="0.05" max="1" step="0.05" data-canvas-field="opacity" value="<?php echo profile_h((string)($canvasConfig['opacity'] ?? 0.55)); ?>"></label>
                        <label class="profile-field"><span>Speed</span><input type="range" min="0.1" max="4" step="0.1" data-canvas-field="speed" value="<?php echo profile_h((string)($canvasConfig['speed'] ?? 1)); ?>"></label>
                        <label class="profile-field"><span>Density</span><input type="range" min="5" max="180" step="1" data-canvas-field="density" value="<?php echo (int)($canvasConfig['density'] ?? 55); ?>"></label>
                        <label class="profile-field"><span>FPS cap</span><input type="range" min="18" max="60" step="1" data-canvas-field="fps" value="<?php echo (int)($canvasConfig['fps'] ?? 40); ?>"></label>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Effetto avatar</span><select name="profile_avatar_effect" id="avatarEffectInput">
                                <?php foreach (['pfp-glow', 'pfp-float', 'pfp-neon-border', 'pfp-glitch', 'pfp-pulse-ring', 'pfp-spin', 'pfp-shake', 'pfp-pixelate', 'pfp-rgb-shift', 'pfp-holographic'] as $value): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_avatar_effect'] ?? 'pfp-glow') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>Forma avatar</span><select name="profile_avatar_shape" id="avatarShapeInput"><?php foreach (['circle' => 'Circle', 'squircle' => 'Squircle', 'hexagon' => 'Hexagon'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_avatar_shape'] ?? 'circle') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Frame URL</span><input type="url" name="profile_avatar_frame_url" maxlength="255" value="<?php echo profile_h($profile['profile_avatar_frame_url'] ?? ''); ?>" placeholder="https://.../frame.png"></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_noise_enabled" value="0"><input type="checkbox" name="profile_noise_enabled" value="1" <?php echo (int)($profile['profile_noise_enabled'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-grip"></i>Noise overlay</span></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_animations_enabled" value="0"><input type="checkbox" name="profile_animations_enabled" value="1" <?php echo (int)($profile['profile_animations_enabled'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-wand-magic-sparkles"></i>Animazioni</span></label>
                    </div>
                </div>

                <div class="profile-edit-section" data-edit-section="builder">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-cubes-stacked"></i> Page builder</span>
                            <p>Blocchi JSON riordinabili, salvati come preset modulare del profilo.</p>
                        </div>
                    </div>
                    <div class="profile-builder-toolbar">
                        <select id="builderTypeSelect" aria-label="Tipo blocco">
                            <?php foreach (['bio' => 'Bio', 'social' => 'Social', 'link' => 'Link', 'projects' => 'Progetti', 'gallery' => 'Gallery', 'video' => 'Video', 'audio' => 'Audio', 'spotify' => 'Spotify', 'youtube' => 'YouTube', 'twitch' => 'Twitch', 'github' => 'GitHub', 'countdown' => 'Countdown', 'quote' => 'Citazione', 'table' => 'Tabella', 'contact' => 'Contact Form', 'achievement' => 'Achievement', 'lootbox' => 'Lootbox', 'custom_html' => 'Custom HTML'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="bio-button bio-button--primary" id="addBuilderBlock"><i class="fas fa-plus"></i>Aggiungi blocco</button>
                        <button type="button" class="bio-button" id="exportBuilderPreset"><i class="fas fa-share-nodes"></i>Preset</button>
                    </div>
                    <div class="profile-builder-repeater" id="profileBuilderRepeater" aria-live="polite"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="links">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-link"></i> Social</span>
                            <p>Icone rapide sotto il profilo.</p>
                        </div><button type="button" class="bio-button" data-add-row="socials">+ Social</button>
                    </div>
                    <div class="profile-repeater" id="socialsRepeater"></div>

                    <div class="bio-section-heading profile-mt">
                        <div><span><i class="fas fa-star"></i> Link personalizzati</span>
                            <p>Card grandi in evidenza.</p>
                        </div><button type="button" class="bio-button" data-add-row="links">+ Link</button>
                    </div>
                    <div class="profile-repeater" id="linksRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="projects">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-cubes"></i> Progetti</span>
                        </div><button type="button" class="bio-button" data-add-row="projects">+ Progetto</button>
                    </div>
                    <div class="profile-repeater" id="projectsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="content">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-play-circle"></i> Contenuti</span>
                            <p>Edit, video, pagine e showcase.</p>
                        </div><button type="button" class="bio-button" data-add-row="contents">+ Contenuto</button>
                    </div>
                    <div class="profile-repeater" id="contentsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="custom">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-wand-magic-sparkles"></i> Blocchi custom</span>
                            <p>Testi, immagini, GIF o video.</p>
                        </div><button type="button" class="bio-button" data-add-row="blocks">+ Blocco</button>
                    </div>
                    <div class="profile-repeater" id="blocksRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="effects">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-sparkles"></i> Effetti</span>
                            <p>Effetti leggeri su pagina, mouse e foto profilo.</p>
                        </div>
                    </div>
                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Effetto pagina</span><select name="profile_effect" id="profileEffectInput">
                                <?php foreach (
                                    [
                                        'none' => 'Nessuno',
                                        'cursor_glow' => 'Mouse glow',
                                        'soft_particles' => 'Particelle soft',
                                        'scanlines' => 'Scanlines soft',
                                        'ambient' => 'Ambient glow',
                                        'aurora' => 'Aurora',
                                        'gradient_waves' => 'Onde gradient',
                                        'stars' => 'Stelle leggere',
                                        'spotlight' => 'Spotlight mouse',
                                        'digital_noise' => 'Digital noise',
                                        'glass_rain' => 'Glass rain'
                                    ] as $value => $label
                                ): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_effect'] ?? 'none') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>Effetto anello PFP</span><select name="avatar_ring_style" id="ringStyleInput">
                                <?php foreach (
                                    [
                                        'spin' => 'Rotazione',
                                        'pulse' => 'Pulse',
                                        'orbit' => 'Orbit',
                                        'glow' => 'Glow',
                                        'dual' => 'Doppio giro',
                                        'rainbow' => 'Arcobaleno',
                                        'halo' => 'Halo soft',
                                        'neon' => 'Neon',
                                        'spark' => 'Spark',
                                        'glitch' => 'Glitch leggero',
                                        'none' => 'Nessuno'
                                    ] as $value => $label
                                ): ?><option value="<?php echo $value; ?>" <?php echo ($profile['avatar_ring_style'] ?? 'spin') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>Colore anello PFP</span><input type="color" name="avatar_ring_color" id="ringColorInput" value="<?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>"></label>
                    </div>
                    <div class="profile-effect-hint">
                        <span><i class="fas fa-wand-magic-sparkles"></i> Gli effetti sono solo estetici e leggeri.</span>
                        <span><i class="fas fa-circle"></i> Il colore dell’anello ora viene applicato anche nel profilo pubblico.</span>
                    </div>
                    <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="avatar_ring_enabled" value="0"><input type="checkbox" name="avatar_ring_enabled" id="ringEnabledInput" value="1" <?php echo (int)($profile['avatar_ring_enabled'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-circle-notch"></i>Mostra anello intorno alla foto profilo</span></label>
                </div>

                <div class="profile-edit-section" data-edit-section="badges">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-trophy"></i> Badge visibili</span>
                            <p>Puoi mostrarne massimo 8.</p>
                        </div>
                    </div>
                    <?php if ($badges): ?>
                        <div class="profile-badge-picker" id="badgePicker">
                            <?php foreach ($badges as $badge): ?>
                                <label class="profile-badge-choice">
                                    <input type="checkbox" value="<?php echo (int)$badge['id']; ?>" <?php echo (int)$badge['selected'] === 1 ? 'checked' : ''; ?>>
                                    <?php if (!empty($badge['img_url'])): ?><img src="/img/<?php echo profile_h($badge['img_url']); ?>" alt=""><?php else: ?><span>✦</span><?php endif; ?>
                                    <strong><?php echo profile_h($badge['nome']); ?></strong>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bio-empty-state"><i class="fas fa-medal"></i><strong>Nessun badge sbloccato</strong>
                            <p>Quando sblocchi achievement, puoi mostrarli qui.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-edit-section" data-edit-section="analytics">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-chart-line"></i> Analytics leggere</span>
                            <p>Visite, click, referrer, device e completion senza librerie pesanti.</p>
                        </div>
                    </div>
                    <div class="profile-analytics-grid">
                        <article class="profile-analytics-card"><span>Completion</span><strong><?php echo (int)$completionPercent; ?>%</strong></article>
                        <article class="profile-analytics-card"><span>Views totali</span><strong><?php echo profile_h(profile_compact_number($profile['profile_views'] ?? 0)); ?></strong></article>
                        <article class="profile-analytics-card"><span>Link</span><strong><?php echo count($links); ?></strong></article>
                        <article class="profile-analytics-card"><span>Blocchi</span><strong><?php echo count($blocks) + count($builder['blocks'] ?? []); ?></strong></article>
                    </div>
                    <canvas class="profile-analytics-canvas" id="profileAnalyticsCanvas" width="860" height="260" aria-label="Visite ultimi 30 giorni"></canvas>
                    <div class="profile-analytics-lists">
                        <div><strong>Top referrer</strong>
                            <ul id="profileTopReferrers"></ul>
                        </div>
                        <div><strong>Device</strong>
                            <ul id="profileTopDevices"></ul>
                        </div>
                    </div>
                </div>

                <div class="profile-edit-section" data-edit-section="visibility">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-eye"></i> Sezioni pubbliche</span>
                            <p>Spegni ciò che non vuoi mostrare. Se una sezione è vuota, resta nascosta comunque.</p>
                        </div>
                    </div>
                    <div class="profile-toggle-grid">
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_socials" value="0"><input type="checkbox" name="profile_show_socials" value="1" <?php echo (int)($profile['profile_show_socials'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fab fa-instagram"></i>Social</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_links" value="0"><input type="checkbox" name="profile_show_links" value="1" <?php echo (int)($profile['profile_show_links'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-link"></i>Link</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_projects" value="0"><input type="checkbox" name="profile_show_projects" value="1" <?php echo (int)($profile['profile_show_projects'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-cubes"></i>Progetti</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_contents" value="0"><input type="checkbox" name="profile_show_contents" value="1" <?php echo (int)($profile['profile_show_contents'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-play"></i>Edit e contenuti</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_badges" value="0"><input type="checkbox" name="profile_show_badges" value="1" <?php echo (int)($profile['profile_show_badges'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-trophy"></i>Badge</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_stats" value="0"><input type="checkbox" name="profile_show_stats" value="1" <?php echo (int)($profile['profile_show_stats'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-chart-simple"></i>Statistiche</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_activity" value="0"><input type="checkbox" name="profile_show_activity" value="1" <?php echo (int)($profile['profile_show_activity'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-clock"></i>Attività</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_discord" value="0"><input type="checkbox" name="profile_show_discord" value="1" <?php echo (int)($profile['profile_show_discord'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fab fa-discord"></i>Discord</span></label>
                    </div>
                </div>
                <div class="profile-editor-footer">
                    <button type="submit" class="bio-button bio-button--primary" id="saveProfileButton"><i class="fas fa-save"></i>Salva profilo</button>
                    <a class="bio-button" href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>">Annulla</a>
                </div>
            </section>

            <aside class="bio-hero bio-card profile-preview-card js-tilt-card js-reveal">
                <span class="bio-pill mb-2">Preview profilo</span>
                <!-- <div class="profile-background-note"><i class="fas fa-image"></i><span>Lo sfondo scelto appare dietro tutta la pagina, non sopra la foto profilo.</span></div> -->
                <div class="bio-avatar-wrap profile-preview-avatar-ring" id="previewAvatarWrap">
                    <div class="bio-avatar-ring" id="previewAvatarRing"></div><img class="bio-avatar" id="previewAvatar" src="<?php echo profile_h(profile_avatar_url($profile, 256)); ?>" alt="">
                </div>
                <div class="bio-name-block">
                    <h1 id="previewName"><?php echo profile_h($displayName); ?></h1>
                    <p class="bio-username" id="previewUsername">@<?php echo profile_h($profile['username']); ?></p>
                </div>
                <p class="bio-tagline" id="previewBio"><?php echo profile_h($profile['bio'] ?: 'La tua bio apparirà qui.'); ?></p>
                <div class="bio-badges"><span class="bio-badge" id="previewStatusBadge"><i class="fas fa-signal"></i>Stato</span><span class="bio-badge"><i class="fas fa-link"></i>Link</span><span class="bio-badge"><i class="fas fa-trophy"></i>Badge</span></div>
                <!-- <p class="bio-description" id="previewExtra">Audio, effetti e blocchi custom appaiono solo se li compili.</p> -->
            </aside>
        </form>
    </main>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <?php profile_json_script('initialSocialsData', $socials); ?>
    <?php profile_json_script('initialLinksData', $links); ?>
    <?php profile_json_script('initialProjectsData', $projects); ?>
    <?php profile_json_script('initialContentsData', $contents); ?>
    <?php profile_json_script('initialBlocksData', $blocks); ?>
    <?php profile_json_script('initialProfileV3Data', [
        'profile_id' => (int)$targetUserId,
        'csrf' => $csrf,
        'theme_preset' => $themePreset,
        'font_family' => $fontFamily,
        'canvas_config' => $canvasConfig,
        'background_config' => $backgroundConfig,
        'plugins' => profile_v3_json_array($profile['profile_plugins_json'] ?? '', []),
        'presets' => profile_v3_json_array($profile['profile_presets_json'] ?? '', []),
    ]); ?>
    <?php profile_json_script('initialBuilderData', $builder); ?>

    <?php if (file_exists(__DIR__ . '/includes/footer.php')) include __DIR__ . '/includes/footer.php'; ?>
</body>

</html>