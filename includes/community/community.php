<?php
/**
 * Cripsum™ — Shitpost e Top Rimasti: il nucleo condiviso.
 *
 * Le due sezioni sono lo stesso meccanismo (un post con uno o più media,
 * commenti, salvataggi, segnalazioni) su due tabelle diverse: `shitposts`
 * con le reazioni, `toprimasti` con i voti e la classifica. Qui stanno le
 * regole comuni: chi può vedere cosa, come si legge il feed, i limiti di
 * frequenza, gli avvisi all'autore.
 *
 * Lo schema si aggiorna a mano (migrations/2026_10_03_community_v3.sql):
 * ogni funzione nuova controlla che la sua colonna o tabella esista e,
 * se manca, si spegne senza rompere il resto.
 */

require_once __DIR__ . '/../content_v2_helpers.php';

/** Le reazioni degli shitpost. La chiave finisce in `shitpost_likes.reazione`. */
const CM_REACTIONS = [
    'fire' => '🔥',
    'lol' => '😂',
    'skull' => '💀',
    'cry' => '😭',
    'moai' => '🗿',
    'heart' => '❤️',
    'clown' => '🤡',
    'cento' => '💯',
];

const CM_MAX_TAGS = 3;
const CM_TAG_LENGTH = 20;
const CM_COMMENT_LENGTH = 500;
const CM_PAGE_SIZE = 18;

/** Le regole che il pannello può cambiare, con il valore di partenza. */
const CM_DEFAULT_SETTINGS = [
    'shitpost_aperto' => 1,
    'rimasto_aperto' => 1,
    'auto_approva' => 0,
    'auto_approva_soglia' => 5,
    'max_post_giorno' => 10,
    'max_in_attesa' => 5,
    'max_media' => 6,
];

// ── Lingua e risposte ───────────────────────────────────────────────────

/**
 * La lingua della pagina che sta chiamando: la dice lei, o il suo indirizzo.
 * Le pagine, che la sanno già, la fissano passandola.
 */
function cm_lang(?string $set = null): string
{
    static $lang = null;
    if ($set !== null) {
        return $lang = ($set === 'en' ? 'en' : 'it');
    }
    if ($lang !== null) {
        return $lang;
    }

    $header = strtolower((string)($_SERVER['HTTP_X_CRIPSUM_LANG'] ?? ''));
    if ($header === 'it' || $header === 'en') {
        return $lang = $header;
    }

    $referer = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
    return $lang = (strncmp($referer, '/en/', 4) === 0 ? 'en' : 'it');
}

function cm_t(string $it, string $en): string
{
    return cm_lang() === 'en' ? $en : $it;
}

/**
 * Un errore imprevisto: il dettaglio va nel log del server, a chi usa il
 * sito arriva una frase generica. Prima il messaggio di MySQL finiva
 * dritto nel browser.
 */
function cm_crash(string $where, Throwable $e): void
{
    error_log('[community] ' . $where . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    cv2_fail(cm_t('Qualcosa è andato storto. Riprova tra poco.', 'Something went wrong. Try again shortly.'), 500);
}

// ── Com'è fatto il database ─────────────────────────────────────────────

/** Colonne di una tabella (nome → tipo), una sola domanda a MySQL per tabella. */
function cm_columns(mysqli $mysqli, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return $cache[$table] = [];
    }

    $columns = [];
    try {
        $result = $mysqli->query('SHOW COLUMNS FROM `' . $table . '`');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[(string)$row['Field']] = (string)$row['Type'];
            }
            $result->free();
        }
    } catch (Throwable $e) {
        $columns = [];
    }

    return $cache[$table] = $columns;
}

function cm_has(mysqli $mysqli, string $table, ?string $column = null): bool
{
    $columns = cm_columns($mysqli, $table);
    return $column === null ? $columns !== [] : isset($columns[$column]);
}

/** La tabella dei commenti di ciascuna sezione, con i nomi delle sue colonne. */
function cm_comment_meta(string $type): array
{
    if ($type === 'rimasto') {
        return [
            'table' => 'content_comments',
            'post' => 'post_id',
            'user' => 'user_id',
            'text' => 'comment',
            'created' => 'created_at',
            'scope' => " AND c.content_type = 'rimasto'",
        ];
    }

    return [
        'table' => 'commenti_shitpost',
        'post' => 'id_shitpost',
        'user' => 'id_utente',
        'text' => 'commento',
        'created' => 'data_commento',
        'scope' => '',
    ];
}

/** Che cosa c'è e che cosa manca, per una sezione. */
function cm_schema(mysqli $mysqli, string $type): array
{
    static $cache = [];
    if (isset($cache[$type])) {
        return $cache[$type];
    }

    $table = cv2_meta($type)['table'];
    $comments = cm_comment_meta($type)['table'];
    $tagType = cm_columns($mysqli, $table)['tag'] ?? '';

    return $cache[$type] = [
        'ready' => cm_has($mysqli, $table),
        'views' => cm_has($mysqli, $table, 'views'),
        'tag' => $tagType !== '',
        'tag_length' => preg_match('/\((\d+)\)/', $tagType, $m) ? (int)$m[1] : 40,
        'spoiler' => cm_has($mysqli, $table, 'is_spoiler'),
        'updated' => cm_has($mysqli, $table, 'updated_at'),
        'dims' => cm_has($mysqli, $table, 'media_w'),
        'poster' => cm_has($mysqli, $table, 'anteprima'),
        'extra' => cm_has($mysqli, $table, 'media_extra') && cm_has($mysqli, 'content_media'),
        'approved_at' => cm_has($mysqli, $table, 'approvato_il'),
        'announced_at' => cm_has($mysqli, $table, 'annunciato_il'),
        'likes' => cm_has($mysqli, 'shitpost_likes'),
        'reactions' => $type === 'shitpost' && cm_has($mysqli, 'shitpost_likes', 'reazione'),
        'votes' => cm_has($mysqli, 'voti_toprimasti'),
        'comments' => cm_has($mysqli, $comments),
        'replies' => cm_has($mysqli, $comments, 'parent_id'),
        'saves' => cm_has($mysqli, 'content_saves'),
        'reports' => cm_has($mysqli, 'content_reports'),
        'views_table' => cm_has($mysqli, 'content_views'),
        'viewer_key' => cm_has($mysqli, 'content_views', 'viewer_key'),
        'actions' => cm_has($mysqli, 'content_azioni'),
    ];
}

// ── Regole ──────────────────────────────────────────────────────────────

function cm_settings(mysqli $mysqli): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = CM_DEFAULT_SETTINGS;
    if (!cm_has($mysqli, 'community_impostazioni')) {
        return $settings;
    }

    try {
        $result = $mysqli->query('SELECT chiave, valore FROM community_impostazioni');
        while ($result && ($row = $result->fetch_assoc())) {
            if (array_key_exists($row['chiave'], $settings)) {
                $settings[$row['chiave']] = (int)$row['valore'];
            }
        }
    } catch (Throwable $e) {
        error_log('[community] regole: ' . $e->getMessage());
    }

    $settings['max_media'] = max(1, min(10, $settings['max_media']));
    $settings['max_post_giorno'] = max(1, min(100, $settings['max_post_giorno']));
    $settings['max_in_attesa'] = max(1, min(50, $settings['max_in_attesa']));
    $settings['auto_approva_soglia'] = max(1, min(500, $settings['auto_approva_soglia']));

    return $settings;
}

/** Quanti media può avere un post: uno solo finché manca la tabella degli altri. */
function cm_max_media(mysqli $mysqli, string $type): int
{
    return cm_schema($mysqli, $type)['extra'] ? cm_settings($mysqli)['max_media'] : 1;
}

/** Post approvati di un utente, sulle due sezioni. */
function cm_approved_count(mysqli $mysqli, int $userId): int
{
    $total = 0;
    foreach (['shitposts', 'toprimasti'] as $table) {
        if (!cm_has($mysqli, $table)) {
            continue;
        }
        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM `$table` WHERE id_utente = ? AND approvato = 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $total += (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        }
    }
    return $total;
}

/** Lo staff pubblica subito; gli altri solo se la regola è accesa e hanno abbastanza post approvati. */
function cm_auto_approve(mysqli $mysqli, array $user): bool
{
    if (cv2_is_admin($user)) {
        return true;
    }

    $settings = cm_settings($mysqli);
    if (empty($settings['auto_approva'])) {
        return false;
    }

    return cm_approved_count($mysqli, (int)$user['id']) >= $settings['auto_approva_soglia'];
}

// ── Tag ─────────────────────────────────────────────────────────────────

function cm_tag_clean(string $tag): string
{
    $tag = mb_strtolower(trim($tag), 'UTF-8');
    $tag = ltrim($tag, '#');
    $tag = preg_replace('/\s+/u', '-', $tag) ?? '';
    $tag = preg_replace('/[^\p{L}\p{N}_-]/u', '', $tag) ?? '';
    return trim(mb_substr($tag, 0, CM_TAG_LENGTH, 'UTF-8'), '-_');
}

/** Da quello che arriva dal form (lista o testo con virgole) ai tag puliti. */
function cm_tags_parse($raw): array
{
    $parts = is_array($raw) ? $raw : preg_split('/[,\n]+/u', (string)$raw);
    $tags = [];
    foreach ((array)$parts as $part) {
        $tag = cm_tag_clean((string)$part);
        if ($tag !== '' && !in_array($tag, $tags, true)) {
            $tags[] = $tag;
        }
        if (count($tags) >= CM_MAX_TAGS) {
            break;
        }
    }
    return $tags;
}

/** I tag come stanno nella colonna: separati da virgola, entro la sua lunghezza. */
function cm_tags_store(array $tags, int $maxLength): ?string
{
    while ($tags && mb_strlen(implode(',', $tags), 'UTF-8') > $maxLength) {
        array_pop($tags);
    }
    return $tags ? implode(',', $tags) : null;
}

// ── Chi può vedere un post ──────────────────────────────────────────────

function cm_post_basic(mysqli $mysqli, string $type, int $postId): ?array
{
    $meta = cv2_meta($type);
    if ($postId <= 0 || !cm_has($mysqli, $meta['table'])) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT id, id_utente, titolo, approvato FROM `{$meta['table']}` WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'id_utente' => (int)$row['id_utente'],
        'titolo' => (string)$row['titolo'],
        'approvato' => (int)$row['approvato'],
    ];
}

/**
 * Il post, oppure la risposta di errore. Un post in attesa esiste solo
 * per chi l'ha scritto e per lo staff; `$public` chiede che sia online
 * (reazioni, commenti, salvataggi e segnalazioni valgono solo lì).
 */
function cm_require_post(mysqli $mysqli, string $type, int $postId, ?array $user, bool $public = true): array
{
    $post = cm_post_basic($mysqli, $type, $postId);
    $missing = cm_t('Questo post non esiste più.', 'This post no longer exists.');

    if (!$post) {
        cv2_fail($missing, 404);
    }

    if ($post['approvato'] === 1) {
        return $post;
    }

    $own = $user && (cv2_is_admin($user) || (int)$user['id'] === $post['id_utente']);
    if (!$own) {
        cv2_fail($missing, 404);
    }
    if ($public) {
        cv2_fail(cm_t('Il post non è ancora online.', 'The post is not online yet.'), 409);
    }

    return $post;
}

// ── Limiti di frequenza ─────────────────────────────────────────────────

/** Freno leggero sulla sessione, per le azioni che non lasciano righe da contare. */
function cm_throttle(string $key, int $max, int $seconds): void
{
    $now = time();
    $hits = array_values(array_filter(
        (array)($_SESSION['cm_throttle'][$key] ?? []),
        static fn($at) => (int)$at > $now - $seconds
    ));

    if (count($hits) >= $max) {
        cv2_fail(cm_t('Stai andando troppo veloce. Aspetta un attimo.', 'You are going too fast. Wait a moment.'), 429);
    }

    $hits[] = $now;
    $_SESSION['cm_throttle'][$key] = $hits;
}

function cm_count(mysqli $mysqli, string $sql, string $types = '', array $params = []): int
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $value = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $value;
}

/**
 * Si può pubblicare adesso? I conti si fanno sulle righe del database e
 * non sulla sessione: aprire un'altra scheda o rifare l'accesso non
 * azzera niente.
 */
function cm_check_can_post(mysqli $mysqli, string $type, array $user): void
{
    $settings = cm_settings($mysqli);
    $isAdmin = cv2_is_admin($user);

    if (!$isAdmin && empty($settings[$type . '_aperto'])) {
        cv2_fail(cm_t('Le pubblicazioni sono chiuse per il momento.', 'Posting is closed for now.'), 403);
    }
    if ($isAdmin) {
        return;
    }

    $table = cv2_meta($type)['table'];
    $userId = (int)$user['id'];

    if (cm_count($mysqli, "SELECT COUNT(*) FROM `$table` WHERE id_utente = ? AND data_creazione > NOW() - INTERVAL 30 SECOND", 'i', [$userId]) > 0) {
        cv2_fail(cm_t('Hai appena pubblicato: aspetta qualche secondo.', 'You just posted: wait a few seconds.'), 429);
    }

    $today = 0;
    $pending = 0;
    foreach (['shitposts', 'toprimasti'] as $each) {
        if (!cm_has($mysqli, $each)) {
            continue;
        }
        $today += cm_count($mysqli, "SELECT COUNT(*) FROM `$each` WHERE id_utente = ? AND data_creazione >= CURDATE()", 'i', [$userId]);
        $pending += cm_count($mysqli, "SELECT COUNT(*) FROM `$each` WHERE id_utente = ? AND approvato = 0", 'i', [$userId]);
    }

    if ($today >= $settings['max_post_giorno']) {
        cv2_fail(cm_t('Hai raggiunto il limite di post per oggi.', 'You reached today\'s post limit.'), 429);
    }
    if ($pending >= $settings['max_in_attesa']) {
        cv2_fail(cm_t(
            'Hai già ' . $pending . ' post in attesa: aspetta che vengano controllati.',
            'You already have ' . $pending . ' posts waiting for review.'
        ), 429);
    }
}

/**
 * Vero la prima volta che un utente fa una certa azione su un post in un
 * giorno. Serve alle missioni: togliere e rimettere lo stesso like non
 * deve farle avanzare ogni volta.
 */
function cm_action_once(mysqli $mysqli, int $userId, string $type, int $postId, string $action): bool
{
    if (cm_schema($mysqli, $type)['actions']) {
        $stmt = $mysqli->prepare('INSERT IGNORE INTO content_azioni (user_id, content_type, post_id, azione, giorno) VALUES (?, ?, ?, ?, CURDATE())');
        if ($stmt) {
            $stmt->bind_param('isis', $userId, $type, $postId, $action);
            $stmt->execute();
            $first = $stmt->affected_rows > 0;
            $stmt->close();

            if (random_int(1, 60) === 1) {
                $mysqli->query('DELETE FROM content_azioni WHERE giorno < CURDATE() - INTERVAL 2 DAY');
            }
            return $first;
        }
    }

    $key = date('Ymd') . '|' . $type . '|' . $postId . '|' . $action;
    if (!empty($_SESSION['cm_actions'][$key])) {
        return false;
    }
    $_SESSION['cm_actions'] = array_slice((array)($_SESSION['cm_actions'] ?? []), -300, null, true);
    $_SESSION['cm_actions'][$key] = 1;
    return true;
}

/** Fa avanzare le missioni senza che un loro errore tocchi l'azione appena salvata. */
function cm_track(mysqli $mysqli, int $userId, array $events): void
{
    if (!function_exists('trackMissionProgress')) {
        return;
    }

    foreach ($events as $event => $countStats) {
        try {
            trackMissionProgress($mysqli, $userId, (string)$event, 1, (bool)$countStats);
        } catch (Throwable $e) {
            error_log('[community] missione ' . $event . ': ' . $e->getMessage());
        }
    }
}

// ── Indirizzi ───────────────────────────────────────────────────────────

function cm_post_url(string $type, int $postId, string $lang = 'it'): string
{
    return '/' . ($lang === 'en' ? 'en' : 'it') . '/' . ($type === 'rimasto' ? 'rimasti' : 'shitpost') . '?post=' . $postId;
}

/** `$n` = 0 è il primo media (quello di sempre); `$variant`: '', 'thumb', 'poster'. */
function cm_media_url(string $type, int $postId, int $n = 0, string $variant = ''): string
{
    return '/api/content/media.php?type=' . $type . '&id=' . $postId
        . ($n > 0 ? '&n=' . $n : '')
        . ($variant !== '' ? '&v=' . $variant : '');
}

function cm_media_kind(?string $mime): string
{
    $mime = (string)$mime;
    if (strncmp($mime, 'video/', 6) === 0) {
        return 'video';
    }
    return $mime === 'image/gif' ? 'gif' : 'image';
}

function cm_media_item(string $type, int $postId, int $n, ?string $mime, $w, $h, $duration, bool $hasPoster): array
{
    $kind = cm_media_kind($mime);

    return [
        'n' => $n,
        'kind' => $kind,
        'w' => (int)$w ?: null,
        'h' => (int)$h ?: null,
        'duration' => (int)$duration ?: null,
        'url' => cm_media_url($type, $postId, $n),
        // Per le immagini la miniatura si genera sempre; per i video solo
        // se chi ha caricato il file ha mandato anche il fotogramma.
        'thumb' => $kind === 'video'
            ? ($hasPoster ? cm_media_url($type, $postId, $n, 'poster') : null)
            : cm_media_url($type, $postId, $n, 'thumb'),
    ];
}

// ── Feed ────────────────────────────────────────────────────────────────

/**
 * Una pagina di post.
 *
 * Opzioni: sort (recent, trending, top, comments, oldest), period (all,
 * month, week: solo Top Rimasti), q, tag, mine, saved, status (staff:
 * approved, pending, all), reported (staff: solo i segnalati), page, limit,
 * post (uno solo, per i link diretti).
 *
 * @return array{posts: array, total: int, pages: int, page: int}
 */
function cm_feed(mysqli $mysqli, string $type, array $options, ?array $user): array
{
    $meta = cv2_meta($type);
    $schema = cm_schema($mysqli, $type);
    $empty = ['posts' => [], 'total' => 0, 'pages' => 1, 'page' => 1];

    if (!$schema['ready']) {
        return $empty;
    }

    $table = '`' . $meta['table'] . '`';
    $blob = '`' . $meta['blob'] . '`';
    $mime = '`' . $meta['mime'] . '`';
    $userId = (int)($user['id'] ?? 0);
    $isAdmin = cv2_is_admin($user);
    $isRimasto = $type === 'rimasto';

    $page = max(1, (int)($options['page'] ?? 1));
    $limit = max(1, min(60, (int)($options['limit'] ?? CM_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    $sort = (string)($options['sort'] ?? 'recent');
    $period = (string)($options['period'] ?? 'all');
    $single = (int)($options['post'] ?? 0);
    $mine = !empty($options['mine']) && $userId > 0;
    $saved = !empty($options['saved']) && $userId > 0 && $schema['saves'];
    $q = trim((string)($options['q'] ?? ''));
    $tag = cm_tag_clean((string)($options['tag'] ?? ''));
    $status = (string)($options['status'] ?? 'approved');

    $where = [];
    $params = [];
    $types = '';

    if ($single > 0) {
        $where[] = 'p.id = ?';
        $params[] = $single;
        $types .= 'i';
        if (!$isAdmin) {
            $where[] = $userId > 0 ? "(p.approvato = 1 OR p.id_utente = $userId)" : 'p.approvato = 1';
        }
    } elseif ($mine) {
        $where[] = "p.id_utente = $userId";
    } elseif ($isAdmin && !empty($options['reported']) && $schema['reports']) {
        // Pannello: i post con almeno una segnalazione ancora aperta, online o no.
        $where[] = "EXISTS (SELECT 1 FROM content_reports rp WHERE rp.content_type = '$type' AND rp.post_id = p.id AND rp.status = 'open')";
    } elseif ($isAdmin && $status === 'pending') {
        $where[] = 'p.approvato = 0';
    } elseif (!($isAdmin && $status === 'all')) {
        $where[] = 'p.approvato = 1';
    }

    if ($q !== '' && $single <= 0) {
        $like = '%' . addcslashes(mb_substr($q, 0, 80, 'UTF-8'), '\\%_') . '%';
        $parts = ['p.titolo LIKE ?', 'p.descrizione LIKE ?', 'u.username LIKE ?'];
        array_push($params, $like, $like, $like);
        $types .= 'sss';
        if ($isRimasto) {
            $parts[] = 'p.motivazione LIKE ?';
            $params[] = $like;
            $types .= 's';
        }
        if ($schema['tag']) {
            $parts[] = 'p.`tag` LIKE ?';
            $params[] = $like;
            $types .= 's';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    if ($tag !== '' && $schema['tag'] && $single <= 0) {
        $where[] = "FIND_IN_SET(?, REPLACE(LOWER(COALESCE(p.`tag`, '')), ', ', ','))";
        $params[] = $tag;
        $types .= 's';
    }

    if ($saved && $single <= 0) {
        $where[] = "EXISTS (SELECT 1 FROM content_saves cs WHERE cs.content_type = '$type' AND cs.post_id = p.id AND cs.user_id = $userId)";
    }

    // ── Punteggio e ordine ──────────────────────────────────────────────
    $trendSql = '0';
    $ranked = false;

    if ($isRimasto) {
        $scoreSql = 'COALESCE(p.reazioni, 0)';
        $since = $period === 'week' ? date('Y-m-d 00:00:00', strtotime('monday this week'))
            : ($period === 'month' ? date('Y-m-01 00:00:00') : '');

        if ($since !== '' && $sort === 'top' && $schema['votes'] && $single <= 0) {
            // Classifica del periodo: contano i voti dati da quella data.
            $scoreSql = "(SELECT COUNT(*) FROM voti_toprimasti pv WHERE pv.id_post = p.id AND pv.data_voto >= '$since')";
            $where[] = "EXISTS (SELECT 1 FROM voti_toprimasti pe WHERE pe.id_post = p.id AND pe.data_voto >= '$since')";
        }
        $likedSql = $userId > 0 && $schema['votes']
            ? "EXISTS (SELECT 1 FROM voti_toprimasti mv WHERE mv.id_post = p.id AND mv.id_utente = $userId)"
            : '0';
    } else {
        $scoreSql = $schema['likes'] ? '(SELECT COUNT(*) FROM shitpost_likes sl WHERE sl.id_shitpost = p.id)' : '0';
        $likedSql = '0';

        if ($sort === 'trending') {
            // In tendenza: quanto si è mosso il post negli ultimi sette giorni.
            $week = date('Y-m-d H:i:s', time() - 7 * 86400);
            $recentLikes = $schema['likes'] ? "(SELECT COUNT(*) FROM shitpost_likes tl WHERE tl.id_shitpost = p.id AND tl.created_at >= '$week')" : '0';
            $recentComments = $schema['comments'] ? "(SELECT COUNT(*) FROM commenti_shitpost tc WHERE tc.id_shitpost = p.id AND tc.data_commento >= '$week')" : '0';
            $trendSql = "($recentLikes + 2 * $recentComments)";
        }
    }

    $comment = cm_comment_meta($type);
    $commentsSql = $schema['comments']
        ? "(SELECT COUNT(*) FROM `{$comment['table']}` c WHERE c.`{$comment['post']}` = p.id{$comment['scope']})"
        : '0';

    $savedSql = $userId > 0 && $schema['saves']
        ? "EXISTS (SELECT 1 FROM content_saves sv WHERE sv.content_type = '$type' AND sv.post_id = p.id AND sv.user_id = $userId)"
        : '0';

    switch ($sort) {
        case 'top':
            $order = 'score DESC, p.data_creazione DESC';
            // I numeri di classifica hanno senso solo sulla lista intera.
            $ranked = $isRimasto && $q === '' && $tag === '' && !$mine && !$saved && $single <= 0 && $status === 'approved';
            break;
        case 'comments':
            $order = 'comments_count DESC, p.data_creazione DESC';
            break;
        case 'trending':
            $order = $isRimasto ? 'p.data_creazione DESC' : 'trend DESC, p.data_creazione DESC';
            break;
        case 'oldest':
            $order = 'p.data_creazione ASC';
            break;
        default:
            $order = 'p.data_creazione DESC';
    }
    if ($mine) {
        // In cima quelli ancora in attesa: sono il motivo per cui si apre «I miei».
        $order = 'p.approvato ASC, p.data_creazione DESC';
        $ranked = false;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM $table p LEFT JOIN utenti u ON u.id = p.id_utente $whereSql");
    if (!$stmt) {
        throw new RuntimeException('conteggio feed: ' . $mysqli->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();

    if ($total === 0) {
        return $empty;
    }

    $sql = "
        SELECT
            p.id,
            p.id_utente,
            p.titolo,
            p.descrizione,
            " . ($isRimasto ? 'p.motivazione' : 'NULL') . " AS extra_text,
            p.data_creazione,
            UNIX_TIMESTAMP(p.data_creazione) AS ts,
            p.approvato,
            p.$mime AS media_mime,
            (p.$blob IS NOT NULL) AS has_media,
            " . ($schema['dims'] ? 'p.media_w, p.media_h, p.media_durata' : 'NULL AS media_w, NULL AS media_h, NULL AS media_durata') . ",
            " . ($schema['poster'] ? '(p.anteprima IS NOT NULL)' : '0') . " AS has_poster,
            " . ($schema['extra'] ? 'p.media_extra' : '0') . " AS media_extra,
            " . ($schema['tag'] ? 'p.`tag`' : 'NULL') . " AS tag,
            " . ($schema['spoiler'] ? 'COALESCE(p.is_spoiler, 0)' : '0') . " AS is_spoiler,
            " . ($schema['views'] ? 'COALESCE(p.views, 0)' : '0') . " AS views,
            $scoreSql AS score,
            $trendSql AS trend,
            $commentsSql AS comments_count,
            $likedSql AS user_liked,
            $savedSql AS user_saved,
            u.username,
            COALESCE(u.ruolo, 'utente') AS ruolo,
            COALESCE(u.is_premium, 0) AS is_premium
        FROM $table p
        LEFT JOIN utenti u ON u.id = p.id_utente
        $whereSql
        ORDER BY $order
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('feed: ' . $mysqli->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $posts = cm_shape_posts($mysqli, $type, $rows, $user);

    if ($ranked) {
        foreach ($posts as $index => &$post) {
            $post['rank'] = $offset + $index + 1;
        }
        unset($post);
    }

    return [
        'posts' => $posts,
        'total' => $total,
        'pages' => max(1, (int)ceil($total / $limit)),
        'page' => $page,
    ];
}

/** Dalle righe del database alla forma che la pagina si aspetta. */
function cm_shape_posts(mysqli $mysqli, string $type, array $rows, ?array $user): array
{
    if (!$rows) {
        return [];
    }

    $schema = cm_schema($mysqli, $type);
    $userId = (int)($user['id'] ?? 0);
    $isAdmin = cv2_is_admin($user);
    $lang = cm_lang();
    $ids = array_map(static fn($row) => (int)$row['id'], $rows);
    $list = implode(',', $ids);

    // Media oltre il primo.
    $extras = [];
    if ($schema['extra'] && array_filter($rows, static fn($row) => (int)$row['media_extra'] > 0)) {
        $result = $mysqli->query("
            SELECT post_id, posizione, mime, larghezza, altezza, durata, (anteprima IS NOT NULL) AS has_poster
            FROM content_media
            WHERE content_type = '$type' AND post_id IN ($list)
            ORDER BY post_id, posizione
        ");
        while ($result && ($media = $result->fetch_assoc())) {
            $extras[(int)$media['post_id']][] = $media;
        }
    }

    // Reazioni: quante per tipo, e quale ha messo chi guarda.
    $reactions = [];
    $mine = [];
    if ($type === 'shitpost' && $schema['likes']) {
        $column = $schema['reactions'] ? 'reazione' : "'fire'";
        $result = $mysqli->query("SELECT id_shitpost, $column AS reazione, COUNT(*) AS n FROM shitpost_likes WHERE id_shitpost IN ($list) GROUP BY id_shitpost, $column");
        while ($result && ($row = $result->fetch_assoc())) {
            $key = isset(CM_REACTIONS[$row['reazione']]) ? $row['reazione'] : 'fire';
            $reactions[(int)$row['id_shitpost']][$key] = ($reactions[(int)$row['id_shitpost']][$key] ?? 0) + (int)$row['n'];
        }

        if ($userId > 0) {
            $result = $mysqli->query("SELECT id_shitpost, $column AS reazione FROM shitpost_likes WHERE id_utente = $userId AND id_shitpost IN ($list)");
            while ($result && ($row = $result->fetch_assoc())) {
                $mine[(int)$row['id_shitpost']] = isset(CM_REACTIONS[$row['reazione']]) ? $row['reazione'] : 'fire';
            }
        }
    }

    $posts = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $authorId = (int)$row['id_utente'];
        $own = $userId > 0 && $authorId === $userId;

        $media = [];
        if ((int)$row['has_media'] === 1) {
            $media[] = cm_media_item($type, $id, 0, $row['media_mime'], $row['media_w'], $row['media_h'], $row['media_durata'], (int)$row['has_poster'] === 1);
        }
        foreach ($extras[$id] ?? [] as $extra) {
            $media[] = cm_media_item($type, $id, (int)$extra['posizione'], $extra['mime'], $extra['larghezza'], $extra['altezza'], $extra['durata'], (int)$extra['has_poster'] === 1);
        }

        $post = [
            'id' => $id,
            'type' => $type,
            'author' => [
                'id' => $authorId,
                'username' => (string)($row['username'] ?? ''),
                'role' => (string)$row['ruolo'],
                'premium' => (int)$row['is_premium'] === 1,
            ],
            'title' => (string)$row['titolo'],
            'description' => (string)$row['descrizione'],
            'extra' => (string)($row['extra_text'] ?? ''),
            'tags' => cm_tags_parse((string)($row['tag'] ?? '')),
            'spoiler' => (int)$row['is_spoiler'] === 1,
            'created' => (string)$row['data_creazione'],
            'ts' => (int)$row['ts'],
            'approved' => (int)$row['approvato'] === 1,
            'score' => (int)$row['score'],
            'comments' => (int)$row['comments_count'],
            'saved' => (int)$row['user_saved'] === 1,
            'media' => $media,
            'url' => cm_post_url($type, $id, $lang),
            'mine' => $own,
            'can_manage' => $own || $isAdmin,
        ];

        if ($type === 'shitpost') {
            $post['reactions'] = (object)($reactions[$id] ?? []);
            $post['my_reaction'] = $mine[$id] ?? null;
        } else {
            $post['voted'] = (int)$row['user_liked'] === 1;
        }

        // Le visite restano a chi ha scritto il post e allo staff.
        if ($own || $isAdmin) {
            $post['views'] = (int)$row['views'];
        }

        $posts[] = $post;
    }

    return $posts;
}

/** I tag più usati fra gli ultimi post approvati, per la riga dei filtri. */
function cm_popular_tags(mysqli $mysqli, string $type, int $limit = 10): array
{
    $schema = cm_schema($mysqli, $type);
    if (!$schema['ready'] || !$schema['tag']) {
        return [];
    }

    $table = cv2_meta($type)['table'];
    $result = $mysqli->query("SELECT `tag` FROM `$table` WHERE approvato = 1 AND `tag` IS NOT NULL AND `tag` <> '' ORDER BY data_creazione DESC LIMIT 400");

    $counts = [];
    while ($result && ($row = $result->fetch_row())) {
        foreach (cm_tags_parse((string)$row[0]) as $tag) {
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }
    }

    arsort($counts);
    return array_slice(array_keys($counts), 0, $limit);
}

/**
 * Albo d'oro dei Top Rimasti: per ogni mese passato, il post che ha
 * preso più voti in quel mese. Si ricava dalle date dei voti.
 */
function cm_hall_of_fame(mysqli $mysqli, int $months = 12): array
{
    $schema = cm_schema($mysqli, 'rimasto');
    if (!$schema['ready'] || !$schema['votes']) {
        return [];
    }

    $from = date('Y-m-01 00:00:00', strtotime('first day of this month -' . $months . ' months'));
    $to = date('Y-m-01 00:00:00');

    $stmt = $mysqli->prepare("
        SELECT DATE_FORMAT(v.data_voto, '%Y-%m') AS ym, v.id_post, COUNT(*) AS n
        FROM voti_toprimasti v
        INNER JOIN toprimasti t ON t.id = v.id_post AND t.approvato = 1
        WHERE v.data_voto >= ? AND v.data_voto < ?
        GROUP BY ym, v.id_post
        ORDER BY ym DESC, n DESC, v.id_post ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $winners = [];
    foreach ($rows as $row) {
        if (!isset($winners[$row['ym']])) {
            $winners[$row['ym']] = ['post' => (int)$row['id_post'], 'votes' => (int)$row['n']];
        }
    }
    if (!$winners) {
        return [];
    }

    $ids = implode(',', array_unique(array_map(static fn($w) => $w['post'], $winners)));
    $posts = [];
    $result = $mysqli->query("
        SELECT t.id, t.titolo, t.tipo_foto_rimasto AS mime, (t.foto_rimasto IS NOT NULL) AS has_media,
               " . ($schema['poster'] ? '(t.anteprima IS NOT NULL)' : '0') . " AS has_poster, u.username
        FROM toprimasti t
        LEFT JOIN utenti u ON u.id = t.id_utente
        WHERE t.id IN ($ids)
    ");
    while ($result && ($row = $result->fetch_assoc())) {
        $posts[(int)$row['id']] = $row;
    }

    $hall = [];
    foreach ($winners as $ym => $winner) {
        $post = $posts[$winner['post']] ?? null;
        if (!$post) {
            continue;
        }

        $thumb = null;
        if ((int)$post['has_media'] === 1) {
            $thumb = cm_media_item('rimasto', (int)$post['id'], 0, $post['mime'], null, null, null, (int)$post['has_poster'] === 1)['thumb'];
        }

        $hall[] = [
            'month' => $ym,
            'votes' => $winner['votes'],
            'id' => (int)$post['id'],
            'title' => (string)$post['titolo'],
            'username' => (string)($post['username'] ?? ''),
            'thumb' => $thumb,
        ];
    }

    return $hall;
}

// ── Commenti ────────────────────────────────────────────────────────────

/** I commenti di un post, dal più vecchio: le risposte portano l'id del commento a cui rispondono. */
function cm_comments(mysqli $mysqli, string $type, int $postId, ?array $user): array
{
    $schema = cm_schema($mysqli, $type);
    if (!$schema['comments']) {
        return [];
    }

    $c = cm_comment_meta($type);
    $parent = $schema['replies'] ? 'c.parent_id' : 'NULL';

    $stmt = $mysqli->prepare("
        SELECT c.id, c.`{$c['user']}` AS user_id, c.`{$c['text']}` AS body, UNIX_TIMESTAMP(c.`{$c['created']}`) AS ts,
               $parent AS parent_id, u.username, COALESCE(u.ruolo, 'utente') AS ruolo, COALESCE(u.is_premium, 0) AS is_premium
        FROM `{$c['table']}` c
        LEFT JOIN utenti u ON u.id = c.`{$c['user']}`
        WHERE c.`{$c['post']}` = ?{$c['scope']}
        ORDER BY c.`{$c['created']}` ASC, c.id ASC
        LIMIT 500
    ");
    if (!$stmt) {
        throw new RuntimeException('commenti: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $userId = (int)($user['id'] ?? 0);
    $isAdmin = cv2_is_admin($user);

    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'parent' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
        'author' => [
            'id' => (int)$row['user_id'],
            'username' => (string)($row['username'] ?? ''),
            'role' => (string)$row['ruolo'],
            'premium' => (int)$row['is_premium'] === 1,
        ],
        'text' => (string)$row['body'],
        'ts' => (int)$row['ts'],
        'can_delete' => $isAdmin || ($userId > 0 && (int)$row['user_id'] === $userId),
    ], $rows);
}

function cm_comment_row(mysqli $mysqli, string $type, int $commentId): ?array
{
    $schema = cm_schema($mysqli, $type);
    if (!$schema['comments'] || $commentId <= 0) {
        return null;
    }

    $c = cm_comment_meta($type);
    $parent = $schema['replies'] ? 'c.parent_id' : 'NULL';
    $stmt = $mysqli->prepare("
        SELECT c.id, c.`{$c['user']}` AS user_id, c.`{$c['post']}` AS post_id, $parent AS parent_id
        FROM `{$c['table']}` c
        WHERE c.id = ?{$c['scope']}
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'post_id' => (int)$row['post_id'],
        'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
    ] : null;
}

/**
 * Salva un commento e avvisa chi di dovere. Restituisce l'id.
 * Le risposte hanno un solo livello: rispondere a una risposta la
 * aggancia al commento di partenza.
 */
function cm_comment_add(mysqli $mysqli, string $type, array $post, array $user, string $text, int $parentId = 0): int
{
    $schema = cm_schema($mysqli, $type);
    if (!$schema['comments']) {
        cv2_fail(cm_t('I commenti non sono disponibili.', 'Comments are not available.'), 503);
    }

    $c = cm_comment_meta($type);
    $userId = (int)$user['id'];
    $postId = (int)$post['id'];

    // Freno: uno ogni cinque secondi, trenta in dieci minuti.
    if (!cv2_is_admin($user)) {
        if (cm_count($mysqli, "SELECT COUNT(*) FROM `{$c['table']}` c WHERE c.`{$c['user']}` = ? AND c.`{$c['created']}` > NOW() - INTERVAL 5 SECOND", 'i', [$userId]) > 0) {
            cv2_fail(cm_t('Stai commentando troppo velocemente.', 'You are commenting too fast.'), 429);
        }
        if (cm_count($mysqli, "SELECT COUNT(*) FROM `{$c['table']}` c WHERE c.`{$c['user']}` = ? AND c.`{$c['created']}` > NOW() - INTERVAL 10 MINUTE", 'i', [$userId]) >= 30) {
            cv2_fail(cm_t('Troppi commenti in poco tempo. Riprova più tardi.', 'Too many comments in a short time. Try again later.'), 429);
        }
    }

    $parent = null;
    if ($parentId > 0 && $schema['replies']) {
        $parent = cm_comment_row($mysqli, $type, $parentId);
        if (!$parent || $parent['post_id'] !== $postId) {
            cv2_fail(cm_t('Il commento a cui rispondi non esiste più.', 'The comment you are replying to no longer exists.'), 404);
        }
        if ($parent['parent_id'] !== null) {
            $root = cm_comment_row($mysqli, $type, $parent['parent_id']);
            $parentId = $root ? $root['id'] : $parent['id'];
        }
    } else {
        $parentId = 0;
    }

    $columns = "`{$c['post']}`, `{$c['user']}`, `{$c['text']}`, `{$c['created']}`";
    $values = '?, ?, ?, NOW()';
    $types = 'iis';
    $params = [$postId, $userId, $text];

    if ($type === 'rimasto') {
        $columns .= ', content_type';
        $values .= ", 'rimasto'";
    }
    if ($parentId > 0) {
        $columns .= ', parent_id';
        $values .= ', ?';
        $types .= 'i';
        $params[] = $parentId;
    }

    $stmt = $mysqli->prepare("INSERT INTO `{$c['table']}` ($columns) VALUES ($values)");
    if (!$stmt) {
        throw new RuntimeException('nuovo commento: ' . $mysqli->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('nuovo commento: ' . $error);
    }
    $commentId = (int)$stmt->insert_id;
    $stmt->close();

    cm_comment_notify($mysqli, $type, $post, $user, $text, $parent);

    return $commentId;
}

/** Avvisa l'autore del post, chi ha ricevuto la risposta e chi è stato menzionato. */
function cm_comment_notify(mysqli $mysqli, string $type, array $post, array $user, string $text, ?array $parent): void
{
    try {
        $senderId = (int)$user['id'];
        $sender = (string)$user['username'];
        $title = cm_clip($post['titolo'], 60);
        $quote = cm_clip($text, 220);
        $told = [$senderId => true];

        $where = [
            'it' => $type === 'rimasto' ? 'nei Top Rimasti' : 'negli Shitpost',
            'en' => $type === 'rimasto' ? 'in Top Rimasti' : 'in Shitpost',
        ];
        $link = static fn(string $lang): string => 'https://cripsum.com' . cm_post_url($type, (int)$post['id'], $lang);

        $send = static function (int $to, string $headIt, string $headEn, string $leadIt, string $leadEn) use ($mysqli, &$told, $quote, $link, $senderId): void {
            if ($to <= 0 || isset($told[$to]) || cm_blocked($mysqli, $to, $senderId)) {
                return;
            }
            $told[$to] = true;
            cm_notify(
                $mysqli,
                $to,
                $headIt,
                $headEn,
                $leadIt . "\n\n«" . $quote . "»\n\n" . $link('it'),
                $leadEn . "\n\n\"" . $quote . "\"\n\n" . $link('en'),
                'social',
                30
            );
        };

        if ($parent) {
            $send(
                $parent['user_id'],
                "Risposta al tuo commento su «{$title}»",
                "Reply to your comment on \"{$title}\"",
                "@{$sender} ha risposto al tuo commento {$where['it']}.",
                "@{$sender} replied to your comment {$where['en']}."
            );
        }

        if (preg_match_all('/(?<![\w@])@([A-Za-z0-9_]{3,20})/', $text, $matches)) {
            foreach (array_slice(array_unique($matches[1]), 0, 3) as $name) {
                $stmt = $mysqli->prepare('SELECT id FROM utenti WHERE username = ? LIMIT 1');
                if (!$stmt) {
                    continue;
                }
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $mentioned = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
                $stmt->close();

                $send(
                    $mentioned,
                    "Ti hanno menzionato su «{$title}»",
                    "You were mentioned on \"{$title}\"",
                    "@{$sender} ti ha menzionato in un commento {$where['it']}.",
                    "@{$sender} mentioned you in a comment {$where['en']}."
                );
            }
        }

        $send(
            (int)$post['id_utente'],
            "Nuovo commento a «{$title}»",
            "New comment on \"{$title}\"",
            "@{$sender} ha commentato il tuo post {$where['it']}.",
            "@{$sender} commented on your post {$where['en']}."
        );
    } catch (Throwable $e) {
        // Un avviso mancato non deve far fallire il commento.
        error_log('[community] avviso commento: ' . $e->getMessage());
    }
}

// ── Avvisi ──────────────────────────────────────────────────────────────

function cm_clip(string $text, int $max): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_strlen($text, 'UTF-8') > $max ? rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…' : $text;
}

/** Vero se `$userId` ha bloccato o nascosto `$otherId`: a lui non si mandano avvisi su di lui. */
function cm_blocked(mysqli $mysqli, int $userId, int $otherId): bool
{
    try {
        if (!function_exists('sc_hidden_ids')) {
            $file = __DIR__ . '/../social_core.php';
            if (!is_file($file)) {
                return false;
            }
            require_once $file;
        }
        return function_exists('sc_hidden_ids') && in_array($otherId, array_map('intval', sc_hidden_ids($mysqli, $userId)), true);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Un messaggio nella posta del sito, con il suo avviso nella campanella.
 *
 * `$quietMinutes` > 0: se il destinatario ha già un messaggio con lo
 * stesso titolo, non letto e più recente di quei minuti, non se ne manda
 * un altro. Dieci commenti di fila sullo stesso post sono un avviso.
 */
function cm_notify(mysqli $mysqli, int $recipientId, string $titleIt, string $titleEn, string $bodyIt, string $bodyEn, string $category = 'system', int $quietMinutes = 0): bool
{
    if ($recipientId <= 0 || !cm_has($mysqli, 'site_messages') || !cm_has($mysqli, 'site_message_recipients')) {
        return false;
    }

    $titleIt = mb_substr($titleIt, 0, 250, 'UTF-8');
    $titleEn = mb_substr($titleEn, 0, 250, 'UTF-8');

    if ($quietMinutes > 0) {
        $recent = cm_count(
            $mysqli,
            'SELECT COUNT(*) FROM site_messages m
             INNER JOIN site_message_recipients r ON r.message_id = m.id
             WHERE r.recipient_id = ? AND r.is_read = 0 AND m.title_it = ? AND m.created_at > NOW() - INTERVAL ? MINUTE',
            'isi',
            [$recipientId, $titleIt, $quietMinutes]
        );
        if ($recent > 0) {
            return false;
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO site_messages (sender_id, title_it, title_en, content_it, content_en, category, target_type) VALUES (NULL, ?, ?, ?, ?, ?, 'single')");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sssss', $titleIt, $titleEn, $bodyIt, $bodyEn, $category);
    $ok = $stmt->execute();
    $messageId = (int)$stmt->insert_id;
    $stmt->close();

    if (!$ok || $messageId <= 0) {
        return false;
    }

    $stmt = $mysqli->prepare('INSERT IGNORE INTO site_message_recipients (message_id, recipient_id) VALUES (?, ?)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $messageId, $recipientId);
    $ok = $stmt->execute();
    $stmt->close();

    try {
        require_once __DIR__ . '/../realtime.php';
        rt_push_user($recipientId, [
            't' => 'ib',
            'xi' => mb_substr($titleIt, 0, 120, 'UTF-8'),
            'xe' => mb_substr($titleEn, 0, 120, 'UTF-8'),
        ]);
    } catch (Throwable $e) {
        // Il messaggio è in posta comunque.
    }

    return $ok;
}

// ── Visualizzazioni ─────────────────────────────────────────────────────

/**
 * Segna come visti i post dell'elenco e restituisce quanti sono nuovi.
 * Chi è collegato conta una volta sola per post; chi non lo è, una volta
 * al giorno, riconosciuto da una chiave che non permette di risalire
 * all'indirizzo.
 */
function cm_count_views(mysqli $mysqli, string $type, array $ids, ?array $user): int
{
    $schema = cm_schema($mysqli, $type);
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0))), 0, 50);
    if (!$ids || !$schema['views']) {
        return 0;
    }

    $table = cv2_meta($type)['table'];
    $list = implode(',', $ids);

    // Solo post che esistono e sono online.
    $valid = [];
    $result = $mysqli->query("SELECT id FROM `$table` WHERE id IN ($list) AND approvato = 1");
    while ($result && ($row = $result->fetch_row())) {
        $valid[] = (int)$row[0];
    }
    if (!$valid) {
        return 0;
    }

    $userId = (int)($user['id'] ?? 0);
    $fresh = [];

    if ($schema['views_table'] && ($userId > 0 || $schema['viewer_key'])) {
        $key = null;
        if ($userId <= 0) {
            require_once __DIR__ . '/../realtime.php';
            $key = sha1(cv2_client_ip() . '|' . date('Y-m-d') . '|' . rt_secret());
        }

        $sql = $schema['viewer_key']
            ? 'INSERT IGNORE INTO content_views (content_type, post_id, user_id, viewer_key, created_at) VALUES (?, ?, ?, ?, NOW())'
            : 'INSERT IGNORE INTO content_views (content_type, post_id, user_id, created_at) VALUES (?, ?, ?, NOW())';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        $viewer = $userId > 0 ? $userId : null;
        foreach ($valid as $id) {
            if ($schema['viewer_key']) {
                $stmt->bind_param('siis', $type, $id, $viewer, $key);
            } else {
                $stmt->bind_param('sii', $type, $id, $viewer);
            }
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $fresh[] = $id;
            }
        }
        $stmt->close();
    } else {
        // Senza la colonna della chiave anonima: ci si ricorda nella sessione.
        $seen = (array)($_SESSION['cm_viewed'][$type] ?? []);
        foreach ($valid as $id) {
            if (!isset($seen[$id])) {
                $seen[$id] = 1;
                $fresh[] = $id;
            }
        }
        $_SESSION['cm_viewed'][$type] = array_slice($seen, -400, null, true);
    }

    if ($fresh) {
        $mysqli->query("UPDATE `$table` SET views = COALESCE(views, 0) + 1 WHERE id IN (" . implode(',', $fresh) . ')');
    }

    return count($fresh);
}

// ── «Nuovi post» ────────────────────────────────────────────────────────
//
// Gli ultimi post andati online stanno in un timbro su file (lo stesso
// meccanismo delle chat): la pagina aperta chiede «c'è qualcosa di
// nuovo?» senza toccare il database.

function cm_pulse_push(string $type, int $postId, int $authorId): void
{
    try {
        require_once __DIR__ . '/../realtime.php';
        rt_update('cp-' . $type, static function (array $data) use ($postId, $authorId): array {
            $posts = array_values(array_filter((array)($data['posts'] ?? []), static fn($p) => (int)($p['id'] ?? 0) !== $postId));
            $posts[] = ['id' => $postId, 'u' => $authorId, 'at' => time()];
            return ['posts' => array_slice($posts, -30)];
        });
    } catch (Throwable $e) {
        error_log('[community] timbro nuovi post: ' . $e->getMessage());
    }
}

function cm_pulse_drop(string $type, int $postId): void
{
    try {
        require_once __DIR__ . '/../realtime.php';
        rt_update('cp-' . $type, static function (array $data) use ($postId): ?array {
            $posts = (array)($data['posts'] ?? []);
            $kept = array_values(array_filter($posts, static fn($p) => (int)($p['id'] ?? 0) !== $postId));
            return count($kept) === count($posts) ? null : ['posts' => $kept];
        });
    } catch (Throwable $e) {
        // Al massimo la pagina annuncia un post che non c'è più.
    }
}
