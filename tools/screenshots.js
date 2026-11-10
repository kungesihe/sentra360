// Captures every page of SENTRA 360 (desktop 1366x768 and phone 1080x1920)
// into docs/screenshots/{desktop,mobile}, plus catalog.json used by the deck.
// Needs the local demo:  php bin/install.php --fresh --demo && php bin/demo_activity.php
const path = require('path');
const fs = require('fs');
const puppeteer = require('puppeteer-core');

const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const BASE = process.env.BASE || 'http://localhost:8360';
const PASS = 'Demo!2026pass';
const ids = require('./ids.json');
const root = path.resolve(__dirname, '..');
const out = path.join(root, 'docs', 'screenshots');
for (const d of ['desktop', 'mobile']) fs.mkdirSync(path.join(out, d), { recursive: true });

const users = {
  public: null,
  lerato: { login: '/login', email: 'lerato@demo.test' },
  sipho: { login: '/login', email: 'sipho@demo.test' },
  admin: { login: '/login', email: 'admin@demo.test' },
  security: { login: '/login', email: 'officer@demo.test' },
};

// [section, user, path, title, explanation, phone?]
const P = [
  ['Public site', 'public', '/', 'Community home page', 'A simple, community-first welcome for Soshanguve: "Safer streets start with neighbours", live safety alerts, the 24/7 emergency number and three steps to join.', true],
  ['Public site', 'public', '/services', 'Services marketplace (public)', 'Anyone can browse the security services on offer, with prices or "quotation required". Booking needs a free account.', true],
  ['Public site', 'public', '/pricelist', 'Price list', 'Every listed service with its price, home protection first. Printable, and also inside the app under Services.', true],
  ['Public site', 'public', '/login', 'One sign-in for everyone', 'Residents, the company office and security officers all sign in here. The app recognises the account and opens the right area automatically.', true],
  ['Public site', 'public', '/register', 'Create a homeowner account', 'Residents join for free with name, mobile, email and password, and give POPIA consent. Accounts are active immediately (verification is switched off for now).', true],
  ['Public site', 'public', '/privacy', 'Privacy policy', 'POPIA-aligned privacy notice in the company\'s name. The Admin can replace it with their own policy.', false],

  ['Homeowner', 'lerato', '/account', 'Homeowner dashboard', 'Everything at a glance: panic button (for clients), safety alerts, counts of reports, applications, quotations and bookings, and quick actions.', true],
  ['Homeowner', 'lerato', '/account/panic', 'Panic button', 'Anyone with a complete profile (home address + emergency contact) can hold the big red button for 3 seconds (or tick a box and press Send). It captures GPS where allowed, can be silent, and warns honestly that it needs internet.', true],
  ['Homeowner', 'sipho', '/account/panic', 'Panic button: finish your profile first', 'Until a resident has a home address and an emergency contact on file, the panic page shows a short checklist instead of the button, so responders always know where to go and who to call.', true],
  ['Homeowner', 'lerato', `/account/panic/${ids.panic_ref}`, 'Panic alert status', 'After sending, the client sees each step with times: received by server, shown in the control room, acknowledged, officer dispatched, resolved. Location keeps updating.', true],
  ['Homeowner', 'lerato', '/account/incidents/new', 'Report an incident', '22 crime and emergency categories, date and time, place with optional GPS, description, witnesses, injuries, and up to 5 photos, documents or videos.', true],
  ['Homeowner', 'lerato', '/account/incidents', 'My incident reports', 'Every report with its reference number and current status.', false],
  ['Homeowner', 'lerato', `/account/incidents/${ids.lerato_incident}`, 'Incident progress', 'Progress steps and a timeline of updates from the company: received, officer assigned, on scene, resolved, with the outcome.', true],
  ['Homeowner', 'lerato', '/account/assets/property', 'My properties', 'Homeowners register several properties. Each shows whether it is protected and the latest application.', false],
  ['Homeowner', 'lerato', '/account/assets/property/new', 'Register a property and apply', 'Address, GPS, existing security, access and concerns, photos, and the protection services wanted (armed response, monitoring, CCTV…).', true],
  ['Homeowner', 'lerato', '/account/assets/vehicle/new', 'Register a vehicle', 'Make, model, registration, colour, year, VIN, driver and photos, with an application for vehicle protection.', false],
  ['Homeowner', 'sipho', `/account/applications/property/${ids.app_sipho}`, 'Application tracking', 'The customer follows the application through review, quotation, payment and activation, and can message the company or send documents.', true],
  ['Homeowner', 'sipho', `/account/quotations/${ids.quote_sipho}`, 'Quotation to accept', 'An itemised quotation with VAT. The customer accepts or declines online. It can be printed or saved as PDF.', true],
  ['Homeowner', 'lerato', `/account/services/${ids.service_vip}`, 'Book a service', 'Pick date, time, place and duration and add instructions. The company confirms availability and price before it is booked.', true],
  ['Homeowner', 'lerato', `/account/requests/${ids.request_lerato}`, 'Booking request', 'The service request with its progress and any quotation from the company.', false],
  ['Homeowner', 'lerato', '/account/contacts', 'Emergency contacts', 'Up to 5 people to notify when the panic button is pressed (SMS sending switches on once a provider is connected).', false],
  ['Homeowner', 'lerato', '/account/notifications', 'Notifications', 'In-app updates about reports, applications, quotations, payments and bookings.', false],
  ['Homeowner', 'lerato', '/account/profile', 'Profile & security', 'Change details and password, see signed-in devices, sign out everywhere, and view POPIA consent records.', false],

  ['Security company', 'security', '/security', 'Live control room', 'Active panic alerts and open incidents with a live map, refreshing every 5 seconds, with an optional alarm sound. Acknowledge, open and dispatch, or call.', false],
  ['Security company', 'security', `/security/incidents/${ids.incident_panic}`, 'Panic incident: respond', 'Map with the client\'s location trail, protected property and access notes, emergency contacts, dispatch to an officer, status updates and notes.', false],
  ['Security company', 'security', `/security/incidents/${ids.incident_resolved}`, 'Officer response', 'The assigned officer marks Accepted, En route, On scene (arrival time recorded) and Completed, and adds photos and notes.', true],
  ['Security company', 'security', '/security/assignments', 'My assignments', 'Each officer sees the incidents assigned to them.', true],
  ['Security company', 'security', '/security/patrols', 'Patrol log', 'Officers check in at areas and checkpoints with notes and optional GPS.', true],
  ['Security company', 'security', '/security/homeowners', 'Homeowner lookup', 'Read-only lookup of homeowners. Contact details are partly hidden in lists, and opening a record is logged.', false],

  ['Admin', 'admin', '/admin', 'Admin dashboard', 'New reports, open incidents, active emergencies, applications, quotations awaiting acceptance, service requests, payments, clients and protected properties.', false],
  ['Admin', 'admin', '/admin/incidents', 'Incident management', 'Search and filter by status, category, priority and dates. Panic alerts are highlighted.', false],
  ['Admin', 'admin', `/admin/incidents/${ids.incident_open}`, 'Incident detail (admin)', 'Evidence, reporter details, map, internal notes, dispatch and status changes. Opening it marks the report as received.', false],
  ['Admin', 'admin', '/admin/applications', 'Protection applications', 'Residential and vehicle applications that need attention.', false],
  ['Admin', 'admin', `/admin/applications/property/${ids.app_anika}`, 'Application review & activation', 'Review the property and services, require an assessment, request info, prepare a quotation, approve, and activate protection (the customer becomes a client).', false],
  ['Admin', 'admin', `/admin/quotations/new/property/${ids.app_sipho}`, 'Prepare a quotation', 'Line items, quantities, prices, monthly or once-off, validity and VAT. Save a draft or send it to the customer.', false],
  ['Admin', 'admin', `/admin/quotations/${ids.quote_anika}`, 'Quotation & payments', 'The printable quotation, its status, and recording EFT, cash or card payments against it.', false],
  ['Admin', 'admin', '/admin/requests', 'Service requests & bookings', 'Incoming bookings by date, to review, quote, confirm and complete.', false],
  ['Admin', 'admin', '/admin/services', 'Services', 'The company manages its services marketplace: prices, availability, area and photos.', false],
  ['Admin', 'admin', '/admin/pricelist', 'Price list management', 'Set the pricing type, price, unit, order and visibility of every service on one screen. Nothing is deleted; untick Listed to hide a service.', false],
  ['Admin', 'admin', '/admin/payments', 'Payments', 'All payments with Excel export. PayFast online payment switches on once merchant keys are added.', false],
  ['Admin', 'admin', '/admin/homeowners', 'Homeowners', 'All homeowners with status and client type. Verify, suspend or mark as client from each record.', false],
  ['Admin', 'admin', '/admin/leads', 'Leads', 'Community members who reported incidents or pressed panic but are not clients yet. One click sends a protection offer into their app (at most once every 14 days).', false],
  ['Admin', 'admin', '/admin/alerts', 'Community safety alerts', 'Publish alerts that appear on the public site and every homeowner dashboard until they expire.', false],
  ['Admin', 'admin', '/admin/reports', 'Reports', 'Incidents by category, response times, application conversion, quotes accepted, revenue and services, with Excel export and Save as PDF.', false],
  ['Admin', 'admin', '/admin/staff', 'Admin & security logins', 'Create and disable Admin and Security company logins.', false],
  ['Admin', 'admin', '/admin/branding', 'Branding & profile', 'Company name, logo, colours, welcome text, services, contacts, emergency number, payment instructions and policies, without touching code.', false],
  ['Admin', 'admin', '/admin/audit', 'Audit log', 'Who did what and when: sign-ins, record views, status changes, payments and exports.', false],
];

(async () => {
  const browser = await puppeteer.launch({ executablePath: EDGE, headless: true, args: ['--no-first-run', '--disable-features=Translate'] });
  const contexts = {};
  const ctxFor = async (u) => {
    if (contexts[u]) return contexts[u];
    const ctx = await browser.createBrowserContext();
    const def = users[u];
    if (def) {
      const pg = await ctx.newPage();
      await pg.goto(BASE + def.login, { waitUntil: 'networkidle2' });
      await pg.type('#identifier', def.email);
      await pg.type('#password', PASS);
      await Promise.all([pg.waitForNavigation({ waitUntil: 'networkidle2' }), pg.click('form.panel button.btn-brand')]);
      await pg.close();
    }
    contexts[u] = ctx;
    return ctx;
  };
  const catalog = [];
  let n = 0;
  for (const [section, user, url, title, explain, phone] of P) {
    n++;
    const slug = String(n).padStart(2, '0') + '-' + title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    let pg;
    for (let attempt = 0; ; attempt++) {
      try { pg = await (await ctxFor(user)).newPage(); break; } catch (e) {
        if (attempt >= 2) throw e;
        delete contexts[user]; // Edge occasionally drops a context; sign in again
      }
    }
    // Desktop
    await pg.setViewport({ width: 1366, height: 768, deviceScaleFactor: 1 });
    await pg.goto(BASE + url, { waitUntil: 'networkidle2' });
    await new Promise((r) => setTimeout(r, url === '/security' ? 2500 : 400));
    const status = await pg.evaluate(() => document.title);
    await pg.screenshot({ path: path.join(out, 'desktop', slug + '.png') });
    // Phone (Play Store size 1080x1920)
    let mobileFile = null;
    if (phone) {
      await pg.setViewport({ width: 432, height: 768, deviceScaleFactor: 2.5, isMobile: true, hasTouch: true });
      await pg.goto(BASE + url, { waitUntil: 'networkidle2' });
      await new Promise((r) => setTimeout(r, 400));
      mobileFile = 'mobile/' + slug + '.png';
      await pg.screenshot({ path: path.join(out, mobileFile) });
    }
    await pg.close();
    catalog.push({ n, section, user, url, title, explain, desktop: 'desktop/' + slug + '.png', mobile: mobileFile });
    console.log(String(n).padStart(2), title, '·', status);
  }
  fs.writeFileSync(path.join(out, 'catalog.json'), JSON.stringify(catalog, null, 2));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
