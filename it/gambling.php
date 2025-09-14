<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per poter fare 🤑gambling🤑 devi essere loggato";

    header('Location: accedi');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - gambling</title>
        <style>
            img {
                border-radius: 10px;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div style="padding-top: 7rem; padding-bottom: 4rem;" class="testobianco">
            <div id="achievement-popup" class="popup">
                <img id="popup-image" src="" alt="Achievement" />
                <div>
                    <h3 id="popup-title"></h3>
                    <p id="popup-description"></p>
                </div>
            </div>
            <div class="account-bar fadeup" id="account-bar" style="padding-top: 1%; padding-bottom: 1%; margin: auto">
                <span class="" style="padding-left: 25px; font-weight: bold">Utente: </span>
                <span class="account-name" style="padding-left: 10px; padding-right: 10px"><?php echo $_SESSION["username"]?></span>
                <div style="padding-left: 25px" class="">
                    <span class="" style="font-weight: bold">Saldo: </span>
                    <span class="account-balance" style="padding-left: 10px">$100</span>
                </div>
                <input type="number" class="form-control inputricarica" style="margin-left: 25px; max-width: 200px; margin-top: 10px" placeholder="inserisci denaro" aria-label="Last name" />
                <button class="btn btn-secondary bottone" style="width: 150px; margin-left: 25px; margin-top: 5px" onclick="ricaricasaldo();">Ricarica</button>

                <p class="errorericarica" style="color: red; margin-top: 3px; margin-left: 25px"></p>
            </div>
            <div style="max-width: 1200px; margin: auto">
                <div id="slot-machine" class="d-flex justify-content-center image-container" style="padding-top: 3%; max-width: 80%; margin: auto">
                    <div class="slot fadeup" style="margin-left: 2%; margin-right: 2%; margin-top: 20px">
                        <img src="../img/cripsumchisiamo.jpg" class="bordobianco" alt="Image 1" />
                    </div>
                    <div class="slot fadeup" style="margin-left: 2%; margin-right: 2%; margin-top: 20px">
                        <img src="../img/barandeep.jpg" class="bordobianco" alt="Image 2" />
                    </div>
                    <div class="slot fadeup" style="margin-left: 2%; margin-right: 2%; margin-top: 20px">
                        <img src="../img/abdul.jpg" class="bordobianco" alt="Image 3" />
                    </div>
                </div>

                <div class="button-container fadeup" style="text-align: center; margin-top: 3%">
                    <button class="btn btn-secondary bottone" id="spin-btn" onclick="spin()">Spin</button>
                </div>
                <p class="text-center erroresoldi" style="margin-top: 3%; color: red"></p>
                <p class="text-center" id="risultato" style="margin-top: 3%"></p>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
        <script src="../js/unlockAchievement-it.js"></script>
        <script>
            let saldoMonitorInterval = null;

            function ricaricasaldo() {
                document.getElementsByClassName("errorericarica")[0].textContent = "";
                var inputMoney = document.getElementsByClassName("inputricarica")[0].value;
                if (inputMoney !== "" && inputMoney !== "e") {
                    var currentMoney = document.getElementsByClassName("account-balance")[0].textContent;
                    currentMoney = parseInt(currentMoney.substring(1)); 
                    var newMoney = currentMoney + parseInt(inputMoney);
                    document.getElementsByClassName("account-balance")[0].textContent = "$" + newMoney;
                    monitorSaldo();
                    document.getElementsByClassName("inputricarica")[0].value = "";
                } else {
                    document.getElementsByClassName("errorericarica")[0].textContent = "Il campo non può essere vuoto";
                    return;
                }
            }

            function spin() {
                var spinBtn = document.getElementById("spin-btn");
                var money = document.getElementsByClassName("account-balance")[0].textContent;
                money = parseInt(money.substring(1));

                if (money >= 10) {
                    money -= 10;
                    document.getElementsByClassName("account-balance")[0].textContent = "$" + money;
                    monitorSaldo(); 
                } else {
                    document.getElementsByClassName("erroresoldi")[0].textContent = "❌ Saldo insufficiente! Serve almeno $10";
                    return;
                }

                spinBtn.disabled = true;
                spinBtn.textContent = "⏳ SPINNING...";

                document.getElementById("risultato").textContent = "";
                document.getElementsByClassName("erroresoldi")[0].textContent = "";
                var slots = document.getElementsByClassName("slot");

                var randomIndexes = [];
                for (var i = 0; i < slots.length; i++) {
                    var randomIndex = Math.floor(Math.random() * 9) + 1;
                    randomIndexes.push(randomIndex);
                }

                for (var i = 0; i < slots.length; i++) {
                    slots[i].getElementsByTagName("img")[0].src = "../img/slott" + randomIndexes[i] + ".jpg";
                }

                var startTime = Date.now();
                var interval = setInterval(function () {
                    randomIndexes = [];
                    for (var i = 0; i < slots.length; i++) {
                        var randomIndex = Math.floor(Math.random() * 9) + 1;
                        randomIndexes.push(randomIndex);
                    }

                    for (var i = 0; i < slots.length; i++) {
                        slots[i].getElementsByTagName("img")[0].src = "../img/slott" + randomIndexes[i] + ".jpg";
                    }

                    if (Date.now() - startTime >= 5000) {
                        if (randomIndexes[0] === randomIndexes[1] && randomIndexes[1] === randomIndexes[2]) {
                            document.getElementById("risultato").textContent = "🎉 JACKPOT! HAI VINTO $1000! 🎉";
                            document.getElementById("risultato").style.color = "#28a745";
                            money += 1000; 
                            document.getElementsByClassName("account-balance")[0].textContent = "$" + money; 
                            unlockAchievement(3);
                        } else {
                            document.getElementById("risultato").textContent = "💸 Hai perso scemo! Riprova la fortuna!";
                            document.getElementById("risultato").style.color = "#dc3545";
                        }
                        
                        spinBtn.disabled = false;
                        spinBtn.textContent = "🎰 SPIN!";
                        clearInterval(interval);
                    }
                }, 100);
            }

            function monitorSaldo() {
                if (saldoMonitorInterval) return; 

                let startTime = Date.now();
                saldoMonitorInterval = setInterval(function () {
                    let money = parseInt(document.getElementsByClassName("account-balance")[0].textContent.substring(1));

                    if (money < 10) {
                        unlockAchievement(11);
                        clearInterval(saldoMonitorInterval);
                        saldoMonitorInterval = null;
                    }

                    if (Date.now() - startTime >= 60000) {
                        clearInterval(saldoMonitorInterval);
                        saldoMonitorInterval = null; 
                    }
                }, 1000); 
            }

        </script>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
