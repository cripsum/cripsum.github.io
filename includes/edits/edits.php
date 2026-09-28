<?php

/**
 * Edits: gli edit dal database (edits, edits_categorie) e i dati pronti per
 * la pagina /it/edits.
 *
 * Un edit e' un file caricato dal pannello (vid/edits/) oppure un embed
 * Streamable. La pagina mostra solo le copertine: il video si carica quando
 * lo si apre.
 *
 * Ogni card porta anche quello che legge PreMiD (il presence di Cripsum.com
 * su github.com/PreMiD/Activities): .edit-card con data-edit-id,
 * .character-name span (titolo), .music-info span (musica) e img.rpcimg,
 * l'immagine invisibile che finisce su Discord. Non vanno rinominati.
 */

require_once __DIR__ . '/../shop/catalog.php';

/** Per quanti giorni un edit appena pubblicato ha l'etichetta "Nuovo". */
const EDITS_NEW_DAYS = 14;

/** Proporzioni di scorta quando non si conoscono (i video di Streamable mai controllati). */
const EDITS_DEFAULT_RATIO = [4, 5];

function edits_ready(?mysqli $mysqli): bool
{
    return shop_tables_ready($mysqli, ['edits', 'edits_categorie']);
}

/** Il codice di un video Streamable da un link qualsiasi (streamable.com/xxx, /e/xxx, /o/xxx) o dal codice nudo. */
function edits_streamable_code(string $value): ?string
{
    $value = trim($value);
    if (preg_match('~^[a-z0-9]{3,20}$~i', $value)) {
        return strtolower($value);
    }
    if (preg_match('~^(?:https?://)?(?:www\.)?streamable\.com/(?:[eo]/)?([a-z0-9]{3,20})(?:[/?#].*)?$~i', $value, $m)) {
        return strtolower($m[1]);
    }

    return null;
}

function edits_categories(mysqli $mysqli, string $lang): array
{
    $out = [];
    foreach (shop_fetch_all($mysqli, 'SELECT * FROM edits_categorie ORDER BY posizione ASC, id ASC') as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'slug' => (string)$row['slug'],
            'name' => shop_pick($row, 'nome', $lang),
            'icon' => (string)($row['icona'] ?: 'fa-solid fa-film'),
        ];
    }

    return $out;
}

function edits_rows(mysqli $mysqli, bool $onlyPublished = true): array
{
    return shop_fetch_all(
        $mysqli,
        "SELECT e.*, c.slug AS categoria_slug, c.nome AS categoria_nome, c.nome_en AS categoria_nome_en, c.icona AS categoria_icona
         FROM edits e
         LEFT JOIN edits_categorie c ON c.id = e.categoria_id
         " . ($onlyPublished ? "WHERE e.stato = 'pubblicato'" : '') . "
         ORDER BY e.posizione ASC, e.id DESC"
    );
}

/** Un percorso di vid/edits/ (quello che salva il pannello) pronto per <video>. */
function edits_video_url(?string $value): string
{
    $value = trim((string)$value);
    if (!preg_match('~^/vid/edits/[A-Za-z0-9_.-]{1,160}\.(?:mp4|webm|mov|m4v)$~i', $value) || str_contains($value, '..')) {
        return '';
    }

    return $value;
}

function edits_view(array $row, string $lang): array
{
    $id = (int)$row['id'];
    $video = edits_video_url($row['video'] ?? '');
    $code = edits_streamable_code((string)($row['streamable'] ?? '')) ?? '';

    $width = (int)($row['larghezza'] ?? 0);
    $height = (int)($row['altezza'] ?? 0);
    if ($width <= 0 || $height <= 0) {
        [$width, $height] = EDITS_DEFAULT_RATIO;
    }
    $ratio = $width / $height;
    $shape = $ratio > 1.08 ? 'landscape' : ($ratio < 0.93 ? 'portrait' : 'square');

    $gif = trim((string)($row['gif_presence'] ?? ''));
    $gif = $gif !== '' ? shop_asset_url($gif) : '';
    $cover = shop_asset_url($row['copertina'] ?? '');

    $label = shop_pick($row, 'etichetta', $lang);
    $isNew = false;
    if ($label === '' && !empty($row['pubblicato_at']) && shop_is_recent((string)$row['pubblicato_at'], EDITS_NEW_DAYS)) {
        $isNew = true;
    }

    $collabLink = trim((string)($row['collab_link'] ?? ''));
    $tiktok = trim((string)($row['tiktok'] ?? ''));

    return [
        'id' => $id,
        'title' => shop_pick($row, 'titolo', $lang),
        'music' => trim((string)($row['musica'] ?? '')),
        'category' => [
            'slug' => (string)($row['categoria_slug'] ?? ''),
            'name' => $row['categoria_slug'] !== null ? shop_pick(['nome' => $row['categoria_nome'], 'nome_en' => $row['categoria_nome_en']], 'nome', $lang) : '',
            'icon' => (string)($row['categoria_icona'] ?: 'fa-solid fa-film'),
        ],
        'source' => $video !== '' ? 'file' : ($code !== '' ? 'streamable' : 'none'),
        'video' => $video,
        'embed' => $code !== '' ? 'https://streamable.com/e/' . rawurlencode($code) . '?autoplay=1' : '',
        'streamable_url' => $code !== '' ? 'https://streamable.com/' . rawurlencode($code) : '',
        // Un file vince sempre; un embed che Streamable non ha piu' si dice chiaramente.
        'missing' => $video === '' && ($code === '' || (isset($row['streamable_ok']) && $row['streamable_ok'] !== null && (int)$row['streamable_ok'] === 0)),
        'width' => $width,
        'height' => $height,
        'ratio' => round($ratio, 4),
        'shape' => $shape,
        // Senza copertina si usa la GIF della presence, poi niente.
        'cover' => $cover !== '' ? $cover : $gif,
        'presence_image' => $gif !== '' ? $gif : $cover,
        'label' => $label !== '' ? $label : '',
        'is_new' => $isNew,
        'featured' => (int)($row['in_evidenza'] ?? 0) === 1,
        'collab' => trim((string)($row['collab_nome'] ?? '')),
        'collab_link' => $collabLink !== '' && shop_valid_link($collabLink) ? $collabLink : '',
        'tiktok' => $tiktok !== '' && shop_valid_link($tiktok) ? $tiktok : '',
        'views' => (int)($row['visualizzazioni'] ?? 0),
        'url' => '/' . $lang . '/edits/' . $id,
    ];
}

/**
 * Le posizioni per l'ordinamento "Piu' visti": la pagina riceve la
 * classifica, non il numero di visualizzazioni, che si vede solo nel
 * pannello.
 */
function edits_popularity_ranks(array $edits): array
{
    $sorted = $edits;
    usort($sorted, static fn(array $a, array $b): int => [$b['views'], $a['id']] <=> [$a['views'], $b['id']]);

    $ranks = [];
    foreach ($sorted as $i => $edit) {
        $ranks[$edit['id']] = $i + 1;
    }

    return $ranks;
}

function edits_page_texts(?mysqli $mysqli, string $lang, array $S): array
{
    $texts = shop_page_texts($mysqli, 'edits', $lang);

    return [
        'title' => $texts['title'] !== '' ? $texts['title'] : $S['title'],
        'subtitle' => $texts['subtitle'] !== '' ? $texts['subtitle'] : $S['subtitle'],
        'link_text' => $texts['link_text'],
        'link_url' => $texts['link_url'],
    ];
}

/** Il titolo con l'ultima parola colorata: "I miei <em>edit</em>". */
function edits_title_html(string $title): string
{
    $title = trim($title);
    $pos = mb_strrpos($title, ' ');
    if ($pos === false) {
        return shop_h($title);
    }

    return shop_h(mb_substr($title, 0, $pos)) . ' <em>' . shop_h(mb_substr($title, $pos + 1)) . '</em>';
}
