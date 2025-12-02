<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);


if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere alla sezione editing devi essere loggato";

    header('Location: accedi');
    exit();
}

if (!isOwner()) {
    http_response_code(403);
    exit('Non autorizzato, scemo');
}

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <link rel="stylesheet" href="../../css/editing.css">
    <link rel="stylesheet" href="../../css/editing-pages.css">
    <title>Cripsum™ - Audio</title>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="page-section">
        <div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    <div class="page-header">
                        <div class="page-icon-wrapper">
                            <i class="fas fa-music page-icon"></i>
                        </div>
                        <h1 class="page-title">Audio</h1>
                        <p class="page-description">Libreria completa di effetti sonori e tracce musicali per i tuoi progetti</p>
                        <!-- <div class="software-badge">
                            <i class="fas fa-headphones"></i> Sound Library
                        </div> -->

                        <div class="filter-bar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Cerca audio o effetti...">
                            </div>
                            <div class="filter-buttons">
                                <button class="filter-btn active">Tutti</button>
                                <button class="filter-btn">Musica</button>
                                <button class="filter-btn">SFX</button>
                                <button class="filter-btn">Ambient</button>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Audio">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Musica</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Trap Beats Collection</h3>
                                <p class="card-meta">
                                    <i class="fas fa-music"></i> 15 Tracce
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 892
                                </p>
                                <p class="card-description">Beat trap moderni e aggressivi, perfetti per edit urban e street. Royalty-free.</p>
                                <div class="card-tags">
                                    <span class="tag">Trap</span>
                                    <span class="tag">Hip-Hop</span>
                                    <span class="tag">Royalty-Free</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-play"></i> Anteprima
                                    </a>
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-download"></i> Scarica
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Audio">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">SFX</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Cinematic SFX Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-volume-up"></i> 150 Effetti
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 1.2k
                                </p>
                                <p class="card-description">Effetti sonori cinematografici: whoosh, impacts, risers e transitions di qualità professionale.</p>
                                <div class="card-tags">
                                    <span class="tag">SFX</span>
                                    <span class="tag">Cinematic</span>
                                    <span class="tag">WAV</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-play"></i> Anteprima
                                    </a>
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-download"></i> Scarica
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Audio">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Musica</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Lo-Fi Chill Beats</h3>
                                <p class="card-meta">
                                    <i class="fas fa-music"></i> 20 Tracce
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 645
                                </p>
                                <p class="card-description">Beat lo-fi rilassanti e atmosferici, ideali per vlog, tutorial e contenuti chill.</p>
                                <div class="card-tags">
                                    <span class="tag">Lo-Fi</span>
                                    <span class="tag">Chill</span>
                                    <span class="tag">Royalty-Free</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-play"></i> Anteprima
                                    </a>
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-download"></i> Scarica
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Audio">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">SFX</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Glitch & Digital SFX</h3>
                                <p class="card-meta">
                                    <i class="fas fa-volume-up"></i> 80 Effetti
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 534
                                </p>
                                <p class="card-description">Effetti sonori glitch, distorsioni digitali e suoni futuristici per edit moderni.</p>
                                <div class="card-tags">
                                    <span class="tag">Glitch</span>
                                    <span class="tag">Digital</span>
                                    <span class="tag">WAV</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-play"></i> Anteprima
                                    </a>
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-download"></i> Scarica
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Audio">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Musica</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Epic Orchestral Music</h3>
                                <p class="card-meta">
                                    <i class="fas fa-music"></i> 12 Tracce
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 723
                                </p>
                                <p class="card-description">Musica orchestrale epica e cinematografica per trailer, intro e momenti drammatici.</p>
                                <div class="card-tags">
                                    <span class="tag">Epic</span>
                                    <span class="tag">Orchestral</span>
                                    <span class="tag">Cinematic</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-play"></i> Anteprima
                                    </a>
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-download"></i> Scarica
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Audio">
                                <div class="card-overlay">
                                    <span class="difficulty-badge advanced">Ambient</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Ambient Atmospheres</h3>
                                <p class="card-meta">
                                    <i class="fas fa-wind"></i> 45 Tracce
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 412
                                </p>
                                <p class="card-description">Atmosfere ambient e texture sonore per creare profondità e mood nei tuoi video.</p>
                                <div class="card-tags">
                                    <span class="tag">Ambient</span>
                                    <span class="tag">Atmosphere</span>
                                    <span class="tag">Texture</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-play"></i> Anteprima
                                    </a>
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-download"></i> Scarica
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="load-more-section">
                        <button class="load-more-btn">
                            <i class="fas fa-plus"></i> Carica altri
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/modeChanger.js"></script>
</body>

</html>