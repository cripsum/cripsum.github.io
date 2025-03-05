const selectedLanguage = getCookie("language") || "🇮🇹 Ita";
if (selectedLanguage === "🇬🇧 Eng") {
    
} else if (selectedLanguage === "🇮🇹 Ita") {
    const pageName = window.location.pathname.split("/").pop();
    window.location.href = "../it/" + pageName;
} 


function salvaImpostazioni() {
    const selectedLanguage = document.querySelector(".selezione-lingua select").value;
    setCookie("language", selectedLanguage);

    if (selectedLanguage === "🇬🇧 Eng") {
       
    } else if (selectedLanguage === "🇮🇹 Ita") {
        const pageName = window.location.pathname.split("/").pop();
        window.location.href = "../it/" + pageName;
    }
}