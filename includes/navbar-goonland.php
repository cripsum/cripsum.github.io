<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
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
            <img src="/img/amongus.jpg" height="40px" style="border-radius: 4px" class="d-inline-block align-middle" />
            <span class="align-middle ms-3 fw-bold testobianco">Cripsum™</span>
        </a>

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
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/home"><i class="fas fa-arrow-left"></i> <?= $t['back_cripsum'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/home"><i class="fas fa-home"></i> <?= $t['home_page'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/goon-generator"><i class="fas fa-cogs"></i> <?= $t['goon_gen'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/anime-girl-quiz"><i class="fas fa-question-circle"></i> <?= $t['waifu_quiz'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/smash-or-pass"><i class="fas fa-heart"></i> <?= $t['smash_pass'] ?></a></li>
                <li class="nav-item"><a class="nav-link" href=""><i class="fas fa-clock"></i> <?= $t['coming_soon'] ?></a></li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item d-flex align-items-center me-2">
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
                    <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/accedi"><i class="fas fa-sign-in-alt"></i> <?= $t['login'] ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/registrati"><i class="fas fa-user-plus"></i> <?= $t['register'] ?></a></li>
                <?php else: ?>
                    <li class="nav-item dropdown dropdownutenti">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>&t=<?php echo time(); ?>" alt="<?= $t['my_profile_alt'] ?>"
                                class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <li><a class="dropdown-item" href="/u/<?php echo htmlspecialchars($username); ?>"><i class="fas fa-user me-2"></i><?= $t['my_profile'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/impostazioni"><i class="fas fa-cog me-2"></i><?= $t['settings'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/achievements"><i class="fas fa-trophy me-2"></i>Achievements</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/inventario"><i class="fas fa-box me-2"></i><?= $t['inventory'] ?></a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/global-chat"><i class="fas fa-envelope me-2"></i><?= $t['global_chat'] ?></a></li>
                            <?php if ($nsfw === 1): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/goonland/home"><i class="fas fa-eye-slash me-2"></i>GoonLand</a></li>
                            <?php endif; ?>
                            <?php if ($ruolo === 'admin' || $ruolo === 'owner'): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/admin"><i class="fas fa-shield-alt me-2"></i><?= $t['admin_panel'] ?></a></li>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="https://cripsum.com/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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