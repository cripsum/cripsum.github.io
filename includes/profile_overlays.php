<?php
/**
 * I tre pannelli che si aprono dal menu del profilo: navigazione, ricerca
 * utenti e segnalazione.
 *
 * Il markup mancava del tutto. I pulsanti c'erano, `assets/js/profile.js` ci
 * attaccava i gestori, il CSS era gia' scritto per intero — ma gli elementi
 * `#profileNavOverlay`, `#profileSearchOverlay` e `#profileReportModal` non
 * esistevano in nessuna pagina. `openOverlay(null)` esce subito senza dire
 * niente, quindi cliccando non succedeva nulla e in console non compariva
 * nessun errore.
 *
 * Sta in un file solo perche' lo usano sia profile.php sia bio.php: due copie
 * dello stesso pannello si sarebbero disallineate al primo ritocco.
 *
 * Gli identificatori qui sotto sono un contratto con profile.js: i nomi delle
 * classi `js-*`, gli id e i `name` dei campi del form vanno cambiati in tutti
 * e due i posti insieme.
 */

if (!isset($lang) || !in_array($lang, ['it', 'en'], true)) {
    $lang = 'it';
}

$po_h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Le stesse voci del menu principale del sito, raggruppate come li'. */
$po_sezioni = [
    [
        'titolo' => 'Memes',
        'voci' => [
            ['fa-fire', 'Shitpost', "/$lang/shitpost"],
            ['fa-star', 'Top Rimasti', "/$lang/rimasti"],
            ['fa-brands fa-tiktok', 'TikTokPedia', "/$lang/tiktokpedia"],
            ['fa-book', 'CripsumPedia', "/$lang/cripsumpedia/home"],
        ],
    ],
    [
        'titolo' => 'Giochi',
        'voci' => [
            ['fa-box-open', 'Lootbox', "/$lang/lootbox"],
            ['fa-dice', 'Gambling', "/$lang/gambling"],
            ['fa-compact-disc', 'Animespot', "/$lang/animespot"],
            ['fa-headphones-simple', 'Pullspot', "/$lang/pullspot"],
        ],
    ],
    [
        'titolo' => 'Profilo',
        'voci' => [
            ['fa-trophy', 'Achievements', "/$lang/achievements"],
            ['fa-bullseye', 'Missioni', "/$lang/missions"],
            ['fa-box', 'Inventario', "/$lang/inventario"],
            ['fa-gear', 'Impostazioni', "/$lang/impostazioni"],
        ],
    ],
    [
        'titolo' => 'Altro',
        'voci' => [
            ['fa-video', 'Edits', "/$lang/edits"],
            ['fa-download', 'Downloads', "/$lang/download"],
            ['fa-shirt', 'Merch', "/$lang/merch"],
            ['fa-comments', 'Chat globale', "/$lang/global-chat"],
        ],
    ],
];

/** Gli stessi valori accettati da api/report_profile.php. */
$po_motivi = [
    ['spam', 'fa-bullhorn', 'Spam / Advertising'],
    ['inappropriate', 'fa-triangle-exclamation', 'Inappropriate / NSFW'],
    ['harassment', 'fa-user-slash', 'Harassment / Bullying'],
    ['other', 'fa-ellipsis', 'Something else'],
];
?>

<!-- Navigazione -->
<div class="profile-nav-overlay" id="profileNavOverlay" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Navigation">
    <div class="profile-nav-overlay-backdrop"></div>

    <div class="profile-nav-overlay-container">
        <div class="profile-nav-overlay-header">
            <div class="profile-nav-overlay-logo">
                <img src="/img/Susremaster.png" alt="" width="36" height="36">
                <span>Cripsum&trade;</span>
            </div>
            <button type="button" class="profile-nav-overlay-close-btn js-close-navigation" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="profile-nav-overlay-content">
            <div class="profile-nav-grid">
                <?php foreach ($po_sezioni as $sezione): ?>
                    <div class="profile-nav-section">
                        <h3><?php echo $po_h($sezione['titolo']); ?></h3>
                        <div class="profile-nav-links">
                            <?php foreach ($sezione['voci'] as [$icona, $etichetta, $href]): ?>
                                <a href="<?php echo $po_h($href); ?>">
                                    <i class="<?php echo str_starts_with($icona, 'fa-brands') ? $po_h($icona) : 'fa-solid ' . $po_h($icona); ?>"></i>
                                    <span><?php echo $po_h($etichetta); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ricerca utenti -->
<div class="profile-nav-overlay profile-search-overlay" id="profileSearchOverlay" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Search users">
    <div class="profile-nav-overlay-backdrop"></div>

    <div class="profile-nav-overlay-container">
        <div class="profile-nav-overlay-header">
            <div class="profile-nav-overlay-logo">
                <span>Search users</span>
            </div>
            <button type="button" class="profile-nav-overlay-close-btn js-close-search" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="profile-nav-overlay-content">
            <div class="profile-search-input-wrap">
                <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
                <input type="search" id="profileSearchInput" autocomplete="off" spellcheck="false"
                    placeholder="<?php echo $lang === 'it' ? 'Cerca un utente...' : 'Search for a user...'; ?>"
                    aria-label="Search users">
                <button type="button" id="profileSearchClear" style="display:none;" aria-label="Clear">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="profile-search-results" id="profileSearchResults">
                <div class="profile-search-status">
                    <?php echo $lang === 'it'
                        ? 'Digita almeno 2 caratteri per iniziare...'
                        : 'Type at least 2 characters to start...'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Segnalazione profilo -->
<div class="profile-report-modal" id="profileReportModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="profileReportTitle">
    <div class="profile-report-backdrop"></div>

    <div class="profile-report-card">
        <button type="button" class="profile-report-close js-close-report" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="profile-report-header">
            <span class="profile-report-title-icon"><i class="fa-solid fa-flag"></i></span>
            <?php /* Titolo e sottotitolo in un contenitore solo: l'intestazione
                     e' una riga flex, e da fratelli finivano affiancati. */ ?>
            <div>
                <h3 id="profileReportTitle">Report profile</h3>
                <p>Tell us what's wrong. Reports go straight to the staff and stay anonymous.</p>
            </div>
        </div>

        <?php /* Nascosto finche' il JS non ci scrive chi si sta segnalando. */ ?>
        <div class="profile-report-user-badge" id="profileReportTargetBadge" style="display:none;">
            <i class="fa-solid fa-user"></i>
            <strong id="profileReportTargetName"></strong>
        </div>

        <form class="profile-report-form" id="profileReportForm" novalidate>
            <input type="hidden" name="reported_user_id" value="">

            <fieldset class="profile-report-reasons">
                <legend>Reason</legend>
                <div class="profile-report-options">
                    <?php foreach ($po_motivi as [$valore, $icona, $etichetta]): ?>
                        <label class="profile-report-option">
                            <input type="radio" name="report_reason" value="<?php echo $po_h($valore); ?>">
                            <span><i class="fa-solid <?php echo $po_h($icona); ?>"></i> <?php echo $po_h($etichetta); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="profile-report-detail-group">
                <label class="detail-label" for="profileReportDetail">Details <span>(optional)</span></label>
                <div class="profile-report-textarea-wrap">
                    <textarea id="profileReportDetail" name="detail" rows="4" maxlength="500"
                        placeholder="Anything that helps us understand..."></textarea>
                    <span class="detail-char-count"><span id="profileReportCharCount">0</span>/500</span>
                </div>
            </div>

            <div class="profile-report-actions">
                <button type="button" class="profile-report-btn-cancel js-close-report">Cancel</button>
                <button type="submit" class="profile-report-btn-submit" id="profileReportSubmitBtn">
                    <i class="fa-solid fa-paper-plane"></i> <span>Send report</span>
                </button>
            </div>
        </form>
    </div>
</div>
