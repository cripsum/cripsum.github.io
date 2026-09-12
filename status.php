<?php
/**
 * Stato dei servizi Cripsum.
 *
 * I dati arrivano dallo storico che il bot registra a ogni controllo
 * (service_status_checks / _daily / _incidents): niente piu' barre verdi
 * scritte a mano.
 *
 * La pagina deve reggere anche il database irraggiungibile, che e' proprio il
 * momento in cui qualcuno la apre: per questo la connessione passa da
 * status_connect(), che restituisce null invece di terminare l'esecuzione.
 */

require_once __DIR__ . '/includes/status_helpers.php';

$link = status_connect();
$checks = status_latest_checks($link);
$daily = status_daily($link, 90);
$incidents = status_incidents($link, 12);

$services = status_services();
$states = [];

foreach ($services as $key => $meta) {
    $states[$key] = status_current($checks, $key);
}

// Lo stato del controllore e' un'informazione a parte: i controlli li scrive
// il bot da un'altra macchina, e se smettono di arrivare quello che resta a
// schermo e' l'ultima fotografia, non la realta'.
$monitor = status_monitor($checks);

// Il database lo sa anche la pagina stessa: se non si e' connessa, e' giu'.
if (!$link) {
    $states['database'] = [
        'status' => 'outage',
        'latency_ms' => null,
        'error' => 'la pagina non riesce a connettersi',
        'age' => 0,
    ];
}

$overall = status_overall($states);
$hasHistory = !empty($daily);

$overallCopy = [
    'operational' => ['Tutti i sistemi sono operativi', 'Nessun problema rilevato negli ultimi controlli.'],
    'degraded' => ['Prestazioni ridotte', 'Qualche servizio risponde più lentamente del solito.'],
    'outage' => ['Disservizio in corso', 'Uno o più servizi non rispondono. Ci stiamo lavorando.'],
    'unknown' => ['Stato non verificabile', 'Il controllo automatico non sta rispondendo: i dati mostrati potrebbero non essere aggiornati.'],
][$overall];

$lastUpdate = $monitor['age'];

// Uptime complessivo: media pesata su tutti i servizi con storico.
$totalChecks = 0;
$totalFailures = 0;
foreach ($daily as $rows) {
    foreach ($rows as $row) {
        $totalChecks += $row['checks'];
        $totalFailures += $row['failures'];
    }
}
$globalUptime = $totalChecks > 0 ? (($totalChecks - $totalFailures) / $totalChecks) * 100 : null;

/** I 90 giorni in ordine, dal più vecchio a oggi. */
$days = [];
for ($i = 89; $i >= 0; $i--) {
    $days[] = date('Y-m-d', strtotime("-$i day"));
}

$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// La navbar vuole $mysqli: senza database si mostra un'intestazione ridotta
// invece di far esplodere la pagina.
$mysqli = $link;
$canRenderNav = $link instanceof mysqli;

if ($canRenderNav) {
    require_once __DIR__ . '/config/session_init.php';
    require_once __DIR__ . '/includes/functions.php';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stato dei servizi · Cripsum</title>
    <meta name="robots" content="noindex">

    <?php if ($canRenderNav): ?>
        <?php /* Gli stili della navbar stanno qui dentro: senza questo include
                 il menu esce come un elenco puntato senza formattazione. */ ?>
        <?php $ogDescription = 'Stato in tempo reale dei servizi Cripsum: sito, database, API e bot Discord.'; ?>
        <?php include __DIR__ . '/includes/head-import.php'; ?>
    <?php else: ?>
        <link rel="icon" href="/img/Susremaster.png" type="image/png">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php /* Questo blocco viene dopo gli stili condivisi apposta: a parita' di
             specificita' vince l'ultimo, e la pagina deve restare com'e'
             disegnata anche con Bootstrap caricato sopra. */ ?>
    <style>
        :root {
            /* Lo stesso viola del resto del sito: la pagina di stato non deve
               sembrare presa da un altro prodotto. */
            --st-accent:  #8b5cf6;
            --st-green:   #34d399;
            --st-yellow:  #fbbf24;
            --st-red:     #f87171;
            --st-grey:    #4b5563;
            --st-bg:      #05070d;
            --st-bg-2:    #0a0e1a;
            --st-card:    rgba(10, 14, 27, 0.72);
            --st-text:    #f7f8ff;
            --st-muted:   #aab3c8;
            --st-muted-2: #778199;
            --st-border:  rgba(255, 255, 255, 0.12);
            --st-radius:  22px;
            --st-shadow:  0 24px 70px rgba(0, 0, 0, 0.36);
        }

        * { box-sizing: border-box; }

        /* La classe serve a battere le regole di Bootstrap e di style-dark.css
           che arrivano dall'include condiviso: senza, il fondo e il testo li
           decidono loro. */
        body.st-body {
            margin: 0;
            background:
                radial-gradient(1200px 600px at 50% -10%, rgba(139, 92, 246, 0.13), transparent 60%),
                linear-gradient(180deg, var(--st-bg), var(--st-bg-2));
            background-attachment: fixed;
            color: var(--st-text);
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
            min-height: 100vh;
        }

        /* Un solo carattere su tutta la pagina. Le cifre restano incolonnate
           con tabular-nums invece che con un monospaziato a parte, cosi' le
           percentuali non ballano da sole a ogni aggiornamento. */
        .st-wrap,
        .st-wrap h1,
        .st-wrap h2 {
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }

        .st-num {
            font-variant-numeric: tabular-nums;
            font-feature-settings: 'tnum' 1;
        }

        .st-wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 2.5rem 1.1rem 4rem;
        }

        /* La navbar del sito e' `position: fixed` e non occupa spazio nel
           flusso: senza questo spazio in cima si mangiava il titolo. Vale solo
           quando la navbar c'e' davvero — con il database irraggiungibile al
           suo posto resta un'intestazione normale, che lo spazio se lo prende
           da sola. */
        body.st-has-nav .st-wrap { padding-top: 6rem; }

        .st-title {
            text-align: center;
            margin: 0 0 .4rem;
            font-size: clamp(1.7rem, 4vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .st-subtitle {
            text-align: center;
            color: var(--st-muted-2);
            margin: 0 0 2rem;
            font-size: .95rem;
        }

        /* ── Banner generale ─────────────────────────────────────── */
        .st-banner {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.35rem 1.5rem;
            border-radius: var(--st-radius);
            border: 1px solid var(--st-border);
            background: var(--st-card);
            box-shadow: var(--st-shadow);
            margin-bottom: 1.6rem;
        }

        .st-banner.operational { border-color: rgba(52, 211, 153, .35); background: linear-gradient(180deg, rgba(52,211,153,.10), var(--st-card)); }
        .st-banner.degraded    { border-color: rgba(251, 191, 36, .35); background: linear-gradient(180deg, rgba(251,191,36,.10), var(--st-card)); }
        .st-banner.outage      { border-color: rgba(248, 113, 113, .35); background: linear-gradient(180deg, rgba(248,113,113,.10), var(--st-card)); }
        .st-banner.unknown     { border-color: rgba(148, 163, 184, .28); }

        /* Avviso sul controllore fermo. Deve stare sopra a tutto e non somigliare
           a una scheda di servizio: quando compare, nessuno dei dati sotto vale
           piu' niente. */
        .st-alarm {
            display: flex;
            gap: .8rem;
            align-items: flex-start;
            padding: 1rem 1.2rem;
            margin-bottom: 1rem;
            border-radius: 16px;
            border: 1px solid rgba(251, 191, 36, .4);
            background: linear-gradient(180deg, rgba(251, 191, 36, .12), rgba(251, 191, 36, .04));
            color: var(--st-text);
            font-size: .88rem;
            line-height: 1.55;
        }

        .st-alarm i { color: var(--st-yellow); margin-top: .2rem; flex: 0 0 auto; }
        .st-alarm strong { display: block; margin-bottom: .1rem; }
        .st-alarm span { color: var(--st-muted); }

        .st-dot {
            flex: 0 0 auto;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            position: relative;
        }

        .st-dot::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            animation: st-pulse 2.4s ease-out infinite;
        }

        .st-dot.operational { background: var(--st-green); }
        .st-dot.operational::after { box-shadow: 0 0 0 2px rgba(52,211,153,.45); }
        .st-dot.degraded { background: var(--st-yellow); }
        .st-dot.degraded::after { box-shadow: 0 0 0 2px rgba(251,191,36,.45); }
        .st-dot.outage { background: var(--st-red); }
        .st-dot.outage::after { box-shadow: 0 0 0 2px rgba(248,113,113,.45); }
        .st-dot.unknown { background: var(--st-grey); }
        .st-dot.unknown::after { box-shadow: none; }

        @keyframes st-pulse {
            0%   { transform: scale(.7); opacity: .9; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        .st-banner h2 { margin: 0 0 .15rem; font-size: 1.15rem; font-weight: 700; }
        .st-banner p  { margin: 0; color: var(--st-muted); font-size: .9rem; }

        .st-banner-meta {
            margin-left: auto;
            text-align: right;
            color: var(--st-muted-2);
            font-size: .78rem;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .st-banner-meta strong { display: block; color: var(--st-text); font-size: 1.05rem; }

        /* ── Schede dei servizi ──────────────────────────────────── */
        .st-card {
            border: 1px solid var(--st-border);
            background: var(--st-card);
            border-radius: var(--st-radius);
            padding: 1.2rem 1.35rem 1rem;
            margin-bottom: .9rem;
        }

        .st-card-head {
            display: flex;
            align-items: center;
            gap: .7rem;
            flex-wrap: wrap;
            margin-bottom: .9rem;
        }

        .st-card-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: rgba(47, 107, 255, .14);
            color: var(--st-accent);
            flex: 0 0 auto;
        }

        .st-card-name { font-weight: 650; font-size: 1rem; line-height: 1.2; }
        .st-card-hint { color: var(--st-muted-2); font-size: .78rem; }

        .st-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .32rem .7rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .st-badge.operational { color: var(--st-green);  background: rgba(52,211,153,.12);  border-color: rgba(52,211,153,.3); }
        .st-badge.degraded    { color: var(--st-yellow); background: rgba(251,191,36,.12);  border-color: rgba(251,191,36,.3); }
        .st-badge.outage      { color: var(--st-red);    background: rgba(248,113,113,.12); border-color: rgba(248,113,113,.3); }
        .st-badge.unknown     { color: var(--st-muted);  background: rgba(148,163,184,.10); border-color: rgba(148,163,184,.25); }

        .st-latency {
            font-variant-numeric: tabular-nums;
            font-size: .72rem;
            opacity: .75;
        }

        /* ── Barre della cronologia ──────────────────────────────── */
        .st-bars {
            display: flex;
            gap: 2px;
            align-items: stretch;
            height: 34px;
            margin-bottom: .5rem;
        }

        .st-bar {
            flex: 1 1 0;
            min-width: 0;
            border-radius: 3px;
            background: var(--st-grey);
            opacity: .35;
            transition: transform .15s ease, opacity .15s ease;
        }

        .st-bar:hover { transform: scaleY(1.12); opacity: 1; }
        .st-bar.operational { background: var(--st-green); opacity: .85; }
        .st-bar.degraded    { background: var(--st-yellow); opacity: .9; }
        .st-bar.outage      { background: var(--st-red); opacity: .95; }

        .st-legend {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--st-muted-2);
            font-size: .72rem;
        }

        .st-legend b { color: var(--st-text); font-weight: 600; }
        .st-error { color: var(--st-red); font-size: .78rem; margin-top: .5rem; }

        /* ── Incidenti ───────────────────────────────────────────── */
        .st-section-title {
            margin: 2.2rem 0 .9rem;
            font-size: 1.05rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .st-incident {
            border: 1px solid var(--st-border);
            border-left: 3px solid var(--st-red);
            background: var(--st-card);
            border-radius: 14px;
            padding: .85rem 1.1rem;
            margin-bottom: .6rem;
        }

        .st-incident.resolved { border-left-color: var(--st-green); }

        .st-incident-head {
            display: flex;
            gap: .6rem;
            align-items: baseline;
            flex-wrap: wrap;
            margin-bottom: .2rem;
        }

        .st-incident-service { font-weight: 650; }

        .st-incident-tag {
            font-size: .7rem;
            font-weight: 700;
            padding: .1rem .5rem;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .st-incident-tag.open     { color: var(--st-red);   background: rgba(248,113,113,.14); }
        .st-incident-tag.resolved { color: var(--st-green); background: rgba(52,211,153,.14); }

        .st-incident-meta {
            color: var(--st-muted-2);
            font-size: .78rem;
            font-variant-numeric: tabular-nums;
        }

        .st-empty {
            border: 1px dashed var(--st-border);
            border-radius: 14px;
            padding: 1.4rem;
            text-align: center;
            color: var(--st-muted-2);
            font-size: .88rem;
        }

        .st-note {
            margin-top: 2rem;
            text-align: center;
            color: var(--st-muted-2);
            font-size: .78rem;
            line-height: 1.7;
        }

        .st-note a { color: var(--st-accent); text-decoration: none; }
        .st-note a:hover { text-decoration: underline; }

        .st-fallback-head {
            text-align: center;
            padding: 1.2rem;
            border-bottom: 1px solid var(--st-border);
            font-weight: 700;
        }

        .st-fallback-head a { color: var(--st-text); text-decoration: none; }

        /* ── Schermi piccoli ─────────────────────────────────────── */
        @media (max-width: 720px) {
            .st-wrap { padding: 1.6rem .85rem 3rem; }

            .st-banner { flex-wrap: wrap; padding: 1.1rem 1.15rem; }
            .st-banner-meta { margin-left: 0; text-align: left; width: 100%; }

            .st-badge { margin-left: 0; width: 100%; justify-content: center; }
            .st-card-head { gap: .6rem; }

            /* Meno giorni invece di barre illeggibili o pagina che scorre.
               Le barre vanno dalla piu' vecchia alla piu' recente, quindi si
               nascondono le PRIME sessanta: cosi' restano gli ultimi trenta
               giorni. Nascondendo le ultime sessanta, come faceva prima, su
               telefono si vedeva il mese piu' vecchio e non quello in corso. */
            .st-bars { height: 30px; }
            .st-bar:nth-child(-n+60) { display: none; }
            .st-legend-desktop { display: none; }
        }

        @media (min-width: 721px) {
            .st-legend-mobile { display: none; }
        }
    </style>
</head>
<body class="st-body<?php echo $canRenderNav ? ' st-has-nav' : ''; ?>">

<?php if ($canRenderNav): ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
<?php else: ?>
    <div class="st-fallback-head"><a href="/">Cripsum</a></div>
<?php endif; ?>

<main class="st-wrap">
    <h1 class="st-title">Stato dei servizi</h1>
    <p class="st-subtitle">Controlli automatici eseguiti dall'esterno, ogni minuto.</p>

    <?php if ($monitor['never']): ?>
        <div class="st-alarm">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <strong>Nessun controllo ricevuto</strong>
                <span>
                    Il controllo automatico non ha ancora scritto niente: finché non arriva il primo giro,
                    di questi servizi non si sa nulla.
                </span>
            </div>
        </div>
    <?php elseif ($monitor['stale']): ?>
        <div class="st-alarm">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <strong>I controlli non stanno arrivando</strong>
                <span>
                    L'ultimo è di <span class="st-num"><?php echo $h(status_format_duration($monitor['age'])); ?></span> fa,
                    e dovrebbe arrivarne uno ogni minuto. Quello che vedi qui sotto è l'ultima fotografia:
                    può non corrispondere più a come stanno le cose adesso.
                </span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($monitor['skew']): ?>
        <div class="st-alarm">
            <i class="fa-solid fa-clock"></i>
            <div>
                <strong>Orologi non allineati</strong>
                <span>
                    I controlli risultano registrati nel futuro: il server del sito e quello del database
                    hanno due orari diversi. Le durate mostrate in questa pagina non sono attendibili
                    finché non viene sistemato.
                </span>
            </div>
        </div>
    <?php endif; ?>

    <section class="st-banner <?php echo $h($overall); ?>">
        <span class="st-dot <?php echo $h($overall); ?>"></span>
        <div>
            <h2><?php echo $h($overallCopy[0]); ?></h2>
            <p><?php echo $h($overallCopy[1]); ?></p>
        </div>
        <div class="st-banner-meta">
            <?php if ($globalUptime !== null): ?>
                <strong class="st-num"><?php echo number_format($globalUptime, 2); ?>%</strong>
                uptime · 90 giorni
            <?php else: ?>
                <strong>—</strong>
                nessuno storico
            <?php endif; ?>
        </div>
    </section>

    <?php foreach ($services as $key => $meta):
        $state = $states[$key];
        $uptime = status_uptime($daily, $key);
        $rows = $daily[$key] ?? [];
    ?>
        <article class="st-card">
            <div class="st-card-head">
                <span class="st-card-icon"><i class="fa-solid <?php echo $h($meta['icon']); ?>"></i></span>
                <div>
                    <div class="st-card-name"><?php echo $h($meta['label']); ?></div>
                    <div class="st-card-hint"><?php echo $h($meta['hint']); ?></div>
                </div>
                <span class="st-badge <?php echo $h($state['status']); ?>">
                    <?php echo $h(status_label($state['status'])); ?>
                    <?php /* `!== null` e non `!empty()`: una misura di 0 ms e' un
                             dato, ed e' quello che viene fuori da un controllo su
                             disco locale. Con empty() spariva e sembrava un
                             servizio mai controllato. */ ?>
                    <?php if ($state['latency_ms'] !== null): ?>
                        <span class="st-latency st-num">
                            <?php echo $state['latency_ms'] > 0 ? (int)$state['latency_ms'] . ' ms' : '&lt;1 ms'; ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="st-bars">
                <?php foreach ($days as $day):
                    $row = $rows[$day] ?? null;

                    if ($row === null) {
                        $class = '';
                        $title = date('d/m/Y', strtotime($day)) . ' · nessun dato';
                    } else {
                        $class = $row['worst'];
                        $uptimeDay = $row['checks'] > 0
                            ? (($row['checks'] - $row['failures']) / $row['checks']) * 100
                            : 100;
                        $title = date('d/m/Y', strtotime($day)) . ' · '
                            . number_format($uptimeDay, 1) . '% · '
                            . $row['checks'] . ' controlli'
                            . ($row['failures'] > 0 ? ', ' . $row['failures'] . ' falliti' : '');
                    }
                ?>
                    <span class="st-bar <?php echo $h($class); ?>" title="<?php echo $h($title); ?>"></span>
                <?php endforeach; ?>
            </div>

            <div class="st-legend">
                <span class="st-legend-desktop">90 giorni fa</span>
                <span class="st-legend-mobile">30 giorni fa</span>
                <span>
                    <?php if ($uptime !== null): ?>
                        <b class="st-num"><?php echo number_format($uptime, 2); ?>%</b> di uptime
                    <?php else: ?>
                        in attesa dei primi controlli
                    <?php endif; ?>
                </span>
                <span>oggi</span>
            </div>

            <?php if (!empty($state['error'])): ?>
                <div class="st-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $h($state['error']); ?></div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <h2 class="st-section-title"><i class="fa-solid fa-clock-rotate-left"></i> Cronologia dei disservizi</h2>

    <?php if ($incidents): ?>
        <?php foreach ($incidents as $incident):
            $resolved = $incident['ended_at'] !== null;
            $label = $services[$incident['service']]['label'] ?? $incident['service'];
        ?>
            <div class="st-incident <?php echo $resolved ? 'resolved' : ''; ?>">
                <div class="st-incident-head">
                    <span class="st-incident-service"><?php echo $h($label); ?></span>
                    <span class="st-incident-tag <?php echo $resolved ? 'resolved' : 'open'; ?>">
                        <?php echo $resolved ? 'risolto' : 'in corso'; ?>
                    </span>
                </div>
                <div class="st-incident-meta">
                    <?php echo $h(date('d/m/Y H:i', strtotime($incident['started_at']))); ?>
                    <?php if ($resolved): ?>
                        → <?php echo $h(date('H:i', strtotime((string)$incident['ended_at']))); ?>
                        · durata <?php echo $h(status_format_duration($incident['duration'])); ?>
                    <?php else: ?>
                        · ancora aperto
                    <?php endif; ?>
                    <?php if (!empty($incident['error'])): ?>
                        · <?php echo $h($incident['error']); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif ($hasHistory): ?>
        <div class="st-empty">Nessun disservizio registrato. 🎉</div>
    <?php else: ?>
        <div class="st-empty">
            Lo storico è appena partito: le barre si riempiranno man mano che arrivano i controlli.
        </div>
    <?php endif; ?>

    <p class="st-note">
        <?php if ($lastUpdate !== null): ?>
            Ultimo controllo <span class="st-num"><?php echo $h(status_format_duration($lastUpdate)); ?></span> fa.
        <?php else: ?>
            Nessun controllo ancora registrato.
        <?php endif; ?>
        <br>
        I controlli arrivano dal bot, che gira su una macchina diversa dal sito: se smettono di arrivare,
        la pagina lo dice invece di mostrare tutto verde.
        <br>
        Problemi non elencati qui? <a href="/it/supporto">Apri un ticket</a>.
    </p>
</main>

<?php if ($canRenderNav && is_file(__DIR__ . '/includes/footer.php')): ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>

</body>
</html>
