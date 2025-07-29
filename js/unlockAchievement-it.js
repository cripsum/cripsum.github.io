function getCookie(name) {
    const cookies = document.cookie.split("; ");
    for (let cookie of cookies) {
        let [key, value] = cookie.split("=");
        if (key === name) return JSON.parse(value);
    }
    return null;
}

function setCookie(name, value) {
    document.cookie = `${name}=${JSON.stringify(value)}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT`;
}

async function unlockAchievement(id) {
    const response = await fetch('https://cripsum.com/api/get_unlocked_achievement');
    const unlocked = await response.json();

    if (!unlocked.some(a => a.id === id)) {
        await fetch('https://cripsum.com/api/set_achievement?achievement_id=' + id);
        showAchievementPopup(id);
    }
}


async function showAchievementPopup(id) {
    console.log("Chiamato showAchievementPopup con ID:", id);

    try {
        const response = await fetch("https://cripsum.com/api/get_achievement?achievement_id=" + id);
        const data = await response.json();

        const achievement = data[0]; // <-- prendi il primo oggetto dell’array

        if (!achievement || !achievement.img_url) {
            console.warn("Achievement non valido:", achievement);
            return;
        }

        console.log("Achievement ottenuto:", achievement);

        document.getElementById("popup-title").textContent = achievement.nome;
        document.getElementById("popup-description").textContent = achievement.descrizione;
        document.getElementById("popup-image").src = "../img/" + achievement.img_url;

        const popup = document.getElementById("achievement-popup");
        popup.classList.add("show");

        setTimeout(() => {
            popup.classList.remove("show");
        }, 3000);
    } catch (err) {
        console.error("Errore nella fetch o nel parsing:", err);
    }
}
