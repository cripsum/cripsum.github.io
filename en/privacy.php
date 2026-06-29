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
    <title>Cripsum™ - Privacy Policy</title>

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
            <p>How we collect, use, and protect your data on Cripsum™.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fa-solid fa-calendar"></i> Updated: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fa-solid fa-shield-halved"></i> GDPR & Security</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Table of Contents</h2>
                <a href="#titolare">1. Data Controller</a>
                <a href="#raccolta">2. Data Collected</a>
                <a href="#uso">3. Purpose of Processing</a>
                <a href="#base-giuridica">4. Legal Basis</a>
                <a href="#conservazione">5. Data Retention</a>
                <a href="#condivisione">6. Data Sharing</a>
                <a href="#diritti">7. Your Rights (GDPR)</a>
                <a href="#sicurezza">8. Data Security</a>
                <a href="#contatti">9. Contact</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="titolare">
                    <h2>1. Data Controller</h2>
                    <p>The data controller for the personal data collected through the Cripsum™ platform is the Cripsum™ administration team (hereinafter "Data Controller", "We", or "Us"). For any privacy-related communication or request, you can contact us at our dedicated email address: <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="raccolta">
                    <h2>2. Data Collected</h2>
                    <p>We collect and process the following categories of personal data to provide our services to you:</p>
                    <ul>
                        <li><strong>Account Data:</strong> Username, email address, and encrypted password (using a secure hashing algorithm).</li>
                        <li><strong>Third-Party Integration Data (Discord):</strong> If you choose to link your Discord account, we collect and store your Discord ID, username, avatar, and, if you enable our Presence Bot, information about your online status and currently running games.</li>
                        <li><strong>Payment Data:</strong> For purchases of Godo Shards or Premium memberships processed through PayPal, we collect the transaction ID, payment status, the email address associated with your PayPal account, and the amount paid. <em>We do not collect or store your credit or debit card details under any circumstances.</em></li>
                        <li><strong>Technical and Navigation Data:</strong> IP address, browser type, session data (via technical cookies necessary for the website's operation), and activity logs for security and fraud prevention purposes.</li>
                        <li><strong>Message Center and Support Tickets:</strong> The text of messages exchanged, support tickets opened, and any files or images attached by the user within the support chat.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="uso">
                    <h2>3. Purpose of Processing</h2>
                    <p>Your data is processed exclusively for the following purposes:</p>
                    <ul>
                        <li><strong>Service Delivery:</strong> Account creation and management, participation in games (Gacha, Duels, Lootboxes), user profile customization, and displaying your Discord online status.</li>
                        <li><strong>Transaction Processing:</strong> Managing the purchase of virtual game currencies (Godo Shards) and the activation/renewal of Premium status.</li>
                        <li><strong>Customer Support:</strong> Managing and responding to support tickets and user reports sent through the Message Center.</li>
                        <li><strong>Security and Moderation:</strong> Monitoring site activities to prevent abuse, botting, hacking attempts, and moderating user-generated content (Shitposts, comments, etc.) to ensure compliance with the Terms of Service.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="base-giuridica">
                    <h2>4. Legal Basis for Processing</h2>
                    <p>We process your personal data based on the following legal conditions:</p>
                    <ul>
                        <li><strong>Performance of a Contract:</strong> For creating your account and providing the game services and digital products you purchase.</li>
                        <li><strong>Consent of the Data Subject:</strong> For optionally linking your Discord account and activating the Presence Bot. You can revoke this consent at any time by unlinking your account in your profile settings.</li>
                        <li><strong>Legitimate Interest:</strong> To ensure website security, prevent fraud and abuse, and moderate content uploaded to the platform.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="conservazione">
                    <h2>5. Data Retention</h2>
                    <p>Your personal data will only be kept for as long as strictly necessary to achieve the purposes for which it was collected:</p>
                    <ul>
                        <li>Account and profile data remain active until the user requests the deletion of their account.</li>
                        <li>Data related to financial transactions (via PayPal) is retained for the minimum period required by applicable tax and anti-money laundering regulations.</li>
                        <li>Support tickets and messages exchanged in the Message Center are retained for historical purposes and the legal protection of the Data Controller.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="condivisione">
                    <h2>6. Data Sharing</h2>
                    <p>We do not sell or transfer your personal data to third parties. Data may only be shared with:</p>
                    <ul>
                        <li><strong>Authorized Third-Party Service Providers:</strong> Such as PayPal (for payment processing) and Cloudflare (for security and protection against cyberattacks).</li>
                        <li><strong>Competent Authorities:</strong> If required by law or to prevent illegal or fraudulent activities on the platform.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="diritti">
                    <h2>7. Your Rights (GDPR)</h2>
                    <p>In accordance with the General Data Protection Regulation (GDPR - Regulation EU 2016/679), you have the right to:</p>
                    <ul>
                        <li>Access your data in our possession and request a copy.</li>
                        <li>Request the correction of inaccurate or incomplete data.</li>
                        <li>Request the deletion of your personal data ("right to be forgotten"), subject to our right/duty to retain it to comply with legal obligations.</li>
                        <li>Limit or object to the processing of your personal data in certain circumstances.</li>
                        <li>Request data portability in a structured, machine-readable format.</li>
                    </ul>
                    <p>To exercise these rights, you can send a written request to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>. We will respond to your request within the legally required timeframe (30 days).</p>
                </section>

                <section class="static-legal-section static-reveal" id="sicurezza">
                    <h2>8. Data Security</h2>
                    <p>We implement appropriate technical and organizational security measures to protect your personal data from loss, misuse, unauthorized access, disclosure, or alteration. We use SSL/HTTPS encryption for all data transmissions on the site and secure hashing techniques to protect user passwords.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>9. Contact</h2>
                    <p>For any questions, concerns, or complaints regarding this Privacy Policy or the processing of your data, you can contact us at: <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
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