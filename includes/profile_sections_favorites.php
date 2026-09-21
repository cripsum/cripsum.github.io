<?php

/**
 * Le quattro sezioni dei preferiti del profilo: giochi, anime/serie/film,
 * canzoni e libri/manga/light novel.
 *
 * Stanno qui e non in profile.php perche' quel file e' gia' lungo 2.000 righe
 * e le nove sezioni di prima sono un unico blocco di ob_start(): quattro
 * sezioni in piu' scritte li' dentro erano altre duecento righe uguali fra
 * loro.
 *
 * Il contenuto arriva da profile_list_favorites() e la scheda e' sempre la
 * stessa: copertina, titolo, sottotitolo. Cambiano solo il titolo della
 * sezione e l'icona.
 */

/** Titolo e icona di ogni sezione, nelle due lingue. */
function profile_favorites_meta(string $lang = 'it'): array
{
    $it = $lang !== 'en';
    return [
        'game' => [
            'section' => 'fav_games',
            'icon' => 'fa-solid fa-gamepad',
            'title' => $it ? 'Giochi preferiti' : 'Favourite games',
        ],
        'watch' => [
            'section' => 'fav_watch',
            'icon' => 'fa-solid fa-clapperboard',
            'title' => $it ? 'Anime, serie e film' : 'Anime, series and films',
        ],
        'music' => [
            'section' => 'fav_music',
            'icon' => 'fa-solid fa-headphones',
            'title' => $it ? 'Canzoni preferite' : 'Favourite songs',
        ],
        'read' => [
            'section' => 'fav_read',
            'icon' => 'fa-solid fa-book-open',
            'title' => $it ? 'Libri, manga e light novel' : 'Books, manga and light novels',
        ],
    ];
}

/**
 * Una sezione di preferiti, gia' pronta da mettere in $sectionsHtml.
 *
 * Restituisce '' quando non c'e' niente da mostrare, com'e' per tutte le
 * altre sezioni: cosi' una sezione vuota non lascia un riquadro spoglio.
 */
function profile_render_favorites_section(string $kind, array $items, string $tiltAttrs, string $lang = 'it'): string
{
    $meta = profile_favorites_meta($lang)[$kind] ?? null;
    if ($meta === null || !$items) {
        return '';
    }

    $sectionKey = $meta['section'];
    $title = profile_get_section_title($sectionKey, $meta['title']);

    ob_start();
?>
    <section class="bio-card profile-favorites-section js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="<?php echo profile_h($sectionKey); ?>" data-section-title="<?php echo profile_h($title); ?>">
        <?php profile_render_section_heading($meta['icon'], $meta['title'], null, $sectionKey); ?>
        <div class="profile-favorites-grid" data-count="<?php echo count($items); ?>" data-section-items>
            <?php foreach ($items as $item): ?>
                <?php
                $itemTitle = (string)($item['title'] ?? '');
                $subtitle = trim((string)($item['subtitle'] ?? ''));
                $itemMeta = trim((string)($item['meta'] ?? ''));
                $image = trim((string)($item['image_url'] ?? ''));
                $url = trim((string)($item['url'] ?? ''));
                // Senza link la scheda resta una scheda: un <a> senza meta
                // manda in cima alla pagina a chi ci clicca sopra.
                $tag = $url !== '' ? 'a' : 'article';
                ?>
                <<?php echo $tag; ?> class="profile-favorite-card"
                    <?php if ($url !== ''): ?>href="<?php echo profile_h($url); ?>" target="_blank" rel="noopener noreferrer"<?php endif; ?>
                    title="<?php echo profile_h($itemTitle); ?>">
                    <span class="profile-favorite-art<?php echo $image === '' ? ' is-empty' : ''; ?>">
                        <?php if ($image !== ''): ?>
                            <img src="<?php echo profile_h($image); ?>" alt="" loading="lazy" decoding="async"
                                onerror="this.parentElement.classList.add('is-empty'); this.remove();">
                        <?php else: ?>
                            <i class="<?php echo profile_h($meta['icon']); ?>" aria-hidden="true"></i>
                        <?php endif; ?>
                    </span>
                    <span class="profile-favorite-info">
                        <strong><?php echo profile_h($itemTitle); ?></strong>
                        <span class="profile-favorite-tags">
                            <?php if ($subtitle !== ''): ?><span class="profile-favorite-tag"><?php echo profile_h($subtitle); ?></span><?php endif; ?>
                            <?php if ($itemMeta !== ''): ?><span class="profile-favorite-meta"><?php echo profile_h($itemMeta); ?></span><?php endif; ?>
                        </span>
                    </span>
                </<?php echo $tag; ?>>
            <?php endforeach; ?>
        </div>
    </section>
<?php
    return (string)ob_get_clean();
}
