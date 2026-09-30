// Comprime subway-builds/ in subway-builds-br/ (brotli q11, stessi nomi),
// copia i .json, salta _training/, verifica ogni file con round-trip + sha256.
const { Worker, isMainThread, parentPort, workerData } = require('worker_threads');
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const crypto = require('crypto');

if (!isMainThread) {
    const { src, dst } = workerData;
    const input = fs.readFileSync(src);
    const out = zlib.brotliCompressSync(input, {
        params: {
            [zlib.constants.BROTLI_PARAM_QUALITY]: 11,
            [zlib.constants.BROTLI_PARAM_LGWIN]: 24,
            [zlib.constants.BROTLI_PARAM_SIZE_HINT]: input.length,
        },
    });
    const back = zlib.brotliDecompressSync(out);
    const sha = b => crypto.createHash('sha256').update(b).digest('hex');
    const ok = sha(back) === sha(input);
    fs.mkdirSync(path.dirname(dst), { recursive: true });
    fs.writeFileSync(dst, out);
    parentPort.postMessage({ ok, inSize: input.length, outSize: out.length });
    return;
}

const root = process.argv[2];
const srcRoot = path.join(root, 'subway-builds');
const dstRoot = path.join(root, 'subway-builds-br');
const files = [];
(function walk(dir) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) { if (e.name !== '_training') walk(p); }
        else files.push(p);
    }
})(srcRoot);

const jobs = [];
for (const f of files) {
    const rel = path.relative(srcRoot, f);
    const dst = path.join(dstRoot, rel);
    if (f.endsWith('.unityweb')) jobs.push({ src: f, dst, rel, size: fs.statSync(f).size });
    else { fs.mkdirSync(path.dirname(dst), { recursive: true }); fs.copyFileSync(f, dst); }
}
jobs.sort((a, b) => b.size - a.size);

const concurrency = 12;
let next = 0, running = 0, totalIn = 0, totalOut = 0, failed = 0;
const started = Date.now();
const report = [];
function launch() {
    while (running < concurrency && next < jobs.length) {
        const job = jobs[next++];
        running++;
        const w = new Worker(__filename, { workerData: job });
        w.on('message', m => {
            totalIn += m.inSize; totalOut += m.outSize;
            if (!m.ok) failed++;
            report.push(`${m.ok ? 'OK ' : 'ERR'} ${job.rel}  ${(m.inSize / 1e6).toFixed(1)} -> ${(m.outSize / 1e6).toFixed(1)} MB`);
            console.log(report[report.length - 1]);
        });
        w.on('error', e => { failed++; console.log('ERR', job.rel, e.message); });
        w.on('exit', () => { running--; if (next < jobs.length) launch(); else if (running === 0) done(); });
    }
}
function done() {
    const summary = `\n${jobs.length} file .unityweb, ${failed} errori, ${(totalIn / 1e6).toFixed(0)} MB -> ${(totalOut / 1e6).toFixed(0)} MB, ${Math.round((Date.now() - started) / 1000)} s`;
    console.log(summary);
    fs.writeFileSync(path.join(dstRoot, '_report.txt'), report.sort().join('\n') + summary + '\n');
}
launch();
