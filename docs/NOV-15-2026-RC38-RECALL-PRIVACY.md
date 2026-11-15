# File18 RC38 — Recall notice does not publish private Listing DTO
Date: 2026-11-15; Plan V12.3 F18-FR-016/020, F18-NFR-001/002, CV-210.

Confirmed source weakness: the previous recall fallback used a private owner Listing row and passed it to public_dto, exposing full seller, price, media, description and location even though normal eligibility checks had failed. Replaced with an independent, generic safety-only recall notice for previously published objects; no private DTO, seller, price, media, contact, offer or chat controls. Never-published recalled drafts remain HTTP 404. Response is noindex/no-store with only approved public recall reason/batch/regulatory references.

New RC38 test executes the actual PHP template against fake WordPress, injects canary private fields, and checks 404/no-cache and no private DTO invocation. Source regression, not a live or browser acceptance claim.

Still pending live/Hostinger evidence and full independent safety review; PR #33 remains Draft.
