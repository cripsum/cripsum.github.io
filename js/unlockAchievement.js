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

function unlockAchievement(id) {
    let achievements = getCookie("achievements") || [];
    if (!achievements.includes(id)) {
        achievements.push(id);
        showAchievementPopup(id);
        setCookie("achievements", achievements);
    }
}

async function showAchievementPopup(id) {
    const response = await fetch("../data/achievements.json");
    const achievements = await response.json();
    const achievement = achievements.find((a) => a.id === id);

    if (achievement) {
        document.getElementById("popup-title").textContent = achievement.title;
        document.getElementById("popup-description").textContent = achievement.description;
        document.getElementById("popup-image").src = achievement.image;

        const popup = document.getElementById("achievement-popup");
        popup.classList.add("show");

        setTimeout(() => {
            popup.classList.remove("show");
        }, 3000); // Il popup scompare dopo 3 secondi
    }
}