<?php
/**
 * Simulated DB regression: named audit lock must remain held until the outer
 * owner transaction COMMIT/ROLLBACK; recursive transactions may not COMMIT early.
 * MySQL concurrency staging is still required.
 */
require __DIR__ . '/bootstrap.php';
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value): string { return strip_tags((string) $value); }
function wp_json_encode($v): string { return json_encode($v); }
function wp_generate_uuid4(): string { static $i=0; return sprintf('00000000-0000-4000-8000-%012d',++$i); }
function current_time($format, $gmt = false): string { return '2026-10-21 00:17:00'; }
function wp_salt($purpose): string { return 'test-secret'; }
function get_current_user_id(): int { return 42; }
function do_action(...$args): void {}
final class LockWPDB {
    public string $prefix='wp_';
    public string $last_error='';
    public array $ops=[];
    public function prepare($query,...$args): string { return vsprintf(str_replace('%s', "'%s'", $query), $args); }
    public function get_var($query) {
        if (str_contains($query, 'GET_LOCK')) { $this->ops[]='LOCK'; return 1; }
        if (str_contains($query, 'RELEASE_LOCK')) { $this->ops[]='UNLOCK'; return 1; }
        if (str_contains($query, 'SELECT entry_hash')) { $this->ops[]='READ_HASH'; return 'genesis'; }
        return null;
    }
    public function query($query) {
        if (in_array($query,['START TRANSACTION','COMMIT','ROLLBACK'],true)) $this->ops[]=$query;
        return 1;
    }
    public function insert($table,$data) { $this->ops[]='AUDIT_INSERT'; return 1; }
}
$wpdb=new LockWPDB();
require dirname(__DIR__).'/includes/class-mkt-db.php';
require dirname(__DIR__).'/includes/class-mkt-audit.php';

$result=MKT_DB::transaction(function(){
    MKT_Audit::record('item_saved','listing','test-public-uuid');
    return true;
});
assert_true($result === true, 'Audit transaction succeeds.');
assert_same(['START TRANSACTION','LOCK','READ_HASH','AUDIT_INSERT','COMMIT','UNLOCK'], $wpdb->ops, 'Audit serialization lock is released only after COMMIT.');

$wpdb->ops=[];
try {
    MKT_DB::transaction(function(){
        MKT_Audit::record('item_saved','listing','test-public-uuid');
        throw new RuntimeException('Simulated owner failure');
    });
} catch (RuntimeException $e) {}
assert_same(['START TRANSACTION','LOCK','READ_HASH','AUDIT_INSERT','ROLLBACK','UNLOCK'], $wpdb->ops, 'Audit serialization lock is released only after ROLLBACK.');

$wpdb->ops=[];
MKT_DB::transaction(function(){
    MKT_DB::transaction(function(){
        MKT_Audit::record('item_saved','listing','test-public-uuid');
        return true;
    });
    return true;
});
assert_same(1, count(array_filter($wpdb->ops, static fn($x) => $x === 'START TRANSACTION')), 'Nested transactions do not implicitly commit owner state.');
assert_same(1, count(array_filter($wpdb->ops, static fn($x) => $x === 'COMMIT')), 'Only the outermost owner transaction commits.');
assert_same('UNLOCK', $wpdb->ops[count($wpdb->ops)-1], 'Audit lock remains held until the outermost COMMIT.');

$wpdb->ops=[];
MKT_DB::transaction(function(){
    MKT_Audit::record('item_saved','listing','first');
    MKT_Audit::record('item_saved','listing','second');
    return true;
});
assert_same(2, count(array_filter($wpdb->ops, static fn($x) => $x === 'UNLOCK')), 'Every recursive named-lock acquisition is released after owner COMMIT.');

fwrite(STDOUT, "Marketplace audit transaction and lock lifetime regressions complete.\n");
