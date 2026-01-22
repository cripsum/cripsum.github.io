<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);

$persona = $_GET['id'] ?? null;

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina Cripsumpedia™ è ancora in fase di sviluppo.";
    header('Location: ../home');
    exit();
}
if (!isOwner()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina Cripsumpedia™ è ancora in fase di sviluppo.";
    header('Location: ../home');
    exit();
}

?>


<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($persona); ?> - Cripsumpedia™</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="u.css">
</head>

<body>
    <div class="main-content">
        <nav class="breadcrumb-nav">
            <a href="../home" class="breadcrumb-link">Home</a>
            <span class="breadcrumb-separator">›</span>
            <a href="../utenti" class="breadcrumb-link">Persone</a>
            <span class="breadcrumb-separator">›</span>
            <span>Mario Rossi</span>
        </nav>

        <article class="article-header">
            <div class="profile-section">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h1 class="profile-title">Mario Rossi</h1>
                    <div class="profile-badges">
                        <span class="badge-item">
                            <i class="fas fa-crown"></i> Fondatore
                        </span>
                        <span class="badge-item">
                            <i class="fas fa-star"></i> Membro Storico
                        </span>
                        <span class="badge-item">
                            <i class="fas fa-trophy"></i> Creatore di Meme
                        </span>
                    </div>
                    <div class="profile-meta">
                        <div class="meta-item">
                            <span class="meta-label">Nel gruppo dal</span>
                            <span class="meta-value">Gennaio 2020</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Provenienza</span>
                            <span class="meta-value">Milano, Italia</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Ruolo</span>
                            <span class="meta-value">Co-Fondatore</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Specialità</span>
                            <span class="meta-value">Organizzazione Eventi</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="intro-text">
                Mario Rossi è uno dei membri fondatori del gruppo e una figura centrale nella comunità.
                Conosciuto per il suo senso dell'umorismo contagioso e la sua capacità di trasformare
                qualsiasi situazione ordinaria in un momento memorabile, Mario ha contribuito a definire
                l'identità e lo spirito del gruppo fin dai primi giorni.
            </div>
        </article>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-book-open section-icon"></i>
                Storia e Background
            </h2>
            <div class="section-content">
                <p>
                    Mario è entrato a far parte del gruppo nel gennaio 2020, durante quello che sarebbe diventato
                    il primo incontro ufficiale della comunità. La sua personalità estroversa e il suo innato
                    talento nel creare situazioni comiche hanno immediatamente catturato l'attenzione di tutti
                    i presenti, rendendolo una figura centrale fin dall'inizio.
                </p>

                <h3>I Primi Giorni</h3>
                <p>
                    Nei primi mesi, Mario si è distinto come organizzatore naturale, coordinando i primi eventi
                    del gruppo e creando le tradizioni che ancora oggi caratterizzano le nostre riunioni. È stato
                    lui a proporre il primo LAN party, evento che sarebbe diventato un appuntamento fisso nel
                    calendario del gruppo.
                </p>

                <div class="highlight-box">
                    <div class="highlight-title">
                        <i class="fas fa-lightbulb"></i>
                        Fatto Interessante
                    </div>
                    <p style="margin: 0; color: rgba(255, 255, 255, 0.75);">
                        Mario è la persona che ha coniato il termine "Godo!", la frase che è diventata
                        il motto non ufficiale del gruppo. La sua origine risale a una partita particolarmente
                        intensa di Mario Kart durante il primo LAN party del 2020.
                    </p>
                </div>

                <h3>Evoluzione nel Gruppo</h3>
                <p>
                    Col passare del tempo, Mario è diventato non solo un membro fondamentale per l'organizzazione
                    degli eventi, ma anche un punto di riferimento per tutti i membri del gruppo. La sua capacità
                    di mediare nelle discussioni e di trovare sempre il lato divertente in ogni situazione ha
                    contribuito a mantenere un'atmosfera positiva e inclusiva.
                </p>

                <p>
                    Durante il 2021, Mario ha attraversato un periodo particolarmente creativo, dando vita a
                    numerosi meme e inside jokes che ancora oggi vengono citati quotidianamente. La sua
                    partecipazione attiva nelle chat di gruppo e la sua presenza costante agli eventi lo hanno
                    reso una figura insostituibile nella comunità.
                </p>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-star section-icon"></i>
                Contributi Principali
            </h2>
            <div class="section-content">
                <p>
                    Mario ha lasciato un'impronta indelebile nella storia del gruppo attraverso numerosi
                    contributi e iniziative. Ecco alcuni dei suoi apporti più significativi:
                </p>

                <ul>
                    <li>
                        <strong>Organizzazione del primo LAN Party (2020):</strong> Ha coordinato l'evento
                        che avrebbe dato inizio alla tradizione dei gaming weekend del gruppo, stabilendo
                        standard di organizzazione che vengono seguiti ancora oggi.
                    </li>
                    <li>
                        <strong>Creazione del sistema di "Quote Memorabili":</strong> Ha iniziato a documentare
                        le frasi più iconiche dette durante gli incontri, creando un archivio che è diventato
                        una parte fondamentale della cultura del gruppo.
                    </li>
                    <li>
                        <strong>Fondatore della tradizione del "Post-Evento Debrief":</strong> Ha introdotto
                        l'usanza di rivedersi dopo ogni grande evento per commentare i momenti migliori,
                        consolidando i ricordi e rafforzando i legami.
                    </li>
                    <li>
                        <strong>Coordinatore del progetto Cripsumpedia:</strong> È stato uno dei primi a
                        proporre la creazione di un archivio digitale della storia del gruppo, contribuendo
                        attivamente alla raccolta delle informazioni e delle storie.
                    </li>
                </ul>

                <div class="quote-box">
                    "La bellezza di questo gruppo è che ogni momento, anche il più banale, può trasformarsi
                    in qualcosa di speciale. È questo che ci rende unici." - Mario Rossi, 2022
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-trophy section-icon"></i>
                Momenti Memorabili
            </h2>
            <div class="section-content">
                <p>
                    Nel corso degli anni, Mario è stato protagonista di numerosi momenti che sono entrati
                    nella leggenda del gruppo. Questi episodi hanno contribuito a costruire la sua reputazione
                    e hanno arricchito la mitologia collettiva della comunità.
                </p>

                <h3>L'Incidente della Pizza (2021)</h3>
                <p>
                    Durante un evento particolarmente affollato, Mario è stato incaricato di ordinare le pizze.
                    A causa di un malinteso telefonico leggendario, invece di 5 pizze ne sono arrivate 50.
                    Quello che sembrava un disastro si è trasformato in una delle serate più divertenti della
                    storia del gruppo, con pizza in abbondanza per tutti e avanzi per i giorni successivi.
                </p>

                <h3>La Vittoria Epica al Torneo di Mario Kart (2020)</h3>
                <p>
                    Nel primo torneo ufficiale organizzato dal gruppo, Mario ha dominato ogni gara con
                    performance straordinarie, culminando nella vittoria finale accompagnata dal suo ormai
                    iconico "Godo!". Questo momento è considerato l'origine del meme più famoso del gruppo.
                </p>

                <h3>Il Discorso del Capodanno 2023</h3>
                <p>
                    Durante i festeggiamenti di Capodanno, Mario ha improvvisato un discorso di mezzanotte
                    che è iniziato come una cosa seria ma è rapidamente degenerato in una serie di battute
                    e riferimenti interni che hanno fatto ridere tutti per ore. Il video di quel discorso
                    viene ancora ripostato nelle ricorrenze del gruppo.
                </p>

                <div class="highlight-box">
                    <div class="highlight-title">
                        <i class="fas fa-medal"></i>
                        Riconoscimenti
                    </div>
                    <p style="margin: 0; color: rgba(255, 255, 255, 0.75);">
                        Nel 2022, Mario è stato votato all'unanimità come "Membro dell'Anno" per il suo
                        contributo eccezionale all'organizzazione degli eventi e alla coesione del gruppo.
                        È l'unico membro ad aver ricevuto questo riconoscimento più di una volta.
                    </p>
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-heart section-icon"></i>
                Personalità e Caratteristiche
            </h2>
            <div class="section-content">
                <p>
                    Mario è noto per diverse caratteristiche distintive che lo rendono una figura unica
                    all'interno del gruppo:
                </p>

                <ul>
                    <li>
                        <strong>Senso dell'umorismo:</strong> Ha un talento naturale nel trovare il lato
                        comico di ogni situazione e nel fare battute al momento giusto che alleggeriscono
                        l'atmosfera.
                    </li>
                    <li>
                        <strong>Capacità organizzative:</strong> Eccelle nel pianificare e coordinare eventi,
                        riuscendo sempre a gestire anche gli imprevisti con calma e creatività.
                    </li>
                    <li>
                        <strong>Spirito inclusivo:</strong> Si assicura sempre che tutti si sentano benvenuti
                        e coinvolti, facendo da ponte tra i vari membri del gruppo.
                    </li>
                    <li>
                        <strong>Energia contagiosa:</strong> La sua presenza riesce sempre a energizzare il
                        gruppo e a trasformare anche le serate più tranquille in esperienze memorabili.
                    </li>
                </ul>

                <p>
                    Queste qualità hanno reso Mario non solo un membro amato del gruppo, ma anche un esempio
                    per i nuovi arrivati di cosa significhi essere parte di questa comunità.
                </p>
            </div>
        </section>

        <section class="related-section">
            <h3 class="related-title">Contenuti Correlati</h3>
            <div class="related-grid">
                <a href="evento-dettaglio.html?id=lan-party" class="related-card">
                    <div class="related-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div class="related-info">
                        <div class="related-name">Primo LAN Party</div>
                        <div class="related-type">Evento • 2020</div>
                    </div>
                </a>

                <a href="meme-dettaglio.html?id=godo" class="related-card">
                    <div class="related-icon">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <div class="related-info">
                        <div class="related-name">"Godo!"</div>
                        <div class="related-type">Meme • 2020</div>
                    </div>
                </a>

                <a href="meme-dettaglio.html?id=pizza-incident" class="related-card">
                    <div class="related-icon">
                        <i class="fas fa-pizza-slice"></i>
                    </div>
                    <div class="related-info">
                        <div class="related-name">L'Incidente della Pizza</div>
                        <div class="related-type">Meme • 2021</div>
                    </div>
                </a>
            </div>
        </section>
    </div>
</body>

</html>