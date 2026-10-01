<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = 'October 2, 2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Privacy Policy</title>

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
        <section class="static-hero static-reveal">
            <h1>Privacy Policy</h1>
            <p>What data we collect on Cripsum™, why, who we share it with and how you can control it.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fa-solid fa-calendar"></i> Updated on <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fa-solid fa-shield-halved"></i> GDPR (EU Reg. 2016/679)</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Contents</h2>
                <a href="#summary">Summary</a>
                <a href="#controller">1. Controller</a>
                <a href="#data">2. Data we collect</a>
                <a href="#purposes">3. Why we use it</a>
                <a href="#public">4. What others can see</a>
                <a href="#minors">5. Minors</a>
                <a href="#cookies">6. Cookies and analytics</a>
                <a href="#recipients">7. Who we share data with</a>
                <a href="#transfers">8. Data outside the EU</a>
                <a href="#retention">9. How long we keep it</a>
                <a href="#rights">10. Your rights</a>
                <a href="#required">11. Required data and automated decisions</a>
                <a href="#security">12. Security</a>
                <a href="#non-users">13. People who are not users</a>
                <a href="#changes">14. Changes</a>
                <a href="#contact">15. Contact</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-legal-section--summary static-reveal" id="summary">
                    <h2>Summary (also for under-18s)</h2>
                    <ul>
                        <li>To use the site we need a username, an email and a password. We do not ask for your name, address or date of birth.</li>
                        <li>Everyone can see your profile, posts and leaderboards. Private and group chats cannot be seen by others: not even staff read them.</li>
                        <li>We do not sell your data and there are no ads on the site.</li>
                        <li>We use Google Analytics to count visits: you can turn it off at any time with the button at the bottom of every page.</li>
                        <li>If you buy something, PayPal or Stripe handle the payment: we never see your card details.</li>
                        <li>In the settings you can download all your data or delete your account.</li>
                        <li>For any question write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="controller">
                    <h2>1. Data controller</h2>
                    <p>The data controller is the Cripsum™ team, which runs cripsum.com as a personal, unincorporated project of private individuals living in Italy.</p>
                    <p>For any privacy request write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>. If an authority, or someone exercising a GDPR right, needs the controller's identification details, we provide them on request. We have not appointed a data protection officer (DPO), because the law does not require one for a project like this.</p>
                </section>

                <section class="static-legal-section static-reveal" id="data">
                    <h2>2. Data we collect</h2>
                    <h3>Account and sign-in</h3>
                    <ul>
                        <li>Username, email and password (stored only as a hash).</li>
                        <li>If you sign up or sign in with Google: your Google ID, email and Google account name, which we use to suggest a username.</li>
                        <li>Your confirmation that you are at least 14 and accept the Terms, given with the checkbox at sign-up.</li>
                        <li>If you turn on two-step verification: the 2FA secret and backup codes.</li>
                        <li>Account preferences, such as language, theme and the «I am at least 18» statement for GoonLand.</li>
                    </ul>
                    <h3>Security</h3>
                    <ul>
                        <li>IP address, browser and operating system of the devices you sign in with, with an approximate location (city and country) derived from the IP, to show you the list of connected devices and warn you about new sign-ins.</li>
                        <li>Sign-in attempts (successful and failed) with IP and outcome, to block attempts to break into accounts.</li>
                        <li>Google's reCAPTCHA check during sign-up.</li>
                        <li>Moderation measures (warnings, mutes, bans) and the log of staff actions.</li>
                    </ul>
                    <h3>Profile and community</h3>
                    <ul>
                        <li>What you put on your profile: picture, banner, bio, links, socials, music, embedded content, favourites, projects, featured badges and characters.</li>
                        <li>Friendships, friend requests, follows and blocked users.</li>
                        <li>Shitposts, Top Rimasti posts, comments, likes, votes, saved content and the reports you send.</li>
                        <li>Messages in the global chat, private and group chats, with attachments, reactions and GIFs. To search GIFs we send Klipy the words you type in the search.</li>
                        <li>In private and group chats: the last message you have read (this is what read receipts are based on; you can turn them off in the chat Privacy settings), the chats you muted, archived or pinned, saved messages, the nicknames you set and your choices about who can message you. The "is typing" indicator lasts a few seconds and is not stored.</li>
                        <li>We remove hidden shot data, such as GPS location, from the photos you upload in chat when the format allows it (JPEG and WebP).</li>
                        <li>To notify you right away about messages and requests we keep on the server, for a short time, a technical list of the latest events that concern you (for example "new message in a chat"): it does not contain the text of private messages. Browser notifications are turned on by you, are shown by your browser while the site is open and do not go through external services.</li>
                        <li>Support tickets and the messages you receive from the site in your inbox.</li>
                    </ul>
                    <h3>Games and statistics</h3>
                    <ul>
                        <li>Characters and items, gacha history and pity, collections, wishlist, missions, achievements, matches and duels, Subway Surfers, Animespot and Pullspot scores, Godos and Shards balances.</li>
                        <li>Usage statistics for Cripsum Rewind and for missions and achievements: days you signed in, time spent in the site's sections (counted only while the page is in the foreground), actions in games. You can turn tracking off in the Rewind settings.</li>
                    </ul>
                    <h3>Purchases</h3>
                    <ul>
                        <li>What you bought (Premium, Shards packs, Premium gifts), amount, date, payment status, method (PayPal or Stripe) and order ID.</li>
                        <li>For gifts: who gave Premium and to whom.</li>
                        <li>Card and account details are handled by PayPal and Stripe: we do not see or store them.</li>
                    </ul>
                    <h3>Discord</h3>
                    <ul>
                        <li>If you connect Discord: your Discord ID, username, display name and avatar.</li>
                        <li>If you turn on Rich Presence: your online status and current activities (for example the game or music), read in real time by our bot and by the Lanyard service. We do not keep a history of your Discord activity.</li>
                    </ul>
                    <h3>Other data</h3>
                    <ul>
                        <li>Applications for the About us page: your account, the name to show, description, photo, socials and links you send us. We review them in the site panel and answer in your site inbox; only the staff can see the photo until the application is accepted.</li>
                        <li>Requests to export your data or delete your account.</li>
                        <li>Browsing data (such as IP, pages visited and device) through the server's technical logs and, unless you turn it off, Google Analytics (section 6).</li>
                        <li>The Shop and Merch checkout is fake: what you type in the form is not sent or stored.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="purposes">
                    <h2>3. Why we use it and on what legal basis</h2>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Purpose</th>
                                    <th>Legal basis (Art. 6 GDPR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Creating and managing your account, running profile, chat, friends, games, missions, Rewind and the site's other features</td>
                                    <td>Performance of the contract, i.e. the Terms (point b)</td>
                                </tr>
                                <tr>
                                    <td>Selling Premium and Shards, sending the confirmation and handling problems, refunds and disputes</td>
                                    <td>Performance of the contract (point b); legal obligations on consumer protection (point c); defence of our rights (point f)</td>
                                </tr>
                                <tr>
                                    <td>Protecting accounts and the site: sign-in checks, device list, new sign-in alerts, reCAPTCHA, attempt limits, preventing fraud, cheats and ban evasion</td>
                                    <td>Legitimate interest in the security of the site and its users (point f)</td>
                                </tr>
                                <tr>
                                    <td>Handling reports, moderation, appeals and requests from authorities</td>
                                    <td>Legal obligations, including the Digital Services Act (point c); legitimate interest in keeping the community safe (point f)</td>
                                </tr>
                                <tr>
                                    <td>Support through tickets and email</td>
                                    <td>Performance of the contract (point b); legitimate interest in answering whoever writes to us (point f)</td>
                                </tr>
                                <tr>
                                    <td>Connecting Discord and showing Rich Presence on your profile</td>
                                    <td>Consent (point a), which you withdraw by disconnecting Discord or turning off Rich Presence</td>
                                </tr>
                                <tr>
                                    <td>Keeping GoonLand and the 18+ modes for users who state they are adults</td>
                                    <td>Legitimate interest in keeping minors away from adult content (point f)</td>
                                </tr>
                                <tr>
                                    <td>Aggregate visit statistics with Google Analytics</td>
                                    <td>Legitimate interest in understanding how the site is used (point f), with Google Signals and ads turned off. You can object at any time with the button in the footer</td>
                                </tr>
                                <tr>
                                    <td>Internal staff notifications in a private channel of our Discord server (for example new sign-ups with username and email) and a public thank-you on the Discord server to whoever buys Premium</td>
                                    <td>Legitimate interest in running the site and the community (point f). If you do not want a public thank-you, write to us</td>
                                </tr>
                                <tr>
                                    <td>Featuring you on the About us page or the OHPY pages</td>
                                    <td>Consent (point a), which you can withdraw at any time</td>
                                </tr>
                                <tr>
                                    <td>Answering requests about your rights and exporting your data</td>
                                    <td>Legal obligation (point c)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>We do not use your data for advertising, we do not sell it and we do not use it to train artificial intelligence systems.</p>
                </section>

                <section class="static-legal-section static-reveal" id="public">
                    <h2>4. What others can see</h2>
                    <p>Some data is public because of how the site works:</p>
                    <ul>
                        <li>your profile (username, picture, banner, bio, links, socials, music, embedded content, favourites, badges, achievements, profile stats and activity), visible to anyone, even without an account, and in link previews shared on other apps;</li>
                        <li>leaderboards and game scores;</li>
                        <li>published shitposts, Top Rimasti posts and comments;</li>
                        <li>global chat messages, visible to the site's users;</li>
                        <li>if you connect Discord and turn on Rich Presence, your Discord status and activities on your profile;</li>
                        <li>profile data, leaderboards and Discord presence can also be read through our <a href="api-docs">public API</a>;</li>
                        <li>your Rewind, if you share it with the public link. Your username may appear in the Rewind of a friend you interacted with a lot, if your settings allow it;</li>
                        <li>the thank-you on our Discord server when you buy Premium.</li>
                    </ul>
                    <p>Private and group chats are visible only to their participants.</p>
                </section>

                <section class="static-legal-section static-reveal" id="minors">
                    <h2>5. Minors</h2>
                    <ul>
                        <li>You must be at least 14 to create an account, the minimum age set in Italy for using these services on your own (Art. 2-quinquies of the Italian Privacy Code). If we find out that an account belongs to someone younger, we close it and delete the data.</li>
                        <li>We do not show ads to minors or profile them. GoonLand and the 18+ modes are reserved for users who state they are adults.</li>
                        <li>A parent can write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a> for information about their minor child's account or to ask for it to be deleted.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="cookies">
                    <h2>6. Cookies and analytics</h2>
                    <p>We use technical cookies to keep you signed in, remember language and theme and protect your account: without them the site does not work. We also use Google Analytics to count visits, with Google Signals and ad personalisation turned off. It is on by default and you can turn it off at any time with the «Google Analytics» button in the footer: turning it off changes nothing in how the site works. All the details are in the <a href="cookie">Cookie Policy</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="recipients">
                    <h2>7. Who we share data with</h2>
                    <p>The data is handled by us, authorised staff and these providers, each only for what it needs:</p>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Who</th>
                                    <th>What for</th>
                                    <th>Where</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Hostinger</td>
                                    <td>Hosts the site and database and sends the site's emails</td>
                                    <td>Servers in the United Kingdom, backups in Germany</td>
                                </tr>
                                <tr>
                                    <td>Team server</td>
                                    <td>Runs the Discord bot (presence, roles, tickets, notifications)</td>
                                    <td>Italy</td>
                                </tr>
                                <tr>
                                    <td>PayPal, Stripe</td>
                                    <td>Payments for Premium and Shards; they are independent controllers of payment data</td>
                                    <td>EU, with transfers to the USA</td>
                                </tr>
                                <tr>
                                    <td>Google</td>
                                    <td>Sign-in with Google, reCAPTCHA, Google Analytics, fonts and libraries (Google Fonts, jQuery), embedded YouTube videos</td>
                                    <td>EU and USA</td>
                                </tr>
                                <tr>
                                    <td>Discord, Lanyard</td>
                                    <td>Account connection, Rich Presence, staff notifications, tickets handled on Discord</td>
                                    <td>USA</td>
                                </tr>
                                <tr>
                                    <td>Klipy</td>
                                    <td>GIF search in chat</td>
                                    <td>USA</td>
                                </tr>
                                <tr>
                                    <td>ipwho.is</td>
                                    <td>Approximate location (city and country) of the IPs in the device list</td>
                                    <td>Outside the EU</td>
                                </tr>
                                <tr>
                                    <td>jsDelivr, Cloudflare (cdnjs)</td>
                                    <td>Deliver some of the site's libraries (graphics, icons)</td>
                                    <td>Global network</td>
                                </tr>
                                <tr>
                                    <td>Spotify, Streamable, Tenor, Apple, AniList, Open Library, Steam, animethemes.moe, waifu.pics, waifu.im, goQR.me and similar services</td>
                                    <td>Content embedded or loaded from external services (music, videos, GIFs, covers, images, anime themes, the public profile QR code): your browser connects to them and they receive your IP</td>
                                    <td>Various, including outside the EU</td>
                                </tr>
                                <tr>
                                    <td>Buy Me a Coffee</td>
                                    <td>Donations, if you choose to make one; independent controller</td>
                                    <td>USA</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>We may disclose data to authorities when the law requires it or to defend our rights. We do not sell or hand over data to anyone.</p>
                </section>

                <section class="static-legal-section static-reveal" id="transfers">
                    <h2>8. Data outside the European Union</h2>
                    <p>The site is hosted in the United Kingdom, which the European Commission recognises as providing an adequate level of protection; backups are in Germany. When data goes to the United States, the main providers (Google, Stripe, PayPal, Discord) rely on the EU-US Data Privacy Framework or on the standard contractual clauses approved by the European Commission. To smaller services (such as Lanyard, ipwho.is and Klipy) we send only the bare minimum: your public Discord ID, the IP to locate or the words searched.</p>
                </section>

                <section class="static-legal-section static-reveal" id="retention">
                    <h2>9. How long we keep data</h2>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>How long</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Account, profile, messages, posts, games, statistics, tickets, purchases recorded by us</td>
                                    <td>As long as the account exists. If you ask for deletion you have 30 days to change your mind; then the account and all linked data, including uploaded files, are deleted</td>
                                </tr>
                                <tr>
                                    <td>Sign-in session</td>
                                    <td>Up to 14 days</td>
                                </tr>
                                <tr>
                                    <td>Approximate IP location</td>
                                    <td>90 days</td>
                                </tr>
                                <tr>
                                    <td>Sign-in attempts (IP and outcome)</td>
                                    <td>90 days</td>
                                </tr>
                                <tr>
                                    <td>Log of staff actions</td>
                                    <td>12 months</td>
                                </tr>
                                <tr>
                                    <td>Your data export files</td>
                                    <td>7 days, then they are deleted</td>
                                </tr>
                                <tr>
                                    <td>Minimal data of banned accounts (username, email, reason)</td>
                                    <td>As long as the ban lasts, to prevent it being evaded</td>
                                </tr>
                                <tr>
                                    <td>Reports sent</td>
                                    <td>As long as needed to handle them; they are deleted anyway with the account of whoever sent them</td>
                                </tr>
                                <tr>
                                    <td>About us applications</td>
                                    <td>As long as needed to review them, at most 12 months if not accepted; if you are featured, until you ask to be removed</td>
                                </tr>
                                <tr>
                                    <td>Google Analytics</td>
                                    <td>Up to 14 months, according to the service settings</td>
                                </tr>
                                <tr>
                                    <td>Payment data held by PayPal and Stripe</td>
                                    <td>According to their policies and the legal obligations they are subject to</td>
                                </tr>
                                <tr>
                                    <td>Backups</td>
                                    <td>They overwrite themselves: deleted data may remain in them for a few weeks</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="static-legal-section static-reveal" id="rights">
                    <h2>10. Your rights</h2>
                    <p>Under the GDPR (Arts. 15-22) you can ask to:</p>
                    <ul>
                        <li>access your data and get a copy;</li>
                        <li>correct wrong data;</li>
                        <li>delete your data, except what we must keep by law or to defend ourselves;</li>
                        <li>restrict processing in some cases;</li>
                        <li>receive your data in a machine-readable format (portability);</li>
                        <li>object to processing based on legitimate interest, for example Google Analytics or Rewind statistics;</li>
                        <li>withdraw your consent at any time, without affecting what was done before.</li>
                    </ul>
                    <p>You can do many of these things yourself: in Settings you find «Your data» to download everything we hold about you and «Delete account» to delete it. The other tools are disconnecting Discord, Rich Presence, the Rewind and chat settings and the Google Analytics button in the footer.</p>
                    <p>For everything else write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>, preferably from your account email, so we can check it is you. We reply within one month, extendable by two more months only for complex requests, and we tell you why.</p>
                    <p>You can also lodge a complaint with the Italian Data Protection Authority, <strong>Garante per la protezione dei dati personali</strong> (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>), or with the authority of the EU country where you live.</p>
                </section>

                <section class="static-legal-section static-reveal" id="required">
                    <h2>11. Required data and automated decisions</h2>
                    <p>Username, email and password are needed to create an account: without them we cannot sign you up. Everything else on the profile is optional.</p>
                    <p>We do not make decisions that have legal or similar effects on you based solely on automated processing. Gacha results are random and do not depend on your profile; reports are always reviewed by a staff member.</p>
                </section>

                <section class="static-legal-section static-reveal" id="security">
                    <h2>12. Security</h2>
                    <p>Connections to the site are encrypted (HTTPS) and passwords are stored only as hashes. We offer two-step verification, limit sign-in attempts, protect forms against forged requests (CSRF), show you your connected devices and notify you in your inbox when someone signs in from a new device. Access to data is limited to the staff who need it.</p>
                    <p>If a data breach put your rights at risk, we would notify the Garante within 72 hours and, if the risk is high, you too.</p>
                </section>

                <section class="static-legal-section static-reveal" id="non-users">
                    <h2>13. People who are not users</h2>
                    <p>Cripsumpedia, the About us page, the OHPY team pages and some characters may concern real people. We publish them with their consent. If you appear on the site, including in content uploaded by a user, and want to correct or remove what concerns you, write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a> with the link to the content.</p>
                </section>

                <section class="static-legal-section static-reveal" id="changes">
                    <h2>14. Changes</h2>
                    <p>We update this policy when something changes on the site or in the law. For important changes we notify you with a message in the site inbox. The date of the last update is always at the top of the page.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contact">
                    <h2>15. Contact</h2>
                    <p>For questions, doubts or complaints about privacy write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
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
