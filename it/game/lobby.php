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
    <link rel="stylesheet" href="/assets/css/game.css?v=1.2-split-ranked">
    <script src="/assets/js/game.js?v=1.2-split-ranked" defer></script>
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
                        <button class="game-btn game-btn-soft" data-action="active-match" type="button"><i class="fas fa-rotate-right"></i> Riprendi</button>
                    </div>
                    <div class="game-action-grid">
                        <button class="game-mode-card" data-action="find-match" data-mode="casual" type="button">
                            <i class="fas fa-gamepad"></i><strong>Casual</strong><span>Partita normale, senza punti ranked.</span>
                        </button>
                        <button class="game-mode-card" data-action="find-match" data-mode="ranked" type="button">
                            <i class="fas fa-ranking-star"></i><strong>Ranked</strong><span>Vinci o perdi punti. Sali di categoria.</span>
                        </button>
                        <button class="game-mode-card" data-action="create-private" type="button">
                            <i class="fas fa-user-group"></i><strong>Privata</strong><span>Crea una stanza con codice.</span>
                        </button>
                    </div>
                    <div class="game-join-row">
                        <input id="roomCodeInput" maxlength="16" placeholder="Codice stanza privata">
                        <button class="game-btn game-btn-main" data-action="join-code" type="button">Entra</button>
                    </div>
                </section>

                <section class="game-panel game-rules game-reveal" id="rulesPanel">
                    <div class="game-panel-head compact">
                        <div><span class="game-kicker">Regole</span><h2>Come funziona</h2></div>
                    </div>
                    <div class="game-rule-list">
                        <article><i class="fas fa-users"></i><strong>Team da 3</strong><span>Usi solo personaggi che hai nell’inventario.</span></article>
                        <article><i class="fas fa-hand-fist"></i><strong>Attacco</strong><span>Fa danno base e ti dà +1 energia.</span></article>
                        <article><i class="fas fa-wand-magic-sparkles"></i><strong>Speciale</strong><span>Fa più danno, ma costa energia e ha cooldown.</span></article>
                        <article><i class="fas fa-shield"></i><strong>Difesa</strong><span>Riduce il prossimo danno e recupera energia.</span></article>
                        <article><i class="fas fa-battery-full"></i><strong>Carica</strong><span>Recupera energia se vuoi preparare la speciale.</span></article>
                        <article><i class="fas fa-repeat"></i><strong>Cambio</strong><span>Clicca una tua carta sotto per cambiarla. Consuma turno.</span></article>
                    </div>
                </section>
            </div>

            <aside class="game-side-col">
                <section class="game-panel game-reveal">
                    <div class="game-panel-head compact">
                        <div><span class="game-kicker">Rank</span><h2>Categorie</h2></div>
                    </div>
                    <div class="game-rank-ladder">
                        <span data-rank="bronzo">Bronzo</span><span data-rank="argento">Argento</span><span data-rank="oro">Oro</span><span data-rank="diamante">Diamante</span><span data-rank="campione">Campione</span><span data-rank="leggenda">Leggenda</span>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="rankingPanel">
                    <div class="game-panel-head compact">
                        <div><span class="game-kicker">Ranking</span><h2>Classifica</h2></div>
                        <button class="game-btn game-btn-soft" type="button" data-action="load-ranking">Aggiorna</button>
                    </div>
                    <div class="game-ranking" id="rankingList"><p class="game-hint">Carico classifica...</p></div>
                </section>
            </aside>
        </section>
    </main>

    <div class="game-toast" id="gameToast" hidden><span></span></div>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
