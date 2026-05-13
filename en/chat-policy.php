<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Chat Policy</title>

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
                <span class="static-pill">Global Chat</span>
                <h1>Chat Policy</h1>
                <p>Simple rules to keep the chat readable and usable for everyone.</p>
                <div class="static-actions">
                    <a href="global-chat" class="static-btn static-btn--primary">
                        <i class="fas fa-comments"></i>
                        <span>Back to chat</span>
                    </a>
                    <a href="supporto" class="static-btn">
                        <i class="fas fa-life-ring"></i>
                        <span>Support</span>
                    </a>
                </div>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fas fa-shield-halved"></i> Active moderation</span>
                <p>Serious or repeated violations may result in mutes or bans.</p>
            </aside>
        </section>

        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-card static-reveal">
                <h2>Respect everyone</h2>
                <p>No insults, threats, or offensive language.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>No spam</h2>
                <p>Avoid repeated messages, advertising, and random links.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Appropriate content</h2>
                <p>Do not send violent, sexual, illegal, or clearly out-of-context content.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>No impersonation</h2>
                <p>Do not pretend to be another user, an admin, or a moderator.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Use common sense</h2>
                <p>You don't have to be perfect. Just don't ruin the chat for others.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Follow the moderators</h2>
                <p>Staff instructions must be followed.</p>
            </article>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>