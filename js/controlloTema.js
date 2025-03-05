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
    let link = document.querySelector(`link[href="${darkThemeHref}"]`);

    if (selectedTheme === 1) {  
        if (!link) { // Se il foglio di stile non esiste, lo aggiunge
            link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = darkThemeHref;
            document.head.appendChild(link);
        }

    } else if (selectedTheme === 2) {  
        if (link) { // Se il foglio di stile esiste, lo rimuove
            link.parentNode.removeChild(link);
        }
    } else if (selectedTheme === 3) {

    }

function controllaTema() {
    const selectElement = document.querySelector(".selezione-tema select");
    const selectedTheme = parseInt(selectElement.value); // Prende il valore corretto

    setCookie("theme", selectedTheme); 

    const darkThemeHref = "../css/style-dark.css"; 
    let link = document.querySelector(`link[href="${darkThemeHref}"]`);

    if (selectedTheme === 1) {  
        if (!link) { // Se il foglio di stile non esiste, lo aggiunge
            link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = darkThemeHref;
            document.head.appendChild(link);
        }

    } else if (selectedTheme === 2) {  
        if (link) { // Se il foglio di stile esiste, lo rimuove
            link.parentNode.removeChild(link);
        }
    } else if (selectedTheme === 3) {

    }
}