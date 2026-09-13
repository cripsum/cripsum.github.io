<?php
/**
 * Sistema di stile del profilo.
 *
 * Una sola fonte di verita' per forme, bordi, riquadri, nome e tema: la usano
 * il profilo pubblico, l'editor (valori iniziali), l'anteprima della bozza, il
 * salvataggio e i preset. `assets/js/profile-style.js` fa la stessa
 * conversione per l'anteprima dal vivo: le due vanno cambiate insieme.
 *
 * Prima quattro impostazioni comandavano gli stessi angoli (forma globale,
 * stile angoli, forma bottoni, raggio card) e si sovrascrivevano a vicenda.
 * Ora ogni elemento ha un solo raggio:
 *
 *   --p-radius-surface  riquadri: card, sezioni
 *   --p-radius-item     elementi dentro i riquadri e box delle statistiche
 *   --p-radius-control  pulsanti, link, card social, tag, mini-badge
 *   --p-radius-icon     riquadri delle icone
 *
 * I nomi vecchi (--radius-lg, --ui-shape-button, --profile-corner-radius, ...)
 * vengono derivati dagli stessi valori, cosi' il CSS che li usa ancora resta
 * coerente.
 *
 * Nessuna colonna nuova: i valori vecchi si convertono in lettura e al primo
 * salvataggio vengono riscritti nel formato nuovo.
 */

const PROFILE_STYLE_CONTROL_SHAPES = ['square', 'rounded', 'pill'];
const PROFILE_STYLE_AVATAR_SHAPES = ['circle', 'squircle', 'square', 'hexagon', 'octagon', 'badge'];
const PROFILE_STYLE_LINK_STYLES = ['glass', 'solid', 'outline', 'neon'];
const PROFILE_STYLE_THEMES = ['dark', 'light', 'auto'];

/** Effetti del nome. Sono esclusivi: la sfumatura e' uno di loro. */
const PROFILE_NAME_EFFECTS = ['none', 'gradient', 'rainbow', 'glow', 'sparkles', 'fire', 'water', 'glitch', 'neon', 'bounce'];

/** Effetti che disegnano il nome con colori propri e ignorano "Colore nome". */
const PROFILE_NAME_EFFECTS_OWN_COLORS = ['gradient', 'rainbow', 'fire', 'water'];

/** Anelli della foto profilo (assets/css/profile-rings.css). */
const PROFILE_RING_STYLES = ['none', 'spin', 'pulse', 'orbit', 'glow', 'dual', 'rainbow', 'halo', 'neon', 'spark', 'glitch'];

/** Stile dell'anello da un valore salvato o inviato. */
function profile_ring_normalize($style, bool $enabled = true): string
{
    $style = is_string($style) ? strtolower(trim($style)) : '';
    if (!$enabled) {
        return 'none';
    }
    return in_array($style, PROFILE_RING_STYLES, true) ? $style : 'spin';
}

/** Lo stile dell'anello di un profilo. */
function profile_ring_style(array $p): string
{
    return profile_ring_normalize($p['avatar_ring_style'] ?? 'spin', (int)($p['avatar_ring_enabled'] ?? 1) === 1);
}

/** Gli strati dell'anello: ogni stile accende quelli che gli servono. */
function profile_ring_inner_html(): string
{
    return '<i class="ring-glow"><i class="ring-layer"></i></i>'
        . '<i class="ring-layer ring-a"></i><i class="ring-layer ring-b"></i><i class="ring-layer ring-c"></i>'
        . '<i class="ring-track"><i class="ring-dot"></i><i class="ring-dot"></i><i class="ring-dot"></i><i class="ring-dot"></i></i>';
}

function profile_style_int($value, int $min, int $max, int $default): int
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return $default;
    }
    return max($min, min($max, (int)round((float)$value)));
}

function profile_style_float($value, float $min, float $max, float $default): float
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return $default;
    }
    return max($min, min($max, (float)$value));
}

function profile_style_hex(?string $value): ?string
{
    $value = trim((string)$value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
}

function profile_style_rgb(string $hex): array
{
    $n = hexdec(ltrim($hex, '#'));
    return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
}

/**
 * Forma di pulsanti, tag e icone.
 *
 * Il formato nuovo sta in `profile_ui_shape` (square/rounded/pill) e
 * `profile_button_shape` viene tenuto allineato. Per i profili salvati prima,
 * la forma globale al valore di default ("circle") non diceva niente, mentre
 * la forma bottoni era quella che si vedeva davvero sui link.
 */
function profile_style_control_shape(?string $uiShape, ?string $buttonShape): string
{
    $uiShape = (string)$uiShape;
    $buttonShape = (string)$buttonShape;

    if ($uiShape === '' || $uiShape === 'circle') {
        return match ($buttonShape) {
            'sharp' => 'square',
            'rounded' => 'rounded',
            default => 'pill',
        };
    }

    return match ($uiShape) {
        'square' => 'square',
        'rounded', 'soft', 'square-rounded' => 'rounded',
        default => 'pill',
    };
}

/** Il valore da scrivere in `profile_button_shape` per una forma nuova. */
function profile_style_button_shape_for(string $controlShape): string
{
    return match ($controlShape) {
        'square' => 'sharp',
        'rounded' => 'rounded',
        default => 'pill',
    };
}

/**
 * Tutte le impostazioni di stile di un profilo, normalizzate.
 *
 * Accetta sia una riga di `utenti` sia una bozza dell'editor (stessi nomi di
 * colonna).
 */
function profile_style_resolve(array $p): array
{
    $theme = in_array($p['profile_theme'] ?? '', PROFILE_STYLE_THEMES, true) ? $p['profile_theme'] : 'dark';
    $isLight = $theme === 'light';

    $accent = profile_style_hex($p['accent_color'] ?? null) ?? '#0f5bff';
    $secondary = profile_style_hex($p['profile_secondary_color'] ?? null) ?? $accent;

    $borderWidth = profile_style_int($p['profile_border_width'] ?? null, 0, 5, 1);
    $borderOpacity = profile_style_int($p['profile_border_opacity'] ?? null, 0, 100, 100);
    $borderColor = profile_style_hex($p['profile_border_color'] ?? null);
    $borderStyle = (string)($p['profile_border_style'] ?? 'solid');

    // Stile "Nessun bordo" di prima: equivale a spessore 0.
    if ($borderStyle === 'none') {
        $borderWidth = 0;
    }
    // Senza un colore scelto il bordo era il 12% del colore del testo del
    // tema: lo stesso aspetto espresso con colore e opacita' espliciti. In
    // pagina il colore segue il tema attivo (vedi border_color_auto).
    $borderColorAuto = $borderColor === null;
    if ($borderColorAuto) {
        $borderColor = $isLight ? '#111827' : '#ffffff';
        $borderOpacity = (int)round($borderOpacity * 0.12);
    }

    $cardColor = profile_style_hex($p['profile_card_color'] ?? null);

    return [
        'theme' => $theme,
        'accent' => $accent,
        'secondary' => $secondary,
        'card_color' => $cardColor ?? ($isLight ? '#ffffff' : '#080c18'),
        // Senza un colore scelto i riquadri seguono il tema attivo, anche quando
        // chi visita lo cambia dal menu del profilo.
        'card_color_auto' => $cardColor === null,
        'text_color' => profile_style_hex($p['profile_text_color'] ?? null),
        'card_opacity' => profile_style_int($p['profile_card_opacity'] ?? null, 0, 100, 68),
        'card_blur' => profile_style_int($p['profile_card_blur'] ?? null, 0, 40, 20),
        'surface_radius' => profile_style_int($p['profile_border_radius'] ?? null, 0, 40, 30),
        'control_shape' => profile_style_control_shape($p['profile_ui_shape'] ?? null, $p['profile_button_shape'] ?? null),
        'avatar_shape' => in_array($p['profile_avatar_shape'] ?? '', PROFILE_STYLE_AVATAR_SHAPES, true) ? $p['profile_avatar_shape'] : 'circle',
        'border_width' => $borderWidth,
        'border_color' => $borderColor,
        'border_color_auto' => $borderColorAuto,
        'border_opacity' => $borderOpacity,
        // L'editor manda l'interruttore come 1/0, il database tiene 'glow'.
        'border_glow' => in_array($borderStyle, ['glow', '1'], true),
        'link_style' => in_array($p['profile_link_style'] ?? '', PROFILE_STYLE_LINK_STYLES, true) ? $p['profile_link_style'] : 'glass',
        'socials_style' => ($p['profile_socials_style'] ?? '') === 'icons' ? 'icons' : 'cards',
        'social_size' => profile_style_int($p['profile_social_size'] ?? null, 32, 72, 42),
        'social_spacing' => profile_style_int($p['profile_icon_spacing'] ?? null, 0, 24, 8),
        'badge_size' => profile_style_int($p['profile_badge_size'] ?? null, 16, 60, 24),
        'button_height' => profile_style_int($p['profile_button_size'] ?? null, 32, 80, 48),
        'bg_overlay_opacity' => profile_style_float($p['profile_bg_overlay_opacity'] ?? null, 0, 1, 1),
        'bg_blur' => profile_style_int($p['profile_bg_blur'] ?? null, 0, 40, 0),
        'bg_orbs_opacity' => profile_style_float($p['profile_bg_orbs_opacity'] ?? null, 0, 1, 0.45),
    ];
}

/** Le variabili CSS che il profilo mette sul <body>. */
function profile_style_css_vars(array $s): array
{
    $r = (int)$s['surface_radius'];
    $item = (int)round($r * 0.7);

    [$controlRadius, $iconRadius] = match ($s['control_shape']) {
        'square' => ['0px', '0px'],
        'rounded' => [max(6, min(16, (int)round($r * 0.45))) . 'px', max(6, min(14, (int)round($r * 0.4))) . 'px'],
        default => ['999px', '50%'],
    };

    [$ar, $ag, $ab] = profile_style_rgb($s['accent']);
    [$br, $bg, $bb] = profile_style_rgb($s['border_color']);
    $alpha = round($s['border_opacity'] / 100, 3);
    $width = (int)$s['border_width'];

    // Colori "automatici": una base per tema definita nel CSS
    // (--p-card-base, --p-border-base), cosi' seguono il tema attivo.
    $cardBase = !empty($s['card_color_auto']) ? 'var(--p-card-base)' : $s['card_color'];
    $alphaPct = round($alpha * 100, 1);
    if (!empty($s['border_color_auto'])) {
        $borderColor = 'color-mix(in srgb, var(--p-border-base) ' . $alphaPct . '%, transparent)';
        $itemBorderColor = 'color-mix(in srgb, var(--p-border-base) ' . round($alphaPct * 0.75, 1) . '%, transparent)';
    } else {
        $borderColor = "rgba($br, $bg, $bb, $alpha)";
        $itemBorderColor = "rgba($br, $bg, $bb, " . round($alpha * 0.75, 3) . ')';
    }

    $vars = [
        '--accent' => $s['accent'],
        '--accent-rgb' => "$ar, $ag, $ab",
        '--profile-accent' => $s['accent'],
        '--accent-2' => $s['secondary'],

        '--profile-card-opacity' => (string)round($s['card_opacity'] / 100, 3),
        '--profile-card-blur' => $s['card_blur'] . 'px',
        '--card' => 'color-mix(in srgb, ' . $cardBase . ' ' . $s['card_opacity'] . '%, transparent)',
        '--profile-card-bg' => 'color-mix(in srgb, ' . $cardBase . ' ' . $s['card_opacity'] . '%, transparent)',
        '--card-strong' => 'color-mix(in srgb, ' . $cardBase . ' ' . min(100, $s['card_opacity'] + 20) . '%, transparent)',

        '--p-radius-surface' => $r . 'px',
        '--p-radius-item' => $item . 'px',
        '--p-radius-control' => $controlRadius,
        '--p-radius-icon' => $iconRadius,

        '--p-border-width' => $width . 'px',
        '--p-border-color' => $borderColor,
        // Gli elementi dentro i riquadri usano lo stesso colore con un bordo
        // sottile: con spessore 0 spariscono anche i loro.
        '--p-item-border-width' => min($width, 1) . 'px',
        '--p-item-border-color' => $itemBorderColor,
        '--p-border-glow' => $s['border_glow'] && $width > 0
            ? '0 0 ' . (10 + $width * 4) . "px rgba($br, $bg, $bb, 0.45)"
            : '0 0 0 transparent',

        // Nomi storici, derivati dagli stessi valori.
        '--radius-lg' => $r . 'px',
        '--radius-md' => $item . 'px',
        '--radius-sm' => (int)round($r * 0.47) . 'px',
        '--ui-shape-button' => $controlRadius,
        '--ui-shape-icon' => $iconRadius,
        '--profile-corner-radius' => $controlRadius,
        '--profile-border-width' => $width . 'px',
        '--profile-border-opacity' => (string)$alpha,
        '--profile-border-opacity-percent' => $s['border_opacity'] . '%',
        '--border' => $borderColor,

        '--social-icon-size' => $s['social_size'] . 'px',
        '--social-icon-spacing' => $s['social_spacing'] . 'px',
        '--badge-size' => $s['badge_size'] . 'px',
        '--button-height' => $s['button_height'] . 'px',

        '--profile-bg-overlay-opacity' => (string)$s['bg_overlay_opacity'],
        '--profile-bg-blur' => $s['bg_blur'] . 'px',
        '--profile-bg-scale' => (string)(1 + $s['bg_blur'] * 0.005),
        '--profile-bg-orbs-opacity' => (string)$s['bg_orbs_opacity'],
    ];

    if (empty($s['card_color_auto'])) {
        $vars['--profile-card-color'] = $s['card_color'];
    }
    if (empty($s['border_color_auto'])) {
        $vars['--profile-border-color'] = $s['border_color'];
    }
    if ($s['text_color'] !== null) {
        $vars['--profile-text-color'] = $s['text_color'];
    }

    return $vars;
}

/** Le variabili come dichiarazioni CSS, pronte per un attributo style. */
function profile_style_inline(array $vars): string
{
    $out = [];
    foreach ($vars as $name => $value) {
        // I valori vengono tutti da profile_style_resolve(): numeri, colori
        // esadecimali e poche parole chiave. Il filtro e' una rete di
        // sicurezza per non uscire mai dall'attributo.
        $value = preg_replace('/[^a-zA-Z0-9#%.,()\s-]/', '', (string)$value);
        $out[] = $name . ': ' . $value;
    }
    return implode('; ', $out) . ';';
}

/**
 * Stile del nome normalizzato: un colore e un effetto.
 *
 * Il formato di prima aveva "tipo colore" (bianco / singolo / sfumatura) e
 * "animazione" separati. Si converte cosi':
 *   - bianco di base   -> colore = colore del testo del profilo
 *   - colore singolo   -> quel colore
 *   - sfumatura        -> effetto "gradient", se non c'era gia' un altro effetto
 */
function profile_name_style_normalize($raw, ?string $textColor = null, string $theme = 'dark'): array
{
    $data = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? json_decode($raw, true) : []);
    if (!is_array($data)) {
        $data = [];
    }

    $defaultColor = profile_style_hex($textColor) ?? ($theme === 'light' ? '#111827' : '#ffffff');

    if (array_key_exists('effect', $data) || array_key_exists('color', $data)) {
        $color = profile_style_hex($data['color'] ?? null) ?? $defaultColor;
        $effect = in_array($data['effect'] ?? '', PROFILE_NAME_EFFECTS, true) ? $data['effect'] : 'none';
    } else {
        $type = (string)($data['type'] ?? 'default');
        $animation = in_array($data['animation'] ?? '', PROFILE_NAME_EFFECTS, true) ? $data['animation'] : 'none';
        $color = $type === 'solid'
            ? (profile_style_hex($data['solid_color'] ?? null) ?? $defaultColor)
            : $defaultColor;
        $effect = ($type === 'gradient' && $animation === 'none') ? 'gradient' : $animation;
    }

    return [
        'color' => $color,
        'effect' => $effect,
        'grad_color1' => profile_style_hex($data['grad_color1'] ?? null) ?? '#ffffff',
        'grad_color2' => profile_style_hex($data['grad_color2'] ?? null) ?? '#8b5cf6',
        'grad_angle' => profile_style_int($data['grad_angle'] ?? null, 0, 360, 90),
        'glow_color' => profile_style_hex($data['glow_color'] ?? null) ?? '#8b5cf6',
    ];
}

/**
 * Lo stile del nome a partire dai campi del form dell'editor, o null se il
 * form non li contiene. Accetta anche i campi dell'editor di prima
 * (tipo colore + animazione), che una scheda aperta da prima puo' ancora
 * mandare.
 */
function profile_name_style_from_input(array $input, ?string $textColor = null, string $theme = 'dark'): ?array
{
    $shared = [
        'grad_color1' => $input['profile_name_grad_color1'] ?? null,
        'grad_color2' => $input['profile_name_grad_color2'] ?? null,
        'grad_angle' => $input['profile_name_grad_angle'] ?? null,
        'glow_color' => $input['profile_name_glow_color'] ?? null,
    ];

    if (isset($input['profile_name_effect']) || isset($input['profile_name_color'])) {
        return profile_name_style_normalize($shared + [
            'color' => $input['profile_name_color'] ?? null,
            'effect' => $input['profile_name_effect'] ?? 'none',
        ], $textColor, $theme);
    }

    if (isset($input['profile_name_color_type']) || isset($input['profile_name_animation'])) {
        return profile_name_style_normalize($shared + [
            'type' => $input['profile_name_color_type'] ?? 'default',
            'solid_color' => $input['profile_name_solid_color'] ?? null,
            'animation' => $input['profile_name_animation'] ?? 'none',
        ], $textColor, $theme);
    }

    return null;
}

/**
 * Le colonne di stile da salvare, a partire da quello che manda l'editor.
 *
 * Usata dal salvataggio del profilo e da quello dei preset, cosi' i due non
 * possono piu' interpretare gli stessi campi in modo diverso. `$current` e' la
 * riga attuale: serve per il nome quando il form non lo contiene.
 */
function profile_style_columns_from_input(array $input, array $current = []): array
{
    $theme = in_array($input['profile_theme'] ?? '', PROFILE_STYLE_THEMES, true) ? $input['profile_theme'] : 'dark';
    $textColor = profile_style_hex($input['profile_text_color'] ?? null);

    $controlShape = profile_style_control_shape($input['profile_ui_shape'] ?? null, $input['profile_button_shape'] ?? null);

    $borderStyleIn = (string)($input['profile_border_style'] ?? 'solid');
    $borderWidth = profile_style_int($input['profile_border_width'] ?? null, 0, 5, 1);
    if ($borderStyleIn === 'none') {
        $borderWidth = 0;
    }

    $nameStyle = profile_name_style_from_input($input, $textColor, $theme)
        ?? profile_name_style_normalize($current['profile_name_style'] ?? null, $textColor, $theme);

    return [
        'profile_ui_shape' => $controlShape,
        'profile_button_shape' => profile_style_button_shape_for($controlShape),
        'profile_border_style' => in_array($borderStyleIn, ['glow', '1'], true) ? 'glow' : 'solid',
        'profile_border_width' => $borderWidth,
        'profile_name_style' => json_encode($nameStyle),
    ];
}

/** Attributi e variabili per il titolo con il nome. */
function profile_name_style_attributes(array $nameStyle): string
{
    $vars = [
        '--name-color' => $nameStyle['color'],
        '--name-grad-1' => $nameStyle['grad_color1'],
        '--name-grad-2' => $nameStyle['grad_color2'],
        '--name-angle' => $nameStyle['grad_angle'] . 'deg',
        '--name-glow-color' => $nameStyle['glow_color'],
    ];

    return 'data-name-effect="' . htmlspecialchars($nameStyle['effect'], ENT_QUOTES, 'UTF-8') . '"'
        // profile.js cerca ancora data-name-anim per le scintille.
        . ' data-name-anim="' . htmlspecialchars($nameStyle['effect'], ENT_QUOTES, 'UTF-8') . '"'
        . ' style="' . htmlspecialchars(profile_style_inline($vars), ENT_QUOTES, 'UTF-8') . '"';
}

/**
 * Layout come lo sceglie l'editor: uno fra cinque. "A schermate" (scroll snap)
 * nel database e' ancora layout standard + profile_layout_snap = 1.
 */
function profile_layout_choice_for(array $p): array
{
    if ((int)($p['profile_layout_snap'] ?? 0) === 1) {
        return ['scrollsnap'];
    }
    $layout = (string)($p['profile_layout'] ?? 'standard');
    $layout = ['left-tabs' => 'standard', 'right-tabs' => 'showcase', 'stacked' => 'clean', 'center-split' => 'compact'][$layout] ?? $layout;
    return [in_array($layout, ['standard', 'compact', 'showcase', 'clean'], true) ? $layout : 'standard'];
}

/**
 * [layout, snap] da salvare. Accetta la scelta unica dell'editor nuovo
 * (`profile_layout_choice`) e, in sua assenza, i due campi di prima.
 */
function profile_layout_from_input(array $input, bool $isPremium): array
{
    $choice = $input['profile_layout_choice'] ?? null;
    if ($choice === null) {
        [$choice] = profile_layout_choice_for($input);
    }
    if ($choice === 'scrollsnap') {
        return $isPremium ? ['standard', 1] : ['standard', 0];
    }
    return [in_array($choice, ['standard', 'compact', 'showcase', 'clean'], true) ? $choice : 'standard', 0];
}

/** Testo leggibile sopra un colore: scuro sui colori chiari, bianco sugli altri. */
function profile_style_contrast_text(string $hex): string
{
    [$r, $g, $b] = profile_style_rgb($hex);
    $lin = static function (int $c): float {
        $c /= 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    return $luminance > 0.45 ? '#111827' : '#ffffff';
}

/**
 * Classe e variabili di una tag del profilo.
 *
 * Tre stili: predefinito (tinta dell'accento), colore, sfumatura. Il testo
 * sceglie da solo il contrasto: prima era sempre bianco, illeggibile sui
 * colori chiari.
 */
function profile_tag_view(array $tag): array
{
    $color = profile_style_hex($tag['color'] ?? null);
    $gradient = profile_style_hex($tag['gradient'] ?? null);

    if ($color === null) {
        return ['class' => 'profile-tag-pill', 'style' => ''];
    }

    $vars = ['--tag-color' => $color];
    if ($gradient !== null) {
        $vars['--tag-color-2'] = $gradient;
        // Il testo deve reggere su tutta la sfumatura: conta il piu' chiaro.
        [$r1, $g1, $b1] = profile_style_rgb($color);
        [$r2, $g2, $b2] = profile_style_rgb($gradient);
        $mid = sprintf('#%02x%02x%02x', intdiv($r1 + $r2, 2), intdiv($g1 + $g2, 2), intdiv($b1 + $b2, 2));
        $vars['--tag-fg'] = profile_style_contrast_text($mid);
        return ['class' => 'profile-tag-pill is-gradient', 'style' => profile_style_inline($vars)];
    }

    $vars['--tag-fg'] = profile_style_contrast_text($color);
    return ['class' => 'profile-tag-pill is-colored', 'style' => profile_style_inline($vars)];
}

/**
 * Livelli dell'inclinazione delle card. Una sola tabella: prima PHP e JS
 * avevano valori diversi e dopo ogni ricarica il livello tornava
 * "Personalizzato".
 */
function profile_tilt_presets(): array
{
    return [
        'soft' => ['max' => 6, 'glare' => 0.1, 'zoom' => 1.01, 'speed' => 900],
        'medium' => ['max' => 15, 'glare' => 0.25, 'zoom' => 1.05, 'speed' => 600],
        'strong' => ['max' => 25, 'glare' => 0.4, 'zoom' => 1.08, 'speed' => 400],
        'extreme' => ['max' => 35, 'glare' => 0.6, 'zoom' => 1.12, 'speed' => 200],
    ];
}

/** Il livello corrispondente ai valori salvati: off, un livello o custom. */
function profile_tilt_preset_for(array $p): string
{
    if ((int)($p['tilt_enabled'] ?? 1) !== 1) {
        return 'off';
    }

    $max = (int)($p['tilt_max'] ?? 15);
    $glare = (float)($p['tilt_glare'] ?? 0);
    $zoom = (float)($p['tilt_zoom'] ?? 1.05);
    $speed = (int)($p['tilt_speed'] ?? 400);

    foreach (profile_tilt_presets() as $name => $preset) {
        if ($max === $preset['max'] && abs($glare - $preset['glare']) < 0.01
            && abs($zoom - $preset['zoom']) < 0.005 && $speed === $preset['speed']) {
            return $name;
        }
    }

    return 'custom';
}
