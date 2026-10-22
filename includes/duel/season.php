<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — stagioni, ricompense in Godos, avvisi in campanella (fase 6).
 *
 * Stagioni (migrations/2026_10_21_duelli2_fase6.sql):
 * - partono solo con game_settings.seasons = 1; finche' e' 0 la classifica
 *   resta quella di sempre (la «Stagione 0»);
 * - durano 6 settimane; le prime 5 Ranked sono di piazzamento (il rango si
 *   vede dopo la quinta);
 * - i punti restano game_player_stats.rating (li leggono profili e lobby);
 * - a fine stagione chi ha fatto almeno 5 Ranked prende i Godos della fascia
 *   piu' alta raggiunta, poi i punti di tutti si avvicinano a 1.000 della
 *   meta' (1.506 -> 1.253) e parte la stagione dopo.
 * La chiusura la fa la pulizia (maint.php), senza cron.
 *
 * Ricompense: una riga in game_rewards per premio, con chiave unica
 * (utente, tipo, riferimento): pagare due volte e' impossibile anche se la
 * stessa cosa viene chiesta due volte. I Godos sono utenti.soldi.
 *
 * Avvisi: i messaggi in Posta con la campanella del sito (cm_notify).
 */

require_once __DIR__ . '/store.php';

const DUEL_SEASON_DAYS = 42;
const DUEL_SEASON_PLACEMENT = 5;
// Godos per la fascia piu' alta raggiunta in stagione (tua decisione 7).
const DUEL_SEASON_REWARDS = ['bronzo' => 100, 'argento' => 250, 'oro' => 500, 'diamante' => 800, 'campione' => 1200, 'leggenda' => 2000];
const DUEL_DAILY_WIN = 150;

/** La migrazione della fase 6 e' stata applicata. */
/**
 * I Godos per fascia a fine stagione: quelli scritti dal pannello
 * (game_settings reward_<fascia>, fase 7) o quelli di DUEL_SEASON_REWARDS.
 */
function duel_season_rewards(mysqli $m): array
{
    $settings = duel_settings($m);
    $out = [];
    foreach (DUEL_SEASON_REWARDS as $tier => $godos) {
        $value = $settings['reward_' . $tier] ?? null;
        $out[$tier] = $value !== null && is_numeric($value) ? max(0, (int)$value) : $godos;
    }
    return $out;
}

/** Quanti giorni dura una stagione nuova (pannello: season_days, da 7 a 120). */
function duel_season_days(mysqli $m): int
{
    $days = (int)(duel_settings($m)['season_days'] ?? 0);
    return $days >= 7 && $days <= 120 ? $days : DUEL_SEASON_DAYS;
}

function duel_phase6_ready(mysqli $m): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = rt_has_table($m, 'game_seasons') && rt_has_table($m, 'game_season_ratings') && rt_has_table($m, 'game_rewards')
            && rt_has_table($m, 'game_challenges') && rt_has_col($m, 'game_matches', 'is_async');
    }
    return $ready;
}

// ─────────────────────────────────────────────────────────────────────────
// Avvisi in campanella
// ─────────────────────────────────────────────────────────────────────────

/** Un messaggio in Posta con la campanella. Non fa mai fallire chi lo chiama. */
function duel_notify(mysqli $m, int $userId, string $titleIt, string $titleEn, string $bodyIt, string $bodyEn, int $quietMinutes = 0): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        require_once __DIR__ . '/../community/community.php';
        if (function_exists('cm_notify')) {
            cm_notify($m, $userId, $titleIt, $titleEn, $bodyIt, $bodyEn, 'system', $quietMinutes);
        }
    } catch (Throwable $e) {
        error_log('[duel] avviso non mandato: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Ricompense
// ─────────────────────────────────────────────────────────────────────────

/** Paga un premio una volta sola. Vero se e' stato pagato adesso. */
function duel_reward_grant(mysqli $m, int $userId, string $kind, string $ref, int $amount, ?int $matchId = null): bool
{
    if ($userId <= 0 || $amount <= 0 || !duel_phase6_ready($m)) {
        return false;
    }
    $added = duel_exec($m, 'INSERT IGNORE INTO game_rewards (user_id, kind, ref, amount, match_id) VALUES (?, ?, ?, ?, ?)', 'issii',
        [$userId, $kind, $ref, $amount, $matchId]);
    if ($added !== 1) {
        return false;
    }
    duel_exec($m, 'UPDATE utenti SET soldi = soldi + ? WHERE id = ?', 'ii', [$amount, $userId]);
    return true;
}

/**
 * Prima vittoria del giorno: contro una persona, o contro il bot da Normale
 * in su (il Facile si vincerebbe sempre).
 */
function duel_reward_daily_win(mysqli $m, array $row, int $winnerId): bool
{
    if ($winnerId <= 0) {
        return false;
    }
    if (duel_is_bot_match($row) && (string)$row['bot_level'] === 'facile') {
        return false;
    }
    $amount = (int)(duel_settings($m)['daily_win'] ?? DUEL_DAILY_WIN);
    return duel_reward_grant($m, $winnerId, 'daily_win', date('Y-m-d'), $amount > 0 ? $amount : DUEL_DAILY_WIN, (int)$row['id']);
}

/** I premi pagati con una partita: [utente => [[kind, amount]]]. */
function duel_rewards_for_match(mysqli $m, int $matchId): array
{
    if (!duel_phase6_ready($m)) {
        return [];
    }
    $out = [];
    foreach (duel_rows($m, 'SELECT user_id, kind, amount FROM game_rewards WHERE match_id = ?', 'i', [$matchId]) as $r) {
        $out[(int)$r['user_id']][] = ['kind' => (string)$r['kind'], 'amount' => (int)$r['amount']];
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────
// Stagioni
// ─────────────────────────────────────────────────────────────────────────

/** La stagione in corso, o null (nessuna: e' la «Stagione 0»). */
function duel_season_current(mysqli $m): ?array
{
    if (!duel_phase6_ready($m)) {
        return null;
    }
    $row = duel_row($m, "SELECT * FROM game_seasons WHERE status = 'active' ORDER BY number DESC LIMIT 1");
    return $row ? duel_season_view($row) : null;
}

function duel_season_view(array $row): array
{
    $start = strtotime((string)$row['starts_at']);
    $end = strtotime((string)$row['ends_at']);
    return [
        'id' => (int)$row['id'], 'number' => (int)$row['number'], 'name' => (string)($row['name'] ?? ''),
        'starts' => $start, 'ends' => $end, 'status' => (string)$row['status'],
        // Arrotondati: col cambio dell'ora legale 42 giorni sono 42 giorni e un'ora.
        'days_left' => max(0, (int)round(($end - time()) / 86400)),
        'progress' => $end > $start ? max(0, min(1, (time() - $start) / ($end - $start))) : 1,
    ];
}

/** Una Ranked finita: conta per la stagione di chi l'ha giocata. */
function duel_season_record(mysqli $m, array $row, array $sides, ?int $winnerSide): void
{
    $season = duel_season_current($m);
    if (!$season || (string)$row['mode'] !== 'ranked') {
        return;
    }
    foreach ([0, 1] as $side) {
        $userId = duel_side_user($row, $side);
        if ($userId <= 0 || !isset($sides[$side])) {
            continue;
        }
        [$before, $after] = $sides[$side];
        $won = $winnerSide === $side ? 1 : 0;
        $lost = $winnerSide !== null && $winnerSide !== $side ? 1 : 0;
        duel_exec($m, 'INSERT INTO game_season_ratings (season_id, user_id, games, wins, losses, rating_start, peak) VALUES (?, ?, 1, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE games = games + 1, wins = wins + VALUES(wins), losses = losses + VALUES(losses), peak = GREATEST(peak, ?)',
            'iiiiiii', [$season['id'], $userId, $won, $lost, $before, max($before, $after), $after]);
    }
    duel_exec($m, 'UPDATE game_matches SET season_id = ? WHERE id = ?', 'ii', [$season['id'], (int)$row['id']]);
}

/** Quante Ranked hai giocato nella stagione in corso (per il piazzamento). */
function duel_season_me(mysqli $m, int $userId, ?array $season = null): ?array
{
    $season ??= duel_season_current($m);
    if (!$season) {
        return null;
    }
    $r = duel_row($m, 'SELECT games, wins, losses, peak FROM game_season_ratings WHERE season_id = ? AND user_id = ?', 'ii', [$season['id'], $userId]);
    $games = (int)($r['games'] ?? 0);
    return ['games' => $games, 'wins' => (int)($r['wins'] ?? 0), 'losses' => (int)($r['losses'] ?? 0), 'peak' => $r ? (int)$r['peak'] : null,
        'placement' => max(0, DUEL_SEASON_PLACEMENT - $games)];
}

/** L'emblema dell'ultima stagione finita (si vede nei duelli, non sul profilo). */
function duel_season_badge(mysqli $m, int $userId): ?array
{
    if ($userId <= 0 || !duel_phase6_ready($m)) {
        return null;
    }
    $r = duel_row($m, "SELECT s.number, r.tier FROM game_season_ratings r JOIN game_seasons s ON s.id = r.season_id
        WHERE r.user_id = ? AND s.status = 'closed' AND r.tier IS NOT NULL ORDER BY s.number DESC LIMIT 1", 'i', [$userId]);
    return $r ? ['n' => (int)$r['number'], 'tier' => (string)$r['tier']] : null;
}

/**
 * Il lavoro delle stagioni nella pulizia: chiude quella finita (premi e
 * punti verso 1.000), apre la prossima se le stagioni sono accese.
 */
function duel_season_tick(mysqli $m): array
{
    $out = ['closed' => null, 'opened' => null];
    if (!duel_phase6_ready($m)) {
        return $out;
    }
    $cur = duel_row($m, "SELECT * FROM game_seasons WHERE status = 'active' ORDER BY number DESC LIMIT 1");
    if ($cur && strtotime((string)$cur['ends_at']) <= time()) {
        if (duel_season_close($m, (int)$cur['id'])) {
            $out['closed'] = (int)$cur['number'];
        }
        $cur = null;
    }
    if (!$cur && duel_settings($m, true)['seasons'] === '1') {
        $out['opened'] = duel_season_open($m);
    }
    return $out;
}

/** Apre la stagione dopo l'ultima (il numero unico evita i doppioni). */
function duel_season_open(mysqli $m): ?int
{
    $next = 1 + (int)(duel_row($m, 'SELECT MAX(number) AS n FROM game_seasons')['n'] ?? 0);
    $added = duel_exec($m, "INSERT IGNORE INTO game_seasons (number, starts_at, ends_at, status) VALUES (?, NOW(), NOW() + INTERVAL ? DAY, 'active')",
        'ii', [$next, duel_season_days($m)]);
    return $added === 1 ? $next : null;
}

/**
 * Chiude una stagione. Un blocco con nome tiene fuori le pulizie in
 * contemporanea; i premi passano da game_rewards (mai due volte), e solo
 * l'UPDATE che chiude la stagione fa anche il reset dei punti: se qualcosa
 * si ferma a meta', il giro dopo ricomincia senza pagare di nuovo.
 */
function duel_season_close(mysqli $m, int $seasonId): bool
{
    $got = duel_row($m, "SELECT GET_LOCK('duel_season', 5) AS ok");
    if ((int)($got['ok'] ?? 0) !== 1) {
        return false;
    }
    try {
        $season = duel_row($m, 'SELECT * FROM game_seasons WHERE id = ?', 'i', [$seasonId]);
        if (!$season || (string)$season['status'] !== 'active') {
            return false;
        }
        $n = (int)$season['number'];
        $players = duel_rows($m, 'SELECT r.user_id, r.games, r.peak, COALESCE(s.rating, 1000) AS rating
            FROM game_season_ratings r LEFT JOIN game_player_stats s ON s.user_id = r.user_id WHERE r.season_id = ?', 'i', [$seasonId]);
        $paid = [];
        $godos = duel_season_rewards($m);
        // Fase T4: il badge della stagione nella fascia del picco (includes/duel/badges.php).
        require_once __DIR__ . '/badges.php';
        $badges = [];
        foreach ($players as $p) {
            $userId = (int)$p['user_id'];
            $placed = (int)$p['games'] >= DUEL_SEASON_PLACEMENT;
            $tier = $placed ? duel_rank((int)$p['peak'])['key'] : null;
            $reward = $tier ? ($godos[$tier] ?? 0) : 0;
            // Il badge si crea fuori dalla transazione del giocatore: resta anche se la sua si annulla.
            // Un badge che non si crea non ferma la chiusura ne' i premi.
            if ($tier && !array_key_exists($tier, $badges)) {
                try {
                    $badges[$tier] = duel_season_badge_id($m, $n, $tier);
                } catch (Throwable $e) {
                    error_log('[duel stagione] badge ' . $tier . ': ' . $e->getMessage());
                    $badges[$tier] = null;
                }
            }
            $badgeId = $tier ? $badges[$tier] : null;
            $now = duel_tx($m, static function () use ($m, $seasonId, $userId, $p, $tier, $reward, $badgeId): bool {
                duel_exec($m, 'UPDATE game_season_ratings SET rating_end = ?, tier = ?, reward = ? WHERE season_id = ? AND user_id = ?', 'isiii',
                    [(int)$p['rating'], $tier, $reward, $seasonId, $userId]);
                if ($badgeId) {
                    try {
                        duel_badge_give($m, $userId, $badgeId);
                    } catch (Throwable $e) {
                        error_log('[duel stagione] badge a ' . $userId . ': ' . $e->getMessage());
                    }
                }
                return duel_reward_grant($m, $userId, 'season', (string)$seasonId, $reward);
            });
            if ($now) {
                $paid[] = [$userId, $tier, $reward];
            }
        }
        $closed = duel_tx($m, static function () use ($m, $seasonId): bool {
            if (duel_exec($m, "UPDATE game_seasons SET status = 'closed', closed_at = NOW() WHERE id = ? AND status = 'active'", 'i', [$seasonId]) !== 1) {
                return false;
            }
            // I punti di tutti si avvicinano a 1.000 della meta'.
            duel_exec($m, 'UPDATE game_player_stats SET rating = ROUND(1000 + (rating - 1000) / 2) WHERE rating <> 1000');
            return true;
        });
        foreach ($paid as [$userId, $tier, $reward]) {
            $it = duel_rank_label($tier, 'it');
            $en = duel_rank_label($tier, 'en');
            $badgeIt = !empty($badges[$tier]) ? " e il badge «Stagione $n · {$it}» sul profilo" : '';
            $badgeEn = !empty($badges[$tier]) ? " and the «Season $n · {$en}» profile badge" : '';
            duel_notify($m, $userId, "Stagione $n finita: $it", "Season $n is over: $en",
                "Hai raggiunto $it nei duelli: +$reward Godos$badgeIt. I punti ripartono più vicini a 1.000 e comincia la stagione dopo.",
                "You reached $en in duels: +$reward Godos$badgeEn. Points move closer to 1,000 and the next season begins.");
        }
        return $closed;
    } finally {
        duel_row($m, "SELECT RELEASE_LOCK('duel_season') AS ok");
    }
}

function duel_rank_label(?string $key, string $lang): string
{
    $names = ['bronzo' => ['Bronzo', 'Bronze'], 'argento' => ['Argento', 'Silver'], 'oro' => ['Oro', 'Gold'], 'diamante' => ['Diamante', 'Diamond'],
        'campione' => ['Campione', 'Champion'], 'leggenda' => ['Leggenda', 'Legend']];
    return $names[$key][$lang === 'en' ? 1 : 0] ?? (string)$key;
}

/**
 * La pagina Stagione (tavola 6): la tua fascia, i premi con quelli gia'
 * raggiunti, la posizione, le stagioni passate.
 */
function duel_season_page(mysqli $m, int $userId): array
{
    $stats = duel_row($m, 'SELECT rating, games_played FROM game_player_stats WHERE user_id = ?', 'i', [$userId]);
    $rating = (int)($stats['rating'] ?? 1000);
    $season = duel_season_current($m);
    $me = $season ? duel_season_me($m, $userId, $season) : null;
    $pos = null;
    $total = 0;
    if ($season) {
        $total = (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_season_ratings WHERE season_id = ?', 'i', [$season['id']])['n'] ?? 0);
        if ($me && $me['games'] > 0) {
            $pos = 1 + (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_season_ratings r JOIN game_player_stats s ON s.user_id = r.user_id
                WHERE r.season_id = ? AND (s.rating > ? OR (s.rating = ? AND s.user_id < ?))', 'iiii', [$season['id'], $rating, $rating, $userId])['n'] ?? 0);
        }
    } else {
        $total = (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_player_stats WHERE games_played > 0')['n'] ?? 0);
        if ((int)($stats['games_played'] ?? 0) > 0) {
            $pos = 1 + (int)(duel_row($m, 'SELECT COUNT(*) AS n FROM game_player_stats WHERE games_played > 0 AND (rating > ? OR (rating = ? AND user_id < ?))',
                'iii', [$rating, $rating, $userId])['n'] ?? 0);
        }
    }
    $peak = $me && $me['peak'] !== null ? $me['peak'] : $rating;
    $rewards = [];
    foreach (duel_season_rewards($m) as $key => $godos) {
        $min = ['bronzo' => 0, 'argento' => 1000, 'oro' => 1200, 'diamante' => 1400, 'campione' => 1600, 'leggenda' => 1900][$key];
        $rewards[] = ['tier' => $key, 'min' => $min, 'godos' => $godos, 'reached' => $peak >= $min && (!$season || ($me && $me['placement'] === 0))];
    }
    $past = [];
    if (duel_phase6_ready($m)) {
        // Fase T4: se il badge della stagione e' sul profilo (includes/duel/badges.php).
        require_once __DIR__ . '/badges.php';
        $badge = duel_badges_ready($m) ? "EXISTS (SELECT 1 FROM user_custom_badges ub JOIN custom_badges b ON b.id = ub.badge_id
            WHERE ub.utente_id = r.user_id AND b.slug = CONCAT('duelli-s', s.number, '-', r.tier))" : '0';
        foreach (duel_rows($m, "SELECT s.number, s.name, r.tier, r.rating_end, r.games, r.reward, $badge AS badge FROM game_season_ratings r
            JOIN game_seasons s ON s.id = r.season_id WHERE r.user_id = ? AND s.status = 'closed' ORDER BY s.number DESC LIMIT 20", 'i', [$userId]) as $r) {
            $past[] = ['n' => (int)$r['number'], 'name' => (string)($r['name'] ?? ''), 'tier' => $r['tier'] !== null ? (string)$r['tier'] : null,
                'rating' => $r['rating_end'] !== null ? (int)$r['rating_end'] : null, 'games' => (int)$r['games'], 'reward' => (int)$r['reward'],
                'badge' => (bool)$r['badge']];
        }
    }
    return [
        'season' => $season, 'me' => $me, 'rating' => $rating, 'rank' => duel_rank($rating), 'peak' => $peak,
        'pos' => $pos, 'total' => $total, 'rewards' => $rewards, 'past' => $past,
        'placement_games' => DUEL_SEASON_PLACEMENT, 'days' => duel_season_days($m),
    ];
}
