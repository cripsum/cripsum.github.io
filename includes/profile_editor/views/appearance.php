<?php
/** Area "Aspetto": tema, colori, layout, forme, riquadri, bordi, testo, sfondo. */

$layoutArt = [
    'standard' => '<span class="art-layout art-layout--standard"><i></i><b></b></span>',
    'showcase' => '<span class="art-layout art-layout--showcase"><b></b><i></i></span>',
    'compact' => '<span class="art-layout art-layout--compact"><b></b><i></i><b></b></span>',
    'clean' => '<span class="art-layout art-layout--clean"><i></i><b></b></span>',
    'scrollsnap' => '<span class="art-layout art-layout--snap"><b></b><b></b><b></b></span>',
];
$layoutOptions = array_map(static fn($o) => $o + ['art' => $layoutArt[$o['value']]], $catalog['layouts']);

$shapeOptions = array_map(static fn($o) => $o + ['art' => '<span class="art-shape art-shape--' . $o['value'] . '"><i></i><b></b></span>'], $catalog['control_shapes']);
$avatarOptions = array_map(static fn($o) => $o + ['art' => '<span class="art-avatar" data-shape="' . $o['value'] . '"></span>'], $catalog['avatar_shapes']);
$linkOptions = array_map(static fn($o) => $o + ['art' => '<span class="art-link art-link--' . $o['value'] . '"></span>'], $catalog['link_styles']);
?>

<?php pe_group('grp-theme', $tt('Tema', 'Theme'), $tt('Un punto di partenza: cambia colori, forme e font in un clic. Poi puoi ritoccare tutto.', 'A starting point: changes colors, shapes and fonts in one click. You can tweak everything afterwards.'), ['keywords' => 'preset temi look stile']); ?>
<div class="pe-themes" role="list">
    <?php foreach ($catalog['themes'] as $theme): ?>
        <button type="button" class="pe-theme<?php echo $isPremium ? '' : ' is-locked'; ?>" data-theme-preset="<?php echo pe_h($theme['value']); ?>" role="listitem" <?php echo $isPremium ? '' : 'data-premium-lock="1"'; ?>>
            <span class="pe-theme-swatches" aria-hidden="true">
                <?php foreach ($theme['swatches'] as $swatch): ?><span style="background: <?php echo pe_h($swatch); ?>"></span><?php endforeach; ?>
            </span>
            <span class="pe-theme-name"><?php echo pe_h($theme['label']); ?></span>
            <?php if (!$isPremium): ?><i class="fa-solid fa-crown pe-choice-lock" aria-hidden="true"></i><?php endif; ?>
        </button>
    <?php endforeach; ?>
</div>

<div class="pe-presets" data-search="<?php echo pe_h($tt('I miei preset salva carica', 'My presets save load')); ?>">
    <div class="pe-presets-head">
        <h4><?php echo pe_h($tt('I miei preset', 'My presets')); ?> <?php echo pe_premium_chip(); ?></h4>
        <button type="button" class="pe-btn pe-btn-secondary pe-btn-sm<?php echo $isPremium ? '' : ' is-locked'; ?>" id="peSavePreset" <?php echo $isPremium ? '' : 'data-premium-lock="1"'; ?>>
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span><?php echo pe_h($tt('Salva l\'aspetto attuale', 'Save current look')); ?></span>
        </button>
    </div>
    <p class="pe-help"><?php echo pe_h($tt('Un preset salva l\'intero profilo, contenuti compresi. Ne puoi tenere 5.', 'A preset saves your whole profile, content included. You can keep 5.')); ?></p>
    <div class="pe-presets-list" id="pePresets"></div>
</div>
<?php pe_group_end(); ?>

<?php pe_group('grp-colors', $tt('Colori', 'Colors'), null, ['keywords' => 'accento colore tema chiaro scuro palette']); ?>
<?php pe_choice('profile_theme', $style['theme'], [
    ['value' => 'dark', 'label' => $tt('Scuro', 'Dark'), 'icon' => 'fa-solid fa-moon'],
    ['value' => 'light', 'label' => $tt('Chiaro', 'Light'), 'icon' => 'fa-solid fa-sun'],
    ['value' => 'auto', 'label' => $tt('Automatico', 'Automatic'), 'icon' => 'fa-solid fa-circle-half-stroke'],
], [
    'label' => $tt('Modalità', 'Mode'),
    'help' => pe_h($tt('Automatico segue il tema del dispositivo di chi visita.', 'Automatic follows the visitor\'s device theme.')),
]); ?>

<div class="pe-field" data-search="<?php echo pe_h($tt('Palette rapide', 'Quick palettes')); ?>">
    <div class="pe-field-label"><span class="pe-field-title"><?php echo pe_h($tt('Palette rapide', 'Quick palettes')); ?></span></div>
    <div class="pe-palettes">
        <?php foreach ($catalog['palettes'] as $index => $palette): ?>
            <button type="button" class="pe-palette-swatch" data-palette="<?php echo $index; ?>" title="<?php echo pe_h($palette['label']); ?>" style="--a: <?php echo pe_h($palette['accent_color']); ?>; --b: <?php echo pe_h($palette['profile_secondary_color']); ?>">
                <span class="visually-hidden"><?php echo pe_h($palette['label']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="pe-row-2">
    <?php pe_color('accent_color', $style['accent'], ['label' => $tt('Colore principale', 'Main color'), 'keywords' => 'accent accento']); ?>
    <?php pe_color('profile_secondary_color', $style['secondary'], ['label' => $tt('Colore secondario', 'Secondary color'), 'keywords' => 'secondary']); ?>
</div>
<?php pe_color('profile_text_color', $style['text_color'], [
    'label' => $tt('Colore del testo', 'Text color'),
    'auto' => true,
    'auto_label' => $tt('Segue la modalità', 'Follows the mode'),
    'keywords' => 'testo text',
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-layout', 'Layout', $tt('Come si dispongono profilo e sezioni sugli schermi grandi. Sul telefono è sempre una colonna.', 'How your profile and sections are arranged on large screens. On phones it is always one column.'), ['keywords' => 'disposizione colonne scroll snap']); ?>
<?php pe_choice('profile_layout_choice', $layoutChoice, $layoutOptions, ['variant' => 'tiles', 'columns' => 3]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-shapes', $tt('Forme', 'Shapes'), null, ['keywords' => 'angoli arrotondati raggio forma']); ?>
<?php pe_slider('profile_border_radius', $style['surface_radius'], [
    'label' => $tt('Arrotondamento dei riquadri', 'Box roundness'),
    'min' => 0, 'max' => 40, 'format' => 'px', 'default' => 30,
    'keywords' => 'raggio radius card',
    'help' => pe_h($tt('Card, sezioni e box delle statistiche.', 'Cards, sections and stat boxes.')),
]); ?>
<?php pe_choice('profile_ui_shape', $style['control_shape'], $shapeOptions, [
    'label' => $tt('Forma di pulsanti, tag e icone', 'Shape of buttons, tags and icons'),
    'variant' => 'tiles',
    'columns' => 3,
    'keywords' => 'bottoni pillola pill',
]); ?>
<?php pe_choice('profile_avatar_shape', $style['avatar_shape'], $avatarOptions, [
    'label' => $tt('Forma della foto profilo', 'Profile photo shape'),
    'variant' => 'tiles',
    'columns' => 6,
    'keywords' => 'avatar pfp cerchio esagono',
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-surfaces', $tt('Riquadri', 'Boxes'), $tt('Lo sfondo di card e sezioni.', 'The background of cards and sections.'), ['keywords' => 'card sfondo trasparenza vetro blur']); ?>
<?php pe_color('profile_card_color', profile_style_hex($profile['profile_card_color'] ?? null), [
    'label' => $tt('Colore', 'Color'),
    'auto' => true,
    'auto_label' => $tt('Segue la modalità', 'Follows the mode'),
    'keywords' => 'card colore',
]); ?>
<?php pe_slider('profile_card_opacity', $style['card_opacity'], ['label' => $tt('Opacità', 'Opacity'), 'min' => 0, 'max' => 100, 'format' => '%', 'default' => 68, 'help' => pe_h($tt('0% rende i riquadri trasparenti.', '0% makes boxes transparent.'))]); ?>
<?php pe_slider('profile_card_blur', $style['card_blur'], ['label' => $tt('Effetto vetro (sfocatura)', 'Glass effect (blur)'), 'min' => 0, 'max' => 40, 'format' => 'px', 'default' => 20]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-borders', $tt('Bordi', 'Borders'), $tt('Valgono per tutti i riquadri, box delle statistiche compresi. Gli elementi dentro i riquadri usano lo stesso colore con un bordo sottile.', 'They apply to every box, stat boxes included. Items inside boxes use the same color with a thin border.'), ['keywords' => 'bordo contorno spessore']); ?>
<?php pe_slider('profile_border_width', $style['border_width'], ['label' => $tt('Spessore', 'Thickness'), 'min' => 0, 'max' => 5, 'format' => 'px', 'zero_label' => $tt('Nessuno', 'None'), 'default' => 1]); ?>
<div data-show-if="profile_border_width!=0">
    <?php pe_color('profile_border_color', profile_style_hex($profile['profile_border_color'] ?? null), [
        'label' => $tt('Colore', 'Color'),
        'auto' => true,
        'auto_label' => $tt('Segue la modalità', 'Follows the mode'),
        'keywords' => 'bordo colore',
    ]); ?>
    <?php pe_slider('profile_border_opacity', (int)($profile['profile_border_opacity'] ?? 100), ['label' => $tt('Opacità', 'Opacity'), 'min' => 0, 'max' => 100, 'format' => '%', 'default' => 100]); ?>
    <?php pe_toggle('profile_border_style', $style['border_glow'], [
        'label' => $tt('Bagliore', 'Glow'),
        'description' => $tt('Un alone del colore del bordo attorno ai riquadri.', 'A halo in the border color around boxes.'),
        'keywords' => 'glow bagliore neon',
    ]); ?>
</div>
<?php pe_group_end(); ?>

<?php pe_group('grp-buttons', $tt('Pulsanti e social', 'Buttons and socials'), null, ['keywords' => 'link pulsanti social icone stile']); ?>
<?php pe_choice('profile_link_style', $style['link_style'], $linkOptions, ['label' => $tt('Stile dei pulsanti link', 'Link button style'), 'variant' => 'tiles', 'columns' => 4]); ?>
<?php pe_slider('profile_button_size', $style['button_height'], ['label' => $tt('Altezza dei pulsanti link', 'Link button height'), 'min' => 32, 'max' => 80, 'format' => 'px', 'default' => 48]); ?>
<?php pe_choice('profile_socials_style', $style['socials_style'], [
    ['value' => 'cards', 'label' => $tt('Card con nome', 'Cards with name'), 'icon' => 'fa-solid fa-table-cells-large'],
    ['value' => 'icons', 'label' => $tt('Solo icone', 'Icons only'), 'icon' => 'fa-solid fa-circle-dot'],
], ['label' => $tt('Social', 'Socials')]); ?>
<?php pe_slider('profile_social_size', $style['social_size'], ['label' => $tt('Dimensione icone social', 'Social icon size'), 'min' => 32, 'max' => 72, 'format' => 'px', 'default' => 42, 'show_if' => 'profile_socials_style=icons']); ?>
<?php pe_slider('profile_icon_spacing', $style['social_spacing'], ['label' => $tt('Spazio fra le icone', 'Space between icons'), 'min' => 0, 'max' => 24, 'format' => 'px', 'default' => 8, 'show_if' => 'profile_socials_style=icons']); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-font', $tt('Testo', 'Text'), null, ['keywords' => 'font carattere tipografia']); ?>
<?php pe_select('profile_font', (string)($profile['profile_font'] ?? 'Poppins'), $catalog['fonts'], ['label' => 'Font', 'font_preview' => true]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-background', $tt('Sfondo', 'Background'), null, ['keywords' => 'sfondo video immagine background banner']); ?>
<div class="pe-bg-editor">
    <div class="pe-bg-preview" id="peBgPreview" data-type="<?php echo pe_h($backgroundType); ?>">
        <?php if ($hasCustomBackground && str_starts_with($backgroundType, 'video/')): ?>
            <video src="<?php echo pe_h($backgroundUrl); ?>" muted loop playsinline autoplay></video>
        <?php elseif ($hasCustomBackground): ?>
            <img src="<?php echo pe_h($backgroundUrl); ?>" alt="">
        <?php else: ?>
            <span class="pe-bg-default"><i class="fa-solid fa-film" aria-hidden="true"></i><?php echo pe_h($tt('Sfondo predefinito', 'Default background')); ?></span>
        <?php endif; ?>
    </div>
    <div class="pe-bg-actions">
        <label class="pe-btn pe-btn-secondary" for="peBannerInput"><i class="fa-solid fa-upload" aria-hidden="true"></i><span><?php echo pe_h($tt('Carica immagine o video', 'Upload image or video')); ?></span></label>
        <input type="file" name="banner" id="peBannerInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" hidden>
        <p class="pe-help"><?php echo pe_h($tt('Foto, GIF o video MP4/WEBM, fino a ', 'Photo, GIF or MP4/WEBM video, up to ') . profile_format_bytes($uploadLimits['background'])); ?>.</p>
    </div>
</div>
<?php pe_slider('profile_bg_overlay_opacity', $style['bg_overlay_opacity'], ['label' => $tt('Scurisci lo sfondo', 'Darken background'), 'min' => 0, 'max' => 1, 'step' => 0.05, 'format' => 'ratio', 'default' => 1]); ?>
<?php pe_slider('profile_bg_blur', $style['bg_blur'], ['label' => $tt('Sfoca lo sfondo', 'Blur background'), 'min' => 0, 'max' => 40, 'format' => 'px', 'default' => 0]); ?>
<?php pe_slider('profile_bg_orbs_opacity', $style['bg_orbs_opacity'], ['label' => $tt('Luci colorate ai lati', 'Colored side glows'), 'min' => 0, 'max' => 1, 'step' => 0.05, 'format' => 'ratio', 'default' => 0.45]); ?>
<?php pe_group_end(); ?>

<div class="pe-reset">
    <button type="button" class="pe-btn pe-btn-danger-ghost" id="peResetAppearance">
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span><?php echo pe_h($tt('Ripristina l\'aspetto predefinito', 'Reset to default style')); ?></span>
    </button>
</div>
