<?php

/**
 * Team esports (OHPY, Counter-Strike 2): lettura dal database e dati pronti
 * per la pagina /it/ohpy e per le pagine dei player.
 *
 * Riusa i pezzi comuni dello shop (escape, testi che ricadono
 * sull'italiano, percorsi delle immagini, colori del tema): stesse regole
 * e stesse garanzie, niente HTML dal database.
 */

require_once __DIR__ . '/../shop/shop_common.php';

const ESPORTS_TEAM_SLUG = 'ohpy';

const ESPORTS_STATES = ['titolare', 'riserva', 'staff', 'ex', 'nascosto'];

/** Formati audio accettati per la musica dei player. */
const ESPORTS_AUDIO_EXTENSIONS = ['mp3', 'ogg', 'm4a', 'aac', 'wav', 'webm'];

/** Clip in game: formati che il player del browser sa leggere (come gli edit). */
const ESPORTS_VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];
const ESPORTS_MAX_CLIPS = 2;

/** Social mostrati nella testata del team e nelle pagine dei player, in quest'ordine. */
const ESPORTS_TEAM_SOCIALS = ['discord', 'twitch', 'instagram', 'tiktok', 'youtube', 'x', 'faceit'];
const ESPORTS_PLAYER_SOCIALS = ['steam', 'faceit', 'leetify', 'twitch', 'instagram', 'tiktok', 'youtube', 'x'];

/** Codice mirino di CS2: CSGO-xxxxx-xxxxx-xxxxx-xxxxx-xxxxx. */
const ESPORTS_CROSSHAIR_PATTERN = '/^CSGO(-[A-Za-z0-9]{5}){5}$/';

function esports_ready(?mysqli $mysqli): bool
{
    return shop_tables_ready($mysqli, ['esports_team', 'esports_giocatori']);
}

function esports_roles(): array
{
    return [
        'igl'     => ['icon' => 'fa-solid fa-chess-king',      'it' => 'IGL',             'en' => 'IGL'],
        'awper'   => ['icon' => 'fa-solid fa-crosshairs',      'it' => 'AWPer',           'en' => 'AWPer'],
        'entry'   => ['icon' => 'fa-solid fa-bolt',            'it' => 'Entry fragger',   'en' => 'Entry fragger'],
        'rifler'  => ['icon' => 'fa-solid fa-gun',             'it' => 'Rifler',          'en' => 'Rifler'],
        'support' => ['icon' => 'fa-solid fa-shield-halved',   'it' => 'Support',         'en' => 'Support'],
        'lurker'  => ['icon' => 'fa-solid fa-user-ninja',      'it' => 'Lurker',          'en' => 'Lurker'],
        'coach'   => ['icon' => 'fa-solid fa-chalkboard-user', 'it' => 'Coach',           'en' => 'Coach'],
        'analyst' => ['icon' => 'fa-solid fa-chart-line',      'it' => 'Analista',        'en' => 'Analyst'],
        'manager' => ['icon' => 'fa-solid fa-briefcase',       'it' => 'Manager',         'en' => 'Manager'],
        'creator' => ['icon' => 'fa-solid fa-video',           'it' => 'Content creator', 'en' => 'Content creator'],
    ];
}

function esports_social_networks(): array
{
    return [
        'discord'   => ['label' => 'Discord',   'icon' => 'fa-brands fa-discord'],
        'steam'     => ['label' => 'Steam',     'icon' => 'fa-brands fa-steam'],
        'faceit'    => ['label' => 'FACEIT',    'icon' => 'fa-solid fa-f'],
        'leetify'   => ['label' => 'Leetify',   'icon' => 'fa-solid fa-chart-simple'],
        'twitch'    => ['label' => 'Twitch',    'icon' => 'fa-brands fa-twitch'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram'],
        'tiktok'    => ['label' => 'TikTok',    'icon' => 'fa-brands fa-tiktok'],
        'youtube'   => ['label' => 'YouTube',   'icon' => 'fa-brands fa-youtube'],
        'x'         => ['label' => 'X',         'icon' => 'fa-brands fa-x-twitter'],
    ];
}

/**
 * Paesi proposti nel pannello. Il server accetta qualunque codice ISO di
 * due lettere: questa lista serve solo alla tendina e al nome sul tooltip.
 */
function esports_countries(): array
{
    return [
        'IT' => ['Italia', 'Italy'], 'SM' => ['San Marino', 'San Marino'], 'CH' => ['Svizzera', 'Switzerland'],
        'FR' => ['Francia', 'France'], 'DE' => ['Germania', 'Germany'], 'ES' => ['Spagna', 'Spain'],
        'PT' => ['Portogallo', 'Portugal'], 'GB' => ['Regno Unito', 'United Kingdom'], 'IE' => ['Irlanda', 'Ireland'],
        'NL' => ['Paesi Bassi', 'Netherlands'], 'BE' => ['Belgio', 'Belgium'], 'AT' => ['Austria', 'Austria'],
        'PL' => ['Polonia', 'Poland'], 'CZ' => ['Repubblica Ceca', 'Czechia'], 'SK' => ['Slovacchia', 'Slovakia'],
        'HU' => ['Ungheria', 'Hungary'], 'RO' => ['Romania', 'Romania'], 'BG' => ['Bulgaria', 'Bulgaria'],
        'GR' => ['Grecia', 'Greece'], 'HR' => ['Croazia', 'Croatia'], 'SI' => ['Slovenia', 'Slovenia'],
        'RS' => ['Serbia', 'Serbia'], 'BA' => ['Bosnia ed Erzegovina', 'Bosnia and Herzegovina'], 'AL' => ['Albania', 'Albania'],
        'MK' => ['Macedonia del Nord', 'North Macedonia'], 'MD' => ['Moldavia', 'Moldova'], 'UA' => ['Ucraina', 'Ukraine'],
        'RU' => ['Russia', 'Russia'], 'LT' => ['Lituania', 'Lithuania'], 'LV' => ['Lettonia', 'Latvia'],
        'EE' => ['Estonia', 'Estonia'], 'FI' => ['Finlandia', 'Finland'], 'SE' => ['Svezia', 'Sweden'],
        'NO' => ['Norvegia', 'Norway'], 'DK' => ['Danimarca', 'Denmark'], 'TR' => ['Turchia', 'Türkiye'],
        'MA' => ['Marocco', 'Morocco'], 'TN' => ['Tunisia', 'Tunisia'], 'EG' => ['Egitto', 'Egypt'],
        'NG' => ['Nigeria', 'Nigeria'], 'SN' => ['Senegal', 'Senegal'], 'IN' => ['India', 'India'],
        'PK' => ['Pakistan', 'Pakistan'], 'BD' => ['Bangladesh', 'Bangladesh'], 'CN' => ['Cina', 'China'],
        'JP' => ['Giappone', 'Japan'], 'KR' => ['Corea del Sud', 'South Korea'], 'PH' => ['Filippine', 'Philippines'],
        'US' => ['Stati Uniti', 'United States'], 'CA' => ['Canada', 'Canada'], 'BR' => ['Brasile', 'Brazil'],
        'AR' => ['Argentina', 'Argentina'], 'MX' => ['Messico', 'Mexico'], 'PE' => ['Perù', 'Peru'],
        'CO' => ['Colombia', 'Colombia'], 'AU' => ['Australia', 'Australia'],
    ];
}

function esports_country_name(string $code, string $lang): string
{
    $countries = esports_countries();
    return isset($countries[$code]) ? $countries[$code][$lang === 'en' ? 1 : 0] : $code;
}

/**
 * Premier rating con il colore della sua fascia, come nel gioco: le
 * migliaia grandi e le ultime tre cifre piccole ("18,452"). CS2 usa la
 * virgola in ogni lingua, e qui si fa lo stesso.
 */
function esports_premier(?int $rating): ?array
{
    if ($rating === null || $rating <= 0) {
        return null;
    }

    $tiers = [
        [30000, '#e4ae39'],
        [25000, '#eb4b4b'],
        [20000, '#d32ce6'],
        [15000, '#8847ff'],
        [10000, '#4b69ff'],
        [5000, '#5e98d9'],
        [0, '#b0c3d9'],
    ];

    $color = '#b0c3d9';
    foreach ($tiers as [$min, $tierColor]) {
        if ($rating >= $min) {
            $color = $tierColor;
            break;
        }
    }

    return [
        'value' => $rating,
        'color' => $color,
        'big' => $rating >= 1000 ? (string)intdiv($rating, 1000) : (string)$rating,
        'small' => $rating >= 1000 ? ',' . str_pad((string)($rating % 1000), 3, '0', STR_PAD_LEFT) : '',
    ];
}

/**
 * Livello FACEIT con il suo colore. Se nel pannello c'e' solo l'ELO, il
 * livello si ricava dalle soglie di FACEIT.
 */
function esports_faceit(?int $level, ?int $elo, string $lang): ?array
{
    $level = $level !== null && $level >= 1 && $level <= 10 ? $level : null;
    $elo = $elo !== null && $elo > 0 ? $elo : null;

    if ($level === null && $elo === null) {
        return null;
    }

    if ($level === null) {
        $level = 1;
        foreach ([2001 => 10, 1751 => 9, 1531 => 8, 1351 => 7, 1201 => 6, 1051 => 5, 901 => 4, 751 => 3, 501 => 2] as $min => $lvl) {
            if ($elo >= $min) {
                $level = $lvl;
                break;
            }
        }
    }

    $color = match (true) {
        $level >= 10 => '#fe1f00',
        $level >= 8 => '#ff6309',
        $level >= 4 => '#ffc800',
        $level >= 2 => '#1ce400',
        default => '#eeeeee',
    };

    return [
        'level' => $level,
        'color' => $color,
        'elo' => $elo,
        'elo_label' => $elo !== null ? number_format($elo, 0, '', $lang === 'en' ? ',' : '.') : '',
    ];
}

function esports_json(?string $json): array
{
    $decoded = json_decode((string)$json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Link dei social: solo indirizzi https, nell'ordine della lista.
 */
function esports_socials(?string $json, array $keys): array
{
    $data = esports_json($json);
    $networks = esports_social_networks();
    $links = [];

    foreach ($keys as $key) {
        $url = trim((string)($data[$key] ?? ''));
        if ($url === '' || !isset($networks[$key]) || !preg_match('~^https://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }
        $links[] = ['key' => $key, 'url' => $url] + $networks[$key];
    }

    return $links;
}

/**
 * Statistiche: coppie etichetta/valore in una lingua sola (K/D, ADR, HS%
 * si scrivono uguali ovunque). Un valore in percentuale prende la barra.
 */
function esports_stats(?string $json): array
{
    $stats = [];

    foreach (esports_json($json) as $pair) {
        $pair = is_array($pair) ? array_values($pair) : [];
        if (count($pair) < 2 || !is_scalar($pair[0]) || !is_scalar($pair[1])) {
            continue;
        }

        $label = trim((string)$pair[0]);
        $value = trim((string)$pair[1]);
        if ($label === '' || $value === '') {
            continue;
        }

        $percent = null;
        if (preg_match('/^(\d{1,3}(?:[.,]\d+)?)\s*%$/', $value, $m)) {
            $number = (float)str_replace(',', '.', $m[1]);
            if ($number >= 0 && $number <= 100) {
                $percent = $number;
            }
        }

        $stats[] = ['label' => $label, 'value' => $value, 'percent' => $percent];
    }

    return $stats;
}

/**
 * Curiosita': {"it": [["Mappa che odia", "Vertigo"], "punto elenco"], "en": [...]}.
 * Le coppie diventano schede, le frasi un elenco. Una lingua vuota ricade
 * sull'italiano.
 */
function esports_facts(?string $json, string $lang): array
{
    $data = esports_json($json);
    $list = $data[$lang] ?? [];
    if (!is_array($list) || !$list) {
        $list = $data['it'] ?? [];
    }

    $facts = [];
    foreach (is_array($list) ? $list : [] as $item) {
        if (is_string($item)) {
            if (trim($item) !== '') {
                $facts[] = ['label' => null, 'value' => trim($item)];
            }
            continue;
        }

        $item = is_array($item) ? array_values($item) : [];
        if (count($item) >= 2 && is_scalar($item[0]) && is_scalar($item[1]) && trim((string)$item[0]) !== '' && trim((string)$item[1]) !== '') {
            $facts[] = ['label' => trim((string)$item[0]), 'value' => trim((string)$item[1])];
        }
    }

    return $facts;
}

/**
 * Data del palmares scritta come la scrive chi legge: "12 mag 2026",
 * "mag 2026" o solo l'anno, secondo quanto e' stato inserito.
 */
function esports_format_date(string $value, string $lang): string
{
    $months = $lang === 'en'
        ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        : ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return $lang === 'en'
            ? $months[(int)$m[2] - 1] . ' ' . (int)$m[3] . ', ' . $m[1]
            : (int)$m[3] . ' ' . $months[(int)$m[2] - 1] . ' ' . $m[1];
    }

    if (preg_match('/^(\d{4})-(\d{2})$/', $value, $m) && (int)$m[2] >= 1 && (int)$m[2] <= 12) {
        return $months[(int)$m[2] - 1] . ' ' . $m[1];
    }

    return $value;
}

function esports_palmares(?string $json, string $lang): array
{
    $entries = [];

    foreach (esports_json($json) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $tournament = trim((string)($item['torneo'] ?? ''));
        if ($tournament === '') {
            continue;
        }

        $placement = trim((string)($item['piazzamento'] ?? ''));
        $date = trim((string)($item['data'] ?? ''));
        $link = trim((string)($item['link'] ?? ''));

        // Oro, argento e bronzo per chi arriva sul podio: basta che il
        // piazzamento cominci con 1, 2 o 3 ("1°", "2nd", "3 posto").
        $medal = null;
        if (preg_match('/^\s*([123])(?!\d)/', $placement, $m)) {
            $medal = ['1' => 'gold', '2' => 'silver', '3' => 'bronze'][$m[1]];
        }

        $entries[] = [
            'tournament' => $tournament,
            'placement' => $placement,
            'medal' => $medal,
            'date' => $date !== '' ? esports_format_date($date, $lang) : '',
            'date_iso' => preg_match('/^\d{4}(-\d{2}){0,2}$/', $date) ? $date : '',
            'link' => $link !== '' && preg_match('~^https://~i', $link) && filter_var($link, FILTER_VALIDATE_URL) ? $link : '',
        ];
    }

    return $entries;
}

/**
 * Indirizzo del brano pronto per <audio>: un file dentro /audio/ (con i
 * pezzi del percorso codificati, perche' i nomi storici hanno spazi e
 * virgole) oppure un link https. Qualunque altra cosa diventa ''.
 */
function esports_audio_url(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (preg_match('~^https://~i', $value)) {
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    $path = rawurldecode($value);
    if (!str_starts_with($path, '/audio/') || str_contains($path, '..') || str_contains($path, "\0")) {
        return '';
    }

    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/**
 * Indirizzo di una clip pronto per <video>: solo file caricati dentro /vid/,
 * con i pezzi del percorso codificati come per l'audio.
 */
function esports_video_url(?string $value): string
{
    $path = rawurldecode(trim((string)$value));
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (!str_starts_with($path, '/vid/') || str_contains($path, '..') || str_contains($path, "\0")
        || !in_array($ext, ESPORTS_VIDEO_EXTENSIONS, true)) {
        return '';
    }

    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/**
 * Le clip in game di un player, al massimo due, nell'ordine del pannello:
 *   [{"video", "copertina", "titolo", "titolo_en", "larghezza", "altezza"}]
 * Le proporzioni arrivano dal video (le legge il pannello al caricamento):
 * la pagina riserva lo spazio giusto prima che il video si carichi.
 */
function esports_clips(?string $json, string $lang): array
{
    $clips = [];

    foreach (esports_json($json) as $item) {
        if (!is_array($item) || count($clips) >= ESPORTS_MAX_CLIPS) {
            continue;
        }

        $src = esports_video_url($item['video'] ?? '');
        if ($src === '') {
            continue;
        }

        $width = (int)($item['larghezza'] ?? 0);
        $height = (int)($item['altezza'] ?? 0);
        $ratio = $width > 0 && $height > 0 ? $width / $height : 16 / 9;

        $clips[] = [
            'src' => $src,
            'poster' => shop_asset_url($item['copertina'] ?? ''),
            'title' => shop_pick($item, 'titolo', $lang),
            'ratio' => round($ratio, 4),
            'shape' => $ratio > 1.08 ? 'landscape' : ($ratio < 0.93 ? 'portrait' : 'square'),
        ];
    }

    return $clips;
}

/**
 * Le variabili CSS del colore principale. Il testo sopra l'accento
 * diventa scuro quando l'accento e' chiaro, come nelle vetrine dello shop.
 */
function esports_accent_vars(string $accent): array
{
    return [
        '--es-accent' => $accent,
        '--es-accent-rgb' => shop_hex_rgb($accent),
        '--es-on-accent' => shop_hex_luminance($accent) > 0.62 ? '#161000' : '#ffffff',
    ];
}

function esports_style(array $vars): string
{
    $css = '';
    foreach ($vars as $name => $value) {
        $css .= $name . ': ' . $value . '; ';
    }

    return trim($css);
}

function esports_team_row(mysqli $mysqli, string $slug = ESPORTS_TEAM_SLUG): ?array
{
    return shop_fetch_one($mysqli, 'SELECT * FROM esports_team WHERE slug = ? LIMIT 1', 's', [$slug]);
}

/**
 * Tutti i player del team, anche i nascosti: chi decide cosa mostrare e'
 * la pagina, che sa se chi guarda e' dello staff.
 */
function esports_player_rows(mysqli $mysqli, int $teamId): array
{
    $rows = shop_fetch_all(
        $mysqli,
        'SELECT g.*, u.username AS utente_username
         FROM esports_giocatori g
         LEFT JOIN utenti u ON u.id = g.utente_id
         WHERE g.team_id = ?
         ORDER BY g.posizione ASC, g.id ASC',
        'i',
        [$teamId]
    );

    // Senza la tabella utenti (un database di prova) la pagina non deve
    // restare vuota: si riprova senza il profilo collegato.
    return $rows ?: shop_fetch_all(
        $mysqli,
        'SELECT * FROM esports_giocatori WHERE team_id = ? ORDER BY posizione ASC, id ASC',
        'i',
        [$teamId]
    );
}

/**
 * Il nome del team come lo si legge fuori dalla testata (tasto indietro,
 * titolo della scheda del browser, Rich Presence): "OHPY" diventa
 * "Team OHPY", un nome che comincia gia' con "Team" resta com'e'.
 */
function esports_team_label(string $name): string
{
    return preg_match('/^team\b/iu', $name) ? $name : 'Team ' . $name;
}

function esports_team_view(array $row, string $lang): array
{
    $accent = shop_hex($row['colore_accento'] ?? null, '#f5a524');
    $link = trim((string)($row['link_url'] ?? ''));
    $vars = esports_accent_vars($accent) + [
        '--es-bg' => shop_hex($row['colore_sfondo'] ?? null, '#07080c'),
        '--es-bg-2' => shop_hex($row['colore_sfondo_2'] ?? null, '#1c1307'),
    ];

    return [
        'id' => (int)$row['id'],
        'slug' => (string)$row['slug'],
        'name' => trim((string)$row['nome']),
        'label' => esports_team_label(trim((string)$row['nome'])),
        'game' => trim((string)($row['gioco'] ?? '')) ?: 'Counter-Strike 2',
        'tagline' => shop_pick($row, 'frase', $lang),
        'description' => shop_pick($row, 'descrizione', $lang),
        'logo' => shop_asset_url($row['logo'] ?? ''),
        'cover' => shop_asset_url($row['copertina'] ?? ''),
        'accent' => $accent,
        'vars' => $vars,
        'style' => esports_style($vars),
        'socials' => esports_socials($row['social'] ?? null, ESPORTS_TEAM_SOCIALS),
        'link_text' => shop_pick($row, 'link_testo', $lang),
        'link_url' => $link !== '' && shop_valid_link($link) ? shop_localize_link($link, $lang) : '',
        'palmares' => esports_palmares($row['palmares'] ?? null, $lang),
        'show_ex' => (int)($row['mostra_ex'] ?? 1) === 1,
        'url' => '/' . $lang . '/ohpy',
    ];
}

function esports_music_view(array $row): ?array
{
    $src = esports_audio_url($row['musica_audio'] ?? '');
    if ($src === '') {
        return null;
    }

    return [
        'src' => $src,
        'title' => trim((string)($row['musica_titolo'] ?? '')),
        'artist' => trim((string)($row['musica_artista'] ?? '')),
        'cover' => shop_asset_url($row['musica_cover'] ?? ''),
        'start' => max(0, (int)($row['musica_inizio'] ?? 0)),
        'volume' => max(0, min(100, (int)($row['musica_volume'] ?? 60))),
    ];
}

/**
 * Il brano di un player come JSON per l'attributo data-es-music dei link
 * che portano alla sua pagina: esports.js lo fa partire nello stesso clic,
 * prima ancora di aver caricato la pagina.
 */
function esports_music_data(?array $music, string $nickname): string
{
    if (!$music) {
        return '';
    }

    return (string)json_encode([
        'src' => $music['src'],
        'title' => $music['title'] !== '' ? $music['title'] : $nickname,
        'artist' => $music['artist'],
        'cover' => $music['cover'],
        'start' => $music['start'],
        'volume' => $music['volume'],
        'nickname' => $nickname,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function esports_player_view(array $row, string $lang): array
{
    $roles = esports_roles();
    $roleKey = isset($roles[$row['ruolo'] ?? '']) ? (string)$row['ruolo'] : 'rifler';
    $country = strtoupper(trim((string)($row['nazionalita'] ?? '')));
    $country = preg_match('/^[A-Z]{2}$/', $country) ? $country : '';
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($row['colore_accento'] ?? '')) ? strtolower($row['colore_accento']) : null;

    $userId = (int)($row['utente_id'] ?? 0);
    $username = trim((string)($row['utente_username'] ?? ''));

    // Senza foto caricata si usa l'avatar del profilo collegato, se c'e'.
    $photo = shop_asset_url($row['foto'] ?? '');
    if ($photo === '' && $userId > 0 && $username !== '') {
        $photo = '/includes/get_pfp.php?id=' . $userId;
    }

    $facts = esports_facts($row['curiosita'] ?? null, $lang);
    $crosshair = trim((string)($row['crosshair'] ?? ''));
    $music = esports_music_view($row);
    $slug = (string)$row['slug'];

    return [
        'id' => (int)$row['id'],
        'slug' => $slug,
        'nickname' => trim((string)$row['nickname']),
        'real_name' => trim((string)($row['nome_reale'] ?? '')),
        'role_key' => $roleKey,
        'role_icon' => $roles[$roleKey]['icon'],
        'role_label' => shop_pick($row, 'ruolo_label', $lang) ?: $roles[$roleKey][$lang === 'en' ? 'en' : 'it'],
        'state' => in_array($row['stato'] ?? '', ESPORTS_STATES, true) ? $row['stato'] : 'nascosto',
        'country' => $country,
        'country_name' => $country !== '' ? esports_country_name($country, $lang) : '',
        // Classe di flag-icons (fi fi-it): le emoji delle bandiere su
        // Windows non si vedono, le SVG si' e pesano pochi byte l'una.
        'flag' => strtolower($country),
        'photo' => $photo,
        'background' => shop_asset_url($row['sfondo'] ?? ''),
        'accent_vars' => $accent ? esports_accent_vars($accent) : [],
        'style' => $accent ? esports_style(esports_accent_vars($accent)) : '',
        'tagline' => shop_pick($row, 'frase', $lang),
        'bio' => shop_pick($row, 'bio', $lang),
        'premier' => esports_premier(isset($row['premier_rating']) ? (int)$row['premier_rating'] : null),
        'faceit' => esports_faceit(
            isset($row['faceit_livello']) ? (int)$row['faceit_livello'] : null,
            isset($row['faceit_elo']) ? (int)$row['faceit_elo'] : null,
            $lang
        ),
        'stats' => esports_stats($row['statistiche'] ?? null),
        'setup' => shop_localized_pairs($row['setup'] ?? null, $lang),
        'crosshair' => preg_match(ESPORTS_CROSSHAIR_PATTERN, $crosshair) ? $crosshair : '',
        'fact_cards' => array_values(array_filter($facts, static fn(array $f): bool => $f['label'] !== null)),
        'fact_list' => array_values(array_filter($facts, static fn(array $f): bool => $f['label'] === null)),
        'socials' => esports_socials($row['social'] ?? null, ESPORTS_PLAYER_SOCIALS),
        'profile_url' => $username !== '' ? '/u/' . rawurlencode($username) : '',
        'username' => $username,
        'music' => $music,
        'music_data' => esports_music_data($music, trim((string)$row['nickname'])),
        'clips' => esports_clips($row['clips'] ?? null, $lang),
        'url' => '/' . $lang . '/ohpy/' . rawurlencode($slug),
    ];
}

/**
 * I player divisi come li mostra la pagina: line-up, panchina (riserve e
 * staff), ex e, solo per lo staff, i nascosti.
 */
function esports_group_players(array $players, bool $showEx, bool $isStaff): array
{
    $groups = ['lineup' => [], 'bench' => [], 'former' => [], 'hidden' => []];

    foreach ($players as $player) {
        switch ($player['state']) {
            case 'titolare':
                $groups['lineup'][] = $player;
                break;
            case 'riserva':
            case 'staff':
                $groups['bench'][] = $player;
                break;
            case 'ex':
                if ($showEx) {
                    $groups['former'][] = $player;
                }
                break;
            default:
                if ($isStaff) {
                    $groups['hidden'][] = $player;
                }
        }
    }

    return $groups;
}
