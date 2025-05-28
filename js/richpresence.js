let ws = null;
let reconnectInterval = null;
let currentEdit = null; // Variabile per tracciare l'edit corrente

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
    state: "Scaricando minchiate su Cripsum™",
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
    imageText: "Edits e creazioni",
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
    state: "Scaricando minchiate su Cripsum™",
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

// Mappa degli edit con i loro dettagli
const editMap = {
  22: { character: "Shorekeeper - Wuthering Waves", music: "Irokz - Toxic Potion (slowed)" },
  21: { character: "Karane Inda", music: "Katy Perry - Harleys in Hawaii" },
  20: { character: "Dante - Devil May Cry", music: "ATLXS - PASSO BEM SOLTO (super slowed)" },
  19: { character: "Sung Jin-Woo - Solo Levelling", music: "Peak - Re-Up" },
  18: { character: "Nagi - Blue Lock", music: "One of the girls X good for you" },
  17: { character: "Cool Mita / Cappie - MiSide", music: "Bruno Mars - Treasure" },
  16: { character: "Crazy Mita - MiSide", music: "Imogen Heap - Headlock" },
  15: { character: "Yuki Suou - Roshidere", music: "Rarin - Mamacita" },
  14: { character: "Alya Kujou - Roshidere", music: "Clean Bandit - Solo" },
  13: { character: "Alya Kujou - Roshidere", music: "Subway Surfers phonk trend" },
  12: { character: "Luca Arlia (meme)", music: "Luca Carboni - Luca lo stesso" },
  11: { character: "Yuki Suou - Roshidere", music: "PnB Rock - Unforgettable (Freestyle)" },
  10: { character: "Alya Kujou - Roshidere", music: "Rarin & Frozy - Kompa" },
  9: { character: "Cristiano Ronaldo", music: "G-Eazy - Tumblr Girls" },
  8: { character: "Mandy - Brawl Stars", music: "NCTS - NEXT!" },
  7: { character: "Choso - Jujutsu Kaisen", music: "The Weeknd - Is There Someone Else?" },
  6: { character: "Nym", music: "Chris Brown - Under the influence" },
  5: { character: "Mortis - Brawl Stars", music: "DJ FNK - Slide da Treme Melódica v2" },
  4: { character: "Nino balletto tattico", music: "Zara Larsson - Lush Life" },
  3: { character: "Mates - Crossbar challenge", music: "G-Eazy - Lady Killers II" },
  2: { character: "Homelander - The Boys", music: "MGMT - Little Dark Age" },
  1: { character: "Heisenberg - Breaking Bad", music: "Travis Scott - MY EYES" }
};

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

function attemptReconnect() {
  if (!reconnectInterval) {
    reconnectInterval = setInterval(() => {
      console.log("Tentativo di riconnessione...");
      connectWebSocket();
    }, 3000);
  }
}

// Funzione per aggiornare l'edit corrente
function setCurrentEdit(editId) {
  currentEdit = editId;
  console.log("Edit corrente impostato:", editId);
  updatePresence();
}

// Funzione per rimuovere l'edit corrente (quando si esce dal video)
function clearCurrentEdit() {
  currentEdit = null;
  console.log("Edit corrente rimosso");
  updatePresence();
}

function updatePresence() {
  if (!ws || ws.readyState !== WebSocket.OPEN) {
    console.log("WebSocket non connesso, impossibile aggiornare presence");
    return;
  }

  const fullPath = window.location.pathname + window.location.search + window.location.hash;
  const pathOnly = window.location.pathname;
  
  console.log("Aggiornamento presence per:", pathOnly);
  
  let page = pageMap[pathOnly] || {
    title: "Navigando",
    state: "Esplorando il sito",
    imageText: "Sul sito web",
    url: fullPath
  };

  // Se siamo nella pagina edits e c'è un edit corrente, modifica la presenza
  if ((pathOnly === "/it/edits" || pathOnly === "/en/edits") && currentEdit && editMap[currentEdit]) {
    const edit = editMap[currentEdit];
    page = {
      title: "Edits",
      state: `Guardando edit di ${edit.character}`,
      imageText: `🎵 ${edit.music}`,
      url: fullPath
    };
  }

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

connectWebSocket();

window.addEventListener("popstate", updatePresence);
window.addEventListener("hashchange", updatePresence);
document.addEventListener("DOMContentLoaded", updatePresence);

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

setInterval(() => {
  if (ws && ws.readyState === WebSocket.OPEN) {
    updatePresence();
  }
}, 30000);

// Espone le funzioni globalmente per l'uso negli altri script
window.setCurrentEdit = setCurrentEdit;
window.clearCurrentEdit = clearCurrentEdit;