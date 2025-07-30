function controllaLingua() {
    const selectedLanguage = document.querySelector(".selezione select").value;

    if (selectedLanguage === "🇬🇧 Eng") {
        const pageName = window.location.pathname.split("/").pop();
        window.location.href = "../en/" + pageName;
    } else if (selectedLanguage === "🇮🇹 Ita") {
    }
}