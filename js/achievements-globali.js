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
    if (timeSpent >= 7200) {
        unlockAchievement(14);
    }
}

// Check time spent every second
setInterval(checkTimeSpent, 1000);

let unlockedachievements = getCookie("achievements");
if(unlockedachievements.lenght === 17){
    unlockAchievement(18);
}
