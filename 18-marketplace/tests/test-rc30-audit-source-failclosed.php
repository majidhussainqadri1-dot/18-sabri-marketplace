<?php
/**
 * RC30 actual audit owner negative regression: SQL outages must never yield
 * a green hash-chain verification or append an unauthenticated genesis entry.
 * These mocks prove owner fail-closed paths; MySQL staging remains mandatory.
 */
require __DIR__ . '/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
function sanitize_text_field($v): string { return trim(strip_tags((string)$v)); }
function wp_strip_all_tags($v): string { return strip_tags((string)$v); }
function wp_json_encode($v): string { return json_encode($v); }
function wp_salt($which): string { return 'audit-test-key'; }
function get_current_user_id(): int { return 17; }
function do_action(...$args): void {}
class MKT_DB {
    public static function table(string $domain): string { return 'wp_mkt_' . $domain; }
    public static function transaction_active(): bool { return false; }
    public static function uuid(): string { static $seq=0; return sprintf('00000000-0000-4000-8000-%012d', ++$seq); }
    public static function now(): string { return '2026-10-22 10:00:00'; }
    public static function release_named_lock_after_transaction(string $name): void {}
}
class AuditFailureDB {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public string $mode = 'healthy';
    public int $inserted = 0;
    public int $released = 0;
    public array $rows = [];
    public array $inserts = [];
    public function prepare(string $query, ...$args): string {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }
    public function get_var(string $sql) {
        if (str_contains($sql, 'GET_LOCK')) return 1;
        if (str_contains($sql, 'RELEASE_LOCK')) { $this->released++; return 1; }
        if (str_contains($sql, 'SELECT COUNT(*)')) {
            if ($this->mode === 'count_error') { $this->last_error = 'Injected COUNT SQL error'; return null; }
            if ($this->mode === 'count_null') return null;
            return $this->mode === 'genesis' ? 0 : 1;
        }
        if (str_contains($sql, 'SELECT entry_hash')) {
            if ($this->mode === 'read_error') { $this->last_error = 'Injected predecessor SQL error'; return null; }
            if (in_array($this->mode, ['genesis','count_error','count_null','nonempty_null'], true)) return null;
            if ($this->mode === 'empty_hash') return '';
            return str_repeat('f', 64);
        }
        return null;
    }
    public function get_results(string $sql, $type) {
        if ($this->mode === 'verify_error') { $this->last_error = 'Injected audit SELECT error'; return []; }
        if ($this->mode === 'verify_null') return null;
        return $this->rows;
    }
    public function insert(string $table, array $data): int {
        $this->inserted++;
        $this->inserts[]=$data;
        return 1;
    }
}
$wpdb = new AuditFailureDB();
require dirname(__DIR__) . '/includes/class-mkt-audit.php';

function expect_audit_throw(AuditFailureDB $db, string $mode, string $meaning): void {
    $db->mode = $mode; $db->last_error = ''; $before=$db->inserted; $released=$db->released;
    $caught=false;
    try { MKT_Audit::record('listing_updated', 'listing', 'uuid-1'); }
    catch (RuntimeException $e) { $caught=true; }
    assert_same(true,$caught,$meaning . ': denied');
    assert_same($before,$db->inserted,$meaning . ': no false audit insert');
    assert_same($released+1,$db->released,$meaning . ': lock released on refusal');
}
expect_audit_throw($wpdb,'read_error','Failed predecessor SELECT');
expect_audit_throw($wpdb,'nonempty_null','NULL predecessor with existing audit rows');
expect_audit_throw($wpdb,'count_error','Failed genesis COUNT');
expect_audit_throw($wpdb,'count_null','Uncertain genesis count');
expect_audit_throw($wpdb,'empty_hash','Corrupt predecessor hash');

$wpdb->mode='genesis'; $wpdb->last_error='';
MKT_Audit::record('listing_created','listing','uuid-2');
assert_same(1,$wpdb->inserted,'Verified empty audit table can receive first canonical record.');
assert_same('',$wpdb->inserts[0]['previous_hash'],'First-record hash predecessor is legitimate empty genesis.');

$wpdb->mode='healthy'; $wpdb->last_error='';
MKT_Audit::record('listing_updated','listing','uuid-3');
assert_same(2,$wpdb->inserted,'Verified nonempty predecessor accepts next record.');
assert_same(str_repeat('f',64),$wpdb->inserts[1]['previous_hash'],'Next audit record chains to verified predecessor.');

$wpdb->mode='verify_null'; $wpdb->last_error='';
$result=MKT_Audit::verify_chain();
assert_same(false,$result['valid'],'NULL audit SELECT can never pass chain verification.');
assert_same('audit_chain_query_unavailable',$result['error'],'NULL audit SELECT supplies explicit unavailable signal.');
$wpdb->mode='verify_error'; $wpdb->last_error='';
$result=MKT_Audit::verify_chain();
assert_same(false,$result['valid'],'SQL error with empty result cannot claim valid chain.');
assert_same('audit_chain_query_unavailable',$result['error'],'SQL error marks verification unavailable.');
$wpdb->mode='healthy'; $wpdb->rows=[]; $wpdb->last_error='';
$result=MKT_Audit::verify_chain();
assert_same(true,$result['valid'],'Verified actual empty table is not a query failure.');
assert_same(0,$result['checked'],'Empty audit table reports exact zero checked.');
$wpdb->rows=[['previous_hash'=>'','trace_id'=>'bad','actor_user_id'=>17,'action'=>'listing_created',
    'object_type'=>'listing','object_public_id'=>'uuid-2','purpose'=>'','outcome'=>'success',
    'reason_code'=>'','details_json'=>'[]','created_at'=>'2026-10-22 10:00:00','entry_hash'=>'not-a-valid-hash']];
$result=MKT_Audit::verify_chain();
assert_same(false,$result['valid'],'Tampered persisted row fails existing cryptographic integrity check.');
fwrite(STDOUT, "RC30 actual audit-chain source-read, genesis, locking and SQL failure regression complete.\n");
