import sharp from 'sharp';
import { mkdirSync, readFileSync } from 'node:fs';

mkdirSync('public/icons', { recursive: true });
const svg = readFileSync('resources/icon.svg');

for (const size of [192, 512]) {
    await sharp(svg)
        .resize(size, size)
        .png()
        .toFile(`public/icons/icon-${size}.png`);
}
await sharp(svg)
    .resize(180, 180)
    .png()
    .toFile('public/icons/apple-touch-icon.png');

// maskable: the artwork stays inside the safe zone, on a solid background
const art = await sharp(svg).resize(360, 360).png().toBuffer();
await sharp({
    create: { width: 512, height: 512, channels: 4, background: '#18181b' },
})
    .composite([{ input: art, gravity: 'center' }])
    .png()
    .toFile('public/icons/icon-512-maskable.png');
