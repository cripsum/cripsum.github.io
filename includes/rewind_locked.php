<?php

/**
 * Cripsum™ — Rewind: schermata di blocco
 *
 * Mostrata a chi apre /it/rewind o /en/rewind senza poterlo vedere.
 *
 * Prima il Rewind era nascosto: chi non era staff veniva rimandato in home con
 * un messaggio nella sessione, e in pratica non sapeva nemmeno che la funzione
 * esistesse. Ora è visibile a tutti e il blocco è una porta con scritto cosa
 * c'è dietro — che è anche l'unico modo perché qualcuno decida di aprirla.
 *
 * L'indirizzo resta valido: chi arriva da un link condiviso non si ritrova su
 * una pagina che non c'entra niente.
 *
 * Il markup riusa le classi di assets/rewind/rewind.css già in uso dalle
 * schermate del racconto (`rw-stage`, `rw-title`, `rw-chip`, `rw-stat`,
 * `rw-btn`): questa pagina non ha un foglio di stile proprio.
 *
 * @package Cripsum\Rewind
 */

require_once __DIR__ . '/rewind_helpers.php';

/**
 * Stampa la pagina di blocco e non torna indietro: chiama exit().
 */
function rewind_render_locked_page(string $lang = 'it'): void
{
    $isEn   = $lang === 'en';
    $window = rewind_free_window();
    $dates  = rewind_free_window_label($lang);
    $open   = !empty($window['start']) ? (int)$window['start'] : 0;

    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $t = $isEn
        ? [
            'title'   => 'Cripsum Rewind — Premium',
            'kicker'  => 'Cripsum Rewind',
            'head'    => 'Your year is<br>already written',
            'lead'    => 'Every pull, every message, every night spent here: it is all recorded and waiting. Rewind turns it into a single story.',
            'premium' => 'Premium feature',
            'gate'    => $dates !== ''
                ? 'It opens for everyone once a year, from <strong>' . $h($dates) . '</strong>. With Premium you can watch it any day you like.'
                : 'With Premium you can watch it any day you like.',
            'in'      => 'Free for everyone in',
            'd' => 'days', 'h' => 'hours', 'm' => 'min', 's' => 'sec',
            'cta'     => 'Get Premium',
            'back'    => 'Back to Cripsum',
            'note'    => 'Your stats keep being collected in the meantime, so waiting costs you nothing.',
        ]
        : [
            'title'   => 'Cripsum Rewind — Premium',
            'kicker'  => 'Cripsum Rewind',
            'head'    => 'Il tuo anno<br>è già scritto',
            'lead'    => 'Ogni pull, ogni messaggio, ogni sera passata qui: è tutto registrato e ti sta aspettando. Il Rewind lo trasforma in una storia sola.',
            'premium' => 'Funzione Premium',
            'gate'    => $dates !== ''
                ? 'Si apre a tutti una volta l\'anno, dal <strong>' . $h($dates) . '</strong>. Con il Premium lo guardi il giorno che vuoi.'
                : 'Con il Premium lo guardi il giorno che vuoi.',
            'in'      => 'Aperto a tutti fra',
            'd' => 'giorni', 'h' => 'ore', 'm' => 'min', 's' => 'sec',
            'cta'     => 'Passa a Premium',
            'back'    => 'Torna su Cripsum',
            'note'    => 'Intanto le tue statistiche continuano a essere raccolte: aspettare non ti fa perdere niente.',
        ];

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    ?>
<!DOCTYPE html>
<html lang="<?php echo $h($lang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#05070d">
    <meta name="robots" content="noindex">
    <title><?php echo $h($t['title']); ?></title>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?php echo $h(rewind_asset('/assets/rewind/rewind.css')); ?>">
</head>

<body class="rw-page">
    <main class="rw-stage">
        <div class="rw-bg-layer">
            <span class="rw-orb rw-orb--a" aria-hidden="true"></span>
            <span class="rw-orb rw-orb--b" aria-hidden="true"></span>
        </div>
        <div class="rw-grain" aria-hidden="true"></div>

        <div class="rw-locked">
            <div class="rw-locked__inner">
                <p class="rw-kicker">
                    <i class="fa-solid fa-clock-rotate-left"></i> <?php echo $h($t['kicker']); ?>
                </p>

                <h1 class="rw-title"><?php echo $t['head']; ?></h1>
                <p class="rw-lead"><?php echo $h($t['lead']); ?></p>

                <p class="rw-locked__tag">
                    <span class="rw-chip rw-chip--gold">
                        <i class="fa-solid fa-gem"></i> <?php echo $h($t['premium']); ?>
                    </span>
                </p>

                <p class="rw-lead rw-locked__gate"><?php echo $t['gate']; ?></p>

                <?php if ($open > 0): ?>
                    <!-- Nascosto finché il JS non ha messo dei numeri veri:
                         quattro zeri fermi sarebbero peggio di niente. -->
                    <div data-rw-countdown data-open-at="<?php echo $open; ?>" hidden>
                        <p class="rw-note rw-locked__cdlabel"><?php echo $h($t['in']); ?></p>
                        <div class="rw-stats rw-locked__cd">
                            <div class="rw-stat">
                                <span class="rw-stat__value" data-rw-d>0</span>
                                <span class="rw-stat__label"><?php echo $h($t['d']); ?></span>
                            </div>
                            <div class="rw-stat">
                                <span class="rw-stat__value" data-rw-h>0</span>
                                <span class="rw-stat__label"><?php echo $h($t['h']); ?></span>
                            </div>
                            <div class="rw-stat">
                                <span class="rw-stat__value" data-rw-m>0</span>
                                <span class="rw-stat__label"><?php echo $h($t['m']); ?></span>
                            </div>
                            <div class="rw-stat">
                                <span class="rw-stat__value" data-rw-s>0</span>
                                <span class="rw-stat__label"><?php echo $h($t['s']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="rw-actions">
                    <a class="rw-btn rw-btn--cta" href="/<?php echo $h($lang); ?>/checkout-premium">
                        <i class="fa-solid fa-gem"></i> <?php echo $h($t['cta']); ?>
                    </a>
                    <a class="rw-btn rw-btn--ghost" href="/<?php echo $h($lang); ?>/home">
                        <?php echo $h($t['back']); ?>
                    </a>
                </div>

                <p class="rw-note"><?php echo $h($t['note']); ?></p>
            </div>
        </div>
    </main>

    <script>
        // Il conto alla rovescia sta qui e non in rewind.js: quel file carica
        // tutto il racconto, e questa pagina un racconto non ce l'ha.
        (() => {
            'use strict';

            const box = document.querySelector('[data-rw-countdown]');
            if (!box) return;

            const openAt = Number(box.dataset.openAt) * 1000;
            if (!Number.isFinite(openAt) || openAt <= 0) return;

            const out = {
                d: box.querySelector('[data-rw-d]'),
                h: box.querySelector('[data-rw-h]'),
                m: box.querySelector('[data-rw-m]'),
                s: box.querySelector('[data-rw-s]'),
            };

            const tick = () => {
                const left = openAt - Date.now();

                // Se l'apertura arriva mentre la pagina è già aperta, basta
                // ricaricare: chi può entrare lo decide comunque il server.
                if (left <= 0) {
                    location.reload();
                    return;
                }

                const s = Math.floor(left / 1000);
                out.d.textContent = Math.floor(s / 86400);
                out.h.textContent = Math.floor(s / 3600) % 24;
                out.m.textContent = Math.floor(s / 60) % 60;
                out.s.textContent = s % 60;
                box.hidden = false;
            };

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>

</html>
    <?php
    exit;
}
