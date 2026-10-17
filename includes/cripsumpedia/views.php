<?php
/**
 * Cripsumpedia — le letture.
 *
 * Prima ogni caricamento di una voce aggiungeva una riga con l'IP cifrato
 * (sha256 senza sale: per un IPv4 si risale all'indirizzo in pochi secondi) e
 * un punto di «tendenza» che non scendeva mai. Adesso: una persona conta una
 * volta ogni sei ore, lo ricorda la sua sessione; staff e bot non contano; nel
 * database resta solo quante letture ha avuto una voce in un giorno.
 */

const CP_VIEW_WINDOW = 21600; // sei ore

/** Anteprime dei link (Discord, Telegram, WhatsApp…) e motori di ricerca. */
function cp_is_bot_request(): bool
{
    $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agent === ''
        || preg_match('~bot|crawl|spider|slurp|preview|embed|facebookexternalhit|discord|telegram|whatsapp|curl|wget|python~i', $agent) === 1;
}

function cp_record_view(mysqli $mysqli, int $entryId): void
{
    if (!cp_schema_ready($mysqli) || $entryId <= 0) return;
    if (cp_is_admin_user() || cp_is_bot_request()) return;
    if (session_status() !== PHP_SESSION_ACTIVE) return;

    $now = time();
    $seen = is_array($_SESSION['cp_seen'] ?? null) ? $_SESSION['cp_seen'] : [];
    if (isset($seen[$entryId]) && (int)$seen[$entryId] > $now - CP_VIEW_WINDOW) return;

    $seen = array_filter($seen, static fn($at): bool => (int)$at > $now - CP_VIEW_WINDOW);
    $seen[$entryId] = $now;
    $_SESSION['cp_seen'] = array_slice($seen, -100, null, true);

    if (cp_v2_ready($mysqli)) {
        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_views_daily (entry_id, day, views) VALUES (?, CURDATE(), 1)
                                  ON DUPLICATE KEY UPDATE views = views + 1");
        if ($stmt) {
            $stmt->bind_param('i', $entryId);
            $stmt->execute();
            $stmt->close();
        }
        $sql = "UPDATE cripsumpedia_entries SET views_count = views_count + 1 WHERE id = ?";
    } else {
        // Prima della migrazione «In tendenza» usa ancora trending_score.
        $sql = "UPDATE cripsumpedia_entries SET views_count = views_count + 1, trending_score = trending_score + 0.35 WHERE id = ?";
    }

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }
}

/** L'ordine «in tendenza»: letture degli ultimi 14 giorni. */
function cp_trending_order_sql(mysqli $mysqli, string $alias = 'e'): string
{
    if (!cp_v2_ready($mysqli)) {
        return "$alias.trending_score DESC, $alias.views_count DESC, $alias.updated_at DESC";
    }
    return "(SELECT COALESCE(SUM(vd.views), 0) FROM cripsumpedia_views_daily vd
             WHERE vd.entry_id = $alias.id AND vd.day >= CURDATE() - INTERVAL 14 DAY) DESC,
            $alias.views_count DESC, $alias.updated_at DESC";
}
