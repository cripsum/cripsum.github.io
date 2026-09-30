// Lettura e scrittura dei contenitori delle build Unity WebGL:
// UnityWebData1.0 (il .data.unityweb) -> data.unity3d (bundle UnityFS) -> nodi.
'use strict';
const crypto = require('crypto');

const sha = (buf, algo = 'sha256') => crypto.createHash(algo).update(buf).digest('hex');

// ---------- UnityWebData1.0 ----------
const WEBDATA_MAGIC = 'UnityWebData1.0\0';

function readWebData(buf) {
    if (buf.toString('latin1', 0, 16) !== WEBDATA_MAGIC) throw new Error('Non e\' un UnityWebData1.0');
    const headerSize = buf.readUInt32LE(16);
    const entries = [];
    let p = 20;
    while (p < headerSize) {
        const offset = buf.readUInt32LE(p);
        const size = buf.readUInt32LE(p + 4);
        const nameLen = buf.readUInt32LE(p + 8);
        const name = buf.toString('utf8', p + 12, p + 12 + nameLen);
        p += 12 + nameLen;
        entries.push({ name, data: buf.subarray(offset, offset + size) });
    }
    return entries;
}

function writeWebData(entries) {
    const names = entries.map(e => Buffer.from(e.name, 'utf8'));
    let headerSize = 20;
    for (const n of names) headerSize += 12 + n.length;
    const total = headerSize + entries.reduce((s, e) => s + e.data.length, 0);
    const out = Buffer.alloc(total);
    out.write(WEBDATA_MAGIC, 0, 'latin1');
    out.writeUInt32LE(headerSize, 16);
    let p = 20;
    let offset = headerSize;
    entries.forEach((e, i) => {
        out.writeUInt32LE(offset, p);
        out.writeUInt32LE(e.data.length, p + 4);
        out.writeUInt32LE(names[i].length, p + 8);
        names[i].copy(out, p + 12);
        p += 12 + names[i].length;
        e.data.copy ? e.data.copy(out, offset) : out.set(e.data, offset);
        offset += e.data.length;
    });
    return out;
}

// ---------- LZ4 (blocchi) ----------
function lz4Decompress(src, outSize) {
    const out = Buffer.alloc(outSize);
    let i = 0, o = 0;
    while (i < src.length) {
        const token = src[i++];
        let lit = token >> 4;
        if (lit === 15) { let b; do { b = src[i++]; lit += b; } while (b === 255); }
        src.copy(out, o, i, i + lit);
        i += lit; o += lit;
        if (i >= src.length) break;
        const dist = src[i] | (src[i + 1] << 8);
        i += 2;
        let len = token & 15;
        if (len === 15) { let b; do { b = src[i++]; len += b; } while (b === 255); }
        len += 4;
        for (let k = 0; k < len; k++) out[o + k] = out[o - dist + k];
        o += len;
    }
    if (o !== outSize) throw new Error(`LZ4: attesi ${outSize} byte, ottenuti ${o}`);
    return out;
}

function decompressBlock(data, flags, size) {
    const kind = flags & 0x3f;
    if (kind === 0) return data;
    if (kind === 2 || kind === 3) return lz4Decompress(data, size);
    throw new Error(`Compressione UnityFS non gestita: ${kind}`);
}

// ---------- UnityFS ----------
function readCString(buf, p) {
    const end = buf.indexOf(0, p);
    return { value: buf.toString('utf8', p, end), next: end + 1 };
}

function readUnityFS(buf) {
    let s = readCString(buf, 0);
    if (s.value !== 'UnityFS') throw new Error('Non e\' un bundle UnityFS');
    let p = s.next;
    const version = buf.readUInt32BE(p); p += 4;
    s = readCString(buf, p); const unityVersion = s.value; p = s.next;
    s = readCString(buf, p); const unityRevision = s.value; p = s.next;
    const totalSize = Number(buf.readBigUInt64BE(p)); p += 8;
    const ciSize = buf.readUInt32BE(p); p += 4;
    const uiSize = buf.readUInt32BE(p); p += 4;
    const flags = buf.readUInt32BE(p); p += 4;
    // Dalla versione 7 l'intestazione e' allineata a 16 byte.
    const headerEnd = p;
    if (version >= 7) p = (p + 15) & ~15;
    const infoAtEnd = (flags & 0x80) !== 0;
    const infoStart = infoAtEnd ? totalSize - ciSize : p;
    const info = decompressBlock(buf.subarray(infoStart, infoStart + ciSize), flags, uiSize);
    let q = 16; // hash 128 bit dei dati
    const blockCount = info.readUInt32BE(q); q += 4;
    const blocks = [];
    for (let i = 0; i < blockCount; i++) {
        blocks.push({ usize: info.readUInt32BE(q), csize: info.readUInt32BE(q + 4), flags: info.readUInt16BE(q + 8) });
        q += 10;
    }
    const nodeCount = info.readUInt32BE(q); q += 4;
    const nodes = [];
    for (let i = 0; i < nodeCount; i++) {
        const offset = Number(info.readBigUInt64BE(q));
        const size = Number(info.readBigUInt64BE(q + 8));
        const nflags = info.readUInt32BE(q + 16);
        const n = readCString(info, q + 20);
        q = n.next;
        nodes.push({ name: n.value, offset, size, flags: nflags });
    }
    const infoTail = info.subarray(q);
    let dataStart = infoAtEnd ? p : p + ciSize;
    const parts = [];
    for (const b of blocks) {
        parts.push(decompressBlock(buf.subarray(dataStart, dataStart + b.csize), b.flags, b.usize));
        dataStart += b.csize;
    }
    const stream = Buffer.concat(parts);
    for (const n of nodes) n.data = stream.subarray(n.offset, n.offset + n.size);
    return { version, unityVersion, unityRevision, flags, headerEnd, nodes, blocks, infoTail, infoHash: info.subarray(0, 16) };
}

// Riscrive il bundle con blocchi NON compressi (il file finale viene comunque
// compresso in brotli per il server). Un blocco ogni 128 KiB come fa Unity.
function writeUnityFS(bundle, nodes) {
    const stream = Buffer.concat(nodes.map(n => n.data));
    const BLOCK = 0x20000;
    const blocks = [];
    for (let o = 0; o < stream.length; o += BLOCK) blocks.push(Math.min(BLOCK, stream.length - o));
    const nameBufs = nodes.map(n => Buffer.concat([Buffer.from(n.name, 'utf8'), Buffer.from([0])]));
    const infoSize = 16 + 4 + blocks.length * 10 + 4 + nodes.reduce((s, n, i) => s + 20 + nameBufs[i].length, 0) + bundle.infoTail.length;
    const info = Buffer.alloc(infoSize);
    let q = 0;
    bundle.infoHash.copy(info, 0); q = 16;
    info.writeUInt32BE(blocks.length, q); q += 4;
    for (const size of blocks) { info.writeUInt32BE(size, q); info.writeUInt32BE(size, q + 4); info.writeUInt16BE(0, q + 8); q += 10; }
    info.writeUInt32BE(nodes.length, q); q += 4;
    let offset = 0;
    nodes.forEach((n, i) => {
        info.writeBigUInt64BE(BigInt(offset), q);
        info.writeBigUInt64BE(BigInt(n.data.length), q + 8);
        info.writeUInt32BE(n.flags, q + 16);
        nameBufs[i].copy(info, q + 20);
        q += 20 + nameBufs[i].length;
        offset += n.data.length;
    });
    bundle.infoTail.copy(info, q);

    const head = Buffer.concat([
        Buffer.from('UnityFS\0', 'latin1'),
        u32be(bundle.version),
        Buffer.from(bundle.unityVersion + '\0', 'utf8'),
        Buffer.from(bundle.unityRevision + '\0', 'utf8'),
    ]);
    let fixedLen = head.length + 8 + 4 + 4 + 4;
    const pad = bundle.version >= 7 ? ((fixedLen + 15) & ~15) - fixedLen : 0;
    // Flag: compressione 0, info subito dopo l'intestazione, resto invariato.
    const flags = (bundle.flags & ~0x3f) & ~0x80;
    const total = fixedLen + pad + info.length + stream.length;
    const hdr = Buffer.alloc(8 + 4 + 4 + 4);
    hdr.writeBigUInt64BE(BigInt(total), 0);
    hdr.writeUInt32BE(info.length, 8);
    hdr.writeUInt32BE(info.length, 12);
    hdr.writeUInt32BE(flags, 16);
    return Buffer.concat([head, hdr, Buffer.alloc(pad), info, stream]);
}

function u32be(v) { const b = Buffer.alloc(4); b.writeUInt32BE(v); return b; }

module.exports = { sha, readWebData, writeWebData, readUnityFS, writeUnityFS };
