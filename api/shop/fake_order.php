<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/shop/catalog.php';

/**
 * "Acquisto" di Negozio e Merch. Non si paga niente e non si salva nessun
 * dato personale: il checkout manda solo prodotto, quantita' e taglia.
 *
 * Serve a tre cose:
 *   - il contatore degli ordini finti, da cui nasce l'ordinamento
 *     "Piu' popolari" (una volta per prodotto per sessione, cosi' non si
 *     gonfia ricaricando);
 *   - il riepilogo che la pagina di conferma mostra;
 *   - l'achievement del merch, che la conferma sblocca una volta sola.
 *
 * Risponde in JSON al checkout; senza JavaScript il form arriva qui con un
 * POST normale e si viene reindirizzati alla conferma.
 */

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$isJson = str_contains($contentType, 'application/json');
$input = $isJson ? json_decode((string)file_get_contents('php://input'), true) : $_POST;
$input = is_array($input) ? $input : [];

$lang = ($input['lang'] ?? 'it') === 'en' ? 'en' : 'it';

function fake_order_reply(bool $isJson, string $lang, bool $ok, string $message = '', int $status = 200, string $redirect = ''): void
{
    if ($isJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $message, 'redirect' => $redirect], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Location: ' . ($redirect !== '' ? $redirect : '/' . $lang . '/negozio'), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fake_order_reply($isJson, $lang, false, 'Metodo non consentito.', 405);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    fake_order_reply($isJson, $lang, false, $lang === 'en' ? 'Session expired. Reload the page.' : 'Sessione scaduta. Ricarica la pagina.', 419);
}

$slug = strtolower(trim((string)($input['p'] ?? '')));
$found = (shop_catalog_ready($mysqli) && preg_match('/^[a-z0-9-]{1,80}$/', $slug))
    ? shop_product_for_checkout($mysqli, $slug, $lang)
    : null;

if (!$found) {
    fake_order_reply($isJson, $lang, false, $lang === 'en' ? 'This product is no longer available.' : 'Questo prodotto non è più disponibile.', 404);
}

$product = $found['product'];
$vetrina = $found['vetrina'];

$qty = max(1, min(99, (int)($input['qty'] ?? 1)));
$size = strtoupper(trim((string)($input['taglia'] ?? '')));
if ($product['sizes'] && !in_array($size, $product['sizes'], true)) {
    fake_order_reply($isJson, $lang, false, $lang === 'en' ? 'Pick a size.' : 'Scegli una taglia.', 422);
}
if (!$product['sizes']) {
    $size = '';
}

$counted = $_SESSION['shop_fake_orders'] ?? [];
if (!is_array($counted)) {
    $counted = [];
}

if (empty($counted[$slug])) {
    $counted[$slug] = time();
    $_SESSION['shop_fake_orders'] = array_slice($counted, -100, null, true);

    try {
        $stmt = $mysqli->prepare('UPDATE shop_prodotti SET ordini_finti = ordini_finti + 1 WHERE id = ? LIMIT 1');
        if ($stmt) {
            $productId = (int)$product['id'];
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('[fake_order] ' . $e->getMessage());
    }
}

// Numero d'ordine finto, stabile per questo ordine: la conferma lo mostra
// anche se la pagina viene ricaricata.
$reference = 'CRP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

$_SESSION['shop_last_order'] = [
    'slug' => $slug,
    'qty' => $qty,
    'size' => $size,
    'ref' => $reference,
    'at' => time(),
];

// L'achievement del merch lo sblocca la pagina di conferma, una volta sola.
if ($vetrina['tipo'] === 'merch') {
    $_SESSION['shop_achievement_pending'] = 7;
}

fake_order_reply($isJson, $lang, true, '', 200, '/' . $lang . '/confirm');
