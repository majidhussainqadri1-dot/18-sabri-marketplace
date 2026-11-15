# RC42: Verified real WordPress/MySQL core-table ownership defect (15 November 2026)

The new disposable WordPress 7.0.1/MySQL 8 workflow installed and activated the exact package and validated **14** early runtime/schema facts, then failed at the first empty public search with `InvalidArgumentException: Unknown marketplace table`. Call stack: MKT_Listings::search → MKT_DB::table('recalls') → MKT_DB::$TABLES whitelist rejection. The recalls table is owned by `MKT_Governance::table('recalls')` and exists under the same actual wp prefix; it must not be added to the core MKT_DB registry or duplicated.

Corrected **four exact call sites**: MKT_Listings::get, get_by_id, search, and MKT_Finalization::search_array. Added RC42 source ownership regression and expanded actual WordPress/MySQL integration smoke to test all four paths without synthetic database stubs. The defect is **proven in isolated CI WordPress**, not proven present in installed live code. Do not claim production resolution without deployed parity and live re-test.

File26 and source-visibility safety depend on these canonical public listing reads; this correction preserves the governance owner, active seller/expiry/recall filters and versioned tombstones.
