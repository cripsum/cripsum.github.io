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
$badges = profile_list_unlocked_badges($mysqli, $targetUserId);
$csrf = profile_csrf_token();

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
    <link rel="stylesheet" href="/assets/css/profile.css?v=2.0">
</head>
<body class="profile-shell profile-editor-shell profile-theme-<?php echo profile_h($profile['profile_theme'] ?: 'dark'); ?>" style="--profile-accent: <?php echo profile_h(profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff')); ?>;">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <?php if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php'; ?>

    <main class="profile-page profile-edit-page">
        <header class="profile-edit-header profile-animate-in">
            <div>
                <span class="profile-state-badge">V2</span>
                <h1>Modifica profilo</h1>
                <p>Gestisci identità, link, progetti, contenuti e privacy.</p>
            </div>
            <a class="profile-soft-link" href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>">Vedi profilo pubblico</a>
        </header>

        <form id="profileEditForm" class="profile-edit-grid" method="post" enctype="multipart/form-data" action="/api/update_profile.php">
            <input type="hidden" name="csrf_token" value="<?php echo profile_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo (int)$targetUserId; ?>">
            <input type="hidden" name="socials_json" id="socialsJson">
            <input type="hidden" name="links_json" id="linksJson">
            <input type="hidden" name="projects_json" id="projectsJson">
            <input type="hidden" name="contents_json" id="contentsJson">
            <input type="hidden" name="badges_json" id="badgesJson">

            <section class="profile-edit-panel profile-animate-in profile-delay-1">
                <div class="profile-editor-tabs" role="tablist">
                    <button type="button" class="is-active" data-edit-tab="identity">Identità</button>
                    <button type="button" data-edit-tab="links">Link</button>
                    <button type="button" data-edit-tab="projects">Progetti</button>
                    <button type="button" data-edit-tab="content">Contenuti</button>
                    <button type="button" data-edit-tab="badges">Badge</button>
                </div>

                <div class="profile-edit-section is-active" data-edit-section="identity">
                    <div class="profile-field-grid two">
                        <label class="profile-field">
                            <span>Nome visualizzato</span>
                            <input type="text" name="display_name" id="displayNameInput" maxlength="40" value="<?php echo profile_h($profile['display_name'] ?? ''); ?>" placeholder="Es. Cripsum">
                        </label>
                        <label class="profile-field">
                            <span>Username</span>
                            <input type="text" name="username" id="usernameInput" maxlength="20" required value="<?php echo profile_h($profile['username']); ?>" placeholder="username">
                            <small>3-20 caratteri. Lettere, numeri e underscore.</small>
                        </label>
                    </div>

                    <label class="profile-field">
                        <span>Bio</span>
                        <textarea name="bio" id="bioInput" maxlength="280" rows="5" placeholder="Scrivi qualcosa di tuo..."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea>
                        <small><span id="bioCounter">0</span>/280</small>
                    </label>

                    <div class="profile-field-grid two">
                        <label class="profile-field">
                            <span>Avatar</span>
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif">
                            <small>Max 2MB. JPG, PNG, WEBP o GIF.</small>
                        </label>
                        <label class="profile-field">
                            <span>Banner</span>
                            <input type="file" name="banner" id="bannerInput" accept="image/jpeg,image/png,image/webp,image/gif">
                            <small>Max 4MB. Meglio formato 16:9.</small>
                        </label>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field">
                            <span>Accent color</span>
                            <input type="color" name="accent_color" id="accentInput" value="<?php echo profile_h(profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff')); ?>">
                        </label>
                        <label class="profile-field">
                            <span>Tema</span>
                            <select name="profile_theme" id="themeInput">
                                <?php foreach (['dark' => 'Scuro', 'light' => 'Chiaro', 'auto' => 'Auto'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($profile['profile_theme'] ?? 'dark') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="profile-field">
                            <span>Layout</span>
                            <select name="profile_layout" id="layoutInput">
                                <?php foreach (['standard' => 'Standard', 'compact' => 'Compatto', 'showcase' => 'Showcase'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($profile['profile_layout'] ?? 'standard') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <label class="profile-field">
                        <span>Privacy profilo</span>
                        <select name="profile_visibility" id="visibilityInput">
                            <?php foreach (['public' => 'Pubblico', 'logged_in' => 'Solo utenti loggati', 'private' => 'Privato'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($profile['profile_visibility'] ?? 'public') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="profile-edit-section" data-edit-section="links">
                    <div class="profile-section-head compact">
                        <div><h2>Social</h2><p>Icone rapide sotto al profilo.</p></div>
                        <button type="button" class="profile-mini-button" data-add-row="socials">+ Social</button>
                    </div>
                    <div class="profile-repeater" id="socialsRepeater"></div>

                    <div class="profile-section-head compact mt">
                        <div><h2>Link personalizzati</h2><p>Link grandi, ordinati e cliccabili.</p></div>
                        <button type="button" class="profile-mini-button" data-add-row="links">+ Link</button>
                    </div>
                    <div class="profile-repeater" id="linksRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="projects">
                    <div class="profile-section-head compact">
                        <div><h2>Progetti</h2><p>Sito, mod, launcher, tool, game, repo.</p></div>
                        <button type="button" class="profile-mini-button" data-add-row="projects">+ Progetto</button>
                    </div>
                    <div class="profile-repeater" id="projectsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="content">
                    <div class="profile-section-head compact">
                        <div><h2>Contenuti</h2><p>Edit, video, pagine, showcase.</p></div>
                        <button type="button" class="profile-mini-button" data-add-row="contents">+ Contenuto</button>
                    </div>
                    <div class="profile-repeater" id="contentsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="badges">
                    <div class="profile-section-head compact">
                        <div><h2>Badge visibili</h2><p>Puoi mostrarne massimo 8.</p></div>
                    </div>
                    <?php if ($badges): ?>
                        <div class="profile-badge-picker" id="badgePicker">
                            <?php foreach ($badges as $badge): ?>
                                <label class="profile-badge-choice">
                                    <input type="checkbox" value="<?php echo (int)$badge['id']; ?>" <?php echo (int)$badge['selected'] === 1 ? 'checked' : ''; ?>>
                                    <?php if (!empty($badge['img_url'])): ?>
                                        <img src="/img/<?php echo profile_h($badge['img_url']); ?>" alt="">
                                    <?php else: ?>
                                        <span>✦</span>
                                    <?php endif; ?>
                                    <strong><?php echo profile_h($badge['nome']); ?></strong>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="profile-empty-state"><div class="profile-empty-icon">✦</div><h3>Nessun badge sbloccato</h3><p>Quando sblocchi achievement, puoi mostrarli qui.</p></div>
                    <?php endif; ?>
                </div>

                <div class="profile-editor-footer">
                    <button type="submit" class="profile-main-button" id="saveProfileButton">Salva profilo</button>
                    <a class="profile-soft-link" href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>">Annulla</a>
                </div>
            </section>

            <aside class="profile-preview-card profile-animate-in profile-delay-2">
                <span class="profile-state-badge">Preview live</span>
                <div class="profile-preview-banner" id="previewBanner" <?php if (!empty($profile['profile_banner_type'])): ?>style="background-image: url('/includes/get_profile_banner.php?id=<?php echo (int)$profile['id']; ?>&t=<?php echo time(); ?>')"<?php endif; ?>></div>
                <img class="profile-preview-avatar" id="previewAvatar" src="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>&t=<?php echo time(); ?>" alt="">
                <h2 id="previewName"><?php echo profile_h($profile['display_name'] ?: $profile['username']); ?></h2>
                <p id="previewUsername">@<?php echo profile_h($profile['username']); ?></p>
                <p id="previewBio"><?php echo profile_h($profile['bio'] ?: 'La tua bio apparirà qui.'); ?></p>
                <div class="profile-preview-actions">
                    <span></span><span></span><span></span>
                </div>
                <small>Questa è solo una preview veloce. Il profilo vero usa anche dati pubblici e statistiche.</small>
            </aside>
        </form>
    </main>

    <div class="profile-toast" id="profileToast" role="status" aria-live="polite"></div>

    <?php profile_json_script('initialSocialsData', $socials); ?>
    <?php profile_json_script('initialLinksData', $links); ?>
    <?php profile_json_script('initialProjectsData', $projects); ?>
    <?php profile_json_script('initialContentsData', $contents); ?>

    <?php if (file_exists(__DIR__ . '/includes/footer.php')) include __DIR__ . '/includes/footer.php'; ?>
    <script src="/assets/js/profile.js?v=2.0"></script>
    <script src="/assets/js/edit-profile.js?v=2.0"></script>
</body>
</html>
