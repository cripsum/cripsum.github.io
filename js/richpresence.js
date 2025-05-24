let ws = null;
let reconnectInterval = null;

// Mappa delle pagine con dettagli
const pageMap = {
  "/": {
    title: "Home",
    state: "Esplorando la homepage",
    imageText: "Benvenuto nel sito"
  },
  //pagine italiane
  "/it/home": {
    title: "Home", 
    state: "Esplorando la homepage",
    imageText: "Benvenuto nel sito"
  },
  "/it/negozio": {
    title: "Negozio",
    state: "Acquistando minchiate",
    imageText: "Negozio online"
  },
  "/it/chisiamo": {
    title: "Chi siamo",
    state: "Guardando il team di Cripsum™",
    imageText: "La nostra storia"
  },
  "/it/edits": {
    title: "Edits", 
    state: "Guardando gli edit",
    imageText: "Edits e creazioni"
  },
  "/it/accedi": {
    title: "Accedi", 
    state: "Accedendo al sito",
    imageText: "Accedi"
  },
  "/it/achievements": {
    title: "Achievements", 
    state: "Guardando gli achievements",
    imageText: "Achievements di Cripsum™"
  },
  "/it/donazioni": {
    title: "Donazioni",
    state: "Supportando Cripsum™",
    imageText: "Donazioni per il sito" 
  },
  "/it/gambling": {
    title: "Gambling", 
    state: "Scommettendo tutti i suoi risparmi",
    imageText: "Gambling"
  },
  "/it/merch": {
    title: "Merch", 
    state: "Acquistando il merch di Simone Tussi",
    imageText: "Merch ufficiale"
  },
  "/it/privacy": {
    title: "Privacy", 
    state: "Leggendo la privacy policy",
    imageText: "Privacy policy"
  },
  "/it/tos": {
    title: "Terms of Service", 
    state: "Leggendo i termini di servizio",
    imageText: "Termini di servizio"
  },
  "/it/supporto": {
    title: "Supporto", 
    state: "Cercando supporto",
    imageText: "Supporto e aiuto"
  },
  "/it/404": {
    title: "404 Not Found",
    state: "Cercando di trovare qualcosa che non esiste",
    imageText: "Pagina non trovata"
  },
  "/404": {
    title: "404 Not Found",
    state: "Cercando di trovare qualcosa che non esiste",
    imageText: "Pagina non trovata"
  },
  "/it/quandel57": {
    title: "Quandel57",
    state: "Guardando il meme di Quandel57",
    imageText: "Profilo di Quandel57"
  },
  "/it/registrati": {
    title: "Registrati",
    state: "Registrandosi al sito",
    imageText: "Registrazione"
  },
  "/it/rimasti": {
    title: "Top rimasti",
    state: "Guardando i top rimasti",
    imageText: "Top rimasti"
  },
  "/it/shitpost": {
    title: "Shitpost",
    state: "Guardando gli shitpost",
    imageText: "Shitpost di Cripsum™"
  },
  "/it/tiktokpedia": {
    title: "TikTokpedia",
    state: "Esplorando la TikTokpedia",
    imageText: "TikTokpedia"
  },
  "/it/download": {
    title: "Download",
    state: "Scaricando il client di Cripsum™",
    imageText: "Download del client"
  },
  "/it/download/fortnite": {
    title: "Download - Fortnite",
    state: "Scaricando le hack di Fortnite",
    imageText: "Download delle hack di Fortnite"
  },
  "/it/download/osu": {
    title: "Download - Osu!",
    state: "Scaricando Osu!",
    imageText: "Download di Osu!"
  },
  "/it/download/yoshukai": {
    title: "Download - Yoshukai",
    state: "Scaricando il corso di Yoshukai",
    imageText: "Download del corso di Yoshukai"
  },

  //pagine generiche
  "/lootbox": {
    title: "Lootbox",
    state: "Aprendo una lootbox",
    imageText: "Lootbox di Cripsum™"
  },
  "/inventario": {
    title: "Inventario",
    state: "Guardando l'inventario",
    imageText: "Inventario"
  },
  "/404": {
    title: "404 Not Found",
    state: "Cercando di trovare qualcosa che non esiste",
    imageText: "Pagina non trovata"
  },

  //pagine utenti
  "/user/cripsum": {
    title: "Profilo di Cripsum",
    state: "Visualizzando il profilo di Cripsum™",
    imageText: "Profilo di Cripsum™"
  },
  "/user/zakator": {
    title: "Profilo di Zakator",
    state: "Visualizzando il profilo di Zakator",
    imageText: "Profilo di Zakator"
  },
  "/user/salsina": {
    title: "Profilo di Xalx Andrea",
    state: "Visualizzando il profilo di Xalx Andrea",
    imageText: "Profilo di Xalx Andrea"
  },
  "/user/tacos": {
    title: "Profilo di Instxnct",
    state: "Visualizzando il profilo di Instxnct",
    imageText: "Profilo di Instxnct"
  },
  "/user/simonetussi": {
    title: "Profilo di Simone Tussi",
    state: "Visualizzando il profilo di Simone Tussi",
    imageText: "Profilo di Simone Tussi"
  },

  //pagine inglesi
  "/en/home": {
    title: "Home", 
    state: "Esplorando la homepage",
    imageText: "Benvenuto nel sito"
  },
  "/en/negozio": {
    title: "Negozio",
    state: "Acquistando minchiate",
    imageText: "Negozio online"
  },
  "/en/chisiamo": {
    title: "Chi siamo",
    state: "Guardando il team di Cripsum™",
    imageText: "La nostra storia"
  },
  "/en/edits": {
    title: "Edits", 
    state: "Guardando gli edit",
    imageText: "Edits e creazioni"
  },
  "/en/accedi": {
    title: "Accedi", 
    state: "Accedendo al sito",
    imageText: "Accedi"
  },
  "/en/achievements": {
    title: "Achievements", 
    state: "Guardando gli achievements",
    imageText: "Achievements di Cripsum™"
  },
  "/en/donazioni": {
    title: "Donazioni",
    state: "Supportando Cripsum™",
    imageText: "Donazioni per il sito" 
  },
  "/en/gambling": {
    title: "Gambling", 
    state: "Scommettendo tutti i suoi risparmi",
    imageText: "Gambling"
  },
  "/en/merch": {
    title: "Merch", 
    state: "Acquistando il merch di Simone Tussi",
    imageText: "Merch ufficiale"
  },
  "/en/privacy": {
    title: "Privacy", 
    state: "Leggendo la privacy policy",
    imageText: "Privacy policy"
  },
  "/en/tos": {
    title: "Terms of Service", 
    state: "Leggendo i termini di servizio",
    imageText: "Termini di servizio"
  },
  "/en/supporto": {
    title: "Supporto", 
    state: "Cercando supporto",
    imageText: "Supporto e aiuto"
  },
  "/en/404": {
    title: "404 Not Found",
    state: "Cercando di trovare qualcosa che non esiste",
    imageText: "Pagina non trovata"
  },
  "/en/quandel57": {
    title: "Quandel57",
    state: "Guardando il meme di Quandel57",
    imageText: "Profilo di Quandel57"
  },
  "/en/registrati": {
    title: "Registrati",
    state: "Registrandosi al sito",
    imageText: "Registrazione"
  },
  "/en/rimasti": {
    title: "Top rimasti",
    state: "Guardando i top rimasti",
    imageText: "Top rimasti"
  },
  "/en/shitpost": {
    title: "Shitpost",
    state: "Guardando gli shitpost",
    imageText: "Shitpost di Cripsum™"
  },
  "/en/tiktokpedia": {
    title: "TikTokpedia",
    state: "Esplorando la TikTokpedia",
    imageText: "TikTokpedia"
  },
  "/en/download": {
    title: "Download",
    state: "Scaricando il client di Cripsum™",
    imageText: "Download del client"
  },
  "/en/download/fortnite": {
    title: "Download - Fortnite",
    state: "Scaricando le hack di Fortnite",
    imageText: "Download delle hack di Fortnite"
  },
  "/en/download/osu": {
    title: "Download - Osu!",
    state: "Scaricando Osu!",
    imageText: "Download di Osu!"
  },
  "/en/download/yoshukai": {
    title: "Download - Yoshukai",
    state: "Scaricando il corso di Yoshukai",
    imageText: "Download del corso di Yoshukai"
  },
};

// Funzione per connettersi al WebSocket
function connectWebSocket() {
  try {
    ws = new WebSocket("ws://localhost:5678");
    
    ws.addEventListener("open", () => {
      console.log("WebSocket connesso");
      clearInterval(reconnectInterval);
      updatePresence();
    });

    ws.addEventListener("close", () => {
      console.log("WebSocket disconnesso, tentativo di riconnessione...");
      attemptReconnect();
    });

    ws.addEventListener("error", (error) => {
      console.error("Errore WebSocket:", error);
      attemptReconnect();
    });

    return ws;
  } catch (error) {
    console.error("Errore nella creazione del WebSocket:", error);
    attemptReconnect();
    return null;
  }
}

// Funzione per tentare la riconnessione
function attemptReconnect() {
  if (!reconnectInterval) {
    reconnectInterval = setInterval(() => {
      console.log("Tentativo di riconnessione...");
      connectWebSocket();
    }, 3000);
  }
}

// Funzione per aggiornare presence
function updatePresence() {
  if (!ws || ws.readyState !== WebSocket.OPEN) {
    console.log("WebSocket non connesso, impossibile aggiornare presence");
    return;
  }

  const fullPath = window.location.pathname + window.location.search + window.location.hash;
  const pathOnly = window.location.pathname;
  
  console.log("Aggiornamento presence per:", pathOnly);
  
  const page = pageMap[pathOnly] || {
    title: "Navigando",
    state: "Esplorando il sito",
    imageText: "Sul sito web",
    url: fullPath
  };

  try {
    const payload = {
      title: page.title,
      state: page.state,
      imageText: page.imageText,
      url: fullPath,
      timestamp: Date.now()
    };
    
    console.log("Invio dati:", payload);
    ws.send(JSON.stringify(payload));
  } catch (error) {
    console.error("Errore nell'invio dei dati:", error);
  }
}

// Inizializza la connessione
connectWebSocket();

// Event listeners per i cambi di pagina
window.addEventListener("popstate", updatePresence);
window.addEventListener("hashchange", updatePresence);
document.addEventListener("DOMContentLoaded", updatePresence);

// Per SPA che usano history.pushState/replaceState
const originalPushState = history.pushState;
const originalReplaceState = history.replaceState;

history.pushState = function() {
  originalPushState.apply(history, arguments);
  setTimeout(updatePresence, 100);
};

history.replaceState = function() {
  originalReplaceState.apply(history, arguments);
  setTimeout(updatePresence, 100);
};

// Aggiorna periodicamente (fallback)
setInterval(() => {
  if (ws && ws.readyState === WebSocket.OPEN) {
    updatePresence();
  }
}, 30000);