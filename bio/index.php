<?php
declare(strict_types=1);

/**
 * bio.cripsum.com/: l'ingresso per chi non e' dentro (tavola 1), «La tua
 * pagina» per chi e' dentro (tavola 2). Vedi includes/bio_pages.php.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_pages.php';

cripsum_bio_only('');
checkBan($mysqli);
cripsum_bio_auto_login($mysqli);

$lang = bio_lang();
$t = static fn(string $key): string => bio_t($key, $lang);
$userId = (int)($_SESSION['user_id'] ?? 0);
$owner = $userId > 0 ? bio_owner($mysqli, $userId) : null;
$bioHost = (string)parse_url(cripsum_bio_url(), PHP_URL_HOST);

// I testi che bio.js mostra senza ricaricare la pagina.
$jsConfig = [
    'csrf' => csrf_token(),
    't' => [
        'copied' => $t('copied'),
        'saved' => $t('saved'),
        'failed' => $t('save_failed'),
        'checking' => $t('name_checking'),
        'free' => $t('name_free'),
        'taken' => $t('name_taken'),
        'taken_link' => $t('name_taken_link'),
        'reserved' => $t('name_reserved'),
        'invalid' => $t('name_invalid'),
        'yours' => $t('name_yours'),
        'vis_now' => [
            'public' => $t('vis_now_public'),
            'logged_in' => $t('vis_now_logged_in'),
            'friends' => $t('vis_now_friends'),
            'private' => $t('vis_now_private'),
        ],
    ],
];

if (!$owner) {
    // ── Ingresso ─────────────────────────────────────────────────────
    // Le ultime 4 della vetrina dello staff, completate dalle piu' viste; nel
    // telefono la pagina scelta dallo staff (bio_showcase()).
    $vetrina = bio_showcase($mysqli);
    $showcase = array_slice($vetrina['picks'], 0, 4);
    $showcaseTitle = $t($showcase ? 'showcase' : 'from_community');
    $hero = null;
    foreach ($vetrina['picks'] as $row) {
        if ((int)$row['id'] === $vetrina['hero_id']) {
            $hero = $row;
            break;
        }
    }
    if (count($showcase) < 4) {
        [$popular] = bio_explore_cards($mysqli, 'viste', '', 0, 1, 8);
        $shown = array_map(static fn(array $row): int => (int)$row['id'], $showcase);
        foreach ($popular as $row) {
            if (count($showcase) < 4 && !in_array((int)$row['id'], $shown, true)) {
                $showcase[] = $row;
            }
        }
    }
    $hero ??= $showcase[0] ?? null;
    $heroName = $hero ? cripsum_bio_profile_name($hero) : 'cripsum';

    // Senza JavaScript il nome si controlla qui (bio/prendi.php rimanda con ?nome=).
    $typed = trim((string)($_GET['nome'] ?? ''));
    $typedState = $typed !== '' ? bio_name_state($mysqli, $typed) : null;

    bio_page_open($t('meta_title'), $t('meta_desc'), $lang, 'bh-page-ingresso');
    ?>
    <section class="bh-hero">
        <div class="bh-hero__text">
            <p class="bh-kicker"><i class="fa-solid fa-link" aria-hidden="true"></i> <?= bio_h($bioHost) ?></p>
            <h1><?= bio_h($t('hero_1')) ?><br><span><?= bio_h($t('hero_2')) ?></span></h1>
            <p class="bh-lead"><?= bio_h($t('hero_lead')) ?></p>

            <form class="bh-claim" action="/prendi" method="get" data-bh-claim>
                <label class="bh-claim__field">
                    <span><?= bio_h($bioHost) ?>/</span>
                    <input type="text" name="nome" value="<?= bio_h($typed) ?>" placeholder="<?= bio_h($t('claim_ph')) ?>" aria-label="<?= bio_h($t('claim_label')) ?>"
                        maxlength="20" spellcheck="false" autocomplete="off" autocapitalize="off" required data-bh-claim-input>
                </label>
                <button type="submit" class="nx-btn nx-btn--primary" data-bh-claim-submit><?= bio_h($t('claim_btn')) ?></button>
            </form>
            <p class="bh-claim__state" data-bh-claim-state aria-live="polite"<?= $typedState ? '' : ' hidden' ?>>
                <?php if ($typedState): ?>
                    <?php $ok = $typedState['state'] === 'free'; ?>
                    <i class="bh-dot<?= $ok ? '' : ' bh-dot--no' ?>" aria-hidden="true"></i>
                    <span>
                        <?php if ($typedState['state'] === 'invalid'): ?>
                            <?= bio_h($t('name_invalid')) ?>
                        <?php else: ?>
                            <?= sprintf(bio_h($t('name_' . $typedState['state'])), '<b>' . bio_h($typed) . '</b>') ?>
                            <?php if ($typedState['state'] === 'taken'): ?>
                                <a href="<?= bio_h($typedState['url']) ?>"><?= bio_h($t('name_taken_link')) ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </p>

            <p class="bh-hero__alt"><?= bio_h($t('have_account')) ?> <a href="/accedi?return=%2F"><?= bio_h($t('sign_in')) ?></a></p>
        </div>

        <figure class="bh-hero__phone">
            <div class="bh-phone"><div class="bh-phone__screen" data-bh-screen>
                <iframe src="/<?= bio_h(rawurlencode($heroName)) ?>?preview_mode=1" title="<?= bio_h($bioHost . '/' . $heroName) ?>" tabindex="-1" aria-hidden="true" allow="autoplay 'none'" loading="lazy"></iframe>
            </div></div>
            <figcaption class="bh-phone__cap"><a href="/<?= bio_h(rawurlencode($heroName)) ?>"><?= bio_h($bioHost . '/' . $heroName) ?></a></figcaption>
        </figure>
    </section>

    <?php if ($showcase): ?>
        <section class="bh-sec">
            <header class="bh-sec__head">
                <h2><?= bio_h($showcaseTitle) ?></h2>
                <a class="bh-more" href="/esplora"><?= bio_h($t('explore_all')) ?><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            <ul class="bh-cards">
                <?php foreach ($showcase as $row) { bio_render_card($row, false, $lang); } ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="bh-sec">
        <header class="bh-sec__head"><h2><?= bio_h($t('features')) ?></h2></header>
        <ul class="bh-feats">
            <?php foreach ([['bg', 'fa-solid fa-palette'], ['music', 'fa-solid fa-music'], ['fx', 'fa-solid fa-wand-magic-sparkles'], ['links', 'fa-solid fa-link'], ['discord', 'fa-brands fa-discord'], ['badges', 'fa-solid fa-trophy']] as [$key, $icon]): ?>
                <li class="bh-feat">
                    <span class="bh-feat__ico"><i class="<?= $icon ?>" aria-hidden="true"></i></span>
                    <div><h3><?= bio_h($t('f_' . $key)) ?></h3><p><?= bio_h($t('f_' . $key . '_t')) ?></p></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
    bio_page_close($lang, $jsConfig);
    exit;
}

// ── La tua pagina ────────────────────────────────────────────────────
$name = cripsum_bio_profile_name($owner);
$pageUrl = cripsum_bio_url(rawurlencode($name));
$accent = bio_color($owner['accent_color'] ?? null);
$visibility = in_array($owner['profile_visibility'] ?? '', ['public', 'logged_in', 'friends', 'private'], true) ? $owner['profile_visibility'] : 'public';
$exploreReady = bio_explore_ready($mysqli);
$todo = bio_owner_todo($mysqli, $owner);
$todoDone = count(array_filter($todo, static fn(array $item): bool => $item[1]));
$isNew = !empty($owner['data_creazione']) && strtotime((string)$owner['data_creazione']) > time() - 86400;
[$editNumber, $editWord] = bio_days_ago($owner['profile_updated_at'] ?? null, $lang);
$requests = bio_pending_friend_requests($mysqli, $userId);
$dailyViews = bio_owner_daily_views($mysqli, $userId);
// La vetrina dell'ingresso, solo per lo staff: le pagine nel loro ordine,
// quella nel telefono, l'aggiunta per nome (api/bio/vetrina.php).
$staffShowcase = $exploreReady && profile_is_staff() ? bio_showcase($mysqli) : null;
$showcaseReady = $staffShowcase !== null && bio_showcase_ready($mysqli);
// L'editor sta su bio (bio/editor.php); l'area si apre col frammento.
$editorUrl = static fn(string $area): string => '/editor#' . $area;

// Il nome scelto da ospite prima di entrare: si offre solo se e' ancora libero
// e se non c'e' gia' un alias (bio/prendi.php, api/bio/alias.php).
$claim = (string)($_SESSION['bio_claim'] ?? '');
if ($claim !== '' && (trim((string)($owner['custom_alias'] ?? '')) !== '' || bio_name_state($mysqli, $claim, $userId)['state'] !== 'free')) {
    unset($_SESSION['bio_claim']);
    $claim = '';
}

bio_page_open($t('your_page') . ' — ' . $bioHost, $t('meta_desc'), $lang, 'bh-page-pannello');
?>
<header class="bh-me"<?= $accent ? ' style="--acc:' . bio_h($accent) . '"' : '' ?>>
    <img class="bh-me__pfp" src="<?= bio_h(profile_avatar_url($owner, 128)) ?>" alt="">
    <div>
        <h1><?= bio_h($t('your_page')) ?></h1>
        <p class="bh-me__link"><?= bio_h($bioHost) ?>/<b><?= bio_h($name) ?></b></p>
    </div>
    <div class="bh-me__actions">
        <button type="button" class="nx-btn nx-btn--outline nx-btn--sm" data-bh-copy="<?= bio_h($pageUrl) ?>"><i class="fa-regular fa-copy" aria-hidden="true"></i><?= bio_h($t('copy_link')) ?></button>
        <button type="button" class="nx-btn nx-btn--outline nx-btn--sm" data-bh-qr-open><i class="fa-solid fa-qrcode" aria-hidden="true"></i><?= bio_h($t('qr')) ?></button>
        <a class="nx-btn nx-btn--outline nx-btn--sm" href="/<?= bio_h(rawurlencode($name)) ?>"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><?= bio_h($t('open')) ?></a>
        <a class="nx-btn nx-btn--primary" href="<?= bio_h($editorUrl('profile')) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= bio_h($t('edit')) ?></a>
    </div>
</header>

<?php if ($isNew): ?>
    <p class="nx-note nx-note--ok bh-welcome"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><?= bio_h(sprintf($t('welcome'), $name)) ?></p>
<?php endif; ?>

<?php if ($claim !== ''): ?>
    <div class="bh-panel bh-claim-card" data-bh-claim-card>
        <div>
            <strong><?= bio_h(sprintf($t('claim_also'), $claim)) ?></strong>
            <span><?= bio_h($t('claim_also_t')) ?></span>
        </div>
        <button type="button" class="nx-btn nx-btn--primary" data-bh-claim-alias="<?= bio_h($claim) ?>"><?= bio_h($t('claim_btn')) ?></button>
    </div>
<?php endif; ?>

<div class="bh-dash">
    <div class="bh-dash__main">
        <?php if ($todoDone < count($todo)): ?>
            <section>
                <div class="bh-panel">
                    <div class="bh-todo-head"><h2 style="margin:0 !important"><?= bio_h($t('todo')) ?></h2><small><?= bio_h(sprintf($t('todo_of'), $todoDone, count($todo))) ?></small></div>
                    <div class="bh-todo-bar"><span style="width:<?= (int)round($todoDone / count($todo) * 100) ?>%"></span></div>
                    <ul class="bh-todo">
                        <?php foreach ($todo as [$key, $done, $area]): ?>
                            <li class="<?= $done ? 'is-done' : '' ?>">
                                <i class="<?= $done ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle' ?>" aria-hidden="true"></i>
                                <span><?= bio_h($t($key)) ?></span>
                                <?php if (!$done): ?>
                                    <a class="nx-btn nx-btn--soft nx-btn--sm" href="<?= bio_h($editorUrl($area)) ?>"><?= bio_h($t('todo_go')) ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <section>
            <h2><?= bio_h($t('who_sees')) ?></h2>
            <div class="bh-panel" data-bh-vis-panel>
                <div class="bh-vis">
                    <div class="nx-tabs" role="radiogroup" aria-label="<?= bio_h($t('who_sees')) ?>">
                        <?php foreach (['public', 'logged_in', 'friends', 'private'] as $option): ?>
                            <button type="button" class="nx-tab<?= $option === $visibility ? ' is-active' : '' ?>" role="radio" aria-checked="<?= $option === $visibility ? 'true' : 'false' ?>" data-bh-vis="<?= $option ?>"><?= bio_h($t('vis_' . $option)) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="bh-vis__now" data-bh-vis-now><?= bio_h($t('vis_now_' . $visibility)) ?></p>
                <?php if ($exploreReady): ?>
                    <div class="bh-line"></div>
                    <div class="bh-switch-row">
                        <div>
                            <strong id="bh-explore-label"><?= bio_h($t('explore_switch')) ?></strong>
                            <span data-bh-explore-note data-on="<?= bio_h($t('explore_switch_t')) ?>" data-off="<?= bio_h($t('explore_switch_off')) ?>"><?= bio_h($t($visibility === 'public' ? 'explore_switch_t' : 'explore_switch_off')) ?></span>
                        </div>
                        <label class="nx-switch"><input type="checkbox" aria-labelledby="bh-explore-label" data-bh-discoverable<?= (int)$owner['profile_discoverable'] === 1 ? ' checked' : '' ?>><i></i></label>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <h2><?= bio_h($t('areas')) ?></h2>
            <ul class="bh-areas">
                <?php foreach ([['profile', 'fa-user'], ['appearance', 'fa-palette'], ['effects', 'fa-wand-magic-sparkles'], ['music', 'fa-music'], ['sections', 'fa-layer-group'], ['settings', 'fa-gear']] as [$area, $icon]): ?>
                    <li class="bh-area"><a href="<?= bio_h($editorUrl($area)) ?>">
                        <span class="bh-area__ico"><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></span>
                        <strong><?= bio_h($t('a_' . $area)) ?></strong>
                        <span class="bh-area__desc"><?= bio_h($t('a_' . $area . '_t')) ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($dailyViews['state'] !== 'none'): ?>
            <section>
                <h2><?= bio_h($t('v_title')) ?></h2>
                <div class="bh-panel bh-views"><?php bio_render_views_chart($dailyViews, $lang); ?></div>
            </section>
        <?php endif; ?>

        <?php if ($staffShowcase !== null): ?>
            <section>
                <h2><?= bio_h($t('sv_title')) ?><span class="bh-tag"><?= bio_h($t('sv_staff')) ?></span></h2>
                <div class="bh-panel bh-picks" data-bh-picks>
                    <p class="bh-picks__lead"><?= bio_h($t('sv_lead')) ?></p>
                    <form class="bh-picks__add" data-bh-pick-add>
                        <input class="nx-input" type="text" name="name" placeholder="<?= bio_h($t('sv_add_ph')) ?>" aria-label="<?= bio_h($t('sv_add_label')) ?>"
                            maxlength="120" autocomplete="off" spellcheck="false" autocapitalize="off" required>
                        <button type="submit" class="nx-btn nx-btn--primary"><?= bio_h($t('sv_add')) ?></button>
                    </form>
                    <?php if ($staffShowcase['picks']): ?>
                        <fieldset class="bh-picks__list">
                            <legend class="bh-sr"><?= bio_h($t('sv_phone_label')) ?></legend>
                            <?php if ($showcaseReady): ?><p class="bh-sr" id="bh-move-hint"><?= bio_h($t('sv_move_hint')) ?></p><?php endif; ?>
                            <ul data-bh-pick-list>
                                <?php foreach ($staffShowcase['picks'] as $i => $row): ?>
                                    <?php
                                    $pickName = cripsum_bio_profile_name($row);
                                    $pickDisplay = trim((string)($row['display_name'] ?? '')) ?: (string)$row['username'];
                                    $isHero = (int)$row['id'] === $staffShowcase['hero_id'];
                                    ?>
                                    <li class="bh-pick<?= $i >= 4 ? ' is-extra' : '' ?>" data-bh-pick="<?= (int)$row['id'] ?>">
                                        <?php if ($showcaseReady): ?>
                                            <button type="button" class="bh-pick__grip" aria-label="<?= bio_h($t('sv_move') . ': ' . $pickDisplay) ?>" aria-describedby="bh-move-hint" title="<?= bio_h($t('sv_move_hint')) ?>" data-bh-grip>
                                                <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                        <img class="bh-pick__pfp" src="<?= bio_h(profile_avatar_url($row, 128)) ?>" alt="" loading="lazy" decoding="async" draggable="false">
                                        <a class="bh-pick__text" href="/<?= bio_h(rawurlencode($pickName)) ?>" draggable="false">
                                            <span class="bh-pick__name"><strong><?= bio_h($pickDisplay) ?></strong><?= bio_premium_gem($row) ?></span>
                                            <span class="bh-pick__url"><?= bio_h($bioHost . '/' . $pickName) ?><em class="bh-pick__extra"> · <?= bio_h($t('sv_only_explore')) ?></em></span>
                                        </a>
                                        <?php if ($showcaseReady): ?>
                                            <label class="bh-pick__phone">
                                                <input type="radio" name="bh-hero" value="<?= (int)$row['id'] ?>" aria-label="<?= bio_h($t('sv_phone') . ': ' . $pickDisplay) ?>" data-bh-hero<?= $isHero ? ' checked' : '' ?>>
                                                <i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><span aria-hidden="true"><?= bio_h($t('sv_phone')) ?></span>
                                            </label>
                                        <?php elseif ($isHero): ?>
                                            <span class="bh-pick__phone is-on"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><span><?= bio_h($t('sv_phone')) ?></span></span>
                                        <?php endif; ?>
                                        <button type="button" class="bh-pick__remove" data-bh-unpick="<?= (int)$row['id'] ?>" aria-label="<?= bio_h($t('feature_off') . ': ' . $pickDisplay) ?>">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </fieldset>
                        <p class="bh-small"><?= bio_h($t($showcaseReady ? 'sv_order_hint' : 'sv_install')) ?></p>
                    <?php else: ?>
                        <p class="bh-picks__empty"><?= bio_h($t('sv_empty')) ?></p>
                    <?php endif; ?>
                    <p class="bh-small"><?= sprintf(bio_h($t('sv_or_star')), '<a href="/esplora?vista=viste">' . bio_h($t('explore')) . '</a>') ?></p>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside class="bh-side">
        <div class="bh-phone"><div class="bh-phone__screen" data-bh-screen>
            <iframe src="/<?= bio_h(rawurlencode($name)) ?>?preview_mode=1" title="<?= bio_h($t('preview')) ?>" tabindex="-1" aria-hidden="true" allow="autoplay 'none'" loading="lazy"></iframe>
        </div></div>
        <dl class="bh-stats">
            <div class="bh-stat"><dt><?= bio_h($t('views')) ?></dt><dd><?= number_format((int)($owner['profile_views'] ?? 0), 0, ',', '.') ?></dd></div>
            <div class="bh-stat"><dt><?= bio_h($t('last_edit')) ?></dt><dd><?= bio_h($editNumber) ?><?php if ($editWord !== ''): ?> <small><?= bio_h($editWord) ?></small><?php endif; ?></dd></div>
        </dl>
        <?php if ($requests > 0): ?>
            <a class="bh-row" href="<?= bio_h(cripsum_main_url($lang . '/amici')) ?>">
                <span class="bh-row__ico"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span>
                <span class="bh-row__text"><strong><?= bio_h($requests === 1 ? $t('requests_1') : sprintf($t('requests_n'), $requests)) ?></strong><span><?= bio_h($t('requests_t')) ?></span></span>
                <i class="fa-solid fa-chevron-right bh-row__go" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
        <a class="bh-row" href="/esplora">
            <span class="bh-row__ico"><i class="fa-solid fa-compass" aria-hidden="true"></i></span>
            <span class="bh-row__text"><strong><?= bio_h($t('explore_row')) ?></strong><span><?= bio_h($t('explore_row_t')) ?></span></span>
            <i class="fa-solid fa-chevron-right bh-row__go" aria-hidden="true"></i>
        </a>
        <?php if ((int)($owner['is_premium'] ?? 0) !== 1): ?>
            <a class="bh-row" href="<?= bio_h(cripsum_main_url($lang . '/checkout-premium')) ?>">
                <span class="bh-row__ico"><img src="/img/premium.svg" alt=""></span>
                <span class="bh-row__text"><strong><?= bio_h($t('premium')) ?></strong><span><?= bio_h($t('premium_t')) ?></span></span>
                <i class="fa-solid fa-chevron-right bh-row__go" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
        <p class="bh-small"><?= sprintf(bio_h($t('account_note')), '<a href="' . bio_h(cripsum_main_url($lang . '/impostazioni')) . '">' . bio_h($t('account_link')) . '</a>') ?></p>
    </aside>
</div>

<dialog class="bh-qr" data-bh-qr>
    <h2><?= bio_h($t('qr_title')) ?></h2>
    <img src="" data-src="/api/profile_qr.php?url=<?= bio_h(rawurlencode($pageUrl)) ?>" alt="">
    <p><?= bio_h($pageUrl) ?></p>
    <button type="button" class="nx-btn nx-btn--outline" data-bh-qr-close><?= bio_h($t('close')) ?></button>
</dialog>
<?php
bio_page_close($lang, $jsConfig);
