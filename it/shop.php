<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/paypal_config.php';

checkBan($mysqli);
requireLogin();

$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Carica bilanci utente
$stmtUser = $mysqli->prepare("SELECT soldi, godoshards_balance FROM utenti WHERE id = ? LIMIT 1");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resUser = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$soldi = (int)($resUser['soldi'] ?? 0);
$godoshards = (int)($resUser['godoshards_balance'] ?? 0);

// Carica bonus primo acquisto consumati
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
            return "{$shards} Pull ({$multi} Multi + {$single} Pull)";
        } else {
            return "{$shards} Pull ({$multi} Multi)";
        }
    } else {
        return "{$shards} Pull";
    }
}

$paymentStatus = $_GET['payment'] ?? '';
$successPackage = $_GET['package_id'] ?? '';
$conversionStatus = $_GET['conversion'] ?? '';
$convertedShards = max(0, (int)($_GET['shards'] ?? 0));
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Shop Godo Shards - Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/shop.css?v=2.1">
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode(PAYPAL_CLIENT_ID); ?>&currency=EUR&locale=it_IT"></script>
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

<body class="shop-shards-body" data-shards-shop data-lang="it" data-user-godos="<?= $soldi ?>" data-user-shards="<?= $godoshards ?>">
    <?php include '../includes/navbar.php'; ?>

    <?php if ($paymentStatus === 'success'): ?>
        <div class="shop-toast" id="payment-toast">
            <i class="fa-solid fa-circle-check"></i>
            <span>Pagamento completato con successo! Le tue Shards sono state accreditate.</span>
        </div>
    <?php elseif ($paymentStatus === 'cancel'): ?>
        <div class="shop-toast is-error" id="payment-toast">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Pagamento annullato dall'utente.</span>
        </div>
    <?php endif; ?>

    <?php if ($conversionStatus === 'success'): ?>
        <div class="shop-toast" id="conversion-toast">
            <i class="fa-solid fa-circle-check"></i>
            <span>Conversione completata<?= $convertedShards > 0 ? ': +' . number_format($convertedShards) . ' Godo Shards' : '' ?>.</span>
        </div>
    <?php elseif ($conversionStatus === 'error'): ?>
        <div class="shop-toast is-error" id="conversion-toast">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Conversione non riuscita. Controlla il saldo e riprova.</span>
        </div>
    <?php endif; ?>

    <div class="shop-container">
        <header class="shop-header">
            <h1 class="shop-title"><img src="/img/godoshards.png" alt="Logo" class="shop-title-logo"> Shop Godo Shards <img src="/img/godoshards.png" alt="Logo" class="shop-title-logo"></h1>

            <div class="shop-balance-bar">
                <div class="shop-balance-item" title="Valuta gratuita ottenibile usando il sito." data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godos.png" alt="Godos" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godos:</span>
                    <span class="shop-balance-val" data-shop-balance="godos"><?= number_format($soldi) ?></span>
                </div>
                <div class="shop-balance-item" title="Valuta premium usata per pullare." data-bs-toggle="tooltip">
                    <span class="shop-balance-icon"><img src="/img/godoshards.png" alt="Godo Shards" class="currency-icon-img"></span>
                    <span class="shop-balance-label">Godo Shards:</span>
                    <span class="shop-balance-val" data-shop-balance="shards"><?= number_format($godoshards) ?></span>
                </div>
            </div>
            <div>
                <a href="lootbox" class="back-to-lootbox-btn">
                    <i class="fa-solid fa-arrow-left"></i> Torna al Gacha
                </a>
            </div>
        </header>        <!-- Tabs Nav -->
        <div class="shop-tabs">
            <button type="button" class="shop-tab-btn active" data-tab="tab-premium"><img src="/img/godoshards.png" alt="Shards" class="tab-icon-img"> Acquista Shards</button>
            <button type="button" class="shop-tab-btn" data-tab="tab-godos"><img src="/img/godos.png" alt="Godos" class="tab-icon-img"> Negozio Godos</button>
        </div>

        <div id="tab-premium" class="shop-tab-content active">
            <main class="shop-grid">
                <?php foreach ($packages as $pid => $pkg):
                    $price = $pkg['price'];
                    $shards = $pkg['shards'];
                    $isBonusAvailable = !in_array($pid, $usedBonuses);
                    
                    // Calcola valore risparmio rispetto a €0,99 (10 shards)
                    $currentRate = $shards / $price;
                    $savingsPercent = round(($currentRate / $baseRate - 1) * 100);
                    
                    // Visual Shards amount (doubled if x2 active)
                    $displayShards = $isBonusAvailable ? ($shards * 2) : $shards;
                    
                    // Classi speciali per card evidenziate
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
                                <span class="shop-badge badge-x2" title="Il primo acquisto di questo pacchetto raddoppia le shards!">x2 Bonus</span>
                            <?php endif; ?>

                            <?php if ($pid === 'shards_80'): ?>
                                <span class="shop-badge badge-pity">⭐ Pity completo</span>
                            <?php elseif ($pid === 'shards_400' || $pid === 'shards_1200'): ?>
                                <span class="shop-badge badge-best">Miglior offerta</span>
                            <?php endif; ?>

                            <?php if ($savingsPercent > 0): ?>
                                <span class="shop-badge badge-value">+<?= $savingsPercent ?>% valore</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-shards-icon"><img src="/img/godoshards.png" alt="Godo Shards" style="width: 60px; height: 60px; object-fit: contain;"></div>

                        <div class="card-amount-wrap">
                            <?php if ($isBonusAvailable): ?>
                                <span class="card-amount-original"><?= $shards ?></span>
                            <?php endif; ?>
                            <span class="card-amount"><?= $displayShards ?></span>
                            <?php if ($isBonusAvailable): ?>
                                <small class="card-amount-bonus-note">+<?= $shards ?> Shards Gratis!</small>
                            <?php endif; ?>
                        </div>

                        <div class="card-equivalence">
                            <?= htmlspecialchars(formatEquivalence($displayShards)) ?>
                        </div>

                        <div class="card-price">
                            €<?= number_format($price, 2, ',', '.') ?>
                        </div>

                        <a class="card-btn js-buy-shards" data-shop-buy
                            href="/api/create_shard_checkout_session.php?package_id=<?= rawurlencode($pid) ?>"
                            data-package-id="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>"
                            data-package-name="<?= htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-package-price="<?= htmlspecialchars((string)$price, ENT_QUOTES, 'UTF-8') ?>">
                            Acquista
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
                        <span class="shop-badge badge-value">100 Punti = 1 Shard</span>
                    </div>

                    <div class="card-shards-icon">
                        <img src="/img/godoshards.png" alt="Godo Shards" style="width: 60px; height: 60px; object-fit: contain;">
                    </div>

                    <div class="card-amount-wrap">
                        <span class="card-amount">Godo Shards</span>
                        <small class="card-amount-bonus-note" style="display: block; min-height: 28px;">Converti Godos in Shards</small>
                    </div>

                    <div class="card-equivalence">
                        Gacha Pulls
                    </div>

                    <div class="card-price" style="font-size: 1.15rem; color: #a855f7;">
                        Costo: 100 Godos / cad
                    </div>

                    <a class="card-btn" id="open-godos-converter" data-shop-convert href="#godosConversionModal" style="background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); border: none;">
                        Converti Punti
                    </a>
                </div>
            </main>
        </div>
    </div>

    <!-- Controller autonomo: non dipende dai modal Bootstrap -->
    <div class="shop-action-modal" id="paymentModal" aria-hidden="true">
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="Chiudi"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="paymentModalLabel" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker">Checkout sicuro</span><h2 id="paymentModalLabel">Scegli come pagare</h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="Chiudi"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <div class="shop-action-modal__body">
                <p class="shop-action-modal__summary">Stai acquistando <strong id="modal-pkg-name"></strong> per <strong id="modal-pkg-price"></strong>.</p>
                <div class="payment-options-grid">
                    <a class="payment-stripe-btn" id="stripe-checkout-btn" href="/api/create_shard_checkout_session.php"><i class="fa-solid fa-credit-card"></i><span>Paga con carta</span></a>
                    <div class="shop-payment-separator"><span>oppure</span></div>
                    <div id="paypal-button-container" class="payment-paypal-container"><p class="shop-payment-status">Caricamento PayPal…</p></div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script type="application/x-legacy-shop-disabled">
        // I modal dello shop vengono aperti esplicitamente: in questo modo il click
        // non dipende dal Data API di Bootstrap e non viene perso se il bundle CDN
        // arriva in ritardo o viene caricato nuovamente dalla navbar.
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

        // Auto nascondi toast dopo 5s
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
        let paypalButtonsInstance = null;

        function preparePaymentModal(pid, name, price) {
            currentPackageId = pid;
            currentPackagePrice = price;

            document.getElementById('modal-pkg-name').textContent = name;
            document.getElementById('modal-pkg-price').textContent = '€' + parseFloat(price).toFixed(2).replace('.', ',');

            // Configura Stripe redirect href
            document.getElementById('stripe-checkout-btn').onclick = function() {
                window.location.href = '/api/create_shard_checkout_session.php?package_id=' + pid;
            };

            // Reset e render dei bottoni PayPal
            const container = document.getElementById('paypal-button-container');
            container.innerHTML = ''; // svuota se c'erano bottoni precedenti
            
            if (!window.paypal?.Buttons) {
                container.innerHTML = '<p class="text-center text-secondary mb-0">PayPal non è disponibile al momento. Puoi comunque pagare con carta.</p>';
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
                            alert(orderData.message || 'Errore nella creazione dell\'ordine PayPal.');
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
                            // Ricarica la pagina con parametro di successo
                            window.location.href = '/it/shop.php?payment=success&package_id=' + currentPackageId;
                        } else {
                            alert(details.message || 'Errore durante la cattura del pagamento.');
                        }
                    });
                },
                onError: function(err) {
                    console.error('[PayPal Error]', err);
                    alert('Si è verificato un errore con PayPal.');
                }
                }).render('#paypal-button-container');

                if (renderResult?.catch) {
                    renderResult.catch(() => {
                        container.innerHTML = '<p class="text-center text-secondary mb-0">PayPal non è disponibile al momento. Puoi comunque pagare con carta.</p>';
                    });
                }
            } catch (error) {
                container.innerHTML = '<p class="text-center text-secondary mb-0">PayPal non è disponibile al momento. Puoi comunque pagare con carta.</p>';
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
                alert("Non hai abbastanza Godos per acquistare Godo Shards! (Costo: 100 Godos per Shard)");
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
                    btn.textContent = "Elaborazione...";
                }

                const res = await fetch('/api/convert_godos_to_shards.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shards: qty })
                });
                const data = await res.json();
                
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Conferma Acquisto";
                }

                if (data.status === 'success') {
                    hideShopModal('godosConversionModal');
                    
                    // Aggiorna balances visivamente nel DOM
                    userGodos = data.soldi_rimasti;
                    document.querySelectorAll('.shop-balance-val').forEach((el, index) => {
                        if (index === 0) el.textContent = data.soldi_rimasti.toLocaleString();
                        if (index === 1) el.textContent = data.shards_rimaste.toLocaleString();
                    });
                    
                    // Mostra un toast di successo
                    showSuccessToast(qty, data.costo_punti);
                } else {
                    alert(data.message || "Errore nella conversione.");
                }
            } catch(e) {
                console.error(e);
                alert("Errore di rete.");
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Conferma Acquisto";
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
                <span>Acquistate ${shards} Godo Shards per ${godos} Godos!</span>
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
        <a class="shop-action-modal__backdrop" href="#" data-shop-close aria-label="Chiudi"></a>
        <section class="shop-action-modal__panel" role="dialog" aria-modal="true" aria-labelledby="godosConversionTitle" tabindex="-1">
            <header class="shop-action-modal__header">
                <div><span class="shop-action-modal__kicker">100 Godos = 1 Shard</span><h2 id="godosConversionTitle">Converti Godos</h2></div>
                <a class="shop-action-modal__close" href="#" data-shop-close aria-label="Chiudi"><i class="fa-solid fa-xmark"></i></a>
            </header>
            <form class="shop-conversion-form" id="godos-conversion-form" action="/api/convert_godos_to_shards.php" method="post">
                <input type="hidden" name="return_to" value="/it/shop.php">
                <div class="shop-conversion-amount"><img src="/img/godoshards.png" alt=""><strong id="slider-shards-val">10</strong><span>Godo Shards</span></div>
                <div class="shop-conversion-slider">
                    <input type="range" class="form-range" id="godos-slider" name="shards" min="1" max="100" value="10">
                    <div><span>Min: 1</span><span id="slider-max-label">Max: 100</span></div>
                </div>
                <div class="shop-conversion-total"><span>Costo totale</span><strong><span id="slider-godos-cost">1.000</span> Godos</strong></div>
                <p class="shop-form-error" data-shop-form-error hidden></p>
                <div class="shop-action-modal__actions">
                    <a class="shop-action-secondary" href="#" data-shop-close>Annulla</a>
                    <button type="submit" class="shop-action-primary" id="btn-confirm-godos-buy">Conferma conversione</button>
                </div>
            </form>
        </section>
    </div>
    <script src="/assets/shop/shards-shop.js?v=1.1" defer></script>
</body>

</html>
