<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
  <span class="badge text-bg-success" data-feed-state><i class="bi bi-circle-fill"></i> Live</span>
  <span class="small text-body-secondary">Updated <span data-feed-time>—</span> · refreshes every 5 seconds</span>
  <div class="form-check form-switch ms-auto mb-0"><input class="form-check-input" type="checkbox" role="switch" id="sound" data-feed-sound><label class="form-check-label small" for="sound">Alarm sound for new panic alerts</label></div>
  <a href="/security/incidents/new" class="btn btn-sm btn-outline-dark">Log incident</a>
</div>
<div class="alert alert-warning small d-none" data-feed-offline>Connection to the server lost. The list may be out of date. Retrying…</div>
<div class="row g-3">
  <div class="col-xl-5">
    <h2 class="h6 text-danger"><i class="bi bi-broadcast-pin"></i> Active panic alerts <span class="badge text-bg-danger" data-panic-count>0</span></h2>
    <div data-panic-list class="mb-4"><div class="panel small text-body-secondary">Loading…</div></div>
    <h2 class="h6"><i class="bi bi-megaphone"></i> Open incidents <span class="badge text-bg-secondary" data-incident-count>0</span></h2>
    <div data-incident-list class="list-group"></div>
  </div>
  <div class="col-xl-7">
    <div class="map-box map-tall" data-feed-map data-lat="<?= e((string) area()['lat']) ?>" data-lng="<?= e((string) area()['lng']) ?>"></div>
    <p class="small text-body-secondary mt-2">Map shows alerts and incidents with GPS. Red = panic, orange = high priority, blue = other. Map © OpenStreetMap contributors.</p>
    <div class="panel small"><i class="bi bi-person-walking"></i> You have <strong data-my-count>0</strong> open assignment(s). <a href="/security/assignments">View</a></div>
  </div>
</div>
<template id="tpl-panic"><div class="panic-card"><div class="d-flex justify-content-between"><strong data-f="reference"></strong><span class="badge text-bg-light" data-f="status_label"></span></div><div data-f="who" class="fw-semibold"></div><div class="small" data-f="where"></div><div class="small" data-f="extra"></div><div class="small" data-f="age"></div><div class="small fw-semibold" data-f="callback"></div><div class="d-flex flex-wrap gap-2 mt-2"><form method="post" data-ack><input type="hidden" name="_csrf"><input type="hidden" name="return" value="feed"><button class="btn btn-sm btn-light fw-bold">Acknowledge</button></form><form method="post" data-callback target="_blank"><input type="hidden" name="_csrf"><input type="hidden" name="method" value="whatsapp"><button class="btn btn-sm btn-success fw-bold"><i class="bi bi-whatsapp"></i> WhatsApp call-back</button></form><a class="btn btn-sm btn-outline-light" data-open>Open &amp; dispatch</a><a class="btn btn-sm btn-outline-light" data-call>Call</a></div></div></template>
