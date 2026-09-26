<?php

/**
 * I pop-up della lootbox, inclusi da lootbox.php.
 *
 * Sono <dialog> nativi: li apre e chiude js/lootbox-modal.js (animazioni,
 * schede, trascinamento sul telefono), i contenuti dinamici li riempie
 * js/lootbox-ui.js. Stili in css/lootbox-modal.css.
 *
 * Usa le variabili della pagina: $h, $gEn, $gLang, $gIsAdmin, $gProfiles.
 */

$M = $gEn ? [
    'close' => 'Close',
    'settings' => 'Settings', 'settings_sub' => 'Sound, controls and codes',
    'tab_general' => 'General', 'tab_rates' => 'Rates', 'tab_codes' => 'Codes', 'tab_admin' => 'Admin',
    'sound' => 'Sound', 'volume' => 'Volume', 'pull_audio' => 'Pull audio', 'pull_audio_sub' => 'Themes and videos of the characters you find',
    'keys' => 'Keyboard', 'key_pull' => 'Open 1×', 'key_again' => 'Open again', 'key_skip' => 'Skip the multi', 'key_close' => 'Close',
    'k_space' => 'Space', 'k_enter' => 'Enter',
    'base_rates' => 'Base rates', 'base_rates_sub' => 'Each banner shows its own in “Details & rates”.',
    'pity_rules' => 'Pity', 'pity_std' => 'Standard banner', 'pity_evt' => 'Limited banners', 'soft' => 'soft from pull %d', 'hard' => 'guaranteed at pull %d',
    'code_title' => 'Got a code?', 'code_sub' => 'Codes come out on Discord and on the socials: characters, Godos and more.',
    'code_ph' => 'Type the code', 'redeem' => 'Redeem',
    'admin_sub' => 'Forces the next pulls (server side).',
    'leaderboard' => 'Leaderboard', 'leaderboard_sub' => 'The best lootbox players',
    'lb_boxes' => 'Boxes opened', 'lb_chars' => 'Characters',
    'history' => 'History', 'details' => 'Details & rates',
    'd_rates' => 'Rates', 'd_chars' => 'Characters', 'd_rules' => 'Rules',
    'funds' => 'Complete the pull',
] : [
    'close' => 'Chiudi',
    'settings' => 'Impostazioni', 'settings_sub' => 'Suoni, comandi e codici',
    'tab_general' => 'Generale', 'tab_rates' => 'Probabilità', 'tab_codes' => 'Codici', 'tab_admin' => 'Admin',
    'sound' => 'Suoni', 'volume' => 'Volume', 'pull_audio' => 'Audio delle pull', 'pull_audio_sub' => 'Sigle e video dei personaggi che trovi',
    'keys' => 'Comandi da tastiera', 'key_pull' => 'Apri 1×', 'key_again' => 'Apri ancora', 'key_skip' => 'Salta la multi', 'key_close' => 'Chiudi',
    'k_space' => 'Spazio', 'k_enter' => 'Invio',
    'base_rates' => 'Probabilità base', 'base_rates_sub' => 'Ogni banner ha le sue in «Dettagli e probabilità».',
    'pity_rules' => 'Pity', 'pity_std' => 'Banner standard', 'pity_evt' => 'Banner evento', 'soft' => 'soft dalla pull %d', 'hard' => 'garantito alla pull %d',
    'code_title' => 'Hai un codice?', 'code_sub' => 'I codici escono su Discord e sui social: personaggi, Godos e altro.',
    'code_ph' => 'Scrivi il codice', 'redeem' => 'Riscatta',
    'admin_sub' => 'Forza le prossime pull (lato server).',
    'leaderboard' => 'Classifica', 'leaderboard_sub' => 'I migliori giocatori della lootbox',
    'lb_boxes' => 'Casse aperte', 'lb_chars' => 'Personaggi',
    'history' => 'Cronologia', 'details' => 'Dettagli e probabilità',
    'd_rates' => 'Probabilità', 'd_chars' => 'Personaggi', 'd_rules' => 'Regole',
    'funds' => 'Completa la pull',
];

$mHead = static function (string $icon, string $title, string $sub, string $id, bool $subDynamic = false) use ($h, $M): string {
    return '<header class="lm__head">'
        . '<span class="lm__grab" aria-hidden="true"></span>'
        . '<span class="lm__icon"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="lm__titles"><h2 id="' . $id . '">' . $h($title) . '</h2><p' . ($subDynamic ? ' data-lm-sub' : '') . '>' . $h($sub) . '</p></div>'
        . '<button type="button" class="lm__x" data-lm-close aria-label="' . $h($M['close']) . '"><i class="fa-solid fa-xmark"></i></button>'
        . '</header>';
};

$mWeights = gacha_rarity_defs();
$mMax = max(array_map(static fn($k) => $mWeights[$k]['peso'], $gRateRows));
?>

<!-- Impostazioni: l'id e' quello che apre anche l'ingranaggio della navbar. -->
<dialog class="lm" id="impostazioniModal" aria-labelledby="lm-settings-title">
    <div class="lm__scrim" data-lm-close></div>
    <div class="lm__panel" style="--lm-w:520px;--lm-c:#6366f1;--lm-c2:#38bdf8">
        <?= $mHead('fa-gear', $M['settings'], $M['settings_sub'], 'lm-settings-title') ?>
        <nav class="lm__tabs" role="tablist" data-lm-tabs>
            <span class="lm__ink" aria-hidden="true"></span>
            <button type="button" role="tab" data-lm-tab="general" aria-selected="true"><i class="fa-solid fa-sliders"></i> <?= $h($M['tab_general']) ?></button>
            <button type="button" role="tab" data-lm-tab="rates" aria-selected="false"><i class="fa-solid fa-dice"></i> <?= $h($M['tab_rates']) ?></button>
            <button type="button" role="tab" data-lm-tab="codes" aria-selected="false"><i class="fa-solid fa-gift"></i> <?= $h($M['tab_codes']) ?></button>
            <?php if ($gIsAdmin): ?>
                <button type="button" role="tab" data-lm-tab="admin" aria-selected="false"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= $h($M['tab_admin']) ?></button>
            <?php endif; ?>
        </nav>
        <div class="lm__body">
            <section class="lm__pane is-active" data-lm-pane="general" role="tabpanel">
                <div class="lm-sec">
                    <h3><?= $h($M['sound']) ?></h3>
                    <div class="lm-card">
                        <label class="lm-row lm-row--range">
                            <i class="fa-solid fa-volume-high lm-row__icon" data-audio-icon></i>
                            <span class="lm-row__main"><b><?= $h($M['volume']) ?></b></span>
                            <input type="range" class="lm-range" min="0" max="100" step="1" value="80" data-audio-volume aria-label="<?= $h($M['volume']) ?>">
                            <span class="lm-row__val" data-audio-volume-val>80%</span>
                        </label>
                        <label class="lm-row">
                            <i class="fa-solid fa-music lm-row__icon"></i>
                            <span class="lm-row__main"><b><?= $h($M['pull_audio']) ?></b><small><?= $h($M['pull_audio_sub']) ?></small></span>
                            <input type="checkbox" class="lm-switch" data-audio-toggle checked>
                        </label>
                    </div>
                </div>
                <div class="lm-sec lm-keys">
                    <h3><?= $h($M['keys']) ?></h3>
                    <div class="lm-card">
                        <div class="lm-row"><span class="lm-row__main"><b><?= $h($M['key_pull']) ?></b></span><kbd class="lm-kbd"><?= $h($M['k_space']) ?></kbd></div>
                        <div class="lm-row"><span class="lm-row__main"><b><?= $h($M['key_again']) ?></b></span><kbd class="lm-kbd"><?= $h($M['k_enter']) ?></kbd></div>
                        <div class="lm-row"><span class="lm-row__main"><b><?= $h($M['key_skip']) ?></b></span><kbd class="lm-kbd">S</kbd></div>
                        <div class="lm-row"><span class="lm-row__main"><b><?= $h($M['key_close']) ?></b></span><kbd class="lm-kbd">Esc</kbd></div>
                    </div>
                </div>
            </section>

            <section class="lm__pane" data-lm-pane="rates" role="tabpanel" hidden>
                <div class="lm-sec">
                    <h3><?= $h($M['base_rates']) ?></h3>
                    <div class="lm-card lm-card--pad">
                        <div class="lm-rates">
                            <?php foreach ($gRateRows as $i => $rk):
                                $w = $mWeights[$rk]['peso'];
                                $label = $rk === 'segreto' ? '???' : gacha_rarity_label($rk, $gLang);
                                $value = $w >= 5 ? round($w) . '%' : number_format($w, 2, $gEn ? '.' : ',', '') . '%';
                            ?>
                                <div class="lm-rate" style="--rc:<?= $h($mWeights[$rk]['color']) ?>;--w:<?= round(max(1.2, $w / $mMax * 100), 2) ?>%;--i:<?= $i ?>">
                                    <span class="lm-dot"><?= $h($label) ?></span>
                                    <span class="lm-rate__track"><i></i></span>
                                    <strong><?= $h($value) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="lm-note"><?= $h($M['base_rates_sub']) ?></p>
                </div>
                <div class="lm-sec">
                    <h3><?= $h($M['pity_rules']) ?></h3>
                    <div class="lm-card">
                        <?php foreach (['standard' => $M['pity_std'], 'evento' => $M['pity_evt']] as $pk => $plabel): $pp = $gProfiles[$pk]; ?>
                            <div class="lm-row">
                                <span class="lm-row__main"><b><?= $h($plabel) ?></b><small><?= $h(sprintf($M['soft'], $pp['soft'] + 1)) ?> · <?= $h(sprintf($M['hard'], $pp['hard'])) ?></small></span>
                                <span class="lm-pitymini" style="--s:<?= round($pp['soft'] / $pp['hard'] * 100, 2) ?>%"><i></i></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="lm__pane" data-lm-pane="codes" role="tabpanel" hidden>
                <div class="lm-code">
                    <span class="lm-code__art" aria-hidden="true"><i class="fa-solid fa-gift"></i></span>
                    <h3><?= $h($M['code_title']) ?></h3>
                    <p><?= $h($M['code_sub']) ?></p>
                    <form class="lm-field" data-redeem-form autocomplete="off">
                        <label class="visually-hidden" for="codiceSegreto"><?= $h($M['code_ph']) ?></label>
                        <input type="text" id="codiceSegreto" placeholder="<?= $h($M['code_ph']) ?>" autocomplete="off" spellcheck="false" maxlength="64">
                        <button type="submit" class="lm-btn lm-btn--main" id="btnRiscatta">
                            <span id="btnRiscattaLabel"><?= $h($M['redeem']) ?></span>
                            <span id="btnRiscattaSpin" hidden><i class="fa-solid fa-circle-notch fa-spin"></i></span>
                        </button>
                    </form>
                </div>
            </section>

            <?php if ($gIsAdmin): ?>
                <section class="lm__pane" data-lm-pane="admin" role="tabpanel" hidden>
                    <p class="lm-note" style="margin-top:0"><?= $h($M['admin_sub']) ?></p>
                    <div class="lm-toggles">
                        <?php foreach (['comune', 'raro', 'epico', 'leggendario', 'speciale', 'segreto', 'theone'] as $rk): ?>
                            <label class="lm-toggle" style="--rc:<?= $h($mWeights[$rk]['color']) ?>">
                                <input type="checkbox" class="admin-force-rarity" id="forza-<?= $rk ?>" data-rarity="<?= $rk ?>">
                                <span><i class="lm-dot"></i><?= $h(($gEn ? 'Only ' : 'Solo ') . gacha_rarity_label($rk, $gLang)) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="lm-toggle" style="--rc:#f472b6">
                            <input type="checkbox" class="admin-force-character" id="forza-lobotomy" data-character-id="155">
                            <span><i class="lm-dot"></i>Mod sono Lobotomy</span>
                        </label>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</dialog>

<dialog class="lm" id="lootboxLeaderboard" aria-labelledby="lm-lb-title">
    <div class="lm__scrim" data-lm-close></div>
    <div class="lm__panel" style="--lm-w:520px;--lm-c:#f59e0b;--lm-c2:#f97316">
        <?= $mHead('fa-trophy', $M['leaderboard'], $M['leaderboard_sub'], 'lm-lb-title') ?>
        <nav class="lm__tabs" role="tablist" data-lm-tabs>
            <span class="lm__ink" aria-hidden="true"></span>
            <button type="button" role="tab" data-lm-tab="casse_aperte" aria-selected="true"><i class="fa-solid fa-box-open"></i> <?= $h($M['lb_boxes']) ?></button>
            <button type="button" role="tab" data-lm-tab="personaggi_sbloccati" aria-selected="false"><i class="fa-solid fa-layer-group"></i> <?= $h($M['lb_chars']) ?></button>
        </nav>
        <div class="lm__body" data-lb-body></div>
    </div>
</dialog>

<dialog class="lm" id="gachaHistoryModal" aria-labelledby="lm-history-title">
    <div class="lm__scrim" data-lm-close></div>
    <div class="lm__panel" style="--lm-w:620px;--lm-c:#38bdf8;--lm-c2:#6366f1">
        <?= $mHead('fa-clock-rotate-left', $M['history'], '', 'lm-history-title', true) ?>
        <div class="lm__body" data-history-body></div>
        <footer class="lm__foot" data-history-foot hidden></footer>
    </div>
</dialog>

<dialog class="lm" id="gachaDetailsModal" aria-labelledby="lm-details-title">
    <div class="lm__scrim" data-lm-close></div>
    <div class="lm__panel" style="--lm-w:640px" data-details-panel>
        <?= $mHead('fa-circle-info', $M['details'], '', 'lm-details-title', true) ?>
        <nav class="lm__tabs" role="tablist" data-lm-tabs>
            <span class="lm__ink" aria-hidden="true"></span>
            <button type="button" role="tab" data-lm-tab="rates" aria-selected="true"><?= $h($M['d_rates']) ?></button>
            <button type="button" role="tab" data-lm-tab="chars" aria-selected="false"><?= $h($M['d_chars']) ?> <span class="lm__tab-count" data-details-count></span></button>
            <button type="button" role="tab" data-lm-tab="rules" aria-selected="false"><?= $h($M['d_rules']) ?></button>
        </nav>
        <div class="lm__body">
            <section class="lm__pane is-active" data-lm-pane="rates" role="tabpanel"></section>
            <section class="lm__pane" data-lm-pane="chars" role="tabpanel" hidden></section>
            <section class="lm__pane" data-lm-pane="rules" role="tabpanel" hidden></section>
        </div>
    </div>
</dialog>

<!-- Valute: conversione Godos -> Shards o rimando allo shop. Gli id restano
     quelli di prima perche' li cerca il resto del sito. -->
<dialog class="lm lm--small" id="gachaConversionModal" aria-labelledby="lm-funds-title">
    <div class="lm__scrim" data-lm-close></div>
    <div class="lm__panel" style="--lm-w:440px;--lm-c:#8b5cf6;--lm-c2:#6366f1">
        <?= $mHead('fa-wand-magic-sparkles', $M['funds'], '', 'lm-funds-title', true) ?>
        <div class="lm__body" data-funds-body></div>
        <footer class="lm__foot" data-funds-foot></footer>
    </div>
</dialog>
