<?php
/**
 * Configurazione Stripe per Cripsum Profiles (Esempio)
 * Copia questo file in 'stripe_config.php' e inserisci le tue chiavi reali.
 * NON caricare 'stripe_config.php' su GitHub.
 */

// Chiavi API (Developers > API Keys)
define('STRIPE_SECRET_KEY', 'sk_test_placeholder_your_secret_key');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_placeholder_your_publishable_key');

// ID Prezzo del Prodotto (Products > Cripsum Premium)
define('STRIPE_PRICE_ID', 'price_placeholder_your_price_id');

// Webhook Signing Secret (Developers > Webhooks)
define('STRIPE_WEBHOOK_SECRET', 'whsec_placeholder_your_webhook_secret');

// URL di Ritorno (Successo / Annullamento)
define('STRIPE_SUCCESS_URL', 'https://cripsum.com/it/edit-profile?payment=success');
define('STRIPE_CANCEL_URL', 'https://cripsum.com/it/edit-profile?payment=cancel');
