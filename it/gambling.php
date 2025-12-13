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
            padding: 8rem 1.5rem 4rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .account-bar {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #333;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 3rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .account-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .account-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .account-value {
            font-size: 1.5rem;
            color: #fff;
            font-weight: 600;
        }

        .account-balance {
            color: #4ade80;
        }

        .recharge-section {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .recharge-input {
            background: #1a1a1a;
            border: 1px solid #333;
            color: #fff;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 1rem;
        }

        .recharge-input:focus {
            outline: none;
            border-color: #4ade80;
            background: #0f0f0f;
        }

        .recharge-btn {
            background: #4ade80;
            color: #000;
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .recharge-btn:hover {
            background: #22c55e;
        }

        .error-message {
            color: #ef4444;
            margin: 0;
            font-size: 0.875rem;
        }

        .slot-machine-container {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #333;
            border-radius: 12px;
            padding: 3rem 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .slots-wrapper {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
            padding: 2rem;
            background: #0f0f0f;
            border-radius: 12px;
            border: 1px solid #222;
        }

        .slot {
            position: relative;
        }

        .slot img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #333;
            display: block;
        }

        .text-center {
            text-align: center;
        }

        .spin-btn {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: #000;
            border: none;
            padding: 1rem 4rem;
            font-size: 1.25rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(74, 222, 128, 0.3);
        }

        .spin-btn:hover:not(:disabled) {
            box-shadow: 0 6px 20px rgba(74, 222, 128, 0.4);
        }

        .spin-btn:active:not(:disabled) {
            transform: scale(0.98);
        }

        .spin-btn:disabled {
            background: #333;
            color: #666;
            cursor: not-allowed;
            box-shadow: none;
        }

        .result-message {
            margin-top: 2rem;
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
            padding: 1rem;
            border-radius: 8px;
        }

        .result-message.success {
            background: rgba(74, 222, 128, 0.1);
            color: #4ade80;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .result-message.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 768px) {
            .gambling-container {
                padding: 7rem 1rem 3rem;
            }

            .account-bar {
                padding: 1.5rem;
                gap: 1.5rem;
            }

            .slots-wrapper {
                gap: 1rem;
                padding: 1.5rem;
                flex-wrap: wrap;
            }

            .slot img {
                width: 140px;
                height: 140px;
            }

            .slot-machine-container {
                padding: 2rem 1rem;
            }

            .spin-btn {
                padding: 0.875rem 3rem;
                font-size: 1.1rem;
            }

            .account-value {
                font-size: 1.25rem;
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