<?php
declare(strict_types=1);

/**
 * Prezzo, condizioni e vantaggi di Cripsum™ Premium, scritti in un posto solo.
 *
 * Li mostrano la homepage (includes/home_next.php) e il checkout
 * (includes/checkout_next.php) del tema nuovo: se cambia il prezzo o un
 * vantaggio, si cambia qui e le due pagine restano allineate. Il prezzo che
 * si paga davvero non dipende da questo file: lo decide il server quando apre
 * il pagamento (api/create_checkout_session.php, api/create_paypal_order.php).
 *
 * Ogni vantaggio e' una coppia [icona, testo]: 'coin' per i Godos, 'gem' per
 * il resto. Il testo contiene dei <strong> e va stampato cosi' com'e': e'
 * scritto qui sotto, non arriva da nessun utente.
 *
 * @return array{price:string, terms:string, lead:string, perks:list<array{0:string,1:string}>}
 */
function cripsum_premium_copy(string $lang): array
{
    if ($lang === 'en') {
        return [
            'price' => '€2.99',
            'terms' => 'One-time, no subscription',
            'lead'  => 'Get premium perks, double your rewards, and show off your support to the community.',
            'perks' => [
                ['coin', '<strong>25.000 Godos</strong> instantly upon purchase'],
                ['coin', 'Daily claim of <strong>500 Godos</strong> in Lootbox'],
                ['coin', '<strong>Double Godos (2x)</strong> on Daily &amp; Weekly missions'],
                ['gem', 'Unlock <strong>premium profile customization</strong>'],
                ['gem', '<strong>Cripsum Rewind any day</strong>, not just one week a year'],
                ['gem', 'Exclusive <strong>premium gem tag</strong> next to your name'],
                ['gem', '<strong>Featured</strong> in the homepage Supporters list'],
            ],
        ];
    }

    return [
        'price' => '€2,99',
        'terms' => 'Una tantum, nessun abbonamento',
        'lead'  => 'Ottieni vantaggi esclusivi, raddoppia i tuoi Godos e supporta la community.',
        'perks' => [
            ['coin', '<strong>25.000 Godos</strong> subito all\'acquisto'],
            ['coin', 'Riscatto giornaliero di <strong>500 Godos Lootbox</strong>'],
            ['coin', '<strong>Doppio boost (2x)</strong> sui Godos delle missioni'],
            ['gem', 'Sblocco della <strong>personalizzazione premium</strong> nei profili'],
            ['gem', '<strong>Cripsum Rewind quando vuoi</strong>, non solo una settimana l\'anno'],
            ['gem', '<strong>Tag premium</strong> con diamante vicino al tuo nome'],
            ['gem', '<strong>Nome in evidenza</strong> nella lista sostenitori'],
        ],
    ];
}
