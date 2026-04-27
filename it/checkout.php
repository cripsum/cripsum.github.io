<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - Checkout</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>


    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--checkout">
        <section class="form-card form-reveal">
            <div class="form-card__header">
                <span class="form-pill">Shop</span>
                <h1>Checkout</h1>
                <p>Completa i dati richiesti per proseguire.</p>
            </div>

            <div class="checkout-layout">
                <div class="form-panel">
                    <form id="checkoutForm" data-form-loading>
                        <div class="form-section">
                            <h2>Contatti</h2>

                            <div class="form-grid form-grid--2">
                                <label class="form-field">
                                    <span>Nome</span>
                                    <input type="text" id="firstName" autocomplete="given-name" required>
                                </label>

                                <label class="form-field">
                                    <span>Cognome</span>
                                    <input type="text" id="lastName" autocomplete="family-name" required>
                                </label>
                            </div>

                            <label class="form-field">
                                <span>Username</span>
                                <input type="text" id="username" placeholder="Username" required>
                            </label>

                            <label class="form-field">
                                <span>Email <small>(opzionale)</small></span>
                                <input type="email" id="email" placeholder="email@esempio.com" autocomplete="email">
                            </label>
                        </div>

                        <div class="form-section">
                            <h2>Indirizzo</h2>

                            <label class="form-field">
                                <span>Indirizzo</span>
                                <input type="text" id="address" placeholder="Via esempio, 123" autocomplete="street-address" required>
                            </label>

                            <label class="form-field">
                                <span>Indirizzo 2 <small>(opzionale)</small></span>
                                <input type="text" id="address2" placeholder="Appartamento, scala, interno">
                            </label>

                            <div class="form-grid form-grid--3">
                                <label class="form-field">
                                    <span>Stato</span>
                                    <input type="text" id="country" autocomplete="country-name" required>
                                </label>

                                <label class="form-field">
                                    <span>Regione</span>
                                    <input type="text" id="state" autocomplete="address-level1" required>
                                </label>

                                <label class="form-field">
                                    <span>CAP</span>
                                    <input type="text" id="zip" autocomplete="postal-code" required>
                                </label>
                            </div>

                            <label class="form-check">
                                <input type="checkbox" id="same-address">
                                <span>Indirizzo di spedizione uguale a quello di pagamento</span>
                            </label>

                            <label class="form-check">
                                <input type="checkbox" id="save-info">
                                <span>Ricorda le informazioni per i prossimi acquisti</span>
                            </label>
                        </div>

                        <div class="form-section">
                            <h2>Pagamento</h2>

                            <label class="form-radio">
                                <input id="credit" name="paymentMethod" type="radio" checked required>
                                <span>Carta di credito</span>
                            </label>

                            <label class="form-radio">
                                <input id="debit" name="paymentMethod" type="radio" required>
                                <span>Carta di debito</span>
                            </label>

                            <label class="form-radio">
                                <input id="paypal" name="paymentMethod" type="radio" required>
                                <span>PayPal</span>
                            </label>

                            <div class="form-grid form-grid--2">
                                <label class="form-field">
                                    <span>Nome sulla carta</span>
                                    <input type="text" id="cc-name" autocomplete="cc-name" required>
                                </label>

                                <label class="form-field">
                                    <span>Numero carta</span>
                                    <input type="text" id="cc-number" inputmode="numeric" autocomplete="cc-number" required>
                                </label>
                            </div>

                            <div class="form-grid form-grid--2">
                                <label class="form-field">
                                    <span>Scadenza</span>
                                    <input type="text" id="cc-expiration" placeholder="MM/AA" autocomplete="cc-exp" required>
                                </label>

                                <label class="form-field">
                                    <span>CVV</span>
                                    <input type="text" id="cc-cvv" inputmode="numeric" autocomplete="cc-csc" required>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="confirm" class="form-btn form-btn--primary form-btn--wide">
                                <i class="fas fa-lock"></i>
                                <span>Continua</span>
                            </a>
                        </div>
                    </form>
                </div>

                <aside class="form-panel checkout-summary">
                    <h2>Riepilogo</h2>
                    <div class="summary-line">
                        <span>Ordine</span>
                        <strong>Cripsum™</strong>
                    </div>
                    <div class="summary-line">
                        <span>Stato</span>
                        <strong>In attesa</strong>
                    </div>
                    <p class="form-muted" style="margin-top:1rem;">Controlla i dati prima di continuare.</p>
                </aside>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
