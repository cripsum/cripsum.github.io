<?php
/**
 * Editor del profilo: una sola pagina per italiano e inglese.
 *
 * it/edit-profile.php ed en/edit-profile.php impostano $editorLang e includono
 * questo file. Prima erano due copie quasi identiche (piu' due copie del JS)
 * e ogni correzione andava fatta due volte.
 *
 * Organizzazione: una barra in alto (stato, annulla, anteprima, pubblica), una
 * barra laterale con sei aree e il pannello dell'area scelta. Le aree stanno in
 * views/, i controlli in fields.php, le scelte possibili in catalog.php.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../profile_helpers.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/catalog.php';

$editorLang = ($editorLang ?? 'it') === 'en' ? 'en' : 'it';
$lang = $editorLang;
$tt = static fn(string $it, string $en): string => $editorLang === 'it' ? $it : $en;

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $tt('Per modificare il profilo devi essere loggato', 'You need to log in to edit your profile');
    header('Location: accedi');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_GET['user_id']) && profile_is_staff() ? (int)$_GET['user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    http_response_code(403);
    exit($tt('Accesso negato.', 'Access denied.'));
}

$profile = profile_get_edit_profile($mysqli, $targetUserId);
if (!$profile) {
    http_response_code(404);
    exit($tt('Profilo non trovato.', 'Profile not found.'));
}

$isPremium = (int)($profile['is_premium'] ?? 0) === 1;

// L'editor parte sempre dal profilo pubblicato: una bozza lasciata da una
// visita precedente farebbe vedere nell'anteprima cose che il form non ha.
unset($_SESSION['profile_draft'][$targetUserId]);
$GLOBALS['pe'] = ['lang' => $editorLang, 'premium' => $isPremium];

$catalog = profile_editor_catalog($tt);
$style = profile_style_resolve($profile);
$nameStyle = profile_name_style_normalize($profile['profile_name_style'] ?? null, $style['text_color'], $style['theme']);
[$layoutChoice] = profile_layout_choice_for($profile);

$socials = profile_list_socials($mysqli, $targetUserId, false);
$links = profile_list_links($mysqli, $targetUserId, false);
$projects = profile_list_projects($mysqli, $targetUserId, false);
$contents = profile_list_contents($mysqli, $targetUserId, false);
$blocks = profile_list_blocks($mysqli, $targetUserId, false);
$embeds = profile_list_embeds($mysqli, $targetUserId, false);
$tags = json_decode($profile['profile_tags_json'] ?? '[]', true);
$tags = is_array($tags) ? $tags : [];
$availableBadges = profile_list_all_user_badges($mysqli, $targetUserId);
$inventoryCharacters = profile_list_inventory_characters($mysqli, $targetUserId);
$displayedCharacterIds = array_map(static fn($c) => (int)$c['id'], profile_list_displayed_characters($mysqli, $targetUserId));

$sectionsConfig = json_decode($profile['profile_sections_config'] ?? '', true);
$sectionsConfig = is_array($sectionsConfig) ? $sectionsConfig : [];
$sectionsOrder = array_values(array_intersect(
    array_map('trim', explode(',', (string)($profile['profile_sections_order'] ?: 'links,embeds,stats,projects,blocks,contents,characters,badges,activity'))),
    array_keys($catalog['sections'])
));
foreach (array_keys($catalog['sections']) as $sectionKey) {
    if (!in_array($sectionKey, $sectionsOrder, true)) {
        $sectionsOrder[] = $sectionKey;
    }
}

// Statistiche del profilo: catalogo con i valori di adesso e scelta attuale.
$statsSelection = profile_stats_selection($profile);
$statsCatalog = profile_stats_editor_catalog($mysqli, $profile, $editorLang);

$csrf = profile_csrf_token();
$flashSuccess = $_SESSION['profile_flash_success'] ?? '';
$flashError = $_SESSION['profile_flash_error'] ?? '';
unset($_SESSION['profile_flash_success'], $_SESSION['profile_flash_error']);

$discordConnected = !empty($profile['discord_id']) && !empty($profile['discord_username']);
$discordAvatarUrl = $discordConnected ? profile_discord_avatar_url((string)$profile['discord_id'], $profile['discord_avatar'] ?? null, 128) : null;
$discordDisplayName = trim((string)($profile['discord_global_name'] ?? '')) ?: trim((string)($profile['discord_username'] ?? ''));
$connectDiscordParams = ['return_url' => $_SERVER['REQUEST_URI'] ?? '/' . $editorLang . '/edit-profile'];
if (profile_is_staff() && $targetUserId !== $currentUserId) {
    $connectDiscordParams['target_user_id'] = $targetUserId;
}
$connectDiscordUrl = '/auth/discord_connect.php?' . http_build_query($connectDiscordParams);

$stamp = !empty($profile['profile_updated_at']) ? (int)strtotime((string)$profile['profile_updated_at']) : time();
$avatarUrl = profile_avatar_url($profile, 256);
$hasCustomBackground = !empty($profile['profile_banner_type']);
$backgroundUrl = $hasCustomBackground ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] . '&t=' . $stamp : '';
$backgroundType = $hasCustomBackground ? (string)$profile['profile_banner_type'] : '';
$hasUploadedMusic = !empty($profile['profile_music_mime']);
$hasCustomAvatar = (int)($profile['discord_use_avatar'] ?? 0) === 1;
if (!$hasCustomAvatar && ($picStmt = $mysqli->prepare("SELECT profile_pic FROM utenti WHERE id = ? LIMIT 1"))) {
    $picStmt->bind_param('i', $targetUserId);
    $picStmt->execute();
    $hasCustomAvatar = trim((string)($picStmt->get_result()->fetch_assoc()['profile_pic'] ?? '')) !== '';
    $picStmt->close();
}
$profileUrl = '/u/' . rawurlencode(strtolower((string)$profile['username']));
$switchLangUrl = '/' . ($editorLang === 'it' ? 'en' : 'it') . '/edit-profile' . (!empty($_GET['user_id']) && profile_is_staff() ? '?user_id=' . (int)$_GET['user_id'] : '');
$serverUploadLimit = profile_server_upload_limit();
$uploadLimits = [
    'avatar' => profile_effective_upload_limit(($isPremium ? 10 : 2) * 1024 * 1024),
    'background' => profile_effective_upload_limit(($isPremium ? 50 : 12) * 1024 * 1024),
    'music' => profile_effective_upload_limit(12 * 1024 * 1024),
];

$views = [
    'profile' => ['icon' => 'fa-solid fa-user', 'label' => $tt('Profilo', 'Profile'), 'title' => $tt('Il tuo profilo', 'Your profile'), 'desc' => $tt('Foto, nome, bio, tag e social: la parte che si vede per prima.', 'Photo, name, bio, tags and socials: what people see first.')],
    'sections' => ['icon' => 'fa-solid fa-layer-group', 'label' => $tt('Sezioni', 'Sections'), 'title' => $tt('Sezioni', 'Sections'), 'desc' => $tt('I blocchi sotto il profilo, nello stesso ordine in cui appaiono. Trascinali per riordinarli.', 'The blocks below your profile, in the order they appear. Drag to reorder.')],
    'appearance' => ['icon' => 'fa-solid fa-palette', 'label' => $tt('Aspetto', 'Style'), 'title' => $tt('Aspetto', 'Style'), 'desc' => $tt('Tema, colori, layout, forme e sfondo.', 'Theme, colors, layout, shapes and background.')],
    'effects' => ['icon' => 'fa-solid fa-wand-magic-sparkles', 'label' => $tt('Effetti', 'Effects'), 'title' => $tt('Effetti', 'Effects'), 'desc' => $tt('Nome, anello della foto, effetti della pagina e del cursore.', 'Name, photo ring, page and cursor effects.')],
    'music' => ['icon' => 'fa-solid fa-music', 'label' => $tt('Musica', 'Music'), 'title' => $tt('Musica', 'Music'), 'desc' => $tt('Una canzone che parte quando qualcuno apre il tuo profilo.', 'A song that plays when someone opens your profile.')],
    'settings' => ['icon' => 'fa-solid fa-gear', 'label' => $tt('Impostazioni', 'Settings'), 'short' => $tt('Opzioni', 'Settings'), 'title' => $tt('Impostazioni', 'Settings'), 'desc' => $tt('Privacy, indirizzo, Discord e scheda del browser.', 'Privacy, address, Discord and browser tab.')],
];

/** Dati iniziali per il JS, stampati in un blocco JSON. */
$editorData = [
    'lang' => $editorLang,
    'premium' => $isPremium,
    'targetUserId' => $targetUserId,
    'profileId' => (int)$profile['id'],
    'profileUrl' => $profileUrl,
    'serverUploadLimit' => $serverUploadLimit,
    'uploadLimits' => $uploadLimits,
    'catalog' => $catalog,
    'nameEffectsOwnColors' => PROFILE_NAME_EFFECTS_OWN_COLORS,
    'items' => [
        'socials' => $socials,
        'links' => $links,
        'projects' => $projects,
        'contents' => $contents,
        'blocks' => $blocks,
        'embeds' => $embeds,
        'tags' => $tags,
    ],
    'badges' => $availableBadges,
    'characters' => array_map(static fn($c) => [
        'id' => (int)$c['id'],
        'name' => (string)$c['nome'],
        'img' => profile_character_img_url($c),
        'rarity' => profile_character_rarity_class((string)($c['rarità'] ?? '')),
        'qty' => (int)($c['quantità'] ?? 0),
    ], $inventoryCharacters),
    'displayedCharacters' => $displayedCharacterIds,
    'sectionsOrder' => $sectionsOrder,
    'sectionsConfig' => (object)$sectionsConfig,
    'stats' => [
        'catalog' => $statsCatalog,
        'groups' => profile_stats_groups($editorLang),
        'selected' => profile_stats_clean_keys($statsSelection['keys'], $isPremium),
        // Senza una scelta salvata il profilo usa le quattro di sempre (zeri
        // nascosti): l'editor la salva solo se l'utente la tocca.
        'explicit' => $statsSelection['explicit'],
        'limit' => profile_stats_limit($isPremium),
        'limitPremium' => PROFILE_STATS_LIMIT_PREMIUM,
    ],
    'hasServerMusic' => $hasUploadedMusic || trim((string)($profile['profile_music_url'] ?? '')) !== '',
    'flash' => ['success' => $flashSuccess, 'error' => $flashError],
    'tiltPreset' => profile_tilt_preset_for($profile),
    // Icona delle card di progetti e contenuti: solo dopo la migration.
    'itemIcons' => profile_item_icons_available($mysqli),
    'hasCustomAvatar' => $hasCustomAvatar,
    'hasCustomBackground' => $hasCustomBackground,
    'discordConnected' => $discordConnected,
    'userKey' => (int)$currentUserId . '-' . $targetUserId,
];

$pv = static fn(string $col, $default = '') => $profile[$col] ?? $default;
$pflag = static fn(string $col, int $default = 1): bool => (int)($profile[$col] ?? $default) === 1;
?>
<!DOCTYPE html>
<html lang="<?php echo $editorLang; ?>">

<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title><?php echo pe_h($tt('Modifica profilo', 'Edit profile')); ?> · Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="/assets/css/profile-editor.css?v=6.1.0">
    <link rel="stylesheet" href="/assets/css/profile-markdown-guide.css?v=6.0.0">
    <link rel="stylesheet" href="/assets/css/profile-rings.css?v=1.0.0">
    <link rel="stylesheet" href="/assets/css/photo-cropper.css?v=1.3">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" defer></script>
    <script src="/assets/js/profile-markdown-guide.js?v=6.0.0" defer></script>
    <script src="/assets/js/photo-cropper.js?v=1.3" defer></script>
    <script src="/assets/js/profile-tab-title.js?v=1.0.0" defer></script>
    <script src="/assets/js/profile-editor/components.js?v=6.1.0" defer></script>
    <script src="/assets/js/profile-editor/items.js?v=6.1.0" defer></script>
    <script src="/assets/js/profile-editor/editor.js?v=6.1.0" defer></script>
</head>

<body class="pe-body<?php echo $isPremium ? ' is-premium' : ''; ?>" style="--pe-accent: <?php echo pe_h($style['accent']); ?>; --editor-accent: <?php echo pe_h($style['accent']); ?>;">
    <script type="application/json" id="peData"><?php echo json_encode($editorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>

    <?php if ($discordConnected): ?>
        <form id="disconnectDiscordForm" method="post" action="/auth/discord_disconnect.php" hidden>
            <input type="hidden" name="csrf_token" value="<?php echo pe_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo $targetUserId; ?>">
            <input type="hidden" name="return_url" value="<?php echo pe_h($_SERVER['REQUEST_URI'] ?? ''); ?>">
        </form>
    <?php endif; ?>

    <form id="profileEditForm" class="pe-app" method="post" enctype="multipart/form-data" action="/api/update_profile.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo pe_h($csrf); ?>">
        <input type="hidden" name="target_user_id" value="<?php echo $targetUserId; ?>">
        <?php foreach (['socials_json', 'links_json', 'projects_json', 'contents_json', 'blocks_json', 'badges_json', 'characters_json', 'embeds_json', 'profile_tags_json', 'profile_sections_order', 'profile_sections_config', 'profile_stats_json'] as $hiddenJson): ?>
            <input type="hidden" name="<?php echo $hiddenJson; ?>" data-json-field="<?php echo $hiddenJson; ?>">
        <?php endforeach; ?>

        <!-- Barra in alto -->
        <header class="pe-topbar">
            <div class="pe-topbar-start">
                <a class="pe-icon-btn" href="<?php echo pe_h($profileUrl); ?>" title="<?php echo pe_h($tt('Torna al profilo', 'Back to profile')); ?>" data-leave-editor>
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span class="visually-hidden"><?php echo pe_h($tt('Torna al profilo', 'Back to profile')); ?></span>
                </a>
                <div class="pe-brand">
                    <strong><?php echo pe_h($tt('Editor profilo', 'Profile editor')); ?></strong>
                    <span class="pe-status" id="peStatus" data-state="saved" role="status" aria-live="polite">
                        <span class="pe-status-dot" aria-hidden="true"></span>
                        <span class="pe-status-text"><?php echo pe_h($tt('Tutto pubblicato', 'Everything published')); ?></span>
                    </span>
                </div>
            </div>

            <div class="pe-topbar-center">
                <button type="button" class="pe-icon-btn" id="peUndo" disabled title="<?php echo pe_h($tt('Annulla', 'Undo')); ?> (Ctrl+Z)"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
                <button type="button" class="pe-icon-btn" id="peRedo" disabled title="<?php echo pe_h($tt('Ripeti', 'Redo')); ?> (Ctrl+Y)"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
                <button type="button" class="pe-search-btn" id="peSearchOpen">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span><?php echo pe_h($tt('Cerca un\'impostazione', 'Find a setting')); ?></span>
                    <kbd>Ctrl K</kbd>
                </button>
            </div>

            <div class="pe-topbar-end">
                <div class="pe-device" role="group" aria-label="<?php echo pe_h($tt('Dispositivo anteprima', 'Preview device')); ?>">
                    <button type="button" class="is-active" data-device="desktop" title="Desktop"><i class="fa-solid fa-desktop" aria-hidden="true"></i></button>
                    <button type="button" data-device="mobile" title="Mobile"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></button>
                </div>
                <a class="pe-lang" href="<?php echo pe_h($switchLangUrl); ?>" data-leave-editor title="<?php echo pe_h($tt('Switch to English', 'Passa all\'italiano')); ?>"><?php echo $editorLang === 'it' ? 'EN' : 'IT'; ?></a>
                <?php if (!$isPremium): ?>
                    <button type="button" class="pe-btn pe-btn-ghost pe-premium-btn" data-open-upsell><i class="fa-solid fa-crown" aria-hidden="true"></i><span>Premium</span></button>
                <?php endif; ?>
                <a class="pe-btn pe-btn-ghost pe-hide-sm" href="<?php echo pe_h($profileUrl); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i><span><?php echo pe_h($tt('Vedi profilo', 'View profile')); ?></span></a>
                <button type="submit" class="pe-btn pe-btn-primary" id="pePublish">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span class="pe-publish-label"><?php echo pe_h($tt('Pubblica', 'Publish')); ?></span>
                    <span class="pe-publish-progress" aria-hidden="true"></span>
                </button>
            </div>
        </header>

        <!-- Navigazione fra le aree -->
        <nav class="pe-rail" aria-label="<?php echo pe_h($tt('Aree dell\'editor', 'Editor areas')); ?>">
            <?php foreach ($views as $viewKey => $view): ?>
                <button type="button" class="pe-rail-btn<?php echo $viewKey === 'profile' ? ' is-active' : ''; ?>" data-view="<?php echo $viewKey; ?>" aria-controls="view-<?php echo $viewKey; ?>">
                    <i class="<?php echo pe_h($view['icon']); ?>" aria-hidden="true"></i>
                    <span class="pe-rail-label"><?php echo pe_h($view['label']); ?></span>
                    <span class="pe-rail-short" aria-hidden="true"><?php echo pe_h($view['short'] ?? $view['label']); ?></span>
                </button>
            <?php endforeach; ?>
            <button type="button" class="pe-rail-btn pe-rail-preview" data-toggle-preview>
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                <span class="pe-rail-label"><?php echo pe_h($tt('Anteprima', 'Preview')); ?></span>
                <span class="pe-rail-short" aria-hidden="true"><?php echo pe_h($tt('Vedi', 'View')); ?></span>
            </button>
        </nav>

        <!-- Pannello dell'area -->
        <aside class="pe-panel" id="pePanel">
            <?php if (!$isPremium): ?>
                <div class="pe-plan-banner" id="pePlanBanner" hidden>
                    <i class="fa-solid fa-crown" aria-hidden="true"></i>
                    <p><?php echo pe_h($tt('Stai usando il piano Base. Le opzioni con la corona sono Premium.', 'You are on the Basic plan. Options with a crown are Premium.')); ?></p>
                    <button type="button" class="pe-link" data-open-upsell><?php echo pe_h($tt('Scopri', 'Learn more')); ?></button>
                    <button type="button" class="pe-icon-btn pe-icon-btn-sm" data-dismiss-banner aria-label="<?php echo pe_h($tt('Chiudi', 'Close')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
            <?php endif; ?>

            <?php foreach ($views as $viewKey => $view): ?>
                <section class="pe-view<?php echo $viewKey === 'profile' ? ' is-active' : ''; ?>" id="view-<?php echo $viewKey; ?>" data-view-panel="<?php echo $viewKey; ?>" <?php echo $viewKey === 'profile' ? '' : 'hidden'; ?>>
                    <header class="pe-view-head">
                        <h2><?php echo pe_h($view['title']); ?></h2>
                        <p><?php echo pe_h($view['desc']); ?></p>
                    </header>
                    <div class="pe-view-body">
                        <?php include __DIR__ . '/views/' . $viewKey . '.php'; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </aside>

        <!-- Anteprima -->
        <main class="pe-preview" id="pePreview" aria-label="<?php echo pe_h($tt('Anteprima del profilo', 'Profile preview')); ?>">
            <div class="pe-preview-stage">
                <div class="pe-frame is-desktop" id="peFrame">
                    <iframe id="profilePreviewIframe" title="<?php echo pe_h($tt('Anteprima del profilo', 'Profile preview')); ?>" src="/profile.php?id=<?php echo (int)$profile['id']; ?>&amp;preview_mode=1"></iframe>
                    <div class="pe-frame-loading" aria-hidden="true"><span></span></div>
                </div>
            </div>
            <button type="button" class="pe-preview-close" data-toggle-preview>
                <i class="fa-solid fa-pen" aria-hidden="true"></i><span><?php echo pe_h($tt('Torna a modificare', 'Back to editing')); ?></span>
            </button>
        </main>
    </form>

    <!-- Ricerca delle impostazioni -->
    <dialog class="pe-dialog pe-palette" id="pePalette" aria-label="<?php echo pe_h($tt('Cerca un\'impostazione', 'Find a setting')); ?>">
        <div class="pe-palette-box">
            <div class="pe-palette-input">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="pePaletteInput" placeholder="<?php echo pe_h($tt('Es. colore nome, bordi, musica…', 'E.g. name color, borders, music…')); ?>" autocomplete="off">
                <kbd>Esc</kbd>
            </div>
            <ul class="pe-palette-results" id="pePaletteResults" role="listbox"></ul>
        </div>
    </dialog>

    <!-- Proposta Premium: si apre solo quando serve -->
    <dialog class="pe-dialog pe-upsell" id="peUpsell" aria-labelledby="peUpsellTitle">
        <div class="pe-upsell-box">
            <button type="button" class="pe-icon-btn pe-dialog-close" data-close-dialog aria-label="<?php echo pe_h($tt('Chiudi', 'Close')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            <span class="pe-upsell-badge"><i class="fa-solid fa-crown" aria-hidden="true"></i> Premium</span>
            <h2 id="peUpsellTitle"><?php echo pe_h($tt('Sblocca tutta la personalizzazione', 'Unlock every customization')); ?></h2>
            <p class="pe-upsell-reason" id="peUpsellReason"></p>
            <ul class="pe-upsell-list">
                <li><i class="fa-solid fa-check" aria-hidden="true"></i><?php echo pe_h($tt('Link, social e blocchi senza limiti', 'Unlimited links, socials and blocks')); ?></li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i><?php echo pe_h($tt('Temi pronti e preset salvati', 'Ready-made themes and saved presets')); ?></li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i><?php echo pe_h($tt('Icone e immagini caricate ovunque', 'Upload icons and images everywhere')); ?></li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i><?php echo pe_h($tt('Font, effetti e cursori esclusivi', 'Exclusive fonts, effects and cursors')); ?></li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i><?php echo pe_h($tt('Blocchi Markdown e HTML, layout a schermate', 'Markdown and HTML blocks, full-screen layout')); ?></li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i><?php echo pe_h($tt('File più grandi: foto 10MB, sfondi 50MB', 'Bigger files: 10MB photos, 50MB backgrounds')); ?></li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i><?php echo pe_h($tt('25.000 Godos inclusi', '25,000 Godos included')); ?></li>
            </ul>
            <div class="pe-upsell-actions">
                <a class="pe-btn pe-btn-primary" href="/<?php echo $editorLang; ?>/checkout-premium.php" data-leave-editor><?php echo pe_h($tt('Passa a Premium · €2.99 una tantum', 'Go Premium · €2.99 one-time')); ?></a>
                <button type="button" class="pe-btn pe-btn-ghost" data-close-dialog><?php echo pe_h($tt('Non ora', 'Not now')); ?></button>
            </div>
        </div>
    </dialog>

    <div class="pe-toasts" id="peToasts" aria-live="polite"></div>
    <div class="pe-uploads" id="peUploads" aria-live="polite"></div>
</body>

</html>
