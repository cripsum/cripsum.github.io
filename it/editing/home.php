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
    <title>Cripsum™ - Editing Home</title>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="editing-section">
        <div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="editing-content">
                        <h1 class="editing-title">Benvenuto su Cripsum™</h1>
                        <p class="editing-subtitle">La piattaforma completa per i tuoi progetti creativi</p>
                        <div class="free-banner">
                            <p class="free-text"><i class="fas fa-gift"></i> E la cosa migliore? È tutto completamente gratuito! :P</p>
                        </div>
                    </div>

                    <div class="resources-grid">
                        <div class="resource-card">
                            <div class="resource-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <h3 class="resource-title">Tutorial & Preset</h3>
                            <p class="resource-description">Guide complete con preset professionali inclusi. Impara le tecniche avanzate e applica subito gli effetti ai tuoi progetti.</p>
                            <a href="#" class="resource-link">
                                <span>Inizia ad imparare</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="resource-card">
                            <div class="resource-icon success">
                                <i class="fas fa-download"></i>
                            </div>
                            <h3 class="resource-title">Download</h3>
                            <p class="resource-description">Software, plugin e strumenti professionali sempre aggiornati per il tuo workflow creativo.</p>
                            <a href="#" class="resource-link success">
                                <span>Scarica ora</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="resource-card">
                            <div class="resource-icon warning">
                                <i class="fas fa-film"></i>
                            </div>
                            <h3 class="resource-title">Clip & Flowframe</h3>
                            <p class="resource-description">Materiale video di qualità e tool per la fluidità dei frame. Tutto ciò che serve per creare edit professionali.</p>
                            <a href="#" class="resource-link warning">
                                <span>Scopri le risorse</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="resource-card">
                            <div class="resource-icon info">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <h3 class="resource-title">Overlay</h3>
                            <p class="resource-description">Effetti visivi premium e overlay di alta qualità per arricchire i tuoi contenuti creativi.</p>
                            <a href="#" class="resource-link info">
                                <span>Visualizza overlay</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="resource-card">
                            <div class="resource-icon danger">
                                <i class="fas fa-music"></i>
                            </div>
                            <h3 class="resource-title">Audio</h3>
                            <p class="resource-description">Libreria completa di effetti sonori e tracce musicali per i tuoi progetti.</p>
                            <a href="#" class="resource-link danger">
                                <span>Ascolta ora</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="resource-card">
                            <div class="resource-icon secondary">
                                <i class="fas fa-file-archive"></i>
                            </div>
                            <h3 class="resource-title">Progetti</h3>
                            <p class="resource-description">File di progetto completi e template pronti all'uso per accelerare il tuo processo creativo.</p>
                            <a href="#" class="resource-link secondary">
                                <span>Ottieni progetti</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="cta-section">
                        <div class="cta-content">
                            <h2 class="cta-title">Un progetto fatto con passione</h2>
                            <p class="cta-description">Questo è un piccolo progetto personale creato con l'idea di condividere risorse utili per l'editing. Non sono un esperto, ma spero che possa essere d'aiuto a chi come me ama sperimentare e imparare cose nuove nel mondo creativo.</p>
                            <button class="cta-button">Esplora</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../../js/modeChanger.js"></script>
</body>

</html>