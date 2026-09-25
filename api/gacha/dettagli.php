<?php
declare(strict_types=1);

/**
 * GET /api/gacha/dettagli?banner=6&lang=it
 *
 * Probabilita', regole del pity e pool di un banner, per la modale
 * "Dettagli e probabilità" della lootbox. I numeri vengono dalle stesse
 * funzioni del motore delle pull.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/gacha/public.php';

$key = trim((string)($_GET['banner'] ?? ''));
$banner = $key !== '' ? gacha_banner_get($mysqli, $key) : null;
$status = $banner ? gacha_banner_status($banner) : null;

if (!$banner || ($status !== 'attivo' && $status !== 'prossimamente')) {
    gacha_api_fail('Banner non trovato.', 404);
}

try {
    gacha_api_json(['ok' => true] + gacha_banner_details($mysqli, $banner, $gachaUserId, gacha_api_lang()));
} catch (Throwable $e) {
    error_log('[gacha dettagli] ' . $e->getMessage());
    gacha_api_fail('Non riesco a calcolare i dettagli del banner.', 500);
}
