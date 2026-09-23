<?php

/**
 * Pacchetti di Godo Shards: l'unico posto da cui la pagina e i pagamenti
 * (Stripe, PayPal, webhook) leggono prezzi e quantita'.
 *
 * Prima lo stesso elenco era copiato in sei file. Adesso sta nella tabella
 * shop_pacchetti_shards e si cambia dal pannello admin; l'elenco qui sotto
 * resta solo come riserva finche' la migrazione non e' stata applicata.
 *
 * Ogni pacchetto esce nella stessa forma di sempre:
 *   ['price' => 4.99, 'price_cents' => 499, 'shards' => 80,
 *    'name' => '80 Godo Shards', 'highlight' => 'pity']
 */

require_once __DIR__ . '/../security_helpers.php';

function gacha_packages_fallback(): array
{
    $rows = [
        ['shards_5', 5, 59, ''],
        ['shards_10', 10, 99, ''],
        ['shards_25', 25, 199, ''],
        ['shards_45', 45, 299, ''],
        ['shards_80', 80, 499, 'pity'],
        ['shards_180', 180, 999, ''],
        ['shards_400', 400, 1999, 'best'],
        ['shards_1200', 1200, 4999, 'best'],
    ];

    $packages = [];
    foreach ($rows as [$slug, $shards, $cents, $highlight]) {
        $packages[$slug] = [
            'price' => $cents / 100,
            'price_cents' => $cents,
            'shards' => $shards,
            'name' => $shards . ' Godo Shards',
            'highlight' => $highlight,
        ];
    }

    return $packages;
}

function gacha_packages_table_ready(?mysqli $mysqli): bool
{
    return $mysqli instanceof mysqli && auth_table_exists($mysqli, 'shop_pacchetti_shards');
}

function gacha_package_from_row(array $row): array
{
    $cents = (int)$row['prezzo_cent'];

    return [
        'price' => $cents / 100,
        'price_cents' => $cents,
        'shards' => (int)$row['shards'],
        'name' => (string)$row['nome'],
        'highlight' => in_array($row['evidenza'], ['pity', 'best'], true) ? $row['evidenza'] : '',
    ];
}

/**
 * I pacchetti in vendita, nell'ordine scelto dall'admin.
 */
function gacha_packages_for_sale(?mysqli $mysqli): array
{
    if (!gacha_packages_table_ready($mysqli)) {
        return gacha_packages_fallback();
    }

    try {
        $result = $mysqli->query(
            'SELECT * FROM shop_pacchetti_shards
             WHERE attivo = 1 AND archiviato_at IS NULL
             ORDER BY posizione ASC, prezzo_cent ASC, id ASC'
        );
    } catch (Throwable $e) {
        error_log('[gacha_catalog] ' . $e->getMessage());
        return gacha_packages_fallback();
    }

    if (!$result) {
        return gacha_packages_fallback();
    }

    $packages = [];
    while ($row = $result->fetch_assoc()) {
        $packages[(string)$row['slug']] = gacha_package_from_row($row);
    }
    $result->free();

    return $packages;
}

/**
 * Un pacchetto da mettere in un checkout nuovo: solo se e' in vendita adesso.
 */
function gacha_package_for_checkout(?mysqli $mysqli, string $slug): ?array
{
    $packages = gacha_packages_for_sale($mysqli);
    return $packages[$slug] ?? null;
}

/**
 * Un pacchetto per chiudere un pagamento gia' partito (webhook Stripe,
 * capture PayPal): lo si cerca anche se nel frattempo l'admin l'ha spento o
 * archiviato, altrimenti chi ha appena pagato resterebbe senza Shards.
 */
function gacha_package_for_payment(?mysqli $mysqli, string $slug): ?array
{
    if (!gacha_packages_table_ready($mysqli)) {
        return gacha_packages_fallback()[$slug] ?? null;
    }

    try {
        $stmt = $mysqli->prepare('SELECT * FROM shop_pacchetti_shards WHERE slug = ? LIMIT 1');
        if (!$stmt) {
            return gacha_packages_fallback()[$slug] ?? null;
        }
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[gacha_catalog] ' . $e->getMessage());
        return gacha_packages_fallback()[$slug] ?? null;
    }

    return $row ? gacha_package_from_row($row) : null;
}

/**
 * Prezzo e Shards di un pacchetto nel momento in cui parte il checkout.
 *
 * Se l'admin cambia il prezzo mentre qualcuno sta pagando, il pagamento va
 * confrontato con il prezzo che quella persona ha visto, non con quello
 * nuovo. Per Stripe la fotografia viaggia nei metadata della sessione (li
 * scrive solo il nostro server con la chiave segreta); per PayPal resta
 * nella sessione PHP di chi paga, perche' il custom_id di un ordine PayPal
 * potrebbe scriverlo anche il browser.
 */
function gacha_snapshot_amounts(array $package): array
{
    return [
        'price_cents' => (int)$package['price_cents'],
        'shards' => (int)$package['shards'],
    ];
}

function gacha_paypal_remember_order(string $orderId, string $packageId, array $package): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $orders = $_SESSION['gacha_paypal_orders'] ?? [];
    if (!is_array($orders)) {
        $orders = [];
    }

    // Si tengono solo gli ultimi ordini di questa sessione.
    $orders = array_slice($orders, -9, null, true);
    $orders[$orderId] = gacha_snapshot_amounts($package) + ['package_id' => $packageId, 'at' => time()];
    $_SESSION['gacha_paypal_orders'] = $orders;
}

/**
 * Il pacchetto con cui chiudere una capture PayPal: la fotografia presa alla
 * creazione dell'ordine se c'e' (e riguarda lo stesso pacchetto), altrimenti
 * i valori attuali.
 */
function gacha_paypal_package_for_capture(?mysqli $mysqli, string $orderId, string $packageId): ?array
{
    $package = gacha_package_for_payment($mysqli, $packageId);
    if ($package === null) {
        return null;
    }

    $snapshot = $_SESSION['gacha_paypal_orders'][$orderId] ?? null;
    if (is_array($snapshot) && ($snapshot['package_id'] ?? '') === $packageId && (int)($snapshot['price_cents'] ?? 0) > 0) {
        $package['price_cents'] = (int)$snapshot['price_cents'];
        $package['price'] = $package['price_cents'] / 100;
        $package['shards'] = (int)$snapshot['shards'];
    }

    return $package;
}

function gacha_paypal_forget_order(string $orderId): void
{
    if (isset($_SESSION['gacha_paypal_orders'][$orderId])) {
        unset($_SESSION['gacha_paypal_orders'][$orderId]);
    }
}

/* ── Impostazioni ─────────────────────────────────────────────────────── */

function gacha_setting(?mysqli $mysqli, string $key, string $default): string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!$mysqli instanceof mysqli || !auth_table_exists($mysqli, 'shop_impostazioni')) {
        return $cache[$key] = $default;
    }

    try {
        $stmt = $mysqli->prepare('SELECT valore FROM shop_impostazioni WHERE chiave = ? LIMIT 1');
        if (!$stmt) {
            return $cache[$key] = $default;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[gacha_catalog] impostazione ' . $key . ': ' . $e->getMessage());
        return $cache[$key] = $default;
    }

    return $cache[$key] = $row ? (string)$row[0] : $default;
}

/**
 * Quanti Godos costa una Shard nella conversione. Si cambia dal pannello
 * (solo owner); senza migrazione resta 100, come e' sempre stato.
 */
function gacha_godos_per_shard(?mysqli $mysqli): int
{
    $value = (int)gacha_setting($mysqli, 'godos_per_shard', '100');
    return max(1, min(1000000, $value));
}

/* ── Oggetti Godos a tempo ────────────────────────────────────────────── */

/**
 * 'aperto', 'presto' (non ancora iniziato) o 'finito'. Le colonne arrivano
 * con la migrazione: senza, ogni oggetto e' semplicemente aperto.
 */
function gacha_item_window(array $item, ?int $now = null): string
{
    $now = $now ?? time();
    $from = !empty($item['disponibile_dal']) ? strtotime((string)$item['disponibile_dal']) : false;
    $until = !empty($item['disponibile_fino']) ? strtotime((string)$item['disponibile_fino']) : false;

    if ($from !== false && $from > $now) {
        return 'presto';
    }
    if ($until !== false && $until <= $now) {
        return 'finito';
    }

    return 'aperto';
}

/* ── Storico degli acquisti di Shards ─────────────────────────────────── */

/*
 * Queste funzioni non lanciano mai eccezioni e non stanno mai dentro la
 * transazione dell'accredito: lo storico e' un di piu' per l'assistenza, un
 * suo errore non deve poter bloccare o annullare le Shards di chi ha pagato.
 */

function gacha_orders_ready(?mysqli $mysqli): bool
{
    return $mysqli instanceof mysqli && auth_table_exists($mysqli, 'shop_ordini');
}

function gacha_order_pending(?mysqli $mysqli, int $userId, string $packageId, string $provider, string $ref, array $package): void
{
    if (!gacha_orders_ready($mysqli) || $userId <= 0 || $ref === '') {
        return;
    }

    try {
        $cents = (int)$package['price_cents'];
        $shards = (int)$package['shards'];
        $stmt = $mysqli->prepare(
            'INSERT IGNORE INTO shop_ordini (user_id, pacchetto, provider, provider_ref, importo_cent, shards_base)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('isssii', $userId, $packageId, $provider, $ref, $cents, $shards);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('[gacha_orders] in attesa ' . $ref . ': ' . $e->getMessage());
    }
}

function gacha_order_paid(?mysqli $mysqli, int $userId, string $packageId, string $provider, string $ref, int $cents, int $baseShards, int $creditedShards, bool $bonus): void
{
    if (!gacha_orders_ready($mysqli) || $userId <= 0 || $ref === '') {
        return;
    }

    try {
        $bonusFlag = $bonus ? 1 : 0;
        $stmt = $mysqli->prepare(
            "INSERT INTO shop_ordini
                (user_id, pacchetto, provider, provider_ref, importo_cent, shards_base, shards_accreditate, bonus, stato, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pagato', NOW())
             ON DUPLICATE KEY UPDATE
                importo_cent = VALUES(importo_cent), shards_base = VALUES(shards_base),
                shards_accreditate = VALUES(shards_accreditate), bonus = VALUES(bonus),
                stato = 'pagato', nota = NULL, paid_at = NOW()"
        );
        if ($stmt) {
            $stmt->bind_param('isssiiii', $userId, $packageId, $provider, $ref, $cents, $baseShards, $creditedShards, $bonusFlag);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('[gacha_orders] pagato ' . $ref . ': ' . $e->getMessage());
    }
}

/**
 * Un pagamento che non si e' chiuso. Non sovrascrive mai un ordine gia'
 * pagato: un evento Stripe ripetuto o in ritardo non deve farlo sembrare
 * fallito.
 */
function gacha_order_error(?mysqli $mysqli, int $userId, string $packageId, string $provider, string $ref, int $cents, string $note): void
{
    if (!gacha_orders_ready($mysqli) || $userId <= 0 || $ref === '') {
        return;
    }

    try {
        $note = mb_substr($note, 0, 250);
        $stmt = $mysqli->prepare(
            "INSERT INTO shop_ordini (user_id, pacchetto, provider, provider_ref, importo_cent, stato, nota)
             VALUES (?, ?, ?, ?, ?, 'errore', ?)
             ON DUPLICATE KEY UPDATE
                nota = IF(stato = 'pagato', nota, VALUES(nota)),
                stato = IF(stato = 'pagato', stato, 'errore')"
        );
        if ($stmt) {
            $stmt->bind_param('isssis', $userId, $packageId, $provider, $ref, $cents, $note);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('[gacha_orders] errore ' . $ref . ': ' . $e->getMessage());
    }
}
