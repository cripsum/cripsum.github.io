// Sostituisce l'etichetta "by tavvkkj" (stringa Unity con prefisso di
// lunghezza 10) con 10 spazi, a parita' di lunghezza: nessun offset cambia.
'use strict';
const fs = require('fs');
const zlib = require('zlib');
const [, , srcRoot, dstRoot, ...maps] = process.argv;
const needle = Buffer.concat([Buffer.from([10, 0, 0, 0]), Buffer.from('by tavvkkj', 'latin1')]);
for (const m of maps) {
    const rel = `${m}/${m}.alt.data.unityweb`;
    const buf = fs.readFileSync(`${srcRoot}/${rel}`);
    let n = 0;
    for (let i = buf.indexOf(needle); i >= 0; i = buf.indexOf(needle, i + 1)) {
        buf.fill(0x20, i + 4, i + 14);
        n++;
    }
    const left = buf.indexOf('tavvkkj');
    const out = zlib.brotliCompressSync(buf, { params: { [zlib.constants.BROTLI_PARAM_QUALITY]: 11, [zlib.constants.BROTLI_PARAM_LGWIN]: 24, [zlib.constants.BROTLI_PARAM_SIZE_HINT]: buf.length } });
    const ok = zlib.brotliDecompressSync(out).equals(buf);
    fs.writeFileSync(`${dstRoot}/${rel}`, out);
    console.log(`${rel}: sostituite ${n}, residui ${left < 0 ? 0 : 'SI'}, verifica ${ok ? 'OK' : 'ERR'}, ${(out.length / 1e6).toFixed(1)} MB`);
}
