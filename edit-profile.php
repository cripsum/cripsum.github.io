<?php
require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/profile_helpers.php';

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

$socials = profile_list_socials($mysqli, $targetUserId, false);
$links = profile_list_links($mysqli, $targetUserId, false);
$projects = profile_list_projects($mysqli, $targetUserId, false);
$contents = profile_list_contents($mysqli, $targetUserId, false);
$blocks = function_exists('profile_list_blocks') ? profile_list_blocks($mysqli, $targetUserId, false) : [];
$badges = profile_list_unlocked_badges($mysqli, $targetUserId);
$csrf = profile_csrf_token();
$accent = profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff');
$theme = profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
if ($theme === 'auto') $theme = 'dark';
$displayName = $profile['display_name'] ?: $profile['username'];
$backgroundUrl = !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] : '/vid/Shorekeeper Wallpaper 4K Loop.mp4';
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
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <title>Cripsum™ - Modifica profilo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/profile.css?v=2.5-plus">
    <script src="/assets/js/profile.js?v=2.5-plus" defer></script>
    <script src="/assets/js/edit-profile.js?v=2.5-plus" defer></script>
</head>
<body class="bio-v2-body profile-editor-shell" data-theme="<?php echo profile_h($theme); ?>" data-accent="<?php echo profile_h($accent); ?>" data-profile-url="https://cripsum.com/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>">
    <?php
    if (file_exists(__DIR__ . '/includes/navbar-bio.php')) {
        include __DIR__ . '/includes/navbar-bio.php';
    } else {
        include __DIR__ . '/includes/navbar.php';
    }
    if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php';
    ?>

    <div class="bio-background" aria-hidden="true">
        <?php if ($backgroundIsVideo): ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster=""><source src="<?php echo profile_h($backgroundUrl); ?>" type="<?php echo profile_h($backgroundType); ?>"></video>
        <?php elseif ($backgroundIsImage): ?>
            <img class="bio-background__media" src="<?php echo profile_h($backgroundUrl); ?>" alt="" loading="eager">
        <?php else: ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster=""><source src="/vid/Shorekeeper Wallpaper 4K Loop.mp4" type="video/mp4"></video>
        <?php endif; ?>
        <div class="bio-background__overlay"></div>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
        <div class="bio-grid-glow"></div>
    </div>

    <main class="profile-edit-layout">
        <header class="bio-card profile-edit-hero js-reveal">
            <div>
                <span class="bio-pill">Profile editor</span>
                <h1>Modifica profilo</h1>
                <p>Gestisci identità, link, progetti, contenuti, badge e Rich Presence Discord.</p>
            </div>
            <div class="profile-edit-hero-actions">
                <a class="bio-button" href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>"><i class="fas fa-eye"></i>Vedi profilo</a>
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

            <section class="bio-card profile-edit-panel js-reveal">
                <div class="profile-editor-tabs" role="tablist">
                    <button type="button" class="is-active" data-edit-tab="identity">Identità</button>
                    <button type="button" data-edit-tab="links">Link</button>
                    <button type="button" data-edit-tab="projects">Progetti</button>
                    <button type="button" data-edit-tab="content">Contenuti</button>
                    <button type="button" data-edit-tab="custom">Custom</button>
                    <button type="button" data-edit-tab="effects">Effetti</button>
                    <button type="button" data-edit-tab="badges">Badge</button>
                    <button type="button" data-edit-tab="visibility">Visibilità</button>
                </div>

                <div class="profile-edit-section is-active" data-edit-section="identity">
                    <div class="bio-section-heading"><div><span><i class="fas fa-id-card"></i> Identità</span><p>Questi dati finiscono nella hero del profilo pubblico.</p></div></div>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Nome visualizzato</span><input type="text" name="display_name" id="displayNameInput" maxlength="40" value="<?php echo profile_h($profile['display_name'] ?? ''); ?>" placeholder="Es. Cripsum"></label>
                        <label class="profile-field"><span>Username</span><input type="text" name="username" id="usernameInput" maxlength="20" required value="<?php echo profile_h($profile['username']); ?>" placeholder="username"><small>3-20 caratteri. Lettere, numeri e underscore.</small></label>
                    </div>

                    <label class="profile-field"><span>Bio</span><textarea name="bio" id="bioInput" maxlength="280" rows="5" placeholder="Scrivi qualcosa di tuo..."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea><small><span id="bioCounter">0</span>/280</small></label>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Stato breve</span><input type="text" name="profile_status" id="statusInput" maxlength="60" value="<?php echo profile_h($profile['profile_status'] ?? ''); ?>" placeholder="editing, gaming, busy..."><small>Appare vicino al nome se non sei online.</small></label>
                        <label class="profile-field"><span>Discord user ID</span><input type="text" name="discord_id" id="discordIdInput" maxlength="25" value="<?php echo profile_h($profile['discord_id'] ?? ''); ?>" placeholder="Es. 963536045180350474"><small>Serve per la Rich Presence.</small></label>
                    </div>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Avatar</span><input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif"><small>Max 2MB. JPG, PNG, WEBP o GIF.</small></label>
                        <label class="profile-field"><span>Sfondo profilo</span><input type="file" name="banner" id="bannerInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"><small>Max 12MB. Foto, GIF o video. Cambia lo sfondo della pagina.</small></label>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Accent color</span><input type="color" name="accent_color" id="accentInput" value="<?php echo profile_h($accent); ?>"></label>
                        <label class="profile-field"><span>Tema</span><select name="profile_theme" id="themeInput"><?php foreach (['dark'=>'Scuro','light'=>'Chiaro','auto'=>'Auto'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_theme'] ?? 'dark') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Layout</span><select name="profile_layout" id="layoutInput"><?php foreach (['standard'=>'Standard','compact'=>'Compatto','showcase'=>'Showcase'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_layout'] ?? 'standard') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                    </div>

                    <label class="profile-field"><span>Privacy profilo</span><select name="profile_visibility" id="visibilityInput"><?php foreach (['public'=>'Pubblico','logged_in'=>'Solo utenti loggati','private'=>'Privato'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_visibility'] ?? 'public') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>

                    <div class="bio-section-heading profile-mt"><div><span><i class="fas fa-music"></i> Audio profilo</span><p>Metti un file audio pubblico. Il player appare solo se compili l’URL.</p></div></div>
                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>URL canzone</span><input type="url" name="profile_music_url" id="musicUrlInput" maxlength="255" value="<?php echo profile_h($profile['profile_music_url'] ?? ''); ?>" placeholder="https://.../audio.mp3"></label>
                        <label class="profile-field"><span>Titolo canzone</span><input type="text" name="profile_music_title" id="musicTitleInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_title'] ?? ''); ?>" placeholder="Nome canzone"></label>
                        <label class="profile-field"><span>Artista / nota</span><input type="text" name="profile_music_artist" id="musicArtistInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_artist'] ?? ''); ?>" placeholder="Artista o fonte"></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_show_audio_player" value="0"><input type="checkbox" name="profile_show_audio_player" value="1" <?php echo (int)($profile['profile_show_audio_player'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-sliders"></i>Mostra player</span></label>
                    </div>
                </div>

                <div class="profile-edit-section" data-edit-section="links">
                    <div class="bio-section-heading"><div><span><i class="fas fa-link"></i> Social</span><p>Icone rapide sotto il profilo.</p></div><button type="button" class="bio-button" data-add-row="socials">+ Social</button></div>
                    <div class="profile-repeater" id="socialsRepeater"></div>

                    <div class="bio-section-heading profile-mt"><div><span><i class="fas fa-star"></i> Link personalizzati</span><p>Card grandi in evidenza.</p></div><button type="button" class="bio-button" data-add-row="links">+ Link</button></div>
                    <div class="profile-repeater" id="linksRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="projects">
                    <div class="bio-section-heading"><div><span><i class="fas fa-cubes"></i> Progetti</span><p>Sito, mod, launcher, tool, game, repo.</p></div><button type="button" class="bio-button" data-add-row="projects">+ Progetto</button></div>
                    <div class="profile-repeater" id="projectsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="content">
                    <div class="bio-section-heading"><div><span><i class="fas fa-play-circle"></i> Contenuti</span><p>Edit, video, pagine e showcase.</p></div><button type="button" class="bio-button" data-add-row="contents">+ Contenuto</button></div>
                    <div class="profile-repeater" id="contentsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="custom">
                    <div class="bio-section-heading"><div><span><i class="fas fa-wand-magic-sparkles"></i> Blocchi custom</span><p>Testi, immagini, GIF o video. Se sono vuoti non appaiono nel profilo.</p></div><button type="button" class="bio-button" data-add-row="blocks">+ Blocco</button></div>
                    <div class="profile-repeater" id="blocksRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="effects">
                    <div class="bio-section-heading"><div><span><i class="fas fa-sparkles"></i> Effetti</span><p>Effetti leggeri su pagina, mouse e foto profilo.</p></div></div>
                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Effetto pagina</span><select name="profile_effect" id="profileEffectInput">
                            <?php foreach (['none'=>'Nessuno','cursor_glow'=>'Mouse glow','soft_particles'=>'Particles leggere','scanlines'=>'Scanlines','ambient'=>'Ambient glow'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_effect'] ?? 'none') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                        </select></label>
                        <label class="profile-field"><span>Effetto anello PFP</span><select name="avatar_ring_style" id="ringStyleInput">
                            <?php foreach (['spin'=>'Rotazione','pulse'=>'Pulse','orbit'=>'Orbit','glow'=>'Glow','none'=>'Nessuno'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['avatar_ring_style'] ?? 'spin') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                        </select></label>
                        <label class="profile-field"><span>Colore anello PFP</span><input type="color" name="avatar_ring_color" id="ringColorInput" value="<?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>"></label>
                    </div>
                    <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="avatar_ring_enabled" value="0"><input type="checkbox" name="avatar_ring_enabled" id="ringEnabledInput" value="1" <?php echo (int)($profile['avatar_ring_enabled'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-circle-notch"></i>Mostra anello intorno alla foto profilo</span></label>
                </div>

                <div class="profile-edit-section" data-edit-section="badges">
                    <div class="bio-section-heading"><div><span><i class="fas fa-trophy"></i> Badge visibili</span><p>Puoi mostrarne massimo 8.</p></div></div>
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
                        <div class="bio-empty-state"><i class="fas fa-medal"></i><strong>Nessun badge sbloccato</strong><p>Quando sblocchi achievement, puoi mostrarli qui.</p></div>
                    <?php endif; ?>
                </div>

                <div class="profile-edit-section" data-edit-section="visibility">
                    <div class="bio-section-heading"><div><span><i class="fas fa-eye"></i> Sezioni pubbliche</span><p>Spegni ciò che non vuoi mostrare. Se una sezione è vuota, resta nascosta comunque.</p></div></div>
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
                <span class="bio-pill">Preview live</span>
                <div class="profile-background-note"><i class="fas fa-image"></i><span>Lo sfondo scelto appare dietro tutta la pagina, non sopra la foto profilo.</span></div>
                <div class="bio-avatar-wrap profile-preview-avatar-ring" id="previewAvatarWrap"><div class="bio-avatar-ring" id="previewAvatarRing"></div><img class="bio-avatar" id="previewAvatar" src="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>&t=<?php echo time(); ?>" alt=""></div>
                <div class="bio-name-block"><p class="bio-kicker">preview profilo</p><h1 id="previewName"><?php echo profile_h($displayName); ?></h1><p class="bio-username" id="previewUsername">@<?php echo profile_h($profile['username']); ?></p></div>
                <p class="bio-tagline" id="previewBio"><?php echo profile_h($profile['bio'] ?: 'La tua bio apparirà qui.'); ?></p>
                <div class="bio-badges"><span class="bio-badge" id="previewStatusBadge"><i class="fas fa-signal"></i>Stato</span><span class="bio-badge"><i class="fas fa-link"></i>Link</span><span class="bio-badge"><i class="fas fa-trophy"></i>Badge</span></div>
                <p class="bio-description" id="previewExtra">Audio, effetti e blocchi custom appaiono solo se li compili.</p>
            </aside>
        </form>
    </main>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <?php profile_json_script('initialSocialsData', $socials); ?>
    <?php profile_json_script('initialLinksData', $links); ?>
    <?php profile_json_script('initialProjectsData', $projects); ?>
    <?php profile_json_script('initialContentsData', $contents); ?>
    <?php profile_json_script('initialBlocksData', $blocks); ?>

    <?php if (file_exists(__DIR__ . '/includes/footer.php')) include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
