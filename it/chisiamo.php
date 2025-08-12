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
        <title>Cripsum™ - chisiamo</title>
        <style>
            img {
                border-radius: 100px;
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="paginainterachisiamo testobianco" style="padding-top: 7rem; padding-bottom: 4rem;">
            <!-- Hero Section -->
            <div class="text-center mb-5">
            <h1 class="fadeup" style="font-size: 3rem; font-weight: 700; margin-bottom: 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Il Nostro Team 🚀
            </h1>
            <p class="fadeup lead" style="font-size: 1.2rem; color: #6c757d; max-width: 600px; margin: 0 auto;">
                Vuoi unirti a noi? Scrivi a <a href="mailto:sburra@cripsum.com" class="text-decoration-none" style="color: #667eea; font-weight: 600;">sburra@cripsum.com</a> 
                con la tua foto, nome e descrizione.
            </p>
            </div>

            <div class="container">
            <!-- Team Grid -->
            <div class="row g-4">
                <!-- Team Member 1 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center">
                    <img src="../img/cripsumchisiamo.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Cripsum" />
                    <div>
                        <h5 class="card-title mb-2">
                        <a href="../user/cripsum" class="text-decoration-none" style="color: #667eea; font-weight: 700;">cripsum</a>
                        </h5>
                        <p class="card-text text-muted mb-0">L'imperatore del Congo, è un editor fallito.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 2 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center">
                    <img src="../img/simonetussi.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Simone Tussi" />
                    <div>
                        <h5 class="card-title mb-2">
                        <a href="../user/simonetussi" class="text-decoration-none" style="color: #667eea; font-weight: 700;">simonetussi.ph</a>
                        </h5>
                        <p class="card-text text-muted mb-0">
                        Fotografo professionista su <a href="https://instagram.com/simonetussi.ph" class="text-decoration-none" style="color: #667eea;">Instagram</a> e 
                        <a href="https://tiktok.com/@simonetussi.ph" class="text-decoration-none" style="color: #667eea;">TikTok</a>.
                        </p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 3 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center">
                    <img src="../img/sahe.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Danebidev" />
                    <div>
                        <h5 class="card-title mb-2" style="color: #343a40; font-weight: 700;">danebidev</h5>
                        <p class="card-text text-muted mb-0">Game developer sempre in risparmio energetico. JavaScript >> Java.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 4 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center">
                    <img src="../img/ray.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Ray" />
                    <div>
                        <h5 class="card-title mb-2" style="color: #343a40; font-weight: 700;">Ray</h5>
                        <p class="card-text text-muted mb-0">Broke/Broken/Broker. Un trader che mangia cani e beve birra fino a stare male.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 5 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center">
                    <img src="../img/barandeep.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Barandeep" />
                    <div>
                        <h5 class="card-title mb-2" style="color: #343a40; font-weight: 700;">Barandeep</h5>
                        <p class="card-text text-muted mb-0">Xenon il gigante indiano, è il project manager.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 6 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center">
                    <img src="../img/samarpreet.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Scammarpreet" />
                    <div>
                        <h5 class="card-title mb-2" style="color: #343a40; font-weight: 700;">Scammarpreet</h5>
                        <p class="card-text text-muted mb-0">Money grabber, scammer, guru, doxer. Gambler professionista.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 7 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center"></div>
                    <img src="../img/tacos.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Tacos" />
                    <div>
                        <h5 class="card-title mb-2">
                        <a href="../user/tacos" class="text-decoration-none" style="color: #667eea; font-weight: 700;">Tacos</a>
                        </h5>
                        <p class="card-text text-muted mb-0">Anche conosciuto come 1nstxnct, è un pro player italiano di Brawl Stars.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 8 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center">
                    <img src="../img/cossu.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Cossu" />
                    <div>
                        <h5 class="card-title mb-2" style="color: #343a40; font-weight: 700;">Cossu</h5>
                        <p class="card-text text-muted mb-0">Lontrone spermatozoico (ama le lontre). Ama le frittate con la banana.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 9 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center"></div>
                    <img src="../img/photo_2023-11-14_17-21-10.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Zakator" />
                    <div>
                        <h5 class="card-title mb-2">
                        <a href="../user/zakator" class="text-decoration-none" style="color: #667eea; font-weight: 700;">Zakator</a>
                        </h5>
                        <p class="card-text text-muted mb-0">Grande ascoltatore di musica anime e phonk, è un hackerino fallito.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 10 -->
                <div class="col-lg-6 col-md-6 fadeup">
                <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                    <div class="card-body p-4 d-flex align-items-center"></div>
                    <img src="../img/salsina.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Xalx Andrea" />
                    <div>
                        <h5 class="card-title mb-2">
                        <a href="../user/salsina" class="text-decoration-none" style="color: #667eea; font-weight: 700;">Xalx Andrea</a>
                        </h5>
                        <p class="card-text text-muted mb-0">Il player più tossico di tutto Yokai Watch. Boh è anoressico.</p>
                    </div>
                    </div>
                </div>
                </div>

                <!-- Team Member 11 -->
                <div class="col-lg-6 col-md-6 fadeup">
                    <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                            <div class="card-body p-4 d-flex align-items-center">
                            <img src="../img/mabbon.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="Mabbon" />
                            <div>
                                <h5 class="card-title mb-2" style="color: #343a40; font-weight: 700;">Mabbon</h5>
                                <p class="card-text text-muted mb-0">Un ragazzo sfruttato e sottopagato. Forse perché è ne-</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Member 12 -->
                <div class="col-lg-6 col-md-6 fadeup">
                    <div class="card border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <div class="card-body p-4 d-flex align-items-center">
                            <img src="../img/lollolapulce.jpg" class="rounded-circle me-4" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #667eea;" alt="LolloLaPulce" />
                            <div>
                                <h5 class="card-title mb-2" style="color: #343a40; font-weight: 700;">LolloLaPulce</h5>
                                <p class="card-text text-muted mb-0">Addetto alla depressione. Grande giocatore di Minecraft.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


                <div class="text-center mt-5">
                    <div class="card border-0 shadow-lg mx-auto" style="max-width: 600px; border-radius: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body p-5">
                            <h3 class="text-white mb-3" style="font-weight: 700;">Unisciti al Team! 🌟</h3>
                            <p class="text-white-50 mb-4">Il prossimo potresti essere tu! Scrivi a sburra@cripsum.com per essere aggiunto al nostro fantastico team.</p>
                            <a href="mailto:sburra@cripsum.com" class="btn btn-light btn-lg px-4 py-2" style="border-radius: 50px; font-weight: 600;">
                            Contattaci Ora
                            </a>
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1) !important;
            }
            
            .fadeup {
            animation: fadeInUp 0.8s ease-out;
            }
            
            @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
