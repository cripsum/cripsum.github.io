<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - About us</title>
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


    <div class="main-container fadeup" style="padding-top: 7rem">
        <section class="chisiamo-section" style="border-radius: 20px;">
            <h1 class="chisiamo-title">Our Development Team</h1>
            <p class="chisiamo-subtitle">
                Want to be part of our development team? Send an email with your image, name, and description, and if you want, a username or social link for credits.
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
                                The emperor of Congo, he's a failed editor who keeps dreaming big.
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
                                Follow simonetussi.ph on <a href="https://instagram.com/simonetussi.ph">Instagram</a> and
                                <a href="https://tiktok.com/@simonetussi.ph">TikTok</a> for amazing shots.
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
                                Game developer always in energy-saving mode.<br>
                                <strong>JavaScript >> Java</strong> - this is his life philosophy.
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
                                A trader who eats dogs and drinks beer until he feels sick. Lives life on the edge.
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
                                Xenon the Indian giant, is the project manager who keeps everything under control with his imposing presence.
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
                                Professional gambler who knows how to make money work.
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
                                <strong>Cantarella</strong> is only his and has already impregnated <strong>Cipher</strong>, he is a huge fan of gacha and gooning. He won first place in <a href="goonland/goon-generator">Goonland</a>
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
                                <strong>Spermatozoic Big Otter</strong> (loves otters)<br>
                                He loves banana omelettes and has... peculiar culinary tastes.
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
                                A big listener of anime and phonk music, he is a failed little hacker who never gives up.
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
                                The most toxic player in all of <strong>Yokai Watch</strong><br>
                                idk, he's anorexic but at least he knows how to play.
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
                                An exploited and underpaid guy. Maybe because he's bla-... better not finish the sentence.
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
                                In charge of depression and a huge Minecraft player. Builds while crying.
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
                                He's ass at all video games, but supports the MAGGICA ROMA
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
                                the coolest (and terrone)
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
                                I'm cool (please Cripsum accept me I swear if you accept me you're cooler than me)
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
                                Yuzuha is only his, he is THE RUPERT
                            </p>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-content">
                        <div class="member-image">
                            <img src="../img/sossioh.png" alt="Sossioh" />
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
            <h2 class="join-title">Join the Team!</h2>
            <p class="join-description">
                Do you have what it takes to be part of the coolest squad on the web?
                Send us your application and become part of the coolest squad of the century!
            </p>
            <a href="candidatura-chisiamo" class="join-email">
                <i class="fas fa-envelope me-2"></i>
                click here to send your application
            </a>
        </section>
    </div>

    <?php include '../includes/scroll_indicator.php'; ?>

    <?php include '../includes/footer-en.php'; ?>

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