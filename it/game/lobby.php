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
    <link rel="stylesheet" href="/assets/css/game.css?v=5.2">
    <script src="/assets/js/game.js?v=5.2" defer></script>
</head>

<body class="game-page" data-page="duel-lobby">
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="game-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="game-shell game-lobby-shell">
        <section class="game-hero game-reveal">
            <div class="game-hero-copy">
                <span class="game-kicker"><i class="fa-solid fa-shield-halved"></i> Cripsum™ Duel</span>
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
                            <h2>Matchmaking Rapido</h2>
                        </div>
                    </div>
                    <div class="game-action-grid">
                        <button class="game-mode-card" data-action="find-match" data-mode="casual" type="button">
                            <i class="fa-solid fa-gamepad"></i><strong>Casual</strong><span>Partita normale, senza punti ranked.</span>
                        </button>
                        <button class="game-mode-card" data-action="find-match" data-mode="ranked" type="button">
                            <i class="fa-solid fa-ranking-star"></i><strong>Ranked</strong><span>Vinci o perdi punti. Sali di categoria.</span>
                        </button>
                        <button class="game-mode-card game-mode-card-bot" data-action="create-bot" type="button">
                            <i class="fa-solid fa-robot"></i><strong>Offline vs Bot</strong><span>Gioca subito contro il bot, senza aspettare.</span>
                        </button>
                    </div>

                    <div class="game-matchmaking-wait" id="matchmakingWait" hidden>
                        <div class="game-matchmaking-orb"><span></span><span></span><span></span></div>
                        <div>
                            <strong>Cerco avversario...</strong>
                            <p>Sto preparando la stanza. Se nessuno entra subito, finirai in attesa.</p>
                        </div>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="privateLobby" style="margin-top: 1rem;">
                    <div class="game-panel-head">
                        <div>
                            <h2>Stanza Privata</h2>
                        </div>
                    </div>

                    <div class="game-private-box" style="margin-top: 0;">
                        <div>
                            <strong>Crea stanza</strong>
                            <span>Genera una stanza protetta da password.</span>
                        </div>
                        <input id="privatePasswordInput" type="password" maxlength="64" placeholder="Password stanza">
                        <button class="game-btn game-btn-special" data-action="create-private" type="button">
                            <i class="fa-solid fa-lock"></i> Crea privata
                        </button>
                    </div>

                    <div style="margin: 1.2rem 0; display: flex; align-items: center; justify-content: center; gap: 1rem; opacity: 0.35;">
                        <span style="flex: 1; height: 1px; background: var(--game-border);"></span>
                        <span style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--game-text);">Oppure entra</span>
                        <span style="flex: 1; height: 1px; background: var(--game-border);"></span>
                    </div>

                    <div class="game-join-row game-join-row-v13" style="margin-top: 0; display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.65rem;">
                        <input id="roomCodeInput" maxlength="16" placeholder="Codice stanza (es. CRPSM)">
                        <input id="joinPasswordInput" type="password" maxlength="64" placeholder="Password (se richiesta)">
                        <button class="game-btn game-btn-main" data-action="join-code" type="button">Entra</button>
                    </div>
                </section>

                <section class="game-panel game-rules game-reveal" id="rulesPanel">
                    <div class="game-panel-head compact">
                        <div>
                            <h2>Come funziona</h2>
                        </div>
                    </div>
                    <div class="game-rule-list">
                        <article><i class="fa-solid fa-users"></i><strong> Team da 3</strong><span> - Scegli e combatti con 3 personaggi diversi del tuo inventario.</span></article>
                        <article><i class="fa-solid fa-gauge-high"></i><strong> Velocità & Turni</strong><span> - Chi ha la Velocità più alta attacca per primo all'inizio del match.</span></article>
                        <article><i class="fa-solid fa-hand-fist"></i><strong> Attacco</strong><span> - Fa danno base basato sull'ATK e ti fornisce +1 energia.</span></article>
                        <article><i class="fa-solid fa-wand-magic-sparkles"></i><strong> Speciale & Ruoli</strong><span> - Ogni classe (Tank, Healer, DPS, ecc.) ha una speciale unica e passive dedicate.</span></article>
                        <article><i class="fa-solid fa-battery-full"></i><strong> Carica</strong><span> - Ricarica +2 energia e aumenta la Velocità del 20% per il prossimo turno.</span></article>
                        <article><i class="fa-solid fa-shield"></i><strong> Difesa</strong><span> - Attiva uno scudo protettivo temporaneo per ridurre i danni subiti.</span></article>
                        <article><i class="fa-solid fa-repeat"></i><strong> Cambio</strong><span> - Clicca un tuo alleato per scambiarlo con quello attivo (consuma il turno).</span></article>
                    </div>
                </section>
            </div>

            <aside class="game-side-col">
                <section class="game-panel game-reveal">
                    <div class="game-panel-head compact">
                        <div>
                            <h2>Rank</h2>
                        </div>
                    </div>
                    <div class="game-rank-ladder game-rank-ladder-v14">
                        <span data-rank="bronzo"><i class="fa-solid fa-shield"></i><strong>Bronzo</strong><small>0-999</small></span>
                        <span data-rank="argento"><i class="fa-solid fa-medal"></i><strong>Argento</strong><small>1000-1199</small></span>
                        <span data-rank="oro"><i class="fa-solid fa-crown"></i><strong>Oro</strong><small>1200-1399</small></span>
                        <span data-rank="diamante"><i class="fa-solid fa-gem"></i><strong>Diamante</strong><small>1400-1599</small></span>
                        <span data-rank="campione"><i class="fa-solid fa-trophy"></i><strong>Campione</strong><small>1600-1899</small></span>
                        <span data-rank="leggenda"><i class="fa-solid fa-dragon"></i><strong>Leggenda</strong><small>1900+</small></span>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="rankingPanel">
                    <div class="game-panel-head compact">
                        <div>
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
                        <div>
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