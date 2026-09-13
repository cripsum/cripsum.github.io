<?php
/**
 * Cataloghi dell'editor del profilo: le scelte possibili per ogni impostazione.
 *
 * Una sola lista per il template PHP e per il JS (arriva in pagina come JSON):
 * prima le stesse opzioni erano scritte a mano in quattro file, con valori
 * che non combaciavano (effetti inesistenti nei temi, livelli del tilt diversi
 * fra PHP e JS).
 *
 * `$tt($it, $en)` sceglie il testo nella lingua dell'editor.
 */

function profile_editor_catalog(callable $tt): array
{
    $opt = static fn(string $value, string $label, bool $premium = false, array $extra = []): array
        => ['value' => $value, 'label' => $label, 'premium' => $premium] + $extra;

    return [
        'fonts' => [
            $opt('Poppins', 'Poppins'),
            $opt('Inter', 'Inter'),
            $opt('Roboto', 'Roboto'),
            $opt('Outfit', 'Outfit'),
            $opt('Montserrat', 'Montserrat'),
            $opt('Playfair Display', 'Playfair Display', true),
            $opt('Space Grotesk', 'Space Grotesk', true),
            $opt('Syne', 'Syne', true),
            $opt('Fira Code', 'Fira Code', true),
            $opt('PT Mono', 'PT Mono', true),
            $opt('Cinzel', 'Cinzel', true),
            $opt('Rubik', 'Rubik', true),
            $opt('Bebas Neue', 'Bebas Neue', true),
            $opt('Minecraft', 'Minecraft', true),
            $opt('Gang of Three', 'Gang of Three', true),
            $opt('Press Start 2P', 'Press Start 2P', true),
            $opt('Bungee', 'Bungee', true),
            $opt('Permanent Marker', 'Permanent Marker', true),
            $opt('Creepster', 'Creepster', true),
            $opt('Shojumaru', 'Shojumaru', true),
        ],

        'page_effects' => [
            $opt('none', $tt('Nessuno', 'None')),
            $opt('cursor_glow', $tt('Bagliore del mouse', 'Mouse glow')),
            $opt('soft_particles', $tt('Particelle leggere', 'Soft particles')),
            $opt('stars', $tt('Stelle', 'Stars')),
            $opt('aurora', 'Aurora'),
            $opt('ambient', $tt('Luce ambientale', 'Ambient glow')),
            $opt('gradient_waves', $tt('Onde sfumate', 'Gradient waves')),
            $opt('scanlines', 'Scanlines'),
            $opt('cyber_grid', $tt('Griglia cyber', 'Cyber grid')),
            $opt('spotlight', $tt('Riflettore sul mouse', 'Mouse spotlight'), true),
            $opt('digital_noise', $tt('Rumore digitale', 'Digital noise'), true),
            $opt('glass_rain', $tt('Pioggia sul vetro', 'Glass rain'), true, ['hint' => $tt('Funziona solo con uno sfondo immagine.', 'Works with image backgrounds only.')]),
            $opt('sakura_falling', $tt('Petali di sakura', 'Sakura petals'), true),
            $opt('bg_grain', $tt('Grana sullo sfondo', 'Background grain'), true),
        ],

        'ring_styles' => [
            $opt('none', $tt('Nessuno', 'None')),
            $opt('spin', $tt('Rotazione', 'Spin')),
            $opt('pulse', 'Pulse'),
            $opt('orbit', 'Orbit'),
            $opt('glow', 'Glow'),
            $opt('dual', $tt('Doppio giro', 'Dual')),
            $opt('rainbow', $tt('Arcobaleno', 'Rainbow')),
            $opt('halo', 'Halo'),
            $opt('neon', 'Neon'),
            $opt('spark', 'Spark'),
            $opt('glitch', 'Glitch'),
        ],

        'name_effects' => [
            $opt('none', $tt('Nessuno', 'None')),
            $opt('gradient', $tt('Sfumatura', 'Gradient'), false, ['ownColors' => true]),
            $opt('glow', $tt('Bagliore', 'Glow')),
            $opt('neon', 'Neon'),
            $opt('rainbow', $tt('Arcobaleno', 'Rainbow'), false, ['ownColors' => true]),
            $opt('sparkles', $tt('Scintille', 'Sparkles')),
            $opt('fire', $tt('Fuoco', 'Fire'), false, ['ownColors' => true]),
            $opt('water', $tt('Acqua', 'Water'), false, ['ownColors' => true]),
            $opt('glitch', 'Glitch'),
            $opt('bounce', $tt('Lettere che saltano', 'Bouncing letters')),
        ],

        'cursor_effects' => [
            $opt('none', $tt('Nessuno', 'None')),
            $opt('follower', $tt('Cerchio che segue', 'Follower dot'), true),
            $opt('trail', $tt('Scia di particelle', 'Particle trail'), true),
            $opt('trail_stars', $tt('Scia di stelle', 'Star trail'), true),
            $opt('trail_hearts', $tt('Scia di cuori', 'Heart trail'), true),
            $opt('cat_follower', $tt('Gattino', 'Cat follower'), true),
        ],

        'music_themes' => [
            $opt('default', $tt('Classico', 'Classic')),
            $opt('retro', $tt('Compatto', 'Compact'), true),
            $opt('cyberpunk', $tt('Centrato', 'Centered'), true),
            $opt('synthwave', $tt('Vinile', 'Vinyl'), true),
        ],

        'layouts' => [
            $opt('standard', $tt('Profilo a sinistra', 'Profile on the left'), false, ['desc' => $tt('Il classico: profilo e contenuti affiancati.', 'The classic: profile next to your content.')]),
            $opt('showcase', $tt('Profilo a destra', 'Profile on the right'), false, ['desc' => $tt('I contenuti vengono prima.', 'Content comes first.')]),
            $opt('compact', $tt('Profilo al centro', 'Profile in the middle'), false, ['desc' => $tt('Contenuti ai due lati.', 'Content on both sides.')]),
            $opt('clean', $tt('Una colonna', 'Single column'), false, ['desc' => $tt('Tutto centrato, uno sotto l\'altro.', 'Everything centered, one below the other.')]),
            $opt('scrollsnap', $tt('A schermate', 'Full screens'), true, ['desc' => $tt('Ogni sezione occupa lo schermo.', 'Each section fills the screen.')]),
        ],

        'link_styles' => [
            $opt('glass', 'Glass'),
            $opt('solid', $tt('Pieno', 'Solid')),
            $opt('outline', $tt('Contorno', 'Outline')),
            $opt('neon', 'Neon'),
        ],

        'control_shapes' => [
            $opt('square', $tt('Squadrata', 'Square')),
            $opt('rounded', $tt('Arrotondata', 'Rounded')),
            $opt('pill', $tt('Pillola', 'Pill')),
        ],

        'avatar_shapes' => [
            $opt('circle', $tt('Cerchio', 'Circle')),
            $opt('squircle', 'Squircle'),
            $opt('square', $tt('Quadrato', 'Square')),
            $opt('hexagon', $tt('Esagono', 'Hexagon')),
            $opt('octagon', $tt('Ottagono', 'Octagon')),
            $opt('badge', $tt('Scudo', 'Shield')),
        ],

        'themes' => [
            ['value' => 'dark_premium', 'label' => 'Dark Premium', 'swatches' => ['#0f5bff', '#c9d9ff', '#030509'], 'settings' => [
                'accent_color' => '#0f5bff', 'profile_secondary_color' => '#c9d9ff', 'profile_card_color' => '#030509', 'profile_text_color' => '#ffffff',
                'profile_theme' => 'dark', 'profile_link_style' => 'glass', 'profile_ui_shape' => 'pill', 'profile_socials_style' => 'cards', 'profile_font' => 'Poppins',
                'profile_card_opacity' => 80, 'profile_card_blur' => 20, 'profile_border_radius' => 24, 'profile_border_width' => 1, 'profile_border_color' => '#0f5bff',
                'profile_border_opacity' => 40, 'profile_border_style' => 'solid', 'profile_avatar_shape' => 'circle', 'profile_effect' => 'none',
            ]],
            ['value' => 'cyberpunk', 'label' => 'Cyberpunk', 'swatches' => ['#ff007f', '#7f00ff', '#0a0512'], 'settings' => [
                'accent_color' => '#ff007f', 'profile_secondary_color' => '#7f00ff', 'profile_card_color' => '#0a0512', 'profile_text_color' => '#ffebf5',
                'profile_theme' => 'dark', 'profile_link_style' => 'neon', 'profile_ui_shape' => 'square', 'profile_socials_style' => 'cards', 'profile_font' => 'Space Grotesk',
                'profile_card_opacity' => 85, 'profile_card_blur' => 15, 'profile_border_radius' => 8, 'profile_border_width' => 2, 'profile_border_color' => '#ff007f',
                'profile_border_opacity' => 100, 'profile_border_style' => 'glow', 'profile_avatar_shape' => 'hexagon', 'profile_effect' => 'cyber_grid',
            ]],
            ['value' => 'glass', 'label' => 'Glassmorphism', 'swatches' => ['#ffffff', '#cbd5e1', '#64748b'], 'settings' => [
                'accent_color' => '#ffffff', 'profile_secondary_color' => '#cbd5e1', 'profile_card_color' => '#ffffff', 'profile_text_color' => '#ffffff',
                'profile_theme' => 'dark', 'profile_link_style' => 'glass', 'profile_ui_shape' => 'pill', 'profile_socials_style' => 'cards', 'profile_font' => 'Outfit',
                'profile_card_opacity' => 15, 'profile_card_blur' => 30, 'profile_border_radius' => 30, 'profile_border_width' => 1, 'profile_border_color' => '#ffffff',
                'profile_border_opacity' => 40, 'profile_border_style' => 'solid', 'profile_avatar_shape' => 'circle', 'profile_effect' => 'none',
            ]],
            ['value' => 'sakura', 'label' => 'Sakura', 'swatches' => ['#ff758c', '#ff7eb3', '#1f1015'], 'settings' => [
                'accent_color' => '#ff758c', 'profile_secondary_color' => '#ff7eb3', 'profile_card_color' => '#1f1015', 'profile_text_color' => '#fff0f5',
                'profile_theme' => 'dark', 'profile_link_style' => 'glass', 'profile_ui_shape' => 'pill', 'profile_socials_style' => 'cards', 'profile_font' => 'Poppins',
                'profile_card_opacity' => 70, 'profile_card_blur' => 20, 'profile_border_radius' => 26, 'profile_border_width' => 1, 'profile_border_color' => '#ff758c',
                'profile_border_opacity' => 60, 'profile_border_style' => 'solid', 'profile_avatar_shape' => 'circle', 'profile_effect' => 'sakura_falling',
            ]],
            ['value' => 'anime', 'label' => 'Anime', 'swatches' => ['#ff6b6b', '#feca57', '#1a0f0f'], 'settings' => [
                'accent_color' => '#ff6b6b', 'profile_secondary_color' => '#feca57', 'profile_card_color' => '#1a0f0f', 'profile_text_color' => '#fff5f5',
                'profile_theme' => 'dark', 'profile_link_style' => 'solid', 'profile_ui_shape' => 'rounded', 'profile_socials_style' => 'cards', 'profile_font' => 'Rubik',
                'profile_card_opacity' => 75, 'profile_card_blur' => 15, 'profile_border_radius' => 18, 'profile_border_width' => 2, 'profile_border_color' => '#ff6b6b',
                'profile_border_opacity' => 80, 'profile_border_style' => 'solid', 'profile_avatar_shape' => 'squircle', 'profile_effect' => 'soft_particles',
            ]],
            ['value' => 'neon', 'label' => 'Neon Glow', 'swatches' => ['#00f0ff', '#ff007f', '#03030d'], 'settings' => [
                'accent_color' => '#00f0ff', 'profile_secondary_color' => '#ff007f', 'profile_card_color' => '#03030d', 'profile_text_color' => '#f0f9ff',
                'profile_theme' => 'dark', 'profile_link_style' => 'neon', 'profile_ui_shape' => 'pill', 'profile_socials_style' => 'cards', 'profile_font' => 'Poppins',
                'profile_card_opacity' => 80, 'profile_card_blur' => 25, 'profile_border_radius' => 28, 'profile_border_width' => 1, 'profile_border_color' => '#00f0ff',
                'profile_border_opacity' => 100, 'profile_border_style' => 'glow', 'profile_avatar_shape' => 'circle', 'profile_effect' => 'ambient',
            ]],
            ['value' => 'rgb', 'label' => 'RGB Gamer', 'swatches' => ['#ff0000', '#00ff00', '#080808'], 'settings' => [
                'accent_color' => '#ff0000', 'profile_secondary_color' => '#00ff00', 'profile_card_color' => '#080808', 'profile_text_color' => '#f7f8ff',
                'profile_theme' => 'dark', 'profile_link_style' => 'outline', 'profile_ui_shape' => 'rounded', 'profile_socials_style' => 'cards', 'profile_font' => 'Minecraft',
                'profile_card_opacity' => 90, 'profile_card_blur' => 10, 'profile_border_radius' => 12, 'profile_border_width' => 2, 'profile_border_color' => '#00ff00',
                'profile_border_opacity' => 100, 'profile_border_style' => 'solid', 'profile_avatar_shape' => 'squircle', 'profile_effect' => 'scanlines',
            ]],
            ['value' => 'discord', 'label' => 'Discord', 'swatches' => ['#5865f2', '#57f287', '#2f3136'], 'settings' => [
                'accent_color' => '#5865f2', 'profile_secondary_color' => '#57f287', 'profile_card_color' => '#2f3136', 'profile_text_color' => '#ffffff',
                'profile_theme' => 'dark', 'profile_link_style' => 'solid', 'profile_ui_shape' => 'rounded', 'profile_socials_style' => 'cards', 'profile_font' => 'Inter',
                'profile_card_opacity' => 95, 'profile_card_blur' => 0, 'profile_border_radius' => 10, 'profile_border_width' => 0, 'profile_border_color' => '#5865f2',
                'profile_border_opacity' => 0, 'profile_border_style' => 'solid', 'profile_avatar_shape' => 'circle', 'profile_effect' => 'none',
            ]],
            ['value' => 'minimal', 'label' => 'Minimal', 'swatches' => ['#000000', '#888888', '#ffffff'], 'settings' => [
                'accent_color' => '#111111', 'profile_secondary_color' => '#888888', 'profile_card_color' => '#ffffff', 'profile_text_color' => '#111111',
                'profile_theme' => 'light', 'profile_link_style' => 'outline', 'profile_ui_shape' => 'square', 'profile_socials_style' => 'cards', 'profile_font' => 'Inter',
                'profile_card_opacity' => 90, 'profile_card_blur' => 0, 'profile_border_radius' => 0, 'profile_border_width' => 1, 'profile_border_color' => '#111111',
                'profile_border_opacity' => 100, 'profile_border_style' => 'solid', 'profile_avatar_shape' => 'square', 'profile_effect' => 'none',
            ]],
        ],

        'palettes' => [
            ['label' => 'Cripsum', 'accent_color' => '#0f5bff', 'profile_secondary_color' => '#8b5cf6'],
            ['label' => 'Cyberpunk', 'accent_color' => '#ff007f', 'profile_secondary_color' => '#7f00ff'],
            ['label' => 'Sunset', 'accent_color' => '#ff6b6b', 'profile_secondary_color' => '#feca57'],
            ['label' => 'Ocean', 'accent_color' => '#10b981', 'profile_secondary_color' => '#3b82f6'],
            ['label' => 'Mono', 'accent_color' => '#ffffff', 'profile_secondary_color' => '#888888'],
            ['label' => 'Blossom', 'accent_color' => '#ff758c', 'profile_secondary_color' => '#ff7eb3'],
            ['label' => 'Orchid', 'accent_color' => '#a855f7', 'profile_secondary_color' => '#ec4899'],
            ['label' => 'Lime', 'accent_color' => '#84cc16', 'profile_secondary_color' => '#22d3ee'],
        ],

        'platforms' => [
            ['value' => 'instagram', 'label' => 'Instagram', 'icon' => 'fa-brands fa-instagram'],
            ['value' => 'tiktok', 'label' => 'TikTok', 'icon' => 'fa-brands fa-tiktok'],
            ['value' => 'youtube', 'label' => 'YouTube', 'icon' => 'fa-brands fa-youtube'],
            ['value' => 'twitch', 'label' => 'Twitch', 'icon' => 'fa-brands fa-twitch'],
            ['value' => 'x', 'label' => 'X', 'icon' => 'fa-brands fa-x-twitter'],
            ['value' => 'discord', 'label' => 'Discord', 'icon' => 'fa-brands fa-discord'],
            ['value' => 'github', 'label' => 'GitHub', 'icon' => 'fa-brands fa-github'],
            ['value' => 'spotify', 'label' => 'Spotify', 'icon' => 'fa-brands fa-spotify'],
            ['value' => 'soundcloud', 'label' => 'SoundCloud', 'icon' => 'fa-brands fa-soundcloud'],
            ['value' => 'steam', 'label' => 'Steam', 'icon' => 'fa-brands fa-steam'],
            ['value' => 'reddit', 'label' => 'Reddit', 'icon' => 'fa-brands fa-reddit-alien'],
            ['value' => 'telegram', 'label' => 'Telegram', 'icon' => 'fa-brands fa-telegram'],
            ['value' => 'threads', 'label' => 'Threads', 'icon' => 'fa-brands fa-threads'],
            ['value' => 'bluesky', 'label' => 'Bluesky', 'icon' => 'fa-solid fa-cloud'],
            ['value' => 'kick', 'label' => 'Kick', 'icon' => 'fa-solid fa-k'],
            ['value' => 'snapchat', 'label' => 'Snapchat', 'icon' => 'fa-brands fa-snapchat'],
            ['value' => 'facebook', 'label' => 'Facebook', 'icon' => 'fa-brands fa-facebook'],
            ['value' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin'],
            ['value' => 'pinterest', 'label' => 'Pinterest', 'icon' => 'fa-brands fa-pinterest'],
            ['value' => 'patreon', 'label' => 'Patreon', 'icon' => 'fa-brands fa-patreon'],
            ['value' => 'paypal', 'label' => 'PayPal', 'icon' => 'fa-brands fa-paypal'],
            ['value' => 'behance', 'label' => 'Behance', 'icon' => 'fa-brands fa-behance'],
            ['value' => 'dribbble', 'label' => 'Dribbble', 'icon' => 'fa-brands fa-dribbble'],
            ['value' => 'website', 'label' => $tt('Sito web', 'Website'), 'icon' => 'fa-solid fa-globe'],
            ['value' => 'email', 'label' => 'Email', 'icon' => 'fa-solid fa-envelope'],
            ['value' => 'other', 'label' => $tt('Altro', 'Other'), 'icon' => 'fa-solid fa-link'],
        ],

        'project_statuses' => [
            $opt('active', $tt('Attivo', 'Active')),
            $opt('paused', $tt('In pausa', 'On hold')),
            $opt('finished', $tt('Finito', 'Finished')),
            $opt('idea', 'Idea'),
        ],

        'content_types' => [
            $opt('edit', 'Edit'),
            $opt('video', 'Video'),
            $opt('game', $tt('Gioco', 'Game')),
            $opt('post', 'Post'),
            $opt('other', $tt('Altro', 'Other')),
        ],

        'embed_types' => [
            $opt('spotify', 'Spotify'),
            $opt('youtube', 'YouTube'),
            $opt('custom', $tt('Altro sito', 'Other site')),
        ],

        'link_button_styles' => [
            $opt('card', 'Card'),
            $opt('compact', $tt('Compatto', 'Compact')),
            $opt('icon', $tt('Solo icona', 'Icon only')),
        ],

        'tilt_presets' => profile_tilt_presets(),

        // Quanti elementi per sezione. Gli stessi numeri stanno in
        // api/update_profile.php, che li applica davvero.
        'limits' => [
            'free' => ['socials' => 5, 'links' => 5, 'projects' => 3, 'contents' => 3, 'blocks' => 1, 'embeds' => 3, 'tags' => 10, 'characters' => 12, 'badges' => 8],
            'premium' => ['socials' => 100, 'links' => 100, 'projects' => 100, 'contents' => 100, 'blocks' => 100, 'embeds' => 100, 'tags' => 10, 'characters' => 12, 'badges' => 1000],
        ],

        // Ordine e icone delle sezioni del profilo.
        'sections' => [
            'links' => ['icon' => 'fa-solid fa-link', 'label' => 'Link', 'toggle' => 'profile_show_links'],
            'embeds' => ['icon' => 'fa-solid fa-share-from-square', 'label' => 'Embed', 'toggle' => 'profile_show_embeds'],
            'stats' => ['icon' => 'fa-solid fa-chart-simple', 'label' => $tt('Statistiche', 'Stats'), 'toggle' => 'profile_show_stats'],
            'projects' => ['icon' => 'fa-solid fa-cubes', 'label' => $tt('Progetti', 'Projects'), 'toggle' => 'profile_show_projects'],
            'blocks' => ['icon' => 'fa-solid fa-shapes', 'label' => $tt('Blocchi liberi', 'Custom blocks'), 'toggle' => 'profile_show_blocks'],
            'contents' => ['icon' => 'fa-solid fa-circle-play', 'label' => $tt('Contenuti', 'Content'), 'toggle' => 'profile_show_contents'],
            'characters' => ['icon' => 'fa-solid fa-user-astronaut', 'label' => $tt('Personaggi', 'Characters'), 'toggle' => 'profile_show_characters'],
            'badges' => ['icon' => 'fa-solid fa-trophy', 'label' => 'Badge', 'toggle' => 'profile_show_badges'],
            'activity' => ['icon' => 'fa-solid fa-clock-rotate-left', 'label' => $tt('Attività', 'Activity'), 'toggle' => 'profile_show_activity'],
        ],
    ];
}
