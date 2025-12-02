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
    <title>Cripsum™ - Download</title>
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
                            <i class="fas fa-download page-icon"></i>
                        </div>
                        <h1 class="page-title">Download</h1>
                        <p class="page-description">Software, plugin e strumenti essenziali per After Effects sempre aggiornati</p>
                        <div class="software-badge">
                            <i class="fab fa-adobe"></i> After Effects
                        </div>

                        <div class="filter-bar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Cerca software o plugin...">
                            </div>
                            <div class="filter-buttons">
                                <button class="filter-btn active">Tutti</button>
                                <button class="filter-btn">Plugin</button>
                                <button class="filter-btn">Script</button>
                                <button class="filter-btn">Software</button>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Plugin" alt="Download">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Gratis</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Motion Bro</h3>
                                <p class="card-meta">
                                    <i class="fas fa-cube"></i> Plugin
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 1.2k
                                </p>
                                <p class="card-description">Libreria completa di preset, template ed effetti pronti all'uso direttamente in After Effects.</p>
                                <div class="card-tags">
                                    <span class="tag">Preset</span>
                                    <span class="tag">Template</span>
                                    <span class="tag">Gratuito</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica ora
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Plugin" alt="Download">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Gratis</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Flow</h3>
                                <p class="card-meta">
                                    <i class="fas fa-cube"></i> Plugin
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 856
                                </p>
                                <p class="card-description">Crea transizioni fluide e animazioni motion graphics in pochi click con questo potente plugin.</p>
                                <div class="card-tags">
                                    <span class="tag">Transizioni</span>
                                    <span class="tag">Motion</span>
                                    <span class="tag">Gratuito</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica ora
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Script" alt="Download">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Gratis</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Animation Composer</h3>
                                <p class="card-meta">
                                    <i class="fas fa-code"></i> Plugin
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 2.1k
                                </p>
                                <p class="card-description">Oltre 100 effetti di transizione, preset ed animazioni preimpostate per velocizzare il workflow.</p>
                                <div class="card-tags">
                                    <span class="tag">Animazioni</span>
                                    <span class="tag">Effetti</span>
                                    <span class="tag">Gratuito</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica ora
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Plugin" alt="Download">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">Premium</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Saber</h3>
                                <p class="card-meta">
                                    <i class="fas fa-cube"></i> Plugin
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 1.8k
                                </p>
                                <p class="card-description">Plugin essenziale per creare effetti laser, spade laser e raggi di luce fotorealistici.</p>
                                <div class="card-tags">
                                    <span class="tag">VFX</span>
                                    <span class="tag">Luce</span>
                                    <span class="tag">Gratuito</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica ora
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Software" alt="Download">
                                <div class="card-overlay">
                                    <span class="difficulty-badge advanced">Trial</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Element 3D</h3>
                                <p class="card-meta">
                                    <i class="fas fa-cube"></i> Plugin
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 945
                                </p>
                                <p class="card-description">Importa e anima modelli 3D direttamente in After Effects con rendering GPU ultra veloce.</p>
                                <div class="card-tags">
                                    <span class="tag">3D</span>
                                    <span class="tag">Rendering</span>
                                    <span class="tag">Trial</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica trial
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Script" alt="Download">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">Gratis</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Duik Bassel</h3>
                                <p class="card-meta">
                                    <i class="fas fa-code"></i> Script
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 678
                                </p>
                                <p class="card-description">Strumento completo per il rigging e l'animazione di personaggi in After Effects.</p>
                                <div class="card-tags">
                                    <span class="tag">Rigging</span>
                                    <span class="tag">Character</span>
                                    <span class="tag">Gratuito</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica ora
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