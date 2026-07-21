<?php

declare(strict_types=1);

/**
 * Central catalogue for Cripsum redeem codes.
 *
 * Optional availability fields for every entry:
 * - active: false disables the code immediately.
 * - starts_at: "YYYY-MM-DD HH:MM:SS" makes it available from that moment.
 * - expires_at: "YYYY-MM-DD HH:MM:SS" makes it unavailable at that moment.
 *
 * Dates are interpreted in the Europe/Rome timezone.
 */
function cripsum_redeem_codes(): array
{
    return [
        'signortoki' => ['tipo' => 'personaggio', 'nome' => 'TOKI'],
        'cripsum' => ['tipo' => 'personaggio', 'nome' => 'CRIPSUM'],
        'peak' => ['tipo' => 'personaggio', 'nome' => 'MAOMAO'],
        'sburevole' => ['tipo' => 'personaggio', 'nome' => 'ZIO DANILO SBUREVOLE'],

        '67' => [
            'tipo' => 'punti',
            'punti' => 67,
            'descrizione' => ['it' => '+67, aura', 'en' => '+67, aura'],
            'active' => true,
        ],
        'godo' => [
            'tipo' => 'punti',
            'punti' => 1000,
            'descrizione' => ['it' => '+1000, tieni, prenditi sta multi', 'en' => '+1000, here, take these 10 pulls'],
            'active' => true,
        ],
        'nauzterrone' => [
            'tipo' => 'punti',
            'punti' => 6767,
            'descrizione' => ['it' => 'xd xd 67 xd nauz terrone', 'en' => 'xd xd 67 xd nauz terrone'],
        ],
        'update30' => [
            'tipo' => 'punti',
            'punti' => 3000,
            'descrizione' => ['it' => '+3000 punti per l\'aggiornamento!', 'en' => '+3000 points for the update!'],
            'active' => false,
        ],
        'cripsumgift' => [
            'tipo' => 'punti',
            'punti' => 500,
            'descrizione' => ['it' => '5 pull uwu', 'en' => '5 pulls uwu'],
            'active' => false,
        ],
        '5050loser' => [
            'tipo' => 'punti',
            'punti' => 5000,
            'descrizione' => [
                'it' => 'Ci dispiaceva per la tua sfiga, quindi beccati queste 50 pull.',
                'en' => 'We felt bad for your terrible luck, so take these 50 pulls',
            ],
            'active' => true,
        ],
        'sossio' => [
            'tipo' => 'punti',
            'punti' => 10067,
            'descrizione' => ['it' => 'SOSSIOHH Ecco 100 pull!', 'en' => 'SOSSIOHH Here are 100 pulls!'],
            'active' => true,
        ],
        'tunggodisreal' => [
            'tipo' => 'punti',
            'punti' => 12000,
            'descrizione' => [
                'it' => 'Tung god ti ha fatto un regalino... Ecco 120 pull!',
                'en' => 'Tung god made you a little gift... Here are 120 pulls!',
                'active' => false,
            ],
        ],
        'sonsofsparda' => [
            'tipo' => 'punti',
            'punti' => 5000,
            'descrizione' => [
                'it' => 'Dante e Vergil ti hanno regalato 50 pull!',
                'en' => 'Dante and Vergil gifted you 50 pulls!',
            ],
            'active' => false,
        ],
        'jackpot' => [
            'tipo' => 'punti',
            'punti' => 8000,
            'descrizione' => ['it' => 'Jackpot! Ecco 80 pull!', 'en' => 'Jackpot! Here are 80 pulls!'],
            'active' => false,
        ],
        'godo2' => [
            'tipo' => 'punti',
            'punti' => 1000,
            'descrizione' => ['it' => '+1000, tieni, prenditi sta multi', 'en' => '+1000, here, take these 10 pulls'],
            'active' => true,
        ],
        'update31' => [
            'tipo' => 'punti',
            'punti' => 3100,
            'descrizione' => ['it' => '+3000 punti per l\'aggiornamento!', 'en' => '+3000 points for the update!'],
            'active' => false,
        ],
        'palestine' => [
            'tipo' => 'punti',
            'punti' => 3000,
            'descrizione' => ['it' => '+30 pull da parte di netanyahu', 'en' => '+30 pulls from netanyahu'],
            'active' => false,
        ],
        'isekaiglzer' => [
            'tipo' => 'punti',
            'punti' => 1000,
            'descrizione' => ['it' => '+10 pull', 'en' => '+10 pulls'],
            'active' => false,
        ],
        'sticazziminecraftdungeonplayerzestyahhh' => [
            'tipo' => 'punti',
            'punti' => 3000,
            'descrizione' => ['it' => '+30 pull', 'en' => '+30 pulls'],
            'active' => false,
        ],
        'testscadenza' => [
            'tipo' => 'punti',
            'punti' => 100,
            'descrizione' => ['it' => 'ehm test baka', 'en' => 'ehm test baka'],
            'expires_at' => '2026-07-07 23:59:59',
            'active' => false,
        ],
        'testscaduto' => [
            'tipo' => 'punti',
            'punti' => 100,
            'descrizione' => ['it' => 'ehm test baka', 'en' => 'ehm test baka'],
            'expires_at' => '2026-07-03 23:59:59',
            'active' => false,
        ],
        'testscadenza2' => [
            'tipo' => 'punti',
            'punti' => 100,
            'descrizione' => ['it' => 'ehm test baka', 'en' => 'ehm test baka'],
            'expires_at' => '2026-07-07 23:59:59',
            'active' => false,
        ],
        'newlootboxupdate' => [
            'tipo' => 'punti',
            'punti' => 2000,
            'descrizione' => ['it' => '+2000 godos', 'en' => '+2000 godos'],
            'expires_at' => '2026-07-10 23:59:59',
            'active' => false,
        ],
        'polyesterguy' => [
            'tipo' => 'punti',
            'punti' => 2000,
            'descrizione' => ['it' => '+2000 godos', 'en' => '+2000 godos'],
            'expires_at' => '2026-07-21 14:39:36',
            'active' => false,
        ],
        'torturacazzoepalle' => [
            'tipo' => 'punti',
            'punti' => 2000,
            'descrizione' => ['it' => '+2000 godos', 'en' => '+2000 godos'],
            'expires_at' => '2026-07-21 14:39:36',
            'active' => true,
        ],
        'kurumisburrata' => [
            'tipo' => 'punti',
            'punti' => 2069,
            'descrizione' => ['it' => '+2000 godos', 'en' => '+2000 godos'],
            'expires_at' => '2026-07-21 14:39:36',
            'active' => false,
        ],
        'ohhpoppyyy' => [
            'tipo' => 'punti',
            'punti' => 4000,
            'descrizione' => ['it' => '+4000 godos', 'en' => '+4000 godos'],
            'expires_at' => '2026-08-11 17:02:01',
            'active' => true,
        ],
        'newlootboxupdate2' => [
            'tipo' => 'punti',
            'punti' => 2000,
            'descrizione' => ['it' => '+2000 godos', 'en' => '+2000 godos'],
            'expires_at' => '2026-08-11 17:02:01',
            'active' => true,
        ],
        'miao' => [
            'tipo' => 'punti',
            'punti' => 2000,
            'descrizione' => ['it' => '+2000 godos', 'en' => '+2000 godos'],
            'expires_at' => '2026-08-11 17:02:01',
            'active' => true,
        ],
    ];
}

function cripsum_redeem_code_date(?string $value): ?DateTimeImmutable
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    try {
        return new DateTimeImmutable(trim($value), new DateTimeZone('Europe/Rome'));
    } catch (Throwable $e) {
        return null;
    }
}

/** Returns active, scheduled, expired or disabled. */
function cripsum_redeem_code_status(array $entry, ?DateTimeImmutable $now = null): string
{
    if (($entry['active'] ?? true) !== true) {
        return 'disabled';
    }

    $timezone = new DateTimeZone('Europe/Rome');
    $now = $now ? $now->setTimezone($timezone) : new DateTimeImmutable('now', $timezone);
    $startsRaw = isset($entry['starts_at']) ? trim((string)$entry['starts_at']) : '';
    $expiresRaw = isset($entry['expires_at']) ? trim((string)$entry['expires_at']) : '';
    $startsAt = cripsum_redeem_code_date($startsRaw !== '' ? $startsRaw : null);
    $expiresAt = cripsum_redeem_code_date($expiresRaw !== '' ? $expiresRaw : null);

    // A typo in a configured date must never leave a code active forever.
    if (($startsRaw !== '' && !$startsAt) || ($expiresRaw !== '' && !$expiresAt)) {
        return 'disabled';
    }

    if ($startsAt && $now < $startsAt) {
        return 'scheduled';
    }
    if ($expiresAt && $now >= $expiresAt) {
        return 'expired';
    }

    return 'active';
}

function cripsum_redeem_code_is_available(array $entry, ?DateTimeImmutable $now = null): bool
{
    return cripsum_redeem_code_status($entry, $now) === 'active';
}
