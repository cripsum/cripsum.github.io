<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>

<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - negozio</title>
        <style>
            img {
                border-radius: 10px;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>
        
        <div class="shop-section" style="margin-top: 7rem; padding-bottom: 7rem">
            <div class="shop-grid">
                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/4090.jpg" class="card-img" alt="RTX 4090" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">RTX 4090 - 2499,99€</a>
                            </h5>
                            <p class="card-description">troppo potente anche per il gamings</p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/iphone20.jpg" class="card-img" alt="Iphone 20" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Iphone 20 - 2179,99€</a>
                            </h5>
                            <p class="card-description">il futuro, ottimo telefono, peccato per l'os</p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/indica.jpg" class="card-img" alt="tua madre" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                    <div class="overlay-badge">Sconto 40%!</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">tua madre - 2,49€</a>
                            </h5>
                            <p class="card-description">se acquistate entro il 2025 sconto del 40%</p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/s30.jpg" class="card-img" alt="Samsung galaxy s30" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Samsung galaxy s30 - 1599,99€</a>
                            </h5>
                            <p class="card-description">
                                16 gb ram - 1tb memoria - batteria: 7000mAh<br />
                                display 4k 144hz - zoom ottico x100 con <br />
                                sensore principale da 12482345 MP
                            </p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/ps6.jpg" class="card-img" alt="Ps6" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Ps6 - 849,49€</a>
                            </h5>
                            <p class="card-description">
                                con ps6 potrete giocare a gta5 <br />
                                remastered in 8k 120fps
                            </p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/monitor540.jpg" class="card-img" alt="Monitor 540hz" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Monitor 540hz - 949,69€</a>
                            </h5>
                            <p class="card-description">fluidità estrema (completamente inutile)</p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/renegade.jpg" class="card-img" alt="renegade raider" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">renegade raider - 429,99€</a>
                            </h5>
                            <p class="card-description">
                                la skin più og di tutte, ma anche <br />
                                la più costosa
                            </p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/tavoletta.jpg" class="card-img" alt="Tavoletta grafica" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Tavoletta grafica - 119,89€</a>
                            </h5>
                            <p class="card-description">accessori per osu</p>
                        </div>
                    </div>
                </div>

                <div class="shop-item fadeup" onclick="window.location.href='checkout'">
                    <div class="shop-card h-100">
                        <div class="card-header">
                            <img src="../img/tastiera2tasti.jpg" class="card-img" alt="tastiera per osu" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">tastiera per osu - 44,99€</a>
                            </h5>
                            <p class="card-description">accessori per osu</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .shop-section {
            max-width: 1400px;
            margin: 2rem auto 0;
            padding: 0 1rem;
        }

        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
        }

        .shop-item {
            position: relative;
            transform: translateY(0);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .shop-card {
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .shop-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(100, 200, 255, 0.05) 0%, 
                rgba(255, 100, 200, 0.03) 50%,
                rgba(100, 255, 150, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 1;
            border-radius: 24px;
        }

        .shop-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.4),
                0 0 40px rgba(100, 200, 255, 0.1);
            border-color: rgba(100, 200, 255, 0.2);
        }

        .shop-card:hover::before {
            opacity: 1;
        }

        .card-header {
            position: relative;
            overflow: hidden;
            height: 280px;
            background: linear-gradient(135deg, 
                rgba(30, 32, 42, 0.8) 0%, 
                rgba(40, 45, 60, 0.8) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .card-img {
            width: 220px;
            height: 220px;
            object-fit: cover;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            filter: brightness(0.9) contrast(1.1) saturate(1.2);
            border-radius: 20px;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .shop-card:hover .card-img {
            transform: scale(1.08);
            filter: brightness(1) contrast(1.2) saturate(1.4);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(0,0,0,0.7), 
                rgba(30, 32, 42, 0.8),
                rgba(0,0,0,0.6));
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            backdrop-filter: blur(4px);
        }

        .shop-card:hover .card-overlay {
            opacity: 1;
        }

        .overlay-content {
            text-align: center;
            color: white;
            transform: translateY(15px) scale(0.9);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .shop-card:hover .overlay-content {
            transform: translateY(0) scale(1);
        }

        .overlay-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #64c8ff, #ff64c8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4));
        }

        .overlay-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            background: linear-gradient(135deg, #ff6b6b, #ffa726);
            color: white;
            box-shadow: 
                0 4px 12px rgba(255, 107, 107, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: inline-block;
            margin-top: 8px;
        }

        .card-body {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.06), 
                rgba(255, 255, 255, 0.03));
            position: relative;
            z-index: 2;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .card-title {
            margin-bottom: 1rem;
            font-size: 1.4rem;
            font-weight: 600;
            text-align: center;
        }

        .card-title a {
            background: linear-gradient(135deg, #ffffff 0%, #e8e8e8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            display: inline-block;
        }

        .card-title a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, #64c8ff, #ff64c8);
            transform: scaleX(0);
            transition: transform 0.3s ease;
            border-radius: 1px;
        }

        .card-title a:hover::after {
            transform: scaleX(1);
        }

        .card-title a:hover {
            background: linear-gradient(135deg, #64c8ff 0%, #ff64c8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-description {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            line-height: 1.6;
            font-weight: 400;
            text-align: center;
            margin: 0;
        }

        .fadeup {
            animation: fadeUp 0.8s ease forwards;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (min-width: 1200px) {
            .shop-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2.5rem;
            }
        }

        @media (min-width: 992px) and (max-width: 1199px) {
            .shop-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .shop-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
            
            .card-header {
                height: 240px;
            }
            
            .card-img {
                width: 180px;
                height: 180px;
            }
            
            .card-body {
                padding: 1.5rem 1.25rem;
            }
        }

        @media (max-width: 767px) {
            .shop-section {
                padding: 0 0.5rem;
            }

            .shop-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem 0;
            }

            .card-header {
                height: 220px;
            }

            .card-img {
                width: 160px;
                height: 160px;
            }

            .card-body {
                padding: 1.5rem 1.25rem;
            }
            
            .card-title {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .shop-grid {
                gap: 1.25rem;
                grid-template-columns: minmax(280px, 1fr);
            }

            .card-header {
                height: 200px;
            }

            .card-img {
                width: 140px;
                height: 140px;
            }
            
            .card-body {
                padding: 1.25rem 1rem;
            }

            .overlay-icon {
                font-size: 2.5rem;
            }
        }
        </style>
        
        <?php include '../includes/footer.php'; ?>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
