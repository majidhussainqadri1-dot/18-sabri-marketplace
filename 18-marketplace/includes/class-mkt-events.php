<?php
defined('ABSPATH') || exit;

final class MKT_Events {
    public static function enqueue(string $event_type, string $aggregate_type, string $aggregate_public_id, array $payload, string $privacy_class = 'internal'): string {
        global $wpdb;
        $event_id = MKT_DB::uuid();
        $wpdb->insert(MKT_DB::table('outbox'), [
            'event_id' => $event_id,
            'event_type' => sanitize_text_field($event_type),
            'aggregate_type' => sanitize_key($aggregate_type),
            'aggregate_public_id' => sanitize_text_field($aggregate_public_id),
            'actor_user_id' => get_current_user_id(),
            'payload_json' => wp_json_encode(self::envelope($event_id, $event_type, $aggregate_type, $aggregate_public_id, $payload)),
            'privacy_class' => sanitize_key($privacy_class),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => MKT_DB::now(),
            'created_at' => MKT_DB::now(),
        ]);
        if (!wp_next_scheduled('mkt_process_outbox')) {
            wp_schedule_single_event(time() + 5, 'mkt_process_outbox');
        }
        return $event_id;
    }

    public static function envelope(string $event_id, string $event_type, string $aggregate_type, string $aggregate_public_id, array $payload): array {
        return [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'event_version' => MKT_Contracts::EVENT_VERSION,
            'occurred_at' => gmdate('c'),
            'producer' => 'file-18-marketplace',
            'producer_version' => MKT_VERSION,
            'aggregate' => ['type' => $aggregate_type, 'public_id' => $aggregate_public_id],
            'actor_user_id' => get_current_user_id(),
            'trace_id' => MKT_Audit::trace_id(),
            'payload' => $payload,
        ];
    }

    public static function process_outbox(): void {
        global $wpdb;
        $table = MKT_DB::table('outbox');
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status IN ('pending','retry') AND available_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT 50", ARRAY_A);
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $attempts = (int) $row['attempts'] + 1;
            $claimed = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status='processing',attempts=%d WHERE id=%d AND status IN ('pending','retry')", $attempts, $id));
            if (!$claimed) {
                continue;
            }
            $event = json_decode((string) $row['payload_json'], true);
            $ok = false;
            $error = '';
            try {
                $platform_ack = (bool) apply_filters('sabri_platform_ingest_event', false, $event);
                $local_consumers = has_action('mkt_event') > 0;
                if ($local_consumers) {
                    do_action('mkt_event', $event);
                }
                $notification_ack = self::send_derived_notifications($event);
                $recipients = array_values(array_filter(array_unique(array_map('intval', (array) (($event['payload']['notify_user_ids'] ?? []))))));
                $ok = $platform_ack || $local_consumers || ($recipients && $notification_ack);
                if (!$ok) {
                    $error = 'No platform, local, or notification consumer acknowledged the event.';
                }
            } catch (Throwable $e) {
                $error = mb_substr($e->getMessage(), 0, 240);
            }
            if ($ok) {
                $wpdb->update($table, ['status' => 'processed', 'processed_at' => MKT_DB::now(), 'last_error' => ''], ['id' => $id]);
            } elseif ($attempts >= 8) {
                $wpdb->update($table, ['status' => 'dead', 'last_error' => $error ?: 'No consumer acknowledged the event.'], ['id' => $id]);
            } else {
                $delay = min(3600, 30 * (2 ** min($attempts, 6)));
                $wpdb->update($table, [
                    'status' => 'retry',
                    'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                    'last_error' => $error ?: 'Retry scheduled.',
                ], ['id' => $id]);
            }
        }
    }

    private static function send_derived_notifications(array $event): bool {
        $type = (string) ($event['event_type'] ?? '');
        $payload = (array) ($event['payload'] ?? []);
        $recipients = (array) ($payload['notify_user_ids'] ?? []);
        $delivered = true;
        $attempted = false;
        foreach (array_unique(array_map('intval', $recipients)) as $user_id) {
            if ($user_id <= 0 || $user_id === (int) ($event['actor_user_id'] ?? 0)) {
                continue;
            }
            $attempted = true;
            $delivered = MKT_Integrations::notify($user_id, $type, [
                'object_public_id' => (string) ($event['aggregate']['public_id'] ?? ''),
                'safe_summary' => sanitize_text_field((string) ($payload['safe_summary'] ?? __('Marketplace activity updated.', 'marketplace'))),
                'url' => esc_url_raw((string) ($payload['url'] ?? home_url('/marketplace/dashboard/'))),
            ], (string) $event['event_id'] . ':' . $user_id) && $delivered;
        }
        return $attempted && $delivered;
    }


    public static function handle_external_event($event, $source = 'platform'): void {
        if (!is_array($event)) {
            return;
        }
        self::consume_external(sanitize_key((string) $source), $event, static function(array $event) {
            global $wpdb;
            $type = (string) ($event['event_type'] ?? '');
            $payload = (array) ($event['payload'] ?? []);
            if ($type === 'SellerSuspended.v1') {
                $user_id = (int) ($payload['user_id'] ?? 0);
                if ($user_id <= 0) {
                    return new WP_Error('mkt_event_missing_user', 'SellerSuspended event is missing user_id.');
                }
                $seller = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('sellers') . ' WHERE user_id=%d', $user_id), ARRAY_A);
                if (!$seller) {
                    return true;
                }
                $wpdb->update(MKT_DB::table('sellers'), [
                    'status' => 'suspended',
                    'suspended_at' => MKT_DB::now(),
                    'updated_at' => MKT_DB::now(),
                    'version' => (int) $seller['version'] + 1,
                ], ['id' => (int) $seller['id'], 'version' => (int) $seller['version']]);
                $wpdb->query($wpdb->prepare("UPDATE " . MKT_DB::table('listings') . " SET status='paused',updated_at=%s,version=version+1 WHERE seller_id=%d AND status='active'", MKT_DB::now(), (int) $seller['id']));
                MKT_Audit::record('external_seller_suspended', 'seller', (string) $seller['public_id'], ['source_event_id' => (string) ($event['event_id'] ?? '')], 'success', '', 'identity_reconciliation');
                return true;
            }
            if ($type === 'PaymentStatusChanged.v1') {
                $deal_public_id = sanitize_text_field((string) ($payload['deal_public_id'] ?? ''));
                $status = sanitize_key((string) ($payload['status'] ?? ''));
                $allowed = ['declared','proof_submitted','verified_manual','provider_confirmed','failed','refunded'];
                if ($deal_public_id === '' || !in_array($status, $allowed, true)) {
                    return new WP_Error('mkt_invalid_payment_event', 'Payment event is invalid.');
                }
                $deal = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE public_id=%s', $deal_public_id), ARRAY_A);
                if (!$deal) {
                    return true;
                }
                $wpdb->update(MKT_DB::table('deals'), [
                    'payment_status' => $status,
                    'updated_at' => MKT_DB::now(),
                    'version' => (int) $deal['version'] + 1,
                ], ['id' => (int) $deal['id'], 'version' => (int) $deal['version']]);
                MKT_Audit::record('external_payment_status_changed', 'deal', $deal_public_id, ['status' => $status, 'source_event_id' => (string) ($event['event_id'] ?? '')], 'success', '', 'payment_reconciliation');
                return true;
            }
            if ($type === 'MessageConversationReported.v1') {
                $context_listing_id = sanitize_text_field((string) ($payload['marketplace_listing_public_id'] ?? ''));
                if ($context_listing_id === '') {
                    return true;
                }
                $public_id = MKT_DB::uuid();
                $wpdb->insert(MKT_DB::table('reports'), [
                    'public_id' => $public_id,
                    'reporter_user_id' => 0,
                    'target_type' => 'listing',
                    'target_public_id' => $context_listing_id,
                    'reason' => 'communication_report',
                    'details' => '[Purpose-limited File 17 report reference; message content not copied]',
                    'evidence_refs' => wp_json_encode([(string) ($event['event_id'] ?? '')]),
                    'status' => 'submitted',
                    'version' => 1,
                    'created_at' => MKT_DB::now(),
                    'updated_at' => MKT_DB::now(),
                ]);
                MKT_Audit::record('external_communication_report_received', 'report', $public_id, ['listing_public_id' => $context_listing_id], 'success', '', 'marketplace_safety');
                return true;
            }
            return true;
        });
    }

    public static function consume_external(string $source, array $event, callable $handler): bool|WP_Error {
        global $wpdb;
        $event_id = sanitize_text_field((string) ($event['event_id'] ?? ''));
        $event_type = sanitize_text_field((string) ($event['event_type'] ?? ''));
        if ($event_id === '' || $event_type === '') {
            return new WP_Error('mkt_invalid_event', __('Invalid event envelope.', 'marketplace'));
        }
        $payload_hash = hash('sha256', wp_json_encode($event));
        $inserted = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . MKT_DB::table('inbox') . ' (source,event_id,event_type,payload_hash,status,received_at) VALUES (%s,%s,%s,%s,%s,%s)',
            sanitize_key($source), $event_id, $event_type, $payload_hash, 'received', MKT_DB::now()
        ));
        if (!$inserted) {
            $existing = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . MKT_DB::table('inbox') . ' WHERE source=%s AND event_id=%s',
                sanitize_key($source), $event_id
            ), ARRAY_A);
            if (!$existing) {
                return new WP_Error('mkt_event_inbox_unavailable', __('The event inbox could not be read safely.', 'marketplace'));
            }
            if (!hash_equals((string) $existing['payload_hash'], $payload_hash)) {
                return new WP_Error('mkt_event_payload_conflict', __('The event ID was reused with a different payload.', 'marketplace'));
            }
            if ((string) $existing['status'] === 'processed') {
                return true;
            }
            if ((string) $existing['status'] !== 'failed') {
                return new WP_Error('mkt_event_in_progress', __('The event is already being processed.', 'marketplace'));
            }
            $reclaimed = $wpdb->query($wpdb->prepare(
                "UPDATE " . MKT_DB::table('inbox') . " SET status='received',received_at=%s WHERE id=%d AND status='failed'",
                MKT_DB::now(), (int) $existing['id']
            ));
            if ($reclaimed !== 1) {
                return new WP_Error('mkt_event_in_progress', __('The failed event is already being retried.', 'marketplace'));
            }
        }
        try {
            $result = $handler($event);
            if (is_wp_error($result)) {
                throw new RuntimeException($result->get_error_message());
            }
            $wpdb->update(MKT_DB::table('inbox'), ['status' => 'processed', 'processed_at' => MKT_DB::now()], ['source' => sanitize_key($source), 'event_id' => $event_id]);
            return true;
        } catch (Throwable $e) {
            $wpdb->update(MKT_DB::table('inbox'), ['status' => 'failed'], ['source' => sanitize_key($source), 'event_id' => $event_id]);
            return new WP_Error('mkt_event_processing_failed', __('The event could not be processed.', 'marketplace'));
        }
    }
}
