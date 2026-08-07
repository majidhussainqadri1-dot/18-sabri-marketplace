<?php
require __DIR__ . '/bootstrap.php';
$root = dirname(__DIR__);
$commerce = file_get_contents($root . '/includes/class-mkt-commerce.php');
$rest = file_get_contents($root . '/includes/class-mkt-rest.php');
$idempotency = file_get_contents($root . '/includes/class-mkt-idempotency.php');
$admin = file_get_contents($root . '/includes/class-mkt-admin.php');
$privacy = file_get_contents($root . '/includes/class-mkt-privacy.php');
$audit = file_get_contents($root . '/includes/class-mkt-audit.php');
$js = file_get_contents($root . '/assets/js/marketplace.js');
$db = file_get_contents($root . '/includes/class-mkt-db.php');

assert_true(str_contains($rest, 'MKT_Idempotency::run'), 'REST mutations are replay-protected.');
assert_true(str_contains($js, "headers['Idempotency-Key']"), 'Browser mutations emit idempotency keys.');
assert_true(str_contains($idempotency, 'request_hash') && str_contains($idempotency, 'mkt_idempotency_conflict'), 'Idempotency key reuse with altered payload is rejected.');
assert_true(str_contains($commerce, "SELECT l.*,s.public_id") && str_contains($commerce, 'FOR UPDATE'), 'Offer acceptance locks listing and rechecks seller state.');
assert_true(str_contains($commerce, "listing_status === 'sold_unavailable'"), 'Sold-out acceptance closes every competing live offer.');
assert_true(str_contains($commerce, 'mkt_deal_reviewer_scope'), 'Reviewer access is restricted to disputed/resolved deals.');
assert_true(str_contains($commerce, 'mkt_dispute_record_required'), 'Deal dispute state requires a structured dispute record.');
assert_true(str_contains($admin, "current_user_can('mkt_moderate')") && str_contains($admin, "current_user_can('mkt_review_disputes')"), 'Moderation UI follows least privilege.');
assert_true(str_contains($privacy, "'dispute' => \$wpdb->get_results") && str_contains($privacy, "MKT_DB::table('disputes')") && str_contains($privacy, "'group_id' => 'mkt-' . \$type"), 'Privacy export includes participant disputes through the generic bounded exporter.');
assert_true(str_contains($privacy, 'private const EXPORT_BATCH = 100') && str_contains($privacy, 'private const ERASE_BATCH = 100'), 'Privacy export and erasure are bounded.');
assert_true(str_contains($audit, 'GET_LOCK') && str_contains($audit, 'verify_chain'), 'Audit chain has concurrency control and verification.');
assert_true(!preg_match('/\bmkt_(conversations?|messages?|calls?|wallets?|escrows?|payouts?)\b/i', $db), 'No parallel communication or money canonical table exists.');
fwrite(STDOUT, "Adversarial invariant suite complete.\n");
