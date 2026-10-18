<?php
declare(strict_types=1);

/**
 * bio.cripsum.com/editor: l'editor del profilo, lo stesso di
 * cripsum.com/<lingua>/edit-profile (includes/profile_editor/page.php).
 * L'anteprima, i salvataggi e i caricamenti restano su bio, con la sessione di
 * bio. L'area si apre col frammento: /editor#appearance.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_pages.php';

cripsum_bio_only('editor');
cripsum_bio_auto_login($mysqli);

if (!isLoggedIn()) {
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: /accedi?' . http_build_query(['return' => '/editor' . ($query !== '' ? '?' . $query : '')]));
    exit;
}

$editorLang = bio_lang();
require __DIR__ . '/../includes/profile_editor/page.php';
