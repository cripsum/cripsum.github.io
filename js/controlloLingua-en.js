const selectedLanguage = getCookie("language") || "🇬🇧 Eng";
if (selectedLanguage === "🇬🇧 Eng") {
    
} else if (selectedLanguage === "🇮🇹 Ita") {
    const pathParts = window.location.pathname.split("/");
    const pageName = pathParts.pop();
    const newPath = pathParts.includes("it") ? pathParts.slice(0, -1).join("/") : pathParts.join("/");
    document.querySelector(".selezione-lingua select").value = "🇬🇧 Eng";
    window.location.href = newPath + "/it/" + pageName;
} 


function salvaImpostazioni() {
    const selectedLanguage = document.querySelector(".selezione-lingua select").value;
    setCookie("language", selectedLanguage);

    if (selectedLanguage === "🇬🇧 Eng") {
       
    } else if (selectedLanguage === "🇮🇹 Ita") {
        const pathParts = window.location.pathname.split("/");
        const pageName = pathParts.pop();
        const newPath = pathParts.includes("it") ? pathParts.slice(0, -1).join("/") : pathParts.join("/");
        document.querySelector(".selezione-lingua select").value = "🇬🇧 Eng";
        window.location.href = newPath + "/it/" + pageName;
    }
}