<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <style>
    </style>
    <?php include '../includes/head-import.php'; ?>
    <link rel="stylesheet" href="../css/edits.css?v=2">
    <title>Cripsum™ - edits</title>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>
    <div class="introduzione-edit-section fadeup" style="padding-top: 10rem;">
        <div class="container">
            <h1 class="introduzione-edit-title">My Latest Edits</h1>
            <p class="introduzione-edit-subtitle">
                Discover my latest edits.
                Each edit is made with passion and attention to details :P (non è vero)
            </p>
            <a href="https://tiktok.com/@cripsum" class="tiktok-link" target="_blank">
                <i class="fab fa-tiktok me-2"></i>
                Watch All My Edits on TikTok
            </a>
        </div>
    </div>

    <div class="filter-section fadeup">
        <div class="filter-container">
            <a href="#" class="filter-btn active" data-filter="all">All Edits</a>
            <a href="#" class="filter-btn" data-filter="anime">Anime</a>
            <a href="#" class="filter-btn" data-filter="games">Games</a>
            <a href="#" class="filter-btn" data-filter="sports">Sports</a>
            <a href="#" class="filter-btn" data-filter="movies">Movies & TV</a>
            <a href="#" class="filter-btn" data-filter="influencer">Influencer</a>
        </div>
    </div>

    <div class="edits-container">
        <div class="edits-grid">
            <div class="edit-card" data-category="games" onclick="playVideo(this, 29)">
                <div class="edit-badge">Latest</div>
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/934apl?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-29">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-star character-icon"></i>
                        Danil Showman
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Sto bene al mare - Marco Mengoni
                    </div>
                </div>
            </div>
            <div class="edit-card" data-category="influencer" onclick="playVideo(this, 28)">
                <div class="edit-badge">Collab</div>
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/kez9r2?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-28">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        <p>The herta & Sparkle - HSR <br> (collab con <a href="https://www.tiktok.com/@nauz_aep" class="linkbianco">Nauz</a>)</p>
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        TWICE - Strategy
                    </div>
                </div>
            </div>
            <div class="edit-card" data-category="anime" onclick="playVideo(this, 27)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/io9mwe?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-27">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Kōtarō Bokuto - Haikyuu
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        QMIIR - Sempero
                    </div>
                </div>
            </div>
            <div class="edit-card" data-category="games" onclick="playVideo(this, 26)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/ypekqr?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-26">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Iuno - Wuthering Waves
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        XYLØ - Afterlife (Ark Patrol Remix)
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 25)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/rh84rz?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-25">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Perfect Cell - DragonBall
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Jmilton, CHASHKAKEFIRA - Reinado
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 24)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/41cdia?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-24">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Waguri Kaoruko
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Tate McRae - it's ok i'm ok
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="games" onclick="playVideo(this, 23)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/xzj4ag?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-23">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Evelyn - Zenless Zone Zero
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Charli XCX - Track 10
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="games" onclick="playVideo(this, 22)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/tfs4nt?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-22">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Shorekeeper - Wuthering Waves
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Irokz - Toxic Potion (slowed)
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 21)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/lowaxh?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-21">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Karane Inda
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Katy Perry - Harleys in Hawaii
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="games" onclick="playVideo(this, 20)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/8iv09j?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-20">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Dante - Devil May Cry
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        ATLXS - PASSO BEM SOLTO (super slowed)
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 19)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/gyfwer?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-19">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Sung Jin-Woo - Solo Levelling
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Peak - Re-Up
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 18)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/1n4azs?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-18">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Nagi - Blue Lock
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        One of the girls X good for you
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="games" onclick="playVideo(this, 17)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/79a35r?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-17">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Cool Mita / Cappie - MiSide
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Bruno Mars - Treasure
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="games" onclick="playVideo(this, 16)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/1j8bd8?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-16">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Crazy Mita - MiSide
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Imogen Heap - Headlock
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 15)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/nkccr6?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-15">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Yuki Suou - Roshidere
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Rarin - Mamacita
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 14)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/wy68h4?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-14">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Alya Kujou - Roshidere
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Clean Bandit - Solo
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 13)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/gyfwui?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-13">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Alya Kujou - Roshidere
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Subway Surfers phonk trend
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="influencer" onclick="playVideo(this, 12)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/pdcav0?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-12">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-star character-icon"></i>
                        Luca Arlia (meme)
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Luca Carboni - Luca lo stesso
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 11)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/mx6h2n?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-11">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Yuki Suou - Roshidere
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        PnB Rock - Unforgettable (Freestyle)
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 10)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/ml3dve?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-10">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Alya Kujou - Roshidere
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Rarin & Frozy - Kompa
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="sports" onclick="playVideo(this, 9)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/bf8j16?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-9">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-futbol character-icon"></i>
                        Cristiano Ronaldo
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        G-Eazy - Tumblr Girls
                    </div>
                </div>
            </div>



            <div class="edit-card" data-category="games" onclick="playVideo(this, 8)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/zjyoct?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-8">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Mandy - Brawl Stars
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        NCTS - NEXT!
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="anime" onclick="playVideo(this, 7)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/bllcn8?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-7">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-user character-icon"></i>
                        Choso - Jujutsu Kaisen
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        The Weeknd - Is There Someone Else?
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="influencer" onclick="playVideo(this, 6)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/r2bppn?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-6">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-star character-icon"></i>
                        Nym
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Chris Brown - Under the influence
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="games" onclick="playVideo(this, 5)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/zd75uc?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-5">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-gamepad character-icon"></i>
                        Mortis - Brawl Stars
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        DJ FNK - Slide da Treme Melódica v2
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="influencer" onclick="playVideo(this, 4)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/r9ygoy?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-4">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-star character-icon"></i>
                        Nino balletto tattico
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Zara Larsson - Lush Life
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="influencer" onclick="playVideo(this, 3)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/vnqxdt?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-3">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-star character-icon"></i>
                        Mates - Crossbar challenge
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        G-Eazy - Lady Killers II
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="movies" onclick="playVideo(this, 2)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/htbn8k?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-2">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-film character-icon"></i>
                        Homelander - The Boys
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        MGMT - Little Dark Age
                    </div>
                </div>
            </div>

            <div class="edit-card" data-category="movies" onclick="playVideo(this, 1)">
                <div class="video-container">
                    <iframe
                        src="https://streamable.com/e/x40nn6?"
                        class="video-iframe"
                        allow="fullscreen;autoplay"
                        allowfullscreen
                        id="video-1">
                    </iframe>
                    <div class="video-overlay">
                        <div class="play-button">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="edit-info">
                    <div class="character-name">
                        <i class="fas fa-film character-icon"></i>
                        Heisenberg - Breaking Bad
                    </div>
                    <div class="music-info">
                        <i class="fas fa-music music-icon"></i>
                        Travis Scott - MY EYES

                    </div>
                </div>
            </div>

        </div>
    </div>


    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement" />
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            unlockAchievement(6);

            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();

                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    const filter = btn.dataset.filter;
                    const cards = document.querySelectorAll('.edit-card');

                    cards.forEach(card => {
                        const categories = card.dataset.category;
                        let shouldShow = false;

                        if (filter === 'all') {
                            shouldShow = true;
                        } else {
                            const cardCategories = categories.split(/[\s,]+/).map(cat => cat.trim());
                            shouldShow = cardCategories.includes(filter);
                        }

                        if (shouldShow) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });

        function playVideo(card, id) {
            document.querySelectorAll('.edit-card').forEach(c => c.classList.remove('playing'));

            card.classList.add('playing');

            const iframe = card.querySelector('.video-iframe');

            const currentSrc = iframe.src;
            iframe.src = '';
            setTimeout(() => {
                iframe.src = currentSrc + (currentSrc.includes('?') ? '&' : '?') + 'autoplay=1';
            }, 100);

            if (window.setCurrentEdit) {
                window.setCurrentEdit(id);
            }

            let watchedVideos = getVideo("watchedVideos") || [];
            if (!watchedVideos.includes(id)) {
                watchedVideos.push(id);
                setVideo("watchedVideos", watchedVideos);

                let totalVideos = document.querySelectorAll(".video-iframe").length;
                if (watchedVideos.length === totalVideos) {
                    unlockAchievement(17);
                }
            }
        }

        function getVideo(name) {
            const cookies = document.cookie.split("; ");
            for (let cookie of cookies) {
                let [key, value] = cookie.split("=");
                if (key === name) return JSON.parse(value);
            }
            return null;
        }

        function setVideo(name, value) {
            document.cookie = `${name}=${JSON.stringify(value)}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT`;
        }


        document.addEventListener("click", function(e) {
            if (!e.target.closest(".edit-card")) {
                document.querySelectorAll('.edit-card').forEach(c => c.classList.remove('playing'));

                if (window.clearCurrentEdit) {
                    window.clearCurrentEdit();
                }
            }
        });

        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const edits = document.querySelector('.introduzione-edit-section');
            if (edits) {
                edits.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });

        (function() {
            const originalError = console.error;
            console.error = function(...args) {
                if (args.some(a => typeof a === "string" && a.includes("socket.streamable.com"))) {
                    return;
                }
                originalError.apply(console, args);
            };

            const originalLog = console.log;
            console.log = function(...args) {
                if (args.some(a => typeof a === "string" && a.includes("Websocket error"))) {
                    return;
                }
                originalLog.apply(console, args);
            };
        })();
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../js/modeChanger.js"></script>
</body>

</html>