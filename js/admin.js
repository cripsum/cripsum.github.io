function viewUserDetails(userId) {
    // Fetch user details
    const userPromise = fetch(`https://cripsum.com/api/get_user?id=${userId}`)
        .then(res => res.text())
        .then(html => JSON.parse(html));
    
    // Fetch unlocked achievements
    const achievementsPromise = fetch(`https://cripsum.com/api/get_unlocked_achievement_from_id.php?id=${userId}`)
        .then(res => res.json());
    
    // Fetch unlocked characters
    const charactersPromise = fetch(`https://cripsum.com/api/get_unlocked_characters_from_id.php?id=${userId}`)
        .then(res => res.json());
    
    Promise.all([userPromise, achievementsPromise, charactersPromise])
        .then(([userDetails, achievements, characters]) => {
            let formattedContent = `
                <div class="user-details">
                    <div class="mb-3">
                        <strong>ID:</strong> ${userDetails.id || 'N/A'}
                    </div>
                    <div class="mb-3">
                        <strong>Nome:</strong> ${userDetails.username || 'N/A'}
                    </div>
                    <div class="mb-3">
                        <strong>Email:</strong> ${userDetails.email || 'N/A'}
                    </div>
                    <div class="mb-3">
                        <strong>Ruolo:</strong> ${userDetails.ruolo || 'N/A'}
                    </div>
                    <div class="mb-3">
                        <strong>Soldi:</strong> ${userDetails.soldi || 'N/A'}
                    </div>
                    <div class="mb-3">
                        <strong>Data creazione account:</strong> ${userDetails.data_creazione || 'N/A'}
                    </div>
                    <div class="mb-3">
                        <strong>Achievement sbloccati:</strong>
                        <ul class="list-unstyled ms-3">
                            ${achievements.map(achievement => `<li>•${achievement.id} - ${achievement.nome || 'Unnamed'}, ${achievement.punti} punti - sbloccato il ${achievement.data}</li>`).join('') || '<li>Nessun achievement sbloccato</li>'}
                        </ul>
                    </div>
                    <div class="mb-3">
                        <strong>Personaggi sbloccati:</strong>
                        <ul class="list-unstyled ms-3">
                            ${characters.map(character => `<li>•${character.id} - ${character.nome || 'Unnamed'} (x${character.quantità}) - trovato il ${character.data}</li>`).join('') || '<li>Nessun personaggio sbloccato</li>'}
                        </ul>
                    </div>
                </div>
            `;
            document.getElementById('userDetailsContent').innerHTML = formattedContent;
            new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
        });
}

function editUser(userId) {
    fetch(`https://cripsum.com/api/get_user?id=${userId}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('editUserId').value = data.id;
            document.getElementById('editUsername').value = data.username;
            document.getElementById('editEmail').value = data.email;
            document.getElementById('editRuolo').value = data.ruolo;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        });
}

function saveUserChanges() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);

    fetch('https://cripsum.com/api/update_user.php', {
        method: 'POST',
        body: formData
    }).then(res => {
        if (res.ok) {
            location.reload();
        }
    });
}

function addCharacterToUser(userId) {
    document.getElementById('characterUserId').value = userId;
    new bootstrap.Modal(document.getElementById('addCharacterModal')).show();
}

function saveCharacterToUser() {
    const form = document.getElementById('addCharacterToUserForm');
    const formData = new FormData(form);

    fetch('https://cripsum.com/api/add_character_to_user.php', {
        method: 'POST',
        body: formData
    }).then(res => {
        if (res.ok) {
            location.reload();
        }
    });
}

function addAchievementToUser(userId) {
    document.getElementById('achievementUserId').value = userId;
    new bootstrap.Modal(document.getElementById('addAchievementModal')).show();
}

function saveAchievementToUser() {
    const form = document.getElementById('addAchievementToUserForm');
    const formData = new FormData(form);

    fetch('https://cripsum.com/api/add_achievement_to_user.php', {
        method: 'POST',
        body: formData
    }).then(res => {
        if (res.ok) {
            location.reload();
        }
    });
}
