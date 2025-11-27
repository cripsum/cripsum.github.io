<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../includes/head-import.php'; ?>
<title>Cripsum™ - privacy</title>
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
            <h1 class="text-center fadeup" style="padding-top: 3%; font-weight: bolder">Informativa sulla Privacy</h1>
            <p class="fadeup">
                Cripsum™ si impegna a proteggere la tua privacy. Questa informativa sulla privacy spiega come raccogliamo, utilizziamo, divulghiamo e proteggiamo le tue informazioni personali.
            </p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Informazioni raccolte</h4>
            <p class="fadeup">Raccogliamo diverse tipologie di informazioni, tra cui:</p>
            <ul class="fadeup">
                <li>
                    <p>Informazioni personali fornite dall'utente (nome, indirizzo email, ecc.).</p>
                </li>
                <li>
                    <p>Informazioni raccolte automaticamente (indirizzo IP, tipo di browser, ecc.).</p>
                </li>
            </ul>
            <h4 class="text-center fadeup" style="padding-top: 10px">Uso delle informazioni</h4>
            <p class="fadeup">Utilizziamo le tue informazioni per:</p>
            <ul class="fadeup">
                <li>
                    <p>Fornire e migliorare i nostri servizi.</p>
                </li>
                <li>
                    <p>Comunicare con te.</p>
                </li>
                <li>
                    <p>Personalizzare la tua esperienza.</p>
                </li>
            </ul>
            <h4 class="text-center fadeup" style="padding-top: 10px">Divulgazione delle informazioni</h4>
            <p class="fadeup">
                Non vendiamo, commerciamo o trasferiamo in altro modo le tue informazioni personali a terzi, eccetto nei casi necessari per adempiere ai nostri obblighi legali o proteggere i
                nostri diritti.
            </p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Protezione delle informazioni</h4>
            <p class="fadeup">Implementiamo una varietà di misure di sicurezza per mantenere la sicurezza delle tue informazioni personali.</p>
            <h4 class="text-center fadeup" style="padding-top: 10px">I tuoi diritti</h4>
            <p class="fadeup">
                Hai il diritto di accedere, correggere o cancellare le tue informazioni personali. Per esercitare questi diritti, contattaci tramite i dettagli forniti sul nostro sito.
            </p>
            <h4 class="text-center fadeup" style="padding-top: 10px">Contatti</h4>
            <p class="fadeup">Per ulteriori informazioni sulla nostra informativa sulla privacy, contattaci all'indirizzo email: privacy@cripsum.com.</p>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../js/modeChanger.js"></script>
</body>

</html>