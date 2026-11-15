# RC50 — late REST safety projection fail-closed
Date: 2026-11-15; parent e6583ce75a7ec50b52e9ed2a8de7784d08e93a4f. Master Plan V12.3 F18-FR-005/008/016/020, NFR-001/003/006.

Proven source problem: `MKT_Governance::augment_rest_response()` adds medical evidence and active recall facts AFTER the core/enhanced REST endpoint has already constructed a successful listing response. Under a failed SQL read, `public_evidence()` or `public_recall()` can return null and hide a critical public safety warning while the original 200 response is still sent. An exception would be uncaught in the REST post-dispatch filter.

Fix: for listing detail and archive post-dispatch, check each owner SQL read after resetting `wpdb->last_error`; on error or exception replace the whole response with a minimal privacy-safe HTTP 503. Do not overwrite an upstream non-2xx error. Under verified healthy SQL, preserve successful listings. No migration or unapproved activation.

The new actual-class RC50 fixture injects failure in evidence table lookup/query and recall query/exception on both routes, validates no partial listing survives and checks healthy recovery. CI now runs 75 named stages on the PHP matrix and real MySQL smoke. 37 staging gates, File26 owner cutover, audit retention review and deployed source parity remain unverified.