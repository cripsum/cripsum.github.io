<?php
/**
 * Shop Gacha: /it/shop e /en/shop.
 *
 * Una pagina sola per le due lingue (prima erano due file quasi identici da
 * 770 righe). Si include da it/shop.php con $shopLang impostata, $mysqli
 * aperta, login gia' verificato e paypal_config.php caricato.
 *
 * I pagamenti non passano di qui: la pagina apre il checkout Stripe
 * (/api/create_shard_checkout_session.php) o i bottoni PayPal
 * (/api/create_paypal_shard_order.php e /api/capture_paypal_shard_order.php)
 * esattamente come prima, con gli stessi id e gli stessi parametri.
 */

require_once __DIR__ . '/../shop_common.php';
require_once __DIR__ . '/../gacha_catalog.php';

$en = $shopLang === 'en';

$G = $en ? [
    'title' => 'Godo Shards Shop',
    'godos_hint' => 'Free currency obtained by using the website.',
    'shards_hint' => 'Premium currency used to pull.',
    'back' => 'Back to Gacha',
    'tab_buy' => 'Buy Shards',
    'tab_godos' => 'Godos Shop',
    'bonus_strip' => 'The first purchase of each pack is doubled: the big number already includes your free Shards.',
    'bonus_badge' => 'x2 Bonus',
    'bonus_title' => 'The first purchase of this package doubles the shards!',
    'bonus_note' => '+%d Shards Free!',
    'pity' => '⭐ Full pity',
    'best' => 'Best offer',
    'value' => '+%d%% value',
    'per_shard' => '%s per Shard',
    'buy' => 'Buy',
    'no_packages' => 'No packs on sale right now. Come back soon.',
    'pulls' => '%d Pulls',
    'pulls_multi' => '%d Pulls (%d Multi)',
    'pulls_mixed' => '%d Pulls (%d Multi + %d Pulls)',
    'rate_badge' => '%s Godos = 1 Shard',
    'convert_title' => 'Convert Godos to Shards',
    'convert_cost' => 'Cost: %s Godos / each',
    'convert_btn' => 'Convert Godos',
    'gacha_pulls' => 'Gacha Pulls',
    'left' => 'Only %d left!',
    'sold_out' => 'Sold out',
    'unlimited' => 'Unlimited availability',
    'ends_in' => 'Ends in',
    'starts_on' => 'From %s',
    'coming' => 'Coming soon',
    'exclusive' => 'Exclusive Badge',
    'cost' => 'Cost: %s Godos',
    'owned' => 'Owned',
    'missing' => 'You need %s more Godos',
    'no_items' => 'Nothing to buy with Godos right now.',
    'pay_success' => 'Payment completed! Your Shards have been credited.',
    'pay_pending' => 'Payment received: crediting your Shards…',
    'pay_cancel' => 'Payment cancelled.',
    'pay_invalid' => 'That pack is no longer on sale.',
    'conv_success' => 'Conversion completed',
    'conv_error' => 'Conversion failed. Check your balance and try again.',
    'close' => 'Close',
    'secure' => 'Secure checkout',
    'choose_pay' => 'Choose how to pay',
    'buying' => 'You are purchasing <strong id="modal-pkg-name"></strong> for <strong id="modal-pkg-price"></strong>.',
    'card' => 'Pay by card',
    'or' => 'or',
    'paypal_loading' => 'Loading PayPal…',
    'convert_kicker' => '%s Godos = 1 Shard',
    'convert_modal' => 'Convert Godos',
    'total_cost' => 'Total cost',
    'cancel' => 'Cancel',
    'confirm_conversion' => 'Confirm conversion',
    'confirm_kicker' => 'Confirm Purchase',
    'confirm_title' => 'Are you sure?',
    'confirm_text' => 'Do you want to purchase this item for your profile?',
    'price' => 'Price:',
    'confirm_buy' => 'Confirm Purchase',
    'unlocked' => 'Unlocked!',
    'completed' => 'Purchase Completed',
    'bought_badge' => 'You have successfully purchased this badge.',
    'awesome' => 'Awesome!',
    'max' => 'Max',
    'quantity' => 'Shards to buy',
] : [
    'title' => 'Shop Godo Shards',
    'godos_hint' => 'Valuta gratuita ottenibile usando il sito.',
    'shards_hint' => 'Valuta premium usata per pullare.',
    'back' => 'Torna al Gacha',
    'tab_buy' => 'Acquista Shards',
    'tab_godos' => 'Negozio Godos',
    'bonus_strip' => 'Il primo acquisto di ogni pacchetto vale doppio: il numero grande comprende già le Shards in regalo.',
    'bonus_badge' => 'x2 Bonus',
    'bonus_title' => 'Il primo acquisto di questo pacchetto raddoppia le shards!',
    'bonus_note' => '+%d Shards Gratis!',
    'pity' => '⭐ Pity completo',
    'best' => 'Miglior offerta',
    'value' => '+%d%% valore',
    'per_shard' => '%s a Shard',
    'buy' => 'Acquista',
    'no_packages' => 'Nessun pacchetto in vendita al momento. Torna presto.',
    'pulls' => '%d Pull',
    'pulls_multi' => '%d Pull (%d Multi)',
    'pulls_mixed' => '%d Pull (%d Multi + %d Pull)',
    'rate_badge' => '%s Godos = 1 Shard',
    'convert_title' => 'Converti Godos in Shards',
    'convert_cost' => 'Costo: %s Godos / cad',
    'convert_btn' => 'Converti Godos',
    'gacha_pulls' => 'Gacha Pulls',
    'left' => 'Solo %d rimasti!',
    'sold_out' => 'Esaurito',
    'unlimited' => 'Disponibilità illimitata',
    'ends_in' => 'Termina tra',
    'starts_on' => 'Dal %s',
    'coming' => 'In arrivo',
    'exclusive' => 'Badge Esclusivo',
    'cost' => 'Costo: %s Godos',
    'owned' => 'Posseduto',
    'missing' => 'Ti mancano %s Godos',
    'no_items' => 'Niente da comprare con i Godos, per ora.',
    'pay_success' => 'Pagamento completato! Le tue Shards sono state accreditate.',
    'pay_pending' => 'Pagamento ricevuto: accredito delle Shards in corso…',
    'pay_cancel' => 'Pagamento annullato.',
    'pay_invalid' => 'Questo pacchetto non è più in vendita.',
    'conv_success' => 'Conversione completata',
    'conv_error' => 'Conversione non riuscita. Controlla il saldo e riprova.',
    'close' => 'Chiudi',
    'secure' => 'Checkout sicuro',
    'choose_pay' => 'Scegli come pagare',
    'buying' => 'Stai acquistando <strong id="modal-pkg-name"></strong> per <strong id="modal-pkg-price"></strong>.',
    'card' => 'Paga con carta',
    'or' => 'oppure',
    'paypal_loading' => 'Caricamento PayPal…',
    'convert_kicker' => '%s Godos = 1 Shard',
    'convert_modal' => 'Converti Godos',
    'total_cost' => 'Costo totale',
    'cancel' => 'Annulla',
    'confirm_conversion' => 'Conferma conversione',
    'confirm_kicker' => 'Conferma Acquisto',
    'confirm_title' => 'Sei sicuro?',
    'confirm_text' => 'Vuoi acquistare questo oggetto per il tuo profilo?',
    'price' => 'Prezzo:',
    'confirm_buy' => 'Conferma Acquisto',
    'unlocked' => 'Oggetto Sbloccato!',
    'completed' => 'Acquisto Completato',
    'bought_badge' => 'Hai acquistato correttamente il badge.',
    'awesome' => 'Fantastico!',
    'max' => 'Max',
    'quantity' => 'Shards da comprare',
];

$num = static fn($value): string => $en ? number_format((float)$value, 0, '.', ',') : number_format((float)$value, 0, ',', '.');
$money = static fn(float $value): string => $en ? '€' . number_format($value, 2, '.', ',') : '€' . number_format($value, 2, ',', '.');

$userId = (int)$_SESSION['user_id'];

/* ── Saldo ───────────────────────────────────────────────────────────── */

$stmtUser = $mysqli->prepare('SELECT soldi, godoshards_balance FROM utenti WHERE id = ? LIMIT 1');
$stmtUser->bind_param('i', $userId);
$stmtUser->execute();
$resUser = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$soldi = (int)($resUser['soldi'] ?? 0);
$godoshards = (int)($resUser['godoshards_balance'] ?? 0);

/* ── Oggetti Godos ───────────────────────────────────────────────────── */

// Ordine, archivio e date arrivano con la migrazione dello shop: prima di
// applicarla gli oggetti restano ordinati per prezzo, come sempre.
$godosHasPosition = auth_column_exists($mysqli, 'godos_shop_items', 'posizione');
$godosArchiveFilter = auth_column_exists($mysqli, 'godos_shop_items', 'archiviato_at') ? ' AND gsi.archiviato_at IS NULL' : '';
$godosOrder = $godosHasPosition ? 'gsi.posizione ASC, gsi.price_godos ASC' : 'gsi.price_godos ASC';

$godosItems = [];
$resItems = $mysqli->query("
    SELECT gsi.*, cb.color, cb.glow, cb.animation, cb.badge_type, cb.name AS badge_name, cb.name_en AS badge_name_en
    FROM godos_shop_items gsi
    LEFT JOIN custom_badges cb ON cb.id = CAST(gsi.item_value AS UNSIGNED) AND gsi.item_type = 'badge'
    WHERE gsi.active = 1{$godosArchiveFilter}
    ORDER BY {$godosOrder}
");
if ($resItems) {
    foreach ($resItems->fetch_all(MYSQLI_ASSOC) as $item) {
        // Un oggetto a tempo scaduto sparisce da solo; uno non ancora
        // iniziato si vede, ma non si compra.
        $item['window'] = gacha_item_window($item);
        if ($item['window'] !== 'finito') {
            $godosItems[] = $item;
        }
    }
}

// Quanti ne sono gia' stati comprati: serve alla barra dei pezzi rimasti.
$soldCounts = [];
if (auth_table_exists($mysqli, 'user_godos_shop_purchases')) {
    $resSold = $mysqli->query('SELECT item_id, COUNT(*) AS n FROM user_godos_shop_purchases GROUP BY item_id');
    if ($resSold) {
        while ($row = $resSold->fetch_assoc()) {
            $soldCounts[(int)$row['item_id']] = (int)$row['n'];
        }
    }
}

$ownedBadges = [];
$stmtOwned = $mysqli->prepare('SELECT badge_id FROM user_custom_badges WHERE utente_id = ?');
if ($stmtOwned) {
    $stmtOwned->bind_param('i', $userId);
    $stmtOwned->execute();
    $resOwned = $stmtOwned->get_result();
    while ($row = $resOwned->fetch_assoc()) {
        $ownedBadges[] = (int)$row['badge_id'];
    }
    $stmtOwned->close();
}

/* ── Pacchetti di Shards ─────────────────────────────────────────────── */

$usedBonuses = [];
$stmtB = $mysqli->prepare('SELECT package_id FROM first_purchase_bonuses WHERE user_id = ? AND first_purchase_bonus_used = 1');
if ($stmtB) {
    $stmtB->bind_param('i', $userId);
    $stmtB->execute();
    $resB = $stmtB->get_result();
    while ($row = $resB->fetch_assoc()) {
        $usedBonuses[] = $row['package_id'];
    }
    $stmtB->close();
}

// Pacchetti, prezzi ed evidenza si gestiscono dal pannello admin.
$packages = gacha_packages_for_sale($mysqli);
$anyBonus = false;
foreach (array_keys($packages) as $pid) {
    if (!in_array($pid, $usedBonuses, true)) {
        $anyBonus = true;
        break;
    }
}

// Il "+X% valore" si misura rispetto a 10 Shards a 0,99 €, come sempre.
$baseRate = 10 / 0.99;

$formatPulls = static function (int $shards) use ($G): string {
    $multi = intdiv($shards, 10);
    $single = $shards % 10;
    if ($multi > 0 && $single > 0) {
        return sprintf($G['pulls_mixed'], $shards, $multi, $single);
    }
    if ($multi > 0) {
        return sprintf($G['pulls_multi'], $shards, $multi);
    }
    return sprintf($G['pulls'], $shards);
};

$rate = gacha_godos_per_shard($mysqli);

/* ── Ritorno da un pagamento o da una conversione ────────────────────── */

$paymentStatus = (string)($_GET['payment'] ?? '');
$conversionStatus = (string)($_GET['conversion'] ?? '');
$convertedShards = max(0, (int)($_GET['shards'] ?? 0));
$invalidPackage = ($_GET['error'] ?? '') === 'invalid_package';

// Dopo Stripe le Shards arrivano con il webhook, che puo' metterci qualche
// secondo: se c'e' lo storico ordini la pagina aspetta quello invece di
// annunciare un accredito che magari non c'e' ancora.
$stripeSession = (string)($_GET['session_id'] ?? '');
$pendingSession = ($paymentStatus === 'success' && preg_match('/^cs_[A-Za-z0-9_]{8,250}$/', $stripeSession) && gacha_orders_ready($mysqli))
    ? $stripeSession
    : '';

$ogTitle = 'Cripsum™ - ' . $G['title'];
?>
<!DOCTYPE html>
<html lang="<?php echo $en ? 'en' : 'it'; ?>">

<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <meta charset="UTF-8">
    <title><?php echo shop_h($G['title']); ?> - Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo shop_h(cripsum_asset('/css/shop.css')); ?>">
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode(PAYPAL_CLIENT_ID); ?>&currency=EUR&locale=<?php echo $en ? 'en_US' : 'it_IT'; ?>"></script>
</head>

<body class="shop-shards-body" data-shards-shop
    data-lang="<?php echo $en ? 'en' : 'it'; ?>"
    data-user-godos="<?php echo $soldi; ?>"
    data-user-shards="<?php echo $godoshards; ?>"
    data-godos-per-shard="<?php echo $rate; ?>"
    data-pending-session="<?php echo shop_h($pendingSession); ?>">
    <?php include __DIR__ . '/../../navbar.php'; ?>

    <?php if ($paymentStatus === 'success' && $pendingSession !== ''): ?>
        <div class="shop-toast is-pending" id="payment-toast" role="status" data-toast-persist>
            <span class="shop-toast-spinner" aria-hidden="true"></span>
            <span><?php echo shop_h($G['pay_pending']); ?></span>
        </div>
    <?php elseif ($paymentStatus === 'success'): ?>
        <div class="shop-toast" id="payment-toast" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo shop_h($G['pay_success']); ?></span>
        </div>
    <?php elseif ($paymentStatus === 'cancel'): ?>
        <div class="shop-toast is-error" id="payment-toast" role="status">
            <i class="fa-solid fa-circle-xmark"></i>
            <span><?php echo shop_h($G['pay_cancel']); ?></span>
        </div>
    <?php elseif ($invalidPackage): ?>
        <div class="shop-toast is-error" id="payment-toast" role="status">
            <i class="fa-solid fa-circle-xmark"></i>
            <span><?php echo shop_h($G['pay_invalid']); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($conversionStatus === 'success'): ?>
        <div class="shop-toast" id="conversion-toast" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo shop_h($G['conv_success'] . ($convertedShards > 0 ? ': +' . $num($convertedShards) . ' Godo Shards' : '') . '.'); ?></span>
        </div>
    <?php elseif ($conversionStatus === 'error'): ?>
        <div class="shop-toast is-error" id="conversion-toast" role="status">
            <i class="fa-solid fa-circle-xmark"></i>
            <span><?php echo shop_h($G['conv_error']); ?></span>
        </div>
    <?php endif; ?>

    <div class="shop-container">
        <header class="shop-header">
            <h1 class="shop-title"><img src="/img/godoshards.png" alt="" class="shop-title-logo"> <?php echo shop_h($G['title']); ?> <img src="/img/godoshards.png" alt="" class="shop-title-logo"></h1>

            <div class="shop-balance-bar">
                <div class="shop-balance-item" title="<?php echo shop_h($G['godos_hint']); ?>" data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godos.png" alt="" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godos:</span>
                    <span class="shop-balance-val" data-shop-balance="godos"><?php echo $num($soldi); ?></span>
                </div>
                <div class="shop-balance-item" title="<?php echo shop_h($G['shards_hint']); ?>" data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godoshards.png" alt="" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godo Shards:</span>
                    <span class="shop-balance-val" data-shop-balance="shards"><?php echo $num($godoshards); ?></span>
                </div>
            </div>
            <div>
                <a href="lootbox" class="back-to-lootbox-btn">
                    <i class="fa-solid fa-arrow-left"></i> <?php echo shop_h($G['back']); ?>
                </a>
            </div>
        </header>

        <div class="shop-tabs" role="tablist">
            <button type="button" class="shop-tab-btn active" data-tab="tab-premium" data-tab-hash="shards" role="tab" aria-selected="true" aria-controls="tab-premium">
                <img src="/img/godoshards.png" alt="" class="tab-icon-img"> <?php echo shop_h($G['tab_buy']); ?>
            </button>
            <button type="button" class="shop-tab-btn" data-tab="tab-godos" data-tab-hash="godos" role="tab" aria-selected="false" aria-controls="tab-godos">
                <img src="/img/godos.png" alt="" class="tab-icon-img"> <?php echo shop_h($G['tab_godos']); ?>
            </button>
        </div>

        <div id="tab-premium" class="shop-tab-content active" role="tabpanel">
            <?php if ($anyBonus && $packages): ?>
                <p class="shop-bonus-strip"><span class="shop-badge badge-x2"><?php echo shop_h($G['bonus_badge']); ?></span> <?php echo shop_h($G['bonus_strip']); ?></p>
            <?php endif; ?>

            <main class="shop-grid">
                <?php if (!$packages): ?>
                    <p class="shop-grid-empty"><?php echo shop_h($G['no_packages']); ?></p>
                <?php endif; ?>

                <?php foreach ($packages as $pid => $pkg):
                    $price = $pkg['price'];
                    $shards = $pkg['shards'];
                    $isBonusAvailable = !in_array($pid, $usedBonuses, true);

                    // Valore rispetto al pacchetto base, 10 Shards a 0,99 €.
                    $savingsPercent = (int)round(($shards / $price / $baseRate - 1) * 100);

                    // Il numero grande e' quello che si riceve davvero, bonus compreso.
                    $displayShards = $isBonusAvailable ? $shards * 2 : $shards;
                    $perShard = $price / $displayShards;

                    $specialClass = $pkg['highlight'] === 'pity' ? 'is-pity' : ($pkg['highlight'] === 'best' ? 'is-best' : '');
                ?>
                    <div class="shop-card <?php echo $specialClass; ?>">
                        <div class="card-badges">
                            <?php if ($isBonusAvailable): ?>
                                <span class="shop-badge badge-x2" title="<?php echo shop_h($G['bonus_title']); ?>"><?php echo shop_h($G['bonus_badge']); ?></span>
                            <?php endif; ?>

                            <?php if ($pkg['highlight'] === 'pity'): ?>
                                <span class="shop-badge badge-pity"><?php echo shop_h($G['pity']); ?></span>
                            <?php elseif ($pkg['highlight'] === 'best'): ?>
                                <span class="shop-badge badge-best"><?php echo shop_h($G['best']); ?></span>
                            <?php endif; ?>

                            <?php if ($savingsPercent > 0): ?>
                                <span class="shop-badge badge-value"><?php echo shop_h(sprintf($G['value'], $savingsPercent)); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="card-shards-icon"><img src="/img/godoshards.png" alt="" width="60" height="60" class="card-shards-img"></div>

                        <div class="card-amount-wrap">
                            <?php if ($isBonusAvailable): ?>
                                <span class="card-amount-original"><?php echo $shards; ?></span>
                            <?php endif; ?>
                            <span class="card-amount"><?php echo $displayShards; ?></span>
                            <?php if ($isBonusAvailable): ?>
                                <small class="card-amount-bonus-note"><?php echo shop_h(sprintf($G['bonus_note'], $shards)); ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="card-equivalence"><?php echo shop_h($formatPulls($displayShards)); ?></div>

                        <div class="card-price">
                            <?php echo shop_h($money($price)); ?>
                            <small class="card-per-shard"><?php echo shop_h(sprintf($G['per_shard'], $en ? '€' . number_format($perShard, 3, '.', ',') : '€' . number_format($perShard, 3, ',', '.'))); ?></small>
                        </div>

                        <a class="card-btn js-buy-shards" data-shop-buy
                            href="/api/create_shard_checkout_session.php?package_id=<?php echo rawurlencode($pid); ?>"
                            data-package-id="<?php echo shop_h($pid); ?>"
                            data-package-name="<?php echo shop_h($pkg['name']); ?>"
                            data-package-price="<?php echo shop_h((string)$price); ?>">
                            <?php echo shop_h($G['buy']); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </main>
        </div>

        <div id="tab-godos" class="shop-tab-content" role="tabpanel">
            <main class="shop-grid">
                <div class="shop-card is-pity">
                    <div class="card-badges">
                        <span class="shop-badge badge-value"><?php echo shop_h(sprintf($G['rate_badge'], $num($rate))); ?></span>
                    </div>

                    <div class="card-shards-icon"><img src="/img/godoshards.png" alt="" width="60" height="60" class="card-shards-img"></div>

                    <div class="card-amount-wrap">
                        <span class="card-amount">Godo Shards</span>
                        <small class="card-amount-bonus-note card-note-muted"><?php echo shop_h($G['convert_title']); ?></small>
                    </div>

                    <div class="card-equivalence"><?php echo shop_h($G['gacha_pulls']); ?></div>

                    <div class="card-price card-price--godos"><?php echo shop_h(sprintf($G['convert_cost'], $num($rate))); ?></div>

                    <a class="card-btn card-btn--convert" id="open-godos-converter" data-shop-convert href="#converti">
                        <?php echo shop_h($G['convert_btn']); ?>
                    </a>
                </div>

                <?php foreach ($godosItems as $item):
                    $itemId = (int)$item['id'];
                    $availability = $item['availability'] !== null ? (int)$item['availability'] : null;
                    $sold = $soldCounts[$itemId] ?? 0;
                    $owned = in_array((int)$item['item_value'], $ownedBadges, true);
                    $soon = $item['window'] === 'presto';
                    $soldOut = $availability !== null && $availability <= 0;
                    $price = (int)$item['price_godos'];
                    $short = !$owned && !$soon && !$soldOut && $price > $soldi;
                    $name = $en ? (string)$item['name_en'] : (string)$item['name_it'];
                    $desc = $en ? (string)$item['description_en'] : (string)$item['description_it'];
                    $color = shop_hex($item['color'] ?? null, '#7c3aed');
                    $rgb = shop_hex_rgb($color);
                    $endsAt = !$soon && !empty($item['disponibile_fino']) ? strtotime((string)$item['disponibile_fino']) : false;
                    $startsAt = $soon && !empty($item['disponibile_dal']) ? strtotime((string)$item['disponibile_dal']) : false;

                    if ($owned) {
                        $state = 'owned';
                        $label = $G['owned'];
                    } elseif ($soon) {
                        $state = 'soon';
                        $label = $startsAt ? sprintf($G['starts_on'], date($en ? 'M j, H:i' : 'd/m H:i', $startsAt)) : $G['coming'];
                    } elseif ($soldOut) {
                        $state = 'soldout';
                        $label = $G['sold_out'];
                    } elseif ($short) {
                        $state = 'short';
                        $label = sprintf($G['missing'], $num($price - $soldi));
                    } else {
                        $state = 'buy';
                        $label = $G['buy'];
                    }
                ?>
                    <div class="shop-card <?php echo $owned ? 'is-owned' : ''; ?> <?php echo $availability !== null ? 'is-limited' : ''; ?> <?php echo $soon ? 'is-soon' : ''; ?>">
                        <div class="card-badges">
                            <?php if ($soon): ?>
                                <span class="shop-badge badge-time"><i class="fa-solid fa-hourglass-half"></i>&nbsp;<?php echo shop_h($G['coming']); ?></span>
                            <?php elseif ($availability !== null): ?>
                                <span class="shop-badge badge-value" data-item-availability="<?php echo $itemId; ?>">
                                    <?php echo shop_h($soldOut ? $G['sold_out'] : sprintf($G['left'], $availability)); ?>
                                </span>
                            <?php else: ?>
                                <span class="shop-badge badge-value"><?php echo shop_h($G['unlimited']); ?></span>
                            <?php endif; ?>

                            <?php if ($endsAt): ?>
                                <span class="shop-badge badge-time" data-countdown="<?php echo shop_h(date('c', $endsAt)); ?>">
                                    <?php echo shop_h($G['ends_in']); ?>&nbsp;<span data-countdown-output>…</span>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="shop-badge-preview <?php echo (int)($item['glow'] ?? 0) === 1 ? 'badge-glow' : ''; ?> badge-anim-<?php echo shop_h($item['animation'] ?: 'none'); ?>"
                            style="--badge-color: <?php echo shop_h($color); ?>; --badge-color-glow-alpha: rgba(<?php echo $rgb; ?>, 0.15); --badge-color-bg-alpha: rgba(<?php echo $rgb; ?>, 0.08); --badge-color-border-alpha: rgba(<?php echo $rgb; ?>, 0.25);">
                            <div class="profile-badge-art">
                                <img src="<?php echo shop_h(shop_asset_url($item['image_url'] ?? '')); ?>" alt="<?php echo shop_h($name); ?>">
                            </div>
                        </div>

                        <div class="card-amount-wrap">
                            <span class="card-amount card-amount--item"><?php echo shop_h($name); ?></span>
                            <small class="card-amount-bonus-note card-note-muted"><?php echo shop_h($desc); ?></small>
                        </div>

                        <?php if ($availability !== null && !$soon): ?>
                            <?php $total = max(1, $availability + $sold); ?>
                            <div class="shop-stock" role="img" aria-label="<?php echo shop_h(sprintf($G['left'], max(0, $availability))); ?>">
                                <span data-item-stock="<?php echo $itemId; ?>" data-stock-total="<?php echo $total; ?>" style="width: <?php echo round(max(0, $availability) / $total * 100, 1); ?>%"></span>
                            </div>
                        <?php endif; ?>

                        <div class="card-equivalence"><?php echo shop_h($G['exclusive']); ?></div>

                        <div class="card-price card-price--godos"><?php echo shop_h(sprintf($G['cost'], $num($price))); ?></div>

                        <button type="button" class="card-btn js-buy-item <?php echo $state !== 'buy' ? 'is-' . $state : ''; ?>"
                            data-shop-buy-item="<?php echo $itemId; ?>"
                            data-item-price="<?php echo $price; ?>"
                            data-item-type="<?php echo shop_h($item['item_type']); ?>"
                            data-item-name="<?php echo shop_h($name); ?>"
                            data-item-state="<?php echo $state; ?>"
                            <?php echo $state !== 'buy' ? 'disabled' : ''; ?>>
                            <?php echo shop_h($label); ?>
                        </button>
                    </div>
                <?php endforeach; ?>

                <?php if (!$godosItems): ?>
                    <p class="shop-grid-empty"><?php echo shop_h($G['no_items']); ?></p>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Pagamento: stessi id di sempre, li usa shards-shop.js -->
    <div class="shop-action-modal" id="paymentModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="paymentModalLabel" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker"><?php echo shop_h($G['secure']); ?></span><h2 id="paymentModalLabel"><?php echo shop_h($G['choose_pay']); ?></h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <div class="shop-action-modal__body">
                <p class="shop-action-modal__summary"><?php echo $G['buying']; ?></p>
                <div class="payment-options-grid">
                    <a class="payment-stripe-btn" id="stripe-checkout-btn" href="/api/create_shard_checkout_session.php"><i class="fa-solid fa-credit-card"></i><span><?php echo shop_h($G['card']); ?></span></a>
                    <div class="shop-payment-separator"><span><?php echo shop_h($G['or']); ?></span></div>
                    <div id="paypal-button-container" class="payment-paypal-container"><p class="shop-payment-status"><?php echo shop_h($G['paypal_loading']); ?></p></div>
                </div>
            </div>
        </section>
    </div>

    <!-- Conversione Godos -> Shards -->
    <div class="shop-action-modal" id="godosConversionModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="godosConversionTitle" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker"><?php echo shop_h(sprintf($G['convert_kicker'], $num($rate))); ?></span><h2 id="godosConversionTitle"><?php echo shop_h($G['convert_modal']); ?></h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <form class="shop-conversion-form" id="godos-conversion-form" action="/api/convert_godos_to_shards.php" method="post">
                <input type="hidden" name="return_to" value="/<?php echo $en ? 'en' : 'it'; ?>/shop.php">
                <?php echo csrf_field(); ?>
                <div class="shop-conversion-amount"><img src="/img/godoshards.png" alt=""><strong id="slider-shards-val">10</strong><span>Godo Shards</span></div>
                <p class="shop-conversion-pulls" id="slider-pulls-val"></p>
                <div class="shop-conversion-slider">
                    <input type="range" class="form-range" id="godos-slider" name="shards" min="1" max="100" value="10" aria-label="<?php echo shop_h($G['quantity']); ?>">
                    <div><span>Min: 1</span><span id="slider-max-label">Max: 100</span></div>
                </div>
                <div class="shop-conversion-quick">
                    <button type="button" data-conv-step="-10">−10</button>
                    <button type="button" data-conv-step="-1">−1</button>
                    <input type="number" id="godos-qty" min="1" step="1" value="10" inputmode="numeric" aria-label="<?php echo shop_h($G['quantity']); ?>">
                    <button type="button" data-conv-step="1">+1</button>
                    <button type="button" data-conv-step="10">+10</button>
                    <button type="button" data-conv-max><?php echo shop_h($G['max']); ?></button>
                </div>
                <div class="shop-conversion-total"><span><?php echo shop_h($G['total_cost']); ?></span><strong><span id="slider-godos-cost"><?php echo $num(10 * $rate); ?></span> Godos</strong></div>
                <p class="shop-form-error" data-shop-form-error hidden></p>
                <div class="shop-action-modal__actions">
                    <a class="shop-action-secondary" href="#" data-shop-close><?php echo shop_h($G['cancel']); ?></a>
                    <button type="submit" class="shop-action-primary" id="btn-confirm-godos-buy"><?php echo shop_h($G['confirm_conversion']); ?></button>
                </div>
            </form>
        </section>
    </div>

    <!-- Conferma acquisto con Godos -->
    <div class="shop-action-modal" id="godosPurchaseConfirmModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker"><?php echo shop_h($G['confirm_kicker']); ?></span><h2 id="confirmModalTitle"><?php echo shop_h($G['confirm_title']); ?></h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <div class="shop-action-modal__body shop-reveal-body">
                <p class="shop-reveal-text"><?php echo shop_h($G['confirm_text']); ?></p>
                <div class="reveal-card-wrap reveal-card-wrap--plain">
                    <div class="reveal-badge-box reveal-badge-box--plain" id="confirm-reveal-badge-box"></div>
                    <div class="reveal-badge-name" id="confirm-reveal-badge-name"></div>
                    <div class="reveal-badge-desc" id="confirm-reveal-badge-desc"></div>
                    <div class="reveal-badge-price"><?php echo shop_h($G['price']); ?> <span id="confirm-reveal-badge-price"></span> Godos</div>
                </div>
                <div class="shop-action-modal__actions">
                    <a class="shop-action-secondary" href="#" data-shop-close><?php echo shop_h($G['cancel']); ?></a>
                    <button type="button" class="shop-action-primary" id="btn-confirm-godos-purchase"><?php echo shop_h($G['confirm_buy']); ?></button>
                </div>
            </div>
        </section>
    </div>

    <!-- Acquisto con Godos riuscito -->
    <div class="shop-action-modal" id="purchaseSuccessModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="successModalTitle" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker" id="success-modal-title"><?php echo shop_h($G['unlocked']); ?></span><h2 id="successModalTitle"><?php echo shop_h($G['completed']); ?></h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="<?php echo shop_h($G['close']); ?>"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <div class="shop-action-modal__body shop-reveal-body">
                <div class="success-glow-ring"><i class="fa-solid fa-circle-check"></i></div>
                <p class="shop-reveal-text" id="success-modal-subtitle"><?php echo shop_h($G['bought_badge']); ?></p>
                <div class="reveal-card-wrap">
                    <div class="reveal-light-rays"></div>
                    <div class="reveal-badge-box" id="success-reveal-badge-box"></div>
                    <div class="reveal-badge-name" id="success-reveal-badge-name"></div>
                    <div class="reveal-badge-desc" id="success-reveal-badge-desc"></div>
                </div>
                <div class="shop-action-modal__actions shop-action-modal__actions--center">
                    <button type="button" class="card-btn card-btn--gold" data-shop-close><?php echo shop_h($G['awesome']); ?></button>
                </div>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/../../' . ($en ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?php echo shop_h(cripsum_asset('/assets/shop/shards-shop.js')); ?>" defer></script>
</body>

</html>
