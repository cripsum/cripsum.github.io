<?php
/** Area "Impostazioni": privacy, indirizzo, Discord, scheda del browser. */

$tabAnimation = (string)($profile['profile_tab_animation'] ?? 'static');
?>

<?php pe_group('grp-privacy', $tt('Chi può vedere il profilo', 'Who can see your profile'), null, ['keywords' => 'privacy privato visibilità amici']); ?>
<?php pe_choice('profile_visibility', (string)($profile['profile_visibility'] ?? 'public'), [
    ['value' => 'public', 'label' => $tt('Tutti', 'Everyone'), 'icon' => 'fa-solid fa-earth-europe'],
    ['value' => 'logged_in', 'label' => $tt('Utenti registrati', 'Signed-in users'), 'icon' => 'fa-solid fa-user-check'],
    ['value' => 'friends', 'label' => $tt('Solo amici', 'Friends only'), 'icon' => 'fa-solid fa-user-group'],
    ['value' => 'private', 'label' => $tt('Solo io', 'Only me'), 'icon' => 'fa-solid fa-lock'],
], ['variant' => 'tiles', 'columns' => 2]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-address', $tt('Indirizzo del profilo', 'Profile address'), null, ['keywords' => 'alias url link indirizzo']); ?>
<div class="pe-field">
    <div class="pe-field-label"><span class="pe-field-title"><?php echo pe_h($tt('Il tuo link', 'Your link')); ?></span></div>
    <div class="pe-copy">
        <code id="peProfileLink">cripsum.com/u/<?php echo pe_h(strtolower((string)$profile['username'])); ?></code>
        <button type="button" class="pe-btn pe-btn-secondary pe-btn-sm" data-copy="#peProfileLink"><i class="fa-regular fa-copy" aria-hidden="true"></i><span><?php echo pe_h($tt('Copia', 'Copy')); ?></span></button>
    </div>
</div>
<?php pe_text('custom_alias', $pv('custom_alias'), [
    'label' => $tt('Link breve', 'Short link'),
    'maxlength' => 30,
    'prefix' => 'cripsum.com/',
    'placeholder' => $tt('iltuonome', 'yourname'),
    'input_id' => 'peAlias',
    'suffix_html' => '<span class="pe-input-status" id="peAliasStatus" aria-hidden="true"></span>',
    'help' => '<span id="peAliasHelp">' . pe_h($tt('Facoltativo. Da 3 a 30 caratteri: lettere, numeri, - e _.', 'Optional. 3 to 30 characters: letters, numbers, - and _.')) . '</span>',
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-discord', 'Discord', null, ['keywords' => 'discord rich presence server widget']); ?>
<div class="pe-discord-card">
    <?php if ($discordConnected): ?>
        <?php if ($discordAvatarUrl): ?><img src="<?php echo pe_h($discordAvatarUrl); ?>" alt="" loading="lazy"><?php else: ?><span class="pe-discord-fallback"><i class="fa-brands fa-discord" aria-hidden="true"></i></span><?php endif; ?>
        <div class="pe-discord-text">
            <strong><?php echo pe_h($discordDisplayName ?: $profile['discord_username']); ?></strong>
            <small>@<?php echo pe_h($profile['discord_username']); ?></small>
        </div>
        <div class="pe-discord-actions">
            <a class="pe-btn pe-btn-secondary pe-btn-sm" href="<?php echo pe_h($connectDiscordUrl); ?>" data-leave-editor><?php echo pe_h($tt('Ricollega', 'Reconnect')); ?></a>
            <button type="submit" form="disconnectDiscordForm" class="pe-btn pe-btn-danger-ghost pe-btn-sm" data-leave-editor><?php echo pe_h($tt('Scollega', 'Disconnect')); ?></button>
        </div>
    <?php else: ?>
        <span class="pe-discord-fallback"><i class="fa-brands fa-discord" aria-hidden="true"></i></span>
        <div class="pe-discord-text">
            <strong><?php echo pe_h($tt('Discord non collegato', 'Discord not connected')); ?></strong>
            <small><?php echo pe_h($tt('Collegalo per usare nome e foto di Discord e mostrare la tua attività.', 'Connect it to use your Discord name and photo and show your activity.')); ?></small>
        </div>
        <div class="pe-discord-actions">
            <a class="pe-btn pe-btn-discord pe-btn-sm" href="<?php echo pe_h($connectDiscordUrl); ?>" data-leave-editor><i class="fa-brands fa-discord" aria-hidden="true"></i><span><?php echo pe_h($tt('Collega', 'Connect')); ?></span></a>
        </div>
    <?php endif; ?>
</div>

<?php pe_toggle('profile_show_discord', $pflag('profile_show_discord'), [
    'label' => $tt('Mostra Discord sul profilo', 'Show Discord on your profile'),
    'description' => $tt('La tua attività in tempo reale e il widget del server.', 'Your live activity and the server widget.'),
]); ?>

<?php pe_text('discord_id', $pv('discord_id'), [
    'label' => $tt('ID utente Discord', 'Discord user ID'),
    'maxlength' => 25,
    'placeholder' => '123456789012345678',
    'readonly' => $discordConnected,
    'help' => $discordConnected
        ? pe_h($tt('Preso dall\'account collegato.', 'Taken from the connected account.'))
        : pe_h($tt('Serve per mostrare l\'attività se non colleghi l\'account.', 'Needed to show your activity if you don\'t connect the account.')),
]); ?>
<p class="pe-note">
    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
    <span><?php echo pe_h($tt('Per mostrare giochi e Spotify in tempo reale devi essere nel server Discord di Cripsum, dove c\'è il bot.', 'To show games and Spotify live you need to be in the Cripsum Discord server, where the bot lives.')); ?>
        <a href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener noreferrer"><?php echo pe_h($tt('Entra nel server', 'Join the server')); ?></a></span>
</p>
<?php pe_text('discord_server_invite', $pv('discord_server_invite'), [
    'label' => $tt('Invito al tuo server', 'Your server invite'),
    'maxlength' => 255,
    'placeholder' => 'https://discord.gg/…',
    'help' => pe_h($tt('Mostra il widget del tuo server sul profilo.', 'Shows your server widget on your profile.')),
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-tab', $tt('Scheda del browser', 'Browser tab'), $tt('Il titolo che compare nella scheda quando qualcuno apre il tuo profilo.', 'The title shown in the tab when someone opens your profile.'), ['keywords' => 'titolo tab scheda animazione']); ?>
<?php pe_text('profile_tab_title', $pv('profile_tab_title'), ['label' => $tt('Titolo', 'Title'), 'maxlength' => 80, 'placeholder' => 'Cripsum™ - ' . profile_display_name($profile)]); ?>
<?php pe_choice('profile_tab_animation', $tabAnimation, [
    ['value' => 'static', 'label' => $tt('Fermo', 'Static')],
    ['value' => 'marquee', 'label' => $tt('Scorre', 'Scrolling')],
    ['value' => 'bounce', 'label' => $tt('Rimbalza', 'Bouncing')],
    ['value' => 'pulse', 'label' => $tt('Alterna', 'Alternating')],
], ['label' => $tt('Animazione', 'Animation')]); ?>
<div class="pe-tab-preview" aria-hidden="true">
    <span class="pe-tab-preview__tab">
        <img src="/img/Susremaster.png" alt="" width="16" height="16">
        <span class="pe-tab-preview__title" id="peTabPreviewTitle" data-default-title="<?php echo pe_h('Cripsum™ - ' . profile_display_name($profile)); ?>"></span>
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </span>
    <span class="pe-tab-preview__caption"><?php echo pe_h($tt('Anteprima della scheda', 'Tab preview')); ?></span>
</div>
<?php pe_text('profile_tab_animation_text', $pv('profile_tab_animation_text'), [
    'label' => $tt('Testo alternativo', 'Alternate text'),
    'maxlength' => 120,
    'placeholder' => '★ ' . $tt('Benvenuto', 'Welcome') . ' ★',
    'show_if' => 'profile_tab_animation=marquee|pulse',
    'help' => pe_h($tt('Scorre o si alterna con il titolo.', 'Scrolls or alternates with the title.')),
]); ?>
<?php pe_slider('profile_tab_animation_speed', (int)($profile['profile_tab_animation_speed'] ?? 1000), [
    'label' => $tt('Velocità', 'Speed'),
    'min' => 200,
    'max' => 5000,
    'step' => 100,
    'format' => 'ms',
    'show_if' => 'profile_tab_animation!=static',
    'help' => pe_h($tt('Scorre: tempo per ogni lettera. Rimbalza: durata di un rimbalzo. Alterna: tempo di ogni testo.', 'Scrolling: time per letter. Bouncing: length of one bounce. Alternating: time for each text.')),
]); ?>
<?php pe_group_end(); ?>
