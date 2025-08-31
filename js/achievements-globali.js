unlockAchievement(1);

const unlockedAchievementsnum = await getUnlockedAchievementsNumber();

if (unlockedAchievementsnum === 20) {
    unlockAchievement(21);
}

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
    // Increment time spent by 1 second
    timeSpent += 1;
    // Update the cookie with the new time spent
    setCookie("timeSpent", timeSpent);
    // Check if 2 hours (7200 seconds) have been reached
    if (timeSpent == 7200) {
        unlockAchievement(14);
    }
}

// Check time spent every second
setInterval(checkTimeSpent, 1000);

function checkDaysVisited() {
    let daysVisited = getCookie("daysVisited") || [];
    const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
    if (!daysVisited.includes(today)) {
        daysVisited.push(today);
        setCookie("daysVisited", daysVisited);
    }
    if (daysVisited.length == 30) {
        unlockAchievement(13);
    }
}

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
