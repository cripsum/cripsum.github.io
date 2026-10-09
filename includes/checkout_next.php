<?php

/**
 * Checkout Premium: il <main> della pagina.
 *
 * Incluso da it/checkout-premium.php e en/checkout-premium.php, che impostano
 * $checkoutLang ('it' o 'en') e hanno gia' preparato $username, $errorMsg e
 * $giftTo.
 *
 * Qui c'e' solo l'impaginazione: a sinistra cosa si compra, a destra un solo
 * riquadro con l'ordine. Il pagamento lo fa lo script in fondo alle due
 * pagine, che e' nato prima di questa impaginazione e non e' cambiato. Quello
 * script trova gli elementi per id e accende e spegne delle classi: gli id
 * (optionSelfCard, giftUsernameInput, payMethodStripe, waiverCheck,
 * stripe-submit-btn, paypal-button-container, summaryRecipient...) e le
 * classi di stato (.checkout-option-card, .gift-container,
 * .payment-method-btn, .checkout-waiver) devono restare quelli.
 *
 * Lo stile sta in assets/theme-next/pages/forms.css.
 */

require_once __DIR__ . '/premium_copy.php';

$ckIsEn = ($checkoutLang ?? 'it') === 'en';
$ckPremium = cripsum_premium_copy($ckIsEn ? 'en' : 'it');

$ck = $ckIsEn
    ? [
        'order'        => 'Your order',
        'included'     => 'What is included',
        'who'          => 'Who is it for?',
        'self_title'   => 'Activate on my account',
        'self_desc'    => 'Upgrade your own profile (<strong>%s</strong>) and instantly get all benefits and your Godos bonus.',
        'gift_title'   => 'Gift to a friend',
        'gift_desc'    => 'Send Premium to another user. They will receive a special email and a gifter badge on their profile!',
        'gift_label'   => 'Friend\'s Username',
        'gift_ph'      => 'Enter exact username',
        'pay'          => 'How do you want to pay?',
        'pay_card'     => 'Credit Card (Stripe)',
        'recipient'    => 'Recipient',
        'you'          => '(You)',
        'badge'        => 'Badge Included',
        'badge_value'  => 'Premium Badge',
        'method'       => 'Method',
        'method_value' => 'Stripe (Card)',
        'total'        => 'Total',
        'once'         => 'one-time',
        'waiver'       => 'I want to receive Cripsum™ Premium right away and I acknowledge that, because delivery starts immediately, I lose my 14-day right of withdrawal. I have read the <a href="tos" target="_blank" rel="noopener">Terms of Service</a>.',
        'minors'       => 'If you are under 18, ask a parent for permission before buying. You will get a confirmation email after paying.',
        'submit'       => 'Complete with Stripe (%s)',
        'no_sub'       => 'No recurring subscription: pay once and Premium stays on your account for as long as your account and the site exist.',
    ]
    : [
        'order'        => 'Il tuo ordine',
        'included'     => 'Cosa è incluso',
        'who'          => 'Per chi è?',
        'self_title'   => 'Attiva sul mio account',
        'self_desc'    => 'Aggiorna il tuo profilo (<strong>%s</strong>) ed ottieni subito tutti i vantaggi e il bonus di Godos.',
        'gift_title'   => 'Regala ad un amico',
        'gift_desc'    => 'Invia l\'attivazione Premium e i 25.000 Godos a un altro utente della community.',
        'gift_label'   => 'Username dell\'amico',
        'gift_ph'      => 'Inserisci l\'username esatto',
        'pay'          => 'Come vuoi pagare?',
        'pay_card'     => 'Carta di Credito (Stripe)',
        'recipient'    => 'Destinatario',
        'you'          => '(Tu)',
        'badge'        => 'Badge Incluso',
        'badge_value'  => 'Badge Premium',
        'method'       => 'Metodo',
        'method_value' => 'Stripe (Carta)',
        'total'        => 'Totale',
        'once'         => 'una tantum',
        'waiver'       => 'Voglio ricevere subito Cripsum™ Premium e prendo atto che, iniziando subito la fornitura, perdo il diritto di recesso di 14 giorni (art. 59, lett. o, Codice del Consumo). Ho letto i <a href="tos" target="_blank" rel="noopener">Termini di servizio</a>.',
        'minors'       => 'Se sei minorenne, chiedi il permesso a un genitore prima di comprare. Dopo il pagamento ti arriva una email di conferma.',
        'submit'       => 'Completa con Stripe (%s)',
        'no_sub'       => 'Nessun abbonamento ricorrente: paghi una volta e il Premium resta sul tuo account finché l\'account e il sito esistono.',
    ];

$ckH = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$ckUser = $ckH($username ?? '');

$ckPerkIcon = [
    'coin' => '<img src="/img/godos-icon.png" alt="" width="22" height="22">',
    'gem' => '<img src="/img/premium.svg" alt="" width="22" height="22">',
];
?>
<main class="form-shell form-shell--checkout ckx">
    <header class="ckx-head form-reveal">
        <img class="ckx-gem" src="/img/premium.svg" alt="" width="48" height="48">
        <h1>Cripsum™ Premium</h1>
        <p class="ckx-lead"><?= $ckH($ckPremium['lead']) ?></p>
        <p class="ckx-price"><strong><?= $ckH($ckPremium['price']) ?></strong><span><?= $ckH($ckPremium['terms']) ?></span></p>
    </header>

    <section class="ckx-panel form-reveal" aria-label="<?= $ckH($ck['order']) ?>">
        <?php if (!empty($errorMsg)): ?>
            <div class="form-message form-message--error" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <span><?= $ckH($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <form id="premiumCheckoutForm" action="/api/create_checkout_session.php" method="POST">
            <?= csrf_field() ?>

            <div class="ckx-group">
                <h2><?= $ckH($ck['who']) ?></h2>

                <div class="checkout-option-card is-active" id="optionSelfCard">
                    <input type="radio" name="purchase_type" value="self" id="purchaseSelf" checked>
                    <div class="option-details">
                        <label for="purchaseSelf" class="option-title">
                            <i class="fa-solid fa-user" aria-hidden="true"></i> <?= $ckH($ck['self_title']) ?>
                        </label>
                        <span class="option-desc"><?= sprintf($ck['self_desc'], $ckUser) ?></span>
                    </div>
                </div>

                <div class="checkout-option-card" id="optionGiftCard">
                    <input type="radio" name="purchase_type" value="gift" id="purchaseGift">
                    <div class="option-details">
                        <label for="purchaseGift" class="option-title">
                            <i class="fa-solid fa-gift" aria-hidden="true"></i> <?= $ckH($ck['gift_title']) ?>
                        </label>
                        <span class="option-desc"><?= $ckH($ck['gift_desc']) ?></span>
                    </div>
                </div>

                <?php /* Compare solo quando si sceglie il regalo: lo mostra lo script. */ ?>
                <div class="gift-container" id="giftInputContainer">
                    <label class="form-field">
                        <span><?= $ckH($ck['gift_label']) ?></span>
                        <input type="text" name="gift_to" id="giftUsernameInput" placeholder="<?= $ckH($ck['gift_ph']) ?>" value="<?= $ckH($giftTo ?? '') ?>">
                    </label>
                    <div class="validation-status" id="validationStatus" style="display:none;"></div>
                </div>
            </div>

            <div class="ckx-group">
                <h2><?= $ckH($ck['pay']) ?></h2>

                <div class="payment-method-selector">
                    <div class="payment-method-btn is-active" id="payMethodStripe" data-method="stripe">
                        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                        <span><?= $ckH($ck['pay_card']) ?></span>
                    </div>
                    <div class="payment-method-btn" id="payMethodPaypal" data-method="paypal">
                        <i class="fa-brands fa-paypal paypal-logo-color" aria-hidden="true"></i>
                        <span>PayPal</span>
                    </div>
                </div>
            </div>

            <div class="ckx-group">
                <?php /* Le tre voci le aggiorna lo script a ogni scelta. */ ?>
                <dl class="ckx-summary">
                    <div>
                        <dt><?= $ckH($ck['recipient']) ?></dt>
                        <dd id="summaryRecipient"><?= $ckUser ?> <?= $ckH($ck['you']) ?></dd>
                    </div>
                    <div id="summaryBadgeLine">
                        <dt><?= $ckH($ck['badge']) ?></dt>
                        <dd id="summaryBadgeText"><img class="cr-premium-gem" src="/img/premium.svg" alt="" width="14" height="14"> <?= $ckH($ck['badge_value']) ?></dd>
                    </div>
                    <div>
                        <dt><?= $ckH($ck['method']) ?></dt>
                        <dd id="summaryPaymentMethod"><?= $ckH($ck['method_value']) ?></dd>
                    </div>
                    <div class="ckx-total">
                        <dt><?= $ckH($ck['total']) ?></dt>
                        <dd><?= $ckH($ckPremium['price']) ?><small><?= $ckH($ck['once']) ?></small></dd>
                    </div>
                </dl>

                <label class="checkout-waiver" id="waiverBox">
                    <input type="checkbox" name="waiver" value="1" id="waiverCheck">
                    <span><?= $ck['waiver'] ?></span>
                </label>

                <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="stripe">

                <div class="form-actions">
                    <button type="submit" id="stripe-submit-btn" class="form-btn form-btn--primary form-btn--wide">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span><?= $ckH(sprintf($ck['submit'], $ckPremium['price'])) ?></span>
                    </button>

                    <div id="paypal-button-container"></div>
                </div>

                <p class="checkout-waiver-note"><?= $ckH($ck['minors']) ?></p>
            </div>
        </form>
    </section>

    <section class="ckx-perks form-reveal" aria-label="<?= $ckH($ck['included']) ?>">
        <ul>
            <?php foreach ($ckPremium['perks'] as $ckPerk): ?>
                <li><?= $ckPerkIcon[$ckPerk[0]] ?><span><?= $ckPerk[1] ?></span></li>
            <?php endforeach; ?>
        </ul>
        <p class="ckx-note"><?= $ckH($ck['no_sub']) ?></p>
    </section>
</main>
