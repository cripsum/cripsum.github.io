<?php
// Human labels for the data export, in the language the archive was requested
// in.
//
// The export used to name files and keys after the database schema
// (`utenti_profile_blocks`, `card_tag_bg`, ...). That is not a vulnerability on
// its own — the archive only ever contains the requester's own rows — but it
// hands out a free map of the database to anyone who receives a copy, and it is
// unreadable for the person it is meant for. Both problems go away by
// exporting labels instead of identifiers.

/** The two languages the site is published in. */
function account_export_lang(?string $lang): string
{
    return strtolower(trim((string)$lang)) === 'en' ? 'en' : 'it';
}

/** Tables whose every column is a credential: never exported at all. */
function account_export_skip_tables(): array
{
    return ['utenti_2fa_backup_codes', 'user_data_exports', 'login_attempts'];
}

/**
 * Columns left out of the export completely.
 *
 * Join keys carry no meaning for a person, and credentials are not merely
 * redacted but omitted: printing "Password: [removed]" tells the reader
 * nothing and still advertises which fields exist.
 */
function account_export_skip_columns(): array
{
    return [
        'id', 'utente_id', 'user_id', 'id_utente', 'csrf_token',
        'deletion_requested_at', 'deletion_scheduled_for',
        'email_verificata', 'verification_token',
    ];
}

/** Labels for the columns that point at another person. */
function account_person_field_label(string $column, string $lang = 'it'): string
{
    static $maps = [
        'it' => [
            'sender_id' => 'Da', 'mittente_id' => 'Da', 'from_user_id' => 'Da',
            'recipient_id' => 'A', 'receiver_id' => 'A', 'destinatario_id' => 'A', 'to_user_id' => 'A',
            'follower_id' => 'Chi segue', 'followed_id' => 'Seguito',
            'blocker_id' => 'Chi ha bloccato', 'blocked_id' => 'Bloccato', 'blocked_user_id' => 'Bloccato',
            'requester_id' => 'Richiesta da', 'addressee_id' => 'Richiesta a',
            'inviter_id' => 'Invitato da', 'invitee_id' => 'Invitato',
            'user_one_id' => 'Utente', 'user_two_id' => 'Con',
            'player1_id' => 'Giocatore 1', 'player2_id' => 'Giocatore 2',
            'winner_id' => 'Vincitore', 'loser_id' => 'Perdente',
            'author_id' => 'Autore', 'author_user_id' => 'Autore', 'target_author_id' => 'Autore',
            'created_by' => 'Creato da',
            'reporter_id' => 'Segnalato da', 'reporter_user_id' => 'Segnalato da', 'reported_by' => 'Segnalato da',
            'reported_id' => 'Utente segnalato', 'reported_user_id' => 'Utente segnalato',
            'target_user_id' => 'Utente', 'player_id' => 'Giocatore',
        ],
        'en' => [
            'sender_id' => 'From', 'mittente_id' => 'From', 'from_user_id' => 'From',
            'recipient_id' => 'To', 'receiver_id' => 'To', 'destinatario_id' => 'To', 'to_user_id' => 'To',
            'follower_id' => 'Follower', 'followed_id' => 'Following',
            'blocker_id' => 'Blocked by', 'blocked_id' => 'Blocked', 'blocked_user_id' => 'Blocked',
            'requester_id' => 'Requested by', 'addressee_id' => 'Requested to',
            'inviter_id' => 'Invited by', 'invitee_id' => 'Invited',
            'user_one_id' => 'User', 'user_two_id' => 'With',
            'player1_id' => 'Player 1', 'player2_id' => 'Player 2',
            'winner_id' => 'Winner', 'loser_id' => 'Loser',
            'author_id' => 'Author', 'author_user_id' => 'Author', 'target_author_id' => 'Author',
            'created_by' => 'Created by',
            'reporter_id' => 'Reported by', 'reporter_user_id' => 'Reported by', 'reported_by' => 'Reported by',
            'reported_id' => 'Reported user', 'reported_user_id' => 'Reported user',
            'target_user_id' => 'User', 'player_id' => 'Player',
        ],
    ];

    $lang = account_export_lang($lang);

    return $maps[$lang][strtolower($column)] ?? ($lang === 'en' ? 'User' : 'Utente');
}

/** Section name shown for a table. */
function account_friendly_table(string $table, string $lang = 'it'): string
{
    static $maps = [
        'it' => [
            'utenti_links' => 'Link del profilo',
            'utenti_social' => 'Social del profilo',
            'utenti_projects' => 'Progetti',
            'utenti_contents' => 'Contenuti ed edit',
            'utenti_profile_blocks' => 'Blocchi personalizzati',
            'utenti_embeds' => 'Embed del profilo',
            'utenti_presets' => 'Preset del profilo',
            'utenti_profile_badges' => 'Badge in evidenza',
            'utenti_profile_characters' => 'Personaggi in evidenza',
            'utenti_profile_activity' => 'Attivita del profilo',
            'utenti_personaggi' => 'Personaggi collezionati',
            'utenti_achievement' => 'Achievement sbloccati',
            'user_custom_badges' => 'Badge personalizzati',
            'private_messages' => 'Messaggi privati',
            'private_conversations' => 'Conversazioni private',
            'private_conversation_participants' => 'Partecipazioni alle conversazioni',
            'private_message_reactions' => 'Reazioni ai messaggi',
            'private_message_attachments' => 'Allegati dei messaggi',
            'messages' => 'Messaggi nella chat globale',
            'chat_members' => 'Chat di gruppo',
            'chat_messages' => 'Messaggi nelle chat di gruppo',
            'chat_invites' => 'Inviti alle chat',
            'chat_reports' => 'Segnalazioni nelle chat',
            'friendships' => 'Amicizie',
            'friendship_requests' => 'Richieste di amicizia',
            'blocked_users' => 'Utenti bloccati',
            'user_follows' => 'Follow',
            'shitposts' => 'Shitpost pubblicati',
            'commenti_shitpost' => 'Commenti agli shitpost',
            'shitpost_likes' => 'Like agli shitpost',
            'toprimasti' => 'Post Top Rimasti',
            'voti_toprimasti' => 'Voti Top Rimasti',
            'content_comments' => 'Commenti ai contenuti',
            'content_saves' => 'Contenuti salvati',
            'content_reports' => 'Segnalazioni inviate',
            'profile_reports' => 'Segnalazioni di profili',
            'site_tickets' => 'Ticket di supporto',
            'site_ticket_messages' => 'Messaggi nei ticket',
            'site_message_recipients' => 'Messaggi ricevuti dal sito',
            'user_sessions' => 'Dispositivi e sessioni',
            'user_missions' => 'Missioni',
            'gacha_pull_history' => 'Storico gacha',
            'game_matches' => 'Partite giocate',
            'game_match_cards' => 'Carte usate nelle partite',
            'game_player_stats' => 'Statistiche di gioco',
            'subway_leaderboard' => 'Punteggi Subway',
            'user_godos_shop_purchases' => 'Acquisti nel negozio',
            'premium_gifts' => 'Regali premium',
            'first_purchase_bonuses' => 'Bonus primo acquisto',
            'user_social_settings' => 'Preferenze social',
            'private_user_settings' => 'Preferenze della chat',
            'cripsumpedia_entries' => 'Voci Cripsumpedia',
        ],
        'en' => [
            'utenti_links' => 'Profile links',
            'utenti_social' => 'Profile socials',
            'utenti_projects' => 'Projects',
            'utenti_contents' => 'Content and edits',
            'utenti_profile_blocks' => 'Custom blocks',
            'utenti_embeds' => 'Profile embeds',
            'utenti_presets' => 'Profile presets',
            'utenti_profile_badges' => 'Featured badges',
            'utenti_profile_characters' => 'Featured characters',
            'utenti_profile_activity' => 'Profile activity',
            'utenti_personaggi' => 'Collected characters',
            'utenti_achievement' => 'Unlocked achievements',
            'user_custom_badges' => 'Custom badges',
            'private_messages' => 'Private messages',
            'private_conversations' => 'Private conversations',
            'private_conversation_participants' => 'Conversation memberships',
            'private_message_reactions' => 'Message reactions',
            'private_message_attachments' => 'Message attachments',
            'messages' => 'Global chat messages',
            'chat_members' => 'Group chats',
            'chat_messages' => 'Group chat messages',
            'chat_invites' => 'Chat invites',
            'chat_reports' => 'Chat reports',
            'friendships' => 'Friendships',
            'friendship_requests' => 'Friend requests',
            'blocked_users' => 'Blocked users',
            'user_follows' => 'Follows',
            'shitposts' => 'Published shitposts',
            'commenti_shitpost' => 'Shitpost comments',
            'shitpost_likes' => 'Shitpost likes',
            'toprimasti' => 'Top Rimasti posts',
            'voti_toprimasti' => 'Top Rimasti votes',
            'content_comments' => 'Content comments',
            'content_saves' => 'Saved content',
            'content_reports' => 'Reports you sent',
            'profile_reports' => 'Profile reports',
            'site_tickets' => 'Support tickets',
            'site_ticket_messages' => 'Ticket messages',
            'site_message_recipients' => 'Messages from the site',
            'user_sessions' => 'Devices and sessions',
            'user_missions' => 'Missions',
            'gacha_pull_history' => 'Gacha history',
            'game_matches' => 'Matches played',
            'game_match_cards' => 'Cards used in matches',
            'game_player_stats' => 'Game statistics',
            'subway_leaderboard' => 'Subway scores',
            'user_godos_shop_purchases' => 'Shop purchases',
            'premium_gifts' => 'Premium gifts',
            'first_purchase_bonuses' => 'First purchase bonuses',
            'user_social_settings' => 'Social preferences',
            'private_user_settings' => 'Chat preferences',
            'cripsumpedia_entries' => 'Cripsumpedia entries',
        ],
    ];

    $lang = account_export_lang($lang);
    $key = strtolower($table);

    return $maps[$lang][$key] ?? account_humanize($key);
}

/** Field name shown for a column. */
function account_friendly_field(string $column, string $lang = 'it'): string
{
    static $maps = [
        'it' => [
            'username' => 'Username',
            'display_name' => 'Nome visualizzato',
            'email' => 'Email',
            'bio' => 'Bio',
            'title' => 'Titolo',
            'nome' => 'Nome',
            'name' => 'Nome',
            'descrizione' => 'Descrizione',
            'description' => 'Descrizione',
            'message' => 'Messaggio',
            'messaggio' => 'Messaggio',
            'body' => 'Testo',
            'content' => 'Contenuto',
            'contenuto' => 'Contenuto',
            'url' => 'Link',
            'link' => 'Link',
            'image_url' => 'Immagine',
            'thumbnail_url' => 'Anteprima',
            'media_url' => 'File allegato',
            'icon' => 'Icona',
            'created_at' => 'Creato il',
            'updated_at' => 'Aggiornato il',
            'data_creazione' => 'Creato il',
            'sent_at' => 'Inviato il',
            'read_at' => 'Letto il',
            'deleted_at' => 'Eliminato il',
            'expires_at' => 'Scade il',
            'last_activity' => 'Ultima attivita',
            'ultimo_accesso' => 'Ultimo accesso',
            'is_visible' => 'Visibile',
            'is_featured' => 'In evidenza',
            'is_premium' => 'Premium',
            'sort_order' => 'Ordine',
            'status' => 'Stato',
            'stato' => 'Stato',
            'soldi' => 'Godos',
            'quantita' => 'Quantita',
            'ip_address' => 'Indirizzo IP',
            'user_agent' => 'Dispositivo',
            'accent_color' => 'Colore principale',
            'profile_status' => 'Stato del profilo',
            'profile_views' => 'Visite al profilo',
            'discord_id' => 'ID Discord',
            'discord_username' => 'Username Discord',
        ],
        'en' => [
            'username' => 'Username',
            'display_name' => 'Display name',
            'email' => 'Email',
            'bio' => 'Bio',
            'title' => 'Title',
            'nome' => 'Name',
            'name' => 'Name',
            'descrizione' => 'Description',
            'description' => 'Description',
            'message' => 'Message',
            'messaggio' => 'Message',
            'body' => 'Text',
            'content' => 'Content',
            'contenuto' => 'Content',
            'url' => 'Link',
            'link' => 'Link',
            'image_url' => 'Image',
            'thumbnail_url' => 'Thumbnail',
            'media_url' => 'Attached file',
            'icon' => 'Icon',
            'created_at' => 'Created on',
            'updated_at' => 'Updated on',
            'data_creazione' => 'Created on',
            'sent_at' => 'Sent on',
            'read_at' => 'Read on',
            'deleted_at' => 'Deleted on',
            'expires_at' => 'Expires on',
            'last_activity' => 'Last activity',
            'ultimo_accesso' => 'Last login',
            'is_visible' => 'Visible',
            'is_featured' => 'Featured',
            'is_premium' => 'Premium',
            'sort_order' => 'Order',
            'status' => 'Status',
            'stato' => 'Status',
            'soldi' => 'Godos',
            'quantita' => 'Quantity',
            'ip_address' => 'IP address',
            'user_agent' => 'Device',
            'accent_color' => 'Main colour',
            'profile_status' => 'Profile status',
            'profile_views' => 'Profile views',
            'discord_id' => 'Discord ID',
            'discord_username' => 'Discord username',
        ],
    ];

    $lang = account_export_lang($lang);
    $key = strtolower($column);

    return $maps[$lang][$key] ?? account_humanize($key);
}

/**
 * Fallback label for anything not in the maps: drops the internal prefixes and
 * reads as a phrase rather than as a column name.
 */
function account_humanize(string $identifier): string
{
    $text = strtolower($identifier);
    $text = preg_replace('/^(utenti|utente|user|users|site|private|chat|game|content)_/', '', $text) ?? $text;
    $text = preg_replace('/^(profile|profilo)_/', '', $text) ?? $text;
    $text = str_replace('_', ' ', $text);
    $text = preg_replace('/\bid\b/', '', $text) ?? $text;
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

    if ($text === '') {
        return 'Dato';
    }

    return ucfirst($text);
}

/** File-name slug for a section, e.g. "Profile links" -> "profile-links". */
function account_export_slug(string $label): string
{
    $slug = strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT', $label));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'dati';
}
