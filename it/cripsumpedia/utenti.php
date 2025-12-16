<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Devi essere loggato per accedere a Cripsumpedia™.";
    header('Location: ../accedi');
    exit();
}

if (!isOwner()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina Cripsumpedia™ è ancora in fase di sviluppo.";
    header('Location: ../home');
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../../includes/head-import.php'; ?>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem 4rem;
        }

        .page-header {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #64c8ff;
            transform: translateX(-3px);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-icon {
            color: #64c8ff;
            font-size: 2rem;
        }

        .page-description {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
        }

        .search-section {
            margin-bottom: 2.5rem;
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .search-input:focus {
            outline: none;
            border-color: #64c8ff;
            box-shadow: 0 0 0 3px rgba(100, 200, 255, 0.1);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.06));
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            font-size: 1.1rem;
        }

        .people-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .person-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }

        .person-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.05), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .person-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.3);
            border-color: rgba(100, 200, 255, 0.3);
        }

        .person-card:hover::before {
            opacity: 1;
        }

        .person-avatar {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(74, 158, 255, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #64c8ff;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .person-card:hover .person-avatar {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(100, 200, 255, 0.2);
        }

        .person-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .person-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .person-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .person-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(100, 200, 255, 0.15);
            color: #64c8ff;
            border: 1px solid rgba(100, 200, 255, 0.3);
            font-weight: 500;
        }

        .person-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            color: rgba(255, 255, 255, 0.4);
        }

        .person-description {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin: 0;
        }

        .person-arrow {
            margin-left: auto;
            color: rgba(255, 255, 255, 0.3);
            font-size: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            align-self: center;
        }

        .person-card:hover .person-arrow {
            color: #64c8ff;
            transform: translateX(5px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 2rem 1.5rem 3rem;
            }

            .page-title {
                font-size: 2rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .person-card {
                flex-direction: column;
                gap: 1.5rem;
                padding: 1.5rem;
            }

            .person-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }

            .person-arrow {
                position: absolute;
                bottom: 1.5rem;
                right: 1.5rem;
            }

            .person-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .person-card {
                padding: 1.25rem;
            }

            .person-name {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <a href="../cripsumpedia" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Torna alla home
            </a>
            <h1 class="page-title">
                <i class="fas fa-users page-icon"></i>
                Persone
            </h1>
            <p class="page-description">
                Esplora i profili di tutti i membri del gruppo. Ogni persona ha la propria storia,
                personalità e contributi unici che hanno aiutato a costruire la nostra community.
            </p>
        </div>

        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Cerca una persona..." id="searchInput">
            </div>
        </div>

        <div class="people-list" id="peopleList">
            <!-- Esempio 1 -->
            <a href="persona-dettaglio.html?id=mario" class="person-card">
                <div class="person-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="person-info">
                    <div class="person-header">
                        <h2 class="person-name">Mario Rossi</h2>
                        <span class="person-badge">Fondatore</span>
                    </div>
                    <div class="person-meta">
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i>
                            Nel gruppo dal 2020
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            Milano
                        </span>
                    </div>
                    <p class="person-description">
                        Uno dei fondatori del gruppo, conosciuto per il suo senso dell'umorismo unico e
                        la capacità di trasformare qualsiasi situazione in un momento memorabile. Ha dato
                        vita a innumerevoli meme e inside jokes che ancora oggi vengono citati.
                    </p>
                </div>
                <i class="fas fa-chevron-right person-arrow"></i>
            </a>

            <!-- Esempio 2 -->
            <a href="persona-dettaglio.html?id=luca" class="person-card">
                <div class="person-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="person-info">
                    <div class="person-header">
                        <h2 class="person-name">Luca Bianchi</h2>
                        <span class="person-badge">Membro Storico</span>
                    </div>
                    <div class="person-meta">
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i>
                            Nel gruppo dal 2021
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            Roma
                        </span>
                    </div>
                    <p class="person-description">
                        Entrato nel gruppo nel 2021, è rapidamente diventato una figura centrale grazie
                        alla sua personalità carismatica. Organizzatore di eventi epici e creatore di
                        alcune delle avventure più memorabili del gruppo.
                    </p>
                </div>
                <i class="fas fa-chevron-right person-arrow"></i>
            </a>

            <!-- Esempio 3 -->
            <a href="persona-dettaglio.html?id=giulia" class="person-card">
                <div class="person-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="person-info">
                    <div class="person-header">
                        <h2 class="person-name">Giulia Verdi</h2>
                    </div>
                    <div class="person-meta">
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i>
                            Nel gruppo dal 2022
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            Torino
                        </span>
                    </div>
                    <p class="person-description">
                        La più recente aggiunta al gruppo, ma già protagonista di numerose storie
                        leggendarie. Famosa per le sue battute fulminanti e per essere stata al centro
                        di alcuni degli eventi più divertenti degli ultimi anni.
                    </p>
                </div>
                <i class="fas fa-chevron-right person-arrow"></i>
            </a>
        </div>
    </div>
    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement" />
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>
    <?php include '../../includes/scroll_indicator.php'; ?>
    <?php include '../../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

    <script src="../../js/modeChanger.js"></script>

    <script>
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.person-card');

            cards.forEach(card => {
                const name = card.querySelector('.person-name').textContent.toLowerCase();
                const description = card.querySelector('.person-description').textContent.toLowerCase();

                if (name.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>

</html>