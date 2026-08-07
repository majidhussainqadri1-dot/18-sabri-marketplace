<?php
defined('ABSPATH') || exit;

final class MKT_Audit {
    public static function trace_id(): string {
        static $trace_id = null;
        return $trace_id ??= MKT_DB::uuid();
    }

    public static function record(string $action, string $object_type, string $object_public_id = '', array $details = [], string $outcome = 'success', string $reason_code = '', string $purpose = ''): string {
        global $wpdb;
        $table = MKT_DB::table('audit');
        $lock_name = $wpdb->prefix . 'mkt_audit_chain';
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,3)', $lock_name)) === 1;
        $previous = $locked ? (string) $wpdb->get_var("SELECT entry_hash FROM {$table} ORDER BY id DESC LIMIT 1") : 'LOCK_UNAVAILABLE';
        $trace_id = self::trace_id();
        $created_at = MKT_DB::now();
        $actor = get_current_user_id();
        $safe_details = self::sanitize_details($details);
        if (!$locked) $safe_details['audit_chain_lock_gap'] = true;
        $payload = wp_json_encode([$previous,$trace_id,$actor,sanitize_key($action),sanitize_key($object_type),sanitize_text_field($object_public_id),sanitize_text_field($purpose),sanitize_key($outcome),sanitize_key($reason_code),$safe_details,$created_at]);
        $entry_hash = hash_hmac('sha256', (string) $payload, wp_salt('auth'));
        $inserted = $wpdb->insert($table, [
            'entry_id' => MKT_DB::uuid(),
            'trace_id' => $trace_id,
            'actor_user_id' => $actor,
            'action' => sanitize_key($action),
            'object_type' => sanitize_key($object_type),
            'object_public_id' => sanitize_text_field($object_public_id),
            'purpose' => sanitize_text_field($purpose),
            'outcome' => sanitize_key($outcome),
            'reason_code' => sanitize_key($reason_code),
            'details_json' => wp_json_encode($safe_details),
            'previous_hash' => $previous,
            'entry_hash' => $entry_hash,
            'created_at' => $created_at,
        ]);
        if ($locked) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        if (!$inserted) {
            // Audit is part of the governed owner transaction. A silent audit-write
            // loss would let a state mutation commit without its required evidence.
            throw new RuntimeException('Marketplace audit evidence could not be persisted.');
        }
        do_action('mkt_audit_recorded', $trace_id, $action, $object_type, $object_public_id, $outcome);
        return $trace_id;
    }

    public static function verify_chain(int $limit = 500): array {
        global $wpdb;
        $limit = min(5000, max(1, $limit));
        $rows = $wpdb->get_results('SELECT * FROM ' . MKT_DB::table('audit') . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A);
        $rows = array_reverse(is_array($rows) ? $rows : []);
        $breaks = 0; $lock_gaps = 0; $prior_hash = null;
        foreach ($rows as $index => $row) {
            $details = json_decode((string) $row['details_json'], true);
            if (!is_array($details)) $details = [];
            if ((string) $row['previous_hash'] === 'LOCK_UNAVAILABLE' || !empty($details['audit_chain_lock_gap'])) $lock_gaps++;
            if ($index > 0 && (string) $row['previous_hash'] !== (string) $prior_hash && (string) $row['previous_hash'] !== 'LOCK_UNAVAILABLE') $breaks++;
            $payload = wp_json_encode([
                (string) $row['previous_hash'], (string) $row['trace_id'], (int) $row['actor_user_id'],
                (string) $row['action'], (string) $row['object_type'], (string) $row['object_public_id'],
                (string) $row['purpose'], (string) $row['outcome'], (string) $row['reason_code'], $details, (string) $row['created_at'],
            ]);
            $expected = hash_hmac('sha256', (string) $payload, wp_salt('auth'));
            if (!hash_equals($expected, (string) $row['entry_hash'])) $breaks++;
            $prior_hash = (string) $row['entry_hash'];
        }
        return ['valid' => $breaks === 0 && $lock_gaps === 0, 'checked' => count($rows), 'breaks' => $breaks, 'lock_gaps' => $lock_gaps];
    }

    private static function sanitize_details(array $details): array {
        $blocked = ['password','token','secret','identity_number','phone','email','address','message','evidence'];
        foreach ($details as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                $details[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $details[$key] = self::sanitize_details($value);
            } elseif (is_string($value)) {
                $details[$key] = mb_substr(wp_strip_all_tags($value), 0, 500);
            }
        }
        return $details;
    }
}
