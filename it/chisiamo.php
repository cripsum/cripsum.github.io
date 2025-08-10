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

        <div class="paginainterachisiamo testobianco" style="padding-top: 7rem">
            <p class="text-center fadeup" style="font-size: 30px; font-weight: bolder; margin-top: 20px">il nostro team di sviluppo 🤑</p>
            <p class="text-center fadeup" style="font-size: 15px; margin-top: 20px">
                vuoi far parte del nostro team di sviluppo? manda una e-mail a <a href="mailto:sburra@cripsum.com" class="linkbianco">sburra@cripsum.com</a> allegando immagine, nome e descrizione, e
                se vuoi, un username o un link social per i crediti.
            </p>
            <section class="container">
                <div class="row d-flex justify-content-around image-container">
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/cripsumchisiamo.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 1" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3"><a href="../user/cripsum" class="linkbianco">cripsum</a></h4>
                                <p style="font-size: 15px; margin-top: 3%">l'imperatore del Congo, è un editor fallito.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/simonetussi.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 2" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3"><a href="../user/simonetussi" class="linkbianco">simonetussi.ph</a></h4>
                                <p style="font-size: 15px; margin-top: 3%">
                                    seguite tutti simonetussi.ph su <a href="https://instagram.com/simonetussi.ph" class="linkbianco">instagram</a> e
                                    <a href="https://tiktok.com/@simonetussi.ph" class="linkbianco">tiktok</a> per degli scatti fantastici.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row d-flex justify-content-around image-container">
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/sahe.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 1" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">danebidev</h4>
                                <p style="font-size: 15px; margin-top: 3%">game developer sempre in risparmio energetico <br /><br />javascript >> java.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/ray.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 2" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">Ray</h4>
                                <p style="font-size: 15px; margin-top: 3%">
                                    Broke/Broken/Broker <br /><br />
                                    un trader che mangia cani e beve birra fino a stare male.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row d-flex justify-content-around image-container">
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/barandeep.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 1" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">Barandeep</h4>
                                <p style="font-size: 15px; margin-top: 3%">xenon il gigante indiano, è il project manager.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/samarpreet.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 2" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">Scammarpreet</h4>
                                <p style="font-size: 15px; margin-top: 3%">money grabber - scammer - guru - doxer <br /><br />gambler professionista</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row d-flex justify-content-around image-container">
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/tacos.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 1" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3"><a href="../user/tacos" class="linkbianco">Tacos</a></h4>
                                <p style="font-size: 15px; margin-top: 3%">anche conosciuto come 1nstxnct, è un pro player italiano di brawl stars</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/cossu.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 2" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">Cossu</h4>
                                <p style="font-size: 15px; margin-top: 3%">
                                    Lontrone spermatozoico (ama le lontre)
                                    <br /><br />
                                    ama le frittate con la banana.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row d-flex justify-content-around image-container">
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/photo_2023-11-14_17-21-10.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 1" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3"><a href="../user/zakator" class="linkbianco">Zakator</a></h4>
                                <p style="font-size: 15px; margin-top: 3%">grande ascoltatore di musica anime e phonk, è un hackerino fallito</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/salsina.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 2" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3"><a href="../user/salsina" class="linkbianco">Xalx Andrea</a></h4>
                                <p style="font-size: 15px; margin-top: 3%">
                                    Il player + tossico di tutto yokai watch
                                    <br /><br />
                                    boh è anoressico
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row d-flex justify-content-around image-container">
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/mabbon.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 1" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">Mabbon</h4>
                                <p style="font-size: 15px; margin-top: 3%">un ragazzo sfruttato e sottopagato. forse perchè è ne-</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/lollolapulce.jpg" class="mt-3 immaginechisiamo ombra" alt="Person 2" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">LolloLaPulce</h4>
                                <p style="font-size: 15px; margin-top: 3%">addetto alla depressione. grande giocatore di minecraft</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row d-flex justify-content-around image-container">
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/boh.png" class="mt-3 immaginechisiamo" alt="Person 1" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">?????</h4>
                                <p style="font-size: 15px; margin-top: 3%">
                                    il prossimo potresti essere tu! scrivi a <a href="mailto:sburra@cripsum.com" class="linkbianco">sburra@cripsum.com</a> per essere aggiunto!
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 fadeup">
                        <div class="person" style="margin-top: 10px; margin-bottom: 5%">
                            <img src="../img/boh.png" class="mt-3 immaginechisiamo" alt="Person 2" />
                            <div style="float: right; max-width: 60%; margin-top: 3%; padding-left: 3%">
                                <h4 class="nomichisiamo mt-3">?????</h4>
                                <p style="font-size: 15px; margin-top: 3%">
                                    il prossimo potresti essere tu! scrivi a <a href="mailto:sburra@cripsum.com" class="linkbianco">sburra@cripsum.com</a> per essere aggiunto!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../includes/footer.php'; ?>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
