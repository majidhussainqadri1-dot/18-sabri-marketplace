<?php
defined('ABSPATH') || exit;

final class MKT_Commerce {
    public static function create_offer(string $listing_public_id, array $input, int $buyer_id, string $idempotency_key): array|WP_Error {
        $auth = MKT_Auth::can('mkt_buy', ['action' => 'create_offer']);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $settings = MKT_DB::settings();
        if (!empty($settings['safe_mode']) || empty($settings['offers_enabled'])) {
            return new WP_Error('mkt_offers_paused', __('Offers are temporarily paused.', 'marketplace'), ['status' => 503]);
        }
        $listing = MKT_Listings::get($listing_public_id, false);
        if (!$listing || $listing['status'] !== 'active' || $listing['availability'] === 'unavailable') {
            return new WP_Error('mkt_listing_unavailable', __('The listing is not available for offers.', 'marketplace'), ['status' => 409]);
        }
        $seller_id = (int) $listing['seller_user_id'];
        if ($seller_id === $buyer_id) {
            return new WP_Error('mkt_self_offer', __('You cannot offer on your own listing.', 'marketplace'), ['status' => 422]);
        }
        $amount = round((float) ($input['amount'] ?? 0), 2);
        $currency = MKT_DB::currency((string) ($input['currency'] ?? $listing['currency']));
        if ($amount <= 0 || $currency !== $listing['currency']) {
            return new WP_Error('mkt_invalid_offer', __('Offer amount and currency are invalid.', 'marketplace'), ['status' => 422]);
        }
        $idempotency_key = self::idempotency_key($idempotency_key);
        if (is_wp_error($idempotency_key)) {
            return $idempotency_key;
        }
        $expires_hours = min(168, max(1, (int) ($input['expires_hours'] ?? 48)));
        global $wpdb;
        try {
            return MKT_DB::transaction(function() use ($wpdb, $listing, $buyer_id, $amount, $currency, $input, $idempotency_key, $expires_hours) {
                $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('offers') . ' WHERE idempotency_key=%s', $idempotency_key), ARRAY_A);
                if ($existing) {
                    if ((int) $existing['buyer_user_id'] !== $buyer_id) {
                        return new WP_Error('mkt_idempotency_conflict', __('The idempotency key belongs to another request.', 'marketplace'), ['status' => 409]);
                    }
                    return self::offer_dto($existing);
                }

                // The public pre-check is only an early rejection. Re-lock and revalidate
                // the canonical listing/seller state inside the transaction so a concurrent
                // pause, removal, recall or seller suspension cannot create a stale offer.
                $fresh_listing = $wpdb->get_row($wpdb->prepare(
                    'SELECT l.*,s.user_id AS seller_user_id,s.status AS seller_status FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id WHERE l.id=%d FOR UPDATE',
                    (int) $listing['id']
                ), ARRAY_A);
                if (!$fresh_listing || (string) $fresh_listing['status'] !== 'active' || (string) $fresh_listing['availability'] === 'unavailable' || (string) $fresh_listing['seller_status'] !== 'approved') {
                    return new WP_Error('mkt_listing_unavailable', __('The listing is no longer available for offers.', 'marketplace'), ['status' => 409]);
                }
                $fresh_seller_id = (int) $fresh_listing['seller_user_id'];
                if ($fresh_seller_id === $buyer_id) {
                    return new WP_Error('mkt_self_offer', __('You cannot offer on your own listing.', 'marketplace'), ['status' => 422]);
                }
                $seller_eligibility = MKT_Auth::seller_eligibility($fresh_seller_id);
                if (empty($seller_eligibility['eligible'])) {
                    return new WP_Error('mkt_listing_unavailable', __('The seller is no longer eligible to receive offers.', 'marketplace'), ['status' => 409]);
                }
                if ($currency !== (string) $fresh_listing['currency']) {
                    return new WP_Error('mkt_invalid_offer', __('The listing currency changed. Reload before making an offer.', 'marketplace'), ['status' => 409]);
                }

                $public_id = MKT_DB::uuid();
                $now = MKT_DB::now();
                $inserted = $wpdb->insert(MKT_DB::table('offers'), [
                    'public_id' => $public_id,
                    'listing_id' => (int) $fresh_listing['id'],
                    'buyer_user_id' => $buyer_id,
                    'seller_user_id' => $fresh_seller_id,
                    'created_by' => $buyer_id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'terms' => wp_kses_post((string) ($input['terms'] ?? '')),
                    'status' => 'open',
                    'idempotency_key' => $idempotency_key,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + $expires_hours * HOUR_IN_SECONDS),
                ]);
                if (!$inserted) {
                    return new WP_Error('mkt_offer_failed', __('The offer could not be created.', 'marketplace'), ['status' => 409]);
                }
                $offer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('offers') . ' WHERE id=%d', $wpdb->insert_id), ARRAY_A);
                MKT_Audit::record('offer_created', 'offer', $public_id, ['listing_public_id' => $fresh_listing['public_id'], 'amount' => $amount, 'currency' => $currency]);
                MKT_Events::enqueue('MarketplaceOfferCreated.v1', 'offer', $public_id, [
                    'listing_public_id' => $fresh_listing['public_id'],
                    'notify_user_ids' => [$fresh_seller_id],
                    'safe_summary' => __('A buyer submitted an offer.', 'marketplace'),
                    'url' => home_url('/marketplace/dashboard/?tab=offers'),
                ], 'participants');
                return self::offer_dto($offer);
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_offer_failed', __('The offer could not be created.', 'marketplace'), ['status' => 500, 'trace_id' => MKT_Audit::trace_id()]);
        }
    }

    public static function transition_offer(string $public_id, string $to, array $input, int $actor_id, int $expected_version, string $idempotency_key): array|WP_Error {
        global $wpdb;
        $offer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('offers') . ' WHERE public_id=%s', $public_id), ARRAY_A);
        if (!$offer) {
            return new WP_Error('mkt_offer_not_found', __('Offer not found.', 'marketplace'), ['status' => 404]);
        }
        if (!in_array($actor_id, [(int) $offer['buyer_user_id'], (int) $offer['seller_user_id']], true)) {
            return new WP_Error('mkt_offer_forbidden', __('You cannot update this offer.', 'marketplace'), ['status' => 403]);
        }
        $participant_capability = $actor_id === (int) $offer['seller_user_id'] ? 'mkt_sell' : 'mkt_buy';
        $auth = MKT_Auth::can($participant_capability, ['action' => 'transition_offer', 'offer_public_id' => $public_id]);
        if (is_wp_error($auth)) return $auth;
        if ((int) $offer['version'] !== $expected_version) {
            return new WP_Error('mkt_stale_version', __('The offer changed. Reload and try again.', 'marketplace'), ['status' => 409]);
        }
        if (strtotime((string) $offer['expires_at']) <= time() && !in_array($offer['status'], ['accepted','declined','withdrawn','expired'], true)) {
            $to = 'expired';
        }
        $transition = MKT_State_Machines::assert('offer', (string) $offer['status'], $to);
        if (is_wp_error($transition)) {
            return $transition;
        }
        $creator_id = (int) $offer['created_by'];
        if (in_array($to, ['accepted','declined'], true) && $actor_id === $creator_id) {
            return new WP_Error('mkt_offer_actor_invalid', __('Only the recipient of the current offer can accept or decline it.', 'marketplace'), ['status' => 403]);
        }
        if ($to === 'withdrawn' && $actor_id !== $creator_id) {
            return new WP_Error('mkt_offer_actor_invalid', __('Only the creator of the current offer can withdraw it.', 'marketplace'), ['status' => 403]);
        }
        $idempotency_key = self::idempotency_key($idempotency_key);
        if (is_wp_error($idempotency_key)) {
            return $idempotency_key;
        }
        try {
            return MKT_DB::transaction(function() use ($wpdb, $offer, $public_id, $to, $input, $actor_id, $expected_version, $idempotency_key) {
                if ($to === 'countered') {
                    $idempotent = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('offers') . ' WHERE idempotency_key=%s', $idempotency_key), ARRAY_A);
                    if ($idempotent) {
                        if ((int) $idempotent['created_by'] !== $actor_id || (int) $idempotent['parent_offer_id'] !== (int) $offer['id']) {
                            return new WP_Error('mkt_idempotency_conflict', __('The idempotency key belongs to another counter-offer.', 'marketplace'), ['status' => 409]);
                        }
                        return self::offer_dto($idempotent);
                    }

                    // Counter-offers are new commerce commitments. Re-lock the listing
                    // and revalidate seller eligibility rather than trusting the stale
                    // parent-offer snapshot.
                    $fresh_listing = $wpdb->get_row($wpdb->prepare(
                        'SELECT l.*,s.user_id AS seller_user_id,s.status AS seller_status FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id WHERE l.id=%d FOR UPDATE',
                        (int) $offer['listing_id']
                    ), ARRAY_A);
                    if (!$fresh_listing || (string) $fresh_listing['status'] !== 'active' || (string) $fresh_listing['availability'] === 'unavailable' || (string) $fresh_listing['seller_status'] !== 'approved') {
                        return new WP_Error('mkt_listing_unavailable', __('The listing is no longer available for a counter-offer.', 'marketplace'), ['status' => 409]);
                    }
                    $seller_eligibility = MKT_Auth::seller_eligibility((int) $fresh_listing['seller_user_id']);
                    if (empty($seller_eligibility['eligible'])) {
                        return new WP_Error('mkt_listing_unavailable', __('The seller is no longer eligible to receive a counter-offer.', 'marketplace'), ['status' => 409]);
                    }
                    if ((string) $fresh_listing['currency'] !== (string) $offer['currency']) {
                        return new WP_Error('mkt_invalid_counter', __('The listing currency changed. Reload before countering.', 'marketplace'), ['status' => 409]);
                    }

                    $amount = round((float) ($input['amount'] ?? 0), 2);
                    if ($amount <= 0) {
                        return new WP_Error('mkt_invalid_counter', __('Counter-offer amount is invalid.', 'marketplace'), ['status' => 422]);
                    }
                    $root = (int) ($offer['root_offer_id'] ?: $offer['id']);
                    $new_public_id = MKT_DB::uuid();
                    $inserted_counter = $wpdb->insert(MKT_DB::table('offers'), [
                        'public_id' => $new_public_id,
                        'root_offer_id' => $root,
                        'parent_offer_id' => (int) $offer['id'],
                        'listing_id' => (int) $offer['listing_id'],
                        'buyer_user_id' => (int) $offer['buyer_user_id'],
                        'seller_user_id' => (int) $offer['seller_user_id'],
                        'created_by' => $actor_id,
                        'amount' => $amount,
                        'currency' => (string) $offer['currency'],
                        'terms' => wp_kses_post((string) ($input['terms'] ?? '')),
                        'status' => 'open',
                        'idempotency_key' => $idempotency_key,
                        'version' => 1,
                        'created_at' => MKT_DB::now(),
                        'updated_at' => MKT_DB::now(),
                        'expires_at' => gmdate('Y-m-d H:i:s', time() + min(168, max(1, (int) ($input['expires_hours'] ?? 48))) * HOUR_IN_SECONDS),
                    ]);
                    if (!$inserted_counter) {
                        return new WP_Error('mkt_counter_create_failed', __('The counter-offer could not be created.', 'marketplace'), ['status' => 409]);
                    }
                    $new_offer_id = (int) $wpdb->insert_id;
                    $updated_parent = $wpdb->update(MKT_DB::table('offers'), ['status' => 'countered', 'updated_at' => MKT_DB::now(), 'responded_at' => MKT_DB::now(), 'version' => $expected_version + 1], ['id' => (int) $offer['id'], 'version' => $expected_version, 'status' => (string) $offer['status']]);
                    if (!$updated_parent) {
                        return new WP_Error('mkt_stale_version', __('The offer changed. Reload and try again.', 'marketplace'), ['status' => 409]);
                    }
                    $new = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('offers') . ' WHERE id=%d', $new_offer_id), ARRAY_A);
                    MKT_Audit::record('offer_countered', 'offer', $public_id, ['new_offer_public_id' => $new_public_id]);
                    MKT_Events::enqueue('MarketplaceOfferCountered.v1', 'offer', $new_public_id, [
                        'notify_user_ids' => [$actor_id === (int) $offer['buyer_user_id'] ? (int) $offer['seller_user_id'] : (int) $offer['buyer_user_id']],
                        'safe_summary' => __('A counter-offer was submitted.', 'marketplace'),
                        'url' => home_url('/marketplace/dashboard/?tab=offers'),
                    ], 'participants');
                    return self::offer_dto($new);
                }

                $updated = $wpdb->update(MKT_DB::table('offers'), [
                    'status' => $to,
                    'updated_at' => MKT_DB::now(),
                    'responded_at' => MKT_DB::now(),
                    'version' => $expected_version + 1,
                ], ['id' => (int) $offer['id'], 'version' => $expected_version, 'status' => (string) $offer['status']]);
                if (!$updated) {
                    return new WP_Error('mkt_stale_version', __('The offer changed. Reload and try again.', 'marketplace'), ['status' => 409]);
                }
                $deal = null;
                if ($to === 'accepted') {
                    $deal = self::create_deal_from_offer($offer, $actor_id);
                    if (is_wp_error($deal)) {
                        return $deal;
                    }
                    $root = (int) ($offer['root_offer_id'] ?: $offer['id']);
                    $listing_status = (string) $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . MKT_DB::table('listings') . ' WHERE id=%d', (int) $offer['listing_id']));
                    if ($listing_status === 'sold_unavailable') {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE " . MKT_DB::table('offers') . " SET status='declined',updated_at=%s,version=version+1 WHERE listing_id=%d AND id<>%d AND status IN ('open','countered')",
                            MKT_DB::now(), (int) $offer['listing_id'], (int) $offer['id']
                        ));
                    } else {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE " . MKT_DB::table('offers') . " SET status='declined',updated_at=%s,version=version+1 WHERE listing_id=%d AND id<>%d AND status IN ('open','countered') AND (id=%d OR root_offer_id=%d)",
                            MKT_DB::now(), (int) $offer['listing_id'], (int) $offer['id'], $root, $root
                        ));
                    }
                }
                MKT_Audit::record('offer_transitioned', 'offer', $public_id, ['from' => $offer['status'], 'to' => $to]);
                MKT_Events::enqueue($to === 'accepted' ? 'MarketplaceOfferAccepted.v1' : 'MarketplaceOfferStatusChanged.v1', 'offer', $public_id, [
                    'from' => $offer['status'],
                    'to' => $to,
                    'notify_user_ids' => [$actor_id === (int) $offer['buyer_user_id'] ? (int) $offer['seller_user_id'] : (int) $offer['buyer_user_id']],
                    'safe_summary' => sprintf(__('Offer status changed to %s.', 'marketplace'), $to),
                    'url' => $deal && !is_wp_error($deal) ? home_url('/marketplace/deal/' . $deal['public_id'] . '/') : home_url('/marketplace/dashboard/?tab=offers'),
                ], 'participants');
                $fresh = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('offers') . ' WHERE id=%d', (int) $offer['id']), ARRAY_A);
                return ['offer' => self::offer_dto($fresh), 'deal' => $deal];
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_offer_transition_failed', __('The offer could not be updated.', 'marketplace'), ['status' => 500, 'trace_id' => MKT_Audit::trace_id()]);
        }
    }

    private static function create_deal_from_offer(array $offer, int $actor_id): array|WP_Error {
        global $wpdb;
        $listing = $wpdb->get_row($wpdb->prepare(
            'SELECT l.*,s.public_id AS seller_public_id,s.user_id AS seller_user_id,s.store_name,s.status AS seller_status FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id WHERE l.id=%d FOR UPDATE',
            (int) $offer['listing_id']
        ), ARRAY_A);
        if (!$listing || $listing['status'] !== 'active' || $listing['seller_status'] !== 'approved') {
            return new WP_Error('mkt_listing_unavailable', __('The listing is no longer available.', 'marketplace'), ['status' => 409]);
        }
        $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE accepted_offer_id=%d', (int) $offer['id']), ARRAY_A);
        if ($existing) {
            return self::deal_dto($existing);
        }
        if ((float) $listing['quantity'] <= 1) {
            $other_deal = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . MKT_DB::table('deals') . " WHERE listing_id=%d AND status NOT IN ('cancelled','closed')",
                (int) $listing['id']
            ));
            if ($other_deal > 0) {
                return new WP_Error('mkt_listing_already_committed', __('Another offer has already committed this listing.', 'marketplace'), ['status' => 409]);
            }
        }
        $public_id = MKT_DB::uuid();
        $snapshot = [
            'listing_public_id' => $listing['public_id'],
            'title' => $listing['title'],
            'seller_public_id' => $listing['seller_public_id'],
            'amount' => (string) $offer['amount'],
            'currency' => (string) $offer['currency'],
            'terms' => wp_strip_all_tags((string) $offer['terms']),
            'accepted_at' => MKT_DB::now(),
            'commission_percent' => 0,
            'platform_escrow' => false,
        ];
        $inserted = $wpdb->insert(MKT_DB::table('deals'), [
            'public_id' => $public_id,
            'listing_id' => (int) $offer['listing_id'],
            'accepted_offer_id' => (int) $offer['id'],
            'buyer_user_id' => (int) $offer['buyer_user_id'],
            'seller_user_id' => (int) $offer['seller_user_id'],
            'agreed_amount' => (float) $offer['amount'],
            'currency' => (string) $offer['currency'],
            'agreed_snapshot' => wp_json_encode($snapshot),
            'payment_mode' => 'direct',
            'payment_status' => 'declared',
            'delivery_status' => 'arranging',
            'status' => 'accepted',
            'version' => 1,
            'created_at' => MKT_DB::now(),
            'updated_at' => MKT_DB::now(),
        ]);
        if (!$inserted) {
            return new WP_Error('mkt_deal_create_failed', __('The deal could not be created.', 'marketplace'), ['status' => 500]);
        }
        if ((float) $listing['quantity'] <= 1) {
            $listing_updated = $wpdb->update(MKT_DB::table('listings'), [
                'quantity' => 0,
                'availability' => 'unavailable',
                'status' => 'sold_unavailable',
                'updated_at' => MKT_DB::now(),
                'version' => (int) $listing['version'] + 1,
            ], ['id' => (int) $listing['id'], 'version' => (int) $listing['version'], 'status' => 'active']);
        } else {
            $listing_updated = $wpdb->update(MKT_DB::table('listings'), [
                'quantity' => max(0, (float) $listing['quantity'] - 1),
                'updated_at' => MKT_DB::now(),
                'version' => (int) $listing['version'] + 1,
            ], ['id' => (int) $listing['id'], 'version' => (int) $listing['version'], 'status' => 'active']);
        }
        if (!$listing_updated) {
            return new WP_Error('mkt_listing_commit_conflict', __('The listing changed while the offer was being accepted.', 'marketplace'), ['status' => 409]);
        }
        MKT_Audit::record('deal_created', 'deal', $public_id, ['offer_public_id' => $offer['public_id'], 'commission_percent' => 0]);
        MKT_Events::enqueue('MarketplaceDealAccepted.v1', 'deal', $public_id, [
            'listing_public_id' => $listing['public_id'],
            'notify_user_ids' => [(int) $offer['buyer_user_id'], (int) $offer['seller_user_id']],
            'safe_summary' => __('A marketplace deal was accepted.', 'marketplace'),
            'url' => home_url('/marketplace/deal/' . $public_id . '/'),
        ], 'participants');
        return self::deal_dto($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE id=%d', $wpdb->insert_id), ARRAY_A));
    }

    public static function transition_deal(string $public_id, string $to, array $input, int $actor_id, int $expected_version): array|WP_Error {
        if (empty(MKT_DB::settings()['deal_transitions_enabled'])) {
            return new WP_Error('mkt_deals_paused', __('Deal updates are temporarily paused.', 'marketplace'), ['status' => 503]);
        }
        global $wpdb;
        $deal = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE public_id=%s', $public_id), ARRAY_A);
        if (!$deal) {
            return new WP_Error('mkt_deal_not_found', __('Deal not found.', 'marketplace'), ['status' => 404]);
        }
        $participant = MKT_Auth::deal_participant($deal, $actor_id);
        $reviewer = false;
        if (current_user_can('mkt_review_disputes')) {
            $reviewer_auth = MKT_Auth::can('mkt_review_disputes', ['action' => 'review_deal', 'deal_public_id' => $public_id]);
            $reviewer = !is_wp_error($reviewer_auth);
        }
        if (!$participant && !$reviewer) {
            return new WP_Error('mkt_deal_forbidden', __('You cannot update this deal.', 'marketplace'), ['status' => 403]);
        }
        if (!$participant && $reviewer && !in_array((string) $deal['status'], ['disputed','resolved'], true)) {
            return new WP_Error('mkt_deal_reviewer_scope', __('A dispute reviewer may change only disputed or resolved deals.', 'marketplace'), ['status' => 403]);
        }
        if ($participant && !in_array($to, ['disputed','cancelled'], true)) {
            $capability = $actor_id === (int) $deal['seller_user_id'] ? 'mkt_sell' : 'mkt_buy';
            $auth = MKT_Auth::can($capability, ['action' => 'transition_deal', 'deal_public_id' => $public_id, 'to' => $to]);
            if (is_wp_error($auth)) return $auth;
        }
        if ((int) $deal['version'] !== $expected_version) {
            return new WP_Error('mkt_stale_version', __('The deal changed. Reload and try again.', 'marketplace'), ['status' => 409]);
        }
        $transition = MKT_State_Machines::assert('deal', (string) $deal['status'], $to);
        if (is_wp_error($transition)) return $transition;
        if ($to === 'resolved' && !$reviewer) {
            return new WP_Error('mkt_deal_reviewer_required', __('A dispute reviewer must resolve a disputed deal.', 'marketplace'), ['status' => 403]);
        }
        if ($to === 'closed') {
            $participant_close = $participant && in_array((string) $deal['status'], ['completed','cancelled'], true);
            $reviewer_close = $reviewer && (string) $deal['status'] === 'resolved';
            if (!$participant_close && !$reviewer_close) {
                return new WP_Error('mkt_deal_close_forbidden', __('This deal is not ready to be closed by the current actor.', 'marketplace'), ['status' => 403]);
            }
        }
        if ($to === 'disputed') {
            $dispute_id = sanitize_text_field((string) ($input['_dispute_record_public_id'] ?? ''));
            $valid_dispute = $dispute_id !== '' && (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . MKT_DB::table('disputes') . ' WHERE public_id=%s AND deal_id=%d',
                $dispute_id, (int) $deal['id']
            )) === 1;
            if (!$valid_dispute) {
                return new WP_Error('mkt_dispute_record_required', __('Open a structured dispute before marking the deal disputed.', 'marketplace'), ['status' => 409]);
            }
        }

        $effective_to = $to;
        $event_type = $to === 'disputed' ? 'MarketplaceDealDisputed.v1' : 'MarketplaceDealStatusChanged.v1';
        $changes = ['updated_at' => MKT_DB::now(), 'version' => $expected_version + 1];

        if ($to === 'completed' && $participant) {
            $field = $actor_id === (int) $deal['buyer_user_id'] ? 'buyer_completion_confirmed_at' : 'seller_completion_confirmed_at';
            $other_field = $field === 'buyer_completion_confirmed_at' ? 'seller_completion_confirmed_at' : 'buyer_completion_confirmed_at';
            $changes[$field] = MKT_DB::now();
            if (empty($deal[$other_field])) {
                $effective_to = (string) $deal['status'];
                $event_type = 'MarketplaceDealCompletionConfirmed.v1';
            } else {
                $changes['completed_at'] = MKT_DB::now();
                $changes['delivery_status'] = 'completed';
            }
        }
        $changes['status'] = $effective_to;
        if ($effective_to === 'closed') $changes['closed_at'] = MKT_DB::now();

        if (isset($input['payment_status'])) {
            $requested_payment = sanitize_key((string) $input['payment_status']);
            $participant_allowed = ['declared','proof_submitted'];
            $reviewer_allowed = ['declared','proof_submitted','verified_manual','failed','refunded'];
            $allowed = $reviewer ? $reviewer_allowed : $participant_allowed;
            if (!in_array($requested_payment, $allowed, true)) {
                return new WP_Error('mkt_payment_status_forbidden', __('This payment status requires an authorized reviewer or verified provider event.', 'marketplace'), ['status' => 403]);
            }
            $changes['payment_status'] = $requested_payment;
        }

        $updated = $wpdb->update(MKT_DB::table('deals'), $changes, ['id' => (int) $deal['id'], 'version' => $expected_version, 'status' => (string) $deal['status']]);
        if (!$updated) {
            return new WP_Error('mkt_stale_version', __('The deal changed. Reload and try again.', 'marketplace'), ['status' => 409]);
        }
        MKT_Audit::record('deal_transitioned', 'deal', $public_id, ['from' => $deal['status'], 'requested_to' => $to, 'effective_to' => $effective_to]);
        MKT_Events::enqueue($event_type, 'deal', $public_id, [
            'from' => $deal['status'],
            'to' => $effective_to,
            'requested_to' => $to,
            'notify_user_ids' => [(int) $deal['buyer_user_id'], (int) $deal['seller_user_id']],
            'safe_summary' => $event_type === 'MarketplaceDealCompletionConfirmed.v1' ? __('One participant confirmed completion.', 'marketplace') : sprintf(__('Deal status changed to %s.', 'marketplace'), $effective_to),
            'url' => home_url('/marketplace/deal/' . $public_id . '/'),
        ], 'participants');
        $fresh = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE id=%d', (int) $deal['id']), ARRAY_A);
        return self::deal_dto($fresh);
    }

    public static function dashboard(int $user_id): array {
        global $wpdb;
        $offers = $wpdb->get_results($wpdb->prepare(
            'SELECT o.*,l.public_id AS listing_public_id,l.title AS listing_title FROM ' . MKT_DB::table('offers') . ' o JOIN ' . MKT_DB::table('listings') . ' l ON l.id=o.listing_id WHERE o.buyer_user_id=%d OR o.seller_user_id=%d ORDER BY o.updated_at DESC LIMIT 100',
            $user_id, $user_id
        ), ARRAY_A);
        $deals = $wpdb->get_results($wpdb->prepare(
            'SELECT d.*,l.public_id AS listing_public_id,l.title AS listing_title FROM ' . MKT_DB::table('deals') . ' d JOIN ' . MKT_DB::table('listings') . ' l ON l.id=d.listing_id WHERE d.buyer_user_id=%d OR d.seller_user_id=%d ORDER BY d.updated_at DESC LIMIT 100',
            $user_id, $user_id
        ), ARRAY_A);
        $listings = MKT_Listings::search(['seller_user_id' => $user_id, 'limit' => 100], true);
        $reports = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('reports') . ' WHERE reporter_user_id=%d ORDER BY updated_at DESC LIMIT 100', $user_id), ARRAY_A);
        $disputes = $wpdb->get_results($wpdb->prepare(
            'SELECT x.*,d.public_id AS deal_public_id FROM ' . MKT_DB::table('disputes') . ' x JOIN ' . MKT_DB::table('deals') . ' d ON d.id=x.deal_id WHERE d.buyer_user_id=%d OR d.seller_user_id=%d ORDER BY x.updated_at DESC LIMIT 100',
            $user_id, $user_id
        ), ARRAY_A);
        $status_counts = static function(array $rows): array {
            $counts = [];
            foreach ($rows as $row) $counts[(string) $row['status']] = ($counts[(string) $row['status']] ?? 0) + 1;
            ksort($counts);
            return $counts;
        };
        $amounts = [];
        foreach ($deals as $deal) {
            if (!in_array((string) $deal['status'], ['completed','resolved','closed'], true)) continue;
            $currency = (string) $deal['currency'];
            $amounts[$currency] = ($amounts[$currency] ?? 0.0) + (float) $deal['agreed_amount'];
        }
        return [
            'current_user_id' => $user_id,
            'listings' => $listings['items'],
            'offers' => array_map([self::class, 'offer_dto'], $offers),
            'deals' => array_map([self::class, 'deal_dto'], $deals),
            'reports' => array_map(['MKT_Moderation', 'report_dto'], $reports),
            'disputes' => array_map(static function(array $row): array {
                $dto = MKT_Moderation::dispute_dto($row);
                $dto['deal_public_id'] = (string) $row['deal_public_id'];
                return $dto;
            }, $disputes),
            'insights' => [
                'listing_status_counts' => $status_counts($listings['items']),
                'offer_status_counts' => $status_counts($offers),
                'deal_status_counts' => $status_counts($deals),
                'completed_amounts_by_currency' => $amounts,
            ],
            'commission_percent' => 0,
        ];
    }

    public static function get_deal(string $public_id, int $user_id): array|WP_Error {
        global $wpdb;
        $deal = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE public_id=%s', $public_id), ARRAY_A);
        if (!$deal) {
            return new WP_Error('mkt_deal_not_found', __('Deal not found.', 'marketplace'), ['status' => 404]);
        }
        if (!MKT_Auth::deal_participant($deal, $user_id)) {
            $reviewer_auth = MKT_Auth::can('mkt_review_disputes', ['action' => 'read_deal_for_dispute', 'deal_public_id' => $public_id]);
            if (is_wp_error($reviewer_auth)) {
                return new WP_Error('mkt_deal_not_found', __('Deal not found.', 'marketplace'), ['status' => 404]);
            }
        }
        return self::deal_dto($deal);
    }

    public static function offer_dto(array $row): array {
        return [
            'public_id' => (string) $row['public_id'],
            'root_offer_id' => (int) $row['root_offer_id'],
            'parent_offer_id' => (int) $row['parent_offer_id'],
            'listing_id' => (int) $row['listing_id'],
            'buyer_user_id' => (int) $row['buyer_user_id'],
            'seller_user_id' => (int) $row['seller_user_id'],
            'created_by' => (int) $row['created_by'],
            'amount' => number_format((float) $row['amount'], 2, '.', ''),
            'currency' => (string) $row['currency'],
            'terms' => wp_kses_post((string) $row['terms']),
            'status' => (string) $row['status'],
            'version' => (int) $row['version'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'expires_at' => (string) $row['expires_at'],
            'listing_public_id' => (string) ($row['listing_public_id'] ?? ''),
            'listing_title' => (string) ($row['listing_title'] ?? ''),
        ];
    }

    public static function deal_dto(array $row): array {
        return [
            'public_id' => (string) $row['public_id'],
            'listing_id' => (int) $row['listing_id'],
            'buyer_user_id' => (int) $row['buyer_user_id'],
            'seller_user_id' => (int) $row['seller_user_id'],
            'agreed_amount' => number_format((float) $row['agreed_amount'], 2, '.', ''),
            'currency' => (string) $row['currency'],
            'agreed_snapshot' => json_decode((string) $row['agreed_snapshot'], true) ?: [],
            'payment_mode' => (string) $row['payment_mode'],
            'payment_status' => (string) $row['payment_status'],
            'delivery_status' => (string) $row['delivery_status'],
            'buyer_completion_confirmed' => !empty($row['buyer_completion_confirmed_at']),
            'seller_completion_confirmed' => !empty($row['seller_completion_confirmed_at']),
            'awaiting_other_party' => (string) $row['status'] !== 'completed' && (!empty($row['buyer_completion_confirmed_at']) xor !empty($row['seller_completion_confirmed_at'])),
            'status' => (string) $row['status'],
            'version' => (int) $row['version'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'completed_at' => $row['completed_at'],
            'closed_at' => $row['closed_at'],
            'commission_percent' => 0,
            'platform_escrow' => false,
            'listing_public_id' => (string) ($row['listing_public_id'] ?? ''),
            'listing_title' => (string) ($row['listing_title'] ?? ''),
            'url' => home_url('/marketplace/deal/' . rawurlencode((string) $row['public_id']) . '/'),
        ];
    }

    private static function idempotency_key(string $key): string|WP_Error {
        $key = sanitize_text_field($key);
        if (strlen($key) < 12 || strlen($key) > 190 || !preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            return new WP_Error('mkt_invalid_idempotency_key', __('A valid idempotency key is required.', 'marketplace'), ['status' => 422]);
        }
        return $key;
    }
}