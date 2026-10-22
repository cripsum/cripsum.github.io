<?php

declare(strict_types=1);

/**
 * Duelli 2.0 — i badge del profilo che danno i duelli (fase T4 della Torre).
 *
 * Sono badge personalizzati come gli altri (custom_badges e
 * user_custom_badges): si vedono subito sul profilo e si nascondono
 * dall'editor. Ogni badge si crea la prima volta che serve, con uno slug
 * fisso, e si da' una volta sola.
 *
 * - «Stagione 3 · Oro» (duelli-s3-oro): alla chiusura della stagione, a chi
 *   si e' piazzato, nella fascia del picco (la stessa dei premi). Campione e
 *   Leggenda con l'alone, Leggenda anche con l'animazione. Tipo 'duelli': il
 *   profilo scrive «Stagione dei duelli» sotto il nome.
 * - «In cima alla Torre» (duelli-torre-cima): la prima volta che si batte il
 *   piano 100.
 *
 * Le stagioni gia' chiuse e il piano 100 gia' battuto li recupera la
 * migrazione 2026_10_22_duelli2_profilo.sql.
 */

require_once __DIR__ . '/db.php';

/** I colori delle fasce, come gli emblemi (pages.css, --t-*). */
const DUEL_TIER_COLORS = ['bronzo' => '#c8793a', 'argento' => '#c7c7cc', 'oro' => '#eab308', 'diamante' => '#22b8cf',
    'campione' => '#a855f7', 'leggenda' => '#ff453a'];

function duel_badges_ready(mysqli $m): bool
{
    return rt_has_table($m, 'custom_badges') && rt_has_table($m, 'user_custom_badges');
}

/**
 * L'id del badge con questo slug, creato se manca. $def: name, name_en,
 * descrizione, descrizione_en, image_url (sotto /img/), color, glow,
 * animation, badge_type.
 */
function duel_badge_id(mysqli $m, string $slug, array $def): ?int
{
    if (!duel_badges_ready($m)) {
        return null;
    }
    $row = duel_row($m, 'SELECT id FROM custom_badges WHERE slug = ? LIMIT 1', 's', [$slug]);
    if ($row) {
        return (int)$row['id'];
    }
    $stmt = duel_q($m, 'INSERT INTO custom_badges (slug, name, name_en, tooltip, tooltip_en, descrizione, descrizione_en, image_url, color, glow, animation, badge_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'sssssssssiss', [
        $slug, $def['name'], $def['name_en'], $def['descrizione'], $def['descrizione_en'], $def['descrizione'], $def['descrizione_en'],
        $def['image_url'], $def['color'], (int)$def['glow'], $def['animation'], $def['badge_type'],
    ]);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id ?: null;
}

/** Da' un badge, visibile da subito; vero se e' nuovo. */
function duel_badge_give(mysqli $m, int $userId, int $badgeId): bool
{
    return $userId > 0 && duel_exec($m, 'INSERT INTO user_custom_badges (utente_id, badge_id, is_visible)
        SELECT ?, ?, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = ?)',
        'iiii', [$userId, $badgeId, $userId, $badgeId]) === 1;
}

/** Il badge del profilo della stagione $n nella fascia $tier (duel_season_badge() e' l'emblema dell'arena). */
function duel_season_badge_id(mysqli $m, int $n, string $tier): ?int
{
    require_once __DIR__ . '/season.php';
    $it = duel_rank_label($tier, 'it');
    $en = duel_rank_label($tier, 'en');
    return duel_badge_id($m, 'duelli-s' . $n . '-' . $tier, [
        'name' => "Stagione $n · $it", 'name_en' => "Season $n · $en",
        'descrizione' => "Ha chiuso la stagione $n dei duelli in $it.", 'descrizione_en' => "Finished season $n of the duels in $en.",
        'image_url' => 'duelli/rank-' . $tier . '.svg', 'color' => DUEL_TIER_COLORS[$tier] ?? '#a1a1a6',
        'glow' => in_array($tier, ['campione', 'leggenda'], true) ? 1 : 0, 'animation' => $tier === 'leggenda' ? 'pulse' : 'none',
        'badge_type' => 'duelli',
    ]);
}

/** Il badge di chi ha battuto il piano 100 della Torre. */
function duel_tower_top_badge(mysqli $m): ?int
{
    return duel_badge_id($m, 'duelli-torre-cima', [
        'name' => 'In cima alla Torre', 'name_en' => 'Top of the Tower',
        'descrizione' => 'Ha battuto il piano 100 della Torre dei duelli.', 'descrizione_en' => 'Beat floor 100 of the duels Tower.',
        'image_url' => 'duelli/torre-cima.svg', 'color' => '#f5f5f7', 'glow' => 1, 'animation' => 'none', 'badge_type' => 'rare',
    ]);
}
