<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = 'June 2026';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Terms of Service</title>

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
                <p>Official terms and conditions for using the Cripsum™ platform.</p>
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
                <a href="#accettazione">1. Acceptance of Terms</a>
                <a href="#eta">2. Age Requirements</a>
                <a href="#account">3. Registration & Security</a>
                <a href="#discord">4. Discord Integration</a>
                <a href="#pagamenti">5. Payments & Virtual Currencies</a>
                <a href="#premium">6. Premium Subscription</a>
                <a href="#giochi">7. Gacha, Lootboxes & Duels</a>
                <a href="#contenuti">8. User-Generated Content</a>
                <a href="#ticket">9. Support Tickets & Chat</a>
                <a href="#moderazione">10. Suspension & Termination</a>
                <a href="#responsabilita">11. Limitation of Liability</a>
                <a href="#manleva">12. Indemnification</a>
                <a href="#modifiche">13. Modifications to Terms</a>
                <a href="#contatti">14. Contact</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="accettazione">
                    <h2>1. Acceptance of Terms</h2>
                    <p>By accessing or using the Cripsum™ website (hereinafter "Platform" or "Service"), you agree to be bound by these Terms and Conditions. If you do not agree to all terms set forth herein, you are not authorized to use the Platform.</p>
                </section>

                <section class="static-legal-section static-reveal" id="eta">
                    <h2>2. Age Requirements</h2>
                    <p>The Platform is intended for users who are at least 18 years of age. By declaring that you are 18 years of age or older upon registration, you confirm that you are of legal age under the laws of your country of residence and assume full legal responsibility for your actions on the Platform.</p>
                    <p>If you are under 18 years of age, you may use the Services only under the supervision and with the explicit consent of a parent or legal guardian, who assumes responsibility for the minor's actions.</p>
                </section>

                <section class="static-legal-section static-reveal" id="account">
                    <h2>3. Registration and Account Security</h2>
                    <p>To access certain features, you must create an account. You are solely responsible for maintaining the confidentiality of your login credentials (username and password) and for any activity that occurs under your account.</p>
                    <p>You agree to immediately notify the Cripsum™ team of any unauthorized use or breach of security of your account. Cripsum™ will not be liable for any loss or damage arising from your failure to comply with this obligation.</p>
                </section>

                <section class="static-legal-section static-reveal" id="discord">
                    <h2>4. Discord Connection and Presence Bot</h2>
                    <p>The Platform offers optional integration with Discord by linking your account (storing your `discord_id`). By linking your account and authorizing our Presence Bot, you agree that data regarding your Discord activity (online status, currently running games, presence details) will be displayed publicly within your Cripsum™ profile.</p>
                    <p>You are free to revoke this authorization at any time by unlinking your Discord account in your profile settings.</p>
                </section>

                <section class="static-legal-section static-reveal" id="pagamenti">
                    <h2>5. Payments, Purchases, and Virtual Currencies</h2>
                    <p>The Platform allows the purchase of virtual currency called <strong>"Godo Shards"</strong> and the acquisition of <strong>"Godos"</strong> (game points). These currencies are purely virtual items intended for entertainment within the Platform:</p>
                    <ul>
                        <li>Godo Shards and Godos <strong>do not constitute real money</strong>, have no monetary value, and can under no circumstances be converted, redeemed, or exchanged for real currency or other physical goods.</li>
                        <li>Payments are processed securely through the third-party platform **PayPal**. You agree to comply with PayPal's terms and conditions during transactions.</li>
                        <li><strong>Refund Policy:</strong> All purchases of Godo Shards and digital services are final and non-refundable. Pursuant to Article 59, letter o) of the Italian Consumer Code (Legislative Decree 206/2005) and EU consumer protection directives, the right of withdrawal is excluded as this concerns the supply of digital content not supplied on a tangible medium, the performance of which begins immediately after payment.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="premium">
                    <h2>6. Premium Subscription</h2>
                    <p>Users can purchase **Premium** status to unlock aesthetic perks and exclusive features on the Platform. The benefits associated with Premium are described on the respective purchase pages and are subject to unilateral changes or updates by the Cripsum™ team.</p>
                    <p>In the event of account suspension or ban for violating these Terms, Premium status will be revoked immediately without any right to a partial or full refund of the remaining period.</p>
                </section>

                <section class="static-legal-section static-reveal" id="giochi">
                    <h2>7. Gacha, Lootboxes, and Duels (Game Mechanics)</h2>
                    <p>The Platform includes game mechanics based on probability and luck algorithms, such as character recruitment (Gacha), chest openings (Lootboxes), and virtual battles (Duels):</p>
                    <ul>
                        <li>These activities are purely for recreational purposes and **do not constitute real gambling**, as the virtual currencies used and digital items obtained have no real-world economic value.</li>
                        <li>Drop rates and duel outcomes are managed by internal algorithms. Cripsum™ does not guarantee the acquisition of specific virtual items or positive outcomes in duels. The software is provided "as is" and server-calculated results are final.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="contenuti">
                    <h2>8. User-Generated Content and Moderation (Shitposts)</h2>
                    <p>Users may upload and publish content on the Platform in the form of text, images, memes, or links (including "Shitposts" and "Top Rimasti").</p>
                    <ul>
                        <li>You are solely responsible for the content you publish. You agree not to upload material that violates copyright, is defamatory, offensive, pornographic, harmful to minors, or incites hatred, violence, or illegal behavior.</li>
                        <li>By uploading content, you grant Cripsum™ a free, perpetual, non-exclusive, worldwide license to host, display, distribute, and reproduce such material within the Platform.</li>
                        <li>The Cripsum™ team reserves the absolute right to moderate, hide, edit, or delete any user-uploaded content without prior notice and at its sole discretion.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="ticket">
                    <h2>9. Support Tickets and Chat</h2>
                    <p>The Message Center and Ticket system allow direct chat between the user and administrators. You agree to use this tool in a civil and respectful manner:</p>
                    <ul>
                        <li>It is strictly forbidden to send attachments containing malware, viruses, copyrighted material without authorization, or images with illegal or explicit content.</li>
                        <li>Sending harmful or offensive attachments will result in the immediate closure of the ticket and potential permanent suspension of your account.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="moderazione">
                    <h2>10. Account Suspension and Termination (Bans)</h2>
                    <p>Cripsum™ reserves the right to suspend, limit, or permanently delete any user account, at its sole discretion, without prior notice and without any financial or legal liability, in the event of:</p>
                    <ul>
                        <li>Violation of these Terms and Conditions.</li>
                        <li>Fraudulent behavior, manipulation of game data (exploits, hacks, botting), or harassment within the community.</li>
                        <li>Requests by competent law enforcement or judicial authorities.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="responsabilita">
                    <h2>11. Limitation of Liability</h2>
                    <p>THE PLATFORM AND ALL RELATED SERVICES ARE PROVIDED "AS IS" AND "AS AVAILABLE", WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED.</p>
                    <p>Cripsum™ does not guarantee that the service will be uninterrupted, error-free, bug-free, or free of data loss. In no event shall Cripsum™, its administrators, or collaborators be liable for any direct, indirect, incidental, special, or consequential damages (including, without limitation, loss of virtual currencies, game characters, or website unavailability) arising out of the use or inability to use the Platform.</p>
                </section>

                <section class="static-legal-section static-reveal" id="manleva">
                    <h2>12. Indemnification</h2>
                    <p>You agree to indemnify, defend, and hold harmless Cripsum™, its administrators, and collaborators from and against any claims, damages, losses, liabilities, costs, or expenses (including legal fees) arising out of your violation of these Terms and Conditions or your improper or unlawful use of the Services.</p>
                </section>

                <section class="static-legal-section static-reveal" id="modifiche">
                    <h2>13. Modifications to Terms</h2>
                    <p>The Cripsum™ team reserves the right to update or modify these Terms and Conditions at any time. Changes will be made known by posting the updated version on this page with the date of the last update. Your continued use of the Platform after changes are posted constitutes acceptance of the new Terms.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>14. Contact</h2>
                    <p>For any questions, reports, or requests for clarification regarding these Terms, you can contact us at: <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>.</p>
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