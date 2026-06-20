<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/paypal_config.php';

checkBan($mysqli);
requireLogin();

$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Check if current user is already premium
$stmt = $mysqli->prepare("SELECT is_premium FROM utenti WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$currUser = $res->fetch_assoc();
$stmt->close();
$userIsPremium = $currUser && (int)($currUser['is_premium'] ?? 0) === 1;

$error = $_GET['error'] ?? '';
$errorMsg = '';
if ($error === 'user_not_found') {
    $errorMsg = 'The recipient user does not exist.';
} elseif ($error === 'already_premium') {
    $errorMsg = 'The recipient indicated already has a Premium account.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ Premium Subscription</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
    <style>
        .checkout-option-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .checkout-option-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .checkout-option-card.is-active {
            background: rgba(124, 58, 237, 0.08);
            border-color: #7c3aed;
            box-shadow: 0 0 15px rgba(124, 58, 237, 0.15);
        }

        .checkout-option-card input[type="radio"] {
            margin-top: 0.25rem;
            accent-color: #7c3aed;
        }

        .option-details {
            flex: 1;
        }

        .option-title {
            font-weight: 600;
            color: #fff;
            margin-bottom: 0.25rem;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .option-desc {
            font-size: 0.88rem;
            color: #a8b0c7;
            line-height: 1.4;
        }

        .gift-container {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: none;
        }

        .gift-container.is-visible {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .validation-status {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-loading {
            color: #a8b0c7;
        }

        .status-success {
            color: #10b981;
        }

        .status-error {
            color: #ef4444;
        }

        .payment-method-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .payment-method-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            color: #a8b0c7;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-method-btn:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .payment-method-btn.is-active {
            border-color: #7c3aed;
            background: rgba(124, 58, 237, 0.08);
            color: #fff;
        }

        .payment-method-btn i {
            font-size: 1.5rem;
        }

        .paypal-logo-color {
            color: #003087;
        }

        .payment-method-btn.is-active .paypal-logo-color {
            color: #0079c1;
        }

        #paypal-button-container {
            margin-top: 1.5rem;
            display: none;
        }

        #paypal-button-container.is-visible {
            display: block;
        }

        #stripe-submit-btn {
            display: block;
        }

        #stripe-submit-btn.is-hidden {
            display: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="form-page">
    <?php include '../includes/navbar.php'; ?>

    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>

    <main class="form-shell form-shell--checkout">
        <section class="form-card form-reveal">
            <div class="form-card__header">
                <h1>Cripsum™ Premium</h1>
                <p>Customize your profile with animated layouts, music, custom badges, and instantly get 200,000 gacha money to pull!</p>
            </div>

            <?php if ($errorMsg): ?>
                <div class="form-message form-message--error" style="display:flex; margin-bottom:1.5rem;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($errorMsg); ?></span>
                </div>
            <?php endif; ?>

            <div class="checkout-layout">
                <div class="form-panel">
                    <form id="premiumCheckoutForm" action="/api/create_checkout_session.php" method="POST">
                        <div class="form-section">
                            <h2>1. Activation recipient</h2>

                            <!-- Option 1: For myself -->
                            <div class="checkout-option-card is-active" id="optionSelfCard">
                                <input type="radio" name="purchase_type" value="self" id="purchaseSelf" checked>
                                <div class="option-details">
                                    <label for="purchaseSelf" class="option-title">
                                        <i class="fa-solid fa-user"></i> Activate on my account
                                    </label>
                                    <span class="option-desc">Upgrade your own profile (<strong><?php echo htmlspecialchars($username); ?></strong>) and instantly get all benefits and your money bonus.</span>
                                </div>
                            </div>

                            <!-- Option 2: Gift to a friend -->
                            <div class="checkout-option-card" id="optionGiftCard">
                                <input type="radio" name="purchase_type" value="gift" id="purchaseGift">
                                <div class="option-details">
                                    <label for="purchaseGift" class="option-title">
                                        <i class="fa-solid fa-gift"></i> Gift to a friend
                                    </label>
                                    <span class="option-desc">Send Premium activation and 200k money bonus to another member of the community.</span>
                                </div>
                            </div>

                            <!-- Input username gift -->
                            <div class="gift-container" id="giftInputContainer">
                                <label class="form-field">
                                    <span>Friend's Username</span>
                                    <input type="text" name="gift_to" id="giftUsernameInput" placeholder="Enter exact username">
                                </label>
                                <div class="validation-status" id="validationStatus" style="display:none;"></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h2>2. Choose payment method</h2>

                            <div class="payment-method-selector">
                                <div class="payment-method-btn is-active" id="payMethodStripe" data-method="stripe">
                                    <i class="fa-solid fa-credit-card"></i>
                                    <span>Credit Card (Stripe)</span>
                                </div>
                                <div class="payment-method-btn" id="payMethodPaypal" data-method="paypal">
                                    <i class="fa-brands fa-paypal paypal-logo-color"></i>
                                    <span>PayPal</span>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="stripe">

                        <div class="form-actions">
                            <!-- Stripe Button -->
                            <button type="submit" id="stripe-submit-btn" class="form-btn form-btn--primary form-btn--wide">
                                <i class="fa-solid fa-lock"></i>
                                <span>Complete with Stripe (€2.99)</span>
                            </button>

                            <!-- PayPal JS SDK Buttons -->
                            <div id="paypal-button-container"></div>
                        </div>
                    </form>
                </div>

                <aside class="form-panel checkout-summary">
                    <h2>Your Order</h2>
                    <div class="summary-line">
                        <span>Package</span>
                        <strong>Cripsum™ Premium</strong>
                    </div>
                    <div class="summary-line">
                        <span>Lootboxes Bonus</span>
                        <strong style="color: #eab308;">+200 pulls</strong>
                    </div>
                    <div class="summary-line">
                        <span>Recipient</span>
                        <strong id="summaryRecipient"><?php echo htmlspecialchars($username); ?> (You)</strong>
                    </div>
                    <div class="summary-line">
                        <span>Price</span>
                        <strong style="font-size: 1.2rem; color: #7c3aed;">€2.99 <span style="font-size:0.75rem; font-weight:normal; color:#a8b0c7;">one-time</span></strong>
                    </div>
                    <div class="summary-line">
                        <span>Method</span>
                        <strong id="summaryPaymentMethod">Stripe (Card)</strong>
                    </div>
                    <p class="form-muted" style="margin-top:1rem;">No recurring subscription. Pay once and keep Premium forever.</p>
                </aside>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>

    <!-- Load PayPal SDK JS -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode(PAYPAL_CLIENT_ID); ?>&currency=EUR&locale=en_US"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const optionSelfCard = document.getElementById('optionSelfCard');
            const optionGiftCard = document.getElementById('optionGiftCard');
            const purchaseSelf = document.getElementById('purchaseSelf');
            const purchaseGift = document.getElementById('purchaseGift');
            const giftInputContainer = document.getElementById('giftInputContainer');
            const giftUsernameInput = document.getElementById('giftUsernameInput');
            const validationStatus = document.getElementById('validationStatus');

            const payMethodStripe = document.getElementById('payMethodStripe');
            const payMethodPaypal = document.getElementById('payMethodPaypal');
            const selectedPaymentMethod = document.getElementById('selectedPaymentMethod');

            const stripeSubmitBtn = document.getElementById('stripe-submit-btn');
            const paypalButtonContainer = document.getElementById('paypal-button-container');

            const summaryRecipient = document.getElementById('summaryRecipient');
            const summaryPaymentMethod = document.getElementById('summaryPaymentMethod');
            const premiumCheckoutForm = document.getElementById('premiumCheckoutForm');

            let isRecipientValid = true;
            let debounceTimer;

            // If current user is already premium, select gift automatically
            const userIsPremium = <?php echo $userIsPremium ? 'true' : 'false'; ?>;
            if (userIsPremium) {
                optionSelfCard.style.opacity = '0.5';
                optionSelfCard.style.pointerEvents = 'none';
                purchaseGift.checked = true;
                togglePurchaseType('gift');
            }

            optionSelfCard.addEventListener('click', function() {
                if (userIsPremium) return;
                purchaseSelf.checked = true;
                togglePurchaseType('self');
            });

            optionGiftCard.addEventListener('click', function() {
                purchaseGift.checked = true;
                togglePurchaseType('gift');
            });

            function togglePurchaseType(type) {
                if (type === 'self') {
                    optionSelfCard.classList.add('is-active');
                    optionGiftCard.classList.remove('is-active');
                    giftInputContainer.classList.remove('is-visible');
                    giftUsernameInput.required = false;
                    summaryRecipient.textContent = <?php echo json_encode($username); ?> + ' (You)';
                    isRecipientValid = true;
                } else {
                    optionGiftCard.classList.add('is-active');
                    optionSelfCard.classList.remove('is-active');
                    giftInputContainer.classList.add('is-visible');
                    giftUsernameInput.required = true;
                    summaryRecipient.textContent = giftUsernameInput.value ? giftUsernameInput.value : 'Enter username...';
                    validateRecipient(giftUsernameInput.value);
                }
            }

            // Recipient username validation (with Debounce)
            giftUsernameInput.addEventListener('input', function() {
                const val = this.value.trim();
                summaryRecipient.textContent = val ? val : 'Enter username...';

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    validateRecipient(val);
                }, 500);
            });

            function validateRecipient(usernameVal) {
                if (!usernameVal) {
                    validationStatus.style.display = 'none';
                    isRecipientValid = false;
                    return;
                }

                validationStatus.style.display = 'flex';
                validationStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span class="status-loading">Verifying...</span>';

                fetch(`/api/check_recipient.php?username=${encodeURIComponent(usernameVal)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.ok) {
                            if (data.exists) {
                                if (data.is_premium) {
                                    validationStatus.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> <span class="status-error">User is already Premium!</span>`;
                                    isRecipientValid = false;
                                } else if (data.is_self) {
                                    validationStatus.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> <span class="status-error">You cannot gift Premium to yourself.</span>`;
                                    isRecipientValid = false;
                                } else {
                                    validationStatus.innerHTML = `<i class="fa-solid fa-circle-check"></i> <span class="status-success">Ready to receive gift!</span>`;
                                    isRecipientValid = true;
                                    summaryRecipient.textContent = `${data.username} (Gift)`;
                                }
                            } else {
                                validationStatus.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> <span class="status-error">User not found.</span>`;
                                isRecipientValid = false;
                            }
                        } else {
                            validationStatus.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> <span class="status-error">Validation error.</span>`;
                            isRecipientValid = false;
                        }
                    })
                    .catch(err => {
                        validationStatus.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> <span class="status-error">Network error.</span>`;
                        isRecipientValid = false;
                    });
            }

            // Payment Method Selectors
            payMethodStripe.addEventListener('click', function() {
                setActivePaymentMethod('stripe');
            });

            payMethodPaypal.addEventListener('click', function() {
                setActivePaymentMethod('paypal');
            });

            function setActivePaymentMethod(method) {
                if (method === 'stripe') {
                    payMethodStripe.classList.add('is-active');
                    payMethodPaypal.classList.remove('is-active');
                    selectedPaymentMethod.value = 'stripe';
                    summaryPaymentMethod.textContent = 'Stripe (Card)';

                    stripeSubmitBtn.classList.remove('is-hidden');
                    paypalButtonContainer.classList.remove('is-visible');
                } else {
                    payMethodPaypal.classList.add('is-active');
                    payMethodStripe.classList.remove('is-active');
                    selectedPaymentMethod.value = 'paypal';
                    summaryPaymentMethod.textContent = 'PayPal';

                    stripeSubmitBtn.classList.add('is-hidden');
                    paypalButtonContainer.classList.add('is-visible');
                }
            }

            premiumCheckoutForm.addEventListener('submit', function(e) {
                if (selectedPaymentMethod.value === 'paypal') {
                    e.preventDefault();
                    return false;
                }
                if (!isRecipientValid) {
                    e.preventDefault();
                    alert('Please enter a valid recipient username.');
                    return false;
                }
            });

            // Initialize PayPal SDK buttons
            paypal.Buttons({
                createOrder: function(data, actions) {
                    if (!isRecipientValid) {
                        alert('Please enter a valid recipient before checking out.');
                        return Promise.reject(new Error('Invalid recipient'));
                    }

                    return fetch('/api/create_paypal_order.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                is_gift: purchaseGift.checked,
                                recipient_username: purchaseGift.checked ? giftUsernameInput.value.trim() : ''
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
                    return fetch('/api/capture_paypal_order.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                orderID: data.orderID,
                                is_gift: purchaseGift.checked,
                                recipient_username: purchaseGift.checked ? giftUsernameInput.value.trim() : ''
                            })
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(details) {
                            if (details.ok) {
                                alert(details.message);
                                window.location.href = '/en/edit-profile?payment=success';
                            } else {
                                alert('Transaction error: ' + details.message);
                            }
                        })
                        .catch(function(err) {
                            console.error(err);
                            alert('Network error while capturing the transaction.');
                        });
                },
                onError: function(err) {
                    console.error('PayPal Error:', err);
                    alert('An error occurred with PayPal. If Client ID is placeholder, configure actual credentials in paypal_config.php.');
                }
            }).render('#paypal-button-container');
        });
    </script>
</body>

</html>