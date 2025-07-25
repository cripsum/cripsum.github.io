<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - supporto</title>
        <style>
            img {
                border-radius: 10px;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div style="max-width: 1200px; margin: auto; padding-top: 7rem" class="testobianco">
            <div class="privacy">
                <h1 class="text-center fadeup" style="padding-top: 3%; font-weight: bolder">Supporto</h1>
                <h3 class="text-center fadeup" style="padding-top: 10px">Assistenza Clienti</h3>
                <p class="fadeup">Il nostro team di assistenza clienti è a tua disposizione per aiutarti con qualsiasi domanda o problema tu possa avere riguardo ai nostri servizi.</p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Contattaci</h4>
                <p class="fadeup">Puoi contattare il nostro supporto tramite:</p>
                <ul class="fadeup">
                    <li><p>Email: sburra@cripsum.com</p></li>
                    <li><p>Telegram: @cripsum</p></li>
                    <li><p>Discord: @cripsum</p></li>
                    <li><p>Instagram: @cripsum</p></li>
                </ul>
                <h4 class="text-center fadeup" style="padding-top: 10px">Orari di apertura</h4>
                <p class="fadeup">Il nostro team di supporto è disponibile dal lunedì al venerdì, dalle 9:00 alle 18:00 (UTC +1).</p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Domande Frequenti</h4>
                <p class="fadeup">Visita la nostra sezione FAQ sul nostro sito web per trovare risposte alle domande più comuni.</p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Segnalazione di problemi</h4>
                <p class="fadeup">
                    Se riscontri un problema tecnico o hai bisogno di assistenza immediata, ti preghiamo di contattarci tramite email o telefono. Il nostro team farà del suo meglio per risolvere il
                    tuo problema il più rapidamente possibile.
                </p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="#" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
