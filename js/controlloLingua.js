function salvaImpostazioni() {
    const selectedLanguage = document.querySelector(".selezione-lingua select").value;

    if (selectedLanguage === "🇬🇧 Eng") {
       
    } else if (selectedLanguage === "🇮🇹 Ita") {
        const pageName = window.location.pathname.split("/").pop();
        window.location.href = "../" + pageName;
    }
}