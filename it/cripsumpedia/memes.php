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
            max-width: 1400px;
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
            color: #ffd764;
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
            color: #ffd764;
            font-size: 2rem;
        }

        .page-description {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
        }

        .filter-section {
            margin-bottom: 2.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-label {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1.25rem;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: linear-gradient(135deg, rgba(255, 215, 100, 0.2), rgba(255, 184, 68, 0.15));
            border-color: rgba(255, 215, 100, 0.4);
            color: #ffd764;
        }

        .memes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .meme-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(15px);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .meme-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 215, 100, 0.05), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .meme-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 215, 100, 0.3);
        }

        .meme-card:hover::before {
            opacity: 1;
        }

        .meme-image {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, rgba(255, 215, 100, 0.15), rgba(255, 184, 68, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #ffd764;
            position: relative;
            z-index: 1;
        }

        .meme-content {
            padding: 1.75rem;
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .meme-header {
            margin-bottom: 1rem;
        }

        .meme-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .meme-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.75rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .meta-item i {
            color: rgba(255, 255, 255, 0.4);
        }

        .meme-description {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            flex: 1;
        }

        .meme-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .meme-category {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(255, 215, 100, 0.15);
            color: #ffd764;
            border: 1px solid rgba(255, 215, 100, 0.3);
            font-weight: 500;
        }

        .meme-arrow {
            color: rgba(255, 255, 255, 0.3);
            font-size: 1.25rem;
            transition: all 0.3s ease;
        }

        .meme-card:hover .meme-arrow {
            color: #ffd764;
            transform: translateX(5px);
        }

        .popularity-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #ffd764;
            border: 1px solid rgba(255, 215, 100, 0.3);
            z-index: 2;
        }

        .popularity-badge i {
            font-size: 0.9rem;
        }

        @media (max-width: 992px) {
            .memes-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1.5rem;
            }
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

            .memes-grid {
                grid-template-columns: 1fr;
            }

            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .memes-grid {
                grid-template-columns: minmax(280px, 1fr);
            }

            .meme-content {
                padding: 1.5rem;
            }

            .meme-image {
                height: 180px;
                font-size: 3rem;
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
                <i class="fas fa-face-grin-tears page-icon"></i>
                Meme
            </h1>
            <p class="page-description">
                La raccolta definitiva di meme, inside jokes e momenti iconici del gruppo. Ogni meme racconta
                una storia e rappresenta un pezzo della nostra cultura condivisa che solo noi possiamo veramente apprezzare.
            </p>
        </div>

        <div class="filter-section">
            <span class="filter-label">Filtra per categoria:</span>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">Lorem ipsum</button>
                <button class="filter-btn" data-filter="citazioni">Lorem ipsum</button>
                <button class="filter-btn" data-filter="situazioni">Lorem ipsum</button>
                <button class="filter-btn" data-filter="inside-jokes">Lorem ipsum</button>
                <button class="filter-btn" data-filter="leggende">Lorem ipsum</button>
            </div>
        </div>

        <div class="memes-grid">

            <a href="meme-dettaglio.html?id=godo" class="meme-card" data-category="citazioni">
                <div class="meme-image">
                    <i class="fas fa-quote-left"></i>
                </div>
                <div class="popularity-badge">
                    <i class="fas fa-fire"></i>
                    Iconico
                </div>
                <div class="meme-content">
                    <div class="meme-header">
                        <h3 class="meme-title">Lorem ipsum</h3>
                        <div class="meme-meta">
                            <span class="meta-item">
                                <i class="fas fa-user"></i>
                                Lorem ipsum
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Lorem ipsum
                            </span>
                        </div>
                    </div>
                    <p class="meme-description">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut
                        labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                    </p>
                    <div class="meme-footer">
                        <span class="meme-category">Lorem ipsum</span>
                        <i class="fas fa-chevron-right meme-arrow"></i>
                    </div>
                </div>
            </a>

            <a href="meme-dettaglio.html?id=pizza-incident" class="meme-card" data-category="situazioni">
                <div class="meme-image">
                    <i class="fas fa-pizza-slice"></i>
                </div>
                <div class="popularity-badge">
                    <i class="fas fa-star"></i>
                    Lorem ipsum
                </div>
                <div class="meme-content">
                    <div class="meme-header">
                        <h3 class="meme-title">Lorem ipsum</h3>
                        <div class="meme-meta">
                            <span class="meta-item">
                                <i class="fas fa-users"></i>
                                Lorem ipsum
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Lorem ipsum
                            </span>
                        </div>
                    </div>
                    <p class="meme-description">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut
                        labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                    </p>
                    <div class="meme-footer">
                        <span class="meme-category">Situazioni</span>
                        <i class="fas fa-chevron-right meme-arrow"></i>
                    </div>
                </div>
            </a>

            <a href="meme-dettaglio.html?id=teoria-complotto" class="meme-card" data-category="inside-jokes">
                <div class="meme-image">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="meme-content">
                    <div class="meme-header">
                        <h3 class="meme-title">Lorem ipsum</h3>
                        <div class="meme-meta">
                            <span class="meta-item">
                                <i class="fas fa-user"></i>
                                Lorem ipsum
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Lorem ipsum
                            </span>
                        </div>
                    </div>
                    <p class="meme-description">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut
                        labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                    </p>
                    <div class="meme-footer">
                        <span class="meme-category">Lorem ipsum</span>
                        <i class="fas fa-chevron-right meme-arrow"></i>
                    </div>
                </div>
            </a>

            <a href="meme-dettaglio.html?id=dance-move" class="meme-card" data-category="leggende">
                <div class="meme-image">
                    <i class="fas fa-person-dancing"></i>
                </div>
                <div class="popularity-badge">
                    <i class="fas fa-fire"></i>
                    Lorem ipsum
                </div>
                <div class="meme-content">
                    <div class="meme-header">
                        <h3 class="meme-title">Lorem ipsum</h3>
                        <div class="meme-meta">
                            <span class="meta-item">
                                <i class="fas fa-user"></i>
                                Lorem ipsum
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Lorem ipsum
                            </span>
                        </div>
                    </div>
                    <p class="meme-description">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut
                        labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                    </p>
                    <div class="meme-footer">
                        <span class="meme-category">Lorem ipsum</span>
                        <i class="fas fa-chevron-right meme-arrow"></i>
                    </div>
                </div>
            </a>

            <a href="meme-dettaglio.html?id=nickname-origins" class="meme-card" data-category="inside-jokes">
                <div class="meme-image">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="meme-content">
                    <div class="meme-header">
                        <h3 class="meme-title">Lorem ipsum</h3>
                        <div class="meme-meta">
                            <span class="meta-item">
                                <i class="fas fa-users"></i>
                                Lorem ipsum
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Lorem ipsum
                            </span>
                        </div>
                    </div>
                    <p class="meme-description">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut
                        labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                    </p>
                    <div class="meme-footer">
                        <span class="meme-category">Lorem ipsum</span>
                        <i class="fas fa-chevron-right meme-arrow"></i>
                    </div>
                </div>
            </a>

            <a href="meme-dettaglio.html?id=epic-fail" class="meme-card" data-category="situazioni">
                <div class="meme-image">
                    <i class="fas fa-face-grin-beam-sweat"></i>
                </div>
                <div class="meme-content">
                    <div class="meme-header">
                        <h3 class="meme-title">Lorem ipsum</h3>
                        <div class="meme-meta">
                            <span class="meta-item">
                                <i class="fas fa-users"></i>
                                Lorem ipsum
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Lorem ipsum
                            </span>
                        </div>
                    </div>
                    <p class="meme-description">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut
                        labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco
                    </p>
                    <div class="meme-footer">
                        <span class="meme-category">Lorem ipsum</span>
                        <i class="fas fa-chevron-right meme-arrow"></i>
                    </div>
                </div>
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



    <script>
        const filterButtons = document.querySelectorAll('.filter-btn');
        const memeCards = document.querySelectorAll('.meme-card');

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {

                filterButtons.forEach(btn => btn.classList.remove('active'));

                button.classList.add('active');

                const filterValue = button.dataset.filter;

                memeCards.forEach(card => {
                    if (filterValue === 'all') {
                        card.style.display = 'flex';
                    } else {
                        if (card.dataset.category === filterValue) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>