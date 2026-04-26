<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

$ogDescription = 'Il team e la lore dietro Cripsum™ / GoonLand.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/chisiamo'), '#');
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Chi siamo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Chi siamo - Cripsum™">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="https://cripsum.com/img/cripsumchisiamo.jpg">
    <meta property="og:url" content="<?php echo htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="/assets/chisiamo/chisiamo.css?v=2.1-original-members">
    <script src="/assets/chisiamo/chisiamo.js?v=2.1-original-members" defer></script>
</head>

<body class="about-page about-original-members">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="about-bg" aria-hidden="true">
        <span class="about-orb about-orb--one"></span>
        <span class="about-orb about-orb--two"></span>
        <span class="about-grid"></span>
    </div>

    <div class="main-container fadeup" style="padding-top: 2rem">
        <section class="chisiamo-section">
            <h1 class="chisiamo-title">Il Nostro Team di Sviluppo</h1>
            <p class="chisiamo-subtitle">
                Vuoi far parte del nostro team di sviluppo? Manda una e-mail allegando immagine, nome e descrizione, e se vuoi, un username o un link social per i crediti.
            </p>
        </section>

        <section class="team-section" id="team">
            <div class="team-toolbar">
                <label class="team-search">
                    <i class="fas fa-search"></i>
                    <input type="search" id="teamSearch" placeholder="Cerca un membro..." autocomplete="off">
                </label>
                <button type="button" class="team-clear" id="clearTeamSearch">
                    <i class="fas fa-xmark"></i>
                    <span>Pulisci</span>
                </button>
            </div>

            <div class="team-empty" id="teamEmpty" hidden>
                <i class="fas fa-face-sad-tear"></i>
                <strong>Nessuno trovato</strong>
                <span>Prova con un altro nome.</span>
            </div>

            <div class="team-grid">
                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/cripsumchisiamo.jpg" alt="Cripsum" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/cripsum">cripsum</a>
                            </h3>
                            <p class="member-description">
                                L'imperatore del Congo, è un editor fallito che continua a sognare in grande.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/simonetussi.jpg" alt="Simone Tussi" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/simonetussi">simonetussi.ph</a>
                            </h3>
                            <p class="member-description">
                                Seguite tutti simonetussi.ph su <a href="https://instagram.com/simonetussi.ph">Instagram</a> e
                                <a href="https://tiktok.com/@simonetussi.ph">TikTok</a> per degli scatti fantastici.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/sahe.jpg" alt="Danebidev" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">danebidev</h3>
                            <p class="member-description">
                                Game developer sempre in risparmio energetico.<br>
                                <strong>JavaScript >> Java</strong> - questa è la sua filosofia di vita.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/ray.jpg" alt="Ray" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Ray</h3>
                            <p class="member-description">
                                <strong>Broke/Broken/Broker</strong><br>
                                Un trader che mangia cani e beve birra fino a stare male. Vive la vita al limite.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/barandeep.jpg" alt="Barandeep" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Barandeep</h3>
                            <p class="member-description">
                                Xenon il gigante indiano, è il project manager che tiene tutto sotto controllo con la sua presenza imponente.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/samarpreet.jpg" alt="Scammarpreet" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Scammarpreet</h3>
                            <p class="member-description">
                                <strong>Money grabber • Scammer • Guru • Doxer</strong><br>
                                Gambler professionista che sa come far girare i soldi.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/houshou_marine.jpeg" alt="Tacos" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/tsundere_nyan">Tsundere Nyan</a>
                            </h3>
                            <p class="member-description">
                                <strong>Cantarella</strong> è solo sua e <strong>Cipher</strong> l'ha già ingravidata, è un grande amante dei gacha e del gooning. Si è aggiudicato il primo posto in <a href="goonland/goon-generator">Goonland</a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/cossu.jpg" alt="Cossu" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Cossu</h3>
                            <p class="member-description">
                                <strong>Lontrone spermatozoico</strong> (ama le lontre)<br>
                                Ama le frittate con la banana e ha gusti culinari... particolari.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/photo_2023-11-14_17-21-10.jpg" alt="Zakator" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/zakator">Zakator</a>
                            </h3>
                            <p class="member-description">
                                Grande ascoltatore di musica anime e phonk, è un hackerino fallito che non si arrende mai.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/salsina.jpg" alt="Xalx Andrea" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="../user/salsina">Xalx Andrea</a>
                            </h3>
                            <p class="member-description">
                                Il player + tossico di tutto <strong>Yokai Watch</strong><br>
                                Boh è anoressico ma almeno sa giocare.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/mabbon.jpg" alt="Mabbon" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Mabbon</h3>
                            <p class="member-description">
                                Un ragazzo sfruttato e sottopagato. Forse perché è ne-... meglio non finire la frase.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/lollolapulce.jpg" alt="LolloLaPulce" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">LolloLaPulce</h3>
                            <p class="member-description">
                                Addetto alla depressione e grande giocatore di Minecraft. Costruisce mentre piange.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/zazzo.png" alt="Zazzo" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">Zazzo</h3>
                            <p class="member-description">
                                Scarso in culo in tutti i videogiochi, ma tifa la MAGGICA ROMA
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="join-team-section">
            <h2 class="join-title">Unisciti al Team!</h2>
            <p class="join-description">
                Hai quello che serve per far parte della squadra più king del web?
                Mandaci la tua candidatura e diventa parte della squadra + da king del secolo!
            </p>
            <a href="candidatura-chisiamo" class="join-email">
                <i class="fas fa-envelope me-2"></i>
                clicca qui per inviare la tua candidatura
            </a>
        </section>
    </div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
