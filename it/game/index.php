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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>GoonLand Duel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/css/game.css?v=1.0-complete">
    <script src="/assets/js/game.js?v=1.0-complete" defer></script>
</head>
<body class="game-page">
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>
    <div class="game-bg" aria-hidden="true"><span></span><span></span></div>
    <main class="game-shell">
        <section class="game-hero">
            <div><span class="game-kicker"><i class="fas fa-shield-halved"></i> GoonLand Duel</span><h1>1v1 Card Battle</h1><p>Scegli 3 personaggi dal tuo inventario e sfida un altro utente in una battaglia a turni.</p></div>
            <div class="game-quick">
                <button class="game-btn game-btn-main" data-action="find-match" data-mode="casual" type="button">Cerca casual</button>
                <button class="game-btn game-btn-special" data-action="find-match" data-mode="ranked" type="button">Ranked</button>
                <button class="game-btn game-btn-ghost" data-action="create-private" type="button">Stanza privata</button>
            </div>
        </section>
        <section class="game-panel" id="gameLobby">
            <div class="game-panel-head"><div><span class="game-kicker">Lobby</span><h2>Inizia una partita</h2></div><button class="game-btn game-btn-soft" data-action="active-match" type="button">Riprendi</button></div>
            <div class="game-join-row"><input id="roomCodeInput" maxlength="16" placeholder="Codice stanza"><button class="game-btn game-btn-main" data-action="join-code" type="button">Entra</button></div>
            <p class="game-hint">Il gioco usa polling. Il server calcola danni, HP, turni e vittoria.</p>
        </section>
        <section class="game-panel" id="teamPanel" hidden>
            <div class="game-panel-head"><div><span class="game-kicker">Team</span><h2>Scegli 3 personaggi</h2></div><div class="game-room-pill">Room: <strong id="roomCodeLabel">---</strong></div></div>
            <div class="game-team-selected" id="selectedTeam"></div>
            <div class="game-inventory-toolbar"><input type="search" id="cardSearch" placeholder="Cerca personaggio..."><span id="teamCounter">0/3</span></div>
            <div class="game-card-grid" id="inventoryGrid"></div>
            <div class="game-actions"><button class="game-btn game-btn-main" data-action="submit-team" type="button">Conferma team</button><button class="game-btn game-btn-ghost" data-action="forfeit" type="button">Annulla/abbandona</button></div>
        </section>
        <section class="game-arena" id="arenaPanel" hidden>
            <div class="game-arena-head"><div><span class="game-kicker" id="matchStatus">Partita</span><h2 id="turnLabel">Attesa...</h2></div><div class="game-room-pill">Room: <strong id="arenaRoomCode">---</strong></div></div>
            <div class="game-board"><div class="game-side"><h3>Avversario</h3><div class="game-active-card" id="opponentActive"></div><div class="game-team-row" id="opponentTeam"></div></div><div class="game-vs"><span>VS</span></div><div class="game-side"><h3>Tu</h3><div class="game-active-card" id="playerActive"></div><div class="game-team-row" id="playerTeam"></div></div></div>
            <div class="game-action-bar" id="actionBar"><button class="game-btn game-btn-main" data-battle-action="basic_attack" type="button">Attacco</button><button class="game-btn game-btn-special" data-battle-action="special_attack" type="button">Speciale</button><button class="game-btn game-btn-ghost" data-battle-action="defend" type="button">Difesa</button><button class="game-btn game-btn-ghost" data-battle-action="charge" type="button">Carica</button><button class="game-btn game-btn-ghost" data-action="forfeit" type="button">Abbandona</button></div>
            <div class="game-log" id="battleLog"></div>
        </section>
        <section class="game-panel" id="rankingPanel"><div class="game-panel-head"><div><span class="game-kicker">Ranking</span><h2>Classifica ranked</h2></div><button class="game-btn game-btn-soft" data-action="load-ranking" type="button">Aggiorna</button></div><div class="game-ranking" id="rankingList"><p class="game-hint">Clicca aggiorna per caricare la classifica.</p></div></section>
    </main>
    <div class="game-toast" id="gameToast" hidden><span></span></div>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
