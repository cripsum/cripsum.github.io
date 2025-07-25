<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$uri = $_SERVER['REQUEST_URI'];
$lang = explode('/', trim($uri, '/'))[0];

if (!in_array($lang, ['it', 'en'])) {
    $lang = 'it'; 
}

if ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'Utente';
    $userId = $_SESSION['user_id'];
    $profilePic = "/includes/get_pfp.php?id=$userId";
    $ruolo = $_SESSION['ruolo'] ?? '';
    $nsfw = $_SESSION['nsfw'] ?? 0; 
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
            style="z-index: 1000"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/home"><i class="fas fa-arrow-left"></i> Torna a Cripsum™</a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/home">Home Page</a></li>
                <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/goonland/goon-generator">Goon Generator</a></li>
                <li class="nav-item"><a class="nav-link" href="">Coming Soon...</a></li>
                <!--<li class="nav-item"><a class="nav-link" href="">Coming Soon...</a></li>
                <li class="nav-item"><a class="nav-link" href="">Coming Soon...</a></li>-->
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/accedi">Accedi</a></li>
                    <li class="nav-item"><a class="nav-link" href="/<?= $lang ?>/registrati">Registrati</a></li>
                <?php else: ?>
                    <li class="nav-item dropdown dropdownutenti">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profilo" 
                                 class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <li><a class="dropdown-item" href="/<?= $lang ?>/profilo"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/impostazioni"><i class="fas fa-cog me-2"></i>Impostazioni</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/ordini"><i class="fas fa-shopping-bag me-2"></i>I miei ordini</a></li>
                            <li><a class="dropdown-item" href="/<?= $lang ?>/global-chat"><i class="fas fa-envelope me-2"></i>Chat Globale</a></li>
                            <?php if ($nsfw === 1): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/goonland/home"><i class="fas fa-eye-slash me-2"></i>GoonLand</a></li>
                            <?php endif; ?>
                            <?php if ($ruolo === 'admin' || $ruolo === 'owner'): ?>
                                <li><a class="dropdown-item" href="/<?= $lang ?>/admin"><i class="fas fa-shield-alt me-2"></i>Pannello Admin</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="https://cripsum.com/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="btn-group ms-auto me-3 linguanuova">
            <button type="button" class="btn impostazioni-toggler" data-bs-toggle="modal" data-bs-target="#impostazioniModal">
                <img src="/img/settings-icon.svg" alt="Impostazioni" style="width: 25px" class="imgbianca" />
            </button>
        </div>
    </div>
</nav>
