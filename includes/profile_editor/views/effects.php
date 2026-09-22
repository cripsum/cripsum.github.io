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
$cursorEffect = (string)($profile['profile_cursor_effect'] ?? 'none');
// Riquadri con l'anteprima vera: il motore di profile-effects.js li anima
// quando entrano nello schermo (editor.js).
$fxArt = static fn(string $kind) => static fn($o) => $o + ['art' => '<span class="pe-fx-art" data-' . $kind . '-fx="' . pe_h($o['value']) . '"></span>'];
$pageEffectOptions = array_map($fxArt('page'), $catalog['page_effects']);
$cursorEffectOptions = array_map($fxArt('cursor'), $catalog['cursor_effects']);
$fxColors = '--accent: ' . pe_h($style['accent']) . '; --accent-2: ' . pe_h($style['secondary']) . ';';
$nameEffectOptions = array_map(static fn($o) => $o + ['art' => '<span class="pe-name-art cn-name" data-name-effect="' . pe_h($o['value']) . '" data-text="Aa">Aa</span>'], $catalog['name_effects']);
$nameVars = '--name-color: ' . pe_h($nameStyle['color']) . '; --name-grad-1: ' . pe_h($nameStyle['grad_color1']) . '; --name-grad-2: ' . pe_h($nameStyle['grad_color2'])
    . '; --name-angle: ' . (int)$nameStyle['grad_angle'] . 'deg; --name-glow-color: ' . pe_h($nameStyle['glow_color']) . '; --accent: ' . pe_h($style['accent']) . ';'
    . ($nameStyle['glitch_color1'] ? ' --name-glitch-1: ' . pe_h($nameStyle['glitch_color1']) . ';' : '')
    . ($nameStyle['glitch_color2'] ? ' --name-glitch-2: ' . pe_h($nameStyle['glitch_color2']) . ';' : '')
    . ($nameStyle['sparkle_color'] ? ' --name-sparkle-color: ' . pe_h($nameStyle['sparkle_color']) . ';' : '');
?>

<?php pe_group('grp-name', $tt('Nome', 'Name'), $tt('Il nome in cima al profilo: scegli un colore oppure un effetto.', 'The name at the top of your profile: pick a color or an effect.'), ['keywords' => 'nome colore sfumatura gradient effetto animazione']); ?>
<div class="pe-name-fx" id="peNameFx" style="<?php echo $nameVars; ?>">
    <div class="pe-name-preview" id="peNamePreview" aria-hidden="true">
        <span class="pe-name-sample cn-name" data-name-effect="<?php echo pe_h($nameStyle['effect']); ?>" data-text="<?php echo pe_h(profile_display_name($profile)); ?>"><?php echo pe_h(profile_display_name($profile)); ?></span>
    </div>

    <?php pe_choice('profile_name_effect', $nameStyle['effect'], $nameEffectOptions, ['label' => $tt('Effetto', 'Effect'), 'variant' => 'tiles', 'columns' => 5, 'class' => 'pe-name-effects']); ?>
</div>

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
<div class="pe-row-2" data-show-if="profile_name_effect=glitch">
    <?php pe_color('profile_name_glitch_color1', $nameStyle['glitch_color1'], ['label' => $tt('Glitch: primo colore', 'Glitch: first color'), 'auto' => true, 'auto_label' => $tt('Classico', 'Classic'), 'auto_preview' => '#ff2a7a']); ?>
    <?php pe_color('profile_name_glitch_color2', $nameStyle['glitch_color2'], ['label' => $tt('Glitch: secondo colore', 'Glitch: second color'), 'auto' => true, 'auto_label' => $tt('Classico', 'Classic'), 'auto_preview' => '#25f4ff']); ?>
</div>
<div data-show-if="profile_name_effect=sparkles">
    <?php pe_color('profile_name_sparkle_color', $nameStyle['sparkle_color'], ['label' => $tt('Colore delle scintille', 'Sparkle color'), 'auto' => true, 'auto_label' => $tt('Misto', 'Mixed'), 'auto_preview' => '#fff4b8', 'help' => pe_h($tt('"Misto" alterna bianco, oro e i colori del profilo.', '"Mixed" alternates white, gold and your profile colors.'))]); ?>
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
<div class="pe-fx-picker" data-fx-picker style="<?php echo $fxColors; ?>">
    <?php pe_choice('profile_effect', $pageEffect, $pageEffectOptions, ['label' => $tt('Effetto', 'Effect'), 'variant' => 'tiles', 'columns' => 3, 'class' => 'pe-fx-choices', 'keywords' => 'particelle stelle aurora onde griglia rumore riflettore sakura pioggia grana scanline']); ?>
</div>
<?php pe_group_end(); ?>

<?php pe_group('grp-cursor', $tt('Cursore', 'Cursor'), $tt('Il puntatore del mouse sul tuo profilo.', 'The mouse pointer on your profile.'), ['keywords' => 'mouse puntatore cursore scia', 'premium' => true]); ?>
<div class="pe-fx-picker" data-fx-picker style="<?php echo $fxColors; ?>">
    <?php pe_choice('profile_cursor_effect', $cursorEffect, $cursorEffectOptions, ['label' => $tt('Effetto', 'Effect'), 'variant' => 'tiles', 'columns' => 3, 'class' => 'pe-fx-choices', 'keywords' => 'cursore mouse scia stelle cuori gattino cerchio']); ?>
</div>

<?php
/*
 * Immagine del cursore: due riquadri (normale e sopra cio' che si clicca).
 * Nel riquadro si clicca il punto che deve cliccare, la "punta"; la misura
 * vale per entrambi. Sotto, un'area dove provarlo con il cursore vero
 * (assets/js/profile-cursor.js, lo stesso del profilo). La logica e' in
 * editor.js, "Cursore personalizzato".
 */
$cursorDisabled = $isPremium ? '' : ' disabled';
$cursorSlots = [
    [
        'key' => 'base',
        'title' => $tt('Normale', 'Default'),
        'desc' => $tt('Su tutta la pagina', 'Across the whole page'),
        'url' => 'profile_cursor_custom_url',
        'url_id' => 'peCursorUrl',
        'hotspot' => 'profile_cursor_hotspot',
        'center' => 'profile_cursor_custom_center',
    ],
    [
        'key' => 'hover',
        'title' => $tt('Sopra link e pulsanti', 'Over links and buttons'),
        'desc' => $tt('Dove si può cliccare', 'Wherever you can click'),
        'url' => 'profile_cursor_custom_hover_url',
        'url_id' => 'peCursorHoverUrl',
        'hotspot' => 'profile_cursor_hover_hotspot',
        'center' => 'profile_cursor_custom_hover_center',
    ],
];
?>
<div class="pe-field pe-cursor<?php echo $isPremium ? '' : ' is-locked'; ?>" id="peCursor" data-search="<?php echo pe_h($tt('Immagine del cursore punta dimensione link', 'Cursor image hotspot size links')); ?>" <?php echo $isPremium ? '' : 'data-premium-lock="1"'; ?>>
    <div class="pe-field-label"><span class="pe-field-title"><?php echo pe_h($tt('Immagine del cursore', 'Cursor image')); ?></span><?php echo pe_premium_chip(); ?></div>

    <div class="pe-cursor-slots">
        <?php foreach ($cursorSlots as $slot):
            $slotUrl = (string)($profile[$slot['url']] ?? '');
            $slotHotspot = $slotUrl !== '' ? profile_cursor_hotspot_for($profile, $slot['hotspot'], $slot['center']) : '0,0';
        ?>
        <div class="pe-cursor-slot<?php echo $slotUrl === '' ? ' is-empty' : ''; ?>" data-cursor-slot="<?php echo pe_h($slot['key']); ?>">
            <div class="pe-cursor-slot-head">
                <strong><?php echo pe_h($slot['title']); ?></strong>
                <small><?php echo pe_h($slot['desc']); ?></small>
            </div>
            <div class="pe-cursor-stage" data-cursor-stage tabindex="0"
                aria-label="<?php echo pe_h($tt('Punta del cursore: clicca sull\'immagine o usa le frecce', 'Cursor hotspot: click the image or use the arrow keys')); ?>">
                <span class="pe-cursor-figure">
                    <img alt="" draggable="false">
                    <span class="pe-cursor-hot" aria-hidden="true"></span>
                </span>
                <span class="pe-cursor-empty">
                    <i class="fa-solid fa-arrow-pointer" aria-hidden="true"></i>
                    <span><?php echo pe_h($tt('Carica un\'immagine', 'Upload an image')); ?></span>
                </span>
            </div>
            <div class="pe-cursor-tip">
                <span class="pe-cursor-tip-text"><?php echo pe_h($tt('Clicca il punto che deve cliccare', 'Click the point that clicks')); ?></span>
                <span class="pe-cursor-tip-presets">
                    <button type="button" class="pe-cursor-preset" data-cursor-hot="0,0" title="<?php echo pe_h($tt('Punta in alto a sinistra, come una freccia', 'Top-left point, like an arrow')); ?>"<?php echo $cursorDisabled; ?>>
                        <i class="fa-solid fa-arrow-pointer" aria-hidden="true"></i><span><?php echo pe_h($tt('Freccia', 'Arrow')); ?></span>
                    </button>
                    <button type="button" class="pe-cursor-preset" data-cursor-hot="50,50" title="<?php echo pe_h($tt('Punta al centro, come un mirino', 'Centered point, like a crosshair')); ?>"<?php echo $cursorDisabled; ?>>
                        <i class="fa-solid fa-crosshairs" aria-hidden="true"></i><span><?php echo pe_h($tt('Centro', 'Center')); ?></span>
                    </button>
                </span>
            </div>
            <p class="pe-cursor-note" data-cursor-note></p>
            <div class="pe-media-url pe-cursor-media" data-media-url data-purpose="cursor" data-accept="image/png,image/gif,image/webp,image/jpeg,.cur,.ani">
                <input type="hidden" name="<?php echo pe_h($slot['url']); ?>" id="<?php echo pe_h($slot['url_id']); ?>" value="<?php echo pe_h($slotUrl); ?>"<?php echo $cursorDisabled; ?>>
            </div>
            <input type="hidden" name="<?php echo pe_h($slot['hotspot']); ?>" value="<?php echo pe_h($slotHotspot); ?>" data-cursor-hot-input<?php echo $cursorDisabled; ?>>
        </div>
        <?php endforeach; ?>
    </div>

    <?php pe_slider('profile_cursor_size', profile_cursor_size_clean($profile['profile_cursor_size'] ?? null), [
        'label' => $tt('Dimensione', 'Size'),
        'min' => PROFILE_CURSOR_SIZE_MIN,
        'max' => PROFILE_CURSOR_SIZE_MAX,
        'step' => 2,
        'format' => 'px',
        'default' => PROFILE_CURSOR_SIZE_DEFAULT,
        'premium' => true,
        'keywords' => 'cursore grandezza misura grande piccolo',
        'help' => pe_h($tt(
            'Il lato più lungo dell\'immagine. 32 px è la misura dei cursori di sistema; più grande, il cursore lo disegna la pagina e segue il mouse con un attimo di ritardo.',
            'The longest side of the image. 32 px is the size of system cursors; bigger, the page draws the cursor and it follows the mouse with a slight delay.'
        )),
    ]); ?>

    <div class="pe-cursor-test is-empty" id="peCursorTest">
        <span class="pe-cursor-test-hint">
            <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>
            <span data-cursor-test-text
                data-ready="<?php echo pe_h($tt('Passa qui sopra per provarlo', 'Hover here to try it')); ?>"
                data-empty="<?php echo pe_h($tt('Carica un\'immagine per provarla qui', 'Upload an image to try it here')); ?>"><?php echo pe_h($tt('Carica un\'immagine per provarla qui', 'Upload an image to try it here')); ?></span>
        </span>
        <span class="pe-cursor-test-link" aria-hidden="true"><i class="fa-solid fa-link" aria-hidden="true"></i><?php echo pe_h($tt('Un link', 'A link')); ?></span>
    </div>

    <p class="pe-help"><?php echo pe_h($tt(
        'PNG con lo sfondo trasparente, GIF animate, CUR o ANI, fino a 2 MB. I bordi trasparenti vengono tolti e le proporzioni restano quelle dell\'immagine.',
        'PNG with a transparent background, animated GIFs, CUR or ANI, up to 2 MB. Transparent edges are trimmed and the image keeps its proportions.'
    )); ?></p>
</div>
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
