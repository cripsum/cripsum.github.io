<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/paypal_config.php';

checkBan($mysqli);
requireLogin();

$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Load user balances
$stmtUser = $mysqli->prepare("SELECT soldi, godoshards_balance FROM utenti WHERE id = ? LIMIT 1");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resUser = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$soldi = (int)($resUser['soldi'] ?? 0);
$godoshards = (int)($resUser['godoshards_balance'] ?? 0);

// Load Godos shop items
$godosItems = [];
$queryGodosItems = "
    SELECT gsi.*, cb.color, cb.glow, cb.animation, cb.badge_type, cb.name as badge_name, cb.name_en as badge_name_en
    FROM godos_shop_items gsi
    LEFT JOIN custom_badges cb ON cb.id = CAST(gsi.item_value AS UNSIGNED) AND gsi.item_type = 'badge'
    WHERE gsi.active = 1
    ORDER BY gsi.price_godos ASC
";
$resItems = $mysqli->query($queryGodosItems);
if ($resItems) {
    $godosItems = $resItems->fetch_all(MYSQLI_ASSOC);
}

// Load owned badges
$ownedBadges = [];
$resOwned = $mysqli->query("SELECT badge_id FROM user_custom_badges WHERE utente_id = " . $userId);
if ($resOwned) {
    while ($row = $resOwned->fetch_assoc()) {
        $ownedBadges[] = (int)$row['badge_id'];
    }
}

if (!function_exists('shop_hex_to_rgb')) {
    function shop_hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }
}

// Load used first purchase bonuses
$stmtB = $mysqli->prepare("SELECT package_id FROM first_purchase_bonuses WHERE user_id = ? AND first_purchase_bonus_used = 1");
$stmtB->bind_param("i", $userId);
$stmtB->execute();
$resB = $stmtB->get_result();
$usedBonuses = [];
while ($row = $resB->fetch_assoc()) {
    $usedBonuses[] = $row['package_id'];
}
$stmtB->close();

$packages = [
    'shards_5' => ['price' => 0.59, 'shards' => 5, 'name' => '5 Godo Shards'],
    'shards_10' => ['price' => 0.99, 'shards' => 10, 'name' => '10 Godo Shards'],
    'shards_25' => ['price' => 1.99, 'shards' => 25, 'name' => '25 Godo Shards'],
    'shards_45' => ['price' => 2.99, 'shards' => 45, 'name' => '45 Godo Shards'],
    'shards_80' => ['price' => 4.99, 'shards' => 80, 'name' => '80 Godo Shards'],
    'shards_180' => ['price' => 9.99, 'shards' => 180, 'name' => '180 Godo Shards'],
    'shards_400' => ['price' => 19.99, 'shards' => 400, 'name' => '400 Godo Shards'],
    'shards_1200' => ['price' => 49.99, 'shards' => 1200, 'name' => '1200 Godo Shards'],
];

$basePrice = 0.99;
$baseShards = 10;
$baseRate = $baseShards / $basePrice; // 10.101 shards per EUR

function formatEquivalence($shards) {
    $multi = floor($shards / 10);
    $single = $shards % 10;
    if ($multi > 0) {
        if ($single > 0) {
            return "{$shards} Pulls ({$multi} Multi + {$single} Pulls)";
        } else {
            return "{$shards} Pulls ({$multi} Multi)";
        }
    } else {
        return "{$shards} Pulls";
    }
}

$paymentStatus = $_GET['payment'] ?? '';
$successPackage = $_GET['package_id'] ?? '';
$conversionStatus = $_GET['conversion'] ?? '';
$convertedShards = max(0, (int)($_GET['shards'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Godo Shards Shop - Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/shop.css?v=2.2">
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode(PAYPAL_CLIENT_ID); ?>&currency=EUR&locale=en_US"></script>
    <style>
        .shop-toast {
            position: fixed;
            top: 5rem;
            right: 2rem;
            z-index: 1050;
            background: rgba(16, 185, 129, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            animation: slideInRight 0.3s ease forwards;
        }
        .shop-toast.is-error {
            background: rgba(239, 68, 68, 0.9);
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>

<body class="shop-shards-body" data-shards-shop data-lang="en" data-user-godos="<?= $soldi ?>" data-user-shards="<?= $godoshards ?>">
    <?php include '../includes/navbar.php'; ?>

    <?php if ($paymentStatus === 'success'): ?>
        <div class="shop-toast" id="payment-toast">
            <i class="fa-solid fa-circle-check"></i>
            <span>Payment completed successfully! Your Shards have been credited.</span>
        </div>
    <?php elseif ($paymentStatus === 'cancel'): ?>
        <div class="shop-toast is-error" id="payment-toast">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Payment cancelled by the user.</span>
        </div>
    <?php endif; ?>

    <?php if ($conversionStatus === 'success'): ?>
        <div class="shop-toast" id="conversion-toast">
            <i class="fa-solid fa-circle-check"></i>
            <span>Conversion completed<?= $convertedShards > 0 ? ': +' . number_format($convertedShards) . ' Godo Shards' : '' ?>.</span>
        </div>
    <?php elseif ($conversionStatus === 'error'): ?>
        <div class="shop-toast is-error" id="conversion-toast">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Conversion failed. Check your balance and try again.</span>
        </div>
    <?php endif; ?>

    <div class="shop-container">
        <header class="shop-header">
            <h1 class="shop-title"><img src="/img/godoshards.png" alt="Logo" class="shop-title-logo"> Godo Shards Shop <img src="/img/godoshards.png" alt="Logo" class="shop-title-logo"></h1>

            <div class="shop-balance-bar">
                <div class="shop-balance-item" title="Free currency obtained by using the website." data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godos.png" alt="Godos" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godos:</span>
                    <span class="shop-balance-val" data-shop-balance="godos"><?= number_format($soldi) ?></span>
                </div>
                <div class="shop-balance-item" title="Premium currency used to pull." data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godoshards.png" alt="Godo Shards" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godo Shards:</span>
                    <span class="shop-balance-val" data-shop-balance="shards"><?= number_format($godoshards) ?></span>
                </div>
            </div>
            <div>
                <a href="lootbox" class="back-to-lootbox-btn">
                    <i class="fa-solid fa-arrow-left"></i> Back to Gacha
                </a>
            </div>
        </header>

        <!-- Tabs Nav -->
        <div class="shop-tabs">
            <button type="button" class="shop-tab-btn active" data-tab="tab-premium"><img src="/img/godoshards.png" alt="Shards" class="tab-icon-img"> Buy Shards</button>
            <button type="button" class="shop-tab-btn" data-tab="tab-godos"><img src="/img/godos.png" alt="Godos" class="tab-icon-img"> Godos Shop</button>
        </div>

        <div id="tab-premium" class="shop-tab-content active">
            <main class="shop-grid">
                <?php foreach ($packages as $pid => $pkg):
                    $price = $pkg['price'];
                    $shards = $pkg['shards'];
                    $isBonusAvailable = !in_array($pid, $usedBonuses);
                    
                    // Calculate savings value compared to €0.99 (10 shards)
                    $currentRate = $shards / $price;
                    $savingsPercent = round(($currentRate / $baseRate - 1) * 100);
                    
                    // Visual Shards amount (doubled if x2 active)
                    $displayShards = $isBonusAvailable ? ($shards * 2) : $shards;
                    
                    // Special classes for highlighted cards
                    $specialClass = '';
                    if ($pid === 'shards_80') {
                        $specialClass = 'is-pity';
                    } elseif ($pid === 'shards_400' || $pid === 'shards_1200') {
                        $specialClass = 'is-best';
                    }
                ?>
                    <div class="shop-card <?= $specialClass ?>">
                        <div class="card-badges">
                            <?php if ($isBonusAvailable): ?>
                                <span class="shop-badge badge-x2" title="The first purchase of this package doubles the shards!">x2 Bonus</span>
                            <?php endif; ?>

                            <?php if ($pid === 'shards_80'): ?>
                                <span class="shop-badge badge-pity">⭐ Full pity</span>
                            <?php elseif ($pid === 'shards_400' || $pid === 'shards_1200'): ?>
                                <span class="shop-badge badge-best">Best offer</span>
                            <?php endif; ?>

                            <?php if ($savingsPercent > 0): ?>
                                <span class="shop-badge badge-value">+<?= $savingsPercent ?>% value</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-shards-icon"><img src="/img/godoshards.png" alt="Godo Shards" style="width: 60px; height: 60px; object-fit: contain;"></div>

                        <div class="card-amount-wrap">
                            <?php if ($isBonusAvailable): ?>
                                <span class="card-amount-original"><?= $shards ?></span>
                            <?php endif; ?>
                            <span class="card-amount"><?= $displayShards ?></span>
                            <?php if ($isBonusAvailable): ?>
                                <small class="card-amount-bonus-note">+<?= $shards ?> Shards Free!</small>
                            <?php endif; ?>
                        </div>

                        <div class="card-equivalence">
                            <?= htmlspecialchars(formatEquivalence($displayShards)) ?>
                        </div>

                        <div class="card-price">
                            €<?= number_format($price, 2, '.', ',') ?>
                        </div>

                        <a class="card-btn js-buy-shards" data-shop-buy
                            href="/api/create_shard_checkout_session.php?package_id=<?= rawurlencode($pid) ?>"
                            data-package-id="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>"
                            data-package-name="<?= htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-package-price="<?= htmlspecialchars((string)$price, ENT_QUOTES, 'UTF-8') ?>">
                            Buy
                        </a>
                    </div>
                <?php endforeach; ?>
            </main>
        </div>

        <div id="tab-godos" class="shop-tab-content">
            <main class="shop-grid">
                <!-- Godos to Shards card -->
                <div class="shop-card is-pity">
                    <div class="card-badges">
                        <span class="shop-badge badge-value">100 Points = 1 Shard</span>
                    </div>

                    <div class="card-shards-icon">
                        <img src="/img/godoshards.png" alt="Godo Shards" style="width: 60px; height: 60px; object-fit: contain;">
                    </div>

                    <div class="card-amount-wrap">
                        <span class="card-amount">Godo Shards</span>
                        <small class="card-amount-bonus-note" style="display: block; min-height: 28px;">Convert Godos to Shards</small>
                    </div>

                    <div class="card-equivalence">
                        Gacha Pulls
                    </div>

                    <div class="card-price" style="font-size: 1.15rem; color: #a855f7;">
                        Cost: 100 Godos / each
                    </div>

                    <a class="card-btn" id="open-godos-converter" data-shop-convert href="#godosConversionModal" style="background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); border: none;">
                        Convert Points
                    </a>
                </div>

                <!-- Database items -->
                <?php foreach ($godosItems as $item): 
                    $isLimited = ($item['availability'] !== null);
                    $owned = in_array((int)$item['item_value'], $ownedBadges, true);
                    $name = ($lang === 'it') ? $item['name_it'] : $item['name_en'];
                    $desc = ($lang === 'it') ? $item['description_it'] : $item['description_en'];
                ?>
                    <div class="shop-card <?= $owned ? 'is-owned' : '' ?> <?= $isLimited ? 'is-limited' : '' ?>">
                        <div class="card-badges">
                            <?php if ($isLimited): ?>
                                <span class="shop-badge badge-value" data-item-availability="<?= $item['id'] ?>">
                                    Only <?= $item['availability'] ?> left!
                                </span>
                            <?php else: ?>
                                <span class="shop-badge badge-value">Unlimited Availability</span>
                            <?php endif; ?>
                        </div>

                        <!-- Badge Preview inside Card -->
                        <div class="shop-badge-preview <?= !empty($item['glow']) && (int)$item['glow'] === 1 ? 'badge-glow' : '' ?> badge-anim-<?= htmlspecialchars($item['animation'] ?: 'none') ?>" style="--badge-color: <?= htmlspecialchars($item['color'] ?: '#7c3aed') ?>; <?php
                            if (!empty($item['color'])) {
                                $rgb = shop_hex_to_rgb($item['color']);
                                if ($rgb) {
                                    $rgbStr = "{$rgb[0]}, {$rgb[1]}, {$rgb[2]}";
                                    echo "--badge-color-glow-alpha: rgba({$rgbStr}, 0.15); --badge-color-bg-alpha: rgba({$rgbStr}, 0.08); --badge-color-border-alpha: rgba({$rgbStr}, 0.25);";
                                }
                            }
                        ?>">
                            <div class="profile-badge-art">
                                <img src="<?= htmlspecialchars($item['image_url'] ?: '') ?>" alt="<?= htmlspecialchars($name) ?>">
                            </div>
                        </div>

                        <div class="card-amount-wrap">
                            <span class="card-amount"><?= htmlspecialchars($name) ?></span>
                            <small class="card-amount-bonus-note" style="display: block; min-height: 28px;"><?= htmlspecialchars($desc) ?></small>
                        </div>

                        <div class="card-equivalence">
                            Exclusive Badge
                        </div>

                        <div class="card-price" style="font-size: 1.15rem; color: #a855f7;">
                            Cost: <?= number_format($item['price_godos'], 0, '.', ',') ?> Godos
                        </div>

                        <button class="card-btn js-buy-item" 
                                data-shop-buy-item="<?= $item['id'] ?>" 
                                data-item-price="<?= $item['price_godos'] ?>"
                                data-item-type="<?= $item['item_type'] ?>"
                                data-item-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $owned ? 'disabled' : '' ?>
                                style="<?= $owned ? 'background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.3); border: 1px solid rgba(255,255,255,0.05); cursor: not-allowed; transform: none; box-shadow: none; filter: none;' : '' ?>">
                            <?= $owned ? 'Owned' : 'Buy' ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </main>
        </div>
    </div>

    <!-- Standalone controller: no Bootstrap modal dependency -->
    <div class="shop-action-modal" id="paymentModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="Close"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="paymentModalLabel" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker">Secure checkout</span><h2 id="paymentModalLabel">Choose how to pay</h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <div class="shop-action-modal__body">
                <p class="shop-action-modal__summary">You are purchasing <strong id="modal-pkg-name"></strong> for <strong id="modal-pkg-price"></strong>.</p>
                <div class="payment-options-grid">
                    <a class="payment-stripe-btn" id="stripe-checkout-btn" href="/api/create_shard_checkout_session.php"><i class="fa-solid fa-credit-card"></i><span>Pay by card</span></a>
                    <div class="shop-payment-separator"><span>or</span></div>
                    <div id="paypal-button-container" class="payment-paypal-container"><p class="shop-payment-status">Loading PayPal…</p></div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script type="application/x-legacy-shop-disabled">
        // Open shop modals explicitly so clicks do not depend on Bootstrap's
        // Data API being ready at exactly the right time.
        function showShopModal(modalId) {
            const modalEl = document.getElementById(modalId);
            if (!modalEl) return;

            if (window.bootstrap?.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }

            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.removeAttribute('aria-hidden');
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.setAttribute('role', 'dialog');
            document.body.classList.add('modal-open');

            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show shop-modal-backdrop';
            backdrop.addEventListener('click', () => hideShopModal(modalId));
            document.body.appendChild(backdrop);
        }

        function hideShopModal(modalId) {
            const modalEl = document.getElementById(modalId);
            if (!modalEl) return;

            if (window.bootstrap?.Modal) {
                const instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) {
                    instance.hide();
                    return;
                }
            }

            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.removeAttribute('aria-modal');
            modalEl.removeAttribute('role');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.shop-modal-backdrop').forEach(el => el.remove());
        }

        // Auto-hide toast after 5s
        const toast = document.getElementById('payment-toast');
        if (toast) {
            setTimeout(() => {
                toast.style.transition = 'opacity 0.5s ease';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }, 5000);
        }

        let currentPackageId = '';
        let currentPackagePrice = '';

        function preparePaymentModal(pid, name, price) {
            currentPackageId = pid;
            currentPackagePrice = price;

            document.getElementById('modal-pkg-name').textContent = name;
            document.getElementById('modal-pkg-price').textContent = '€' + parseFloat(price).toFixed(2);

            // Configure Stripe redirect href
            document.getElementById('stripe-checkout-btn').onclick = function() {
                window.location.href = '/api/create_shard_checkout_session.php?package_id=' + pid;
            };

            // Reset and render PayPal buttons
            const container = document.getElementById('paypal-button-container');
            container.innerHTML = '';
            
            if (!window.paypal?.Buttons) {
                container.innerHTML = '<p class="text-center text-secondary mb-0">PayPal is temporarily unavailable. You can still pay by card.</p>';
                return;
            }

            try {
                const renderResult = paypal.Buttons({
                createOrder: function(data, actions) {
                    return fetch('/api/create_paypal_shard_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            package_id: currentPackageId
                        })
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(orderData) {
                        if (orderData.ok && orderData.id) {
                            return orderData.id;
                        } else {
                            alert(orderData.message || 'Error creating PayPal order.');
                            return Promise.reject(new Error(orderData.message));
                        }
                    });
                },
                onApprove: function(data, actions) {
                    return fetch('/api/capture_paypal_shard_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            orderID: data.orderID,
                            package_id: currentPackageId
                        })
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(details) {
                        if (details.ok) {
                            hideShopModal('paymentModal');
                            window.location.href = '/en/shop.php?payment=success&package_id=' + currentPackageId;
                        } else {
                            alert(details.message || 'Error capturing payment.');
                        }
                    });
                },
                onError: function(err) {
                    console.error('[PayPal Error]', err);
                    alert('An error occurred with PayPal.');
                }
                }).render('#paypal-button-container');

                if (renderResult?.catch) {
                    renderResult.catch(() => {
                        container.innerHTML = '<p class="text-center text-secondary mb-0">PayPal is temporarily unavailable. You can still pay by card.</p>';
                    });
                }
            } catch (error) {
                container.innerHTML = '<p class="text-center text-secondary mb-0">PayPal is temporarily unavailable. You can still pay by card.</p>';
            }
        }

        // Gestione Tab
        document.querySelectorAll('.shop-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.shop-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.shop-tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                const tabId = btn.dataset.tab;
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Godos converter slider logic
        let userGodos = <?= (int)$soldi ?>;
        let godosSlider;
        let sliderShardsVal;
        let sliderGodosCost;
        let sliderMaxLabel;

        document.addEventListener('DOMContentLoaded', () => {
            if (window.bootstrap?.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
            }

            godosSlider = document.getElementById('godos-slider');
            sliderShardsVal = document.getElementById('slider-shards-val');
            sliderGodosCost = document.getElementById('slider-godos-cost');
            sliderMaxLabel = document.getElementById('slider-max-label');

            if (godosSlider) {
                godosSlider.addEventListener('input', updateSliderDisplay);
            }

            const btnConfirm = document.getElementById('btn-confirm-godos-buy');
            if (btnConfirm) {
                btnConfirm.addEventListener('click', handleGodosConversion);
            }

            document.querySelectorAll('.js-buy-shards').forEach(btn => {
                btn.addEventListener('click', () => {
                    showShopModal('paymentModal');
                    preparePaymentModal(btn.dataset.packageId, btn.dataset.packageName, btn.dataset.packagePrice);
                });
            });

            document.getElementById('open-godos-converter')?.addEventListener('click', () => {
                if (prepareGodosConverter()) {
                    showShopModal('godosConversionModal');
                }
            });

            document.querySelectorAll('.shop-modal [data-bs-dismiss="modal"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!window.bootstrap?.Modal) {
                        hideShopModal(btn.closest('.shop-modal')?.id);
                    }
                });
            });
        });

        function prepareGodosConverter() {
            const maxBuyable = Math.floor(userGodos / 100);
            if (maxBuyable <= 0) {
                alert("You do not have enough Godos to purchase Godo Shards! (Cost: 100 Godos per Shard)");
                return false;
            }
            if (godosSlider) {
                godosSlider.max = maxBuyable;
                godosSlider.value = Math.min(10, maxBuyable);
            }
            if (sliderMaxLabel) {
                sliderMaxLabel.textContent = "Max: " + maxBuyable;
            }
            updateSliderDisplay();
            return true;
        }

        function updateSliderDisplay() {
            if (!godosSlider || !sliderShardsVal || !sliderGodosCost) return;
            const qty = parseInt(godosSlider.value);
            sliderShardsVal.textContent = qty;
            sliderGodosCost.textContent = (qty * 100).toLocaleString();
        }

        async function handleGodosConversion() {
            if (!godosSlider) return;
            const qty = parseInt(godosSlider.value);
            if (qty <= 0) return;
            
            const btn = document.getElementById('btn-confirm-godos-buy');
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = "Processing...";
                }

                const res = await fetch('/api/convert_godos_to_shards.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shards: qty })
                });
                const data = await res.json();
                
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Confirm Purchase";
                }

                if (data.status === 'success') {
                    hideShopModal('godosConversionModal');
                    
                    // Update balances in DOM
                    userGodos = data.soldi_rimasti;
                    document.querySelectorAll('.shop-balance-val').forEach((el, index) => {
                        if (index === 0) el.textContent = data.soldi_rimasti.toLocaleString();
                        if (index === 1) el.textContent = data.shards_rimaste.toLocaleString();
                    });
                    
                    // Show success toast
                    showSuccessToast(qty, data.costo_punti);
                } else {
                    alert(data.message || "Error during conversion.");
                }
            } catch(e) {
                console.error(e);
                alert("Network error.");
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Confirm Purchase";
                }
            }
        }

        function showSuccessToast(shards, godos) {
            const toastDiv = document.createElement('div');
            toastDiv.className = 'shop-toast';
            toastDiv.style.position = 'fixed';
            toastDiv.style.top = '5rem';
            toastDiv.style.right = '2rem';
            toastDiv.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <span>Purchased ${shards} Godo Shards for ${godos} Godos!</span>
            `;
            document.body.appendChild(toastDiv);
            setTimeout(() => {
                toastDiv.style.transition = 'opacity 0.5s ease';
                toastDiv.style.opacity = '0';
                setTimeout(() => toastDiv.remove(), 500);
            }, 4000);
        }
    </script>

    <div class="shop-action-modal" id="godosConversionModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="Close"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="godosConversionTitle" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker">100 Godos = 1 Shard</span><h2 id="godosConversionTitle">Convert Godos</h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <form class="shop-conversion-form" id="godos-conversion-form" action="/api/convert_godos_to_shards.php" method="post">
                <input type="hidden" name="return_to" value="/en/shop.php">
                <div class="shop-conversion-amount"><img src="/img/godoshards.png" alt=""><strong id="slider-shards-val">10</strong><span>Godo Shards</span></div>
                <div class="shop-conversion-slider">
                    <input type="range" class="form-range" id="godos-slider" name="shards" min="1" max="100" value="10">
                    <div><span>Min: 1</span><span id="slider-max-label">Max: 100</span></div>
                </div>
                <div class="shop-conversion-total"><span>Total cost</span><strong><span id="slider-godos-cost">1,000</span> Godos</strong></div>
                <p class="shop-form-error" data-shop-form-error hidden></p>
                <div class="shop-action-modal__actions">
                    <a class="shop-action-secondary" href="#" data-shop-close>Cancel</a>
                    <button type="submit" class="shop-action-primary" id="btn-confirm-godos-buy">Confirm conversion</button>
                </div>
            </form>
        </section>
    </div>

    <!-- Godos Purchase Success Modal -->
    <div class="shop-action-modal" id="purchaseSuccessModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="Close"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="successModalTitle" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker" id="success-modal-title">Unlocked!</span><h2 id="successModalTitle">Purchase Completed</h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <div class="shop-action-modal__body text-center p-3">
                <div class="success-glow-ring">
                    <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: #fbbf24; filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.5));"></i>
                </div>
                
                <p class="text-secondary" id="success-modal-subtitle">You have successfully purchased this badge.</p>
                
                <!-- Card Reveal Container -->
                <div class="reveal-card-wrap my-3">
                    <div class="reveal-light-rays"></div>
                    <div class="reveal-badge-box" id="success-reveal-badge-box">
                        <!-- Badge image will display here -->
                    </div>
                    <div class="reveal-badge-name mt-2" id="success-reveal-badge-name" style="font-size: 1.3rem; font-weight: 800; color: #fff;"></div>
                    <div class="reveal-badge-desc text-secondary px-3 mt-1" id="success-reveal-badge-desc" style="font-size: 0.9rem;"></div>
                </div>
                
                <div class="shop-action-modal__actions" style="justify-content: center; margin-top: 1.5rem;">
                    <button type="button" class="card-btn" data-shop-close style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); max-width: 200px; color: #000; border: none;">
                        Awesome!
                    </button>
                </div>
            </div>
        </section>
    </div>

    <script src="/assets/shop/shards-shop.js?v=1.3" defer></script>
</body>

</html>
