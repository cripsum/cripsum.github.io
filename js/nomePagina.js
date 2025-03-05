const pageTitle = document.title;
let titleIndex = 0;

function animateTitle() {
    document.title = pageTitle.substring(titleIndex) + " " + pageTitle.substring(0, titleIndex);
    titleIndex = (titleIndex + 1) % pageTitle.length;
}

setInterval(animateTitle, 300);