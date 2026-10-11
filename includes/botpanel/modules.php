<?php
declare(strict_types=1);

/**
 * I moduli del bot: quali impostazioni ha ciascuno, e come si controllano.
 *
 * La stessa descrizione serve a due cose. La pagina la usa per disegnare i
 * campi (un canale diventa un menu dei canali, un elenco di ruoli diventa le
 * etichette dei ruoli); il salvataggio la usa per controllare quello che
 * arriva. Un campo che qui non c'e' non viene salvato, qualunque cosa mandi
 * il browser.
 *
 * Tipi di campo:
 *   bool                       interruttore
 *   int      min, max          numero intero
 *   text     max, multiline    testo
 *   choice   options           una scelta fra valori fissi (valore => etichetta)
 *   color                      colore esadecimale, o vuoto
 *   channel  kinds, need       un canale del server; `need` e' cio' che il bot
 *                              deve poterci fare (send, embed, private_threads…)
 *   roles    max, assign       ruoli del server; con `assign` sono ruoli che il
 *                              bot dara' a qualcuno, e valgono regole piu' strette
 *   users    max               ID di utenti Discord
 *   list     max, item         elenco di oggetti, ognuno con i suoi campi
 *
 * Su qualsiasi campo: `required` (obbligatorio quando il modulo e' acceso) e
 * `home` (esiste solo nel server di Cripsum).
 */

/**
 * @return array{it: string, en: string}
 */
function botpanel_t(string $it, string $en): array
{
    return ['it' => $it, 'en' => $en];
}

/**
 * @return array<string, array<string, mixed>>
 */
function botpanel_modules(): array
{
    $channelText = ['text', 'announcement'];

    return [
        'general' => [
            'icon' => 'fa-user-shield',
            'toggle' => false,
            'ready' => true,
            'name' => botpanel_t('Staff', 'Staff'),
            'lead' => botpanel_t(
                'Chi può usare i comandi di staff di Poppy in questo server.',
                'Who can use Poppy\'s staff commands in this server.'
            ),
            'fields' => [
                'mod_role_ids' => [
                    'type' => 'roles',
                    'max' => 10,
                    'label' => botpanel_t('Chi può moderare', 'Who can moderate'),
                    'hint' => botpanel_t(
                        'Oltre a chi ha già i permessi di Discord per farlo.',
                        'On top of whoever already has the Discord permissions for it.'
                    ),
                ],
                'admin_role_ids' => [
                    'type' => 'roles',
                    'max' => 10,
                    'label' => botpanel_t('Chi può cambiare cosa scrive Poppy', 'Who can change what Poppy posts'),
                    'hint' => botpanel_t(
                        'Benvenuto, embed e pannelli. Oltre a chi ha «Gestisci server».',
                        'Welcome, embeds and panels. On top of whoever has "Manage Server".'
                    ),
                ],
            ],
        ],

        'moderation' => [
            'icon' => 'fa-shield-halved',
            'toggle' => true,
            'default_enabled' => true,
            'ready' => true,
            'name' => botpanel_t('Moderazione', 'Moderation'),
            'lead' => botpanel_t(
                'I comandi /mod funzionano da subito per chi ha già i permessi su Discord. Qui decidi dove resta traccia e cosa succede da solo.',
                'The /mod commands work right away for anyone who already has the Discord permissions. Here you decide where the trail goes and what happens on its own.'
            ),
            'fields' => [
                'log_channel_id' => [
                    'type' => 'channel',
                    'kinds' => $channelText,
                    'need' => ['send', 'embed'],
                    'label' => botpanel_t('Canale dei log', 'Log channel'),
                    'hint' => botpanel_t(
                        'Ogni azione finisce qui, con chi l\'ha fatta e perché.',
                        'Every action lands here, with who did it and why.'
                    ),
                ],
                'dm_notice' => [
                    'type' => 'bool',
                    'default' => true,
                    'label' => botpanel_t('Avvisa in privato', 'Notify in DMs'),
                    'hint' => botpanel_t(
                        'Il membro riceve un messaggio con il motivo prima della sanzione.',
                        'The member gets a message with the reason before the action.'
                    ),
                ],
                'warn_expiry_days' => [
                    'type' => 'choice',
                    'default' => 0,
                    'options' => [
                        0 => botpanel_t('Mai', 'Never'),
                        30 => botpanel_t('Dopo 30 giorni', 'After 30 days'),
                        90 => botpanel_t('Dopo 90 giorni', 'After 90 days'),
                        180 => botpanel_t('Dopo 6 mesi', 'After 6 months'),
                        365 => botpanel_t('Dopo un anno', 'After a year'),
                    ],
                    'label' => botpanel_t('I warn scadono', 'Warnings expire'),
                    'hint' => botpanel_t(
                        'Un warn scaduto resta nello storico ma non conta più per le sanzioni automatiche.',
                        'An expired warning stays in the history but no longer counts toward automatic actions.'
                    ),
                ],
                'thresholds' => [
                    'type' => 'list',
                    'max' => 5,
                    'label' => botpanel_t('Sanzioni automatiche', 'Automatic actions'),
                    'hint' => botpanel_t(
                        'Quando i warn attivi di un membro arrivano a una soglia, Poppy applica la sanzione da solo e lo scrive nei log.',
                        'When a member\'s active warnings reach a threshold, Poppy applies the action on its own and logs it.'
                    ),
                    'add' => botpanel_t('Aggiungi soglia', 'Add threshold'),
                    'item' => [
                        'count' => [
                            'type' => 'int',
                            'min' => 2,
                            'max' => 20,
                            'default' => 3,
                            'label' => botpanel_t('Warn attivi', 'Active warnings'),
                        ],
                        'action' => [
                            'type' => 'choice',
                            'default' => 'timeout',
                            'options' => [
                                'timeout' => botpanel_t('Timeout', 'Timeout'),
                                'kick' => botpanel_t('Espulsione', 'Kick'),
                                'ban' => botpanel_t('Ban', 'Ban'),
                            ],
                            'label' => botpanel_t('Sanzione', 'Action'),
                        ],
                        'duration' => [
                            'type' => 'choice',
                            'default' => 3600,
                            'options' => [
                                600 => botpanel_t('10 minuti', '10 minutes'),
                                3600 => botpanel_t('1 ora', '1 hour'),
                                86400 => botpanel_t('1 giorno', '1 day'),
                                604800 => botpanel_t('1 settimana', '1 week'),
                            ],
                            'label' => botpanel_t('Durata del timeout', 'Timeout length'),
                        ],
                    ],
                ],
            ],
        ],

        'messages' => [
            'icon' => 'fa-message',
            'toggle' => true,
            'default_enabled' => false,
            'ready' => true,
            'name' => botpanel_t('Messaggi', 'Messages'),
            'lead' => botpanel_t(
                'Quello che Poppy scrive da solo quando succede qualcosa, e i messaggi fissi che pubblichi tu, come le regole.',
                'What Poppy writes on its own when something happens, and the fixed messages you post yourself, like the rules.'
            ),
            'fields' => [
                'autorole_ids' => [
                    'type' => 'roles',
                    'max' => 5,
                    'assign' => true,
                    'label' => botpanel_t('Ruoli dati all\'ingresso', 'Roles given on join'),
                    'hint' => botpanel_t(
                        'Poppy li assegna a chi entra. Solo ruoli sotto il suo e senza permessi di moderazione.',
                        'Poppy gives them to whoever joins. Only roles below its own and without moderation permissions.'
                    ),
                ],
            ],
        ],

        'tickets' => [
            'icon' => 'fa-ticket',
            'toggle' => true,
            'default_enabled' => false,
            'ready' => true,
            'name' => botpanel_t('Ticket', 'Tickets'),
            'lead' => botpanel_t(
                'Chi apre un ticket ottiene una conversazione privata con lo staff. Le categorie decidono chi risponde e cosa viene chiesto all\'apertura.',
                'Whoever opens a ticket gets a private conversation with the staff. Categories decide who answers and what is asked up front.'
            ),
            'fields' => [
                'panel_channel_id' => [
                    'type' => 'channel',
                    'kinds' => ['text'],
                    'need' => ['send', 'embed', 'private_threads', 'send_in_threads', 'manage_threads'],
                    'required' => true,
                    'label' => botpanel_t('Canale dei ticket', 'Ticket channel'),
                    'hint' => botpanel_t(
                        'Qui sta il pannello, e ogni ticket nasce come thread privato di questo canale.',
                        'The panel lives here, and each ticket is born as a private thread of this channel.'
                    ),
                ],
                'log_channel_id' => [
                    'type' => 'channel',
                    'kinds' => $channelText,
                    'need' => ['send', 'embed', 'attach'],
                    'label' => botpanel_t('Canale dei log', 'Log channel'),
                    'hint' => botpanel_t(
                        'Aperture, chiusure e la trascrizione di ogni ticket chiuso.',
                        'Openings, closings and the transcript of every closed ticket.'
                    ),
                ],
                'staff_role_ids' => [
                    'type' => 'roles',
                    'max' => 10,
                    'label' => botpanel_t('Chi risponde ai ticket', 'Who answers tickets'),
                    'hint' => botpanel_t(
                        'Vale per le categorie che non hanno ruoli propri. Senza nessun ruolo rispondono i moderatori.',
                        'Applies to categories without roles of their own. With no role at all, moderators answer.'
                    ),
                ],
                'panel_title' => [
                    'type' => 'text',
                    'max' => 100,
                    'default' => 'Support',
                    'label' => botpanel_t('Titolo del pannello', 'Panel title'),
                ],
                'panel_description' => [
                    'type' => 'text',
                    'max' => 1000,
                    'multiline' => true,
                    'default' => 'Need help? Pick a topic below and a private thread opens, visible only to you and the staff.',
                    'label' => botpanel_t('Testo del pannello', 'Panel text'),
                ],
                'panel_color' => [
                    'type' => 'color',
                    'default' => '#8b5cf6',
                    'label' => botpanel_t('Colore', 'Colour'),
                ],
                'max_open' => [
                    'type' => 'int',
                    'min' => 1,
                    'max' => 10,
                    'default' => 3,
                    'label' => botpanel_t('Ticket aperti per persona', 'Open tickets per person'),
                ],
                'auto_close_hours' => [
                    'type' => 'choice',
                    'default' => 0,
                    'options' => [
                        0 => botpanel_t('Mai', 'Never'),
                        24 => botpanel_t('Dopo 1 giorno di silenzio', 'After 1 day of silence'),
                        72 => botpanel_t('Dopo 3 giorni di silenzio', 'After 3 days of silence'),
                        168 => botpanel_t('Dopo una settimana di silenzio', 'After a week of silence'),
                    ],
                    'label' => botpanel_t('Chiusura automatica', 'Automatic closing'),
                ],
                'rating' => [
                    'type' => 'bool',
                    'default' => false,
                    'label' => botpanel_t('Chiedi un voto alla chiusura', 'Ask for a rating on close'),
                    'hint' => botpanel_t(
                        'Chi ha aperto il ticket può dare da 1 a 5 stelle.',
                        'Whoever opened the ticket can give 1 to 5 stars.'
                    ),
                ],
                'categories' => [
                    'type' => 'list',
                    'max' => 25,
                    'keyed' => true,
                    'label' => botpanel_t('Categorie', 'Categories'),
                    'add' => botpanel_t('Nuova categoria', 'New category'),
                    'item' => [
                        'emoji' => [
                            'type' => 'text',
                            'max' => 32,
                            'default' => '💬',
                            'label' => botpanel_t('Emoji', 'Emoji'),
                        ],
                        'label' => [
                            'type' => 'text',
                            'max' => 80,
                            'required' => true,
                            'label' => botpanel_t('Nome', 'Name'),
                        ],
                        'description' => [
                            'type' => 'text',
                            'max' => 100,
                            'label' => botpanel_t('Descrizione nel menu', 'Description in the menu'),
                        ],
                        'staff_role_ids' => [
                            'type' => 'roles',
                            'max' => 5,
                            'label' => botpanel_t('Chi risponde', 'Who answers'),
                        ],
                        'site_topic' => [
                            'type' => 'choice',
                            'home' => true,
                            'default' => '',
                            'options' => [
                                '' => botpanel_t('No, resta solo su Discord', 'No, it stays on Discord only'),
                                'Segnalazione Bug' => botpanel_t('Sì, come «Segnalazione Bug»', 'Yes, as "Bug report"'),
                                'Problema Account' => botpanel_t('Sì, come «Problema Account»', 'Yes, as "Account issue"'),
                                'Segnalazione Utente' => botpanel_t('Sì, come «Segnalazione Utente»', 'Yes, as "Report a user"'),
                                'Altro' => botpanel_t('Sì, come «Altro»', 'Yes, as "Something else"'),
                            ],
                            'label' => botpanel_t('Collega al supporto del sito', 'Link to the website support'),
                            'hint' => botpanel_t(
                                'Il ticket compare anche nell\'area supporto del sito, con il suo codice TK, e le risposte viaggiano nei due sensi.',
                                'The ticket also shows up in the website support area, with its TK code, and replies travel both ways.'
                            ),
                        ],
                        'questions' => [
                            'type' => 'list',
                            'max' => 5,
                            'label' => botpanel_t('Domande all\'apertura', 'Questions on opening'),
                            'hint' => botpanel_t(
                                'Chi apre il ticket le compila prima di entrare. Fino a 5.',
                                'Whoever opens the ticket fills them in before entering. Up to 5.'
                            ),
                            'add' => botpanel_t('Aggiungi domanda', 'Add question'),
                            'item' => [
                                'label' => [
                                    'type' => 'text',
                                    'max' => 45,
                                    'required' => true,
                                    'label' => botpanel_t('Domanda', 'Question'),
                                ],
                                'style' => [
                                    'type' => 'choice',
                                    'default' => 'short',
                                    'options' => [
                                        'short' => botpanel_t('Riga singola', 'Single line'),
                                        'paragraph' => botpanel_t('Testo lungo', 'Long text'),
                                    ],
                                    'label' => botpanel_t('Tipo', 'Type'),
                                ],
                            ],
                        ],
                    ],
                ],
                'replies' => [
                    'type' => 'list',
                    'max' => 15,
                    'label' => botpanel_t('Risposte pronte', 'Ready-made replies'),
                    'hint' => botpanel_t(
                        'Lo staff le richiama in un ticket con /ticket reply, invece di riscrivere ogni volta la stessa cosa.',
                        'The staff calls them up in a ticket with /ticket reply, instead of typing the same thing every time.'
                    ),
                    'add' => botpanel_t('Aggiungi risposta', 'Add reply'),
                    'item' => [
                        'name' => [
                            'type' => 'text',
                            'max' => 40,
                            'required' => true,
                            'label' => botpanel_t('Nome', 'Name'),
                        ],
                        'text' => [
                            'type' => 'text',
                            'max' => 1500,
                            'multiline' => true,
                            'required' => true,
                            'label' => botpanel_t('Testo', 'Text'),
                        ],
                    ],
                ],
            ],
        ],

        'giveaway' => [
            'icon' => 'fa-gift',
            'toggle' => true,
            'default_enabled' => false,
            'ready' => true,
            'name' => botpanel_t('Giveaway', 'Giveaways'),
            'lead' => botpanel_t(
                'Poppy pubblica l\'annuncio, raccoglie le partecipazioni ed estrae alla scadenza.',
                'Poppy posts the announcement, collects the entries and draws when time is up.'
            ),
            'fields' => [
                'manager_role_ids' => [
                    'type' => 'roles',
                    'max' => 5,
                    'label' => botpanel_t('Chi può lanciare un giveaway', 'Who can start a giveaway'),
                    'hint' => botpanel_t(
                        'Oltre allo staff e a chi ha «Gestisci server».',
                        'On top of the staff and whoever has "Manage Server".'
                    ),
                ],
                'ping_role_ids' => [
                    'type' => 'roles',
                    'max' => 1,
                    'label' => botpanel_t('Ruolo da avvisare', 'Role to notify'),
                    'hint' => botpanel_t(
                        'Poppy lo menziona quando parte un giveaway. Vuoto: nessuna menzione.',
                        'Poppy mentions it when a giveaway starts. Empty: no mention.'
                    ),
                ],
                'color' => [
                    'type' => 'color',
                    'default' => '#ec4899',
                    'label' => botpanel_t('Colore dell\'annuncio', 'Announcement colour'),
                ],
            ],
        ],

        'restricted' => [
            'icon' => 'fa-lock',
            'toggle' => true,
            'default_enabled' => false,
            'ready' => true,
            'name' => botpanel_t('Canali riservati', 'Restricted channels'),
            'lead' => botpanel_t(
                'Canali in cui scrivono solo le persone scelte. Il messaggio di chiunque altro viene cancellato subito e conservato nei log.',
                'Channels where only the chosen people can write. Anyone else\'s message is deleted at once and kept in the log.'
            ),
            'fields' => [
                'log_channel_id' => [
                    'type' => 'channel',
                    'kinds' => $channelText,
                    'need' => ['send', 'embed', 'attach'],
                    'label' => botpanel_t('Canale dei log', 'Log channel'),
                    'hint' => botpanel_t(
                        'Dove finiscono i messaggi cancellati, con autore, testo e allegati.',
                        'Where deleted messages end up, with author, text and attachments.'
                    ),
                ],
                'channels' => [
                    'type' => 'list',
                    'max' => 10,
                    'label' => botpanel_t('Canali', 'Channels'),
                    'add' => botpanel_t('Aggiungi canale', 'Add channel'),
                    'item' => [
                        'channel_id' => [
                            'type' => 'channel',
                            'kinds' => $channelText,
                            'need' => ['manage_messages'],
                            'required' => true,
                            'label' => botpanel_t('Canale', 'Channel'),
                        ],
                        'role_ids' => [
                            'type' => 'roles',
                            'max' => 10,
                            'label' => botpanel_t('Ruoli che possono scrivere', 'Roles that can write'),
                        ],
                        'user_ids' => [
                            'type' => 'users',
                            'max' => 25,
                            'label' => botpanel_t('Persone che possono scrivere', 'People who can write'),
                            'hint' => botpanel_t('ID Discord, uno per riga.', 'Discord IDs, one per line.'),
                        ],
                    ],
                ],
            ],
        ],

        'logs' => [
            'icon' => 'fa-clock-rotate-left',
            'toggle' => true,
            'default_enabled' => false,
            'ready' => true,
            'name' => botpanel_t('Log del server', 'Server log'),
            'lead' => botpanel_t(
                'Tiene traccia di quello che succede nel server in un canale che scegli tu.',
                'Keeps track of what happens in the server, in a channel you choose.'
            ),
            'fields' => [
                'channel_id' => [
                    'type' => 'channel',
                    'kinds' => $channelText,
                    'need' => ['send', 'embed'],
                    'required' => true,
                    'label' => botpanel_t('Canale', 'Channel'),
                ],
                'message_delete' => [
                    'type' => 'bool',
                    'default' => true,
                    'label' => botpanel_t('Messaggi cancellati', 'Deleted messages'),
                ],
                'message_edit' => [
                    'type' => 'bool',
                    'default' => true,
                    'label' => botpanel_t('Messaggi modificati', 'Edited messages'),
                ],
                'member_join' => [
                    'type' => 'bool',
                    'default' => true,
                    'label' => botpanel_t('Ingressi', 'Joins'),
                ],
                'member_leave' => [
                    'type' => 'bool',
                    'default' => true,
                    'label' => botpanel_t('Uscite', 'Leaves'),
                ],
                'role_change' => [
                    'type' => 'bool',
                    'default' => false,
                    'label' => botpanel_t('Cambi di ruolo', 'Role changes'),
                ],
            ],
        ],

        'voice' => [
            'icon' => 'fa-volume-high',
            'toggle' => true,
            'default_enabled' => false,
            'ready' => true,
            'name' => botpanel_t('Stanze vocali', 'Voice rooms'),
            'lead' => botpanel_t(
                'Chi entra nel canale d\'ingresso si ritrova in una stanza vocale tutta sua, che sparisce quando resta vuota.',
                'Whoever joins the entry channel lands in a voice room of their own, which disappears once it is empty.'
            ),
            'fields' => [
                'hub_channel_id' => [
                    'type' => 'channel',
                    'kinds' => ['voice'],
                    'need' => ['manage_channel', 'move_members'],
                    'required' => true,
                    'label' => botpanel_t('Canale d\'ingresso', 'Entry channel'),
                    'hint' => botpanel_t(
                        'Un canale vocale che serve solo da porta: le stanze nascono nella sua stessa categoria, con gli stessi permessi.',
                        'A voice channel that only works as a door: rooms are created in its category, with the same permissions.'
                    ),
                ],
                'name' => [
                    'type' => 'text',
                    'max' => 60,
                    'default' => '{user}\'s room',
                    'label' => botpanel_t('Nome delle stanze', 'Room name'),
                    'hint' => botpanel_t('{user} diventa il nome di chi la apre.', '{user} becomes the name of whoever opens it.'),
                ],
                'user_limit' => [
                    'type' => 'int',
                    'min' => 0,
                    'max' => 99,
                    'default' => 0,
                    'label' => botpanel_t('Posti per stanza', 'Seats per room'),
                    'hint' => botpanel_t('0 = senza limite. Chi apre la stanza può cambiarlo.', '0 = no limit. Whoever opens the room can change it.'),
                ],
            ],
        ],

        'reports' => [
            'icon' => 'fa-flag',
            'toggle' => true,
            'default_enabled' => false,
            'ready' => true,
            'name' => botpanel_t('Segnalazioni', 'Reports'),
            'lead' => botpanel_t(
                'Chiunque può segnalare un messaggio con il tasto destro: arriva allo staff con i pulsanti per gestirlo.',
                'Anyone can report a message with a right click: it reaches the staff with buttons to handle it.'
            ),
            'fields' => [
                'channel_id' => [
                    'type' => 'channel',
                    'kinds' => $channelText,
                    'need' => ['send', 'embed'],
                    'required' => true,
                    'label' => botpanel_t('Canale dello staff', 'Staff channel'),
                    'hint' => botpanel_t(
                        'Dove arrivano le segnalazioni. Sceglilo visibile solo allo staff.',
                        'Where reports arrive. Pick one only the staff can see.'
                    ),
                ],
            ],
        ],
    ];
}

/**
 * I campi come li vede un server: quelli che esistono solo nel server di
 * Cripsum (`home`) negli altri non compaiono, a nessun livello.
 *
 * @param array<string, array<string, mixed>> $fields
 * @return array<string, array<string, mixed>>
 */
function botpanel_fields_for(array $fields, bool $isHome): array
{
    $out = [];

    foreach ($fields as $key => $spec) {
        if (!empty($spec['home']) && !$isHome) {
            continue;
        }

        if (($spec['type'] ?? '') === 'list' && is_array($spec['item'] ?? null)) {
            $spec['item'] = botpanel_fields_for($spec['item'], $isHome);
        }

        $out[$key] = $spec;
    }

    return $out;
}

/**
 * Valore di partenza di un campo.
 *
 * @param array<string, mixed> $spec
 * @return mixed
 */
function botpanel_field_default(array $spec)
{
    if (array_key_exists('default', $spec)) {
        return $spec['default'];
    }

    switch ($spec['type']) {
        case 'bool':
            return false;
        case 'int':
            return (int)($spec['min'] ?? 0);
        case 'roles':
        case 'users':
        case 'list':
            return [];
        case 'choice':
            return array_key_first($spec['options']);
        default:
            return '';
    }
}

/**
 * Impostazioni di un modulo con i valori di partenza al posto di quelli
 * mancanti: e' quello che la pagina mostra.
 *
 * @param array<string, array<string, mixed>> $fields
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function botpanel_with_defaults(array $fields, array $config): array
{
    $out = [];

    foreach ($fields as $key => $spec) {
        $value = array_key_exists($key, $config) ? $config[$key] : botpanel_field_default($spec);

        if ($spec['type'] === 'list' && is_array($value)) {
            $value = array_values(array_map(
                static fn($item): array => (is_array($item) && isset($item['id']) ? ['id' => (string)$item['id']] : [])
                    + botpanel_with_defaults($spec['item'], is_array($item) ? $item : []),
                $value
            ));
        }

        $out[$key] = $value;
    }

    return $out;
}

/**
 * Controlla un gruppo di campi. Restituisce i valori puliti; gli errori si
 * accumulano in `$errors` come [percorso => codice].
 *
 * `$ctx`: channels e roles (id => dati, dal bot), access (di chi salva),
 * is_home, enabled (il modulo e' acceso?), lenient (un canale o un ruolo che
 * non esiste viene tolto invece di essere un errore: serve a copiare le
 * impostazioni da un altro server).
 *
 * @param array<string, array<string, mixed>> $fields
 * @param mixed $input
 * @param array<string, mixed> $ctx
 * @param array<string, string> $errors
 * @return array<string, mixed>
 */
function botpanel_validate_fields(array $fields, $input, array $ctx, array &$errors, string $path = ''): array
{
    $input = is_array($input) ? $input : [];
    $out = [];

    foreach ($fields as $key => $spec) {
        $at = $path === '' ? $key : $path . '.' . $key;
        $raw = $input[$key] ?? null;

        // Un campo che esiste solo a casa, fuori casa torna al valore di partenza.
        if (!empty($spec['home']) && empty($ctx['is_home'])) {
            $out[$key] = botpanel_field_default($spec);
            continue;
        }

        $required = !empty($spec['required']) && !empty($ctx['enabled']);

        switch ($spec['type']) {
            case 'bool':
                $out[$key] = $raw === null ? (bool)botpanel_field_default($spec) : (bool)$raw;
                break;

            case 'int':
                if ($raw === null || $raw === '') {
                    $out[$key] = (int)botpanel_field_default($spec);
                    break;
                }
                if (!is_numeric($raw)) {
                    $errors[$at] = 'number';
                    break;
                }
                $value = (int)$raw;
                if ($value < (int)$spec['min'] || $value > (int)$spec['max']) {
                    $errors[$at] = 'range';
                    break;
                }
                $out[$key] = $value;
                break;

            case 'text':
                $value = is_scalar($raw) ? (string)$raw : '';
                // Niente caratteri di controllo; gli a capo solo dove hanno senso.
                $value = (string)preg_replace(
                    !empty($spec['multiline']) ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u',
                    '',
                    $value
                );
                $value = trim($value);
                if (mb_strlen($value) > (int)$spec['max']) {
                    $errors[$at] = 'too_long';
                    break;
                }
                if ($value === '' && $raw === null && isset($spec['default'])) {
                    $value = (string)$spec['default'];
                }
                if ($value === '' && $required) {
                    $errors[$at] = 'required';
                    break;
                }
                $out[$key] = $value;
                break;

            case 'choice':
                $default = botpanel_field_default($spec);
                if ($raw === null || $raw === '') {
                    $out[$key] = $default;
                    break;
                }
                $match = null;
                foreach (array_keys($spec['options']) as $option) {
                    if ((string)$option === (string)$raw) {
                        $match = $option;
                        break;
                    }
                }
                if ($match === null) {
                    $errors[$at] = 'choice';
                    break;
                }
                $out[$key] = $match;
                break;

            case 'color':
                $value = is_string($raw) ? strtolower(trim($raw)) : '';
                if ($value === '' && $raw === null) {
                    $value = (string)botpanel_field_default($spec);
                }
                if ($value !== '' && !preg_match('/^#[0-9a-f]{6}$/', $value)) {
                    $errors[$at] = 'color';
                    break;
                }
                $out[$key] = $value;
                break;

            case 'channel':
                $value = is_string($raw) ? trim($raw) : '';
                if ($value === '') {
                    if ($required) {
                        $errors[$at] = 'required';
                    }
                    $out[$key] = '';
                    break;
                }
                $channel = $ctx['channels'][$value] ?? null;
                $kinds = $spec['kinds'] ?? ['text', 'announcement'];
                // Un canale che il bot non ha elencato non e' di questo server.
                if (!botpanel_is_snowflake($value) || !$channel || !in_array($channel['kind'] ?? '', $kinds, true)) {
                    if (!empty($ctx['lenient'])) {
                        $out[$key] = '';
                        break;
                    }
                    $errors[$at] = 'channel';
                    break;
                }
                foreach ((array)($spec['need'] ?? []) as $ability) {
                    if (empty($channel['bot'][$ability]) || empty($channel['bot']['view'])) {
                        $errors[$at] = 'bot_cannot:' . $ability;
                        break 2;
                    }
                }
                $out[$key] = $value;
                break;

            case 'roles':
                $ids = [];
                foreach (is_array($raw) ? $raw : [] as $index => $id) {
                    $id = is_string($id) ? trim($id) : '';
                    $role = $ctx['roles'][$id] ?? null;

                    if (!botpanel_is_snowflake($id) || !$role) {
                        if (!empty($ctx['lenient'])) {
                            continue;
                        }
                        $errors[$at] = 'role';
                        break 2;
                    }

                    // Un ruolo che il bot assegna da solo: non puo' essere
                    // piu' alto del suo, ne' dare poteri di moderazione, ne'
                    // stare sopra chi lo sta configurando — altrimenti chi ha
                    // solo «Gestisci server» si farebbe dare dal bot un ruolo
                    // che Discord non gli lascerebbe prendere.
                    if (!empty($spec['assign'])) {
                        $access = (array)($ctx['access'] ?? []);
                        $aboveActor = empty($access['owner'])
                            && (int)($role['position'] ?? 0) >= (int)($access['top_role_position'] ?? 0);

                        if (empty($role['assignable']) || !empty($role['dangerous']) || $aboveActor) {
                            if (!empty($ctx['lenient'])) {
                                continue;
                            }
                            $errors[$at] = empty($role['assignable'])
                                ? 'role_too_high'
                                : (!empty($role['dangerous']) ? 'role_dangerous' : 'role_above_you');
                            break 2;
                        }
                    }

                    $ids[$id] = true;
                }
                if (count($ids) > (int)$spec['max']) {
                    $errors[$at] = 'too_many';
                    break;
                }
                $out[$key] = array_map('strval', array_keys($ids));
                break;

            case 'users':
                $ids = [];
                foreach (is_array($raw) ? $raw : [] as $id) {
                    $id = is_string($id) ? trim($id) : '';
                    if ($id === '') {
                        continue;
                    }
                    if (!botpanel_is_snowflake($id)) {
                        $errors[$at] = 'user';
                        break 2;
                    }
                    $ids[$id] = true;
                }
                if (count($ids) > (int)$spec['max']) {
                    $errors[$at] = 'too_many';
                    break;
                }
                $out[$key] = array_map('strval', array_keys($ids));
                break;

            case 'list':
                $items = is_array($raw) ? array_values($raw) : [];
                if (count($items) > (int)$spec['max']) {
                    $errors[$at] = 'too_many';
                    break;
                }
                $clean = [];
                $seen = [];
                foreach ($items as $index => $item) {
                    $entry = botpanel_validate_fields($spec['item'], $item, $ctx, $errors, $at . '.' . $index);

                    // Le voci con un nome (le categorie dei ticket) tengono
                    // un identificativo che non cambia quando si rinominano:
                    // e' quello scritto nei bottoni gia' pubblicati.
                    if (!empty($spec['keyed'])) {
                        $id = is_array($item) && is_string($item['id'] ?? null) && preg_match('/^[a-z0-9]{6,12}$/', $item['id'])
                            ? $item['id']
                            : '';
                        if ($id === '' || isset($seen[$id])) {
                            $id = substr(bin2hex(random_bytes(6)), 0, 8);
                        }
                        $seen[$id] = true;
                        $entry = ['id' => $id] + $entry;
                    }

                    $clean[] = $entry;
                }
                $out[$key] = $clean;
                break;

            default:
                // Un tipo sconosciuto non salva niente.
                break;
        }
    }

    return $out;
}

/**
 * Controlla le impostazioni di un modulo.
 *
 * @param mixed $input
 * @param array<string, mixed> $ctx
 * @return array{ok: bool, config: array<string, mixed>, errors: array<string, string>}
 */
function botpanel_validate_module(string $module, $input, array $ctx): array
{
    $definition = botpanel_modules()[$module] ?? null;

    if ($definition === null || empty($definition['ready'])) {
        return ['ok' => false, 'config' => [], 'errors' => ['' => 'unknown_module']];
    }

    $errors = [];
    $config = botpanel_validate_fields($definition['fields'], $input, $ctx, $errors);

    // Controlli che riguardano piu' campi insieme.
    if ($module === 'moderation' && $errors === []) {
        $counts = array_column($config['thresholds'] ?? [], 'count');
        if (count($counts) !== count(array_unique($counts))) {
            $errors['thresholds'] = 'duplicate';
        }
    }

    if ($module === 'restricted' && $errors === []) {
        $channels = array_column($config['channels'] ?? [], 'channel_id');
        if (count($channels) !== count(array_unique($channels))) {
            $errors['channels'] = 'duplicate';
        }
    }

    if ($module === 'tickets' && $errors === [] && !empty($ctx['enabled']) && empty($config['categories'])) {
        $errors['categories'] = 'required';
    }

    if ($module === 'tickets' && $errors === []) {
        $names = array_map('mb_strtolower', array_column($config['replies'] ?? [], 'name'));
        if (count($names) !== count(array_unique($names))) {
            $errors['replies'] = 'duplicate';
        }
    }

    return ['ok' => $errors === [], 'config' => $config, 'errors' => $errors];
}

/**
 * Canali e ruoli del bot, indicizzati per ID: e' il contesto dei controlli.
 *
 * @param array<string, mixed> $meta
 * @param array<string, mixed> $access
 * @return array<string, mixed>
 */
function botpanel_validation_context(array $meta, array $access, bool $enabled, bool $lenient = false): array
{
    $channels = [];
    foreach ((array)($meta['channels'] ?? []) as $channel) {
        if (is_array($channel) && isset($channel['id'])) {
            $channels[(string)$channel['id']] = $channel;
        }
    }

    $roles = [];
    foreach ((array)($meta['roles'] ?? []) as $role) {
        if (is_array($role) && isset($role['id'])) {
            $roles[(string)$role['id']] = $role;
        }
    }

    return [
        'channels' => $channels,
        'roles' => $roles,
        'access' => $access,
        'is_home' => !empty($access['is_home']),
        'enabled' => $enabled,
        'lenient' => $lenient,
    ];
}
