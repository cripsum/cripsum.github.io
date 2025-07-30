function viewUserDetails(userId) {
    fetch(`https://cripsum.com/api/get_user?id=${userId}`)
        .then(res => res.text())
        .then(html => {
            document.getElementById('userDetailsContent').innerHTML = html;
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
