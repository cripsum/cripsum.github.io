<?php
require_once '../config/session_init.php';
require_once '../includes/theme.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include '../includes/head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <title>Cripsum™ - Chat Policy</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/assets/static/static.css') ?>">
    <script src="/assets/static/static.js?v=1.0-static" defer></script>

</head>

<body class="static-page">
    <?php include '../includes/navbar.php'; ?>

    <div class="static-bg" aria-hidden="true">
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
                <p>The same rules apply. Staff do not read these chats: they only see the messages a participant reports. If someone bothers you, you can report the message from its menu, block the person and, if needed, open a ticket.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Messages from people you do not know</h2>
                <p>People who are not your friends write to you as a request: until you accept or reply they can only send a few messages. In the chat Privacy settings you can choose to be messaged by friends only.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Report</h2>
                <p>Use «Report» on messages, in the global chat as well as in private and group chats. Every report is reviewed by a staff member; the author of the message is not notified.</p>
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