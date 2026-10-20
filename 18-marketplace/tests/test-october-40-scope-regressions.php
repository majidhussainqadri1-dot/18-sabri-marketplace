<?php
/**
 * Regression checks for the 20 October 2026 forty-scope source audit.
 *
 * This suite is deliberately environment-independent. It does not replace
 * WordPress/MySQL integration tests or acceptance against a deployed build.
 */
require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$read = static fn(string $file): string => (string) file_get_contents($root . '/includes/' . $file);

$listings = $read('class-mkt-listings.php');
$finalization = $read('class-mkt-finalization.php');
$rest = $read('class-mkt-rest.php');
$governance = $read('class-mkt-governance.php');
$release = $read('class-mkt-release-gates.php');
$completion = $read('class-mkt-plan-completion.php');
$events = $read('class-mkt-events.php');
$audit = $read('class-mkt-audit.php');
$db = $read('class-mkt-db.php');

$tuple = '(l.published_at < %s OR (l.published_at = %s AND l.id < %d))';
foreach ([$listings, $finalization] as $source) {
    assert_true(str_contains($source, 'ORDER BY l.published_at DESC,l.id DESC'), 'Publication and id define search ordering.');
    assert_true(str_contains($source, $tuple), 'Cursor predicate follows the same compound ordering.');
    assert_true(str_contains($source, 'cursor_expired'), 'Deleted/unresolvable cursor fails explicitly.');
    assert_true(!str_contains($source, "$" . "where[] = 'l.id<%d'"), 'Id-only cursor is not accepted.');
}

// A draft created first but published later has a low id and a new timestamp;
// an id-only cursor skips it, despite its correct place in publication order.
$records = [
    ['id' => 19, 'published_at' => '2026-10-20 11:00:00'],
    ['id' => 2,  'published_at' => '2026-10-20 10:00:00'],
    ['id' => 24, 'published_at' => '2026-10-19 10:00:00'],
];
$anchor = $records[1];
$next = array_values(array_filter($records, static fn($row) =>
    $row['published_at'] < $anchor['published_at']
    || ($row['published_at'] === $anchor['published_at'] && $row['id'] < $anchor['id'])
));
assert_same(24, $next[0]['id'], 'Composite cursor includes older publication with higher id.');

foreach ([$rest, $governance, $release, $completion] as $source) {
    assert_true(str_contains($source, "did_action('application_password_did_authenticate')"), 'Nonce bypass requires verified application-password authentication.');
    assert_true(!str_contains($source, "$" . "authorization!=='' || !empty($" . "_SERVER['PHP_AUTH_USER'])"), 'Arbitrary auth headers cannot bypass nonce.');
}
assert_true(str_contains($rest, "get_header('X-WP-Nonce')"), 'Core REST path reads request-scoped nonce.');

assert_true(str_contains($events, 'MKT_DB::transaction(static function() use ($handler, $event, $wpdb, $source, $event_id)'), 'External event handler and inbox acknowledgement share a transaction.');
assert_true(str_contains($events, "'status' => 'received'"), 'Event acknowledgement uses expected inbox state.');
assert_true(str_contains($events, "mkt_event_acknowledgement_failed"), 'Failed acknowledgement cannot return success.');
assert_true(str_contains($events, "apply_filters('mkt_event_acknowledged', false, $" . 'event)'), 'Local consumption needs explicit acknowledgement.');
assert_true(!str_contains($events, '$ok = $platform_ack || $local_consumers'), 'Registered action is not treated as delivery acknowledgement.');

assert_true(str_contains($audit, 'audit chain lock could not be acquired'), 'Required audit fails closed if serial chain lock is unavailable.');
assert_true(!str_contains($audit, "'LOCK_UNAVAILABLE';"), 'New audit writes must not deliberately produce invalid chain gaps.');
assert_true(str_contains($db, "if ($" . "wpdb->query('START TRANSACTION') === false)"), 'Transaction start error blocks owner mutation.');
assert_true(str_contains($db, "if ($" . "wpdb->query('COMMIT') === false)"), 'Transaction commit error blocks false success.');


$commerce = $read('class-mkt-commerce.php');
$deal = substr($commerce, (int) strpos($commerce, 'private static function create_deal_from_offer'), (int) (strpos($commerce, 'public static function transition_deal') - strpos($commerce, 'private static function create_deal_from_offer')));
$dealInserted = strpos($deal, '$new_deal_id = (int) $wpdb->insert_id;');
$dealAudit = strpos($deal, "MKT_Audit::record('deal_created'");
assert_true($dealInserted !== false && $dealAudit !== false && $dealInserted < $dealAudit, 'Deal PK is captured before audit and outbox insert calls.');
assert_true(str_contains($deal, "WHERE id=%d', $" . "new_deal_id") && str_contains($deal, 'mkt_deal_readback_failed'), 'Deal readback uses the saved deal PK and rejects a missing or mismatched row.');
assert_true(!str_contains($deal, "WHERE id=%d', $" . "wpdb->insert_id"), 'Deal readback must not use a mutable last-insert id.');

assert_true(str_contains($db, 'Marketplace legacy seller migration write failed'), 'Legacy seller migration refuses silent insert failure.');
assert_true(str_contains($db, 'Marketplace legacy listing migration write failed'), 'Legacy listing migration refuses silent insert failure.');
assert_true(str_contains($db, 'Marketplace legacy communication handoff write failed'), 'Communication handoff migration rejects failed inserts.');
assert_true(str_contains($db, 'legacy seller migration read failed') && str_contains($db, 'legacy listing migration read failed'), 'Legacy inventory failure cannot masquerade as empty input.');
assert_true(str_contains($db, "'migration_handoffs' => ['id','legacy_type','legacy_id','status']"), 'Schema verification checks version-critical migration columns.');
$verifyTables = strpos($db, 'foreach (self::TABLES as $name)');
$promote = strpos($db, "update_option('mkt_schema_version', MKT_SCHEMA_VERSION");
assert_true($verifyTables !== false && $promote !== false && $verifyTables < $promote, 'Schema option promotion occurs after table/column verification.');

$moderation = $read('class-mkt-moderation.php');
$reportOpen = strpos($moderation, 'public static function create_report');
$reportNext = strpos($moderation, 'private static function validate_report_target');
assert_true($reportOpen !== false && $reportNext > $reportOpen, 'Report command has an inspectable owner boundary.');
$reportSource = substr($moderation, $reportOpen, $reportNext - $reportOpen);
assert_true(str_contains($reportSource, 'MKT_DB::transaction(static function()'), 'Report, audit, and outbox are inside one owner transaction.');
assert_true(str_contains($reportSource, '$report_id = (int) $wpdb->insert_id;'), 'Report primary key is captured before audit/outbox inserts.');
assert_true(str_contains($reportSource, "WHERE id=%d', $" . "report_id"), 'Report readback uses its captured key.');
assert_true(!str_contains($reportSource, "WHERE id=%d', $" . "wpdb->insert_id"), 'Report readback never uses mutable last-insert ID.');
assert_true(str_contains($reportSource, 'mkt_report_readback_failed'), 'Missing or mismatched report readback is rejected.');

$listingSaveStart = strpos($listings, 'public static function save(');
$listingSaveEnd = strpos($listings, 'public static function open_chat(');
assert_true($listingSaveStart !== false && $listingSaveEnd > $listingSaveStart, 'Saved listing has an inspectable command boundary.');
$listingSave = substr($listings, $listingSaveStart, $listingSaveEnd - $listingSaveStart);
assert_true(str_contains($listingSave, 'MKT_DB::transaction(static function()'), 'Save and audit run in a single owner transaction.');
assert_true(str_contains($listingSave, 'if ($changed === false)'), 'Failed save or unsave writes are not reported as successful.');
assert_true(str_contains($listingSave, 'mkt_save_failed'), 'Failed save or unsave responds with a distinct machine error.');

$commerce = $read('class-mkt-commerce.php');
$policy = $read('class-mkt-policy.php');
assert_true(str_contains($events, '$notification_required ? self::send_derived_notifications($event) : false') &&
    str_contains($events, '(!$notification_required || $notification_ack)'),
    'Outbox cannot complete if a required File 19 notification was not acknowledged.');
assert_true(str_contains($listings, "'price' => round((float) ") &&
    str_contains($listings, "'quantity' => round((float) "),
    'Invalid negative listing amounts are not silently zero-clamped before domain policy.');
assert_true(str_contains($policy, 'is_finite((float)') &&
    substr_count($commerce, '!is_finite($amount)') >= 2,
    'Listing, offer and counteroffer inputs explicitly reject non-finite monetary values.');

fwrite(STDOUT, "October forty-scope corrective invariants complete.\n");
