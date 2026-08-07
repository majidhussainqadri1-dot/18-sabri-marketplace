(() => {
  'use strict';
  if (typeof MKT_APP === 'undefined') return;
  const toast = (message, isError = false) => {
    const el = document.querySelector('[data-mkt-toast]');
    if (!el) return;
    el.hidden = false;
    el.textContent = message;
    el.setAttribute('data-state', isError ? 'error' : 'success');
    window.setTimeout(() => { el.hidden = true; }, 5500);
  };
  const lines = (value) => String(value || '').split(/\r?\n|,/).map(v => v.trim()).filter(Boolean);
  const request = async (path, body) => {
    const response = await fetch(MKT_APP.restUrl + path.replace(/^\//, ''), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': MKT_APP.nonce,
        'Idempotency-Key': (self.crypto && crypto.randomUUID) ? crypto.randomUUID() : `mkt-${Date.now()}-${Math.random().toString(16).slice(2)}`
      },
      body: JSON.stringify(body)
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || MKT_APP.strings.error);
    return data;
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-mkt-evidence-form]');
    if (!form) return;
    event.preventDefault();
    const fd = new FormData(form);
    const listingId = form.dataset.listingId || '';
    const payload = {
      expected_listing_version: Number(form.dataset.listingVersion || 0),
      expected_evidence_version: Number(form.dataset.evidenceVersion || 0),
      evidence: {
        ingredients: lines(fd.get('ingredients')),
        manufacturer: String(fd.get('manufacturer') || ''),
        license_or_registration: String(fd.get('license_or_registration') || ''),
        batch_number: String(fd.get('batch_number') || ''),
        expiry_date: String(fd.get('expiry_date') || ''),
        not_applicable: {
          license_or_registration: String(fd.get('na_license_or_registration') || ''),
          batch_number: String(fd.get('na_batch_number') || ''),
          expiry_date: String(fd.get('na_expiry_date') || '')
        },
        claims: lines(fd.get('claims')),
        source_refs: lines(fd.get('source_refs'))
      }
    };
    try {
      toast(MKT_APP.strings.working);
      await request(`listings/${encodeURIComponent(listingId)}/evidence`, payload);
      toast('Evidence saved. Reloading the current listing version…');
      window.setTimeout(() => window.location.reload(), 500);
    } catch (error) {
      toast(error.message || MKT_APP.strings.error, true);
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-mkt-evidence-review]');
    if (!form) return;
    event.preventDefault();
    const submitter = event.submitter;
    const decision = submitter && submitter.value ? submitter.value : '';
    if (!['approved', 'rejected'].includes(decision)) return;
    const fd = new FormData(form);
    try {
      toast(MKT_APP.strings.working);
      await request(`listings/${encodeURIComponent(form.dataset.listingId || '')}/evidence/review`, {
        expected_version: Number(form.dataset.evidenceVersion || 0),
        decision,
        note: String(fd.get('note') || '')
      });
      toast(`Evidence ${decision}. Reloading…`);
      window.setTimeout(() => window.location.reload(), 500);
    } catch (error) {
      toast(error.message || MKT_APP.strings.error, true);
    }
  });
})();
