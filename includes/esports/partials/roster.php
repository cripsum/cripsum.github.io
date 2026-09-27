<?php
/**
 * Pagina del team: testata, line-up, panchina e staff, palmares, ex player
 * e (solo per lo staff) i nascosti.
 *
 * Variabili: $esTeam, $esGroups, $esOrdered, $S.
 */
?>
<main class="es-shell" id="es-main" data-es-page="team">
    <section class="es-hero<?php echo $esTeam['cover'] !== '' ? ' has-cover' : ''; ?>" aria-labelledby="es-team-title">
        <?php if ($esTeam['cover'] !== ''): ?>
            <img class="es-hero__cover" src="<?php echo shop_h($esTeam['cover']); ?>" alt="" fetchpriority="high" decoding="async">
        <?php endif; ?>
        <div class="es-hero__grid" aria-hidden="true"></div>

        <div class="es-hero__content">
            <?php if ($esTeam['logo'] !== ''): ?>
                <img class="es-hero__logo" src="<?php echo shop_h($esTeam['logo']); ?>" alt="<?php echo shop_h('Logo ' . $esTeam['name']); ?>" width="96" height="96" decoding="async">
            <?php endif; ?>
            <h1 class="es-hero__title" id="es-team-title" tabindex="-1"><?php echo shop_h($esTeam['name']); ?></h1>
            <?php if ($esTeam['tagline'] !== ''): ?>
                <p class="es-hero__lead"><?php echo shop_h($esTeam['tagline']); ?></p>
            <?php endif; ?>
            <?php if ($esTeam['description'] !== ''): ?>
                <p class="es-hero__text"><?php echo shop_linkify($esTeam['description']); ?></p>
            <?php endif; ?>

            <?php if (($esTeam['link_url'] !== '' && $esTeam['link_text'] !== '') || $esTeam['socials']): ?>
                <div class="es-hero__actions">
                    <?php if ($esTeam['link_url'] !== '' && $esTeam['link_text'] !== ''): ?>
                        <?php $esExternal = (bool)preg_match('~^https?://~i', $esTeam['link_url']); ?>
                        <a class="es-btn es-btn--primary" href="<?php echo shop_h($esTeam['link_url']); ?>" <?php echo $esExternal ? 'target="_blank" rel="noopener"' : ''; ?>>
                            <?php if (stripos($esTeam['link_url'], 'discord') !== false): ?><i class="fa-brands fa-discord" aria-hidden="true"></i><?php endif; ?>
                            <?php echo shop_h($esTeam['link_text']); ?>
                        </a>
                    <?php endif; ?>
                    <?php foreach ($esTeam['socials'] as $esSocial): ?>
                        <a class="es-icon-btn" href="<?php echo shop_h($esSocial['url']); ?>" target="_blank" rel="noopener" title="<?php echo shop_h($esSocial['label']); ?>">
                            <i class="<?php echo shop_h($esSocial['icon']); ?>" aria-hidden="true"></i>
                            <span class="visually-hidden"><?php echo shop_h($esSocial['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$esOrdered): ?>
        <section class="es-panel es-empty">
            <i class="fa-solid fa-crosshairs" aria-hidden="true"></i>
            <h2><?php echo shop_h($S['empty_title']); ?></h2>
            <p><?php echo shop_h($S['empty_text']); ?></p>
        </section>
    <?php endif; ?>

    <?php if ($esGroups['lineup']): ?>
        <section class="es-section" aria-labelledby="es-lineup-title">
            <h2 class="es-section__title" id="es-lineup-title"><?php echo shop_h($S['lineup']); ?></h2>
            <ul class="es-grid es-grid--lineup" role="list">
                <?php foreach ($esGroups['lineup'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'big'; include __DIR__ . '/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($esGroups['bench']): ?>
        <section class="es-section" aria-labelledby="es-bench-title">
            <h2 class="es-section__title" id="es-bench-title"><?php echo shop_h($S['bench']); ?></h2>
            <ul class="es-grid es-grid--small" role="list">
                <?php foreach ($esGroups['bench'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'small'; include __DIR__ . '/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($esTeam['palmares']): ?>
        <section class="es-section" aria-labelledby="es-palmares-title">
            <h2 class="es-section__title" id="es-palmares-title"><?php echo shop_h($S['palmares']); ?></h2>
            <ol class="es-palmares" role="list">
                <?php foreach ($esTeam['palmares'] as $esEntry): ?>
                    <li class="es-palmares__item<?php echo $esEntry['medal'] ? ' is-' . $esEntry['medal'] : ''; ?>">
                        <span class="es-palmares__medal" aria-hidden="true"><i class="fa-solid <?php echo $esEntry['medal'] ? 'fa-trophy' : 'fa-flag-checkered'; ?>"></i></span>
                        <span class="es-palmares__body">
                            <?php if ($esEntry['link'] !== ''): ?>
                                <a class="es-palmares__name" href="<?php echo shop_h($esEntry['link']); ?>" target="_blank" rel="noopener"><?php echo shop_h($esEntry['tournament']); ?> <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            <?php else: ?>
                                <strong class="es-palmares__name"><?php echo shop_h($esEntry['tournament']); ?></strong>
                            <?php endif; ?>
                            <?php if ($esEntry['date'] !== ''): ?>
                                <?php if ($esEntry['date_iso'] !== ''): ?>
                                    <time datetime="<?php echo shop_h($esEntry['date_iso']); ?>"><?php echo shop_h($esEntry['date']); ?></time>
                                <?php else: ?>
                                    <span><?php echo shop_h($esEntry['date']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($esEntry['placement'] !== ''): ?>
                            <span class="es-palmares__place"><?php echo shop_h($esEntry['placement']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($esGroups['former']): ?>
        <details class="es-section es-former">
            <summary class="es-section__title">
                <?php echo shop_h($S['former']); ?> <span class="es-former__count"><?php echo count($esGroups['former']); ?></span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </summary>
            <ul class="es-grid es-grid--small" role="list">
                <?php foreach ($esGroups['former'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'small'; include __DIR__ . '/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <?php if ($esGroups['hidden']): ?>
        <section class="es-section es-section--staff" aria-labelledby="es-hidden-title">
            <h2 class="es-section__title" id="es-hidden-title"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> <?php echo shop_h($S['hidden_group']); ?></h2>
            <ul class="es-grid es-grid--small" role="list">
                <?php foreach ($esGroups['hidden'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'small'; include __DIR__ . '/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
