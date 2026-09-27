<?php
/**
 * Pagina di un player (/it/ohpy/{player}).
 *
 * Testata a tutta larghezza (foto, nickname, ranghi, musica), poi due
 * colonne: statistiche, curiosita' e bio a sinistra, setup e social a
 * destra. In fondo il player precedente e il successivo.
 *
 * I link verso le altre pagine del team hanno data-es-link: esports.js li
 * carica senza ricaricare il sito, e la musica del player di arrivo parte
 * nello stesso clic (data-es-music).
 *
 * Variabili: $esPlayer, $esPrev, $esNext, $esTeam, $S.
 */

$p = $esPlayer;
$esStats = $p['stats'];
$esTopStats = array_slice($esStats, 0, 4);
$esMoreStats = array_slice($esStats, 4);
$esMusic = $p['music'];
$esInitial = mb_strtoupper(mb_substr($p['nickname'], 0, 1, 'UTF-8'), 'UTF-8');

$esHasMain = $esStats || $p['fact_cards'] || $p['fact_list'] || $p['bio'] !== '';
$esHasSide = $p['crosshair'] !== '' || $p['setup'] || $p['socials'] || $p['profile_url'] !== '';
?>
<main class="es-shell es-player" id="es-main" data-es-page="player" data-es-slug="<?php echo shop_h($p['slug']); ?>">
    <a class="es-back" href="<?php echo shop_h($esTeam['url']); ?>" data-es-link>
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php echo shop_h('Team ' . $esTeam['name']); ?>
    </a>

    <?php if ($p['state'] === 'nascosto'): ?>
        <p class="es-preview"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> <?php echo shop_h($S['preview_banner']); ?></p>
    <?php endif; ?>

    <section class="es-phero<?php echo $p['background'] !== '' ? ' has-bg' : ''; ?>" aria-labelledby="es-player-title">
        <?php if ($p['background'] !== ''): ?>
            <img class="es-phero__bg" src="<?php echo shop_h($p['background']); ?>" alt="" decoding="async">
        <?php endif; ?>
        <div class="es-phero__grid" aria-hidden="true"></div>

        <div class="es-phero__media" data-es-swipe>
            <span class="es-phero__watermark" aria-hidden="true"><?php echo shop_h($p['nickname']); ?></span>
            <?php if ($p['photo'] !== ''): ?>
                <img class="es-phero__photo" src="<?php echo shop_h($p['photo']); ?>" alt="<?php echo shop_h($p['nickname']); ?>" width="600" height="800" fetchpriority="high" decoding="async">
            <?php else: ?>
                <span class="es-phero__initial" aria-hidden="true"><?php echo shop_h($esInitial); ?></span>
            <?php endif; ?>
        </div>

        <div class="es-phero__info">
            <p class="es-phero__meta">
                <?php if ($p['flag'] !== ''): ?>
                    <span class="es-flag fi fi-<?php echo shop_h($p['flag']); ?>" aria-hidden="true"></span>
                    <span><?php echo shop_h($p['country_name']); ?></span>
                    <span class="es-dot" aria-hidden="true">·</span>
                <?php endif; ?>
                <span><i class="<?php echo shop_h($p['role_icon']); ?>" aria-hidden="true"></i> <?php echo shop_h($p['role_label']); ?></span>
            </p>
            <h1 class="es-phero__nick" id="es-player-title" tabindex="-1"><?php echo shop_h($p['nickname']); ?></h1>
            <?php if ($p['real_name'] !== ''): ?>
                <p class="es-phero__real"><?php echo shop_h($p['real_name']); ?></p>
            <?php endif; ?>
            <?php if ($p['tagline'] !== ''): ?>
                <p class="es-phero__quote"><?php echo shop_h($p['tagline']); ?></p>
            <?php endif; ?>

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

            <?php if ($esMusic): ?>
                <div class="es-music" data-es-music-box data-es-music="<?php echo shop_h($p['music_data']); ?>">
                    <span class="es-music__disc" aria-hidden="true">
                        <?php if ($esMusic['cover'] !== ''): ?>
                            <img src="<?php echo shop_h($esMusic['cover']); ?>" alt="" decoding="async">
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
        </div>
    </section>

    <?php if ($esHasMain || $esHasSide): ?>
        <div class="es-player__body<?php echo $esHasMain && $esHasSide ? '' : ' is-single'; ?>">
            <?php if ($esHasMain): ?>
                <div class="es-player__main">
                    <?php if ($esStats): ?>
                        <section class="es-block" aria-labelledby="es-stats-title">
                            <h2 class="es-block__title" id="es-stats-title"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> <?php echo shop_h($S['stats']); ?></h2>
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

                    <?php if ($p['fact_cards'] || $p['fact_list']): ?>
                        <section class="es-block" aria-labelledby="es-facts-title">
                            <h2 class="es-block__title" id="es-facts-title"><i class="fa-solid fa-face-grin-squint-tears" aria-hidden="true"></i> <?php echo shop_h($S['facts']); ?></h2>
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
                        <section class="es-block" aria-labelledby="es-bio-title">
                            <h2 class="es-block__title" id="es-bio-title"><i class="fa-solid fa-user" aria-hidden="true"></i> <?php echo shop_h($S['bio']); ?></h2>
                            <p class="es-bio"><?php echo shop_linkify($p['bio']); ?></p>
                        </section>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($esHasSide): ?>
                <aside class="es-player__side">
                    <?php if ($p['crosshair'] !== '' || $p['setup']): ?>
                        <section class="es-block" aria-labelledby="es-setup-title">
                            <h2 class="es-block__title" id="es-setup-title"><i class="fa-solid fa-computer-mouse" aria-hidden="true"></i> <?php echo shop_h($S['setup']); ?></h2>
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

                    <?php if ($p['socials'] || $p['profile_url'] !== ''): ?>
                        <section class="es-block" aria-labelledby="es-socials-title">
                            <h2 class="es-block__title" id="es-socials-title"><i class="fa-solid fa-link" aria-hidden="true"></i> <?php echo shop_h($S['socials']); ?></h2>
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
                </aside>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($esPrev && $esNext): ?>
        <nav class="es-player__nav" aria-label="<?php echo shop_h($S['other_players']); ?>">
            <?php foreach ([['prev', $esPrev, 'fa-arrow-left', $S['prev_short']], ['next', $esNext, 'fa-arrow-right', $S['next_short']]] as [$esDir, $esOther, $esArrow, $esLabel]): ?>
                <a class="es-step es-step--<?php echo $esDir; ?>"
                    href="<?php echo shop_h($esOther['url']); ?>"
                    rel="<?php echo $esDir; ?>"
                    data-es-link
                    data-es-step="<?php echo $esDir; ?>"
                    <?php echo $esOther['music_data'] !== '' ? 'data-es-music="' . shop_h($esOther['music_data']) . '"' : ''; ?>
                    <?php echo $esOther['style'] !== '' ? 'style="' . shop_h($esOther['style']) . '"' : ''; ?>>
                    <i class="fa-solid <?php echo $esArrow; ?> es-step__arrow" aria-hidden="true"></i>
                    <span class="es-step__thumb" aria-hidden="true">
                        <?php if ($esOther['photo'] !== ''): ?>
                            <img src="<?php echo shop_h($esOther['photo']); ?>" alt="" loading="lazy" decoding="async">
                        <?php else: ?>
                            <?php echo shop_h(mb_strtoupper(mb_substr($esOther['nickname'], 0, 1, 'UTF-8'), 'UTF-8')); ?>
                        <?php endif; ?>
                    </span>
                    <span class="es-step__text">
                        <small><?php echo shop_h($esLabel); ?></small>
                        <strong><?php echo shop_h($esOther['nickname']); ?></strong>
                        <em><i class="<?php echo shop_h($esOther['role_icon']); ?>" aria-hidden="true"></i> <?php echo shop_h($esOther['role_label']); ?></em>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</main>
