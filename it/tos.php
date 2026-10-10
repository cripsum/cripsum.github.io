<?php
require_once '../config/session_init.php';
require_once '../includes/theme.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = '2 ottobre 2026';
?>
<!DOCTYPE html>
<html lang="it"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include '../includes/head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <title>Cripsum™ - Termini di servizio</title>

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
                <h1>Termini di servizio</h1>
                <p>Le regole per usare Cripsum™, comprare Premium e Godo Shards e stare nella community.</p>
                <div class="static-meta">
                    <span class="static-chip"><i class="fa-solid fa-calendar"></i> Aggiornati il <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
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
                <a href="#in-breve">In breve</a>
                <a href="#gestore">1. Chi gestisce il sito</a>
                <a href="#accettazione">2. Accettazione</a>
                <a href="#servizio">3. Cos'è Cripsum™</a>
                <a href="#eta">4. Età minima e minori</a>
                <a href="#account">5. Account</a>
                <a href="#regole">6. Regole di comportamento</a>
                <a href="#contenuti">7. Contenuti degli utenti</a>
                <a href="#moderazione">8. Segnalazioni e moderazione</a>
                <a href="#chat">9. Chat, messaggi e ticket</a>
                <a href="#goonland">10. GoonLand e contenuti 18+</a>
                <a href="#valute">11. Godos e Godo Shards</a>
                <a href="#giochi">12. Gacha, lootbox e giochi</a>
                <a href="#acquisti">13. Acquisti</a>
                <a href="#negozio">14. Negozio, Merch e Download</a>
                <a href="#donazioni">15. Donazioni</a>
                <a href="#terze-parti">16. Servizi di terze parti</a>
                <a href="#proprieta">17. Proprietà intellettuale</a>
                <a href="#persone">18. Persone reali sul sito</a>
                <a href="#api">19. API pubbliche</a>
                <a href="#disponibilita">20. Disponibilità del servizio</a>
                <a href="#chiusura">21. Sospensione e chiusura</a>
                <a href="#responsabilita">22. Responsabilità</a>
                <a href="#manleva">23. Manleva</a>
                <a href="#modifiche">24. Modifiche ai Termini</a>
                <a href="#legge">25. Legge e controversie</a>
                <a href="#finali">26. Disposizioni finali</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-legal-section--summary static-reveal" id="in-breve">
                    <h2>In breve</h2>
                    <p>Questo riassunto non sostituisce i Termini, ma ti dice le cose più importanti.</p>
                    <ul>
                        <li>Per creare un account devi avere <strong>almeno 14 anni</strong>. GoonLand e le modalità 18+ sono solo per maggiorenni.</li>
                        <li>Rispetta gli altri: niente insulti, bullismo, contenuti illegali o foto di altre persone senza il loro permesso.</li>
                        <li>Godos e Godo Shards sono valute di gioco: non valgono soldi veri e non si possono rivendere.</li>
                        <li>Premium e Shards si comprano con PayPal o Stripe. Li ricevi subito, per questo rinunci al recesso di 14 giorni; se qualcosa non arriva o non funziona, te lo sistemiamo o ti rimborsiamo.</li>
                        <li>Se sei minorenne, chiedi il permesso a un genitore prima di comprare qualcosa.</li>
                        <li>Negozio e Merch sono finti: non si paga e non arriva niente.</li>
                        <li>Puoi segnalare contenuti e chiedere aiuto contro il cyberbullismo: rispondiamo entro 24 ore.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="gestore">
                    <h2>1. Chi gestisce il sito</h2>
                    <p>Cripsum™ (cripsum.com, di seguito «il sito») è un progetto personale, non costituito in società, gestito dal team di Cripsum™ (di seguito «noi»), composto da privati residenti in Italia.</p>
                    <p>Contatti:</p>
                    <ul>
                        <li><a href="mailto:tos@cripsum.com">tos@cripsum.com</a> per questi Termini, gli acquisti, le segnalazioni e i ricorsi;</li>
                        <li><a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a> per i dati personali;</li>
                        <li>la pagina <a href="supporto">Supporto</a> per l'assistenza.</li>
                    </ul>
                    <p>Questi recapiti sono anche il punto di contatto unico per utenti e autorità previsto dagli articoli 11 e 12 del Regolamento (UE) 2022/2065 sui servizi digitali (DSA). Puoi scriverci in italiano o in inglese. Se un'autorità, o chi esercita un diritto previsto dalla legge, ha bisogno dei dati identificativi del gestore, li forniamo su richiesta motivata.</p>
                </section>

                <section class="static-legal-section static-reveal" id="accettazione">
                    <h2>2. Accettazione</h2>
                    <p>Usando il sito accetti questi Termini. Quando crei un account li accetti in modo espresso con la spunta nella pagina di registrazione (anche se ti registri con Google). Fa parte dei Termini anche il <a href="chat-policy">Regolamento della chat</a>.</p>
                    <p>L'<a href="privacy">Informativa privacy</a> e la <a href="cookie">Cookie policy</a> non sono contratti: spiegano come trattiamo i tuoi dati. Se non sei d'accordo con i Termini, non usare il sito.</p>
                </section>

                <section class="static-legal-section static-reveal" id="servizio">
                    <h2>3. Cos'è Cripsum™</h2>
                    <p>Cripsum™ è un sito di intrattenimento gratuito con profili personalizzabili, amici e follow, chat globale, private e di gruppo, contenuti degli utenti (Shitpost, Top Rimasti, commenti), giochi (gacha, lootbox, duelli, Subway Surfers, Animespot, Pullspot e altri minigiochi), missioni e achievement, Cripsum Rewind, Cripsumpedia, le pagine del team esports OHPY, download gratuiti e un negozio parodia.</p>
                    <p>Molti contenuti sono ironici e pensati come meme: non vanno presi alla lettera. Parte del codice del sito è pubblica su <a href="https://github.com/cripsum/cripsum.github.io" target="_blank" rel="noopener">GitHub</a>.</p>
                    <p>Le uniche operazioni con denaro reale sono l'acquisto di Premium e di Godo Shards (sezione 13) e le donazioni volontarie (sezione 15).</p>
                </section>

                <section class="static-legal-section static-reveal" id="eta">
                    <h2>4. Età minima e minori</h2>
                    <ul>
                        <li>Per creare un account devi avere <strong>almeno 14 anni</strong>. Registrandoti dichiari di averli.</li>
                        <li>Se hai tra 14 e 17 anni puoi usare il sito, tranne GoonLand e le modalità 18+ (sezione 10). Per comprare Premium o Shards ti serve il permesso di un genitore o di chi ne fa le veci.</li>
                        <li>Se scopriamo che un account appartiene a una persona con meno di 14 anni, lo chiudiamo.</li>
                        <li>Se sei un genitore e pensi che tuo figlio abbia un account pur avendo meno di 14 anni, o abbia fatto un acquisto senza permesso, scrivici a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>: chiudiamo l'account o valutiamo il rimborso dell'acquisto.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="account">
                    <h2>5. Account</h2>
                    <ul>
                        <li>Usa un indirizzo email tuo e valido: ci serve per verificare l'account, recuperare la password e mandarti le conferme d'acquisto.</li>
                        <li>L'account è personale: non puoi venderlo, prestarlo, scambiarlo o cederlo.</li>
                        <li>Lo username non deve essere offensivo né far credere di essere un'altra persona o un membro dello staff.</li>
                        <li>Sei responsabile di quello che succede con il tuo account e della sicurezza della password. Ti consigliamo di attivare la verifica in due passaggi (2FA). Dalle impostazioni puoi vedere i dispositivi collegati e scollegarli.</li>
                        <li>Se pensi che qualcuno abbia usato il tuo account, cambia la password e avvisaci subito.</li>
                        <li>Se accedi con Google o colleghi Discord, valgono anche i termini di quei servizi.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="regole">
                    <h2>6. Regole di comportamento</h2>
                    <p>Sul sito è vietato:</p>
                    <ul>
                        <li>molestare, insultare, minacciare o discriminare altre persone; fare bullismo o cyberbullismo; incitare all'odio o alla violenza;</li>
                        <li>pubblicare dati personali, foto o video di altre persone senza il loro consenso, soprattutto se sono minori;</li>
                        <li>pubblicare contenuti sessuali fuori da GoonLand e, ovunque, qualsiasi contenuto sessuale che coinvolga minori, anche disegnato o generato (tolleranza zero: lo segnaliamo alle autorità);</li>
                        <li>pubblicare contenuti illegali, violenti o che incoraggiano l'autolesionismo;</li>
                        <li>fare spam, pubblicità non autorizzata, phishing o diffondere malware;</li>
                        <li>impersonare altri utenti, lo staff o persone reali;</li>
                        <li>usare cheat, exploit, bot, script o automazioni che alterano giochi, classifiche, missioni o valute, o sfruttare un bug invece di segnalarlo;</li>
                        <li>raccogliere dati in massa (scraping) o sovraccaricare il sito e le API;</li>
                        <li>aggirare ban, limiti o controlli di età, anche con altri account.</li>
                    </ul>
                    <p>Il <a href="chat-policy">Regolamento della chat</a> aggiunge alcune regole specifiche per le chat.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contenuti">
                    <h2>7. Contenuti degli utenti</h2>
                    <ul>
                        <li>I contenuti che pubblichi (testi, immagini, video, link, profilo) restano tuoi e ne sei responsabile. Pubblicandoli dichiari di avere i diritti necessari e il consenso delle persone che compaiono.</li>
                        <li>Ci dai una licenza gratuita, non esclusiva e valida in tutto il mondo per ospitarli, mostrarli agli altri utenti nelle sezioni in cui li pubblichi e adattarli al sito (miniature, anteprime), solo per far funzionare il sito.</li>
                        <li>La licenza finisce quando cancelli il contenuto o l'account, tranne che per le copie di backup, che si sovrascrivono da sole in poche settimane.</li>
                        <li>Shitpost e Top Rimasti vengono approvati dallo staff prima di essere pubblicati.</li>
                        <li>Possiamo rimuovere o nascondere contenuti che violano questi Termini o la legge, come spiegato nella sezione 8.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="moderazione">
                    <h2>8. Segnalazioni e moderazione</h2>
                    <h3>Come segnalare</h3>
                    <p>Puoi segnalare profili, post, commenti e messaggi della chat globale con i pulsanti «Segnala», oppure scrivere a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> indicando dove si trova il contenuto (il link), perché secondo te è illegale o vietato e un tuo recapito. Se segnali un contenuto di abuso su minori puoi anche restare anonimo.</p>
                    <h3>Cosa facciamo</h3>
                    <p>Le segnalazioni vengono esaminate a mano dallo staff, in modo tempestivo, diligente e imparziale: non usiamo sistemi automatici per decidere. A seconda della gravità e delle ripetizioni possiamo rimuovere o nascondere un contenuto, limitare alcune funzioni (per esempio il mute in chat), sospendere l'account per un periodo o chiuderlo definitivamente.</p>
                    <h3>Motivazione e ricorso</h3>
                    <p>Se interveniamo su un tuo contenuto o sul tuo account ti diciamo cosa abbiamo fatto e perché, in inbox o via email, salvo quando la legge o un'autorità lo impediscono o si tratta di spam. Se non sei d'accordo puoi scrivere a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> entro 6 mesi: la decisione viene riesaminata, se possibile da una persona diversa. Puoi comunque rivolgerti a un'autorità o a un giudice.</p>
                    <p>Se un contenuto fa pensare a un reato che mette in pericolo la vita o la sicurezza di qualcuno, lo segnaliamo alle autorità.</p>
                    <h3>Cyberbullismo</h3>
                    <p>Se hai almeno 14 anni e sei vittima di cyberbullismo sul sito, o se sei il genitore di un minore che lo è, puoi chiederci di oscurare, rimuovere o bloccare i contenuti scrivendo a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> con oggetto «Cyberbullismo». Ti confermiamo di aver preso in carico la richiesta entro 24 ore e interveniamo entro 48 ore, come prevede la Legge 71/2017. Se non lo facciamo, puoi rivolgerti al Garante per la protezione dei dati personali.</p>
                </section>

                <section class="static-legal-section static-reveal" id="chat">
                    <h2>9. Chat, messaggi e ticket</h2>
                    <ul>
                        <li>La chat globale è visibile agli utenti del sito ed è moderata. Nelle chat private e di gruppo scrivono solo i partecipanti; gli amministratori di un gruppo possono gestirne i membri.</li>
                        <li>Lo staff non legge le chat private e di gruppo. Vede solo i messaggi che qualcuno segnala (quelli della chat globale, e quelli di una chat privata o di gruppo quando a segnalarli è un partecipante) e i messaggi dei ticket di supporto.</li>
                        <li>Puoi bloccare altri utenti e scegliere dalle impostazioni della chat chi può scriverti.</li>
                        <li>Negli allegati sono vietati malware, materiale illegale, contenuti espliciti e materiale protetto da diritto d'autore senza permesso.</li>
                        <li>I ticket servono per chiedere assistenza allo staff; possono essere gestiti anche dal nostro server Discord. Usali con rispetto: un uso offensivo o dannoso porta alla chiusura del ticket e può portare al ban.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="goonland">
                    <h2>10. GoonLand e contenuti 18+</h2>
                    <ul>
                        <li>GoonLand e le modalità indicate come 18+ contengono materiale per adulti, anche esplicito, che in parte arriva da servizi esterni (per esempio waifu.pics e waifu.im).</li>
                        <li>Sono <strong>riservate ai maggiorenni</strong>. Per entrarci devi attivare nelle impostazioni la dichiarazione «Ho almeno 18 anni»: attivandola dichiari, sotto la tua responsabilità, di essere maggiorenne e di voler vedere questi contenuti.</li>
                        <li>Se hai meno di 18 anni non devi attivarla. Una dichiarazione falsa viola questi Termini e porta alla chiusura dell'account.</li>
                        <li>Non verifichiamo l'età con documenti. I genitori possono usare il controllo parentale del dispositivo o del browser per bloccare gli indirizzi che contengono <code>/goonland</code>.</li>
                        <li>Le immagini arrivano da servizi che non controlliamo in tempo reale. Se ne trovi una che ritrae, o sembra ritrarre, un minore, o che è comunque illegale, segnalala subito: la blocchiamo.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="valute">
                    <h2>11. Godos e Godo Shards</h2>
                    <ul>
                        <li>I <strong>Godos</strong> si guadagnano usando il sito (missioni, riscatti giornalieri, eventi, codici). Le <strong>Godo Shards</strong> si comprano con denaro reale, si ottengono convertendo Godos al tasso indicato nel negozio o si ricevono in regalo.</li>
                        <li>Sono valute di gioco: non sono denaro, non hanno valore fuori dal sito, non si possono convertire in denaro né farsi rimborsare (salvo quanto previsto nella sezione 13).</li>
                        <li>Non si possono trasferire ad altri account, salvo con le funzioni del sito che lo prevedono, e non si possono vendere o comprare fuori dal sito.</li>
                        <li>Non scadono finché il tuo account e il sito esistono. Se elimini l'account, le perdi.</li>
                        <li>Possiamo correggere saldi sbagliati causati da bug, errori o exploit.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="giochi">
                    <h2>12. Gacha, lootbox e giochi</h2>
                    <ul>
                        <li>Le probabilità di ogni banner sono pubblicate in «Dettagli e probabilità», insieme al funzionamento di pity e rate-up. I risultati li calcola il server e sono definitivi, salvo errori tecnici che correggiamo.</li>
                        <li>Non è gioco d'azzardo: i premi sono oggetti digitali senza valore economico e non si possono convertire in denaro. Alcune estrazioni però usano Shards che si possono comprare: se sei minorenne parlane con un genitore e fissate un limite di spesa.</li>
                        <li>Il Gambling Arcade usa crediti finti salvati solo nel tuo browser: non c'è denaro in gioco e non si vince niente.</li>
                        <li>Classifiche e punteggi sono pubblici. Possiamo togliere punteggi, premi o oggetti ottenuti con cheat, exploit o bug.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="acquisti">
                    <h2>13. Acquisti: Premium e Godo Shards</h2>
                    <h3>Cosa si compra</h3>
                    <ul>
                        <li><strong>Cripsum™ Premium</strong>: un acquisto una tantum, non un abbonamento, al prezzo indicato nel checkout. I vantaggi sono descritti nella pagina d'acquisto. Il Premium si può anche regalare a un altro utente.</li>
                        <li><strong>Pacchetti di Godo Shards</strong>: prezzi e contenuto sono indicati nel negozio. Il primo acquisto di ogni pacchetto vale doppio, come indicato nella pagina.</li>
                    </ul>
                    <p>Il venditore è il team di Cripsum™ (sezione 1). I prezzi sono in euro e sono finali: da parte nostra non ci sono costi aggiuntivi. Paghi con PayPal o Stripe, secondo i loro termini; noi non vediamo e non salviamo i dati della tua carta.</p>
                    <h3>Consegna e conferma</h3>
                    <p>Premium e Shards arrivano sull'account appena il pagamento è confermato. Dopo il pagamento ti mandiamo un'email di conferma con il riepilogo dell'acquisto.</p>
                    <h3>Diritto di recesso</h3>
                    <p>Premium e Shards sono contenuti digitali forniti subito. Prima di pagare ti chiediamo, con una spunta obbligatoria, di acconsentire alla fornitura immediata e di prendere atto che perdi il diritto di recesso di 14 giorni (art. 59, comma 1, lett. o, del Codice del Consumo). Lo confermiamo nell'email d'acquisto.</p>
                    <h3>Garanzia e rimborsi</h3>
                    <p>Restano i diritti di legge sui contenuti digitali (artt. 135-octies e seguenti del Codice del Consumo). Se il Premium o le Shards non arrivano o non funzionano come descritto, scrivi a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> con l'ID ordine: sistemiamo il problema o ti rimborsiamo.</p>
                    <h3>Durata del Premium</h3>
                    <p>Il Premium resta sul tuo account finché l'account e il sito esistono. I vantaggi possono cambiare nel tempo, ma senza togliere l'essenziale di ciò che hai comprato; se una modifica lo togliesse, puoi chiedere il rimborso. Se decidessimo di chiudere il sito o il Premium, lo annunceremo con almeno 30 giorni di anticipo.</p>
                    <h3>Minori, chiusure e contestazioni</h3>
                    <ul>
                        <li>Se sei minorenne puoi comprare solo con il permesso di un genitore. Un genitore può scriverci per un acquisto fatto senza permesso: valutiamo il rimborso.</li>
                        <li>Se l'account viene chiuso per una violazione grave dei Termini, perdi Premium e Shards senza rimborso.</li>
                        <li>Se chiudiamo il tuo account senza che tu abbia violato i Termini, ti rimborsiamo le Shards comprate e non ancora usate e, se lo hai comprato da meno di 12 mesi, il Premium.</li>
                        <li>Se annulli un pagamento tramite PayPal, Stripe o la banca dopo aver ricevuto il prodotto, possiamo togliere il prodotto e sospendere l'account finché la questione non è risolta.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="negozio">
                    <h2>14. Negozio, Merch e Download</h2>
                    <ul>
                        <li><strong>Negozio e Merch sono una parodia</strong>: non vendiamo niente, non si paga niente e non arriva niente. Il checkout è finto, non inviamo né salviamo i dati che scrivi nel modulo e il «pagamento» è una battuta. Non inserire dati reali.</li>
                        <li>La pagina <a href="download">Download</a> offre file gratuiti, come guide, il videocorso e risorse per l'editing. Puoi usarli per scopi personali e non commerciali, salvo diversa indicazione; non ripubblicarli come se fossero tuoi. Le risorse che contengono materiale di terzi restano dei rispettivi titolari.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="donazioni">
                    <h2>15. Donazioni</h2>
                    <p>Le donazioni sono facoltative, passano da Buy Me a Coffee e servono a tenere online il sito. Non danno diritto a prodotti o vantaggi, salvo quando è indicato esplicitamente. Per pagamenti e rimborsi valgono i termini di Buy Me a Coffee.</p>
                </section>

                <section class="static-legal-section static-reveal" id="terze-parti">
                    <h2>16. Servizi di terze parti</h2>
                    <ul>
                        <li><strong>Discord</strong>: puoi collegare il tuo account Discord e, se attivi la Rich Presence, mostrare sul profilo il tuo stato e i giochi in esecuzione. Puoi scollegarlo quando vuoi dalle impostazioni.</li>
                        <li><strong>Google</strong>: puoi registrarti e accedere con Google; nella registrazione usiamo Google reCAPTCHA contro i bot.</li>
                        <li>Sul sito compaiono anche contenuti di servizi esterni: video YouTube e Streamable, brani Spotify, GIF, copertine e immagini di altri servizi.</li>
                    </ul>
                    <p>Per questi servizi valgono i loro termini e le loro informative. Non siamo responsabili dei loro contenuti né del loro funzionamento; i link esterni portano fuori dal sito.</p>
                </section>

                <section class="static-legal-section static-reveal" id="proprieta">
                    <h2>17. Proprietà intellettuale</h2>
                    <ul>
                        <li>Il nome Cripsum™, il logo, la grafica e i testi originali del sito sono nostri. Non puoi copiarli o usarli per scopi commerciali senza permesso. Per il codice pubblicato su GitHub valgono le condizioni del repository.</li>
                        <li>Personaggi, marchi, giochi, anime, musica e clip di terzi presenti sul sito (per esempio nel gacha, in Animespot, in Pullspot, in Subway Surfers e negli edit) appartengono ai rispettivi titolari. Sono usati come contenuti di fan, parodia o citazione, senza alcuna affiliazione con i titolari.</li>
                        <li>Se sei titolare di un diritto e vuoi che un contenuto venga rimosso, scrivi a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> indicando il contenuto (il link) e il diritto che vanti: rispondiamo in tempi brevi.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="persone">
                    <h2>18. Persone reali sul sito</h2>
                    <p>Cripsumpedia, la pagina Chi siamo, le pagine del team OHPY e alcuni personaggi possono riguardare persone reali. Li pubblichiamo con il consenso delle persone interessate, che possono chiedere in qualsiasi momento di correggere o togliere quello che le riguarda scrivendo a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>. Il tono è ironico e non vuole offendere nessuno.</p>
                </section>

                <section class="static-legal-section static-reveal" id="api">
                    <h2>19. API pubbliche</h2>
                    <p>Le API pubbliche descritte in <a href="api-docs">API Docs</a> sono gratuite e fornite «così come sono», senza garanzia di disponibilità. Non usarle per raccogliere dati in massa, profilare gli utenti o ripubblicare i dati fuori contesto, e non superare limiti di richieste ragionevoli. Possiamo bloccare chi ne abusa e modificare o chiudere le API.</p>
                </section>

                <section class="static-legal-section static-reveal" id="disponibilita">
                    <h2>20. Disponibilità del servizio</h2>
                    <p>Facciamo il possibile perché il sito funzioni, ma possono esserci interruzioni, manutenzioni e bug, e le funzioni gratuite possono cambiare o essere tolte. Per quelle a pagamento vale quanto scritto nella sezione 13.</p>
                </section>

                <section class="static-legal-section static-reveal" id="chiusura">
                    <h2>21. Sospensione e chiusura dell'account</h2>
                    <ul>
                        <li><strong>Da parte tua</strong>: puoi eliminare l'account quando vuoi da Impostazioni → Elimina account. Hai 30 giorni per ripensarci: basta accedere di nuovo. Dopo, l'account e i dati collegati vengono cancellati e perdi Godos, Shards, Premium e oggetti.</li>
                        <li><strong>Da parte nostra</strong>: possiamo sospendere o chiudere un account che viola questi Termini o la legge, o su richiesta di un'autorità, con una misura proporzionata e motivata come spiegato nella sezione 8. Nei casi gravi o urgenti la misura può essere immediata.</li>
                        <li>Chi è stato bannato non può creare altri account per aggirare il ban.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="responsabilita">
                    <h2>22. Responsabilità</h2>
                    <p>Il sito è offerto gratuitamente, per intrattenimento e «così com'è». Nei limiti consentiti dalla legge non rispondiamo di interruzioni del servizio, di contenuti pubblicati dagli utenti o da servizi esterni, di danni indiretti, né della perdita di dati di gioco o valute causata da bug (che comunque cerchiamo di ripristinare).</p>
                    <p>Queste limitazioni non valgono in caso di dolo o colpa grave, per i danni alla persona e negli altri casi in cui la legge, compreso il Codice del Consumo, non permette di limitare la responsabilità. Restano sempre i tuoi diritti di consumatore sugli acquisti.</p>
                </section>

                <section class="static-legal-section static-reveal" id="manleva">
                    <h2>23. Manleva</h2>
                    <p>Se violi questi Termini o la legge e per questo qualcuno ci chiede un risarcimento, dovrai tenerci indenni dai danni e dalle spese ragionevoli, comprese quelle legali, causati dalla tua violazione, nei limiti previsti dalla legge.</p>
                </section>

                <section class="static-legal-section static-reveal" id="modifiche">
                    <h2>24. Modifiche ai Termini</h2>
                    <p>Possiamo aggiornare questi Termini per nuove funzioni, cambiamenti di legge o motivi di sicurezza. Per le modifiche importanti ti avvisiamo con un messaggio nella inbox del sito almeno 15 giorni prima che entrino in vigore, salvo quando la legge o la sicurezza impongono tempi più brevi.</p>
                    <p>Se non sei d'accordo puoi eliminare l'account prima di quella data; se continui a usare il sito dopo, le modifiche valgono anche per te. Le modifiche non cambiano gli acquisti già fatti. In cima alla pagina trovi sempre la data dell'ultimo aggiornamento.</p>
                </section>

                <section class="static-legal-section static-reveal" id="legge">
                    <h2>25. Legge applicabile e controversie</h2>
                    <p>Questi Termini sono regolati dalla legge italiana. Se sei un consumatore residente in un altro Paese dell'Unione europea, restano le tutele inderogabili previste dalla legge del tuo Paese.</p>
                    <p>Per le controversie con un consumatore è competente il giudice del luogo in cui il consumatore risiede (art. 66-bis del Codice del Consumo). Prima di andare per vie legali scrivici a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>: proviamo a risolvere insieme. Puoi anche ricorrere a una procedura di risoluzione alternativa delle controversie (ADR) prevista dalla legge.</p>
                </section>

                <section class="static-legal-section static-reveal" id="finali">
                    <h2>26. Disposizioni finali</h2>
                    <ul>
                        <li>Se una parte di questi Termini non fosse valida, il resto rimane valido.</li>
                        <li>Se non facciamo valere subito un nostro diritto, non vuol dire che ci rinunciamo.</li>
                        <li>I Termini sono disponibili anche in inglese; in caso di differenze prevale la versione italiana.</li>
                    </ul>
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
