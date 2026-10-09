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
    <title>Cripsum™ - Cookie Policy</title>

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
        <section class="static-hero static-reveal">
            <h1>Cookie Policy</h1>
            <p>Which cookies and similar tools Cripsum™ uses and how you can manage them.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fa-solid fa-calendar"></i> Updated on <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fa-solid fa-cookie-bite"></i> No ads</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Contents</h2>
                <a href="#what">1. What they are</a>
                <a href="#technical">2. Technical cookies</a>
                <a href="#analytics">3. Google Analytics</a>
                <a href="#third-parties">4. Third-party content</a>
                <a href="#manage">5. How to manage them</a>
                <a href="#changes">6. Changes and contact</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="what">
                    <h2>1. What they are</h2>
                    <p>Cookies are small files that a site stores in your browser to remember you from one page to the next. localStorage and the browser's other storage tools work in a similar way: on this page we call them all «cookies».</p>
                    <p>There are no ads and no advertising profiling cookies on Cripsum™. To learn how we handle personal data, read the <a href="privacy">Privacy Policy</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="technical">
                    <h2>2. Technical cookies</h2>
                    <p>They make the site work and do not require consent. They cannot be turned off from the site; if you block them in your browser, signing in and some features stop working.</p>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>What it does</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>__Host-cripsum_session</code></td>
                                    <td>Keeps you signed in and protects forms against forged requests</td>
                                    <td>Up to 14 days</td>
                                </tr>
                                <tr>
                                    <td><code>__Host-cripsum_device</code></td>
                                    <td>Recognises the device for the connected devices list and security alerts</td>
                                    <td>1 year</td>
                                </tr>
                                <tr>
                                    <td><code>cripsum_lang</code></td>
                                    <td>Remembers the language (Italian or English)</td>
                                    <td>1 year</td>
                                </tr>
                                <tr>
                                    <td><code>theme</code></td>
                                    <td>Remembers the chosen theme</td>
                                    <td>Until you change it</td>
                                </tr>
                                <tr>
                                    <td>Achievement and page cookies</td>
                                    <td>Remember the progress of some achievements and small preferences, for example edits already watched</td>
                                    <td>Until you delete them</td>
                                </tr>
                                <tr>
                                    <td>localStorage (<code>cripsum.*</code> and similar)</td>
                                    <td>Preferences such as volume, sounds, filters, sorting, chat alerts and your Google Analytics choice; drafts of messages you have not sent yet and recently used emoji</td>
                                    <td>Until you delete them</td>
                                </tr>
                                <tr>
                                    <td>Google reCAPTCHA</td>
                                    <td>Tells people apart from bots on the sign-up page</td>
                                    <td>Up to 6 months</td>
                                </tr>
                                <tr>
                                    <td>PayPal, Stripe</td>
                                    <td>Make payments work, only on the purchase pages</td>
                                    <td>According to their policies</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="static-legal-section static-reveal" id="analytics">
                    <h2>3. Google Analytics</h2>
                    <p>We use Google Analytics to know how many people visit the site and which pages they use most. We use it only for aggregate statistics about our site: Google Signals and ad personalisation are turned off and Google Analytics does not store IP addresses.</p>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>What it does</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>_ga</code>, <code>_ga_T0CTM2SBJJ</code></td>
                                    <td>Count visits and sessions in aggregate</td>
                                    <td>Up to 2 years</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>Google Analytics is on by default. You can turn it off at any time with the button below or the one in the footer of every page: the site stops loading it, deletes its cookies and remembers your choice in this browser. Turning it off changes nothing in how the site works.</p>
                    <p>
                        <button type="button" class="static-btn static-btn--primary static-analytics-toggle" data-analytics-toggle data-label-on="Google Analytics is on: click to turn it off" data-label-off="Google Analytics is off: click to turn it back on">
                            <i class="fa-solid fa-chart-simple"></i>
                            <span data-analytics-label>Google Analytics is on: click to turn it off</span>
                        </button>
                    </p>
                    <p>Google's privacy policy: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">policies.google.com/privacy</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="third-parties">
                    <h2>4. Third-party content</h2>
                    <p>Some pages show content from other services: YouTube and Streamable videos, Spotify tracks, Tenor and Klipy GIFs, images from Discord and other sites. When you load them, your browser connects to those services, which receive your IP and may store their own cookies under their policies. Some graphic libraries come from jsDelivr, Cloudflare and Google (fonts and jQuery).</p>
                    <ul>
                        <li>YouTube: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">policies.google.com/privacy</a></li>
                        <li>Spotify: <a href="https://www.spotify.com/legal/privacy-policy/" target="_blank" rel="noopener">spotify.com/legal/privacy-policy</a></li>
                        <li>Discord: <a href="https://discord.com/privacy" target="_blank" rel="noopener">discord.com/privacy</a></li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="manage">
                    <h2>5. How to manage them</h2>
                    <p>Besides the Google Analytics button, you can view and delete cookies in your browser settings, or block third-party ones. Guides for the main browsers: <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Chrome</a>, <a href="https://support.mozilla.org/kb/clear-cookies-and-site-data-firefox" target="_blank" rel="noopener">Firefox</a>, <a href="https://support.apple.com/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a>, <a href="https://support.microsoft.com/edge/manage-cookies-in-microsoft-edge-view-allow-block-delete-and-use" target="_blank" rel="noopener">Edge</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="changes">
                    <h2>6. Changes and contact</h2>
                    <p>We update this page when the site's cookies change; the date of the last update is at the top. For questions write to <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
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
