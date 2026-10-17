<?php
/**
 * Cripsumpedia — la ricerca che si apre sopra la pagina (fase 3).
 *
 * Risponde a ogni lettera scritta (lo script aspetta un attimo prima di
 * chiedere), quindi deve essere rapida e mandare poco. Le voci trovate si
 * dividono così:
 * - per tipo (Persone, Eventi, Meme): trovate dal titolo, da un soprannome
 *   («detto anche salsina») o dalla riga «in breve»;
 * - «Nel testo di»: voci che nominano la parola solo nel testo o in una
 *   citazione, con il pezzo di frase dove compare.
 * Senza niente di scritto: le voci più lette adesso.
 */

const CP_PALETTE_PER_GROUP = 4;
const CP_PALETTE_TEXT = 3;

/** Una voce come la mostra la finestra di ricerca. */
function cp_palette_hit(array $entry, string $lang): array
{
    $title = cp_i18n($entry, 'title', $lang);
    $type = (string)$entry['entry_type'];
    $line = cp_entry_line($entry, $lang, 110);
    if ($type === 'event') {
        $date = cp_event_date($entry, $lang);
        $line = $date !== '' ? $date . ($line !== '' ? ' · ' . $line : '') : $line;
    }
    return [
        'id' => (int)$entry['id'],
        'type' => $type,
        'title' => $title,
        'url' => cp_entry_url($entry, $lang),
        'image' => cp_entry_image($entry),
        'mono' => cp_monogram($title),
        'line' => $line,
    ];
}

/** I soprannomi delle voci indicate, nella lingua giusta: id => [soprannomi]. */
function cp_aliases_for(mysqli $mysqli, array $ids, string $lang): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $mysqli->prepare("SELECT entry_id, alias, alias_en FROM cripsumpedia_aliases WHERE entry_id IN ($marks) ORDER BY id ASC");
    if (!$stmt) return [];
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $out = [];
    foreach (cp_stmt_all($stmt) as $row) {
        $alias = cp_i18n($row, 'alias', $lang);
        if ($alias !== '') $out[(int)$row['entry_id']][] = $alias;
    }
    $stmt->close();
    return $out;
}

/**
 * Il pezzo di testo intorno alla parola cercata, una quarantina di caratteri
 * per parte, tagliato a parole intere e preso dal solo paragrafo dove la
 * parola compare (i paragrafi sono le righe di $text). Vuoto se non c'è.
 */
function cp_text_snippet(string $text, string $query, int $radius = 42): string
{
    $line = '';
    foreach (preg_split('~\n+~u', $text) ?: [] as $candidate) {
        if (mb_stripos($candidate, $query, 0, 'UTF-8') !== false) {
            $line = $candidate;
            break;
        }
    }
    $text = trim((string)preg_replace('~\s+~u', ' ', $line));
    $pos = mb_stripos($text, $query, 0, 'UTF-8');
    if ($pos === false) return '';

    $length = mb_strlen($text, 'UTF-8');
    $start = max(0, $pos - $radius);
    $end = min($length, $pos + mb_strlen($query, 'UTF-8') + $radius);
    $piece = mb_substr($text, $start, $end - $start, 'UTF-8');

    // Niente parole tagliate a metà ai bordi.
    if ($start > 0) $piece = preg_replace('~^\S*\s~u', '', $piece) ?? $piece;
    if ($end < $length) $piece = preg_replace('~\s\S*$~u', '', $piece) ?? $piece;
    return ($start > 0 ? '…' : '') . trim($piece) . ($end < $length ? '…' : '');
}

/**
 * Il testo di una voce senza tag (quello salvato, se è pronto), più le sue
 * citazioni: un paragrafo, un punto di elenco o un titoletto per riga.
 */
function cp_entry_plain_text(mysqli $mysqli, array $entry, string $lang): string
{
    $useEn = $lang === 'en' && trim((string)($entry['content_md_en'] ?? '')) !== '';
    $html = cp_stored_html($useEn ? ($entry['content_html_en'] ?? null) : ($entry['content_html'] ?? null))
        ?? cp_markdown_render((string)($useEn ? $entry['content_md_en'] : ($entry['content_md'] ?? '')));
    $html = (string)preg_replace('~<br\s*/?>|</(p|li|h[2-4]|blockquote|figcaption|summary|div)>~i', "$0\n", $html);
    $text = html_entity_decode((string)preg_replace('~<[^>]*>~', ' ', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    foreach (cp_fetch_quotes($mysqli, (int)$entry['id'], 10) as $quote) {
        $text .= "\n«" . cp_i18n($quote, 'quote_text', $lang) . '»';
    }
    return $text;
}

/**
 * I risultati per la finestra di ricerca.
 *
 * @return array{query: string, groups: array<string, array>, text: array, total: int, popular: array}
 */
function cp_palette_search(mysqli $mysqli, string $query, string $lang): array
{
    $query = mb_substr(trim((string)preg_replace('~\s+~u', ' ', $query)), 0, 80, 'UTF-8');
    $out = ['query' => $query, 'groups' => ['person' => [], 'event' => [], 'meme' => []], 'text' => [], 'total' => 0, 'popular' => []];
    if (!cp_schema_ready($mysqli)) return $out;

    if ($query === '') {
        $out['popular'] = array_map(static fn(array $e): array => cp_palette_hit($e, $lang), cp_fetch_entries($mysqli, ['order' => 'trending', 'limit' => 5]));
        return $out;
    }

    $rows = cp_search_entries($mysqli, $query, $lang, ['limit' => 30]);
    $out['total'] = count($rows);
    $aliases = cp_aliases_for($mysqli, array_map(static fn(array $r): int => (int)$r['id'], $rows), $lang);
    $contains = static fn(string $haystack): bool => $haystack !== '' && mb_stripos($haystack, $query, 0, 'UTF-8') !== false;

    foreach ($rows as $row) {
        $hit = cp_palette_hit($row, $lang);
        $type = $hit['type'];
        if (!isset($out['groups'][$type])) continue;

        $matchedAlias = '';
        if (!$contains($hit['title'])) {
            foreach ($aliases[$hit['id']] ?? [] as $alias) {
                if ($contains($alias)) {
                    $matchedAlias = $alias;
                    break;
                }
            }
        }

        $inName = $contains($hit['title']) || $matchedAlias !== ''
            || $contains(cp_i18n($row, 'subtitle', $lang)) || $contains(cp_i18n($row, 'description', $lang));

        if (!$inName) {
            // Solo nel testo o in una citazione: va in «Nel testo di» col pezzo di frase.
            $snippet = cp_text_snippet(cp_entry_plain_text($mysqli, $row, $lang), $query);
            if ($snippet !== '') {
                if (count($out['text']) < CP_PALETTE_TEXT) {
                    $out['text'][] = ['snippet' => $snippet] + $hit;
                }
                continue;
            }
            // Trovata da un tag, o con lettere accentate diverse: resta nel suo tipo.
        }

        if (count($out['groups'][$type]) < CP_PALETTE_PER_GROUP) {
            $out['groups'][$type][] = $matchedAlias !== '' ? ['alias' => $matchedAlias] + $hit : $hit;
        }
    }
    return $out;
}

/** I testi della finestra, per lo script. */
function cp_palette_strings(string $lang): array
{
    // Quelli con %s arrivano così e lo script mette al posto la parola cercata.
    $keys = ['search_pedia', 'search_hint', 'palette_results', 'group_text', 'aka', 'write_hint', 'write_this', 'all_results', 'hint_move', 'hint_open', 'hint_close', 'key_enter', 'searching', 'nothing_found', 'nothing_hint', 'popular_now', 'close'];
    $strings = [];
    foreach ($keys as $key) $strings[$key] = cp_ui($key, $lang);
    $strings['groups'] = ['person' => cp_t('people', $lang), 'event' => cp_t('events', $lang), 'meme' => cp_t('memes', $lang)];
    return $strings;
}
