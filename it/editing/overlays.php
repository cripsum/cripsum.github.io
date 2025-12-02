<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <link rel="stylesheet" href="../../css/editing.css">
    <link rel="stylesheet" href="../../css/editing-pages.css">
    <title>Cripsum™ - Overlay</title>
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
                            <i class="fas fa-layer-group page-icon"></i>
                        </div>
                        <h1 class="page-title">Overlay</h1>
                        <p class="page-description">Effetti visivi premium e overlay di alta qualità per arricchire i tuoi contenuti creativi</p>
                        <div class="software-badge">
                            <i class="fas fa-magic"></i> VFX Assets
                        </div>

                        <div class="filter-bar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Cerca overlay...">
                            </div>
                            <div class="filter-buttons">
                                <button class="filter-btn active">Tutti</button>
                                <button class="filter-btn">Glitch</button>
                                <button class="filter-btn">Light Leaks</button>
                                <button class="filter-btn">Particelle</button>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <div class="content-card">
                            <div class="card-thumbnail">
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Glitch+Overlay" alt="Overlay">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">4K</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Glitch Effect Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-layer-group"></i> 30 Overlay
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 678
                                </p>
                                <p class="card-description">Collezione di 30 overlay glitch in 4K con canale alpha, perfetti per effetti digitali e cyberpunk.</p>
                                <div class="card-tags">
                                    <span class="tag">Glitch</span>
                                    <span class="tag">Alpha Channel</span>
                                    <span class="tag">4K</span>
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
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Light+Leaks" alt="Overlay">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">HD</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Cinematic Light Leaks</h3>
                                <p class="card-meta">
                                    <i class="fas fa-layer-group"></i> 45 Overlay
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 891
                                </p>
                                <p class="card-description">Light leaks cinematografici per aggiungere atmosfera e calore ai tuoi video.</p>
                                <div class="card-tags">
                                    <span class="tag">Light Leaks</span>
                                    <span class="tag">Cinematic</span>
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
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=VHS+Overlay" alt="Overlay">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">HD</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">VHS & Retro Pack</h3>
                                <p class="card-meta">
                                    <i class="fas fa-layer-group"></i> 25 Overlay
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 534
                                </p>
                                <p class="card-description">Effetti VHS vintage, rumore analogico e distorsioni per uno stile retrò autentico.</p>
                                <div class="card-tags">
                                    <span class="tag">VHS</span>
                                    <span class="tag">Retro</span>
                                    <span class="tag">Vintage</span>
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
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Particle+FX" alt="Overlay">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">4K</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Particle Effects HD</h3>
                                <p class="card-meta">
                                    <i class="fas fa-layer-group"></i> 40 Overlay
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 723
                                </p>
                                <p class="card-description">Particelle, scintille, polvere e effetti atmosferici in alta risoluzione con alpha channel.</p>
                                <div class="card-tags">
                                    <span class="tag">Particelle</span>
                                    <span class="tag">Alpha</span>
                                    <span class="tag">4K</span>
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
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Smoke+FX" alt="Overlay">
                                <div class="card-overlay">
                                    <span class="difficulty-badge intermediate">4K</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Smoke & Fog Collection</h3>
                                <p class="card-meta">
                                    <i class="fas fa-layer-group"></i> 35 Overlay
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 612
                                </p>
                                <p class="card-description">Effetti fumo e nebbia realistici perfetti per aggiungere profondità e atmosfera.</p>
                                <div class="card-tags">
                                    <span class="tag">Fumo</span>
                                    <span class="tag">Nebbia</span>
                                    <span class="tag">4K</span>
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
                                <img src="https://via.placeholder.com/400x250/262626/d4d4d4?text=Film+Grain" alt="Overlay">
                                <div class="card-overlay">
                                    <span class="difficulty-badge beginner">HD</span>
                                </div>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Film Grain & Texture</h3>
                                <p class="card-meta">
                                    <i class="fas fa-layer-group"></i> 20 Overlay
                                    <span class="divider">•</span>
                                    <i class="fas fa-download"></i> 445
                                </p>
                                <p class="card-description">Texture di pellicola cinematografica e grain per un look analogico professionale.</p>
                                <div class="card-tags">
                                    <span class="tag">Film Grain</span>
                                    <span class="tag">Texture</span>
                                    <span class="tag">Cinematic</span>
                                </div>
                                <div class="card-actions">
                                    <a href="#" class="btn-primary full-width">
                                        <i class="fas fa-download"></i> Scarica Pack
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