<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

$isLoggedIn = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
$currentUsername = $_SESSION['username'] ?? null;

$isPremium = false;
$supporters = [];
$supportersTotal = 0;

/** Riga di chi sta guardando: serve al suo posto fisso tra i supporter. */
$viewerRow = [];

/** Tante facce bastano: la fila e' decorativa, non un elenco da consultare. */
const HOME_SUPPORTERS_LIMIT = 40;

if (isset($mysqli) && $mysqli instanceof mysqli) {
    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        $stmtPrem = $mysqli->prepare("SELECT is_premium, accent_color, profile_updated_at FROM utenti WHERE id = ? LIMIT 1");
        if ($stmtPrem) {
            $stmtPrem->bind_param('i', $_SESSION['user_id']);
            $stmtPrem->execute();
            $viewerRow = $stmtPrem->get_result()->fetch_assoc() ?: [];
            $isPremium = ((int)($viewerRow['is_premium'] ?? 0) === 1);
            $stmtPrem->close();
        }
    }

    require_once __DIR__ . '/../includes/account_data_helpers.php';
    // Deactivated accounts (deletion pending) drop out of the list.
    $suppActive = account_active_sql($mysqli);
    $suppActiveClause = $suppActive !== '' ? ' AND ' . $suppActive : '';
    $stmtSupp = $mysqli->prepare("SELECT id, username, display_name, discord_use_display_name, discord_global_name, discord_username, profile_updated_at, accent_color, avatar_ring_color FROM utenti WHERE is_premium = 1 $suppActiveClause ORDER BY id DESC LIMIT " . HOME_SUPPORTERS_LIMIT);
    if ($stmtSupp) {
        $stmtSupp->execute();
        $resSupp = $stmtSupp->get_result();
        while ($row = $resSupp->fetch_assoc()) {
            $supporters[] = $row;
        }
        $stmtSupp->close();
    }

    // La fila si ferma a HOME_SUPPORTERS_LIMIT facce, il conteggio no.
    $supportersTotal = count($supporters);
    $stmtSuppCount = $mysqli->prepare("SELECT COUNT(*) AS totale FROM utenti WHERE is_premium = 1 $suppActiveClause");
    if ($stmtSuppCount) {
        $stmtSuppCount->execute();
        $supportersTotal = max($supportersTotal, (int)($stmtSuppCount->get_result()->fetch_assoc()['totale'] ?? 0));
        $stmtSuppCount->close();
    }
}

require_once __DIR__ . '/../includes/home_slides.php';
$homeSlides = home_slides_load($mysqli ?? null, 'it');

function home_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$profileUrl = ($isLoggedIn && $currentUsername)
    ? '/u/' . rawurlencode(strtolower((string)$currentUsername))
    : 'accedi';

$ogDescription = 'Homepage di Cripsum™. Edit, meme, gambling, profili custom, tanti giochi e tanto gooning.';
$ogTitle = 'Cripsum™ — meme, edit, lootbox e profili della community';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/home'), '#');

// Questo file prepara i dati; la pagina la disegna includes/home_next.php,
// la stessa per le due lingue.
require_once __DIR__ . '/../includes/theme.php';
$homeLang = 'it';
require __DIR__ . '/../includes/home_next.php';
