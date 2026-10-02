<?php
/**
 * Lettura dei messaggi.
 *
 *   con `chat_id`     → messaggi di un gruppo (solo per i membri attivi);
 *   senza             → chat globale.
 *
 * Parametri comuni: before, after, ids=1,2,3, around, limit. Restano
 * accettati i nomi storici (before_message_id, after_message_id, before_id,
 * after_id) per le schede aperte con la versione precedente.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $userId, $chatUser): void {
    $options = ['limit' => (int)($_GET['limit'] ?? 0)];
    if ($options['limit'] <= 0) {
        unset($options['limit']);
    }
    $before = (int)($_GET['before'] ?? $_GET['before_message_id'] ?? $_GET['before_id'] ?? 0);
    $after = (int)($_GET['after'] ?? $_GET['after_message_id'] ?? $_GET['after_id'] ?? 0);
    if (!empty($_GET['ids'])) {
        $options['ids'] = explode(',', (string)$_GET['ids']);
    } elseif (!empty($_GET['around'])) {
        $options['around'] = (int)$_GET['around'];
    } elseif ($after > 0) {
        $options['after'] = $after;
    } elseif ($before > 0) {
        $options['before'] = $before;
    }

    // ── Gruppo ─────────────────────────────────────────────────────────────
    if (isset($_GET['chat_id'])) {
        $chatId = (int)$_GET['chat_id'];
        $member = cg_require($mysqli, $chatId, $userId);
        $result = cg_fetch($mysqli, $userId, $chatId, $options);

        $isLatestPage = empty($options['ids']) && empty($options['around']) && empty($options['before']) && empty($options['after']);
        if ($isLatestPage && empty($_GET['noread']) && $result['messages']) {
            cg_mark_read($mysqli, $member);
        }

        send_success($result + ['last_read_id' => $member['last_read_message_id']]);
    }

    // ── Chat globale ───────────────────────────────────────────────────────
    $search = trim((string)($_GET['search'] ?? ''));
    if ($search !== '') {
        $options['search'] = $search;
    }

    $hidden = gc_hidden($mysqli, $userId);
    $messages = gc_views(gc_fetch($mysqli, $options), $chatUser, $hidden);

    // Chi ricarica gli ultimi messaggi ha la chat davanti: le menzioni sono viste.
    if ($search === '' && empty($options['ids']) && empty($options['around']) && empty($options['before']) && empty($options['after'])) {
        rt_mentions_clear($userId);
    }

    gc_presence_touch($userId, gc_presence_entry($chatUser));
    gc_online_refresh($mysqli);
    gc_restore_pinned($mysqli);

    $stamp = rt_read('g');
    $aux = gc_aux_view($stamp, $userId, $hidden);

    send_success([
        'messages' => $messages,
        'online_count' => $aux['online_count'],
        'typing' => $aux['typing'],
        'state' => ['seq' => (int)($stamp['seq'] ?? 0), 'aux' => (int)($stamp['aux'] ?? 0)] + $aux,
        'server_time' => date(DATE_ATOM),
    ]);
});
