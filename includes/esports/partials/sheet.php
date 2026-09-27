<?php
/**
 * Scheda di un player: un <dialog> a tutto schermo che esports.js apre con
 * showModal(). Chiudi, precedente e successivo sono link veri, cosi' la
 * scheda si usa anche senza JavaScript (la pagina si ricarica).
 *
 * La scheda aperta dal server (link diretto) nasce con l'attributo open:
 * senza script resta visibile, con lo script diventa modale.
 *
 * Variabili: $p (player), $esPrev, $esNext, $esIsOpen, $esTeam, $S.
 */

$esId = 'es-sheet-' . $p['slug'];
$esLazy = $esIsOpen ? 'fetchpriority="high"' : 'loading="lazy"';
$esStats = $p['stats'];
$esTopStats = array_slice($esStats, 0, 4);
$esMoreStats = array_slice($esStats, 4);
$esHasStats = $p['premier'] || $p['faceit'] || $esStats;
$esMusic = $p['music'];
?>
<dialog class="es-sheet" id="<?php echo shop_h($esId); ?>"
    data-es-sheet="<?php echo shop_h($p['slug']); ?>"
    data-es-url="<?php echo shop_h($p['url']); ?>"
    data-es-doc-title="<?php echo shop_h('Cripsum™ - ' . $p['nickname'] . ' · ' . $esTeam['name']); ?>"
    data-es-presence="<?php echo shop_h(sprintf($S['presence_player'], $p['nickname'])); ?>"
    aria-labelledby="<?php echo shop_h($esId); ?>-title"
    <?php echo $p['style'] !== '' ? 'style="' . shop_h($p['style']) . '"' : ''; ?>
    <?php echo $esIsOpen ? 'open' : ''; ?>>
    <div class="es-sheet__frame">
        <a class="es-sheet__close" href="<?php echo shop_h($esTeam['url']); ?>" data-es-close title="<?php echo shop_h($S['close']); ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            <span class="visually-hidden"><?php echo shop_h($S['close']); ?></span>
        </a>

        <div class="es-sheet__media">
            <?php if ($p['background'] !== ''): ?>
                <img class="es-sheet__bg" src="<?php echo shop_h($p['background']); ?>" alt="" <?php echo $esLazy; ?> decoding="async">
            <?php endif; ?>
            <span class="es-sheet__watermark" aria-hidden="true"><?php echo shop_h($p['nickname']); ?></span>
            <?php if ($p['photo'] !== ''): ?>
                <img class="es-sheet__photo" src="<?php echo shop_h($p['photo']); ?>" alt="<?php echo shop_h($p['nickname']); ?>" width="600" height="800" <?php echo $esLazy; ?> decoding="async">
            <?php else: ?>
                <span class="es-sheet__initial" aria-hidden="true"><?php echo shop_h(mb_strtoupper(mb_substr($p['nickname'], 0, 1, 'UTF-8'), 'UTF-8')); ?></span>
            <?php endif; ?>
        </div>

        <div class="es-sheet__content" data-es-scroll>
            <?php if ($p['state'] === 'nascosto'): ?>
                <p class="es-sheet__preview"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> <?php echo shop_h($S['preview_banner']); ?></p>
            <?php endif; ?>

            <header class="es-sheet__head">
                <p class="es-sheet__meta">
                    <?php if ($p['flag'] !== ''): ?>
                        <span class="es-flag fi fi-<?php echo shop_h($p['flag']); ?>" aria-hidden="true"></span>
                        <span><?php echo shop_h($p['country_name']); ?></span>
                        <span class="es-dot" aria-hidden="true">·</span>
                    <?php endif; ?>
                    <span><i class="<?php echo shop_h($p['role_icon']); ?>" aria-hidden="true"></i> <?php echo shop_h($p['role_label']); ?></span>
                </p>
                <?php // All'apertura il fuoco va sul nome (lo legge chi usa uno screen reader), non sulla X. ?>
                <h2 class="es-sheet__nick" id="<?php echo shop_h($esId); ?>-title" tabindex="-1" autofocus><?php echo shop_h($p['nickname']); ?></h2>
                <?php if ($p['real_name'] !== ''): ?>
                    <p class="es-sheet__real"><?php echo shop_h($p['real_name']); ?></p>
                <?php endif; ?>
                <?php if ($p['tagline'] !== ''): ?>
                    <p class="es-sheet__quote"><?php echo shop_h($p['tagline']); ?></p>
                <?php endif; ?>
            </header>

            <?php if ($esMusic): ?>
                <div class="es-music" data-es-music
                    data-src="<?php echo shop_h($esMusic['src']); ?>"
                    data-title="<?php echo shop_h($esMusic['title'] !== '' ? $esMusic['title'] : $S['music_kit']); ?>"
                    data-artist="<?php echo shop_h($esMusic['artist']); ?>"
                    data-cover="<?php echo shop_h($esMusic['cover']); ?>"
                    data-start="<?php echo (int)$esMusic['start']; ?>"
                    data-volume="<?php echo (int)$esMusic['volume']; ?>">
                    <span class="es-music__disc" aria-hidden="true">
                        <?php if ($esMusic['cover'] !== ''): ?>
                            <img src="<?php echo shop_h($esMusic['cover']); ?>" alt="" <?php echo $esLazy; ?> decoding="async">
                        <?php else: ?>
                            <i class="fa-solid fa-compact-disc"></i>
                        <?php endif; ?>
                    </span>
                    <span class="es-music__info">
                        <small><?php echo shop_h($S['music_kit']); ?></small>
                        <strong><?php echo shop_h($esMusic['title'] !== '' ? $esMusic['title'] : $p['nickname']); ?></strong>
                        <?php if ($esMusic['artist'] !== ''): ?>
                            <span><?php echo shop_h($esMusic['artist']); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="es-music__eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                    <span class="es-music__controls">
                        <button type="button" class="es-music__toggle" data-es-music-toggle
                            data-label-play="<?php echo shop_h($S['music_play']); ?>"
                            data-label-pause="<?php echo shop_h($S['music_pause']); ?>"
                            aria-label="<?php echo shop_h($S['music_play']); ?>">
                            <i class="fa-solid fa-play" aria-hidden="true"></i>
                        </button>
                        <label class="es-music__volume">
                            <i class="fa-solid fa-volume-high" aria-hidden="true" data-es-volume-icon></i>
                            <span class="visually-hidden"><?php echo shop_h($S['music_volume']); ?></span>
                            <input type="range" min="0" max="100" step="1" value="<?php echo (int)$esMusic['volume']; ?>" data-es-volume>
                        </label>
                    </span>
                    <p class="es-music__hint" data-es-music-hint hidden><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> <?php echo shop_h($S['music_blocked']); ?></p>
                    <p class="es-music__error" data-es-music-error hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?php echo shop_h($S['music_error']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($esHasStats): ?>
                <section class="es-block">
                    <h3 class="es-block__title"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> <?php echo shop_h($S['stats']); ?></h3>

                    <?php if ($p['premier'] || $p['faceit']): ?>
                        <div class="es-ranks">
                            <?php if ($p['premier']): ?>
                                <div class="es-rank">
                                    <span class="es-premier" style="--tier: <?php echo shop_h($p['premier']['color']); ?>">
                                        <b><?php echo shop_h($p['premier']['big']); ?></b><small><?php echo shop_h($p['premier']['small']); ?></small>
                                    </span>
                                    <span class="es-rank__label"><?php echo shop_h($S['rating_premier']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($p['faceit']): ?>
                                <div class="es-rank">
                                    <span class="es-faceit" style="--lvl: <?php echo shop_h($p['faceit']['color']); ?>; --lvl-p: <?php echo (int)$p['faceit']['level'] * 10; ?>" title="<?php echo shop_h(sprintf($S['level'], $p['faceit']['level'])); ?>">
                                        <b><?php echo (int)$p['faceit']['level']; ?></b>
                                    </span>
                                    <span class="es-rank__label">
                                        <?php echo shop_h($S['rating_faceit']); ?>
                                        <?php if ($p['faceit']['elo_label'] !== ''): ?>
                                            <strong><?php echo shop_h($p['faceit']['elo_label']); ?> ELO</strong>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($esTopStats): ?>
                        <dl class="es-stats">
                            <?php foreach ($esTopStats as $esStat): ?>
                                <div class="es-stat">
                                    <dt><?php echo shop_h($esStat['label']); ?></dt>
                                    <dd>
                                        <?php echo shop_h($esStat['value']); ?>
                                        <?php if ($esStat['percent'] !== null): ?>
                                            <span class="es-bar" style="--p: <?php echo shop_h((string)$esStat['percent']); ?>" aria-hidden="true"><span></span></span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>

                    <?php if ($esMoreStats): ?>
                        <dl class="es-statlist">
                            <?php foreach ($esMoreStats as $esStat): ?>
                                <div class="es-statlist__row">
                                    <dt><?php echo shop_h($esStat['label']); ?></dt>
                                    <dd>
                                        <?php echo shop_h($esStat['value']); ?>
                                        <?php if ($esStat['percent'] !== null): ?>
                                            <span class="es-bar" style="--p: <?php echo shop_h((string)$esStat['percent']); ?>" aria-hidden="true"><span></span></span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($p['crosshair'] !== '' || $p['setup']): ?>
                <section class="es-block">
                    <h3 class="es-block__title"><i class="fa-solid fa-computer-mouse" aria-hidden="true"></i> <?php echo shop_h($S['setup']); ?></h3>
                    <?php if ($p['crosshair'] !== ''): ?>
                        <div class="es-crosshair">
                            <span class="es-crosshair__label"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> <?php echo shop_h($S['crosshair']); ?></span>
                            <code><?php echo shop_h($p['crosshair']); ?></code>
                            <button type="button" class="es-btn es-btn--small" data-es-copy="<?php echo shop_h($p['crosshair']); ?>">
                                <i class="fa-regular fa-copy" aria-hidden="true"></i> <?php echo shop_h($S['copy']); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($p['setup']): ?>
                        <dl class="es-pairs">
                            <?php foreach ($p['setup'] as $esPair): ?>
                                <div>
                                    <dt><?php echo shop_h($esPair['label']); ?></dt>
                                    <dd><?php echo shop_h($esPair['value']); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($p['fact_cards'] || $p['fact_list']): ?>
                <section class="es-block">
                    <h3 class="es-block__title"><i class="fa-solid fa-face-grin-squint-tears" aria-hidden="true"></i> <?php echo shop_h($S['facts']); ?></h3>
                    <?php if ($p['fact_cards']): ?>
                        <dl class="es-facts">
                            <?php foreach ($p['fact_cards'] as $esFact): ?>
                                <div class="es-fact">
                                    <dt><?php echo shop_h($esFact['label']); ?></dt>
                                    <dd><?php echo shop_linkify($esFact['value']); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if ($p['fact_list']): ?>
                        <ul class="es-factlist">
                            <?php foreach ($p['fact_list'] as $esFact): ?>
                                <li><?php echo shop_linkify($esFact['value']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($p['bio'] !== ''): ?>
                <section class="es-block">
                    <h3 class="es-block__title"><i class="fa-solid fa-user" aria-hidden="true"></i> <?php echo shop_h($S['bio']); ?></h3>
                    <p class="es-bio"><?php echo shop_linkify($p['bio']); ?></p>
                </section>
            <?php endif; ?>

            <?php if ($p['socials'] || $p['profile_url'] !== ''): ?>
                <section class="es-block">
                    <h3 class="es-block__title"><i class="fa-solid fa-link" aria-hidden="true"></i> <?php echo shop_h($S['socials']); ?></h3>
                    <ul class="es-socials" role="list">
                        <?php if ($p['profile_url'] !== ''): ?>
                            <li><a class="es-social es-social--site" href="<?php echo shop_h($p['profile_url']); ?>"><img src="/img/Susremaster.png" alt="" width="18" height="18"> <?php echo shop_h($S['profile_link']); ?></a></li>
                        <?php endif; ?>
                        <?php foreach ($p['socials'] as $esSocial): ?>
                            <li><a class="es-social es-social--<?php echo shop_h($esSocial['key']); ?>" href="<?php echo shop_h($esSocial['url']); ?>" target="_blank" rel="noopener"><i class="<?php echo shop_h($esSocial['icon']); ?>" aria-hidden="true"></i> <?php echo shop_h($esSocial['label']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($esPrev && $esNext): ?>
                <nav class="es-sheet__nav" aria-label="<?php echo shop_h($S['prev'] . ' / ' . $S['next']); ?>">
                    <a class="es-step es-step--prev" href="<?php echo shop_h($esPrev['url']); ?>" data-es-step="<?php echo shop_h($esPrev['slug']); ?>" rel="prev">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        <span><small><?php echo shop_h($S['prev_short']); ?></small><strong><?php echo shop_h($esPrev['nickname']); ?></strong></span>
                    </a>
                    <a class="es-step es-step--next" href="<?php echo shop_h($esNext['url']); ?>" data-es-step="<?php echo shop_h($esNext['slug']); ?>" rel="next">
                        <span><small><?php echo shop_h($S['next_short']); ?></small><strong><?php echo shop_h($esNext['nickname']); ?></strong></span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</dialog>
