<?php
// Copia questo file e imposta questi valori nel server.
// Il redirect URI deve essere identico a quello inserito nel Discord Developer Portal.

define('CRIPSUM_DISCORD_CLIENT_ID', getenv('DISCORD_CLIENT_ID') ?: 'INSERISCI_CLIENT_ID');
define('CRIPSUM_DISCORD_CLIENT_SECRET', getenv('DISCORD_CLIENT_SECRET') ?: 'INSERISCI_CLIENT_SECRET');
define('CRIPSUM_DISCORD_REDIRECT_URI', getenv('DISCORD_REDIRECT_URI') ?: 'https://cripsum.com/auth/discord_callback.php');
