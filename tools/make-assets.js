// Generates app icons (public/icons) and Google Play graphics (docs/play-store).
// Re-run for another company:  node make-assets.js "Company Name" "#0B3D91" "#111827"
const path = require('path');
const fs = require('fs');
const puppeteer = require('puppeteer-core');

const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const [name = 'SENTRA 360 Security', primary = '#0B3D91', dark = '#111827'] = process.argv.slice(2);
const root = path.resolve(__dirname, '..');
const iconsDir = path.join(root, 'public', 'icons');
const playDir = path.join(root, 'docs', 'play-store');
fs.mkdirSync(iconsDir, { recursive: true });
fs.mkdirSync(playDir, { recursive: true });

const shield = (size) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="${size}" height="${size}">
  <path d="M16 2 4 6.5v8.8c0 7.4 5.1 13.2 12 14.7 6.9-1.5 12-7.3 12-14.7V6.5L16 2z" fill="#fff"/>
  <path d="M16 5.2 7 8.6v6.7c0 5.7 3.8 10.2 9 11.6 5.2-1.4 9-5.9 9-11.6V8.6L16 5.2z" fill="${primary}"/>
  <path d="m11 16 3.5 3.5L21.5 12" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

const page = (w, h, body) => `<!doctype html><html><head><style>
  *{margin:0;padding:0;box-sizing:border-box} html,body{width:${w}px;height:${h}px;overflow:hidden}
  body{font-family:"Segoe UI",Arial,sans-serif}</style></head><body>${body}</body></html>`;

const iconHtml = (size, safe) => page(size, size, `<div style="width:${size}px;height:${size}px;background:linear-gradient(145deg,${primary},${dark});display:grid;place-items:center">${shield(Math.round(size * safe))}</div>`);

const featureHtml = page(1024, 500, `
  <div style="width:1024px;height:500px;background:radial-gradient(700px 400px at 85% 10%, ${primary}cc, transparent 60%),linear-gradient(135deg,${dark},#020617);color:#fff;display:flex;align-items:center;padding:0 70px;gap:56px">
    <div style="flex:none">${shield(220)}</div>
    <div>
      <div style="font-size:54px;font-weight:800;line-height:1.05;letter-spacing:-1px">${name}</div>
      <div style="font-size:28px;margin-top:18px;color:#CBD5E1">Panic button · Incident reports · Home &amp; vehicle protection</div>
      <div style="margin-top:28px;display:inline-block;background:#DC2626;padding:12px 22px;border-radius:999px;font-size:22px;font-weight:700">Your security company, in your pocket</div>
    </div>
  </div>`);

(async () => {
  const browser = await puppeteer.launch({ executablePath: EDGE, headless: true, args: ['--no-first-run'] });
  const shot = async (html, w, h, file) => {
    const p = await browser.newPage();
    await p.setViewport({ width: w, height: h, deviceScaleFactor: 1 });
    await p.setContent(html, { waitUntil: 'load' });
    await p.screenshot({ path: file, type: 'png', omitBackground: false });
    await p.close();
    console.log('wrote', path.relative(root, file));
  };
  await shot(iconHtml(512, 0.62), 512, 512, path.join(iconsDir, 'icon-512.png'));
  await shot(iconHtml(192, 0.62), 192, 192, path.join(iconsDir, 'icon-192.png'));
  await shot(iconHtml(512, 0.46), 512, 512, path.join(iconsDir, 'maskable-512.png')); // content inside the 80% safe zone
  await shot(iconHtml(512, 0.6), 512, 512, path.join(playDir, 'play-icon-512.png'));
  await shot(featureHtml, 1024, 500, path.join(playDir, 'feature-graphic-1024x500.png'));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
