document.addEventListener("DOMContentLoaded", () => {
    const boxes = document.querySelectorAll(".fadeup");

    const observer = new IntersectionObserver(
        (entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {

                    if (!entry.target.classList.contains("visible")) {
                        entry.target.classList.add("visible");
                    }

                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1 }
    );

    boxes.forEach((fadeup) => observer.observe(fadeup));
});

document.addEventListener("DOMContentLoaded", () => {
    const boxes = document.querySelectorAll(".fadeuphr");

    const observer = new IntersectionObserver(
        (entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {

                    if (!entry.target.classList.contains("visible-hr")) {
                        entry.target.classList.add("visible-hr");
                    }

                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1 }
    );

    boxes.forEach((fadeuphr) => observer.observe(fadeuphr));
});

document.addEventListener("DOMContentLoaded", () => {
    const boxes = document.querySelectorAll(".fadein");

    const observer = new IntersectionObserver(
        (entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {

                    if (!entry.target.classList.contains("visible-fadein")) {
                        entry.target.classList.add("visible-fadein");
                    }

                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1 }
    );

    boxes.forEach((fadein) => observer.observe(fadein));
});