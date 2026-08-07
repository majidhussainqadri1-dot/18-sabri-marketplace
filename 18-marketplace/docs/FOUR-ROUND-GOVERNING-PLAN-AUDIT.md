# File 18 — Four-Round Governing-Plan Audit

Corrective runtime: **2.0.1**; schema remains **2.0.0**; contract **1.1.0**.

## Governing sources

1. Definitive Integrated Master Plan v3.0.
2. Consolidated All-Chats Recovered Directives v2.2.
3. Continuous Value / Global Top-20 Superset Master Plan v1.0.
4. File 18 Marketplace Complete Master Plan v1.0.

## Round 1 — constitution, ownership, business law, UX

Defects found and corrected: protected actions accepted approved-but-unverified accounts; high-risk state was not enforced in the general capability gate; default discovery privileged `featured_label`; current single-free-tier/no-donor-advantage/no-paid-ranking law was not explicit in runtime contracts; component colors did not consume File 25 primary tokens; local Back/Home controls could duplicate File 20 ownership.

## Round 2 — authorization, replay, concurrency, event integrity

Defects found and corrected: failed idempotent operations could be reclaimed non-atomically; expired `processing` reservations had no safe takeover; external inbox treated every duplicate as success, so failed events could be lost permanently and reused event IDs with altered payloads were not rejected.

## Round 3 — integration, migration, authorization and degraded behavior

Fresh review after the first two corrections found further defects and corrected them: dispute-reviewer deal access relied on a WordPress capability without revalidating current File 00 verification/risk/suspension state; the schema/runtime upgrader used a non-atomic transient lock and did not promote the runtime option when schema version was unchanged; legacy seller/product/communication migrations loaded unbounded record sets; the dormant promotions setting remained writable despite the no-paid/no-donor-influence constitution; and policy/settings writes needed current high-trust authorization. These paths are now fail-closed or break-glass-scoped as appropriate, migration is keyset-batched, and the runtime/status contracts expose the current business constitution.

## Round 4 — supply chain, source review, packaging, release truth

Defect found and corrected: the PR depended on an encoded base64 source bundle plus reconstruction script, which is not an acceptable substitute for directly reviewable canonical source. The ordinary source tree is now canonical; reconstruction artifacts are removed; QA and release builds operate on the direct source tree. Runtime advanced to 2.0.1/RC2.

## Status boundary

This audit establishes code/package/automated-QA candidate completion only. Hostinger staging, real companion integrations, browser/device/accessibility evidence, backup/restore/rollback rehearsal, Founder acceptance, live deployment and operational monitoring remain separate gates.
