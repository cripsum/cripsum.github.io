<?php
/*
 * Catalogo delle mappe di Subway Surfers: e' l'unica fonte per la pagina
 * (card, filtri, modalita'), per subway.js (letto dal JSON stampato nella
 * pagina) e per la whitelist delle API (includes/subway_helpers.php).
 *
 * Le build stanno su Hostinger in /subway-builds-br/<slug>/, compresse in brotli
 * (vedi .htaccess di quella cartella). Per ogni mappa si usa
 * <slug>.alt.json; le varianti di allenamento sono <slug>.<modo>.json,
 * generate offline e verificate byte per byte.
 *
 * `legacy` e' la vecchia build su jsDelivr, usata solo se il server delle
 * build non risponde. `mb` e' il download compresso (dati + wasm + framework).
 */

/*
 * Build nuove (quelle su Hostinger) accese o spente. Sono le versioni
 * distribuite da Poki e il loro codice contiene il blocco di dominio di Poki
 * (se il sito non e' autorizzato apre poki.com/sitelock): cosi' come sono non
 * possono girare su cripsum.com, e quel blocco non va tolto. Spente, la pagina
 * usa solo le vecchie build di jsDelivr (14 mappe, niente allenamento), che il
 * sito usava fino al 30/09/2026.
 */
const SUBWAY_NEW_BUILDS = false;

// Cartella public_html/subway-builds-br su Hostinger (stesso nome della
// cartella locale da cui si caricano i file).
const SUBWAY_BUILDS_BASE = '/subway-builds-br/';

/*
 * Il framework (il JavaScript del motore) deve essere quello compilato insieme
 * al wasm della mappa: con quello "condiviso" dei .json il wasm di quasi tutte
 * le mappe non si collegava (LinkError: tabella 125739 invece di 126009, e
 * funzioni mancanti). Abbinamenti trovati confrontando gli hash dei wasm con
 * quelli dei pacchetti jsDelivr, che hanno ciascuno il proprio framework.
 * Percorso relativo alla base delle build, oppure URL completo.
 */
const SUBWAY_FW_SHARED1 = 'cairo/cairo.alt.wasm.framework.unityweb';   // wasm_code_shared (= Beijing_2)
const SUBWAY_FW_SHARED2 = 'https://cdn.jsdelivr.net/npm/subwaymexico@1.0.0/Build/Mexico/Mexico3.wasm.framework.unityweb';   // wasm_code_shared_2 e Winter Holiday (= Mexico3)

// Modi di allenamento: chiave => suffisso dei file.
const SUBWAY_TRAINING_MODES = [
    'training' => 'training',
    'training3rows' => 'training3rows',
    'trainingobstacles' => 'trainingobstacles',
];

function subway_catalog(): array
{
    $legacy = static fn(string $pkg, string $build, string $loader = 'UnityLoader.2019.2.js', ?string $bootstrap = '4399.js', int $mb = 53) => [
        'pkg' => $pkg, 'build' => $build, 'loader' => $loader, 'bootstrap' => $bootstrap, 'mb' => $mb,
    ];
    $v2Loader = 'loaders/v2/unity/static/UnityLoader.2019.2.js';

    return [
        ['slug' => 'london', 'name' => 'London', 'region' => 'europe', 'mb' => 21, 'training' => true, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 350,
            'legacy' => $legacy('subwaylondon@1.0.0', 'Build/London.json', 'UnityLoader.2019.2.js', '4399.js', 54)],
        ['slug' => 'saintpetersburg', 'name' => 'Saint Petersburg', 'region' => 'europe', 'mb' => 21, 'training' => true, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 205,
            'legacy' => $legacy('subwaystpetersburg@1.0.0', 'Build/StPetersburg.json', $v2Loader)],
        ['slug' => 'iceland', 'name' => 'Iceland', 'region' => 'europe', 'mb' => 21, 'training' => true, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 190,
            'legacy' => $legacy('subwayiceland@1.0.0', 'Build/Iceland/Iceland_1.json')],
        ['slug' => 'barcelona', 'name' => 'Barcelona', 'region' => 'europe', 'mb' => 20, 'training' => true, 'framework' => SUBWAY_FW_SHARED2, 'hue' => 25],
        ['slug' => 'berlin', 'name' => 'Berlin', 'region' => 'europe', 'mb' => 22, 'training' => false, 'framework' => 'https://cdn.jsdelivr.net/npm/subwayberlin@1.0.0/Build/Berlin.wasm.framework.unityweb', 'hue' => 45,
            'legacy' => $legacy('subwayberlin@1.0.0', 'Build/Berlin.json', 'UnityLoader.2019.2.js', '4399.js', 54)],
        ['slug' => 'monaco', 'name' => 'Monaco', 'region' => 'europe', 'mb' => 21, 'training' => false, 'hue' => 330,
            'legacy' => $legacy('subwaymonaco@1.1.0', 'Build/Monaco.json', 'UnityLoader.2019.2.js', '4399.js', 52)],
        ['slug' => 'moscow', 'name' => 'Moscow', 'region' => 'europe', 'mb' => 20, 'training' => false, 'hue' => 5],
        ['slug' => 'paris', 'name' => 'Paris', 'region' => 'europe', 'mb' => 20, 'training' => false, 'hue' => 225],
        ['slug' => 'venice', 'name' => 'Venice', 'region' => 'europe', 'mb' => 24, 'training' => false, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 170],
        ['slug' => 'zurich', 'name' => 'Zurich', 'region' => 'europe', 'mb' => 22, 'training' => false, 'framework' => 'https://cdn.jsdelivr.net/npm/subwayzurich@1.1.3/Build/ZurichNewPrivacy.wasm.framework.unityweb', 'hue' => 0,
            'legacy' => $legacy('subwayzurich@1.1.3', 'Build/ZurichNewPrivacy.json')],

        ['slug' => 'neworleans', 'name' => 'New Orleans', 'region' => 'america', 'mb' => 21, 'training' => true, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 275,
            'legacy' => $legacy('subwayneworleans@1.0.0', 'Build/NewOrleans.json')],
        ['slug' => 'havana', 'name' => 'Havana', 'region' => 'america', 'mb' => 21, 'training' => true, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 15,
            'legacy' => $legacy('subwayhavana@2.0.0', 'Build/Havana_4.json', $v2Loader)],
        ['slug' => 'buenosaires', 'name' => 'Buenos Aires', 'region' => 'america', 'mb' => 21, 'training' => true, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 200],
        ['slug' => 'mexico', 'name' => 'Mexico', 'region' => 'america', 'mb' => 21, 'training' => true, 'framework' => SUBWAY_FW_SHARED2, 'hue' => 140,
            'legacy' => $legacy('subwaymexico@1.0.0', 'Build/Mexico/Mexico3.json', 'UnityLoader.2019.2.js', 'subwaySurf14.08.js')],
        ['slug' => 'miami', 'name' => 'Miami', 'region' => 'america', 'mb' => 26, 'training' => true, 'hue' => 310,
            'legacy' => $legacy('subwaymiami@1.0.0', 'Build/Miami/subway_miami_v1.json', 'UnityLoader.2019.2.js', null, 54)],
        ['slug' => 'houston', 'name' => 'Houston', 'region' => 'america', 'mb' => 21, 'training' => false, 'framework' => 'https://cdn.jsdelivr.net/npm/subwayhouston@1.1.0/Build/Houston/Houston.wasm.framework.unityweb', 'hue' => 30,
            'legacy' => $legacy('subwayhouston@1.1.0', 'Build/Houston/Houston.json', 'UnityLoader.2019.2.js', '4399.sf.js')],
        ['slug' => 'sanfrancisco', 'name' => 'San Francisco', 'region' => 'america', 'mb' => 21, 'training' => false, 'hue' => 12,
            'legacy' => $legacy('subwaysanfrancisco@1.0.0', 'Build/SanFrancisco.json', 'UnityLoader.2019.2.js', '4399.js', 52)],
        ['slug' => 'newyork', 'name' => 'New York', 'region' => 'america', 'mb' => 22, 'training' => false, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 215],
        ['slug' => 'rio', 'name' => 'Rio', 'region' => 'america', 'mb' => 24, 'training' => false, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 95],

        ['slug' => 'beijing', 'name' => 'Beijing', 'region' => 'asia', 'mb' => 22, 'training' => true, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 0,
            'legacy' => $legacy('subwaybeijing@1.0.0', 'Build/Beijing_2.json', $v2Loader, '4399.js', 54)],
        ['slug' => 'hongkong', 'name' => 'Hong Kong', 'region' => 'asia', 'mb' => 24, 'training' => false, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 320],
        ['slug' => 'tokyo', 'name' => 'Tokyo', 'region' => 'asia', 'mb' => 24, 'training' => false, 'framework' => SUBWAY_FW_SHARED1, 'hue' => 340],
        ['slug' => 'bangkok', 'name' => 'Bangkok', 'region' => 'asia', 'mb' => 50, 'training' => false, 'hue' => 50, 'beta' => true],

        ['slug' => 'cairo', 'name' => 'Cairo', 'region' => 'africa', 'mb' => 19, 'training' => false, 'hue' => 40],

        ['slug' => 'winterholiday', 'name' => 'Winter Holiday', 'region' => 'event', 'mb' => 22, 'training' => true, 'framework' => SUBWAY_FW_SHARED2, 'hue' => 185,
            'legacy' => $legacy('subwaywinterholiday@1.0.0', 'Build/WinterHoliday/WinterHoliday.json', 'UnityLoader.2019.2.js', 'subwaySurf14.08.js', 54)],
    ];
}

/**
 * Il catalogo che la pagina mostra davvero: con le build nuove spente restano
 * le mappe con la vecchia build su jsDelivr, senza allenamento e con il peso
 * di quella build.
 */
function subway_catalog_active(): array
{
    if (SUBWAY_NEW_BUILDS) {
        return subway_catalog();
    }
    $maps = [];
    foreach (subway_catalog() as $map) {
        if (empty($map['legacy'])) {
            continue;
        }
        $map['training'] = false;
        $map['mb'] = $map['legacy']['mb'];
        unset($map['framework'], $map['beta']);
        $maps[] = $map;
    }
    return $maps;
}

function subway_catalog_slugs(): array
{
    return array_column(subway_catalog(), 'slug');
}
