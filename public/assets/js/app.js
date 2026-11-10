// SENTRA 360 — small progressive enhancements (no inline scripts; CSP-friendly).
(function () {
  'use strict';

  // Company sign-up: build the web address from the company name and check availability.
  const nameInput = document.querySelector('[data-slug-source]');
  const slugInput = document.querySelector('[data-slug-input]');
  const slugStatus = document.querySelector('[data-slug-status]');
  const slugPreview = document.querySelector('[data-slug-preview]');
  if (slugInput) {
    let touched = slugInput.value !== '';
    let timer;
    const slugify = (s) => s.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim()
      .replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '').slice(0, 40);
    const check = () => {
      const v = slugInput.value;
      if (slugPreview) slugPreview.textContent = v || 'your-company';
      clearTimeout(timer);
      if (!v) { slugStatus.textContent = ''; return; }
      timer = setTimeout(async () => {
        try {
          const r = await fetch('/api/slug-available?slug=' + encodeURIComponent(v));
          const j = await r.json();
          slugStatus.className = 'small ' + (j.available ? 'text-success' : 'text-danger');
          slugStatus.textContent = !j.valid ? 'Use 3–40 lowercase letters, numbers or hyphens.'
            : (j.available ? 'Available' : 'Already taken');
        } catch (e) { slugStatus.textContent = ''; }
      }, 300);
    };
    if (nameInput) nameInput.addEventListener('input', () => { if (!touched) { slugInput.value = slugify(nameInput.value); check(); } });
    slugInput.addEventListener('input', () => { touched = true; slugInput.value = slugify(slugInput.value); check(); });
    check();
  }

  // Colour pickers mirror into their text field.
  document.querySelectorAll('[data-color-sync]').forEach((picker) => {
    const text = document.getElementById(picker.dataset.colorSync);
    picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); });
    text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
  });

  // Confirm dangerous actions.
  document.querySelectorAll('form[data-confirm]').forEach((f) => {
    f.addEventListener('submit', (e) => { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // Show / hide password.
  document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePassword);
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.innerHTML = input.type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    });
  });
})();
