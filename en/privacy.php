<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = 'April 2026';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Privacy</title>

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
        <section class="static-hero static-reveal">
            <span class="static-pill">Privacy</span>
            <h1>Privacy Policy</h1>
            <p>Here you can find out how personal information is handled on Cripsum™.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fa-solid fa-calendar"></i> Updated: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fa-solid fa-shield-halved"></i> Data & Security</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Table of Contents</h2>
                <a href="#raccolta">Information Collected</a>
                <a href="#uso">Use of Information</a>
                <a href="#divulgazione">Disclosure</a>
                <a href="#protezione">Protection</a>
                <a href="#diritti">Your Rights</a>
                <a href="#contatti">Contact</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="raccolta">
                    <h2>Information Collected</h2>
                    <p>We only collect the information necessary to operate the site and your account.</p>
                    <ul>
                        <li>Information provided by the user, such as username and email address.</li>
                        <li>Technical information collected automatically, such as IP address, browser type, and session data.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="uso">
                    <h2>Use of Information</h2>
                    <p>We use information to manage the site, improve our services, and communicate with you when necessary.</p>
                    <ul>
                        <li>To provide and improve site features.</li>
                        <li>To manage accounts, logins, and security.</li>
                        <li>To personalize parts of the user experience.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="divulgazione">
                    <h2>Disclosure of Information</h2>
                    <p>We do not sell your personal data. We may only share it if necessary to comply with legal obligations or to protect our rights.</p>
                </section>

                <section class="static-legal-section static-reveal" id="protezione">
                    <h2>Protection of Information</h2>
                    <p>We use technical and organizational measures to protect personal information from unauthorized access.</p>
                </section>

                <section class="static-legal-section static-reveal" id="diritti">
                    <h2>Your Rights</h2>
                    <p>You may request access to, correction of, or deletion of your personal data, within the limits provided by law.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>Contact</h2>
                    <p>For any privacy-related questions, you can write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
                </section>
            </div>
        </div>
    </main>

    <button class="static-top-btn" id="staticBackTop" type="button" aria-label="Back to top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>