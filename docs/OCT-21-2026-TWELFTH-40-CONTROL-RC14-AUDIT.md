# File18 Twelfth 40-Control Corrective Source Audit — 2026-10-21 / RC14

**Exact reviewed baseline:** prior source RC13 `b746106ce464984982928832a576fbcfc0fbec10` (successful PHP 8.1/8.3 40-pass QA). Working Draft PR **#14** branch `fix/file18-twelfth-forty-control-review-2026-10-21` stacked on prior unmerged PR #13, *not* repository main or deployed code.

## Governing source scope
Reviewed uploaded File18 Marketplace Complete Master Plan, Consolidated Three Central Governing Master Plans, File17 communications, conditional CF05 derivative Analytics and conditional CF06 Translation Operations (including Future40). One owner per entity: File00 canonical risk, age, guardian and account status; File10 media; File17 messages; File19 notification; File20 language/shell; File24 assurance; File25 RTL/design; File26 global search; File18 original listing, offers, deals, reports, disputes. No commission, donor rank, duplicate translations or independent CF05/CF06 owner activation.

## 40 distinct plan and source owner controls
Each numbered line is **one source-to-requirement inspection and traceability domain**, machine-checked for a source anchor by `tests/test-seventh-40-plan-anchors.php`. It is NOT a complete runtime test of that requirement. The exact GitHub CI additionally repeats all 34 steps of static/contract/fault-injection QA 40 times per PHP 8.1/8.3 job; final-pass counts must come from the final exact-head workflow logs.

| # | Control | Governing issue | Code/owner area | Residual acceptance |
|---:|---|---|---|---|
| 1 | `F18-FR-001` | Listing category/prohibited evidence | policy | Actual user moderation UAT pending |
| 2 | `F18-FR-002` | Seller authority/guardian/age | identity + listings | Unknown minor/risk fail-closed patch; real File00 staging pending |
| 3 | `F18-FR-003` | Listing create/draft validation | listings | Real DB/new listing UAT pending |
| 4 | `F18-FR-004` | Media link/rights/safety | listings + File10 | Preflight SQL fail/row-locked count/TOCTOU fixed; File10 real provider pending |
| 5 | `F18-FR-005` | Regulated product evidence | governance | Human reviewer and live policy pending |
| 6 | `F18-FR-006` | Listing CAS transitions | listings | Actual multi-user concurrent approval pending |
| 7 | `F18-FR-007` | Search/cursor/filter | finalization | Real index/volume/DB EXPLAIN pending |
| 8 | `F18-FR-008` | Public DTO/privacy | listings | Guest cache/private object BOLA UAT pending |
| 9 | `F18-FR-009` | Save/share/report | listings | Replay/rate abuse UAT pending |
| 10 | `F18-FR-010` | File17 conversation handoff | integrations | Deployed File17 owner mapping pending |
| 11 | `F18-FR-011` | Offer validation/expiration | commerce | Multi-user real MySQL pending |
| 12 | `F18-FR-012` | Counteroffer version CAS | commerce | Simultaneous offers stale-version real test pending |
| 13 | `F18-FR-013` | Accepted deal snapshot | commerce | Actual owner transactional DB test pending |
| 14 | `F18-FR-014` | 0% commission, no donor advantage | contracts + commerce | Actual active options/donor influence unknown |
| 15 | `F18-FR-015` | Payment status event boundary | events | Provider origin signature and File03 conditional interface pending |
| 16 | `F18-FR-016` | Reports/moderation/appeals | moderation | Reviewer accounts real acceptance pending |
| 17 | `F18-FR-017` | Disputes/evidence/legal hold | moderation + governance | Live legal-policy/retention approval pending |
| 18 | `F18-FR-018` | No paid rank promotion | db + admin | Active wp_options and UI equality pending |
| 19 | `F18-FR-019` | Seller Studio dashboard | governance | Real seller session and analytics projection pending |
| 20 | `F18-FR-020` | Maintenance/expiry reconciliation | maintenance | Actual WP Cron and File19 delivery pending |
| 21 | `F18-NFR-001` | Auth/permission/object/guardian | auth | Unknown risk/age and textual suspended fail-closed; real File00 assertions pending |
| 22 | `F18-NFR-002` | Privacy export/erasure/hold | privacy | Actual WordPress Privacy tool/DB acceptance pending |
| 23 | `F18-NFR-003` | Inbox/outbox/idempotency | events | Parallel workers and real owner receipts pending |
| 24 | `F18-NFR-004` | Bounded indexed queries | finalization | Production EXPLAIN/load pending |
| 25 | `F18-NFR-005` | RTL/keyboard/mobile/noindex | routes | Real browser/screen reader/zoom pending |
| 26 | `F18-NFR-006` | Tamper-resistant audit ledger | audit | Live lock behavior/key recovery pending |
| 27 | `F18-NFR-007` | Real schema/version/migration | db | Installed MySQL/version unknown |
| 28 | `F18-NFR-008` | Backup/restore/repair | maintenance | Isolated restore + rollback rehearsal pending |
| 29 | `F18-NFR-009` | Contract-version partner probes | integrations | File00/10/17/19/20/24/25/26 deployed parity pending |
| 30 | `F18-NFR-010` | Language/currency/RTL formatting | finalization | CF06 remains conditional; native localization real UAT pending |
| 31 | `CEN-OWN-00` | File00 identity, no second account owner | integrations | Canonically verified assertions required for buy/sell |
| 32 | `CEN-OWN-10` | File10 canonical media owner | listings | Remote reference allocated and compensated after DB rollback |
| 33 | `CEN-OWN-17` | File17 communications owner | integrations | No second conversations DB |
| 34 | `CEN-OWN-19` | File19 notification owner | integrations | ACK required when handoff should occur |
| 35 | `CEN-OWN-20` | File20 shell/language choice owner | integrations | File18 supplies route, no global language takeover |
| 36 | `CEN-OWN-24` | File24 assurance owner | integrations | No fail-open when assurance receiver unavailable |
| 37 | `CEN-OWN-25` | File25 design/RTL tokens owner | routes | No duplicate layout library |
| 38 | `CEN-OWN-26` | File26 global search index owner | integrations | File18 remains domain source search consumer |
| 39 | `CEN-LEGAL-HOLD` | Court/safety holds, privacy exceptions | governance | Failed lookup never permits erasure; real legal acceptance pending |
| 40 | `CEN-RECALL` | Product recall/takedown notice | governance | Real public warning + notification acceptance pending |

## New root-cause findings / source corrections (4 defect groups)
1. **R12-01 / File00 suspended textual boolean and legacy minor/guardian truthiness.** File18 integrated identity adapter previously used PHP `!empty()` or `(bool)` for provider-supplied fields; textual `'false'` becomes true, causing incorrect account suspension or guardian classification. Explicit strict boolean parser now used for suspended, legacy is_minor and guardian flags. The originally attached File00 contract still needs actual provider integration to verify schema and expected age semantics.
2. **R12-02 / Unverified risk/age state allowed protected buy/sell.** `MKT_Auth::can` and `seller_eligibility` previously treated `is_minor = null` as adult/no guardian requirement and `risk_state = unknown` as acceptable. Now unknown or malformed minor classification and risk fail closed; safe denial states have direct actual-class test assertions in `test-file00-explicit-identity-denial.php`. User eligibility must be confirmed through authoritative File00 rather than locally invented adult status.
3. **R12-03 / Media reference SQL and race bypass.** `MKT_Listings::attach_media` previously cast a failed `COUNT(*)` to zero and checked max media refs **outside** a transaction. Two concurrent File10 media allocations could both precheck below max then over-attach; listing could change to published meanwhile. Now preflight rejects unknown counts; row-level `SELECT ... FOR UPDATE` inside the owner DB transaction rechecks listing state and media count before insert. SQL error/limit/status conflict returns WP_Error, rolls back and compensates File10 *after* rollback. Expanded `test-media-atomicity.php` covers failed preflight, failed in-lock count, a competing inserter reaching max and post-preflight active listing state. **Real InnoDB multi-worker acceptance not run.**

4. **R12-04 / Verified File00 main contract interoperability missing and provider-scoped denial not enforced.** A cross-repository exact-source comparison (File00 `main` exact inspected HEAD `2fa7c022ee9cd1b65432e900579512f304532442`, `source/sabri-membership-core/includes/class-smc-contracts.php`) found File00 exports `SMC_Contracts::assertions()` including `clinical_commerce.marketplace_seller_actions_allowed` / `marketplace_direct_deal_allowed`, `age_context.known/minor`, `eligible` and identity-document verification. File18 previously depended on optional unrelated adapter names and did not enforce File00's explicit no-minor direct-deal/seller restrictions. Now it consumes the File00 canonical class when available and no higher-priority dedicated provider assertions exist, checks both positive scoped permission fields and age/current identity status, and never promotes denial via generic WP capabilities. Only File00's explicit positive seller/deal scoped decisions are projected into corresponding `mkt_sell`/`mkt_buy` capability assertions, so owner-approved adults do not need invented WordPress role grants. A missing scoped field, unknown age or File00 exception fails closed; missing general risk signal can be accepted **only** through explicit positive File00 scoped permission AND canonical eligibility. Direct source-class contract regression `test-file00-native-marketplace-contract.php` reproduces approved adult, guardian-consented minor denial, File00 noneligible, missing scope, missing age proof, high-risk override, and owner exception. The main branch and test are **source facts only**, never proof of the actual deployed File00 build; staging/provider validation is still mandatory.

No claim every scenario or all **58 mapped CV** requirements is operational. The 40 source controls and 40 repeated automated QA are not 40 separate full manual source reviews, not real browser/API/provider acceptance, and not real WordPress/MySQL deployment testing.

## Required freeze and blockers
| Ledger | Status |
|---|---|
| `main` HEAD at audit start | `04a5872e3e2e170ee400e7c607e39abd5484df65` |
| Starting corrected RC13 HEAD | `b746106ce464984982928832a576fbcfc0fbec10` |
| Final RC14 Draft HEAD and release SHA | Verify last completed CI only |
| Exact deployed WP plugin/version/checksum | **UNVERIFIED** |
| Live DB/schema/columns/rows/wp_options | **UNVERIFIED** |
| Migration state/logs | **UNVERIFIED** |
| Live verification | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

- WPVibe connector previously listed zero registered WordPress sites. Connecting to `https://sabrihomeopathy.com` returned **Cloudflare HTTP 530** with no 1xxx/Ray ID root cause, cannot bypass security or resolve without live host evidence. Site accessibility is not established by GitHub successes.
- Still required: read-only deployed reality freeze, deployment-parity audit; genuine staging File00/10/17/19/20/24/25/26 provider compatibility and CF05/CF06 conditional boundaries; authoritative File00 risk/age classifications; actual DB concurrency/rollback, staging snapshot/restore, privacy and moderation journeys, AJ-23/AJ-40 and individual 58 related CV IDs; two independent fresh post-change reviews; Founder approval; safe package→staging→live deployment and live re-test.
- Stacked Draft PR #14 is **not merged or deployed**. A green source CI can show source correction but cannot establish live incident resolution.
