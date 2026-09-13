<?php

/**
 * Cripsum™ — Statistiche del profilo
 *
 * Il box "Statistiche" del profilo mostrava sempre le stesse quattro cifre.
 * Qui c'e' il catalogo di tutto quello che si puo' mostrare, preso dagli
 * stessi dati che usa il Rewind, e il calcolo dei valori.
 *
 * DOVE STA LA SCELTA
 *
 * Le chiavi scelte vivono in `profile_sections_config`, sotto
 * `stats.items`: e' la configurazione della sezione statistiche, e cosi'
 * non serve una colonna nuova. Senza `items` il profilo mostra le quattro
 * statistiche di sempre, nascondendo quelle a zero come faceva prima.
 *
 * COSTO
 *
 * Si calcola solo quello che il profilo mostra: una manciata di COUNT per
 * utente, ognuno nel suo try/catch. Una tabella che manca (il tracciamento
 * del Rewind arriva con una migration) spegne quella statistica e basta.
 */

require_once __DIR__ . '/security_helpers.php';

const PROFILE_STATS_DEFAULT = ['views', 'achievements', 'characters', 'pulls'];
const PROFILE_STATS_LIMIT_FREE = 4;
const PROFILE_STATS_LIMIT_PREMIUM = 8;

/**
 * Tutte le statistiche disponibili, nell'ordine in cui l'editor le propone.
 *
 * @return array<string,array{group:string,icon:string,it:string,en:string,format:string}>
 */
function profile_stats_catalog(): array
{
    return [
        // Profilo e presenza
        'views'            => ['group' => 'presence', 'icon' => 'fa-solid fa-eye', 'it' => 'Visite al profilo', 'en' => 'Profile views', 'format' => 'number'],
        'member_days'      => ['group' => 'presence', 'icon' => 'fa-solid fa-cake-candles', 'it' => 'Giorni su Cripsum', 'en' => 'Days on Cripsum', 'format' => 'number'],
        'time_on_site'     => ['group' => 'presence', 'icon' => 'fa-solid fa-clock', 'it' => 'Ore sul sito', 'en' => 'Hours on site', 'format' => 'duration'],
        'days_active'      => ['group' => 'presence', 'icon' => 'fa-solid fa-calendar-check', 'it' => 'Giorni attivi', 'en' => 'Active days', 'format' => 'number'],
        'streak'           => ['group' => 'presence', 'icon' => 'fa-solid fa-fire', 'it' => 'Giorni di fila', 'en' => 'Day streak', 'format' => 'number'],
        'best_streak'      => ['group' => 'presence', 'icon' => 'fa-solid fa-medal', 'it' => 'Serie record', 'en' => 'Best streak', 'format' => 'number'],
        'favorite_page'    => ['group' => 'presence', 'icon' => 'fa-solid fa-location-dot', 'it' => 'Sezione preferita', 'en' => 'Favourite section', 'format' => 'text'],

        // Collezione e gacha
        'characters'       => ['group' => 'collection', 'icon' => 'fa-solid fa-user-astronaut', 'it' => 'Personaggi', 'en' => 'Characters', 'format' => 'number'],
        'pulls'            => ['group' => 'collection', 'icon' => 'fa-solid fa-dice-d20', 'it' => 'Pull', 'en' => 'Pulls', 'format' => 'number'],
        'collection'       => ['group' => 'collection', 'icon' => 'fa-solid fa-layer-group', 'it' => 'Collezione completata', 'en' => 'Collection complete', 'format' => 'percent'],
        'rarest_character' => ['group' => 'collection', 'icon' => 'fa-solid fa-gem', 'it' => 'Personaggio più raro', 'en' => 'Rarest character', 'format' => 'text'],
        'win_5050'         => ['group' => 'collection', 'icon' => 'fa-solid fa-scale-balanced', 'it' => '50/50 vinti', 'en' => '50/50 won', 'format' => 'percent'],
        'max_pity'         => ['group' => 'collection', 'icon' => 'fa-solid fa-hourglass-half', 'it' => 'Pity più alto', 'en' => 'Highest pity', 'format' => 'number'],
        'lootboxes'        => ['group' => 'collection', 'icon' => 'fa-solid fa-box-open', 'it' => 'Lootbox aperte', 'en' => 'Lootboxes opened', 'format' => 'number'],

        // Progressi
        'achievements'     => ['group' => 'progress', 'icon' => 'fa-solid fa-trophy', 'it' => 'Achievement', 'en' => 'Achievements', 'format' => 'number'],
        'achievement_points' => ['group' => 'progress', 'icon' => 'fa-solid fa-star', 'it' => 'Punti achievement', 'en' => 'Achievement points', 'format' => 'number'],
        'achievement_completion' => ['group' => 'progress', 'icon' => 'fa-solid fa-chart-pie', 'it' => 'Achievement completati', 'en' => 'Achievements complete', 'format' => 'percent'],
        'missions'         => ['group' => 'progress', 'icon' => 'fa-solid fa-list-check', 'it' => 'Missioni completate', 'en' => 'Missions completed', 'format' => 'number'],

        // Social e contenuti
        'friends'          => ['group' => 'social', 'icon' => 'fa-solid fa-user-group', 'it' => 'Amici', 'en' => 'Friends', 'format' => 'number'],
        'messages'         => ['group' => 'social', 'icon' => 'fa-solid fa-comments', 'it' => 'Messaggi inviati', 'en' => 'Messages sent', 'format' => 'number'],
        'posts'            => ['group' => 'social', 'icon' => 'fa-solid fa-image', 'it' => 'Post pubblicati', 'en' => 'Posts published', 'format' => 'number'],
        'likes_received'   => ['group' => 'social', 'icon' => 'fa-solid fa-heart', 'it' => 'Like ricevuti', 'en' => 'Likes received', 'format' => 'number'],
        'comments'         => ['group' => 'social', 'icon' => 'fa-solid fa-comment', 'it' => 'Commenti scritti', 'en' => 'Comments written', 'format' => 'number'],

        // Giochi
        'duels_won'        => ['group' => 'games', 'icon' => 'fa-solid fa-khanda', 'it' => 'Duelli vinti', 'en' => 'Duels won', 'format' => 'number'],
        'duel_winrate'     => ['group' => 'games', 'icon' => 'fa-solid fa-chart-line', 'it' => 'Vittorie nei duelli', 'en' => 'Duel win rate', 'format' => 'percent'],
        'subway_best'      => ['group' => 'games', 'icon' => 'fa-solid fa-train-subway', 'it' => 'Record Subway', 'en' => 'Subway record', 'format' => 'time'],
        'pullspot_won'     => ['group' => 'games', 'icon' => 'fa-solid fa-magnifying-glass', 'it' => 'Pullspot indovinati', 'en' => 'Pullspot solved', 'format' => 'number'],
        'animespot_points' => ['group' => 'games', 'icon' => 'fa-solid fa-music', 'it' => 'Punti Animespot', 'en' => 'Animespot points', 'format' => 'number'],

        // Economia
        'godos'            => ['group' => 'economy', 'icon' => 'fa-solid fa-coins', 'it' => 'Godos', 'en' => 'Godos', 'format' => 'number'],
        'godos_earned'     => ['group' => 'economy', 'icon' => 'fa-solid fa-sack-dollar', 'it' => 'Godos guadagnati', 'en' => 'Godos earned', 'format' => 'number'],
    ];
}

/** Nomi dei gruppi, per l'editor. */
function profile_stats_groups(string $lang = 'it'): array
{
    $it = $lang !== 'en';
    return [
        'presence'   => $it ? 'Profilo e presenza' : 'Profile and presence',
        'collection' => $it ? 'Collezione e gacha' : 'Collection and gacha',
        'progress'   => $it ? 'Progressi' : 'Progress',
        'social'     => $it ? 'Social e contenuti' : 'Social and content',
        'games'      => $it ? 'Giochi' : 'Games',
        'economy'    => $it ? 'Economia' : 'Economy',
    ];
}

function profile_stats_limit(bool $isPremium): int
{
    return $isPremium ? PROFILE_STATS_LIMIT_PREMIUM : PROFILE_STATS_LIMIT_FREE;
}

/**
 * Chiavi valide, senza doppioni, entro il limite del piano.
 *
 * @param mixed $raw array di chiavi o JSON che lo contiene
 * @return string[]
 */
function profile_stats_clean_keys($raw, bool $isPremium): array
{
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw)) {
        return [];
    }

    $catalog = profile_stats_catalog();
    $keys = [];
    foreach ($raw as $key) {
        if (is_string($key) && isset($catalog[$key]) && !in_array($key, $keys, true)) {
            $keys[] = $key;
        }
    }

    return array_slice($keys, 0, profile_stats_limit($isPremium));
}

/**
 * Le statistiche che il profilo deve mostrare.
 *
 * `explicit` dice se l'utente le ha scelte: le quattro di sempre nascondono
 * gli zeri, una scelta esplicita no (se l'hai messa, la vuoi vedere).
 *
 * @return array{keys:string[],explicit:bool}
 */
function profile_stats_selection(array $profile): array
{
    $isPremium = (int)($profile['is_premium'] ?? 0) === 1;
    $config = json_decode((string)($profile['profile_sections_config'] ?? ''), true);
    $items = is_array($config) ? ($config['stats']['items'] ?? null) : null;

    if (is_array($items)) {
        return ['keys' => profile_stats_clean_keys($items, $isPremium), 'explicit' => true];
    }

    return ['keys' => PROFILE_STATS_DEFAULT, 'explicit' => false];
}

/**
 * Rimette la scelta delle statistiche dentro la configurazione delle sezioni.
 *
 * @param string|null   $configJson configurazione gia' ripulita (anche vuota)
 * @param string[]|null $keys       null = nessuna scelta da salvare
 */
function profile_stats_merge_config(?string $configJson, ?array $keys): ?string
{
    $config = json_decode((string)$configJson, true);
    $config = is_array($config) ? $config : [];

    if ($keys === null) {
        unset($config['stats']['items']);
        if (isset($config['stats']) && $config['stats'] === []) {
            unset($config['stats']);
        }
    } else {
        $config['stats'] = (is_array($config['stats'] ?? null) ? $config['stats'] : []);
        $config['stats']['items'] = array_values($keys);
    }

    return $config === [] ? null : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ─────────────────────────────────────────────────────────────
//  CALCOLO
// ─────────────────────────────────────────────────────────────

/** Una query che non gira (tabella assente, colonna rinominata) vale null. */
function profile_stats_row(mysqli $mysqli, string $sql, string $types, array $params): ?array
{
    try {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function profile_stats_scalar(mysqli $mysqli, string $sql, string $types, array $params): ?int
{
    $row = profile_stats_row($mysqli, $sql, $types, $params);
    if ($row === null) {
        return null;
    }
    $value = reset($row);
    return $value === null ? null : (int)$value;
}

/**
 * Valori grezzi delle statistiche richieste.
 *
 * Numeri per quasi tutto, stringhe per le statistiche di testo, null quando
 * il dato non esiste. Le query che servono a piu' statistiche girano una
 * volta sola.
 *
 * @param string[] $keys
 * @return array<string,int|string|null>
 */
function profile_stats_raw(mysqli $mysqli, array $profile, array $keys, string $lang = 'it'): array
{
    $userId = (int)($profile['id'] ?? 0);
    $values = [];
    if ($userId <= 0) {
        return $values;
    }

    $memo = [];
    $once = static function (string $name, callable $fn) use (&$memo) {
        if (!array_key_exists($name, $memo)) {
            $memo[$name] = $fn();
        }
        return $memo[$name];
    };

    $owned = static fn() => $once('owned', static fn() => array_key_exists('num_personaggi', $profile)
        ? ['n' => (int)$profile['num_personaggi'], 'qty' => (int)$profile['total_personaggi']]
        : (profile_stats_row($mysqli, 'SELECT COUNT(DISTINCT personaggio_id) AS n, COALESCE(SUM(`quantità`), 0) AS qty FROM utenti_personaggi WHERE utente_id = ?', 'i', [$userId]) ?? ['n' => null, 'qty' => null]));

    $achievements = static fn() => $once('achievements', static fn() => array_key_exists('num_achievement', $profile)
        ? (int)$profile['num_achievement']
        : profile_stats_scalar($mysqli, 'SELECT COUNT(DISTINCT achievement_id) FROM utenti_achievement WHERE utente_id = ?', 'i', [$userId]));

    $totals = static fn() => $once('totals', static fn() => profile_stats_row(
        $mysqli,
        'SELECT total_seconds, legacy_seconds, total_days_active, legacy_days, current_streak, longest_streak FROM user_stat_totals WHERE utente_id = ? LIMIT 1',
        'i',
        [$userId]
    ) ?? (auth_table_exists($mysqli, 'user_stat_totals') ? [] : null));

    $daily = static fn(string $column) => $once('daily_' . $column, static fn() => profile_stats_scalar(
        $mysqli,
        // $column arriva solo dalle chiamate qui sotto, mai dall'esterno.
        "SELECT COALESCE(SUM(`$column`), 0) FROM user_daily_stats WHERE utente_id = ?",
        'i',
        [$userId]
    ));

    $gacha = static fn() => $once('gacha', static fn() => profile_stats_row(
        $mysqli,
        'SELECT SUM(esito_50_50 = 1) AS won, SUM(esito_50_50 = 0) AS lost, COALESCE(MAX(pity_al_momento), 0) AS max_pity, COUNT(*) AS n
         FROM gacha_pull_history WHERE utente_id = ?',
        'i',
        [$userId]
    ));

    $duels = static fn() => $once('duels', static fn() => profile_stats_row(
        $mysqli,
        "SELECT COUNT(*) AS played, COALESCE(SUM(winner_id = ?), 0) AS wins
         FROM game_matches
         WHERE (player1_id = ? OR player2_id = ?) AND status = 'finished'",
        'iii',
        [$userId, $userId, $userId]
    ));

    $catalogue = static fn(string $table) => $once('catalogue_' . $table, static fn() => profile_stats_scalar(
        $mysqli,
        "SELECT COUNT(*) FROM `$table`",
        '',
        []
    ));

    foreach ($keys as $key) {
        switch ($key) {
            case 'views':
                $values[$key] = (int)($profile['profile_views'] ?? 0);
                break;

            case 'member_days':
                $created = strtotime((string)($profile['data_creazione'] ?? ''));
                $values[$key] = $created ? max(0, (int)floor((time() - $created) / 86400)) : null;
                break;

            case 'time_on_site':
                $row = $totals();
                $values[$key] = $row === null ? null : (int)($row['total_seconds'] ?? 0) + (int)($row['legacy_seconds'] ?? 0);
                break;

            case 'days_active':
                $row = $totals();
                $values[$key] = $row === null ? null : (int)($row['total_days_active'] ?? 0) + (int)($row['legacy_days'] ?? 0);
                break;

            case 'streak':
                $row = $totals();
                $values[$key] = $row === null ? null : (int)($row['current_streak'] ?? 0);
                break;

            case 'best_streak':
                $row = $totals();
                $values[$key] = $row === null ? null : (int)($row['longest_streak'] ?? 0);
                break;

            case 'favorite_page':
                $row = profile_stats_row(
                    $mysqli,
                    "SELECT page_key FROM user_page_stats
                     WHERE utente_id = ? AND page_key <> 'altro'
                     GROUP BY page_key HAVING SUM(seconds) > 0
                     ORDER BY SUM(seconds) DESC LIMIT 1",
                    'i',
                    [$userId]
                );
                $values[$key] = $row ? profile_stats_page_name((string)$row['page_key'], $lang) : null;
                break;

            case 'characters':
                $values[$key] = $owned()['n'];
                break;

            case 'pulls':
                $values[$key] = $owned()['qty'];
                break;

            case 'collection':
                $n = $owned()['n'];
                $total = $catalogue('personaggi');
                $values[$key] = ($n === null || !$total) ? null : (int)round($n / $total * 100);
                break;

            case 'rarest_character':
                $row = profile_stats_row(
                    $mysqli,
                    "SELECT p.nome FROM utenti_personaggi up
                     INNER JOIN personaggi p ON p.id = up.personaggio_id
                     WHERE up.utente_id = ?
                     ORDER BY FIELD(p.`rarità`, 'theone', 'segreto', 'speciale', 'leggendario', 'epico', 'raro', 'comune') ASC, p.id ASC
                     LIMIT 1",
                    'i',
                    [$userId]
                );
                $values[$key] = $row && trim((string)$row['nome']) !== '' ? (string)$row['nome'] : null;
                break;

            case 'win_5050':
                $row = $gacha();
                $played = $row ? (int)$row['won'] + (int)$row['lost'] : 0;
                $values[$key] = $played > 0 ? (int)round((int)$row['won'] / $played * 100) : null;
                break;

            case 'max_pity':
                $row = $gacha();
                $values[$key] = $row === null ? null : (int)$row['max_pity'];
                break;

            case 'lootboxes':
                $values[$key] = $daily('lootboxes_opened');
                break;

            case 'achievements':
                $values[$key] = $achievements();
                break;

            case 'achievement_points':
                $values[$key] = profile_stats_scalar(
                    $mysqli,
                    'SELECT COALESCE(SUM(a.punti), 0) FROM utenti_achievement ua INNER JOIN achievement a ON a.id = ua.achievement_id WHERE ua.utente_id = ?',
                    'i',
                    [$userId]
                );
                break;

            case 'achievement_completion':
                $n = $achievements();
                $total = $catalogue('achievement');
                $values[$key] = ($n === null || !$total) ? null : (int)round($n / $total * 100);
                break;

            case 'missions':
                $values[$key] = profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM user_missions WHERE user_id = ? AND completata = 1', 'i', [$userId]);
                break;

            case 'friends':
                $values[$key] = profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM friendships WHERE user_one_id = ? OR user_two_id = ?', 'ii', [$userId, $userId]);
                break;

            case 'messages':
                $parts = [
                    profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM messages WHERE user_id = ?', 'i', [$userId]),
                    profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM private_messages WHERE sender_id = ?', 'i', [$userId]),
                    profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM chat_messages WHERE sender_id = ?', 'i', [$userId]),
                ];
                $known = array_filter($parts, static fn($v) => $v !== null);
                $values[$key] = $known ? array_sum($known) : null;
                break;

            case 'posts':
                $parts = [
                    profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM shitposts WHERE id_utente = ? AND approvato = 1', 'i', [$userId]),
                    profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM toprimasti WHERE id_utente = ? AND approvato = 1', 'i', [$userId]),
                ];
                $known = array_filter($parts, static fn($v) => $v !== null);
                $values[$key] = $known ? array_sum($known) : null;
                break;

            case 'likes_received':
                $values[$key] = profile_stats_scalar(
                    $mysqli,
                    'SELECT COUNT(*) FROM shitpost_likes l INNER JOIN shitposts s ON s.id = l.id_shitpost WHERE s.id_utente = ?',
                    'i',
                    [$userId]
                );
                break;

            case 'comments':
                $values[$key] = profile_stats_scalar($mysqli, 'SELECT COUNT(*) FROM commenti_shitpost WHERE id_utente = ?', 'i', [$userId]);
                break;

            case 'duels_won':
                $row = $duels();
                $values[$key] = $row === null ? null : (int)$row['wins'];
                break;

            case 'duel_winrate':
                $row = $duels();
                $values[$key] = ($row && (int)$row['played'] > 0) ? (int)round((int)$row['wins'] / (int)$row['played'] * 100) : null;
                break;

            case 'subway_best':
                $values[$key] = profile_stats_scalar($mysqli, 'SELECT best_time_ms FROM subway_leaderboard WHERE utente_id = ? LIMIT 1', 'i', [$userId]);
                break;

            case 'pullspot_won':
                $values[$key] = $daily('pullspot_won');
                break;

            case 'animespot_points':
                $values[$key] = $daily('animespot_points');
                break;

            case 'godos':
                $values[$key] = array_key_exists('soldi', $profile)
                    ? (int)$profile['soldi']
                    : profile_stats_scalar($mysqli, 'SELECT soldi FROM utenti WHERE id = ?', 'i', [$userId]);
                break;

            case 'godos_earned':
                $values[$key] = $daily('godos_earned');
                break;
        }
    }

    return $values;
}

/** Il nome leggibile di una sezione del sito, come nel Rewind. */
function profile_stats_page_name(string $key, string $lang = 'it'): string
{
    $names = [
        'home' => ['Home', 'Home'], 'profilo' => ['Profili', 'Profiles'], 'rewind' => ['Rewind', 'Rewind'],
        'chat' => ['Chat', 'Chat'], 'global-chat' => ['Chat globale', 'Global chat'], 'inbox' => ['Inbox', 'Inbox'],
        'amici' => ['Amici', 'Friends'], 'lootbox' => ['Lootbox', 'Lootbox'], 'gacha' => ['Shop gacha', 'Gacha shop'],
        'negozio' => ['Negozio', 'Store'], 'inventario' => ['Inventario', 'Inventory'], 'achievements' => ['Achievement', 'Achievements'],
        'missions' => ['Missioni', 'Missions'], 'subway' => ['Subway', 'Subway'], 'pullspot' => ['Pullspot', 'Pullspot'],
        'animespot' => ['Animespot', 'Animespot'], 'game' => ['Duelli', 'Duels'], 'gambling' => ['Gambling', 'Gambling'],
        'goonland' => ['GoonLand', 'GoonLand'], 'shitpost' => ['Shitpost', 'Shitpost'], 'rimasti' => ['Top Rimasti', 'Top Rimasti'],
        'cripsumpedia' => ['CripsumPedia', 'CripsumPedia'], 'edits' => ['Edits', 'Edits'], 'download' => ['Download', 'Downloads'],
        'tiktokpedia' => ['TikTokPedia', 'TikTokPedia'], 'merch' => ['Merch', 'Merch'], 'donazioni' => ['Donazioni', 'Donations'],
        'impostazioni' => ['Impostazioni', 'Settings'],
    ];
    $pair = $names[$key] ?? [ucfirst($key), ucfirst($key)];
    return $lang === 'en' ? $pair[1] : $pair[0];
}

/**
 * Valore pronto da mostrare: cifra principale e unita' piccola accanto.
 *
 * @param int|string|null $raw
 * @return array{value:string,unit:string}|null
 */
function profile_stats_format(string $key, $raw, string $lang = 'it'): ?array
{
    $def = profile_stats_catalog()[$key] ?? null;
    if ($def === null || $raw === null || $raw === '') {
        return null;
    }

    switch ($def['format']) {
        case 'text':
            return ['value' => (string)$raw, 'unit' => ''];

        case 'percent':
            return ['value' => (string)(int)$raw, 'unit' => '%'];

        case 'duration':
            $seconds = (int)$raw;
            if ($seconds < 3600) {
                return ['value' => (string)(int)round($seconds / 60), 'unit' => 'min'];
            }
            return ['value' => profile_compact_number((int)round($seconds / 3600)), 'unit' => $lang === 'en' ? 'h' : 'h'];

        case 'time':
            $ms = max(0, (int)$raw);
            $minutes = intdiv($ms, 60000);
            $seconds = intdiv($ms % 60000, 1000);
            return ['value' => $minutes > 0 ? sprintf('%d:%02d', $minutes, $seconds) : (string)$seconds, 'unit' => $minutes > 0 ? '' : 's'];

        default:
            return ['value' => profile_compact_number((int)$raw), 'unit' => ''];
    }
}

/**
 * Le card del box statistiche, pronte per il template.
 *
 * @return array<int,array{key:string,icon:string,label:string,value:string,unit:string,format:string}>
 */
function profile_stats_cards(mysqli $mysqli, array $profile, string $lang = 'it'): array
{
    $selection = profile_stats_selection($profile);
    if ($selection['keys'] === []) {
        return [];
    }

    $catalog = profile_stats_catalog();
    $raw = profile_stats_raw($mysqli, $profile, $selection['keys'], $lang);
    $cards = [];

    foreach ($selection['keys'] as $key) {
        $value = $raw[$key] ?? null;
        // Le quattro di sempre nascondono gli zeri; una scelta esplicita li mostra.
        if ($value === null || (!$selection['explicit'] && (int)$value === 0)) {
            continue;
        }
        $formatted = profile_stats_format($key, $value, $lang);
        if ($formatted === null) {
            continue;
        }
        $cards[] = [
            'key'    => $key,
            'icon'   => $catalog[$key]['icon'],
            'label'  => $lang === 'en' ? $catalog[$key]['en'] : $catalog[$key]['it'],
            'value'  => $formatted['value'],
            'unit'   => $formatted['unit'],
            'format' => $catalog[$key]['format'],
        ];
    }

    return $cards;
}

/**
 * Il catalogo per l'editor, con il valore attuale di ogni statistica.
 *
 * @return array<int,array<string,mixed>>
 */
function profile_stats_editor_catalog(mysqli $mysqli, array $profile, string $lang = 'it'): array
{
    $catalog = profile_stats_catalog();
    $raw = profile_stats_raw($mysqli, $profile, array_keys($catalog), $lang);
    $out = [];

    foreach ($catalog as $key => $def) {
        $formatted = profile_stats_format($key, $raw[$key] ?? null, $lang);
        $out[] = [
            'key'   => $key,
            'group' => $def['group'],
            'icon'  => $def['icon'],
            'label' => $lang === 'en' ? $def['en'] : $def['it'],
            'value' => $formatted ? $formatted['value'] . ($formatted['unit'] !== '' ? ($def['format'] === 'percent' ? '' : ' ') . $formatted['unit'] : '') : null,
        ];
    }

    return $out;
}
