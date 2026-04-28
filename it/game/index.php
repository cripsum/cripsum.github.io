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
    <title>Cripsum™ Duel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/css/game.css?v=1.1-cripsum-duel">
    <script src="/assets/js/game.js?v=1.1-cripsum-duel" defer></script>
</head>

<body class="game-page" data-page="cripsum-duel">
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="game-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="game-shell">
        <section class="game-hero game-reveal">
            <div class="game-hero-copy">
                <span class="game-kicker"><i class="fas fa-shield-halved"></i> Cripsum™ Duel</span>
                <h1>Duelli 1v1 con i tuoi personaggi.</h1>
                <p>Scegli 3 carte dal tuo inventario lootbox. Entra in partita. Fai la mossa giusta al turno giusto.</p>

                <div class="game-steps" aria-label="Come iniziare">
                    <span><b>1</b> Cerca o crea stanza</span>
                    <span><b>2</b> Scegli 3 carte</span>
                    <span><b>3</b> Gioca a turni</span>
                </div>
            </div>

            <div class="game-quick" aria-label="Azioni rapide">
                <button class="game-btn game-btn-main" data-action="find-match" data-mode="casual" type="button">
                    <i class="fas fa-bolt"></i> Cerca casual
                </button>
                <button class="game-btn game-btn-special" data-action="find-match" data-mode="ranked" type="button">
                    <i class="fas fa-trophy"></i> Ranked
                </button>
                <button class="game-btn game-btn-ghost" data-action="create-private" type="button">
                    <i class="fas fa-lock"></i> Stanza privata
                </button>
            </div>
        </section>

        <section class="game-layout">
            <div class="game-main-col">
                <section class="game-panel game-reveal" id="gameLobby">
                    <div class="game-panel-head">
                        <div>
                            <span class="game-kicker">Lobby</span>
                            <h2>Inizia da qui</h2>
                        </div>
                        <button class="game-btn game-btn-soft" data-action="active-match" type="button">
                            <i class="fas fa-rotate-right"></i> Riprendi
                        </button>
                    </div>

                    <div class="game-action-grid">
                        <button class="game-mode-card" data-action="find-match" data-mode="casual" type="button">
                            <i class="fas fa-gamepad"></i>
                            <strong>Casual</strong>
                            <span>Partita normale, senza pressione.</span>
                        </button>

                        <button class="game-mode-card" data-action="find-match" data-mode="ranked" type="button">
                            <i class="fas fa-ranking-star"></i>
                            <strong>Ranked</strong>
                            <span>Conta per la classifica.</span>
                        </button>

                        <button class="game-mode-card" data-action="create-private" type="button">
                            <i class="fas fa-user-group"></i>
                            <strong>Privata</strong>
                            <span>Crea una stanza con codice.</span>
                        </button>
                    </div>

                    <div class="game-join-row">
                        <input id="roomCodeInput" maxlength="16" placeholder="Codice stanza privata">
                        <button class="game-btn game-btn-main" data-action="join-code" type="button">Entra</button>
                    </div>

                    <p class="game-hint"><i class="fas fa-server"></i> Il server controlla danni, turni, HP e vittoria. Il client mostra solo la partita.</p>
                </section>

                <section class="game-panel" id="teamPanel" hidden>
                    <div class="game-panel-head">
                        <div>
                            <span class="game-kicker">Team</span>
                            <h2>Scegli 3 personaggi</h2>
                        </div>
                        <div class="game-room-pill">Room: <strong id="roomCodeLabel">---</strong></div>
                    </div>

                    <div class="game-guide game-guide-team">
                        <i class="fas fa-circle-info"></i>
                        <span>Seleziona 3 carte. La prima sarà quella attiva all’inizio.</span>
                    </div>

                    <div class="game-team-selected" id="selectedTeam"></div>

                    <div class="game-inventory-toolbar">
                        <input type="search" id="cardSearch" placeholder="Cerca personaggio...">
                        <span id="teamCounter">0/3</span>
                    </div>

                    <div class="game-card-grid" id="inventoryGrid"></div>

                    <div class="game-actions">
                        <button class="game-btn game-btn-main" data-action="submit-team" type="button">
                            <i class="fas fa-check"></i> Conferma team
                        </button>
                        <button class="game-btn game-btn-ghost" data-action="forfeit" type="button">
                            Annulla/abbandona
                        </button>
                    </div>
                </section>

                <section class="game-arena" id="arenaPanel" hidden>
                    <div class="game-fx" id="gameFx" aria-hidden="true"><span></span></div>

                    <div class="game-arena-head">
                        <div>
                            <span class="game-kicker" id="matchStatus">Partita</span>
                            <h2 id="turnLabel">Attesa...</h2>
                        </div>
                        <div class="game-room-pill">Room: <strong id="arenaRoomCode">---</strong></div>
                    </div>

                    <div class="game-turn-coach" id="turnCoach">
                        <i class="fas fa-lightbulb"></i>
                        <span>Quando è il tuo turno, scegli una mossa sotto l’arena.</span>
                    </div>

                    <div class="game-board">
                        <div class="game-side game-side-opponent">
                            <h3>Avversario</h3>
                            <div class="game-active-card" id="opponentActive"></div>
                            <div class="game-team-row" id="opponentTeam"></div>
                        </div>

                        <div class="game-vs"><span>VS</span></div>

                        <div class="game-side game-side-player">
                            <h3>Tu</h3>
                            <div class="game-active-card" id="playerActive"></div>
                            <div class="game-team-row" id="playerTeam"></div>
                        </div>
                    </div>

                    <div class="game-action-bar" id="actionBar" aria-label="Azioni battaglia">
                        <button class="game-move game-move-attack" data-battle-action="basic_attack" type="button">
                            <i class="fas fa-hand-fist"></i>
                            <strong>Attacco</strong>
                            <span>Danno base · +1 energia</span>
                        </button>
                        <button class="game-move game-move-special" data-battle-action="special_attack" type="button">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <strong>Speciale</strong>
                            <span>Più danno · costa energia</span>
                        </button>
                        <button class="game-move game-move-defend" data-battle-action="defend" type="button">
                            <i class="fas fa-shield"></i>
                            <strong>Difesa</strong>
                            <span>Riduce danno · +1 energia</span>
                        </button>
                        <button class="game-move game-move-charge" data-battle-action="charge" type="button">
                            <i class="fas fa-battery-full"></i>
                            <strong>Carica</strong>
                            <span>Recupera +2 energia</span>
                        </button>
                    </div>

                    <div class="game-sub-actions">
                        <span><i class="fas fa-repeat"></i> Per cambiare carta clicca una tua carta sotto.</span>
                        <button class="game-btn game-btn-ghost" data-action="forfeit" type="button">Abbandona</button>
                    </div>

                    <div class="game-log-wrap">
                        <div class="game-log-title">Log turno</div>
                        <div class="game-log" id="battleLog"></div>
                    </div>
                </section>
            </div>

            <aside class="game-side-col">
                <section class="game-panel game-rules game-reveal" id="rulesPanel">
                    <div class="game-panel-head compact">
                        <div>
                            <span class="game-kicker">Regole</span>
                            <h2>Come funziona</h2>
                        </div>
                    </div>

                    <div class="game-rule-list">
                        <div class="game-rule"><b>Team</b><span>Ogni player sceglie 3 personaggi che possiede.</span></div>
                        <div class="game-rule"><b>Turni</b><span>Agisci solo quando il turno è tuo.</span></div>
                        <div class="game-rule"><b>Attacco</b><span>Fa danno e recupera 1 energia.</span></div>
                        <div class="game-rule"><b>Speciale</b><span>Fa più danno, costa energia e va in cooldown.</span></div>
                        <div class="game-rule"><b>Difesa</b><span>Riduce il prossimo danno e dà 1 energia.</span></div>
                        <div class="game-rule"><b>Vittoria</b><span>Vinci quando tutte le carte avversarie sono KO.</span></div>
                    </div>
                </section>

                <section class="game-panel game-ranking-panel game-reveal" id="rankingPanel">
                    <div class="game-panel-head compact">
                        <div>
                            <span class="game-kicker">Ranking</span>
                            <h2>Classifica</h2>
                        </div>
                        <button class="game-icon-btn" data-action="load-ranking" type="button" aria-label="Aggiorna classifica">
                            <i class="fas fa-rotate"></i>
                        </button>
                    </div>
                    <div class="game-ranking" id="rankingList">
                        <p class="game-hint">Caricamento classifica...</p>
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
