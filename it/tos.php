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
    <title>Cripsum™ - Termini e Condizioni</title>

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
                <span class="static-pill">Termini</span>
                <h1>Termini e Condizioni</h1>
                <p>Regolamento ufficiale e condizioni d'uso della piattaforma Cripsum™.</p>
                <div class="static-meta">
                    <span class="static-chip"><i class="fa-solid fa-calendar"></i> Aggiornati: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="static-chip"><i class="fa-solid fa-scale-balanced"></i> Regole del sito</span>
                </div>
            </div>
            <div class="static-hero__logo-container">
                <img src="/img/tos.gif" alt="Cripsum™ TOS Logo" class="static-tos-logo">
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Indice</h2>
                <a href="#accettazione">1. Accettazione dei Termini</a>
                <a href="#eta">2. Requisiti di età</a>
                <a href="#account">3. Registrazione e Sicurezza</a>
                <a href="#discord">4. Collegamento Discord</a>
                <a href="#pagamenti">5. Pagamenti e Valute Virtuali</a>
                <a href="#premium">6. Abbonamento Premium</a>
                <a href="#giochi">7. Gacha, Lootbox e Duelli</a>
                <a href="#contenuti">8. Contenuti dell'Utente (Shitpost)</a>
                <a href="#ticket">9. Ticket di Supporto e Chat</a>
                <a href="#moderazione">10. Sospensione e Terminazione</a>
                <a href="#responsabilita">11. Limitazione di Responsabilità</a>
                <a href="#manleva">12. Clausola di Manleva</a>
                <a href="#modifiche">13. Modifiche ai Termini</a>
                <a href="#contatti">14. Contatti</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="accettazione">
                    <h2>1. Accettazione dei Termini</h2>
                    <p>Accedendo o utilizzando il sito web Cripsum™ (di seguito "Piattaforma" o "Servizio"), l'utente accetta di essere vincolato dai presenti Termini e Condizioni. Se non si accettano tutti i termini qui stabiliti, l'utente non è autorizzato a utilizzare la Piattaforma.</p>
                </section>

                <section class="static-legal-section static-reveal" id="eta">
                    <h2>2. Requisiti di età</h2>
                    <p>La Piattaforma è destinata a utenti che abbiano compiuto almeno 18 anni. Dichiarando di avere 18 anni o più al momento dell'iscrizione, l'utente conferma di essere maggiorenne secondo le leggi del proprio paese di residenza e si assume la piena responsabilità legale delle proprie azioni sulla Piattaforma.</p>
                    <p>Se l'utente ha un'età inferiore ai 18 anni, può utilizzare i Servizi solo sotto la supervisione e con il consenso esplicito di un genitore o tutore legale, che si assume la responsabilità dell'operato del minore.</p>
                </section>

                <section class="static-legal-section static-reveal" id="account">
                    <h2>3. Registrazione e Sicurezza dell'Account</h2>
                    <p>Per accedere ad alcune funzionalità, l'utente deve creare un account. L'utente è l'unico responsabile della riservatezza delle proprie credenziali di accesso (username e password) e di qualsiasi attività svolta tramite il proprio account.</p>
                    <p>L'utente si impegna a notificare immediatamente al team di Cripsum™ qualsiasi uso non autorizzato o violazione della sicurezza del proprio account. Cripsum™ non sarà responsabile per eventuali perdite o danni derivanti dal mancato rispetto di questo obbligo.</p>
                </section>

                <section class="static-legal-section static-reveal" id="discord">
                    <h2>4. Collegamento Discord e Bot di Presenza</h2>
                    <p>La Piattaforma offre l'integrazione opzionale con Discord tramite collegamento dell'account (memorizzando il proprio `discord_id`). Collegando l'account e autorizzando il nostro Bot di Presenza, l'utente accetta che i dati relativi alla propria attività su Discord (stato online, giochi in esecuzione, dettagli della presenza) vengano mostrati pubblicamente all'interno del proprio profilo Cripsum™.</p>
                    <p>L'utente è libero di revocare tale autorizzazione in qualsiasi momento scollegando l'account Discord dalle impostazioni del profilo.</p>
                </section>

                <section class="static-legal-section static-reveal" id="pagamenti">
                    <h2>5. Pagamenti, Acquisti e Valute Virtuali</h2>
                    <p>La Piattaforma permette l'acquisto di valuta virtuale denominata <strong>"Godo Shards"</strong> e l'acquisizione di <strong>"Godos"</strong> (punti di gioco). Queste valute sono elementi puramente virtuali destinati all'intrattenimento all'interno della Piattaforma:</p>
                    <ul>
                        <li>Godo Shards e Godos <strong>non costituiscono denaro reale</strong>, non hanno alcun valore monetario e non possono in nessun caso essere convertiti, riscattati o scambiati con valuta reale o altri beni fisici.</li>
                        <li>I pagamenti vengono elaborati in sicurezza tramite la piattaforma terza **PayPal**. L'utente accetta di rispettare i termini e le condizioni di PayPal durante le transazioni.</li>
                        <li><strong>Politica di Rimborso:</strong> Tutti gli acquisti di Godo Shards e servizi digitali sono definitivi e non rimborsabili. Ai sensi dell'art. 59, lett. o) del Codice del Consumo (D.Lgs. 206/2005), il diritto di recesso è escluso in quanto si tratta di fornitura di contenuto digitale mediante supporto non materiale, la cui esecuzione inizia immediatamente dopo l'avvenuto pagamento.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="premium">
                    <h2>6. Abbonamento Premium</h2>
                    <p>L'utente può acquistare lo status **Premium** per sbloccare vantaggi estetici e funzionalità esclusive sulla Piattaforma. I vantaggi legati al Premium sono descritti nelle relative pagine di acquisto e sono soggetti a modifiche o aggiornamenti unilaterali da parte del team di Cripsum™.</p>
                    <p>In caso di ban o sospensione dell'account per violazione dei presenti Termini, lo status Premium verrà revocato immediatamente senza alcun diritto al rimborso parziale o totale del periodo rimanente.</p>
                </section>

                <section class="static-legal-section static-reveal" id="giochi">
                    <h2>7. Gacha, Lootbox e Duelli (Meccaniche di Gioco)</h2>
                    <p>La Piattaforma include meccaniche di gioco basate su algoritmi di probabilità e fortuna, quali l'estrazione di personaggi (Gacha), l'apertura di scrigni (Lootbox) e scontri virtuali (Duelli):</p>
                    <ul>
                        <li>Tali attività hanno scopo puramente ricreativo e **non costituiscono gioco d'azzardo reale**, in quanto le valute utilizzate e i beni digitali ottenuti non hanno valore economico nel mondo reale.</li>
                        <li>I tassi di probabilità (drop rates) sono gestiti tramite algoritmi interni. Cripsum™ non garantisce l'ottenimento di specifici oggetti virtuali o esiti positivi nei duelli. Il software viene fornito "così com'è" e i risultati calcolati dal server sono definitivi.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="contenuti">
                    <h2>8. Contenuti dell'Utente e Moderazione (Shitpost)</h2>
                    <p>Gli utenti possono caricare e pubblicare contenuti sulla Piattaforma sotto forma di testi, immagini, meme o link (inclusi "Shitpost" e "Top Rimasti").</p>
                    <ul>
                        <li>L'utente è il solo e unico responsabile dei contenuti che pubblica. Si impegna a non caricare materiale che violi il diritto d'autore, che sia diffamatorio, offensivo, pornografico, pedopornografico, o che inciti all'odio, alla violenza o a comportamenti illegali.</li>
                        <li>Caricando un contenuto, l'utente concede a Cripsum™ una licenza gratuita, perpetua, non esclusiva e mondiale per ospitare, visualizzare, distribuire e riprodurre tale materiale all'interno della Piattaforma.</li>
                        <li>Il team di Cripsum™ si riserva il diritto insindacabile di moderare, nascondere, modificare o eliminare qualsiasi contenuto caricato dall'utente senza alcun preavviso e a propria discrezione.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="ticket">
                    <h2>9. Ticket di Supporto e Chat</h2>
                    <p>Il Centro Messaggi e il sistema dei Ticket consentono una chat diretta tra l'utente e gli amministratori. L'utente si impegna a utilizzare questo strumento in modo civile e rispettoso:</p>
                    <ul>
                        <li>È severamente vietato inviare allegati contenenti malware, virus, materiale protetto da copyright senza autorizzazione, o immagini dal contenuto illegale o esplicito.</li>
                        <li>L'invio di allegati dannosi o offensivi comporterà l'immediata chiusura del ticket e la potenziale sospensione permanente dell'account dell'utente.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="moderazione">
                    <h2>10. Sospensione e Terminazione dell'Account (Ban)</h2>
                    <p>Cripsum™ si riserva il diritto di sospendere, limitare o eliminare definitivamente l'account di qualsiasi utente, a propria discrezione, senza preavviso e senza alcuna responsabilità finanziaria o legale, in caso di:</p>
                    <ul>
                        <li>Violazione dei presenti Termini e Condizioni.</li>
                        <li>Comportamenti fraudolenti, manipolazione dei dati di gioco (exploit, hack, botting) o disturbo alla community.</li>
                        <li>Richiesta da parte delle autorità giudiziarie competenti.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="responsabilita">
                    <h2>11. Limitazione di Responsabilità</h2>
                    <p>LA PIATTAFORMA E TUTTI I SERVIZI CORRELATI SONO FORNITI "COSÌ COME SONO" E "COME DISPONIBILI", SENZA ALCUN TIPO DI GARANZIA ESPLICITA O IMPLICITA.</p>
                    <p>Cripsum™ non garantisce che il servizio sia privo di interruzioni, errori, bug o perdita di dati. In nessun caso Cripsum™, i suoi amministratori o collaboratori saranno responsabili per danni diretti, indiretti, incidentali, speciali o consequenziali (inclusa, a titolo esemplificativo, la perdita di valute virtuali, personaggi di gioco, o l'indisponibilità del sito) derivanti dall'uso o dall'impossibilità di utilizzare la Piattaforma.</p>
                </section>

                <section class="static-legal-section static-reveal" id="manleva">
                    <h2>12. Clausola di Manleva</h2>
                    <p>L'utente accetta di manlevare, difendere e tenere indenne Cripsum™, i suoi amministratori e collaboratori da qualsiasi pretesa, danno, perdita, responsabilità, costo o spesa (incluse le spese legali) derivanti dalla violazione da parte dell'utente dei presenti Termini e Condizioni o dall'uso improprio o illegale dei Servizi.</p>
                </section>

                <section class="static-legal-section static-reveal" id="modifiche">
                    <h2>13. Modifiche ai Termini</h2>
                    <p>Il team di Cripsum™ si riserva il diritto di aggiornare o modificare i presenti Termini e Condizioni in qualsiasi momento. Le modifiche saranno rese note pubblicando la versione aggiornata su questa pagina con la data dell'ultimo aggiornamento. L'uso continuato della Piattaforma dopo la pubblicazione delle modifiche costituisce accettazione dei nuovi Termini.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>14. Contatti</h2>
                    <p>Per qualsiasi domanda, segnalazione o richiesta di chiarimenti in merito ai presenti Termini, puoi contattarci all'indirizzo email: <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>.</p>
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