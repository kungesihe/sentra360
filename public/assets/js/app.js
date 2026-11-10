// SENTRA 360 — progressive enhancements (no inline scripts; CSP-friendly).
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const csrf = () => ($('meta[name="csrf-token"]') || {}).content || '';

  // Installable app (PWA / Play Store). The worker never caches account pages.
  if ('serviceWorker' in navigator && location.protocol === 'https:' || location.hostname === 'localhost') {
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  }

  // ---------------------------------------------------------------- generic
  $$('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); }));
  $$('[data-confirm-click]').forEach((b) => b.addEventListener('click', (e) => { if (!window.confirm(b.dataset.confirmClick)) e.preventDefault(); }));
  $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  $$('form[data-autosubmit]').forEach((f) => f.submit());

  $$('[data-toggle-password]').forEach((btn) => btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.togglePassword);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.innerHTML = input.type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
  }));

  $$('[data-color-sync]').forEach((picker) => {
    const text = document.getElementById(picker.dataset.colorSync);
    picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); });
    text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
  });

  // Incident status: show the "resolution" box when resolving/closing.
  $$('[data-resolution-toggle]').forEach((sel) => {
    const field = $('[data-resolution-field]', sel.form);
    const upd = () => { const on = ['resolved', 'closed'].includes(sel.value); field.classList.toggle('d-none', !on); field.required = on; };
    sel.addEventListener('change', upd); upd();
  });

  // ------------------------------------------------------------ geolocation
  function getPosition(timeout) {
    return new Promise((resolve) => {
      if (!navigator.geolocation) return resolve({ status: 'unavailable' });
      navigator.geolocation.getCurrentPosition(
        (p) => resolve({ status: 'captured', lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy) }),
        (err) => resolve({ status: err.code === 1 ? 'denied' : 'unavailable' }),
        { enableHighAccuracy: true, timeout: timeout || 8000, maximumAge: 15000 }
      );
    });
  }
  $$('[data-geo-fill]').forEach((btn) => btn.addEventListener('click', async () => {
    const form = btn.form || btn.closest('form');
    const status = document.getElementById(btn.dataset.geoStatus);
    if (status) status.textContent = 'Getting your location…';
    const pos = await getPosition(10000);
    if (pos.status === 'captured') {
      $('[name="latitude"]', form).value = pos.lat.toFixed(7);
      $('[name="longitude"]', form).value = pos.lng.toFixed(7);
      if (status) status.textContent = `GPS location attached (accurate to about ${pos.acc} m).`;
    } else if (status) {
      status.textContent = pos.status === 'denied' ? 'Location permission was refused, so no GPS was attached.' : 'Location is not available on this device.';
    }
  }));

  // ------------------------------------------------------------ panic button
  const panicForm = $('[data-panic-form]');
  if (panicForm) {
    const btn = $('[data-panic-hold]', panicForm);
    const ring = $('[data-panic-ring]', panicForm);
    const statusEl = $('[data-panic-status]', panicForm);
    const confirmBox = $('[data-panic-confirm]', panicForm);
    const offline = $('[data-offline-banner]');
    const HOLD_MS = 3000;
    let start = 0, raf = 0, sending = false;

    const updOnline = () => offline && offline.classList.toggle('d-none', navigator.onLine);
    window.addEventListener('online', updOnline); window.addEventListener('offline', updOnline); updOnline();

    async function send() {
      if (sending) return;
      if (!navigator.onLine) { statusEl.textContent = 'You are offline: the alert was NOT sent. Call for help by phone.'; return; }
      sending = true;
      statusEl.textContent = 'Getting your location and sending the alert…';
      const pos = await getPosition(6000);
      $('#location_status').value = pos.status;
      if (pos.status === 'captured') { $('#latitude').value = pos.lat.toFixed(7); $('#longitude').value = pos.lng.toFixed(7); $('#accuracy').value = pos.acc; }
      confirmBox.checked = true;
      panicForm.submit();
    }
    const tick = () => {
      const p = Math.min(100, ((performance.now() - start) / HOLD_MS) * 100);
      ring.style.setProperty('--p', p);
      if (p >= 100) { stop(false); if (navigator.vibrate) navigator.vibrate(200); send(); return; }
      raf = requestAnimationFrame(tick);
    };
    const begin = (e) => { if (sending) return; e.preventDefault(); start = performance.now(); btn.classList.add('holding'); statusEl.textContent = 'Keep holding…'; raf = requestAnimationFrame(tick); };
    const stop = (reset = true) => { cancelAnimationFrame(raf); btn.classList.remove('holding'); if (reset && !sending) { ring.style.setProperty('--p', 0); statusEl.textContent = 'Released too early: nothing was sent. Hold for 3 full seconds.'; } };
    btn.addEventListener('pointerdown', begin);
    ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) => btn.addEventListener(ev, () => { if (start && !sending) stop(true); start = 0; }));
    btn.addEventListener('keydown', (e) => { if ((e.key === ' ' || e.key === 'Enter') && !e.repeat) begin(e); });
    btn.addEventListener('keyup', (e) => { if (e.key === ' ' || e.key === 'Enter') { if (!sending) stop(true); } });
    btn.addEventListener('contextmenu', (e) => e.preventDefault());
    panicForm.addEventListener('submit', async (e) => {
      if (sending) return;           // already handled by the hold
      e.preventDefault();
      if (!confirmBox.checked) { statusEl.textContent = 'Tick the confirmation box first, or hold the red button.'; return; }
      send();
    });
  }

  // Siren for non-silent alerts (WebAudio; only plays after the user has interacted).
  let audioCtx;
  function beep(times, freq) {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      for (let i = 0; i < times; i++) {
        const o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = 'square'; o.frequency.value = i % 2 ? freq * 0.75 : freq;
        o.connect(g); g.connect(audioCtx.destination);
        const t = audioCtx.currentTime + i * 0.45;
        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.4, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.4);
        o.start(t); o.stop(t + 0.42);
      }
    } catch (e) { /* audio not supported */ }
  }

  // ------------------------------------------------------ panic status page
  const track = $('[data-panic-track]');
  if (track) {
    const ref = track.dataset.panicTrack;
    let open = track.dataset.panicOpen === '1';
    if (open && track.dataset.panicSilent === '0') { beep(8, 880); }
    const locNote = $('[data-loc-updates]');
    let sentLoc = 0;
    async function poll() {
      try {
        const r = await fetch(`/account/panic/${encodeURIComponent(ref)}/status`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!r.ok) return;
        const j = await r.json();
        $('[data-panic-label]').textContent = j.label;
        const times = { submitted: j.submitted_at, delivered: j.delivered_at, acknowledged: j.acknowledged_at, dispatched: j.dispatched_at, resolved: j.resolved_at };
        $$('[data-step]').forEach((li) => {
          const at = times[li.dataset.step];
          $('[data-step-icon]', li).className = 'bi ' + (at ? 'bi-check-circle-fill text-success' : 'bi-circle text-secondary');
          $('[data-step-time]', li).textContent = at ? at.slice(11, 19) : 'waiting…';
        });
        const off = $('[data-panic-officer]');
        if (off) off.textContent = j.officer ? `Response officer ${j.officer.name}: ${j.officer.status}` : '';
        if (['resolved', 'cancelled'].includes(j.status) && open) { open = false; window.location.reload(); }
      } catch (e) { /* offline; try again */ }
    }
    async function sendLocation() {
      if (!open) return;
      const pos = await getPosition(8000);
      if (pos.status !== 'captured') return;
      const body = new URLSearchParams({ latitude: pos.lat.toFixed(7), longitude: pos.lng.toFixed(7), accuracy: pos.acc });
      try {
        const r = await fetch(`/account/panic/${encodeURIComponent(ref)}/location`, { method: 'POST', headers: { 'X-CSRF-Token': csrf() }, body });
        if (r.ok) { sentLoc++; if (locNote) locNote.textContent = ` · live location updates sent: ${sentLoc}`; }
      } catch (e) { /* ignore */ }
    }
    poll(); setInterval(poll, 4000);
    if (open) { sendLocation(); setInterval(sendLocation, 20000); }
  }

  // ------------------------------------------------------------------- maps
  // Leaflet loads asynchronously so a slow map download never blocks the page.
  function whenLeaflet(cb) {
    if (window.L) return cb(window.L);
    const s = document.getElementById('leaflet-js');
    if (s) s.addEventListener('load', () => window.L && cb(window.L));
  }
  const tiles = (map) => window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap contributors' }).addTo(map);
  const dot = (color) => window.L.divIcon({ className: '', html: `<span style="display:block;width:18px;height:18px;border-radius:50%;background:${color};border:3px solid #fff;box-shadow:0 0 0 2px ${color}"></span>`, iconSize: [18, 18], iconAnchor: [9, 9] });

  whenLeaflet((L) => {
    $$('[data-map]').forEach((el) => {
      const lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
      let trail = []; try { trail = JSON.parse(el.dataset.trail || '[]'); } catch (e) { trail = []; }
      const center = !isNaN(lat) ? [lat, lng] : trail[trail.length - 1];
      if (!center) return;
      const map = L.map(el).setView(center, 16); tiles(map);
      L.marker(center, { icon: dot('#DC2626') }).addTo(map).bindPopup(el.dataset.label || '');
      if (trail.length > 1) L.polyline(trail, { color: '#DC2626', weight: 3, opacity: .7 }).addTo(map);
    });
  });

  // ----------------------------------------------------------- control room
  const feedMap = $('[data-feed-map]');
  if (feedMap) {
    const panicList = $('[data-panic-list]'), incList = $('[data-incident-list]');
    const tpl = $('#tpl-panic');
    const soundToggle = $('[data-feed-sound]');
    try { soundToggle.checked = localStorage.getItem('sentra-sound') === '1'; } catch (e) { /* storage blocked */ }
    soundToggle.addEventListener('change', () => { try { localStorage.setItem('sentra-sound', soundToggle.checked ? '1' : '0'); } catch (e) { /* ignore */ } if (soundToggle.checked) beep(1, 660); });
    let map = null, layer = null, fitted = false, L = null;
    whenLeaflet((lib) => {
      L = lib; map = L.map(feedMap).setView([-26.1, 28.05], 11); tiles(map); layer = L.layerGroup().addTo(map);
      if (lastData) drawMap(lastData, 'redraw', true);
    });
    const seen = new Set();
    let first = true, lastSig = '', mapSig = '', lastData = null;
    // The map draws on its own schedule: the map library may finish loading after the first data arrives.
    function drawMap(j, sig, refit) {
      lastData = j;
      if (!layer || (sig === mapSig && !refit)) return;
      mapSig = sig;
      layer.clearLayers();
      const pts = [];
      j.panics.filter((p) => p.latitude).forEach((p) => { const ll = [+p.latitude, +p.longitude]; pts.push(ll); L.marker(ll, { icon: dot('#DC2626') }).bindPopup(`${p.reference} · ${p.first_name} ${p.surname}`.replace(/[<>&]/g, '')).addTo(layer); });
      j.incidents.filter((i) => i.latitude).forEach((i) => { const ll = [+i.latitude, +i.longitude]; pts.push(ll); L.marker(ll, { icon: dot(i.priority === 'high' || i.priority === 'critical' ? '#EA580C' : '#2563EB') }).bindPopup(`${i.reference} · ${i.title}`.replace(/[<>&]/g, '')).addTo(layer); });
      if (pts.length && (!fitted || refit)) { map.fitBounds(pts, { padding: [40, 40], maxZoom: 15 }); fitted = true; }
    }
    const age = (ts) => { const s = Math.max(0, (Date.now() - new Date(ts.replace(' ', 'T')).getTime()) / 1000); return s < 90 ? `${Math.round(s)} s ago` : `${Math.round(s / 60)} min ago`; };

    async function load() {
      try {
        const r = await fetch('/security/feed', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!r.ok) throw new Error(String(r.status));
        const j = await r.json();
        $('[data-feed-offline]').classList.add('d-none');
        $('[data-feed-state]').className = 'badge text-bg-success';
        $('[data-feed-time]').textContent = j.now;
        $('[data-panic-count]').textContent = j.panics.length;
        $('[data-incident-count]').textContent = j.incidents.length;
        $('[data-my-count]').textContent = j.my_assignments;

        let newOnes = 0;
        j.panics.forEach((p) => { if (!seen.has(p.id)) { seen.add(p.id); if (!first) newOnes++; } });
        // Only rebuild the lists when something changed, so buttons aren't replaced mid-click.
        const sig = JSON.stringify([j.panics.map((p) => [p.id, p.status, p.latitude, p.longitude]), j.incidents.map((i) => [i.id, i.status, i.officers])]);
        drawMap(j, sig, newOnes > 0);
        if (sig === lastSig) {
          $$('[data-age-ts]').forEach((el) => { el.textContent = `Raised ${age(el.dataset.ageTs)}`; });
          first = false;
          return;
        }
        lastSig = sig;
        panicList.replaceChildren();
        j.panics.forEach((p) => {
          const card = tpl.content.firstElementChild.cloneNode(true);
          if (['acknowledged', 'dispatched'].includes(p.status)) card.classList.add('acked');
          const set = (k, v) => { $(`[data-f="${k}"]`, card).textContent = v; };
          set('reference', p.reference); set('status_label', p.status_label);
          set('who', `${p.first_name} ${p.surname}`);
          set('where', p.property_name ? `${p.property_name}, ${p.address}, ${p.suburb}` : (p.latitude ? `GPS ${(+p.latitude).toFixed(5)}, ${(+p.longitude).toFixed(5)}${p.accuracy_m ? ' (±' + p.accuracy_m + ' m)' : ''}` : `No GPS (${p.location_status})`));
          set('extra', p.extra_info ? `“${p.extra_info}”` : '');
          set('age', `Raised ${age(p.created_at)}`);
          $('[data-f="age"]', card).dataset.ageTs = p.created_at;
          const ack = $('[data-ack]', card);
          if (['submitted', 'delivered'].includes(p.status)) { ack.action = `/security/incidents/${p.incident_id}/acknowledge`; $('[name="_csrf"]', ack).value = csrf(); } else { ack.remove(); }
          $('[data-open]', card).href = `/security/incidents/${p.incident_id}`;
          $('[data-call]', card).href = `tel:${p.mobile}`;
          panicList.appendChild(card);
        });
        if (!j.panics.length) { const d = document.createElement('div'); d.className = 'panel small text-body-secondary'; d.textContent = 'No active panic alerts.'; panicList.appendChild(d); }
        if (newOnes && soundToggle.checked) beep(10, 988);
        if (newOnes && document.hidden) document.title = `(${newOnes}) PANIC · Control room`;

        incList.replaceChildren();
        j.incidents.forEach((i) => {
          const a = document.createElement('a');
          a.href = `/security/incidents/${i.id}`;
          a.className = 'list-group-item list-group-item-action' + (i.priority === 'critical' ? ' list-group-item-danger' : i.priority === 'high' ? ' list-group-item-warning' : '');
          const top = document.createElement('div'); top.className = 'd-flex justify-content-between';
          const b = document.createElement('strong'); b.textContent = `${i.reference} · ${i.title}`;
          const s = document.createElement('span'); s.className = 'small'; s.textContent = i.status_label;
          top.append(b, s);
          const sub = document.createElement('div'); sub.className = 'small text-body-secondary';
          sub.textContent = `${i.location_text || ''} · ${age(i.created_at)}${i.officers ? ' · ' + i.officers : ' · not dispatched'}`;
          a.append(top, sub); incList.appendChild(a);
        });
        if (!j.incidents.length) { const d = document.createElement('div'); d.className = 'list-group-item small text-body-secondary'; d.textContent = 'No open incidents.'; incList.appendChild(d); }

        first = false;
      } catch (e) {
        $('[data-feed-offline]').classList.remove('d-none');
        $('[data-feed-state]').className = 'badge text-bg-danger';
      }
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) document.title = 'Control room'; });
    load(); setInterval(load, 5000);
  }
})();
