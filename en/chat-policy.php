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
    <link rel="stylesheet" href="/assets/static/static.css?v=1.3-static">
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
                <h1>Chat Policy</h1>
                <p>Simple rules to keep the chat readable and safe for everyone. They are part of the <a href="tos">Terms of Service</a>.</p>
                <div class="static-actions">
                    <a href="global-chat" class="static-btn static-btn--primary">
                        <i class="fa-solid fa-comments"></i>
                        <span>Back to chat</span>
                    </a>
                    <a href="supporto" class="static-btn">
                        <i class="fa-solid fa-life-ring"></i>
                        <span>Support</span>
                    </a>
                </div>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fa-solid fa-shield-halved"></i> Active moderation</span>
                <p>Serious or repeated violations may result in mutes, suspensions or bans.</p>
            </aside>
        </section>

        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-card static-reveal">
                <h2>Respect everyone</h2>
                <p>No insults, threats, discrimination or offensive language.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>No bullying</h2>
                <p>Targeting someone is not allowed, not even «as a joke». If it happens to you, write to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> with the subject «Cyberbullying»: we reply within 24 hours.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>No other people's data</h2>
                <p>Do not post photos, phone numbers, addresses or other data of other people without their consent.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Appropriate content</h2>
                <p>There are minors in the chat too: no sexual, violent, illegal or clearly out-of-context content.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>No spam</h2>
                <p>Avoid repeated messages, advertising and random links.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Safe attachments</h2>
                <p>No malware, suspicious links or copyrighted material without permission.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>No impersonation</h2>
                <p>Do not pretend to be another user, an admin, a moderator or a real person.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Private and group chats</h2>
                <p>The same rules apply. Staff do not read these chats: if someone bothers you, block them and, if needed, open a ticket.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Report</h2>
                <p>Use «Report» on global chat messages. Every report is reviewed by a staff member.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Follow the moderators</h2>
                <p>Staff instructions must be followed. If you disagree with a decision, you can appeal it as explained in the Terms.</p>
            </article>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>