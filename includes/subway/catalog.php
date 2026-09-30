<?php
/*
 * Catalogo delle mappe di Subway Surfers: e' l'unica fonte per la pagina
 * (card, filtri, modalita'), per subway.js (letto dal JSON stampato nella
 * pagina) e per la whitelist delle API (includes/subway_helpers.php).
 *
 * Le build stanno su Hostinger in /subway-builds/<slug>/, compresse in brotli
 * (vedi .htaccess di quella cartella). Per ogni mappa si usa
 * <slug>.alt.json; le varianti di allenamento sono <slug>.<modo>.json,
 * generate offline e verificate byte per byte.
 *
 * `legacy` e' la vecchia build su jsDelivr, usata solo se il server delle
 * build non risponde. `mb` e' il download compresso (dati + wasm + framework).
 */

const SUBWAY_BUILDS_BASE = '/subway-builds/';

// Modi di allenamento: chiave => suffisso dei file.
const SUBWAY_TRAINING_MODES = [
    'training' => 'training',
    'training3rows' => 'training3rows',
    'trainingobstacles' => 'trainingobstacles',
];

function subway_catalog(): array
{
    $legacy = static fn(string $pkg, string $build, string $loader = 'UnityLoader.2019.2.js', ?string $bootstrap = '4399.js') => [
        'pkg' => $pkg, 'build' => $build, 'loader' => $loader, 'bootstrap' => $bootstrap,
    ];
    $v2Loader = 'loaders/v2/unity/static/UnityLoader.2019.2.js';

    return [
        ['slug' => 'london', 'name' => 'London', 'region' => 'europe', 'mb' => 21, 'training' => true, 'hue' => 350,
            'legacy' => $legacy('subwaylondon@1.0.0', 'Build/London.json')],
        ['slug' => 'saintpetersburg', 'name' => 'Saint Petersburg', 'region' => 'europe', 'mb' => 21, 'training' => true, 'hue' => 205,
            'legacy' => $legacy('subwaystpetersburg@1.0.0', 'Build/StPetersburg.json', $v2Loader)],
        ['slug' => 'iceland', 'name' => 'Iceland', 'region' => 'europe', 'mb' => 21, 'training' => true, 'hue' => 190,
            'legacy' => $legacy('subwayiceland@1.0.0', 'Build/Iceland/Iceland_1.json')],
        ['slug' => 'barcelona', 'name' => 'Barcelona', 'region' => 'europe', 'mb' => 20, 'training' => true, 'hue' => 25],
        ['slug' => 'berlin', 'name' => 'Berlin', 'region' => 'europe', 'mb' => 22, 'training' => false, 'hue' => 45,
            'legacy' => $legacy('subwayberlin@1.0.0', 'Build/Berlin.json')],
        ['slug' => 'monaco', 'name' => 'Monaco', 'region' => 'europe', 'mb' => 21, 'training' => false, 'hue' => 330,
            'legacy' => $legacy('subwaymonaco@1.1.0', 'Build/Monaco.json')],
        ['slug' => 'moscow', 'name' => 'Moscow', 'region' => 'europe', 'mb' => 20, 'training' => false, 'hue' => 5],
        ['slug' => 'paris', 'name' => 'Paris', 'region' => 'europe', 'mb' => 20, 'training' => false, 'hue' => 225],
        ['slug' => 'venice', 'name' => 'Venice', 'region' => 'europe', 'mb' => 24, 'training' => false, 'hue' => 170],
        ['slug' => 'zurich', 'name' => 'Zurich', 'region' => 'europe', 'mb' => 22, 'training' => false, 'hue' => 0,
            'legacy' => $legacy('subwayzurich@1.1.3', 'Build/ZurichNewPrivacy.json')],

        ['slug' => 'neworleans', 'name' => 'New Orleans', 'region' => 'america', 'mb' => 21, 'training' => true, 'hue' => 275,
            'legacy' => $legacy('subwayneworleans@1.0.0', 'Build/NewOrleans.json')],
        ['slug' => 'havana', 'name' => 'Havana', 'region' => 'america', 'mb' => 21, 'training' => true, 'hue' => 15,
            'legacy' => $legacy('subwayhavana@2.0.0', 'Build/Havana_4.json', $v2Loader)],
        ['slug' => 'buenosaires', 'name' => 'Buenos Aires', 'region' => 'america', 'mb' => 21, 'training' => true, 'hue' => 200],
        ['slug' => 'mexico', 'name' => 'Mexico', 'region' => 'america', 'mb' => 21, 'training' => true, 'hue' => 140,
            'legacy' => $legacy('subwaymexico@1.0.0', 'Build/Mexico/Mexico3.json', 'UnityLoader.2019.2.js', 'subwaySurf14.08.js')],
        ['slug' => 'miami', 'name' => 'Miami', 'region' => 'america', 'mb' => 26, 'training' => true, 'hue' => 310,
            'legacy' => $legacy('subwaymiami@1.0.0', 'Build/Miami/subway_miami_v1.json', 'UnityLoader.2019.2.js', null)],
        ['slug' => 'houston', 'name' => 'Houston', 'region' => 'america', 'mb' => 21, 'training' => false, 'hue' => 30,
            'legacy' => $legacy('subwayhouston@1.1.0', 'Build/Houston/Houston.json', 'UnityLoader.2019.2.js', '4399.sf.js')],
        ['slug' => 'sanfrancisco', 'name' => 'San Francisco', 'region' => 'america', 'mb' => 21, 'training' => false, 'hue' => 12,
            'legacy' => $legacy('subwaysanfrancisco@1.0.0', 'Build/SanFrancisco.json')],
        ['slug' => 'newyork', 'name' => 'New York', 'region' => 'america', 'mb' => 22, 'training' => false, 'hue' => 215],
        ['slug' => 'rio', 'name' => 'Rio', 'region' => 'america', 'mb' => 24, 'training' => false, 'hue' => 95],

        ['slug' => 'beijing', 'name' => 'Beijing', 'region' => 'asia', 'mb' => 22, 'training' => true, 'hue' => 0,
            'legacy' => $legacy('subwaybeijing@1.0.0', 'Build/Beijing_2.json', $v2Loader)],
        ['slug' => 'hongkong', 'name' => 'Hong Kong', 'region' => 'asia', 'mb' => 24, 'training' => false, 'hue' => 320],
        ['slug' => 'tokyo', 'name' => 'Tokyo', 'region' => 'asia', 'mb' => 24, 'training' => false, 'hue' => 340],
        ['slug' => 'bangkok', 'name' => 'Bangkok', 'region' => 'asia', 'mb' => 50, 'training' => false, 'hue' => 50, 'beta' => true],

        ['slug' => 'cairo', 'name' => 'Cairo', 'region' => 'africa', 'mb' => 19, 'training' => false, 'hue' => 40],

        ['slug' => 'winterholiday', 'name' => 'Winter Holiday', 'region' => 'event', 'mb' => 22, 'training' => true, 'hue' => 185,
            'legacy' => $legacy('subwaywinterholiday@1.0.0', 'Build/WinterHoliday/WinterHoliday.json', 'UnityLoader.2019.2.js', 'subwaySurf14.08.js')],
    ];
}

function subway_catalog_slugs(): array
{
    return array_column(subway_catalog(), 'slug');
}
