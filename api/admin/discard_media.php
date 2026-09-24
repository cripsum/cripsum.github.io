<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Immagini caricate in un form e poi non usate: il form chiuso senza
 * salvare, o una foto sostituita prima di salvare. Il pannello le manda qui
 * quando la finestra si chiude.
 *
 * Si cancellano solo quelle nelle cartelle del pannello che nessuna riga del
 * database usa: se il form e' stato salvato, le sue foto restano.
 *
 * POST { files: ["/img/merch/poppy/123_foto.jpg", ...] }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_fail('Metodo non consentito.', 405);
}

$input = admin_input();
$files = $input['files'] ?? [];

if (!is_array($files) || count($files) > 50) {
    admin_fail('Elenco di file non valido.');
}

$deleted = admin_media_cleanup($mysqli, array_filter($files, 'is_string'), (int)$adminUser['id']);

admin_ok(['deleted' => count($deleted)]);
