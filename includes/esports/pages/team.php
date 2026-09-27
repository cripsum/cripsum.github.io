<?php
/**
 * /it/ohpy (il team) e /it/ohpy/{player} (la stessa pagina con la scheda
 * del player gia' aperta). La regola in .htaccess passa lo slug come
 * ?player=.
 *
 * Tutte le schede stanno nella pagina come <dialog>: cliccando una card si
 * apre la sua senza ricaricare, e la musica del player parte subito perche'
 * il clic e' un gesto dell'utente, che il browser esige per l'audio. Con un
 * link aperto da fuori il browser puo' bloccarla: esports.js la fa partire
 * al primo tocco sulla pagina.
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

// Le schede seguono l'ordine in cui le card compaiono: le frecce e lo
// swipe scorrono i player come li vede chi guarda la pagina.
$esOrdered = array_merge($esGroups['lineup'], $esGroups['bench'], $esGroups['former'], $esGroups['hidden']);

$esOpen = null;
if ($esPlayerSlug !== '') {
    foreach ($esOrdered as $candidate) {
        if ($candidate['slug'] === $esPlayerSlug) {
            $esOpen = $candidate;
            break;
        }
    }

    if (!$esOpen) {
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
}

$esTeamTitle = 'Team ' . $esTeam['name'];
$esPresenceTeam = sprintf($S['presence_team'], $esTeam['name']);

if ($esOpen) {
    $pageTitle = $esOpen['nickname'] . ' · ' . $esTeam['name'];
    $pageDescription = $esOpen['tagline'] !== '' ? $esOpen['tagline']
        : ($esOpen['bio'] !== '' ? $esOpen['bio'] : sprintf($S['player_fallback_desc'], $esOpen['nickname'], $esOpen['role_label'], $esTeam['name']));
    $pageImage = $esOpen['photo'] !== '' ? $esOpen['photo'] : ($esTeam['cover'] ?: $esTeam['logo']);
    $presence = ['title' => $esTeamTitle, 'state' => sprintf($S['presence_player'], $esOpen['nickname'])];
} else {
    $pageTitle = $esTeamTitle . ' · ' . $esTeam['game'];
    $pageDescription = $esTeam['tagline'] !== '' ? $esTeam['tagline'] : $esTeam['description'];
    $pageImage = $esTeam['cover'] ?: $esTeam['logo'];
    $presence = ['title' => $esTeamTitle, 'state' => $esPresenceTeam];
}

$bodyStyle = $esTeam['style'];
$bodyData = [
    'es-base' => $esTeam['url'],
    'es-open' => $esOpen['slug'] ?? '',
    'es-title' => 'Cripsum™ - ' . $esTeamTitle . ' · ' . $esTeam['game'],
    'es-presence' => $esPresenceTeam,
    'es-copied' => $S['copied'],
    'es-copy-failed' => $S['copy_failed'],
];

include __DIR__ . '/../partials/top.php';
?>
<main class="es-shell" id="es-main">
    <section class="es-hero<?php echo $esTeam['cover'] !== '' ? ' has-cover' : ''; ?>" aria-labelledby="es-team-title">
        <?php if ($esTeam['cover'] !== ''): ?>
            <img class="es-hero__cover" src="<?php echo shop_h($esTeam['cover']); ?>" alt="" fetchpriority="high" decoding="async">
        <?php endif; ?>
        <div class="es-hero__grid" aria-hidden="true"></div>

        <div class="es-hero__content">
            <?php if ($esTeam['logo'] !== ''): ?>
                <img class="es-hero__logo" src="<?php echo shop_h($esTeam['logo']); ?>" alt="<?php echo shop_h('Logo ' . $esTeam['name']); ?>" width="96" height="96" decoding="async">
            <?php endif; ?>
            <h1 class="es-hero__title" id="es-team-title"><?php echo shop_h($esTeam['name']); ?></h1>
            <?php if ($esTeam['tagline'] !== ''): ?>
                <p class="es-hero__lead"><?php echo shop_h($esTeam['tagline']); ?></p>
            <?php endif; ?>
            <?php if ($esTeam['description'] !== ''): ?>
                <p class="es-hero__text"><?php echo shop_linkify($esTeam['description']); ?></p>
            <?php endif; ?>

            <?php if (($esTeam['link_url'] !== '' && $esTeam['link_text'] !== '') || $esTeam['socials']): ?>
                <div class="es-hero__actions">
                    <?php if ($esTeam['link_url'] !== '' && $esTeam['link_text'] !== ''): ?>
                        <?php $esExternal = (bool)preg_match('~^https?://~i', $esTeam['link_url']); ?>
                        <a class="es-btn es-btn--primary" href="<?php echo shop_h($esTeam['link_url']); ?>" <?php echo $esExternal ? 'target="_blank" rel="noopener"' : ''; ?>>
                            <?php if (stripos($esTeam['link_url'], 'discord') !== false): ?><i class="fa-brands fa-discord" aria-hidden="true"></i><?php endif; ?>
                            <?php echo shop_h($esTeam['link_text']); ?>
                        </a>
                    <?php endif; ?>
                    <?php foreach ($esTeam['socials'] as $esSocial): ?>
                        <a class="es-icon-btn" href="<?php echo shop_h($esSocial['url']); ?>" target="_blank" rel="noopener" title="<?php echo shop_h($esSocial['label']); ?>">
                            <i class="<?php echo shop_h($esSocial['icon']); ?>" aria-hidden="true"></i>
                            <span class="visually-hidden"><?php echo shop_h($esSocial['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$esOrdered): ?>
        <section class="es-panel es-empty">
            <i class="fa-solid fa-crosshairs" aria-hidden="true"></i>
            <h2><?php echo shop_h($S['empty_title']); ?></h2>
            <p><?php echo shop_h($S['empty_text']); ?></p>
        </section>
    <?php endif; ?>

    <?php if ($esGroups['lineup']): ?>
        <section class="es-section" aria-labelledby="es-lineup-title">
            <h2 class="es-section__title" id="es-lineup-title"><?php echo shop_h($S['lineup']); ?></h2>
            <ul class="es-grid es-grid--lineup" role="list">
                <?php foreach ($esGroups['lineup'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'big'; include __DIR__ . '/../partials/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($esGroups['bench']): ?>
        <section class="es-section" aria-labelledby="es-bench-title">
            <h2 class="es-section__title" id="es-bench-title"><?php echo shop_h($S['bench']); ?></h2>
            <ul class="es-grid es-grid--small" role="list">
                <?php foreach ($esGroups['bench'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'small'; include __DIR__ . '/../partials/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($esTeam['palmares']): ?>
        <section class="es-section" aria-labelledby="es-palmares-title">
            <h2 class="es-section__title" id="es-palmares-title"><?php echo shop_h($S['palmares']); ?></h2>
            <ol class="es-palmares" role="list">
                <?php foreach ($esTeam['palmares'] as $esEntry): ?>
                    <li class="es-palmares__item<?php echo $esEntry['medal'] ? ' is-' . $esEntry['medal'] : ''; ?>">
                        <span class="es-palmares__medal" aria-hidden="true"><i class="fa-solid <?php echo $esEntry['medal'] ? 'fa-trophy' : 'fa-flag-checkered'; ?>"></i></span>
                        <span class="es-palmares__body">
                            <?php if ($esEntry['link'] !== ''): ?>
                                <a class="es-palmares__name" href="<?php echo shop_h($esEntry['link']); ?>" target="_blank" rel="noopener"><?php echo shop_h($esEntry['tournament']); ?> <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            <?php else: ?>
                                <strong class="es-palmares__name"><?php echo shop_h($esEntry['tournament']); ?></strong>
                            <?php endif; ?>
                            <?php if ($esEntry['date'] !== ''): ?>
                                <?php if ($esEntry['date_iso'] !== ''): ?>
                                    <time datetime="<?php echo shop_h($esEntry['date_iso']); ?>"><?php echo shop_h($esEntry['date']); ?></time>
                                <?php else: ?>
                                    <span><?php echo shop_h($esEntry['date']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($esEntry['placement'] !== ''): ?>
                            <span class="es-palmares__place"><?php echo shop_h($esEntry['placement']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($esGroups['former']): ?>
        <details class="es-section es-former"<?php echo $esOpen && $esOpen['state'] === 'ex' ? ' open' : ''; ?>>
            <summary class="es-section__title">
                <?php echo shop_h($S['former']); ?> <span class="es-former__count"><?php echo count($esGroups['former']); ?></span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </summary>
            <ul class="es-grid es-grid--small" role="list">
                <?php foreach ($esGroups['former'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'small'; include __DIR__ . '/../partials/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <?php if ($esGroups['hidden']): ?>
        <section class="es-section es-section--staff" aria-labelledby="es-hidden-title">
            <h2 class="es-section__title" id="es-hidden-title"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> <?php echo shop_h($S['hidden_group']); ?></h2>
            <ul class="es-grid es-grid--small" role="list">
                <?php foreach ($esGroups['hidden'] as $esIndex => $p): ?>
                    <li style="--i: <?php echo (int)$esIndex; ?>"><?php $esCardSize = 'small'; include __DIR__ . '/../partials/card.php'; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>

<?php
$esCount = count($esOrdered);
foreach ($esOrdered as $esIndex => $p) {
    $esPrev = $esCount > 1 ? $esOrdered[($esIndex - 1 + $esCount) % $esCount] : null;
    $esNext = $esCount > 1 ? $esOrdered[($esIndex + 1) % $esCount] : null;
    $esIsOpen = $esOpen && $esOpen['slug'] === $p['slug'];
    include __DIR__ . '/../partials/sheet.php';
}

include __DIR__ . '/../partials/bottom.php';
