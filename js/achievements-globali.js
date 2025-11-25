unlockAchievement(1);

(async () => {
    const unlockedAchievementsnum = await getUnlockedAchievementsNumber();

    if (unlockedAchievementsnum === 20) {
        unlockAchievement(21);
    }
})();

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

const now = new Date();
if (now.getHours() === 3) {
    unlockAchievement(12);
}

function checkTimeSpent() {
    let timeSpent = parseInt(getCookie("timeSpent")) || 0;

    timeSpent += 1;

    setCookie("timeSpent", timeSpent);

    if (timeSpent >= 7200) {
        if (getCookie("achievement14Unlocked")){}
        else {
            unlockAchievement(14);
            setCookie("achievement14Unlocked", true);
        }
    }
}

setInterval(checkTimeSpent, 1000);

function checkDaysVisited() {
    let daysVisited = getCookie("daysVisited");
    if (!Array.isArray(daysVisited)) {
        daysVisited = [];
    }
    const today = new Date().toISOString().slice(0, 10);
    if (!daysVisited.includes(today)) {
        daysVisited.push(today);
        setCookie("daysVisited", daysVisited);
    }
    if (daysVisited.length >= 30) {
        unlockAchievement(13);
    }
}

checkDaysVisited();

async function getUnlockedAchievementsNumber()  {
    try {
        const response = await fetch('https://cripsum.com/api/get_unlocked_achievement_number', {
            credentials: "include"
        });
        const data = await response.json();
        return data.count;
    } catch (err) {
        console.error("Errore in getUnlockedAchievementsNumber:", err);
        return 0;
    }
}
