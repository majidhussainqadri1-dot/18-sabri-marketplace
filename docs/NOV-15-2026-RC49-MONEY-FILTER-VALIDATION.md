# RC49 — canonical monetary search filters fail-closed
Date: 2026-11-15, exact parent e5d4ab09570d61e1f9d1eec60498d7a648f73346. V12.3 F18-FR-007/008; NFR-003/004.

Source defect: both `MKT_Listings::search` and `MKT_Finalization::search_array` silently converted malformed or nonfinite min/max price values to `0` using `(float)` and `max(0,...)`. This made user-specified price boundaries disappear or distort public discovery rather than report invalid input.

Both search owners now validate non-negative finite numeric minimum and maximum prices within MySQL decimal(18,2) field magnitude, with min<=max. Invalid or contradictory bounds return `invalid_price_range` without hitting the SQL query; the already-versioned advanced REST route sends HTTP 400. Valid numeric ranges preserve prior behavior. New real-class RC49 negative/positive search tests run in 74 QA stages and both supported PHP runtimes.

Remaining: staging with real users and currencies, JS/RTL screen-reader and browser tests, File26 activation, audit retention governance (#18), deployed parity (#17).