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
    <title>Cripsum™ Duel - Lobby</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/css/game.css?v=1.5-offline-bot">
    <script src="/assets/js/game.js?v=1.5-offline-bot" defer></script>
</head>

<body class="game-page" data-page="duel-lobby">
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>
    <div class="game-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="game-shell game-lobby-shell">
        <section class="game-hero game-reveal">
            <div class="game-hero-copy">
                <span class="game-kicker"><i class="fas fa-shield-halved"></i> Cripsum™ Duel</span>
                <h1>Lobby</h1>
                <p>Avvia una partita, controlla il tuo rank e guarda la classifica. Il game vero sta in una pagina separata.</p>
                <div class="game-steps" aria-label="Come iniziare">
                    <span><b>1</b> Cerca partita</span>
                    <span><b>2</b> Scegli 3 carte</span>
                    <span><b>3</b> Combatti</span>
                </div>
            </div>
            <div class="game-profile-card" id="profileSummary">
                <div class="game-profile-skeleton">Carico profilo...</div>
            </div>
        </section>

        <section class="game-lobby-grid">
            <div class="game-main-col">
                <section class="game-panel game-reveal" id="gameLobby">
                    <div class="game-panel-head">
                        <div>
                            <span class="game-kicker">Start</span>
                            <h2>Inizia una partita</h2>
                        </div>
                    </div>
                    <div class="game-action-grid">
                        <button class="game-mode-card" data-action="find-match" data-mode="casual" type="button">
                            <i class="fas fa-gamepad"></i><strong>Casual</strong><span>Partita normale, senza punti ranked.</span>
                        </button>
                        <button class="game-mode-card" data-action="find-match" data-mode="ranked" type="button">
                            <i class="fas fa-ranking-star"></i><strong>Ranked</strong><span>Vinci o perdi punti. Sali di categoria.</span>
                        </button>
                        <button class="game-mode-card game-mode-card-bot" data-action="create-bot" type="button">
                            <i class="fas fa-robot"></i><strong>Offline vs Bot</strong><span>Gioca subito contro il bot, senza aspettare.</span>
                        </button>
                    </div>

                    <div class="game-private-box">
                        <div>
                            <strong>Stanza privata</strong>
                            <span>Crea una stanza con codice e password.</span>
                        </div>
                        <input id="privatePasswordInput" type="password" maxlength="64" placeholder="Password stanza">
                        <button class="game-btn game-btn-special" data-action="create-private" type="button">
                            <i class="fas fa-lock"></i> Crea privata
                        </button>
                    </div>

                    <div class="game-join-row game-join-row-v13">
                        <input id="roomCodeInput" maxlength="16" placeholder="Codice stanza privata">
                        <input id="joinPasswordInput" type="password" maxlength="64" placeholder="Password">
                        <button class="game-btn game-btn-main" data-action="join-code" type="button">Entra</button>
                    </div>

                    <div class="game-matchmaking-wait" id="matchmakingWait" hidden>
                        <div class="game-matchmaking-orb"><span></span><span></span><span></span></div>
                        <div>
                            <strong>Cerco avversario...</strong>
                            <p>Sto preparando la stanza. Se nessuno entra subito, finirai in attesa.</p>
                        </div>
                    </div>
                </section>

                <section class="game-panel game-rules game-reveal" id="rulesPanel">
                    <div class="game-panel-head compact">
                        <div><span class="game-kicker">Regole</span>
                            <h2>Come funziona</h2>
                        </div>
                    </div>
                    <div class="game-rule-list">
                        <article><i class="fas fa-users"></i><strong> Team da 3</strong><span> - Usi solo personaggi che hai nell’inventario.</span></article>
                        <article><i class="fas fa-hand-fist"></i><strong> Attacco</strong><span> - Fa danno base e ti dà +1 energia.</span></article>
                        <article><i class="fas fa-wand-magic-sparkles"></i><strong> Speciale</strong><span> - Fa più danno, ma costa energia e ha cooldown.</span></article>
                        <article><i class="fas fa-shield"></i><strong> Difesa</strong><span> - Riduce il prossimo danno e recupera energia.</span></article>
                        <article><i class="fas fa-battery-full"></i><strong> Carica</strong><span> - Recupera energia se vuoi preparare la speciale.</span></article>
                        <article><i class="fas fa-repeat"></i><strong> Cambio</strong><span> - Clicca una tua carta sotto per cambiarla. Consuma turno.</span></article>
                    </div>
                </section>
            </div>

            <aside class="game-side-col">
                <section class="game-panel game-reveal">
                    <div class="game-panel-head compact">
                        <div><span class="game-kicker">Rank</span>
                            <h2>Categorie</h2>
                        </div>
                    </div>
                    <div class="game-rank-ladder game-rank-ladder-v14">
                        <span data-rank="bronzo"><i class="fas fa-shield"></i><strong>Bronzo</strong><small>0-999</small></span>
                        <span data-rank="argento"><i class="fas fa-medal"></i><strong>Argento</strong><small>1000-1199</small></span>
                        <span data-rank="oro"><i class="fas fa-crown"></i><strong>Oro</strong><small>1200-1399</small></span>
                        <span data-rank="diamante"><i class="fas fa-gem"></i><strong>Diamante</strong><small>1400-1599</small></span>
                        <span data-rank="campione"><i class="fas fa-trophy"></i><strong>Campione</strong><small>1600-1899</small></span>
                        <span data-rank="leggenda"><i class="fas fa-dragon"></i><strong>Leggenda</strong><small>1900+</small></span>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="rankingPanel">
                    <div class="game-panel-head compact">
                        <div><span class="game-kicker">Ranking</span>
                            <h2>Classifica</h2>
                        </div>
                        <button class="game-btn game-btn-soft" type="button" data-action="load-ranking">Aggiorna</button>
                    </div>
                    <div class="game-ranking" id="rankingList">
                        <p class="game-hint">Carico classifica...</p>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="liveMatchesPanel">
                    <div class="game-panel-head compact">
                        <div><span class="game-kicker">Spectate</span>
                            <h2>Partite live</h2>
                        </div>
                        <button class="game-btn game-btn-soft" type="button" data-action="load-live">Aggiorna</button>
                    </div>
                    <div class="game-live-list" id="liveMatchesList">
                        <p class="game-hint">Carico partite live...</p>
                    </div>
                </section>
            </aside>
        </section>
    </main>

    <div class="game-toast" id="gameToast" hidden><span></span></div>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>