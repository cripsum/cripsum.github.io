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
        <title>Cripsum™ - tos</title>
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
                <h1 class="text-center fadeup" style="padding-top: 3%; font-weight: bolder">Termini e Condizioni</h1>
                <p class="fadeup">Benvenuto su Cripsum™. Utilizzando i nostri servizi, accetti i seguenti termini e condizioni.</p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Uso dei servizi</h4>
                <p class="fadeup">Devi utilizzare i nostri servizi solo per scopi legali e in conformità con tutte le leggi applicabili. È vietato utilizzare i nostri servizi per:</p>
                <ul class="fadeup">
                    <li><p>Qualsiasi attività illegale o non autorizzata.</p></li>
                    <li><p>Inviare spam o contenuti dannosi.</p></li>
                </ul>
                <h4 class="text-center fadeup" style="padding-top: 10px">Account dell'utente</h4>
                <p class="fadeup">
                    Se crei un account su Cripsum™, sei responsabile del mantenimento della riservatezza delle tue informazioni di accesso e di tutte le attività che avvengono sotto il tuo account.
                </p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Proprietà intellettuale</h4>
                <p class="fadeup">
                    Tutti i contenuti presenti su Cripsum™, inclusi testi, immagini, loghi e software, sono di proprietà di Cripsum™ o dei suoi licenziatari e sono protetti dalle leggi sul diritto
                    d'autore.
                </p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Limitazione di responsabilità</h4>
                <p class="fadeup">Cripsum™ non sarà responsabile per eventuali danni diretti, indiretti, incidentali o consequenziali derivanti dall'uso dei nostri servizi.</p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Modifiche ai termini</h4>
                <p class="fadeup">
                    Ci riserviamo il diritto di modificare questi termini e condizioni in qualsiasi momento. Le modifiche saranno pubblicate sul nostro sito web e, se significative, ti informeremo
                    tramite email.
                </p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Contatti</h4>
                <p class="fadeup">Per domande riguardanti questi termini e condizioni, contattaci all'indirizzo email: tos@cripsum.com.</p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="#" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
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
