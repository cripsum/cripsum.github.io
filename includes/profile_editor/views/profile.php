<?php
/** Area "Profilo": la parte alta del profilo, quella che si vede per prima. */
?>
<div class="pe-checklist" id="peChecklist" hidden></div>

<?php pe_group('grp-avatar', $tt('Foto profilo', 'Profile photo'), null, ['keywords' => 'avatar pfp immagine picture']); ?>
<div class="pe-avatar-editor" data-avatar-shape="<?php echo pe_h($style['avatar_shape']); ?>">
    <div class="pe-avatar-preview">
        <img src="<?php echo pe_h($avatarUrl); ?>" alt="" id="peAvatarPreview">
    </div>
    <div class="pe-avatar-actions">
        <label class="pe-btn pe-btn-secondary" for="peAvatarInput">
            <i class="fa-solid fa-camera" aria-hidden="true"></i><span><?php echo pe_h($tt('Cambia foto', 'Change photo')); ?></span>
        </label>
        <input type="file" name="avatar" id="peAvatarInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        <p class="pe-help"><?php echo pe_h($tt('JPG, PNG, WEBP o GIF, fino a ', 'JPG, PNG, WEBP or GIF, up to ') . profile_format_bytes($uploadLimits['avatar'])); ?>. <?php echo pe_h($tt('Potrai ritagliarla prima di usarla.', 'You can crop it before using it.')); ?></p>
    </div>
</div>
<?php pe_toggle('discord_use_avatar', $pflag('discord_use_avatar', 0), [
    'label' => $tt('Usa la foto di Discord', 'Use your Discord photo'),
    'description' => $discordConnected
        ? $tt('Si aggiorna da sola quando la cambi su Discord.', 'Updates by itself when you change it on Discord.')
        : $tt('Collega Discord in Impostazioni per usarla.', 'Connect Discord in Settings to use it.'),
    'disabled' => !$discordConnected,
    'keywords' => 'discord avatar',
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-identity', $tt('Nome e bio', 'Name and bio'), null, ['keywords' => 'nome username bio stato']); ?>
<?php pe_text('display_name', $pv('display_name'), [
    'label' => $tt('Nome visualizzato', 'Display name'),
    'maxlength' => 40,
    'placeholder' => $profile['username'],
    'counter' => true,
    'input_id' => 'peDisplayName',
    'help' => pe_h($tt('Lascia vuoto per mostrare lo username. Colore ed effetti del nome sono in Effetti.', 'Leave empty to show your username. Name color and effects are in Effects.')),
]); ?>
<?php pe_toggle('discord_use_display_name', $pflag('discord_use_display_name', 0), [
    'label' => $tt('Usa il nome di Discord', 'Use your Discord name'),
    'disabled' => !$discordConnected,
    'keywords' => 'discord nome',
]); ?>
<?php pe_text('username', $pv('username'), [
    'label' => 'Username',
    'maxlength' => 20,
    'required' => true,
    'prefix' => 'cripsum.com/u/',
    'input_id' => 'peUsername',
    'help' => pe_h($tt('Da 3 a 20 caratteri: lettere, numeri e trattino basso.', '3 to 20 characters: letters, numbers and underscore.')),
]); ?>
<?php pe_textarea('bio', $pv('bio'), [
    'label' => 'Bio',
    'maxlength' => 280,
    'rows' => 4,
    'input_id' => 'peBio',
    'placeholder' => $tt('Racconta qualcosa di te…', 'Tell people something about you…'),
]); ?>
<?php pe_text('profile_status', $pv('profile_status'), [
    'label' => $tt('Stato', 'Status'),
    'maxlength' => 60,
    'placeholder' => $tt('Es. sto editando, in pausa…', 'E.g. editing, taking a break…'),
    'counter' => true,
    'help' => pe_h($tt('Compare vicino al nome quando non sei online.', 'Shown next to your name when you are offline.')),
]); ?>
<?php pe_toggle('profile_hide_meta', $pflag('profile_hide_meta', 0), [
    'label' => $tt('Nascondi iscrizione e ultimo accesso', 'Hide join date and last seen'),
    'description' => $tt('Le informazioni piccole in fondo alla card del profilo.', 'The small details at the bottom of your profile card.'),
    'premium' => true,
    'keywords' => 'meta data iscrizione',
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-tags', $tt('Tag', 'Tags'), $tt('Etichette colorate sotto la bio, fino a 10.', 'Colored labels under your bio, up to 10.'), [
    'keywords' => 'pill pillole etichette tag',
    'action' => '<button type="button" class="pe-btn pe-btn-secondary pe-btn-sm" data-add-item="tags"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>' . pe_h($tt('Aggiungi', 'Add')) . '</span></button>',
]); ?>
<div class="pe-items" data-items="tags"></div>
<?php pe_group_end(); ?>

<?php pe_group('grp-socials', 'Social', $tt('Le icone dei tuoi profili sugli altri siti.', 'Icons linking to your profiles on other sites.'), [
    'keywords' => 'instagram tiktok youtube link social',
    'action' => '<button type="button" class="pe-btn pe-btn-secondary pe-btn-sm" data-add-item="socials"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>' . pe_h($tt('Aggiungi', 'Add')) . '</span></button>',
]); ?>
<div class="pe-items" data-items="socials"></div>
<button type="button" class="pe-goto" data-goto="grp-buttons">
    <i class="fa-solid fa-palette" aria-hidden="true"></i><?php echo pe_h($tt('Card o icone? Cambia l\'aspetto dei social', 'Cards or icons? Change how socials look')); ?><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
</button>
<?php pe_group_end(); ?>
