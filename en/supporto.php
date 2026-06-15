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
    <title>Cripsum™ - Help & Support</title>

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
                <span class="static-pill">Support</span>
                <h1>Need help?</h1>
                <p>Find contact info and quick answers to the most common issues here.</p>

                <?php if ($isLogged): ?>
                    <div class="static-alert static-alert--success" style="margin-top:1rem;">
                        <i class="fa-solid fa-user-check"></i>
                        <p>You are writing as <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong>. If you report an issue, please include your username.</p>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fa-solid fa-clock"></i> Response is not immediate</span>
                <p>Write clearly. It helps us resolve things faster.</p>
            </aside>
        </section>

        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-contact-card static-reveal">
                <h2>Contacts</h2>
                <div class="static-grid">
                    <a href="mailto:sburra@cripsum.com" class="static-contact-link">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Email: sburra@cripsum.com</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fa-brands fa-telegram"></i>
                        <span>Telegram: @cripsum</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fa-brands fa-discord"></i>
                        <span>Discord: @cripsum</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fa-brands fa-instagram"></i>
                        <span>Instagram: @cripsum</span>
                    </a>
                </div>
            </article>

            <article class="static-card static-reveal">
                <h2>Before writing</h2>
                <ul>
                    <li>Explain what you were doing.</li>
                    <li>Attach a screenshot if needed.</li>
                    <li>Mention your browser, device, and the page involved.</li>
                    <li>If you have an account, include your username.</li>
                </ul>
            </article>
        </section>

        <section class="static-faq static-reveal" id="supportFaq" style="margin-top:1rem;">
            <h2>Quick FAQ</h2>

            <label class="static-faq-search">
                <i class="fa-solid fa-search"></i>
                <input type="search" placeholder="Search the FAQ..." data-static-faq-search="#supportFaq">
            </label>

            <details class="static-faq-item">
                <summary>I can't log in</summary>
                <p>Try password recovery. If you don't receive an email, check your spam folder or contact support.</p>
            </details>

            <details class="static-faq-item">
                <summary>The lootbox isn't saving something</summary>
                <p>Make sure you are logged in. Then try refreshing and check if your session has expired.</p>
            </details>

            <details class="static-faq-item">
                <summary>I see a visual bug</summary>
                <p>Do a hard refresh and clear your cache. If it persists, send a screenshot and the page name.</p>
            </details>

            <details class="static-faq-item">
                <summary>How do I report a user?</summary>
                <p>Use the on-page reporting tools, if available. Otherwise, contact support with their username and reason.</p>
            </details>

            <p class="static-empty" data-static-faq-empty>No results found.</p>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>