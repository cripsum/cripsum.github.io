(() => {
    'use strict';

    const cfg = window.Cripsumpedia || {};
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const state = {
        previewCache: new Map(),
        searchTimer: null,
        searchAbort: null,
        relationTimer: null,
        toastTimer: null,
        relations: Array.isArray(window.CripsumpediaEditorRelations) ? window.CripsumpediaEditorRelations : [],
    };

    function toast(message, isError = false) {
        const el = $('[data-cp-toast]');
        if (!el) return;
        clearTimeout(state.toastTimer);
        el.textContent = message;
        el.hidden = false;
        el.classList.toggle('is-error', isError);
        requestAnimationFrame(() => el.classList.add('is-visible'));
        state.toastTimer = window.setTimeout(() => {
            el.classList.remove('is-visible');
            window.setTimeout(() => {
                el.hidden = true;
            }, 200);
        }, 2600);
    }

    function debounce(fn, delay = 260) {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = window.setTimeout(() => fn(...args), delay);
        };
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[char]);
    }

    function endpoint(url, params = {}) {
        const next = new URL(url, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                next.searchParams.set(key, value);
            }
        });
        return next;
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            ...options,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }
        return data;
    }

    function initReveal() {
        const els = $$('.cp-reveal');
        if (!els.length) return;
        if (!('IntersectionObserver' in window)) {
            els.forEach((el) => el.classList.add('is-visible'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.12 });
        els.forEach((el) => io.observe(el));
    }

    function initTopbar() {
        const toggle = $('[data-cp-nav-toggle]');
        const nav = $('[data-cp-nav]');
        if (!toggle || !nav) return;
        toggle.addEventListener('click', () => {
            nav.classList.toggle('is-open');
        });
        document.addEventListener('click', (event) => {
            if (!nav.classList.contains('is-open')) return;
            if (nav.contains(event.target) || toggle.contains(event.target)) return;
            nav.classList.remove('is-open');
        });
    }

    function initProgress() {
        const bar = $('[data-cp-progress]');
        if (!bar) return;
        const update = () => {
            const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
            const percent = Math.min(100, Math.max(0, (window.scrollY / max) * 100));
            bar.style.width = `${percent}%`;
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
    }

    function highlightText(text, query) {
        if (!query || !text) return escapeHtml(text);
        const escapedQuery = query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
        const regex = new RegExp(`(${escapedQuery})`, 'gi');
        return escapeHtml(text).replace(regex, '<mark class="cp-highlight">$1</mark>');
    }

    function renderLiveResults(results, query = '') {
        if (!results.length) {
            return `<div class="cp-empty" style="min-height:7rem">${escapeHtml(cfg.lang === 'en' ? 'No results' : 'Nessun risultato')}</div>`;
        }
        return results.map((item) => {
            const hlTitle = highlightText(item.title, query);
            const hlDesc = highlightText((item.description || '').slice(0, 96), query);
            return `
                <a class="cp-live-item" href="${escapeHtml(item.url)}" style="--entry-accent:${escapeHtml(item.accent)}">
                    <span class="cp-live-thumb">
                        <img src="${escapeHtml(item.image)}" alt="" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove()">
                        <i class="fa-solid fa-book-open"></i>
                    </span>
                    <span>
                        <strong>${hlTitle}</strong>
                        <small>${escapeHtml(item.type_label)} · ${hlDesc}...</small>
                    </span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            `;
        }).join('');
    }

    function initLiveSearch() {
        $$('[data-cp-live-search]').forEach((form) => {
            const input = $('[data-cp-search-input]', form);
            const box = $('[data-cp-search-results]', form);
            if (!input || !box) return;

            const search = debounce(async () => {
                const q = input.value.trim();
                if (q.length < 2) {
                    if (state.searchAbort) state.searchAbort.abort();
                    box.hidden = true;
                    box.innerHTML = '';
                    return;
                }
                if (state.searchAbort) state.searchAbort.abort();
                state.searchAbort = new AbortController();
                box.hidden = false;
                box.innerHTML = `<div class="cp-live-item"><span class="cp-live-thumb cp-live-thumb--ghost"><i class="fa-solid fa-magnifying-glass"></i></span><span><strong>${escapeHtml(cfg.lang === 'en' ? 'Searching...' : 'Cerco...')}</strong><small>Cripsumpedia</small></span></div>`;
                try {
                    const data = await fetchJson(endpoint(cfg.searchEndpoint, {
                        q,
                        lang: cfg.lang,
                        limit: 7,
                    }), { signal: state.searchAbort.signal });
                    if (input.value.trim() !== q) return;
                    box.innerHTML = renderLiveResults(data.results || [], q);
                } catch (err) {
                    if (err.name === 'AbortError') return;
                    box.innerHTML = `<div class="cp-empty" style="min-height:7rem">${escapeHtml(err.message)}</div>`;
                }
            }, 240);

            input.addEventListener('input', search);
            input.addEventListener('focus', search);
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    box.hidden = true;
                    input.blur();
                }
            });
            form.addEventListener('submit', () => {
                box.hidden = true;
            });
            document.addEventListener('click', (event) => {
                if (!form.contains(event.target)) box.hidden = true;
            });
        });
    }

    function initCustomSelects() {
        $$('select').forEach((select) => {
            if (select.dataset.cpSelectReady === '1' || select.closest('.cp-select')) return;
            select.dataset.cpSelectReady = '1';
            select.classList.add('cp-native-select');

            const wrapper = document.createElement('div');
            wrapper.className = 'cp-select';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'cp-select__button';
            button.setAttribute('aria-haspopup', 'listbox');
            button.setAttribute('aria-expanded', 'false');
            const menu = document.createElement('div');
            menu.className = 'cp-select__menu';
            menu.setAttribute('role', 'listbox');
            menu.hidden = true;

            const currentLabel = () => select.options[select.selectedIndex]?.textContent?.trim() || '';
            const syncButton = () => {
                button.innerHTML = `<span>${escapeHtml(currentLabel())}</span><i class="fa-solid fa-chevron-down"></i>`;
                $$('.cp-select__option', menu).forEach((option) => {
                    option.classList.toggle('is-active', option.dataset.value === select.value);
                    option.setAttribute('aria-selected', option.dataset.value === select.value ? 'true' : 'false');
                });
            };

            Array.from(select.options).forEach((nativeOption) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'cp-select__option';
                option.dataset.value = nativeOption.value;
                option.setAttribute('role', 'option');
                option.textContent = nativeOption.textContent || '';
                option.addEventListener('click', () => {
                    select.value = nativeOption.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncButton();
                    menu.hidden = true;
                    button.setAttribute('aria-expanded', 'false');
                    const form = select.closest('form');
                    if (!select.getAttribute('onchange') && form?.classList.contains('cp-filter-bar')) {
                        window.setTimeout(() => form?.requestSubmit ? form.requestSubmit() : form?.submit(), 0);
                    }
                });
                menu.appendChild(option);
            });

            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);
            wrapper.appendChild(button);
            wrapper.appendChild(menu);
            syncButton();

            const positionMenu = () => {
                const rect = button.getBoundingClientRect();
                const menuW = Math.max(rect.width, 180);
                let menuLeft = rect.left + window.scrollX;
                if (rect.left + menuW > window.innerWidth - 8) {
                    menuLeft = window.scrollX + window.innerWidth - menuW - 8;
                }
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;
                const menuMaxH = 260;
                let menuTop, openUpward = false;
                if (spaceBelow < menuMaxH && spaceAbove > spaceBelow) {
                    openUpward = true;
                    menuTop = rect.top + window.scrollY - Math.min(menuMaxH, spaceAbove - 8);
                } else {
                    menuTop = rect.bottom + window.scrollY + 6;
                }
                menu.style.cssText = `position:absolute;top:${menuTop}px;left:${Math.max(8 + window.scrollX, menuLeft)}px;width:${menuW}px;z-index:99999;right:auto;max-height:${openUpward ? spaceAbove - 8 : Math.min(menuMaxH, spaceBelow - 8)}px;`;
            };

            button.addEventListener('click', () => {
                const open = menu.hidden;
                $$('.cp-select__menu').forEach((otherMenu) => {
                    if (otherMenu !== menu) { otherMenu.hidden = true; otherMenu.style.cssText = ''; }
                });
                $$('.cp-select__button').forEach((otherButton) => {
                    if (otherButton !== button) otherButton.setAttribute('aria-expanded', 'false');
                });
                menu.hidden = !open;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (!menu.hidden) {
                    // Move to body to escape any stacking context created by backdrop-filter
                    if (menu.parentNode !== document.body) document.body.appendChild(menu);
                    positionMenu();
                }
            });

            select.addEventListener('change', syncButton);
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('.cp-select') || event.target.closest('.cp-select__menu')) return;
            $$('.cp-select__menu').forEach((menu) => { menu.hidden = true; menu.style.cssText = ''; });
            $$('.cp-select__button').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            $$('.cp-select__menu').forEach((menu) => { menu.hidden = true; menu.style.cssText = ''; });
            $$('.cp-select__button').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        });
        window.addEventListener('scroll', () => {
            $$('.cp-select__menu:not([hidden])').forEach((menu) => { menu.hidden = true; menu.style.cssText = ''; });
            $$('.cp-select__button').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        }, { passive: true });
    }

    function initRandom() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-cp-random]');
            if (!button) return;
            button.disabled = true;
            try {
                const type = button.dataset.cpRandomType || '';
                const data = await fetchJson(endpoint(cfg.searchEndpoint, {
                    action: 'random',
                    lang: cfg.lang,
                    type,
                }));
                if (data.url) window.location.href = data.url;
            } catch (err) {
                toast(err.message, true);
            } finally {
                button.disabled = false;
            }
        });
    }

    function positionHover(card, target) {
        const rect = target.getBoundingClientRect();
        const gap = 12;
        const width = Math.min(340, window.innerWidth - 24);
        let left = rect.left;
        if (left + width > window.innerWidth - 12) left = window.innerWidth - width - 12;
        let top = rect.bottom + gap;
        if (top + 260 > window.innerHeight) top = rect.top - 260 - gap;
        card.style.left = `${Math.max(12, left)}px`;
        card.style.top = `${Math.max(12, top)}px`;
    }

    function initHoverPreviews() {
        const card = $('[data-cp-hover-card]');
        if (!card) return;
        let activeLink = null;

        document.addEventListener('mouseover', async (event) => {
            const link = event.target.closest('.cp-lore-link[data-lore-id]');
            if (!link) return;
            activeLink = link;
            const id = link.dataset.loreId;
            positionHover(card, link);
            card.hidden = false;
            card.innerHTML = '<small>Loading preview...</small>';
            try {
                let data = state.previewCache.get(id);
                if (!data) {
                    data = await fetchJson(endpoint(cfg.searchEndpoint, {
                        action: 'preview',
                        id,
                        lang: cfg.lang,
                    }));
                    state.previewCache.set(id, data);
                }
                if (activeLink !== link) return;
                const preview = data.html || {};
                card.innerHTML = `
                    <img src="${escapeHtml(preview.image || '')}" alt="" loading="lazy" onerror="this.remove()">
                    <small>${escapeHtml(preview.type || '')}</small>
                    <strong>${escapeHtml(preview.title || '')}</strong>
                    <small>${escapeHtml(preview.description || '')}</small>
                `;
                positionHover(card, link);
            } catch (err) {
                card.innerHTML = `<small>${escapeHtml(err.message)}</small>`;
            }
        });

        document.addEventListener('mouseout', (event) => {
            const link = event.target.closest('.cp-lore-link[data-lore-id]');
            if (!link) return;
            activeLink = null;
            card.hidden = true;
        });
    }

    function initEntryActions() {
        const share = $('[data-cp-share]');
        if (share) {
            share.addEventListener('click', async () => {
                try {
                    if (navigator.share) {
                        await navigator.share({ title: document.title, url: window.location.href });
                    } else {
                        await navigator.clipboard.writeText(window.location.href);
                        toast(cfg.lang === 'en' ? 'Link copied' : 'Link copiato');
                    }
                } catch {
                    toast(cfg.lang === 'en' ? 'Share cancelled' : 'Condivisione annullata');
                }
            });
        }

        const focus = $('[data-cp-focus]');
        if (focus) {
            focus.addEventListener('click', () => {
                document.body.classList.toggle('cp-focus-mode');
            });
        }

        document.addEventListener('click', async (event) => {
            const reaction = event.target.closest('[data-cp-reaction]');
            const favorite = event.target.closest('[data-cp-favorite]');
            const button = reaction || favorite;
            if (!button) return;
            const body = new FormData();
            body.set('action', reaction ? 'react' : 'favorite');
            body.set('entry_id', button.dataset.entryId || '');
            if (reaction) body.set('reaction', button.dataset.reaction || 'hype');
            button.disabled = true;
            try {
                const data = await fetchJson(cfg.saveEndpoint, { method: 'POST', body });
                button.classList.toggle('is-active', data.state === 'added');
                toast(data.state === 'removed'
                    ? (cfg.lang === 'en' ? 'Removed' : 'Rimosso')
                    : (cfg.lang === 'en' ? 'Added' : 'Aggiunto'));
            } catch (err) {
                toast(err.message, true);
            } finally {
                button.disabled = false;
            }
        });
    }

    function markdownPreview(markdown) {
        const blocks = String(markdown || '').replace(/\r\n/g, '\n').trim().split(/\n{2,}/);
        return blocks.map((block) => {
            const raw = block.trim();
            if (!raw) return '';
            const lines = raw.split('\n');
            const inline = (value) => escapeHtml(value)
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[^*])\*([^*]+)\*(?!\*)/g, '$1<em>$2</em>')
                .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

            const spoiler = raw.match(/^\[spoiler(?::([^\]]+))?\]([\s\S]*)\[\/spoiler\]$/i);
            if (spoiler) {
                return `<details class="cp-spoiler" open><summary>${escapeHtml(spoiler[1] || 'Spoiler')}</summary><div><p>${inline(spoiler[2])}</p></div></details>`;
            }
            const image = raw.match(/^!\[([^\]]*)\]\((https?:\/\/[^)]+|\/[^)]+)\)$/);
            if (image) return `<figure class="cp-content-image"><img src="${escapeHtml(image[2])}" alt="${escapeHtml(image[1])}"><figcaption>${escapeHtml(image[1])}</figcaption></figure>`;
            const heading = raw.match(/^(#{1,4})\s+(.+)$/);
            if (heading) {
                const level = Math.min(4, heading[1].length + 1);
                return `<h${level}>${inline(heading[2])}</h${level}>`;
            }
            if (lines.every((line) => /^\s*>/.test(line))) {
                return `<blockquote>${lines.map((line) => inline(line.replace(/^\s*>\s?/, ''))).join('<br>')}</blockquote>`;
            }
            if (lines.every((line) => /^\s*[-*]\s+/.test(line))) {
                return `<ul>${lines.map((line) => `<li>${inline(line.replace(/^\s*[-*]\s+/, ''))}</li>`).join('')}</ul>`;
            }
            return `<p>${lines.map(inline).join('<br>')}</p>`;
        }).join('');
    }

    function initEditorTabs() {
        const editor = $('[data-cp-editor-form]');
        if (!editor) return;
        const buttons = $$('[data-cp-editor-tab]', editor);
        const panes = $$('[data-cp-editor-pane]', editor);
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.dataset.cpEditorTab;
                buttons.forEach((item) => item.classList.toggle('is-active', item === button));
                panes.forEach((pane) => pane.classList.toggle('is-active', pane.dataset.cpEditorPane === key));
            });
        });
    }

    function slugify(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function initEditorPreview() {
        const form = $('[data-cp-editor-form]');
        if (!form) return;
        const title = $('[name="title"]', form);
        const slug = $('[name="slug"]', form);
        const description = $('[name="description"]', form);
        const content = $('[data-cp-markdown-source]', form);
        const previewCard = $('[data-cp-editor-preview]');
        const previewMarkdown = $('[data-cp-markdown-preview]');
        const refresh = $('[data-cp-refresh-preview]');

        let touchedSlug = Boolean(slug && slug.value.trim());
        if (slug) slug.addEventListener('input', () => { touchedSlug = true; });
        if (title && slug) {
            title.addEventListener('input', () => {
                if (!touchedSlug) slug.value = slugify(title.value);
            });
        }

        const update = () => {
            if (previewCard) {
                previewCard.innerHTML = `
                    <h2>${escapeHtml(title?.value || (cfg.lang === 'en' ? 'New page' : 'Nuova pagina'))}</h2>
                    <p>${escapeHtml(description?.value || '')}</p>
                `;
            }
            if (previewMarkdown) previewMarkdown.innerHTML = markdownPreview(content?.value || '');
        };

        [title, description, content].filter(Boolean).forEach((input) => {
            input.addEventListener('input', debounce(update, 120));
        });
        if (refresh) refresh.addEventListener('click', update);
        update();
    }

    function updateRelationsHidden(form) {
        const hidden = $('[data-cp-relations-json]', form);
        if (!hidden) return;
        hidden.value = JSON.stringify(state.relations.map((item) => ({
            target_id: item.target_id || item.id,
            relation_type: item.relation_type || 'related',
            relation_label: item.relation_label || '',
            relation_label_en: item.relation_label_en || '',
            weight: Number(item.weight || 50),
            bidirectional: item.bidirectional !== false,
        })));
    }

    function renderRelations(form) {
        const list = $('[data-cp-relation-selected]', form);
        if (!list) return;
        if (!state.relations.length) {
            list.innerHTML = '<div class="cp-empty" style="min-height:7rem">Nessuna relazione collegata.</div>';
            updateRelationsHidden(form);
            return;
        }
        list.innerHTML = state.relations.map((item, index) => `
            <div class="cp-relation-chip" data-index="${index}">
                <img src="${escapeHtml(item.image || '/img/Susremaster.png')}" alt="" loading="lazy" onerror="this.remove()">
                <span>
                    <strong>${escapeHtml(item.title || item.name || 'Entry')}</strong>
                    <small>${escapeHtml(item.type || '')}</small>
                    <input type="text" value="${escapeHtml(item.relation_label || '')}" placeholder="relazione, origine, rivalita..." data-cp-relation-label>
                </span>
                <button type="button" data-cp-remove-relation><i class="fa-solid fa-xmark"></i></button>
            </div>
        `).join('');
        updateRelationsHidden(form);
    }

    function initRelationBuilder() {
        const form = $('[data-cp-editor-form]');
        const builder = $('[data-cp-relation-builder]', form || document);
        if (!form || !builder) return;
        const input = $('[data-cp-relation-search]', builder);
        const results = $('[data-cp-relation-results]', builder);

        renderRelations(form);

        if (input && results) {
            const search = debounce(async () => {
                const q = input.value.trim();
                if (q.length < 2) {
                    results.hidden = true;
                    results.innerHTML = '';
                    return;
                }
                results.hidden = false;
                results.innerHTML = `<div class="cp-relation-result"><span><strong>${escapeHtml(cfg.lang === 'en' ? 'Searching...' : 'Cerco...')}</strong></span></div>`;
                try {
                    const data = await fetchJson(endpoint(cfg.relationsEndpoint, {
                        action: 'search',
                        q,
                        lang: cfg.lang,
                        exclude: $('[name="id"]', form)?.value || '',
                    }));
                    const items = data.results || [];
                    results.innerHTML = items.length ? items.map((item) => `
                        <div class="cp-relation-result">
                            <img src="${escapeHtml(item.image)}" alt="" loading="lazy" onerror="this.remove()">
                            <span><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(item.type_label)}</small></span>
                            <button type="button" data-cp-add-relation data-entry='${escapeHtml(JSON.stringify(item))}'>${escapeHtml(cfg.lang === 'en' ? 'Add' : 'Aggiungi')}</button>
                        </div>
                    `).join('') : '<div class="cp-empty" style="min-height:7rem">Nessun risultato.</div>';
                } catch (err) {
                    results.innerHTML = `<div class="cp-empty" style="min-height:7rem">${escapeHtml(err.message)}</div>`;
                }
            }, 250);
            input.addEventListener('input', search);
        }

        builder.addEventListener('click', (event) => {
            const add = event.target.closest('[data-cp-add-relation]');
            const remove = event.target.closest('[data-cp-remove-relation]');
            if (add) {
                try {
                    const item = JSON.parse(add.dataset.entry || '{}');
                    if (!state.relations.some((rel) => Number(rel.target_id || rel.id) === Number(item.id))) {
                        state.relations.push({
                            target_id: item.id,
                            title: item.title,
                            type: item.type,
                            image: item.image,
                            relation_type: 'related',
                            relation_label: '',
                            weight: 50,
                            bidirectional: true,
                        });
                    }
                    renderRelations(form);
                    results.hidden = true;
                    input.value = '';
                } catch {
                    toast('Relazione non valida.', true);
                }
            }
            if (remove) {
                const row = remove.closest('[data-index]');
                const index = Number(row?.dataset.index || -1);
                if (index >= 0) {
                    state.relations.splice(index, 1);
                    renderRelations(form);
                }
            }
        });

        builder.addEventListener('input', (event) => {
            const label = event.target.closest('[data-cp-relation-label]');
            if (!label) return;
            const row = label.closest('[data-index]');
            const index = Number(row?.dataset.index || -1);
            if (index >= 0 && state.relations[index]) {
                state.relations[index].relation_label = label.value;
                updateRelationsHidden(form);
            }
        });
    }

    function initUploads() {
        const zone = $('[data-cp-upload-zone]');
        const input = $('[data-cp-upload-input]');
        if (!zone || !input) return;

        const upload = async (files) => {
            if (!files.length) return;
            const body = new FormData();
            body.set('action', 'upload_media');
            body.set('csrf_token', cfg.csrf || '');
            Array.from(files).forEach((file) => body.append('files[]', file));
            zone.classList.add('is-drag');
            try {
                const data = await fetchJson(cfg.saveEndpoint, { method: 'POST', body });
                const urls = data.files || [];
                if (urls[0]) {
                    await navigator.clipboard?.writeText(urls[0]).catch(() => {});
                    toast(`Upload OK: ${urls[0]}`);
                } else {
                    toast('Nessun file caricato.', true);
                }
            } catch (err) {
                toast(err.message, true);
            } finally {
                zone.classList.remove('is-drag');
                input.value = '';
            }
        };

        ['dragenter', 'dragover'].forEach((name) => {
            zone.addEventListener(name, (event) => {
                event.preventDefault();
                zone.classList.add('is-drag');
            });
        });
        ['dragleave', 'drop'].forEach((name) => {
            zone.addEventListener(name, (event) => {
                event.preventDefault();
                if (name === 'drop') upload(event.dataTransfer.files);
                zone.classList.remove('is-drag');
            });
        });
        input.addEventListener('change', () => upload(input.files));
    }

    function initEditorSubmit() {
        const form = $('[data-cp-editor-form]');
        if (!form) return;
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            updateRelationsHidden(form);
            const submit = form.querySelector('[type="submit"]');
            const body = new FormData(form);
            submit.disabled = true;
            try {
                const data = await fetchJson(cfg.saveEndpoint, { method: 'POST', body });
                if (data.id) {
                    const idInput = $('[name="id"]', form);
                    if (idInput) idInput.value = data.id;
                    const next = new URL(window.location.href);
                    next.searchParams.set('id', data.id);
                    window.history.replaceState({}, '', next);
                }
                toast(data.message || 'Salvato.');
            } catch (err) {
                toast(err.message, true);
            } finally {
                submit.disabled = false;
            }
        });
    }

    function initAdminDelete() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-cp-delete-entry]');
            if (!button) return;
            const id = button.dataset.cpDeleteEntry;
            if (!id || !window.confirm('Eliminare questa voce?')) return;
            const body = new FormData();
            body.set('action', 'delete_entry');
            body.set('csrf_token', cfg.csrf || '');
            body.set('id', id);
            button.disabled = true;
            try {
                await fetchJson(cfg.saveEndpoint, { method: 'POST', body });
                button.closest('[data-entry-id]')?.remove();
                toast('Voce eliminata.');
            } catch (err) {
                toast(err.message, true);
            } finally {
                button.disabled = false;
            }
        });
    }

    function initViewToggle() {
        const gridBtn = $('[data-cp-view-toggle="grid"]');
        const listBtn = $('[data-cp-view-toggle="list"]');
        const grid = $('[data-cp-card-grid]');
        if (!grid) return;

        const setMode = (mode) => {
            if (mode === 'list') {
                grid.classList.add('is-list');
                listBtn?.classList.add('is-active');
                gridBtn?.classList.remove('is-active');
            } else {
                grid.classList.remove('is-list');
                gridBtn?.classList.add('is-active');
                listBtn?.classList.remove('is-active');
            }
            localStorage.setItem('cp-view-mode', mode);
        };

        const savedMode = localStorage.getItem('cp-view-mode') || 'grid';
        setMode(savedMode);

        gridBtn?.addEventListener('click', () => setMode('grid'));
        listBtn?.addEventListener('click', () => setMode('list'));
    }

    document.addEventListener('DOMContentLoaded', () => {
        initReveal();
        initTopbar();
        initProgress();
        initLiveSearch();
        initCustomSelects();
        initRandom();
        initHoverPreviews();
        initEntryActions();
        initEditorTabs();
        initEditorPreview();
        initRelationBuilder();
        initUploads();
        initEditorSubmit();
        initAdminDelete();
        initViewToggle();
    });
})();
