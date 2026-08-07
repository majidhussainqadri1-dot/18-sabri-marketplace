<?php
require __DIR__ . '/bootstrap.php';
$root = dirname(__DIR__);
$php = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if ($file->isFile() && str_ends_with($path, '.php') && !str_contains($path, '/tests/')) {
        $php .= "\n/* {$path} */\n" . file_get_contents($path);
    }
}

assert_true(str_contains($php, "define('MKT_VERSION', '2.1.0')"), 'Runtime version is 2.1.0.');
assert_true(str_contains($php, "define('MKT_SCHEMA_VERSION', '2.1.0')"), 'Schema version is 2.1.0.');
assert_true(str_contains($php, "define('MKT_CONTRACT_VERSION', '1.2.0')"), 'Contract version is 1.2.0.');
assert_true(str_contains($php, "'commission_percent' => 0"), 'Zero-commission output is explicit.');
assert_true(!preg_match('/\bmkt_(conversations?|messages?|calls?|wallets?|escrows?|payouts?)\b/i', $php), 'No parallel communication, wallet, escrow or payout canonical table exists.');
assert_true(!preg_match('/\b(10|[1-9])\s*%\s*commission/i', $php), 'No non-zero commission copy exists.');
assert_true(!preg_match('/\b(eval|exec|shell_exec|passthru|system)\s*\(/i', $php), 'No dangerous execution primitive exists.');
assert_true(!preg_match('/(AKIA[0-9A-Z]{16}|-----BEGIN (RSA|OPENSSH|EC) PRIVATE KEY-----|sk-[A-Za-z0-9]{20,})/', $php), 'No credential pattern is embedded.');
assert_true(str_contains($php, "'file-17-communication'"), 'Legacy communication handoff targets File 17.');
assert_true(str_contains($php, 'sabri_communication_open_context_conversation'), 'File 17 context conversation adapter exists.');
assert_true(str_contains($php, 'sabri_notifications_ingest_event'), 'File 19 notification adapter exists.');
assert_true(str_contains($php, 'sabri_shell_navigation_destinations'), 'File 20 shell integration exists.');
assert_true(str_contains($php, 'sabri_assurance_register_native_control'), 'File 24 assurance integration exists.');
assert_true(str_contains($php, 'sabri_search_providers'), 'File 26 search integration exists.');
assert_true(str_contains($php, 'MarketplaceDealAccepted.v1'), 'Versioned deal event exists.');
assert_true(str_contains($php, 'MarketplaceListingStatusChanged.v1'), 'Versioned listing status event exists.');
assert_true(str_contains($php, 'MarketplaceRecallActivated.v1'), 'Versioned recall event exists.');
assert_true(str_contains($php, 'MarketplaceListingEvidenceReviewed.v1'), 'Versioned evidence-review event exists.');
assert_true(str_contains($php, 'FOR UPDATE'), 'Critical state transitions lock canonical rows.');
assert_true(str_contains($php, 'expected_version'), 'Optimistic version checks exist.');
assert_true(str_contains($php, 'wp_privacy_personal_data_exporters'), 'Privacy exporter is registered.');
assert_true(str_contains($php, 'wp_privacy_personal_data_erasers'), 'Privacy eraser is registered.');
assert_true(str_contains($php, 'noindex, noarchive, nofollow') || str_contains($php, 'noindex, nofollow, noarchive'), 'Private/recall robot policy exists.');
assert_true(str_contains($php, 'private, no-store'), 'Private/recall cache policy exists.');
assert_true(str_contains($php, 'Non-destructive uninstall'), 'Uninstall is non-destructive by default.');
assert_true(str_contains($php, 'SELECT GET_LOCK') && str_contains($php, 'SELECT RELEASE_LOCK'), 'Upgrade lock is atomic and connection-owned.');
assert_true(!str_contains($php, 'true|WP_Error'), 'PHP 8.1 compatibility: no standalone true union return type.');

$db = file_get_contents($root . '/includes/class-mkt-db.php');
foreach (['sellers','policies','listings','media_refs','saves','offers','deals','reports','disputes','outbox','inbox','idempotency','audit','metrics_daily','migration_handoffs'] as $table) {
    assert_true(str_contains($db, "'{$table}'"), "Required base table domain {$table} is declared.");
}
assert_true(str_contains($db, 'LIMIT 200') && str_contains($db, 'LIMIT 500'), 'Legacy migration uses bounded keyset batches.');

$governance = file_get_contents($root . '/includes/class-mkt-governance.php');
foreach (['mkt_listing_evidence','mkt_retention_holds','mkt_recalls'] as $table) assert_true(str_contains($governance, $table), "Required governance domain {$table} is declared.");
$completion = file_get_contents($root . '/includes/class-mkt-plan-completion.php');
assert_true(str_contains($completion, 'mkt_listing_facets'), 'Marketplace language facet domain is declared.');

$rest = file_get_contents($root . '/includes/class-mkt-rest.php');
foreach (['/listings','/offers/','/deals/','/reports','/disputes/','/dashboard','/system-check','/repair'] as $route) assert_true(str_contains($rest, $route), "REST route family {$route} exists.");

assert_true(str_contains($php, 'MKT_Idempotency::run'), 'Protected mutations use the canonical idempotency ledger.');
assert_true(str_contains($rest, '/media/(?P<media_id>') && str_contains($rest, 'WP_REST_Server::DELETABLE'), 'Listing media deletion route exists.');
assert_true(str_contains($php, 'transition_report') && str_contains($php, "'appealed'"), 'Report appeal workflow exists.');
assert_true(str_contains($php, 'transition_dispute') && str_contains($php, "'withdrawn'"), 'Dispute appeal and withdrawal workflow exists.');
assert_true(str_contains($php, 'policy_version_created'), 'Policy changes create versioned audit evidence.');
assert_true(str_contains($php, 'verify_chain'), 'Audit-chain verification exists.');
assert_true(str_contains($php, "'status' => 'completed'") && str_contains($php, 'platform_ack'), 'Outbox requires explicit acknowledgement before completion.');
assert_true(str_contains($php, "'file-17-communication'") && !str_contains($db, "'conversations'"), 'Communication migration is a File 17 handoff, not a duplicate backend.');
assert_true(str_contains($php, 'mkt_self_review_forbidden'), 'Regulated evidence self-review is prohibited.');
assert_true(str_contains($php, 'authority_reference'), 'Documented legal/safety retention holds exist.');

$docs = ['ARCHITECTURE.md','API-EVENT-CONTRACTS.md','SECURITY-PRIVACY.md','MIGRATION-ROLLBACK.md','STAGING-ACCEPTANCE.md','OPERATIONS-RUNBOOK.md','REQUIREMENTS-TRACEABILITY.md','FOUR-ROUND-GOVERNING-PLAN-AUDIT.md'];
foreach ($docs as $doc) assert_true(is_file($root . '/docs/' . $doc), "Required documentation {$doc} exists.");
fwrite(STDOUT, "Source architecture suite complete.\n");
