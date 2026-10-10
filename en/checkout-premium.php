<?php
require_once '../config/session_init.php';
require_once '../includes/theme.php';
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
} elseif ($error === 'waiver') {
    $errorMsg = 'To continue, confirm that you want Premium right away and that you give up your right of withdrawal.';
}
$giftTo = isset($_GET['gift_to']) ? trim((string)$_GET['gift_to']) : '';
?>
<!DOCTYPE html>
<html lang="en"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include '../includes/head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <meta charset="UTF-8">
    <title>Cripsum™ Premium</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/assets/forms/forms.css') ?>">
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

        /* Rinuncia al recesso: i pagamenti partono solo con questa spunta. */
        .checkout-waiver {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            padding: 1rem 1.1rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            color: #cbd5e1;
            font-size: .92rem;
            line-height: 1.5;
            cursor: pointer;
            transition: border-color .2s ease;
        }

        .checkout-waiver input {
            flex: 0 0 auto;
            width: 1.1rem;
            height: 1.1rem;
            margin-top: .2rem;
            accent-color: #7c3aed;
        }

        .checkout-waiver a {
            color: #adc2ff;
        }

        .checkout-waiver.is-missing {
            border-color: rgba(248, 113, 113, .75);
        }

        .checkout-waiver-note {
            margin: .6rem 0 0;
            font-size: .82rem;
            color: #a8b0c7;
        }

        #stripe-submit-btn[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        #paypal-button-container.is-locked {
            opacity: .5;
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
    </div>

    <?php /* L'impaginazione sta in includes/checkout_next.php, la stessa per le
             due lingue. Gli id e le classi di stato sono quelli che usa lo
             script in fondo a questa pagina. */ ?>
    <?php $checkoutLang = 'en'; include '../includes/checkout_next.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

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

            // If current user is already premium or if there is a prefilled recipient, select gift automatically
            const userIsPremium = <?php echo $userIsPremium ? 'true' : 'false'; ?>;
            const prefilledGiftTo = <?php echo json_encode($giftTo); ?>;
            if (userIsPremium || prefilledGiftTo !== '') {
                if (userIsPremium) {
                    optionSelfCard.style.opacity = '0.5';
                    optionSelfCard.style.pointerEvents = 'none';
                }
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
                const summaryBadgeText = document.getElementById('summaryBadgeText');
                if (type === 'self') {
                    optionSelfCard.classList.add('is-active');
                    optionGiftCard.classList.remove('is-active');
                    giftInputContainer.classList.remove('is-visible');
                    giftUsernameInput.required = false;
                    summaryRecipient.textContent = <?php echo json_encode($username); ?> + ' (You)';
                    isRecipientValid = true;
                    if (summaryBadgeText) {
                        summaryBadgeText.innerHTML = '<img class="cr-premium-gem" src="/img/premium.svg" alt="" width="14" height="14"> Premium Badge';
                    }
                } else {
                    optionGiftCard.classList.add('is-active');
                    optionSelfCard.classList.remove('is-active');
                    giftInputContainer.classList.add('is-visible');
                    giftUsernameInput.required = true;
                    summaryRecipient.textContent = giftUsernameInput.value ? giftUsernameInput.value : 'Enter username...';
                    validateRecipient(giftUsernameInput.value);
                    if (summaryBadgeText) {
                        summaryBadgeText.innerHTML = '<i class="fa-solid fa-gift"></i> Premium Gifter';
                    }
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

            // Spunta di rinuncia al recesso: finche' manca, Stripe e PayPal
            // restano spenti (e il server rifiuta comunque l'ordine).
            const waiverCheck = document.getElementById('waiverCheck');
            const waiverBox = document.getElementById('waiverBox');
            let paypalActions = null;

            function syncWaiver() {
                const ok = waiverCheck.checked;
                stripeSubmitBtn.disabled = !ok;
                paypalButtonContainer.classList.toggle('is-locked', !ok);
                if (ok) waiverBox.classList.remove('is-missing');
                if (paypalActions) {
                    if (ok) {
                        paypalActions.enable();
                    } else {
                        paypalActions.disable();
                    }
                }
            }

            function flagWaiver() {
                waiverBox.classList.add('is-missing');
                waiverBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            waiverCheck.addEventListener('change', syncWaiver);
            syncWaiver();

            premiumCheckoutForm.addEventListener('submit', function(e) {
                if (selectedPaymentMethod.value === 'paypal') {
                    e.preventDefault();
                    return false;
                }
                if (!waiverCheck.checked) {
                    e.preventDefault();
                    flagWaiver();
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
                onInit: function(data, actions) {
                    paypalActions = actions;
                    syncWaiver();
                },
                onClick: function() {
                    if (!waiverCheck.checked) flagWaiver();
                },
                createOrder: function(data, actions) {
                    if (!waiverCheck.checked) {
                        flagWaiver();
                        return Promise.reject(new Error('waiver'));
                    }

                    if (!isRecipientValid) {
                        alert('Please enter a valid recipient before checking out.');
                        return Promise.reject(new Error('Invalid recipient'));
                    }

                    return fetch('/api/create_paypal_order.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            body: JSON.stringify({
                                is_gift: purchaseGift.checked,
                                waiver: waiverCheck.checked,
                                recipient_username: purchaseGift.checked ? giftUsernameInput.value.trim() : '',
                                csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
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
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            body: JSON.stringify({
                                orderID: data.orderID,
                                is_gift: purchaseGift.checked,
                                recipient_username: purchaseGift.checked ? giftUsernameInput.value.trim() : '',
                                csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
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
                    alert('An error occurred with PayPal. Check the secure server configuration.');
                }
            }).render('#paypal-button-container');
        });
    </script>
</body>

</html>
