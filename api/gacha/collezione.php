<?php
declare(strict_types=1);

/**
 * GET /api/gacha/collezione?lang=it
 *
 * Tutto l'inventario in una risposta: personaggi (i segreti non trovati
 * restano mascherati), statistiche, collezioni, frammenti e negozio.
 * La pagina lo riceve gia' dentro l'HTML; questo serve per aggiornarlo.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/gacha/collection.php';

try {
    gacha_api_json(['ok' => true] + gacha_collection_payload($mysqli, $gachaUserId, gacha_api_lang()));
} catch (Throwable $e) {
    error_log('[gacha collezione] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gacha_api_fail('Non riesco a caricare l\'inventario.', 500);
}
