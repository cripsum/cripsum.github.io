<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Il feed di Shitpost e Top Rimasti.
 *
 * GET type, sort, period, q, tag, mine, saved, status, page, limit
 *     post=<id>   un post solo (link diretti e condivisi)
 *     hall=1      con la prima pagina dei Top Rimasti, anche l'albo d'oro
 */
try {
    $type = cv2_normalize_type((string)($_GET['type'] ?? 'shitpost'));
    $wantsOwn = (string)($_GET['saved'] ?? '0') === '1' || (string)($_GET['mine'] ?? '0') === '1';

    if ($wantsOwn && !$currentUser) {
        cv2_fail(cm_t('Devi accedere.', 'You need to log in.'), 401);
    }

    // Da qui si legge soltanto: la sessione si può lasciare alle altre richieste.
    cripsum_release_session();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $single = (int)($_GET['post'] ?? 0);

    $feed = cm_feed($mysqli, $type, [
        'sort' => (string)($_GET['sort'] ?? 'recent'),
        'period' => (string)($_GET['period'] ?? 'all'),
        'q' => (string)($_GET['q'] ?? ''),
        'tag' => (string)($_GET['tag'] ?? ''),
        'mine' => (string)($_GET['mine'] ?? '0') === '1',
        'saved' => (string)($_GET['saved'] ?? '0') === '1',
        'status' => (string)($_GET['status'] ?? 'approved'),
        'page' => $page,
        'limit' => (int)($_GET['limit'] ?? CM_PAGE_SIZE),
        'post' => $single,
    ], $currentUser);

    $out = [
        'posts' => $feed['posts'],
        'pagination' => ['page' => $feed['page'], 'pages' => $feed['pages'], 'total' => $feed['total']],
        'now' => time(),
    ];

    if ($page === 1 && $single <= 0) {
        $out['tags'] = cm_popular_tags($mysqli, $type);
        if ($type === 'rimasto' && (string)($_GET['hall'] ?? '0') === '1') {
            $out['hall'] = cm_hall_of_fame($mysqli);
        }
    }

    cv2_ok($out);
} catch (Throwable $e) {
    cm_crash('feed', $e);
}
