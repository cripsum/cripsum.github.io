# Build di Subway Surfers: script offline

Si eseguono sul PC, mai sul server. Le build stanno in `subway-builds/`
(originali, in `.gitignore`) e l'uscita in `subway-builds-br/` (da caricare a
mano su Hostinger in `public_html/subway-builds/`).

1. `node compress-builds.js <radice-del-repo>`
   Comprime ogni `.unityweb` in brotli (q11) con lo stesso nome, copia i
   `.json`, salta `_training/`, verifica ogni file decomprimendolo.
2. `node strip-label.js <repo>/subway-builds <repo>/subway-builds-br <mappa>...`
   Sostituisce l'etichetta "by tavvkkj" (10 caratteri) con 10 spazi nei
   `.alt.data` che la contengono, a parita' di lunghezza, e ricomprime.
   Il 01/10/2026 serviva per bangkok cairo hongkong moscow newyork paris rio
   tokyo venice.
3. `node generate-training.js <repo>/subway-builds <repo>/subway-builds-br [mappa...]`
   Genera `<mappa>.training*.data.unityweb` + `.json` da
   `<mappa>.alt.data.unityweb` e `_training/training-patches.json`.
   Una variante viene scritta solo se il `resources.assets` ricostruito ha
   esattamente lo SHA-1 di riferimento delle patch (`targetSha`).

Regola di ricostruzione (trovata confrontando gli SHA): oggetti nell'ordine
della tabella del SerializedFile, ciascuno allineato a 8 byte, nessuna coda.
Funziona per le 11 mappe con strategia `serialized-rebuild`; le mappe
`core8-inplace` (berlin houston monaco sanfrancisco zurich) e newyork (base
diversa) restano senza allenamento.

`unitypack.js` legge e scrive UnityWebData1.0 e bundle UnityFS (blocchi LZ4
in lettura, non compressi in scrittura: il brotli fa il resto);
`serialized.js` legge la tabella degli oggetti e ricostruisce il file.
4. `node build-sizes.js <radice-del-repo>`
   Rigenera `includes/subway/build_sizes.php` (dimensioni decompresse dei
   file, usate da subway.js per il progresso del loader). Da rilanciare se
   si aggiungono o cambiano build.

`htaccess-subway-builds.txt` e' la copia dell'`.htaccess` da mettere in
`public_html/subway-builds/.htaccess`.
