<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per giocare devi essere loggato.";

    header('Location: accedi');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Utente';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Gambling</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/achievement-style.css?v=2.0-popup">
    <link rel="stylesheet" href="/assets/gambling/gambling.css?v=2.0-arcade">
    <script src="/assets/gambling/gambling.js?v=2.0-arcade" defer></script>
</head>

<body class="gambling-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="gambling-bg" aria-hidden="true">
        <span class="gambling-orb gambling-orb--one"></span>
        <span class="gambling-orb gambling-orb--two"></span>
        <span class="gambling-grid-bg"></span>
    </div>

    <main
        class="gambling-shell"
        data-user-id="<?php echo htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8'); ?>"
        data-username="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
    >
        <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement">
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>

        <section class="gambling-hero gambling-reveal">
            <div>
                <span class="gambling-pill">Gioco finto</span>
                <h1>Gambling Arcade</h1>
                <p>Slot machine con crediti finti. Nessun soldo reale, nessun pagamento, solo mini-game.</p>

                <div class="gambling-actions">
                    <button class="gambling-btn gambling-btn--soft" type="button" data-open-rules>
                        <i class="fas fa-circle-info"></i>
                        <span>Regole</span>
                    </button>

                    <button class="gambling-btn gambling-btn--soft" type="button" data-reset-session>
                        <i class="fas fa-rotate-left"></i>
                        <span>Reset sessione</span>
                    </button>
                </div>
            </div>

            <aside class="gambling-wallet" aria-label="Saldo gioco">
                <span class="wallet-label">Saldo</span>
                <strong data-balance>100</strong>
                <span class="wallet-caption">crediti finti</span>

                <div class="wallet-recharge">
                    <input type="number" min="1" max="5000" step="1" inputmode="numeric" data-recharge-input placeholder="Importo">
                    <button type="button" data-recharge-button>Ricarica</button>
                </div>

                <p class="gambling-inline-error" data-recharge-error></p>
            </aside>
        </section>

        <section class="gambling-layout">
            <div class="slot-card gambling-reveal">
                <div class="slot-topbar">
                    <div>
                        <span class="slot-label">Puntata</span>
                        <strong data-current-bet>10</strong>
                    </div>

                    <div class="slot-switches">
                        <label class="slot-switch">
                            <input type="checkbox" data-turbo-toggle>
                            <span>Turbo</span>
                        </label>

                        <label class="slot-switch">
                            <input type="checkbox" data-sound-toggle>
                            <span>Suono</span>
                        </label>
                    </div>
                </div>

                <div class="bet-row" role="group" aria-label="Scegli puntata">
                    <button type="button" class="bet-chip is-active" data-bet="10">10</button>
                    <button type="button" class="bet-chip" data-bet="25">25</button>
                    <button type="button" class="bet-chip" data-bet="50">50</button>
                    <button type="button" class="bet-chip" data-bet="100">100</button>
                </div>

                <div class="slot-machine" data-slot-machine>
                    <div class="slot-reel">
                        <img src="/img/slott1.jpg" alt="Slot 1" data-slot-image>
                    </div>
                    <div class="slot-reel">
                        <img src="/img/slott2.jpg" alt="Slot 2" data-slot-image>
                    </div>
                    <div class="slot-reel">
                        <img src="/img/slott3.jpg" alt="Slot 3" data-slot-image>
                    </div>
                </div>

                <div class="spin-controls">
                    <button class="spin-btn" type="button" data-spin-button>
                        <i class="fas fa-play"></i>
                        <span>SPIN</span>
                    </button>

                    <button class="auto-spin-btn" type="button" data-auto-spin-button>
                        <i class="fas fa-forward"></i>
                        <span>Auto x10</span>
                    </button>
                </div>

                <div class="result-box" data-result-box>
                    <span class="result-kicker">Pronto</span>
                    <strong data-result-title>Fai partire la slot</strong>
                    <p data-result-text>Scegli la puntata e prova lo spin.</p>
                </div>
            </div>

            <aside class="gambling-side">
                <section class="stat-card gambling-reveal">
                    <h2>Sessione</h2>

                    <div class="stats-grid">
                        <div>
                            <span>Spin</span>
                            <strong data-stat="spins">0</strong>
                        </div>
                        <div>
                            <span>Vinti</span>
                            <strong data-stat="won">0</strong>
                        </div>
                        <div>
                            <span>Persi</span>
                            <strong data-stat="lost">0</strong>
                        </div>
                        <div>
                            <span>Best win</span>
                            <strong data-stat="best">0</strong>
                        </div>
                        <div>
                            <span>Jackpot</span>
                            <strong data-stat="jackpots">0</strong>
                        </div>
                        <div>
                            <span>Profitto</span>
                            <strong data-stat="profit">0</strong>
                        </div>
                    </div>
                </section>

                <section class="payout-card gambling-reveal">
                    <h2>Payout</h2>

                    <div class="payout-row">
                        <span>3 uguali</span>
                        <strong>x20</strong>
                    </div>
                    <div class="payout-row">
                        <span>2 uguali</span>
                        <strong>x2</strong>
                    </div>
                    <div class="payout-row">
                        <span>Tre 7</span>
                        <strong>x35</strong>
                    </div>
                    <div class="payout-row">
                        <span>Tre 9</span>
                        <strong>x50</strong>
                    </div>
                </section>

                <section class="history-card gambling-reveal">
                    <div class="history-head">
                        <h2>Ultime giocate</h2>
                        <button type="button" data-clear-history>pulisci</button>
                    </div>

                    <div class="history-list" data-history-list>
                        <p class="history-empty">Nessuna giocata per ora.</p>
                    </div>
                </section>
            </aside>
        </section>
    </main>

    <div class="gambling-modal" data-rules-modal hidden>
        <div class="gambling-modal__panel" role="dialog" aria-modal="true" aria-labelledby="rulesTitle">
            <button class="gambling-modal__close" type="button" data-close-rules aria-label="Chiudi">
                <i class="fas fa-xmark"></i>
            </button>

            <span class="gambling-pill">Regole</span>
            <h2 id="rulesTitle">Come funziona</h2>
            <p>È un mini-gioco con crediti finti. Il saldo resta nel browser tramite localStorage e può essere resettato.</p>

            <ul>
                <li>Ogni spin scala la puntata scelta.</li>
                <li>Con 2 simboli uguali vinci x2.</li>
                <li>Con 3 simboli uguali vinci x20.</li>
                <li>Tre 7 e tre 9 valgono di più.</li>
                <li>Auto-spin fa massimo 10 spin e si ferma se il saldo non basta.</li>
            </ul>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="/js/unlockAchievement-it.js?v=2.0-popup"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
