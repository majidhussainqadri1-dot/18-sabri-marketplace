<?php
/**
 * Isolated WordPress 7.0.1 / MySQL 8 smoke integration; it runs ONLY in CI.
 * This is neither Hostinger staging nor deployed website verification.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) {
    throw new RuntimeException('This test must run under isolated WP-CLI WordPress.');
}
$checks = 0;
function assert_wp_marketplace($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    WP_CLI::log('PASS WP/MYSQL: '.$message);
}
global $wpdb;
assert_wp_marketplace(defined('MKT_VERSION') && MKT_VERSION === '2.1.0', 'Marketplace plugin 2.1.0 active');
assert_wp_marketplace(get_bloginfo('version') === '7.0.1', 'Exact WordPress 7.0.1 runtime');
assert_wp_marketplace(version_compare((string)$wpdb->db_version(), '8.0', '>='), 'Real MySQL 8 engine');
assert_wp_marketplace(MKT_DB::schema_ready(), 'All canonical tables, columns and UNIQUE indexes exist');
assert_wp_marketplace(MKT_Governance::schema_ready(), 'Regulated evidence, legal holds and recall tables ready');
assert_wp_marketplace(MKT_Plan_Completion::schema_ready(), 'Language facet table columns ready');
assert_wp_marketplace((string)get_option('mkt_schema_version', '') === MKT_SCHEMA_VERSION, 'Schema marker matches actual installed structure');
assert_wp_marketplace((string)get_option('mkt_governance_schema_version', '') === MKT_Governance::SCHEMA_VERSION, 'Governance migration marker promoted');
assert_wp_marketplace((string)get_option('mkt_plan_completion_schema_version', '') === MKT_Plan_Completion::SCHEMA_VERSION, 'Facet migration marker promoted');

MKT_DB::maybe_upgrade();
MKT_DB::maybe_upgrade();
assert_wp_marketplace(MKT_DB::schema_ready(), 'Repeated migration check leaves MySQL schema intact');
assert_wp_marketplace((string)get_option('mkt_schema_version') === MKT_SCHEMA_VERSION, 'Repeated migration marker remains current');

$manifest = MKT_Deployment_Reality::snapshot();
assert_wp_marketplace(($manifest['source_manifest']['status'] ?? '') === 'verified', 'Packaged source MANIFEST hashes verified on real WordPress filesystem');
assert_wp_marketplace(($manifest['deployment_parity'] ?? '') === 'unverified', 'Installed manifest never falsely claims GitHub/live parity');
assert_wp_marketplace(array_key_exists('archive_sha256', $manifest) && $manifest['archive_sha256'] === null, 'Installed manifest SHA never impersonates a ZIP checksum');

$search = MKT_Listings::search(['limit' => 20]);
assert_wp_marketplace(is_array($search) && !array_key_exists('error', $search) && $search['items'] === [], 'Anonymous-ready empty public search uses real SQL');
assert_wp_marketplace(MKT_Contracts::zero_commission() === 0, 'Zero commission runtime invariant');
$eligibility = MKT_Auth::seller_eligibility((int)get_current_user_id());
assert_wp_marketplace(empty($eligibility['eligible']) && in_array('identity_provider_unavailable', $eligibility['reasons'], true), 'No File00 identity means seller actions are fail-closed');
$auth = MKT_Auth::can('mkt_sell', ['action' => 'create_listing']);
assert_wp_marketplace(is_wp_error($auth), 'WordPress administrator cannot bypass missing canonical identity owner');
assert_wp_marketplace((bool)wp_next_scheduled('mkt_hourly_maintenance'), 'Marketplace hourly maintenance scheduled');
assert_wp_marketplace((bool)wp_next_scheduled('mkt_daily_maintenance'), 'Marketplace daily maintenance scheduled');
assert_wp_marketplace((string)$wpdb->last_error === '', 'No unhandled SQL error after smoke');

WP_CLI::success("File18 isolated real WordPress 7.0.1 / MySQL 8 smoke: $checks assertions passed. This does not prove staging or live.");
