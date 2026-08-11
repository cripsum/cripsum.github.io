/* Cripsum Subway Surfers hosted map selector */

(function () {
    'use strict';

    const providerBaseUrl = 'https://ashuni.lol/';
    const defaultCardImage = 'https://raw.githubusercontent.com/tavvkkj/therealoness-builds-1/main/game-assets/crosspromo/embedded/ssblast_Image_ss_blast_xpromo_banner_key_art_512x512_02.png';
    const maps = [
        ['transylvania', 'Transylvania', 'Europe'],
        ['moscow', 'Moscow', 'Europe'],
        ['cairo', 'Cairo', 'Africa'],
        ['hongkong', 'Hong Kong', 'Asia'],
        ['paris', 'Paris', 'Europe'],
        ['bangkok', 'Bangkok', 'Asia'],
        ['rio', 'Rio', 'America'],
        ['tokyo', 'Tokyo', 'Asia'],
        ['venice', 'Venice', 'Europe'],
        ['newyork', 'New York', 'America'],
        ['zurich', 'Zurich', 'Europe'],
        ['barcelona', 'Barcelona', 'Europe'],
        ['houston', 'Houston', 'America'],
        ['miami', 'Miami', 'America'],
        ['berlin', 'Berlin', 'Europe'],
        ['buenosaires', 'Buenos Aires', 'America'],
        ['mexico', 'Mexico', 'America'],
        ['beijing', 'Beijing', 'Asia'],
        ['london', 'London', 'Europe'],
        ['iceland', 'Iceland', 'Europe'],
        ['havana', 'Havana', 'America'],
        ['neworleans', 'New Orleans', 'America'],
        ['winterholiday', 'Winter Holiday', 'Europe'],
        ['sanfrancisco', 'San Francisco', 'America'],
        ['saintpetersburg', 'Saint Petersburg', 'Europe'],
        ['monaco', 'Monaco', 'Europe']
    ];
    const mapBySlug = new Map(maps.map(map => [map[0], map]));

    function isItalian() {
        return String(document.documentElement.lang || '').toLowerCase().startsWith('it');
    }

    function localizedRegion(region) {
        return isItalian() && region === 'Europe' ? 'Europa' : region;
    }

    function providerUrl(slug) {
        if (!mapBySlug.has(slug)) return null;
        return new URL(`${encodeURIComponent(slug)}/`, providerBaseUrl).href;
    }

    function openMap(slug) {
        const target = providerUrl(slug);
        if (!target) return;
        window.location.assign(target);
    }

    function createCard([slug, name, region]) {
        const card = document.createElement('div');
        card.className = 'subway-map-card';
        card.dataset.map = slug;

        const background = document.createElement('div');
        background.className = 'subway-map-bg';
        background.style.backgroundImage = `url('${defaultCardImage}')`;

        const content = document.createElement('div');
        content.className = 'subway-map-content';

        const tag = document.createElement('span');
        tag.className = 'subway-map-tag';
        tag.textContent = localizedRegion(region);

        const heading = document.createElement('h3');
        heading.textContent = name;

        const description = document.createElement('p');
        description.textContent = isItalian()
            ? 'Apri questa mappa nella versione di gioco funzionante.'
            : 'Open this map in the working game version.';

        content.append(tag, heading, description);
        card.append(background, content);
        return card;
    }

    function bindCard(card) {
        const slug = String(card.dataset.map || '');
        const map = mapBySlug.get(slug);
        if (!map || card.dataset.hostedBound === 'true') return;

        const label = isItalian()
            ? `Apri ${map[1]} su Ashuni`
            : `Open ${map[1]} on Ashuni`;
        card.dataset.hostedBound = 'true';
        card.dataset.providerUrl = providerUrl(slug);
        card.setAttribute('role', 'link');
        card.setAttribute('tabindex', '0');
        card.setAttribute('aria-label', label);
        card.setAttribute('title', label);

        card.addEventListener('click', () => openMap(slug));
        card.addEventListener('keydown', event => {
            if (event.code !== 'Enter' && event.code !== 'Space') return;
            event.preventDefault();
            openMap(slug);
        });
    }

    function initializeSelector() {
        const portal = document.getElementById('subwayPortal');
        const grid = portal && portal.querySelector('.subway-grid');
        if (!grid) return;

        const cardsBySlug = new Map(
            Array.from(grid.querySelectorAll('.subway-map-card[data-map]'), card => [card.dataset.map, card])
        );

        for (const map of maps) {
            if (!cardsBySlug.has(map[0])) {
                const card = createCard(map);
                grid.appendChild(card);
                cardsBySlug.set(map[0], card);
            }
        }

        cardsBySlug.forEach(bindCard);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSelector, { once: true });
    } else {
        initializeSelector();
    }
})();
