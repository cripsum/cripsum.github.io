<?php
/**
 * Cripsumpedia — i pezzi delle pagine da leggere (fase 2): la barra sotto la
 * navbar, ritratti, meme, righe degli eventi, linea del tempo, ultime
 * modifiche, e i testi in italiano e inglese che usano.
 *
 * Disegno approvato nelle tavole proposte-home/cripsumpedia-tavola-1..6.png:
 * nero e bianco, il colore arriva solo dalle foto, riquadri solo dove servono.
 */

/** I testi nuovi. Un valore con due forme è [singolare, plurale]. */
function cp_ui_strings(string $lang): array
{
    static $dict = null;
    $dict ??= [
        'it' => [
            'search_pedia' => 'Cerca nella Cripsumpedia',
            'random_entry' => 'Una voce a caso',
            'write' => 'Scrivi una voce',
            'hero_1' => 'La lore di Cripsum,',
            'hero_2' => 'scritta da chi c\'era.',
            'hero_lead' => 'Chi è chi nel server, cosa è successo e da dove arriva quella frase che ripetono tutti.',
            'search_hint' => 'Cerca una persona, un evento, un meme…',
            'try_with' => 'Prova con',
            'featured' => 'In evidenza',
            'read' => 'Leggi',
            'all_people' => 'Tutte le persone',
            'timeline_link' => 'La linea del tempo',
            'all_memes' => 'Tutti',
            'recent_changes' => 'Ultime modifiche',
            'new_badge' => 'Nuova',
            'did_new' => 'ha scritto',
            'did_edit' => 'ha modificato',
            'did_restore' => 'ha rimesso com\'era',
            'someone' => 'qualcuno',
            'invite_title' => 'Manca qualcuno? Scrivilo tu.',
            'invite_text' => 'Per ora le voci le scrive lo staff. Presto potrà proporne una chiunque abbia un account.',
            'on_this_day' => 'Accadde oggi',
            'years_ago' => ['un anno fa', '%d anni fa'],
            'add_person' => 'Manca qualcuno? Scrivi la sua voce',
            'add_event' => 'Manca un pezzo di storia?',
            'add_event_text' => 'Scrivi cosa è successo, quando e chi c\'era.',
            'add_meme' => 'Aggiungi un meme',
            'lead_person' => 'Chi è chi nel server. Ognuno ha la sua voce: soprannomi, storie, citazioni.',
            'lead_event' => 'La storia del server in ordine, dalla prima cosa successa all\'ultima.',
            'lead_meme' => 'Le frasi, le immagini e i tormentoni del server, e da dove arrivano.',
            'sort_az' => 'A–Z',
            'sort_newest_people' => 'Ultime arrivate',
            'sort_recent' => 'Dal più recente',
            'sort_oldest' => 'Dal più vecchio',
            'sort_new' => 'Recenti',
            'sort_label' => 'Ordine',
            'filter_label' => 'Filtra per tag',
            'no_date' => 'Senza data',
            'involved' => ['%d persona coinvolta', '%d persone coinvolte'],
            'nobody_here' => 'Qui non c\'è ancora nessuna voce.',
            'modified' => 'Modificata %s',
            'authors' => ['%d autore', '%d autori'],
            'reads' => ['%d lettura', '%d letture'],
            'edit' => 'Modifica',
            'share' => 'Condividi',
            'known_as' => 'Conosciuto come',
            'profile' => 'Profilo',
            'lore_date' => 'Data lore',
            'since_person' => 'Nel server dal',
            'when' => 'Quando',
            'since_meme' => 'Nato',
            'who_was_there' => 'Chi c\'era',
            'tags' => 'Tag',
            'in_this_entry' => 'In questa voce',
            'quotes' => 'Citazioni',
            'links' => 'Collegamenti',
            'cited_in' => 'Ne parlano anche',
            'cited_in_n' => ['Citata in %d voce', 'Citata in %d voci'],
            'last_edit' => 'Ultima modifica %s',
            'note_non_canon' => 'Questa storia non è canonica.',
            'note_disputed' => 'Questa storia è dibattuta: non tutti la raccontano così.',
            'note_draft' => 'Bozza: la vede solo lo staff.',
            'note_archived' => 'Archiviata: la vede solo lo staff.',
            'results_for' => 'Risultati per «%s»',
            'results' => ['%d risultato', '%d risultati'],
            'popular_now' => 'Le più lette adesso',
            'nothing_found' => 'Nessuna voce per «%s».',
            'nothing_hint' => 'Prova con un soprannome, o con una parola sola.',
            'write_this' => 'Scrivi la voce «%s»',
            'not_found_title' => 'Questa voce non c\'è',
            'not_found_text' => 'Forse ha cambiato nome, o nessuno l\'ha ancora scritta.',
            'back_to_pedia' => 'Torna alla Cripsumpedia',
            'link_copied' => 'Link copiato',
            'just_now' => 'ora',
            'minutes_ago' => '%d min fa',
            'today_at' => 'oggi, %s',
            'yesterday' => 'ieri',
            'months' => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'],
            'months_long' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
            'rel' => [
                'related' => 'Collegato a', 'friend' => 'Amico di', 'rival' => 'Rivale di', 'protagonist' => 'Protagonista di',
                'present' => 'Presente a', 'created' => 'Ha creato', 'invented' => 'Ha inventato', 'appears' => 'Compare in', 'cause' => 'Causa di',
            ],
        ],
        'en' => [
            'search_pedia' => 'Search the Cripsumpedia',
            'random_entry' => 'A random entry',
            'write' => 'Write an entry',
            'hero_1' => 'Cripsum\'s lore,',
            'hero_2' => 'written by those who were there.',
            'hero_lead' => 'Who\'s who on the server, what happened and where that line everyone repeats comes from.',
            'search_hint' => 'Search a person, an event, a meme…',
            'try_with' => 'Try',
            'featured' => 'Featured',
            'read' => 'Read',
            'all_people' => 'All people',
            'timeline_link' => 'The timeline',
            'all_memes' => 'All',
            'recent_changes' => 'Recent changes',
            'new_badge' => 'New',
            'did_new' => 'wrote',
            'did_edit' => 'edited',
            'did_restore' => 'restored',
            'someone' => 'someone',
            'invite_title' => 'Someone missing? Write them in.',
            'invite_text' => 'For now entries are written by the staff. Soon anyone with an account will be able to suggest one.',
            'on_this_day' => 'On this day',
            'years_ago' => ['a year ago', '%d years ago'],
            'add_person' => 'Someone missing? Write their entry',
            'add_event' => 'A piece of history missing?',
            'add_event_text' => 'Write what happened, when and who was there.',
            'add_meme' => 'Add a meme',
            'lead_person' => 'Who\'s who on the server. Everyone has an entry: nicknames, stories, quotes.',
            'lead_event' => 'The server\'s history in order, from the first thing that happened to the latest.',
            'lead_meme' => 'The server\'s lines, pictures and running jokes, and where they come from.',
            'sort_az' => 'A–Z',
            'sort_newest_people' => 'Newest',
            'sort_recent' => 'Newest first',
            'sort_oldest' => 'Oldest first',
            'sort_new' => 'Recent',
            'sort_label' => 'Order',
            'filter_label' => 'Filter by tag',
            'no_date' => 'Undated',
            'involved' => ['%d person involved', '%d people involved'],
            'nobody_here' => 'No entries here yet.',
            'modified' => 'Edited %s',
            'authors' => ['%d author', '%d authors'],
            'reads' => ['%d read', '%d reads'],
            'edit' => 'Edit',
            'share' => 'Share',
            'known_as' => 'Also known as',
            'profile' => 'Profile',
            'lore_date' => 'Lore date',
            'since_person' => 'On the server since',
            'when' => 'When',
            'since_meme' => 'First seen',
            'who_was_there' => 'Who was there',
            'tags' => 'Tags',
            'in_this_entry' => 'In this entry',
            'quotes' => 'Quotes',
            'links' => 'Connections',
            'cited_in' => 'Also mentioned in',
            'cited_in_n' => ['Mentioned in %d entry', 'Mentioned in %d entries'],
            'last_edit' => 'Last edited %s',
            'note_non_canon' => 'This story is not canon.',
            'note_disputed' => 'This story is disputed: not everyone tells it this way.',
            'note_draft' => 'Draft: only the staff can see it.',
            'note_archived' => 'Archived: only the staff can see it.',
            'results_for' => 'Results for “%s”',
            'results' => ['%d result', '%d results'],
            'popular_now' => 'Most read right now',
            'nothing_found' => 'No entries for “%s”.',
            'nothing_hint' => 'Try a nickname, or a single word.',
            'write_this' => 'Write the “%s” entry',
            'not_found_title' => 'This entry doesn\'t exist',
            'not_found_text' => 'Maybe it was renamed, or nobody has written it yet.',
            'back_to_pedia' => 'Back to the Cripsumpedia',
            'link_copied' => 'Link copied',
            'just_now' => 'just now',
            'minutes_ago' => '%d min ago',
            'today_at' => 'today, %s',
            'yesterday' => 'yesterday',
            'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'months_long' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'rel' => [
                'related' => 'Connected to', 'friend' => 'Friend of', 'rival' => 'Rival of', 'protagonist' => 'Main character of',
                'present' => 'Was at', 'created' => 'Created', 'invented' => 'Came up with', 'appears' => 'Appears in', 'cause' => 'Caused',
            ],
        ],
    ];
    return $dict[$lang] ?? $dict['it'];
}

/** Un testo, con i valori al posto dei %s / %d. */
function cp_ui(string $key, string $lang, mixed ...$args): string
{
    $text = cp_ui_strings($lang)[$key] ?? $key;
    if (is_array($text)) $text = $text[0];
    return $args ? vsprintf((string)$text, $args) : (string)$text;
}

/** Un testo con singolare e plurale. */
function cp_ui_n(string $key, int $count, string $lang): string
{
    $forms = cp_ui_strings($lang)[$key] ?? [$key, $key];
    $form = is_array($forms) ? ($count === 1 ? $forms[0] : $forms[1]) : $forms;
    return sprintf($form, $count);
}

// ── Date ────────────────────────────────────────────────────────────────────

/** «4 feb 1999» (short), «4 febbraio 1999» (long), «4 febbraio» (day). */
function cp_format_date(?string $date, string $lang, string $style = 'short'): string
{
    $date = trim((string)$date);
    if ($date === '' || str_starts_with($date, '0000')) return '';
    $ts = strtotime($date);
    if (!$ts) return '';

    $strings = cp_ui_strings($lang);
    $month = (int)date('n', $ts) - 1;
    $name = $style === 'short' ? $strings['months'][$month] : $strings['months_long'][$month];
    $text = date('j', $ts) . ' ' . $name;
    return $style === 'day' ? $text : $text . ' ' . date('Y', $ts);
}

/** «12 min fa», «oggi, 09:34», «ieri», «14 ott», «14 ott 2025». */
function cp_relative_time(?string $datetime, string $lang): string
{
    $ts = strtotime((string)$datetime);
    if (!$ts) return '';
    $diff = time() - $ts;

    if ($diff < 60) return cp_ui('just_now', $lang);
    if ($diff < 3600) return cp_ui('minutes_ago', $lang, (int)floor($diff / 60));
    if (date('Y-m-d', $ts) === date('Y-m-d')) return cp_ui('today_at', $lang, date('H:i', $ts));
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) return cp_ui('yesterday', $lang);

    $strings = cp_ui_strings($lang);
    $text = date('j', $ts) . ' ' . $strings['months'][(int)date('n', $ts) - 1];
    return date('Y', $ts) === date('Y') ? $text : $text . ' ' . date('Y', $ts);
}

/** La data da mostrare per un evento: quella vera, se no quella della lore. */
function cp_event_date(array $entry, string $lang, string $style = 'short'): string
{
    $real = cp_format_date($entry['real_date'] ?? null, $lang, $style);
    return $real !== '' ? $real : trim((string)($entry['lore_date'] ?? ''));
}

// ── Pezzi di una voce ───────────────────────────────────────────────────────

/** La foto di una voce, o null: senza foto si mostrano le iniziali. */
function cp_entry_image(array $entry): ?string
{
    $path = trim((string)($entry['image_url'] ?? '')) ?: trim((string)($entry['banner_url'] ?? ''));
    return $path !== '' ? cp_asset_url($path) : null;
}

/** «xalx andrea» → «XA», «uip.rl» → «U». */
function cp_monogram(string $title): string
{
    $words = preg_split('~[^\p{L}\p{N}]+~u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letters = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $letters .= mb_substr($word, 0, 1, 'UTF-8');
    }
    if (count($words) > 1 && mb_strlen($words[1], 'UTF-8') <= 2) {
        $letters = mb_substr($letters, 0, 1, 'UTF-8'); // «uip.rl»: «rl» non è una parola
    }
    return mb_strtoupper($letters !== '' ? $letters : '?', 'UTF-8');
}

/** La riga che accompagna una voce nelle liste: «in breve», se no l'inizio della descrizione. */
function cp_entry_line(array $entry, string $lang, int $length = 90): string
{
    $line = cp_i18n($entry, 'subtitle', $lang);
    if ($line === '') $line = cp_i18n($entry, 'description', $lang);
    return $line === '' ? '' : cp_excerpt($line, $length);
}

/** Foto della voce dentro un riquadro, o le iniziali (per un meme: il titolo scritto grande). */
function cp_render_media(array $entry, string $class, string $lang, bool $lazy = true): void
{
    $image = cp_entry_image($entry);
    $title = cp_i18n($entry, 'title', $lang);
?>
    <span class="<?= cp_h($class) ?>">
        <?php if ($image): ?>
            <img src="<?= cp_h($image) ?>" alt="" <?= $lazy ? 'loading="lazy"' : '' ?> decoding="async" data-cp-fade>
        <?php elseif (($entry['entry_type'] ?? '') === 'meme'): ?>
            <span class="cp-word">«<?= cp_h($title) ?>»</span>
        <?php else: ?>
            <span class="cp-mono" aria-hidden="true"><?= cp_h(cp_monogram($title)) ?></span>
        <?php endif; ?>
    </span>
<?php
}

function cp_avatar_url(int $userId, int $size = 64): string
{
    return '/includes/get_pfp.php?id=' . $userId . '&size=' . $size;
}

/** L'etichetta di un collegamento, a parole: quella scritta dallo staff, se c'è. */
function cp_relation_label(array $relation, string $lang): string
{
    $label = cp_i18n($relation, 'relation_label', $lang);
    if ($label !== '') return $label;
    $type = (string)($relation['relation_type'] ?? 'related');
    return cp_ui_strings($lang)['rel'][$type] ?? cp_ui_strings($lang)['rel']['related'];
}

/**
 * L'indice di una voce dai suoi titoletti (h2 e h3 con id, li mette
 * cp_markdown_render). Vuoto sotto i tre titoletti: non serve.
 */
function cp_toc(string $html): array
{
    if (!preg_match_all('~<h([23]) id="([^"]+)">(.*?)</h\1>~su', $html, $matches, PREG_SET_ORDER)) return [];
    $items = array_map(static fn(array $m): array => [
        'level' => (int)$m[1],
        'id' => $m[2],
        'text' => html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    ], $matches);
    return count($items) >= 3 ? $items : [];
}

// ── Componenti ──────────────────────────────────────────────────────────────

/** La barra della Cripsumpedia, sotto la navbar del sito. */
function cp_render_bar(string $lang, string $active = '', bool $withSearch = true, string $query = ''): void
{
    $links = ['person' => cp_t('people', $lang), 'event' => cp_t('events', $lang), 'meme' => cp_t('memes', $lang)];
?>
    <nav class="cp-bar<?= $withSearch ? '' : ' cp-bar--home' ?>" aria-label="Cripsumpedia">
        <a class="cp-brand" href="<?= cp_h(cp_url('home', [], $lang)) ?>"><i class="fa-solid fa-book-open" aria-hidden="true"></i>Cripsumpedia</a>
        <div class="cp-bar__links">
            <?php foreach ($links as $type => $label): ?>
                <a href="<?= cp_h(cp_url('category', ['type' => $type], $lang)) ?>"<?= $active === $type ? ' class="is-active" aria-current="page"' : '' ?>><?= cp_h($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($withSearch): ?>
            <form class="cp-bar__search" action="<?= cp_h(cp_url('search', [], $lang)) ?>" method="get" role="search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" value="<?= cp_h($query) ?>" placeholder="<?= cp_h(cp_ui('search_pedia', $lang)) ?>" aria-label="<?= cp_h(cp_ui('search_pedia', $lang)) ?>" autocomplete="off" spellcheck="false">
            </form>
        <?php endif; ?>
        <div class="cp-bar__tools">
            <button class="cp-icon" type="button" data-cp-random title="<?= cp_h(cp_ui('random_entry', $lang)) ?>" aria-label="<?= cp_h(cp_ui('random_entry', $lang)) ?>"><i class="fa-solid fa-shuffle" aria-hidden="true"></i></button>
            <?php if (cp_can_write()): ?>
                <a class="cp-btn" href="<?= cp_h(cp_write_url($lang)) ?>" aria-label="<?= cp_h(cp_ui('write', $lang)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><span><?= cp_h(cp_ui('write', $lang)) ?></span></a>
            <?php endif; ?>
        </div>
    </nav>
<?php
}

function cp_render_person_tile(array $entry, string $lang): void
{
    $line = cp_entry_line($entry, $lang, 60);
?>
    <a class="cp-person" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>">
        <?php cp_render_media($entry, 'cp-person__img', $lang); ?>
        <strong><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></strong>
        <?php if ($line !== ''): ?><span><?= cp_h($line) ?></span><?php endif; ?>
    </a>
<?php
}

function cp_render_meme_tile(array $entry, string $lang): void
{
?>
    <a class="cp-meme" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>">
        <?php cp_render_media($entry, 'cp-meme__img', $lang); ?>
        <strong><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></strong>
    </a>
<?php
}

/** La casella tratteggiata che invita a scrivere (solo per chi può). */
function cp_render_ghost_tile(string $label, string $url, string $class = 'cp-person'): void
{
?>
    <a class="<?= cp_h($class) ?>" href="<?= cp_h($url) ?>">
        <span class="cp-ghost"><i class="fa-solid fa-plus" aria-hidden="true"></i><?= cp_h($label) ?></span>
    </a>
<?php
}

/** Una riga della lista degli eventi in pagina d'ingresso. */
function cp_render_event_row(array $entry, string $lang): void
{
    $line = cp_entry_line($entry, $lang, 110);
?>
    <li>
        <a class="cp-ev" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>">
            <time><?= cp_h(cp_event_date($entry, $lang)) ?></time>
            <span class="cp-ev__text"><strong><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></strong><?php if ($line !== ''): ?><span><?= cp_h($line) ?></span><?php endif; ?></span>
            <?php cp_render_media($entry, 'cp-ev__img', $lang); ?>
        </a>
    </li>
<?php
}

/** Un evento sulla linea del tempo, con chi c'era. */
function cp_render_timeline_item(array $entry, array $people, string $lang): void
{
    $line = cp_entry_line($entry, $lang, 220);
    $date = cp_format_date($entry['real_date'] ?? null, $lang, 'day');
    if ($date === '') $date = trim((string)($entry['lore_date'] ?? ''));
    $names = array_map(static fn(array $p): string => cp_i18n($p, 'title', $lang), $people);
?>
    <li>
        <a class="cp-tl" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>">
            <time><?= cp_h($date) ?></time>
            <span class="cp-tl__text">
                <strong><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></strong>
                <?php if ($line !== ''): ?><span class="cp-tl__line"><?= cp_h($line) ?></span><?php endif; ?>
                <?php if ($people): ?>
                    <span class="cp-tl__who">
                        <span class="cp-faces" aria-hidden="true">
                            <?php foreach (array_slice($people, 0, 3) as $person): ?>
                                <?php cp_render_media($person, 'cp-face', $lang); ?>
                            <?php endforeach; ?>
                            <?php if (count($people) > 3): ?><span class="cp-face cp-face--more">+<?= count($people) - 3 ?></span><?php endif; ?>
                        </span>
                        <?= cp_h(count($people) > 3 ? cp_ui_n('involved', count($people), $lang) : implode(', ', $names)) ?>
                    </span>
                <?php endif; ?>
            </span>
            <?php cp_render_media($entry, 'cp-tl__img', $lang); ?>
        </a>
    </li>
<?php
}

/** Una riga di «Ultime modifiche». */
function cp_render_change_row(array $change, string $lang): void
{
    $entry = $change['entry'];
    $verb = match ($change['kind']) {
        'new', 'import' => cp_ui('did_new', $lang),
        'restore' => cp_ui('did_restore', $lang),
        default => cp_ui('did_edit', $lang),
    };
    $isNew = in_array($change['kind'], ['new', 'import'], true) && strtotime($change['at']) > time() - 7 * 86400;
    $who = $change['name'] !== '' ? $change['name'] : cp_ui('someone', $lang);
?>
    <li>
        <?php if ($change['user_id'] > 0): ?>
            <img src="<?= cp_h(cp_avatar_url($change['user_id'], 64)) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <span class="cp-face cp-face--empty" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="cp-feed__what">
            <?php if ($change['username'] !== ''): ?><a class="cp-feed__who" href="/u/<?= cp_h(rawurlencode($change['username'])) ?>"><?= cp_h($who) ?></a><?php else: ?><b><?= cp_h($who) ?></b><?php endif; ?>
            <?= cp_h($verb) ?>
            <a href="<?= cp_h(cp_entry_url($entry, $lang)) ?>"><?= cp_h(cp_i18n($entry, 'title', $lang)) ?></a>
            <?php if ($isNew): ?><span class="cp-new"><?= cp_h(cp_ui('new_badge', $lang)) ?></span><?php endif; ?>
        </span>
        <time datetime="<?= cp_h(date('c', strtotime($change['at']) ?: time())) ?>"><?= cp_h(cp_relative_time($change['at'], $lang)) ?></time>
    </li>
<?php
}

/** La testata di una sezione: titolo, quante voci, link a tutte. */
function cp_render_section_head(string $title, ?int $count, string $moreLabel = '', string $moreUrl = ''): void
{
?>
    <header class="cp-sec__head">
        <h2><?= cp_h($title) ?><?php if ($count !== null): ?> <small><?= (int)$count ?></small><?php endif; ?></h2>
        <?php if ($moreUrl !== ''): ?>
            <a class="cp-more" href="<?= cp_h($moreUrl) ?>"><?= cp_h($moreLabel) ?><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        <?php endif; ?>
    </header>
<?php
}
