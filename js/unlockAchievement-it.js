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
    const achievement = await fetch('https://cripsum.com/api/get_unlocked_achievement');
    if (!achievement.includes(id)) {
        await fetch('https://cripsum.com/api/set_achievement' + '?achievement_id=' + id);
        showAchievementPopup(id);
    }
}

async function showAchievementPopup(id) {
    console.log("Chiamato showAchievementPopup con ID:", id); // <--- questo
    const achievement = await fetch("https://cripsum.com/api/get_achievement?achievement_id=" + id).then(response => response.json());
    console.log("Achievement ottenuto:", achievement); // <--- questo

    if (achievement) {
        document.getElementById("popup-title").textContent = achievement.nome;
        document.getElementById("popup-description").textContent = achievement.descrizione;
        document.getElementById("popup-image").src = "../img/" + achievement.img_url;

        const popup = document.getElementById("achievement-popup");
        popup.classList.add("show");

        setTimeout(() => {
            popup.classList.remove("show");
        }, 3000); // Il popup scompare dopo 3 secondi
    }
}