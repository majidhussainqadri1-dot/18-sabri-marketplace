<?php
defined('ABSPATH') || exit;

final class MKT_Moderation {
    public static function create_report(array $input, int $user_id): array|WP_Error {
        $auth = MKT_Auth::can('mkt_buy', ['action' => 'report']);
        if (is_wp_error($auth)) return $auth;

        $target_type = sanitize_key((string) ($input['target_type'] ?? 'listing'));
        $target_public_id = sanitize_text_field((string) ($input['target_public_id'] ?? ''));
        $reason = sanitize_key((string) ($input['reason'] ?? 'other'));
        if (!in_array($target_type, ['listing','seller','offer','deal'], true) || !wp_is_uuid($target_public_id)) {
            return new WP_Error('mkt_invalid_report', __('Invalid report target.', 'marketplace'), ['status' => 422]);
        }
        $target = self::validate_report_target($target_type, $target_public_id, $user_id);
        if (is_wp_error($target)) return $target;

        // Keep the canonical moderation API aligned with the complete Top-20/report
        // taxonomy exposed by the current REST completion layer and public UI.
        $allowed_reasons = ['illegal','harm','fraud','scam','counterfeit','unsafe','unsafe_claim','false_claim','false_cure_claim','privacy','harassment','abuse','impersonation','copyright','minor_safety','child_safety','non_delivery','misleading_price','other'];
        if (!in_array($reason, $allowed_reasons, true)) $reason = 'other';
        $details = wp_kses_post((string) ($input['details'] ?? ''));
        if (mb_strlen(wp_strip_all_tags($details)) < 10) {
            return new WP_Error('mkt_report_details_required', __('Please describe the concern clearly.', 'marketplace'), ['status' => 422]);
        }

        global $wpdb;
        $public_id = MKT_DB::uuid();
        $inserted = $wpdb->insert(MKT_DB::table('reports'), [
            'public_id' => $public_id,
            'reporter_user_id' => $user_id,
            'target_type' => $target_type,
            'target_public_id' => $target_public_id,
            'reason' => $reason,
            'details' => $details,
            'evidence_refs' => wp_json_encode(self::sanitize_evidence_refs((array) ($input['evidence_refs'] ?? []))),
            'status' => 'submitted',
            'version' => 1,
            'created_at' => MKT_DB::now(),
            'updated_at' => MKT_DB::now(),
        ]);
        if (!$inserted) {
            return new WP_Error('mkt_report_failed', __('The report could not be submitted.', 'marketplace'), ['status' => 500]);
        }
        MKT_Audit::record('report_submitted', 'report', $public_id, ['target_type' => $target_type, 'target_public_id' => $target_public_id, 'reason' => $reason], 'success', '', 'marketplace_safety');
        MKT_Events::enqueue('MarketplaceReportSubmitted.v1', 'report', $public_id, [
            'safe_summary' => __('A marketplace safety report was submitted.', 'marketplace'),
            'target_type' => $target_type,
            'target_public_id' => $target_public_id,
            'reason_taxonomy' => $reason,
        ], 'restricted');
        return self::report_dto($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('reports') . ' WHERE id=%d', $wpdb->insert_id), ARRAY_A));
    }

    private static function validate_report_target(string $target_type, string $public_id, int $user_id): array|WP_Error {
        global $wpdb;
        if ($target_type === 'listing') {
            $listing = MKT_Listings::get($public_id, false);
            return $listing ?: new WP_Error('mkt_report_target_not_found', __('The report target is not available.', 'marketplace'), ['status' => 404]);
        }
        if ($target_type === 'seller') {
            $seller = $wpdb->get_row($wpdb->prepare("SELECT public_id,user_id,status FROM " . MKT_DB::table('sellers') . " WHERE public_id=%s", $public_id), ARRAY_A);
            if (!$seller || ((string) $seller['status'] !== 'approved' && (int) $seller['user_id'] !== $user_id && !current_user_can('mkt_moderate'))) {
                return new WP_Error('mkt_report_target_not_found', __('The report target is not available.', 'marketplace'), ['status' => 404]);
            }
            return $seller;
        }
        $table = $target_type === 'offer' ? 'offers' : 'deals';
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table($table) . ' WHERE public_id=%s', $public_id), ARRAY_A);
        if (!$row || (!in_array($user_id, [(int) $row['buyer_user_id'], (int) $row['seller_user_id']], true) && !current_user_can('mkt_moderate'))) {
            return new WP_Error('mkt_report_target_not_found', __('The report target is not available.', 'marketplace'), ['status' => 404]);
        }
        return $row;
    }

    public static function transition_report(string $public_id, string $to, array $input, int $actor_id, int $expected_version): array|WP_Error {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('reports') . ' WHERE public_id=%s', $public_id), ARRAY_A);
        if (!$row) return new WP_Error('mkt_report_not_found', __('Report not found.', 'marketplace'), ['status' => 404]);
        if ((int) $row['version'] !== $expected_version) return new WP_Error('mkt_stale_version', __('The report changed. Reload and try again.', 'marketplace'), ['status' => 409]);

        $is_reporter_appeal = $to === 'appealed' && (int) $row['reporter_user_id'] === $actor_id;
        if ($is_reporter_appeal) {
            $auth = MKT_Auth::can('mkt_buy', ['action' => 'appeal_report', 'report_public_id' => $public_id]);
            if (is_wp_error($auth)) return $auth;
            if (!in_array((string) $row['status'], ['restricted','no_action','decided'], true)) {
                return new WP_Error('mkt_report_appeal_unavailable', __('This report is not currently eligible for appeal.', 'marketplace'), ['status' => 409]);
            }
        } else {
            $auth = MKT_Auth::can('mkt_moderate', ['action' => 'transition_report']);
            if (is_wp_error($auth)) return $auth;
        }
        $transition = MKT_State_Machines::assert('report', (string) $row['status'], $to);
        if (is_wp_error($transition)) return $transition;

        $changes = [
            'status' => $to,
            'assigned_to' => $is_reporter_appeal ? (int) $row['assigned_to'] : $actor_id,
            'decision_code' => $is_reporter_appeal ? (string) $row['decision_code'] : sanitize_key((string) ($input['decision_code'] ?? '')),
            'decision_note' => $is_reporter_appeal
                ? wp_kses_post((string) ($input['appeal_statement'] ?? $input['decision_note'] ?? ''))
                : wp_kses_post((string) ($input['decision_note'] ?? '')),
            'updated_at' => MKT_DB::now(),
            'version' => $expected_version + 1,
        ];
        if (in_array($to, ['decided','no_action','restricted'], true)) $changes['decided_at'] = MKT_DB::now();
        if ($to === 'closed') $changes['closed_at'] = MKT_DB::now();
        $updated = $wpdb->update(MKT_DB::table('reports'), $changes, ['id' => (int) $row['id'], 'version' => $expected_version, 'status' => (string) $row['status']]);
        if (!$updated) return new WP_Error('mkt_stale_version', __('The report changed. Reload and try again.', 'marketplace'), ['status' => 409]);

        if ($to === 'restricted' && $row['target_type'] === 'listing') {
            $listing = MKT_Listings::get((string) $row['target_public_id'], true);
            if ($listing && MKT_State_Machines::can('listing', (string) $listing['status'], 'removed')) {
                $listing_result = MKT_Listings::transition((string) $listing['public_id'], 'removed', $actor_id, (int) $listing['version'], (string) ($input['decision_code'] ?? 'safety_report'), (string) ($input['decision_note'] ?? ''));
                if (is_wp_error($listing_result)) {
                    // Official REST/admin entry points wrap this transition in the owner
                    // transaction; surfacing the error makes the report + listing change
                    // roll back together instead of committing a false restricted state.
                    return $listing_result;
                }
            }
        }
        MKT_Audit::record($is_reporter_appeal ? 'report_appealed' : 'report_transitioned', 'report', $public_id, ['from' => $row['status'], 'to' => $to, 'decision_code' => $changes['decision_code']], 'success', '', 'marketplace_safety');
        MKT_Events::enqueue('MarketplaceReportStatusChanged.v1', 'report', $public_id, [
            'from' => $row['status'], 'to' => $to, 'notify_user_ids' => [(int) $row['reporter_user_id']],
            'safe_summary' => sprintf(__('Marketplace report status changed to %s.', 'marketplace'), $to),
            'url' => home_url('/marketplace/dashboard/?tab=reports'),
        ], 'restricted');
        return self::report_dto($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('reports') . ' WHERE id=%d', (int) $row['id']), ARRAY_A));
    }

    public static function open_dispute(string $deal_public_id, array $input, int $user_id): array|WP_Error {
        $deal = MKT_Commerce::get_deal($deal_public_id, $user_id);
        if (is_wp_error($deal)) return $deal;
        $reason = sanitize_key((string) ($input['reason'] ?? 'other'));
        $statement = wp_kses_post((string) ($input['statement'] ?? ''));
        if (mb_strlen(wp_strip_all_tags($statement)) < 20) {
            return new WP_Error('mkt_dispute_statement_required', __('Please provide a clear dispute statement.', 'marketplace'), ['status' => 422]);
        }
        global $wpdb;
        try {
            return MKT_DB::transaction(function() use ($wpdb, $deal_public_id, $reason, $statement, $input, $user_id) {
                $deal_row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE public_id=%s FOR UPDATE', $deal_public_id), ARRAY_A);
                if (!$deal_row || !MKT_Auth::deal_participant($deal_row, $user_id)) {
                    return new WP_Error('mkt_deal_not_found', __('Deal not found.', 'marketplace'), ['status' => 404]);
                }
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . MKT_DB::table('disputes') . " WHERE deal_id=%d AND status NOT IN ('closed','withdrawn') ORDER BY id DESC LIMIT 1", (int) $deal_row['id']), ARRAY_A);
                if ($existing) return self::dispute_dto($existing);

                $public_id = MKT_DB::uuid();
                $inserted = $wpdb->insert(MKT_DB::table('disputes'), [
                    'public_id' => $public_id,
                    'deal_id' => (int) $deal_row['id'],
                    'opened_by' => $user_id,
                    'reason' => $reason,
                    'statement' => $statement,
                    'evidence_refs' => wp_json_encode(self::sanitize_evidence_refs((array) ($input['evidence_refs'] ?? []))),
                    'status' => 'opened',
                    'access_grants' => wp_json_encode([]),
                    'version' => 1,
                    'created_at' => MKT_DB::now(),
                    'updated_at' => MKT_DB::now(),
                ]);
                if (!$inserted) return new WP_Error('mkt_dispute_failed', __('The dispute could not be opened.', 'marketplace'), ['status' => 500]);
                $deal_transition = MKT_Commerce::transition_deal($deal_public_id, 'disputed', ['_dispute_record_public_id' => $public_id], $user_id, (int) $deal_row['version']);
                if (is_wp_error($deal_transition)) return $deal_transition;

                MKT_Audit::record('dispute_opened', 'dispute', $public_id, ['deal_public_id' => $deal_public_id, 'reason' => $reason], 'success', '', 'deal_dispute');
                MKT_Events::enqueue('MarketplaceDealDisputed.v1', 'dispute', $public_id, [
                    'deal_public_id' => $deal_public_id,
                    'notify_user_ids' => [(int) $deal_row['buyer_user_id'], (int) $deal_row['seller_user_id']],
                    'safe_summary' => __('A deal dispute was opened.', 'marketplace'),
                    'url' => home_url('/marketplace/deal/' . $deal_public_id . '/'),
                ], 'restricted');
                return self::dispute_dto($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('disputes') . ' WHERE public_id=%s', $public_id), ARRAY_A));
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_dispute_failed', __('The dispute could not be opened.', 'marketplace'), ['status' => 500, 'trace_id' => MKT_Audit::trace_id()]);
        }
    }

    public static function transition_dispute(string $public_id, string $to, array $input, int $actor_id, int $expected_version): array|WP_Error {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT d.*,x.buyer_user_id,x.seller_user_id,x.public_id AS deal_public_id FROM ' . MKT_DB::table('disputes') . ' d JOIN ' . MKT_DB::table('deals') . ' x ON x.id=d.deal_id WHERE d.public_id=%s', $public_id), ARRAY_A);
        if (!$row) return new WP_Error('mkt_dispute_not_found', __('Dispute not found.', 'marketplace'), ['status' => 404]);
        if ((int) $row['version'] !== $expected_version) return new WP_Error('mkt_stale_version', __('The dispute changed. Reload and try again.', 'marketplace'), ['status' => 409]);

        $participant = in_array($actor_id, [(int) $row['buyer_user_id'], (int) $row['seller_user_id']], true);
        $participant_action = $participant && (($to === 'appealed' && (string) $row['status'] === 'decided') || ($to === 'withdrawn' && in_array((string) $row['status'], ['opened','evidence'], true)) || ($to === 'evidence' && (string) $row['status'] === 'more_info'));
        if ($participant_action) {
            $capability = $actor_id === (int) $row['seller_user_id'] ? 'mkt_sell' : 'mkt_buy';
            $auth = MKT_Auth::can($capability, ['action' => 'participant_dispute_transition', 'dispute_public_id' => $public_id]);
            if (is_wp_error($auth)) return $auth;
        } else {
            $auth = MKT_Auth::can('mkt_review_disputes', ['action' => 'transition_dispute']);
            if (is_wp_error($auth)) return $auth;
        }
        $transition = MKT_State_Machines::assert('dispute', (string) $row['status'], $to);
        if (is_wp_error($transition)) return $transition;

        $changes = [
            'status' => $to,
            'assigned_to' => $participant_action ? (int) $row['assigned_to'] : $actor_id,
            'decision_code' => $participant_action ? (string) $row['decision_code'] : sanitize_key((string) ($input['decision_code'] ?? '')),
            'decision_note' => wp_kses_post((string) ($input[$to === 'appealed' ? 'appeal_statement' : 'decision_note'] ?? $input['decision_note'] ?? '')),
            'evidence_refs' => isset($input['evidence_refs']) ? wp_json_encode(self::sanitize_evidence_refs((array) $input['evidence_refs'])) : (string) $row['evidence_refs'],
            'updated_at' => MKT_DB::now(),
            'version' => $expected_version + 1,
        ];
        if ($to === 'decided') $changes['decided_at'] = MKT_DB::now();
        if ($to === 'closed') $changes['closed_at'] = MKT_DB::now();
        $updated = $wpdb->update(MKT_DB::table('disputes'), $changes, ['id' => (int) $row['id'], 'version' => $expected_version, 'status' => (string) $row['status']]);
        if (!$updated) return new WP_Error('mkt_stale_version', __('The dispute changed. Reload and try again.', 'marketplace'), ['status' => 409]);

        MKT_Audit::record($to === 'appealed' ? 'dispute_appealed' : 'dispute_transitioned', 'dispute', $public_id, ['from' => $row['status'], 'to' => $to, 'decision_code' => $changes['decision_code']], 'success', '', 'deal_dispute');
        MKT_Events::enqueue('MarketplaceDisputeStatusChanged.v1', 'dispute', $public_id, [
            'deal_public_id' => (string) $row['deal_public_id'], 'from' => $row['status'], 'to' => $to,
            'notify_user_ids' => [(int) $row['buyer_user_id'], (int) $row['seller_user_id']],
            'safe_summary' => sprintf(__('Deal dispute status changed to %s.', 'marketplace'), $to),
            'url' => home_url('/marketplace/deal/' . (string) $row['deal_public_id'] . '/'),
        ], 'restricted');
        return self::dispute_dto($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('disputes') . ' WHERE id=%d', (int) $row['id']), ARRAY_A));
    }

    private static function sanitize_evidence_refs(array $refs): array {
        return array_slice(array_values(array_unique(array_filter(array_map(static function($value): string {
            $value = sanitize_text_field((string) $value);
            return strlen($value) <= 190 ? $value : '';
        }, $refs)))), 0, 20);
    }

    public static function report_dto(array $row): array {
        return [
            'public_id' => (string) $row['public_id'],
            'reporter_user_id' => (int) $row['reporter_user_id'],
            'target_type' => (string) $row['target_type'],
            'target_public_id' => (string) $row['target_public_id'],
            'reason' => (string) $row['reason'],
            'status' => (string) $row['status'],
            'decision_code' => (string) $row['decision_code'],
            'version' => (int) $row['version'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    public static function dispute_dto(array $row): array {
        return [
            'public_id' => (string) $row['public_id'],
            'deal_id' => (int) $row['deal_id'],
            'opened_by' => (int) $row['opened_by'],
            'reason' => (string) $row['reason'],
            'statement' => wp_kses_post((string) $row['statement']),
            'status' => (string) $row['status'],
            'decision_code' => (string) $row['decision_code'],
            'decision_note' => wp_kses_post((string) $row['decision_note']),
            'version' => (int) $row['version'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}