/**
 * Cripsum™ — service worker delle notifiche.
 *
 * Il browser lo tiene pronto anche a sito chiuso. Quando il server manda un
 * segnale push (includes/webpush.php) si sveglia, chiede al sito cosa è
 * successo e mostra la notifica; al clic porta alla chat o alla pagina
 * giusta. Il segnale porta solo il numero dell'evento e l'id dell'utente:
 * titolo e testo li dà api/notify/feed.php, con la sessione di chi è
 * collegato, cioè con gli stessi controlli degli avvisi in pagina.
 *
 * Non intercetta le richieste delle pagine (nessun gestore `fetch`): il
 * sito si carica esattamente come senza.
 *
 * Deve stare nella radice del sito: un service worker comanda solo le
 * pagine sotto la cartella in cui si trova.
 */

const ICON = '/img/app-192.png';
const BADGE = '/img/app-badge.png';
const ITALIAN = (self.navigator.language || 'it').toLowerCase().startsWith('it');
const TEXT = ITALIAN
    ? { generic: 'Hai nuove notifiche', genericBody: 'Apri Cripsum™ per vederle.', test: 'Notifiche attive', testBody: 'Su questo dispositivo arrivano anche a sito chiuso.' }
    : { generic: 'You have new notifications', genericBody: 'Open Cripsum™ to see them.', test: 'Notifications are on', testBody: 'They reach this device even when the site is closed.' };

// Una versione nuova prende il posto della vecchia subito, senza aspettare
// che tutte le schede vengano chiuse.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

function openWindows() {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true });
}

/** Solo percorsi del sito: una notifica non porta mai altrove. */
function sitePath(url) {
    const value = String(url || '');
    return value.startsWith('/') && !value.startsWith('//') ? value : '/';
}

function show(title, options) {
    return self.registration.showNotification(title, Object.assign({ icon: ICON, badge: BADGE }, options));
}

/**
 * Il segnale era per un account che su questo browser non è più collegato:
 * il sito lo toglie dai suoi dispositivi. Se non è collegato nessuno, il
 * browser smette anche di ricevere segnali.
 */
async function forget(userId, nobodyHere) {
    try {
        const subscription = await self.registration.pushManager.getSubscription();
        if (!subscription) return;
        await fetch('/api/notify/push.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'drop', endpoint: subscription.endpoint, user: userId })
        });
        if (nobodyHere) await subscription.unsubscribe();
    } catch (_) {
        /* si riproverà al prossimo segnale */
    }
}

async function onPush(event) {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (_) {
        data = {};
    }

    if (data.t === 'test') {
        await show(TEXT.test, { body: TEXT.testBody, tag: 'cripsum-test', data: { url: '/' } });
        return;
    }

    // Le pagine aperte controllano subito, senza aspettare il loro giro: se
    // una è in primo piano l'avviso lo dà lei, con il riquadro in pagina.
    const windows = await openWindows();
    windows.forEach((client) => client.postMessage({ type: 'cripsum-push', s: Number(data.s) || 0 }));
    if (windows.some((client) => client.focused && client.visibilityState === 'visible')) return;

    const seq = Number(data.s) || 0;
    let items = null;
    try {
        const response = await fetch('/api/notify/feed.php?since=' + encodeURIComponent(seq - 1), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        });
        if (response.status === 401) {
            await forget(Number(data.u) || 0, true);
            return;
        }
        const feed = await response.json();
        if (data.u && feed.uid && Number(feed.uid) !== Number(data.u)) {
            await forget(Number(data.u), false);
            return;
        }
        if (feed && feed.ok) items = (feed.items || []).filter((item) => item && !item.silent);
    } catch (_) {
        items = null;
    }

    // Il sito non ha risposto (telefono appena tornato in rete): meglio un
    // avviso generico che niente.
    if (items === null) {
        await show(TEXT.generic, { body: TEXT.genericBody, tag: 'cripsum-generic', data: { url: '/' } });
        return;
    }

    for (const item of items) {
        await show(String(item.title || 'Cripsum™'), {
            body: String(item.text || ''),
            icon: item.avatar && sitePath(item.avatar) !== '/' ? sitePath(item.avatar) : ICON,
            // Stessa etichetta per la stessa chat: la notifica nuova prende
            // il posto della vecchia, ma si fa sentire di nuovo.
            tag: 'cripsum-' + String(item.key || 'info'),
            renotify: true,
            data: { url: sitePath(item.url), item }
        });
    }
}

self.addEventListener('push', (event) => {
    event.waitUntil(onPush(event));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data = event.notification.data || {};
    const url = sitePath(data.url);

    event.waitUntil((async () => {
        const windows = await openWindows();
        const target = windows.find((client) => client.visibilityState === 'visible') || windows[0];
        if (target) {
            try {
                await target.focus();
            } catch (_) {
                /* alcuni browser non lasciano portare avanti la finestra */
            }
            // La pagina sa aprire una chat sul posto; altrimenti cambia indirizzo.
            target.postMessage({ type: 'cripsum-open', url, item: data.item || null });
            return;
        }
        await self.clients.openWindow(url);
    })());
});
