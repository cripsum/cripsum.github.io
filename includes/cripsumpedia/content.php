<?php
/**
 * Cripsumpedia — il testo delle voci, chi cita chi, gli indirizzi vecchi.
 *
 * Il markdown si trasforma in HTML quando la voce viene salvata e il risultato
 * resta in content_html: la pagina non lo rifà a ogni visita. I collegamenti
 * alle altre voci invece si aggiungono a ogni visita, perché dipendono dalle
 * voci che esistono in quel momento (una voce nuova si collega da sola anche
 * nei testi scritti prima).
 *
 * L'HTML salvato comincia con un timbro che dice con quale versione del
 * trasformatore è stato fatto. Quando il trasformatore cambia si alza il
 * numero: i testi col timbro vecchio vengono rifatti al volo e riscritti alla
 * prima apertura del pannello da parte dello staff (cp_v2_backfill).
 */

// 2: i titoletti hanno un id, per l'indice della voce (fase 2).
// 3: id uguali su ogni sistema («Curiosità» → «curiosita»).
const CP_RENDER_STAMP = '<!--cp-render:3-->';

/** Il testo in HTML col timbro, pronto da salvare. Vuoto se non c'è testo. */
function cp_render_for_storage(string $markdown): string
{
    $html = cp_markdown_render($markdown);
    return $html === '' ? '' : CP_RENDER_STAMP . $html;
}

/** L'HTML salvato, se è stato fatto con il trasformatore di adesso. */
function cp_stored_html(?string $stored): ?string
{
    if ($stored === null) return null;
    if ($stored === '') return '';
    if (!str_starts_with($stored, CP_RENDER_STAMP)) return null;
    return substr($stored, strlen(CP_RENDER_STAMP));
}

/**
 * Il testo di una voce da mostrare, nella lingua giusta e con i collegamenti
 * alle altre voci. La lingua segue cp_i18n(): l'inglese solo se è stato scritto.
 */
function cp_entry_content_html(mysqli $mysqli, array $entry, string $lang): string
{
    $useEn = $lang === 'en' && trim((string)($entry['content_md_en'] ?? '')) !== '';
    $markdown = (string)($useEn ? $entry['content_md_en'] : ($entry['content_md'] ?? ''));
    $html = cp_stored_html($useEn ? ($entry['content_html_en'] ?? null) : ($entry['content_html'] ?? null));
    if ($html === null) {
        $html = cp_markdown_render($markdown);
    }
    return cp_autolink_html($html, $mysqli, $lang, (int)($entry['id'] ?? 0));
}

/** Rifà e salva l'HTML di una voce, nelle due lingue. */
function cp_store_entry_html(mysqli $mysqli, int $entryId): bool
{
    if (!cp_v2_ready($mysqli)) return false;

    $stmt = $mysqli->prepare("SELECT content_md, content_md_en FROM cripsumpedia_entries WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $entryId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;

    $html = cp_render_for_storage((string)($row['content_md'] ?? ''));
    $markdownEn = trim((string)($row['content_md_en'] ?? ''));
    $htmlEn = $markdownEn !== '' ? cp_render_for_storage($markdownEn) : null;

    $stmt = $mysqli->prepare("UPDATE cripsumpedia_entries SET content_html = ?, content_html_en = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('ssi', $html, $htmlEn, $entryId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ── Chi cita chi ────────────────────────────────────────────────────────────

/** Il testo di una voce senza tag, nelle due lingue: è lì che si cercano i nomi. */
function cp_mention_haystack(array $entry): string
{
    $text = '';
    foreach (['content_md', 'content_md_en'] as $field) {
        $markdown = trim((string)($entry[$field] ?? ''));
        if ($markdown !== '') {
            // Ogni tag diventa uno spazio: tolto e basta, «Cripsum<br>e» darebbe «Cripsume».
            $plain = preg_replace('~<[^>]*>~', ' ', cp_markdown_render($markdown)) ?? '';
            $text .= ' ' . html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return $text;
}

/**
 * Gli id delle voci nominate in un testo. Stesse regole dei collegamenti
 * automatici (cp_autolink_html): titoli e soprannomi delle voci pubblicate,
 * parola intera, maiuscole e minuscole uguali.
 *
 * @param array<int, array{term: string, id: int}> $terms
 */
function cp_mentioned_ids(string $text, array $terms, int $selfId): array
{
    $ids = [];
    if (trim($text) === '') return $ids;

    // Un'espressione sola per tutti i nomi diventerebbe enorme: si va a blocchi.
    foreach (array_chunk($terms, 200) as $chunk) {
        $lookup = [];
        $parts = [];
        foreach ($chunk as $term) {
            if ((int)$term['id'] === $selfId) continue;
            $lookup[mb_strtolower($term['term'], 'UTF-8')] = (int)$term['id'];
            $parts[] = preg_quote($term['term'], '~');
        }
        if (!$parts) continue;
        $pattern = '~(?<![\p{L}\p{N}_])(' . implode('|', $parts) . ')(?![\p{L}\p{N}_])~iu';
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $id = $lookup[mb_strtolower($match, 'UTF-8')] ?? 0;
                if ($id > 0) $ids[$id] = true;
            }
        }
    }
    return array_map('intval', array_keys($ids));
}

/** Tutti i nomi delle voci pubblicate, in italiano e in inglese, senza doppioni. */
function cp_all_lore_terms(mysqli $mysqli): array
{
    $terms = [];
    foreach (['it', 'en'] as $lang) {
        foreach (cp_lore_terms($mysqli, $lang) as $term) {
            $terms[mb_strtolower($term['term'], 'UTF-8')] = ['term' => $term['term'], 'id' => (int)$term['id']];
        }
    }
    // I nomi più lunghi prima: «xalx e super mario bros» vince su «xalx».
    uasort($terms, static fn(array $a, array $b): int => mb_strlen($b['term'], 'UTF-8') <=> mb_strlen($a['term'], 'UTF-8'));
    return array_values($terms);
}

/** Riscrive le voci citate da una voce. */
function cp_set_outgoing_mentions(mysqli $mysqli, int $entryId, array $targetIds): void
{
    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_mentions WHERE entry_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }
    $stmt = $mysqli->prepare("INSERT IGNORE INTO cripsumpedia_mentions (entry_id, target_id) VALUES (?, ?)");
    if (!$stmt) return;
    foreach ($targetIds as $targetId) {
        $targetId = (int)$targetId;
        $stmt->bind_param('ii', $entryId, $targetId);
        $stmt->execute();
    }
    $stmt->close();
}

/**
 * Dopo il salvataggio di una voce: cosa cita lei (il suo testo può essere
 * cambiato) e chi cita lei (possono essere cambiati il titolo o i soprannomi).
 * Le altre coppie non possono essere cambiate da questo salvataggio.
 */
function cp_refresh_mentions(mysqli $mysqli, int $entryId): void
{
    if (!cp_v2_ready($mysqli)) return;

    $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
    if (!$entry) return;

    $terms = cp_all_lore_terms($mysqli);
    cp_set_outgoing_mentions($mysqli, $entryId, cp_mentioned_ids(cp_mention_haystack($entry), $terms, $entryId));

    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_mentions WHERE target_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $stmt->close();
    }

    $ownTerms = array_values(array_filter($terms, static fn(array $term): bool => $term['id'] === $entryId));
    if (!$ownTerms) return; // non pubblicata, o nomi troppo corti: nessuno la può citare

    // Prima un filtro grossolano nel database, poi la parola intera qui.
    $likes = [];
    $params = [];
    foreach ($ownTerms as $term) {
        $likes[] = '(content_md LIKE ? OR content_md_en LIKE ?)';
        $like = '%' . addcslashes($term['term'], '%_\\') . '%';
        array_push($params, $like, $like);
    }
    $sql = "SELECT id, content_md, content_md_en FROM cripsumpedia_entries
            WHERE id <> ? AND deleted_at IS NULL AND (" . implode(' OR ', $likes) . ")";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return;
    $stmt->bind_param('i' . str_repeat('s', count($params)), $entryId, ...$params);
    $stmt->execute();
    $candidates = cp_stmt_all($stmt);
    $stmt->close();

    $insert = $mysqli->prepare("INSERT IGNORE INTO cripsumpedia_mentions (entry_id, target_id) VALUES (?, ?)");
    if (!$insert) return;
    foreach ($candidates as $candidate) {
        $sourceId = (int)$candidate['id'];
        if (cp_mentioned_ids(cp_mention_haystack($candidate), $ownTerms, $sourceId)) {
            $insert->bind_param('ii', $sourceId, $entryId);
            $insert->execute();
        }
    }
    $insert->close();
}

// ── Indirizzi vecchi ────────────────────────────────────────────────────────

/**
 * Una voce ha cambiato titolo (o tipo): il suo indirizzo vecchio deve portare
 * a quello nuovo, perché i link sono già stati condivisi su Discord.
 * L'indirizzo che la voce ha adesso non può più essere un rimando.
 */
function cp_remember_old_slug(mysqli $mysqli, int $entryId, string $oldType, string $oldSlug, string $newType, string $newSlug): void
{
    if (!cp_v2_ready($mysqli)) return;

    if ($oldSlug !== '' && ($oldType !== $newType || $oldSlug !== $newSlug)) {
        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_slug_redirects (entry_type, old_slug, entry_id, created_at)
                                  VALUES (?, ?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE entry_id = VALUES(entry_id), created_at = NOW()");
        if ($stmt) {
            $stmt->bind_param('ssi', $oldType, $oldSlug, $entryId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $mysqli->prepare("DELETE FROM cripsumpedia_slug_redirects WHERE entry_type = ? AND old_slug = ?");
    if ($stmt) {
        $stmt->bind_param('ss', $newType, $newSlug);
        $stmt->execute();
        $stmt->close();
    }
}

/** La voce pubblicata a cui porta un indirizzo vecchio, se c'è. */
function cp_find_slug_redirect(mysqli $mysqli, ?string $type, string $slug): ?array
{
    $type = cp_normalize_type($type);
    if (!$type || $slug === '' || !cp_v2_ready($mysqli)) return null;

    $stmt = $mysqli->prepare("SELECT e.*
                              FROM cripsumpedia_slug_redirects r
                              INNER JOIN cripsumpedia_entries e ON e.id = r.entry_id
                              WHERE r.entry_type = ? AND r.old_slug = ?
                                AND e.status = 'published' AND e.deleted_at IS NULL
                              LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('ss', $type, $slug);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $entry ?: null;
}
