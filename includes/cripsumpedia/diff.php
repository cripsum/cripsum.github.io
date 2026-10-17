<?php
/**
 * Cripsumpedia — le differenze tra due versioni di una voce (fase 4), per
 * «Da approvare» e per la cronologia: campo per campo, il testo paragrafo per
 * paragrafo. Rosso tolto, verde aggiunto, grigio quello che resta intorno.
 */

/** L'ordine in cui si mostrano i campi. */
const CP_DIFF_FIELDS = [
    'entry_type' => 'line', 'title' => 'line', 'subtitle' => 'line', 'image_url' => 'image', 'real_date' => 'line', 'lore_date' => 'line',
    'content_md' => 'text', 'aliases' => 'list', 'tags' => 'list', 'relations' => 'list', 'quotes' => 'list', 'profile_user_id' => 'line',
    'title_en' => 'line', 'subtitle_en' => 'line', 'content_md_en' => 'text', 'description' => 'text', 'description_en' => 'text',
    'status' => 'line', 'canonical_status' => 'line', 'featured' => 'line',
];

/**
 * Due sequenze → operazioni [['same'|'del'|'add', valore], …] (sottosequenza
 * comune più lunga). Inizio e fine uguali si tolgono prima, così la tabella
 * resta piccola anche per testi lunghi.
 */
function cp_diff_sequence(array $a, array $b): array
{
    $n = count($a);
    $m = count($b);
    $start = 0;
    while ($start < $n && $start < $m && $a[$start] === $b[$start]) $start++;
    $endA = $n;
    $endB = $m;
    while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
        $endA--;
        $endB--;
    }

    $ops = [];
    for ($i = 0; $i < $start; $i++) $ops[] = ['same', $a[$i]];
    $midA = array_slice($a, $start, $endA - $start);
    $midB = array_slice($b, $start, $endB - $start);
    $p = count($midA);
    $q = count($midB);

    if ($p * $q > 250000) {
        foreach ($midA as $x) $ops[] = ['del', $x];
        foreach ($midB as $x) $ops[] = ['add', $x];
    } else {
        $lcs = array_fill(0, $p + 1, array_fill(0, $q + 1, 0));
        for ($i = $p - 1; $i >= 0; $i--) {
            for ($j = $q - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $midA[$i] === $midB[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        $i = 0;
        $j = 0;
        while ($i < $p && $j < $q) {
            if ($midA[$i] === $midB[$j]) {
                $ops[] = ['same', $midA[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $ops[] = ['del', $midA[$i++]];
            } else {
                $ops[] = ['add', $midB[$j++]];
            }
        }
        while ($i < $p) $ops[] = ['del', $midA[$i++]];
        while ($j < $q) $ops[] = ['add', $midB[$j++]];
    }
    for ($i = $endA; $i < $n; $i++) $ops[] = ['same', $a[$i]];
    return $ops;
}

/** Un paragrafo del testo in HTML leggibile: titoletti in grassetto, a capo come a capo. */
function cp_diff_paragraph(string $paragraph): string
{
    if (preg_match('~^#{1,4}\s+(.+)$~u', $paragraph, $m)) return '<strong>' . cp_render_inline_markdown(trim($m[1])) . '</strong>';
    $lines = preg_split('~\n~', $paragraph) ?: [];
    return implode('<br>', array_map(static fn(string $line): string => cp_render_inline_markdown($line), $lines));
}

/** Testo: paragrafi cambiati, con uno uguale prima e dopo; il resto diventa «…». */
function cp_diff_text(string $old, string $new): array
{
    $split = static function (string $text): array {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') return [];
        return array_values(array_filter(array_map(static fn(string $p): string => rtrim((string)preg_replace('~[ \t]+$~m', '', trim($p))), preg_split("~\n{2,}~", $text) ?: []), static fn(string $p): bool => $p !== ''));
    };
    $ops = cp_diff_sequence($split($old), $split($new));
    $changed = [];
    foreach ($ops as $index => [$op]) {
        if ($op !== 'same') $changed[$index] = true;
    }
    $rows = [];
    $gap = false;
    foreach ($ops as $index => [$op, $paragraph]) {
        $near = isset($changed[$index]) || isset($changed[$index - 1]) || isset($changed[$index + 1]);
        if ($op === 'same' && !$near) {
            if (!$gap) $rows[] = ['op' => 'gap', 'html' => '…'];
            $gap = true;
            continue;
        }
        $gap = false;
        $rows[] = ['op' => $op, 'html' => cp_diff_paragraph($paragraph)];
    }
    return $rows;
}

/** Il valore di un campo su una riga, a parole. */
function cp_diff_line_value(mysqli $mysqli, string $field, mixed $value, string $lang): string
{
    $w = cp_write_strings($lang);
    if ($value === null || $value === '') return '';
    return match ($field) {
        'entry_type' => cp_type_label((string)$value, $lang),
        'real_date' => cp_format_date((string)$value, $lang, 'long'),
        'status' => $value === 'published' ? $w['status_published'] : ($value === 'draft' ? $w['status_draft'] : (string)$value),
        'canonical_status' => $w['canon_values'][(string)$value] ?? (string)$value,
        'featured' => (int)$value === 1 ? ($lang === 'en' ? 'yes' : 'sì') : '',
        'profile_user_id' => (static function () use ($mysqli, $value): string {
            $stmt = $mysqli->prepare('SELECT username FROM utenti WHERE id = ? LIMIT 1');
            if (!$stmt) return '#' . (int)$value;
            $id = (int)$value;
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $name = $stmt->get_result()->fetch_row()[0] ?? null;
            $stmt->close();
            return $name !== null ? '@' . $name : '#' . $id;
        })(),
        default => (string)$value,
    };
}

/** I titoli delle voci collegate, per scrivere i collegamenti a parole. */
function cp_diff_titles(mysqli $mysqli, array $ids, string $lang): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $mysqli->prepare("SELECT id, title, title_en FROM cripsumpedia_entries WHERE id IN ($marks)");
    if (!$stmt) return [];
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $out = [];
    foreach (cp_stmt_all($stmt) as $row) $out[(int)$row['id']] = cp_i18n($row, 'title', $lang);
    $stmt->close();
    return $out;
}

/**
 * Le differenze tra due versioni (dati come cp_entry_snapshot; $old vuoto =
 * voce nuova). Solo i campi cambiati:
 * [['field', 'label', 'kind' => text|line|list|image, 'rows' => [['op', 'html']], 'old', 'new']].
 */
function cp_diff(mysqli $mysqli, array $old, array $new, string $lang): array
{
    $labels = cp_write_strings($lang)['fields'];
    $rel = cp_ui_strings($lang)['rel'];
    $titles = cp_diff_titles($mysqli, array_merge(
        array_column($old['relations'] ?? [], 'target_id'),
        array_column($new['relations'] ?? [], 'target_id')
    ), $lang);
    $out = [];

    foreach (CP_DIFF_FIELDS as $field => $kind) {
        $isList = $kind === 'list';
        $isTop = $field === 'entry_type';
        $before = $isList ? ($old[$field] ?? []) : ($isTop ? ($old[$field] ?? null) : ($old['fields'][$field] ?? null));
        $after = $isList ? ($new[$field] ?? []) : ($isTop ? ($new[$field] ?? null) : ($new['fields'][$field] ?? null));
        if ($before === '' ) $before = null;
        if ($after === '') $after = null;
        if ($field === 'featured' && !(int)$before && !(int)$after) continue;
        if (json_encode($before, JSON_UNESCAPED_UNICODE) === json_encode($after, JSON_UNESCAPED_UNICODE)) continue;
        // Una voce nuova non ha «com'era»: stato e canonicità di partenza non dicono niente.
        if (!$old && in_array($field, ['status', 'canonical_status'], true) && in_array($after, ['published', 'canon'], true)) continue;

        $item = ['field' => $field, 'label' => $labels[$field] ?? $field, 'kind' => $kind, 'rows' => []];

        if ($kind === 'image') {
            $item['old'] = $before ? cp_asset_url((string)$before) : null;
            $item['new'] = $after ? cp_asset_url((string)$after) : null;
        } elseif ($kind === 'text') {
            $item['rows'] = cp_diff_text((string)$before, (string)$after);
        } elseif ($kind === 'line') {
            $was = cp_diff_line_value($mysqli, $field, $before, $lang);
            $now = cp_diff_line_value($mysqli, $field, $after, $lang);
            if ($was === $now) continue;
            if ($was !== '') $item['rows'][] = ['op' => 'del', 'html' => cp_h($was)];
            if ($now !== '') $item['rows'][] = ['op' => 'add', 'html' => cp_h($now)];
        } else {
            $show = match ($field) {
                'quotes' => static fn(array $q): string => '«' . cp_h($q['text'] ?? '') . '»' . (($q['speaker'] ?? '') !== '' ? ' — ' . cp_h($q['speaker']) : ''),
                'relations' => static fn(array $r): string => cp_h(($r['label'] ?? '') !== '' ? $r['label'] : ($rel[$r['relation_type'] ?? 'related'] ?? $rel['related']))
                    . ' · ' . cp_h($titles[(int)($r['target_id'] ?? 0)] ?? '#' . (int)($r['target_id'] ?? 0)),
                default => static fn(array $p): string => cp_h($p['it'] ?? '') . (($p['en'] ?? '') !== '' ? ' <small>(' . cp_h($p['en']) . ')</small>' : ''),
            };
            $key = match ($field) {
                'quotes' => static fn(array $q): string => mb_strtolower((string)($q['text'] ?? ''), 'UTF-8'),
                'relations' => static fn(array $r): string => (int)($r['target_id'] ?? 0) . '|' . ($r['relation_type'] ?? ''),
                default => static fn(array $p): string => mb_strtolower((string)($p['it'] ?? ''), 'UTF-8'),
            };
            $afterByKey = [];
            foreach ((array)$after as $value) $afterByKey[$key($value)] = $value;
            $beforeKeys = [];
            foreach ((array)$before as $value) {
                $k = $key($value);
                $beforeKeys[$k] = true;
                if (!isset($afterByKey[$k])) {
                    $item['rows'][] = ['op' => 'del', 'html' => $show($value)];
                } elseif (json_encode($afterByKey[$k]) !== json_encode($value)) {
                    $item['rows'][] = ['op' => 'del', 'html' => $show($value)];
                    $item['rows'][] = ['op' => 'add', 'html' => $show($afterByKey[$k])];
                } else {
                    $item['rows'][] = ['op' => 'same', 'html' => $show($value)];
                }
            }
            foreach ((array)$after as $value) {
                if (!isset($beforeKeys[$key($value)])) $item['rows'][] = ['op' => 'add', 'html' => $show($value)];
            }
            if (!array_filter($item['rows'], static fn(array $row): bool => $row['op'] !== 'same')) continue;
        }
        $out[] = $item;
    }
    return $out;
}

/** Stampa le differenze (stesso disegno in «Da approvare» e in cronologia). */
function cp_render_diff(array $diff, mysqli $mysqli, string $lang): void
{
    foreach ($diff as $item): ?>
        <section class="cp-diff__field">
            <h3><?= cp_h($item['label']) ?></h3>
            <?php if ($item['kind'] === 'image'): ?>
                <div class="cp-diff__img">
                    <?php if ($item['old']): ?><span class="cp-diff__photo cp-del"><img src="<?= cp_h($item['old']) ?>" alt="<?= $lang === 'en' ? 'Before' : 'Prima' ?>" loading="lazy"></span><?php endif; ?>
                    <?php if ($item['old'] && $item['new']): ?><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><?php endif; ?>
                    <?php if ($item['new']): ?><span class="cp-diff__photo cp-add"><img src="<?= cp_h($item['new']) ?>" alt="<?= $lang === 'en' ? 'After' : 'Dopo' ?>" loading="lazy"></span><?php endif; ?>
                    <?php if (!$item['new']): ?><span class="cp-muted"><?= $lang === 'en' ? 'Photo removed' : 'Foto tolta' ?></span><?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($item['rows'] as $row): ?>
                    <div class="cp-diff__row<?= $row['op'] === 'del' ? ' cp-del' : ($row['op'] === 'add' ? ' cp-add' : ($row['op'] === 'gap' ? ' cp-gap' : '')) ?>"><?php if ($row['op'] === 'del' || $row['op'] === 'add'): ?><span class="cp-sr"><?= $row['op'] === 'del' ? ($lang === 'en' ? 'Removed: ' : 'Tolto: ') : ($lang === 'en' ? 'Added: ' : 'Aggiunto: ') ?></span><?php endif; ?><?= $item['kind'] === 'text' && $row['op'] !== 'gap' ? cp_resolve_wiki_links($row['html'], $mysqli, $lang) : $row['html'] ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endforeach;
}
