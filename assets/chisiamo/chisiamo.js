/*
 * Chi siamo: le card del team compaiono man mano che si scorre.
 */
(() => {
    'use strict';

    const reveal = (member) => {
        const finish = (event) => {
            if (event.propertyName !== 'opacity') return;
            member.classList.add('reveal-complete');
            member.removeEventListener('transitionend', finish);
        };
        member.addEventListener('transitionend', finish);
        member.classList.add('is-visible');
    };

    document.addEventListener('DOMContentLoaded', () => {
        const members = document.querySelectorAll('.team-member');

        if (!('IntersectionObserver' in window)) {
            members.forEach(reveal);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                reveal(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -6% 0px' });

        members.forEach((member) => observer.observe(member));
    });
})();
