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
    let response = await fetch('https://cripsum.com/api/get_unlocked_achievement').catch((e) => [console.error("Error fetching unlocked achievements:", e), []]);
    let achievements = response ? await response.json() : [];
    if (!achievements.includes(id)) {
        await fetch('../api/set_achievement' + '?achievement_id=' + id);
        showAchievementPopup(id);
    }
}
async function showAchievementPopup(id) {
    const response = await fetch('../api/get_achievement' + '?achievement_id=' + id).catch((e) => {
        console.error("Error fetching achievement:", e);
        return null;
    });
    const achievement = response ? await response.json() : null;

    if (achievement) {
        document.getElementById("popup-title").textContent = achievement.nome;
        document.getElementById("popup-description").textContent = achievement.descrizione;
        document.getElementById("popup-image").src = '../img/'+ achievement.img_url;

        const popup = document.getElementById("achievement-popup");
        popup.classList.add("show");

        setTimeout(() => {
            popup.classList.remove("show");
        }, 3000); // Il popup scompare dopo 3 secondi
    }
}