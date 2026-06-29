<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = 'Giugno 2026';
?>
<!DOCTYPE html>
<html lang="it">
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
            <h1>Informativa sulla Privacy</h1>
            <p>Come raccogliamo, utilizziamo e proteggiamo i tuoi dati su Cripsum™.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fa-solid fa-calendar"></i> Aggiornata: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fa-solid fa-shield-halved"></i> GDPR & Sicurezza</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Indice</h2>
                <a href="#titolare">1. Titolare del Trattamento</a>
                <a href="#raccolta">2. Dati raccolti</a>
                <a href="#uso">3. Finalità del trattamento</a>
                <a href="#base-giuridica">4. Base giuridica</a>
                <a href="#conservazione">5. Conservazione dei dati</a>
                <a href="#condivisione">6. Condivisione dei dati</a>
                <a href="#diritti">7. I tuoi diritti (GDPR)</a>
                <a href="#sicurezza">8. Sicurezza dei dati</a>
                <a href="#contatti">9. Contatti</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="titolare">
                    <h2>1. Titolare del Trattamento</h2>
                    <p>Il titolare del trattamento dei dati personali raccolti tramite la piattaforma Cripsum™ è il team di amministrazione di Cripsum™ (di seguito "Titolare" o "Noi"). Per qualsiasi comunicazione o richiesta in merito alla privacy, puoi contattarci all'indirizzo email dedicato: <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="raccolta">
                    <h2>2. Dati raccolti</h2>
                    <p>Raccogliamo ed elaboriamo le seguenti categorie di dati personali per fornirti i nostri servizi:</p>
                    <ul>
                        <li><strong>Dati dell'account:</strong> Nome utente, indirizzo email e password crittografata (tramite algoritmo di hash sicuro).</li>
                        <li><strong>Dati di integrazione con terze parti (Discord):</strong> Se decidi di collegare il tuo account Discord, raccogliamo e memorizziamo il tuo Discord ID, il tuo username, l'avatar e, se abiliti il nostro Bot di Presenza, le informazioni sullo stato della tua attività online e i giochi in esecuzione.</li>
                        <li><strong>Dati di pagamento:</strong> Per gli acquisti di Godo Shards o dell'abbonamento Premium elaborati tramite PayPal, raccogliamo l'ID della transazione, lo stato del pagamento, l'indirizzo email associato al tuo conto PayPal e l'importo pagato. <em>Non raccogliamo né memorizziamo in alcun modo i dati della tua carta di credito o di debito.</em></li>
                        <li><strong>Dati di navigazione e tecnici:</strong> Indirizzo IP, tipo di browser, dati di sessione (tramite cookie tecnici necessari al funzionamento del sito) e registri (log) delle attività per motivi di sicurezza e prevenzione delle frodi.</li>
                        <li><strong>Centro Messaggi e Ticket di Supporto:</strong> I testi dei messaggi scambiati, i ticket di supporto aperti e qualsiasi file o immagine allegato dall'utente all'interno della chat di supporto.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="uso">
                    <h2>3. Finalità del trattamento</h2>
                    <p>I tuoi dati vengono trattati esclusivamente per le seguenti finalità:</p>
                    <ul>
                        <li><strong>Erogazione del servizio:</strong> Creazione e gestione dell'account, partecipazione ai giochi (Gacha, Duelli, Lootbox), personalizzazione del profilo utente e visualizzazione sullo stato online di Discord.</li>
                        <li><strong>Elaborazione delle transazioni:</strong> Gestire l'acquisto di valute di gioco virtuali (Godo Shards) e l'attivazione/rinnovo dello status Premium.</li>
                        <li><strong>Assistenza clienti:</strong> Gestire e rispondere ai ticket di supporto e alle segnalazioni degli utenti inviate tramite il Centro Messaggi.</li>
                        <li><strong>Sicurezza e moderazione:</strong> Monitorare le attività sul sito per prevenire abusi, botting, tentativi di hacking e per moderare i contenuti generati dagli utenti (Shitpost, commenti, ecc.) al fine di garantire il rispetto dei Termini di Servizio.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="base-giuridica">
                    <h2>4. Base giuridica del trattamento</h2>
                    <p>Trattiamo i tuoi dati personali sulla base delle seguenti condizioni di liceità:</p>
                    <ul>
                        <li><strong>Esecuzione di un contratto:</strong> Per la creazione del tuo account e per la fornitura dei servizi di gioco e dei prodotti digitali acquistati.</li>
                        <li><strong>Consenso dell'interessato:</strong> Per il collegamento opzionale del tuo account Discord e l'attivazione del Bot di Presenza. Puoi revocare questo consenso in qualsiasi momento scollegando l'account dalle impostazioni del profilo.</li>
                        <li><strong>Legittimo interesse:</strong> Per garantire la sicurezza del sito, prevenire frodi e abusi, e moderare i contenuti caricati sulla piattaforma.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="conservazione">
                    <h2>5. Conservazione dei dati</h2>
                    <p>I tuoi dati personali saranno conservati solo per il tempo strettamente necessario a conseguire le finalità per le quali sono stati raccolti:</p>
                    <ul>
                        <li>I dati dell'account e del profilo rimangono attivi fino a quando l'utente non decide di richiedere la cancellazione del proprio account.</li>
                        <li>I dati relativi alle transazioni finanziarie (tramite PayPal) vengono conservati per il periodo minimo richiesto dalle normative fiscali e antiriciclaggio vigenti.</li>
                        <li>I ticket di supporto e i messaggi scambiati nel Centro Messaggi vengono conservati per ragioni storiche e di tutela legale del Titolare.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="condivisione">
                    <h2>6. Condivisione dei dati</h2>
                    <p>Non vendiamo né cediamo i tuoi dati personali a soggetti terzi. I dati possono essere condivisi solo con:</p>
                    <ul>
                        <li><strong>Fornitori di servizi terzi autorizzati:</strong> Come PayPal (per l'elaborazione dei pagamenti) e Cloudflare (per la sicurezza e la protezione da attacchi informatici).</li>
                        <li><strong>Autorità competenti:</strong> Qualora richiesto dalla legge o per prevenire attività illecite o fraudolente sulla piattaforma.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="diritti">
                    <h2>7. I tuoi diritti (GDPR)</h2>
                    <p>In conformità con il Regolamento Generale sulla Protezione dei Dati (GDPR - Regolamento UE 2016/679), hai il diritto di:</p>
                    <ul>
                        <li>Accedere ai tuoi dati in nostro possesso e richiederne una copia.</li>
                        <li>Richiedere la rettifica di dati inesatti o incompleti.</li>
                        <li>Richiedere la cancellazione dei tuoi dati personali ("diritto all'oblio"), fatto salvo il nostro diritto/dovere di conservarli per adempiere a obblighi legali.</li>
                        <li>Limitare o opporsi al trattamento dei tuoi dati personali in determinate circostanze.</li>
                        <li>Richiedere la portabilità dei dati in un formato strutturato e leggibile da dispositivo automatico.</li>
                    </ul>
                    <p>Per esercitare questi diritti, puoi inviare una richiesta scritta a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>. Risponderemo alla tua richiesta entro i termini previsti dalla legge (30 giorni).</p>
                </section>

                <section class="static-legal-section static-reveal" id="sicurezza">
                    <h2>8. Sicurezza dei dati</h2>
                    <p>Implementiamo misure di sicurezza tecniche e organizzative adeguate per proteggere i tuoi dati personali da perdita, abuso, accesso non autorizzato, divulgazione o alterazione. Utilizziamo la crittografia SSL/HTTPS per tutte le trasmissioni di dati sul sito e tecniche di hashing sicuro per proteggere le password degli utenti.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>9. Contatti</h2>
                    <p>Per qualsiasi domanda, dubbio o reclamo in merito alla presente Informativa sulla Privacy o al trattamento dei tuoi dati, puoi contattarci all'indirizzo email: <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
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
