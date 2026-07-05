<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli && function_exists('checkBan')) {
    checkBan($mysqli);
}

$lang = isset($apiDocsLang) && in_array($apiDocsLang, ['it', 'en'], true) ? $apiDocsLang : 'en';
$isIt = $lang === 'it';
$baseUrl = 'https://api.cripsum.com';

function api_docs_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$copyLabel = $isIt ? 'Copia' : 'Copy';
$docs = [
    'title' => $isIt ? 'Cripsum™ API Docs' : 'Cripsum™ API Docs',
    'meta' => $isIt
        ? 'Documentazione ufficiale della REST API pubblica Cripsum: presence, leaderboard, banner e profili.'
        : 'Official documentation for the public Cripsum REST API: presence, leaderboards, banners, and profiles.',
    'pill' => $isIt ? 'REST API' : 'REST API',
    'h1' => $isIt ? 'Cripsum™ REST API.' : 'Cripsum™ REST API.',
    'lead' => $isIt
        ? 'Una API RESTful JSON per integrare presence Discord, classifiche, banner gacha e profili Cripsum pubblici in siti, bot, widget e overlay.'
        : 'A RESTful JSON API for integrating Discord presence, leaderboards, gacha banners, and public Cripsum profiles into websites, bots, widgets, and overlays.',
    'openIndex' => $isIt ? 'Apri indice API' : 'Open API index',
    'jumpEndpoints' => $isIt ? 'Vai agli endpoint' : 'Jump to endpoints',
    'quickNav' => $isIt ? 'Navigazione' : 'Navigation',
    'overview' => $isIt ? 'Panoramica' : 'Overview',
    'endpoints' => $isIt ? 'Endpoint' : 'Endpoints',
    'errors' => $isIt ? 'Errori' : 'Errors',
    'baseUrl' => $isIt ? 'Base URL' : 'Base URL',
    'baseText' => $isIt
        ? 'Tutte le route usano risorse prevedibili, metodi HTTP standard e risposte JSON.'
        : 'All routes use predictable resources, standard HTTP methods, and JSON responses.',
    'jsonResponses' => $isIt ? 'Risposte JSON' : 'JSON responses',
    'jsonResponsesText' => $isIt
        ? 'Ogni risposta usa un payload JSON con success e dati nel campo data quando presenti.'
        : 'Every response uses a JSON payload with success and data when available.',
    'restfulRoutes' => $isIt ? 'Route RESTful' : 'RESTful routes',
    'restfulRoutesText' => $isIt
        ? 'Gli endpoint rappresentano risorse: users, leaderboards, banners e profiles.'
        : 'Endpoints represent resources: users, leaderboards, banners, and profiles.',
    'method' => $isIt ? 'Metodo' : 'Method',
    'description' => $isIt ? 'Descrizione' : 'Description',
    'response' => $isIt ? 'Risposta' : 'Response',
    'parameters' => $isIt ? 'Parametri' : 'Parameters',
    'values' => $isIt ? 'Valori' : 'Values',
    'example' => $isIt ? 'Esempio' : 'Example',
    'footerTitle' => $isIt ? 'Vuoi usarla nel sito?' : 'Want to use it on the site?',
    'footerText' => $isIt
        ? 'Usa questi endpoint per widget, landing, card profilo, bot e overlay collegati all’ecosistema Cripsum.'
        : 'Use these endpoints for widgets, landing pages, profile cards, bots, and overlays connected to the Cripsum ecosystem.',
];

$leaderboardTypes = ['godos', 'shards', 'pulls', 'collection', 'achievements', 'missions', 'views', 'duels'];
?>
<!DOCTYPE html>
<html lang="<?php echo api_docs_h($lang); ?>">

<head>
    <?php include __DIR__ . '/head-import.php'; ?>
    <title><?php echo api_docs_h($docs['title']); ?></title>
    <meta name="description" content="<?php echo api_docs_h($docs['meta']); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo api_docs_h($docs['title']); ?>">
    <meta property="og:description" content="<?php echo api_docs_h($docs['meta']); ?>">
    <meta property="og:image" content="https://cripsum.com/img/Susremaster.png">
    <meta property="og:url" content="https://cripsum.com/<?php echo api_docs_h($lang); ?>/api-docs">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="/assets/api-docs/api-docs.css?v=1.3">
    <script src="/assets/api-docs/api-docs.js?v=1.3" defer></script>
</head>

<body class="api-docs-body">
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="docs-bg" aria-hidden="true"></div>
    <div class="docs-grid" aria-hidden="true"></div>

    <main class="docs-page">
        <section class="docs-hero">
            <div>
                <span class="docs-pill"><i class="fa-solid fa-code"></i><?php echo api_docs_h($docs['pill']); ?></span>
                <h1><?php echo api_docs_h($docs['h1']); ?></h1>
                <p><?php echo api_docs_h($docs['lead']); ?></p>
                <div class="docs-actions">
                    <a class="docs-btn docs-btn--primary" href="<?php echo api_docs_h($baseUrl); ?>/v1/cripsum" target="_blank" rel="noopener">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        <span><?php echo api_docs_h($docs['openIndex']); ?></span>
                    </a>
                    <a class="docs-btn" href="#endpoints">
                        <i class="fa-solid fa-list"></i>
                        <span><?php echo api_docs_h($docs['jumpEndpoints']); ?></span>
                    </a>
                </div>
            </div>

            <div class="docs-terminal" aria-label="<?php echo api_docs_h($docs['example']); ?>">
                <div class="docs-terminal__bar">
                    <span class="docs-dot"></span>
                    <span class="docs-dot"></span>
                    <span class="docs-dot"></span>
                </div>
                <pre>curl <?php echo api_docs_h($baseUrl); ?>/v1/cripsum

{
  "success": true,
  "service": "Cripsum Public API",
  "endpoints": {
    "presence": "/v1/users/:discordId",
    "leaderboards": "/v1/cripsum/leaderboards/:type",
    "banners": "/v1/cripsum/banners",
    "profile": "/v1/cripsum/profiles/:username"
  }
}</pre>
            </div>
        </section>

        <div class="docs-layout">
            <aside class="docs-sidebar">
                <strong><?php echo api_docs_h($docs['quickNav']); ?></strong>
                <a href="#overview"><i class="fa-solid fa-compass"></i><?php echo api_docs_h($docs['overview']); ?></a>
                <a href="#endpoints"><i class="fa-solid fa-plug"></i><?php echo api_docs_h($docs['endpoints']); ?></a>
                <a href="#errors"><i class="fa-solid fa-triangle-exclamation"></i><?php echo api_docs_h($docs['errors']); ?></a>
            </aside>

            <div class="docs-content">
                <section class="docs-card" id="overview">
                    <div class="docs-section-title">
                        <div>
                            <h2><?php echo api_docs_h($docs['overview']); ?></h2>
                            <p><?php echo api_docs_h($docs['baseText']); ?></p>
                        </div>
                    </div>

                    <div class="docs-grid-cards">
                        <article class="docs-mini-card">
                            <i class="fa-solid fa-globe"></i>
                            <h3><?php echo api_docs_h($docs['baseUrl']); ?></h3>
                            <p><code class="docs-inline-code"><?php echo api_docs_h($baseUrl); ?></code></p>
                        </article>
                        <article class="docs-mini-card">
                            <i class="fa-solid fa-route"></i>
                            <h3><?php echo api_docs_h($docs['restfulRoutes']); ?></h3>
                            <p><?php echo api_docs_h($docs['restfulRoutesText']); ?></p>
                        </article>
                        <article class="docs-mini-card">
                            <i class="fa-solid fa-code"></i>
                            <h3><?php echo api_docs_h($docs['jsonResponses']); ?></h3>
                            <p><?php echo api_docs_h($docs['jsonResponsesText']); ?></p>
                        </article>
                    </div>
                </section>

                <section class="docs-card" id="endpoints">
                    <div class="docs-section-title">
                        <div>
                            <h2><?php echo api_docs_h($docs['endpoints']); ?></h2>
                            <p><?php echo $isIt ? 'Tutti gli endpoint restituiscono JSON e usano il campo success per indicare l’esito.' : 'Every endpoint returns JSON and uses the success field to indicate the result.'; ?></p>
                        </div>
                    </div>
                </section>

                <article class="docs-endpoint" id="index">
                    <div class="docs-endpoint__top">
                        <span class="docs-method">GET</span>
                        <code class="docs-path">/v1/cripsum</code>
                    </div>
                    <h3><?php echo $isIt ? 'Indice API' : 'API index'; ?></h3>
                    <p><?php echo $isIt ? 'Restituisce una mappa rapida degli endpoint pubblici disponibili.' : 'Returns a quick map of the available public endpoints.'; ?></p>
                    <div class="docs-badges">
                        <span class="docs-badge"><i class="fa-solid fa-unlock"></i><?php echo $isIt ? 'Pubblico' : 'Public'; ?></span>
                        <span class="docs-badge"><i class="fa-solid fa-database"></i>JSON</span>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head">
                            <span>curl</span>
                            <button class="docs-copy" type="button" data-copy-target="#copy-index"><?php echo api_docs_h($copyLabel); ?></button>
                        </div>
                        <pre id="copy-index">curl <?php echo api_docs_h($baseUrl); ?>/v1/cripsum</pre>
                    </div>
                </article>

                <article class="docs-endpoint" id="presence">
                    <div class="docs-endpoint__top">
                        <span class="docs-method">GET</span>
                        <code class="docs-path">/v1/users/:discordId</code>
                    </div>
                    <h3>Discord Presence</h3>
                    <p><?php echo $isIt ? 'Restituisce lo stato Discord in formato compatibile con Lanyard.' : 'Returns Discord presence data in a Lanyard-compatible format.'; ?></p>
                    <div class="docs-table-wrap">
                        <table class="docs-table">
                            <thead>
                                <tr>
                                    <th><?php echo api_docs_h($docs['parameters']); ?></th>
                                    <th><?php echo api_docs_h($docs['description']); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>discordId</code></td>
                                    <td><?php echo $isIt ? 'Snowflake Discord pubblico dell’utente.' : 'Public Discord snowflake for the user.'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head">
                            <span>curl</span>
                            <button class="docs-copy" type="button" data-copy-target="#copy-presence"><?php echo api_docs_h($copyLabel); ?></button>
                        </div>
                        <pre id="copy-presence">curl <?php echo api_docs_h($baseUrl); ?>/v1/users/123456789012345678</pre>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head"><span><?php echo api_docs_h($docs['response']); ?></span></div>
                        <pre>{
  "success": true,
  "data": {
    "discord_user": {
      "id": "123456789012345678",
      "username": "cripsum"
    },
    "discord_status": "online",
    "activities": [],
    "listening_to_spotify": false
  }
}</pre>
                    </div>
                </article>

                <article class="docs-endpoint" id="leaderboards">
                    <div class="docs-endpoint__top">
                        <span class="docs-method">GET</span>
                        <code class="docs-path">/v1/cripsum/leaderboards/:type</code>
                    </div>
                    <h3><?php echo $isIt ? 'Classifiche pubbliche' : 'Public leaderboards'; ?></h3>
                    <p><?php echo $isIt ? 'Restituisce la top 10 pubblica per una metrica aggregata.' : 'Returns the public top 10 for an aggregate metric.'; ?></p>
                    <div class="docs-table-wrap">
                        <table class="docs-table">
                            <thead>
                                <tr>
                                    <th><?php echo api_docs_h($docs['parameters']); ?></th>
                                    <th><?php echo api_docs_h($docs['values']); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>type</code></td>
                                    <td><?php echo api_docs_h(implode(', ', $leaderboardTypes)); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="docs-table-note"><?php echo $isIt ? 'La risposta contiene sempre al massimo 10 entry ordinate per valore decrescente.' : 'The response always contains up to 10 entries ordered by descending value.'; ?></p>
                    <div class="docs-code">
                        <div class="docs-code__head">
                            <span>curl</span>
                            <button class="docs-copy" type="button" data-copy-target="#copy-leaderboard"><?php echo api_docs_h($copyLabel); ?></button>
                        </div>
                        <pre id="copy-leaderboard">curl <?php echo api_docs_h($baseUrl); ?>/v1/cripsum/leaderboards/godos</pre>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head"><span><?php echo api_docs_h($docs['response']); ?></span></div>
                        <pre>{
  "success": true,
  "type": "godos",
  "data": {
    "entries": [
      { "position": 1, "username": "cripsum", "is_premium": true, "value": 25000 }
    ]
  }
}</pre>
                    </div>
                </article>

                <article class="docs-endpoint" id="banners">
                    <div class="docs-endpoint__top">
                        <span class="docs-method">GET</span>
                        <code class="docs-path">/v1/cripsum/banners</code>
                    </div>
                    <h3><?php echo $isIt ? 'Banner gacha pubblici' : 'Public gacha banners'; ?></h3>
                    <p><?php echo $isIt ? 'Mostra i banner attivi senza usare un account: niente saldo, pity personale o garantito.' : 'Shows active banners without using an account: no balance, personal pity, or guarantee state.'; ?></p>
                    <div class="docs-badges">
                        <span class="docs-badge"><i class="fa-solid fa-eye"></i><?php echo $isIt ? 'Solo pubblico' : 'Public only'; ?></span>
                        <span class="docs-badge"><i class="fa-solid fa-image"></i><?php echo $isIt ? 'Immagini incluse' : 'Images included'; ?></span>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head">
                            <span>curl</span>
                            <button class="docs-copy" type="button" data-copy-target="#copy-banners"><?php echo api_docs_h($copyLabel); ?></button>
                        </div>
                        <pre id="copy-banners">curl <?php echo api_docs_h($baseUrl); ?>/v1/cripsum/banners</pre>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head"><span><?php echo api_docs_h($docs['response']); ?></span></div>
                        <pre>{
  "success": true,
  "data": {
    "standard": {
      "id": "standard",
      "name": "Standard Banner",
      "cost": { "godos": 0, "godoshards": 0 }
    },
    "limited": [
      {
        "id": "12",
        "name": "Limited Banner",
        "cost": { "godos": 100, "godoshards": 1 },
        "rate_up_character": {
          "name": "Character",
          "rarity_label": "Secret"
        }
      }
    ]
  }
}</pre>
                    </div>
                </article>

                <article class="docs-endpoint" id="profiles">
                    <div class="docs-endpoint__top">
                        <span class="docs-method">GET</span>
                        <code class="docs-path">/v1/cripsum/profiles/:username</code>
                    </div>
                    <h3><?php echo $isIt ? 'Profili pubblici' : 'Public profiles'; ?></h3>
                    <p><?php echo $isIt ? 'Restituisce dati profilo solo se il profilo Cripsum è pubblico. Se l’account ha Discord collegato, include anche discord_connected e discord_id. Non permette lookup tramite Discord ID.' : 'Returns profile data only when the Cripsum profile is public. If the account has Discord connected, it also includes discord_connected and discord_id. It does not allow Discord ID lookups.'; ?></p>
                    <div class="docs-table-wrap">
                        <table class="docs-table">
                            <thead>
                                <tr>
                                    <th><?php echo api_docs_h($docs['parameters']); ?></th>
                                    <th><?php echo api_docs_h($docs['description']); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>username</code></td>
                                    <td><?php echo $isIt ? 'Username o alias pubblico Cripsum.' : 'Cripsum public username or alias.'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head">
                            <span>curl</span>
                            <button class="docs-copy" type="button" data-copy-target="#copy-profile"><?php echo api_docs_h($copyLabel); ?></button>
                        </div>
                        <pre id="copy-profile">curl <?php echo api_docs_h($baseUrl); ?>/v1/cripsum/profiles/cripsum</pre>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head"><span><?php echo api_docs_h($docs['response']); ?></span></div>
                        <pre>{
  "success": true,
  "found": true,
  "data": {
    "username": "cripsum",
    "display_name": "Cripsum",
    "private": false,
    "profile_url": "https://cripsum.com/u/cripsum",
    "discord_connected": true,
    "discord_id": "123456789012345678",
    "avatar_url": "https://cripsum.com/includes/get_pfp.php?id=1"
  }
}</pre>
                    </div>
                </article>

                <section class="docs-card" id="errors">
                    <h2><?php echo api_docs_h($docs['errors']); ?></h2>
                    <p><?php echo $isIt ? 'Gli errori usano status HTTP standard e payload JSON semplice.' : 'Errors use standard HTTP statuses and a simple JSON payload.'; ?></p>
                    <div class="docs-table-wrap">
                        <table class="docs-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th><?php echo api_docs_h($docs['description']); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>400</code></td>
                                    <td><?php echo $isIt ? 'Parametro mancante o non valido.' : 'Missing or invalid parameter.'; ?></td>
                                </tr>
                                <tr>
                                    <td><code>404</code></td>
                                    <td><?php echo $isIt ? 'Risorsa non trovata quando applicabile.' : 'Resource not found when applicable.'; ?></td>
                                </tr>
                                <tr>
                                    <td><code>502</code></td>
                                    <td><?php echo $isIt ? 'Il servizio Cripsum interno non ha risposto correttamente.' : 'The internal Cripsum service did not respond correctly.'; ?></td>
                                </tr>
                                <tr>
                                    <td><code>503</code></td>
                                    <td><?php echo $isIt ? 'API temporaneamente non configurata o non disponibile.' : 'API temporarily unavailable or not configured.'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="docs-code">
                        <div class="docs-code__head"><span><?php echo api_docs_h($docs['response']); ?></span></div>
                        <pre>{
  "success": false,
  "error": "Invalid leaderboard type.",
  "available_types": ["godos", "shards", "pulls"]
}</pre>
                    </div>
                </section>

                <section class="docs-card docs-footer-cta">
                    <div>
                        <h2><?php echo api_docs_h($docs['footerTitle']); ?></h2>
                        <p><?php echo api_docs_h($docs['footerText']); ?></p>
                    </div>
                    <a class="docs-btn docs-btn--primary" href="<?php echo api_docs_h($baseUrl); ?>/v1/cripsum" target="_blank" rel="noopener">
                        <i class="fa-solid fa-terminal"></i>
                        <span><?php echo api_docs_h($docs['openIndex']); ?></span>
                    </a>
                </section>
            </div>
        </div>
    </main>

    <?php include __DIR__ . ($lang === 'en' ? '/footer-en.php' : '/footer.php'); ?>
</body>

</html>