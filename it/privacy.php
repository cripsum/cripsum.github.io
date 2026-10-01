<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = '2 ottobre 2026';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Informativa privacy</title>

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
            <h1>Informativa privacy</h1>
            <p>Quali dati raccogliamo su Cripsum™, perché, con chi li condividiamo e come puoi controllarli.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fa-solid fa-calendar"></i> Aggiornata il <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fa-solid fa-shield-halved"></i> GDPR (Reg. UE 2016/679)</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Indice</h2>
                <a href="#in-breve">In breve</a>
                <a href="#titolare">1. Titolare</a>
                <a href="#dati">2. Dati che raccogliamo</a>
                <a href="#finalita">3. Perché li usiamo</a>
                <a href="#pubblici">4. Cosa vedono gli altri</a>
                <a href="#minori">5. Minori</a>
                <a href="#cookie">6. Cookie e statistiche</a>
                <a href="#destinatari">7. Con chi condividiamo i dati</a>
                <a href="#trasferimenti">8. Dati fuori dall'UE</a>
                <a href="#conservazione">9. Per quanto tempo</a>
                <a href="#diritti">10. I tuoi diritti</a>
                <a href="#obblighi">11. Dati obbligatori e decisioni automatiche</a>
                <a href="#sicurezza">12. Sicurezza</a>
                <a href="#non-utenti">13. Persone che non sono utenti</a>
                <a href="#modifiche">14. Modifiche</a>
                <a href="#contatti">15. Contatti</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-legal-section--summary static-reveal" id="in-breve">
                    <h2>In breve (anche per chi ha meno di 18 anni)</h2>
                    <ul>
                        <li>Per usare il sito ci servono username, email e password. Non ti chiediamo nome, cognome, indirizzo o data di nascita.</li>
                        <li>Il tuo profilo, i post e le classifiche li possono vedere tutti. Le chat private e di gruppo no: nemmeno lo staff le legge.</li>
                        <li>Non vendiamo i tuoi dati e sul sito non c'è pubblicità.</li>
                        <li>Usiamo Google Analytics per contare le visite: puoi spegnerlo quando vuoi dal pulsante in fondo a ogni pagina.</li>
                        <li>Se compri qualcosa, il pagamento lo gestiscono PayPal o Stripe: i dati della carta non li vediamo.</li>
                        <li>Dalle impostazioni puoi scaricare tutti i tuoi dati o cancellare l'account.</li>
                        <li>Per qualsiasi dubbio scrivi a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="titolare">
                    <h2>1. Titolare del trattamento</h2>
                    <p>Il titolare del trattamento è il team di Cripsum™, che gestisce il sito cripsum.com come progetto personale, non costituito in società, di privati residenti in Italia.</p>
                    <p>Per qualsiasi richiesta sulla privacy scrivi a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>. Se un'autorità, o chi esercita un diritto previsto dal GDPR, ha bisogno dei dati identificativi del titolare, li forniamo su richiesta. Non abbiamo nominato un responsabile della protezione dei dati (DPO), perché per un progetto come questo la legge non lo richiede.</p>
                </section>

                <section class="static-legal-section static-reveal" id="dati">
                    <h2>2. Dati che raccogliamo</h2>
                    <h3>Account e accesso</h3>
                    <ul>
                        <li>Username, email e password (salvata solo in forma cifrata con hash).</li>
                        <li>Se ti registri o accedi con Google: il tuo ID Google, l'email e il nome dell'account Google, che usiamo per proporre lo username.</li>
                        <li>La conferma che hai almeno 14 anni e accetti i Termini, data con la spunta in registrazione.</li>
                        <li>Se attivi la verifica in due passaggi: il segreto 2FA e i codici di backup.</li>
                        <li>Le preferenze dell'account, come la lingua, il tema e la dichiarazione «Ho almeno 18 anni» per GoonLand.</li>
                    </ul>
                    <h3>Sicurezza</h3>
                    <ul>
                        <li>Indirizzo IP, browser e sistema operativo dei dispositivi con cui accedi, con una posizione approssimativa (città e paese) ricavata dall'IP, per mostrarti la lista dei dispositivi collegati e avvisarti dei nuovi accessi.</li>
                        <li>I tentativi di accesso (riusciti e falliti) con IP ed esito, per bloccare i tentativi di forzare gli account.</li>
                        <li>La verifica reCAPTCHA di Google durante la registrazione.</li>
                        <li>Le misure di moderazione (avvisi, mute, ban) e il registro delle azioni dello staff.</li>
                    </ul>
                    <h3>Profilo e community</h3>
                    <ul>
                        <li>Quello che metti nel profilo: foto, banner, bio, link, social, musica, contenuti incorporati, preferiti, progetti, badge e personaggi in evidenza.</li>
                        <li>Amicizie, richieste di amicizia, follow e utenti bloccati.</li>
                        <li>Shitpost, post di Top Rimasti, commenti, like, voti, contenuti salvati e segnalazioni che invii.</li>
                        <li>I messaggi nella chat globale, nelle chat private e di gruppo, con allegati, reazioni e GIF. Per cercare le GIF inviamo a Klipy le parole che scrivi nella ricerca.</li>
                        <li>Nelle chat private e di gruppo: fino a quale messaggio hai letto (da qui le conferme di lettura, che puoi spegnere dalla Privacy della chat), le chat che hai silenziato, archiviato o fissato, i messaggi salvati, i soprannomi che dai e le tue scelte su chi può scriverti. L'indicazione «sta scrivendo» dura pochi secondi e non viene conservata.</li>
                        <li>Dalle foto che carichi in chat togliamo i dati nascosti dello scatto, per esempio la posizione GPS, quando il formato lo permette (JPEG e WebP).</li>
                        <li>Per avvisarti subito di messaggi e richieste teniamo sul server, per poco tempo, un elenco tecnico degli ultimi eventi che ti riguardano (per esempio «nuovo messaggio in una chat»): non contiene il testo dei messaggi privati. Le notifiche del browser le attivi tu, le mostra il tuo browser mentre il sito è aperto e non passano da servizi esterni.</li>
                        <li>I ticket di supporto e i messaggi che ricevi dal sito nella inbox.</li>
                    </ul>
                    <h3>Giochi e statistiche</h3>
                    <ul>
                        <li>Personaggi e oggetti, storico e pity del gacha, collezioni, wishlist, missioni, achievement, partite e duelli, punteggi di Subway Surfers, Animespot e Pullspot, saldi di Godos e Shards.</li>
                        <li>Statistiche di utilizzo per il Cripsum Rewind e per missioni e achievement: giorni di accesso, tempo passato nelle varie sezioni del sito (conteggiato solo mentre la pagina è in primo piano), azioni nei giochi. Puoi disattivare il tracciamento dalle impostazioni del Rewind.</li>
                    </ul>
                    <h3>Acquisti</h3>
                    <ul>
                        <li>Cosa hai comprato (Premium, pacchetti di Shards, regali Premium), importo, data, stato del pagamento, metodo (PayPal o Stripe) e ID dell'ordine.</li>
                        <li>Per i regali: chi ha regalato il Premium e a chi.</li>
                        <li>I dati della carta o del conto li gestiscono PayPal e Stripe: noi non li vediamo e non li salviamo.</li>
                    </ul>
                    <h3>Discord</h3>
                    <ul>
                        <li>Se colleghi Discord: ID, username, nome visualizzato e avatar Discord.</li>
                        <li>Se attivi la Rich Presence: il tuo stato online e le attività in corso (per esempio il gioco o la musica), letti in tempo reale dal nostro bot e dal servizio Lanyard. Non salviamo uno storico della tua attività su Discord.</li>
                    </ul>
                    <h3>Altri dati</h3>
                    <ul>
                        <li>Candidature per la pagina Chi siamo: il tuo account, il nome da mostrare, descrizione, foto, social e link che ci mandi. Le guardiamo dal pannello del sito e ti rispondiamo nella posta del sito; la foto la vede solo lo staff finché la candidatura non viene accettata.</li>
                        <li>Le richieste di esportazione dei dati e di cancellazione dell'account.</li>
                        <li>Dati di navigazione (come IP, pagine visitate e dispositivo) tramite i registri tecnici del server e, se non lo disattivi, Google Analytics (sezione 6).</li>
                        <li>Il checkout del Negozio e del Merch è finto: quello che scrivi nel modulo non viene inviato né salvato.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="finalita">
                    <h2>3. Perché li usiamo e su quale base</h2>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Finalità</th>
                                    <th>Base giuridica (art. 6 GDPR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Creare e gestire l'account, far funzionare profilo, chat, amici, giochi, missioni, Rewind e le altre funzioni del sito</td>
                                    <td>Esecuzione del contratto, cioè dei Termini (lett. b)</td>
                                </tr>
                                <tr>
                                    <td>Vendere Premium e Shards, mandarti la conferma e gestire problemi, rimborsi e contestazioni</td>
                                    <td>Esecuzione del contratto (lett. b); obblighi di legge sulla tutela del consumatore (lett. c); difesa dei nostri diritti (lett. f)</td>
                                </tr>
                                <tr>
                                    <td>Proteggere account e sito: controllo degli accessi, lista dispositivi, avvisi di nuovi accessi, reCAPTCHA, limiti ai tentativi, prevenzione di frodi, cheat e ban aggirati</td>
                                    <td>Legittimo interesse alla sicurezza del sito e dei suoi utenti (lett. f)</td>
                                </tr>
                                <tr>
                                    <td>Gestire segnalazioni, moderazione, ricorsi e richieste delle autorità</td>
                                    <td>Obblighi di legge, tra cui il Regolamento sui servizi digitali (lett. c); legittimo interesse a mantenere la community sicura (lett. f)</td>
                                </tr>
                                <tr>
                                    <td>Assistenza tramite ticket e contatti email</td>
                                    <td>Esecuzione del contratto (lett. b); legittimo interesse a rispondere a chi ci scrive (lett. f)</td>
                                </tr>
                                <tr>
                                    <td>Collegare Discord e mostrare la Rich Presence sul profilo</td>
                                    <td>Consenso (lett. a), che revochi scollegando Discord o spegnendo la Rich Presence</td>
                                </tr>
                                <tr>
                                    <td>Riservare GoonLand e le modalità 18+ a chi dichiara di essere maggiorenne</td>
                                    <td>Legittimo interesse a tenere i minori lontani dai contenuti per adulti (lett. f)</td>
                                </tr>
                                <tr>
                                    <td>Statistiche aggregate sulle visite con Google Analytics</td>
                                    <td>Legittimo interesse a capire come viene usato il sito (lett. f), con Google Signals e pubblicità disattivati. Puoi opporti quando vuoi dal pulsante nel footer</td>
                                </tr>
                                <tr>
                                    <td>Notifiche interne allo staff su un canale privato del nostro server Discord (per esempio nuove registrazioni con username ed email) e ringraziamento pubblico sul server Discord a chi compra il Premium</td>
                                    <td>Legittimo interesse a gestire il sito e la community (lett. f). Se non vuoi essere ringraziato pubblicamente, scrivici</td>
                                </tr>
                                <tr>
                                    <td>Pubblicare te nella pagina Chi siamo o nelle pagine OHPY</td>
                                    <td>Consenso (lett. a), che puoi revocare quando vuoi</td>
                                </tr>
                                <tr>
                                    <td>Rispondere alle richieste sui tuoi diritti ed esportare i tuoi dati</td>
                                    <td>Obbligo di legge (lett. c)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>Non usiamo i tuoi dati per pubblicità, non li vendiamo e non li usiamo per addestrare sistemi di intelligenza artificiale.</p>
                </section>

                <section class="static-legal-section static-reveal" id="pubblici">
                    <h2>4. Cosa vedono gli altri</h2>
                    <p>Alcuni dati sono pubblici per come funziona il sito:</p>
                    <ul>
                        <li>il profilo (username, foto, banner, bio, link, social, musica, contenuti incorporati, preferiti, badge, achievement, statistiche e attività del profilo), visibile a chiunque, anche senza account, e nelle anteprime dei link condivisi su altre app;</li>
                        <li>le classifiche e i punteggi dei giochi;</li>
                        <li>shitpost, post di Top Rimasti e commenti pubblicati;</li>
                        <li>i messaggi della chat globale, visibili agli utenti del sito;</li>
                        <li>se colleghi Discord e attivi la Rich Presence, lo stato e le attività Discord sul profilo;</li>
                        <li>i dati del profilo, le classifiche e la presenza Discord sono leggibili anche tramite le nostre <a href="api-docs">API pubbliche</a>;</li>
                        <li>il Rewind, se lo condividi con il link pubblico. Il tuo username può comparire nel Rewind di un amico con cui hai interagito molto, se le vostre impostazioni lo permettono;</li>
                        <li>il ringraziamento sul nostro server Discord quando compri il Premium.</li>
                    </ul>
                    <p>Le chat private e di gruppo sono visibili solo ai partecipanti.</p>
                </section>

                <section class="static-legal-section static-reveal" id="minori">
                    <h2>5. Minori</h2>
                    <ul>
                        <li>Per creare un account devi avere almeno 14 anni, l'età minima prevista in Italia per usare questi servizi da soli (art. 2-quinquies del Codice privacy). Se scopriamo che un account appartiene a una persona più giovane, lo chiudiamo e cancelliamo i dati.</li>
                        <li>Ai minori non mostriamo pubblicità e non ne facciamo profilazione. GoonLand e le modalità 18+ sono riservate a chi dichiara di essere maggiorenne.</li>
                        <li>Un genitore può scriverci a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a> per avere informazioni sull'account del figlio minorenne o chiederne la cancellazione.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="cookie">
                    <h2>6. Cookie e statistiche</h2>
                    <p>Usiamo cookie tecnici per farti restare connesso, ricordare lingua e tema e proteggere l'account: senza, il sito non funziona. Usiamo anche Google Analytics per contare le visite, con Google Signals e personalizzazione degli annunci disattivati. È attivo di default e puoi spegnerlo quando vuoi dal pulsante «Google Analytics» nel footer: spegnerlo non cambia nulla nel funzionamento del sito. Tutti i dettagli sono nella <a href="cookie">Cookie policy</a>.</p>
                </section>

                <section class="static-legal-section static-reveal" id="destinatari">
                    <h2>7. Con chi condividiamo i dati</h2>
                    <p>I dati li trattiamo noi, lo staff autorizzato e questi fornitori, ognuno solo per quello che gli serve:</p>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Chi</th>
                                    <th>Per cosa</th>
                                    <th>Dove</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Hostinger</td>
                                    <td>Ospita sito e database e invia le email del sito</td>
                                    <td>Server nel Regno Unito, backup in Germania</td>
                                </tr>
                                <tr>
                                    <td>Server del team</td>
                                    <td>Fa girare il bot Discord (presenza, ruoli, ticket, notifiche)</td>
                                    <td>Italia</td>
                                </tr>
                                <tr>
                                    <td>PayPal, Stripe</td>
                                    <td>Pagamenti di Premium e Shards; sono titolari autonomi dei dati di pagamento</td>
                                    <td>UE, con trasferimenti negli USA</td>
                                </tr>
                                <tr>
                                    <td>Google</td>
                                    <td>Accesso con Google, reCAPTCHA, Google Analytics, font e librerie (Google Fonts, jQuery), video YouTube incorporati</td>
                                    <td>UE e USA</td>
                                </tr>
                                <tr>
                                    <td>Discord, Lanyard</td>
                                    <td>Collegamento dell'account, Rich Presence, notifiche allo staff, ticket gestiti su Discord</td>
                                    <td>USA</td>
                                </tr>
                                <tr>
                                    <td>Klipy</td>
                                    <td>Ricerca delle GIF in chat</td>
                                    <td>USA</td>
                                </tr>
                                <tr>
                                    <td>ipwho.is</td>
                                    <td>Posizione approssimativa (città e paese) degli IP nella lista dei dispositivi</td>
                                    <td>Fuori UE</td>
                                </tr>
                                <tr>
                                    <td>jsDelivr, Cloudflare (cdnjs)</td>
                                    <td>Distribuiscono alcune librerie del sito (grafica, icone)</td>
                                    <td>Rete globale</td>
                                </tr>
                                <tr>
                                    <td>Spotify, Streamable, Tenor, Apple, AniList, Open Library, Steam, animethemes.moe, waifu.pics, waifu.im, goQR.me e servizi simili</td>
                                    <td>Contenuti incorporati o caricati da servizi esterni (musica, video, GIF, copertine, immagini, sigle, QR del profilo pubblico): il tuo browser si collega a loro e ricevono il tuo IP</td>
                                    <td>Vari, anche fuori UE</td>
                                </tr>
                                <tr>
                                    <td>Buy Me a Coffee</td>
                                    <td>Donazioni, se decidi di farne; titolare autonomo</td>
                                    <td>USA</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>Possiamo comunicare dati alle autorità quando la legge lo impone o per difendere i nostri diritti. Non vendiamo né cediamo i dati a nessuno.</p>
                </section>

                <section class="static-legal-section static-reveal" id="trasferimenti">
                    <h2>8. Dati fuori dall'Unione europea</h2>
                    <p>Il sito è ospitato nel Regno Unito, che la Commissione europea riconosce come Paese con un livello di protezione adeguato; i backup sono in Germania. Quando i dati vanno negli Stati Uniti, i fornitori principali (Google, Stripe, PayPal, Discord) usano il Data Privacy Framework UE-USA o le clausole contrattuali standard approvate dalla Commissione europea. Ai servizi più piccoli (come Lanyard, ipwho.is e Klipy) inviamo solo il minimo indispensabile: il tuo ID Discord pubblico, l'IP da localizzare o le parole cercate.</p>
                </section>

                <section class="static-legal-section static-reveal" id="conservazione">
                    <h2>9. Per quanto tempo teniamo i dati</h2>
                    <div class="static-table-wrap">
                        <table class="static-table">
                            <thead>
                                <tr>
                                    <th>Dati</th>
                                    <th>Per quanto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Account, profilo, messaggi, post, giochi, statistiche, ticket, acquisti registrati da noi</td>
                                    <td>Finché l'account esiste. Se chiedi di cancellarlo hai 30 giorni per ripensarci; poi l'account e tutti i dati collegati, compresi i file caricati, vengono cancellati</td>
                                </tr>
                                <tr>
                                    <td>Sessione di accesso</td>
                                    <td>Al massimo 14 giorni</td>
                                </tr>
                                <tr>
                                    <td>Posizione approssimativa degli IP</td>
                                    <td>90 giorni</td>
                                </tr>
                                <tr>
                                    <td>Tentativi di accesso (IP ed esito)</td>
                                    <td>90 giorni</td>
                                </tr>
                                <tr>
                                    <td>Registro delle azioni dello staff</td>
                                    <td>12 mesi</td>
                                </tr>
                                <tr>
                                    <td>File di esportazione dei tuoi dati</td>
                                    <td>7 giorni, poi vengono cancellati</td>
                                </tr>
                                <tr>
                                    <td>Dati minimi degli account bannati (username, email, motivo)</td>
                                    <td>Finché dura il ban, per evitare che venga aggirato</td>
                                </tr>
                                <tr>
                                    <td>Segnalazioni inviate</td>
                                    <td>Il tempo necessario a gestirle; vengono comunque cancellate con l'account di chi le ha inviate</td>
                                </tr>
                                <tr>
                                    <td>Candidature per Chi siamo</td>
                                    <td>Finché servono a valutarle, al massimo 12 mesi se non vengono accettate; se vieni pubblicato, finché non chiedi di toglierti</td>
                                </tr>
                                <tr>
                                    <td>Google Analytics</td>
                                    <td>Al massimo 14 mesi, secondo le impostazioni del servizio</td>
                                </tr>
                                <tr>
                                    <td>Dati di pagamento presso PayPal e Stripe</td>
                                    <td>Secondo le loro informative e gli obblighi di legge a cui sono soggetti</td>
                                </tr>
                                <tr>
                                    <td>Backup</td>
                                    <td>Si sovrascrivono da soli: i dati cancellati possono restarci per alcune settimane</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="static-legal-section static-reveal" id="diritti">
                    <h2>10. I tuoi diritti</h2>
                    <p>In base al GDPR (artt. 15-22) puoi chiedere di:</p>
                    <ul>
                        <li>accedere ai tuoi dati e averne una copia;</li>
                        <li>correggere i dati sbagliati;</li>
                        <li>cancellare i dati, salvo quelli che dobbiamo tenere per legge o per difenderci;</li>
                        <li>limitare il trattamento in alcuni casi;</li>
                        <li>ricevere i dati in un formato leggibile da un computer (portabilità);</li>
                        <li>opporti ai trattamenti basati sul legittimo interesse, per esempio Google Analytics o le statistiche del Rewind;</li>
                        <li>revocare il consenso quando vuoi, senza che questo tocchi quello che è stato fatto prima.</li>
                    </ul>
                    <p>Molte cose le puoi fare da solo: in Impostazioni trovi «I tuoi dati» per scaricare tutto quello che abbiamo su di te ed «Elimina account» per cancellarlo. Gli altri strumenti sono lo scollegamento di Discord, la Rich Presence, le impostazioni del Rewind e della chat e il pulsante di Google Analytics nel footer.</p>
                    <p>Per il resto scrivi a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>, possibilmente dall'email del tuo account, così possiamo verificare che sei tu. Rispondiamo entro un mese, prorogabile di altri due solo se la richiesta è complessa, e ti diciamo il motivo.</p>
                    <p>Puoi anche presentare reclamo al <strong>Garante per la protezione dei dati personali</strong> (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>) o all'autorità del Paese UE in cui vivi.</p>
                </section>

                <section class="static-legal-section static-reveal" id="obblighi">
                    <h2>11. Dati obbligatori e decisioni automatiche</h2>
                    <p>Username, email e password sono necessari per creare un account: senza non possiamo registrarti. Tutto il resto del profilo è facoltativo.</p>
                    <p>Non prendiamo decisioni che producono effetti legali o simili su di te basandoci solo su un trattamento automatico. I risultati del gacha sono casuali e non dipendono dal tuo profilo; le segnalazioni le valuta sempre una persona dello staff.</p>
                </section>

                <section class="static-legal-section static-reveal" id="sicurezza">
                    <h2>12. Sicurezza</h2>
                    <p>Le connessioni al sito sono cifrate (HTTPS) e le password sono salvate solo come hash. Offriamo la verifica in due passaggi, limitiamo i tentativi di accesso, proteggiamo i moduli da richieste false (CSRF), ti mostriamo i dispositivi collegati e ti avvisiamo in inbox quando qualcuno accede da un dispositivo nuovo. L'accesso ai dati è riservato allo staff che ne ha bisogno.</p>
                    <p>Se ci fosse una violazione dei dati che mette a rischio i tuoi diritti, avviseremo il Garante entro 72 ore e, se il rischio è alto, anche te.</p>
                </section>

                <section class="static-legal-section static-reveal" id="non-utenti">
                    <h2>13. Persone che non sono utenti</h2>
                    <p>Cripsumpedia, la pagina Chi siamo, le pagine del team OHPY e alcuni personaggi possono riguardare persone reali. Li pubblichiamo con il loro consenso. Se compari sul sito, anche in un contenuto caricato da un utente, e vuoi correggere o togliere quello che ti riguarda, scrivi a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a> con il link al contenuto.</p>
                </section>

                <section class="static-legal-section static-reveal" id="modifiche">
                    <h2>14. Modifiche</h2>
                    <p>Aggiorniamo questa informativa quando cambia qualcosa nel sito o nella legge. Per le modifiche importanti ti avvisiamo con un messaggio nella inbox del sito. In cima alla pagina trovi sempre la data dell'ultimo aggiornamento.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>15. Contatti</h2>
                    <p>Per domande, dubbi o reclami sulla privacy scrivi a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
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
