function getCookie(name) {
    const cookies = document.cookie.split("; ");
    for (let cookie of cookies) {
        let [key, value] = cookie.split("=");
        if (key === name) return JSON.parse(value);
    }
    return null;
}

const selectedLanguage = getCookie("language") || "🇬🇧 Eng";
if (selectedLanguage === "🇬🇧 Eng") {
    
} else if (selectedLanguage === "🇮🇹 Ita") {
     const pageName = window.location.pathname.split("/").pop();
    window.location.href = "https://cripsum.com/" + pageName;
} 


function salvaImpostazioni() {
    const selectedLanguage = document.querySelector(".selezione-lingua select").value;
    setCookie("language", selectedLanguage);

    if (selectedLanguage === "🇬🇧 Eng") {
       
    } else if (selectedLanguage === "🇮🇹 Ita") {
        const pageName = window.location.pathname.split("/").pop();
        window.location.href = "../" + pageName;
    }
}