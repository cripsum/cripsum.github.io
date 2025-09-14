<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per poter fare 🤑GAMBLING🤑 devi essere loggato";

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

        <div class="gambling-container testobianco">
            <div id="achievement-popup" class="popup">
                <img id="popup-image" src="" alt="Achievement" />
                <div>
                    <h3 id="popup-title"></h3>
                    <p id="popup-description"></p>
                </div>
            </div>
            
            <h1 class="chisiamo-title fadeup">🎰 Gambling 🎰</h1>
            
            <div class="fadeup">
                <strong>💰 Come funziona:</strong><br>
                Ogni spin costa <strong>$10</strong> • Vincendo ottieni <strong>$1000</strong><br>
                Allinea 3 foto uguali per vincere il jackpot!
            </div>
            
            <div class="account-bar fadeup" id="account-bar" style="padding: 1.5rem; margin: 2rem auto;">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <span style="font-weight: bold">👤 Utente: </span>
                        <span class="account-name"><?php echo $_SESSION["username"]?></span>
                    </div>
                    <div class="col-md-3">
                        <span style="font-weight: bold">💰 Saldo: </span>
                        <span class="account-balance">$100</span>
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control inputricarica" max="10000" placeholder="Max $10,000" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-light bottone" onclick="ricaricasaldo();">💳 Ricarica</button>
                    </div>
                </div>
                <p class="errorericarica text-center" style="color: #ffebee; margin-top: 10px;"></p>
            </div>
            
            <div class="slot-machine-wrapper fadeup">
                <div id="slot-machine" class="d-flex justify-content-center image-container" style="margin-bottom: 2rem;">
                    <div class="slot" style="margin: 0 15px;">
                        <img src="../img/cripsumchisiamo.jpg" class="bordobianco" alt="Image 1" style="width: 150px; height: 150px; object-fit: cover;" />
                    </div>
                    <div class="slot" style="margin: 0 15px;">
                        <img src="../img/barandeep.jpg" class="bordobianco" alt="Image 2" style="width: 150px; height: 150px; object-fit: cover;" />
                    </div>
                    <div class="slot" style="margin: 0 15px;">
                        <img src="../img/abdul.jpg" class="bordobianco" alt="Image 3" style="width: 150px; height: 150px; object-fit: cover;" />
                    </div>
                </div>

                <div class="button-container" style="text-align: center;">
                    <button id="spin-btn" class="spin-button" onclick="spin()">🎰 SPIN!</button>
                </div>
                
                <p class="text-center erroresoldi" style="margin-top: 1rem; color: #dc3545; font-weight: bold;"></p>
                <p class="text-center" id="risultato" style="margin-top: 1rem; font-size: 1.3rem; font-weight: bold;"></p>
            </div>
        </div>
        
        <?php include '../includes/footer.php'; ?>
        <script src="../js/unlockAchievement-it.js"></script>
        <script>
            let saldoMonitorInterval = null;

            function ricaricasaldo() {
                document.getElementsByClassName("errorericarica")[0].textContent = "";
                var inputMoney = document.getElementsByClassName("inputricarica")[0].value;
                
                if (inputMoney !== "" && inputMoney !== "e" && !isNaN(inputMoney)) {
                    inputMoney = parseInt(inputMoney);
                    
                    if (inputMoney > 10000) {
                        document.getElementsByClassName("errorericarica")[0].textContent = "Massimo $10,000 per ricarica";
                        return;
                    }
                    
                    if (inputMoney <= 0) {
                        document.getElementsByClassName("errorericarica")[0].textContent = "Inserisci un importo valido";
                        return;
                    }
                    
                    var currentMoney = document.getElementsByClassName("account-balance")[0].textContent;
                    currentMoney = parseInt(currentMoney.substring(1)); 
                    var newMoney = currentMoney + inputMoney;
                    document.getElementsByClassName("account-balance")[0].textContent = "$" + newMoney;
                    monitorSaldo();
                    document.getElementsByClassName("inputricarica")[0].value = "";
                } else {
                    document.getElementsByClassName("errorericarica")[0].textContent = "Inserisci un importo valido";
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
                            document.getElementById("risultato").textContent = "💸 Hai perso! Riprova la fortuna!";
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
