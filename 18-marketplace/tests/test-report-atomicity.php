<?php
/**
 * Runtime-shaped negative tests for the File 18 report owner transaction.
 * Standalone WPDB simulation; not a substitute for WordPress/MySQL staging.
 */
require __DIR__ . '/bootstrap.php';
function get_current_user_id():int{return 42;}
define('ARRAY_A', 'ARRAY_A');
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function wp_is_uuid($value): bool { return (bool) preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $value); }
function wp_kses_post($value): string { return strip_tags((string) $value); }
function wp_strip_all_tags($value): string { return strip_tags((string) $value); }
function wp_json_encode($value): string { return json_encode($value); }
class MKT_Auth { public static function can(string $role, array $ctx = []) { return true; } }
class MKT_Listings { public static function get(string $id, bool $private = false) { return ['public_id'=>$id,'status'=>'active']; } }
class MKT_DB {
    private static int $number = 0;
    public static function table(string $name): string { return 'wp_mkt_'.$name; }
    public static function uuid(): string { return '00000000-0000-4000-8000-'.str_pad((string) (++self::$number), 12, '0', STR_PAD_LEFT); }
    public static function now(): string { return '2026-10-21 00:00:00'; }
    public static function transaction(callable $fn) {
        global $wpdb;
        $checkpoint = $wpdb->rows;
        try {
            $result = $fn($wpdb);
            if (is_wp_error($result)) $wpdb->rows = $checkpoint;
            return $result;
        } catch (Throwable $e) {
            $wpdb->rows = $checkpoint;
            throw $e;
        }
    }
}
class TestWPDB {
    public int $insert_id = 0;
    public array $rows = [];
    public bool $missing_readback = false;
    public function insert(string $table, array $data) {
        $this->insert_id = count($this->rows) + 1;
        $this->rows[$this->insert_id] = ['id'=>$this->insert_id,'decision_code'=>'']+$data;
        return 1;
    }
    public function prepare(string $query, ...$params): string {
        return vsprintf(str_replace(['%d','%s'],['%d',"'%s'"],$query), $params);
    }
    public function get_row(string $query, string $type) {
        if ($this->missing_readback) return null;
        if (!preg_match('/WHERE id=(\d+)/', $query, $m)) return null;
        return $this->rows[(int)$m[1]] ?? null;
    }
}
class MKT_Audit {
    public static bool $fail = false;
    public static function trace_id(): string { return 'fixed-test-trace'; }
    public static function record(...$args) {
        global $wpdb;
        if (self::$fail) throw new RuntimeException('Audit insert failed');
        $wpdb->insert_id = 771;
        return 'audit';
    }
}
class MKT_Events {
    public static bool $fail = false;
    public static function enqueue(...$args) {
        global $wpdb;
        if (self::$fail) throw new RuntimeException('Outbox insert failed');
        $wpdb->insert_id = 881;
        return 'event';
    }
}
$wpdb = new TestWPDB();
require dirname(__DIR__).'/includes/class-mkt-moderation.php';
$target='00000000-0000-4000-8000-000000000999';
$input=['target_type'=>'listing','target_public_id'=>$target,'reason'=>'fraud','details'=>'Verified counterfeit / fraud report'];

$ok=MKT_Moderation::create_report($input,42);
assert_true(is_array($ok), 'Report is returned as an array after audit/outbox writes.');
assert_same(array_values($wpdb->rows)[0]['public_id'], $ok['public_id'], 'Report readback identity survives later inserts.');
assert_same(1, count($wpdb->rows), 'A successful safety report creates one canonical report.');

MKT_Events::$fail=true;
$failure=MKT_Moderation::create_report($input,42);
assert_true(is_wp_error($failure), 'Outbox failure is returned as an error.');
assert_same(1, count($wpdb->rows), 'Outbox failure rolls the report creation back.');
MKT_Events::$fail=false;

MKT_Audit::$fail=true;
$failure=MKT_Moderation::create_report($input,42);
assert_true(is_wp_error($failure), 'Audit failure is returned as an error.');
assert_same(1, count($wpdb->rows), 'Audit failure rolls the report creation back.');
MKT_Audit::$fail=false;

$wpdb->missing_readback=true;
$failure=MKT_Moderation::create_report($input,42);
assert_true(is_wp_error($failure), 'Missing report readback is rejected.');
assert_same(1, count($wpdb->rows), 'Missing report readback rolls the report back.');
fwrite(STDOUT, "Marketplace report atomicity and durable ID regression complete.\n");
