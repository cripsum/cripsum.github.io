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
    <title>Editing™ - Home</title>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="editing-section">
        <div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="editing-content">
                        <h1 class="editing-title">Benvenuto su Editing™</h1>
                        <p class="editing-subtitle">La piattaforma completa per i tuoi progetti creativi</p>
                    </div>

                    <div class="resources-grid">
                        <div class="resource-card">
                            <div class="resource-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h3 class="resource-title">Tutorial</h3>
                            <p class="resource-description">Guide dettagliate e tutorial passo-passo per padroneggiare le tecniche di editing più avanzate.</p>
                            <a href="#" class="resource-link">
                                <span>Esplora i tutorial</span>
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
                                <i class="fas fa-palette"></i>
                            </div>
                            <h3 class="resource-title">Preset</h3>
                            <p class="resource-description">Collezione curata di preset professionali per dare ai tuoi progetti uno stile unico e distintivo.</p>
                            <a href="#" class="resource-link warning">
                                <span>Scopri i preset</span>
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
                            <p class="resource-description">Libreria completa di effetti sonori e tracce musicali royalty-free per i tuoi progetti.</p>
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
                            <h2 class="cta-title">Porta il tuo editing al livello successivo</h2>
                            <p class="cta-description">Unisciti a migliaia di creator che si affidano a Editing™ per le loro risorse creative. Che tu sia alle prime armi o un professionista esperto, abbiamo tutto ciò che ti serve per trasformare le tue idee in realtà.</p>
                            <button class="cta-button">Inizia ora</button>
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