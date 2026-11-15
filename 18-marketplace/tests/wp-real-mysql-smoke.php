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
$faceted = MKT_Finalization::search_array(['limit'=>20,'availability'=>'available']);
assert_wp_marketplace(is_array($faceted) && !array_key_exists('error',$faceted) && $faceted['items'] === [], 'Faceted archive uses governance-owned recall table on real MySQL');
$nonexistent = '00000000-0000-4000-8000-000000000042';
assert_wp_marketplace(MKT_Listings::get($nonexistent,false) === null, 'Public listing detail reads the governance recall table safely');
assert_wp_marketplace(MKT_Listings::get_by_id(987654321,false) === null, 'Public numeric listing lookup reads governance recalls safely');
assert_wp_marketplace(MKT_Contracts::zero_commission() === 0, 'Zero commission runtime invariant');
$eligibility = MKT_Auth::seller_eligibility((int)get_current_user_id());
assert_wp_marketplace(empty($eligibility['eligible']) && in_array('identity_provider_unavailable', $eligibility['reasons'], true), 'No File00 identity means seller actions are fail-closed');
$auth = MKT_Auth::can('mkt_sell', ['action' => 'create_listing']);
assert_wp_marketplace(is_wp_error($auth), 'WordPress administrator cannot bypass missing canonical identity owner');
assert_wp_marketplace((bool)wp_next_scheduled('mkt_hourly_maintenance'), 'Marketplace hourly maintenance scheduled');
assert_wp_marketplace((bool)wp_next_scheduled('mkt_daily_maintenance'), 'Marketplace daily maintenance scheduled');
assert_wp_marketplace((string)$wpdb->last_error === '', 'No unhandled SQL error after smoke');


// In a disposable CI database, bypass seller creation API deliberately to
// test the real public SQL projections. The identity-owner contract remains
// fail-closed for all protected mutations.
$fixture_user_id = (int) get_current_user_id();
$fixture_time = MKT_DB::now();
$seller_id_public = MKT_DB::uuid();
$listing_id_public = MKT_DB::uuid();
$media_id_public = MKT_DB::uuid();
$media_payload = [
    'url' => 'https://cdn.example.org/sabri-book.jpg',
    'thumbnail_url' => 'https://cdn.example.org/sabri-book-thumb.jpg',
    'width' => 1024, 'height' => 768,
    'gps_latitude' => '31.5000', 'gps_longitude' => '74.3000',
    'patient_identifier' => 'private-patient-record',
    'provider_api_token' => 'private-provider-credential',
];
$seller_insert = $wpdb->insert(MKT_DB::table('sellers'), [
    'public_id' => $seller_id_public, 'user_id' => $fixture_user_id,
    'store_name' => 'CI Verified Bookshop', 'status' => 'approved',
    'created_at' => $fixture_time, 'updated_at' => $fixture_time,
    'approved_at' => $fixture_time,
]);
assert_wp_marketplace($seller_insert === 1, 'Real MySQL approved seller fixture inserted');
$fixture_seller_id = (int) $wpdb->insert_id;
$listing_insert = $wpdb->insert(MKT_DB::table('listings'), [
    'public_id' => $listing_id_public,'seller_id' => $fixture_seller_id,
    'title' => 'CI Reference Book', 'slug' => 'ci-reference-book-'.substr($listing_id_public,0,8),
    'category' => 'books', 'description' => 'Safe reference listing for isolated integration smoke',
    'price' => '1299.50', 'currency' => 'PKR', 'quantity' => '1.000',
    'status' => 'active', 'availability' => 'available',
    'created_by' => $fixture_user_id, 'updated_by' => $fixture_user_id,
    'published_at' => $fixture_time, 'expires_at' => gmdate('Y-m-d H:i:s',time()+86400),
    'created_at' => $fixture_time, 'updated_at' => $fixture_time,
]);
assert_wp_marketplace($listing_insert === 1, 'Real MySQL approved public listing fixture inserted');
$fixture_listing_id = (int) $wpdb->insert_id;
$media_insert = $wpdb->insert(MKT_DB::table('media_refs'), [
    'public_id' => $media_id_public, 'listing_id' => $fixture_listing_id,
    'owner_user_id' => $fixture_user_id, 'provider' => 'central-media',
    'provider_public_id' => $media_id_public, 'media_kind' => 'image',
    'rights_status' => 'approved', 'scan_status' => 'clean',
    'status' => 'active', 'metadata_json' => wp_json_encode($media_payload),
    'alt_text' => 'CI reference book cover',
    'created_at' => $fixture_time, 'updated_at' => $fixture_time,
]);
assert_wp_marketplace($media_insert === 1, 'Real MySQL approved File10-style media reference inserted');
$owned = MKT_Listings::get($listing_id_public, false);
assert_wp_marketplace(is_array($owned) && (string)$owned['status'] === 'active', 'Live SQL public lookup verifies approved seller and unexpired listing');
$dto = MKT_Listings::public_dto($owned);
$public_metadata = $dto['media'][0]['metadata'] ?? [];
assert_wp_marketplace(($public_metadata['url'] ?? '') === $media_payload['url'], 'Real MySQL safe media URL projects to public listing');
assert_wp_marketplace(($public_metadata['width'] ?? null) === 1024, 'Real MySQL media dimensions remain available');
assert_wp_marketplace(!isset($public_metadata['gps_latitude']) && !isset($public_metadata['patient_identifier']) && !isset($public_metadata['provider_api_token']), 'Real MySQL public media projection redacts EXIF, patient and provider secrets');
$public_search = MKT_Listings::search(['limit'=>20]);
assert_wp_marketplace(count($public_search['items'] ?? []) === 1, 'Core public SQL search returns precisely the eligible fixture');
$advanced_search = MKT_Finalization::search_array(['limit'=>20,'availability'=>'available','category'=>'books']);
assert_wp_marketplace(count($advanced_search['items'] ?? []) === 1 && !isset($advanced_search['error']), 'Faceted real SQL search returns one valid public listing');
$recall_id_public = MKT_DB::uuid();
$recall_insert = $wpdb->insert(MKT_Governance::table('recalls'), [
    'public_id' => $recall_id_public, 'listing_public_id' => $listing_id_public,
    'reason' => 'ci_safety_recall', 'status' => 'active',
    'created_by' => $fixture_user_id, 'approved_by' => $fixture_user_id,
    'created_at' => $fixture_time, 'updated_at' => $fixture_time, 'activated_at' => $fixture_time,
]);
assert_wp_marketplace($recall_insert === 1, 'Governance-owned recall record inserted in real MySQL');
assert_wp_marketplace(MKT_Listings::get($listing_id_public,false)===null, 'Active real MySQL recall hides product detail');
assert_wp_marketplace(MKT_Listings::search(['limit'=>20])['items']===[], 'Active real MySQL recall removes product from core search');
assert_wp_marketplace(MKT_Finalization::search_array(['limit'=>20])['items']===[], 'Active real MySQL recall removes product from faceted search');
assert_wp_marketplace($wpdb->update(MKT_Governance::table('recalls'),['status'=>'resolved','updated_at'=>MKT_DB::now()],['public_id'=>$recall_id_public])===1,'Real MySQL recall resolution transition saved');
assert_wp_marketplace(MKT_Listings::get($listing_id_public,false)!==null, 'Resolved recall permits re-evaluation of eligible public listing');
assert_wp_marketplace($wpdb->update(MKT_DB::table('sellers'),['status'=>'suspended','updated_at'=>MKT_DB::now()],['id'=>$fixture_seller_id])===1,'Real MySQL seller suspension saved');
assert_wp_marketplace(MKT_Listings::get($listing_id_public,false)===null, 'Suspended real MySQL seller cannot expose public product');
assert_wp_marketplace(MKT_Finalization::search_array(['limit'=>20])['items']===[], 'Suspended seller product excluded from advanced discovery');
assert_wp_marketplace($wpdb->update(MKT_DB::table('sellers'),['status'=>'approved','updated_at'=>MKT_DB::now()],['id'=>$fixture_seller_id])===1,'Disposable seller restored for expiry case');
assert_wp_marketplace($wpdb->update(MKT_DB::table('listings'),['expires_at'=>gmdate('Y-m-d H:i:s',time()-3600),'updated_at'=>MKT_DB::now()],['id'=>$fixture_listing_id])===1,'Real MySQL listing expiry fixture saved');
assert_wp_marketplace(MKT_Listings::get($listing_id_public,false)===null, 'Expired listing cannot pass canonical public lookup');
assert_wp_marketplace(MKT_Listings::search(['limit'=>20])['items']===[], 'Expired listing cannot appear in public search');
assert_wp_marketplace($wpdb->last_error==='', 'Real MySQL integration projection finished with no unhandled SQL error');

WP_CLI::success("File18 isolated real WordPress 7.0.1 / MySQL 8 smoke: $checks assertions passed. This does not prove staging or live.");
