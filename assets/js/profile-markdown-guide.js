(function () {
    const isEnglish = document.documentElement.lang === 'en' || window.location.pathname.includes('/en/');
    const copy = isEnglish ? {
        trigger: 'Markdown guide',
        title: 'Markdown guide',
        subtitle: 'See every format supported by profile custom blocks and insert ready-to-edit examples.',
        close: 'Close guide',
        insert: 'Insert',
        inserted: 'Example inserted',
        preview: 'Preview',
        syntax: 'Syntax',
        categories: {
            text: 'Text',
            structure: 'Structure',
            media: 'Media & code',
            advanced: 'Advanced',
        },
    } : {
        trigger: 'Guida Markdown',
        title: 'Guida Markdown',
        subtitle: 'Scopri tutti i formati supportati nei blocchi custom e inserisci esempi pronti da modificare.',
        close: 'Chiudi guida',
        insert: 'Inserisci',
        inserted: 'Esempio inserito',
        preview: 'Anteprima',
        syntax: 'Sintassi',
        categories: {
            text: 'Testo',
            structure: 'Struttura',
            media: 'Media e codice',
            advanced: 'Avanzato',
        },
    };

    const examples = isEnglish ? [
        { category: 'text', icon: 'fa-bold', title: 'Bold', description: 'Give strong emphasis to important text.', syntax: '**important text**', preview: '<strong>important text</strong>' },
        { category: 'text', icon: 'fa-italic', title: 'Italic', description: 'Add light emphasis or a different tone.', syntax: '*italic text*', preview: '<em>italic text</em>' },
        { category: 'text', icon: 'fa-strikethrough', title: 'Strikethrough', description: 'Show content that has been removed or superseded.', syntax: '~~old text~~', preview: '<del>old text</del>' },
        { category: 'text', icon: 'fa-code', title: 'Inline code', description: 'Highlight a command, variable or short code fragment.', syntax: '`npm run dev`', preview: '<code>npm run dev</code>' },
        { category: 'text', icon: 'fa-link', title: 'Link', description: 'Create a clickable external link.', syntax: '[Visit Cripsum](https://cripsum.com)', preview: '<a href="https://cripsum.com" target="_blank" rel="noopener noreferrer">Visit Cripsum</a>' },
        { category: 'structure', icon: 'fa-heading', title: 'Headings', description: 'Use one to six hashes to create heading levels.', syntax: '# Heading 1\n## Heading 2\n### Heading 3\n#### Heading 4\n##### Heading 5\n###### Heading 6', preview: '<h2>Heading 1</h2><h4>Heading 3</h4><h6>Heading 6</h6>' },
        { category: 'structure', icon: 'fa-list-ul', title: 'Bullet list', description: 'Organize items without a fixed order.', syntax: '- First item\n- Second item\n- Third item', preview: '<ul><li>First item</li><li>Second item</li><li>Third item</li></ul>' },
        { category: 'structure', icon: 'fa-list-ol', title: 'Numbered list', description: 'Create a sequence of ordered steps.', syntax: '1. First step\n2. Second step\n3. Third step', preview: '<ol><li>First step</li><li>Second step</li><li>Third step</li></ol>' },
        { category: 'structure', icon: 'fa-quote-left', title: 'Quote', description: 'Set a sentence apart as a quotation or note.', syntax: '> A quote worth remembering.', preview: '<blockquote>A quote worth remembering.</blockquote>' },
        { category: 'structure', icon: 'fa-minus', title: 'Divider', description: 'Separate two parts of the block visually.', syntax: '---', preview: '<span>Section one</span><hr><span>Section two</span>' },
        { category: 'structure', icon: 'fa-align-left', title: 'Line breaks', description: 'Every new line is preserved in the profile block.', syntax: 'First line\nSecond line\n\nNew paragraph', preview: 'First line<br>Second line<br><br>New paragraph' },
        { category: 'media', icon: 'fa-image', title: 'Image', description: 'Display an image from a public URL or uploaded file.', syntax: '![Cripsum image](/img/cripsumchisiamo.jpg)', preview: '<img src="/img/cripsumchisiamo.jpg" alt="Cripsum image">' },
        { category: 'media', icon: 'fa-file-code', title: 'Code block', description: 'Show a multi-line code sample with fixed-width text.', syntax: '```js\nconst greeting = "Hello";\nconsole.log(greeting);\n```', preview: '<pre><code>const greeting = "Hello";\nconsole.log(greeting);</code></pre>' },
        { category: 'advanced', icon: 'fa-table', title: 'Table', description: 'Compare structured values in rows and columns.', syntax: '| Name | Status |\n| --- | --- |\n| Profile | Online |\n| Project | Active |', preview: '<table class="profile-markdown-table"><thead><tr><th>Name</th><th>Status</th></tr></thead><tbody><tr><td>Profile</td><td>Online</td></tr><tr><td>Project</td><td>Active</td></tr></tbody></table>' },
    ] : [
        { category: 'text', icon: 'fa-bold', title: 'Grassetto', description: 'Dai maggiore risalto alle parole importanti.', syntax: '**testo importante**', preview: '<strong>testo importante</strong>' },
        { category: 'text', icon: 'fa-italic', title: 'Corsivo', description: 'Aggiungi un’enfasi leggera o un tono diverso.', syntax: '*testo in corsivo*', preview: '<em>testo in corsivo</em>' },
        { category: 'text', icon: 'fa-strikethrough', title: 'Barrato', description: 'Mostra un contenuto rimosso o non più valido.', syntax: '~~testo precedente~~', preview: '<del>testo precedente</del>' },
        { category: 'text', icon: 'fa-code', title: 'Codice inline', description: 'Evidenzia un comando, una variabile o poco codice.', syntax: '`npm run dev`', preview: '<code>npm run dev</code>' },
        { category: 'text', icon: 'fa-link', title: 'Link', description: 'Crea un collegamento esterno cliccabile.', syntax: '[Visita Cripsum](https://cripsum.com)', preview: '<a href="https://cripsum.com" target="_blank" rel="noopener noreferrer">Visita Cripsum</a>' },
        { category: 'structure', icon: 'fa-heading', title: 'Titoli', description: 'Usa da uno a sei cancelletti per i livelli del titolo.', syntax: '# Titolo 1\n## Titolo 2\n### Titolo 3\n#### Titolo 4\n##### Titolo 5\n###### Titolo 6', preview: '<h2>Titolo 1</h2><h4>Titolo 3</h4><h6>Titolo 6</h6>' },
        { category: 'structure', icon: 'fa-list-ul', title: 'Elenco puntato', description: 'Organizza elementi senza un ordine fisso.', syntax: '- Primo elemento\n- Secondo elemento\n- Terzo elemento', preview: '<ul><li>Primo elemento</li><li>Secondo elemento</li><li>Terzo elemento</li></ul>' },
        { category: 'structure', icon: 'fa-list-ol', title: 'Elenco numerato', description: 'Crea una sequenza ordinata di passaggi.', syntax: '1. Primo passaggio\n2. Secondo passaggio\n3. Terzo passaggio', preview: '<ol><li>Primo passaggio</li><li>Secondo passaggio</li><li>Terzo passaggio</li></ol>' },
        { category: 'structure', icon: 'fa-quote-left', title: 'Citazione', description: 'Separa una frase come citazione o nota.', syntax: '> Una frase da ricordare.', preview: '<blockquote>Una frase da ricordare.</blockquote>' },
        { category: 'structure', icon: 'fa-minus', title: 'Separatore', description: 'Dividi visivamente due parti del blocco.', syntax: '---', preview: '<span>Prima sezione</span><hr><span>Seconda sezione</span>' },
        { category: 'structure', icon: 'fa-align-left', title: 'A capo', description: 'Ogni nuova riga viene mantenuta nel blocco del profilo.', syntax: 'Prima riga\nSeconda riga\n\nNuovo paragrafo', preview: 'Prima riga<br>Seconda riga<br><br>Nuovo paragrafo' },
        { category: 'media', icon: 'fa-image', title: 'Immagine', description: 'Mostra un’immagine pubblica o un file già caricato.', syntax: '![Immagine Cripsum](/img/cripsumchisiamo.jpg)', preview: '<img src="/img/cripsumchisiamo.jpg" alt="Immagine Cripsum">' },
        { category: 'media', icon: 'fa-file-code', title: 'Blocco di codice', description: 'Mostra più righe di codice con carattere monospaziato.', syntax: '```js\nconst saluto = "Ciao";\nconsole.log(saluto);\n```', preview: '<pre><code>const saluto = "Ciao";\nconsole.log(saluto);</code></pre>' },
        { category: 'advanced', icon: 'fa-table', title: 'Tabella', description: 'Confronta valori strutturati in righe e colonne.', syntax: '| Nome | Stato |\n| --- | --- |\n| Profilo | Online |\n| Progetto | Attivo |', preview: '<table class="profile-markdown-table"><thead><tr><th>Nome</th><th>Stato</th></tr></thead><tbody><tr><td>Profilo</td><td>Online</td></tr><tr><td>Progetto</td><td>Attivo</td></tr></tbody></table>' },
    ];

    let modal = null;
    let activeTextarea = null;
    let activeCategory = 'text';
    let lastTrigger = null;

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const renderExamples = () => {
        const grid = modal?.querySelector('.markdown-guide-grid');
        if (!grid) return;
        grid.innerHTML = examples.map((example, index) => ({ ...example, index }))
            .filter((example) => example.category === activeCategory)
            .map((example) => `
                <article class="markdown-guide-item">
                    <div class="markdown-guide-item-heading">
                        <span><i class="fa-solid ${example.icon}"></i></span>
                        <div><h3>${escapeHtml(example.title)}</h3><p>${escapeHtml(example.description)}</p></div>
                    </div>
                    <div class="markdown-guide-preview-wrap">
                        <small>${copy.preview}</small>
                        <div class="markdown-guide-preview profile-block-custom-content">${example.preview}</div>
                    </div>
                    <div class="markdown-guide-syntax">
                        <div><small>${copy.syntax}</small><code>${escapeHtml(example.syntax)}</code></div>
                        <button type="button" data-markdown-insert="${example.index}" title="${copy.insert}">
                            <i class="fa-solid fa-arrow-turn-down"></i><span>${copy.insert}</span>
                        </button>
                    </div>
                </article>`).join('');
    };

    const closeGuide = () => {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('markdown-guide-open');
        if (lastTrigger) lastTrigger.focus({ preventScroll: true });
    };

    const ensureModal = () => {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.className = 'markdown-guide-overlay';
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <section class="markdown-guide-dialog" role="dialog" aria-modal="true" aria-labelledby="markdownGuideTitle">
                <header class="markdown-guide-header">
                    <div class="markdown-guide-title">
                        <span><i class="fa-brands fa-markdown"></i></span>
                        <div><h2 id="markdownGuideTitle">${copy.title}</h2><p>${copy.subtitle}</p></div>
                    </div>
                    <button type="button" class="markdown-guide-close" aria-label="${copy.close}" title="${copy.close}"><i class="fa-solid fa-xmark"></i></button>
                </header>
                <nav class="markdown-guide-tabs" aria-label="${copy.title}">
                    ${Object.entries(copy.categories).map(([key, label], index) => `
                        <button type="button" data-markdown-category="${key}" class="${index === 0 ? 'is-active' : ''}">${label}</button>`).join('')}
                </nav>
                <div class="markdown-guide-body"><div class="markdown-guide-grid"></div></div>
            </section>`;
        document.body.appendChild(modal);

        modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('.markdown-guide-close')) {
                closeGuide();
                return;
            }

            const categoryButton = event.target.closest('[data-markdown-category]');
            if (categoryButton) {
                activeCategory = categoryButton.dataset.markdownCategory;
                modal.querySelectorAll('[data-markdown-category]').forEach((button) => {
                    button.classList.toggle('is-active', button === categoryButton);
                });
                renderExamples();
                modal.querySelector('.markdown-guide-body')?.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            const insertButton = event.target.closest('[data-markdown-insert]');
            if (!insertButton || !activeTextarea) return;
            const example = examples[Number(insertButton.dataset.markdownInsert)];
            if (!example) return;
            const start = activeTextarea.selectionStart ?? activeTextarea.value.length;
            const end = activeTextarea.selectionEnd ?? start;
            const prefix = start > 0 && activeTextarea.value[start - 1] !== '\n' ? '\n\n' : '';
            const suffix = end < activeTextarea.value.length && activeTextarea.value[end] !== '\n' ? '\n\n' : '';
            const insertion = prefix + example.syntax + suffix;
            activeTextarea.setRangeText(insertion, start, end, 'end');
            activeTextarea.dispatchEvent(new Event('input', { bubbles: true }));
            insertButton.classList.add('is-confirmed');
            insertButton.querySelector('span').textContent = copy.inserted;
            window.setTimeout(() => {
                insertButton.classList.remove('is-confirmed');
                insertButton.querySelector('span').textContent = copy.insert;
            }, 1400);
        });

        renderExamples();
        return modal;
    };

    const openGuide = (trigger) => {
        const row = trigger.closest('.profile-row-card');
        activeTextarea = row?.querySelector('.block-body-textarea') || null;
        lastTrigger = trigger;
        ensureModal();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('markdown-guide-open');
        window.requestAnimationFrame(() => modal.querySelector('.markdown-guide-close')?.focus({ preventScroll: true }));
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-open-markdown-guide]');
        if (!trigger) return;
        event.preventDefault();
        openGuide(trigger);
    });

    document.addEventListener('change', (event) => {
        if (!event.target.matches('.block-type-select')) return;
        const trigger = event.target.closest('.profile-row-card')?.querySelector('[data-open-markdown-guide]');
        if (trigger) trigger.hidden = event.target.value !== 'markdown';
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('is-open')) closeGuide();
    });

    window.profileMarkdownGuide = { open: openGuide, close: closeGuide };
}());
