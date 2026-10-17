<?php

declare(strict_types=1);

/**
 * Il vecchio editor della Cripsumpedia. Dalla fase 4 si scrive da «Scrivi»,
 * per tutti: i link salvati (anche quelli con ?id= di una voce) portano lì.
 */

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$id = (int)($_GET['id'] ?? 0);
$target = $id > 0
    ? cp_edit_url($lang, $id)
    : cp_write_url($lang, $_GET['type'] ?? null, (string)($_GET['title'] ?? ''));

header('Location: ' . $target, true, 301);
exit;
