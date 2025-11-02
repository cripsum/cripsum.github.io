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
            img {
                border-radius: 10px;
            }
            .logodesc {
                margin-top: 1rem;
            }

        body {
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0a0a0a 100%);
            background-attachment: fixed;
            background-repeat: no-repeat;
            color: #ffffff;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .introduzione-edit-section {
            padding: 120px 0 60px;
            text-align: center;
            position: relative;
        }

        .introduzione-edit-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 30%, rgba(100, 200, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .introduzione-edit-title {
            font-size: clamp(2.5rem, 8vw, 5rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #64c8ff 50%, #ff64c8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 3s ease-in-out infinite;
            position: relative;
            z-index: 2;
        }

        .introduzione-edit-subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            z-index: 2;
        }

        .tiktok-link {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #ff0050, #ff4081);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(255, 0, 80, 0.3);
            position: relative;
            z-index: 2;
        }

        .tiktok-link:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 36px rgba(255, 0, 80, 0.5);
            color: white;
        }

        .edits-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        .edits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
            width: 100%;
        }

        .edit-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            cursor: pointer;
            opacity: 0;
            transform: translateY(40px);
            animation: fadeInUp 0.8s ease forwards;
            width: 100%;
            min-width: 0; 
        }

        .edit-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.05) 0%, rgba(255, 100, 200, 0.03) 50%, rgba(100, 255, 150, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            border-radius: 24px;
        }

        .edit-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px rgba(100, 200, 255, 0.15);
            border-color: rgba(100, 200, 255, 0.3);
        }

        .edit-card:hover::before {
            opacity: 1;
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 400px;
            overflow: hidden;
            border-radius: 20px 20px 0 0;
            background: linear-gradient(135deg, rgba(30, 32, 42, 0.8) 0%, rgba(40, 45, 60, 0.8) 100%);
        }

        .video-iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 20px 20px 0 0;
            transition: all 0.3s ease;
        }

        .edit-card:hover .video-iframe {
            transform: scale(1.02);
        }

        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(30, 32, 42, 0.7), rgba(0, 0, 0, 0.5));
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .edit-card:hover .video-overlay {
            opacity: 1;
        }

        .play-button {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #0a0a0a;
            transform: scale(0.8);
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .edit-card:hover .play-button {
            transform: scale(1);
        }

        .edit-info {
            padding: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .character-name {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
        }

        .character-icon {
            width: 20px;
            height: 20px;
            margin-right: 0.5rem;
            opacity: 0.8;
        }

        .music-info {
            display: flex;
            align-items: center;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.4;
        }

        .music-icon {
            width: 18px;
            height: 18px;
            margin-right: 0.5rem;
            opacity: 0.7;
        }

        .edit-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, #ff6b6b, #ffa726);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
            z-index: 3;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shimmer {
            0%, 100% {
                filter: hue-rotate(0deg);
            }
            50% {
                filter: hue-rotate(180deg);
            }
        }

        @media (max-width: 768px) {
            .edits-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem 0;
            }

            .edits-container {
                padding: 0 1rem;
            }

            .introduzione-edit-section {
                padding: 100px 0 40px;
            }

            .video-container {
                height: 300px;
            }
        }

        @media (max-width: 480px) {
            .edit-card {
                border-radius: 20px;
            }

            .video-container {
                height: 250px;
                border-radius: 16px 16px 0 0;
            }

            .edit-info {
                padding: 1.25rem;
            }
        }

        .edit-card:nth-child(1) { animation-delay: 0.1s; }
        .edit-card:nth-child(2) { animation-delay: 0.2s; }
        .edit-card:nth-child(3) { animation-delay: 0.3s; }
        .edit-card:nth-child(4) { animation-delay: 0.4s; }
        .edit-card:nth-child(5) { animation-delay: 0.5s; }
        .edit-card:nth-child(6) { animation-delay: 0.6s; }
        .edit-card:nth-child(7) { animation-delay: 0.7s; }
        .edit-card:nth-child(8) { animation-delay: 0.8s; }
        .edit-card:nth-child(9) { animation-delay: 0.9s; }
        .edit-card:nth-child(10) { animation-delay: 1.0s; }
        .edit-card:nth-child(11) { animation-delay: 1.1s; }
        .edit-card:nth-child(12) { animation-delay: 1.2s; }

        .filter-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            margin-bottom: 2rem;
        }

        .filter-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(255, 100, 200, 0.15));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .edit-card.playing .video-overlay {
            opacity: 0;
            pointer-events: none;
        }

        .edit-card.playing {
            border-color: rgba(100, 200, 255, 0.5);
            box-shadow: 0 0 30px rgba(100, 200, 255, 0.3);
        }
        </style>
        <?php include '../includes/head-import.php'; ?>
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
                Each edit is made with passion and attention to details :P
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
            <div class="edit-card" data-category="games" onclick="playVideo(this, 28)">
                <div class="edit-badge">Latest - Collab</div>
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
                        <i class="fas fa-user character-icon"></i>
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


            document.addEventListener("click", function (e) {
                if (!e.target.closest(".edit-card")) {
                    document.querySelectorAll('.edit-card').forEach(c => c.classList.remove('playing'));
                    
                    if (window.clearCurrentEdit) {
                        window.clearCurrentEdit();
                    }
                }
            });

            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const hero = document.querySelector('.introduzione-edit-section');
                if (hero) {
                    hero.style.transform = `translateY(${scrolled * 0.5}px)`;
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
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
