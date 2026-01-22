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
    <title>Cripsum™ - Progetti</title>
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
                            <i class="fas fa-file-archive page-icon"></i>
                        </div>
                        <h1 class="page-title">Progetti</h1>
                        <p class="page-description">File di progetto completi e template pronti all'uso per accelerare il tuo processo creativo</p>
                        <!-- <div class="software-badge">
                            <i class="fab fa-adobe"></i> After Effects
                        </div> -->

                        <div class="filter-bar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Cerca progetti...">
                            </div>
                            <div class="filter-buttons">
                                <button class="filter-btn active">Tutti</button>
                                <button class="filter-btn">Intro</button>
                                <button class="filter-btn">Transition</button>
                                <button class="filter-btn">Lower Thirds</button>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Project">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Template</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Modern Intro Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-file-video"></i> 10 Template
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 945
                                </p>
                                <p class="card-description">Collezione di 10 intro moderne e dinamiche completamente personalizzabili in After Effects.</p>
                                <div class="card-tags">
                                    <span class="tag">Intro</span>
                                    <span class="tag">Logo Reveal</span>
                                    <span class="tag">AE 2023+</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Progetto
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Project">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">Template</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Smooth Transitions Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-file-video"></i> 25 Transizioni
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 1.3k
                                </p>
                                <p class="card-description">25 transizioni fluide e creative pronte all'uso. Drag & drop nel tuo progetto.</p>
                                <div class="card-tags">
                                    <span class="tag">Transizioni</span>
                                    <span class="tag">Preset</span>
                                    <span class="tag">AE 2022+</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Progetto
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Project">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Template</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Lower Thirds Collection</h3>
                                <p class="card-meta">
                                    <i class="fas fa-file-video"></i> 15 Template
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 678
                                </p>
                                <p class="card-description">Lower thirds professionali e animati per interviste, presentazioni e contenuti video.</p>
                                <div class="card-tags">
                                    <span class="tag">Lower Thirds</span>
                                    <span class="tag">Text Animation</span>
                                    <span class="tag">AE 2023+</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Progetto
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Project">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">Template</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Kinetic Typography Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-file-video"></i> 20 Template
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 823
                                </p>
                                <p class="card-description">Animazioni di testo dinamiche e moderne perfette per titoli, citazioni e call-to-action.</p>
                                <div class="card-tags">
                                    <span class="tag">Typography</span>
                                    <span class="tag">Kinetic</span>
                                    <span class="tag">AE 2022+</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Progetto
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Project">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Template</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Social Media Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-file-video"></i> 30 Template
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 1.5k
                                </p>
                                <p class="card-description">Template ottimizzati per Instagram, TikTok e YouTube. Stories, reels e shorts pronti all'uso.</p>
                                <div class="card-tags">
                                    <span class="tag">Social Media</span>
                                    <span class="tag">Vertical</span>
                                    <span class="tag">AE 2023+</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Progetto
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="/img/eiopago.jpg" alt="Project">
                                <div class="card-overlay">
                                    <span class="difficulty-badge advanced">Template</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">VFX Starter Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-file-video"></i> 12 Progetti
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 567
                                </p>
                                <p class="card-description">Progetti VFX completi con spiegazioni. Impara le tecniche mentre crei effetti incredibili.</p>
                                <div class="card-tags">
                                    <span class="tag">VFX</span>
                                    <span class="tag">Tutorial Project</span>
                                    <span class="tag">AE 2022+</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Progetto
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