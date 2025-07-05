<?php

session_start();
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

if ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'Utente';
    $profilePic = $_SESSION['profile_pic'] ?? '../img/abdul.jpg';
    $userId = $_SESSION['user_id'];
}
?>

<nav class="navbarutenti navbar navbar-expand-xl fadein">
    <div class="container-fluid">
        <a class="navbar-brand" href="home">
            <img src="../img/amongus.jpg" height="40px" style="border-radius: 4px" class="d-inline-block align-middle" />
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
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="home">Home page</a>
                </li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Memes</a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="shitpost">Shitpost</a></li>
                        <li><a class="dropdown-item" href="tiktokpedia">TikTokPedia</a></li>
                        <li><a class="dropdown-item" href="rimasti">Top rimasti</a></li>
                        <li><a class="dropdown-item" href="quandel57" style="color: red; font-weight: bold">Quandel57</a></li>
                        <li><a class="dropdown-item arcobalenos" href="gambling" style="font-weight: bold">Gambling!!</a></li>
                        <li><a class="dropdown-item testo-arcobaleno" href="../lootbox" style="font-weight: bold">Lootbox</a></li>
                        <li><a class="dropdown-item" href="achievements">Achievements</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="download">Downloads</a>
                </li>
                <li class="nav-item dropdown dropdownutenti">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Shop</a>
                    <ul class="dropdown-menu animate slideIn">
                        <li><a class="dropdown-item" href="negozio">Negozio</a></li>
                        <li><a class="dropdown-item" href="merch">Merch</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="donazioni">Donazioni</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="chisiamo">Chi siamo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="edits">Edits</a>
                </li>
            </ul>
            
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="accedi">Accedi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="registrati">Registrati</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item dropdown dropdownutenti">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profilo" 
                                 class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                            <span><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <li><a class="dropdown-item" href="profilo">
                                <i class="fas fa-user me-2"></i>Il mio profilo
                            </a></li>
                            <li><a class="dropdown-item" href="impostazioni">
                                <i class="fas fa-cog me-2"></i>Impostazioni
                            </a></li>
                            <li><a class="dropdown-item" href="ordini">
                                <i class="fas fa-shopping-bag me-2"></i>I miei ordini
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="https://cripsum.com/logout">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="btn-group ms-auto me-3 linguanuova">
        <button type="button" class="btn impostazioni-toggler" data-bs-toggle="modal" data-bs-target="#impostazioniModal" aria-expanded="false">
            <img src="../img/settings-icon.svg" alt="" style="width: 25px" class="imgbianca" />
        </button>
    </div>
</nav>