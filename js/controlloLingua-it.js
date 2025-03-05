const selectedLanguage = getCookie("language") || "🇮🇹 Ita";
if (selectedLanguage === "🇬🇧 Eng") {
    const pathParts = window.location.pathname.split("/");
    const pageName = pathParts.pop();
    const newPath = pathParts.includes("en") ? pathParts.slice(0, -1).join("/") : pathParts.join("/");
    window.location.href = newPath + "/en/" + pageName;
} else if (selectedLanguage === "🇮🇹 Ita") {
}


function salvaImpostazioni() {
    const selectedLanguage = document.querySelector(".selezione select").value;
    setCookie("language", selectedLanguage);

    if (selectedLanguage === "🇬🇧 Eng") {
        const pathParts = window.location.pathname.split("/");
        const pageName = pathParts.pop();
        const newPath = pathParts.includes("en") ? pathParts.slice(0, -1).join("/") : pathParts.join("/");
        window.location.href = newPath + "/en/" + pageName;
    } else if (selectedLanguage === "🇮🇹 Ita") {
    }
}