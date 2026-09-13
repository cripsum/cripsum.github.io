<?php
/** Area "Effetti": nome, foto profilo, pagina, cursore, inclinazione. */

$ownColorEffects = implode('|', PROFILE_NAME_EFFECTS_OWN_COLORS);
$ringStyle = profile_ring_style($profile);
$ringAvatar = profile_avatar_url($profile, 128);
// Ogni riquadro mostra l'anello vero, sulla tua foto e nella tua forma.
$ringOptions = array_map(static fn($o) => $o + ['art' => '<span class="pe-ring-art"><span class="bio-avatar-wrap" data-ring="' . pe_h($o['value']) . '">'
    . '<span class="bio-avatar-ring">' . profile_ring_inner_html() . '</span>'
    . '<img class="bio-avatar" src="' . pe_h($ringAvatar) . '" alt="" loading="lazy" decoding="async"></span></span>'], $catalog['ring_styles']);
$tiltPreset = profile_tilt_preset_for($profile);
$pageEffect = (string)($profile['profile_effect'] ?? 'none');
$nameEffectOptions = array_map(static fn($o) => $o + ['art' => '<span class="pe-name-art" data-effect="' . $o['value'] . '">Aa</span>'], $catalog['name_effects']);
?>

<?php pe_group('grp-name', $tt('Nome', 'Name'), $tt('Il nome in cima al profilo: scegli un colore oppure un effetto.', 'The name at the top of your profile: pick a color or an effect.'), ['keywords' => 'nome colore sfumatura gradient effetto animazione']); ?>
<div class="pe-name-preview" id="peNamePreview" aria-hidden="true">
    <span class="pe-name-sample" data-effect="<?php echo pe_h($nameStyle['effect']); ?>"><?php echo pe_h(profile_display_name($profile)); ?></span>
</div>

<?php pe_choice('profile_name_effect', $nameStyle['effect'], $nameEffectOptions, ['label' => $tt('Effetto', 'Effect'), 'variant' => 'tiles', 'columns' => 5, 'class' => 'pe-name-effects']); ?>

<div data-show-if="profile_name_effect!=<?php echo $ownColorEffects; ?>">
    <?php pe_color('profile_name_color', $nameStyle['color'], ['label' => $tt('Colore del nome', 'Name color'), 'keywords' => 'nome colore']); ?>
</div>
<p class="pe-note" data-show-if="profile_name_effect=<?php echo $ownColorEffects; ?>">
    <i class="fa-solid fa-circle-info" aria-hidden="true"></i><?php echo pe_h($tt('Questo effetto usa colori suoi, quindi il colore del nome non si applica.', 'This effect uses its own colors, so the name color does not apply.')); ?>
</p>

<div class="pe-row-2" data-show-if="profile_name_effect=gradient">
    <?php pe_color('profile_name_grad_color1', $nameStyle['grad_color1'], ['label' => $tt('Sfumatura: inizio', 'Gradient: start')]); ?>
    <?php pe_color('profile_name_grad_color2', $nameStyle['grad_color2'], ['label' => $tt('Sfumatura: fine', 'Gradient: end')]); ?>
</div>
<?php pe_slider('profile_name_grad_angle', $nameStyle['grad_angle'], ['label' => $tt('Direzione della sfumatura', 'Gradient direction'), 'min' => 0, 'max' => 360, 'step' => 5, 'format' => 'deg', 'default' => 90, 'show_if' => 'profile_name_effect=gradient']); ?>
<div data-show-if="profile_name_effect=glow|neon">
    <?php pe_color('profile_name_glow_color', $nameStyle['glow_color'], ['label' => $tt('Colore del bagliore', 'Glow color')]); ?>
</div>
<?php pe_group_end(); ?>

<?php pe_group('grp-ring', $tt('Foto profilo', 'Profile photo'), $tt('L\'anello animato attorno alla foto. La forma della foto è in Aspetto.', 'The animated ring around your photo. The photo shape is in Style.'), ['keywords' => 'anello ring avatar pfp bordo']); ?>
<div class="pe-ring-picker" id="peRingPicker"
    data-avatar-shape="<?php echo pe_h($style['avatar_shape']); ?>"
    data-avatar-border="<?php echo $pflag('profile_avatar_border') ? '1' : '0'; ?>"
    style="--profile-ring: <?php echo pe_h(profile_style_hex($profile['avatar_ring_color'] ?? null) ?? $style['accent']); ?>; --accent-2: <?php echo pe_h($style['secondary']); ?>;">
    <?php pe_choice('avatar_ring_style', $ringStyle, $ringOptions, ['label' => $tt('Anello', 'Ring'), 'variant' => 'tiles', 'columns' => 4, 'class' => 'pe-ring-choices', 'keywords' => 'anello ring scia battito orbita bagliore glow aureola halo neon scintille glitch arcobaleno']); ?>
</div>
<input type="hidden" name="avatar_ring_enabled" id="peRingEnabled" value="<?php echo $ringStyle === 'none' ? '0' : '1'; ?>">
<div data-show-if="avatar_ring_style!=none">
    <?php pe_color('avatar_ring_color', profile_style_hex($profile['avatar_ring_color'] ?? null) ?? $style['accent'], ['label' => $tt('Colore dell\'anello', 'Ring color')]); ?>
</div>
<?php pe_toggle('profile_avatar_border', $pflag('profile_avatar_border'), [
    'label' => $tt('Cornice attorno alla foto', 'Frame around the photo'),
    'description' => $tt('Un bordo pieno del colore dell\'anello.', 'A solid border in the ring color.'),
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-page-effect', $tt('Effetto della pagina', 'Page effect'), null, ['keywords' => 'particelle stelle aurora sakura pioggia effetto sfondo']); ?>
<?php pe_select('profile_effect', $pageEffect, $catalog['page_effects'], ['label' => $tt('Effetto', 'Effect')]); ?>
<p class="pe-note" data-show-if="profile_effect=glass_rain"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><?php echo pe_h($tt('La pioggia sul vetro funziona solo con uno sfondo immagine.', 'Glass rain only works with an image background.')); ?></p>
<?php pe_group_end(); ?>

<?php pe_group('grp-cursor', $tt('Cursore', 'Cursor'), $tt('Il puntatore del mouse sul tuo profilo.', 'The mouse pointer on your profile.'), ['keywords' => 'mouse puntatore cursore scia', 'premium' => true]); ?>
<?php pe_select('profile_cursor_effect', (string)($profile['profile_cursor_effect'] ?? 'none'), $catalog['cursor_effects'], ['label' => $tt('Effetto', 'Effect'), 'premium' => true]); ?>

<div class="pe-field<?php echo $isPremium ? '' : ' is-locked'; ?>" data-search="<?php echo pe_h($tt('Immagine del cursore', 'Cursor image')); ?>" <?php echo $isPremium ? '' : 'data-premium-lock="1"'; ?>>
    <div class="pe-field-label"><span class="pe-field-title"><?php echo pe_h($tt('Immagine del cursore', 'Cursor image')); ?></span><?php echo pe_premium_chip(); ?></div>
    <div class="pe-media-url" data-media-url data-purpose="cursor" data-accept="image/jpeg,image/png,image/webp,image/gif,.cur,.ani">
        <input type="hidden" name="profile_cursor_custom_url" id="peCursorUrl" value="<?php echo pe_h($profile['profile_cursor_custom_url'] ?? ''); ?>" <?php echo $isPremium ? '' : 'disabled'; ?>>
    </div>
    <p class="pe-help"><?php echo pe_h($tt('PNG, JPG, WEBP, GIF, CUR o ANI. Le immagini diventano 64×64.', 'PNG, JPG, WEBP, GIF, CUR or ANI. Images become 64×64.')); ?></p>
</div>
<?php pe_toggle('profile_cursor_custom_center', $pflag('profile_cursor_custom_center', 0), ['label' => $tt('Punta dal centro dell\'immagine', 'Point from the image center'), 'premium' => true, 'show_if' => 'profile_cursor_custom_url']); ?>

<div class="pe-field<?php echo $isPremium ? '' : ' is-locked'; ?>" data-search="<?php echo pe_h($tt('Cursore sui link', 'Cursor over links')); ?>" <?php echo $isPremium ? '' : 'data-premium-lock="1"'; ?>>
    <div class="pe-field-label"><span class="pe-field-title"><?php echo pe_h($tt('Cursore sopra i link', 'Cursor over links')); ?></span><?php echo pe_premium_chip(); ?></div>
    <div class="pe-media-url" data-media-url data-purpose="cursor" data-accept="image/jpeg,image/png,image/webp,image/gif,.cur,.ani">
        <input type="hidden" name="profile_cursor_custom_hover_url" id="peCursorHoverUrl" value="<?php echo pe_h($profile['profile_cursor_custom_hover_url'] ?? ''); ?>" <?php echo $isPremium ? '' : 'disabled'; ?>>
    </div>
</div>
<?php pe_toggle('profile_cursor_custom_hover_center', $pflag('profile_cursor_custom_hover_center', 0), ['label' => $tt('Punta dal centro dell\'immagine', 'Point from the image center'), 'premium' => true, 'show_if' => 'profile_cursor_custom_hover_url']); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-tilt', $tt('Inclinazione delle card', 'Card tilt'), $tt('Le card si inclinano in 3D quando ci passi sopra con il mouse.', 'Cards tilt in 3D when you hover them with the mouse.'), ['keywords' => 'tilt 3d inclinazione']); ?>
<?php pe_choice('tilt_preset', $tiltPreset, [
    ['value' => 'off', 'label' => 'Off'],
    ['value' => 'soft', 'label' => $tt('Leggera', 'Soft')],
    ['value' => 'medium', 'label' => $tt('Media', 'Medium')],
    ['value' => 'strong', 'label' => $tt('Forte', 'Strong')],
    ['value' => 'extreme', 'label' => $tt('Estrema', 'Extreme')],
    ['value' => 'custom', 'label' => $tt('Su misura', 'Custom')],
], ['label' => $tt('Intensità', 'Intensity')]); ?>
<input type="hidden" name="tilt_enabled" id="peTiltEnabled" value="<?php echo $tiltPreset === 'off' ? '0' : '1'; ?>">
<div data-show-if="tilt_preset=custom">
    <?php pe_slider('tilt_max', (int)($profile['tilt_max'] ?? 15), ['label' => $tt('Inclinazione massima', 'Maximum tilt'), 'min' => 0, 'max' => 45, 'format' => 'deg']); ?>
    <?php pe_slider('tilt_glare', (float)($profile['tilt_glare'] ?? 0), ['label' => $tt('Riflesso', 'Glare'), 'min' => 0, 'max' => 1, 'step' => 0.05, 'format' => 'ratio']); ?>
    <?php pe_slider('tilt_zoom', (float)($profile['tilt_zoom'] ?? 1.05), ['label' => 'Zoom', 'min' => 1, 'max' => 1.3, 'step' => 0.01, 'format' => 'x']); ?>
    <?php pe_slider('tilt_speed', (int)($profile['tilt_speed'] ?? 400), ['label' => $tt('Velocità di ritorno', 'Return speed'), 'min' => 100, 'max' => 2000, 'step' => 50, 'format' => 'ms']); ?>
</div>
<?php pe_group_end(); ?>
