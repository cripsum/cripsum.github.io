/**
 * Sistema di stile del profilo, lato browser.
 *
 * Gemello di includes/profile_style.php: stesse regole, stessi nomi. Serve
 * all'anteprima dell'editor per applicare subito forme, bordi, colori e nome
 * senza aspettare che il server ridisegni la pagina. Se cambi una regola qui,
 * cambiala anche la', e viceversa.
 */
(function (global) {
    'use strict';

    const THEMES = ['dark', 'light', 'auto'];
    const AVATAR_SHAPES = ['circle', 'squircle', 'square', 'hexagon', 'octagon', 'badge'];
    const LINK_STYLES = ['glass', 'solid', 'outline', 'neon'];
    const NAME_EFFECTS = ['none', 'gradient', 'rainbow', 'glow', 'sparkles', 'fire', 'water', 'glitch', 'neon', 'bounce'];
    const NAME_EFFECTS_OWN_COLORS = ['gradient', 'rainbow', 'fire', 'water'];

    const hex = (value) => {
        const v = String(value ?? '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(v) ? v.toLowerCase() : null;
    };

    const int = (value, min, max, fallback) => {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return fallback;
        return Math.max(min, Math.min(max, Math.round(Number(value))));
    };

    const float = (value, min, max, fallback) => {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return fallback;
        return Math.max(min, Math.min(max, Number(value)));
    };

    const rgb = (h) => {
        const n = parseInt(h.slice(1), 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    };

    function controlShape(uiShape, buttonShape) {
        const ui = String(uiShape || '');
        if (ui === '' || ui === 'circle') {
            return buttonShape === 'sharp' ? 'square' : (buttonShape === 'rounded' ? 'rounded' : 'pill');
        }
        if (ui === 'square') return 'square';
        if (['rounded', 'soft', 'square-rounded'].includes(ui)) return 'rounded';
        return 'pill';
    }

    /** Stesse chiavi di profile_style_resolve(); accetta i nomi di colonna. */
    function resolve(p) {
        const theme = THEMES.includes(p.profile_theme) ? p.profile_theme : 'dark';
        const isLight = theme === 'light';
        const accent = hex(p.accent_color) || '#0f5bff';

        let borderWidth = int(p.profile_border_width, 0, 5, 1);
        let borderOpacity = int(p.profile_border_opacity, 0, 100, 100);
        let borderColor = hex(p.profile_border_color);
        const borderStyle = String(p.profile_border_style || 'solid');
        if (borderStyle === 'none') borderWidth = 0;
        const borderColorAuto = borderColor === null;
        if (borderColorAuto) {
            borderColor = isLight ? '#111827' : '#ffffff';
            borderOpacity = Math.round(borderOpacity * 0.12);
        }

        const cardColor = hex(p.profile_card_color);

        return {
            theme,
            accent,
            secondary: hex(p.profile_secondary_color) || accent,
            card_color: cardColor || (isLight ? '#ffffff' : '#080c18'),
            card_color_auto: cardColor === null,
            text_color: hex(p.profile_text_color),
            card_opacity: int(p.profile_card_opacity, 0, 100, 68),
            card_blur: int(p.profile_card_blur, 0, 40, 20),
            surface_radius: int(p.profile_border_radius, 0, 40, 30),
            control_shape: controlShape(p.profile_ui_shape, p.profile_button_shape),
            avatar_shape: AVATAR_SHAPES.includes(p.profile_avatar_shape) ? p.profile_avatar_shape : 'circle',
            border_width: borderWidth,
            border_color: borderColor,
            border_color_auto: borderColorAuto,
            border_opacity: borderOpacity,
            border_glow: borderStyle === 'glow' || borderStyle === '1',
            link_style: LINK_STYLES.includes(p.profile_link_style) ? p.profile_link_style : 'glass',
            socials_style: p.profile_socials_style === 'icons' ? 'icons' : 'cards',
            social_size: int(p.profile_social_size, 32, 72, 42),
            social_spacing: int(p.profile_icon_spacing, 0, 24, 8),
            badge_size: int(p.profile_badge_size, 16, 60, 24),
            button_height: int(p.profile_button_size, 32, 80, 48),
            bg_overlay_opacity: float(p.profile_bg_overlay_opacity, 0, 1, 1),
            bg_blur: int(p.profile_bg_blur, 0, 40, 0),
            bg_orbs_opacity: float(p.profile_bg_orbs_opacity, 0, 1, 0.45),
        };
    }

    /** Stesse variabili di profile_style_css_vars(). */
    function cssVars(s) {
        const r = s.surface_radius;
        const item = Math.round(r * 0.7);
        let controlRadius = '999px';
        let iconRadius = '50%';
        if (s.control_shape === 'square') {
            controlRadius = '0px';
            iconRadius = '0px';
        } else if (s.control_shape === 'rounded') {
            controlRadius = Math.max(6, Math.min(16, Math.round(r * 0.45))) + 'px';
            iconRadius = Math.max(6, Math.min(14, Math.round(r * 0.4))) + 'px';
        }

        const [ar, ag, ab] = rgb(s.accent);
        const [br, bg, bb] = rgb(s.border_color);
        const alpha = Math.round(s.border_opacity / 100 * 1000) / 1000;
        const width = s.border_width;
        const cardBase = s.card_color_auto ? 'var(--p-card-base)' : s.card_color;
        const alphaPct = Math.round(alpha * 1000) / 10;

        let borderColor;
        let itemBorderColor;
        if (s.border_color_auto) {
            borderColor = `color-mix(in srgb, var(--p-border-base) ${alphaPct}%, transparent)`;
            itemBorderColor = `color-mix(in srgb, var(--p-border-base) ${Math.round(alphaPct * 7.5) / 10}%, transparent)`;
        } else {
            borderColor = `rgba(${br}, ${bg}, ${bb}, ${alpha})`;
            itemBorderColor = `rgba(${br}, ${bg}, ${bb}, ${Math.round(alpha * 750) / 1000})`;
        }

        const vars = {
            '--accent': s.accent,
            '--accent-rgb': `${ar}, ${ag}, ${ab}`,
            '--profile-accent': s.accent,
            '--accent-2': s.secondary,
            '--profile-card-opacity': String(Math.round(s.card_opacity * 10) / 1000),
            '--profile-card-blur': s.card_blur + 'px',
            '--card': `color-mix(in srgb, ${cardBase} ${s.card_opacity}%, transparent)`,
            '--profile-card-bg': `color-mix(in srgb, ${cardBase} ${s.card_opacity}%, transparent)`,
            '--card-strong': `color-mix(in srgb, ${cardBase} ${Math.min(100, s.card_opacity + 20)}%, transparent)`,
            '--p-radius-surface': r + 'px',
            '--p-radius-item': item + 'px',
            '--p-radius-control': controlRadius,
            '--p-radius-icon': iconRadius,
            '--p-border-width': width + 'px',
            '--p-border-color': borderColor,
            '--p-item-border-width': Math.min(width, 1) + 'px',
            '--p-item-border-color': itemBorderColor,
            '--p-border-glow': s.border_glow && width > 0
                ? `0 0 ${10 + width * 4}px rgba(${br}, ${bg}, ${bb}, 0.45)`
                : '0 0 0 transparent',
            '--radius-lg': r + 'px',
            '--radius-md': item + 'px',
            '--radius-sm': Math.round(r * 0.47) + 'px',
            '--ui-shape-button': controlRadius,
            '--ui-shape-icon': iconRadius,
            '--profile-corner-radius': controlRadius,
            '--profile-border-width': width + 'px',
            '--profile-border-opacity': String(alpha),
            '--profile-border-opacity-percent': s.border_opacity + '%',
            '--border': borderColor,
            '--social-icon-size': s.social_size + 'px',
            '--social-icon-spacing': s.social_spacing + 'px',
            '--badge-size': s.badge_size + 'px',
            '--button-height': s.button_height + 'px',
            '--profile-bg-overlay-opacity': String(s.bg_overlay_opacity),
            '--profile-bg-blur': s.bg_blur + 'px',
            '--profile-bg-scale': String(1 + s.bg_blur * 0.005),
            '--profile-bg-orbs-opacity': String(s.bg_orbs_opacity),
        };

        // Le variabili opzionali vanno anche tolte quando tornano "automatiche":
        // null qui significa removeProperty in apply().
        vars['--profile-card-color'] = s.card_color_auto ? null : s.card_color;
        vars['--profile-border-color'] = s.border_color_auto ? null : s.border_color;
        vars['--profile-text-color'] = s.text_color;

        return vars;
    }

    /** Stessa conversione di profile_name_style_normalize(). */
    function nameStyle(data, textColor, theme) {
        const d = data && typeof data === 'object' ? data : {};
        const fallback = hex(textColor) || (theme === 'light' ? '#111827' : '#ffffff');
        let color;
        let effect;

        if ('effect' in d || 'color' in d) {
            color = hex(d.color) || fallback;
            effect = NAME_EFFECTS.includes(d.effect) ? d.effect : 'none';
        } else {
            const type = String(d.type || 'default');
            const animation = NAME_EFFECTS.includes(d.animation) ? d.animation : 'none';
            color = type === 'solid' ? (hex(d.solid_color) || fallback) : fallback;
            effect = type === 'gradient' && animation === 'none' ? 'gradient' : animation;
        }

        return {
            color,
            effect,
            grad_color1: hex(d.grad_color1) || '#ffffff',
            grad_color2: hex(d.grad_color2) || '#8b5cf6',
            grad_angle: int(d.grad_angle, 0, 360, 90),
            glow_color: hex(d.glow_color) || '#8b5cf6',
        };
    }

    /** Applica le variabili e gli attributi di stile a un <body>. */
    function apply(body, settings) {
        const s = resolve(settings);
        Object.entries(cssVars(s)).forEach(([name, value]) => {
            if (value === null || value === undefined) {
                body.style.removeProperty(name);
            } else {
                body.style.setProperty(name, value, 'important');
            }
        });

        // In anteprima conta il tema scelto dal proprietario, non quello che il
        // visitatore puo' forzare dal menu del profilo.
        body.dataset.ownerTheme = s.theme;
        body.dataset.theme = s.theme === 'auto'
            ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
            : s.theme;
        body.dataset.controlShape = s.control_shape;
        body.dataset.avatarShape = s.avatar_shape;
        body.dataset.profileLinkStyle = s.link_style;
        body.dataset.profileSocialsStyle = s.socials_style;
        return s;
    }

    global.CripsumProfileStyle = {
        NAME_EFFECTS,
        NAME_EFFECTS_OWN_COLORS,
        resolve,
        cssVars,
        nameStyle,
        apply,
        controlShape,
    };
})(window);
