<?php
require_once '../config/session_init.php';
require_once '../includes/theme.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = '10 ottobre 2026';
?>
<!DOCTYPE html>
<html lang="it"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include '../includes/head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <title>Cripsum™ - Cookie policy</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/assets/static/static.css') ?>">
    <script src="/assets/static/static.js?v=1.0-static" defer></script>
</head>

<body class="static-page">
    <?php include '../includes/navbar.php'; ?>

    <div class="static-bg" aria-hidden="true">
    </div>

    <main class="static-shell">
        <section class="static-hero static-reveal">
            <h1>Cookie policy</h1>
            <p>Quali cookie e strumenti simili usa Cripsum™ e come puoi gestirli.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fa-solid fa-calendar"></i> Aggiornata il <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fa-solid fa-cookie-bite"></i> Niente pubblicità</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Indice</h2>
                <a href="#cosa-sono">1. Cosa sono</a>
                <a href="#tecnici">2. Cookie tecnici</a>
                <a href="#analytics">3. Google Analytics</a>
                <a href="#terze-parti">4. Contenuti di terze parti</a>
                <a href="#gestione">5. Come gestirli</a>
                <a href="#modifiche">6. Modifiche e contatti</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="cosa-sono">
                    <h2>1. Cosa sono</h2>
                    <p>I cookie sono piccoli file che un sito salva nel browser per ricordarsi di te tra una pagina e l'altra. Funzionano in modo simile il localStorage e gli altri strumenti di memoria del browser: in questa pagina li chiamiamo tutti «cookie».</p>
                    <p>Su Cripsum™ non ci sono pubblicità né cookie di profilazione pubblicitaria. Per sapere come trattiamo i dati personali leggi l'<a href="privacy">Informativa privacy</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="tecnici">
                    <h2>2. Cookie tecnici</h2>
                    <p>Servono a far funzionare il sito e non richiedono il consenso. Non si possono disattivare dal sito; se li blocchi dal browser, l'accesso e alcune funzioni smettono di funzionare.</p>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>A cosa serve</th>
                                    <th>Durata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>__Host-cripsum_session</code></td>
                                    <td>Ti tiene connesso e protegge i moduli da richieste false</td>
                                    <td>Fino a 14 giorni</td>
                                </tr>
                                <tr>
                                    <td><code>__Host-cripsum_device</code></td>
                                    <td>Riconosce il dispositivo per la lista dei dispositivi collegati e gli avvisi di sicurezza</td>
                                    <td>1 anno</td>
                                </tr>
                                <tr>
                                    <td><code>cripsum_lang</code></td>
                                    <td>Ricorda la lingua (italiano o inglese)</td>
                                    <td>1 anno</td>
                                </tr>
                                <tr>
                                    <td>Cookie degli achievement e delle pagine</td>
                                    <td>Ricordano i progressi di alcuni achievement e piccole preferenze, per esempio gli edit già visti</td>
                                    <td>Finché non li cancelli</td>
                                </tr>
                                <tr>
                                    <td>localStorage (<code>cripsum.*</code> e simili)</td>
                                    <td>Preferenze come volume, suoni, filtri, ordinamenti, avvisi delle chat e la tua scelta su Google Analytics; le bozze dei messaggi che non hai ancora inviato e le emoji usate di recente</td>
                                    <td>Finché non li cancelli</td>
                                </tr>
                                <tr>
                                    <td>Google reCAPTCHA</td>
                                    <td>Distingue le persone dai bot nella pagina di registrazione</td>
                                    <td>Fino a 6 mesi</td>
                                </tr>
                                <tr>
                                    <td>PayPal, Stripe</td>
                                    <td>Fanno funzionare il pagamento, solo nelle pagine di acquisto</td>
                                    <td>Secondo le loro policy</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="static-legal-section static-reveal" id="analytics">
                    <h2>3. Google Analytics</h2>
                    <p>Usiamo Google Analytics per sapere quante persone visitano il sito e quali pagine usano di più. Lo usiamo solo per statistiche aggregate sul nostro sito: Google Signals e personalizzazione degli annunci sono disattivati e Google Analytics non conserva gli indirizzi IP.</p>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>A cosa serve</th>
                                    <th>Durata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>_ga</code>, <code>_ga_T0CTM2SBJJ</code></td>
                                    <td>Contano visite e sessioni in modo aggregato</td>
                                    <td>Fino a 2 anni</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>Google Analytics è attivo di default. Puoi spegnerlo quando vuoi con il pulsante qui sotto o con quello nel footer di ogni pagina: il sito smette di caricarlo, cancella i suoi cookie e ricorda la scelta in questo browser. Spegnerlo non cambia nulla nel funzionamento del sito.</p>
                    <p>
                        <button type="button" class="static-btn static-btn--primary static-analytics-toggle" data-analytics-toggle data-label-on="Google Analytics attivo: clicca per spegnerlo" data-label-off="Google Analytics spento: clicca per riattivarlo">
                            <i class="fa-solid fa-chart-simple"></i>
                            <span data-analytics-label>Google Analytics attivo: clicca per spegnerlo</span>
                        </button>
                    </p>
                    <p>Informativa di Google: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">policies.google.com/privacy</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="terze-parti">
                    <h2>4. Contenuti di terze parti</h2>
                    <p>Alcune pagine mostrano contenuti di altri servizi: video di YouTube e Streamable, brani di Spotify, GIF di Tenor e Klipy, immagini da Discord e da altri siti. Quando li carichi, il browser si collega a quei servizi, che ricevono il tuo IP e possono salvare cookie propri secondo le loro policy. Alcune librerie grafiche arrivano da jsDelivr, Cloudflare e Google (font e jQuery).</p>
                    <ul>
                        <li>YouTube: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">policies.google.com/privacy</a></li>
                        <li>Spotify: <a href="https://www.spotify.com/legal/privacy-policy/" target="_blank" rel="noopener">spotify.com/legal/privacy-policy</a></li>
                        <li>Discord: <a href="https://discord.com/privacy" target="_blank" rel="noopener">discord.com/privacy</a></li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="gestione">
                    <h2>5. Come gestirli</h2>
                    <p>Oltre al pulsante di Google Analytics, puoi vedere e cancellare i cookie dalle impostazioni del browser, oppure bloccare quelli di terze parti. Le guide dei browser principali: <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Chrome</a>, <a href="https://support.mozilla.org/kb/clear-cookies-and-site-data-firefox" target="_blank" rel="noopener">Firefox</a>, <a href="https://support.apple.com/it-it/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a>, <a href="https://support.microsoft.com/it-it/edge/manage-cookies-in-microsoft-edge-view-allow-block-delete-and-use" target="_blank" rel="noopener">Edge</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="modifiche">
                    <h2>6. Modifiche e contatti</h2>
                    <p>Aggiorniamo questa pagina quando cambiano i cookie del sito; in cima trovi la data dell'ultimo aggiornamento. Per domande scrivi a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
                </section>
            </div>
        </div>
    </main>

    <button class="static-top-btn" id="staticBackTop" type="button" aria-label="Torna su">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
