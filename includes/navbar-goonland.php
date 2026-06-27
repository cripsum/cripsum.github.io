<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/mission_tracker.php';
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

$unreadCount = 0;
if (isLoggedIn()) {
    trackDailyLogin($mysqli, (int)$_SESSION['user_id']);
    trackMissionProgress($mysqli, (int)$_SESSION['user_id'], 'view_page');
    if (isset($mysqli)) {
        $unreadCount = getUnreadMessagesCount($mysqli, $_SESSION['user_id']);
    }
}
$uri = $_SERVER['REQUEST_URI'];
$lang = explode('/', trim($uri, '/'))[0];

if (!in_array($lang, ['it', 'en'])) {
    $lang = 'it';
}

// Dizionario delle traduzioni per GoonLand
$t = [
    'it' => [
        'back_cripsum' => 'Torna a Cripsum™',
        'home_page'    => 'Home Page',
        'goon_gen'     => 'Goon Generator',
        'waifu_quiz'   => 'Waifu Quiz',
        'smash_pass'   => 'Smash or Pass',
        'coming_soon'  => 'Coming Soon...',
        'login'        => 'Accedi',
        'register'     => 'Registrati',
        'my_profile'   => 'Il mio profilo',
        'settings'     => 'Impostazioni',
        'inventory'    => 'Inventario',
        'global_chat'  => 'Chat Globale',
        'admin_panel'  => 'Pannello Admin',
        'my_profile_alt' => 'Profilo',
        'missions'     => 'Missioni',
    ],
    'en' => [
        'back_cripsum' => 'Back to Cripsum™',
        'home_page'    => 'Home Page',
        'goon_gen'     => 'Goon Generator',
        'waifu_quiz'   => 'Waifu Quiz',
        'smash_pass'   => 'Smash or Pass',
        'coming_soon'  => 'Coming Soon...',
        'login'        => 'Log in',
        'register'     => 'Sign up',
        'my_profile'   => 'My profile',
        'settings'     => 'Settings',
        'inventory'    => 'Inventory',
        'global_chat'  => 'Global Chat',
        'admin_panel'  => 'Admin Panel',
        'my_profile_alt' => 'Profile',
        'missions'     => 'Missions',
    ],
][$lang];

// Language switcher: swap /it/ ↔ /en/ in the current URL
$altLang   = ($lang === 'it') ? 'en' : 'it';
$altLabel  = ($lang === 'it') ? 'EN' : 'IT';
$curLabel  = strtoupper($lang);
$switchUrl = preg_replace('#^/' . $lang . '(/|$)#', '/' . $altLang . '$1', $uri);

if ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'Utente';
    $userId = $_SESSION['user_id'];
    $profilePic = "/includes/get_pfp.php?id=$userId";
    $ruolo = $_SESSION['ruolo'] ?? '';
    $nsfw = $_SESSION['nsfw'] ?? 0;
    $richpresence = $_SESSION['richpresence'] ?? 0;
}
?>

<nav class="navbarutenti navbar navbar-expand-xl fadein">
    <div class="container-fluid">
        <a class="navbar-brand" href="/<?= $lang ?>/home">
            <img src="/img/amongus-logo.jpg" height="40px" style="border-radius: 4px" class="d-inline-block align-middle" />
            <span class="align-middle ms-3 fw-bold testobianco">Cripsum™</span>
        </a>

        <!-- Mobile actions (visible on mobile, hidden on desktop) -->
        <div class="navbar-mobile-actions d-xl-none">
            <a href="<?= htmlspecialchars($switchUrl) ?>"
                class="lang-switch"
                aria-label="Switch language to <?= $altLabel ?>"
                title="Switch to <?= $altLabel ?>">
                <span class="lang-switch__cur"><?= $curLabel ?></span>
                <span class="lang-switch__sep">·</span>
                <span class="lang-switch__alt"><?= $altLabel ?></span>
            </a>

            <?php if ($isLoggedIn): ?>
                <a href="/<?= $lang ?>/inbox" class="nav-inbox-link-mobile position-relative" aria-label="Inbox" title="Inbox">
                    <i class="fa-solid fa-envelope"></i>
                    <span id="inbox-unread-count-mobile" class="badge bg-danger position-absolute translate-middle rounded-pill <?= ($unreadCount > 0) ? '' : 'd-none' ?>">
                        <?= $unreadCount ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent"
            aria-expanded="false"
            aria-label="Toggle navigation"
            style="z-index: 1000">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/home"><i class="fa-solid fa-arrow-left"></i> <?= $t['back_cripsum'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/home"><i class="fa-solid fa-home"></i> <?= $t['home_page'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/goon-generator"><i class="fa-solid fa-gears"></i> <?= $t['goon_gen'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/anime-girl-quiz"><i class="fa-solid fa-circle-question"></i> <?= $t['waifu_quiz'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/smash-or-pass"><i class="fa-solid fa-heart"></i> <?= $t['smash_pass'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href=""><i class="fa-solid fa-clock"></i> <?= $t['coming_soon'] ?></a></li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item d-none d-xl-flex align-items-center me-2 lang-switch-item">
                    <a href="<?= htmlspecialchars($switchUrl) ?>"
                        class="lang-switch"
                        aria-label="Switch language to <?= $altLabel ?>"
                        title="Switch to <?= $altLabel ?>">
                        <span class="lang-switch__cur"><?= $curLabel ?></span>
                        <span class="lang-switch__sep">·</span>
                        <span class="lang-switch__alt"><?= $altLabel ?></span>
                    </a>
                </li>
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item nav-auth-group">
                        <a class="nav-link" href="/<?= $lang ?>/accedi"><i class="fa-solid fa-right-to-bracket"></i> <?= $t['login'] ?></a>
                        <a class="nav-link" href="/<?= $lang ?>/registrati"><i class="fa-solid fa-user-plus"></i> <?= $t['register'] ?></a>
                    </li>
                <?php else: ?>
                    <!-- ══ CENTRO MESSAGGI (INBOX) ══ -->
                    <li class="nav-item d-none d-xl-flex align-items-center ms-2 me-1 inbox-item" style="position: relative;">
                        <a href="/<?= $lang ?>/inbox" class="nav-link nav-inbox-link d-flex align-items-center position-relative" aria-label="Inbox" title="Inbox">
                            <i class="fa-solid fa-envelope"></i>
                            <span id="inbox-unread-count" class="badge bg-danger position-absolute translate-middle rounded-pill <?= ($unreadCount > 0) ? '' : 'd-none' ?>">
                                <?= $unreadCount ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item dropdown dropdownutenti dropdownprofilo">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>&t=<?php echo time(); ?>" alt="<?= $t['my_profile_alt'] ?>"
                                class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <li><a class="dropdown-item" href="/u/<?php echo htmlspecialchars($username); ?>"><i class="fa-solid fa-user me-2"></i><?= $t['my_profile'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/impostazioni"><i class="fa-solid fa-gear me-2"></i><?= $t['settings'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/achievements"><i class="fa-solid fa-trophy me-2"></i>Achievements</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/missions"><i class="fa-solid fa-bullseye me-2"></i><?= $t['missions'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/inventario"><i class="fa-solid fa-box me-2"></i><?= $t['inventory'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/global-chat"><i class="fa-solid fa-envelope me-2"></i><?= $t['global_chat'] ?></a></li>
                            <?php if ($nsfw === 1): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/goonland/home"><i class="fa-solid fa-eye-slash me-2"></i>GoonLand</a></li>
                            <?php endif; ?>
                            <?php if ($ruolo === 'admin' || $ruolo === 'owner'): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/admin"><i class="fa-solid fa-shield-halved me-2"></i><?= $t['admin_panel'] ?></a></li>
                            <?php endif; ?>
                            <li class="grid-span-2">
                                <hr class="dropdown-divider">
                            </li>
                            <li class="grid-span-2"><a class="dropdown-item text-danger" href="https://cripsum.com/logout"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($richpresence === 1): ?>
    <script>
        window.addEventListener('load', function() {
            var script = document.createElement('script');
            script.src = '/js/richpresence.js';
            document.head.appendChild(script);
        });
    </script>
<?php endif; ?>
<?php if ($isLoggedIn): ?>
    <script>
        window.addEventListener('load', function() {
            var script = document.createElement('script');
            script.src = '/js/unlockAchievement-it.js?v=2';
            document.head.appendChild(script);
        });
    </script>
    <script>
        window.addEventListener('load', function() {
            var script = document.createElement('script');
            script.src = '/js/achievements-globali.js?v=3';
            document.head.appendChild(script);
        });
    </script>
<?php endif; ?>

<style>
    .lang-switch {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 20px;
        text-decoration: none;
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .06em;
        color: rgba(255, 255, 255, 0.55);
        transition: border-color .2s, color .2s, background .2s;
        white-space: nowrap;
    }

    .lang-switch:hover {
        border-color: rgba(255, 255, 255, 0.45);
        color: #fff;
        background: rgba(255, 255, 255, 0.07);
    }

    .lang-switch__cur {
        color: #fff;
        font-weight: 700;
    }

    .lang-switch__sep {
        color: rgba(255, 255, 255, 0.25);
        font-weight: 400;
    }

    @media (max-width: 1199.98px) {
        .lang-switch {
            margin: 6px 0 2px 0;
        }
    }
</style>

<script>
    // Safeguard: Load Bootstrap JS bundle dynamically if it's missing on the page
    window.addEventListener('DOMContentLoaded', function() {
        if (typeof bootstrap === 'undefined') {
            console.log('Bootstrap JS missing, loading dynamically...');
            var script = document.createElement('script');
            script.src = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js";
            script.crossOrigin = "anonymous";
            document.head.appendChild(script);
        }
    });

    // Sync desktop and mobile inbox unread badges
    (function() {
        const desktopBadge = document.getElementById('inbox-unread-count');
        const mobileBadge = document.getElementById('inbox-unread-count-mobile');
        if (desktopBadge && mobileBadge) {
            const syncBadges = () => {
                mobileBadge.textContent = desktopBadge.textContent;
                if (desktopBadge.classList.contains('d-none')) {
                    mobileBadge.classList.add('d-none');
                } else {
                    mobileBadge.classList.remove('d-none');
                }
            };
            syncBadges();
            const observer = new MutationObserver(syncBadges);
            observer.observe(desktopBadge, { attributes: true, childList: true, characterData: true });
        }
    })();
</script>