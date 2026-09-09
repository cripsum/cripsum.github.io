<?php

/**
 * Testi del centro messaggi.
 *
 * La pagina esisteva in due copie, it/inbox.php e en/inbox.php, identiche
 * salvo 237 righe di stringhe: stesso markup, stesso JavaScript, tradotti a
 * mano due volte. Qui i testi stanno in un posto solo e la pagina e' una.
 */

if (!function_exists('inbox_strings')) {

    function inbox_strings(string $lang): array
    {
        $strings = [
            'it' => [
                // ── testata e navigazione ──────────────────────────
                'sec_messages'    => 'Messaggi',
                'sec_tickets'     => 'Ticket',
                'confirm_delete'  => 'Confermi?',
                'sending'         => 'Invio...',
                'cat_changelog'   => 'Novità',
                'cat_social'      => 'Social',
                'cat_ticket'      => 'Ticket',
                'filter_by'       => 'Filtra per categoria',
                'no_messages'     => 'Non hai ancora messaggi',
                'no_tickets'      => 'Non hai ticket di assistenza',
                'page_title'      => 'Centro Messaggi',
                'og_description'  => 'Centro Messaggi di Cripsum™. Controlla le tue notifiche, rispondi ai ticket di supporto e riscatta i tuoi premi.',
                'aside_label'     => 'Categorie messaggi',
                'messages'        => 'Messaggi',
                'cat_all'         => 'Tutti i messaggi',
                'cat_system_nav'  => 'Notifiche di Sistema',
                'cat_social_nav'  => 'Notifiche Social',
                'cat_rewards_nav' => 'Ricompense',
                'cat_special_nav' => 'Eventi speciali',
                'support'         => 'Assistenza',
                'tickets'         => 'Ticket Supporto',
                'search'          => 'Cerca...',

                // ── filtri ─────────────────────────────────────────
                'tab_inbox'   => 'Entrate',
                'tab_unread'  => 'Non letti',
                'tab_starred' => 'Importanti',
                'tab_archive' => 'Archivio',
                'tab_open'    => 'Aperti',
                'tab_closed'  => 'Chiusi',

                // ── stati della lista ──────────────────────────────
                'loading'          => 'Caricamento...',
                'loading_chat'     => 'Caricamento chat...',
                'load_error'       => 'Impossibile caricare i messaggi.',
                'load_chat_error'  => 'Impossibile caricare i messaggi della chat.',
                'no_items'         => 'Nessun elemento trovato',
                'no_chat_messages' => 'Nessun messaggio nella chat.',
                'empty_title'      => 'Seleziona una conversazione',
                'empty_body'       => 'Clicca su un messaggio o un ticket a sinistra per leggerlo o rispondere.',

                // ── categorie nelle schede ─────────────────────────
                'cat_system'     => 'Sistema',
                'cat_security'   => 'Sicurezza',
                'cat_moderation' => 'Moderazione',
                'cat_reward'     => 'Premio',
                'cat_special'    => 'Speciale',

                // ── azioni sul messaggio ───────────────────────────
                'back'      => 'Indietro',
                'important' => 'Importante',
                'archive'   => 'Archivia',
                'restore'   => 'Ripristina',
                'delete'    => 'Elimina',

                // ── premi ──────────────────────────────────────────
                'reward_money_sub'     => 'Valuta del sito',
                'reward_shard_sub'     => 'Valuta rara del sito',
                'reward_character'     => 'Personaggio ID',
                'reward_character_sub' => 'Aggiunto all\'inventario',
                'reward_badge'         => 'Badge personalizzato ID',
                'reward_badge_sub'     => 'Profilo sbloccato',
                'reward_premium'       => 'Status Premium',
                'reward_premium_sub'   => 'Vantaggi VIP attivati',
                'rewards_claimed'      => 'Premi Riscattati',
                'rewards_included'     => 'Premi inclusi in questo messaggio',
                'claim_rewards'        => 'Riscatta premi',
                'claiming'             => 'Riscatto in corso...',
                'modal_title'          => 'Premio Riscattato!',
                'modal_sub'            => 'Hai aggiunto con successo i seguenti oggetti al tuo account:',
                'modal_close'          => 'Ottimo',

                // ── ticket ─────────────────────────────────────────
                'topic'                => 'Argomento',
                'status'               => 'Stato',
                'user'                 => 'Utente',
                'guest'                => 'Ospite',
                'status_open'          => 'Aperto',
                'status_closed'        => 'Chiuso',
                'ticket_reopen'        => 'Riapri Ticket',
                'ticket_close'         => 'Chiudi Ticket',
                'ticket_closed_banner' => 'Questo ticket è chiuso. Riaprilo per inviare nuovi messaggi.',
                'attach_image'         => 'Allega un\'immagine',
                'reply_placeholder'    => 'Rispondi al ticket...',
                'send'                 => 'Invia',

                // ── errori ─────────────────────────────────────────
                'error_prefix'        => 'Errore: ',
                'conn_error'          => 'Errore di connessione.',
                'conn_error_generic'  => 'Si è verificato un errore di connessione.',
                'ticket_status_error' => 'Impossibile aggiornare lo stato del ticket: ',
                'only_images'         => 'Per favore seleziona solo file di tipo immagine.',
                'image_too_big'       => 'L\'immagine non può superare i 5MB.',
                'send_error'          => 'Impossibile inviare: ',
                'send_conn_error'     => 'Errore di connessione durante l\'invio.',
                'star_error'          => 'Impossibile aggiornare lo stato importante.',
                'archive_error'       => 'Impossibile archiviare il messaggio.',
                'delete_confirm'      => 'Sei sicuro di voler eliminare questo messaggio? Questa azione non può essere annullata.',
                'delete_error'        => 'Impossibile eliminare il messaggio.',
                'claim_error'         => 'Impossibile riscattare i premi.',
            ],

            'en' => [
                'sec_messages'    => 'Messages',
                'sec_tickets'     => 'Tickets',
                'confirm_delete'  => 'Confirm?',
                'sending'         => 'Sending...',
                'cat_changelog'   => 'Changelog',
                'cat_social'      => 'Social',
                'cat_ticket'      => 'Ticket',
                'filter_by'       => 'Filter by category',
                'no_messages'     => 'You have no messages yet',
                'no_tickets'      => 'You have no support tickets',
                'page_title'      => 'Message Center',
                'og_description'  => 'Cripsum™ Message Center. Check your notifications, reply to support tickets, and claim your rewards.',
                'aside_label'     => 'Message categories',
                'messages'        => 'Messages',
                'cat_all'         => 'All Messages',
                'cat_system_nav'  => 'System Notifications',
                'cat_social_nav'  => 'Social Notifications',
                'cat_rewards_nav' => 'Rewards',
                'cat_special_nav' => 'Special Events',
                'support'         => 'Support',
                'tickets'         => 'Support Tickets',
                'search'          => 'Search...',

                'tab_inbox'   => 'Inbox',
                'tab_unread'  => 'Unread',
                'tab_starred' => 'Starred',
                'tab_archive' => 'Archive',
                'tab_open'    => 'Open',
                'tab_closed'  => 'Closed',

                'loading'          => 'Loading...',
                'loading_chat'     => 'Loading chat...',
                'load_error'       => 'Unable to load messages.',
                'load_chat_error'  => 'Unable to load chat messages.',
                'no_items'         => 'No items found',
                'no_chat_messages' => 'No messages in this chat.',
                'empty_title'      => 'Select a conversation',
                'empty_body'       => 'Click on a message or a ticket on the left to read or reply.',

                'cat_system'     => 'System',
                'cat_security'   => 'Security',
                'cat_moderation' => 'Moderation',
                'cat_reward'     => 'Reward',
                'cat_special'    => 'Special',

                'back'      => 'Back',
                'important' => 'Important',
                'archive'   => 'Archive',
                'restore'   => 'Restore',
                'delete'    => 'Delete',

                'reward_money_sub'     => 'Site currency',
                'reward_shard_sub'     => 'Rare site currency',
                'reward_character'     => 'Character ID',
                'reward_character_sub' => 'Added to inventory',
                'reward_badge'         => 'Custom badge ID',
                'reward_badge_sub'     => 'Profile unlocked',
                'reward_premium'       => 'Premium Status',
                'reward_premium_sub'   => 'VIP perks activated',
                'rewards_claimed'      => 'Rewards Claimed',
                'rewards_included'     => 'Rewards included in this message',
                'claim_rewards'        => 'Claim rewards',
                'claiming'             => 'Claiming...',
                'modal_title'          => 'Reward Claimed!',
                'modal_sub'            => 'You have successfully added the following items to your account:',
                'modal_close'          => 'Awesome',

                'topic'                => 'Topic',
                'status'               => 'Status',
                'user'                 => 'User',
                'guest'                => 'Guest',
                'status_open'          => 'Open',
                'status_closed'        => 'Closed',
                'ticket_reopen'        => 'Reopen Ticket',
                'ticket_close'         => 'Close Ticket',
                'ticket_closed_banner' => 'This ticket is closed. Reopen it to send new messages.',
                'attach_image'         => 'Attach an image',
                'reply_placeholder'    => 'Reply to ticket...',
                'send'                 => 'Send',

                'error_prefix'        => 'Error: ',
                'conn_error'          => 'Connection error.',
                'conn_error_generic'  => 'A connection error occurred.',
                'ticket_status_error' => 'Unable to update ticket status: ',
                'only_images'         => 'Please select image files only.',
                'image_too_big'       => 'The image cannot exceed 5MB.',
                'send_error'          => 'Unable to send: ',
                'send_conn_error'     => 'Connection error while sending.',
                'star_error'          => 'Unable to update starred state.',
                'archive_error'       => 'Unable to archive message.',
                'delete_confirm'      => 'Are you sure you want to delete this message? This action cannot be undone.',
                'delete_error'        => 'Unable to delete message.',
                'claim_error'         => 'Unable to claim rewards.',
            ],
        ];

        return $strings[$lang] ?? $strings['it'];
    }

    /**
     * Sottoinsieme che serve al JavaScript.
     *
     * Mandare tutto sarebbe piu' comodo, ma meta' di queste stringhe le usa
     * solo il markup: passare solo quelle giuste tiene onesto l'elenco.
     */
    function inbox_js_strings(array $t): array
    {
        $keys = [
            'tab_inbox', 'tab_archive', 'tab_open', 'tab_closed',
            'loading', 'loading_chat', 'load_error', 'load_chat_error',
            'no_items', 'no_chat_messages', 'empty_title', 'empty_body',
            'cat_system', 'cat_security', 'cat_moderation', 'cat_reward', 'cat_special',
            'back', 'important', 'archive', 'restore', 'delete',
            'reward_money_sub', 'reward_shard_sub', 'reward_character', 'reward_character_sub',
            'reward_badge', 'reward_badge_sub', 'reward_premium', 'reward_premium_sub',
            'rewards_claimed', 'rewards_included', 'claim_rewards', 'claiming',
            'topic', 'status', 'user', 'guest', 'status_open', 'status_closed',
            'ticket_reopen', 'ticket_close', 'ticket_closed_banner',
            'attach_image', 'reply_placeholder', 'send',
            'sec_messages', 'sec_tickets', 'confirm_delete', 'sending',
            'cat_changelog', 'cat_social', 'cat_ticket', 'no_messages', 'no_tickets',
            'error_prefix', 'conn_error', 'conn_error_generic', 'ticket_status_error',
            'only_images', 'image_too_big', 'send_error', 'send_conn_error',
            'star_error', 'archive_error', 'delete_confirm', 'delete_error', 'claim_error',
        ];

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $t[$k] ?? '';
        }

        return $out;
    }
}
