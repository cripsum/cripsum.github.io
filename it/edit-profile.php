<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'Per modificare il profilo devi essere loggato';
    header('Location: accedi');
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
$nameStyle = [];
if ($profile && !empty($profile['profile_name_style'])) {
    $nameStyle = json_decode($profile['profile_name_style'], true);
}
if (!is_array($nameStyle)) {
    $nameStyle = [];
}
$nameType = $nameStyle['type'] ?? 'default';
$nameAnim = $nameStyle['animation'] ?? 'none';
$nameSolidColor = $nameStyle['solid_color'] ?? '#ffffff';
$nameGradColor1 = $nameStyle['grad_color1'] ?? '#ffffff';
$nameGradColor2 = $nameStyle['grad_color2'] ?? '#8b5cf6';
$nameGradAngle = $nameStyle['grad_angle'] ?? 90;
$nameGlowColor = $nameStyle['glow_color'] ?? '#8b5cf6';

$socials = profile_list_socials($mysqli, $targetUserId, false);
$links = profile_list_links($mysqli, $targetUserId, false);
$projects = profile_list_projects($mysqli, $targetUserId, false);
$contents = profile_list_contents($mysqli, $targetUserId, false);
$blocks = function_exists('profile_list_blocks') ? profile_list_blocks($mysqli, $targetUserId, false) : [];
$embeds = function_exists('profile_list_embeds') ? profile_list_embeds($mysqli, $targetUserId, false) : [];
$availableBadges = profile_list_all_user_badges($mysqli, $targetUserId);
$inventoryCharacters = profile_list_inventory_characters($mysqli, $targetUserId);
$csrf = profile_csrf_token();
$profileFlashSuccess = $_SESSION['profile_flash_success'] ?? '';
$profileFlashError = $_SESSION['profile_flash_error'] ?? '';
unset($_SESSION['profile_flash_success'], $_SESSION['profile_flash_error']);
$accent = profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff');
$secColorRaw = trim((string)($profile['profile_secondary_color'] ?? ''));
$secondaryColor = (preg_match('/^#[0-9a-fA-F]{6}$/', $secColorRaw)) ? strtolower($secColorRaw) : $accent;

$accentHex = ltrim($accent, '#');
if (strlen($accentHex) == 3) {
    $r = hexdec(substr($accentHex, 0, 1) . substr($accentHex, 0, 1));
    $g = hexdec(substr($accentHex, 1, 1) . substr($accentHex, 1, 1));
    $b = hexdec(substr($accentHex, 2, 1) . substr($accentHex, 2, 1));
} else {
    $r = hexdec(substr($accentHex, 0, 2));
    $g = hexdec(substr($accentHex, 2, 2));
    $b = hexdec(substr($accentHex, 4, 2));
}
$accentRgbComma = "$r, $g, $b";
$cardColor = profile_optional_hex_color($profile['profile_card_color'] ?? '') ?: '';
$textColor = profile_optional_hex_color($profile['profile_text_color'] ?? '') ?: '';
$linkStyle = profile_allowed_value((string)($profile['profile_link_style'] ?? 'glass'), ['glass', 'solid', 'outline', 'neon'], 'glass');
$buttonShape = profile_allowed_value((string)($profile['profile_button_shape'] ?? 'pill'), ['pill', 'rounded', 'sharp'], 'pill');
$theme = profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
if ($theme === 'auto') $theme = 'dark';
$avatarShape = profile_allowed_value((string)($profile['profile_avatar_shape'] ?? 'circle'), ['circle', 'squircle', 'square', 'hexagon', 'octagon', 'badge'], 'circle');
$avatarBorder = (int)($profile['profile_avatar_border'] ?? 1);
$cardColorCss = $cardColor ?: ($theme === 'light' ? '#ffffff' : '#080c18');
$textColorCss = $textColor ?: ($theme === 'light' ? '#111827' : '#f7f8ff');
$displayName = profile_display_name($profile);
$discordConnected = !empty($profile['discord_id']) && !empty($profile['discord_username']);
$discordAvatarUrl = $discordConnected ? profile_discord_avatar_url((string)$profile['discord_id'], $profile['discord_avatar'] ?? null, 128) : null;
$discordDisplayName = trim((string)($profile['discord_global_name'] ?? '')) ?: trim((string)($profile['discord_username'] ?? ''));
$connectDiscordUrl = '../auth/discord_connect.php' . (profile_is_staff() && $targetUserId !== $currentUserId ? '?target_user_id=' . (int)$targetUserId : '');
$backgroundUrl = !empty($profile['profile_banner_type']) ? '../includes/get_profile_banner.php?id=' . (int)$profile['id'] : '../vid/nga.mp4';
$backgroundType = !empty($profile['profile_banner_type']) ? (string)$profile['profile_banner_type'] : 'video/mp4';
$backgroundIsVideo = str_starts_with($backgroundType, 'video/');
$backgroundIsImage = str_starts_with($backgroundType, 'image/');

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
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <title>Cripsum™ - Modifica profilo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link class="profile-css-file" rel="stylesheet" href="/assets/css/profile.css?v=4.8.5">
    <link rel="stylesheet" href="/assets/css/editor-premium.css?v=4.8.5">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&family=Inter:wght@300..900&family=Roboto:wght@300..900&family=Outfit:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Space+Grotesk:wght@300..700&family=Syne:wght@400..800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Fira+Code:wght@300..700&family=PT+Mono&family=Cinzel:wght@400..900&family=Rubik:ital,wght@0,300..900;1,300..900&family=Bebas+Neue&family=Press+Start+2P&family=Bungee&family=Permanent+Marker&family=Creepster&family=Shojumaru&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="/assets/js/profile.js?v=4.8.5" defer></script>
    <script src="/assets/js/edit-profile.js?v=4.8.5" defer></script>
</head>

<body class="bio-v2-body profile-editor-shell" data-theme="<?php echo profile_h($theme); ?>" data-accent="<?php echo profile_h($accent); ?>" data-profile-link-style="<?php echo profile_h($linkStyle); ?>" data-profile-button-shape="<?php echo profile_h($buttonShape); ?>" data-profile-effect="<?php echo profile_h($profile['profile_effect'] ?? 'none'); ?>" data-profile-url="https://cripsum.com/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>" data-avatar-shape="<?php echo profile_h($avatarShape); ?>" data-avatar-border="<?php echo $avatarBorder; ?>" style="--accent: <?php echo profile_h($accent); ?>; --accent-rgb: <?php echo $accentRgbComma; ?>; --profile-ring: <?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>; --accent-2: <?php echo profile_h($secondaryColor); ?>; --profile-card-color: <?php echo profile_h($cardColorCss); ?>; --profile-text-color: <?php echo profile_h($textColorCss); ?>;">
    <?php
    $isPublicProfilePage = false;
    if (file_exists(__DIR__ . '/../includes/navbar-bio.php')) {
        include __DIR__ . '/../includes/navbar-bio.php';
    } else {
        include __DIR__ . '/../includes/navbar.php';
    }
    if (file_exists(__DIR__ . '/../includes/impostazioni.php')) include __DIR__ . '/../includes/impostazioni.php';
    ?>

    <?php if ($discordConnected): ?>
        <form id="disconnectDiscordForm" method="post" action="../auth/discord_disconnect.php" class="profile-hidden-form">
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
                <source src="/vid/nga.mp4" type="video/mp4">
            </video>
        <?php endif; ?>
        <div class="bio-background__overlay"></div>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
        <div class="bio-grid-glow"></div>
    </div>
    <div class="profile-effects-layer" aria-hidden="true"></div>
    <main class="builder-shell-layout">
        <!-- Floating Mobile Preview Toggle -->
        <button type="button" class="btn-floating-preview" id="floatingPreviewBtn" aria-label="Anteprima">
            <i class="fas fa-eye"></i>
        </button>

        <form id="profileEditForm" class="builder-grid-container" method="post" enctype="multipart/form-data" action="../api/update_profile.php">
            <!-- Left Sidebar -->
            <div class="editor-sidebar">
                <div class="editor-sidebar-header">
                    <div class="editor-sidebar-brand">
                        <div class="editor-brand-title">
                            <h2>Profile Editor</h2>
                        </div>
                        <div class="editor-header-actions">
                            <button type="button" class="editor-btn editor-btn-icon" id="undoBtn" disabled title="Annulla (Ctrl+Z)"><i class="fas fa-undo"></i></button>
                            <button type="button" class="editor-btn editor-btn-icon" id="redoBtn" disabled title="Ripristina (Ctrl+Y)"><i class="fas fa-redo"></i></button>
                            <button type="submit" name="salva" class="editor-btn editor-btn-primary" id="saveBtn"><i class="fas fa-save"></i> Salva</button>
                        </div>
                    </div>
                    
                    <div class="editor-controls-row">
                        <div class="editor-search-wrapper">
                            <i class="fas fa-search editor-search-icon"></i>
                            <input type="text" class="editor-search-input" id="editorSearch" placeholder="Cerca impostazioni (es. avatar, colori...)...">
                            <button type="button" class="editor-search-clear" id="editorSearchClear" style="display: none;"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>

                <div class="editor-sidebar-scroll">
                    <?php if ($profileFlashSuccess || $profileFlashError): ?>
                        <div class="bio-card profile-flash <?php echo $profileFlashError ? 'is-error' : 'is-success'; ?>" style="margin-bottom: 1rem; display: flex !important;">
                            <i class="<?php echo $profileFlashError ? 'fas fa-triangle-exclamation' : 'fas fa-check'; ?>"></i>
                            <span><?php echo profile_h($profileFlashError ?: $profileFlashSuccess); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Section 1: Identità -->
                    <div class="profile-edit-section editor-card" data-edit-section="identity">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-id-card"></i></span>
                                <div class="editor-card-text">
                                    <h3>Identità</h3>
                                    <p>Nome visualizzato, bio, avatar, musica e tag</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Configurato</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Nome visualizzato</span><input type="text" name="display_name" id="displayNameInput" maxlength="40" value="<?php echo profile_h($profile['display_name'] ?? ''); ?>" placeholder="Es. Godo"></label>
                                <label class="profile-field"><span>Username</span><input type="text" name="username" id="usernameInput" maxlength="20" required value="<?php echo profile_h($profile['username']); ?>" placeholder="username"><small>3-20 caratteri. Lettere, numeri e underscore.</small></label>
                            </div>

                            <label class="profile-field"><span>Alias URL Personalizzato (cripsum.com/tuoalias)</span>
                                <div style="position: relative;">
                                    <input type="text" name="custom_alias" id="customAliasInput" maxlength="30" value="<?php echo profile_h($profile['custom_alias'] ?? ''); ?>" placeholder="aliascustom" style="padding-right: 40px;">
                                    <span id="aliasValidationIcon" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 1.1rem; pointer-events: none;"></span>
                                </div>
                                <small id="aliasValidationMessage" style="transition: color 0.2s;">Lascia vuoto per disattivare. Permette di accedere al tuo profilo tramite cripsum.com/tuoalias</small>
                            </label>

                            <label class="profile-field"><span>Bio</span><textarea name="bio" id="bioInput" maxlength="280" rows="5" placeholder="Scrivi qualcosa di tuo..."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea><small><span id="bioCounter">0</span>/280</small></label>

                            <label class="profile-field"><span>Stato breve</span><input type="text" name="profile_status" id="statusInput" maxlength="60" value="<?php echo profile_h($profile['profile_status'] ?? ''); ?>" placeholder="editing, gaming, busy..."><small>Appare vicino al nome se non sei online.</small></label>

                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Avatar</span><input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif"><small>Max 2MB. JPG, PNG, WEBP o GIF.</small></label>
                                <label class="profile-field"><span>Sfondo profilo</span><input type="file" name="banner" id="bannerInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"><small>Max 12MB. Foto, GIF o video. Cambia lo sfondo della pagina.</small></label>
                            </div>

                            <label class="profile-field"><span>Privacy profilo</span><select name="profile_visibility" id="visibilityInput"><?php foreach (['public' => 'Pubblico', 'logged_in' => 'Solo utenti loggati', 'private' => 'Privato'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_visibility'] ?? 'public') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>

                            <div class="bio-section-heading profile-mt" style="border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <div><span><i class="fas fa-tags"></i> Tag / Pills Personalizzate</span>
                                    <p>Aggiungi pillole colorate sotto la tua biografia (max 10).</p>
                                </div>
                                <button type="button" class="bio-button" data-add-row="tags">+ Aggiungi Tag</button>
                            </div>
                            <div class="profile-repeater" id="tagsRepeater"></div>

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
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_show_audio_player" value="0"><input type="checkbox" name="profile_show_audio_player" value="1" <?php echo (int)($profile['profile_show_audio_player'] ?? 1) === 1 ? 'checked' : ''; ?> id="showAudioPlayerInput"><span><i class="fas fa-sliders"></i>Mostra player</span></label>
                                <input type="hidden" id="hasServerMusic" value="<?php echo (!empty($profile['profile_music_mime']) || !empty(trim((string)($profile['profile_music_url'] ?? '')))) ? '1' : '0'; ?>">
                                <?php if (!empty($profile['profile_music_mime'])): ?>
                                    <label class="profile-toggle-card profile-inline-toggle"><input type="checkbox" name="remove_profile_music_upload" value="1"><span><i class="fas fa-trash"></i>Rimuovi MP3 caricato</span></label>
                                <?php endif; ?>
                            </div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fas fa-door-open"></i> Click to Enter (Schermata d'ingresso)</span>
                                    <p>Mostra una schermata introduttiva d'ingresso. Utile per far partire l'audio in automatico.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_click_to_enter" value="0"><input type="checkbox" name="profile_click_to_enter" value="1" <?php echo (int)($profile['profile_click_to_enter'] ?? 0) === 1 ? 'checked' : ''; ?> id="clickToEnterInput"><span><i class="fas fa-hand-pointer"></i>Abilita Click to Enter</span></label>
                                <label class="profile-field"><span>Testo bottone d'ingresso</span><input type="text" name="profile_enter_text" id="enterTextInput" maxlength="80" value="<?php echo profile_h($profile['profile_enter_text'] ?? ''); ?>" placeholder="Es. Click to Enter / Entra"></label>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Stile e Colori -->
                    <div class="profile-edit-section editor-card" data-edit-section="design">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-palette"></i></span>
                                <div class="editor-card-text">
                                    <h3>Stile e Colori</h3>
                                    <p>Temi premium, palette, layout, forme, bordi e opacità</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Personalizzato</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <!-- Premium Themes presets gallery -->
                            <div class="profile-presets-block" style="margin-bottom: 1.5rem;">
                                <span><i class="fas fa-magic"></i> Temi Premium (Un-click per applicare)</span>
                                <div class="theme-presets-gallery">
                                    <div class="theme-preset-card" data-theme-preset="cyberpunk">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff007f;"></div>
                                            <div class="theme-preview-color" style="background: #7f00ff;"></div>
                                            <div class="theme-preview-color" style="background: #0a0512;"></div>
                                        </div>
                                        <span class="theme-preset-name">Cyberpunk</span>
                                        <span class="theme-preset-desc">Neon cyberpunk glow cibernetico</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="rgb">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff0000;"></div>
                                            <div class="theme-preview-color" style="background: #00ff00;"></div>
                                            <div class="theme-preview-color" style="background: #080808;"></div>
                                        </div>
                                        <span class="theme-preset-name">RGB Gamer</span>
                                        <span class="theme-preset-desc">Colori dinamici e stravaganti</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="glass">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: rgba(255,255,255,0.2);"></div>
                                            <div class="theme-preview-color" style="background: rgba(255,255,255,0.15);"></div>
                                            <div class="theme-preview-color" style="background: rgba(255,255,255,0.05);"></div>
                                        </div>
                                        <span class="theme-preset-name">Glassmorphism</span>
                                        <span class="theme-preset-desc">Effetto vetro trasparente e blur</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="sakura">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff758c;"></div>
                                            <div class="theme-preview-color" style="background: #ff7eb3;"></div>
                                            <div class="theme-preview-color" style="background: #1f1015;"></div>
                                        </div>
                                        <span class="theme-preset-name">Sakura</span>
                                        <span class="theme-preset-desc">Ciliegio in fiore e toni rosa</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="anime">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff6b6b;"></div>
                                            <div class="theme-preview-color" style="background: #feca57;"></div>
                                            <div class="theme-preview-color" style="background: #1a0f0f;"></div>
                                        </div>
                                        <span class="theme-preset-name">Anime</span>
                                        <span class="theme-preset-desc">Colori caldi e stile orientale</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="neon">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #00f0ff;"></div>
                                            <div class="theme-preview-color" style="background: #ff007f;"></div>
                                            <div class="theme-preview-color" style="background: #03030d;"></div>
                                        </div>
                                        <span class="theme-preset-name">Neon Glow</span>
                                        <span class="theme-preset-desc">Bagliori di luce e contrasti scuri</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="discord">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #5865f2;"></div>
                                            <div class="theme-preview-color" style="background: #57f287;"></div>
                                            <div class="theme-preview-color" style="background: #2f3136;"></div>
                                        </div>
                                        <span class="theme-preset-name">Discord Style</span>
                                        <span class="theme-preset-desc">Design del client Discord</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="minimal">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #000000;"></div>
                                            <div class="theme-preview-color" style="background: #888888;"></div>
                                            <div class="theme-preview-color" style="background: #ffffff;"></div>
                                        </div>
                                        <span class="theme-preset-name">Minimal</span>
                                        <span class="theme-preset-desc">Pulito, elegante, bianco e nero</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="dark_premium">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #c9d9ff;"></div>
                                            <div class="theme-preview-color" style="background: #0f5bff;"></div>
                                            <div class="theme-preview-color" style="background: #030509;"></div>
                                        </div>
                                        <span class="theme-preset-name">Dark Premium</span>
                                        <span class="theme-preset-desc">Look moderno scuro per eccellenza</span>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-presets-block">
                                <span><i class="fas fa-palette"></i> Palette Colore Veloci</span>
                                <div class="profile-presets-grid">
                                    <button type="button" class="profile-preset-btn" data-accent="#0f5bff" data-secondary="#8b5cf6" data-card="#080c18" data-text="#f7f8ff" style="--btn-accent: #0f5bff; --btn-secondary: #8b5cf6;" title="Default Cripsum"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ff007f" data-secondary="#7f00ff" data-card="#0a0512" data-text="#ffebf5" style="--btn-accent: #ff007f; --btn-secondary: #7f00ff;" title="Cyberpunk"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ff6b6b" data-secondary="#feca57" data-card="#1a0f0f" data-text="#fff5f5" style="--btn-accent: #ff6b6b; --btn-secondary: #feca57;" title="Sunset Glow"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#10b981" data-secondary="#3b82f6" data-card="#040d1a" data-text="#ecfdf5" style="--btn-accent: #10b981; --btn-secondary: #3b82f6;" title="Emerald Ocean"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ffffff" data-secondary="#888888" data-card="#121212" data-text="#ffffff" style="--btn-accent: #ffffff; --btn-secondary: #888888;" title="Monochrome Clean"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ff758c" data-secondary="#ff7eb3" data-card="#1f1015" data-text="#fff0f5" style="--btn-accent: #ff758c; --btn-secondary: #ff7eb3;" title="Cherry Blossom"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#a855f7" data-secondary="#ec4899" data-card="#150b24" data-text="#faf5ff" style="--btn-accent: #a855f7; --btn-secondary: #ec4899;" title="Purple Orchid"></button>
                                </div>
                            </div>

                            <div class="profile-presets-block profile-mt">
                                <span><i class="fas fa-shapes"></i> UI Style Presets</span>
                                <div class="profile-presets-grid" style="grid-template-columns: repeat(3, 1fr);">
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="modern" style="padding: 8px; font-size: 0.8rem;">Modern</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="glass" style="padding: 8px; font-size: 0.8rem;">Glass</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="bubble" style="padding: 8px; font-size: 0.8rem;">Bubble</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="sharp" style="padding: 8px; font-size: 0.8rem;">Sharp</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="cyber" style="padding: 8px; font-size: 0.8rem;">Cyber</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="minimal" style="padding: 8px; font-size: 0.8rem;">Minimal</button>
                                </div>
                            </div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fas fa-sliders"></i> Opzioni Layout e Colori</span></div>
                            </div>
                            <div class="profile-field-grid three">
                                <label class="profile-field"><span>Accent principale</span><input type="color" name="accent_color" id="accentInput" value="<?php echo profile_h($accent); ?>"></label>
                                <label class="profile-field"><span>Accent secondario</span><input type="color" name="profile_secondary_color" id="secondaryColorInput" value="<?php echo profile_h($secondaryColor); ?>"></label>
                                <label class="profile-field"><span>Tema</span><select name="profile_theme" id="themeInput"><?php foreach (['dark' => 'Scuro', 'light' => 'Chiaro', 'auto' => 'Auto'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_theme'] ?? 'dark') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                                <label class="profile-field"><span>Layout</span><select name="profile_layout" id="layoutInput"><?php foreach (['standard' => 'Standard', 'compact' => 'Compatto', 'showcase' => 'Showcase', 'clean' => 'Clean'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_layout'] ?? 'standard') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                                <label class="profile-field"><span>Colore card</span><input type="color" name="profile_card_color" id="cardColorInput" value="<?php echo profile_h($cardColor ?: '#080c18'); ?>"><small>Lascia default per vetro trasparente.</small></label>
                                <label class="profile-field"><span>Colore testo</span><input type="color" name="profile_text_color" id="textColorInput" value="<?php echo profile_h($textColor ?: ($theme === 'light' ? '#111827' : '#f7f8ff')); ?>"></label>
                                <label class="profile-field"><span>Stile link</span><select name="profile_link_style" id="linkStyleInput"><?php foreach (['glass' => 'Glass', 'solid' => 'Pieno', 'outline' => 'Outline', 'neon' => 'Neon'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $linkStyle === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                                <label class="profile-field"><span>Forma bottoni</span><select name="profile_button_shape" id="buttonShapeInput"><?php foreach (['pill' => 'Pill', 'rounded' => 'Rounded', 'sharp' => 'Squadrato'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $buttonShape === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                                <label class="profile-field"><span>Stile Social</span><select name="profile_socials_style" id="socialsStyleInput"><?php foreach (['cards' => 'Card grandi (2x riga)', 'icons' => 'Solo icone clean'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_socials_style'] ?? 'cards') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                            </div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fas fa-wand-magic-sparkles"></i> Forme, Bordi e Trasparenze</span></div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Forma Globale UI</span><select name="profile_ui_shape" id="uiShapeInput">
                                        <?php foreach (['circle' => 'Circle (100%)', 'rounded' => 'Rounded (24px)', 'soft' => 'Soft Rounded (16px)', 'square-rounded' => 'Square Rounded (8px)', 'square' => 'Square (0px)', 'pill' => 'Pill (999px)'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_ui_shape'] ?? 'circle') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                <label class="profile-field"><span>Forma Avatar PFP</span><select name="profile_avatar_shape" id="avatarShapeInput">
                                        <?php foreach (['circle' => 'Cerchio', 'squircle' => 'Squircle', 'square' => 'Quadrato', 'hexagon' => 'Esagono', 'octagon' => 'Ottagono', 'badge' => 'Gaming Badge (Scudo)'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_avatar_shape'] ?? 'circle') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>

                                <label class="profile-field"><span>Stile Angoli Componenti</span><select name="profile_corner_style" id="cornerStyleInput">
                                        <?php foreach (['circle' => 'Cerchio (Rotondo 100px)', 'rounded' => 'Arrotondato classico', 'soft' => 'Leggermente arrotondato', 'square' => 'Squadrato', 'custom' => 'Personalizzato (px)'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_corner_style'] ?? 'circle') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                <label class="profile-field" id="cornerStyleCustomContainer" style="display: <?php echo ($profile['profile_corner_style'] ?? 'circle') === 'custom' ? 'block' : 'none'; ?>;"><span>Arrotondamento Personalizzato (<span id="cornerStyleCustomVal"><?php echo (int)($profile['profile_corner_style_custom'] ?? 8); ?></span>px)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_corner_style_custom" id="cornerStyleCustomInput" min="0" max="100" value="<?php echo (int)($profile['profile_corner_style_custom'] ?? 8); ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Stile Bordi Card e Pulsanti</span><select name="profile_border_style" id="borderStyleInput">
                                        <?php foreach (['none' => 'Nessun Bordo', 'thin' => 'Bordo Sottile', 'glow' => 'Bordo Glow (Bagliore)', 'gradient' => 'Bordo Gradiente (Premium)'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_border_style'] ?? 'thin') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>

                                <label class="profile-field"><span>Dimensione Icone Social</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_social_size" id="socialSizeInput" min="32" max="72" value="<?php echo (int)($profile['profile_social_size'] ?? 42); ?>" style="flex: 1;">
                                        <span id="socialSizeVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_social_size'] ?? 42); ?>px</span>
                                    </div>
                                </label>
                                <label class="profile-field"><span>Spaziatura Icone Social</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_icon_spacing" id="iconSpacingInput" min="0" max="24" value="<?php echo (int)($profile['profile_icon_spacing'] ?? 8); ?>" style="flex: 1;">
                                        <span id="iconSpacingVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_icon_spacing'] ?? 8); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Dimensione Badge</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_badge_size" id="badgeSizeInput" min="16" max="60" value="<?php echo (int)($profile['profile_badge_size'] ?? 24); ?>" style="flex: 1;">
                                        <span id="badgeSizeVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_badge_size'] ?? 24); ?>px</span>
                                    </div>
                                </label>
                                <label class="profile-field"><span>Altezza Pulsanti (Media/Link)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_button_size" id="buttonSizeInput" min="32" max="80" value="<?php echo (int)($profile['profile_button_size'] ?? 48); ?>" style="flex: 1;">
                                        <span id="buttonSizeVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_button_size'] ?? 48); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Font profilo</span><select name="profile_font" id="fontInput">
                                        <?php
                                        $fonts = [
                                            'Poppins' => 'Poppins (Default)',
                                            'Inter' => 'Inter',
                                            'Roboto' => 'Roboto',
                                            'Outfit' => 'Outfit',
                                            'Playfair Display' => 'Playfair Display',
                                            'Space Grotesk' => 'Space Grotesk',
                                            'Syne' => 'Syne',
                                            'Montserrat' => 'Montserrat',
                                            'Fira Code' => 'Fira Code (Monospace)',
                                            'PT Mono' => 'PT Mono',
                                            'Cinzel' => 'Cinzel (Serif Elegant)',
                                            'Rubik' => 'Rubik',
                                            'Bebas Neue' => 'Bebas Neue',
                                            'Minecraft' => 'Minecraft (Gaming)',
                                            'Gang of Three' => 'Gang of Three (Kung-Fu Brush)',
                                            'Press Start 2P' => 'Press Start 2P (Retro Retro)',
                                            'Bungee' => 'Bungee (Arcade Heavy)',
                                            'Permanent Marker' => 'Permanent Marker (Graffiti)',
                                            'Creepster' => 'Creepster (Horror)',
                                            'Shojumaru' => 'Shojumaru (Asian Style)'
                                        ];
                                        foreach ($fonts as $fontVal => $fontLabel): ?>
                                            <option value="<?php echo $fontVal; ?>" <?php echo ($profile['profile_font'] ?? 'Poppins') === $fontVal ? 'selected' : ''; ?>><?php echo $fontLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>

                                <label class="profile-field"><span>Opacità card</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_card_opacity" id="cardOpacityInput" min="0" max="100" value="<?php echo (int)($profile['profile_card_opacity'] ?? 68); ?>" style="flex: 1;">
                                        <span id="cardOpacityVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_card_opacity'] ?? 68); ?>%</span>
                                    </div>
                                    <small>0% per farsi il profilo full trasparente.</small>
                                </label>

                                <label class="profile-field"><span>Sfocatura card (Blur)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_card_blur" id="cardBlurInput" min="0" max="40" value="<?php echo (int)($profile['profile_card_blur'] ?? 20); ?>" style="flex: 1;">
                                        <span id="cardBlurVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_card_blur'] ?? 20); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Opacità bordo card</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_border_opacity" id="borderOpacityInput" min="0" max="100" value="<?php echo (int)($profile['profile_border_opacity'] ?? 100); ?>" style="flex: 1;">
                                        <span id="borderOpacityVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_border_opacity'] ?? 100); ?>%</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Raggio angoli card</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_border_radius" id="borderRadiusInput" min="0" max="40" value="<?php echo (int)($profile['profile_border_radius'] ?? 30); ?>" style="flex: 1;">
                                        <span id="borderRadiusVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_border_radius'] ?? 30); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Spessore bordo card</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_border_width" id="borderWidthInput" min="0" max="5" value="<?php echo (int)($profile['profile_border_width'] ?? 1); ?>" style="flex: 1;">
                                        <span id="borderWidthVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_border_width'] ?? 1); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Colore bordo card</span>
                                    <input type="color" name="profile_border_color" id="borderColorInput" value="<?php echo profile_h($profile['profile_border_color'] ?? '#ffffff'); ?>">
                                    <small>Ignorato se lo spessore del bordo è 0.</small>
                                </label>
                            </div>
                            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <button type="button" id="resetDesignBtn" class="bio-button" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.25); display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.9rem;">
                                    <i class="fas fa-undo"></i> Ripristina valori di default
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Discord -->
                    <div class="profile-edit-section editor-card" data-edit-section="discord">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fab fa-discord"></i></span>
                                <div class="editor-card-text">
                                    <h3>Discord</h3>
                                    <p>Account Discord, Lanyard ID e Widget server</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge <?php echo $discordConnected ? 'is-active' : ''; ?>"><?php echo $discordConnected ? 'Collegato' : 'Scollegato'; ?></span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
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

                            <div class="profile-field-grid two" style="margin-top: 2%;">
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_display_name" value="0"><input type="checkbox" name="discord_use_display_name" id="discordUseNameInput" value="1" <?php echo (int)($profile['discord_use_display_name'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fab fa-discord"></i>Usa nome Discord</span></label>
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_avatar" value="0"><input type="checkbox" name="discord_use_avatar" id="discordUseAvatarInput" value="1" <?php echo (int)($profile['discord_use_avatar'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fab fa-discord"></i>Usa avatar Discord</span></label>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 1.5rem;">
                                <div><span><i class="fas fa-signal"></i> Lanyard & Rich Presence</span>
                                    <p>Mostra il tuo stato e la tua attività in tempo reale (giochi, Spotify, ecc.).</p>
                                </div>
                            </div>

                            <label class="profile-field">
                                <span>Discord user ID</span>
                                <input type="text" name="discord_id" id="discordIdInput" maxlength="25" value="<?php echo profile_h($profile['discord_id'] ?? ''); ?>" placeholder="Es. 8239582304530540">
                                <small>Necessario per caricare la tua attività e il tuo stato Lanyard.</small>
                            </label>

                            <div class="profile-discord-note" style="margin-bottom: 2rem;">
                                <i class="fas fa-info-circle"></i>
                                <span>Il login Discord salva solo ID, username e avatar. Se vuoi abilitare la Rich Presence ti basta entrare nel <a href="https://discord.com/invite/lanyard" target="_blank" rel="noopener noreferrer">server discord Lanyard</a>.</span>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 1.5rem;">
                                <div><span><i class="fab fa-discord"></i> Server Discord</span>
                                    <p>Mostra un widget del tuo server Discord sul tuo profilo pubblico.</p>
                                </div>
                            </div>

                            <label class="profile-field">
                                <span>Link d'invito del server Discord</span>
                                <input type="text" name="discord_server_invite" id="discordServerInviteInput" value="<?php echo profile_h($profile['discord_server_invite'] ?? ''); ?>" placeholder="https://discord.gg/invito o codice d'invito">
                                    <small>Inserisci il link d'invito (es. <code>https://discord.gg/tuoserver</code>) per mostrare il widget del server.</small>
                            </label>
                        </div>
                    </div>

                    <!-- Section 4: Social e Link -->
                    <div class="profile-edit-section editor-card" data-edit-section="links">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-link"></i></span>
                                <div class="editor-card-text">
                                    <h3>Social e Link</h3>
                                    <p>Profili social (icone) e bottoni custom grandi</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Link</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
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
                    </div>

                    <!-- Section 5: Embed -->
                    <div class="profile-edit-section editor-card" data-edit-section="embeds">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-share-square"></i></span>
                                <div class="editor-card-text">
                                    <h3>Embed</h3>
                                    <p>Playlist Spotify, video YouTube o widget esterni</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Media</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-share-square"></i> Embed</span>
                                    <p>Inserisci playlist di Spotify o video di YouTube.</p>
                                </div><button type="button" class="bio-button" data-add-row="embeds">+ Embed</button>
                            </div>
                            <div class="profile-repeater" id="embedsRepeater"></div>
                        </div>
                    </div>

                    <!-- Section 6: Progetti -->
                    <div class="profile-edit-section editor-card" data-edit-section="projects">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-cubes"></i></span>
                                <div class="editor-card-text">
                                    <h3>Progetti</h3>
                                    <p>Vetrina dei tuoi progetti o siti web preferiti</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Vetrina</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-cubes"></i> Progetti</span></div>
                                <button type="button" class="bio-button" data-add-row="projects">+ Progetto</button>
                            </div>
                            <div class="profile-repeater" id="projectsRepeater"></div>
                        </div>
                    </div>

                    <!-- Section 7: Contenuti -->
                    <div class="profile-edit-section editor-card" data-edit-section="content">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-play-circle"></i></span>
                                <div class="editor-card-text">
                                    <h3>Contenuti</h3>
                                    <p>Edit, video e showcase multimediali</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Video</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-play-circle"></i> Contenuti</span>
                                    <p>Edit, video, pagine e showcase.</p>
                                </div><button type="button" class="bio-button" data-add-row="contents">+ Contenuto</button>
                            </div>
                            <div class="profile-repeater" id="contentsRepeater"></div>
                        </div>
                    </div>

                    <!-- Section 8: Blocchi custom -->
                    <div class="profile-edit-section editor-card" data-edit-section="custom">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-wand-magic-sparkles"></i></span>
                                <div class="editor-card-text">
                                    <h3>Blocchi Custom</h3>
                                    <p>Sezioni libere con testo, immagini, GIF o video</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">HTML/Testo</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-wand-magic-sparkles"></i> Blocchi custom</span>
                                    <p>Testi, immagini, GIF o video.</p>
                                </div><button type="button" class="bio-button" data-add-row="blocks">+ Blocco</button>
                            </div>
                            <div class="profile-repeater" id="blocksRepeater"></div>
                        </div>
                    </div>

                    <!-- Section 9: Effetti e Audio -->
                    <div class="profile-edit-section editor-card" data-edit-section="effects">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-magic"></i></span>
                                <div class="editor-card-text">
                                    <h3>Effetti e Personalizzazione Nome</h3>
                                    <p>Effetti pagina, anello avatar, colori nome, tilt e scheda browser</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Effetti</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-magic"></i> Effetti Pagina</span>
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
                                                'glass_rain' => 'Glass rain',
                                                'sakura_falling' => 'Petali di sakura',
                                                'cyber_grid' => 'Griglia cyber'
                                            ] as $value => $label
                                        ): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_effect'] ?? 'none') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                                    </select><small id="glassRainWarning" class="profile-effect-warning" style="display:<?php echo ($profile['profile_effect'] ?? 'none') === 'glass_rain' ? 'flex' : 'none'; ?>"><i class="fas fa-info-circle"></i> Glass rain supporta solo sfondi statici (immagini).</small></label>
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
                            <div class="profile-effect-hint" style="margin: 0.5rem 0 1rem 0;">
                                <span><i class="fas fa-info-circle"></i> Il colore dell’anello viene applicato nel profilo pubblico.</span>
                            </div>
                            <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="avatar_ring_enabled" value="0"><input type="checkbox" name="avatar_ring_enabled" id="ringEnabledInput" value="1" <?php echo (int)($profile['avatar_ring_enabled'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-circle-notch"></i>Mostra anello intorno alla foto profilo</span></label>
                            <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_avatar_border" value="0"><input type="checkbox" name="profile_avatar_border" id="avatarBorderInput" value="1" <?php echo (int)($profile['profile_avatar_border'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-border-style"></i>Mostra bordo della foto profilo</span></label>

                            <div class="bio-section-heading" style="margin-top: 1.8rem; border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <div><span><i class="fas fa-signature"></i> Personalizzazione Nome</span>
                                    <p>Modifica l'aspetto e le animazioni del tuo nome visualizzato.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid three">
                                <label class="profile-field"><span>Tipo colore nome</span><select name="profile_name_color_type" id="nameColorTypeInput">
                                        <option value="default" <?php echo ($nameStyle['type'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Bianco di base</option>
                                        <option value="solid" <?php echo ($nameStyle['type'] ?? 'default') === 'solid' ? 'selected' : ''; ?>>Colore singolo</option>
                                        <option value="gradient" <?php echo ($nameStyle['type'] ?? 'default') === 'gradient' ? 'selected' : ''; ?>>Sfumatura</option>
                                    </select></label>

                                <label class="profile-field field-name-solid"><span>Colore nome</span><input type="color" name="profile_name_solid_color" id="nameSolidColorInput" value="<?php echo profile_h($nameStyle['solid_color'] ?? '#ffffff'); ?>"></label>

                                <label class="profile-field field-name-gradient"><span>Colore sfumatura 1</span><input type="color" name="profile_name_grad_color1" id="nameGradColor1Input" value="<?php echo profile_h($nameStyle['grad_color1'] ?? '#ffffff'); ?>"></label>
                                <label class="profile-field field-name-gradient"><span>Colore sfumatura 2</span><input type="color" name="profile_name_grad_color2" id="nameGradColor2Input" value="<?php echo profile_h($nameStyle['grad_color2'] ?? '#8b5cf6'); ?>"></label>
                                <label class="profile-field field-name-gradient"><span>Angolo sfumatura (gradi)</span><input type="number" name="profile_name_grad_angle" id="nameGradAngleInput" min="0" max="360" value="<?php echo (int)($nameStyle['grad_angle'] ?? 90); ?>"></label>

                                <label class="profile-field"><span>Animazione nome</span><select name="profile_name_animation" id="nameAnimationInput">
                                        <option value="none" <?php echo ($nameStyle['animation'] ?? 'none') === 'none' ? 'selected' : ''; ?>>Nessuna</option>
                                        <option value="rainbow" <?php echo ($nameStyle['animation'] ?? 'none') === 'rainbow' ? 'selected' : ''; ?>>Slide arcobaleno</option>
                                        <option value="glow" <?php echo ($nameStyle['animation'] ?? 'none') === 'glow' ? 'selected' : ''; ?>>Bagliore pulsante</option>
                                        <option value="sparkles" <?php echo ($nameStyle['animation'] ?? 'none') === 'sparkles' ? 'selected' : ''; ?>>Sparkles magici</option>
                                        <option value="fire" <?php echo ($nameStyle['animation'] ?? 'none') === 'fire' ? 'selected' : ''; ?>>Fuoco animato</option>
                                        <option value="water" <?php echo ($nameStyle['animation'] ?? 'none') === 'water' ? 'selected' : ''; ?>>Acqua fluida</option>
                                        <option value="glitch" <?php echo ($nameStyle['animation'] ?? 'none') === 'glitch' ? 'selected' : ''; ?>>Glitch cibernetico</option>
                                        <option value="neon" <?php echo ($nameStyle['animation'] ?? 'none') === 'neon' ? 'selected' : ''; ?>>Neon tremolante</option>
                                        <option value="bounce" <?php echo ($nameStyle['animation'] ?? 'none') === 'bounce' ? 'selected' : ''; ?>>Lettere rimbalzanti</option>
                                    </select></label>

                                <label class="profile-field field-name-glow"><span>Colore bagliore</span><input type="color" name="profile_name_glow_color" id="nameGlowColorInput" value="<?php echo profile_h($nameStyle['glow_color'] ?? '#8b5cf6'); ?>"></label>
                            </div>

                            <?php
                            $dbTiltEnabled = (int)($profile['tilt_enabled'] ?? 1);
                            $dbTiltMax = (int)($profile['tilt_max'] ?? 15);
                            $dbTiltGlare = (float)($profile['tilt_glare'] ?? 0.0);
                            $dbTiltZoom = (float)($profile['tilt_zoom'] ?? 1.05);
                            $dbTiltSpeed = (int)($profile['tilt_speed'] ?? 400);

                            $selectedPreset = 'custom';
                            if ($dbTiltEnabled === 0) {
                                $selectedPreset = 'off';
                            } else {
                                if ($dbTiltMax === 5 && abs($dbTiltGlare - 0.08) < 0.01 && abs($dbTiltZoom - 1.01) < 0.01 && $dbTiltSpeed === 1000) {
                                    $selectedPreset = 'super_soft';
                                } else if ($dbTiltMax === 10 && abs($dbTiltGlare - 0.15) < 0.01 && abs($dbTiltZoom - 1.02) < 0.01 && $dbTiltSpeed === 800) {
                                    $selectedPreset = 'soft';
                                } else if ($dbTiltMax === 15 && abs($dbTiltGlare - 0.25) < 0.01 && abs($dbTiltZoom - 1.05) < 0.01 && $dbTiltSpeed === 600) {
                                    $selectedPreset = 'medium';
                                } else if ($dbTiltMax === 25 && abs($dbTiltGlare - 0.40) < 0.01 && abs($dbTiltZoom - 1.08) < 0.01 && $dbTiltSpeed === 400) {
                                    $selectedPreset = 'strong';
                                } else if ($dbTiltMax === 35 && abs($dbTiltGlare - 0.60) < 0.01 && abs($dbTiltZoom - 1.12) < 0.01 && $dbTiltSpeed === 200) {
                                    $selectedPreset = 'extreme';
                                } else if ($dbTiltMax === 15 && abs($dbTiltGlare - 0.0) < 0.01 && abs($dbTiltZoom - 1.05) < 0.01 && $dbTiltSpeed === 400) {
                                    $selectedPreset = 'medium';
                                }
                            }
                            ?>
                            <div class="bio-section-heading" style="margin-top: 1.8rem; border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <div><span><i class="fas fa-cube"></i> Effetto Inclinazione (Tilt Card)</span>
                                    <p>Personalizza l'effetto di inclinazione 3D della card del profilo al passaggio del mouse.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Livello Tilt</span>
                                    <select name="tilt_preset" id="tiltPresetInput">
                                        <option value="off" <?php echo $selectedPreset === 'off' ? 'selected' : ''; ?>>Off (Disattivato)</option>
                                        <option value="super_soft" <?php echo $selectedPreset === 'super_soft' ? 'selected' : ''; ?>>Super Soft</option>
                                        <option value="soft" <?php echo $selectedPreset === 'soft' ? 'selected' : ''; ?>>Soft</option>
                                        <option value="medium" <?php echo $selectedPreset === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="strong" <?php echo $selectedPreset === 'strong' ? 'selected' : ''; ?>>Strong</option>
                                        <option value="extreme" <?php echo $selectedPreset === 'extreme' ? 'selected' : ''; ?>>Extreme</option>
                                        <option value="custom" <?php echo $selectedPreset === 'custom' ? 'selected' : ''; ?>>Personalizzato</option>
                                    </select>
                                </label>
                                <label class="profile-toggle-card profile-inline-toggle" style="margin-top: 1.5rem;">
                                    <input type="hidden" name="tilt_enabled" value="0">
                                    <input type="checkbox" name="tilt_enabled" id="tiltEnabledInput" value="1" <?php echo $dbTiltEnabled === 1 ? 'checked' : ''; ?>>
                                    <span>Abilita inclinazione card</span>
                                </label>
                            </div>
                            <div id="tiltCustomControls" class="profile-field-grid two" style="display: <?php echo $selectedPreset === 'custom' ? 'grid' : 'none'; ?>; margin-top: 1rem;">
                                <label class="profile-field"><span>Gradi inclinazione max (<span id="tiltMaxVal"><?php echo $dbTiltMax; ?></span>°)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_max" id="tiltMaxInput" min="0" max="45" value="<?php echo $dbTiltMax; ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Intensità riflesso (Glare) (<span id="tiltGlareVal"><?php echo $dbTiltGlare; ?></span>)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_glare" id="tiltGlareInput" min="0" max="1" step="0.05" value="<?php echo $dbTiltGlare; ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Zoom Hover (<span id="tiltZoomVal"><?php echo $dbTiltZoom; ?></span>x)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_zoom" id="tiltZoomInput" min="1.0" max="1.3" step="0.01" value="<?php echo $dbTiltZoom; ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Velocità animazione (ms) (<span id="tiltSpeedVal"><?php echo $dbTiltSpeed; ?></span>ms)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_speed" id="tiltSpeedInput" min="100" max="2000" step="50" value="<?php echo $dbTiltSpeed; ?>" style="flex: 1;">
                                    </div>
                                </label>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 1.8rem; border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <div><span><i class="fas fa-window-maximize"></i> Titolo Scheda Browser (Tab)</span>
                                    <p>Personalizza il titolo del browser e aggiungi effetti di animazione.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Titolo personalizzato</span>
                                    <input type="text" name="profile_tab_title" id="profileTabTitleInput" maxlength="80" value="<?php echo profile_h($profile['profile_tab_title'] ?? ''); ?>" placeholder="Lascia vuoto per usare il nome utente">
                                </label>
                                <label class="profile-field"><span>Animazione Titolo</span>
                                    <select name="profile_tab_animation" id="profileTabAnimationInput">
                                        <option value="static" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'static' ? 'selected' : ''; ?>>Statico</option>
                                        <option value="marquee" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'marquee' ? 'selected' : ''; ?>>Scorrimento (Marquee)</option>
                                        <option value="bounce" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'bounce' ? 'selected' : ''; ?>>Rimbalzo (Bounce)</option>
                                        <option value="pulse" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'pulse' ? 'selected' : ''; ?>>Pulsante (Pulse)</option>
                                    </select>
                                </label>
                            </div>
                            <div class="profile-field-grid two" style="margin-top: 1rem;">
                                <label class="profile-field"><span>Testo animato / Alternativo</span>
                                    <input type="text" name="profile_tab_animation_text" id="profileTabAnimationTextInput" maxlength="120" value="<?php echo profile_h($profile['profile_tab_animation_text'] ?? ''); ?>" placeholder="Es. ★ Benvenuto ★">
                                    <small>Usato come testo secondario per l'effetto marquee o per l'alternanza pulse.</small>
                                </label>
                                <label class="profile-field"><span>Velocità animazione (ms) (<span id="profileTabSpeedVal"><?php echo (int)($profile['profile_tab_animation_speed'] ?? 1000); ?></span>ms)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_tab_animation_speed" id="profileTabAnimationSpeedInput" min="200" max="5000" step="100" value="<?php echo (int)($profile['profile_tab_animation_speed'] ?? 1000); ?>" style="flex: 1;">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Section 10: Badge -->
                    <div class="profile-edit-section editor-card" data-edit-section="badges">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-trophy"></i></span>
                                <div class="editor-card-text">
                                    <h3>Badge</h3>
                                    <p>Scegli i badge da mostrare nel profilo e ordinali</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Badge</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-trophy"></i> Badge visibili</span>
                                    <p>Scegli quali badge mostrare sul tuo profilo (massimo 8) e ordinali.</p>
                                </div>
                            </div>
                            <div class="profile-sortable-list" id="badgeSortList" data-badges="<?php echo profile_h(json_encode($availableBadges)); ?>">
                                <!-- Popolato via Javascript -->
                            </div>
                        </div>
                    </div>

                    <!-- Section 11: Personaggi -->
                    <div class="profile-edit-section editor-card" data-edit-section="characters">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-user-astronaut"></i></span>
                                <div class="editor-card-text">
                                    <h3>Personaggi preferiti</h3>
                                    <p>Seleziona i personaggi dell'inventario da mostrare</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Inventario</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <?php if ($inventoryCharacters): ?>
                                <div class="profile-character-search-wrap editor-search-wrapper">
                                    <i class="fas fa-search editor-search-icon"></i>
                                    <input
                                        type="text"
                                        id="characterSearchInput"
                                        class="profile-character-search"
                                        placeholder="Cerca personaggio..."
                                        autocomplete="off">
                                </div>

                                <div class="profile-character-picker" id="characterPicker">
                                    <?php foreach ($inventoryCharacters as $char): ?>
                                        <?php
                                        $charImg       = profile_character_img_url($char);
                                        $rarityClass   = profile_character_rarity_class((string)($char['rarità'] ?? ''));
                                        $charQty       = (int)($char['quantità'] ?? 0);
                                        $rarityLabel   = ucfirst((string)($char['rarità'] ?? ''));
                                        ?>
                                        <label
                                            class="profile-character-choice rarity-<?php echo profile_h($rarityClass); ?>"
                                            data-char-name="<?php echo profile_h(strtolower($char['nome'])); ?>"
                                            title="<?php echo profile_h($char['nome']); ?>">
                                            <input
                                                type="checkbox"
                                                value="<?php echo (int)$char['id']; ?>"
                                                <?php echo (int)$char['selected'] === 1 ? 'checked' : ''; ?>>

                                            <?php if ($charImg): ?>
                                                <img src="<?php echo profile_h($charImg); ?>" alt="<?php echo profile_h($char['nome']); ?>" loading="lazy">
                                            <?php else: ?>
                                                <span class="profile-character-img-fallback-picker">
                                                    <i class="fas fa-user-astronaut"></i>
                                                </span>
                                            <?php endif; ?>

                                            <div class="profile-character-choice-info">
                                                <strong><?php echo profile_h($char['nome']); ?></strong>
                                                <?php if ($charQty > 1): ?>
                                                    <small>×<?php echo $charQty; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <p class="profile-character-hint">
                                    <i class="fas fa-circle-info"></i>
                                    <?php
                                    $selectedCount = count(array_filter($inventoryCharacters, fn($c) => (int)$c['selected'] === 1));
                                    echo $selectedCount . '/12 selezionati.';
                                    ?>
                                </p>

                                <div class="profile-character-sort-section" style="margin-top: 1.5rem;">
                                    <strong style="display: block; margin-bottom: 0.5rem;"><i class="fas fa-sort"></i> Ordinamento Personaggi Selezionati</strong>
                                    <p style="font-size: 0.82rem; color: var(--muted-2); margin-bottom: 0.75rem;">Trascina per scegliere l'ordine di visualizzazione.</p>
                                    <div id="characterSortList" class="profile-character-sort-list">
                                        <!-- Populated dynamically via JS -->
                                    </div>
                                </div>

                            <?php else: ?>
                                <div class="bio-empty-state">
                                    <i class="fas fa-user-astronaut"></i>
                                    <strong>Nessun personaggio nell'inventario</strong>
                                    <p>Ottieni personaggi dalle lootbox per mostrarli qui.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section 12: Visibilità e Ordinamento -->
                    <div class="profile-edit-section editor-card" data-edit-section="visibility">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-eye"></i></span>
                                <div class="editor-card-text">
                                    <h3>Visibilità e Ordinamento</h3>
                                    <p>Spegni o riordina sezioni, layout dei badge</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Opzioni</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-eye"></i> Sezioni pubbliche</span>
                                    <p>Spegni ciò che non vuoi mostrare. Le sezioni vuote restano nascoste comunque.</p>
                                </div>
                            </div>
                            <div class="profile-toggle-grid">
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_socials" value="0"><input type="checkbox" name="profile_show_socials" value="1" <?php echo (int)($profile['profile_show_socials'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fab fa-instagram"></i>Social</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_links" value="0"><input type="checkbox" name="profile_show_links" value="1" <?php echo (int)($profile['profile_show_links'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-link"></i>Link</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_embeds" value="0"><input type="checkbox" name="profile_show_embeds" value="1" <?php echo (int)($profile['profile_show_embeds'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-share-square"></i>Embed</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_projects" value="0"><input type="checkbox" name="profile_show_projects" value="1" <?php echo (int)($profile['profile_show_projects'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-cubes"></i>Progetti</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_contents" value="0"><input type="checkbox" name="profile_show_contents" value="1" <?php echo (int)($profile['profile_show_contents'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-play"></i>Edit e contenuti</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_blocks" value="0"><input type="checkbox" name="profile_show_blocks" value="1" <?php echo (int)($profile['profile_show_blocks'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-wand-magic-sparkles"></i>Blocchi Custom</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_badges" value="0"><input type="checkbox" name="profile_show_badges" value="1" <?php echo (int)($profile['profile_show_badges'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-trophy"></i>Badge</span></label>
                                <label class="profile-toggle-card">
                                    <input type="hidden" name="profile_show_characters" value="0">
                                    <input type="checkbox" name="profile_show_characters" value="1"
                                        <?php echo (int)($profile['profile_show_characters'] ?? 1) === 1 ? 'checked' : ''; ?>>
                                    <span><i class="fas fa-user-astronaut"></i>Personaggi</span>
                                </label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_stats" value="0"><input type="checkbox" name="profile_show_stats" value="1" <?php echo (int)($profile['profile_show_stats'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-chart-simple"></i>Statistiche</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_activity" value="0"><input type="checkbox" name="profile_show_activity" value="1" <?php echo (int)($profile['profile_show_activity'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-clock"></i>Attività</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_discord" value="0"><input type="checkbox" name="profile_show_discord" value="1" <?php echo (int)($profile['profile_show_discord'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fab fa-discord"></i>Discord</span></label>
                            </div>

                            <div style="margin-top: 1.25rem; width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <label class="profile-field" style="display: flex; flex-direction: column; gap: 0.4rem; width: 100%;">
                                    <span style="font-size: 0.82rem; font-weight: 600; color: var(--muted);"><i class="fas fa-trophy"></i> Visualizzazione Badge</span>
                                    <select name="profile_badges_display" id="badgesDisplayInput" class="profile-select-menu" style="width: 100%; max-width: 100%;">
                                        <option value="both" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'both' ? 'selected' : ''; ?>>Mostra in entrambi (sotto il nome e sezione)</option>
                                        <option value="card_only" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'card_only' ? 'selected' : ''; ?>>Mostra solo sul profilo (card principale)</option>
                                        <option value="tab_only" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'tab_only' ? 'selected' : ''; ?>>Mostra solo nella sezione/tab dei badge</option>
                                        <option value="none" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'none' ? 'selected' : ''; ?>>Nascondi badge completamente</option>
                                    </select>
                                </label>
                                <label class="profile-field" style="display: flex; flex-direction: column; gap: 0.4rem; width: 100%;">
                                    <span style="font-size: 0.82rem; font-weight: 600; color: var(--muted);"><i class="fas fa-location-arrow"></i> Posizione dei mini-badge</span>
                                    <select name="profile_badges_position" id="badgesPositionInput" class="profile-select-menu" style="width: 100%; max-width: 100%;">
                                        <option value="below_bio" <?php echo ($profile['profile_badges_position'] ?? 'below_bio') === 'below_bio' ? 'selected' : ''; ?>>Sotto la bio</option>
                                        <option value="below_username" <?php echo ($profile['profile_badges_position'] ?? 'below_bio') === 'below_username' ? 'selected' : ''; ?>>Sotto lo username</option>
                                        <option value="right_of_name" <?php echo ($profile['profile_badges_position'] ?? 'below_bio') === 'right_of_name' ? 'selected' : ''; ?>>A destra del nome</option>
                                    </select>
                                </label>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 1.8rem;">
                                <div><span><i class="fas fa-sort"></i> Ordinamento sezioni</span>
                                    <p>Trascina per cambiare l'ordine delle sezioni sul profilo pubblico.</p>
                                </div>
                            </div>
                            <input type="hidden" name="profile_sections_order" id="sectionsOrderJson" value="<?php echo profile_h($profile['profile_sections_order'] ?? 'links,embeds,stats,projects,blocks,contents,characters,badges,activity'); ?>">
                            <div id="sectionsSortList" class="profile-sections-sort-list">
                                <!-- Popolato via Javascript -->
                            </div>
                        </div>
                    </div>

                    <!-- Section 13: Preset -->
                    <div class="profile-edit-section editor-card" data-edit-section="presets">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fas fa-magic"></i></span>
                                <div class="editor-card-text">
                                    <h3>Preset del profilo</h3>
                                    <p>Salva o carica configurazioni complete per scambiare profili al volo</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Preset</span>
                                <span class="editor-card-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fas fa-magic"></i> Preset del Profilo</span>
                                    <p>Salva e carica configurazioni complete del tuo profilo (massimo 3 preset).</p>
                                </div>
                                <button type="button" class="bio-button" id="saveNewPresetBtn"><i class="fas fa-plus"></i> Salva Preset Corrente</button>
                            </div>
                            <div class="presets-list-container" id="presetsListContainer">
                                <!-- Presets loaded via AJAX -->
                            </div>
                        </div>
                    </div>
                </div> <!-- End of editor-sidebar-scroll -->
            </div> <!-- End of editor-sidebar -->

            <!-- Resize Handle -->
            <div class="editor-resize-handle" id="editorResizeHandle"></div>

            <!-- Right Preview Panel -->
            <div class="editor-preview-pane">
                <div class="preview-toolbar">
                    <span class="preview-status">Anteprima in tempo reale</span>
                    <div class="viewport-buttons">
                        <button type="button" class="btn-viewport is-active" data-viewport="desktop"><i class="fas fa-desktop"></i> Desktop</button>
                        <button type="button" class="btn-viewport" data-viewport="mobile"><i class="fas fa-mobile-alt"></i> Mobile</button>
                    </div>
                    <a href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>" target="_blank" class="btn-view-live" title="Apri in una nuova scheda"><i class="fas fa-external-link-alt"></i> Vedi profilo</a>
                </div>
                <div class="preview-canvas">
                    <div class="device-frame desktop" id="previewDeviceFrame">
                        <iframe id="profilePreviewIframe" src="../profile.php?id=<?php echo (int)$profile['id']; ?>&preview_mode=1"></iframe>
                    </div>
                </div>
            </div>

            <!-- HIDDEN INPUTS -->
            <input type="hidden" name="csrf_token" value="<?php echo profile_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo (int)$targetUserId; ?>">
            <input type="hidden" name="socials_json" id="socialsJson">
            <input type="hidden" name="links_json" id="linksJson">
            <input type="hidden" name="projects_json" id="projectsJson">
            <input type="hidden" name="contents_json" id="contentsJson">
            <input type="hidden" name="blocks_json" id="blocksJson">
            <input type="hidden" name="badges_json" id="badgesJson">
            <input type="hidden" name="characters_json" id="charactersJson">
            <input type="hidden" name="embeds_json" id="embedsJson">
            <input type="hidden" name="profile_tags_json" id="profileTagsJson">
        </form>
    </main>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <?php profile_json_script('initialSocialsData', $socials); ?>
    <?php
    $tags = json_decode($profile['profile_tags_json'] ?? '[]', true) ?: [];
    profile_json_script('initialTagsData', $tags);
    ?>
    <?php profile_json_script('initialLinksData', $links); ?>
    <?php profile_json_script('initialProjectsData', $projects); ?>
    <?php profile_json_script('initialContentsData', $contents); ?>
    <?php profile_json_script('initialBlocksData', $blocks); ?>
    <?php profile_json_script('initialEmbedsData', $embeds); ?>
    <?php
    $displayedChars = profile_list_displayed_characters($mysqli, $targetUserId);
    $displayedCharIds = array_map(fn($c) => (int)$c['id'], $displayedChars);
    profile_json_script('initialCharactersData', $displayedCharIds);
    ?>

    <?php // if (file_exists(__DIR__ . '/../includes/footer.php')) include __DIR__ . '/../includes/footer.php'; ?>
    <div class="editor-loading-overlay" id="editorLoadingOverlay">
        <div class="editor-loading-spinner"></div>
        <div class="editor-loading-text" id="editorLoadingText">Salvataggio in corso...</div>
        <div class="editor-loading-subtext" id="editorLoadingSubtext">Uploader dei file multimediali attivo, attendi senza chiudere la pagina.</div>
    </div>
</body>

</html>
