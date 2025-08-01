let ws = null;
let reconnectInterval = null;
let currentEdit = null;
let lastCharacterFound = null; 

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
  "/it/profilo": {
    title: "Profilo",
    state: "Visualizzando il proprio profilo",
    imageText: "Profilo utente",
  },
  "/it/global-chat": {
    title: "Chat Globale",
    state: "Chattando con gli altri utenti",
    imageText: "Chat globale",
  },
  "/it/impostazioni": {
    title: "impostazioni",
    state: "Modificando le impostazioni",
    imageText: "Impostazioni del sito"
  },
  "/it/lootbox": {
    title: "Lootbox",
    state: "Aprendo una lootbox",
    imageText: "Lootbox di Cripsum™"
  },
  "/it/inventario": {
    title: "Inventario",
    state: "Guardando l'inventario",
    imageText: "Inventario"
  },
  "/404": {
    title: "404 Not Found",
    state: "Cercando di trovare qualcosa che non esiste",
    imageText: "Pagina non trovata"
  },
  "/it/admin": {
    title: "Admin",
    state: "Visualizzando il pannello admin",
    imageText: "Pannello di amministrazione"
  },
  "/it/goonland/home": {
    title: "Goonland",
    state: "Esplorando Goonland",
    imageText: "Benvenuto a Goonland",
    largeImageKey: "https://media1.tenor.com/m/QJ7OYh157fcAAAAC/sonic.gif"
  },
  "/it/goonland/goon-generator": {
    title: "Goon Generator",
    state: "Sta Goonando",
    imageText: "Benvenuto a Goonland",
    largeImageKey: "https://media1.tenor.com/m/QJ7OYh157fcAAAAC/sonic.gif"
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
    imageText: "Profilo di Zakator",
    largeImageKey: "https://media1.tenor.com/m/KxEHUVCM5lMAAAAC/dragon-ball-super-super-hero-orange-piccolo.gif"
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

const editMap = {
  25: { character: "Perfect Cell - Dragon Ball", music: "Jmilton, CHASHKAKEFIRA - Reinado", image: "https://media1.tenor.com/m/mMm1Kd38phYAAAAC/big-brain-cell.gif" },
  24: { character: "Waguri Kaoruko", music: "Tate McRae - it's ok i'm ok", image: "https://media1.tenor.com/m/7ddM67UZbgYAAAAC/kaoruko-waguri-waguri-kaoruko.gif" },
  23: { character: "Evelyn - Zenless Zone Zero", music: "Charli XCX - Track 10", image: "https://media1.tenor.com/m/OKrN0ca7FrYAAAAC/evelyn-zzz-singing.gif" },
  22: { character: "Shorekeeper - Wuthering Waves", music: "Irokz - Toxic Potion (slowed)", image: "https://media1.tenor.com/m/V505Lf9lIaAAAAAC/shorekeeper-smile.gif" },
  21: { character: "Karane Inda", music: "Katy Perry - Harleys in Hawaii", image: "https://media1.tenor.com/m/eLDDY0lV10wAAAAC/the-100-girlfriends-who-really-karane-inda.gif" },
  20: { character: "Dante - Devil May Cry", music: "ATLXS - PASSO BEM SOLTO (super slowed)", image: "https://media1.tenor.com/m/ZeqTSp9HVusAAAAC/taking-a-look-dante.gif" },
  19: { character: "Sung Jin-Woo - Solo Levelling", music: "Peak - Re-Up", image: "https://media1.tenor.com/m/cmGCMoAyI_cAAAAC/solo-leveling-solo-leveling-season-2.gif" },
  18: { character: "Nagi - Blue Lock", music: "One of the girls X good for you", image: "https://media1.tenor.com/m/LXELPRJNdIkAAAAC/nagi-seishiro.gif" },
  17: { character: "Cool Mita / Cappie - MiSide", music: "Bruno Mars - Treasure", image: "https://media1.tenor.com/m/nawiqYXYvmQAAAAC/miside-mita.gif" },
  16: { character: "Crazy Mita - MiSide", music: "Imogen Heap - Headlock", image: "https://media1.tenor.com/m/nawiqYXYvmQAAAAC/miside-mita.gif" },
  15: { character: "Yuki Suou - Roshidere", music: "Rarin - Mamacita", image: "https://media1.tenor.com/m/4TXm2fzhjzUAAAAC/alya-sometimes-hides-her-feelings-in-russian-roshidere.gif" },
  14: { character: "Alya Kujou - Roshidere", music: "Clean Bandit - Solo", image: "https://media1.tenor.com/m/kMvtJh1VrroAAAAC/alya-san-hides-her-feelings-in-russian-tokidoki-bosotto-russia-go-de-dereru-tonari-no-alya-san.gif" },
  13: { character: "Alya Kujou - Roshidere", music: "Subway Surfers phonk trend", image: "https://media1.tenor.com/m/kMvtJh1VrroAAAAC/alya-san-hides-her-feelings-in-russian-tokidoki-bosotto-russia-go-de-dereru-tonari-no-alya-san.gif" },
  12: { character: "Luca Arlia (meme)", music: "Luca Carboni - Luca lo stesso", image: "https://media1.tenor.com/m/lMQ-ddynXIcAAAAd/andiamo-a-disoneste-disoneste.gif" },
  11: { character: "Yuki Suou - Roshidere", music: "PnB Rock - Unforgettable (Freestyle)", image: "https://media1.tenor.com/m/4TXm2fzhjzUAAAAC/alya-sometimes-hides-her-feelings-in-russian-roshidere.gif" },
  10: { character: "Alya Kujou - Roshidere", music: "Rarin & Frozy - Kompa", image: "https://media1.tenor.com/m/kMvtJh1VrroAAAAC/alya-san-hides-her-feelings-in-russian-tokidoki-bosotto-russia-go-de-dereru-tonari-no-alya-san.gif" },
  9: { character: "Cristiano Ronaldo", music: "G-Eazy - Tumblr Girls", image: "https://media1.tenor.com/m/xwKvSU1YrKIAAAAC/smile.gif" },
  8: { character: "Mandy - Brawl Stars", music: "NCTS - NEXT!", image: "https://media1.tenor.com/m/8ZeXp8r3SAsAAAAC/mandy-brawl-stars.gif" },
  7: { character: "Choso - Jujutsu Kaisen", music: "The Weeknd - Is There Someone Else?", image: "https://media1.tenor.com/m/1BZtVPRMvOcAAAAC/choso-jujutsu-kaisen.gif" },
  6: { character: "Nym", music: "Chris Brown - Under the influence", image: "https://media1.tenor.com/m/zRZzaNPaz0YAAAAC/nym.gif" },
  5: { character: "Mortis - Brawl Stars", music: "DJ FNK - Slide da Treme Melódica v2", image: "https://media1.tenor.com/m/wsb2cEKbkJgAAAAC/brawlstars-mortis.gif" },
  4: { character: "Nino balletto tattico", music: "Zara Larsson - Lush Life", image: "https://cripsum.com/img/foto_nino.jpg" },
  3: { character: "Mates - Crossbar challenge", music: "G-Eazy - Lady Killers II", image: "https://www.webboh.it/wp-content/uploads/2019/04/mates_articolo-2.jpg" },
  2: { character: "Homelander - The Boys", music: "MGMT - Little Dark Age", image: "https://media1.tenor.com/m/2MKHetsOfHkAAAAC/homelander-the-boys.gif" },
  1: { character: "Heisenberg - Breaking Bad", music: "Travis Scott - MY EYES", image: "https://media1.tenor.com/m/hFGpisu2EDEAAAAC/say-my-name-heisenberg-breaking-bad.gif" }
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

function setCurrentEdit(editId) {
  currentEdit = editId;
  console.log("Edit corrente impostato:", editId);
  updatePresence();
}

function clearCurrentEdit() {
  currentEdit = null;
  console.log("Edit corrente rimosso");
  updatePresence();
}

function setLastCharacterFound(characterName) {
  lastCharacterFound = characterName;
  console.log("ultimo personaggio trovato: ", characterName);
  updatePresence();
}

function clearLastCharacterFound() {
  lastCharacterFound = null;
  console.log("Ultimo personaggio trovato rimosso");
  updatePresence();
}

async function updatePresence() {
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
    largeImageKey: "https://media1.tenor.com/m/98wDXMV0R_gAAAAC/wuwa-wuthering-waves.gif",
    url: fullPath
  };


  if ((pathOnly === "/it/edits" || pathOnly === "/en/edits") && currentEdit && editMap[currentEdit]) {
    const edit = editMap[currentEdit];
    page = {
      title: "Edits",
      state: `Guardando ${edit.character}`,
      imageText: `🎵 ${edit.music}`,
      largeImageKey: edit.image,
      url: fullPath
    };
  }

  if (pathOnly === "/it/lootbox") {
  try {
    // Check if functions are available before calling them
    if (typeof getInventory === 'function' && typeof getCharacterNumber === 'function') {
      const inventory = await getInventory();
      const totalCharacters = await getCharacterNumber();

      if (lastCharacterFound) {
        page = {
          title: "Lootbox",
          state: `Ha appena pullato ${lastCharacterFound}`,
          imageText: `Personaggi trovati: ${inventory.length} / ${totalCharacters}`,
          url: fullPath
        };
      }
    }
  } catch (error) {
    console.error("Error getting inventory data:", error);
  }
}

  if (pathOnly.startsWith("/user/")) {
    const username = pathOnly.split("/user/")[1];
    if (username) {
      page = {
        title: `Profilo di ${username}`,
        state: `Visualizzando il profilo di ${username}`,
        imageText: `Profilo di ${username}`,
        url: fullPath
      };
    }
  }

  try {
    const payload = {
      title: page.title,
      state: page.state,
      imageText: page.imageText,
      largeImageKey: page.largeImageKey,
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

/**if(window.location.pathname === "/it/edits" || window.location.pathname === "/en/edits") {
    setInterval(() => {
    if (ws && ws.readyState === WebSocket.OPEN) {
      updatePresence();
    }
  }, 30000);
}
else{
  if (ws && ws.readyState === WebSocket.OPEN) {
    updatePresence();
  }
}*/

  if (ws && ws.readyState === WebSocket.OPEN) {
    updatePresence();
  }


window.setCurrentEdit = setCurrentEdit;
window.clearCurrentEdit = clearCurrentEdit;

window.setLastCharacterFound = setLastCharacterFound;
window.clearLastCharacterFound = clearLastCharacterFound;