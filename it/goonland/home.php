<?php

ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: accedi');
    exit();
}

if (isset($_SESSION['nsfw']) && $_SESSION['nsfw'] == 0) {
    header('Location: home');
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include '../../includes/head-import.php'; ?>
    <script src="/js/nomePagina.js"></script>
    <link rel="stylesheet" href="/css/style-goonland.css" />
    <title>GoonLand™ - Home</title>
  </head>
  <body>
        <?php include '../../includes/navbar.php'; ?>
        <?php include '../../includes/impostazioni.php'; ?>
        <div class="title fadeup" style="padding-top: 7rem;">
        <h1 style="font-weight:bolder;">Welcome to Goonland!</h1>
        </div>
        <div class="p1 fadeup">
        <h3 style="font-weight:bold;">Cos'è Goonland?</h3>
        <img class="img" src="/img/raspberry-chan16gb.png" alt="" />
        <p>
            Goonland è un progetto ideato da Zakator e Cripsum: uno spazio digitale unico nel suo genere, nato dalla volontà di creare un ambiente che fosse al tempo stesso provocatorio, giocoso e visivamente coinvolgente. Non si tratta solo di un sito, ma di un piccolo universo costruito per intrattenere, far riflettere e talvolta confondere in modo creativo e fuori dagli schemi.
        </p>
        <p>
            All'interno di Goonland troverai una raccolta di giochi interattivi, esperienze sperimentali e contenuti a tema, tutti sviluppati per incarnare l'estetica visionaria e spesso surreale del progetto. Ogni elemento del sito è pensato per immergere l'utente in un viaggio digitale dove nulla è davvero come sembra, e dove l'ironia si mescola con una sottile critica alla cultura dell'intrattenimento online.
        </p>
        </div>
        <hr class="goonhr fadeuphr" />
        <div class="p2 fadeup">
        <h3 style="font-weight:bold;">Cos'è il gooning?</h3>
            <img class="img2" src="/img/raspberry-chan8gb.png" alt="" />
        <p>
            Il termine "gooning" affonda le sue radici negli angoli più oscuri e assurdi di Internet, dove è nato per descrivere uno stato mentale ipnotico, quasi trance, indotto dalla ripetizione ossessiva di stimoli sensoriali, come immagini, suoni o contenuti digitali. È una condizione in cui l'attenzione viene completamente risucchiata, portando a un'esperienza psicologica intensa e stranamente appagante.
        </p>
        <p>
            In chiave ironica e satirica, Goonland prende il concetto di gooning e lo trasforma in una metafora dell’era digitale: un invito a lasciarsi assorbire — consapevolmente — dall’assurdo, dall’eccesso, dalla bellezza distorta di un flusso continuo di contenuti. È un modo per rappresentare il caos creativo del web e la nostra relazione con la tecnologia, l’intrattenimento e la perdita del tempo.
        </p>
        </div>
        <hr class="goonhr fadeuphr" />
        <div class="p3 fadeup">
        <h3 style="font-weight:bold;">Vi auguriamo tanto gooning!</h3>
        <p>
            Che tu sia un veterano della rete, cresciuto a pane e culture digitali underground, oppure un esploratore curioso alla ricerca di nuovi territori dell’assurdo, Goonland ti dà il benvenuto. Qui puoi perderti, ritrovarti o semplicemente lasciarti trasportare da un’esperienza fuori dal comune. Mettiti comodo, dimentica le regole per un po’ e preparati a entrare in un mondo che non chiede di essere compreso, ma semplicemente vissuto. Buon gooning!
        </p>
        </div>

        <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="crediti mb-2" style="color: #d63384;">Si ringrazia <a href="/user/zakator" class="arcobaleno testo-arcobaleno" style="font-weight:bolder;">Zakator</a> per il contributo creativo e tecnico nella realizzazione di GoonLand e delle relative funzionalità di gioco</p>
            <p class="mb-1 testonero">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="../privacy" class="linknero">Privacy</a></li>
                <li class="list-inline-item"><a href="../tos" class="linknero">Termini</a></li>
                <li class="list-inline-item"><a href="../supporto" class="linknero">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="/js/modeChanger.js"></script>
  </body>
</html>
