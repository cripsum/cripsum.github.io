<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capodanno Epico 2024 - Cripsumpedia™</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
            padding-top: 5rem;
        }

        .main-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: 3rem 2rem 4rem;
        }

        .breadcrumb-nav {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
            flex-wrap: wrap;
        }

        .breadcrumb-link {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .breadcrumb-link:hover {
            color: #ff64c8;
        }

        .breadcrumb-separator {
            color: rgba(255, 255, 255, 0.3);
        }

        .event-header {
            margin-bottom: 3rem;
        }

        .event-hero {
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.15), rgba(255, 74, 169, 0.1));
            border: 1px solid rgba(255, 100, 200, 0.3);
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .event-icon-large {
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.3), rgba(255, 74, 169, 0.2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #ff64c8;
            box-shadow: 0 8px 32px rgba(255, 100, 200, 0.3);
        }

        .event-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #ffffff;
        }

        .event-subtitle {
            font-size: 1.15rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1.5rem;
        }

        .event-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .meta-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }

        .meta-icon {
            font-size: 1.5rem;
            color: #ff64c8;
            margin-bottom: 0.5rem;
        }

        .meta-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .meta-value {
            font-size: 1.05rem;
            color: #ffffff;
            font-weight: 600;
        }

        .event-tags {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .event-tag {
            padding: 0.5rem 1.25rem;
            border-radius: 20px;
            background: rgba(255, 100, 200, 0.2);
            color: #ff64c8;
            border: 1px solid rgba(255, 100, 200, 0.4);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .content-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .section-icon {
            color: #ff64c8;
            font-size: 1.5rem;
        }

        .section-content {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.8;
        }

        .section-content p {
            margin-bottom: 1.25rem;
        }

        .section-content h3 {
            font-size: 1.35rem;
            font-weight: 600;
            color: #ffffff;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .section-content ul,
        .section-content ol {
            margin-left: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .section-content li {
            margin-bottom: 0.75rem;
            line-height: 1.7;
        }

        .timeline-container {
            position: relative;
            padding-left: 2rem;
            margin: 2rem 0;
        }

        .timeline-line {
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, #ff64c8, rgba(255, 100, 200, 0.3));
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            padding-left: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.35rem;
            top: 0.25rem;
            width: 0.7rem;
            height: 0.7rem;
            background: #ff64c8;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(255, 100, 200, 0.2);
        }

        .timeline-time {
            font-size: 0.9rem;
            color: #ff64c8;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .timeline-text {
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.7;
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-left: 4px solid #ff64c8;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .highlight-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ff64c8;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quote-box {
            border-left: 4px solid #ff64c8;
            padding: 1.25rem 1.5rem;
            margin: 1.5rem 0;
            background: rgba(255, 100, 200, 0.05);
            border-radius: 4px;
            font-style: italic;
            color: rgba(255, 255, 255, 0.85);
        }

        .participants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .participant-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .participant-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 100, 200, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .participant-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 0.75rem;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.2), rgba(255, 74, 169, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: #ff64c8;
        }

        .participant-name {
            font-size: 0.95rem;
            font-weight: 500;
            color: #ffffff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.1), rgba(255, 74, 169, 0.05));
            border: 1px solid rgba(255, 100, 200, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ff64c8;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .related-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid rgba(255, 255, 255, 0.1);
        }

        .related-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
        }

        .related-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 1.25rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .related-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 100, 200, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .related-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.2), rgba(255, 74, 169, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ff64c8;
            flex-shrink: 0;
        }

        .related-info {
            flex: 1;
        }

        .related-name {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .related-type {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 2rem 1.5rem 3rem;
            }

            .event-hero {
                padding: 2rem 1.5rem;
            }

            .event-title {
                font-size: 2rem;
            }

            .event-icon-large {
                width: 100px;
                height: 100px;
                font-size: 3rem;
            }

            .event-meta-grid {
                grid-template-columns: 1fr;
            }

            .participants-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .event-hero {
                padding: 1.75rem 1.25rem;
            }

            .related-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="main-content">
        <nav class="breadcrumb-nav">
            <a href="cripsumpedia.php" class="breadcrumb-link">Home</a>
            <span class="breadcrumb-separator">›</span>
            <a href="eventi.html" class="breadcrumb-link">Eventi</a>
            <span class="breadcrumb-separator">›</span>
            <span>Capodanno Epico 2024</span>
        </nav>

        <header class="event-header">
            <div class="event-hero">
                <div class="event-icon-large">
                    <i class="fas fa-champagne-glasses"></i>
                </div>
                <h1 class="event-title">Capodanno Epico 2024</h1>
                <p class="event-subtitle">
                    La notte che ha ridefinito il significato di festa per il gruppo
                </p>

                <div class="event-meta-grid">
                    <div class="meta-box">
                        <div class="meta-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="meta-label">Data</div>
                        <div class="meta-value">31 Dic 2023 - 1 Gen 2024</div>
                    </div>
                    <div class="meta-box">
                        <div class="meta-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="meta-label">Luogo</div>
                        <div class="meta-value">Villa sul Lago di Como</div>
                    </div>
                    <div class="meta-box">
                        <div class="meta-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="meta-label">Partecipanti</div>
                        <div class="meta-value">18 Persone</div>
                    </div>
                </div>

                <div class="event-tags">
                    <span class="event-tag">Epico</span>
                    <span class="event-tag">Festa</span>
                    <span class="event-tag">Memorabile</span>
                    <span class="event-tag">Leggendario</span>
                </div>
            </div>
        </header>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-book-open section-icon"></i>
                Panoramica
            </h2>
            <div class="section-content">
                <p>
                    Il Capodanno Epico 2024 rimarrà per sempre impresso nella memoria collettiva del gruppo
                    come uno degli eventi più straordinari e memorabili della nostra storia. Quello che era
                    iniziato come una semplice idea per passare insieme l'ultima notte dell'anno si è
                    trasformato in un'esperienza che ha superato ogni aspettativa, creando ricordi che
                    vengono ancora raccontati e ricordati con affetto e nostalgia.
                </p>

                <p>
                    La scelta della location, una splendida villa affacciata sul Lago di Como, ha fornito
                    lo scenario perfetto per quello che sarebbe diventato l'evento dell'anno. Con 18 membri
                    del gruppo presenti, l'atmosfera era elettrica già dalle prime ore del pomeriggio del
                    31 dicembre, quando tutti hanno iniziato ad arrivare carichi di entusiasmo e aspettative.
                </p>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">14</div>
                        <div class="stat-label">Ore di Festa</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">3</div>
                        <div class="stat-label">Nuovi Meme</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">∞</div>
                        <div class="stat-label">Ricordi Creati</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Divertimento</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-clock section-icon"></i>
                Cronologia della Serata
            </h2>
            <div class="section-content">
                <p>
                    La serata si è sviluppata in una serie di momenti indimenticabili, ognuno contribuendo
                    a creare l'atmosfera magica che ha caratterizzato l'evento. Ecco una ricostruzione
                    dettagliata di come si sono svolti i fatti:
                </p>

                <div class="timeline-container">
                    <div class="timeline-line"></div>

                    <div class="timeline-item">
                        <div class="timeline-time">18:00 - Arrivo e Setup</div>
                        <div class="timeline-text">
                            I primi membri iniziano ad arrivare alla villa. L'entusiasmo è palpabile mentre
                            tutti collaborano per preparare gli spazi comuni, sistemare le decorazioni e
                            organizzare il buffet. Mario prende subito in mano la situazione coordinando
                            le operazioni con la sua solita efficienza.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">20:00 - Aperitivo sul Terrazzo</div>
                        <div class="timeline-text">
                            Con tutti finalmente arrivati, il gruppo si riunisce sul terrazzo panoramico
                            per l'aperitivo. La vista mozzafiato sul lago crea l'atmosfera perfetta.
                            Iniziano i primi brindisi e le prime risate, mentre il sole tramonta
                            colorando il cielo di sfumature arancioni e rosa.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">21:30 - Cena Collettiva</div>
                        <div class="timeline-text">
                            La cena si trasforma in un'esperienza culinaria memorabile. Il menu preparato
                            insieme combina tradizione e creatività, con ogni portata accompagnata da
                            racconti, battute e aneddoti. L'atmosfera è talmente rilassata e piacevole
                            che la cena si prolunga per oltre due ore.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">23:00 - Giochi e Karaoke Pre-Mezzanotte</div>
                        <div class="timeline-text">
                            Nell'ora che precede la mezzanotte, il gruppo si lancia in una serie di giochi
                            improvvisati e sessioni di karaoke che diventano sempre più esilaranti.
                            Nascono alcune delle performance più imbarazzanti e memorabili della storia
                            del gruppo, immortalate in video che vengono ancora ripostati oggi.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">00:00 - Il Conto alla Rovescia</div>
                        <div class="timeline-text">
                            Il momento più atteso arriva. Tutti si riuniscono sul terrazzo con bicchieri
                            di champagne. Il countdown viene scandito all'unisono, e allo scoccare della
                            mezzanotte esplode una gioia collettiva. Abbracci, brindisi e fuochi d'artificio
                            improvvisati (che per poco non causano un piccolo incidente) segnano l'inizio
                            del nuovo anno nel modo più spettacolare possibile.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">01:00 - La Festa Continua</div>
                        <div class="timeline-text">
                            Dopo la mezzanotte, l'energia del gruppo non accenna a diminuire. La musica
                            viene alzata, la pista da ballo improvvisata nel salone si riempie, e la festa
                            entra nel vivo. Giulia introduce la sua ormai famosa "mossa proibita" che
                            diventerà un meme istantaneo.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">03:00 - Sessione Notturna sul Lago</div>
                        <div class="timeline-text">
                            Un sottogruppo dei più avventurosi decide di fare una passeggiata lungo la
                            riva del lago. Con torce e coperte, il gruppo si siede sulla spiaggia privata
                            della villa, guardando le stelle e condividendo pensieri profondi e riflessioni
                            sul passato e sul futuro. È uno dei momenti più intimi e significativi della serata.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">05:00 - Alba e Colazione</div>
                        <div class="timeline-text">
                            Incredibilmente, molti sono ancora svegli per vedere l'alba. Il gruppo si
                            riunisce di nuovo sul terrazzo per assistere al primo tramonto del 2024.
                            Seguono una colazione improvvisata e le prime battute su come nessuno abbia
                            dormito. Nasce il meme "Dormire è sopravvalutato".
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-time">08:00 - Epilogo</div>
                        <div class="timeline-text">
                            Finalmente, dopo 14 ore di festa ininterrotta, il gruppo inizia a sfaldarsi.
                            Chi va a dormire, chi si dirige verso casa, chi rimane a godersi gli ultimi
                            momenti insieme. Tutti sono d'accordo: è stata una delle migliori serate
                            mai vissute insieme.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-star section-icon"></i>
                Momenti Iconici
            </h2>
            <div class="section-content">
                <p>
                    Durante la notte sono accaduti numerosi episodi che sono entrati di diritto nella
                    leggenda del gruppo. Questi momenti hanno definito l'essenza dell'evento e sono ancora
                    oggi costantemente citati e ricordati.
                </p>

                <h3>I Fuochi d'Artificio Improvvisati</h3>
                <p>
                    Quello che doveva essere un semplice spettacolo di petardi si è trasformato in un
                    mini-show pirotecnico quando Luca ha deciso di "migliorare" il setup originale.
                    Il risultato è stato spettacolare ma anche leggermente pericoloso, con uno dei
                    fuochi che ha preso una direzione inaspettata finendo quasi in piscina.
                    Fortunatamente nessuno si è fatto male e l'incidente è diventato uno degli
                    aneddoti più raccontati della serata.
                </p>

                <h3>Il Karaoke di Mario</h3>
                <p>
                    Alle 23:30, Mario ha preso il microfono per quella che sarebbe dovuta essere una
                    semplice canzone di riscaldamento. Quello che ne è seguito è stata una performance
                    di 15 minuti che ha attraversato almeno 5 canzoni diverse, con Mario che improvvisava
                    medley sempre più assurdi. La sua interpretazione finale di "Bohemian Rhapsody" è
                    stata definita da Giulia come "traumatizzante nel modo migliore possibile".
                </p>

                <div class="quote-box">
                    "Non dimenticherò mai l'espressione di Mario quando ha realizzato che stavamo tutti
                    registrando la sua performance. Pura arte contemporanea." - Giulia, 2024
                </div>

                <h3>La Nascita della "Danza Proibita"</h3>
                <p>
                    Intorno all'una di notte, in un momento di particolare euforia, Giulia ha inventato
                    una mossa di danza così bizzarra e imbarazzante che è stata immediatamente dichiarata
                    "proibita" da tutti i presenti. Naturalmente, questo ha significato che tutti hanno
                    dovuto provare a replicarla, creando uno dei video più divertenti nella storia del gruppo.
                </p>

                <h3>Le Conversazioni dell'Alba</h3>
                <p>
                    Forse il momento più toccante della serata è stato quando, alle tre del mattino,
                    un gruppo si è ritrovato sulla spiaggia privata della villa. Lontani dalla musica
                    e dal caos della festa, hanno avuto conversazioni profonde sulla vita, l'amicizia
                    e il futuro. Questi momenti di connessione autentica hanno ricordato a tutti perché
                    questo gruppo è così speciale.
                </p>

                <div class="highlight-box">
                    <div class="highlight-title">
                        <i class="fas fa-trophy"></i>
                        Achievement Sbloccato
                    </div>
                    <p style="margin: 0; color: rgba(255, 255, 255, 0.75);">
                        "Notte Perfetta" - Tutti i membri presenti hanno dichiarato questo il miglior
                        Capodanno mai vissuto. È l'unico evento nella storia del gruppo ad aver ricevuto
                        una valutazione unanime di 10/10 da tutti i partecipanti.
                    </p>
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-users section-icon"></i>
                Partecipanti
            </h2>
            <div class="section-content">
                <p>
                    18 membri coraggiosi del gruppo hanno partecipato a questa epica celebrazione.
                    Ognuno ha contribuito in modo unico a rendere la serata indimenticabile:
                </p>

                <div class="participants-grid">
                    <a href="persona-dettaglio.html?id=mario" class="participant-card">
                        <div class="participant-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="participant-name">Mario</div>
                    </a>
                    <a href="persona-dettaglio.html?id=luca" class="participant-card">
                        <div class="participant-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="participant-name">Luca</div>
                    </a>
                    <a href="persona-dettaglio.html?id=giulia" class="participant-card">
                        <div class="participant-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="participant-name">Giulia</div>
                    </a>
                    <div class="participant-card">
                        <div class="participant-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="participant-name">+15 Altri</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-heart section-icon"></i>
                Impatto e Legacy
            </h2>
            <div class="section-content">
                <p>
                    Il Capodanno Epico 2024 non è stato solo una grande festa, ma ha rappresentato un
                    momento di svolta per il gruppo. L'evento ha consolidato legami, creato nuove tradizioni
                    e stabilito nuovi standard per tutti gli eventi futuri.
                </p>

                <h3>Nuove Tradizioni Nate</h3>
                <p>
                    Dalla serata sono emerse diverse tradizioni che il gruppo ha deciso di mantenere per
                    gli eventi futuri. Il "brindisi dell'alba" è diventato un rituale per chi riesce a
                    restare sveglio fino al mattino. La "playlist della mezzanotte", curata collettivamente
                    durante le settimane precedenti, è diventata un elemento fisso della preparazione di
                    ogni grande evento.
                </p>

                <h3>Rafforzamento dei Legami</h3>
                <p>
                    Molti membri hanno dichiarato che quella notte ha rappresentato un punto di svolta nei
                    loro rapporti con gli altri. Le conversazioni profonde della notte, i momenti di pura
                    gioia condivisa e l'esperienza di creare insieme qualcosa di speciale hanno creato
                    connessioni che vanno oltre la semplice amicizia.
                </p>

                <div class="quote-box">
                    "Prima di quella notte, eravamo un gruppo di amici. Dopo, siamo diventati una famiglia.
                    C'è una differenza enorme, e quella notte ha fatto la differenza." - Luca, intervista
                    post-evento
                </div>

                <h3>Standard per Eventi Futuri</h3>
                <p>
                    Il successo del Capodanno Epico 2024 ha innalzato notevolmente le aspettative per tutti
                    gli eventi successivi. Mentre nessuno si aspetta di replicare esattamente quella magia,
                    l'evento ha dimostrato cosa è possibile raggiungere quando tutti contribuiscono con
                    entusiasmo e creatività. Ha anche insegnato preziose lezioni sull'importanza della
                    pianificazione collaborativa e della flessibilità.
                </p>

                <h3>Contenuti Generati</h3>
                <p>
                    L'evento ha prodotto una quantità straordinaria di contenuti che continuano a vivere
                    nelle chat e nei ricordi del gruppo. Sono state scattate oltre 500 foto, registrati
                    più di 50 video, e creati almeno 3 nuovi meme che sono ancora in uso regolare.
                    Il materiale raccolto durante la serata costituisce una delle più ricche collezioni
                    di ricordi nella storia del gruppo.
                </p>
            </div>
        </section>

        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-lightbulb section-icon"></i>
                Curiosità e Aneddoti
            </h2>
            <div class="section-content">
                <ul>
                    <li>
                        La villa è stata prenotata 6 mesi in anticipo da Mario, che aveva avuto una
                        "premonizione" che sarebbe stata l'ambientazione perfetta per un evento epico.
                    </li>
                    <li>
                        Durante i preparativi, sono state consumate più di 20 pizze, 15 bottiglie di
                        champagne, e una quantità imprecisata di snack vari.
                    </li>
                    <li>
                        Il DJ improvvisato della serata ha creato una playlist di 14 ore che non ha mai
                        ripetuto la stessa canzone due volte.
                    </li>
                    <li>
                        Tre membri del gruppo hanno effettivamente dormito durante la festa, ma solo per
                        brevi pisolini di 20 minuti ciascuno.
                    </li>
                    <li>
                        Il video della performance di karaoke di Mario ha raggiunto lo status di "leggenda"
                        ed è protetto da password nelle chat del gruppo per evitare leak accidentali.
                    </li>
                    <li>
                        Durante la conversazione dell'alba, sono state fatte 7 promesse importanti, di cui
                        5 sono state effettivamente mantenute nei mesi successivi.
                    </li>
                    <li>
                        Il proprietario della villa ha lasciato una recensione entusiasta, definendo il
                        gruppo "gli ospiti più educati e divertenti mai avuti".
                    </li>
                </ul>

                <div class="highlight-box">
                    <div class="highlight-title">
                        <i class="fas fa-award"></i>
                        Record Stabiliti
                    </div>
                    <p style="margin: 0; color: rgba(255, 255, 255, 0.75);">
                        • Festa più lunga: 14 ore consecutive<br>
                        • Maggior numero di partecipanti a un evento: 18<br>
                        • Più meme generati in una singola notte: 3<br>
                        • Performance di karaoke più lunga: 15 minuti (Mario)<br>
                        • Valutazione media dell'evento: 10/10 (unanime)
                    </p>
                </div>
            </div>
        </section>

        <section class="related-section">
            <h3 class="related-title">Eventi e Contenuti Correlati</h3>
            <div class="related-grid">
                <a href="persona-dettaglio.html?id=mario" class="related-card">
                    <div class="related-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="related-info">
                        <div class="related-name">Mario Rossi</div>
                        <div class="related-type">Organizzatore Principale</div>
                    </div>
                </a>

                <a href="meme-dettaglio.html?id=dance-move" class="related-card">
                    <div class="related-icon">
                        <i class="fas fa-person-dancing"></i>
                    </div>
                    <div class="related-info">
                        <div class="related-name">La Danza Proibita</div>
                        <div class="related-type">Meme • Nato questa notte</div>
                    </div>
                </a>

                <a href="evento-dettaglio.html?id=gita-lago" class="related-card">
                    <div class="related-icon">
                        <i class="fas fa-mountain"></i>
                    </div>
                    <div class="related-info">
                        <div class="related-name">Weekend al Lago</div>
                        <div class="related-type">Evento Successivo</div>
                    </div>
                </a>
            </div>
        </section>
    </div>
</body>

</html>