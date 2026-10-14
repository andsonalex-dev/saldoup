import { writeFileSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
function crc32(bytes) {
    let crc = 0xffffffff;
    for (const byte of bytes) {
        crc ^= byte;
        for (let i = 0; i < 8; i++) crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
    }
    return (crc ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
    const name = Buffer.from(type); const length = Buffer.alloc(4); length.writeUInt32BE(data.length);
    const checksum = Buffer.alloc(4); checksum.writeUInt32BE(crc32(Buffer.concat([name, data])));
    return Buffer.concat([length, name, data, checksum]);
}
for (const size of [192, 512]) {
    const rows = Buffer.alloc(size * (size * 4 + 1));
    for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
        const px = x * 512 / size, py = y * 512 / size;
        const bar = [[148, 218], [230, 174], [312, 130]].some(([left, top]) => px >= left && px < left + 52 && py >= top && py < 318);
        const base = px >= 130 && px <= 382 && py >= 344 && py <= 364;
        const color = bar ? [194, 163, 255, 255] : base ? [245, 243, 250, 255] : [23, 21, 30, 255];
        const offset = y * (size * 4 + 1) + 1 + x * 4;
        color.forEach((value, i) => rows[offset + i] = value);
    }
    const header = Buffer.alloc(13); header.writeUInt32BE(size); header.writeUInt32BE(size, 4); header[8] = 8; header[9] = 6;
    writeFileSync(`public/icons/icon-${size}.png`, Buffer.concat([Buffer.from([137,80,78,71,13,10,26,10]), chunk('IHDR', header), chunk('IDAT', deflateSync(rows)), chunk('IEND', Buffer.alloc(0))]));
}
