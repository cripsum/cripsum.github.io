// SerializedFile di Unity (versioni 17-21, senza type tree): lettura della
// tabella degli oggetti e ricostruzione con alcuni oggetti sostituiti.
'use strict';

function parseSerialized(buf) {
    const metadataSize = buf.readUInt32BE(0);
    const fileSize = buf.readUInt32BE(4);
    const version = buf.readUInt32BE(8);
    const dataOffset = buf.readUInt32BE(12);
    const bigEndian = buf[16] !== 0;
    if (version >= 22) throw new Error(`SerializedFile v${version} non gestito`);
    if (bigEndian) throw new Error('SerializedFile big endian non gestito');
    let p = 20;
    const end = buf.indexOf(0, p);
    const unityVersion = buf.toString('latin1', p, end);
    p = end + 1;
    p += 4; // target platform
    const typeTree = buf[p++] !== 0;
    if (typeTree) throw new Error('SerializedFile con type tree non gestito');
    const typeCount = buf.readInt32LE(p); p += 4;
    const types = [];
    for (let i = 0; i < typeCount; i++) {
        const classId = buf.readInt32LE(p); p += 4;
        if (version >= 16) p += 1; // stripped
        let scriptIndex = -1;
        if (version >= 17) { scriptIndex = buf.readInt16LE(p); p += 2; }
        if ((version >= 17 && scriptIndex >= 0) || classId === 114 || (version < 17 && classId < 0)) p += 16; // script id
        p += 16; // old type hash
        types.push(classId);
    }
    const objectCount = buf.readInt32LE(p); p += 4;
    const objects = [];
    for (let i = 0; i < objectCount; i++) {
        p = (p + 3) & ~3;
        const entry = p;
        const pathId = buf.readBigInt64LE(p); p += 8;
        const byteStart = buf.readUInt32LE(p); p += 4;
        const byteSize = buf.readUInt32LE(p); p += 4;
        const typeIndex = buf.readInt32LE(p); p += 4;
        if (version < 17) p += 4 + (version >= 15 ? 1 : 0); // classId/scriptIndex vecchi (non usati qui)
        objects.push({ entry, pathId: pathId.toString(), byteStart, byteSize, classId: types[typeIndex] });
    }
    return { metadataSize, fileSize, version, dataOffset, unityVersion, objects };
}

/**
 * Sostituisce i dati degli oggetti indicati (Map pathId -> Buffer) e
 * ridispone gli oggetti nell'ordine della tabella, ciascuno allineato a
 * 8 byte, senza coda finale. E' la disposizione che riproduce esattamente gli
 * SHA di riferimento delle patch. Metadati e dimensione vengono aggiornati.
 */
function rebuildSerialized(buf, replacements) {
    const sf = parseSerialized(buf);
    const ordered = sf.objects;
    const head = Buffer.from(buf.subarray(0, sf.dataOffset));
    const parts = [];
    let cursor = 0;
    let replaced = 0;
    for (const obj of ordered) {
        const aligned = (cursor + 7) & ~7;
        if (aligned > cursor) { parts.push(Buffer.alloc(aligned - cursor)); cursor = aligned; }
        let data = buf.subarray(sf.dataOffset + obj.byteStart, sf.dataOffset + obj.byteStart + obj.byteSize);
        if (replacements.has(obj.pathId)) { data = replacements.get(obj.pathId); replaced++; }
        head.writeUInt32LE(cursor, obj.entry + 8);
        head.writeUInt32LE(data.length, obj.entry + 12);
        parts.push(data);
        cursor += data.length;
    }
    const out = Buffer.concat([head, ...parts]);
    out.writeUInt32BE(out.length, 4);
    return { data: out, replaced, total: sf.objects.length };
}

module.exports = { parseSerialized, rebuildSerialized };
