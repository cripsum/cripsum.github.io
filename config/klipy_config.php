<?php
// KLIPY API config.
// Crea una API key su https://partner.klipy.com e impostala come variabile ambiente KLIPY_API_KEY.
// In alternativa, per hosting senza env vars, inseriscila nel fallback sotto.
if (!defined('KLIPY_API_KEY')) define('KLIPY_API_KEY', getenv('KLIPY_API_KEY') ?: 'vDVGwhR8HLXQgNIBnC2SJ86CqysGWytzjnP4phM5i2Fe7Bf1ANM3Nz2f6ofjFkE9');
if (!defined('KLIPY_LIMIT')) define('KLIPY_LIMIT', 24);
if (!defined('KLIPY_RATING')) define('KLIPY_RATING', 'pg-13');
if (!defined('KLIPY_LOCALE')) define('KLIPY_LOCALE', 'it_IT');
