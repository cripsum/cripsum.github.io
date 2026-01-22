<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
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
    <div style="max-width: 1200px; margin: auto; padding-top: 7rem; padding-bottom: 4rem;" class="testobianco">
        <div class="privacy">
            <h1 class="text-center fadeup" style="padding-top: 3%; font-weight: bolder">Linee guida della chat globale</h1>
            <p class="text-center fadeup">Accedendo alla chat, dichiari di aver letto e accettato queste regole. Violazioni possono portare a ban temporanei o permanenti.</p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Rispetta tutti</h4>
            <p class="text-center fadeup">Niente insulti, minacce o linguaggio offensivo.</p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Niente spam</h4>
            <p class="text-center fadeup">
                Evita messaggi ripetuti, pubblicità o link inutili.
            </p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Contenuti adatti a tutti</h4>
            <p class="text-center fadeup">
                Non postare contenuti violenti, sessuali o illegali.
            </p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Vietato impersonare altri</h4>
            <p class="text-center fadeup">
                Non fingere di essere un altro utente o un moderatore.
            </p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Usa il buon senso</h4>
            <p class="text-center fadeup">Comportati in modo civile e corretto.</p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Segui le indicazioni dei moderatori</h4>
            <p class="text-center fadeup">Le loro decisioni vanno rispettate.</p>
            <div class="mt-4 fadeup text-center ">
                <a href="global-chat" class="linkbianco">← Torna alla chat globale</a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    
</body>

</html>