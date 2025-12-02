<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Editing™ - Home</title>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="container-fluid py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold text-primary">Welcome to Editing™</h1>
                    <p class="lead text-muted">Your ultimate destination for creative editing resources</p>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                                </div>
                                <h5 class="card-title">Tutorials</h5>
                                <p class="card-text text-muted">Learn editing techniques with our comprehensive guides and step-by-step tutorials.</p>
                                <a href="#" class="btn btn-outline-primary">Explore Tutorials</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-download fa-3x text-success"></i>
                                </div>
                                <h5 class="card-title">Downloads</h5>
                                <p class="card-text text-muted">Get the latest programs, plugins, and tools for professional editing.</p>
                                <a href="#" class="btn btn-outline-success">Download Now</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-palette fa-3x text-warning"></i>
                                </div>
                                <h5 class="card-title">Presets</h5>
                                <p class="card-text text-muted">Enhance your projects with our curated collection of editing presets.</p>
                                <a href="#" class="btn btn-outline-warning">Browse Presets</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-layer-group fa-3x text-info"></i>
                                </div>
                                <h5 class="card-title">Overlays</h5>
                                <p class="card-text text-muted">Add visual flair to your content with our premium overlay collection.</p>
                                <a href="#" class="btn btn-outline-info">View Overlays</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-music fa-3x text-danger"></i>
                                </div>
                                <h5 class="card-title">Audio Assets</h5>
                                <p class="card-text text-muted">Discover high-quality sound effects and music tracks for your projects.</p>
                                <a href="#" class="btn btn-outline-danger">Listen Now</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-file-archive fa-3x text-secondary"></i>
                                </div>
                                <h5 class="card-title">Project Files</h5>
                                <p class="card-text text-muted">Download complete project files and templates to jumpstart your creativity.</p>
                                <a href="#" class="btn btn-outline-secondary">Get Projects</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-5">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <h3 class="mb-3">Ready to elevate your editing game?</h3>
                            <p class="text-muted mb-4">Join thousands of creators who trust Editing™ for their creative resources. Whether you're a beginner or a professional, we have everything you need to bring your vision to life.</p>
                            <button class="btn btn-primary btn-lg">Get Started</button>
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