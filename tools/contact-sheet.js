// Combines slide PNGs into contact sheets (4 x 4 per sheet) for quick visual review.
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');
const dir = process.argv[2];
const files = fs.readdirSync(dir).filter((f) => /^s\d+\.png$/.test(f)).sort();
const W = 640, H = 360, COLS = 4, ROWS = 4, PER = COLS * ROWS;
(async () => {
  for (let sheet = 0; sheet * PER < files.length; sheet++) {
    const batch = files.slice(sheet * PER, (sheet + 1) * PER);
    const composites = await Promise.all(batch.map(async (f, i) => ({
      input: await sharp(path.join(dir, f)).resize(W, H).png().toBuffer(),
      left: (i % COLS) * (W + 10), top: Math.floor(i / COLS) * (H + 10),
    })));
    const out = path.join(dir, `sheet-${sheet + 1}.png`);
    await sharp({ create: { width: COLS * (W + 10), height: ROWS * (H + 10), channels: 3, background: '#888' } }).composite(composites).png().toFile(out);
    console.log(out, batch[0], '→', batch[batch.length - 1]);
  }
})();
