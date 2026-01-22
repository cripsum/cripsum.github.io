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
    <title>Cripsum™ - Tutorial & Preset</title>
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
                            <i class="fas fa-book-open page-icon"></i>
                        </div>
                        <h1 class="page-title">Tutorial & Preset</h1>
                        <p class="page-description">Impara le tecniche di editing più avanzate in After Effects e applica subito i preset professionali ai tuoi progetti</p>
                        <!-- <div class="software-badge">
                            <i class="fab fa-adobe"></i> After Effects
                        </div> -->

                        <div class="filter-bar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Cerca tutorial o preset...">
                            </div>
                            <div class="filter-buttons">
                                <button class="filter-btn active">Tutti</button>
                                <button class="filter-btn">Principianti</button>
                                <button class="filter-btn">Intermedio</button>
                                <button class="filter-btn">Avanzato</button>
                            </div>
                        </div>
                    </div>
                    <div class="content-grid">
                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Tutorial">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Principiante</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Color Grading Cinematico</h3>
                                <p class="card-meta">
                                    <i class="fas fa-clock"></i> 15 min
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 234
                                </p>
                                <p class="card-description">Impara a dare ai tuoi video un look cinematografico professionale con tecniche di color grading avanzate.</p>
                                <div class="card-tags">
                                    <span class="tag">Premiere Pro</span>
                                    <span class="tag">DaVinci Resolve</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-play"></i> Guarda Tutorial
                                    </a>
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-download"></i> Scarica Preset
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Tutorial">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">Intermedio</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Transizioni Smooth & Creative</h3>
                                <p class="card-meta">
                                    <i class="fas fa-clock"></i> 22 min
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 189
                                </p>
                                <p class="card-description">Crea transizioni fluide e creative che rendono i tuoi video dinamici e professionali.</p>
                                <div class="card-tags">
                                    <span class="tag">After Effects</span>
                                    <span class="tag">Premiere Pro</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-play"></i> Guarda Tutorial
                                    </a>
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-download"></i> Scarica Preset
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Tutorial">
                                <div class="card-overlay">
                                    <span class="difficulty-badge advanced">Avanzato</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Effetti Glitch & Distortion</h3>
                                <p class="card-meta">
                                    <i class="fas fa-clock"></i> 30 min
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 156
                                </p>
                                <p class="card-description">Tecniche avanzate per creare effetti glitch, distorsioni e interferenze dall'aspetto professionale.</p>
                                <div class="card-tags">
                                    <span class="tag">After Effects</span>
                                    <span class="tag">Avanzato</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-play"></i> Guarda Tutorial
                                    </a>
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-download"></i> Scarica Preset
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Tutorial">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Principiante</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Animazioni Testo Dinamiche</h3>
                                <p class="card-meta">
                                    <i class="fas fa-clock"></i> 18 min
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 298
                                </p>
                                <p class="card-description">Scopri come animare testi in modo accattivante con effetti moderni e dinamici.</p>
                                <div class="card-tags">
                                    <span class="tag">After Effects</span>
                                    <span class="tag">Premiere Pro</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-play"></i> Guarda Tutorial
                                    </a>
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-download"></i> Scarica Preset
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Tutorial">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">Intermedio</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Speed Ramping Professionale</h3>
                                <p class="card-meta">
                                    <i class="fas fa-clock"></i> 25 min
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 203
                                </p>
                                <p class="card-description">Master delle tecniche di speed ramping per creare slow motion e accelerazioni fluide.</p>
                                <div class="card-tags">
                                    <span class="tag">Premiere Pro</span>
                                    <span class="tag">DaVinci Resolve</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-play"></i> Guarda Tutorial
                                    </a>
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-download"></i> Scarica Preset
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Tutorial">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">Intermedio</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">VFX Base per Edit</h3>
                                <p class="card-meta">
                                    <i class="fas fa-clock"></i> 28 min
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 175
                                </p>
                                <p class="card-description">Introduzione agli effetti speciali di base per rendere i tuoi edit più interessanti e dinamici.</p>
                                <div class="card-tags">
                                    <span class="tag">After Effects</span>
                                    <span class="tag">VFX</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary">
                                        <i class="fas fa-play"></i> Guarda Tutorial
                                    </a>
                                    <a href="#" class="btn-secondary">
                                        <i class="fas fa-download"></i> Scarica Preset
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
    
</body>

</html>