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

        <div style="padding-top: 7rem" class="testobianco">
            <div id="achievement-popup" class="popup">
                <img id="popup-image" src="" alt="Achievement" />
                <div>
                    <h3 id="popup-title"></h3>
                    <p id="popup-description"></p>
                </div>
            </div>
            <div class="account-bar fadeup" id="account-bar" style="padding-top: 1%; padding-bottom: 1%; margin: auto">
                <span class="" style="padding-left: 25px; font-weight: bold">Utente: </span>
                <span class="account-name" style="padding-left: 10px; padding-right: 10px">Account Name</span>
                <div style="padding-left: 25px" class="">
                    <span class="" style="font-weight: bold">Saldo: </span>
                    <span class="account-balance" style="padding-left: 10px">$100</span>
                </div>
                <input type="number" class="form-control inputricarica" style="margin-left: 25px; max-width: 200px; margin-top: 10px" placeholder="inserisci denaro" aria-label="Last name" />
                <button class="btn btn-secondary bottone" style="width: 150px; margin-left: 25px; margin-top: 5px" onclick="ricaricasaldo();">Ricarica saldo</button>

                <p class="errorericarica" style="color: red; margin-top: 3px; margin-left: 25px"></p>
            </div>

            <div class="input-container" style="text-align: center; padding-top: 3%; max-width: 500px; margin: auto" id="accesso-gambling">
                <div class="row" style="margin: auto">
                    <div class="col fadeup">
                        <input type="text" id="name-input" class="form-control" placeholder="inserisci il tuo nome" aria-label="First name" />
                    </div>
                    <div class="col fadeup" style="max-width: 200px">
                        <input type="number" id="money-input" class="form-control" placeholder="inserisci denaro" aria-label="Last name" />
                    </div>
                </div>
                <button class="btn btn-secondary bottone fadeup" style="margin-top: 10px" onclick="updateAccount()">Accedi</button>
                <p id="errore" class="errore text-center fadeup" style="color: red; margin-top: 3px"></p>
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
                <!--
            <div class="loading" style="padding-top: 2%;" id="caricamento">
                <span class="loading__dot"></span>
                <span class="loading__dot"></span>
                <span class="loading__dot"></span>
            </div> close_div(1)
            -->

                <div class="button-container fadeup" style="text-align: center; margin-top: 3%">
                    <button class="btn btn-secondary bottone" onclick="spin()">Spin</button>
                </div>
                <p class="text-center erroresoldi" style="margin-top: 3%; color: red"></p>
                <p class="text-center" id="risultato" style="margin-top: 3%"></p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script src="../js/unlockAchievement-it.js"></script>
        <script>
            let saldoMonitorInterval = null;

            function ricaricasaldo() {
                document.getElementsByClassName("errorericarica")[0].textContent = "";
                var inputMoney = document.getElementsByClassName("inputricarica")[0].value;
                if (inputMoney !== "" && inputMoney !== "e") {
                    var currentMoney = document.getElementsByClassName("account-balance")[0].textContent;
                    currentMoney = parseInt(currentMoney.substring(1)); // remove the $ sign and convert to integer
                    var newMoney = currentMoney + parseInt(inputMoney);
                    document.getElementsByClassName("account-balance")[0].textContent = "$" + newMoney;
                    monitorSaldo();
                    document.getElementsByClassName("inputricarica")[0].value = "";
                } else {
                    document.getElementsByClassName("errorericarica")[0].textContent = "Il campo non può essere vuoto";
                    return;
                }
            }

            document.getElementById("account-bar").style.display = "none";

            function updateAccount() {
                document.getElementsByClassName("errore")[0].textContent = "";
                var name = document.getElementById("name-input").value;
                var money = document.getElementById("money-input").value;
                if (money !== "" && name !== "") {
                    document.getElementsByClassName("erroresoldi")[0].textContent = "";
                    document.getElementById("account-bar").style.display = "block";
                    document.getElementsByClassName("account-balance")[0].textContent = "$" + money;
                    document.getElementById("money-input").value = "";
                    document.getElementsByClassName("account-name")[0].textContent = name;
                    document.getElementById("name-input").value = "";
                    document.getElementById("accesso-gambling").style.display = "none";
                    monitorSaldo();
                } else {
                    document.getElementsByClassName("errore")[0].textContent = "i campi non possono essere vuoti";
                }
            }

            function spin() {
                if (document.getElementById("account-bar").style.display === "none") {
                    document.getElementsByClassName("erroresoldi")[0].textContent = "Devi effettuare l'accesso per giocare";
                    return;
                }
                var money = document.getElementsByClassName("account-balance")[0].textContent;
                money = parseInt(money.substring(1)); // remove the $ sign and convert to integer

                if (money >= 10) {
                    money -= 10;
                    document.getElementsByClassName("account-balance")[0].textContent = "$" + money;
                    monitorSaldo(); // Avvia il monitoraggio se non è già in corso
                } else {
                    document.getElementsByClassName("erroresoldi")[0].textContent = "Saldo insufficiente"; // display an error message if there are not enough funds
                    return;
                }

                document.getElementById("risultato").textContent = "";
                document.getElementsByClassName("erroresoldi")[0].textContent = "";
                // Get the slot elements
                var slots = document.getElementsByClassName("slot");

                // Generate random indexes for the images
                var randomIndexes = [];
                for (var i = 0; i < slots.length; i++) {
                    var randomIndex = Math.floor(Math.random() * 9) + 1;
                    randomIndexes.push(randomIndex);
                }

                // Set the initial image sources
                for (var i = 0; i < slots.length; i++) {
                    slots[i].getElementsByTagName("img")[0].src = "../img/slott" + randomIndexes[i] + ".jpg";
                }

                // Spin the images for 5 seconds
                var startTime = Date.now();
                var interval = setInterval(function () {
                    // Generate new random indexes for the images
                    randomIndexes = [];
                    for (var i = 0; i < slots.length; i++) {
                        var randomIndex = Math.floor(Math.random() * 9) + 1;
                        randomIndexes.push(randomIndex);
                    }

                    // Set the new image sources
                    for (var i = 0; i < slots.length; i++) {
                        slots[i].getElementsByTagName("img")[0].src = "../img/slott" + randomIndexes[i] + ".jpg";
                    }

                    // Check if all images are the same after 5 seconds
                    if (Date.now() - startTime >= 5000) {
                        if (randomIndexes[0] === randomIndexes[1] && randomIndexes[1] === randomIndexes[2]) {
                            document.getElementById("risultato").textContent = "Hai Vinto!";
                            money += 100; // add 100 to the money count
                            document.getElementsByClassName("account-balance")[0].textContent = "$" + money; // update the money count display
                            unlockAchievement(3);
                        } else {
                            document.getElementById("risultato").textContent = "Hai perso scemo, ritenta!";
                        }
                        clearInterval(interval);
                    }
                }, 100);
            }

            function monitorSaldo() {
                if (saldoMonitorInterval) return; // Evita di creare più intervalli

                let startTime = Date.now();
                saldoMonitorInterval = setInterval(function () {
                    let money = parseInt(document.getElementsByClassName("account-balance")[0].textContent.substring(1));

                    if (money < 10) {
                        unlockAchievement(11);
                        clearInterval(saldoMonitorInterval);
                        saldoMonitorInterval = null; // Resetta la variabile
                    }

                    if (Date.now() - startTime >= 60000) {
                        // Dopo 1 minuto, ferma il controllo
                        clearInterval(saldoMonitorInterval);
                        saldoMonitorInterval = null; // Resetta la variabile
                    }
                }, 1000); // Controlla ogni secondo
            }

            // function close_div(id) {
            //     document.getElementById("caricamento").style.display = "none";
            // }
        </script>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
