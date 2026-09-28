<?php
/**
 * /it/ohpy (il team) e /it/ohpy/{player} (la pagina del player). La regola
 * in .htaccess passa lo slug come ?player=.
 *
 * Sono due pagine vere: si aprono anche da un link diretto e ognuna ha la
 * sua anteprima per i social. Passando dalla griglia a un player, o da un
 * player all'altro, esports.js le carica senza ricaricare tutto il sito:
 * cosi' la musica del player parte nello stesso clic (i browser fanno
 * partire l'audio solo dentro un gesto dell'utente) e continua senza
 * interruzioni. Con un link aperto da fuori il browser puo' bloccarla:
 * allora parte al primo tocco sulla pagina.
 *
 * Variabili attese: $esLang ('it' | 'en'), $mysqli.
 */

require_once __DIR__ . '/../esports.php';
require_once __DIR__ . '/../strings.php';

$S = esports_strings($esLang);
$esIsStaff = shop_is_staff();
$esPlayerSlug = strtolower(trim((string)($_GET['player'] ?? '')));

$esTeam = null;
if (esports_ready($mysqli)) {
    $esTeamRow = esports_team_row($mysqli);
    $esTeam = $esTeamRow ? esports_team_view($esTeamRow, $esLang) : null;
}

if (!$esTeam) {
    $pageTitle = 'Team OHPY';
    include __DIR__ . '/../partials/top.php';
    $stateIcon = 'fa-solid fa-crosshairs';
    $stateTitle = $S['soon_title'];
    $stateText = $S['soon_text'];
    $stateLink = ['href' => '/' . $esLang . '/home', 'label' => $S['go_home']];
    include __DIR__ . '/../partials/state.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

$esPlayers = array_map(
    static fn(array $row): array => esports_player_view($row, $esLang),
    esports_player_rows($mysqli, $esTeam['id'])
);
$esGroups = esports_group_players($esPlayers, $esTeam['show_ex'], $esIsStaff);

// Precedente e successivo seguono l'ordine in cui le card compaiono nella
// griglia: si scorrono i player come li vede chi guarda la pagina.
$esOrdered = array_merge($esGroups['lineup'], $esGroups['bench'], $esGroups['former'], $esGroups['hidden']);

$esTeamTitle = $esTeam['label'];
$bodyData = [
    'es-base' => $esTeam['url'],
    'es-copied' => $S['copied'],
    'es-copy-failed' => $S['copy_failed'],
    'es-tap' => $S['music_tap'],
];

/* ── Pagina del player ───────────────────────────────────────────────── */

if ($esPlayerSlug !== '') {
    $esPlayer = null;
    $esPosition = 0;
    foreach ($esOrdered as $esPosition => $candidate) {
        if ($candidate['slug'] === $esPlayerSlug) {
            $esPlayer = $candidate;
            break;
        }
    }

    if (!$esPlayer) {
        http_response_code(404);
        $pageTitle = $S['not_found_title'];
        $bodyStyle = $esTeam['style'];
        include __DIR__ . '/../partials/top.php';
        $stateIcon = 'fa-solid fa-user-slash';
        $stateTitle = $S['not_found_title'];
        $stateText = $S['not_found_text'];
        $stateLink = ['href' => $esTeam['url'], 'label' => $S['back_to_team']];
        include __DIR__ . '/../partials/state.php';
        include __DIR__ . '/../partials/bottom.php';
        return;
    }

    $esCount = count($esOrdered);
    $esPrev = $esCount > 1 ? $esOrdered[($esPosition - 1 + $esCount) % $esCount] : null;
    $esNext = $esCount > 1 ? $esOrdered[($esPosition + 1) % $esCount] : null;

    $pageTitle = $esPlayer['nickname'] . ' · ' . $esTeam['name'];
    $pageDescription = $esPlayer['tagline'] !== '' ? $esPlayer['tagline']
        : ($esPlayer['bio'] !== '' ? $esPlayer['bio'] : sprintf($S['player_fallback_desc'], $esPlayer['nickname'], $esPlayer['role_label'], $esTeam['label']));
    $pageImage = $esPlayer['photo'] !== '' ? $esPlayer['photo'] : ($esTeam['cover'] ?: $esTeam['logo']);
    $presence = ['title' => $esTeamTitle, 'state' => sprintf($S['presence_player'], $esPlayer['nickname'])];

    // Il colore del player, se ne ha uno, vale per tutta la sua pagina.
    $bodyStyle = esports_style(array_merge($esTeam['vars'], $esPlayer['accent_vars']));

    include __DIR__ . '/../partials/top.php';
    include __DIR__ . '/../partials/player.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

/* ── Pagina del team ─────────────────────────────────────────────────── */

$pageTitle = $esTeamTitle . ' · ' . $esTeam['game'];
$pageDescription = $esTeam['tagline'] !== '' ? $esTeam['tagline'] : $esTeam['description'];
$pageImage = $esTeam['cover'] ?: $esTeam['logo'];
$presence = ['title' => $esTeamTitle, 'state' => sprintf($S['presence_team'], $esTeam['label'])];
$bodyStyle = $esTeam['style'];

include __DIR__ . '/../partials/top.php';
include __DIR__ . '/../partials/roster.php';
include __DIR__ . '/../partials/bottom.php';
