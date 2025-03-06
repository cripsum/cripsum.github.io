function getCookie(name) {
    const cookies = document.cookie.split("; ");
    for (let cookie of cookies) {
        let [key, value] = cookie.split("=");
        if (key === name) return JSON.parse(value);
    }
    return null;
}

const selectedTheme = getCookie("theme") || 1;

    const darkThemeHref = "../css/style-dark.css"; 
    const lightThemeHref = "../css/style-light.css";
    const ogThemeHref = "../css/style-og.css";
    let link = document.querySelector(`link[href="${darkThemeHref}"]`);
    let linklight = document.querySelector(`link[href="${lightThemeHref}"]`);
    let linkog = document.querySelector(`link[href="${lightThemeHref}"]`);

    if (selectedTheme === 1) {  
        if (!link) { // Se il foglio di stile non esiste, lo aggiunge
            link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = darkThemeHref;
            document.head.appendChild(link);

            document.head.removeChild(linklight);
            document.head.removeChild(linkog);
        }

    } else if (selectedTheme === 2) {  
        if (link || linkog) { // Se il foglio di stile esiste, lo rimuove
            document.head.removeChild(link);
            document.head.removeChild(linkog);

            linklight = document.createElement("link");
            linklight.rel = "stylesheet";
            linklight.href = lightThemeHref;
            document.head.appendChild(linklight);
        }
    } else if (selectedTheme === 3) {
        if (link || linklight) {
        document.head.removeChild(link);
        document.head.removeChild(linklight);

        linkog = document.createElement("link");
        linkog.rel = "stylesheet";
        linkog.href = lightThemeHref;
        document.head.appendChild(linkog);
        }
    }

function controllaTema() {
    const selectElement = document.querySelector(".selezione-tema select"); // Prende l'elemento corretto
    const selectedTheme = parseInt(selectElement.value); // Prende il valore corretto

    setCookie("theme", selectedTheme); 

    const darkThemeHref = "../css/style-dark.css"; 
    const lightThemeHref = "../css/style-light.css";
    const ogThemeHref = "../css/style-og.css";
    let link = document.querySelector(`link[href="${darkThemeHref}"]`);
    let linklight = document.querySelector(`link[href="${lightThemeHref}"]`);
    let linkog = document.querySelector(`link[href="${lightThemeHref}"]`);

    if (selectedTheme === 1) {  
        if (!link) { // Se il foglio di stile non esiste, lo aggiunge
            link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = darkThemeHref;
            document.head.appendChild(link);
            document.head.removeChild(linklight);
            document.head.removeChild(linkog);
        }

    } else if (selectedTheme === 2) {  
        if (link || linkog) { // Se il foglio di stile esiste, lo rimuove
            document.head.removeChild(link);
            document.head.removeChild(linkog);
            linklight = document.createElement("link");
            linklight.rel = "stylesheet";
            linklight.href = lightThemeHref;
            document.head.appendChild(linklight);
        }
    } else if (selectedTheme === 3) {
        if (link || linklight) {
        document.head.removeChild(link);
        document.head.removeChild(linklight);

        linkog = document.createElement("link");
        linkog.rel = "stylesheet";
        linkog.href = lightThemeHref;
        document.head.appendChild(linkog);
        }
    }
}