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
    <title>Cripsum™ - Terms</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/static/static.css?v=1.2-static">
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
                <span class="static-pill">Terms</span>
                <h1>Terms of Service</h1>
                <p>By using Cripsum™ you agree to these rules.</p>
                <div class="static-meta">
                    <span class="static-chip"><i class="fa-solid fa-calendar"></i> Updated: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="static-chip"><i class="fa-solid fa-scale-balanced"></i> Site Rules</span>
                </div>
            </div>
            <div class="static-hero__logo-container">
                <img src="/img/tos.gif" alt="Cripsum™ TOS Logo" class="static-tos-logo">
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Table of Contents</h2>
                <a href="#uso">Use of Services</a>
                <a href="#account">User Accounts</a>
                <a href="#eta">Age & Responsibility</a>
                <a href="#proprieta">Intellectual Property</a>
                <a href="#responsabilita">Liability</a>
                <a href="#modifiche">Modifications</a>
                <a href="#contatti">Contact</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="uso">
                    <h2>Use of Services</h2>
                    <p>You must use Cripsum™ only for lawful purposes and in compliance with all applicable laws.</p>
                    <ul>
                        <li>Do not use the site for any illegal or unauthorized activities.</li>
                        <li>Do not send spam, harmful content, or engage in behavior that negatively impacts the experience of other users.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="account">
                    <h2>User Accounts</h2>
                    <p>If you create an account, you are responsible for maintaining the security of your credentials and for all activities that occur under your account.</p>
                </section>

                <section class="static-legal-section static-reveal" id="eta">
                    <h2>Age & Responsibility</h2>
                    <p>By declaring that you are 18 years of age or older, you confirm that you are of legal age according to the laws of your country and are responsible for your actions.</p>
                    <p>If you are a minor, you must have the consent of a parent or legal guardian before using our services.</p>
                    <p>The user is solely responsible for their use of the services and any consequences that may arise.</p>
                </section>

                <section class="static-legal-section static-reveal" id="proprieta">
                    <h2>Intellectual Property</h2>
                    <p>All text, images, logos, and software on Cripsum™ belong to Cripsum™ or their respective owners and are protected by copyright laws.</p>
                </section>

                <section class="static-legal-section static-reveal" id="responsabilita">
                    <h2>Limitation of Liability</h2>
                    <p>Cripsum™ is not liable for any direct, indirect, incidental, or consequential damages resulting from the use of our services.</p>
                </section>

                <section class="static-legal-section static-reveal" id="modifiche">
                    <h2>Modifications to Terms</h2>
                    <p>We may modify these terms at any time. Changes will be published on the site and, if significant, communicated via email.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>Contact</h2>
                    <p>For any questions regarding these terms, you can write to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>.</p>
                </section>
            </div>
        </div>
    </main>

    <button class="static-top-btn" id="staticBackTop" type="button" aria-label="Torna su">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>