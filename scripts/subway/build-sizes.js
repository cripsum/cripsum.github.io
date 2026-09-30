// Rigenera includes/subway/build_sizes.php: dimensione DECOMPRESSA di dati,
// wasm e framework di ogni build (per nome file), letta da subway-builds-br.
// Uso: node build-sizes.js <radice-del-repo>
'use strict';
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const root = process.argv[2] || '.';
const br = path.join(root, 'subway-builds-br');
const decoded = {};
const sizeOf = file => (decoded[file] ??= zlib.brotliDecompressSync(fs.readFileSync(file)).length);

const out = {};
for (const map of fs.readdirSync(br).sort()) {
    const dir = path.join(br, map);
    if (!fs.statSync(dir).isDirectory() || map === 'shared') continue;
    const sizes = {};
    const configs = fs.readdirSync(dir).filter(f => f.endsWith('.alt.json') || /\.training[a-z0-9]*\.json$/.test(f));
    for (const name of configs) {
        const config = JSON.parse(fs.readFileSync(path.join(dir, name), 'utf8').replace(/^﻿/, ''));
        for (const key of ['dataUrl', 'wasmCodeUrl', 'wasmFrameworkUrl']) {
            if (!config[key]) continue;
            const rel = config[key].split('?')[0];
            sizes[path.basename(rel)] = sizeOf(path.join(dir, rel));
        }
    }
    out[map] = sizes;
}

const lines = Object.entries(out).map(([map, sizes]) =>
    `    '${map}' => [${Object.entries(sizes).map(([file, bytes]) => `'${file}' => ${bytes}`).join(', ')}],`);
const php = `<?php
/*
 * Dimensioni DECOMPRESSE dei file di ogni build (dati, wasm, framework),
 * per nome file. subway.js le passa al loader Unity quando il server non
 * manda Content-Length (Cloudflare ricomprime al volo): senza, il loader
 * del 2019 va in errore a ogni evento di download.
 * Generato da scripts/subway/build-sizes.js a partire da subway-builds-br.
 */

return [
${lines.join('\n')}
];
`;
fs.writeFileSync(path.join(root, 'includes', 'subway', 'build_sizes.php'), php);
console.log(`${Object.keys(out).length} mappe scritte in includes/subway/build_sizes.php`);
