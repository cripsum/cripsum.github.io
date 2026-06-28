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
<html lang="en">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Cripsum™ Duel - Lobby</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/css/game.css?v=2.8-custom-reactions">
    <script src="/assets/js/game.js?v=2.8-custom-reactions" defer></script>
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
                            <h2>Quick Matchmaking</h2>
                        </div>
                    </div>
                    <div class="game-action-grid">
                        <button class="game-mode-card" data-action="find-match" data-mode="casual" type="button">
                            <i class="fa-solid fa-gamepad"></i><strong>Casual</strong><span>Normal match, without ranked points.</span>
                        </button>
                        <button class="game-mode-card" data-action="find-match" data-mode="ranked" type="button">
                            <i class="fa-solid fa-ranking-star"></i><strong>Ranked</strong><span>Win or lose points. Climb the ranks.</span>
                        </button>
                        <button class="game-mode-card game-mode-card-bot" data-action="create-bot" type="button">
                            <i class="fa-solid fa-robot"></i><strong>Offline vs Bot</strong><span>Play instantly against the bot, without waiting.</span>
                        </button>
                    </div>

                    <div class="game-matchmaking-wait" id="matchmakingWait" hidden>
                        <div class="game-matchmaking-orb"><span></span><span></span><span></span></div>
                        <div>
                            <strong>Finding opponent...</strong>
                            <p>Preparing the room. If no one joins, you'll be placed on wait.</p>
                        </div>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="privateLobby" style="margin-top: 1rem;">
                    <div class="game-panel-head">
                        <div>
                            <h2>Private Room</h2>
                        </div>
                    </div>

                    <div class="game-private-box" style="margin-top: 0;">
                        <div>
                            <strong>Create Room</strong>
                            <span>Generate a private room protected by a password.</span>
                        </div>
                        <input id="privatePasswordInput" type="password" maxlength="64" placeholder="Room password">
                        <button class="game-btn game-btn-special" data-action="create-private" type="button">
                            <i class="fa-solid fa-lock"></i> Create private
                        </button>
                    </div>

                    <div style="margin: 1.2rem 0; display: flex; align-items: center; justify-content: center; gap: 1rem; opacity: 0.35;">
                        <span style="flex: 1; height: 1px; background: var(--game-border);"></span>
                        <span style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--game-text);">Or Join</span>
                        <span style="flex: 1; height: 1px; background: var(--game-border);"></span>
                    </div>

                    <div class="game-join-row game-join-row-v13" style="margin-top: 0; display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.65rem;">
                        <input id="roomCodeInput" maxlength="16" placeholder="Room code (e.g., CRPSM)">
                        <input id="joinPasswordInput" type="password" maxlength="64" placeholder="Password (if required)">
                        <button class="game-btn game-btn-main" data-action="join-code" type="button">Join</button>
                    </div>
                </section>

                <section class="game-panel game-rules game-reveal" id="rulesPanel">
                    <div class="game-panel-head compact">
                        <div>
                            <h2>How it works</h2>
                        </div>
                    </div>
                    <div class="game-rule-list">
                        <article><i class="fa-solid fa-users"></i><strong> Team of 3</strong><span> - Choose and battle with 3 different characters from your inventory.</span></article>
                        <article><i class="fa-solid fa-gauge-high"></i><strong> Speed & Turns</strong><span> - The character with the highest Speed goes first at the start of the match.</span></article>
                        <article><i class="fa-solid fa-hand-fist"></i><strong> Attack</strong><span> - Deals base damage based on ATK and gives you +1 energy.</span></article>
                        <article><i class="fa-solid fa-wand-magic-sparkles"></i><strong> Special & Roles</strong><span> - Every class (Tank, Healer, DPS, etc.) has a unique special and passive skills.</span></article>
                        <article><i class="fa-solid fa-battery-full"></i><strong> Charge</strong><span> - Recovers +2 energy and increases Speed by 20% for the next turn.</span></article>
                        <article><i class="fa-solid fa-shield"></i><strong> Defend</strong><span> - Activates a temporary protective shield to reduce damage received.</span></article>
                        <article><i class="fa-solid fa-repeat"></i><strong> Switch</strong><span> - Click your ally to swap them with the active one (consumes turn).</span></article>
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
                        <span data-rank="bronzo"><i class="fa-solid fa-shield"></i><strong>Bronze</strong><small>0-999</small></span>
                        <span data-rank="argento"><i class="fa-solid fa-medal"></i><strong>Silver</strong><small>1000-1199</small></span>
                        <span data-rank="oro"><i class="fa-solid fa-crown"></i><strong>Gold</strong><small>1200-1399</small></span>
                        <span data-rank="diamante"><i class="fa-solid fa-gem"></i><strong>Diamond</strong><small>1400-1599</small></span>
                        <span data-rank="campione"><i class="fa-solid fa-trophy"></i><strong>Champion</strong><small>1600-1899</small></span>
                        <span data-rank="leggenda"><i class="fa-solid fa-dragon"></i><strong>Legend</strong><small>1900+</small></span>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="rankingPanel">
                    <div class="game-panel-head compact">
                        <div>
                            <h2>Ranking</h2>
                        </div>
                        <button class="game-btn game-btn-soft" type="button" data-action="load-ranking">Refresh</button>
                    </div>
                    <div class="game-ranking" id="rankingList">
                        <p class="game-hint">Loading ranking...</p>
                    </div>
                </section>

                <section class="game-panel game-reveal" id="liveMatchesPanel">
                    <div class="game-panel-head compact">
                        <div>
                            <h2>Live matches</h2>
                        </div>
                        <button class="game-btn game-btn-soft" type="button" data-action="load-live">Refresh</button>
                    </div>
                    <div class="game-live-list" id="liveMatchesList">
                        <p class="game-hint">Loading live matches...</p>
                    </div>
                </section>
            </aside>
        </section>
    </main>

    <div class="game-toast" id="gameToast" hidden><span></span></div>
    <?php include '../../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>