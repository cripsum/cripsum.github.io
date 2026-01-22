<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);
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
            color: #ff64c8;
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
            color: #ff64c8;
            font-size: 2rem;
        }

        .page-description {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
        }

        .timeline-section {
            position: relative;
            padding-left: 3rem;
        }

        .timeline-line {
            position: absolute;
            left: 1.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg,
                    rgba(255, 100, 200, 0.5),
                    rgba(255, 100, 200, 0.2),
                    rgba(255, 100, 200, 0.1));
        }

        .timeline-year {
            margin-bottom: 2.5rem;
        }

        .year-label {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ff64c8;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .year-label::before {
            content: '';
            position: absolute;
            left: 1.1rem;
            width: 0.8rem;
            height: 0.8rem;
            background: #ff64c8;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(255, 100, 200, 0.2);
        }

        .events-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .event-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 2rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
            display: block;
        }

        .event-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.05), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .event-card:hover {
            transform: translateX(8px);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 100, 200, 0.3);
        }

        .event-card:hover::before {
            opacity: 1;
        }

        .event-header {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .event-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.2), rgba(255, 74, 169, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ff64c8;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .event-card:hover .event-icon {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(255, 100, 200, 0.2);
        }

        .event-info {
            flex: 1;
        }

        .event-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .event-date {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .event-date i {
            color: rgba(255, 255, 255, 0.4);
        }

        .event-description {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        .event-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            position: relative;
            z-index: 1;
        }

        .event-tag {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(255, 100, 200, 0.15);
            color: #ff64c8;
            border: 1px solid rgba(255, 100, 200, 0.3);
        }

        .event-arrow {
            position: absolute;
            top: 2rem;
            right: 2rem;
            color: rgba(255, 255, 255, 0.3);
            font-size: 1.5rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .event-card:hover .event-arrow {
            color: #ff64c8;
            transform: translateX(5px);
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

            .timeline-section {
                padding-left: 2rem;
            }

            .timeline-line {
                left: 0.75rem;
            }

            .year-label::before {
                left: 0.35rem;
            }

            .event-card {
                padding: 1.5rem;
            }

            .event-header {
                gap: 1rem;
            }

            .event-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .event-arrow {
                position: static;
                margin-top: 1rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .event-card {
                padding: 1.25rem;
            }

            .event-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <a href="home" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Torna alla home
            </a>
            <h1 class="page-title">
                <i class="fas fa-calendar-star page-icon"></i>
                Eventi
            </h1>
            <p class="page-description">
                Una timeline completa di tutti gli eventi memorabili che hanno segnato la storia del gruppo.
                Dalle avventure epiche ai momenti di pura follia, ogni evento ha contribuito a creare i ricordi che condividiamo.
            </p>
        </div>

        <div class="timeline-section">
            <div class="timeline-line"></div>

            <div class="timeline-year">
                <h2 class="year-label">2025</h2>
                <div class="events-grid">
                    <a href="evento-dettaglio.html?id=capodanno-2024" class="event-card">
                        <div class="event-header">
                            <div class="event-icon">
                                <i class="fas fa-champagne-glasses"></i>
                            </div>
                            <div class="event-info">
                                <h3 class="event-title">Lorem ipsum</h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    Lorem ipsum
                                </div>
                            </div>
                            <i class="fas fa-chevron-right event-arrow"></i>
                        </div>
                        <p class="event-description">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                            labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                            laboris nisi ut aliquip ex ea commodo consequat.
                        </p>
                        <div class="event-tags">
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                        </div>
                    </a>

                    <a href="evento-dettaglio.html?id=gita-lago" class="event-card">
                        <div class="event-header">
                            <div class="event-icon">
                                <i class="fas fa-mountain"></i>
                            </div>
                            <div class="event-info">
                                <h3 class="event-title">Lorem ipsum</h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    Lorem ipsum
                                </div>
                            </div>
                            <i class="fas fa-chevron-right event-arrow"></i>
                        </div>
                        <p class="event-description">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                            labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                            laboris nisi ut aliquip ex ea commodo consequat.
                        </p>
                        <div class="event-tags">
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="timeline-year">
                <h2 class="year-label">2024</h2>
                <div class="events-grid">
                    <a href="evento-dettaglio.html?id=lan-party" class="event-card">
                        <div class="event-header">
                            <div class="event-icon">
                                <i class="fas fa-gamepad"></i>
                            </div>
                            <div class="event-info">
                                <h3 class="event-title">Lorem ipsum</h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    Lorem ipsum
                                </div>
                            </div>
                            <i class="fas fa-chevron-right event-arrow"></i>
                        </div>
                        <p class="event-description">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                            labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                            laboris nisi ut aliquip ex ea commodo consequat.
                        </p>
                        <div class="event-tags">
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                        </div>
                    </a>

                    <a href="evento-dettaglio.html?id=fuga-escape" class="event-card">
                        <div class="event-header">
                            <div class="event-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="event-info">
                                <h3 class="event-title">Lorem ipsum</h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    Lorem ipsum
                                </div>
                            </div>
                            <i class="fas fa-chevron-right event-arrow"></i>
                        </div>
                        <p class="event-description">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                            labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                            laboris nisi ut aliquip ex ea commodo consequat.
                        </p>
                        <div class="event-tags">
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="timeline-year">
                <h2 class="year-label">2023</h2>
                <div class="events-grid">
                    <a href="evento-dettaglio.html?id=primo-incontro" class="event-card">
                        <div class="event-header">
                            <div class="event-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="event-info">
                                <h3 class="event-title">Lorem ipsum</h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    Lorem ipsum
                                </div>
                            </div>
                            <i class="fas fa-chevron-right event-arrow"></i>
                        </div>
                        <p class="event-description">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
                            labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                            laboris nisi ut aliquip ex ea commodo consequat.
                        </p>
                        <div class="event-tags">
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                            <span class="event-tag">Lorem ipsum</span>
                        </div>
                    </a>
                </div>
            </div>
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


</body>

</html>