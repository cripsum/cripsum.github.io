<?php
/**
 * Card di un player nella griglia: un link vero alla sua pagina.
 * data-es-link la fa caricare a esports.js senza ricaricare il sito, e
 * data-es-music porta il brano, che parte nello stesso clic.
 *
 * Variabili: $p (player), $esCardSize ('big' | 'small'), $esIndex, $S.
 */

$esInitial = mb_strtoupper(mb_substr($p['nickname'], 0, 1, 'UTF-8'), 'UTF-8');
?>
<a class="es-card es-card--<?php echo shop_h($esCardSize); ?><?php echo $p['state'] === 'nascosto' ? ' is-hidden-player' : ''; ?>"
    href="<?php echo shop_h($p['url']); ?>"
    data-es-player="<?php echo shop_h($p['slug']); ?>"
    data-es-link
    <?php echo $p['music_data'] !== '' ? 'data-es-music="' . shop_h($p['music_data']) . '"' : ''; ?>
    aria-label="<?php echo shop_h(sprintf($S['open_player'], $p['nickname'])); ?>"
    <?php echo $p['style'] !== '' ? 'style="' . shop_h($p['style']) . '"' : ''; ?>>
    <span class="es-card__frame">
        <span class="es-card__glow" aria-hidden="true"></span>
        <?php if ($p['photo'] !== ''): ?>
            <img class="es-card__photo" src="<?php echo shop_h($p['photo']); ?>" alt="" width="600" height="800" <?php echo $esCardSize === 'big' && $esIndex < 5 ? '' : 'loading="lazy"'; ?> decoding="async">
        <?php else: ?>
            <span class="es-card__initial" aria-hidden="true"><?php echo shop_h($esInitial); ?></span>
        <?php endif; ?>

        <span class="es-card__top">
            <?php if ($p['flag'] !== ''): ?>
                <span class="es-flag fi fi-<?php echo shop_h($p['flag']); ?>" title="<?php echo shop_h($p['country_name']); ?>"></span>
            <?php endif; ?>
            <?php if ($p['music']): ?>
                <span class="es-card__music" title="<?php echo shop_h($S['music_kit']); ?>"><i class="fa-solid fa-music" aria-hidden="true"></i></span>
            <?php endif; ?>
            <?php if ($p['state'] === 'nascosto'): ?>
                <span class="es-card__hidden"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> <?php echo shop_h($S['hidden_badge']); ?></span>
            <?php endif; ?>
            <?php if ($p['premier']): ?>
                <span class="es-premier es-premier--mini" style="--tier: <?php echo shop_h($p['premier']['color']); ?>" title="<?php echo shop_h($S['rating_premier']); ?>">
                    <b><?php echo shop_h($p['premier']['big']); ?></b><small><?php echo shop_h($p['premier']['small']); ?></small>
                </span>
            <?php endif; ?>
        </span>

        <span class="es-card__body">
            <span class="es-card__role"><i class="<?php echo shop_h($p['role_icon']); ?>" aria-hidden="true"></i> <?php echo shop_h($p['role_label']); ?></span>
            <strong class="es-card__nick"><?php echo shop_h($p['nickname']); ?></strong>
            <?php if ($p['real_name'] !== ''): ?>
                <span class="es-card__name"><?php echo shop_h($p['real_name']); ?></span>
            <?php endif; ?>
        </span>
    </span>
</a>
