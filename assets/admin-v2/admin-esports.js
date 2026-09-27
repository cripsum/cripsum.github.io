/*
 * Pannello admin: Team esports (OHPY, Counter-Strike 2).
 *
 * Due schede: i player (lista con stato, riordino, modifica in una
 * finestra) e il team (testata, colori, social, palmares). Usa gli
 * strumenti dei form dello shop (A.forms, da admin-shop.js: va caricato
 * dopo) e l'API /api/admin/esports.php.
 *
 * I player nascono nascosti: si preparano, si guardano in anteprima dalla
 * loro scheda (lo staff la vede) e poi si mettono in line-up.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A || !A.forms) return;

    const { api, confirmBox, showToast, thumb, setLoading, emptyState } = A;
    const { fields, sectionTitle, slugify, bindForm, formModal, moveButtons, grip, bindReorder, tabs, PRESETS } = A.forms;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const EP = 'esports.php';
    const get = (params = {}) => api(`${EP}?${new URLSearchParams(params)}`);
    const post = (action, body = {}) => api(EP, { method: 'POST', body: { action, ...body } });

    const state = { tab: 'player', stato: '' };

    const STATES = [
        ['titolare', 'Titolare', 'in line-up'],
        ['riserva', 'Riserva', 'in panchina'],
        ['staff', 'Staff', 'coach, analisti, manager'],
        ['ex', 'Ex', 'nella sezione degli ex'],
        ['nascosto', 'Nascosto', 'lo vede solo lo staff'],
    ];

    const TEAM_PRESETS = [['CS2', '#f5a524', '#07080c', '#1c1307'], ...PRESETS];

    const matches = (...texts) => {
        const q = String(A.getQuery() || '').toLowerCase().trim();
        return !q || texts.some((t) => String(t || '').toLowerCase().includes(q));
    };

    const debounce = (fn, wait = 350) => {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
    };

    const seconds = (value) => {
        const total = Math.max(0, Math.floor(Number(value) || 0));
        return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
    };

    /* Fascia del Premier: stessi colori della pagina (esports_premier). */
    const premierColor = (rating) => {
        const tiers = [[30000, '#e4ae39'], [25000, '#eb4b4b'], [20000, '#d32ce6'], [15000, '#8847ff'], [10000, '#4b69ff'], [5000, '#5e98d9']];
        const tier = tiers.find(([min]) => rating >= min);
        return tier ? tier[1] : '#b0c3d9';
    };

    /* Un file di /audio/ con spazi e virgole nel nome, pronto per <audio>. */
    const audioSrc = (value) => {
        const v = String(value || '').trim();
        if (/^https:\/\//i.test(v)) return v;
        if (!v.startsWith('/audio/')) return '';
        try {
            return v.split('/').map((part) => encodeURIComponent(decodeURIComponent(part))).join('/');
        } catch (_) {
            return '';
        }
    };

    /* ── Lista dei player ───────────────────────────────────────────── */

    const playersTable = (box, ctx, reload) => {
        let rows = ctx.players.filter((p) => matches(p.nickname, p.nome_reale, p.slug, p.utente, p.musica_titolo));
        if (state.stato) rows = rows.filter((p) => p.stato === state.stato);
        const canSort = !state.stato && !A.getQuery();
        const last = rows.length - 1;
        const roleOf = (key) => ctx.roles.find((r) => r.key === key) || { label: key, icon: 'fa-solid fa-user' };
        const stateOptions = (current) => STATES.map(([value, label]) => `<option value="${value}" ${current === value ? 'selected' : ''}>${label}</option>`).join('');

        box.innerHTML = `
            <div class="shop-admin-bar">
                <div class="admin-toolbar-actions">
                    <select class="admin-input" data-filter-stato aria-label="Filtra per stato">
                        <option value="">Tutti gli stati</option>
                        ${stateOptions(state.stato)}
                    </select>
                </div>
                <button type="button" class="admin-btn admin-btn--primary" data-new-player><i class="fa-solid fa-plus"></i> Nuovo player</button>
            </div>
            <p class="admin-muted esports-admin-note">Sulla pagina i player stanno in gruppi (line-up, panchina e staff, ex); dentro ogni gruppo vale l'ordine di questa lista. Trascina le righe o usa le frecce.</p>
            ${rows.length ? `
            <table class="admin-table">
                <thead><tr><th>Player</th><th>Ruolo</th><th>Musica</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                    ${rows.map((p, i) => {
                        const role = roleOf(p.ruolo);
                        return `
                        <tr ${canSort ? `draggable="true" data-shop-row="${Number(p.id)}"` : ''} class="${p.stato === 'nascosto' ? 'is-off' : ''}">
                            <td data-label="Player"><div class="admin-name-cell">${grip(canSort)}${thumb(p.foto, 'fa-solid fa-user')}<div>
                                <div class="admin-row-title">${e(p.nickname)}${p.nazionalita ? ` <span class="esports-admin-code">${e(p.nazionalita)}</span>` : ''}</div>
                                <div class="admin-row-sub"><a href="/it/ohpy/${e(p.slug)}" target="_blank" rel="noopener">/it/ohpy/${e(p.slug)} <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
                            </div></div></td>
                            <td data-label="Ruolo"><span class="esports-admin-role"><i class="${e(role.icon)}"></i> ${e(p.ruolo_label || role.label)}</span></td>
                            <td data-label="Musica">${p.musica_audio
                                ? `<span class="admin-badge admin-badge--success"><i class="fa-solid fa-music"></i>${e(p.musica_titolo || 'Sì')}</span>`
                                : '<span class="admin-muted">—</span>'}</td>
                            <td data-label="Stato">
                                <select class="admin-input shop-admin-state" data-state-player="${Number(p.id)}" aria-label="Stato di ${e(p.nickname)}">${stateOptions(p.stato)}</select>
                            </td>
                            <td data-label="Azioni"><div class="admin-row-actions admin-row-actions--slides">
                                ${canSort ? moveButtons(Number(p.id), i, last, false) : ''}
                                <button class="admin-btn admin-btn--small" data-edit-player="${Number(p.id)}"><i class="fa-solid fa-pen"></i> Modifica</button>
                                <button class="admin-btn admin-btn--small admin-btn--danger" data-delete-player="${Number(p.id)}" title="Elimina" aria-label="Elimina ${e(p.nickname)}"><i class="fa-solid fa-trash"></i></button>
                            </div></td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>` : emptyState('fa-solid fa-users', 'Nessun player', ctx.players.length
                ? 'Nessun risultato con questo filtro.'
                : 'Aggiungi il primo con «Nuovo player»: nasce nascosto, lo provi dalla sua scheda e poi lo metti in line-up.')}`;

        const find = (id) => ctx.players.find((p) => Number(p.id) === Number(id));

        $('[data-filter-stato]', box).addEventListener('change', (event) => {
            state.stato = event.target.value;
            playersTable(box, ctx, reload);
        });
        $('[data-new-player]', box).addEventListener('click', () => playerForm(null, ctx, reload));
        $$('[data-edit-player]', box).forEach((b) => b.addEventListener('click', () => playerForm(find(b.dataset.editPlayer), ctx, reload)));

        $$('[data-state-player]', box).forEach((select) => {
            const previous = select.value;
            select.addEventListener('change', async () => {
                try {
                    const res = await post('set_state', { id: select.dataset.statePlayer, stato: select.value });
                    showToast(res.message);
                    reload();
                } catch (error) {
                    select.value = previous;
                    showToast(error.message, true);
                }
            });
        });

        $$('[data-delete-player]', box).forEach((b) => b.addEventListener('click', () => {
            const p = find(b.dataset.deletePlayer);
            confirmBox('Eliminare il player?', `<p class="admin-muted">«${e(p.nickname)}» sparisce dalla pagina e la sua scheda smette di funzionare. Foto e musica caricate si cancellano, se nessun altro le usa. Se ha solo lasciato il team, mettilo «Ex».</p>`, async () => {
                await post('delete_player', { id: p.id });
                showToast('Player eliminato.');
                reload();
            });
        }));

        if (canSort && rows.length) bindReorder(box, rows.map((p) => Number(p.id)), EP, 'reorder', reload);
    };

    /* ── Form del player ────────────────────────────────────────────── */

    const playerPreview = (form, ctx) => {
        const box = $('[data-player-preview]', form);
        if (!box) return;
        const val = (name) => form.elements[name]?.value?.trim() || '';
        const role = ctx.roles.find((r) => r.key === val('ruolo'));
        const accent = form.elements.colore_proprio?.checked ? val('colore_accento') : (ctx.team.colore_accento || '#f5a524');
        const photo = A.assetUrl(val('foto'));
        const premier = Number(val('premier_rating').replace(/[.,\s']/g, '')) || 0;
        const nickname = val('nickname') || 'Nickname';

        box.innerHTML = `
            <div class="esports-admin-card" style="--a: ${e(accent || '#f5a524')}">
                ${photo ? `<img src="${e(photo)}" alt="">` : `<span class="esports-admin-card__initial">${e(nickname.charAt(0).toUpperCase())}</span>`}
                <div class="esports-admin-card__top">
                    ${val('nazionalita') ? `<span class="esports-admin-code">${e(val('nazionalita'))}</span>` : ''}
                    ${val('musica_audio') ? '<i class="fa-solid fa-music"></i>' : ''}
                    ${premier ? `<b style="--t: ${premierColor(premier)}">${premier >= 1000 ? `${Math.floor(premier / 1000)}<small>,${String(premier % 1000).padStart(3, '0')}</small>` : premier}</b>` : ''}
                </div>
                <div class="esports-admin-card__body">
                    <small><i class="${e(role?.icon || 'fa-solid fa-user')}"></i> ${e(val('ruolo_label') || role?.label || '')}</small>
                    <strong>${e(nickname)}</strong>
                    ${val('nome_reale') ? `<span>${e(val('nome_reale'))}</span>` : ''}
                </div>
            </div>
            ${val('stato') === 'nascosto' ? '<small class="shop-admin-help"><i class="fa-solid fa-eye-slash"></i> Nascosto: sulla pagina lo vede solo lo staff.</small>' : ''}`;

        const slugPreview = $('[data-slug-preview]', form);
        if (slugPreview) slugPreview.textContent = slugify(val('slug') || val('nickname')).slice(0, 60) || '...';
    };

    const musicFields = (values) => `
        <div class="admin-field admin-field--full esports-admin-kit" data-kit-search>
            <label for="esKitQuery">Cerca il music kit</label>
            <div class="esports-admin-kit__box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="esKitQuery" placeholder="es. Daniel Sadowski, Crimson Assault" autocomplete="off" data-kit-query>
            </div>
            <div class="esports-admin-kit__results" data-kit-results hidden></div>
            <small class="shop-admin-help">Dal catalogo pubblico dei kit di CS2: compila titolo, artista e copertina. Il file audio lo carichi tu qui sotto.</small>
        </div>
        ${fields([
            { name: 'musica_titolo', label: 'Titolo del brano', max: 120, placeholder: 'es. Crimson Assault' },
            { name: 'musica_artista', label: 'Artista', max: 120, placeholder: 'es. Daniel Sadowski' },
            { name: 'musica_cover', label: 'Copertina del kit', type: 'image', full: true },
        ], values)}
        <div class="admin-field admin-field--full" data-audio-field>
            <label for="esAudio">File audio</label>
            <div class="admin-input-group">
                <input type="text" id="esAudio" name="musica_audio" value="${e(values.musica_audio || '')}" placeholder="/audio/esports/brano.mp3 oppure https://..." data-audio-input>
                <label class="admin-btn shop-admin-upload"><i class="fa-solid fa-upload"></i> Carica<input type="file" accept="audio/mpeg,audio/ogg,audio/mp4,audio/aac,audio/wav,.mp3,.ogg,.m4a,.aac,.wav" data-upload-audio hidden></label>
            </div>
            <audio class="esports-admin-audio" controls preload="none" data-audio-preview hidden></audio>
            <small class="shop-admin-help">mp3, ogg, m4a, aac o wav, fino a 35 MB. Un brano sostituito si cancella dal sito quando salvi.</small>
        </div>
        <div class="admin-field">
            <label for="esStart">Parti da (secondi) <span class="shop-admin-help" data-start-label>${seconds(values.musica_inizio)}</span></label>
            <div class="admin-input-group">
                <input type="number" id="esStart" name="musica_inizio" min="0" max="3600" step="1" inputmode="numeric" value="${Number(values.musica_inizio || 0)}">
                <button type="button" class="admin-btn" data-audio-here title="Usa il punto a cui è arrivata l'anteprima"><i class="fa-solid fa-location-crosshairs"></i> Parti da qui</button>
            </div>
            <small class="shop-admin-help">Per far partire la musica dal drop: ascolta l'anteprima, fermala nel punto giusto e premi «Parti da qui».</small>
        </div>
        <div class="admin-field">
            <label for="esVolume">Volume di partenza: <output data-volume-out>${Number(values.musica_volume ?? 60)}</output>%</label>
            <input type="range" id="esVolume" name="musica_volume" min="0" max="100" step="1" value="${Number(values.musica_volume ?? 60)}" class="esports-admin-range">
            <small class="shop-admin-help">Chi ascolta può cambiarlo dalla scheda.</small>
        </div>`;

    let kitCatalog = null;

    const loadKits = () => {
        if (!kitCatalog) {
            kitCatalog = get({ action: 'music_kits' }).then((res) => res.kits || []).catch((error) => {
                kitCatalog = null;
                throw error;
            });
        }
        return kitCatalog;
    };

    const normalize = (value) => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    const bindKitSearch = (form) => {
        const wrap = $('[data-kit-search]', form);
        if (!wrap) return;
        const input = $('[data-kit-query]', wrap);
        const results = $('[data-kit-results]', wrap);
        let kits = [];

        const render = () => {
            const words = normalize(input.value).trim().split(/\s+/).filter(Boolean);
            if (!words.length) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            const found = kits.filter((kit) => {
                const hay = normalize(`${kit.artist} ${kit.title}`);
                return words.every((word) => hay.includes(word));
            }).slice(0, 12);

            results.hidden = false;
            results.innerHTML = found.length
                ? found.map((kit) => `
                    <button type="button" class="esports-admin-kit__item" data-kit="${e(kit.id)}" style="--c: ${e(kit.color || '#4b69ff')}">
                        ${kit.image ? `<img src="${e(kit.image)}" alt="" loading="lazy">` : '<i class="fa-solid fa-compact-disc"></i>'}
                        <span><strong>${e(kit.title)}</strong><small>${e(kit.artist)}</small></span>
                    </button>`).join('')
                : '<p class="admin-muted">Nessun kit con questo nome.</p>';
        };

        const ensureKits = async () => {
            if (kits.length) return true;
            results.hidden = false;
            results.innerHTML = '<p class="admin-muted">Carico il catalogo dei kit...</p>';
            try {
                kits = await loadKits();
                return true;
            } catch (error) {
                results.innerHTML = `<p class="admin-danger-text">${e(error.message)}</p>`;
                return false;
            }
        };

        input.addEventListener('focus', async () => {
            if (await ensureKits()) render();
        });
        input.addEventListener('input', async () => {
            if (await ensureKits()) render();
        });
        input.addEventListener('keydown', (event) => {
            // Invio non deve salvare il form, Esc chiude i risultati e non la finestra.
            if (event.key === 'Enter') event.preventDefault();
            if (event.key === 'Escape' && !results.hidden) {
                event.stopPropagation();
                results.hidden = true;
            }
        });

        results.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-kit]');
            if (!button) return;
            const kit = kits.find((k) => k.id === button.dataset.kit);
            if (!kit) return;

            form.elements.musica_titolo.value = kit.title;
            form.elements.musica_artista.value = kit.artist;
            input.value = kit.artist ? `${kit.artist}, ${kit.title}` : kit.title;
            results.hidden = true;
            form.dispatchEvent(new Event('input'));

            if (!kit.image) {
                showToast('Kit scelto: ora carica il file audio.');
                return;
            }

            try {
                showToast('Copio la copertina del kit...');
                const res = await post('import_cover', { image: kit.image, kit: kit.id });
                const cover = form.elements.musica_cover;
                cover.value = res.url;
                A.trackUpload?.(res.url);
                cover.dispatchEvent(new Event('input', { bubbles: true }));
                showToast('Kit scelto: ora carica il file audio.');
            } catch (error) {
                showToast(error.message, true);
            }
        });
    };

    const bindMusic = (form) => {
        const input = $('[data-audio-input]', form);
        const upload = $('[data-upload-audio]', form);
        const preview = $('[data-audio-preview]', form);
        const start = form.elements.musica_inizio;
        const volume = form.elements.musica_volume;
        const volumeOut = $('[data-volume-out]', form);
        const startLabel = $('[data-start-label]', form);
        const here = $('[data-audio-here]', form);
        if (!input || !preview) return;

        const setPreview = () => {
            const src = audioSrc(input.value);
            preview.hidden = !src;
            if (!src) {
                if (preview.getAttribute('src')) {
                    preview.pause();
                    preview.removeAttribute('src');
                    preview.dataset.src = '';
                }
                return;
            }
            if (preview.dataset.src === src) return;
            preview.dataset.src = src;
            preview.src = src;
        };

        const showStart = () => {
            if (startLabel) startLabel.textContent = seconds(start.value);
        };

        preview.addEventListener('loadedmetadata', () => {
            const s = Number(start.value) || 0;
            if (s > 0 && s < preview.duration) preview.currentTime = s;
        });

        preview.addEventListener('error', () => {
            if (preview.getAttribute('src')) showToast('Anteprima: il file audio non si apre.', true);
        });

        input.addEventListener('change', setPreview);
        input.addEventListener('input', debounce(setPreview));

        volume.addEventListener('input', () => {
            volumeOut.textContent = volume.value;
            preview.volume = Number(volume.value) / 100;
        });

        start.addEventListener('input', showStart);
        start.addEventListener('change', () => {
            const s = Number(start.value) || 0;
            if (preview.readyState > 0 && s < preview.duration) preview.currentTime = s;
        });

        here.addEventListener('click', () => {
            if (!preview.getAttribute('src') || preview.readyState === 0) {
                showToast('Fai partire l\'anteprima e fermala nel punto giusto.', true);
                return;
            }
            start.value = String(Math.floor(preview.currentTime));
            showStart();
            showToast(`La musica partirà da ${seconds(start.value)}.`);
        });

        upload.addEventListener('change', async () => {
            const file = upload.files?.[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('type', 'audio');
            fd.append('folder', 'esports');
            try {
                showToast('Caricamento audio...');
                const res = await api('upload_media.php', { method: 'POST', body: fd });
                input.value = res.url;
                A.trackUpload?.(res.url);
                setPreview();
                form.dispatchEvent(new Event('input'));
                showToast('Audio caricato: scegli da dove farlo partire.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                upload.value = '';
            }
        });

        // Chiusa la finestra, l'anteprima non deve continuare a suonare.
        $('#adminModal')?.addEventListener('hidden.bs.modal', () => preview.pause(), { once: true });

        preview.volume = Number(volume.value) / 100;
        setPreview();
    };

    const playerForm = (item, ctx, reload) => {
        const values = item
            ? {
                ...item,
                statistiche: item.statistiche_testo || '',
                colore_accento: item.colore_accento || ctx.team.colore_accento || '#f5a524',
            }
            : { stato: 'nascosto', ruolo: 'rifler', musica_volume: 60, musica_inizio: 0, colore_accento: ctx.team.colore_accento || '#f5a524' };

        const countries = [['', '— nessuna —'], ...ctx.countries.map(([code, name]) => [code, `${name} (${code})`])];
        if (values.nazionalita && !ctx.countries.some(([code]) => code === values.nazionalita)) {
            countries.push([values.nazionalita, values.nazionalita]);
        }

        const html = `
            <div class="admin-field--full shop-admin-split">
                <div class="admin-form-grid">
                    ${fields([
                        sectionTitle('Chi è'),
                        { name: 'nickname', label: 'Nickname', required: true, max: 40, placeholder: 'es. cripsum' },
                        { name: 'nome_reale', label: 'Nome reale', max: 80, placeholder: 'facoltativo' },
                        { name: 'ruolo', label: 'Ruolo', type: 'select', options: ctx.roles.map((r) => [r.key, r.label]) },
                        { name: 'stato', label: 'Stato', type: 'select', options: STATES.map(([value, label, hint]) => [value, `${label}: ${hint}`]) },
                        { name: 'ruolo_label', label: 'Ruolo personalizzato (IT)', max: 60, placeholder: 'vuoto = quello scelto sopra', help: 'es. «Entry fragger e meme lord».' },
                        { name: 'ruolo_label_en', label: 'Ruolo personalizzato (EN)', max: 60, placeholder: 'vuoto = usa l\'italiano' },
                        { name: 'nazionalita', label: 'Nazionalità', type: 'select', options: countries },
                        { name: 'slug', label: 'Indirizzo', max: 60, placeholder: 'vuoto = dal nickname', help: 'La scheda sarà <code>/it/ohpy/<b data-slug-preview>...</b></code>' },
                    ], values)}
                </div>
                <div class="shop-admin-preview-wrap"><span class="shop-admin-help">Anteprima della card</span><div data-player-preview></div></div>
            </div>
            ${fields([
                sectionTitle('Aspetto', 'Foto verticale (3:4). Un PNG scontornato su sfondo trasparente rende benissimo: nella scheda ha dietro il nickname gigante.'),
                { name: 'foto', label: 'Foto', type: 'image', help: 'Vuota = la foto profilo del suo account Cripsum, se è collegato.' },
                { name: 'sfondo', label: 'Sfondo della scheda', type: 'image', help: 'Facoltativo: sta dietro la foto, un po\' trasparente.' },
                { name: 'colore_proprio', label: 'Un colore suo, diverso da quello del team', type: 'checkbox', checked: false },
                `<div data-color-field class="admin-field--full esports-admin-color">${fields([{ name: 'colore_accento', label: 'Colore del player', type: 'color', value: '#f5a524' }], values)}</div>`,

                sectionTitle('Testi'),
                { name: 'frase', label: 'Frase (IT)', max: 200, placeholder: 'La sua frase tipica, sotto il nickname' },
                { name: 'frase_en', label: 'Frase (EN)', max: 200, placeholder: 'vuoto = usa l\'italiano' },
                { name: 'bio', label: 'Bio (IT)', type: 'textarea', max: 3000, rows: 4 },
                { name: 'bio_en', label: 'Bio (EN)', type: 'textarea', max: 3000, rows: 4, placeholder: 'vuoto = usa l\'italiano' },

                sectionTitle('Statistiche', 'Tutto facoltativo: quello che resta vuoto non compare.'),
                { name: 'premier_rating', label: 'Premier rating', inputmode: 'numeric', placeholder: 'es. 18452', help: 'Il colore della fascia si mette da solo.' },
                { name: 'faceit_livello', label: 'Livello FACEIT', type: 'select', options: [['', 'Dall\'ELO (o nessuno)'], ...Array.from({ length: 10 }, (_, i) => [String(i + 1), `Livello ${i + 1}`])] },
                { name: 'faceit_elo', label: 'ELO FACEIT', inputmode: 'numeric', placeholder: 'es. 2150', help: 'Con l\'ELO il livello si calcola da solo.' },
                { name: 'statistiche', label: 'Altre statistiche', type: 'textarea', rows: 5, full: true, placeholder: 'K/D: 1.24\nADR: 86.3\nHS%: 54%\nWin rate: 58%\nMappe giocate: 412', help: 'Una per riga, «Etichetta: valore». Le prime 4 diventano i numeri grandi; i valori in % prendono una barra.' },

                sectionTitle('Setup'),
                { name: 'crosshair', label: 'Codice mirino', max: 64, full: true, placeholder: 'CSGO-xxxxx-xxxxx-xxxxx-xxxxx-xxxxx', help: 'In CS2: Impostazioni › Mirino › Condividi. Sulla scheda c\'è il tasto per copiarlo.' },
                { name: 'setup_it', label: 'Setup (IT)', type: 'textarea', rows: 4, placeholder: 'Sensibilità: 1.2\nDPI: 800\nRisoluzione: 1280x960 stretchato\nMouse: Logitech G Pro', help: 'Una riga per voce, «Etichetta: valore».' },
                { name: 'setup_en', label: 'Setup (EN)', type: 'textarea', rows: 4, placeholder: 'vuoto = usa l\'italiano' },

                sectionTitle('Curiosità', 'Le info stupide. «Etichetta: valore» diventa una scheda, «- frase» un punto dell\'elenco.'),
                { name: 'curiosita_it', label: 'Curiosità (IT)', type: 'textarea', rows: 5, placeholder: 'Mappa che odia: Vertigo\nArma della vergogna: Negev\n- Ha comprato una Zeus al pistol round' },
                { name: 'curiosita_en', label: 'Curiosità (EN)', type: 'textarea', rows: 5, placeholder: 'vuoto = usa l\'italiano' },

                sectionTitle('Social e profilo'),
                { name: 'utente', label: 'Profilo Cripsum (username)', max: 21, placeholder: 'es. cripsum', help: 'Aggiunge il link al suo profilo sul sito.' },
                ...ctx.player_socials.map((s) => ({ name: `social_${s.key}`, label: s.label, max: 255, placeholder: 'https://...' })),

                sectionTitle('Musica', 'Parte da sola quando si apre la sua scheda. Carica l\'mp3 del suo music kit o incolla un link https.'),
                musicFields(values),
            ], values)}`;

        formModal({
            title: item ? `Player ${item.nickname}` : 'Nuovo player',
            subtitle: item ? `/it/ohpy/${item.slug}` : 'Nasce nascosto: lo provi dalla sua scheda e poi lo metti in line-up',
            html,
            endpoint: EP,
            action: 'save_player',
            extra: { id: item?.id || 0 },
            after: reload,
            onReady: (form) => {
                const own = form.elements.colore_proprio;
                const colorField = $('[data-color-field]', form);
                const syncColor = () => {
                    if (colorField) colorField.hidden = !own.checked;
                };
                own?.addEventListener('change', syncColor);
                syncColor();

                bindForm(form, () => playerPreview(form, ctx), 'esports');
                bindKitSearch(form);
                bindMusic(form);
            },
        });
    };

    /* ── Team ───────────────────────────────────────────────────────── */

    const teamForm = (ctx, reload) => {
        const values = { ...ctx.team, palmares: ctx.team.palmares_testo || '' };

        const html = fields([
            sectionTitle('Nome e testi', 'La testata della pagina.'),
            { name: 'nome', label: 'Nome del team', required: true, max: 80 },
            { name: 'gioco', label: 'Gioco', max: 60, placeholder: 'Counter-Strike 2', help: 'Nel titolo della pagina e nelle anteprime dei link.' },
            { name: 'frase', label: 'Frase (IT)', max: 200 },
            { name: 'frase_en', label: 'Frase (EN)', max: 200, placeholder: 'vuoto = usa l\'italiano' },
            { name: 'descrizione', label: 'Descrizione (IT)', type: 'textarea', max: 3000, rows: 3 },
            { name: 'descrizione_en', label: 'Descrizione (EN)', type: 'textarea', max: 3000, rows: 3, placeholder: 'vuoto = usa l\'italiano' },

            sectionTitle('Immagini', 'Il logo sta sopra il nome; la copertina riempie la testata, scurita in basso.'),
            { name: 'logo', label: 'Logo', type: 'image' },
            { name: 'copertina', label: 'Copertina', type: 'image' },

            sectionTitle('Colori', 'Testo e bordi si adattano da soli. Parti da un preset e ritocca.'),
            `<div class="admin-field--full shop-admin-presets">${TEAM_PRESETS.map(([name, a, b1, b2]) =>
                `<button type="button" class="shop-admin-preset" data-preset="${a},${b1},${b2}" style="--a:${a};--b1:${b1};--b2:${b2}"><span></span>${e(name)}</button>`).join('')}</div>`,
            { name: 'colore_accento', label: 'Colore principale', type: 'color', value: '#f5a524' },
            { name: 'colore_sfondo', label: 'Sfondo 1', type: 'color', value: '#07080c' },
            { name: 'colore_sfondo_2', label: 'Sfondo 2', type: 'color', value: '#1c1307' },
            '<div class="admin-field admin-field--full"><label>Anteprima</label><div class="shop-admin-theme" data-theme-preview></div></div>',

            sectionTitle('Bottone e social', 'Il bottone è facoltativo, per esempio «Entra nel Discord».'),
            { name: 'link_testo', label: 'Testo del bottone (IT)', max: 60 },
            { name: 'link_testo_en', label: 'Testo del bottone (EN)', max: 60, placeholder: 'vuoto = usa l\'italiano' },
            { name: 'link_url', label: 'Link del bottone', max: 255, full: true, placeholder: 'https://discord.gg/... oppure /it/...' },
            ...ctx.team_socials.map((s) => ({ name: `social_${s.key}`, label: s.label, max: 255, placeholder: 'https://...' })),

            sectionTitle('Palmarès', 'Un risultato per riga: «data | torneo | piazzamento | link». Data e link sono facoltativi; 1°, 2° e 3° prendono la medaglia.'),
            { name: 'palmares', label: 'Risultati', type: 'textarea', rows: 5, full: true, placeholder: '2026-05-12 | Cripsum Cup | 1° | https://...\n03/2026 | Torneo del bar | 3°' },

            sectionTitle('Sezioni'),
            { name: 'mostra_ex', label: 'Mostra la sezione degli ex player', type: 'checkbox' },
        ], values);

        formModal({
            title: `Team ${ctx.team.nome}`,
            subtitle: 'Testata, colori, social e palmarès della pagina /it/ohpy',
            html,
            endpoint: EP,
            action: 'save_team',
            after: reload,
            onReady: (form) => {
                const preview = $('[data-theme-preview]', form);
                const draw = () => {
                    const val = (name) => form.elements[name]?.value || '';
                    const accent = val('colore_accento') || '#f5a524';
                    const hex = accent.replace('#', '');
                    const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
                    preview.style.setProperty('--a', accent);
                    preview.style.setProperty('--b1', val('colore_sfondo') || '#07080c');
                    preview.style.setProperty('--b2', val('colore_sfondo_2') || '#1c1307');
                    preview.style.setProperty('--on', (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.62 ? '#161000' : '#ffffff');
                    preview.innerHTML = `
                        <strong class="esports-admin-theme__title">${e(val('nome') || 'OHPY')}</strong>
                        <small>${e(val('frase') || 'La frase del team')}</small>
                        <span class="shop-admin-theme__btn">${e(val('link_testo') || 'Line-up')}</span>`;
                };

                $$('[data-preset]', form).forEach((button) => button.addEventListener('click', () => {
                    const [a, b1, b2] = button.dataset.preset.split(',');
                    [['colore_accento', a], ['colore_sfondo', b1], ['colore_sfondo_2', b2]].forEach(([name, value]) => {
                        const picker = form.elements[name];
                        picker.value = value;
                        const text = $('[data-color-text]', picker.parentElement);
                        if (text) text.value = value;
                    });
                    draw();
                }));

                bindForm(form, draw, 'esports');
            },
        });
    };

    const teamPanel = (box, ctx, reload) => {
        const t = ctx.team;
        const count = (stato) => ctx.players.filter((p) => p.stato === stato).length;
        const palmares = String(t.palmares_testo || '').split('\n').filter((line) => line.trim()).length;
        const socials = ctx.team_socials.filter((s) => t[`social_${s.key}`]);

        box.innerHTML = `
            <div class="esports-admin-team" style="--a: ${e(t.colore_accento || '#f5a524')}; --b1: ${e(t.colore_sfondo || '#07080c')}; --b2: ${e(t.colore_sfondo_2 || '#1c1307')}">
                ${t.copertina ? `<img class="esports-admin-team__cover" src="${e(A.assetUrl(t.copertina))}" alt="">` : ''}
                <div class="esports-admin-team__main">
                    ${t.logo ? `<img class="esports-admin-team__logo" src="${e(A.assetUrl(t.logo))}" alt="">` : ''}
                    <div>
                        <strong>${e(t.nome)}</strong>
                        <small>${e(t.frase || 'Nessuna frase')}</small>
                    </div>
                </div>
                <ul class="esports-admin-team__facts">
                    <li><b>${count('titolare')}</b> in line-up</li>
                    <li><b>${count('riserva') + count('staff')}</b> in panchina e staff</li>
                    <li><b>${count('ex')}</b> ex ${Number(t.mostra_ex) === 1 ? '' : '(sezione spenta)'}</li>
                    <li><b>${palmares}</b> risultati nel palmarès</li>
                    <li><b>${socials.length}</b> social ${socials.length ? `(${socials.map((s) => e(s.label)).join(', ')})` : ''}</li>
                </ul>
                <div class="esports-admin-team__actions">
                    <a class="admin-btn" href="/it/ohpy" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Apri la pagina</a>
                    <button type="button" class="admin-btn admin-btn--primary" data-edit-team><i class="fa-solid fa-pen"></i> Modifica team e pagina</button>
                </div>
            </div>`;

        $('[data-edit-team]', box).addEventListener('click', () => teamForm(ctx, reload));
    };

    /* ── Sezione ────────────────────────────────────────────────────── */

    const load = async () => {
        const root = $('[data-esports-admin]');
        if (!root) return;

        const body = tabs(root, [
            ['player', 'Player', 'fa-solid fa-users'],
            ['team', 'Team e pagina', 'fa-solid fa-flag'],
        ], state.tab, (tab) => {
            state.tab = tab;
            load();
        });

        setLoading(body);
        let ctx;
        try {
            ctx = await get({ action: 'list' });
        } catch (error) {
            body.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore', error.message);
            return;
        }

        if (ctx.ready === false) {
            body.innerHTML = emptyState('fa-solid fa-database', 'Migrazione da applicare', ctx.message);
            return;
        }

        if (state.tab === 'team') teamPanel(body, ctx, load);
        else playersTable(body, ctx, load);
    };

    A.registerSection('esports', load);
})();
