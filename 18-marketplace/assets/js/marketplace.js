(() => {
  'use strict';
  const app = window.MKT_APP || {};
  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const idempotency = prefix => `${prefix}:${Date.now()}:${crypto.getRandomValues(new Uint32Array(2)).join('-')}`;

  async function api(path, options = {}) {
    const headers = { 'Accept': 'application/json', ...(options.headers || {}) };
    const method = String(options.method || 'GET').toUpperCase();
    if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    if (!['GET', 'HEAD'].includes(method) && !headers['Idempotency-Key']) headers['Idempotency-Key'] = idempotency('mkt');
    if (app.nonce) headers['X-WP-Nonce'] = app.nonce;
    const response = await fetch(`${app.restUrl}${path.replace(/^\//, '')}`, { credentials: 'same-origin', ...options, headers });
    const data = await response.json().catch(() => ({ code: 'invalid_response', message: app.strings?.error || 'Request failed.' }));
    if (!response.ok) {
      const error = new Error(data.message || app.strings?.error || 'Request failed.');
      error.code = data.code; error.data = data.data || {}; error.status = response.status;
      throw error;
    }
    return data;
  }

  function toast(message, kind = 'success') {
    const node = qs('[data-mkt-toast]');
    if (!node) return;
    node.textContent = message; node.dataset.kind = kind; node.hidden = false;
    clearTimeout(node._timer); node._timer = setTimeout(() => { node.hidden = true; }, 6000);
  }

  function buttonBusy(button, busy) {
    if (!button) return;
    if (busy) { button.dataset.originalText = button.textContent; button.textContent = app.strings?.working || 'Working…'; button.disabled = true; button.setAttribute('aria-busy', 'true'); }
    else { button.textContent = button.dataset.originalText || button.textContent; button.disabled = false; button.removeAttribute('aria-busy'); }
  }

  function objectFromForm(form) {
    const fd = new FormData(form), out = {};
    for (const [key, value] of fd.entries()) {
      const arrayMatch = key.match(/^(.+)\[\]$/), nestedMatch = key.match(/^([^[]+)\[([^]]+)\]$/);
      if (arrayMatch) { const k = arrayMatch[1]; (out[k] ||= []).push(value); }
      else if (nestedMatch) { const [, parent, child] = nestedMatch; (out[parent] ||= {})[child] = value; }
      else out[key] = value;
    }
    return out;
  }

  function openModal(modal) {
    if (!modal) return;
    modal.hidden = false; document.body.style.overflow = 'hidden';
    modal._returnFocus = document.activeElement;
    const first = qs('button,input,select,textarea,a[href]', modal); if (first) first.focus();
  }
  function closeModal(modal) {
    if (!modal) return;
    modal.hidden = true; document.body.style.overflow = '';
    if (modal._returnFocus?.focus) modal._returnFocus.focus();
  }
  qsa('[data-mkt-close]').forEach(button => button.addEventListener('click', () => closeModal(button.closest('.mkt-modal'))));
  qsa('.mkt-modal').forEach(modal => {
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(modal); });
    modal.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeModal(modal);
      if (event.key === 'Tab') {
        const focusable = qsa('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href]', modal).filter(el => !el.hidden);
        if (!focusable.length) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    });
  });

  const detail = qs('[data-listing-id]');
  if (detail) {
    const listingId = detail.dataset.listingId;
    qs('[data-mkt-chat]', detail)?.addEventListener('click', async event => {
      const button = event.currentTarget; buttonBusy(button, true);
      try { const result = await api(`listings/${listingId}/chat`, { method: 'POST', body: '{}' }); window.location.assign(result.url); }
      catch (error) { toast(error.message, 'error'); buttonBusy(button, false); }
    });
    qs('[data-mkt-save]', detail)?.addEventListener('click', async event => {
      const button = event.currentTarget; buttonBusy(button, true);
      try { await api(`listings/${listingId}/save`, { method: 'POST', body: JSON.stringify({ save: true }) }); toast(app.strings?.saved || 'Saved.'); button.textContent = '♥ Saved'; }
      catch (error) { toast(error.message, 'error'); }
      finally { buttonBusy(button, false); }
    });
    qs('[data-mkt-offer]', detail)?.addEventListener('click', () => openModal(qs('[data-mkt-offer-modal]')));
    qs('[data-mkt-report]', detail)?.addEventListener('click', () => openModal(qs('[data-mkt-report-modal]')));
    qs('[data-mkt-offer-form]')?.addEventListener('submit', async event => {
      event.preventDefault(); const form = event.currentTarget, button = qs('button[type=submit]', form); buttonBusy(button, true);
      const payload = objectFromForm(form), key = idempotency('offer');
      try { await api(`listings/${listingId}/offers`, { method: 'POST', headers: { 'Idempotency-Key': key }, body: JSON.stringify(payload) }); closeModal(form.closest('.mkt-modal')); form.reset(); toast('Offer submitted.'); }
      catch (error) { toast(error.message, 'error'); }
      finally { buttonBusy(button, false); }
    });
    qs('[data-mkt-report-form]')?.addEventListener('submit', async event => {
      event.preventDefault(); const form = event.currentTarget, button = qs('button[type=submit]', form); buttonBusy(button, true);
      const payload = { ...objectFromForm(form), target_type: 'listing', target_public_id: listingId };
      try { await api('reports', { method: 'POST', body: JSON.stringify(payload) }); closeModal(form.closest('.mkt-modal')); form.reset(); toast('Report submitted for review.'); }
      catch (error) { toast(error.message, 'error'); }
      finally { buttonBusy(button, false); }
    });
  }

  qs('[data-mkt-share]')?.addEventListener('click', async event => {
    const url = event.currentTarget.dataset.shareUrl || location.href;
    try { if (navigator.share) await navigator.share({ url, title: document.title }); else { await navigator.clipboard.writeText(url); toast('Link copied.'); } }
    catch (error) { if (error.name !== 'AbortError') toast('The link could not be shared.', 'error'); }
  });

  const listingForm = qs('[data-mkt-listing-form]');
  if (listingForm) {
    listingForm.addEventListener('submit', async event => {
      event.preventDefault(); const form = event.currentTarget, button = qs('button[type=submit]', form); buttonBusy(button, true);
      const payload = objectFromForm(form);
      const mediaProviderId = String(payload.media_provider_public_id || '').trim();
      const mediaAltText = String(payload.media_alt_text || '').trim();
      delete payload.media_provider_public_id; delete payload.media_alt_text;
      ['price','quantity'].forEach(key => { if (payload[key] !== undefined) payload[key] = Number(payload[key]); });
      payload.delivery_modes ||= []; payload.contact_modes ||= []; payload.declarations ||= {};
      const listingId = form.dataset.listingId || '';
      if (listingId) payload.version = Number(form.dataset.listingVersion || 0);
      try {
        const listing = await api(listingId ? `listings/${listingId}` : 'listings', { method: listingId ? 'PATCH' : 'POST', body: JSON.stringify(payload) });
        form.dataset.listingId = listing.public_id; form.dataset.listingVersion = String(listing.version);
        if (mediaProviderId) {
          await api(`listings/${listing.public_id}/media`, { method: 'POST', body: JSON.stringify({ provider: 'central-media', provider_public_id: mediaProviderId, public_id: mediaProviderId, alt_text: mediaAltText }) });
        }
        toast(listingId ? 'Draft saved.' : 'Draft created.');
        await sleep(500);
        window.location.assign(`${location.origin}/marketplace/sell/?id=${encodeURIComponent(listing.public_id)}`);
      } catch (error) {
        const details = error.data?.errors?.map(item => item.message).join(' ') || error.message; toast(details, 'error');
        const invalid = error.data?.errors?.[0]?.field; if (invalid) qs(`[name="${CSS.escape(invalid)}"]`, form)?.focus();
      } finally { buttonBusy(button, false); }
    });
    qs('[data-mkt-submit-review]', listingForm)?.addEventListener('click', async event => {
      const button = event.currentTarget, listingId = listingForm.dataset.listingId; if (!listingId) return;
      buttonBusy(button, true);
      try {
        const updated = await api(`listings/${listingId}/submit`, { method: 'POST', body: JSON.stringify({ version: Number(listingForm.dataset.listingVersion || 0) }) });
        listingForm.dataset.listingVersion = String(updated.version); toast('Listing submitted for review.'); await sleep(500); window.location.assign(`${location.origin}/marketplace/dashboard/?tab=listings`);
      } catch (error) { toast(error.message, 'error'); }
      finally { buttonBusy(button, false); }
    });
    qsa('[data-mkt-remove-media]', listingForm).forEach(button => button.addEventListener('click', async () => {
      const mediaId = button.dataset.mktRemoveMedia, listingId = listingForm.dataset.listingId; if (!listingId || !mediaId) return;
      if (!confirm('Remove this media reference?')) return;
      buttonBusy(button, true);
      try { await api(`listings/${listingId}/media/${mediaId}`, { method: 'DELETE' }); button.closest('li')?.remove(); toast('Media reference removed.'); }
      catch (error) { toast(error.message, 'error'); buttonBusy(button, false); }
    }));
  }

  qsa('[data-mkt-tab]').forEach(tab => tab.addEventListener('click', () => {
    qsa('[data-mkt-tab]').forEach(node => node.setAttribute('aria-selected', node === tab ? 'true' : 'false'));
    qsa('[data-mkt-panel]').forEach(panel => { panel.hidden = panel.dataset.mktPanel !== tab.dataset.mktTab; });
  }));
  const requestedTab = new URLSearchParams(location.search).get('tab'); if (requestedTab) qs(`[data-mkt-tab="${CSS.escape(requestedTab)}"]`)?.click();

  qsa('[data-mkt-listing-row]').forEach(row => qsa('[data-mkt-listing-action]', row).forEach(button => button.addEventListener('click', async () => {
    const to = button.dataset.mktListingAction; if (!confirm(`Change listing status to ${to}?`)) return;
    buttonBusy(button, true);
    try {
      const updated = await api(`listings/${row.dataset.listingId}/transition`, { method: 'POST', body: JSON.stringify({ to, version: Number(row.dataset.listingVersion), reason: 'seller_action' }) });
      row.dataset.listingVersion = String(updated.version); qs('.mkt-status', row).textContent = updated.status.replaceAll('_',' '); toast('Listing updated.'); location.reload();
    } catch (error) { toast(error.message, 'error'); }
    finally { buttonBusy(button, false); }
  })));

  qsa('[data-mkt-offer-row]').forEach(row => qsa('[data-mkt-offer-action]', row).forEach(button => button.addEventListener('click', async () => {
    const to = button.dataset.mktOfferAction, payload = { to, version: Number(row.dataset.offerVersion) };
    if (to === 'countered') {
      const amount = prompt('Enter the counter-offer amount.'); if (amount === null) return;
      payload.amount = Number(amount); payload.terms = prompt('Optional counter-offer terms.') || '';
    } else if (!confirm(`Change offer status to ${to}?`)) return;
    buttonBusy(button, true);
    try {
      const result = await api(`offers/${row.dataset.offerId}/transition`, { method: 'POST', headers: { 'Idempotency-Key': idempotency(`offer-${to}`) }, body: JSON.stringify(payload) });
      const updated = result.offer || result;
      row.dataset.offerVersion = String(updated.version); qs('.mkt-status', row).textContent = updated.status.replaceAll('_',' '); toast('Offer updated.'); location.reload();
    } catch (error) { toast(error.message, 'error'); }
    finally { buttonBusy(button, false); }
  })));

  qsa('[data-mkt-report-row]').forEach(row => qs('[data-mkt-report-appeal]', row)?.addEventListener('click', async event => {
    const statement = prompt('Explain why this decision should be reviewed.'); if (!statement) return;
    const button = event.currentTarget; buttonBusy(button, true);
    try { await api(`reports/${row.dataset.reportId}/transition`, { method: 'POST', body: JSON.stringify({ to: 'appealed', version: Number(row.dataset.reportVersion), appeal_statement: statement }) }); toast('Report appeal submitted.'); location.reload(); }
    catch (error) { toast(error.message, 'error'); }
    finally { buttonBusy(button, false); }
  }));

  qsa('[data-mkt-dispute-row]').forEach(row => qsa('[data-mkt-dispute-action]', row).forEach(button => button.addEventListener('click', async () => {
    const to = button.dataset.mktDisputeAction, payload = { to, version: Number(row.dataset.disputeVersion) };
    if (to === 'appealed') { const statement = prompt('Explain why the dispute decision should be reviewed.'); if (!statement) return; payload.appeal_statement = statement; }
    if (to === 'evidence') { const reference = prompt('Enter an approved evidence reference.'); if (!reference) return; payload.evidence_refs = [reference]; }
    if (to === 'withdrawn' && !confirm('Withdraw this dispute?')) return;
    buttonBusy(button, true);
    try { await api(`disputes/${row.dataset.disputeId}/transition`, { method: 'POST', body: JSON.stringify(payload) }); toast('Dispute updated.'); location.reload(); }
    catch (error) { toast(error.message, 'error'); }
    finally { buttonBusy(button, false); }
  })));

  const deal = qs('[data-deal-id]');
  if (deal) {
    qsa('[data-mkt-deal-action]', deal).forEach(button => button.addEventListener('click', async () => {
      const to = button.dataset.mktDealAction; if (!confirm(`Change deal status to ${to}?`)) return;
      buttonBusy(button, true);
      try { const updated = await api(`deals/${deal.dataset.dealId}/transition`, { method: 'POST', body: JSON.stringify({ to, version: Number(deal.dataset.dealVersion) }) }); deal.dataset.dealVersion = updated.version; qs('.mkt-status', deal).textContent = updated.status.replaceAll('_',' '); toast('Deal updated.'); }
      catch (error) { toast(error.message, 'error'); }
      finally { buttonBusy(button, false); }
    }));
    qs('[data-mkt-dispute]', deal)?.addEventListener('click', () => openModal(qs('[data-mkt-dispute-modal]')));
    qs('[data-mkt-dispute-form]')?.addEventListener('submit', async event => {
      event.preventDefault(); const form = event.currentTarget, button = qs('button[type=submit]', form); buttonBusy(button, true);
      try { await api(`deals/${deal.dataset.dealId}/disputes`, { method: 'POST', body: JSON.stringify(objectFromForm(form)) }); closeModal(form.closest('.mkt-modal')); toast('Dispute opened.'); location.reload(); }
      catch (error) { toast(error.message, 'error'); }
      finally { buttonBusy(button, false); }
    });
  }

  qs('[data-mkt-load-more]')?.addEventListener('click', async event => {
    const button = event.currentTarget, grid = qs('#mkt-listings'); buttonBusy(button, true);
    try {
      const params = new URLSearchParams(location.search); params.set('cursor', button.dataset.cursor); params.set('limit', '24');
      const result = await api(`listings?${params.toString()}`);
      result.items.forEach(item => {
        const article = document.createElement('article'); article.className = 'mkt-card';
        article.innerHTML = `<a class="mkt-card-media" href="${escapeAttr(item.url)}" tabindex="-1" aria-hidden="true"><span class="mkt-media-placeholder">▧</span></a><div class="mkt-card-body"><div class="mkt-card-meta"><span>${escapeHtml(item.category.replaceAll('_',' '))}</span></div><h3><a href="${escapeAttr(item.url)}">${escapeHtml(item.title)}</a></h3><p class="mkt-price"><bdi>${escapeHtml(item.currency)} ${escapeHtml(item.price)}</bdi></p><p class="mkt-seller">♙ ${escapeHtml(item.seller.store_name)}</p><a class="mkt-button mkt-button-secondary" href="${escapeAttr(item.url)}">View listing →</a></div>`;
        grid.append(article);
      });
      if (result.has_more) { button.dataset.cursor = result.next_cursor; buttonBusy(button, false); } else button.remove();
    } catch (error) { toast(error.message, 'error'); buttonBusy(button, false); }
  });

  function escapeHtml(value) { const div = document.createElement('div'); div.textContent = String(value ?? ''); return div.innerHTML; }
  function escapeAttr(value) { return escapeHtml(value).replaceAll('`','&#96;'); }
})();
