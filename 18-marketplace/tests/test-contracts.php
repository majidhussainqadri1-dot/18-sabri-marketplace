<?php
require __DIR__ . '/bootstrap.php';
define('MKT_VERSION', '2.0.0');
define('MKT_SCHEMA_VERSION', '2.0.0');
define('MKT_CONTRACT_VERSION', '1.0.0');
require dirname(__DIR__) . '/includes/class-mkt-contracts.php';

assert_same(0, MKT_Contracts::zero_commission(), 'Platform commission is exactly zero.');
assert_true(in_array('active', MKT_Contracts::listing_statuses(), true), 'Active listing status exists.');
assert_true(in_array('appealed', MKT_Contracts::listing_statuses(), true), 'Listing appeal status exists.');
assert_true(in_array('countered', MKT_Contracts::offer_statuses(), true), 'Counter-offer status exists.');
assert_true(in_array('disputed', MKT_Contracts::deal_statuses(), true), 'Deal dispute status exists.');
assert_true(in_array('appealed', MKT_Contracts::report_statuses(), true), 'Report appeal status exists.');
$manifest = MKT_Contracts::contract_manifest();
assert_same('File 17', $manifest['canonical_owners']['communication'], 'File 17 remains communication owner.');
assert_same('File 19', $manifest['canonical_owners']['notifications'], 'File 19 remains notification owner.');
assert_same('File 20', $manifest['canonical_owners']['shell'], 'File 20 remains shell owner.');
assert_same(0, $manifest['commission_percent'], 'Contract manifest declares zero commission.');
assert_true(in_array('PKR', MKT_Contracts::allowed_currencies(), true), 'PKR currency is supported.');
assert_true(in_array('USD', MKT_Contracts::allowed_currencies(), true), 'USD currency is supported.');
fwrite(STDOUT, "Contract suite complete.\n");
