<?php
declare(strict_types=1);

/**
 * Le pagine di bio.cripsum.com: l'ingresso, «La tua pagina» ed Esplora
 * (bio/index.php, bio/esplora.php). Le tavole approvate sono in
 * proposte-home/bio-tavola-1..4.png, fuori dal repo.
 *
 * E' lo stesso sito: navbar e footer veri, tema del sito, stesse sessioni
 * (l'accesso passa da includes/bio_sso.php). Qui stanno le scritte nelle due
 * lingue, il guscio delle pagine e le letture dal database. Lo stile sta in
 * assets/bio/bio.css, i comportamenti in assets/bio/bio.js.
 */

require_once __DIR__ . '/bio_sso.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/profile_helpers.php';

/**
 * La lingua delle pagine di bio. Non c'e' /it/ o /en/ nell'indirizzo: vale
 * ?lang= (il cambio lingua della navbar), poi quella ricordata dal sito.
 */
function bio_lang(): string
{
    $asked = (string)($_GET['lang'] ?? '');
    if (in_array($asked, ['it', 'en'], true)) {
        if (($_COOKIE['cripsum_lang'] ?? '') !== $asked && !headers_sent()) {
            setcookie('cripsum_lang', $asked, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => !empty(session_get_cookie_params()['secure']),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['cripsum_lang'] = $asked;

        return $asked;
    }

    return cripsum_preferred_lang();
}

function bio_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Le scritte delle pagine di bio. */
function bio_t(string $key, string $lang): string
{
    static $dict = [
        'it' => [
            'meta_title' => 'bio.cripsum.com — il tuo link in bio',
            'meta_desc' => 'Una pagina con la tua musica, i tuoi colori e tutti i tuoi link, a un indirizzo corto da mettere ovunque.',
            'hero_1' => 'Il tuo link in bio,',
            'hero_2' => 'fatto come vuoi tu.',
            'hero_lead' => "Una pagina con la tua musica, i tuoi colori e tutti i tuoi link, a un indirizzo corto da mettere ovunque. Basta l'account di Cripsum.",
            'claim_label' => 'Il nome che vuoi',
            'claim_ph' => 'tuonome',
            'claim_btn' => 'Prendilo',
            'name_free' => '%s è libero',
            'name_taken' => '%s è già preso.',
            'name_taken_link' => 'Guarda la sua pagina',
            'name_reserved' => "%s è una parola del sito, scegline un'altra.",
            'name_invalid' => 'Da 3 a 20 caratteri: lettere, numeri e _.',
            'name_yours' => '%s è già tuo.',
            'name_checking' => 'Controllo…',
            'have_account' => 'Hai già un account?',
            'sign_in' => 'Accedi',
            'showcase' => 'In vetrina',
            'explore_all' => 'Esplora tutti',
            'features' => 'Cosa ci puoi mettere',
            'f_bg' => 'Sfondo e colori', 'f_bg_t' => "Un'immagine, una gif o un video, e i colori che vuoi tu.",
            'f_music' => 'La tua canzone', 'f_music_t' => 'Parte quando qualcuno apre la tua pagina.',
            'f_fx' => 'Effetti', 'f_fx_t' => 'Il cursore, il nome animato, la scheda che si inclina.',
            'f_links' => 'Link e social', 'f_links_t' => "Tutti i tuoi posti, nell'ordine che vuoi.",
            'f_discord' => 'Discord', 'f_discord_t' => 'Stato, cosa stai ascoltando e il tuo server, sempre aggiornati.',
            'f_badges' => 'Badge e personaggi', 'f_badges_t' => 'Gli achievement e i pull migliori che hai fatto su Cripsum.',
            'your_page' => 'La tua pagina',
            'copy_link' => 'Copia link',
            'copied' => 'Link copiato.',
            'qr' => 'QR',
            'qr_title' => 'Il QR della tua pagina',
            'close' => 'Chiudi',
            'open' => 'Apri',
            'edit' => 'Modifica',
            'welcome' => 'bio.cripsum.com/%s adesso è tuo.',
            'claim_also' => 'Vuoi anche bio.cripsum.com/%s?',
            'claim_also_t' => "L'hai scelto prima di entrare ed è ancora libero: diventa il tuo link breve.",
            'claim_done' => 'Fatto: bio.cripsum.com/%s è tuo.',
            'who_sees' => 'Chi può vederla',
            'vis_public' => 'Tutti', 'vis_logged_in' => 'Registrati', 'vis_friends' => 'Amici', 'vis_private' => 'Solo io',
            'vis_now_public' => 'Adesso la vede chiunque abbia il link, anche senza account.',
            'vis_now_logged_in' => 'Adesso la vede solo chi ha un account su Cripsum.',
            'vis_now_friends' => 'Adesso la vedono solo i tuoi amici.',
            'vis_now_private' => 'Adesso la vedi solo tu.',
            'explore_switch' => 'Mostrami in Esplora',
            'explore_switch_t' => "La tua pagina compare nell'elenco dei profili della community.",
            'explore_switch_off' => 'Esplora mostra solo le pagine che vedono tutti.',
            'saved' => 'Salvato.',
            'save_failed' => 'Non sono riuscito a salvare. Riprova.',
            'todo' => 'Da completare',
            'todo_of' => '%d di %d',
            'todo_address' => 'Scegli il tuo indirizzo',
            'todo_photo' => 'Metti una foto profilo',
            'todo_bio' => 'Scrivi una bio',
            'todo_links' => 'Aggiungi un social o un link',
            'todo_bg' => 'Scegli uno sfondo',
            'todo_go' => 'Fai',
            'areas' => 'Modifica',
            'a_profile' => 'Profilo', 'a_profile_t' => 'Foto, nome, bio, tag e social.',
            'a_appearance' => 'Aspetto', 'a_appearance_t' => 'Tema, colori, layout e sfondo.',
            'a_effects' => 'Effetti', 'a_effects_t' => 'Nome, anello della foto, cursore.',
            'a_music' => 'Musica', 'a_music_t' => 'La canzone che parte quando ti aprono.',
            'a_sections' => 'Sezioni', 'a_sections_t' => 'Link, progetti, preferiti, in ordine.',
            'a_settings' => 'Impostazioni', 'a_settings_t' => 'Indirizzo, Discord, scheda del browser.',
            'preview' => 'Anteprima della tua pagina',
            'views' => 'Visite',
            'last_edit' => 'Ultima modifica',
            'v_title' => 'Visite',
            'v_total' => 'negli ultimi 30 giorni',
            'v_aria' => 'Visite al giorno negli ultimi 30 giorni: %d in tutto, al massimo %d in un giorno.',
            'v_one' => '1 visita',
            'v_many' => '%d visite',
            'v_table' => 'Vedi i numeri',
            'v_day' => 'Giorno',
            'v_since' => 'Il conto per giorno parte dal %s.',
            'v_off' => 'Hai spento le statistiche: le visite per giorno non si contano. Le riaccendi nelle %s.',
            'v_off_link' => 'Impostazioni',
            'today' => 'oggi',
            'yesterday' => 'ieri',
            'days_ago' => 'giorni fa',
            'never' => 'mai',
            'requests_1' => "1 richiesta d'amicizia",
            'requests_n' => "%d richieste d'amicizia",
            'requests_t' => 'Rispondi su Cripsum',
            'explore_row' => 'Esplora',
            'explore_row_t' => 'Le pagine della community',
            'premium' => 'Premium',
            'premium_t' => 'Più sezioni, effetti e caratteri',
            'account_note' => 'Password, email e dispositivi stanno nelle %s.',
            'account_link' => 'Impostazioni di Cripsum',
            'explore' => 'Esplora',
            'explore_lead' => 'Le pagine della community di Cripsum.',
            'search_ph' => 'Cerca un nome…',
            'search_label' => 'Cerca un nome',
            'tab_vetrina' => 'In vetrina', 'tab_nuove' => 'Nuove', 'tab_viste' => 'Più viste', 'tab_premium' => 'Premium', 'tab_amici' => 'Amici',
            'more' => 'Mostra altre',
            'hide_note' => 'Non vuoi comparire qui? Lo togli da %s.',
            'empty_vetrina' => 'La vetrina è vuota, per ora.',
            'empty_vetrina_staff' => 'Metti una pagina in vetrina con la stellina sulla sua scheda, o dalla sezione «Vetrina» di «La tua pagina».',
            'empty_amici' => 'I tuoi amici non hanno ancora una pagina che si vede qui.',
            'empty_premium' => 'Ancora nessuna pagina Premium da mostrare.',
            'empty_search' => 'Nessuna pagina con questo nome.',
            'empty_all' => 'Ancora nessuna pagina da mostrare.',
            'feature_on' => 'Metti in vetrina',
            'feature_off' => 'Togli dalla vetrina',
            'from_community' => 'Dalla community',
            'err_login' => 'Devi accedere per farlo.',
            'err_csrf' => 'Sessione scaduta. Ricarica la pagina.',
            'err_slow' => 'Troppe richieste: aspetta un attimo.',
            'err_generic' => 'Qualcosa non è andato. Riprova.',
            'err_taken' => 'Quel nome non è più libero.',
            'install' => 'Per Esplora e la vetrina manca un passo nel database: migrations/2026_10_18_bio_esplora.sql.',
            // La vetrina nel pannello (solo staff).
            'sv_title' => 'Vetrina',
            'sv_staff' => 'Staff',
            'sv_lead' => "Le pagine in alto su bio.cripsum.com per chi non è dentro. Nell'ingresso vanno le prime 4; se sono meno, il resto lo riempiono le più viste.",
            'sv_order_hint' => "Trascina le pagine dalla maniglia per cambiare l'ordine. Le nuove arrivano in cima.",
            'sv_move' => 'Sposta',
            'sv_move_hint' => 'Trascina, o usa le frecce su e giù.',
            'sv_order_saved' => 'Ordine salvato.',
            'sv_order_stale' => 'La vetrina è cambiata nel frattempo: ricarica la pagina.',
            'sv_empty' => "La vetrina è vuota: l'ingresso mostra le pagine più viste.",
            'sv_add_label' => 'Aggiungi una pagina alla vetrina',
            'sv_add_ph' => 'Nome o link della pagina',
            'sv_add' => 'Aggiungi',
            'sv_phone' => 'Nel telefono',
            'sv_phone_label' => "La pagina nel telefono dell'ingresso",
            'sv_phone_set' => 'Fatto: adesso è nel telefono.',
            'sv_only_explore' => 'solo in Esplora',
            'sv_or_star' => 'Puoi anche usare la stellina sulle schede di %s.',
            'sv_install' => "Per cambiare l'ordine e scegliere la pagina nel telefono manca un passo nel database: migrations/2026_10_18_bio_vetrina.sql. Intanto le pagine vanno dall'ultima aggiunta e nel telefono c'è la prima.",
            'sv_added' => 'Aggiunta alla vetrina.',
            'sv_removed' => 'Tolta dalla vetrina.',
            'sv_not_found' => 'Nessuna pagina con questo nome.',
            'sv_not_public' => "Questa pagina non può andare in vetrina: dev'essere pubblica e visibile in Esplora.",
            'sv_not_featured' => 'Nel telefono va una pagina della vetrina.',
        ],
        'en' => [
            'meta_title' => 'bio.cripsum.com — your link in bio',
            'meta_desc' => 'A page with your music, your colours and all your links, at a short address you can put anywhere.',
            'hero_1' => 'Your link in bio,',
            'hero_2' => 'made your way.',
            'hero_lead' => 'A page with your music, your colours and all your links, at a short address you can put anywhere. All you need is a Cripsum account.',
            'claim_label' => 'The name you want',
            'claim_ph' => 'yourname',
            'claim_btn' => 'Take it',
            'name_free' => '%s is free',
            'name_taken' => '%s is already taken.',
            'name_taken_link' => 'See their page',
            'name_reserved' => '%s is a word the site uses, pick another one.',
            'name_invalid' => '3 to 20 characters: letters, numbers and _.',
            'name_yours' => '%s is already yours.',
            'name_checking' => 'Checking…',
            'have_account' => 'Already have an account?',
            'sign_in' => 'Sign in',
            'showcase' => 'Featured',
            'explore_all' => 'Explore all',
            'features' => 'What you can add',
            'f_bg' => 'Background and colours', 'f_bg_t' => 'An image, a gif or a video, and the colours you like.',
            'f_music' => 'Your song', 'f_music_t' => 'It plays when someone opens your page.',
            'f_fx' => 'Effects', 'f_fx_t' => 'The cursor, an animated name, a card that tilts.',
            'f_links' => 'Links and socials', 'f_links_t' => 'All your places, in the order you want.',
            'f_discord' => 'Discord', 'f_discord_t' => 'Status, what you are listening to and your server, always up to date.',
            'f_badges' => 'Badges and characters', 'f_badges_t' => 'Your achievements and best pulls on Cripsum.',
            'your_page' => 'Your page',
            'copy_link' => 'Copy link',
            'copied' => 'Link copied.',
            'qr' => 'QR',
            'qr_title' => "Your page's QR code",
            'close' => 'Close',
            'open' => 'Open',
            'edit' => 'Edit',
            'welcome' => 'bio.cripsum.com/%s is yours now.',
            'claim_also' => 'Do you also want bio.cripsum.com/%s?',
            'claim_also_t' => 'You picked it before signing in and it is still free: it becomes your short link.',
            'claim_done' => 'Done: bio.cripsum.com/%s is yours.',
            'who_sees' => 'Who can see it',
            'vis_public' => 'Everyone', 'vis_logged_in' => 'Members', 'vis_friends' => 'Friends', 'vis_private' => 'Only me',
            'vis_now_public' => 'Right now anyone with the link can see it, even without an account.',
            'vis_now_logged_in' => 'Right now only people with a Cripsum account can see it.',
            'vis_now_friends' => 'Right now only your friends can see it.',
            'vis_now_private' => 'Right now only you can see it.',
            'explore_switch' => 'Show me in Explore',
            'explore_switch_t' => "Your page shows up in the list of the community's profiles.",
            'explore_switch_off' => 'Explore only shows pages everyone can see.',
            'saved' => 'Saved.',
            'save_failed' => 'Could not save. Try again.',
            'todo' => 'To do',
            'todo_of' => '%d of %d',
            'todo_address' => 'Pick your address',
            'todo_photo' => 'Add a profile photo',
            'todo_bio' => 'Write a bio',
            'todo_links' => 'Add a social or a link',
            'todo_bg' => 'Pick a background',
            'todo_go' => 'Do it',
            'areas' => 'Edit',
            'a_profile' => 'Profile', 'a_profile_t' => 'Photo, name, bio, tags and socials.',
            'a_appearance' => 'Style', 'a_appearance_t' => 'Theme, colours, layout and background.',
            'a_effects' => 'Effects', 'a_effects_t' => 'Name, photo ring, cursor.',
            'a_music' => 'Music', 'a_music_t' => 'The song that plays when they open you.',
            'a_sections' => 'Sections', 'a_sections_t' => 'Links, projects, favourites, in order.',
            'a_settings' => 'Settings', 'a_settings_t' => 'Address, Discord, browser tab.',
            'preview' => 'Preview of your page',
            'views' => 'Views',
            'last_edit' => 'Last edit',
            'v_title' => 'Views',
            'v_total' => 'in the last 30 days',
            'v_aria' => 'Views per day in the last 30 days: %d in total, at most %d in a day.',
            'v_one' => '1 view',
            'v_many' => '%d views',
            'v_table' => 'See the numbers',
            'v_day' => 'Day',
            'v_since' => 'Daily counting starts on %s.',
            'v_off' => 'You turned statistics off: daily views are not counted. Turn them back on in %s.',
            'v_off_link' => 'Settings',
            'today' => 'today',
            'yesterday' => 'yesterday',
            'days_ago' => 'days ago',
            'never' => 'never',
            'requests_1' => '1 friend request',
            'requests_n' => '%d friend requests',
            'requests_t' => 'Answer on Cripsum',
            'explore_row' => 'Explore',
            'explore_row_t' => "The community's pages",
            'premium' => 'Premium',
            'premium_t' => 'More sections, effects and fonts',
            'account_note' => 'Password, email and devices are in %s.',
            'account_link' => 'Cripsum settings',
            'explore' => 'Explore',
            'explore_lead' => "The pages of Cripsum's community.",
            'search_ph' => 'Search a name…',
            'search_label' => 'Search a name',
            'tab_vetrina' => 'Featured', 'tab_nuove' => 'New', 'tab_viste' => 'Most viewed', 'tab_premium' => 'Premium', 'tab_amici' => 'Friends',
            'more' => 'Show more',
            'hide_note' => "Don't want to show up here? Turn it off in %s.",
            'empty_vetrina' => 'Nothing featured yet.',
            'empty_vetrina_staff' => 'Feature a page with the star on its card, or from the "Showcase" section of "Your page".',
            'empty_amici' => 'Your friends do not have a page that shows up here yet.',
            'empty_premium' => 'No Premium pages to show yet.',
            'empty_search' => 'No page with this name.',
            'empty_all' => 'No pages to show yet.',
            'feature_on' => 'Feature',
            'feature_off' => 'Remove from featured',
            'from_community' => 'From the community',
            'err_login' => 'You need to sign in to do this.',
            'err_csrf' => 'Session expired. Reload the page.',
            'err_slow' => 'Too many requests: wait a moment.',
            'err_generic' => 'Something went wrong. Try again.',
            'err_taken' => 'That name is no longer free.',
            'install' => 'Explore and featured pages need one database step: migrations/2026_10_18_bio_esplora.sql.',
            'sv_title' => 'Showcase',
            'sv_staff' => 'Staff',
            'sv_lead' => "The pages at the top of bio.cripsum.com for people who aren't signed in. The landing page shows the first 4; if there are fewer, the most viewed fill the rest.",
            'sv_order_hint' => 'Drag the pages by the handle to change the order. New ones go to the top.',
            'sv_move' => 'Move',
            'sv_move_hint' => 'Drag, or use the up and down arrows.',
            'sv_order_saved' => 'Order saved.',
            'sv_order_stale' => 'The showcase changed in the meantime: reload the page.',
            'sv_empty' => 'The showcase is empty: the landing page shows the most viewed pages.',
            'sv_add_label' => 'Add a page to the showcase',
            'sv_add_ph' => 'Name or link of the page',
            'sv_add' => 'Add',
            'sv_phone' => 'In the phone',
            'sv_phone_label' => "The page in the landing page's phone",
            'sv_phone_set' => 'Done: it is in the phone now.',
            'sv_only_explore' => 'only in Explore',
            'sv_or_star' => 'You can also use the star on the cards in %s.',
            'sv_install' => 'Changing the order and choosing the page in the phone need one database step: migrations/2026_10_18_bio_vetrina.sql. Until then the pages go from the last one added and the phone shows the first.',
            'sv_added' => 'Added to the showcase.',
            'sv_removed' => 'Removed from the showcase.',
            'sv_not_found' => 'No page with this name.',
            'sv_not_public' => 'This page cannot be featured: it must be public and visible in Explore.',
            'sv_not_featured' => 'The phone shows a page from the showcase.',
        ],
    ];

    return $dict[$lang][$key] ?? $dict['it'][$key] ?? $key;
}

/**
 * Apre la pagina: testa (head-import, tema, bio.css), body, navbar del sito.
 * Dopo si stampa il contenuto e si chiude con bio_page_close().
 */
function bio_page_open(string $title, string $description, string $lang, string $bodyClass): void
{
    global $mysqli;

    // head-import stampa l'anteprima dei link da questi valori.
    $ogTitle = $title;
    $ogDescription = $description;
    $ogUrl = cripsum_bio_url(ltrim(strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/', '/'));
    ?>
<!DOCTYPE html>
<html lang="<?= bio_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/head-import.php'; ?>
    <meta name="theme-color" content="#0a0a0b">
    <title><?= bio_h($title) ?></title>
    <?php cripsum_theme_head(); ?>
    <link rel="stylesheet" href="<?= bio_h(cripsum_theme_asset('/assets/bio/bio.css')) ?>">
</head>
<body class="bh-body <?= bio_h($bodyClass) ?>">
    <?php
    $nsfw = (int)($_SESSION['nsfw'] ?? 0);
    $ruolo = (string)($_SESSION['ruolo'] ?? '');
    include __DIR__ . '/navbar.php';
    ?>
    <main class="bh-shell">
    <?php
}

/** Chiude la pagina: footer del sito e gli script; $config arriva a bio.js. */
function bio_page_close(string $lang, array $config = []): void
{
    ?>
    </main>
    <?php include __DIR__ . '/footer.php'; ?>
    <div class="bh-toast" data-bh-toast role="status" aria-live="polite" hidden></div>
    <script>window.BioHub = <?= json_encode($config + ['lang' => $lang], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?= bio_h(cripsum_theme_asset('/assets/bio/bio.js')) ?>" defer></script>
</body>
</html>
    <?php
}

/** Le colonne di Esplora (migration 2026_10_18_bio_esplora.sql). */
function bio_explore_ready(mysqli $mysqli): bool
{
    return auth_column_exists($mysqli, 'utenti', 'profile_discoverable')
        && auth_column_exists($mysqli, 'utenti', 'profile_featured_at');
}

/** L'ordine della vetrina e la pagina nel telefono (migration 2026_10_18_bio_vetrina.sql). */
function bio_showcase_ready(mysqli $mysqli): bool
{
    return auth_column_exists($mysqli, 'utenti', 'profile_featured_pos')
        && auth_table_exists($mysqli, 'bio_settings');
}

/**
 * Un nome per bio.cripsum.com, come lo vede il campo dell'ingresso. Le regole
 * sono quelle degli username, perche' chi lo prende da ospite lo usa come
 * username registrandosi: 'free', 'taken', 'reserved', 'invalid' o 'yours'.
 */
function bio_name_state(mysqli $mysqli, string $name, int $viewerId = 0): array
{
    $name = trim($name);
    if (!auth_is_valid_username($name)) {
        return ['state' => 'invalid'];
    }
    if (cripsum_is_reserved_name($name)) {
        return ['state' => 'reserved'];
    }

    $stmt = $mysqli->prepare("
        SELECT id, username, custom_alias
        FROM utenti
        WHERE LOWER(username) = LOWER(?) OR LOWER(custom_alias) = LOWER(?)
        LIMIT 1
    ");
    if (!$stmt) {
        return ['state' => 'invalid'];
    }
    $stmt->bind_param('ss', $name, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['state' => 'free'];
    }
    if ($viewerId > 0 && (int)$row['id'] === $viewerId) {
        return ['state' => 'yours'];
    }

    return ['state' => 'taken', 'url' => '/' . rawurlencode($name)];
}

/** Il profilo di chi e' dentro, con quello che serve a «La tua pagina». */
function bio_owner(mysqli $mysqli, int $userId): ?array
{
    $extra = bio_explore_ready($mysqli) ? ', profile_discoverable' : ', 1 AS profile_discoverable';
    $stmt = $mysqli->prepare("
        SELECT id, username, display_name, custom_alias, bio, discord_use_avatar,
               (profile_pic IS NOT NULL AND profile_pic <> '') AS has_pic,
               profile_banner_type, profile_visibility, profile_views, profile_updated_at,
               data_creazione, is_premium, accent_color{$extra}
        FROM utenti
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Cosa manca alla pagina: la stessa lista dell'editor
 * (assets/js/profile-editor/editor.js, «Primo accesso: cosa manca»), con
 * l'indirizzo in cima. Ogni voce: [chiave, fatta, area dell'editor].
 */
function bio_owner_todo(mysqli $mysqli, array $owner): array
{
    $userId = (int)$owner['id'];
    $links = 0;
    foreach (['utenti_social', 'utenti_links'] as $table) {
        if (!auth_table_exists($mysqli, $table)) {
            continue;
        }
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM {$table} WHERE utente_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $links += (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
            $stmt->close();
        }
    }

    return [
        ['todo_address', true, 'settings'],
        ['todo_photo', (int)($owner['discord_use_avatar'] ?? 0) === 1 || (int)($owner['has_pic'] ?? 0) === 1, 'profile'],
        ['todo_bio', trim((string)($owner['bio'] ?? '')) !== '', 'profile'],
        ['todo_links', $links > 0, 'profile'],
        ['todo_bg', !empty($owner['profile_banner_type']), 'appearance'],
    ];
}

function bio_pending_friend_requests(mysqli $mysqli, int $userId): int
{
    if (!auth_table_exists($mysqli, 'friendship_requests')) {
        return 0;
    }
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM friendship_requests WHERE receiver_id = ? AND status = 'pending'");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $n = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
    $stmt->close();

    return $n;
}

/**
 * Le visite ricevute giorno per giorno negli ultimi $days giorni (oggi
 * compreso), dalle statistiche del Rewind: profile_increment_views() le scrive
 * gia' in user_daily_stats.profile_views_received, una volta per visitatore e
 * sessione come il contatore totale. Niente tabella nuova.
 *
 * Restituisce ['state' => 'ok', 'days' => [Y-m-d => visite], 'since' => primo
 * giorno con statistiche o null], ['state' => 'off'] se la persona ha spento le
 * statistiche (Impostazioni, Rewind), ['state' => 'none'] se la tabella non c'e'.
 */
function bio_owner_daily_views(mysqli $mysqli, int $userId, int $days = 30): array
{
    if (!auth_table_exists($mysqli, 'user_daily_stats') || !auth_column_exists($mysqli, 'user_daily_stats', 'profile_views_received')) {
        return ['state' => 'none'];
    }

    if (auth_table_exists($mysqli, 'user_stats_prefs')) {
        $stmt = $mysqli->prepare('SELECT tracking_enabled FROM user_stats_prefs WHERE utente_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $pref = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($pref && (int)$pref['tracking_enabled'] === 0) {
                return ['state' => 'off'];
            }
        }
    }

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $series[date('Y-m-d', strtotime("-{$i} days"))] = 0;
    }
    $from = array_key_first($series);

    $stmt = $mysqli->prepare('SELECT day, profile_views_received AS views FROM user_daily_stats WHERE utente_id = ? AND day >= ?');
    if (!$stmt) {
        return ['state' => 'none'];
    }
    $stmt->bind_param('is', $userId, $from);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $day = substr((string)$row['day'], 0, 10);
        if (isset($series[$day])) {
            $series[$day] = (int)$row['views'];
        }
    }
    $stmt->close();

    // Da quando ci sono statistiche: se e' dentro la finestra, si dice.
    $since = null;
    $stmt = $mysqli->prepare('SELECT MIN(day) AS first_day FROM user_daily_stats WHERE utente_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $first = substr((string)($stmt->get_result()->fetch_assoc()['first_day'] ?? ''), 0, 10);
        $stmt->close();
        $since = $first !== '' && $first > $from ? $first : null;
    }

    return ['state' => 'ok', 'days' => $series, 'since' => $since];
}

/** «19 ott» / «Oct 19». Senza intl: i nomi dei mesi sono qui. */
function bio_short_date(string $ymd, string $lang): string
{
    $ts = strtotime($ymd);
    $month = (int)date('n', $ts) - 1;
    $it = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
    $en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    return $lang === 'it' ? date('j', $ts) . ' ' . $it[$month] : $en[$month] . ' ' . date('j', $ts);
}

/** Il valore pulito sopra il massimo, per la riga in alto del grafico: 1, 2, 5, 10, 20, 50... */
function bio_nice_max(int $max): int
{
    if ($max <= 1) {
        return 1;
    }
    $power = 10 ** (int)floor(log10($max));
    foreach ([1, 2, 5, 10] as $step) {
        if ($step * $power >= $max) {
            return $step * $power;
        }
    }

    return 10 * $power;
}

/**
 * Le visite per giorno: un istogramma a colonne, una sola serie. Colonne
 * sottili grigie col bordo arrotondato in cima, 2px di spazio fra l'una e
 * l'altra, una riga in alto col valore pulito e la base; sotto il primo giorno
 * e «oggi». Passando sopra una colonna (o con le frecce, col grafico a fuoco)
 * si vedono giorno e visite; i numeri sono anche nella tabella sotto.
 */
function bio_render_views_chart(array $data, string $lang): void
{
    $t = static fn(string $key): string => bio_t($key, $lang);

    if (($data['state'] ?? '') === 'off') {
        ?>
        <p class="bh-small"><?= sprintf(bio_h($t('v_off')), '<a href="' . bio_h(cripsum_main_url($lang . '/impostazioni')) . '">' . bio_h($t('v_off_link')) . '</a>') ?></p>
        <?php
        return;
    }

    $days = $data['days'];
    $total = array_sum($days);
    $max = max($days);
    $top = bio_nice_max($max);
    $keys = array_keys($days);
    $views = static fn(int $n): string => $n === 1 ? $t('v_one') : sprintf($t('v_many'), $n);
    ?>
    <div class="bh-views__head"><strong><?= number_format($total, 0, ',', '.') ?></strong><span><?= bio_h($t('v_total')) ?></span></div>
    <div class="bh-chart" tabindex="0" role="img" aria-label="<?= bio_h(sprintf($t('v_aria'), $total, $max)) ?>" data-bh-chart>
        <div class="bh-chart__plot">
            <span class="bh-chart__top" aria-hidden="true"><?= number_format($top, 0, ',', '.') ?></span>
            <ol class="bh-chart__bars" aria-hidden="true">
                <?php foreach ($days as $day => $count): ?>
                    <?php $label = $day === end($keys) ? $t('today') : bio_short_date($day, $lang); ?>
                    <li data-tip="<?= bio_h($views($count) . ' · ' . $label) ?>"><span style="height:<?= round($count / $top * 100, 2) ?>%"></span></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="bh-chart__x" aria-hidden="true"><span><?= bio_h(bio_short_date($keys[0], $lang)) ?></span><span><?= bio_h($t('today')) ?></span></div>
        <div class="bh-chart__tip" role="status" hidden></div>
    </div>
    <details class="bh-views__table">
        <summary><?= bio_h($t('v_table')) ?></summary>
        <table class="nx-table">
            <thead><tr><th scope="col"><?= bio_h($t('v_day')) ?></th><th scope="col"><?= bio_h($t('v_title')) ?></th></tr></thead>
            <tbody>
                <?php foreach (array_reverse($days, true) as $day => $count): ?>
                    <tr><td><?= bio_h($day === end($keys) ? $t('today') : bio_short_date($day, $lang)) ?></td><td><?= (int)$count ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>
    <?php if (!empty($data['since'])): ?>
        <p class="bh-small"><?= bio_h(sprintf($t('v_since'), bio_short_date((string)$data['since'], $lang))) ?></p>
    <?php endif; ?>
    <?php
}

/** «oggi», «ieri», «3 giorni fa». */
function bio_days_ago(?string $datetime, string $lang): array
{
    $ts = $datetime ? strtotime($datetime) : false;
    if (!$ts) {
        return [bio_t('never', $lang), ''];
    }
    $days = (int)floor((strtotime('today') - strtotime(date('Y-m-d', $ts))) / 86400);
    if ($days <= 0) {
        return [bio_t('today', $lang), ''];
    }
    if ($days === 1) {
        return [bio_t('yesterday', $lang), ''];
    }

    return [(string)$days, bio_t('days_ago', $lang)];
}

/** Un colore #rrggbb sicuro da mettere in uno style, o null. */
function bio_color(?string $value): ?string
{
    $value = trim((string)$value);

    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
}

/** Le viste di Esplora. */
const BIO_EXPLORE_VIEWS = ['vetrina', 'nuove', 'viste', 'premium', 'amici'];

/**
 * Le schede di Esplora. Solo profili pubblici (per «Amici» anche quelli che
 * gli amici possono vedere), mai privati, bannati o in cancellazione, e mai chi
 * ha spento «Mostrami in Esplora». Restituisce [righe, ce ne sono altre].
 */
function bio_explore_cards(mysqli $mysqli, string $view, string $q, int $viewerId, int $page, int $perPage = 24): array
{
    $ready = bio_explore_ready($mysqli);
    $where = ['u.isBannato = 0'];
    $types = '';
    $params = [];
    $join = '';

    if (auth_column_exists($mysqli, 'utenti', 'deletion_requested_at')) {
        $where[] = 'u.deletion_requested_at IS NULL';
    }
    if ($ready) {
        $where[] = 'u.profile_discoverable = 1';
    }

    if ($view === 'amici' && $viewerId > 0 && auth_table_exists($mysqli, 'friendships')) {
        $join = 'JOIN friendships f ON f.user_one_id = LEAST(?, u.id) AND f.user_two_id = GREATEST(?, u.id)';
        $types .= 'ii';
        $params[] = $viewerId;
        $params[] = $viewerId;
        $where[] = "u.profile_visibility IN ('public', 'logged_in', 'friends')";
        $where[] = 'u.id <> ' . (int)$viewerId;
    } else {
        $where[] = "u.profile_visibility = 'public'";
    }

    if ($view === 'vetrina') {
        if (!$ready) {
            return [[], false];
        }
        $where[] = 'u.profile_featured_at IS NOT NULL';
    }
    if ($view === 'premium') {
        $where[] = 'u.is_premium = 1';
    }

    $q = trim($q);
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(u.username LIKE ? OR u.display_name LIKE ? OR u.custom_alias LIKE ?)';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }

    // La vetrina nell'ordine dello staff; le appena aggiunte (posto NULL) in cima.
    $showcaseOrder = auth_column_exists($mysqli, 'utenti', 'profile_featured_pos')
        ? 'u.profile_featured_pos IS NOT NULL, u.profile_featured_pos ASC, u.profile_featured_at DESC'
        : 'u.profile_featured_at DESC';
    $order = match ($view) {
        'vetrina' => $showcaseOrder,
        'viste', 'premium' => 'u.profile_views DESC, u.id DESC',
        'amici' => 'u.username ASC',
        default => 'COALESCE(u.profile_updated_at, u.data_creazione) DESC, u.id DESC',
    };

    $featured = $ready ? 'u.profile_featured_at' : 'NULL AS profile_featured_at';
    $limit = $perPage + 1;
    $offset = max(0, ($page - 1) * $perPage);
    $sql = "
        SELECT u.id, u.username, u.display_name, u.custom_alias, u.accent_color, u.is_premium,
               u.profile_banner_type, u.profile_updated_at, {$featured}
        FROM utenti u
        {$join}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$order}
        LIMIT {$limit} OFFSET {$offset}
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [[], false];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $more = count($rows) > $perPage;

    return [array_slice($rows, 0, $perPage), $more];
}

/**
 * La vetrina dello staff, nel suo ordine: tutte le pagine (l'ingresso mostra
 * le prime 4) e quella nel telefono dell'ingresso, cioe' la scelta dello staff
 * finche' e' in vetrina, se no la prima.
 */
function bio_showcase(mysqli $mysqli): array
{
    [$picks] = bio_explore_cards($mysqli, 'vetrina', '', 0, 1, 48);
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $picks);

    $chosen = 0;
    if ($picks && bio_showcase_ready($mysqli)) {
        $result = $mysqli->query('SELECT hero_user_id FROM bio_settings WHERE id = 1');
        $chosen = (int)(($result ? $result->fetch_assoc() : null)['hero_user_id'] ?? 0);
    }

    return ['picks' => $picks, 'hero_id' => in_array($chosen, $ids, true) ? $chosen : ($ids[0] ?? 0)];
}

/**
 * Una pagina da mettere in vetrina dal pannello: per username o alias, anche
 * incollando il link. Restituisce [riga, chiave dell'errore]; la riga solo se
 * Esplora la mostrerebbe (pubblica, «Mostrami in Esplora», non bannata).
 */
function bio_showcase_lookup(mysqli $mysqli, string $input): array
{
    $name = rtrim((string)strtok(trim($input), '?#'), '/');
    $name = ltrim(rawurldecode((string)substr($name, (int)strrpos('/' . $name, '/'))), '@');
    if ($name === '') {
        return [null, 'sv_not_found'];
    }

    $deleting = auth_column_exists($mysqli, 'utenti', 'deletion_requested_at') ? 'deletion_requested_at' : 'NULL';
    $stmt = $mysqli->prepare("
        SELECT id, isBannato, profile_visibility, profile_discoverable, {$deleting} AS deleting
        FROM utenti
        WHERE LOWER(username) = LOWER(?) OR LOWER(custom_alias) = LOWER(?)
        ORDER BY LOWER(username) = LOWER(?) DESC
        LIMIT 1
    ");
    $stmt->bind_param('sss', $name, $name, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return [null, 'sv_not_found'];
    }
    if ((int)$row['isBannato'] !== 0 || $row['deleting'] !== null || $row['profile_visibility'] !== 'public' || (int)$row['profile_discoverable'] !== 1) {
        return [null, 'sv_not_public'];
    }

    return [$row, ''];
}

/** La gemma Premium del sito (img/premium.svg) dopo il nome, per chi e' Premium. */
function bio_premium_gem(array $row): string
{
    if ((int)($row['is_premium'] ?? 0) !== 1) {
        return '';
    }

    return '<img class="cr-premium-gem bh-gem" src="/img/premium.svg" alt="Premium" title="Premium" width="14" height="14">';
}

/** La scheda di un profilo (Esplora e vetrina dell'ingresso). */
function bio_render_card(array $row, bool $staff, string $lang): void
{
    $name = cripsum_bio_profile_name($row);
    $display = trim((string)($row['display_name'] ?? '')) ?: (string)$row['username'];
    $accent = bio_color($row['accent_color'] ?? null);
    $stamp = !empty($row['profile_updated_at']) ? (int)strtotime((string)$row['profile_updated_at']) : 0;
    $mime = strtolower((string)($row['profile_banner_type'] ?? ''));
    $banner = '/includes/get_profile_banner.php?id=' . (int)$row['id'] . '&t=' . $stamp;
    $featured = !empty($row['profile_featured_at']);
    ?>
    <li class="bh-card"<?= $accent ? ' style="--acc:' . bio_h($accent) . '"' : '' ?>>
        <a href="/<?= bio_h(rawurlencode($name)) ?>">
            <?php if (str_starts_with($mime, 'image/')): ?>
                <div class="bh-card__bg"><img src="<?= bio_h($banner) ?>" alt="" loading="lazy" decoding="async"></div>
            <?php elseif (str_starts_with($mime, 'video/')): ?>
                <?php /* Parte solo quando la scheda si vede (bio.js); fino ad allora il primo fotogramma (#t=0.1). */ ?>
                <div class="bh-card__bg"><video src="<?= bio_h($banner) ?>#t=0.1" muted loop playsinline preload="metadata" disablepictureinpicture disableremoteplayback aria-hidden="true" tabindex="-1" data-bh-video></video></div>
            <?php else: ?>
                <div class="bh-card__bg bh-card__bg--plain"></div>
            <?php endif; ?>
            <div class="bh-card__body">
                <img class="bh-card__pfp" src="<?= bio_h(profile_avatar_url($row, 128)) ?>" alt="" loading="lazy" decoding="async">
                <span class="bh-card__text">
                    <span class="bh-card__name"><span><?= bio_h($display) ?></span><?= bio_premium_gem($row) ?></span>
                    <span class="bh-card__url"><?= bio_h((string)parse_url(cripsum_bio_url(), PHP_URL_HOST)) ?>/<?= bio_h($name) ?></span>
                </span>
            </div>
        </a>
        <?php if ($staff): ?>
            <button type="button" class="bh-star<?= $featured ? ' is-on' : '' ?>" data-bh-feature="<?= (int)$row['id'] ?>" aria-pressed="<?= $featured ? 'true' : 'false' ?>"
                aria-label="<?= bio_h(bio_t($featured ? 'feature_off' : 'feature_on', $lang)) ?>" data-on="<?= bio_h(bio_t('feature_off', $lang)) ?>" data-off="<?= bio_h(bio_t('feature_on', $lang)) ?>">
                <i class="fa-<?= $featured ? 'solid' : 'regular' ?> fa-star" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
    </li>
    <?php
}
