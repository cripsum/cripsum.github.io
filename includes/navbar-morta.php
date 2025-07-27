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
        <a class="navbar-brand" href="">
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

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

            </ul>
        </div>
    </div>
</nav>
