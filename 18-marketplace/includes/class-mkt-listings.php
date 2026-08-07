<?php
defined('ABSPATH') || exit;

final class MKT_Listings {
    public static function ensure_seller(int $user_id): array|WP_Error {
        $eligibility = MKT_Auth::seller_eligibility($user_id);
        if (!$eligibility['eligible']) {
            return new WP_Error('mkt_seller_ineligible', __('Your account is not eligible to sell.', 'marketplace'), ['status' => 403, 'reasons' => $eligibility['reasons']]);
        }
        global $wpdb;
        $table = MKT_DB::table('sellers');
        $seller = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE user_id=%d", $user_id), ARRAY_A);
        $snapshot = [
            'status' => $eligibility['assertions']['status'],
            'verified' => $eligibility['assertions']['verified'],
            'is_minor' => $eligibility['assertions']['is_minor'],
            'guardian_verified' => $eligibility['assertions']['guardian_verified'],
            'risk_state' => $eligibility['assertions']['risk_state'],
            'captured_at' => MKT_DB::now(),
            'identity_version' => $eligibility['assertions']['version'],
        ];
        if (!$seller) {
            $user = get_userdata($user_id);
            $wpdb->insert($table, [
                'public_id' => MKT_DB::uuid(),
                'user_id' => $user_id,
                'store_name' => sanitize_text_field((string) ($user ? $user->display_name : '')),
                'seller_type' => 'individual',
                'status' => 'approved',
                'eligibility_snapshot' => wp_json_encode($snapshot),
                'public_contact_modes' => wp_json_encode(['file17_chat']),
                'country' => '',
                'region' => '',
                'city' => '',
                'version' => 1,
                'created_at' => MKT_DB::now(),
                'updated_at' => MKT_DB::now(),
                'approved_at' => MKT_DB::now(),
            ]);
            if (!$wpdb->insert_id) {
                return new WP_Error('mkt_seller_create_failed', __('Seller record could not be created.', 'marketplace'), ['status' => 500]);
            }
            $seller = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $wpdb->insert_id), ARRAY_A);
            MKT_Audit::record('seller_created', 'seller', (string) $seller['public_id'], ['user_id' => $user_id]);
        } else {
            $wpdb->update($table, [
                'status' => 'approved',
                'eligibility_snapshot' => wp_json_encode($snapshot),
                'updated_at' => MKT_DB::now(),
                'version' => (int) $seller['version'] + 1,
            ], ['id' => (int) $seller['id'], 'version' => (int) $seller['version']]);
            $seller = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int) $seller['id']), ARRAY_A);
        }
        return $seller;
    }

    public static function create(array $input, int $user_id): array|WP_Error {
        $auth = MKT_Auth::can('mkt_sell', ['action' => 'create_listing']);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $settings = MKT_DB::settings();
        if (!empty($settings['safe_mode']) || empty($settings['selling_enabled'])) {
            return new WP_Error('mkt_selling_paused', __('New listings are temporarily paused.', 'marketplace'), ['status' => 503]);
        }
        $seller = self::ensure_seller($user_id);
        if (is_wp_error($seller)) {
            return $seller;
        }
        $data = self::sanitize_input($input);
        $policy = MKT_Policy::evaluate_listing($data, MKT_Auth::assertions($user_id));
        if (!$policy['valid']) {
            return new WP_Error('mkt_listing_invalid', __('The listing did not pass policy validation.', 'marketplace'), ['status' => 422, 'errors' => $policy['errors']]);
        }
        $public_id = MKT_DB::uuid();
        $slug = self::unique_slug($data['title'], $public_id);
        $now = MKT_DB::now();
        $expires = gmdate('Y-m-d H:i:s', time() + max(1, (int) $settings['listing_expiry_days']) * DAY_IN_SECONDS);
        global $wpdb;
        $insert = [
            'public_id' => $public_id,
            'seller_id' => (int) $seller['id'],
            'title' => $data['title'],
            'slug' => $slug,
            'category' => $data['category'],
            'subcategory' => $data['subcategory'],
            'listing_type' => $data['listing_type'],
            'condition_name' => $data['condition_name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'currency' => $data['currency'],
            'quantity' => $data['quantity'],
            'availability' => $data['availability'],
            'location_country' => $data['location_country'],
            'location_region' => $data['location_region'],
            'location_city' => $data['location_city'],
            'delivery_modes' => wp_json_encode($data['delivery_modes']),
            'contact_modes' => wp_json_encode($data['contact_modes']),
            'declarations' => wp_json_encode($data['declarations']),
            'policy_snapshot' => wp_json_encode($policy['policy_snapshot']),
            'status' => 'draft',
            'version' => 1,
            'created_by' => $user_id,
            'updated_by' => $user_id,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => $expires,
        ];
        if (!$wpdb->insert(MKT_DB::table('listings'), $insert)) {
            return new WP_Error('mkt_listing_create_failed', __('The listing could not be created.', 'marketplace'), ['status' => 500]);
        }
        $listing = self::get_by_id((int) $wpdb->insert_id, true);
        MKT_Audit::record('listing_created', 'listing', $public_id, ['category' => $data['category'], 'policy_holds' => $policy['holds']]);
        MKT_Events::enqueue('MarketplaceListingCreated.v1', 'listing', $public_id, ['seller_user_id' => $user_id, 'safe_summary' => __('A listing draft was created.', 'marketplace')]);
        return self::private_dto($listing);
    }

    public static function update(string $public_id, array $input, int $user_id, int $expected_version): array|WP_Error {
        $auth = MKT_Auth::can('mkt_sell', ['action' => 'update_listing']);
        if (is_wp_error($auth)) return $auth;
        $listing = self::get($public_id, true);
        if (!$listing) {
            return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        }
        if (!MKT_Auth::own_listing($listing, $user_id) && !current_user_can('mkt_moderate')) {
            return new WP_Error('mkt_listing_forbidden', __('You cannot edit this listing.', 'marketplace'), ['status' => 403]);
        }
        if (!in_array($listing['status'], ['draft','paused','rejected','appealed','expired'], true)) {
            return new WP_Error('mkt_listing_state_locked', __('This listing cannot be edited in its current state.', 'marketplace'), ['status' => 409]);
        }
        if ((int) $listing['version'] !== $expected_version) {
            return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409, 'current_version' => (int) $listing['version']]);
        }
        $merged = array_merge($listing, $input);
        $data = self::sanitize_input($merged);
        $policy = MKT_Policy::evaluate_listing($data, MKT_Auth::assertions($user_id));
        if (!$policy['valid']) {
            return new WP_Error('mkt_listing_invalid', __('The listing did not pass policy validation.', 'marketplace'), ['status' => 422, 'errors' => $policy['errors']]);
        }
        global $wpdb;
        $revision_to_draft = in_array((string) $listing['status'], ['rejected','appealed','expired'], true);
        $changes = [
            'title' => $data['title'],
            'category' => $data['category'],
            'subcategory' => $data['subcategory'],
            'listing_type' => $data['listing_type'],
            'condition_name' => $data['condition_name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'currency' => $data['currency'],
            'quantity' => $data['quantity'],
            'availability' => $data['availability'],
            'location_country' => $data['location_country'],
            'location_region' => $data['location_region'],
            'location_city' => $data['location_city'],
            'delivery_modes' => wp_json_encode($data['delivery_modes']),
            'contact_modes' => wp_json_encode($data['contact_modes']),
            'declarations' => wp_json_encode($data['declarations']),
            'policy_snapshot' => wp_json_encode($policy['policy_snapshot']),
            'updated_by' => $user_id,
            'updated_at' => MKT_DB::now(),
            'version' => $expected_version + 1,
        ];
        if ($revision_to_draft) {
            $changes['status'] = 'draft';
            $changes['moderation_reason'] = '';
            $changes['moderation_note'] = '';
            $changes['submitted_at'] = null;
        }
        $updated = $wpdb->update(MKT_DB::table('listings'), $changes, ['id' => (int) $listing['id'], 'version' => $expected_version]);
        if (!$updated) {
            return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409]);
        }
        MKT_Audit::record('listing_updated', 'listing', $public_id, ['version' => $expected_version + 1]);
        return self::private_dto(self::get($public_id, true));
    }

    public static function submit(string $public_id, int $user_id, int $expected_version): array|WP_Error {
        $auth = MKT_Auth::can('mkt_sell', ['action' => 'submit_listing']);
        if (is_wp_error($auth)) return $auth;
        $listing = self::get($public_id, true);
        if (!$listing) {
            return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        }
        if (!MKT_Auth::own_listing($listing, $user_id)) {
            return new WP_Error('mkt_listing_forbidden', __('You cannot submit this listing.', 'marketplace'), ['status' => 403]);
        }
        $transition = MKT_State_Machines::assert('listing', (string) $listing['status'], 'review');
        if (is_wp_error($transition)) {
            return $transition;
        }
        $policy = MKT_Policy::evaluate_listing($listing, MKT_Auth::assertions($user_id));
        if (!$policy['valid']) {
            return new WP_Error('mkt_listing_invalid', __('The listing did not pass policy validation.', 'marketplace'), ['status' => 422, 'errors' => $policy['errors']]);
        }
        if (!self::media_ready((int) $listing['id'], (string) $listing['listing_type'] === 'product')) {
            return new WP_Error('mkt_media_not_ready', __('All listing media must pass rights and security checks.', 'marketplace'), ['status' => 422]);
        }
        global $wpdb;
        $updated = $wpdb->update(MKT_DB::table('listings'), [
            'status' => 'review',
            'submitted_at' => MKT_DB::now(),
            'updated_at' => MKT_DB::now(),
            'updated_by' => $user_id,
            'version' => $expected_version + 1,
        ], ['id' => (int) $listing['id'], 'version' => $expected_version, 'status' => (string) $listing['status']]);
        if (!$updated) {
            return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409]);
        }
        MKT_Audit::record('listing_submitted', 'listing', $public_id, ['version' => $expected_version + 1]);
        MKT_Events::enqueue('MarketplaceListingSubmitted.v1', 'listing', $public_id, ['seller_user_id' => $user_id, 'safe_summary' => __('A listing was submitted for review.', 'marketplace')]);
        return self::private_dto(self::get($public_id, true));
    }

    public static function transition(string $public_id, string $to, int $actor_id, int $expected_version, string $reason = '', string $note = ''): array|WP_Error {
        $listing = self::get($public_id, true);
        if (!$listing) {
            return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        }
        $owner = MKT_Auth::own_listing($listing, $actor_id);
        $moderator = current_user_can('mkt_moderate');
        $seller_pairs = [
            'active:paused', 'paused:active', 'active:sold_unavailable',
            'rejected:appealed', 'removed:appealed',
        ];
        $pair = (string) $listing['status'] . ':' . $to;
        if ($owner && in_array($pair, $seller_pairs, true)) {
            $auth = MKT_Auth::can('mkt_sell', ['action' => 'transition_listing', 'from' => $listing['status'], 'to' => $to]);
            if (is_wp_error($auth)) return $auth;
        } elseif ($moderator) {
            $auth = MKT_Auth::can('mkt_moderate', ['action' => 'moderate_listing', 'from' => $listing['status'], 'to' => $to]);
            if (is_wp_error($auth)) return $auth;
        } else {
            return new WP_Error('mkt_listing_transition_forbidden', __('You cannot change this listing state.', 'marketplace'), ['status' => 403]);
        }
        $transition = MKT_State_Machines::assert('listing', (string) $listing['status'], $to);
        if (is_wp_error($transition)) {
            return $transition;
        }
        if ((int) $listing['version'] !== $expected_version) {
            return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409]);
        }
        if ($to === 'active') {
            $policy = MKT_Policy::evaluate_listing($listing, MKT_Auth::assertions((int) $listing['seller_user_id']));
            if (!$policy['valid'] || !self::media_ready((int) $listing['id'], (string) $listing['listing_type'] === 'product')) {
                return new WP_Error('mkt_listing_not_publishable', __('The listing cannot be published until policy and media checks pass.', 'marketplace'), ['status' => 422, 'errors' => $policy['errors']]);
            }
        }
        global $wpdb;
        $changes = [
            'status' => sanitize_key($to),
            'moderation_reason' => sanitize_key($reason),
            'moderation_note' => wp_kses_post($note),
            'updated_by' => $actor_id,
            'updated_at' => MKT_DB::now(),
            'version' => $expected_version + 1,
            'last_reviewed_at' => $moderator ? MKT_DB::now() : $listing['last_reviewed_at'],
        ];
        if ($to === 'active' && empty($listing['published_at'])) {
            $changes['published_at'] = MKT_DB::now();
        }
        if ($to === 'removed') {
            $changes['removed_at'] = MKT_DB::now();
        }
        $updated = $wpdb->update(MKT_DB::table('listings'), $changes, ['id' => (int) $listing['id'], 'version' => $expected_version, 'status' => (string) $listing['status']]);
        if (!$updated) {
            return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409]);
        }
        $event = $to === 'active' ? 'MarketplaceListingPublished.v1' : 'MarketplaceListingStatusChanged.v1';
        MKT_Audit::record('listing_transitioned', 'listing', $public_id, ['from' => $listing['status'], 'to' => $to, 'reason' => $reason]);
        MKT_Events::enqueue($event, 'listing', $public_id, [
            'from' => $listing['status'],
            'to' => $to,
            'seller_user_id' => (int) $listing['seller_user_id'],
            'notify_user_ids' => [(int) $listing['seller_user_id']],
            'safe_summary' => sprintf(__('Listing status changed to %s.', 'marketplace'), $to),
            'url' => self::url($listing),
        ]);
        return self::private_dto(self::get($public_id, true));
    }

    public static function attach_media(string $public_id, array $media_input, int $user_id): array|WP_Error {
        $auth = MKT_Auth::can('mkt_sell', ['action' => 'attach_listing_media']);
        if (is_wp_error($auth)) return $auth;
        $listing = self::get($public_id, true);
        if (!$listing || !MKT_Auth::own_listing($listing, $user_id)) {
            return new WP_Error('mkt_listing_forbidden', __('You cannot attach media to this listing.', 'marketplace'), ['status' => 403]);
        }
        if (!in_array((string) $listing['status'], ['draft','rejected','paused','appealed','expired'], true)) {
            return new WP_Error('mkt_media_state_locked', __('Media cannot be changed in the current listing state.', 'marketplace'), ['status' => 409]);
        }
        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table('media_refs') . ' WHERE listing_id=%d AND status=%s', (int) $listing['id'], 'active'));
        if ($count >= (int) MKT_DB::settings()['max_media_refs']) {
            return new WP_Error('mkt_media_limit', __('The listing media limit has been reached.', 'marketplace'), ['status' => 422]);
        }
        $media = MKT_Integrations::media_reference($media_input, $user_id);
        if (is_wp_error($media)) {
            return $media;
        }
        $ref_id = MKT_DB::uuid();
        $inserted = $wpdb->insert(MKT_DB::table('media_refs'), [
            'public_id' => $ref_id,
            'listing_id' => (int) $listing['id'],
            'owner_user_id' => $user_id,
            'provider' => sanitize_key((string) ($media_input['provider'] ?? 'central-media')),
            'provider_public_id' => $media['public_id'],
            'media_kind' => $media['kind'],
            'rights_status' => $media['rights_status'],
            'scan_status' => $media['scan_status'],
            'alt_text' => sanitize_text_field((string) ($media_input['alt_text'] ?? '')),
            'sort_order' => $count,
            'metadata_json' => wp_json_encode(array_merge($media['metadata'], ['url' => $media['url'], 'thumbnail_url' => $media['thumbnail_url']])),
            'status' => 'active',
            'created_at' => MKT_DB::now(),
            'updated_at' => MKT_DB::now(),
        ]);
        if (!$inserted) {
            do_action('sabri_media_release_reference', (string) $media['public_id'], $user_id, 'marketplace');
            return new WP_Error('mkt_media_attach_failed', __('The media reference could not be attached.', 'marketplace'), ['status' => 500]);
        }
        MKT_Audit::record('listing_media_attached', 'listing', $public_id, ['media_ref' => $ref_id]);
        return ['public_id' => $ref_id] + $media;
    }


    public static function remove_media(string $public_id, string $media_public_id, int $user_id): bool|WP_Error {
        $auth = MKT_Auth::can('mkt_sell', ['action' => 'remove_listing_media']);
        if (is_wp_error($auth)) return $auth;
        $listing = self::get($public_id, true);
        if (!$listing || !MKT_Auth::own_listing($listing, $user_id)) {
            return new WP_Error('mkt_listing_forbidden', __('You cannot remove media from this listing.', 'marketplace'), ['status' => 403]);
        }
        if (!in_array((string) $listing['status'], ['draft','rejected','paused','appealed','expired'], true)) {
            return new WP_Error('mkt_media_state_locked', __('Media cannot be changed in the current listing state.', 'marketplace'), ['status' => 409]);
        }
        global $wpdb;
        $media = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('media_refs') . ' WHERE public_id=%s AND listing_id=%d AND status=%s', $media_public_id, (int) $listing['id'], 'active'), ARRAY_A);
        if (!$media) return new WP_Error('mkt_media_not_found', __('Media reference not found.', 'marketplace'), ['status' => 404]);
        $updated = $wpdb->update(MKT_DB::table('media_refs'), ['status' => 'removed', 'updated_at' => MKT_DB::now()], ['id' => (int) $media['id'], 'status' => 'active']);
        if (!$updated) return new WP_Error('mkt_media_remove_failed', __('Media could not be removed.', 'marketplace'), ['status' => 409]);
        do_action('sabri_media_release_reference', (string) $media['provider_public_id'], $user_id, 'marketplace');
        MKT_Audit::record('listing_media_removed', 'listing', $public_id, ['media_ref' => $media_public_id]);
        return true;
    }

    public static function save(string $public_id, int $user_id, bool $save = true): bool|WP_Error {
        $auth = MKT_Auth::can('mkt_buy', ['action' => 'save_listing']);
        if (is_wp_error($auth)) return $auth;
        $listing = self::get($public_id, false);
        if (!$listing || $listing['status'] !== 'active') {
            return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        }
        global $wpdb;
        if ($save) {
            $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . MKT_DB::table('saves') . ' (user_id,listing_id,created_at) VALUES (%d,%d,%s)', $user_id, (int) $listing['id'], MKT_DB::now()));
            MKT_Audit::record('listing_saved', 'listing', $public_id);
        } else {
            $wpdb->delete(MKT_DB::table('saves'), ['user_id' => $user_id, 'listing_id' => (int) $listing['id']]);
            MKT_Audit::record('listing_unsaved', 'listing', $public_id);
        }
        return true;
    }

    public static function open_chat(string $public_id, int $user_id): array|WP_Error {
        $auth = MKT_Auth::can('mkt_buy', ['action' => 'open_listing_chat']);
        if (is_wp_error($auth)) return $auth;
        $listing = self::get($public_id, false);
        if (!$listing || $listing['status'] !== 'active') {
            return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        }
        $seller_user_id = (int) $listing['seller_user_id'];
        if ($seller_user_id === $user_id) {
            return new WP_Error('mkt_own_listing_chat', __('You cannot start a buyer conversation on your own listing.', 'marketplace'), ['status' => 422]);
        }
        $context = [
            'object_public_id' => $public_id,
            'title' => $listing['title'],
            'url' => self::url($listing),
            'price' => $listing['price'],
            'currency' => $listing['currency'],
            'status' => $listing['status'],
            'snapshot_hash' => hash('sha256', wp_json_encode([$listing['public_id'],$listing['title'],$listing['price'],$listing['currency'],$listing['status'],$listing['version']])),
        ];
        $result = MKT_Integrations::open_context_conversation($user_id, $seller_user_id, $context);
        if (is_wp_error($result)) {
            MKT_Audit::record('listing_chat_failed', 'listing', $public_id, [], 'denied', $result->get_error_code());
            return $result;
        }
        MKT_Audit::record('listing_chat_opened', 'listing', $public_id, ['conversation_id' => $result['conversation_id']]);
        return $result;
    }

    public static function get(string $public_id, bool $include_private = false): ?array {
        global $wpdb;
        $where = $include_private ? '' : " AND l.status='active' AND s.status='approved'";
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT l.*,s.public_id AS seller_public_id,s.user_id AS seller_user_id,s.store_name,s.status AS seller_status,s.public_contact_modes FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . " s ON s.id=l.seller_id WHERE l.public_id=%s {$where} LIMIT 1",
            $public_id
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function get_by_id(int $id, bool $include_private = false): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT l.*,s.public_id AS seller_public_id,s.user_id AS seller_user_id,s.store_name,s.status AS seller_status,s.public_contact_modes FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id WHERE l.id=%d' . ($include_private ? '' : " AND l.status='active' AND s.status='approved'") . ' LIMIT 1',
            $id
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function search(array $filters = [], bool $private = false): array {
        global $wpdb;
        $limit = min(50, max(1, (int) ($filters['limit'] ?? 20)));
        $cursor = max(0, (int) ($filters['cursor'] ?? 0));
        $where = [];
        $params = [];
        if (!$private) {
            $where[] = "l.status='active'";
            $where[] = "s.status='approved'";
        }
        if (!empty($filters['seller_user_id'])) {
            $where[] = 's.user_id=%d'; $params[] = (int) $filters['seller_user_id'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'l.category=%s'; $params[] = sanitize_key((string) $filters['category']);
        }
        if (!empty($filters['country'])) {
            $where[] = 'l.location_country=%s'; $params[] = strtoupper(substr(sanitize_text_field((string) $filters['country']), 0, 2));
        }
        if (!empty($filters['city'])) {
            $where[] = 'l.location_city=%s'; $params[] = sanitize_text_field((string) $filters['city']);
        }
        if (!empty($filters['currency'])) {
            $where[] = 'l.currency=%s'; $params[] = MKT_DB::currency((string) $filters['currency']);
        }
        if (isset($filters['price_min']) && $filters['price_min'] !== '') {
            $where[] = 'l.price>=%f'; $params[] = max(0, (float) $filters['price_min']);
        }
        if (isset($filters['price_max']) && $filters['price_max'] !== '') {
            $where[] = 'l.price<=%f'; $params[] = max(0, (float) $filters['price_max']);
        }
        if (!empty($filters['q'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field((string) $filters['q'])) . '%';
            $where[] = '(l.title LIKE %s OR l.description LIKE %s OR l.category LIKE %s)';
            array_push($params, $like, $like, $like);
        }
        if ($cursor > 0) {
            $where[] = 'l.id<%d'; $params[] = $cursor;
        }
        $sql = 'SELECT l.*,s.public_id AS seller_public_id,s.user_id AS seller_user_id,s.store_name,s.status AS seller_status,s.public_contact_modes FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY l.published_at DESC,l.id DESC LIMIT %d';
        $params[] = $limit + 1;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        $has_more = count($rows) > $limit;
        if ($has_more) {
            array_pop($rows);
        }
        $items = array_map($private ? [self::class, 'private_dto'] : [self::class, 'public_dto'], $rows);
        $next_cursor = $has_more && $rows ? (int) end($rows)['id'] : null;
        return ['items' => $items, 'next_cursor' => $next_cursor, 'has_more' => $has_more];
    }

    public static function search_provider_query(array $query): array {
        $result = self::search(['q' => (string) ($query['q'] ?? ''), 'limit' => min(20, (int) ($query['limit'] ?? 10))]);
        return array_map(static fn(array $item): array => [
            'type' => 'marketplace_listing',
            'public_id' => $item['public_id'],
            'title' => $item['title'],
            'url' => $item['url'],
            'summary' => wp_trim_words(wp_strip_all_tags((string) $item['description']), 20),
            'owner' => 'file-18-marketplace',
        ], $result['items']);
    }

    public static function public_dto(array $row): array {
        $dto = [];
        foreach (MKT_Contracts::public_listing_fields() as $field) {
            if (array_key_exists($field, $row)) {
                $dto[$field] = $row[$field];
            }
        }
        $dto['price'] = number_format((float) $row['price'], 2, '.', '');
        $dto['quantity'] = number_format((float) $row['quantity'], 3, '.', '');
        $dto['delivery_modes'] = json_decode((string) ($row['delivery_modes'] ?? '[]'), true) ?: [];
        $dto['contact_modes'] = json_decode((string) ($row['contact_modes'] ?? '[]'), true) ?: [];
        $dto['seller'] = [
            'public_id' => (string) $row['seller_public_id'],
            'store_name' => (string) $row['store_name'],
            'status' => (string) $row['seller_status'],
        ];
        $dto['media'] = self::media((int) $row['id'], true);
        $dto['url'] = self::url($row);
        $dto['commission_percent'] = 0;
        return $dto;
    }

    public static function private_dto(array $row): array {
        $dto = self::public_dto($row);
        foreach (['id','seller_id','seller_user_id','declarations','policy_snapshot','moderation_reason','moderation_note','created_by','updated_by','created_at','submitted_at','expires_at','removed_at'] as $field) {
            $dto[$field] = $row[$field] ?? null;
        }
        $dto['declarations'] = json_decode((string) ($dto['declarations'] ?? '[]'), true) ?: [];
        $dto['policy_snapshot'] = json_decode((string) ($dto['policy_snapshot'] ?? '[]'), true) ?: [];
        $dto['media'] = self::media((int) $row['id'], false);
        return $dto;
    }

    public static function media(int $listing_id, bool $public_only = true): array {
        global $wpdb;
        $where = $public_only ? " AND status='active' AND rights_status='approved' AND scan_status='clean'" : '';
        $rows = $wpdb->get_results($wpdb->prepare('SELECT public_id,provider,provider_public_id,media_kind,rights_status,scan_status,alt_text,sort_order,metadata_json,status FROM ' . MKT_DB::table('media_refs') . " WHERE listing_id=%d {$where} ORDER BY sort_order ASC,id ASC", $listing_id), ARRAY_A);
        return array_map(static function(array $row): array {
            $row['metadata'] = json_decode((string) $row['metadata_json'], true) ?: [];
            unset($row['metadata_json']);
            return $row;
        }, $rows);
    }

    private static function media_ready(int $listing_id, bool $require_one = false): bool {
        global $wpdb;
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . MKT_DB::table('media_refs') . " WHERE listing_id=%d AND status='active'", $listing_id));
        if ($require_one && $total < 1) return false;
        $unsafe = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . MKT_DB::table('media_refs') . " WHERE listing_id=%d AND status='active' AND (rights_status<>'approved' OR scan_status<>'clean')", $listing_id));
        return $unsafe === 0;
    }

    public static function url(array $listing): string {
        return home_url('/marketplace/listing/' . rawurlencode((string) $listing['public_id']) . '/' . rawurlencode((string) $listing['slug']) . '/');
    }

    private static function unique_slug(string $title, string $public_id): string {
        global $wpdb;
        $base = sanitize_title($title) ?: 'listing';
        $slug = $base;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . MKT_DB::table('listings') . ' WHERE slug=%s', $slug))) {
            $slug = $base . '-' . $i++;
        }
        return mb_substr($slug, 0, 220) . '-' . substr(str_replace('-', '', $public_id), 0, 8);
    }

    private static function sanitize_input(array $input): array {
        $delivery_modes = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($input['delivery_modes'] ?? [])))));
        $contact_modes = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($input['contact_modes'] ?? ['file17_chat'])))));
        $contact_modes = array_values(array_intersect($contact_modes, ['file17_chat','phone_by_consent','whatsapp_by_consent','email_by_consent']));
        if (!in_array('file17_chat', $contact_modes, true)) {
            $contact_modes[] = 'file17_chat';
        }
        return [
            'title' => mb_substr(sanitize_text_field((string) ($input['title'] ?? '')), 0, 255),
            'category' => sanitize_key((string) ($input['category'] ?? '')),
            'subcategory' => sanitize_key((string) ($input['subcategory'] ?? '')),
            'listing_type' => in_array((string) ($input['listing_type'] ?? ''), ['product','service','digital'], true) ? (string) $input['listing_type'] : 'product',
            'condition_name' => in_array((string) ($input['condition_name'] ?? ''), ['new','used','refurbished','not_applicable'], true) ? (string) $input['condition_name'] : 'new',
            'description' => wp_kses_post((string) ($input['description'] ?? '')),
            'price' => round(max(0, (float) ($input['price'] ?? 0)), 2),
            'currency' => MKT_DB::currency((string) ($input['currency'] ?? MKT_DB::settings()['default_currency'])),
            'quantity' => round(max(0, (float) ($input['quantity'] ?? 1)), 3),
            'availability' => in_array((string) ($input['availability'] ?? ''), ['available','limited','preorder','service_schedule'], true) ? (string) $input['availability'] : 'available',
            'location_country' => strtoupper(substr(preg_replace('/[^A-Z]/i', '', (string) ($input['location_country'] ?? '')), 0, 2)),
            'location_region' => mb_substr(sanitize_text_field((string) ($input['location_region'] ?? '')), 0, 120),
            'location_city' => mb_substr(sanitize_text_field((string) ($input['location_city'] ?? '')), 0, 120),
            'delivery_modes' => $delivery_modes,
            'contact_modes' => $contact_modes,
            'declarations' => [
                'truthful' => !empty($input['declarations']['truthful']),
                'rights_owned' => !empty($input['declarations']['rights_owned']),
                'no_patient_data' => !empty($input['declarations']['no_patient_data']),
                'zero_commission_understood' => !empty($input['declarations']['zero_commission_understood']),
            ],
        ];
    }
}
