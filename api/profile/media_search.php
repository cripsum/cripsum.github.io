<?php

/**
 * Ricerca per le sezioni "preferiti" del profilo (e per il brano del player).
 *
 * Le fonti e la conversione dei risultati stanno in
 * includes/profile_media_search.php, che si puo' provare da riga di comando.
 * Qui ci sono solo i controlli, il limite di richieste e la cache: le stesse
 * tre lettere digitate da tutti non devono diventare una richiesta in uscita
 * a testa.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/profile_media_search.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Devi essere loggato.'], 401);
}

$userId = profile_current_user_id() ?? 0;

// Letti i dati della sessione, il lucchetto si molla subito: l'editor sta
// salvando la bozza e ricaricando l'anteprima nello stesso momento.
cripsum_release_session();

const PMS_CACHE_TTL = 86400;    // un giorno
const PMS_RATE_MAX = 40;        // richieste...
const PMS_RATE_WINDOW = 60;     // ...al minuto, per utente

$kind = strtolower(trim((string)($_GET['kind'] ?? '')));
$query = profile_clean_text($_GET['q'] ?? '', 80);

if (!in_array($kind, PMS_KINDS, true)) {
    profile_json_response(['ok' => false, 'message' => 'Tipo di ricerca non valido.'], 422);
}
if (mb_strlen($query, 'UTF-8') < 2) {
    profile_json_response(['ok' => true, 'results' => []]);
}

$cacheRoot = __DIR__ . '/../../scratch/profile_media_search';

/** Una finestra scorrevole per utente, tenuta in un file. */
function pms_rate_limited(string $cacheRoot, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $dir = $cacheRoot . '/rate';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $file = $dir . '/' . $userId . '.json';
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string)@file_get_contents($file), true);
        $hits = is_array($decoded) ? $decoded : [];
    }
    $hits = array_values(array_filter($hits, static fn($t): bool => is_int($t) && $t > $now - PMS_RATE_WINDOW));
    if (count($hits) >= PMS_RATE_MAX) {
        return true;
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return false;
}

if (pms_rate_limited($cacheRoot, $userId)) {
    profile_json_response(['ok' => false, 'message' => 'Troppe ricerche di fila. Riprova fra poco.'], 429);
}

$cachePath = $cacheRoot . '/' . $kind . '/' . sha1(mb_strtolower($query, 'UTF-8')) . '.json';

if (is_file($cachePath) && filemtime($cachePath) >= time() - PMS_CACHE_TTL) {
    $decoded = json_decode((string)@file_get_contents($cachePath), true);
    if (is_array($decoded)) {
        profile_json_response(['ok' => true, 'results' => $decoded, 'cached' => true]);
    }
}

$results = pms_search($kind, $query);

// Una ricerca a cui e' mancata una fonte non va tenuta per un giorno: la
// prossima volta magari risponde e i risultati sono completi.
if ($results && !pms_last_search_incomplete()) {
    $dir = dirname($cachePath);
    if (is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir)) {
        @file_put_contents($cachePath, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

profile_json_response([
    'ok' => true,
    'results' => $results,
    'partial' => pms_last_search_incomplete(),
    'message' => $results ? '' : 'Nessun risultato. Puoi aggiungerlo a mano.',
]);
