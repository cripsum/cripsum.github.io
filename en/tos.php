<?php
require_once '../config/session_init.php';
require_once '../includes/theme.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = 'October 2, 2026';
?>
<!DOCTYPE html>
<html lang="en"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include '../includes/head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <title>Cripsum™ - Terms of Service</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/assets/static/static.css') ?>">
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
                <h1>Terms of Service</h1>
                <p>The rules for using Cripsum™, buying Premium and Godo Shards and being part of the community.</p>
                <div class="static-meta">
                    <span class="static-chip"><i class="fa-solid fa-calendar"></i> Updated on <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="static-chip"><i class="fa-solid fa-scale-balanced"></i> Site rules</span>
                </div>
            </div>
            <div class="static-hero__logo-container">
                <img src="/img/tos.gif" alt="Cripsum™ TOS Logo" class="static-tos-logo">
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Contents</h2>
                <a href="#summary">Summary</a>
                <a href="#operator">1. Who runs the site</a>
                <a href="#acceptance">2. Acceptance</a>
                <a href="#service">3. What Cripsum™ is</a>
                <a href="#age">4. Minimum age and minors</a>
                <a href="#account">5. Account</a>
                <a href="#rules">6. Rules of conduct</a>
                <a href="#content">7. User content</a>
                <a href="#moderation">8. Reports and moderation</a>
                <a href="#chat">9. Chat, messages and tickets</a>
                <a href="#goonland">10. GoonLand and 18+ content</a>
                <a href="#currencies">11. Godos and Godo Shards</a>
                <a href="#games">12. Gacha, lootboxes and games</a>
                <a href="#purchases">13. Purchases</a>
                <a href="#shop">14. Shop, Merch and Downloads</a>
                <a href="#donations">15. Donations</a>
                <a href="#third-parties">16. Third-party services</a>
                <a href="#ip">17. Intellectual property</a>
                <a href="#people">18. Real people on the site</a>
                <a href="#api">19. Public API</a>
                <a href="#availability">20. Service availability</a>
                <a href="#termination">21. Suspension and closure</a>
                <a href="#liability">22. Liability</a>
                <a href="#indemnity">23. Indemnity</a>
                <a href="#changes">24. Changes to the Terms</a>
                <a href="#law">25. Governing law and disputes</a>
                <a href="#final">26. Final provisions</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-legal-section--summary static-reveal" id="summary">
                    <h2>Summary</h2>
                    <p>This summary does not replace the Terms, but it tells you the most important things.</p>
                    <ul>
                        <li>You must be <strong>at least 14</strong> to create an account. GoonLand and the 18+ modes are for adults only.</li>
                        <li>Respect others: no insults, bullying, illegal content or photos of other people without their permission.</li>
                        <li>Godos and Godo Shards are game currencies: they are not worth real money and cannot be resold.</li>
                        <li>Premium and Shards are bought with PayPal or Stripe. You get them right away, which is why you give up the 14-day right of withdrawal; if something does not arrive or does not work, we fix it or refund you.</li>
                        <li>If you are under 18, ask a parent for permission before buying anything.</li>
                        <li>The Shop and Merch are fake: you pay nothing and nothing arrives.</li>
                        <li>You can report content and ask for help against cyberbullying: we reply within 24 hours.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="operator">
                    <h2>1. Who runs the site</h2>
                    <p>Cripsum™ (cripsum.com, «the site») is a personal, unincorporated project run by the Cripsum™ team («we»), made up of private individuals living in Italy.</p>
                    <p>Contacts:</p>
                    <ul>
                        <li><a href="mailto:tos@cripsum.com">tos@cripsum.com</a> for these Terms, purchases, reports and appeals;</li>
                        <li><a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a> for personal data;</li>
                        <li>the <a href="supporto">Support</a> page for help.</li>
                    </ul>
                    <p>These contacts are also the single point of contact for users and authorities under Articles 11 and 12 of Regulation (EU) 2022/2065 on digital services (DSA). You can write to us in Italian or English. If an authority, or someone exercising a right granted by law, needs the operator's identification details, we provide them on a reasoned request.</p>
                </section>

                <section class="static-legal-section static-reveal" id="acceptance">
                    <h2>2. Acceptance</h2>
                    <p>By using the site you accept these Terms. When you create an account you accept them expressly by ticking the box on the sign-up page (also when you sign up with Google). The <a href="chat-policy">Chat Policy</a> is part of the Terms too.</p>
                    <p>The <a href="privacy">Privacy Policy</a> and the <a href="cookie">Cookie Policy</a> are not contracts: they explain how we handle your data. If you do not agree with the Terms, do not use the site.</p>
                </section>

                <section class="static-legal-section static-reveal" id="service">
                    <h2>3. What Cripsum™ is</h2>
                    <p>Cripsum™ is a free entertainment site with customizable profiles, friends and follows, global, private and group chats, user content (Shitpost, Top Rimasti, comments), games (gacha, lootboxes, duels, Subway Surfers, Animespot, Pullspot and other minigames), missions and achievements, Cripsum Rewind, Cripsumpedia, the pages of the OHPY esports team, free downloads and a parody shop.</p>
                    <p>Much of the content is ironic and meant as memes: it should not be taken literally. Part of the site's code is public on <a href="https://github.com/cripsum/cripsum.github.io" target="_blank" rel="noopener">GitHub</a>.</p>
                    <p>The only operations involving real money are buying Premium and Godo Shards (section 13) and voluntary donations (section 15).</p>
                </section>

                <section class="static-legal-section static-reveal" id="age">
                    <h2>4. Minimum age and minors</h2>
                    <ul>
                        <li>You must be <strong>at least 14</strong> to create an account. By signing up you confirm that you are.</li>
                        <li>If you are between 14 and 17 you can use the site, except GoonLand and the 18+ modes (section 10). To buy Premium or Shards you need the permission of a parent or guardian.</li>
                        <li>If we find out that an account belongs to someone under 14, we close it.</li>
                        <li>If you are a parent and think your child under 14 has an account, or made a purchase without permission, write to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>: we close the account or consider refunding the purchase.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="account">
                    <h2>5. Account</h2>
                    <ul>
                        <li>Use a valid email address of your own: we need it to verify the account, reset your password and send purchase confirmations.</li>
                        <li>Your account is personal: you cannot sell, lend, trade or transfer it.</li>
                        <li>Your username must not be offensive or make people think you are someone else or a staff member.</li>
                        <li>You are responsible for what happens with your account and for keeping your password safe. We recommend turning on two-step verification (2FA). In the settings you can see your connected devices and sign them out.</li>
                        <li>If you think someone used your account, change your password and tell us right away.</li>
                        <li>If you sign in with Google or connect Discord, their terms apply too.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="rules">
                    <h2>6. Rules of conduct</h2>
                    <p>On the site it is forbidden to:</p>
                    <ul>
                        <li>harass, insult, threaten or discriminate against others; bully or cyberbully; incite hatred or violence;</li>
                        <li>post personal data, photos or videos of other people without their consent, especially if they are minors;</li>
                        <li>post sexual content outside GoonLand and, anywhere, any sexual content involving minors, even drawn or generated (zero tolerance: we report it to the authorities);</li>
                        <li>post illegal or violent content, or content that encourages self-harm;</li>
                        <li>spam, advertise without permission, phish or spread malware;</li>
                        <li>impersonate other users, staff or real people;</li>
                        <li>use cheats, exploits, bots, scripts or automation that alter games, leaderboards, missions or currencies, or exploit a bug instead of reporting it;</li>
                        <li>scrape data in bulk or overload the site and the API;</li>
                        <li>get around bans, limits or age checks, including with other accounts.</li>
                    </ul>
                    <p>The <a href="chat-policy">Chat Policy</a> adds a few rules specific to the chats.</p>
                </section>

                <section class="static-legal-section static-reveal" id="content">
                    <h2>7. User content</h2>
                    <ul>
                        <li>The content you post (text, images, videos, links, profile) remains yours and you are responsible for it. By posting it you confirm that you have the necessary rights and the consent of the people who appear in it.</li>
                        <li>You give us a free, non-exclusive, worldwide licence to host it, show it to other users in the sections where you post it and adapt it to the site (thumbnails, previews), only to make the site work.</li>
                        <li>The licence ends when you delete the content or your account, except for backup copies, which overwrite themselves within a few weeks.</li>
                        <li>Shitposts and Top Rimasti posts are approved by staff before they are published.</li>
                        <li>We may remove or hide content that breaks these Terms or the law, as explained in section 8.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="moderation">
                    <h2>8. Reports and moderation</h2>
                    <h3>How to report</h3>
                    <p>You can report profiles, posts, comments and global chat messages with the «Report» buttons, or write to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> saying where the content is (the link), why you think it is illegal or not allowed, and how to reach you. If you report child abuse content you may stay anonymous.</p>
                    <h3>What we do</h3>
                    <p>Reports are reviewed by hand by staff, in a timely, diligent and impartial way: we do not use automated systems to decide. Depending on how serious and repeated the violation is, we may remove or hide content, restrict some features (for example a chat mute), suspend the account for a period or close it permanently.</p>
                    <h3>Reasons and appeals</h3>
                    <p>If we act on your content or account we tell you what we did and why, in your inbox or by email, unless the law or an authority prevents it or it is spam. If you disagree you can write to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> within 6 months: the decision is reviewed again, by a different person when possible. You can always turn to an authority or a court.</p>
                    <p>If content suggests a crime that endangers someone's life or safety, we report it to the authorities.</p>
                    <h3>Cyberbullying</h3>
                    <p>If you are at least 14 and are being cyberbullied on the site, or you are the parent of a minor who is, you can ask us to hide, remove or block the content by writing to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> with the subject «Cyberbullying». We confirm that we have taken charge of the request within 24 hours and act within 48 hours, as required by Italian Law 71/2017. If we do not, you can turn to the Italian Data Protection Authority (Garante per la protezione dei dati personali).</p>
                </section>

                <section class="static-legal-section static-reveal" id="chat">
                    <h2>9. Chat, messages and tickets</h2>
                    <ul>
                        <li>The global chat is visible to the site's users and is moderated. In private and group chats only the participants write; group admins can manage the members.</li>
                        <li>Staff do not read private and group chats. They only see the messages that someone reports (those of the global chat, and those of a private or group chat when a participant reports them) and the messages in support tickets.</li>
                        <li>You can block other users and choose in the chat settings who can message you.</li>
                        <li>Attachments must not contain malware, illegal material, explicit content or copyrighted material without permission.</li>
                        <li>Tickets are for asking staff for help; they may also be handled from our Discord server. Use them respectfully: offensive or harmful use leads to the ticket being closed and may lead to a ban.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="goonland">
                    <h2>10. GoonLand and 18+ content</h2>
                    <ul>
                        <li>GoonLand and the modes marked 18+ contain adult material, including explicit material, that partly comes from external services (for example waifu.pics and waifu.im).</li>
                        <li>They are <strong>for adults only</strong>. To enter you must turn on the «I am at least 18» statement in the settings: by turning it on you declare, under your own responsibility, that you are an adult and that you want to see this content.</li>
                        <li>If you are under 18 you must not turn it on. A false statement breaks these Terms and leads to the account being closed.</li>
                        <li>We do not verify age with documents. Parents can use the parental controls of their device or browser to block addresses containing <code>/goonland</code>.</li>
                        <li>The images come from services we do not check in real time. If you find one that shows, or seems to show, a minor, or that is otherwise illegal, report it right away: we block it.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="currencies">
                    <h2>11. Godos and Godo Shards</h2>
                    <ul>
                        <li><strong>Godos</strong> are earned by using the site (missions, daily claims, events, codes). <strong>Godo Shards</strong> are bought with real money, obtained by converting Godos at the rate shown in the shop, or received as gifts.</li>
                        <li>They are game currencies: they are not money, have no value outside the site, and cannot be converted into money or refunded (except as provided in section 13).</li>
                        <li>They cannot be transferred to other accounts, except through site features that allow it, and cannot be sold or bought outside the site.</li>
                        <li>They do not expire as long as your account and the site exist. If you delete your account, you lose them.</li>
                        <li>We may correct wrong balances caused by bugs, errors or exploits.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="games">
                    <h2>12. Gacha, lootboxes and games</h2>
                    <ul>
                        <li>The odds of every banner are published in «Details &amp; rates», together with how pity and rate-ups work. Results are calculated by the server and are final, except for technical errors that we fix.</li>
                        <li>It is not gambling: prizes are digital items with no economic value and cannot be converted into money. However, some pulls use Shards that can be bought: if you are a minor, talk to a parent and agree on a spending limit.</li>
                        <li>The Gambling Arcade uses fake credits stored only in your browser: no money is involved and there is nothing to win.</li>
                        <li>Leaderboards and scores are public. We may remove scores, prizes or items obtained through cheats, exploits or bugs.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="purchases">
                    <h2>13. Purchases: Premium and Godo Shards</h2>
                    <h3>What you can buy</h3>
                    <ul>
                        <li><strong>Cripsum™ Premium</strong>: a one-time purchase, not a subscription, at the price shown at checkout. The benefits are described on the purchase page. Premium can also be given as a gift to another user.</li>
                        <li><strong>Godo Shards packs</strong>: prices and contents are shown in the shop. The first purchase of each pack counts double, as shown on the page.</li>
                    </ul>
                    <p>The seller is the Cripsum™ team (section 1). Prices are in euros and are final: we add no extra costs. You pay with PayPal or Stripe, under their terms; we do not see or store your card details.</p>
                    <h3>Delivery and confirmation</h3>
                    <p>Premium and Shards reach your account as soon as the payment is confirmed. After paying you receive a confirmation email with a summary of the purchase.</p>
                    <h3>Right of withdrawal</h3>
                    <p>Premium and Shards are digital content supplied immediately. Before you pay we ask you, with a mandatory checkbox, to agree to immediate supply and to acknowledge that you lose the 14-day right of withdrawal (Art. 59(1)(o) of the Italian Consumer Code, implementing Art. 16(m) of Directive 2011/83/EU). We confirm this in the purchase email.</p>
                    <h3>Guarantee and refunds</h3>
                    <p>Your statutory rights for digital content still apply (Art. 135-octies and following of the Italian Consumer Code). If Premium or Shards do not arrive or do not work as described, write to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> with the order ID: we fix the problem or refund you.</p>
                    <h3>How long Premium lasts</h3>
                    <p>Premium stays on your account for as long as the account and the site exist. The benefits may change over time, but without taking away the essence of what you bought; if a change did, you can ask for a refund. If we ever decided to close the site or Premium, we would announce it at least 30 days in advance.</p>
                    <h3>Minors, closures and disputes</h3>
                    <ul>
                        <li>If you are a minor you may buy only with a parent's permission. A parent can write to us about a purchase made without permission: we will consider a refund.</li>
                        <li>If the account is closed for a serious breach of the Terms, you lose Premium and Shards without a refund.</li>
                        <li>If we close your account without you breaking the Terms, we refund the Shards you bought and have not used yet and, if you bought it less than 12 months earlier, Premium.</li>
                        <li>If you reverse a payment through PayPal, Stripe or your bank after receiving the product, we may remove the product and suspend the account until the matter is settled.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="shop">
                    <h2>14. Shop, Merch and Downloads</h2>
                    <ul>
                        <li><strong>The Shop and Merch are a parody</strong>: we sell nothing, you pay nothing and nothing arrives. The checkout is fake, we do not send or store what you type in the form, and the «payment» is a joke. Do not enter real data.</li>
                        <li>The <a href="download">Downloads</a> page offers free files, such as guides, the video course and editing resources. You may use them for personal, non-commercial purposes unless stated otherwise; do not republish them as your own. Resources containing third-party material remain the property of their owners.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="donations">
                    <h2>15. Donations</h2>
                    <p>Donations are optional, go through Buy Me a Coffee and help keep the site online. They do not entitle you to products or benefits unless explicitly stated. Payments and refunds are governed by Buy Me a Coffee's terms.</p>
                </section>

                <section class="static-legal-section static-reveal" id="third-parties">
                    <h2>16. Third-party services</h2>
                    <ul>
                        <li><strong>Discord</strong>: you can connect your Discord account and, if you turn on Rich Presence, show your status and the games you are playing on your profile. You can disconnect it at any time in the settings.</li>
                        <li><strong>Google</strong>: you can sign up and sign in with Google; on the sign-up page we use Google reCAPTCHA against bots.</li>
                        <li>The site also shows content from external services: YouTube and Streamable videos, Spotify tracks, GIFs, covers and images from other services.</li>
                    </ul>
                    <p>Their own terms and privacy policies apply to these services. We are not responsible for their content or how they work; external links take you off the site.</p>
                </section>

                <section class="static-legal-section static-reveal" id="ip">
                    <h2>17. Intellectual property</h2>
                    <ul>
                        <li>The Cripsum™ name, logo, graphics and original text of the site are ours. You may not copy them or use them commercially without permission. The code published on GitHub is governed by the repository's terms.</li>
                        <li>Third-party characters, trademarks, games, anime, music and clips on the site (for example in the gacha, Animespot, Pullspot, Subway Surfers and the edits) belong to their owners. They are used as fan content, parody or quotation, with no affiliation with the owners.</li>
                        <li>If you own a right and want content removed, write to <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> with the content (the link) and the right you claim: we reply quickly.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="people">
                    <h2>18. Real people on the site</h2>
                    <p>Cripsumpedia, the About us page, the OHPY team pages and some characters may concern real people. We publish them with the consent of the people involved, who can ask at any time to correct or remove what concerns them by writing to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>. The tone is ironic and is not meant to offend anyone.</p>
                </section>

                <section class="static-legal-section static-reveal" id="api">
                    <h2>19. Public API</h2>
                    <p>The public API described in <a href="api-docs">API Docs</a> is free and provided «as is», with no guarantee of availability. Do not use it to collect data in bulk, profile users or republish data out of context, and keep to reasonable request limits. We may block anyone who abuses it and change or shut down the API.</p>
                </section>

                <section class="static-legal-section static-reveal" id="availability">
                    <h2>20. Service availability</h2>
                    <p>We do our best to keep the site running, but there may be outages, maintenance and bugs, and free features may change or be removed. For paid features, section 13 applies.</p>
                </section>

                <section class="static-legal-section static-reveal" id="termination">
                    <h2>21. Suspension and account closure</h2>
                    <ul>
                        <li><strong>By you</strong>: you can delete your account at any time from Settings → Delete account. You have 30 days to change your mind: just sign in again. After that, the account and the data linked to it are deleted and you lose Godos, Shards, Premium and items.</li>
                        <li><strong>By us</strong>: we may suspend or close an account that breaks these Terms or the law, or at the request of an authority, with a proportionate and reasoned measure as explained in section 8. In serious or urgent cases the measure may be immediate.</li>
                        <li>Anyone who has been banned may not create other accounts to get around the ban.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="liability">
                    <h2>22. Liability</h2>
                    <p>The site is offered free of charge, for entertainment and «as is». To the extent permitted by law we are not liable for service outages, content posted by users or external services, indirect damages, or the loss of game data or currencies caused by bugs (which we still try to restore).</p>
                    <p>These limitations do not apply in case of wilful misconduct or gross negligence, for personal injury, and in the other cases where the law, including consumer law, does not allow liability to be limited. Your consumer rights on purchases always remain.</p>
                </section>

                <section class="static-legal-section static-reveal" id="indemnity">
                    <h2>23. Indemnity</h2>
                    <p>If you break these Terms or the law and someone claims damages from us because of it, you must hold us harmless from the damages and reasonable costs, including legal costs, caused by your breach, within the limits set by law.</p>
                </section>

                <section class="static-legal-section static-reveal" id="changes">
                    <h2>24. Changes to the Terms</h2>
                    <p>We may update these Terms for new features, changes in the law or security reasons. For important changes we notify you with a message in the site inbox at least 15 days before they take effect, unless the law or security require a shorter time.</p>
                    <p>If you do not agree you can delete your account before that date; if you keep using the site afterwards, the changes apply to you too. Changes do not affect purchases already made. The date of the last update is always at the top of the page.</p>
                </section>

                <section class="static-legal-section static-reveal" id="law">
                    <h2>25. Governing law and disputes</h2>
                    <p>These Terms are governed by Italian law. If you are a consumer living in another EU country, the mandatory protections of your country's law still apply.</p>
                    <p>For disputes with a consumer, the court of the place where the consumer lives has jurisdiction (Art. 66-bis of the Italian Consumer Code). Before taking legal action, write to us at <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>: we will try to sort it out together. You can also use an alternative dispute resolution (ADR) procedure provided by law.</p>
                </section>

                <section class="static-legal-section static-reveal" id="final">
                    <h2>26. Final provisions</h2>
                    <ul>
                        <li>If any part of these Terms is invalid, the rest remains valid.</li>
                        <li>If we do not enforce a right straight away, it does not mean we waive it.</li>
                        <li>The Terms are available in Italian and English; if they differ, the Italian version prevails.</li>
                    </ul>
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
