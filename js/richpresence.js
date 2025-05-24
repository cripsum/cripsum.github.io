let ws = null;
let reconnectInterval = null;

// Mappa delle pagine con dettagli
const pageMap = {
  "/": {
    title: "Home",
    state: "Esplora la homepage",
    imageText: "Benvenuto nel sito"
  },
  "/home": {
    title: "Home", 
    state: "Esplora la homepage",
    imageText: "Benvenuto nel sito"
  },
  "/negozio": {
    title: "Negozio",
    state: "Acquistando minchiate",
    imageText: "Negozio online"
  },
  "/chisiamo": {
    title: "Chi siamo",
    state: "Scoprendo il nostro team",
    imageText: "La nostra storia"
  },
  "/edits": {
    title: "Edits", 
    state: "Guardando gli edit",
    imageText: "Edits e creazioni"
  }
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