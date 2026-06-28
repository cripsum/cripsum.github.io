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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Godo Shards Shop - Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/shop.css?v=1.1">
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

<body class="shop-shards-body">
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

    <div class="shop-container">
        <header class="shop-header">
            <h1 class="shop-title">💎 Godo Shards Shop 💎</h1>
            <p class="shop-subtitle">Buy premium Godo Shards to perform gacha pulls on the website.</p>

            <div class="shop-balance-bar">
                <div class="shop-balance-item" title="Free currency obtained by using the website." data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godos.png" alt="Godos" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godos:</span>
                    <span class="shop-balance-val"><?= number_format($soldi) ?></span>
                </div>
                <div class="shop-balance-item" title="Premium currency used to pull." data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godoshards.png" alt="Godo Shards" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godo Shards:</span>
                    <span class="shop-balance-val"><?= number_format($godoshards) ?></span>
                </div>
            </div>
            <div>
                <a href="lootbox" class="back-to-lootbox-btn">
                    <i class="fa-solid fa-arrow-left"></i> Back to Gacha
                </a>
            </div>
        </header>

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
                        <?php elseif ($savingsPercent > 0 && ($pid === 'shards_400' || $pid === 'shards_1200')): ?>
                            <span class="shop-badge badge-best">Best offer</span>
                        <?php elseif ($savingsPercent > 0): ?>
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
                            <small class="card-amount-bonus-note">+<?= $shards ?> Free Shards!</small>
                        <?php endif; ?>
                    </div>

                    <div class="card-equivalence">
                        <?= htmlspecialchars(formatEquivalence($displayShards)) ?>
                    </div>

                    <div class="card-price">
                        €<?= number_format($price, 2, '.', ',') ?>
                    </div>

                    <button class="card-btn" onclick="openPaymentModal('<?= $pid ?>', '<?= $pkg['name'] ?>', '<?= $price ?>')">
                        Buy
                    </button>
                </div>
            <?php endforeach; ?>
        </main>
    </div>

    <!-- Payment Choice Modal -->
    <div class="modal fade shop-modal" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Select Payment Method</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center text-secondary mb-4">You are purchasing <strong id="modal-pkg-name" class="text-white"></strong> for <strong id="modal-pkg-price" class="text-white"></strong>.</p>
                    
                    <div class="payment-options-grid">
                        <!-- Stripe Button -->
                        <button class="payment-stripe-btn" id="stripe-checkout-btn">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>Pay with Card (Stripe)</span>
                        </button>
                        
                        <div class="text-center my-2 text-secondary">- OR -</div>
                        
                        <!-- PayPal Button -->
                        <div id="paypal-button-container" class="payment-paypal-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script>
        // Init Bootstrap tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

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

        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

        function openPaymentModal(pid, name, price) {
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
            
            paypal.Buttons({
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
                            paymentModal.hide();
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

            paymentModal.show();
        }
    </script>
</body>

</html>
