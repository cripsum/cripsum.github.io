<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
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

            @media only screen and (max-width: 756px) {
                .logodesc {
                    margin-top: 0.45rem;
                }
            }

            .video-container {
                position: relative;
                display: inline-block;
            }

            .video-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0);
                cursor: pointer;
            }
        </style>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - edits</title>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>
        <div style="margin: auto; padding-top: 120px; max-width: 1920px" class="testobianco">
            <!--<div style="max-width: 80%; margin: auto">
                <p class="text-center mt-3 testobianco">la pagina è in manutenzione, tornerà presto, per il momento puoi guardare i miei edit su tiktok</p>
                <p class="text-center mt-2"><a href="https://www.tiktok.com/@cripsum/" class="linkbianco">clicca qui per guardare i miei edit</a></p>
            </div>
            <div class="text-center mt-2">
                <img src="../img/rockstop.png" alt="" class="img-fluid infondo ombra" />
            </div>-->

            <p class="text text-center mt-3 fadein" style="font-weight: bolder">i miei ultimi edit:</p>


            <div class="d-flex justify-content-around image-container edits" style="margin: auto; padding-top: 3rem">
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(25)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/rh84rz?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="25"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Perfect Cell - DragonBall</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Jmilton, CHASHKAKEFIRA - Reinado</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(24)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/41cdia?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="24"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Waguri Kaoruko</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Tate McRae - it's ok i'm ok</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(23)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/xzj4ag?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="23"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Evelyn - Zenless Zone Zero</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Charli XCX - Track 10</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(22)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="320"
                            src="https://streamable.com/e/tfs4nt?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="22"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Shorekeeper - Wuthering Waves</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Irokz - Toxic Potion (slowed)</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(21)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/lowaxh?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="21"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Karane Inda</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Katy Perry - Harleys in Hawaii</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(20)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/8iv09j?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="20"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Dante - Devil May Cry</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> ATLXS - PASSO BEM SOLTO (super slowed)</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(19)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="180"
                            src="https://streamable.com/e/gyfwer?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="19"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Sung Jin-Woo - Solo Levelling</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Peak - Re-Up</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(18)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/1n4azs?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="18"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Nagi - Blue Lock</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> One of the girls X good for you</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(17)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="320"
                            src="https://streamable.com/e/zlj3qk?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="17"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Cool Mita / Cappie - MiSide</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Bruno Mars - Treasure</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(16)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="320"
                            src="https://streamable.com/e/1j8bd8?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="16"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Crazy Mita - MiSide</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Imogen Heap - Headlock</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(15)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/raooth?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="15"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Yuki Suou - Roshidere</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Rarin - Mamacita</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(14)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="180"
                            src="https://streamable.com/e/hvj1e1?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="14"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Alya Kujou - Roshidere</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Clean Bandit - Solo</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(13)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/a9tpgu?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="13"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Alya Kujou - Roshidere</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Subway Surfers phonk trend</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(12)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="355"
                            src="https://streamable.com/e/myq1g7?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="12"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Luca Arlia (meme)</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Luca Carboni - Luca lo stesso</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(11)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/nzfwpd?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="11"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Yuki Suou - Roshidere</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> PnB Rock - Unforgettable (Freestyle)</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(10)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/ml3dve?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="10"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Alya Kujou - Roshidere</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Rarin & Frozy - Kompa</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(9)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/cyrqyx?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="9"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Cristiano Ronaldo</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> G-Eazy - Tumblr Girls</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(8)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/zjyoct?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="8"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Mandy - Brawl Stars</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> NCTS - NEXT!</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(7)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/sef96p?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="7"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Choso - Jujutsu Kaisen</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> The Weeknd - Is There Someone Else?</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(6)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="400"
                            src="https://streamable.com/e/78el08?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="6"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Nym</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Chris Brown - Under the influence</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(5)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="355"
                            src="https://streamable.com/e/w0t9wc?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="5"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Mortis - Brawl Stars</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> DJ FNK - Slide da Treme Melódica v2</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(4)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="320"
                            src="https://streamable.com/e/ltynr9?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="4"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Nino balletto tattico</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Zara Larsson - Lush Life</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup">
                    <div onclick="watchVideo(3)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="320"
                            src="https://streamable.com/e/vnqxdt?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="3"
                            tabindex="0"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Mates - Crossbar challenge</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> G-Eazy - Lady Killers II</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup video-container">
                    <div onclick="watchVideo(2)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="320"
                            src="https://streamable.com/e/htbn8k?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="2"
                            tabindex="0"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Homelander - The Boys</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> MGMT - Little Dark Age</p>
                </div>
                <div style="margin-bottom: 30px" class="fadeup video-container">
                    <div onclick="watchVideo(1)">
                        <iframe
                            allow="fullscreen;autoplay"
                            allowfullscreen
                            height="320"
                            src="https://streamable.com/e/kxgfka?"
                            width="320"
                            class="bordobianco video-frame"
                            style="border-radius: 7px; margin: auto; display: block; margin-top: 3%; margin-left: 10px; margin-right: 10px; margin-bottom: 10px"
                            id="1"
                            tabindex="0"
                        ></iframe>
                    </div>
                    <p class="text text-center" style="margin-bottom: 0"><img src="../img/user-svgrepo-com.svg" class="logodesc" style="width: 20px" /> Heisenberg - Breaking Bad</p>
                    <p class="text text-center"><img src="../img/music-svgrepo-com.svg" class="logodesc" style="width: 20px; border-radius: 0" /> Travis Scott - MY EYES</p>
                </div>
            </div>
            <p class="text-center fadeup" style="margin-top: 20px">
                <a href="https://tiktok.com/cripsum" class="text linkbianco">clicca qui per guardare tutti i miei edit</a>
            </p>
            <!--
             -->
        </div>
        <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script>
            unlockAchievement(6);

            let totalVideos = document.querySelectorAll(".video-frame").length;

            function watchVideo(id) {
                // Imposta l'edit corrente nella rich presence
                if (window.setCurrentEdit) {
                    window.setCurrentEdit(id);
                }

                let watchedVideos = getVideo("watchedVideos") || [];
                if (!watchedVideos.includes(id)) {
                    watchedVideos.push(id);
                    setVideo("watchedVideos", watchedVideos);

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

            document.querySelectorAll(".video-frame").forEach((el) => {
                el.addEventListener("click", function () {
                    const videoId = parseInt(this.id);
                    if (window.setCurrentEdit) {
                        window.setCurrentEdit(videoId);
                        watchVideo(videoId);
                    }
                });
            });

            document.addEventListener(
                "click",
                function (e) {
                    if (!e.target.closest(".video-frame")) {
                        if (window.clearCurrentEdit) {
                            window.clearCurrentEdit();
                        }
                    }
                },
                true
            );

            /* solo se scrollo

            // Aggiungi event listener per rilevare quando l'utente esce da un video
            document.addEventListener("click", function (e) {
                // Se il click non è su un iframe o sul suo contenitore, rimuovi l'edit corrente
                if (!e.target.closest(".video-frame") && !e.target.closest('[onclick^="watchVideo"]')) {
                    if (window.clearCurrentEdit) {
                        window.clearCurrentEdit();
                    }
                }
            });

            // Opzionale: rimuovi l'edit corrente quando l'utente scrolla via dal video
            let currentVideoInView = null;
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        const iframe = entry.target;
                        const videoId = parseInt(iframe.id);

                        if (entry.isIntersecting && entry.intersectionRatio > 0.5) {
                            // Video è visibile
                            if (currentVideoInView !== videoId) {
                                currentVideoInView = videoId;
                                if (window.setCurrentEdit) {
                                    window.setCurrentEdit(videoId);
                                }
                            }
                        } else if (currentVideoInView === videoId) {
                            // Video non è più visibile
                            currentVideoInView = null;
                            if (window.clearCurrentEdit) {
                                window.clearCurrentEdit();
                            }
                        }
                    });
                },
                {
                    threshold: [0, 0.5, 1],
                }
            );

            // Osserva tutti i video frames
            document.querySelectorAll(".video-frame").forEach((iframe) => {
                observer.observe(iframe);
            });

            */
        </script>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
