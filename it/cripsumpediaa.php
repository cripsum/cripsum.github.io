<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Devi essere loggato per accedere a Cripsumpedia™.";
    header('Location: accedi');
    exit();
}

if (!isOwner()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina Cripsumpedia™ è ancora in fase di sviluppo.";
    header('Location: home');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsumpedia™</title>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660"
        crossorigin="anonymous"></script>
    <style>
        body {
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
            padding-top: 5rem;
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem 4rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem 1rem;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #ffffff;
        }

        .page-description {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.75);
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.8;
        }

        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .section-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            padding: 2.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .section-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .section-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .section-card:hover::before {
            opacity: 1;
        }

        .section-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .section-card:nth-child(1) .section-icon-wrapper {
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.15), rgba(74, 158, 255, 0.1));
        }

        .section-card:nth-child(2) .section-icon-wrapper {
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.15), rgba(255, 74, 169, 0.1));
        }

        .section-card:nth-child(3) .section-icon-wrapper {
            background: linear-gradient(135deg, rgba(255, 215, 100, 0.15), rgba(255, 184, 68, 0.1));
        }

        .section-card:hover .section-icon-wrapper {
            transform: scale(1.1);
        }

        .section-icon {
            font-size: 2.5rem;
            position: relative;
            z-index: 1;
        }

        .section-card:nth-child(1) .section-icon {
            color: #64c8ff;
        }

        .section-card:nth-child(2) .section-icon {
            color: #ff64c8;
        }

        .section-card:nth-child(3) .section-icon {
            color: #ffd764;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #ffffff;
            position: relative;
            z-index: 1;
        }

        .section-text {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .section-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            z-index: 1;
        }

        .section-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }

        .section-card:nth-child(1) .section-badge {
            background: rgba(100, 200, 255, 0.1);
            color: #64c8ff;
        }

        .section-card:nth-child(2) .section-badge {
            background: rgba(255, 100, 200, 0.1);
            color: #ff64c8;
        }

        .section-card:nth-child(3) .section-badge {
            background: rgba(255, 215, 100, 0.1);
            color: #ffd764;
        }

        .section-arrow {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
        }

        .section-card:hover .section-arrow {
            transform: translateX(5px);
            color: rgba(255, 255, 255, 0.8);
        }

        .info-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 2.5rem;
            margin-top: 3rem;
            backdrop-filter: blur(10px);
        }

        .info-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #ffffff;
        }

        .info-text {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
            margin-bottom: 0;
        }

        @media (max-width: 992px) {
            .sections-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }

            .page-title {
                font-size: 2.5rem;
            }

            .main-content {
                padding: 2rem 1.5rem 3rem;
            }
        }

        @media (max-width: 768px) {
            .sections-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .page-header {
                padding: 1.5rem 0.5rem;
                margin-bottom: 2rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .page-description {
                font-size: 1rem;
            }

            .section-card {
                padding: 2rem;
            }

            .section-icon-wrapper {
                width: 70px;
                height: 70px;
            }

            .section-icon {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .info-section {
                padding: 2rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 4rem;
            }

            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .sections-grid {
                grid-template-columns: minmax(280px, 1fr);
            }

            .section-card {
                padding: 1.75rem;
            }

            .info-section {
                padding: 1.75rem;
            }

            .info-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body class="">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Cripsumpedia™</h1>
            <p class="page-description">
                Benvenuto nell'archivio della lore di Cripsum™! Qui troverai tutte le informazioni,
                le storie e i momenti più memorabili che hanno caratterizzato il gruppo più fico di sempre.
                Esplora le diverse sezioni per scoprire tutto quello che c'è da sapere.
            </p>
        </div>

        <div class="sections-grid">
            <a href="cripsumpediaa/utenti" class="section-card">
                <div class="section-icon-wrapper">
                    <i class="fas fa-users section-icon"></i>
                </div>
                <h2 class="section-title">Persone</h2>
                <p class="section-text">
                    Scopri la lore delle persone del gruppo attraverso biografie dettagliate, storie personali e aneddoti.
                    Ogni membro ha contribuito in modo unico alla creazione della nostra community, e qui potrai conoscere
                    le loro personalità, i loro contributi e tutto quello che li rende speciali.
                </p>
                <div class="section-footer">
                    <span class="section-badge">Esplora</span>
                    <i class="fas fa-arrow-right section-arrow"></i>
                </div>
            </a>

            <a href="cripsumpediaa/eventi" class="section-card">
                <div class="section-icon-wrapper">
                    <i class="fas fa-star section-icon"></i>
                </div>
                <h2 class="section-title">Eventi</h2>
                <p class="section-text">
                    Rivivi i momenti più importanti e significativi della storia del gruppo. Dalle avventure epiche
                    ai disastri memorabili, dai traguardi raggiunti alle serate indimenticabili. Ogni evento racconta
                    una storia che ha contribuito a definire chi siamo oggi come gruppo.
                </p>
                <div class="section-footer">
                    <span class="section-badge">Scopri</span>
                    <i class="fas fa-arrow-right section-arrow"></i>
                </div>
            </a>

            <a href="cripsumpediaa/memes" class="section-card">
                <div class="section-icon-wrapper">
                    <i class="fas fa-face-grin-tears section-icon"></i>
                </div>
                <h2 class="section-title">Memes</h2>
                <p class="section-text">
                    La collezione definitiva di meme, inside jokes e momenti diventati leggenda. Qui troverai tutte
                    quelle battute, situazioni assurde e momenti esilaranti che solo chi fa parte del gruppo può davvero
                    comprendere.
                </p>
                <div class="section-footer">
                    <span class="section-badge">Divertiti</span>
                    <i class="fas fa-arrow-right section-arrow"></i>
                </div>
            </a>
        </div>

        <!-- <div class="info-section">
            <h3 class="info-title">Contribuisci all'archivio</h3>
            <p class="info-text">
                Cripsumpedia™ è un progetto in continua evoluzione. Se hai storie, foto o ricordi da aggiungere,
                o se vuoi suggerire nuove voci per l'enciclopedia, sentiti libero di contribuire. Questo spazio
                è di tutti noi e cresce grazie ai contributi di ciascun membro del gruppo. Insieme possiamo
                preservare e celebrare la nostra storia condivisa.
            </p>
        </div> -->
    </div>
    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement" />
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>
    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

    <script src="../js/modeChanger.js"></script>
</body>

</html>