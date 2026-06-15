<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$isLogged = function_exists('isLoggedIn') && isLoggedIn();
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Donations</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/static/static.css?v=1.0-static">
    <script src="/assets/static/static.js?v=1.0-static" defer></script>

</head>

<body class="static-page">
    <?php include '../includes/navbar.php'; ?>



    <div class="static-bg" aria-hidden="true">
        <span class="static-orb static-orb--one"></span>
        <span class="static-orb static-orb--two"></span>
        <span class="static-grid-bg"></span>
    </div>


    <main class="static-shell">
        <section class="static-hero static-hero--split static-reveal">
            <div>
                <span class="static-pill">Donations</span>
                <h1>Support Cripsum™</h1>
                <p>Donations help keep the site online and bring new features. They are completely optional.</p>

                <?php if ($isLogged): ?>
                    <div class="static-meta">
                        <span class="static-chip"><i class="fa-solid fa-user"></i> Thank you, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="static-actions">
                    <a href="https://www.buymeacoffee.com/cripsum" target="_blank" rel="noopener" class="static-btn static-btn--primary" onclick="unlockAchievement(4)">
                        <i class="fa-solid fa-heart"></i>
                        <span>Donate on BuyMeACoffee</span>
                    </a>
                    <a href="supporto" class="static-btn">
                        <i class="fa-solid fa-circle-question"></i>
                        <span>Questions?</span>
                    </a>
                </div>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fa-solid fa-mug-hot"></i> Optional</span>
                <p>The site remains usable even without donating.</p>

                <div class="static-donation-button">
                    <a href="https://www.buymeacoffee.com/cripsum" target="_blank" rel="noopener" onclick="unlockAchievement(4)">
                        <img
                            src="https://img.buymeacoffee.com/button-api/?text=Buy me a coffee :P&emoji=☕&slug=cripsum&button_colour=FFDD00&font_colour=000000&font_family=Poppins&outline_colour=000000&coffee_colour=ffffff"
                            alt="Buy me a coffee">
                    </a>
                </div>
            </aside>
        </section>

        <section class="static-grid static-grid--3" style="margin-top:1rem;">
            <article class="static-card static-reveal">
                <h2>Server</h2>
                <p>Helps cover hosting, domain, and services used by the site.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Time</h2>
                <p>The site requires maintenance, fixes, and new ideas.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Free</h2>
                <p>It is not mandatory. It is just a way to give support.</p>
            </article>
        </section>

        <section class="static-faq static-reveal" id="donationFaq" style="margin-top:1rem;">
            <h2>FAQ donations</h2>

            <details class="static-faq-item">
                <summary>Do I have to donate to use the site?</summary>
                <p>No. Donation is optional.</p>
            </details>

            <details class="static-faq-item">
                <summary>Are donations refundable?</summary>
                <p>It depends on the platform used to donate. Check the terms of BuyMeACoffee.</p>
            </details>

            <details class="static-faq-item">
                <summary>Do I receive anything in return?</summary>
                <p>It is not a purchase. It is support for the project. Any badges or bonuses are managed separately.</p>
            </details>
        </section>
    </main>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <?php include '../includes/footer-en.php'; ?>
    <script src="../js/unlockAchievement-it.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>