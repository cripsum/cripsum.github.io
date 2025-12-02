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
    <title>Cripsum™ - Clip & Flowframe</title>
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
                            <i class="fas fa-film page-icon"></i>
                        </div>
                        <h1 class="page-title">Clip & Flowframe</h1>
                        <p class="page-description">Materiale video di qualità e tool per la fluidità dei frame per creare edit professionali</p>
                        <div class="software-badge">
                            <i class="fas fa-video"></i> Material & Tools
                        </div>

                        <div class="filter-bar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Cerca clip o tool...">
                            </div>
                            <div class="filter-buttons">
                                <button class="filter-btn active">Tutti</button>
                                <button class="filter-btn">Clip Video</button>
                                <button class="filter-btn">Flowframe Tools</button>
                                <button class="filter-btn">Stock Footage</button>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Clip">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">4K</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Cinematic Clips Pack Vol.1</h3>
                                <p class="card-meta">
                                    <i class="fas fa-video"></i> 50 Clip
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 432
                                </p>
                                <p class="card-description">Collezione di 50 clip cinematografiche in 4K, perfette per edit emozionali e storytelling.</p>
                                <div class="card-tags">
                                    <span class="tag">4K</span>
                                    <span class="tag">Cinematic</span>
                                    <span class="tag">60fps</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Pack
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Tool">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">Software</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Flowframes AI</h3>
                                <p class="card-meta">
                                    <i class="fas fa-microchip"></i> Tool
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 1.2k
                                </p>
                                <p class="card-description">Software AI per frame interpolation. Converti video da 30fps a 60fps o 120fps con qualità professionale.</p>
                                <div class="card-tags">
                                    <span class="tag">AI</span>
                                    <span class="tag">Frame Interpolation</span>
                                    <span class="tag">Gratis</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Tool
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Clip">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">HD</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Urban Street Footage</h3>
                                <p class="card-meta">
                                    <i class="fas fa-video"></i> 35 Clip
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 298
                                </p>
                                <p class="card-description">Riprese urbane e street con atmosfera notturna, ideali per edit trap, drill e hip-hop.</p>
                                <div class="card-tags">
                                    <span class="tag">Urban</span>
                                    <span class="tag">Night</span>
                                    <span class="tag">1080p</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Pack
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Plugin">
                                <div class="card-overlay">
                                    <span class="difficulty-badge advanced">Plugin</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">ReelSmart Motion Blur</h3>
                                <p class="card-meta">
                                    <i class="fas fa-cube"></i> Plugin AE
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 867
                                </p>
                                <p class="card-description">Plugin professionale per aggiungere motion blur realistico ai tuoi video e animazioni.</p>
                                <div class="card-tags">
                                    <span class="tag">Motion Blur</span>
                                    <span class="tag">After Effects</span>
                                    <span class="tag">Trial</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Trial
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Clip">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">4K</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Nature & Landscape Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-video"></i> 40 Clip
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 512
                                </p>
                                <p class="card-description">Riprese mozzafiato di natura, paesaggi e scenari naturali in altissima qualità.</p>
                                <div class="card-tags">
                                    <span class="tag">Nature</span>
                                    <span class="tag">4K</span>
                                    <span class="tag">60fps</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Pack
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Plugin">
                                <div class="card-overlay">
                                    <span class="difficulty-badge advanced">Plugin</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Twixtor Pro</h3>
                                <p class="card-meta">
                                    <i class="fas fa-cube"></i> Plugin AE
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 1.4k
                                </p>
                                <p class="card-description">Il miglior plugin per slow motion di qualità professionale con motion tracking avanzato.</p>
                                <div class="card-tags">
                                    <span class="tag">Slow Motion</span>
                                    <span class="tag">Time Remap</span>
                                    <span class="tag">Trial</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Trial
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