<?php

/**
 * Homepage.
 *
 * Inclusa da it/home.php e en/home.php, che preparano i dati: qui si decide
 * solo come vengono mostrati, una volta per le due lingue. La pagina che
 * include imposta $homeLang ('it' o 'en').
 *
 * Tutto quello che va calcolato sta in questo primo blocco; sotto c'e' solo
 * markup che stampa variabili gia' pronte.
 *
 * La fila dei supporter usa le classi di sempre (.supporter-card,
 * .supporters-grid) perche' sono quelle che cerca js/home-supporters.js,
 * riusato qui senza modifiche; da assets/home-v5/home.js arrivano ancora la
 * comparsa allo scorrimento e il contatore di Discord. Lo stile invece e'
 * tutto in assets/home-next/home.css.
 */

$hxIsEn = ($homeLang ?? 'it') === 'en';
$hxLang = $hxIsEn ? 'en' : 'it';

$hx = $hxIsEn
    ? [
        'meta_title'   => 'Cripsum™ — memes, edits, lootboxes and community profiles',
        'title'        => 'Welcome to the best site in the Congo.',
        'lead'         => 'Editing, memes, lootboxes, profiles, achievements, community posts and many secrets. What are you waiting for? Join us!',
        'question'     => 'Are you over 25 and own a PC?',
        'profile'      => 'Go to profile',
        'signup'       => 'Sign up',
        'login'        => 'Login',
        'login_close'  => 'Log in',
        'news'         => 'News',
        'mood_label'   => 'Site moods',
        'moods'        => ['Happiness', 'Sadness', 'Amazement'],
        'feat_title'   => 'What you can do on Cripsum™',
        'feat_label'   => 'Sections of the site',
        'feat_prev'    => 'Previous',
        'feat_next'    => 'Next',
        'feat_pause'   => 'Pause',
        'feat_play'    => 'Resume',
        'feat_open'    => 'Open',
        'prem_title'   => 'Unlock the Ultimate Cripsum™ Experience',
        'prem_cta'     => 'Get Premium',
        'supp_title'   => 'Our Premium Supporters',
        'supp_lead'    => 'A big thanks to the users who support Cripsum™!',
        'supp_one'     => 'supporter',
        'supp_many'    => 'supporters',
        'supp_you'     => 'You',
        'supp_slot'    => 'Your spot',
        'supp_slot_label' => 'Your spot: get Premium',
        'close_title'  => 'Got a Cripsum™ account?',
        'close_lead'   => 'Your account unlocks your profile, chat, lootboxes, and achievements.',
        'joke'         => 'Grab your free V-bucks here!!!!',
        'hero_alt'     => 'The Cripsum logo: four coloured characters on a hill',
    ]
    : [
        'meta_title'   => 'Cripsum™ — meme, edit, lootbox e profili della community',
        'title'        => 'Benvenuto/a nel sito migliore del Congo.',
        'lead'         => 'Editing, meme, lootbox, profili, achievements, post della community e tanti segreti, cosa aspetti a unirti?',
        'question'     => 'Hai più di 25 anni e possiedi un PC?',
        'profile'      => 'Vai al profilo',
        'signup'       => 'Registrati',
        'login'        => 'Accedi',
        'login_close'  => 'Accedi',
        'news'         => 'Novità',
        'mood_label'   => 'Mood del sito',
        'moods'        => ['Felicità', 'Tristezza', 'Stupore'],
        'feat_title'   => 'Cosa puoi fare su Cripsum™',
        'feat_label'   => 'Sezioni del sito',
        'feat_prev'    => 'Precedente',
        'feat_next'    => 'Successiva',
        'feat_pause'   => 'Metti in pausa',
        'feat_play'    => 'Riprendi',
        'feat_open'    => 'Apri',
        'prem_title'   => 'Sblocca l\'esperienza Cripsum™ definitiva',
        'prem_cta'     => 'Diventa Premium',
        'supp_title'   => 'I nostri Supporter Premium',
        'supp_lead'    => 'Un grazie speciale agli utenti che supportano Cripsum™!',
        'supp_one'     => 'supporter',
        'supp_many'    => 'supporter',
        'supp_you'     => 'Tu',
        'supp_slot'    => 'Il tuo posto',
        'supp_slot_label' => 'Il tuo posto: diventa Premium',
        'close_title'  => 'Hai un account Cripsum™?',
        'close_lead'   => 'Con l’account usi profilo, chat, lootbox e achievement.',
        'joke'         => 'Clicca qui per V-bucks gratis!!!!',
        'hero_alt'     => 'Il logo di Cripsum: quattro personaggi colorati su una collina',
    ];

// Prezzo, condizioni e vantaggi del Premium sono gli stessi che mostra il
// checkout: stanno in un file solo, cosi' non possono dire due cose diverse.
require_once __DIR__ . '/premium_copy.php';
$hxPremium = cripsum_premium_copy($hxLang);
$hx['prem_lead'] = $hxPremium['lead'];
$hx['prem_price'] = $hxPremium['price'];
$hx['prem_terms'] = $hxPremium['terms'];
$hx['perks'] = $hxPremium['perks'];

// Il titolo entra una parola alla volta: ogni parola ha la sua maschera.
$hxWords = preg_split('/\s+/u', trim($hx['title'])) ?: [$hx['title']];

$hxError = (string)($_SESSION['error_message'] ?? '');
unset($_SESSION['error_message']);

$hxIsLogged = !empty($isLoggedIn) && !empty($currentUsername);
$hxIsPremium = !empty($isPremium);
$hxProfileUrl = (string)($profileUrl ?? 'accedi');

// Le slide del pannello admin; se mancano, quelle di riserva.
$hxSlides = [];
foreach ((!empty($homeSlides) ? $homeSlides : home_slides_fallback($hxLang)) as $hxSlide) {
    $hxSlides[] = [
        'media' => (string)$hxSlide['media'],
        'title' => (string)$hxSlide['title'],
        'description' => (string)($hxSlide['description'] ?? ''),
        'link' => (string)($hxSlide['link'] ?? '') !== '' ? (string)$hxSlide['link'] : '#',
        'cta' => (string)($hxSlide['buttonText'] ?? '') !== '' ? (string)$hxSlide['buttonText'] : $hx['feat_open'],
    ];
}
$hxFirst = $hxSlides[0] ?? null;
$hxSlideTotal = sprintf('%02d', count($hxSlides));

// Chi guarda da Premium ha il suo posto fisso accanto alla fila: li' dentro
// non lo si ripete.
$hxViewerId = $hxIsPremium ? (int)($_SESSION['user_id'] ?? 0) : 0;
$hxSupporters = [];
foreach (($supporters ?? []) as $hxRow) {
    if ($hxViewerId !== 0 && (int)$hxRow['id'] === $hxViewerId) {
        continue;
    }

    $hxUseDiscord = (int)($hxRow['discord_use_display_name'] ?? 0) === 1;
    $hxDiscord = trim((string)($hxRow['discord_global_name'] ?? '')) ?: trim((string)($hxRow['discord_username'] ?? ''));
    $hxStamp = !empty($hxRow['profile_updated_at']) ? strtotime((string)$hxRow['profile_updated_at']) : time();

    $hxSupporters[] = [
        'href' => '/u/' . rawurlencode(strtolower((string)$hxRow['username'])),
        'name' => ($hxUseDiscord && $hxDiscord !== '') ? $hxDiscord : (trim((string)($hxRow['display_name'] ?? '')) ?: (string)$hxRow['username']),
        'pfp' => '/includes/get_pfp.php?id=' . (int)$hxRow['id'] . '&t=' . (int)$hxStamp . '&size=96',
        'color' => !empty($hxRow['accent_color']) ? (string)$hxRow['accent_color'] : '#6e6e73',
    ];
}

$hxHasSupporters = !empty($supporters);
$hxTotal = (int)($supportersTotal ?? count($supporters ?? []));
$hxTotalText = $hxIsEn ? number_format($hxTotal) : number_format($hxTotal, 0, ',', '.');
$hxTotalLabel = $hxTotal === 1 ? $hx['supp_one'] : $hx['supp_many'];

$hxViewerStamp = !empty($viewerRow['profile_updated_at']) ? strtotime((string)$viewerRow['profile_updated_at']) : time();
$hxViewerPfp = '/includes/get_pfp.php?id=' . $hxViewerId . '&t=' . (int)$hxViewerStamp . '&size=96';
$hxViewerColor = !empty($viewerRow['accent_color']) ? (string)$viewerRow['accent_color'] : '#6e6e73';

$hxPerkIcon = [
    'coin' => '<img src="/img/godos-icon.png" alt="" width="22" height="22">',
    'gem' => '<img src="/img/premium.svg" alt="" width="22" height="22">',
];
$hxChevron = '<svg class="home-link__chev" viewBox="0 0 8 14" aria-hidden="true"><path d="M1.5 1.5 7 7l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$hxArrow = '<svg class="home-btn__chev" viewBox="0 0 8 14" aria-hidden="true"><path d="M1.5 1.5 7 7l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$hxPaddleIcon = '<svg viewBox="0 0 8 14" aria-hidden="true"><path d="M1.5 1.5 7 7l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';

// La riga dello scherzo e' la stessa frase ripetuta: otto volte bastano a
// riempire anche uno schermo largo, e devono essere pari perche' il giro
// ricomincia a meta'.
$hxJokeRepeats = range(1, 8);

$hxHomeCss = cripsum_asset('/assets/home-next/home.css');
$hxHomeJs = cripsum_asset('/assets/home-next/home.js');
$hxSharedJs = cripsum_asset('/assets/home-v5/home.js');
$hxFooter = __DIR__ . ($hxIsEn ? '/footer-en.php' : '/footer.php');
?>
<!DOCTYPE html>
<html lang="<?= $hxLang ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include __DIR__ . '/head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <title data-i18n="meta.title"><?= home_h($hx['meta_title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link rel="preload" as="image" href="/img/amongus-logo.jpg">
    <link rel="stylesheet" href="<?= home_h($hxHomeCss) ?>">
    <link rel="stylesheet" href="<?= home_h(cripsum_asset('/assets/news/news-popup.css')) ?>">
    <?php /* L'ingresso della testata aspetta il carattere. Se Poppins arriva mentre le
             parole del titolo stanno salendo, il titolo cambia righe a meta' e tutto
             quello che c'e' sotto fa un salto: sui telefoni si vedeva come uno
             sfarfallio. Finche' c'e' .home-wait le animazioni restano ferme
             all'inizio (home.css); dopo un secondo e mezzo partono comunque. */ ?>
    <script>
        (function () {
            var root = document.documentElement;
            if (!document.fonts || !document.fonts.load) return;
            var go = function () { root.classList.remove('home-wait'); };
            root.classList.add('home-wait');
            Promise.all(['600', '500', 'italic 500', '400'].map(function (face) {
                return document.fonts.load(face + ' 1em Poppins');
            })).then(go, go);
            setTimeout(go, 1500);
        })();
    </script>
    <?php /* Senza script niente farebbe comparire le sezioni: le si mostra subito. */ ?>
    <noscript><style>.home-reveal { opacity: 1 !important; filter: none !important; transform: none !important; }</style></noscript>
    <script src="<?= home_h($hxSharedJs) ?>" defer></script>
    <script src="<?= home_h($hxHomeJs) ?>" defer></script>
    <script src="/assets/news/news-popup.js?v=1.1" defer></script>
</head>

<body class="home-next-body">
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="home-next">
        <?php if ($hxError !== ''): ?>
            <div class="home-alert" role="alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= home_h($hxError) ?></span>
            </div>
        <?php endif; ?>

        <section class="home-hero home-wrap">
            <h1 class="home-hero__title" aria-label="<?= home_h($hx['title']) ?>">
                <?php foreach ($hxWords as $hxIndex => $hxWord): ?>
                    <span class="home-word" aria-hidden="true"><span style="--i: <?= $hxIndex ?>"><?= home_h($hxWord) ?></span></span>
                <?php endforeach; ?>
            </h1>
            <p class="home-hero__lead"><?= home_h($hx['lead']) ?></p>
            <p class="home-hero__question"><?= home_h($hx['question']) ?></p>

            <div class="home-actions">
                <?php if ($hxIsLogged): ?>
                    <a class="home-btn home-btn--primary" href="<?= home_h($hxProfileUrl) ?>"><span><?= home_h($hx['profile']) ?></span></a>
                <?php else: ?>
                    <a class="home-btn home-btn--primary" href="registrati"><span><?= home_h($hx['signup']) ?></span></a>
                    <a class="home-link" href="accedi"><span class="home-link__label"><?= home_h($hx['login']) ?></span><?= $hxChevron ?></a>
                <?php endif; ?>
                <button class="home-link" type="button" onclick="if (window.newsPopup) window.newsPopup.open();"><span class="home-link__label"><?= home_h($hx['news']) ?></span><?= $hxChevron ?></button>
            </div>

            <figure class="home-hero__media home-tile">
                <img src="/img/amongus-logo.jpg" alt="<?= home_h($hx['hero_alt']) ?>" width="750" height="504">
            </figure>
        </section>

        <section class="home-mood home-wrap" aria-label="<?= home_h($hx['mood_label']) ?>">
            <figure>
                <div class="home-tile home-reveal"><img src="/img/felicita.jpg" alt=""></div>
                <figcaption class="home-reveal" style="--d: 160"><?= home_h($hx['moods'][0]) ?></figcaption>
            </figure>
            <figure>
                <div class="home-tile home-reveal" style="--d: 110"><img src="/img/tristezza.jpg" alt=""></div>
                <figcaption class="home-reveal" style="--d: 270"><?= home_h($hx['moods'][1]) ?></figcaption>
            </figure>
            <figure>
                <div class="home-tile home-reveal" style="--d: 220"><img src="/img/stupore.jpg" alt=""></div>
                <figcaption class="home-reveal" style="--d: 380"><?= home_h($hx['moods'][2]) ?></figcaption>
            </figure>
        </section>

        <?php if ($hxFirst !== null): ?>
            <section class="home-block home-wrap" aria-labelledby="homeFeatTitle">
                <div class="home-head home-showcase__head home-reveal">
                    <h2 id="homeFeatTitle"><?= home_h($hx['feat_title']) ?></h2>
                    <?php /* Nascosti finche' lo script non li rende utili. */ ?>
                    <div class="home-showcase__controls" id="homeShowcaseControls" hidden>
                        <button type="button" class="home-paddle home-paddle--step home-paddle--prev" id="homeShowcasePrev" aria-label="<?= home_h($hx['feat_prev']) ?>"><?= $hxPaddleIcon ?></button>
                        <button type="button" class="home-paddle" id="homeShowcasePause" aria-label="<?= home_h($hx['feat_pause']) ?>" data-label-pause="<?= home_h($hx['feat_pause']) ?>" data-label-play="<?= home_h($hx['feat_play']) ?>"><i class="fa-solid fa-pause" aria-hidden="true"></i></button>
                        <button type="button" class="home-paddle home-paddle--step" id="homeShowcaseNext" aria-label="<?= home_h($hx['feat_next']) ?>"><?= $hxPaddleIcon ?></button>
                    </div>
                </div>

                <?php /* Una voce alla volta in grande, e sotto tutte le altre in
                         miniatura. La prima sta gia' nell'HTML e ogni miniatura e'
                         un link vero alla sua pagina: senza script la sezione
                         resta usabile. Il resto lo fa home-next/home.js, che
                         legge testi e immagini dagli attributi delle miniature. */ ?>
                <div class="home-showcase home-reveal" id="homeShowcase" style="--d: 120">
                    <div class="home-stage" id="homeStage">
                        <div class="home-stage__copy" id="homeStageCopy">
                            <p class="home-stage__count" aria-hidden="true"><span id="homeStageIndex">01</span> / <?= home_h($hxSlideTotal) ?></p>
                            <h3 class="home-stage__title" id="homeStageTitle"><?= home_h($hxFirst['title']) ?></h3>
                            <p class="home-stage__text" id="homeStageText"><?= home_h($hxFirst['description']) ?></p>
                            <a class="home-btn home-btn--primary" id="homeStageLink" href="<?= home_h($hxFirst['link']) ?>"><span id="homeStageCta"><?= home_h($hxFirst['cta']) ?></span><?= $hxArrow ?></a>
                        </div>
                        <?php /* Anche la foto porta alla pagina, ma chi usa la tastiera
                                 o un lettore di schermo ha gia' il pulsante accanto. */ ?>
                        <a class="home-stage__media" id="homeStageMedia" href="<?= home_h($hxFirst['link']) ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= home_h($hxFirst['media']) ?>" alt="" decoding="async">
                        </a>
                    </div>

                    <nav class="home-rail" id="homeRail" aria-label="<?= home_h($hx['feat_label']) ?>">
                        <?php foreach ($hxSlides as $hxIndex => $hxSlide): ?>
                            <a class="home-thumb<?= $hxIndex === 0 ? ' is-active' : '' ?>" href="<?= home_h($hxSlide['link']) ?>" title="<?= home_h($hxSlide['title']) ?>"<?= $hxIndex === 0 ? ' aria-current="true"' : '' ?>
                                data-title="<?= home_h($hxSlide['title']) ?>" data-text="<?= home_h($hxSlide['description']) ?>" data-cta="<?= home_h($hxSlide['cta']) ?>" data-media="<?= home_h($hxSlide['media']) ?>">
                                <span class="home-thumb__bar" aria-hidden="true"><span></span></span>
                                <span class="home-thumb__image"><img src="<?= home_h($hxSlide['media']) ?>" alt="" loading="lazy" decoding="async"></span>
                                <span class="home-thumb__label"><?= home_h($hxSlide['title']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$hxIsPremium): ?>
            <?php /* Prezzo, condizioni e vantaggi arrivano da
                     includes/premium_copy.php, come nel checkout. */ ?>
            <section class="home-prem home-wrap home-reveal" aria-labelledby="homePremiumTitle">
                <div>
                    <img class="home-prem__gem" src="/img/premium.svg" alt="" width="52" height="52">
                    <h2 id="homePremiumTitle"><?= home_h($hx['prem_title']) ?></h2>
                    <p class="home-prem__lead"><?= home_h($hx['prem_lead']) ?></p>
                    <p class="home-price"><strong><?= home_h($hx['prem_price']) ?></strong><span><?= home_h($hx['prem_terms']) ?></span></p>
                    <a class="home-btn home-btn--dark" href="checkout-premium"><span><?= home_h($hx['prem_cta']) ?></span></a>
                </div>
                <ul class="home-perks">
                    <?php foreach ($hx['perks'] as $hxPerk): ?>
                        <li><?= $hxPerkIcon[$hxPerk[0]] ?><span><?= $hxPerk[1] ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($hxHasSupporters): ?>
            <section class="home-block home-wrap" aria-labelledby="homeSupportersTitle">
                <div class="home-head home-head--center home-reveal">
                    <h2 id="homeSupportersTitle"><?= home_h($hx['supp_title']) ?></h2>
                    <p><?= home_h($hx['supp_lead']) ?></p>
                    <p class="home-count"><img src="/img/premium.svg" alt="" width="14" height="14"><span><strong><?= home_h($hxTotalText) ?></strong> <?= home_h($hxTotalLabel) ?></span></p>
                </div>

                <div class="supporters-row home-reveal" style="--d: 140">
                    <?php /* Il posto di chi guarda sta fuori dalla fila che scorre: chi
                             e' Premium ci trova se stesso, gli altri un posto vuoto
                             che porta al checkout. */ ?>
                    <div class="supporters-you">
                        <?php if ($hxIsPremium): ?>
                            <a href="<?= home_h($hxProfileUrl) ?>" class="supporter-card supporter-card--me" style="--supporter-color: <?= home_h($hxViewerColor) ?>;">
                                <span class="supporter-avatar-container"><img src="<?= home_h($hxViewerPfp) ?>" alt="" class="supporter-pfp" width="64" height="64" decoding="async"></span>
                                <span class="supporter-name"><?= home_h($hx['supp_you']) ?></span>
                            </a>
                        <?php else: ?>
                            <a href="checkout-premium" class="supporter-card supporter-card--slot" aria-label="<?= home_h($hx['supp_slot_label']) ?>">
                                <span class="supporter-avatar-container"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                                <span class="supporter-name"><?= home_h($hx['supp_slot']) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="supporters-scroll-wrapper">
                        <div class="supporters-grid">
                            <?php foreach ($hxSupporters as $hxSupporter): ?>
                                <a href="<?= home_h($hxSupporter['href']) ?>" class="supporter-card" title="<?= home_h($hxSupporter['name']) ?>" style="--supporter-color: <?= home_h($hxSupporter['color']) ?>;">
                                    <span class="supporter-avatar-container"><img src="<?= home_h($hxSupporter['pfp']) ?>" alt="" class="supporter-pfp" width="64" height="64" decoding="async"></span>
                                    <span class="supporter-name"><?= home_h($hxSupporter['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$hxIsLogged): ?>
            <section class="home-close home-wrap">
                <div class="home-head home-head--center home-reveal">
                    <h2><?= home_h($hx['close_title']) ?></h2>
                    <p><?= home_h($hx['close_lead']) ?></p>
                </div>
                <div class="home-actions home-reveal" style="--d: 120">
                    <a class="home-btn home-btn--primary" href="registrati"><span><?= home_h($hx['signup']) ?></span></a>
                    <a class="home-link" href="accedi"><span class="home-link__label"><?= home_h($hx['login_close']) ?></span><?= $hxChevron ?></a>
                    <a class="home-link" href="https://discord.gg/XdheJHVURw" data-discord-guild="1275495488229081108" target="_blank" rel="noopener"><span class="home-link__label">Discord</span><?= $hxChevron ?><span class="home-online" data-discord-count hidden></span></a>
                </div>
            </section>
        <?php endif; ?>

        <?php /* Lo scherzo dei V-bucks chiude la pagina per tutti, loggati o no. */ ?>
        <a class="home-joke" href="https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh" target="_blank" rel="noopener" aria-label="<?= home_h($hx['joke']) ?>" onclick="if (typeof unlockAchievement === 'function') unlockAchievement(10);">
            <span class="home-joke__track" aria-hidden="true">
                <?php foreach ($hxJokeRepeats as $hxRepeat): ?>
                    <span><?= home_h($hx['joke']) ?></span><i class="fa-solid fa-gift"></i>
                <?php endforeach; ?>
            </span>
        </a>
    </main>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <?php include $hxFooter; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <?php if ($hxHasSupporters): ?>
        <script src="/js/home-supporters.js?v=2.0" defer></script>
    <?php endif; ?>
</body>

</html>
