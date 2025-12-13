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
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Gambling</title>
    <style>
        .gambling-container {
            padding-top: 7rem;
            padding-bottom: 4rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .account-bar {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-bottom: 3rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .account-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .account-label {
            font-weight: bold;
            color: var(--text-primary);
        }

        .account-value {
            color: var(--accent-color);
            font-weight: 600;
        }

        .recharge-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .recharge-input {
            max-width: 180px;
            border-radius: 8px;
        }

        .recharge-btn {
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
        }

        .recharge-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .error-message {
            color: #dc3545;
            margin: 0;
            font-size: 0.9rem;
            width: 100%;
        }

        .slot-machine-container {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .slots-wrapper {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .slot {
            flex: 0 0 auto;
            transition: transform 0.3s ease;
        }

        .slot:hover {
            transform: scale(1.05);
        }

        .slot img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 15px;
            border: 3px solid var(--border-color);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .spin-btn {
            padding: 1rem 3rem;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 12px;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .spin-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        .spin-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .result-message {
            margin-top: 2rem;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .result-message.success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .result-message.error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .account-bar {
                padding: 1rem;
                gap: 1rem;
            }

            .slot img {
                width: 150px;
                height: 150px;
            }

            .slots-wrapper {
                gap: 1rem;
            }

            .slot-machine-container {
                padding: 2rem 1rem;
            }

            .spin-btn {
                padding: 0.8rem 2rem;
                font-size: 1rem;
                min-width: 150px;
            }
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

        <div class="account-bar fadeup">
            <div class="account-info">
                <span class="account-label">Utente:</span>
                <span class="account-value"><?php echo htmlspecialchars($_SESSION["username"]) ?></span>
            </div>
            <div class="account-info">
                <span class="account-label">Saldo:</span>
                <span class="account-value account-balance">$100</span>
            </div>
            <div class="recharge-section">
                <input type="number" class="form-control recharge-input" placeholder="Importo" min="1" />
                <button class="btn btn-secondary recharge-btn" onclick="ricaricasaldo()">Ricarica</button>
                <p class="error-message errorericarica"></p>
            </div>
        </div>

        <div class="slot-machine-container fadeup">
            <div class="slots-wrapper">
                <div class="slot">
                    <img src="../img/cripsumchisiamo.jpg" alt="Slot 1" />
                </div>
                <div class="slot">
                    <img src="../img/barandeep.jpg" alt="Slot 2" />
                </div>
                <div class="slot">
                    <img src="../img/abdul.jpg" alt="Slot 3" />
                </div>
            </div>

            <div class="text-center">
                <button class="btn btn-primary spin-btn" id="spin-btn" onclick="spin()">SPIN!</button>
            </div>
            <p class="error-message text-center erroresoldi"></p>
            <p id="risultato"></p>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../js/unlockAchievement-it.js"></script>
    <script>
        let saldoMonitorInterval = null;

        function ricaricasaldo() {
            const errorElement = document.querySelector(".errorericarica");
            const inputElement = document.querySelector(".recharge-input");
            const balanceElement = document.querySelector(".account-balance");

            errorElement.textContent = "";
            const inputMoney = inputElement.value;

            if (!inputMoney || inputMoney === "e" || inputMoney <= 0) {
                errorElement.textContent = "Inserisci un importo valido";
                return;
            }

            let currentMoney = parseInt(balanceElement.textContent.substring(1));
            const newMoney = currentMoney + parseInt(inputMoney);
            balanceElement.textContent = "$" + newMoney;
            monitorSaldo();
            inputElement.value = "";
        }

        function spin() {
            const spinBtn = document.getElementById("spin-btn");
            const balanceElement = document.querySelector(".account-balance");
            const resultElement = document.getElementById("risultato");
            const errorElement = document.querySelector(".erroresoldi");

            let money = parseInt(balanceElement.textContent.substring(1));

            if (money < 10) {
                errorElement.textContent = "Saldo insufficiente! Servono almeno $10";
                return;
            }

            money -= 10;
            balanceElement.textContent = "$" + money;
            monitorSaldo();

            spinBtn.disabled = true;
            spinBtn.textContent = "SPINNING...";
            resultElement.textContent = "";
            resultElement.className = "";
            errorElement.textContent = "";

            const slots = document.querySelectorAll(".slot img");
            let randomIndexes = [];

            const startTime = Date.now();
            const interval = setInterval(() => {
                randomIndexes = Array.from({
                    length: 3
                }, () => Math.floor(Math.random() * 9) + 1);
                slots.forEach((slot, i) => {
                    slot.src = `../img/slott${randomIndexes[i]}.jpg`;
                });

                if (Date.now() - startTime >= 2000) {
                    clearInterval(interval);

                    if (randomIndexes[0] === randomIndexes[1] && randomIndexes[1] === randomIndexes[2]) {
                        resultElement.textContent = "JACKPOT! HAI VINTO $1000!";
                        resultElement.className = "result-message success";
                        money += 1000;
                        balanceElement.textContent = "$" + money;
                        unlockAchievement(3);
                    } else {
                        resultElement.textContent = "Hai perso! Riprova!";
                        resultElement.className = "result-message error";
                    }

                    spinBtn.disabled = false;
                    spinBtn.textContent = "SPIN!";
                }
            }, 100);
        }

        function monitorSaldo() {
            if (saldoMonitorInterval) return;

            const startTime = Date.now();
            saldoMonitorInterval = setInterval(() => {
                const money = parseInt(document.querySelector(".account-balance").textContent.substring(1));

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

        document.querySelector(".recharge-input").addEventListener("keypress", (e) => {
            if (e.key === "Enter") ricaricasaldo();
        });
    </script>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../js/modeChanger.js"></script>
</body>

</html>