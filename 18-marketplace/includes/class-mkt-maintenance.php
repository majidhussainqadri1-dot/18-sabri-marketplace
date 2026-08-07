<?php
defined('ABSPATH') || exit;

final class MKT_Maintenance {
    public static function schedule(): void {
        if (!wp_next_scheduled('mkt_hourly_maintenance')) wp_schedule_event(time() + 300, 'hourly', 'mkt_hourly_maintenance');
        if (!wp_next_scheduled('mkt_daily_maintenance')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mkt_daily_maintenance');
    }

    public static function unschedule(): void {
        foreach (['mkt_hourly_maintenance','mkt_daily_maintenance'] as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) wp_unschedule_event($timestamp, $hook);
        }
    }

    public static function hourly(): void {
        self::expire_records();
        MKT_Events::process_outbox();
        self::process_communication_handoffs();
        self::reconcile_sellers(50);
    }

    public static function daily(): void {
        self::expire_records();
        self::purge_retention();
        self::aggregate_metrics();
        self::reconcile_sellers(500);
    }

    private static function expire_records(): void {
        global $wpdb;
        $now = MKT_DB::now();
        $listings = $wpdb->get_results($wpdb->prepare("SELECT public_id,version,status FROM " . MKT_DB::table('listings') . " WHERE status IN ('active','paused') AND expires_at IS NOT NULL AND expires_at<=%s LIMIT 200", $now), ARRAY_A);
        foreach ($listings as $listing) {
            $wpdb->update(MKT_DB::table('listings'), ['status' => 'expired', 'updated_at' => $now, 'version' => (int) $listing['version'] + 1], ['public_id' => $listing['public_id'], 'version' => (int) $listing['version']]);
            MKT_Events::enqueue('MarketplaceListingStatusChanged.v1', 'listing', (string) $listing['public_id'], ['from' => $listing['status'], 'to' => 'expired', 'safe_summary' => __('A listing expired.', 'marketplace')]);
        }
        $offers = $wpdb->get_results($wpdb->prepare("SELECT public_id,version,status FROM " . MKT_DB::table('offers') . " WHERE status IN ('open','countered') AND expires_at<=%s LIMIT 500", $now), ARRAY_A);
        foreach ($offers as $offer) {
            $wpdb->update(MKT_DB::table('offers'), ['status' => 'expired', 'updated_at' => $now, 'version' => (int) $offer['version'] + 1], ['public_id' => $offer['public_id'], 'version' => (int) $offer['version']]);
        }
    }

    private static function reconcile_sellers(int $limit): void {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('sellers') . ' ORDER BY updated_at ASC LIMIT %d', $limit), ARRAY_A);
        foreach ($rows as $seller) {
            $eligibility = MKT_Auth::seller_eligibility((int) $seller['user_id']);
            $target = $eligibility['eligible'] ? 'approved' : 'suspended';
            if ($target === $seller['status']) continue;
            $wpdb->update(MKT_DB::table('sellers'), [
                'status' => $target,
                'eligibility_snapshot' => wp_json_encode(['reasons' => $eligibility['reasons'], 'captured_at' => MKT_DB::now(), 'identity_version' => $eligibility['assertions']['version']]),
                'updated_at' => MKT_DB::now(),
                'suspended_at' => $target === 'suspended' ? MKT_DB::now() : null,
                'version' => (int) $seller['version'] + 1,
            ], ['id' => (int) $seller['id'], 'version' => (int) $seller['version']]);
            if ($target === 'suspended') {
                $wpdb->query($wpdb->prepare("UPDATE " . MKT_DB::table('listings') . " SET status='paused',updated_at=%s,version=version+1 WHERE seller_id=%d AND status='active'", MKT_DB::now(), (int) $seller['id']));
            }
            MKT_Events::enqueue('MarketplaceSellerStatusChanged.v1', 'seller', (string) $seller['public_id'], ['from' => $seller['status'], 'to' => $target]);
        }
    }

    private static function process_communication_handoffs(): void {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . MKT_DB::table('migration_handoffs') . " WHERE target_owner='file-17-communication' AND status IN ('pending','retry') ORDER BY id ASC LIMIT 50", ARRAY_A);
        foreach ($rows as $row) {
            $legacy_payload = self::legacy_communication_payload((string) $row['legacy_type'], (int) $row['legacy_id']);
            if (is_wp_error($legacy_payload)) {
                $attempts = (int) $row['attempts'] + 1;
                $wpdb->update(MKT_DB::table('migration_handoffs'), [
                    'status' => $attempts >= 10 ? 'blocked' : 'retry',
                    'attempts' => $attempts,
                    'last_error' => sanitize_text_field($legacy_payload->get_error_message()),
                    'updated_at' => MKT_DB::now(),
                ], ['id' => (int) $row['id']]);
                continue;
            }
            $result = apply_filters('sabri_communication_import_legacy_reference', null, [
                'source' => 'file-18-marketplace-legacy',
                'source_version' => MKT_SCHEMA_VERSION,
                'legacy_type' => $row['legacy_type'],
                'legacy_id' => (int) $row['legacy_id'],
                'payload_hash' => $row['payload_hash'],
                'payload' => $legacy_payload,
                'privacy_class' => 'private_communication',
                'delete_source_after_verified_import' => false,
            ]);
            if (is_array($result) && !empty($result['target_reference'])) {
                $wpdb->update(MKT_DB::table('migration_handoffs'), ['status' => 'completed', 'target_reference' => sanitize_text_field((string) $result['target_reference']), 'updated_at' => MKT_DB::now(), 'last_error' => ''], ['id' => (int) $row['id']]);
            } else {
                $attempts = (int) $row['attempts'] + 1;
                $wpdb->update(MKT_DB::table('migration_handoffs'), ['status' => $attempts >= 10 ? 'blocked' : 'retry', 'attempts' => $attempts, 'last_error' => 'File 17 import contract unavailable or declined.', 'updated_at' => MKT_DB::now()], ['id' => (int) $row['id']]);
            }
        }
    }

    private static function legacy_communication_payload(string $type, int $legacy_id): array|WP_Error {
        global $wpdb;
        if (!in_array($type, ['conversation','message'], true) || $legacy_id <= 0) {
            return new WP_Error('mkt_invalid_legacy_reference', __('Invalid legacy communication reference.', 'marketplace'));
        }
        $table = $wpdb->prefix . ($type === 'conversation' ? 'smp_conversations' : 'smp_messages');
        $exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
        if (!$exists) {
            return new WP_Error('mkt_legacy_source_missing', __('Legacy communication source table is unavailable.', 'marketplace'));
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $legacy_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('mkt_legacy_record_missing', __('Legacy communication record is unavailable.', 'marketplace'));
        }
        $allowed = $type === 'conversation'
            ? ['id','product_id','buyer_id','seller_id','seller_user_id','status','deal_status','agreed_price','currency','last_message_id','last_message_at','created_at','updated_at']
            : ['id','conversation_id','sender_id','message_type','message_text','attachment_path','attachment_name','attachment_mime','attachment_size','offer_amount','offer_status','contact_payload','reply_to','delivered_at','read_at','edited_at','deleted_for_all','deleted_at','created_at'];
        $payload = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $row)) continue;
            $value = $row[$field];
            if (is_string($value) && strlen($value) > 1048576) {
                return new WP_Error('mkt_legacy_payload_too_large', __('A legacy communication record exceeds the import limit.', 'marketplace'));
            }
            $payload[$field] = $value;
        }
        $payload['_source_checksum'] = hash('sha256', wp_json_encode($payload));
        return $payload;
    }

    private static function purge_retention(): void {
        global $wpdb;
        $settings = MKT_DB::settings();
        $audit_cutoff = gmdate('Y-m-d H:i:s', time() - (int) $settings['retention_audit_days'] * DAY_IN_SECONDS);
        $report_cutoff = gmdate('Y-m-d H:i:s', time() - (int) $settings['retention_reports_days'] * DAY_IN_SECONDS);
        $dispute_cutoff = gmdate('Y-m-d H:i:s', time() - (int) $settings['retention_disputes_days'] * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . MKT_DB::table('audit') . ' WHERE created_at<%s', $audit_cutoff));
        $wpdb->query($wpdb->prepare("UPDATE " . MKT_DB::table('reports') . " SET details='[Expired by retention policy]',evidence_refs='[]',decision_note='' WHERE updated_at<%s AND status='closed'", $report_cutoff));
        $wpdb->query($wpdb->prepare("UPDATE " . MKT_DB::table('disputes') . " SET statement='[Expired by retention policy]',evidence_refs='[]',decision_note='',access_grants='[]' WHERE updated_at<%s AND status='closed'", $dispute_cutoff));
        $wpdb->query("DELETE FROM " . MKT_DB::table('idempotency') . " WHERE expires_at<UTC_TIMESTAMP()");
    }

    private static function aggregate_metrics(): void {
        global $wpdb;
        $date = gmdate('Y-m-d', time() - DAY_IN_SECONDS);
        $metrics = [
            'active_listings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . MKT_DB::table('listings') . " WHERE status='active'"),
            'offers_created' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table('offers') . ' WHERE DATE(created_at)=%s', $date)),
            'deals_accepted' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table('deals') . ' WHERE DATE(created_at)=%s', $date)),
            'reports_created' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table('reports') . ' WHERE DATE(created_at)=%s', $date)),
        ];
        foreach ($metrics as $key => $value) {
            $wpdb->replace(MKT_DB::table('metrics_daily'), [
                'metric_date' => $date,
                'metric_key' => $key,
                'dimension_hash' => hash('sha256', 'global'),
                'value_count' => $value,
                'value_sum' => 0,
                'metadata_json' => '{}',
                'updated_at' => MKT_DB::now(),
            ]);
        }
    }
}
