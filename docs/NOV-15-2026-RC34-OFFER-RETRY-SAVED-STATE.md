# File 18 — Stable client offer retry keys and saved-state UI
Date: 15 November 2026. Source: Draft PR #33 (not main or live). Plan: File18 F18-FR-011/012, F18-NFR-003/005, V12.3 repeated-order-intent safety.

## Two confirmed client source defects fixed
1. On every submission the offer form generated a new Idempotency-Key, even when retrying identical data following a network/API failure whose server-side commit could have succeeded. Persist a form-scoped in-memory WeakMap key/fingerprint across errors for identical offer amount, terms and currency; mint a new key when payload changes; clear only after confirmed success. No sensitive payload is written into the DOM or localStorage.
2. The Save button changed its text to '♥ Saved' before finally calling buttonBusy(false), which restored the previous 'Save' label. Assign the successful display string to the busy controller's intended final label instead.

## Regression
Run actual browser JS in Node vm fixture with controlled FormData/fetch/mock DOM: repeated failures with same key; payload change new key; successful return resets key and form; saved button retains label and is re-enabled. Standalone CI + full QA stage number 60 of 63, repeated 40× per PHP runtime.

## Pending
Real concurrent MySQL idempotency, File00/17/19/26 contracts, deployed schema parity, staging/real devices, legal audit-chain retention signoff (#18), live connection (#17), founder release signoff. No production-ready claim.
Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
