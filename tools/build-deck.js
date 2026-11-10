// Builds docs/SENTRA360-Walkthrough.pptx from docs/screenshots/catalog.json
const path = require('path');
const fs = require('fs');
const pptxgen = require('pptxgenjs');
const React = require('react');
const ReactDOMServer = require('react-dom/server');
const sharp = require('sharp');
const Bi = require('react-icons/bi');
const { applyTheme } = require('./apply_theme.js');

const root = path.resolve(__dirname, '..');
const shots = path.join(root, 'docs', 'screenshots');
const catalog = JSON.parse(fs.readFileSync(path.join(shots, 'catalog.json'), 'utf8'));
const OUT = path.join(root, 'docs', 'SENTRA360-Walkthrough.pptx');

const THEME = {
  name: 'SENTRA 360',
  headFontFace: 'Arial',
  bodyFontFace: 'Calibri',
  colors: {
    dk1: '0B1F2A', lt1: 'FFFFFF', dk2: '0E5E6F', lt2: 'EEF3F6',
    accent1: '0E5E6F', accent2: 'DC2626', accent3: 'D97706', accent4: '15803D', accent5: '64748B', accent6: '1E3A8A',
    hlink: '0E5E6F', folHlink: '64748B',
  },
};

async function icon(Comp, hex = 'FFFFFF') {
  const svg = ReactDOMServer.renderToStaticMarkup(React.createElement(Comp, { size: 256, color: '#' + hex }));
  const png = await sharp(Buffer.from(svg)).png().toBuffer();
  return 'image/png;base64,' + png.toString('base64');
}
const shadow = () => ({ type: 'outer', color: '0B1F2A', blur: 8, offset: 2, angle: 90, opacity: 0.18 });

(async () => {
  const pres = new pptxgen();
  pres.layout = 'LAYOUT_WIDE'; // 13.33 x 7.5 in
  pres.title = 'SENTRA 360: product walkthrough';
  pres.author = 'SENTRA 360';
  pres.subject = 'Every screen of the SENTRA 360 security app, explained';
  pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
  const C = pres.SchemeColor;

  const footer = { text: { text: 'SENTRA 360 · product walkthrough', options: { x: 0.5, y: 7.05, w: 6, h: 0.3, fontSize: 10, color: C.accent5 } } };
  const num = (c) => ({ x: 12.2, y: 7.05, w: 0.7, h: 0.3, fontSize: 10, color: c, align: 'right' });
  const titlePh = (color) => ({ placeholder: { options: { name: 'title', type: 'title', x: 0.5, y: 0.3, w: 12.33, h: 0.75, fontSize: 32, bold: true, color, align: 'left', valign: 'middle', margin: 0 }, text: '' } });

  pres.defineSlideMaster({
    title: 'Title', background: { color: C.text1 },
    objects: [
      { placeholder: { options: { name: 'title', type: 'title', x: 0.8, y: 2.2, w: 11.7, h: 1.7, fontSize: 48, bold: true, color: C.background1, align: 'left', valign: 'bottom', margin: 0 }, text: '' } },
      { placeholder: { options: { name: 'body', type: 'body', x: 0.8, y: 4.1, w: 11.7, h: 1.4, fontSize: 22, color: 'CBD5E1', valign: 'top', margin: 0 }, text: '' } },
    ],
  });
  pres.defineSlideMaster({
    title: 'Section', background: { color: C.text2 },
    objects: [
      { placeholder: { options: { name: 'title', type: 'title', x: 0.8, y: 2.4, w: 11.7, h: 1.3, fontSize: 44, bold: true, color: C.background1, align: 'left', valign: 'bottom', margin: 0 }, text: '' } },
      { placeholder: { options: { name: 'body', type: 'body', x: 0.8, y: 3.9, w: 10.5, h: 1.6, fontSize: 20, color: C.background1, valign: 'top', margin: 0 }, text: '' } },
    ],
    slideNumber: num(C.background1),
  });
  pres.defineSlideMaster({ title: 'Content', background: { color: C.background1 }, objects: [titlePh(C.text1), footer], slideNumber: num(C.accent5) });
  pres.defineSlideMaster({
    title: 'Screen wide', background: { color: C.background1 },
    objects: [titlePh(C.text1), footer,
      { placeholder: { options: { name: 'body', type: 'body', x: 9.45, y: 1.6, w: 3.38, h: 4.75, fontSize: 16, color: C.text1, valign: 'top', margin: 0, paraSpaceAfter: 8 }, text: '' } }],
    slideNumber: num(C.accent5),
  });
  pres.defineSlideMaster({
    title: 'Screen dual', background: { color: C.background1 },
    objects: [titlePh(C.text1), footer,
      { placeholder: { options: { name: 'body', type: 'body', x: 0.5, y: 5.72, w: 7.2, h: 1.2, fontSize: 15, color: C.text1, valign: 'top', margin: 0 }, text: '' } }],
    slideNumber: num(C.accent5),
  });

  const caption = (slide, text) => slide.addText(text, { x: 0.5, y: 1.05, w: 12.33, h: 0.35, fontSize: 12, color: C.accent5, margin: 0, isTextBox: true, objectName: 'Caption' });
  const iconCircle = async (slide, Comp, x, y, d, fill) => {
    slide.addShape(pres.shapes.OVAL, { x, y, w: d, h: d, fill: { color: fill }, line: { color: fill }, objectName: 'Icon circle' });
    slide.addImage({ data: await icon(Comp), x: x + d * 0.22, y: y + d * 0.22, w: d * 0.56, h: d * 0.56, altText: '' });
  };
  const card = (slide, x, y, w, h, name) => slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h, rectRadius: 0.12, fill: { color: C.background2 }, line: { color: C.background2 }, shadow: shadow(), objectName: name });

  // ------------------------------------------------------------------ title
  pres.addSection({ title: 'Introduction' });
  let s = pres.addSlide({ masterName: 'Title', sectionTitle: 'Introduction' });
  s.addText('SENTRA 360', { placeholder: 'title' });
  s.addText('Security management, community safety and emergency response app: every screen, explained', { placeholder: 'body' });
  s.addImage({ data: await icon(Bi.BiShieldQuarter, 'FFFFFF'), x: 0.8, y: 1.0, w: 1.0, h: 1.0, altText: 'Shield icon' });
  s.addText(`Prepared ${new Date().toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' })} · screenshots from the demo company "Ubuntu Shield Security" (sample data)`, { x: 0.8, y: 6.5, w: 11.7, h: 0.4, fontSize: 12, color: '94A3B8', margin: 0, isTextBox: true });
  s.addNotes('SENTRA 360 is one app for one private security company, with three logins. The screenshots use sample data from a fictional demo company.');

  // --------------------------------------------------------- three logins
  s = pres.addSlide({ masterName: 'Content', sectionTitle: 'Introduction' });
  s.addText('One app, three logins', { placeholder: 'title' });
  caption(s, 'Each account only works on its own login page and only opens its own area');
  const logins = [
    [Bi.BiHome, 'Homeowner', '/login · residents and clients', ['Reports incidents with photos and GPS', 'Applies to protect home and vehicles', 'Books services, accepts quotations, pays', 'Panic button once protected'], C.accent1],
    [Bi.BiBuildings, 'Admin', '/admin/login · the company office', ['Reviews applications and sends quotations', 'Records payments and activates clients', 'Manages services, alerts and branding', 'Reports with Excel and PDF export'], C.accent6],
    [Bi.BiShieldQuarter, 'Security company', '/security/login · control room and officers', ['Live control room with map and alarm', 'Acknowledges panic alerts, dispatches officers', 'Officers update en route / on scene', 'Patrol check-ins and homeowner lookup'], C.accent2],
  ];
  for (let i = 0; i < 3; i++) {
    const [Ic, h, sub, bullets, col] = logins[i];
    const x = 0.5 + i * 4.18;
    card(s, x, 1.6, 3.95, 5.1, `${h} card`);
    await iconCircle(s, Ic, x + 0.35, 1.9, 0.85, col);
    s.addText(h, { x: x + 0.35, y: 2.9, w: 3.3, h: 0.5, fontSize: 22, bold: true, color: C.text1, margin: 0, isTextBox: true });
    s.addText(sub, { x: x + 0.35, y: 3.4, w: 3.3, h: 0.4, fontSize: 12, color: C.accent5, margin: 0, isTextBox: true });
    s.addText(bullets.map((b, j) => ({ text: b, options: { bullet: true, breakLine: j < bullets.length - 1 } })), { x: x + 0.35, y: 3.95, w: 3.3, h: 2.5, fontSize: 15, color: C.text1, paraSpaceAfter: 6, valign: 'top', margin: 0, isTextBox: true });
  }
  s.addNotes('Homeowners sign themselves up. Admin and Security company logins are created by the Admin under "Admin & security logins".');

  // ----------------------------------------------------------- journeys
  const flow = async (title, cap, steps, col, notes, Ic) => {
    const sl = pres.addSlide({ masterName: 'Content', sectionTitle: 'Introduction' });
    sl.addText(title, { placeholder: 'title' });
    caption(sl, cap);
    const n = steps.length, gap = 0.2, w = (12.33 - gap * (n - 1)) / n;
    for (let i = 0; i < n; i++) {
      const x = 0.5 + i * (w + gap);
      card(sl, x, 2.0, w, 2.85, `Step ${i + 1}`);
      sl.addShape(pres.shapes.OVAL, { x: x + w / 2 - 0.35, y: 2.3, w: 0.7, h: 0.7, fill: { color: col }, line: { color: col }, objectName: `Step ${i + 1} number` });
      sl.addText(String(i + 1), { x: x + w / 2 - 0.35, y: 2.3, w: 0.7, h: 0.7, fontSize: 20, bold: true, color: C.background1, align: 'center', valign: 'middle', margin: 0, isTextBox: true });
      sl.addText(steps[i][0], { x: x + 0.12, y: 3.2, w: w - 0.24, h: 0.8, fontSize: 16, bold: true, color: C.text1, align: 'center', valign: 'top', margin: 0, isTextBox: true });
      sl.addText(steps[i][1], { x: x + 0.12, y: 3.95, w: w - 0.24, h: 0.8, fontSize: 13, color: C.accent5, align: 'center', valign: 'top', margin: 0, isTextBox: true });
    }
    await iconCircle(sl, Ic, 0.5, 5.35, 0.6, col);
    sl.addText(notes, { x: 1.3, y: 5.3, w: 11.5, h: 0.75, fontSize: 14, color: C.text1, valign: 'middle', margin: 0, isTextBox: true });
    sl.addNotes(notes);
  };
  await flow('From community member to paying client', 'The commercial journey the brief asks for',
    [['Registers', 'Free homeowner account'], ['Reports or requests', 'Incident or service request'], ['Applies', 'Home or vehicle protection'], ['Company reviews', 'Assessment if needed'], ['Quotation', 'Itemised, with VAT'], ['Accepts & pays', 'EFT now, PayFast ready'], ['Active client', 'Panic button unlocked']],
    C.accent1, 'Every step is visible to the homeowner on a progress bar and timeline. The Admin moves the application along from the admin portal.', Bi.BiTrendingUp);
  await flow('From panic button to resolution', 'The emergency journey, with honest status at every step',
    [['Hold 3 seconds', 'Prevents accidental alerts'], ['Received', 'Server confirms, with reference'], ['Seen', 'Shown on the control room screen'], ['Acknowledged', 'Operator presses Acknowledge'], ['Dispatched', 'Officer assigned and on the way'], ['Resolved', 'Outcome recorded']],
    C.accent2, 'The client sees the time of each step. If it is not acknowledged within 2 minutes, the app tells them to phone the emergency line or 10111.', Bi.BiBroadcast);

  // -------------------------------------------------------- honest by design
  s = pres.addSlide({ masterName: 'Content', sectionTitle: 'Introduction' });
  s.addText('Built to be honest and private', { placeholder: 'title' });
  caption(s, 'What makes it safe to rely on, and safe to show to clients');
  const pillars = [
    [Bi.BiCheckShield, 'A report is not a dispatch', 'Statuses separate submitted, received, assigned and resolved, so nobody assumes help is coming when it is not.', C.accent1],
    [Bi.BiMessageError, 'Nothing is faked', 'SMS, email and online payments say plainly when they are not set up. Messages are never shown as "sent" when they were not.', C.accent3],
    [Bi.BiWifiOff, 'Offline warnings', 'The panic screen warns that alerts need internet. Offline, the app shows the emergency numbers instead.', C.accent2],
    [Bi.BiLockAlt, 'Privacy (POPIA)', 'Consent is recorded, contact details are masked in lists, every record view is logged, and homeowners can delete their account.', C.accent6],
  ];
  for (let i = 0; i < 4; i++) {
    const [Ic, h, t, col] = pillars[i];
    const x = 0.5 + (i % 2) * 6.27, y = 1.65 + Math.floor(i / 2) * 2.45;
    card(s, x, y, 6.06, 2.15, h);
    await iconCircle(s, Ic, x + 0.3, y + 0.35, 0.8, col);
    s.addText(h, { x: x + 1.35, y: y + 0.3, w: 4.5, h: 0.5, fontSize: 20, bold: true, color: C.text1, margin: 0, isTextBox: true });
    s.addText(t, { x: x + 1.35, y: y + 0.85, w: 4.5, h: 1.35, fontSize: 15, color: C.text1, valign: 'top', margin: 0, isTextBox: true });
  }

  // --------------------------------------------------------- at a glance
  s = pres.addSlide({ masterName: 'Content', sectionTitle: 'Introduction' });
  s.addText('At a glance', { placeholder: 'title' });
  caption(s, 'Where the app stands today');
  const stats = [['45', 'screens across the public site and three portals'], ['34', 'automated tests passing, including every page'], ['22', 'incident categories from the brief'], ['R0', 'monthly hosting today (Render + TiDB free plans)']];
  for (let i = 0; i < 4; i++) {
    const x = 0.5 + i * 3.13;
    card(s, x, 1.7, 2.9, 2.75, `Stat ${i + 1}`);
    s.addText(stats[i][0], { x: x + 0.25, y: 1.9, w: 2.4, h: 1.3, fontSize: 66, bold: true, color: C.accent1, margin: 0, isTextBox: true });
    s.addText(stats[i][1], { x: x + 0.25, y: 3.3, w: 2.45, h: 1.0, fontSize: 15, color: C.text1, valign: 'top', margin: 0, isTextBox: true });
  }
  s.addText('Live at https://sentra360-t9tq.onrender.com', { x: 0.5, y: 4.95, w: 12.33, h: 0.5, fontSize: 18, bold: true, color: C.text1, margin: 0, isTextBox: true, hyperlink: { url: 'https://sentra360-t9tq.onrender.com' } });
  s.addText('Not yet connected: SMS and email delivery, PayFast online payments, a custom domain. Mobile and email verification is switched off for now.', { x: 0.5, y: 5.5, w: 12.33, h: 0.7, fontSize: 15, color: C.accent5, margin: 0, isTextBox: true });

  // ------------------------------------------------------------- screens
  const sections = {
    'Public site': [Bi.BiGlobe, 'What anyone can see, before signing in: the branded company site, services, the three sign-in pages and sign-up.'],
    Homeowner: [Bi.BiHome, 'Residents and clients: reports, the panic button, properties and vehicles, applications, quotations, bookings and their profile.'],
    'Security company': [Bi.BiShieldQuarter, 'Control room operators and response officers: the live control room, responding to incidents, assignments and patrols.'],
    Admin: [Bi.BiBuildings, 'The company office: incidents, applications to activation, quotations and payments, services, alerts, reports, logins and branding.'],
  };
  let current = null;
  for (const item of catalog) {
    if (item.section !== current) {
      current = item.section;
      pres.addSection({ title: current });
      const sec = pres.addSlide({ masterName: 'Section', sectionTitle: current });
      const count = catalog.filter((c) => c.section === current).length;
      sec.addText(current, { placeholder: 'title' });
      sec.addText(sections[current][1] + `  (${count} screens)`, { placeholder: 'body' });
      sec.addImage({ data: await icon(sections[current][0], 'FFFFFF'), x: 0.8, y: 1.2, w: 0.95, h: 0.95, altText: '' });
    }
    const dual = !!item.mobile;
    const sl = pres.addSlide({ masterName: dual ? 'Screen dual' : 'Screen wide', sectionTitle: current });
    sl.addText(item.title, { placeholder: 'title' });
    caption(sl, `${item.section.toUpperCase()}  ·  ${item.url.length > 70 ? item.url.slice(0, 67) + '…' : item.url}`);
    const desk = path.join(shots, item.desktop);
    if (dual) {
      sl.addShape(pres.shapes.RECTANGLE, { x: 0.47, y: 1.47, w: 7.26, h: 4.11, fill: { color: 'E2E8F0' }, line: { color: 'E2E8F0' }, objectName: 'Screen frame' });
      sl.addImage({ path: desk, x: 0.5, y: 1.5, w: 7.2, h: 4.05, altText: `${item.title}, desktop` });
      sl.addText(item.explain, { placeholder: 'body' });
      sl.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 8.75, y: 1.45, w: 3.15, h: 5.5, rectRadius: 0.28, fill: { color: C.text1 }, line: { color: C.text1 }, shadow: shadow(), objectName: 'Phone frame' });
      sl.addImage({ path: path.join(shots, item.mobile), x: 8.875, y: 1.6, w: 2.9, h: 5.155, altText: `${item.title}, phone` });
    } else {
      sl.addShape(pres.shapes.RECTANGLE, { x: 0.47, y: 1.57, w: 8.66, h: 4.89, fill: { color: 'E2E8F0' }, line: { color: 'E2E8F0' }, objectName: 'Screen frame' });
      sl.addImage({ path: desk, x: 0.5, y: 1.6, w: 8.6, h: 4.84, altText: item.title });
      sl.addText(item.explain, { placeholder: 'body' });
    }
    sl.addNotes(`${item.title} (${item.url}). ${item.explain}`);
  }

  // ------------------------------------------------------------- Play Store
  pres.addSection({ title: 'Going live' });
  s = pres.addSlide({ masterName: 'Section', sectionTitle: 'Going live' });
  s.addText('Going live on Google Play', { placeholder: 'title' });
  s.addText('The app is already installable. Google Play publishes it as a Trusted Web Activity: a small Android app that opens the site full-screen.', { placeholder: 'body' });
  s.addImage({ data: await icon(Bi.BiMobileAlt, 'FFFFFF'), x: 0.8, y: 1.2, w: 0.95, h: 0.95, altText: '' });

  s = pres.addSlide({ masterName: 'Content', sectionTitle: 'Going live' });
  s.addText('Ready for Google Play', { placeholder: 'title' });
  caption(s, 'Already prepared in docs/play-store, alongside the full step-by-step guide');
  s.addImage({ path: path.join(root, 'docs', 'play-store', 'feature-graphic-1024x500.png'), x: 0.5, y: 1.6, w: 6.4, h: 3.125, altText: 'Google Play feature graphic' });
  s.addImage({ path: path.join(root, 'docs', 'play-store', 'play-icon-512.png'), x: 0.5, y: 4.95, w: 1.6, h: 1.6, altText: 'App icon' });
  s.addText('512 × 512 app icon and 1024 × 500 feature graphic', { x: 2.3, y: 5.3, w: 4.6, h: 0.9, fontSize: 14, color: C.accent5, margin: 0, isTextBox: true });
  const ready = ['Installable app: manifest, icons, offline page', 'App-to-website link endpoint (asset links)', 'Account deletion page Google requires', 'Privacy policy page', '8 phone screenshots, 1080 × 1920', 'Listing text, data-safety and content-rating answers'];
  card(s, 7.3, 1.6, 5.53, 4.95, 'Prepared list');
  s.addText(ready.map((r, i) => ({ text: r, options: { bullet: true, breakLine: i < ready.length - 1 } })), { x: 7.6, y: 1.85, w: 5.0, h: 4.5, fontSize: 16, color: C.text1, paraSpaceAfter: 10, valign: 'top', margin: 0, isTextBox: true });

  s = pres.addSlide({ masterName: 'Content', sectionTitle: 'Going live' });
  s.addText('What only you can do', { placeholder: 'title' });
  caption(s, 'Decisions and accounts, in order');
  const todo = [
    [Bi.BiWorld, 'Own domain', 'e.g. app.yourcompany.co.za. The Android app is tied to it for good.'],
    [Bi.BiServer, 'Render Starter plan', 'About US$7/month, so the app never sleeps. Essential for a panic button.'],
    [Bi.BiUserCheck, 'Play Console account', 'US$25 once. A company account needs a D-U-N-S number.'],
    [Bi.BiPackage, 'Build the app', 'At pwabuilder.com. Keep the signing key safe.'],
    [Bi.BiEnvelope, 'SMS and email provider', 'So codes and emergency-contact messages are really sent.'],
    [Bi.BiCreditCard, 'PayFast keys', 'To switch on online card payments.'],
  ];
  for (let i = 0; i < 6; i++) {
    const [Ic, h, t] = todo[i];
    const x = 0.5 + (i % 3) * 4.18, y = 1.6 + Math.floor(i / 3) * 2.6;
    card(s, x, y, 3.95, 2.35, h);
    await iconCircle(s, Ic, x + 0.3, y + 0.3, 0.7, C.accent1);
    s.addText(h, { x: x + 1.15, y: y + 0.3, w: 2.65, h: 0.7, fontSize: 18, bold: true, color: C.text1, valign: 'middle', margin: 0, isTextBox: true });
    s.addText(t, { x: x + 0.3, y: y + 1.15, w: 3.4, h: 1.05, fontSize: 14, color: C.text1, valign: 'top', margin: 0, isTextBox: true });
  }

  s = pres.addSlide({ masterName: 'Title', sectionTitle: 'Going live' });
  s.addText('Try it now', { placeholder: 'title' });
  s.addText([
    { text: 'Website: https://sentra360-t9tq.onrender.com', options: { breakLine: true } },
    { text: 'Admin: /admin/login   ·   Security company: /security/login', options: { breakLine: true } },
    { text: 'Homeowners sign up at /register' },
  ], { placeholder: 'body' });
  s.addNotes('The first visit after 15 quiet minutes takes about 50 seconds on the free hosting plan.');

  await pres.writeFile({ fileName: OUT });
  await applyTheme(OUT, THEME);
  console.log('wrote', OUT, '·', pres.slides.length, 'slides');
})().catch((e) => { console.error(e); process.exit(1); });
