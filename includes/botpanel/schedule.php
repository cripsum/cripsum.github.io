<?php
declare(strict_types=1);

/**
 * Calcolo delle ripetizioni dei messaggi programmati.
 *
 * Sta qui e non nell'endpoint perche' lo usano in due: il pannello, per
 * mostrare la prossima uscita, e api/bot/guilds/scheduled.php quando il bot
 * dice di averla pubblicata.
 */

const BOTPANEL_SCHEDULE_REPEATS = ['none', 'daily', 'weekly', 'monthly'];

/**
 * La prossima uscita dopo `$from`, o null se il messaggio non si ripete.
 *
 * Si avanza nel fuso di chi l'ha programmato, sull'ora dell'orologio: "ogni
 * giorno alle 18" resta alle 18 col cambio dell'ora legale. Se il bot e'
 * rimasto spento per giorni si salta direttamente alla prima uscita futura,
 * invece di recuperarle tutte di fila.
 */
function botpanel_schedule_next(int $from, string $repeat, string $tz, int $now): ?int
{
    $step = ['daily' => '+1 day', 'weekly' => '+1 week', 'monthly' => '+1 month'][$repeat] ?? null;
    if ($step === null) {
        return null;
    }

    try {
        $zone = new DateTimeZone($tz !== '' ? $tz : 'Europe/Rome');
    } catch (Throwable $error) {
        $zone = new DateTimeZone('Europe/Rome');
    }

    $at = (new DateTimeImmutable('@' . $from))->setTimezone($zone);

    // Un limite, per sicurezza: un anno di uscite giornaliere.
    for ($i = 0; $i < 400; $i++) {
        $at = $at->modify($step);
        if ($at->getTimestamp() > $now) {
            return $at->getTimestamp();
        }
    }

    return null;
}

/** Un fuso orario che PHP conosce, o quello di Roma. */
function botpanel_schedule_tz(string $tz): string
{
    return in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'Europe/Rome';
}
