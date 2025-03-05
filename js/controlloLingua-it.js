function getCookie(name) {
    const cookies = document.cookie.split("; ");
    for (let cookie of cookies) {
        let [key, value] = cookie.split("=");
        if (key === name) return JSON.parse(value);
    }
    return null;
}

const selectedLanguage = getCookie("language") || "🇮🇹 Ita";
if (selectedLanguage === "🇬🇧 Eng") {
    const pageName = window.location.pathname.split("/").pop();

    window.location.href = "../../en/" + pageName;
} else if (selectedLanguage === "🇮🇹 Ita") {
}


function salvaImpostazioni() {
    const selectedLanguage = document.querySelector(".selezione select").value;
    setCookie("language", selectedLanguage);

    if (selectedLanguage === "🇬🇧 Eng") {
        const pageName = window.location.pathname.split("/").pop();
        window.location.href = "../en/" + pageName;
    } else if (selectedLanguage === "🇮🇹 Ita") {
    }
}