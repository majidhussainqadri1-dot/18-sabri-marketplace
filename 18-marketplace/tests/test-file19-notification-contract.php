<?php
/**
 * Runtime-shaped File 18 → File 19 notification contract regression.
 * Stubs File 19's documented public entry points; real staging still required.
 */
require __DIR__ . '/bootstrap.php';
if (!defined('MKT_CONTRACT_VERSION')) define('MKT_CONTRACT_VERSION', '1.2.0');
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function esc_url_raw($value): string { return filter_var((string) $value, FILTER_VALIDATE_URL) ?: ''; }
final class MKT_Audit { public static function trace_id(): string { return 'f18-test-trace'; } }
$GLOBALS['mkt_test_registered'] = [];
$GLOBALS['mkt_test_events'] = [];
$GLOBALS['mkt_test_next_status'] = 'processed';
function sun_register_notification_producer($name, array $config): bool {
    $GLOBALS['mkt_test_registered'][$name] = $config;
    return !empty($config['owner']);
}
function sun_ingest_domain_event(array $event) {
    $required = ['producer','owner','event_id','event_type','schema_version','occurred_at','recipients'];
    foreach ($required as $field) {
        if (empty($event[$field])) return new WP_Error('sun_event_missing_'.$field, 'Required field not provided');
    }
    $config = $GLOBALS['mkt_test_registered'][$event['producer']] ?? null;
    if (!$config || $event['owner'] !== $config['owner'] || !preg_match('/^[A-Z][A-Za-z0-9]*(?:\.[A-Z][A-Za-z0-9]*)+$/', $event['event_type'])) {
        return new WP_Error('sun_contract_invalid','Producer, owner or event type invalid');
    }
    if (!preg_match('/^v?[0-9]+(?:\.[0-9]+){0,2}$/', $event['schema_version'])) return new WP_Error('sun_schema_invalid','Schema invalid');
    if (!is_array($event['recipients']) || ($event['recipients'][0]['user_id'] ?? 0) <= 0) return new WP_Error('sun_recipients_invalid','Recipient invalid');
    $GLOBALS['mkt_test_events'][] = $event;
    if ($GLOBALS['mkt_test_next_status'] === 'error') return new WP_Error('sun_event_processing_failed','Test failure');
    return ['event_public_id'=>'test-event-id','status'=>$GLOBALS['mkt_test_next_status'],'created'=>1,'suppressed'=>0];
}
require dirname(__DIR__) . '/includes/class-mkt-integrations.php';

$ok = MKT_Integrations::notify(13, 'MarketplaceOfferCreated.v1', [
    'safe_summary'=>'You received a new offer.',
    'url'=>'https://example.com/marketplace/dashboard/',
], 'marketplace-event-123:13');
assert_true($ok, 'File 19 canonical ingestion accepts the Marketplace producer event.');
assert_same('File 18', $GLOBALS['mkt_test_registered']['file-18-marketplace']['owner'], 'Producer owner is registered as File 18.');
$event = end($GLOBALS['mkt_test_events']);
assert_same('Marketplace.OfferCreated', $event['event_type'], 'Marketplace native event maps to File 19 dotted past-tense fact.');
assert_same('marketplace-event-123:13', $event['event_id'], 'Deduplication key persists as immutable File 19 event ID.');
assert_same(13, $event['recipients'][0]['user_id'], 'Recipient is a canonical File 19 user reference.');
assert_same('You received a new offer.', $event['data']['summary'], 'No raw private chat body is copied.');
assert_same('1.0.0', $event['schema_version'], 'File 19 schema version is declared.');

$GLOBALS['mkt_test_next_status']='duplicate';
assert_true(MKT_Integrations::notify(13, 'MarketplaceOfferCreated.v1', [], 'marketplace-event-123:13'), 'File 19 duplicate acknowledgement is idempotent success.');
$GLOBALS['mkt_test_next_status']='error';
assert_true(!MKT_Integrations::notify(13, 'MarketplaceOfferCreated.v1', [], 'marketplace-event-123:13'), 'File 19 processing errors are never acknowledged.');
assert_true(!MKT_Integrations::notify(13, 'UnexpectedEvent', [], 'bad-event-id'), 'Unversioned incompatible event types fail closed.');
assert_true(!MKT_Integrations::notify(0, 'MarketplaceOfferCreated.v1', [], 'bad-user'), 'Missing recipient cannot be notified.');
fwrite(STDOUT, "File 18/File 19 public contract regression complete.\n");
