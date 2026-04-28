<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'Per giocare devi essere loggato';
    header('Location: ../accedi');
    exit();
}

$matchId = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Cripsum™ Duel - Game</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/css/game.css?v=1.3-private-chat">
    <script src="/assets/js/game.js?v=1.3-private-chat" defer></script>
</head>
<body class="game-page" data-page="duel-arena" data-match-id="<?php echo htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8'); ?>">
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>
    <div class="game-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="game-shell game-arena-shell">
        <section class="game-compact-top">
            <a class="game-btn game-btn-ghost" href="/it/game/lobby.php"><i class="fas fa-arrow-left"></i> Lobby</a>
            <div class="game-room-pill">Room: <strong id="arenaRoomCode">---</strong></div>
        </section>

        <section class="game-panel game-waiting-panel" id="waitingPanel" hidden>
            <div class="game-panel-head"><div><span class="game-kicker">Attesa</span><h2>In attesa dell’avversario</h2></div></div>
            <div class="game-waiting-box">
                <div class="game-matchmaking-orb is-large"><span></span><span></span><span></span></div>
                <div>
                    <strong>Matchmaking attivo</strong>
                    <p class="game-hint">Condividi codice e password se è una stanza privata. Appena entra un player scegli il team.</p>
                </div>
            </div>
            <div class="game-actions"><button class="game-btn game-btn-ghost" data-action="forfeit" type="button">Annulla stanza</button></div>
        </section>

        <section class="game-panel" id="teamPanel" hidden>
            <div class="game-panel-head">
                <div><span class="game-kicker">Team</span><h2>Scegli 3 personaggi</h2></div>
                <div class="game-room-pill">Room: <strong id="roomCodeLabel">---</strong></div>
            </div>
            <div class="game-guide"><i class="fas fa-circle-info"></i><span>Seleziona 3 carte. La prima sarà quella attiva all’inizio.</span></div>
            <div class="game-team-selected" id="selectedTeam"></div>
            <div class="game-inventory-toolbar"><input type="search" id="cardSearch" placeholder="Cerca personaggio..."><span id="teamCounter">0/3</span></div>
            <div class="game-card-grid" id="inventoryGrid"></div>
            <div class="game-actions"><button class="game-btn game-btn-main" data-action="submit-team" type="button"><i class="fas fa-check"></i> Conferma team</button><button class="game-btn game-btn-ghost" data-action="forfeit" type="button">Abbandona</button></div>
        </section>

        <section class="game-arena" id="arenaPanel" hidden>
            <div class="game-fx" id="gameFx" aria-hidden="true"><span></span></div>
            <div class="game-arena-head">
                <div><span class="game-kicker" id="matchStatus">Partita</span><h2 id="turnLabel">Attesa...</h2></div>
                <div class="game-turn-mini" id="turnCoach"><i class="fas fa-lightbulb"></i><span>Segui i glow sulle carte per capire cosa succede.</span></div>
            </div>
            <div class="game-board">
                <div class="game-side game-side-opponent"><h3 id="opponentName">Avversario</h3><div class="game-active-card" id="opponentActive"></div><div class="game-team-row" id="opponentTeam"></div></div>
                <div class="game-vs"><span>VS</span></div>
                <div class="game-side game-side-player"><h3>Tu</h3><div class="game-active-card" id="playerActive"></div><div class="game-team-row" id="playerTeam"></div></div>
            </div>
            <div class="game-action-bar" id="actionBar" aria-label="Azioni battaglia">
                <button class="game-move game-move-attack" data-battle-action="basic_attack" type="button"><i class="fas fa-hand-fist"></i><strong>Attacco</strong><span>Danno base · +1 energia</span></button>
                <button class="game-move game-move-special" data-battle-action="special_attack" type="button"><i class="fas fa-wand-magic-sparkles"></i><strong>Speciale</strong><span>Più danno · costa energia</span></button>
                <button class="game-move game-move-defend" data-battle-action="defend" type="button"><i class="fas fa-shield"></i><strong>Difesa</strong><span>Riduce danno · +1 energia</span></button>
                <button class="game-move game-move-charge" data-battle-action="charge" type="button"><i class="fas fa-battery-full"></i><strong>Carica</strong><span>Recupera +2 energia</span></button>
            </div>
            <div class="game-sub-actions"><span><i class="fas fa-repeat"></i> Per cambiare carta clicca una tua carta sotto.</span><button class="game-btn game-btn-ghost" data-action="forfeit" type="button">Abbandona</button></div>
            <div class="game-arena-bottom">
                <div class="game-log-wrap">
                    <div class="game-log-title">Log turno</div>
                    <div class="game-log" id="battleLog"></div>
                </div>

                <div class="game-chat-wrap">
                    <div class="game-log-title">Chat partita</div>
                    <div class="game-chat-messages" id="chatMessages">
                        <p class="game-hint">Scrivi all’avversario durante la partita.</p>
                    </div>
                    <form class="game-chat-form" id="chatForm">
                        <input id="chatInput" maxlength="220" placeholder="Messaggio...">
                        <button class="game-btn game-btn-main" type="submit">Invia</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <div class="game-result-modal" id="resultModal" hidden>
        <div class="game-result-card">
            <span class="game-kicker" id="resultKicker">Risultato</span>
            <h2 id="resultTitle">Partita conclusa</h2>
            <p id="resultText"></p>
            <div class="game-ranked-feedback" id="rankedFeedback" hidden></div>
            <div class="game-actions"><a class="game-btn game-btn-main" href="/it/game/lobby.php">Torna alla lobby</a></div>
        </div>
    </div>

    <div class="game-toast" id="gameToast" hidden><span></span></div>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
