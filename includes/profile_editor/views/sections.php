<?php
/**
 * Area "Sezioni": le sezioni del profilo nell'ordine in cui compaiono.
 *
 * Qui stanno insieme ordine, visibilita', intestazione e contenuto di ogni
 * sezione: prima erano tre schede diverse in due aree diverse.
 */

$badgesDisplay = (string)($profile['profile_badges_display'] ?? 'both');
$badgesVisible = $pflag('profile_show_badges');
// "Nascondi completamente" era un doppione dell'occhio della sezione.
if ($badgesDisplay === 'none') {
    $badgesDisplay = 'both';
    $badgesVisible = false;
}

$sectionItemsLabel = [
    'links' => $tt('Aggiungi link', 'Add link'),
    'embeds' => $tt('Aggiungi embed', 'Add embed'),
    'projects' => $tt('Aggiungi progetto', 'Add project'),
    'contents' => $tt('Aggiungi contenuto', 'Add content'),
    'blocks' => $tt('Aggiungi blocco', 'Add block'),
];
$sectionDescriptions = [
    'links' => $tt('Pulsanti grandi verso i tuoi siti.', 'Big buttons pointing to your sites.'),
    'embeds' => $tt('Playlist Spotify, video YouTube e widget.', 'Spotify playlists, YouTube videos and widgets.'),
    'stats' => $tt('Tempo sul sito, collezione, giochi e altro: scegli cosa mostrare.', 'Time on site, collection, games and more: choose what to show.'),
    'projects' => $tt('Una vetrina dei tuoi progetti.', 'A showcase of your projects.'),
    'blocks' => $tt('Testo, immagini o video, come preferisci.', 'Text, images or video, however you like.'),
    'contents' => $tt('Edit, video e post da mettere in mostra.', 'Edits, videos and posts to show off.'),
    'characters' => $tt('I personaggi del tuo inventario, fino a 12.', 'Characters from your inventory, up to 12.'),
    'badges' => $tt('Scegli quali badge mostrare e in che ordine.', 'Choose which badges to show and in what order.'),
    'activity' => $tt('Le ultime cose che hai fatto sul sito.', 'The latest things you did on the site.'),
];
?>
<div class="pe-sections" id="peSections">
    <?php foreach ($sectionsOrder as $sectionKey):
        $section = $catalog['sections'][$sectionKey];
        $visible = $sectionKey === 'badges' ? $badgesVisible : $pflag($section['toggle']);
        $config = is_array($sectionsConfig[$sectionKey] ?? null) ? $sectionsConfig[$sectionKey] : [];
        $headerLocked = !$isPremium;
    ?>
        <article class="pe-section" data-section="<?php echo $sectionKey; ?>" data-search="<?php echo pe_h($section['label'] . ' ' . $sectionDescriptions[$sectionKey]); ?>" id="sec-<?php echo $sectionKey; ?>">
            <header class="pe-section-head">
                <span class="pe-drag" title="<?php echo pe_h($tt('Trascina per spostare', 'Drag to move')); ?>"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
                <button type="button" class="pe-section-toggle" aria-expanded="false" aria-controls="sec-body-<?php echo $sectionKey; ?>">
                    <span class="pe-section-icon"><i class="<?php echo pe_h($section['icon']); ?>" aria-hidden="true"></i></span>
                    <span class="pe-section-text">
                        <strong><?php echo pe_h($section['label']); ?></strong>
                        <small data-section-summary="<?php echo $sectionKey; ?>"><?php echo pe_h($sectionDescriptions[$sectionKey]); ?></small>
                    </span>
                    <i class="fa-solid fa-chevron-down pe-chevron" aria-hidden="true"></i>
                </button>
                <label class="pe-eye" title="<?php echo pe_h($tt('Mostra o nascondi sul profilo', 'Show or hide on your profile')); ?>">
                    <input type="hidden" name="<?php echo $section['toggle']; ?>" value="0">
                    <input type="checkbox" name="<?php echo $section['toggle']; ?>" value="1" <?php echo $visible ? 'checked' : ''; ?>>
                    <i class="fa-solid fa-eye pe-eye-on" aria-hidden="true"></i>
                    <i class="fa-solid fa-eye-slash pe-eye-off" aria-hidden="true"></i>
                    <span class="visually-hidden"><?php echo pe_h($tt('Visibile sul profilo', 'Visible on profile')); ?></span>
                </label>
            </header>

            <div class="pe-section-body" id="sec-body-<?php echo $sectionKey; ?>" hidden>
                <?php if (isset($sectionItemsLabel[$sectionKey])): ?>
                    <div class="pe-items" data-items="<?php echo $sectionKey; ?>"></div>
                    <button type="button" class="pe-add" data-add-item="<?php echo $sectionKey; ?>">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i><span><?php echo pe_h($sectionItemsLabel[$sectionKey]); ?></span>
                        <span class="pe-add-limit" data-limit-for="<?php echo $sectionKey; ?>"></span>
                    </button>
                <?php endif; ?>

                <?php if ($sectionKey === 'stats'): ?>
                    <div class="pe-stats" id="peStats">
                        <p class="pe-help pe-stats-count" id="peStatsCount"></p>
                        <div class="pe-stats-chosen" id="peStatsChosen"></div>
                        <h4 class="pe-subhead"><?php echo pe_h($tt('Tutte le statistiche', 'All stats')); ?></h4>
                        <p class="pe-help"><?php echo pe_h($tt('Accanto a ognuna c\'è il tuo valore di adesso. Si aggiornano da sole.', 'Each one shows your current value. They update by themselves.')); ?></p>
                        <div class="pe-stats-catalog" id="peStatsCatalog"></div>
                    </div>
                <?php endif; ?>

                <?php if ($sectionKey === 'activity'): ?>
                    <p class="pe-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><?php echo pe_h($sectionDescriptions[$sectionKey]); ?></p>
                <?php endif; ?>

                <?php if ($sectionKey === 'characters'): ?>
                    <?php if ($inventoryCharacters): ?>
                        <div class="pe-characters" id="peCharacters">
                            <div class="pe-input-icon">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" class="pe-input" id="peCharacterSearch" placeholder="<?php echo pe_h($tt('Cerca nell\'inventario…', 'Search your inventory…')); ?>" autocomplete="off">
                            </div>
                            <p class="pe-help" id="peCharacterCount"></p>
                            <div class="pe-character-grid" id="peCharacterGrid"></div>
                            <h4 class="pe-subhead"><?php echo pe_h($tt('Ordine sul profilo', 'Order on your profile')); ?></h4>
                            <div class="pe-character-order" id="peCharacterOrder"></div>
                        </div>
                    <?php else: ?>
                        <div class="pe-empty">
                            <i class="fa-solid fa-user-astronaut" aria-hidden="true"></i>
                            <strong><?php echo pe_h($tt('Nessun personaggio nell\'inventario', 'No characters in your inventory')); ?></strong>
                            <p><?php echo pe_h($tt('Apri qualche lootbox e torna qui per mostrarli.', 'Open some lootboxes and come back to show them off.')); ?></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($sectionKey === 'badges'): ?>
                    <div class="pe-badges" id="peBadges"></div>
                    <?php pe_choice('profile_badges_display', $badgesDisplay, [
                        ['value' => 'both', 'label' => $tt('Ovunque', 'Everywhere')],
                        ['value' => 'card_only', 'label' => $tt('Sotto il nome', 'Under the name')],
                        ['value' => 'tab_only', 'label' => $tt('Solo sezione', 'Section only')],
                    ], ['label' => $tt('Dove mostrarli', 'Where to show them'), 'keywords' => 'badge posizione']); ?>
                    <?php pe_choice('profile_badges_position', (string)($profile['profile_badges_position'] ?? 'below_bio'), [
                        ['value' => 'right_of_name', 'label' => $tt('Accanto al nome', 'Next to name')],
                        ['value' => 'below_username', 'label' => $tt('Sotto lo username', 'Under username')],
                        ['value' => 'below_bio', 'label' => $tt('Sotto la bio', 'Under bio')],
                    ], ['label' => $tt('Posizione nella card', 'Position in the card'), 'show_if' => 'profile_badges_display=both|card_only']); ?>
                    <?php pe_slider('profile_badge_size', $style['badge_size'], ['label' => $tt('Dimensione dei badge nella card', 'Badge size in the card'), 'min' => 16, 'max' => 60, 'format' => 'px', 'default' => 24, 'show_if' => 'profile_badges_display=both|card_only']); ?>
                <?php endif; ?>

                <?php if (!in_array($sectionKey, ['stats'], true)): ?>
                    <details class="pe-section-header-settings<?php echo $headerLocked ? ' is-locked' : ''; ?>" <?php echo $headerLocked ? 'data-premium-lock="1"' : ''; ?>>
                        <summary>
                            <i class="fa-solid fa-heading" aria-hidden="true"></i><?php echo pe_h($tt('Titolo e icona della sezione', 'Section title and icon')); ?>
                            <?php echo pe_premium_chip(); ?>
                        </summary>
                        <div class="pe-section-header-fields" data-section-config="<?php echo $sectionKey; ?>">
                            <div class="pe-field">
                                <div class="pe-field-label"><label for="sec-title-<?php echo $sectionKey; ?>"><?php echo pe_h($tt('Titolo', 'Title')); ?></label></div>
                                <input class="pe-input" type="text" id="sec-title-<?php echo $sectionKey; ?>" data-config="title" maxlength="80" value="<?php echo pe_h($config['title'] ?? ''); ?>" placeholder="<?php echo pe_h($section['label']); ?>" <?php echo $headerLocked ? 'disabled' : ''; ?>>
                            </div>
                            <div class="pe-field">
                                <div class="pe-field-label"><span class="pe-field-title"><?php echo pe_h($tt('Icona', 'Icon')); ?></span></div>
                                <?php echo pe_icon_input('data-config="icon"' . ($headerLocked ? ' disabled' : ''), (string)($config['icon'] ?? ''), $section['icon']); ?>
                            </div>
                            <label class="pe-toggle">
                                <span class="pe-toggle-text"><span class="pe-toggle-title"><?php echo pe_h($tt('Nascondi il titolo', 'Hide the title')); ?></span><small><?php echo pe_h($tt('La sezione resta, senza intestazione.', 'The section stays, without a heading.')); ?></small></span>
                                <input class="pe-switch-input" type="checkbox" role="switch" data-config="hidden" <?php echo !empty($config['hidden']) ? 'checked' : ''; ?> <?php echo $headerLocked ? 'disabled' : ''; ?>>
                                <span class="pe-switch" aria-hidden="true"></span>
                            </label>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
