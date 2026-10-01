<?php
/**
 * Ricerca di utenti a cui scrivere (per «Nuova chat»): prima gli amici, poi
 * gli altri utenti a cui si può mandare un messaggio.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $query = trim((string)($_GET['q'] ?? $input['q'] ?? ''));

    if ($query === '') {
        // Nessuna ricerca: si propongono gli amici, che sono i primi a cui si scrive.
        $friends = array_slice(sc_friends($mysqli, $userId), 0, 40);
        foreach ($friends as &$friend) {
            $friend['can_message'] = true;
        }
        unset($friend);
        send_success(['results' => $friends]);
    }

    $users = sc_search($mysqli, $userId, $query, 20);
    usort($users, static fn($a, $b) => [(int)!$a['is_friend'], strtolower($a['username'])] <=> [(int)!$b['is_friend'], strtolower($b['username'])]);
    send_success(['results' => $users]);
});
