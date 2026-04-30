<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Chi siamo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/chisiamo/chisiamo-colors.css?v=2.2-original-cards-colors">
    <style>
        @font-face {
            font-family: NotoColorEmojiLimited;
            unicode-range: U+1F1E6-1F1FF;
            src: url(https://raw.githack.com/googlefonts/noto-emoji/main/fonts/NotoColorEmoji.ttf);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background:
                radial-gradient(circle at 8% 6%, rgba(47, 107, 255, .24), transparent 30rem),
                radial-gradient(circle at 92% 18%, rgba(139, 92, 246, .16), transparent 28rem),
                linear-gradient(135deg, #05070d 0%, #0a0e1a 100%);
            background-attachment: fixed;
            background-repeat: no-repeat;
            background-size: cover;
            min-height: 100vh;
            color: #ffffff;
            overflow-x: hidden;
        }
    </style>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="main-container fadeup" style="padding-top: 7rem">
        <section class="chisiamo-section" style="border-radius: 20px;">
            <h1 class="chisiamo-title">Il Nostro Team di Sviluppo</h1>
            <p class="chisiamo-subtitle">
                Vuoi far parte del nostro team di sviluppo? Manda una e-mail allegando immagine, nome e descrizione, e se vuoi, un username o un link social per i crediti.
            </p>
        </section>

        <section class="team-section">
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

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/nauz.png" alt="nauz" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="https://www.tiktok.com/@nauz_aep">Nauz</a>
                            </h3>
                            <p class="member-description">
                                il più fico (e terrone)
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/lexus.jpg" alt="lexus" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="https://e-z.bio/lexus">Lexus</a>
                            </h3>
                            <p class="member-description">
                                Sono fico (ti prego cripsum accettami ti giuro che se mi accetti sei più fico di me)
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/rupert.jpg" alt="rupert" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="https://cripsum.com/u/fln_papera">FLN_papera</a>
                            </h3>
                            <p class="member-description">
                                Yuzuha è solo sua, è IL RUPERT
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/sossioh.jpg" alt="Sossioh" />
                        </div>
                        <div class="member-info">
                            <h3 class="member-name">
                                <a href="https://web.sitodaking.it">Sossioh</a>
                            </h3>
                            <p class="member-description">
                                MKWMKWMKWMKWMKWMKWMKWMKWMKWMKW
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const members = document.querySelectorAll('.team-member');

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    });
                }, {
                    threshold: 0.1
                });

                members.forEach((member) => observer.observe(member));
            } else {
                members.forEach((member) => member.classList.add('is-visible'));
            }
        });
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>

</body>

</html>