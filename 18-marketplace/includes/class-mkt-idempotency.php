<?php
defined('ABSPATH') || exit;

final class MKT_Idempotency {
    public static function run(string $scope, string $key, array $request_data, callable $callback) {
        $key = sanitize_text_field($key);
        if (strlen($key) < 12 || strlen($key) > 190 || !preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            return new WP_Error('mkt_invalid_idempotency_key', __('A valid Idempotency-Key header is required for this action.', 'marketplace'), ['status' => 422]);
        }
        $actor_id = get_current_user_id();
        $scope = substr(sanitize_key($scope), 0, 80);
        $request_hash = hash('sha256', wp_json_encode(self::canonicalize($request_data)));
        global $wpdb;
        $table = MKT_DB::table('idempotency');
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (scope,actor_user_id,idempotency_key,request_hash,status,created_at,expires_at) VALUES (%s,%d,%s,%s,'processing',%s,%s)",
            $scope, $actor_id, $key, $request_hash, MKT_DB::now(), gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS)
        ));
        if (!$inserted) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE actor_user_id=%d AND scope=%s AND idempotency_key=%s", $actor_id, $scope, $key), ARRAY_A);
            if (!$row) return new WP_Error('mkt_idempotency_unavailable', __('The request could not be reserved safely.', 'marketplace'), ['status' => 503]);
            if (!hash_equals((string) $row['request_hash'], $request_hash)) {
                return new WP_Error('mkt_idempotency_conflict', __('The Idempotency-Key was already used with different request data.', 'marketplace'), ['status' => 409]);
            }
            if ((string) $row['status'] === 'completed') {
                $cached = json_decode((string) $row['response_json'], true);
                return $cached ?? true;
            }
            if ((string) $row['status'] === 'processing') {
                return new WP_Error('mkt_request_in_progress', __('An identical request is already in progress.', 'marketplace'), ['status' => 409, 'retry_after' => 2]);
            }
            $wpdb->update($table, ['status' => 'processing', 'response_code' => 0, 'response_json' => null, 'created_at' => MKT_DB::now(), 'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS)], ['id' => (int) $row['id']]);
        }

        try {
            $result = $callback();
            if (is_wp_error($result)) {
                $wpdb->update($table, [
                    'status' => 'failed',
                    'response_code' => self::error_status($result),
                    'response_json' => wp_json_encode(['code' => $result->get_error_code(), 'message' => $result->get_error_message()]),
                ], ['actor_user_id' => $actor_id, 'scope' => $scope, 'idempotency_key' => $key]);
                return $result;
            }
            $response = is_object($result) && method_exists($result, 'get_data') ? $result->get_data() : $result;
            $wpdb->update($table, ['status' => 'completed', 'response_code' => 200, 'response_json' => wp_json_encode($response)], ['actor_user_id' => $actor_id, 'scope' => $scope, 'idempotency_key' => $key]);
            return $result;
        } catch (Throwable $e) {
            $wpdb->update($table, ['status' => 'failed', 'response_code' => 500, 'response_json' => wp_json_encode(['code' => 'mkt_internal_error'])], ['actor_user_id' => $actor_id, 'scope' => $scope, 'idempotency_key' => $key]);
            throw $e;
        }
    }

    private static function error_status(WP_Error $error): int {
        $data = $error->get_error_data();
        return is_array($data) && isset($data['status']) ? (int) $data['status'] : 400;
    }

    private static function canonicalize(array $data): array {
        foreach ($data as $key => $value) {
            if (is_array($value)) $data[$key] = self::canonicalize($value);
        }
        if (!array_is_list($data)) ksort($data);
        return $data;
    }
}
