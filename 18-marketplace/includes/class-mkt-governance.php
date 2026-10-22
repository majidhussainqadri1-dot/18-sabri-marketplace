<?php
defined('ABSPATH') || exit;

/**
 * Marketplace governance extensions required by the current governing plans.
 *
 * File 18 remains the canonical owner of listing evidence, marketplace recalls,
 * and marketplace-scoped retention holds. Communication and notification truth
 * remain with Files 17 and 19 respectively.
 */
final class MKT_Governance {
    public const SCHEMA_VERSION = '1.0.0';
    private const MAX_HOLD_DAYS = 3650;

    private const TABLES = [
        'listing_evidence' => 'mkt_listing_evidence',
        'retention_holds' => 'mkt_retention_holds',
        'recalls' => 'mkt_recalls',
    ];

    public static function activate(): void {
        self::install_schema();
    }

    public static function boot(): void {
        self::maybe_upgrade();
        add_action('rest_api_init', [self::class, 'register_routes'], 25);
        add_filter('rest_post_dispatch', [self::class, 'augment_rest_response'], 20, 3);
        add_filter('mkt_assurance_manifest', [self::class, 'assurance_manifest']);
    }

    public static function table(string $name): string {
        if (!isset(self::TABLES[$name])) {
            throw new InvalidArgumentException('Unknown Marketplace governance table.');
        }
        global $wpdb;
        return $wpdb->prefix . self::TABLES[$name];
    }

    public static function table_exists(string $name): bool {
        global $wpdb;
        $table = self::table($name);
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    public static function schema_ready(): bool {
        // A WordPress option is a migration CLAIM, not proof of actual tables.
        // Verify the required deployed columns before accepting schema parity.
        global $wpdb;
        $required = [
            'listing_evidence' => ['id','listing_public_id','evidence_json','status','version','reviewed_by','reviewed_at'],
            'retention_holds' => ['id','public_id','object_type','object_public_id','status','expires_at','released_at'],
            'recalls' => ['id','public_id','listing_public_id','status','version','activated_at','resolved_at'],
        ];
        foreach ($required as $name => $columns) {
            if (!self::table_exists($name)) return false;
            $wpdb->last_error = '';
            $found = $wpdb->get_col('SHOW COLUMNS FROM ' . self::table($name), 0);
            if (!is_array($found) || array_diff($columns, $found) || $wpdb->last_error !== '') return false;
            // Columns without their unique constraints are NOT a deployable
            // canonical owner schema: duplicate evidence/public IDs would pass.
            $wpdb->last_error = '';
            $indexes = $wpdb->get_results('SHOW INDEX FROM ' . self::table($name), ARRAY_A);
            if (!is_array($indexes) || $wpdb->last_error !== '') return false;
            $keys = [];
            foreach ($indexes as $index) {
                $key = (string) ($index['Key_name'] ?? '');
                $seq = (int) ($index['Seq_in_index'] ?? 0);
                if ($key === '' || $seq < 1) continue;
                if ((int) ($index['Non_unique'] ?? 1) !== 0) continue;
                $keys[$key][$seq] = (string) ($index['Column_name'] ?? '');
            }
            $required_unique = $name === 'listing_evidence' ? 'listing_public_id' : 'public_id';
            // A UNIQUE KEY (public_id, extra_column) does NOT constrain
            // public_id itself; accepting only its first index column is unsafe.
            if (($keys['PRIMARY'] ?? []) !== [1 => 'id']
                || ($keys[$required_unique] ?? []) !== [1 => $required_unique]) return false;
        }
        return true;
    }

    public static function maybe_upgrade(): void {
        if ((string) get_option('mkt_governance_schema_version', '') !== self::SCHEMA_VERSION || !self::schema_ready()) {
            self::install_schema();
        }
    }

    private static function install_schema(): void {
        if (!function_exists('dbDelta')) require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        $evidence = self::table('listing_evidence');
        $holds = self::table('retention_holds');
        $recalls = self::table('recalls');

        dbDelta("CREATE TABLE {$evidence} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            listing_public_id char(36) NOT NULL,
            evidence_json longtext NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'pending',
            version bigint unsigned NOT NULL DEFAULT 1,
            reviewed_by bigint unsigned NOT NULL DEFAULT 0,
            review_note longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            reviewed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY listing_public_id (listing_public_id),
            KEY status (status),
            KEY updated_at (updated_at)
        ) {$c};");

        dbDelta("CREATE TABLE {$holds} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            object_type varchar(30) NOT NULL,
            object_public_id varchar(190) NOT NULL,
            reason varchar(120) NOT NULL,
            authority_reference varchar(190) NOT NULL DEFAULT '',
            status varchar(24) NOT NULL DEFAULT 'active',
            created_by bigint unsigned NOT NULL,
            released_by bigint unsigned NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            released_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            KEY target_status (object_type,object_public_id,status),
            KEY expiry (status,expires_at)
        ) {$c};");

        dbDelta("CREATE TABLE {$recalls} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            listing_public_id char(36) NOT NULL,
            batch_ref varchar(120) NOT NULL DEFAULT '',
            reason varchar(120) NOT NULL,
            details longtext NULL,
            regulator_reference varchar(190) NOT NULL DEFAULT '',
            status varchar(24) NOT NULL DEFAULT 'draft',
            version bigint unsigned NOT NULL DEFAULT 1,
            created_by bigint unsigned NOT NULL,
            approved_by bigint unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            activated_at datetime NULL,
            resolved_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            KEY listing_status (listing_public_id,status),
            KEY updated_at (updated_at)
        ) {$c};");

        // Promotion only after real deployed tables/columns were confirmed.
        // Never let a partial dbDelta result be accepted as upgraded.
        if (self::schema_ready()) {
            update_option('mkt_governance_schema_version', self::SCHEMA_VERSION, false);
            delete_option('mkt_governance_schema_error');
        } else {
            delete_option('mkt_governance_schema_version');
            update_option('mkt_governance_schema_error', 'schema_verification_failed', false);
        }
    }

    public static function register_routes(): void {
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/listings/(?P<id>[0-9a-fA-F-]{36})/evidence', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'rest_get_evidence'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'rest_save_evidence'],
                'permission_callback' => static fn(): bool => is_user_logged_in(),
            ],
        ]);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/listings/(?P<id>[0-9a-fA-F-]{36})/evidence/review', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_review_evidence'],
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/seller-studio', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_seller_studio'],
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/recalls', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_create_recall'],
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/recalls/(?P<id>[0-9a-fA-F-]{36})/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_transition_recall'],
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/retention-holds', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_create_hold'],
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/retention-holds/(?P<id>[0-9a-fA-F-]{36})/release', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rest_release_hold'],
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]);
    }

    public static function rest_get_evidence(WP_REST_Request $request) {
        $listing_id = sanitize_text_field((string) $request['id']);
        $listing = MKT_Listings::get($listing_id, false);
        if (!$listing) {
            return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        }
        return rest_ensure_response([
            'listing_public_id' => $listing_id,
            'evidence' => self::public_evidence($listing_id),
            'recall' => self::public_recall($listing_id),
        ]);
    }

    public static function rest_save_evidence(WP_REST_Request $request) {
        return self::mutation($request, 'listing_evidence_save', function(array $input) use ($request) {
            return self::save_evidence((string) $request['id'], $input, get_current_user_id());
        });
    }

    public static function rest_review_evidence(WP_REST_Request $request) {
        return self::mutation($request, 'listing_evidence_review', function(array $input) use ($request) {
            return self::review_evidence((string) $request['id'], $input, get_current_user_id());
        });
    }

    public static function rest_seller_studio(WP_REST_Request $request) {
        $auth = MKT_Auth::can('mkt_sell', ['action' => 'seller_studio']);
        if (is_wp_error($auth)) return $auth;
        $studio = self::seller_studio(get_current_user_id());
        if (empty($studio['available'])) return new WP_Error('mkt_seller_studio_unavailable', __('Seller Studio facts could not be verified.', 'marketplace'), ['status'=>503]);
        return rest_ensure_response($studio);
    }

    public static function rest_create_recall(WP_REST_Request $request) {
        return self::mutation($request, 'recall_create', fn(array $input) => self::create_recall($input, get_current_user_id()));
    }

    public static function rest_transition_recall(WP_REST_Request $request) {
        return self::mutation($request, 'recall_transition', function(array $input) use ($request) {
            return self::transition_recall((string) $request['id'], $input, get_current_user_id());
        });
    }

    public static function rest_create_hold(WP_REST_Request $request) {
        return self::mutation($request, 'retention_hold_create', fn(array $input) => self::create_hold($input, get_current_user_id()));
    }

    public static function rest_release_hold(WP_REST_Request $request) {
        return self::mutation($request, 'retention_hold_release', function(array $input) use ($request) {
            return self::release_hold((string) $request['id'], $input, get_current_user_id());
        });
    }

    private static function mutation(WP_REST_Request $request, string $scope, callable $callback) {
        if (!is_user_logged_in()) {
            return new WP_Error('mkt_auth_required', __('Sign in is required.', 'marketplace'), ['status' => 401]);
        }
        if (!self::application_password_auth()) {
            $nonce = (string) $request->get_header('X-WP-Nonce');
            if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('mkt_invalid_nonce', __('The security token is missing or expired.', 'marketplace'), ['status' => 403]);
            }
        }
        $input = (array) ($request->get_json_params() ?: []);
        $key = (string) $request->get_header('Idempotency-Key');
        try {
            $result = MKT_Idempotency::run('gov_' . sanitize_key($scope), $key, $input, fn() => $callback($input));
            return is_wp_error($result) ? $result : rest_ensure_response($result);
        } catch (Throwable $e) {
            return new WP_Error('mkt_governance_mutation_failed', __('The marketplace governance action could not be completed safely.', 'marketplace'), ['status' => 500, 'trace_id' => MKT_Audit::trace_id()]);
        }
    }

    private static function application_password_auth(): bool {
        return did_action('application_password_did_authenticate') > 0;
    }

    public static function save_evidence(string $listing_public_id, array $input, int $actor_id): array|WP_Error {
        $identity = self::require_current_actor($actor_id);
        if (is_wp_error($identity)) return $identity;
        $auth = MKT_Auth::can('mkt_sell', ['action' => 'save_listing_evidence', 'listing_public_id' => $listing_public_id]);
        if (is_wp_error($auth)) return $auth;
        $listing = MKT_Listings::get($listing_public_id, true);
        if (!$listing || !MKT_Auth::own_listing($listing, $actor_id)) {
            return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        }
        if (!in_array((string) $listing['status'], ['draft','paused','rejected','appealed','expired'], true)) {
            return new WP_Error('mkt_evidence_state_locked', __('Listing evidence cannot be changed in the current state.', 'marketplace'), ['status' => 409]);
        }
        $expected_listing_version = (int) ($input['expected_listing_version'] ?? 0);
        if ($expected_listing_version !== (int) $listing['version']) {
            return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409, 'current_version' => (int) $listing['version']]);
        }
        $evidence = self::sanitize_evidence((array) ($input['evidence'] ?? []));
        $validation = self::validate_evidence_payload($evidence, (string) $listing['category']);
        if ($validation) {
            return new WP_Error('mkt_evidence_invalid', __('The structured product evidence is incomplete or invalid.', 'marketplace'), ['status' => 422, 'errors' => $validation]);
        }

        global $wpdb;
        try {
            return MKT_DB::transaction(function() use ($wpdb, $listing, $listing_public_id, $evidence, $input, $actor_id, $expected_listing_version) {
                // Serialize on the listing BEFORE its evidence record.
                // Evidence review uses the same lock ordering and concurrent
                // publication/ownership changes must be seen here afresh.
                $fresh_listing = $wpdb->get_row($wpdb->prepare(
                    'SELECT l.*,s.user_id AS seller_user_id FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id WHERE l.public_id=%s FOR UPDATE',
                    $listing_public_id
                ), ARRAY_A);
                if (!$fresh_listing || (int) $fresh_listing['seller_user_id'] !== $actor_id
                    || (int) $fresh_listing['id'] !== (int) $listing['id']) {
                    return new WP_Error('mkt_listing_not_found', __('Listing no longer belongs to the current seller.', 'marketplace'), ['status' => 404]);
                }
                if (!in_array((string) $fresh_listing['status'], ['draft','paused','rejected','appealed','expired'], true)) {
                    return new WP_Error('mkt_evidence_state_locked', __('Evidence cannot be changed after the listing state changed.', 'marketplace'), ['status' => 409]);
                }
                if ((int) $fresh_listing['version'] !== $expected_listing_version) {
                    return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409, 'current_version' => (int) $fresh_listing['version']]);
                }
                $fresh_validation = self::validate_evidence_payload($evidence, (string) $fresh_listing['category']);
                if ($fresh_validation) {
                    return new WP_Error('mkt_evidence_invalid', __('Current category evidence requirements are not met.', 'marketplace'), ['status' => 422, 'errors' => $fresh_validation]);
                }

                $table = self::table('listing_evidence');
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE listing_public_id=%s FOR UPDATE", $listing_public_id), ARRAY_A);
                // An omitted evidence version is NOT permission to silently
                // overwrite the latest approved/pending reviewer decision.
                $expected_evidence_version = array_key_exists('expected_evidence_version', $input)
                    && filter_var($input['expected_evidence_version'], FILTER_VALIDATE_INT) !== false
                    ? (int) $input['expected_evidence_version'] : -1;
                $current_evidence_version = $row ? (int) $row['version'] : 0;
                if ($expected_evidence_version !== $current_evidence_version) {
                    return new WP_Error('mkt_stale_evidence_version', __('The evidence changed or its expected version is missing.', 'marketplace'), ['status' => 409, 'current_version' => $current_evidence_version]);
                }
                $listing_updated = $wpdb->update(MKT_DB::table('listings'), [
                    'updated_at' => MKT_DB::now(),
                    'updated_by' => $actor_id,
                    'version' => $expected_listing_version + 1,
                ], ['id' => (int) $fresh_listing['id'], 'version' => $expected_listing_version, 'status' => (string) $fresh_listing['status']]);
                if ($listing_updated !== 1) {
                    return new WP_Error('mkt_stale_version', __('The listing changed. Reload and try again.', 'marketplace'), ['status' => 409]);
                }
                $now = MKT_DB::now();
                if ($row) {
                    $changed = $wpdb->update($table, [
                        'evidence_json' => wp_json_encode($evidence),
                        'status' => 'pending',
                        'version' => (int) $row['version'] + 1,
                        'reviewed_by' => 0,
                        'review_note' => '',
                        'updated_at' => $now,
                        'reviewed_at' => null,
                    ], ['id' => (int) $row['id'], 'version' => (int) $row['version']]);
                    if ($changed !== 1) return new WP_Error('mkt_stale_evidence_version', __('The evidence record changed.', 'marketplace'), ['status' => 409]);
                } else {
                    $inserted = $wpdb->insert($table, [
                        'listing_public_id' => $listing_public_id,
                        'evidence_json' => wp_json_encode($evidence),
                        'status' => 'pending',
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    if (!$inserted) return new WP_Error('mkt_evidence_save_failed', __('The evidence could not be saved.', 'marketplace'), ['status' => 500]);
                }
                MKT_Events::enqueue('MarketplaceListingEvidenceUpdated.v1', 'listing', $listing_public_id, [
                    'seller_user_id' => $actor_id,
                    'safe_summary' => __('Structured listing evidence was updated and requires review.', 'marketplace'),
                ], 'restricted');
                MKT_Audit::record('listing_evidence_saved', 'listing', $listing_public_id, ['category' => $fresh_listing['category']], 'success', '', 'marketplace_evidence');
                $confirmed = self::evidence_record($listing_public_id, true);
                $new_version = $row ? (int) $row['version'] + 1 : 1;
                if (!$confirmed || (int) ($confirmed['version'] ?? 0) !== $new_version
                    || (string) ($confirmed['status'] ?? '') !== 'pending') {
                    return new WP_Error('mkt_evidence_readback_failed', __('Updated evidence could not be verified; the save was rolled back.', 'marketplace'), ['status' => 503]);
                }
                return $confirmed;
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_evidence_save_failed', __('The evidence could not be saved safely.', 'marketplace'), ['status' => 500, 'trace_id' => MKT_Audit::trace_id()]);
        }
    }

    public static function review_evidence(string $listing_public_id, array $input, int $actor_id): array|WP_Error {
        $identity = self::require_current_actor($actor_id);
        if (is_wp_error($identity)) return $identity;
        $auth = MKT_Auth::can('mkt_moderate', ['action' => 'review_listing_evidence', 'listing_public_id' => $listing_public_id]);
        if (is_wp_error($auth)) return $auth;
        $decision = sanitize_key((string) ($input['decision'] ?? ''));
        if (!in_array($decision, ['approved','rejected'], true)) {
            return new WP_Error('mkt_invalid_evidence_decision', __('Choose approved or rejected.', 'marketplace'), ['status' => 422]);
        }
        global $wpdb;
        $table = self::table('listing_evidence');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE listing_public_id=%s", $listing_public_id), ARRAY_A);
        if (!$row) return new WP_Error('mkt_evidence_not_found', __('Evidence record not found.', 'marketplace'), ['status' => 404]);
        $expected = (int) ($input['expected_version'] ?? 0);
        if ($expected !== (int) $row['version']) return new WP_Error('mkt_stale_evidence_version', __('The evidence record changed.', 'marketplace'), ['status' => 409]);
        $listing = MKT_Listings::get($listing_public_id, true);
        if (!$listing) return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        if ($actor_id === (int) ($listing['created_by'] ?? 0) || $actor_id === (int) ($listing['seller_user_id'] ?? 0)) {
            return new WP_Error('mkt_self_review_forbidden', __('A seller cannot review evidence for their own listing.', 'marketplace'), ['status' => 403]);
        }
        if ($decision === 'approved') {
            $errors = self::validate_evidence_payload(json_decode((string) $row['evidence_json'], true) ?: [], (string) $listing['category']);
            if ($errors) return new WP_Error('mkt_evidence_invalid', __('Evidence cannot be approved until all required fields are valid.', 'marketplace'), ['status' => 422, 'errors' => $errors]);
        }
        try {
            return MKT_DB::transaction(function() use ($wpdb, $table, $row, $decision, $input, $actor_id, $listing_public_id) {
                // Lock listing before evidence, identical to the strict REST
                // owner. Earlier non-locking reads do not authorize mutation.
                $locked_listing = $wpdb->get_row($wpdb->prepare(
                    'SELECT l.*,s.user_id AS seller_user_id FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id WHERE l.public_id=%s FOR UPDATE',
                    $listing_public_id
                ), ARRAY_A);
                if (!$locked_listing) return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
                if ($actor_id === (int) ($locked_listing['created_by'] ?? 0) || $actor_id === (int) $locked_listing['seller_user_id']) {
                    return new WP_Error('mkt_self_review_forbidden', __('A seller cannot review evidence for their own listing.', 'marketplace'), ['status' => 403]);
                }
                $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE listing_public_id=%s FOR UPDATE", $listing_public_id), ARRAY_A);
                if (!$fresh || (int) $fresh['version'] !== (int) $row['version']) {
                    return new WP_Error('mkt_stale_evidence_version', __('The evidence record changed.', 'marketplace'), ['status' => 409]);
                }
                if ($decision === 'approved') {
                    $errors = self::validate_evidence_payload(json_decode((string) $fresh['evidence_json'], true) ?: [], (string) $locked_listing['category']);
                    if ($errors) return new WP_Error('mkt_evidence_invalid', __('Evidence cannot be approved until required facts are valid.', 'marketplace'), ['status' => 422, 'errors' => $errors]);
                }
                $updated = $wpdb->update($table, [
                    'status' => $decision,
                    'version' => (int) $row['version'] + 1,
                    'reviewed_by' => $actor_id,
                    'review_note' => wp_kses_post((string) ($input['note'] ?? '')),
                    'updated_at' => MKT_DB::now(),
                    'reviewed_at' => MKT_DB::now(),
                ], ['id' => (int) $row['id'], 'version' => (int) $row['version']]);
                if ($updated !== 1) return new WP_Error('mkt_stale_evidence_version', __('The evidence record changed.', 'marketplace'), ['status' => 409]);
                $paused = false;
                if ($decision === 'rejected' && (string) $locked_listing['status'] === 'active') {
                    $paused_update = $wpdb->update(MKT_DB::table('listings'), [
                        'status' => 'paused', 'updated_at' => MKT_DB::now(),
                        'updated_by' => $actor_id, 'version' => (int) $locked_listing['version'] + 1,
                    ], ['id' => (int) $locked_listing['id'], 'version' => (int) $locked_listing['version'], 'status' => 'active']);
                    if ($paused_update !== 1) return new WP_Error('mkt_listing_evidence_pause_conflict', __('Listing changed during evidence rejection.', 'marketplace'), ['status' => 409]);
                    $paused = true;
                }
                MKT_Events::enqueue('MarketplaceListingEvidenceReviewed.v1', 'listing', $listing_public_id, [
                    'status' => $decision,
                    'notify_user_ids' => [(int) $locked_listing['seller_user_id']],
                    'safe_summary' => sprintf(__('Listing evidence review status changed to %s.', 'marketplace'), $decision),
                ], 'restricted');
                if ($paused) {
                    MKT_Events::enqueue('MarketplaceListingStatusChanged.v1', 'listing', $listing_public_id, [
                        'from' => 'active', 'to' => 'paused', 'reason' => 'evidence_rejected',
                        'notify_user_ids' => [(int) $locked_listing['seller_user_id']],
                        'safe_summary' => __('Listing paused because regulated evidence was rejected.', 'marketplace'),
                    ], 'restricted');
                }
                MKT_Audit::record('listing_evidence_reviewed', 'listing', $listing_public_id, [
                    'decision' => $decision, 'self_review_prevented' => true, 'listing_paused' => $paused,
                ], 'success', '', 'marketplace_evidence');
                $confirmed = self::evidence_record($listing_public_id, true);
                if (!$confirmed || (int) ($confirmed['version'] ?? 0) !== (int) $row['version'] + 1
                    || (string) ($confirmed['status'] ?? '') !== $decision) {
                    return new WP_Error('mkt_evidence_readback_failed', __('Reviewed evidence could not be verified; the decision was rolled back.', 'marketplace'), ['status' => 503]);
                }
                return $confirmed;
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_evidence_review_failed', __('The evidence review could not be saved safely.', 'marketplace'), ['status' => 500, 'trace_id' => MKT_Audit::trace_id()]);
        }
    }

    public static function evidence_gate(array $data, array $category_rules): array {
        if (empty($category_rules['requires_structured_evidence'])) {
            return ['errors' => [], 'holds' => []];
        }
        $listing_public_id = sanitize_text_field((string) ($data['public_id'] ?? ''));
        if ($listing_public_id === '') {
            return ['errors' => [], 'holds' => ['structured_evidence_required_before_submit']];
        }
        $row = self::evidence_record($listing_public_id, true);
        if (!$row) {
            return ['errors' => [['code' => 'structured_evidence_required', 'message' => __('Structured product evidence is required before review or publication.', 'marketplace')]], 'holds' => []];
        }
        if ((string) $row['status'] !== 'approved') {
            return ['errors' => [['code' => 'evidence_review_required', 'message' => __('Structured product evidence must be approved before publication.', 'marketplace')]], 'holds' => ['evidence_review']];
        }
        $errors = self::validate_evidence_payload((array) $row['evidence'], (string) ($data['category'] ?? ''));
        return ['errors' => $errors, 'holds' => []];
    }

    public static function public_evidence(string $listing_public_id): ?array {
        $row = self::evidence_record($listing_public_id, true);
        if (!$row || (string) $row['status'] !== 'approved') return null;
        $evidence = (array) $row['evidence'];
        return [
            'ingredients' => (array) ($evidence['ingredients'] ?? []),
            'manufacturer' => (string) ($evidence['manufacturer'] ?? ''),
            'license_or_registration' => (string) ($evidence['license_or_registration'] ?? ''),
            'batch_number' => (string) ($evidence['batch_number'] ?? ''),
            'expiry_date' => (string) ($evidence['expiry_date'] ?? ''),
            'not_applicable' => (array) ($evidence['not_applicable'] ?? []),
            'claims' => (array) ($evidence['claims'] ?? []),
            'source_refs' => (array) ($evidence['source_refs'] ?? []),
            'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
            'version' => (int) $row['version'],
        ];
    }

    private static function evidence_record(string $listing_public_id, bool $include_private = false): ?array {
        if (!self::table_exists('listing_evidence')) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('listing_evidence') . ' WHERE listing_public_id=%s', $listing_public_id), ARRAY_A);
        if (!$row || (!$include_private && (string) $row['status'] !== 'approved')) return null;
        $row['evidence'] = json_decode((string) $row['evidence_json'], true) ?: [];
        unset($row['evidence_json']);
        return $row;
    }

    private static function sanitize_evidence(array $input): array {
        $ingredients = array_slice(array_values(array_unique(array_filter(array_map(static function($value): string {
            return mb_substr(sanitize_text_field((string) $value), 0, 160);
        }, (array) ($input['ingredients'] ?? []))))), 0, 50);
        $claims = array_slice(array_values(array_unique(array_filter(array_map(static function($value): string {
            return mb_substr(sanitize_text_field((string) $value), 0, 300);
        }, (array) ($input['claims'] ?? []))))), 0, 10);
        $refs = array_slice(array_values(array_unique(array_filter(array_map(static function($value): string {
            return mb_substr(sanitize_text_field((string) $value), 0, 190);
        }, (array) ($input['source_refs'] ?? []))))), 0, 20);
        $na = [];
        foreach (['license_or_registration','batch_number','expiry_date'] as $field) {
            $reason = mb_substr(sanitize_text_field((string) (($input['not_applicable'][$field] ?? ''))), 0, 190);
            if ($reason !== '') $na[$field] = $reason;
        }
        return [
            'ingredients' => $ingredients,
            'manufacturer' => mb_substr(sanitize_text_field((string) ($input['manufacturer'] ?? '')), 0, 190),
            'license_or_registration' => mb_substr(sanitize_text_field((string) ($input['license_or_registration'] ?? '')), 0, 190),
            'batch_number' => mb_substr(sanitize_text_field((string) ($input['batch_number'] ?? '')), 0, 120),
            'expiry_date' => sanitize_text_field((string) ($input['expiry_date'] ?? '')),
            'not_applicable' => $na,
            'claims' => $claims,
            'source_refs' => $refs,
        ];
    }

    private static function validate_evidence_payload(array $evidence, string $category): array {
        $errors = [];
        if ($category === 'homeopathic_medicines') {
            if (empty($evidence['ingredients'])) $errors[] = ['code' => 'ingredients_required', 'field' => 'ingredients'];
            if (trim((string) ($evidence['manufacturer'] ?? '')) === '') $errors[] = ['code' => 'manufacturer_required', 'field' => 'manufacturer'];
            foreach (['license_or_registration','batch_number','expiry_date'] as $field) {
                if (trim((string) ($evidence[$field] ?? '')) === '' && trim((string) ($evidence['not_applicable'][$field] ?? '')) === '') {
                    $errors[] = ['code' => 'evidence_value_or_na_required', 'field' => $field];
                }
            }
        }
        $expiry = trim((string) ($evidence['expiry_date'] ?? ''));
        if ($expiry !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiry, new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d') !== $expiry) {
                $errors[] = ['code' => 'invalid_expiry_date', 'field' => 'expiry_date'];
            } elseif ($date < new DateTimeImmutable('today', new DateTimeZone('UTC'))) {
                $errors[] = ['code' => 'expired_batch', 'field' => 'expiry_date'];
            }
        }
        foreach ((array) ($evidence['claims'] ?? []) as $claim) {
            if (self::prohibited_claim((string) $claim)) {
                $errors[] = ['code' => 'prohibited_claim', 'field' => 'claims'];
                break;
            }
        }
        return $errors;
    }

    public static function prohibited_claim(string $text): bool {
        $text = strtolower(wp_strip_all_tags($text));
        $patterns = [
            '/\bguaranteed\s+cure\b/u',
            '/\b100\s*%\s*cure\b/u',
            '/\bcures?\s+(cancer|diabetes|tuberculosis|heart\s+disease)\b/u',
            '/\bno\s+side\s+effects?\s+guaranteed\b/u',
            '/\breplaces?\s+(emergency|hospital|doctor)\s+care\b/u',
        ];
        foreach ($patterns as $pattern) if (preg_match($pattern, $text)) return true;
        return false;
    }

    public static function seller_studio(int $user_id): array|WP_Error {
        $identity = self::require_current_actor($user_id);
        if (is_wp_error($identity)) return $identity;
        global $wpdb;
        $unavailable = ['available'=>false, 'error'=>'seller_studio_sources_unavailable', 'inventory'=>[], 'listing_quality'=>['average_score'=>null, 'reviewed'=>null], 'reports'=>null, 'inquiries'=>['available'=>false], 'response_sla'=>['available'=>false]];
        $wpdb->last_error = '';
        $seller = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('sellers') . ' WHERE user_id=%d', $user_id), ARRAY_A);
        if ($wpdb->last_error !== '') return $unavailable;
        if (!$seller) {
            return ['available' => true, 'inventory' => [], 'listing_quality' => ['average_score' => 0, 'reviewed' => 0], 'reports' => 0, 'inquiries' => ['available' => false], 'response_sla' => ['available' => false]];
        }
        $wpdb->last_error = '';
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('listings') . ' WHERE seller_id=%d ORDER BY id DESC LIMIT 200', (int) $seller['id']), ARRAY_A);
        if (!is_array($rows) || $wpdb->last_error !== '') return $unavailable;
        $inventory = [];
        $scores = [];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $inventory[$status] = ($inventory[$status] ?? 0) + 1;
            $score = 0;
            if (mb_strlen(trim((string) $row['title'])) >= 8) $score += 20;
            if (mb_strlen(wp_strip_all_tags((string) $row['description'])) >= 80) $score += 25;
            if ((string) $row['category'] !== '') $score += 15;
            if ((string) $row['currency'] !== '' && (float) $row['price'] >= 0) $score += 15;
            $wpdb->last_error = '';
            $media_count_raw = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . MKT_DB::table('media_refs') . " WHERE listing_id=%d AND status='active' AND rights_status='approved' AND scan_status='clean'", (int) $row['id']));
            if ($media_count_raw === null || $wpdb->last_error !== '' || !ctype_digit((string)$media_count_raw)) return $unavailable;
            $media_count = (int)$media_count_raw;
            if ($media_count > 0 || (string) $row['listing_type'] === 'service') $score += 15;
            $evidence = self::evidence_record((string) $row['public_id'], true);
            if ((string) $row['category'] !== 'homeopathic_medicines' || ($evidence && (string) $evidence['status'] === 'approved')) $score += 10;
            $scores[] = min(100, $score);
        }
        ksort($inventory);
        $listing_ids = array_map(static fn(array $row): string => (string) $row['public_id'], $rows);
        $reports = 0;
        if ($listing_ids) {
            $placeholders = implode(',', array_fill(0, count($listing_ids), '%s'));
            $sql = $wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table('reports') . " WHERE target_type='listing' AND target_public_id IN ({$placeholders})", $listing_ids);
            $wpdb->last_error = '';
            $reports_raw = $wpdb->get_var($sql);
            if ($reports_raw === null || $wpdb->last_error !== '' || !ctype_digit((string)$reports_raw)) return $unavailable;
            $reports = (int)$reports_raw;
        }
        $external = apply_filters('sabri_marketplace_seller_studio_metrics', ['inquiries' => ['available' => false], 'response_sla' => ['available' => false]], $user_id);
        return [
            'available' => true,
            'inventory' => $inventory,
            'listing_quality' => [
                'average_score' => $scores ? round(array_sum($scores) / count($scores), 1) : 0,
                'reviewed' => count($scores),
                'method' => 'bounded completeness/safety score; not a ranking signal',
            ],
            'reports' => $reports,
            'inquiries' => is_array($external['inquiries'] ?? null) ? $external['inquiries'] : ['available' => false],
            'response_sla' => is_array($external['response_sla'] ?? null) ? $external['response_sla'] : ['available' => false],
            'paid_ranking' => false,
            'donation_advantage' => false,
        ];
    }

    public static function create_recall(array $input, int $actor_id): array|WP_Error {
        $identity = self::require_current_actor($actor_id);
        if (is_wp_error($identity)) return $identity;
        $auth = MKT_Auth::can('mkt_moderate', ['action' => 'create_marketplace_recall']);
        if (is_wp_error($auth)) return $auth;
        $listing_public_id = sanitize_text_field((string) ($input['listing_public_id'] ?? ''));
        $listing = MKT_Listings::get($listing_public_id, true);
        if (!$listing) return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
        $reason = mb_substr(sanitize_text_field((string) ($input['reason'] ?? '')), 0, 120);
        if ($reason === '') return new WP_Error('mkt_recall_reason_required', __('A recall reason is required.', 'marketplace'), ['status' => 422]);
        global $wpdb;
        $public_id = MKT_DB::uuid();
        try {
            return MKT_DB::transaction(static function() use ($wpdb, $public_id, $listing_public_id, $input, $reason, $actor_id) {
                $inserted = $wpdb->insert(self::table('recalls'), [
                    'public_id' => $public_id,
                    'listing_public_id' => $listing_public_id,
                    'batch_ref' => mb_substr(sanitize_text_field((string) ($input['batch_ref'] ?? '')), 0, 120),
                    'reason' => $reason,
                    'details' => wp_kses_post((string) ($input['details'] ?? '')),
                    'regulator_reference' => mb_substr(sanitize_text_field((string) ($input['regulator_reference'] ?? '')), 0, 190),
                    'status' => 'draft',
                    'version' => 1,
                    'created_by' => $actor_id,
                    'created_at' => MKT_DB::now(),
                    'updated_at' => MKT_DB::now(),
                ]);
                if ($inserted !== 1) {
                    return new WP_Error('mkt_recall_create_failed', __('Recall record could not be created.', 'marketplace'), ['status' => 503]);
                }
                MKT_Audit::record('recall_created', 'listing', $listing_public_id, ['recall_public_id' => $public_id, 'reason' => $reason], 'success', '', 'marketplace_safety');
                $created = self::recall_record($public_id);
                return $created ?: new WP_Error('mkt_recall_readback_failed', __('The recall record could not be verified.', 'marketplace'), ['status' => 503]);
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_recall_create_failed', __('The recall could not be saved and audited safely.', 'marketplace'), ['status' => 503]);
        }
    }

    public static function transition_recall(string $public_id, array $input, int $actor_id): array|WP_Error {
        $identity = self::require_current_actor($actor_id);
        if (is_wp_error($identity)) return $identity;
        $auth = MKT_Auth::can('mkt_moderate', ['action' => 'transition_marketplace_recall']);
        if (is_wp_error($auth)) return $auth;
        $to = sanitize_key((string) ($input['to'] ?? ''));
        if (!in_array($to, ['active','resolved'], true)) return new WP_Error('mkt_invalid_recall_state', __('Invalid recall state.', 'marketplace'), ['status' => 422]);
        global $wpdb;
        $table = self::table('recalls');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id=%s", $public_id), ARRAY_A);
        if (!$row) return new WP_Error('mkt_recall_not_found', __('Recall record not found.', 'marketplace'), ['status' => 404]);
        $expected = (int) ($input['expected_version'] ?? 0);
        if ($expected !== (int) $row['version']) return new WP_Error('mkt_stale_version', __('Recall record changed.', 'marketplace'), ['status' => 409]);
        $valid = ((string) $row['status'] === 'draft' && $to === 'active') || ((string) $row['status'] === 'active' && $to === 'resolved');
        if (!$valid) return new WP_Error('mkt_invalid_recall_transition', __('Recall transition is not allowed.', 'marketplace'), ['status' => 409]);
        try {
            return MKT_DB::transaction(function() use ($wpdb, $table, $row, $public_id, $to, $actor_id) {
                $listing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('listings') . ' WHERE public_id=%s FOR UPDATE', (string) $row['listing_public_id']), ARRAY_A);
                if (!$listing) return new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]);
                $changes = ['status' => $to, 'version' => (int) $row['version'] + 1, 'updated_at' => MKT_DB::now()];
                if ($to === 'active') {
                    $changes['approved_by'] = $actor_id;
                    $changes['activated_at'] = MKT_DB::now();
                    if ((string) $listing['status'] === 'active') {
                        $paused = $wpdb->update(MKT_DB::table('listings'), ['status' => 'paused', 'updated_at' => MKT_DB::now(), 'version' => (int) $listing['version'] + 1], ['id' => (int) $listing['id'], 'version' => (int) $listing['version'], 'status' => 'active']);
                        if ($paused !== 1) return new WP_Error('mkt_recall_listing_conflict', __('Listing changed during recall activation.', 'marketplace'), ['status' => 409]);
                    }
                } else {
                    $changes['resolved_at'] = MKT_DB::now();
                }
                $updated = $wpdb->update($table, $changes, ['id' => (int) $row['id'], 'version' => (int) $row['version'], 'status' => (string) $row['status']]);
                if ($updated !== 1) return new WP_Error('mkt_stale_version', __('Recall record changed.', 'marketplace'), ['status' => 409]);
                $users = [(int) $listing['created_by']];
                if ($to === 'active') {
                    $buyers = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT buyer_user_id FROM ' . MKT_DB::table('deals') . ' WHERE listing_id=%d', (int) $listing['id']));
                    $users = array_values(array_unique(array_filter(array_merge($users, array_map('intval', $buyers)))));
                }
                MKT_Events::enqueue($to === 'active' ? 'MarketplaceRecallActivated.v1' : 'MarketplaceRecallResolved.v1', 'listing', (string) $row['listing_public_id'], [
                    'recall_public_id' => $public_id,
                    'batch_ref' => (string) $row['batch_ref'],
                    'reason' => (string) $row['reason'],
                    'regulator_reference' => (string) $row['regulator_reference'],
                    'notify_user_ids' => $users,
                    'safe_summary' => $to === 'active' ? __('A marketplace product recall or takedown was activated.', 'marketplace') : __('A marketplace recall record was resolved; the listing still requires independent publication review.', 'marketplace'),
                    'url' => MKT_Listings::url($listing),
                ], 'restricted');
                MKT_Audit::record('recall_' . $to, 'listing', (string) $row['listing_public_id'], ['recall_public_id' => $public_id, 'batch_ref' => $row['batch_ref']], 'success', '', 'marketplace_safety');
                $confirmed = self::recall_record($public_id);
                if (!$confirmed || (int) ($confirmed['version'] ?? 0) !== (int) $row['version'] + 1
                    || (string) ($confirmed['status'] ?? '') !== $to) {
                    return new WP_Error('mkt_recall_readback_failed', __('The recall transition could not be verified and was rolled back.', 'marketplace'), ['status' => 503]);
                }
                return $confirmed;
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_recall_transition_failed', __('Recall action could not be completed safely.', 'marketplace'), ['status' => 500, 'trace_id' => MKT_Audit::trace_id()]);
        }
    }

    private static function recall_record(string $public_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('recalls') . ' WHERE public_id=%s', $public_id), ARRAY_A);
        return $row ?: null;
    }

    /** Fail closed on an unavailable recall owner table or uncertain SQL read. */
    public static function recall_publication_guard(string $listing_public_id, bool $locking_read = false): bool|WP_Error {
        if (!self::table_exists('recalls')) {
            return new WP_Error('mkt_recall_verification_unavailable', __('Recall information cannot be verified. Publication is paused.', 'marketplace'), ['status'=>503]);
        }
        global $wpdb;
        $wpdb->last_error = '';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT public_id FROM " . self::table('recalls') . " WHERE listing_public_id=%s AND status='active' LIMIT 1" . ($locking_read ? ' FOR UPDATE' : ''),
            $listing_public_id
        ), ARRAY_A);
        if ($wpdb->last_error !== '' || ($row !== null && !is_array($row))) {
            return new WP_Error('mkt_recall_verification_unavailable', __('Recall information cannot be verified. Publication is paused.', 'marketplace'), ['status'=>503]);
        }
        return is_array($row);
    }

    public static function public_recall(string $listing_public_id): ?array {
        if (!self::table_exists('recalls')) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT public_id,batch_ref,reason,regulator_reference,status,activated_at,updated_at FROM " . self::table('recalls') . " WHERE listing_public_id=%s AND status='active' ORDER BY id DESC LIMIT 1", $listing_public_id), ARRAY_A);
        return $row ?: null;
    }

    public static function create_hold(array $input, int $actor_id): array|WP_Error {
        $identity = self::require_current_actor($actor_id);
        if (is_wp_error($identity)) return $identity;
        $auth = self::retention_authorization('create_retention_hold');
        if (is_wp_error($auth)) return $auth;
        $object_type = sanitize_key((string) ($input['object_type'] ?? ''));
        $object_public_id = sanitize_text_field((string) ($input['object_public_id'] ?? ''));
        if (!in_array($object_type, ['listing','offer','deal','report','dispute','recall'], true) || $object_public_id === '' || !self::object_exists($object_type, $object_public_id)) {
            return new WP_Error('mkt_hold_target_invalid', __('Retention-hold target is invalid.', 'marketplace'), ['status' => 422]);
        }
        $reason = mb_substr(sanitize_text_field((string) ($input['reason'] ?? '')), 0, 120);
        $authority = mb_substr(sanitize_text_field((string) ($input['authority_reference'] ?? '')), 0, 190);
        $expires_at = sanitize_text_field((string) ($input['expires_at'] ?? ''));
        $expires = strtotime($expires_at . ' UTC');
        if ($reason === '' || $authority === '' || !$expires || $expires <= time() || $expires > time() + self::MAX_HOLD_DAYS * DAY_IN_SECONDS) {
            return new WP_Error('mkt_hold_invalid', __('A documented, time-bounded retention hold with authority reference is required.', 'marketplace'), ['status' => 422]);
        }
        global $wpdb;
        $wpdb->last_error = '';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT public_id FROM " . self::table('retention_holds') . " WHERE object_type=%s AND object_public_id=%s AND status='active' AND expires_at>UTC_TIMESTAMP() LIMIT 1", $object_type, $object_public_id));
        if ($wpdb->last_error !== '') {
            return new WP_Error('mkt_hold_lookup_unavailable', __('Existing legal holds could not be checked safely.', 'marketplace'), ['status' => 503]);
        }
        if ($existing) return new WP_Error('mkt_hold_exists', __('An active retention hold already exists for this object.', 'marketplace'), ['status' => 409, 'public_id' => (string) $existing]);
        $public_id = MKT_DB::uuid();
        try {
            return MKT_DB::transaction(static function() use ($wpdb, $public_id, $object_type, $object_public_id, $reason, $authority, $actor_id, $expires) {
                // There is no unique partial index for active, unexpired holds:
                // serialize by canonical object and recheck UNDER the named lock.
                // Keep the lock through owner COMMIT/ROLLBACK via MKT_DB.
                $hold_lock = 'mkt_hold_' . substr(hash('sha256', $object_type . ':' . $object_public_id), 0, 48);
                $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $hold_lock, 4));
                if ((string) $acquired !== '1') {
                    return new WP_Error('mkt_hold_lock_unavailable', __('Another retention-hold change is in progress.', 'marketplace'), ['status' => 503]);
                }
                MKT_DB::release_named_lock_after_transaction($hold_lock);
                $wpdb->last_error = '';
                $current = $wpdb->get_var($wpdb->prepare(
                    "SELECT public_id FROM " . self::table('retention_holds') . " WHERE object_type=%s AND object_public_id=%s AND status='active' AND expires_at>UTC_TIMESTAMP() LIMIT 1",
                    $object_type, $object_public_id
                ));
                if ($wpdb->last_error !== '') {
                    return new WP_Error('mkt_hold_lookup_unavailable', __('Existing legal holds could not be rechecked under the object lock.', 'marketplace'), ['status' => 503]);
                }
                if ($current) {
                    return new WP_Error('mkt_hold_exists', __('An active retention hold already exists for this object.', 'marketplace'), ['status' => 409, 'public_id' => (string) $current]);
                }
                $inserted = $wpdb->insert(self::table('retention_holds'), [
                    'public_id' => $public_id,
                    'object_type' => $object_type,
                    'object_public_id' => $object_public_id,
                    'reason' => $reason,
                    'authority_reference' => $authority,
                    'status' => 'active',
                    'created_by' => $actor_id,
                    'expires_at' => gmdate('Y-m-d H:i:s', $expires),
                    'created_at' => MKT_DB::now(),
                    'updated_at' => MKT_DB::now(),
                ]);
                if ($inserted !== 1) {
                    return new WP_Error('mkt_hold_create_failed', __('Retention hold could not be created.', 'marketplace'), ['status' => 503]);
                }
                MKT_Audit::record('retention_hold_created', $object_type, $object_public_id, ['hold_public_id' => $public_id, 'reason' => $reason, 'expires_at' => gmdate('c', $expires)], 'success', '', 'legal_safety_hold');
                return ['public_id' => $public_id, 'object_type' => $object_type, 'object_public_id' => $object_public_id, 'status' => 'active', 'expires_at' => gmdate('Y-m-d H:i:s', $expires)];
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_hold_create_failed', __('The legal hold could not be saved and audited safely.', 'marketplace'), ['status' => 503]);
        }
    }

    public static function release_hold(string $public_id, array $input, int $actor_id): array|WP_Error {
        $identity = self::require_current_actor($actor_id);
        if (is_wp_error($identity)) return $identity;
        $auth = self::retention_authorization('release_retention_hold');
        if (is_wp_error($auth)) return $auth;
        global $wpdb;
        $wpdb->last_error = '';
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('retention_holds') . ' WHERE public_id=%s', $public_id), ARRAY_A);
        if ($wpdb->last_error !== '') {
            return new WP_Error('mkt_hold_lookup_unavailable', __('The legal-hold record could not be read safely.', 'marketplace'), ['status' => 503]);
        }
        if (!$row) return new WP_Error('mkt_hold_not_found', __('Retention hold not found.', 'marketplace'), ['status' => 404]);
        if ((string) $row['status'] !== 'active') return ['public_id' => $public_id, 'status' => (string) $row['status']];
        $reason = mb_substr(sanitize_text_field((string) ($input['reason'] ?? '')), 0, 190);
        if ($reason === '') return new WP_Error('mkt_hold_release_reason_required', __('A release reason is required.', 'marketplace'), ['status' => 422]);
        try {
            return MKT_DB::transaction(static function() use ($wpdb, $row, $actor_id, $public_id, $reason) {
                $updated = $wpdb->update(self::table('retention_holds'), [
                    'status' => 'released',
                    'released_by' => $actor_id,
                    'released_at' => MKT_DB::now(),
                    'updated_at' => MKT_DB::now(),
                ], ['id' => (int) $row['id'], 'status' => 'active']);
                if ($updated !== 1) {
                    return new WP_Error('mkt_hold_release_conflict', __('Retention hold changed.', 'marketplace'), ['status' => 409]);
                }
                MKT_Audit::record('retention_hold_released', (string) $row['object_type'], (string) $row['object_public_id'], ['hold_public_id' => $public_id, 'release_reason' => $reason], 'success', '', 'legal_safety_hold');
                return ['public_id' => $public_id, 'status' => 'released'];
            });
        } catch (Throwable $e) {
            return new WP_Error('mkt_hold_release_failed', __('The hold release could not be saved and audited safely.', 'marketplace'), ['status' => 503]);
        }
    }

    /** Governance authorization is tied to the authenticated request actor. */
    private static function require_current_actor(int $actor_id): bool|WP_Error {
        if ($actor_id <= 0 || $actor_id !== (int) get_current_user_id()) {
            return new WP_Error('mkt_actor_mismatch', __('The requested account does not match the authenticated Marketplace actor.', 'marketplace'), ['status' => 403]);
        }
        return true;
    }

    private static function retention_authorization(string $action) {
        $review = MKT_Auth::can('mkt_review_disputes', ['action' => $action]);
        if (!is_wp_error($review)) return $review;
        return MKT_Auth::can('mkt_moderate', ['action' => $action]);
    }

    public static function hold_guard_available(): bool {
        if (!self::table_exists('retention_holds')) return false;
        global $wpdb;
        $wpdb->last_error = '';
        $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . self::table('retention_holds'), 0);
        return is_array($columns) && !array_diff(['object_type','object_public_id','status','expires_at'], $columns)
            && $wpdb->last_error === '';
    }

    public static function has_active_hold(string $object_type, string $object_public_id): bool {
        // Unavailable legal-hold truth must NEVER be interpreted as permission
        // to erase sensitive evidence; fail closed until schema is repaired.
        if (!self::hold_guard_available()) return true;
        global $wpdb;
        $wpdb->last_error = '';
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . self::table('retention_holds') . " WHERE object_type=%s AND object_public_id=%s AND status='active' AND expires_at>UTC_TIMESTAMP()", sanitize_key($object_type), $object_public_id));
        return $count === null || $wpdb->last_error !== '' || (int) $count > 0;
    }

    private static function object_exists(string $type, string $public_id): bool {
        global $wpdb;
        if ($type === 'recall') {
            return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table('recalls') . ' WHERE public_id=%s', $public_id)) === 1;
        }
        $map = ['listing' => 'listings', 'offer' => 'offers', 'deal' => 'deals', 'report' => 'reports', 'dispute' => 'disputes'];
        return isset($map[$type]) && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table($map[$type]) . ' WHERE public_id=%s', $public_id)) === 1;
    }

    public static function augment_rest_response($response, $server, WP_REST_Request $request) {
        if ($request->get_method() !== 'GET' || !($response instanceof WP_REST_Response)) return $response;
        $route = (string) $request->get_route();
        $data = $response->get_data();
        if (!is_array($data)) return $response;
        if (preg_match('#^/marketplace/v1/listings/[0-9a-fA-F-]{36}$#', $route) && !empty($data['public_id'])) {
            $data['evidence'] = self::public_evidence((string) $data['public_id']);
            $data['recall'] = self::public_recall((string) $data['public_id']);
            $response->set_data($data);
        } elseif ($route === '/marketplace/v1/listings' && isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (!is_array($item) || empty($item['public_id'])) continue;
                $item['evidence'] = self::public_evidence((string) $item['public_id']);
                $item['recall'] = self::public_recall((string) $item['public_id']);
            }
            unset($item);
            $response->set_data($data);
        } elseif ($route === '/marketplace/v1/dashboard') {
            $data['seller_studio'] = self::seller_studio(get_current_user_id());
            $response->set_data($data);
        }
        return $response;
    }

    public static function assurance_manifest(array $manifest): array {
        $manifest['governance_schema'] = self::SCHEMA_VERSION;
        $manifest['owned_governance_tables'] = array_values(self::TABLES);
        $manifest['controls']['structured_evidence'] = true;
        $manifest['controls']['time_bounded_retention_holds'] = true;
        $manifest['controls']['recall_takedown'] = true;
        $manifest['controls']['seller_studio_non_ranking'] = true;
        return $manifest;
    }
}
