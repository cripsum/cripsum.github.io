const ws = new WebSocket("ws://localhost:5678");

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
    state: "acquistando minchiate",
    imageText: "Negozio online"
  },
  "/chisiamo": {
    title: "Chi siamo",
    state: "il nostro team di sviluppo",
    imageText: "La nostra storia"
  },
  "/edits": {
    title: "Edits",
    state: "Sta guardando gli edit",
    imageText: "Edits e creazioni"
  }
};

// Funzione per aggiornare presence
function updatePresence() {
  const fullPath = window.location.pathname + window.location.search + window.location.hash;
  const pathOnly = window.location.pathname;
  const page = pageMap[pathOnly] || {
    title: "Navigando",
    state: "",
    imageText: "Sul sito",
    url: fullPath
  };

  ws.send(JSON.stringify({
    title: page.title,
    state: page.state,
    imageText: page.imageText,
    url: fullPath
  }));
}

// Connessione WebSocket e trigger iniziale
ws.addEventListener("open", updatePresence);

// Se il sito è una SPA, ascolta cambi di URL
window.addEventListener("popstate", updatePresence);
document.addEventListener("DOMContentLoaded", updatePresence);
