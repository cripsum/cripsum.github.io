<?php

/**
 * Configurazione del gacha: l'unico posto dove stanno rarita', pesi, pity ed
 * economia dei frammenti.
 *
 * Prima gli stessi numeri erano copiati nei due endpoint di pull, nella
 * lootbox IT ed EN, nell'API dei banner, nel bot e negli helper, con valori
 * che non sempre coincidevano (il pity standard era 90 in un file e 80 in un
 * altro). Adesso li leggono tutti da qui, e il JS li riceve dalla pagina.
 *
 * Si include sempre con require_once.
 */

/**
 * Le rarita' dalla piu' bassa alla piu' alta.
 *
 * `peso` e' la probabilita' base in percentuale (la somma fa ~100).
 * `fascia` raggruppa le rarita' che contano come una sola per il rate-up:
 * segreto e theone stanno nella stessa, ed e' per questo che su un banner
 * evento anche un theone fa scattare il 50/50, come e' sempre stato.
 */
function gacha_rarity_defs(): array
{
    return [
        'comune'      => ['it' => 'Comune',      'en' => 'Common',    'color' => '#9ca3af', 'peso' => 51.00,  'fascia' => 1],
        'raro'        => ['it' => 'Raro',        'en' => 'Rare',      'color' => '#38bdf8', 'peso' => 28.00,  'fascia' => 2],
        'epico'       => ['it' => 'Epico',       'en' => 'Epic',      'color' => '#c084fc', 'peso' => 13.00,  'fascia' => 3],
        'leggendario' => ['it' => 'Leggendario', 'en' => 'Legendary', 'color' => '#fbbf24', 'peso' => 5.999,  'fascia' => 4],
        'speciale'    => ['it' => 'Speciale',    'en' => 'Special',   'color' => '#ffffff', 'peso' => 1.80,   'fascia' => 5],
        'segreto'     => ['it' => 'Segreto',     'en' => 'Secret',    'color' => '#a855f7', 'peso' => 0.20,   'fascia' => 6],
        'theone'      => ['it' => 'The One',     'en' => 'The One',   'color' => '#60a5fa', 'peso' => 0.001,  'fascia' => 6],
    ];
}

/** Chiavi delle rarita' dalla piu' bassa alla piu' alta. */
function gacha_rarity_keys(): array
{
    return array_keys(gacha_rarity_defs());
}

/**
 * Riporta una rarita' scritta in qualsiasi forma alla sua chiave
 * ("Leggendario ", "THE ONE", "the_one", "rarità" con l'accento...).
 * Una rarita' sconosciuta torna stringa vuota: chi chiama decide se
 * scartarla o trattarla come comune.
 */
function gacha_rarity_key(?string $value): string
{
    $v = mb_strtolower(trim((string)$value), 'UTF-8');
    $v = strtr($v, ['à' => 'a', 'á' => 'a', ' ' => '', '_' => '', '-' => '']);

    $aliases = [
        'common' => 'comune', 'rare' => 'raro', 'epic' => 'epico', 'legendary' => 'leggendario',
        'mitico' => 'leggendario', 'special' => 'speciale', 'secret' => 'segreto', 'one' => 'theone',
    ];
    $v = $aliases[$v] ?? $v;

    return isset(gacha_rarity_defs()[$v]) ? $v : '';
}

/** Posizione della rarita' (0 = comune). -1 se sconosciuta. */
function gacha_rarity_rank(?string $value): int
{
    $key = gacha_rarity_key($value);
    if ($key === '') {
        return -1;
    }
    $pos = array_search($key, gacha_rarity_keys(), true);
    return $pos === false ? -1 : (int)$pos;
}

function gacha_rarity_tier(?string $value): int
{
    $key = gacha_rarity_key($value);
    return $key === '' ? 0 : (int)gacha_rarity_defs()[$key]['fascia'];
}

/** Rarita' della stessa fascia, dalla piu' bassa. */
function gacha_tier_rarities(int $tier): array
{
    $out = [];
    foreach (gacha_rarity_defs() as $key => $def) {
        if ((int)$def['fascia'] === $tier) {
            $out[] = $key;
        }
    }
    return $out;
}

function gacha_rarity_label(?string $value, string $lang = 'it'): string
{
    $key = gacha_rarity_key($value);
    if ($key === '') {
        return (string)$value;
    }
    $def = gacha_rarity_defs()[$key];
    return (string)($lang === 'en' ? $def['en'] : $def['it']);
}

/** Pesi base di tutte le rarita'. */
function gacha_base_weights(): array
{
    $out = [];
    foreach (gacha_rarity_defs() as $key => $def) {
        $out[$key] = (float)$def['peso'];
    }
    return $out;
}

/**
 * Le rarita' da cui si parte a mostrare il video della pull e che non si
 * saltano: restano quelle di gacha.js, qui servono al calcolo dei dettagli.
 */
function gacha_top_rarities(): array
{
    return gacha_tier_rarities(6);
}

/* ── Pity ─────────────────────────────────────────────────────────────── */

/**
 * I due gruppi di pity storici, con i numeri esatti di sempre.
 *
 * `soft_bonus` e' quanto sale il peso di ogni rarita' per ogni pull oltre il
 * soft pity; `hard_pesi` e' la ripartizione quando scatta l'hard pity.
 * Sono i valori che stavano scritti a mano in api_gacha_pull.php: standard
 * +4 speciale e +2 segreto a pull dal 70, 90% speciale / 10% segreto al 90;
 * evento +6 segreto e +0,03 theone dal 65, 99% segreto / 1% theone all'80.
 *
 * Il gruppo `evento` e' condiviso da tutti i banner evento: pity e garantito
 * passano da un banner all'altro, come prima.
 */
function gacha_pity_profiles(): array
{
    return [
        'standard' => [
            'soft' => 70,
            'hard' => 90,
            'soglia' => 'speciale',
            'soft_bonus' => ['speciale' => 4.0, 'segreto' => 2.0],
            'hard_pesi' => ['speciale' => 90, 'segreto' => 10],
        ],
        'evento' => [
            'soft' => 65,
            'hard' => 80,
            'soglia' => 'segreto',
            'soft_bonus' => ['segreto' => 6.0, 'theone' => 0.03],
            'hard_pesi' => ['segreto' => 99, 'theone' => 1],
        ],
    ];
}

/** I gruppi il cui contatore sta nelle colonne storiche di `utenti`. */
function gacha_legacy_pity_groups(): array
{
    return [
        'standard' => ['contatore' => 'pity_standard', 'garantito' => null],
        'evento' => ['contatore' => 'pity_evento', 'garantito' => 'garantito_evento'],
    ];
}

/**
 * Il profilo di pity di un gruppo. I due storici sono fissi (cambiarli da un
 * banner cambierebbe il pity di tutti gli altri banner dello stesso gruppo);
 * un gruppo dedicato prende soft, hard e soglia dal banner e ripartisce i
 * bonus in proporzione ai pesi base delle rarita' sopra la soglia.
 */
function gacha_pity_profile(string $group, ?int $soft = null, ?int $hard = null, ?string $soglia = null): array
{
    $profiles = gacha_pity_profiles();
    if (isset($profiles[$group])) {
        return $profiles[$group] + ['gruppo' => $group, 'fisso' => true];
    }

    $soglia = gacha_rarity_key($soglia) ?: 'segreto';
    $hard = max(1, min(1000, $hard ?? 80));
    $soft = max(1, min($hard, $soft ?? max(1, $hard - 15)));

    $base = gacha_base_weights();
    $above = [];
    foreach (gacha_rarity_keys() as $key) {
        if (gacha_rarity_rank($key) >= gacha_rarity_rank($soglia)) {
            $above[$key] = $base[$key];
        }
    }
    $sum = array_sum($above) ?: 1;

    $softBonus = [];
    $hardPesi = [];
    foreach ($above as $key => $weight) {
        $softBonus[$key] = 6.0 * $weight / $sum;
        $hardPesi[$key] = $weight;
    }

    return [
        'soft' => $soft,
        'hard' => $hard,
        'soglia' => $soglia,
        'soft_bonus' => $softBonus,
        'hard_pesi' => $hardPesi,
        'gruppo' => $group,
        'fisso' => false,
    ];
}

/** Probabilita' (0-100) di prendere il rate-up quando esce la sua fascia. */
const GACHA_DEFAULT_FEATURED_QUOTA = 50;

/** Garanzia della multi: almeno una pull da questa rarita' in su ogni 10x. */
const GACHA_MULTI_GUARANTEE_RARITY = 'epico';
const GACHA_MULTI_SIZE = 10;

/** Tempi minimi tra una richiesta e l'altra (anti-spam, per sessione). */
const GACHA_PULL_RATE_LIMIT_MS = 800;
const GACHA_MULTI_RATE_LIMIT_S = 5;

/* ── Frammenti ────────────────────────────────────────────────────────── */

/**
 * Quanti frammenti vale una copia in eccesso, per rarita'. Una copia e' in
 * eccesso quando non serve piu' a potenziare il personaggio fino al MAX.
 */
function gacha_frammenti_valori(): array
{
    return [
        'comune' => 1,
        'raro' => 3,
        'epico' => 6,
        'leggendario' => 15,
        'speciale' => 25,
        'segreto' => 60,
        'theone' => 150,
    ];
}

/** Prezzo in frammenti di un personaggio del negozio, per rarita'. */
function gacha_frammenti_prezzi(): array
{
    return [
        'raro' => 40,
        'epico' => 100,
        'leggendario' => 250,
        'speciale' => 400,
        'segreto' => 1500,
    ];
}

/** Quanti personaggi per rarita' entrano nella rotazione settimanale. */
function gacha_frammenti_slot(): array
{
    return [
        'raro' => 2,
        'epico' => 2,
        'leggendario' => 1,
        'speciale' => 1,
        'segreto' => 1,
    ];
}

/* ── Achievement del gacha ────────────────────────────────────────────── */

/**
 * Gli achievement che prima sbloccava il browser dopo ogni pull. Adesso li
 * assegna il server, con gli stessi id e le stesse soglie.
 */
const GACHA_ACH_FIRST_PULL = 5;
const GACHA_ACH_100_BOXES = 8;
const GACHA_ACH_500_BOXES = 16;
const GACHA_ACH_10_COMMONS = 9;
const GACHA_ACH_100_CHARACTERS = 18;
