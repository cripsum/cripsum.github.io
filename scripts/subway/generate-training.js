// Genera i .data di allenamento (una volta sola, offline) a partire da
// <mappa>.alt.data.unityweb + training-patches.json. Ogni variante viene
// accettata solo se il resources.assets ricostruito ha esattamente lo SHA-1
// di riferimento delle patch. Uscita: file brotli + json, in subway-builds-br.
'use strict';
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const u = require('./unitypack.js');
const s = require('./serialized.js');

const [, , srcRoot, dstRoot, ...onlyMaps] = process.argv;
const patches = JSON.parse(fs.readFileSync(path.join(srcRoot, '_training', 'training-patches.json'), 'utf8'));
const VARIANTS = { 'training-default': 'training', 'training-3rows': 'training3rows', 'training-obstacles': 'trainingobstacles' };

const results = [];
for (const [map, entry] of Object.entries(patches.maps)) {
    if (onlyMaps.length && !onlyMaps.includes(map)) continue;
    if (entry.strategy !== 'serialized-rebuild') { results.push(`${map}: saltata (${entry.strategy})`); continue; }

    const altJsonPath = path.join(srcRoot, map, `${map}.alt.json`);
    const dataPath = path.join(srcRoot, map, `${map}.alt.data.unityweb`);
    const web = u.readWebData(fs.readFileSync(dataPath));
    const bundleEntry = web.find(e => e.name === 'data.unity3d');
    const bundle = u.readUnityFS(bundleEntry.data);
    const node = bundle.nodes.find(n => n.name === 'resources.assets');
    if (u.sha(node.data, 'sha1').slice(0, 12) !== entry.diagnostics.baseNodeSha) {
        results.push(`${map}: saltata (base diversa da quella delle patch)`);
        continue;
    }

    for (const [variantId, suffix] of Object.entries(VARIANTS)) {
        const variant = entry.variants[variantId];
        const replacements = new Map(variant.replacements.map(r => [String(r.pathId), Buffer.from(r.data, 'base64')]));
        const rebuilt = s.rebuildSerialized(node.data, replacements);
        const got = u.sha(rebuilt.data, 'sha1').slice(0, 12);
        if (got !== variant.diagnostics.targetSha || rebuilt.replaced !== replacements.size) {
            results.push(`${map} ${variantId}: SCARTATA (sha ${got} != ${variant.diagnostics.targetSha})`);
            continue;
        }
        if (rebuilt.data.includes('tavvkkj')) {
            results.push(`${map} ${variantId}: SCARTATA (contiene l'etichetta)`);
            continue;
        }

        const nodes = bundle.nodes.map(n => ({ name: n.name, flags: n.flags, data: n.name === 'resources.assets' ? rebuilt.data : n.data }));
        const newBundle = u.writeUnityFS(bundle, nodes);
        const newWeb = u.writeWebData(web.map(e => e.name === 'data.unity3d' ? { name: e.name, data: newBundle } : e));

        // Rilettura di controllo: il nodo deve uscire identico dal file finale.
        const check = u.readUnityFS(u.readWebData(newWeb).find(e => e.name === 'data.unity3d').data);
        const checkNode = check.nodes.find(n => n.name === 'resources.assets');
        if (u.sha(checkNode.data, 'sha1').slice(0, 12) !== variant.diagnostics.targetSha) {
            results.push(`${map} ${variantId}: SCARTATA (rilettura non coerente)`);
            continue;
        }

        const outName = `${map}.${suffix}.data.unityweb`;
        const compressed = zlib.brotliCompressSync(newWeb, {
            params: {
                [zlib.constants.BROTLI_PARAM_QUALITY]: 11,
                [zlib.constants.BROTLI_PARAM_LGWIN]: 24,
                [zlib.constants.BROTLI_PARAM_SIZE_HINT]: newWeb.length,
            },
        });
        fs.mkdirSync(path.join(dstRoot, map), { recursive: true });
        fs.writeFileSync(path.join(dstRoot, map, outName), compressed);
        const config = JSON.parse(fs.readFileSync(altJsonPath, 'utf8'));
        config.dataUrl = outName;
        fs.writeFileSync(path.join(dstRoot, map, `${map}.${suffix}.json`), JSON.stringify(config, null, 4) + '\n');
        results.push(`${map} ${variantId}: OK ${(newWeb.length / 1e6).toFixed(1)} MB -> ${(compressed.length / 1e6).toFixed(1)} MB`);
    }
}
console.log(results.join('\n'));
