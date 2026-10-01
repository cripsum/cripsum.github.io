<?php
// Elenco degli utenti che ho bloccato: prima non esisteva, e chi bloccava
// qualcuno non aveva modo di ritrovarlo per sbloccarlo.
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    send_api_success(['blocked' => sc_blocked($mysqli, $userId)]);
});
