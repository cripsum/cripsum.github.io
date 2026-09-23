<?php

/**
 * Validazione e piccoli servizi comuni alle API admin dello shop
 * (shop_catalog, shop_content, shop_downloads, shop_gacha).
 *
 * Ogni funzione che trova un valore sbagliato risponde subito con
 * admin_fail(): il messaggio arriva cosi' com'e' nel toast del pannello,
 * quindi e' scritto per chi lo legge, con il nome del campo.
 */

require_once __DIR__ . '/../shop/shop_common.php';

function admin_shop_action(): string
{
    $action = $_SERVER['REQUEST_METHOD'] === 'GET'
        ? (string)($_GET['action'] ?? '')
        : (string)(admin_input()['action'] ?? '');

    return preg_match('/^[a-z_]{2,40}$/', $action) ? $action : '';
}

function admin_shop_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_fail('Metodo non consentito.', 405);
    }
}

function admin_shop_text(array $input, string $key, string $label, int $max, bool $required = false): ?string
{
    $value = trim(str_replace("\r\n", "\n", (string)($input[$key] ?? '')));

    if ($value === '') {
        if ($required) {
            admin_fail($label . ': campo obbligatorio.');
        }
        return null;
    }

    if (mb_strlen($value, 'UTF-8') > $max) {
        admin_fail($label . ': massimo ' . $max . ' caratteri.');
    }

    return $value;
}

function admin_shop_int(array $input, string $key, string $label, int $min, int $max, ?int $default = null): ?int
{
    $raw = trim((string)($input[$key] ?? ''));

    if ($raw === '') {
        if ($default === null) {
            admin_fail($label . ': campo obbligatorio.');
        }
        return $default;
    }

    if (!preg_match('/^-?\d+$/', $raw)) {
        admin_fail($label . ': serve un numero intero.');
    }

    $value = (int)$raw;
    if ($value < $min || $value > $max) {
        admin_fail($label . ': deve stare tra ' . number_format($min, 0, ',', '.') . ' e ' . number_format($max, 0, ',', '.') . '.');
    }

    return $value;
}

function admin_shop_bool(array $input, string $key): int
{
    $value = $input[$key] ?? 0;
    return ($value === true || $value === 1 || $value === '1' || $value === 'on' || $value === 'true') ? 1 : 0;
}

/**
 * Un prezzo scritto come lo scrive una persona ("4,99", "4.99", "1.299,00",
 * "12") trasformato in centesimi. Niente float: i centesimi si contano.
 */
function admin_shop_price_cents(array $input, string $key, string $label, int $minCents, int $maxCents, bool $required = true): ?int
{
    $raw = trim(str_replace(['€', ' '], '', (string)($input[$key] ?? '')));

    if ($raw === '') {
        if ($required) {
            admin_fail($label . ': campo obbligatorio.');
        }
        return null;
    }

    // "1.299,50" -> "1299.50"; "1,299.50" -> "1299.50"; "4,99" -> "4.99"
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $raw)) {
        $raw = str_replace(['.', ','], ['', '.'], $raw);
    } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $raw)) {
        $raw = str_replace(',', '', $raw);
    } else {
        $raw = str_replace(',', '.', $raw);
    }

    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $raw, $m)) {
        admin_fail($label . ': scrivi un prezzo come 4,99.');
    }

    $cents = (int)$m[1] * 100 + (int)str_pad($m[2] ?? '0', 2, '0');

    if ($cents < $minCents || $cents > $maxCents) {
        admin_fail($label . ': deve stare tra ' . number_format($minCents / 100, 2, ',', '.') . ' € e ' . number_format($maxCents / 100, 2, ',', '.') . ' €.');
    }

    return $cents;
}

function admin_shop_slugify(string $value): string
{
    $map = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss', '&' => '-e-', '™' => '',
    ];

    $value = strtr(mb_strtolower(trim($value), 'UTF-8'), $map);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

/**
 * Lo slug finisce negli URL (/it/merch/poppy, /it/download/osu): se non lo
 * si scrive viene ricavato dal nome.
 */
function admin_shop_slug(array $input, string $key, string $fallbackSource, int $max = 60): string
{
    $raw = trim((string)($input[$key] ?? ''));
    $slug = admin_shop_slugify($raw !== '' ? $raw : $fallbackSource);
    $slug = trim(substr($slug, 0, $max), '-');

    if ($slug === '' || strlen($slug) < 2) {
        admin_fail('Slug: servono almeno 2 lettere o numeri.');
    }

    return $slug;
}

/**
 * Un'immagine: percorso del sito, nome restituito dal caricamento (finisce
 * in /img/) o indirizzo https. Si salva gia' nella forma che la pagina usa.
 */
function admin_shop_image(array $input, string $key, string $label, bool $required = false): ?string
{
    $value = trim((string)($input[$key] ?? ''));

    if ($value === '') {
        if ($required) {
            admin_fail($label . ': campo obbligatorio.');
        }
        return null;
    }

    if (mb_strlen($value) > 255) {
        admin_fail($label . ': percorso troppo lungo.');
    }

    if (preg_match('~^https?://~i', $value)) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            admin_fail($label . ': indirizzo non valido.');
        }
        return $value;
    }

    if (str_contains($value, '..') || str_contains($value, "\0") || preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) || str_starts_with($value, '//')) {
        admin_fail($label . ': usa un percorso del sito (/img/...) o un indirizzo https.');
    }

    if (!preg_match('~^[a-zA-Z0-9_\-./ ()%]+$~', $value)) {
        admin_fail($label . ': il percorso contiene caratteri non validi.');
    }

    return shop_asset_url($value);
}

function admin_shop_link(array $input, string $key, string $label, bool $required = false): ?string
{
    $value = trim((string)($input[$key] ?? ''));

    if ($value === '') {
        if ($required) {
            admin_fail($label . ': campo obbligatorio.');
        }
        return null;
    }

    if (!shop_valid_link($value)) {
        admin_fail($label . ': usa un percorso interno (/it/...) o un indirizzo https completo.');
    }

    return $value;
}

function admin_shop_color(array $input, string $key, string $label, string $default): string
{
    $value = trim((string)($input[$key] ?? ''));

    if ($value === '') {
        return $default;
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        admin_fail($label . ': usa un colore esadecimale come #ffd23f.');
    }

    return strtolower($value);
}

function admin_shop_enum(array $input, string $key, string $label, array $allowed, string $default): string
{
    $value = (string)($input[$key] ?? $default);

    if (!in_array($value, $allowed, true)) {
        admin_fail($label . ': valore non valido.');
    }

    return $value;
}

function admin_shop_ids(array $input, string $key = 'order', int $limit = 500): array
{
    $raw = $input[$key] ?? null;

    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }

    if (!is_array($raw) || !$raw) {
        admin_fail('Ordine mancante.');
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    if (!$ids || count($ids) > $limit) {
        admin_fail('Ordine non valido.');
    }

    return $ids;
}

/**
 * Riscrive le posizioni da capo a passi di dieci, nell'ordine ricevuto:
 * come per le slide della home, non restano mai due righe a pari merito.
 */
function admin_shop_reorder(mysqli $mysqli, string $table, string $column, array $ids): void
{
    if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) {
        admin_fail('Tabella non valida.', 500);
    }

    $stmt = $mysqli->prepare("UPDATE `$table` SET `$column` = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        admin_fail('Riordino non disponibile.', 500);
    }

    $mysqli->begin_transaction();
    $position = 10;

    foreach ($ids as $id) {
        $stmt->bind_param('ii', $position, $id);
        if (!$stmt->execute()) {
            $stmt->close();
            $mysqli->rollback();
            admin_fail('Non sono riuscito a salvare il nuovo ordine.', 500);
        }
        $position += 10;
    }

    $stmt->close();
    $mysqli->commit();
}

/**
 * La posizione per una riga nuova: in fondo alla lista.
 */
function admin_shop_next_position(mysqli $mysqli, string $sql, string $types = '', array $params = []): int
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 10;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return (int)($row[0] ?? 0) + 10;
}

/**
 * Esegue una query preparata di scrittura e fallisce con un messaggio
 * leggibile. Le violazioni di chiave unica (slug gia' usato) diventano un
 * errore sul campo invece di un 500.
 */
function admin_shop_exec(mysqli $mysqli, string $sql, string $types, array $params, string $failMessage): mysqli_stmt
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        admin_fail(admin_prepare_error($mysqli, $failMessage), 500);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $errno = $stmt->errno;
        $stmt->close();
        if ($errno === 1062) {
            admin_fail('Esiste già un elemento con questo slug: scegline un altro.');
        }
        admin_fail($failMessage, 500);
    }

    return $stmt;
}

function admin_shop_rows(mysqli $mysqli, string $sql, string $types = '', array $params = []): array
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        admin_fail(admin_prepare_error($mysqli, 'Lettura non riuscita.'), 500);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows ?: [];
}

function admin_shop_row(mysqli $mysqli, string $sql, string $types = '', array $params = []): ?array
{
    $rows = admin_shop_rows($mysqli, $sql, $types, $params);
    return $rows[0] ?? null;
}
