function getCookie(name) {
    const cookies = document.cookie.split("; ");
    for (let cookie of cookies) {
        let [key, value] = cookie.split("=");
        if (key === name) return JSON.parse(value);
    }
    return null;
}

const selectedTheme = getCookie("theme") || 1;
if (selectedTheme === 1) {

    const link = document.querySelector('link[href="../js/style-dark.css"]');
    if (!link) {
        link.parentNode.addChild(link);
    }
    
} else if (selectedTheme === 2) {

    const link = document.querySelector('link[href="../js/style-dark.css"]');
    if (link) {
        link.parentNode.removeChild(link);
    }

} else if (selectedTheme === 3) {

}


function controllaTema() {
    const selectedTheme = document.querySelector(".selezione-tema select").id;
    setCookie("theme", selectedTheme);

    if (selectedTheme === 1) {  
        const link = document.querySelector('link[href="../js/style-dark.css"]');
        if (!link) {
            link.parentNode.addChild(link);
        }

    } else if (selectedTheme === 2) {

        const link = document.querySelector('link[href="../js/style-dark.css"]');
        if (link) {
            link.parentNode.removeChild(link);
        }


    } else if (selectedTheme === 3) {

    }
}