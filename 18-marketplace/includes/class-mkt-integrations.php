<?php
defined('ABSPATH') || exit;

final class MKT_Integrations {
    private static array $status_cache = [];

    public static function boot(): void {
        add_filter('sabri_shell_navigation_destinations', [self::class, 'shell_navigation']);
        add_filter('sabri_shell_layout_mode', [self::class, 'shell_layout'], 20, 2);
        add_filter('sabri_search_providers', [self::class, 'search_provider']);
        add_action('sabri_platform_contract_registry', [self::class, 'contract_registry']);
        add_action('sabri_assurance_register_controls', [self::class, 'assurance_controls']);
        // File 17 owns all conversations. These filters authorize a File 18
        // product context only; they never create a second messages store.
        add_filter('sn_network_context_authorize', [self::class, 'file17_context_authorize'], 10, 6);
        add_filter('sn_network_context_projection', [self::class, 'file17_context_projection'], 10, 5);
    }

    public static function register_providers(): void {
        // File 19 owns notification projection and delivery. Register this domain
        // producer with File 19's actual sun.event.v1 contract when available.
        self::register_notification_producer();
        do_action('mkt_contract_ready', MKT_Contracts::contract_manifest());
    }

    private static function register_notification_producer(): bool {
        if (!function_exists('sun_register_notification_producer')) return false;
        return (bool) sun_register_notification_producer('file-18-marketplace', [
            'owner' => 'File 18',
            'event_types' => ['Marketplace.*'],
            'schema_versions' => ['1.0.0'],
            'internal' => true,
            'allowed_data_fields' => ['summary','status','object_name'],
        ]);
    }

    public static function identity_assertions(int $user_id): array {
        $empty = [
            'available' => false,
            'compatible' => false,
            'user_id' => $user_id,
            'platform_uuid' => '',
            'status' => 'unknown',
            'approved' => false,
            'verified' => false,
            'suspended' => false,
            'age' => null,
            'is_minor' => null,
            'guardian_verified' => false,
            'risk_state' => 'unknown',
            'capabilities' => [],
            'roles' => [],
            'version' => '',
            'contract_mode' => 'unavailable',
        ];
        if ($user_id <= 0) return $empty;

        $raw = apply_filters('sabri_identity_assertions', null, $user_id, 'marketplace');
        if (!is_array($raw) && function_exists('smc_get_marketplace_assertions')) {
            $raw = smc_get_marketplace_assertions($user_id);
        }
        if (!is_array($raw) && function_exists('smc_get_profile') && function_exists('smc_user_status')) {
            $profile = smc_get_profile($user_id);
            $raw = [
                'available' => true,
                'compatible' => true,
                'status' => (string) smc_user_status($user_id),
                'platform_uuid' => is_array($profile) ? (string) ($profile['platform_uuid'] ?? '') : '',
                'age' => is_array($profile) && isset($profile['age']) ? (int) $profile['age'] : null,
                'is_minor' => is_array($profile) && array_key_exists('is_minor', $profile) ? (bool) $profile['is_minor'] : null,
                'guardian_verified' => is_array($profile) && !empty($profile['guardian_verified']),
                'risk_state' => is_array($profile) ? (string) ($profile['risk_state'] ?? 'unknown') : 'unknown',
                'capabilities' => is_array($profile) && isset($profile['capabilities']) && is_array($profile['capabilities']) ? $profile['capabilities'] : [],
                'roles' => is_array($profile) && isset($profile['roles']) && is_array($profile['roles']) ? $profile['roles'] : [],
                'version' => defined('SMC_VERSION') ? (string) SMC_VERSION : 'legacy',
                'contract_mode' => 'legacy_compat',
            ];
        }
        if (!is_array($raw)) return $empty;

        // An identity provider may explicitly declare itself unavailable/degraded.
        // Never overwrite that signal with local optimism.
        if (array_key_exists('available', $raw) && !$raw['available']) {
            $unavailable = array_replace($empty, $raw);
            $unavailable['available'] = false;
            $unavailable['compatible'] = false;
            $unavailable['user_id'] = $user_id;
            return $unavailable;
        }

        $assertions = array_replace($empty, $raw);
        $assertions['user_id'] = $user_id;
        $assertions['status'] = sanitize_key((string) $assertions['status']);
        $allowed_statuses = ['pending','approved','verified','active','limited','suspended','banned','revoked','rejected','expired'];
        if (!in_array($assertions['status'], $allowed_statuses, true)) {
            $assertions['available'] = false;
            $assertions['compatible'] = false;
            $assertions['status'] = 'unknown';
            $assertions['contract_mode'] = 'malformed';
            return $assertions;
        }

        $assertions['roles'] = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $assertions['roles']))));
        $assertions['capabilities'] = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $assertions['capabilities']))));
        $assertions['risk_state'] = sanitize_key((string) $assertions['risk_state']);
        if (!in_array($assertions['risk_state'], ['low','normal','medium','elevated','high','critical','blocked','unknown'], true)) {
            $assertions['risk_state'] = 'unknown';
        }
        $assertions['approved'] = !empty($assertions['approved']) || in_array($assertions['status'], ['approved','verified','active'], true);
        $assertions['verified'] = !empty($assertions['verified']) || $assertions['status'] === 'verified';
        $assertions['suspended'] = !empty($assertions['suspended']) || in_array($assertions['status'], ['suspended','banned','revoked'], true);
        $assertions['guardian_verified'] = (bool) $assertions['guardian_verified'];
        if ($assertions['is_minor'] !== null) $assertions['is_minor'] = (bool) $assertions['is_minor'];
        if ($assertions['age'] !== null) $assertions['age'] = max(0, min(130, (int) $assertions['age']));
        $assertions['version'] = sanitize_text_field((string) $assertions['version']);

        $provider_compatible = array_key_exists('compatible', $raw) ? (bool) $raw['compatible'] : true;
        $provider_compatible = (bool) apply_filters('mkt_identity_contract_compatible', $provider_compatible, $assertions['version'], $raw);
        $assertions['compatible'] = $provider_compatible;
        $assertions['available'] = $provider_compatible;
        $assertions['contract_mode'] = sanitize_key((string) ($assertions['contract_mode'] ?: ($assertions['version'] !== '' ? 'versioned' : 'legacy_compat')));
        return $assertions;
    }

    public static function communication_status(): array {
        if (isset(self::$status_cache['communication'])) return self::$status_cache['communication'];
        $available = has_filter('sabri_communication_open_context_conversation')
            || function_exists('sabri_network_open_context_conversation')
            || function_exists('sn_open_context_conversation')
            || self::native_file17_available();
        return self::$status_cache['communication'] = [
            'available' => $available,
            'version' => defined('SABRI_NETWORK_VERSION') ? (string) SABRI_NETWORK_VERSION : '',
        ];
    }

    public static function open_context_conversation(int $actor_id, int $other_user_id, array $context): array|WP_Error {
        if ($actor_id <= 0 || $other_user_id <= 0 || $actor_id === $other_user_id) {
            return new WP_Error('mkt_invalid_conversation_participants', __('Invalid conversation participants.', 'marketplace'), ['status' => 400]);
        }

        // File 17 owns the conversation, but Marketplace owns seller eligibility.
        // Recheck the seller at the handoff boundary so lagging projections/events
        // cannot start new commerce contact after suspension or verification loss.
        $seller = MKT_Auth::seller_eligibility($other_user_id);
        if (empty($seller['eligible'])) {
            return new WP_Error('mkt_seller_contact_unavailable', __('The seller is not currently eligible for marketplace contact.', 'marketplace'), ['status' => 409]);
        }

        $context = [
            'type' => 'marketplace_listing',
            'provider' => 'file-18-marketplace',
            'provider_version' => MKT_CONTRACT_VERSION,
            'object_public_id' => sanitize_text_field((string) ($context['object_public_id'] ?? '')),
            'title' => sanitize_text_field((string) ($context['title'] ?? '')),
            'url' => esc_url_raw((string) ($context['url'] ?? '')),
            'price' => (string) ($context['price'] ?? ''),
            'currency' => sanitize_key((string) ($context['currency'] ?? '')),
            'status' => sanitize_key((string) ($context['status'] ?? '')),
            'snapshot_hash' => sanitize_text_field((string) ($context['snapshot_hash'] ?? '')),
        ];
        if (function_exists('sabri_network_open_context_conversation')) {
            $result = sabri_network_open_context_conversation($actor_id, $other_user_id, $context);
        } elseif (function_exists('sn_open_context_conversation')) {
            $result = sn_open_context_conversation($actor_id, $other_user_id, $context);
        } elseif (has_filter('sabri_communication_open_context_conversation')) {
            $result = apply_filters('sabri_communication_open_context_conversation', null, $actor_id, $other_user_id, $context);
        } elseif (self::native_file17_available()) {
            $result = self::native_file17_handoff($actor_id, $other_user_id, $context);
        } else {
            $result = null;
        }
        if (is_wp_error($result)) return $result;
        if (!is_array($result) || (empty($result['conversation_id']) && empty($result['public_id'])) || empty($result['url'])) {
            return new WP_Error('mkt_communication_unavailable', __('Product-linked conversation is temporarily unavailable.', 'marketplace'), ['status' => 503]);
        }

        // File 17 is an internal platform owner. A provider result may never turn the
        // Marketplace action into an open redirect or external deep-link handoff.
        $url = esc_url_raw((string) $result['url']);
        $home_parts = wp_parse_url(home_url('/'));
        $target_parts = wp_parse_url($url);
        $same_origin = is_array($home_parts) && is_array($target_parts)
            && !empty($home_parts['host']) && !empty($target_parts['host'])
            && !empty($home_parts['scheme']) && !empty($target_parts['scheme'])
            && strtolower((string) $home_parts['scheme']) === strtolower((string) $target_parts['scheme'])
            && hash_equals(strtolower((string) $home_parts['host']), strtolower((string) $target_parts['host']))
            && (int) ($home_parts['port'] ?? (strtolower((string) $home_parts['scheme']) === 'https' ? 443 : 80))
                === (int) ($target_parts['port'] ?? (strtolower((string) $target_parts['scheme']) === 'https' ? 443 : 80))
            && !isset($target_parts['user']) && !isset($target_parts['pass']);
        if ($url === '' || !$same_origin) {
            return new WP_Error('mkt_communication_url_invalid', __('Product-linked conversation returned an invalid destination.', 'marketplace'), ['status' => 503]);
        }

        return [
            'conversation_id' => sanitize_text_field((string) ($result['conversation_id'] ?? $result['public_id'])),
            'url' => $url,
        ];
    }

    private static function native_file17_available(): bool {
        return function_exists('rest_do_request')
            && class_exists('WP_REST_Request')
            && class_exists('SN_REST')
            && class_exists('SN_Context_Adapters')
            && class_exists('SN_Messages')
            && method_exists('SN_Messages', 'messages_url');
    }

    private static function native_file17_handoff(int $actor_id, int $seller_id, array $context): array|WP_Error {
        // Re-dispatch through File 17's real REST permission, privacy and
        // messaging policy checks; do not call its internal DB writers.
        if (!self::native_file17_available() || get_current_user_id() !== $actor_id) {
            return new WP_Error('mkt_communication_actor_invalid', __('The conversation must be opened by its authenticated buyer.', 'marketplace'), ['status' => 403]);
        }
        $object = (string) ($context['object_public_id'] ?? '');
        if ($object === '' || !MKT_Listings::get($object, false)) {
            return new WP_Error('mkt_communication_listing_unavailable', __('This listing is no longer available for conversation.', 'marketplace'), ['status' => 404]);
        }
        $create = new WP_REST_Request('POST', '/sabri-network/v2/conversations');
        $create->set_param('type', 'direct');
        $create->set_param('user_id', $seller_id);
        $response = rest_do_request($create);
        if (is_wp_error($response) || !is_object($response) || !method_exists($response, 'get_status') || (int) $response->get_status() >= 400) {
            return new WP_Error('mkt_file17_conversation_failed', __('The communication provider could not authorize this conversation.', 'marketplace'), ['status' => 503]);
        }
        $payload = (array) $response->get_data();
        $conversation_id = (int) ($payload['conversation']['id'] ?? 0);
        if ($conversation_id <= 0) {
            return new WP_Error('mkt_file17_conversation_malformed', __('The communication provider returned an invalid conversation.', 'marketplace'), ['status' => 502]);
        }
        // A repeated handoff returns the same direct conversation, and File 17
        // upserts the context under its conversation/provider/object constraint.
        $attach = new WP_REST_Request('POST', '/sabri-network/v2/conversations/' . $conversation_id . '/contexts');
        $attach->set_param('provider', 'file18_marketplace');
        $attach->set_param('provider_object_id', $object);
        $attach->set_param('provider_version', MKT_CONTRACT_VERSION);
        $attach->set_param('purpose', 'product_enquiry');
        $attached = rest_do_request($attach);
        if (is_wp_error($attached) || !is_object($attached) || !method_exists($attached, 'get_status') || (int) $attached->get_status() >= 400) {
            return new WP_Error('mkt_file17_context_failed', __('The listing context could not be attached safely. Retry the request.', 'marketplace'), ['status' => 503]);
        }
        return ['conversation_id' => (string) $conversation_id, 'url' => SN_Messages::messages_url($conversation_id)];
    }

    public static function file17_context_authorize($previous, string $provider, string $object, int $conversation, int $actor, string $action) {
        if ($provider !== 'file18_marketplace') return $previous;
        $listing = MKT_Listings::get($object, false);
        if (!$listing || !class_exists('SN_DB') || !SN_DB::is_member($conversation, $actor)
            || !SN_DB::is_member($conversation, (int) $listing['seller_user_id'])) {
            return new WP_Error('mkt_file17_context_forbidden', __('The listing conversation context is unavailable.', 'marketplace'), ['status' => 403]);
        }
        $seller = MKT_Auth::seller_eligibility((int) $listing['seller_user_id']);
        return !empty($seller['eligible']) ? true
            : new WP_Error('mkt_file17_seller_unavailable', __('The listing seller is not currently eligible for contact.', 'marketplace'), ['status' => 409]);
    }

    public static function file17_context_projection($previous, string $provider, string $object, int $conversation, int $actor) {
        if ($provider !== 'file18_marketplace') return $previous;
        $permission = self::file17_context_authorize(null, $provider, $object, $conversation, $actor, 'read');
        if ($permission !== true) return null;
        $listing = MKT_Listings::get($object, false);
        if (!$listing) return null;
        return [
            'url' => MKT_Listings::url($listing),
            'label' => (string) $listing['title'],
            'status' => 'available',
            'summary' => __('Marketplace listing', 'marketplace'),
        ];
    }

    public static function notify(int $user_id, string $event_type, array $context, string $dedupe_key): bool {
        if ($user_id <= 0) return false;
        $event = [
            'recipient_user_id' => $user_id,
            'event_type' => preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $event_type) ? $event_type : 'MarketplaceEvent.v1',
            'category' => 'marketplace',
            'priority' => (string) ($context['priority'] ?? 'normal'),
            'source' => 'file-18-marketplace',
            'source_version' => MKT_CONTRACT_VERSION,
            'dedupe_key' => sanitize_text_field($dedupe_key),
            'context' => $context,
        ];
        if (function_exists('sun_ingest_domain_event')) {
            // Legacy sabri_notify_* shapes are not File 19's canonical contract.
            // sun.event.v1 requires a registered owner, dotted fact name,
            // immutable event ID, schema version, timestamp and recipients.
            if (!self::register_notification_producer()) return false;
            if (!preg_match('/^Marketplace([A-Za-z0-9]+)\\.v[0-9]+$/', (string) $event['event_type'], $matches)) {
                return false;
            }
            $source_event = [
                'producer' => 'file-18-marketplace',
                'owner' => 'File 18',
                'event_id' => sanitize_text_field($dedupe_key),
                'event_type' => 'Marketplace.' . $matches[1],
                'schema_version' => '1.0.0',
                'occurred_at' => gmdate('c'),
                'recipients' => [['user_id' => $user_id]],
                'category' => 'marketplace',
                'priority' => sanitize_key((string) $event['priority']),
                'source_version' => MKT_CONTRACT_VERSION,
                'idempotency_key' => sanitize_text_field($dedupe_key),
                'trace_id' => MKT_Audit::trace_id(),
                'deep_link' => esc_url_raw((string) ($context['url'] ?? '')),
                'data' => ['summary' => sanitize_text_field((string) ($context['safe_summary'] ?? 'Marketplace update.'))],
            ];
            $receipt = sun_ingest_domain_event($source_event);
            return !is_wp_error($receipt) && is_array($receipt)
                && in_array((string) ($receipt['status'] ?? ''), ['processed','duplicate'], true);
        }
        if (function_exists('sabri_notify_event')) return (bool) sabri_notify_event($event);
        if (function_exists('sabri_notify_user')) return (bool) sabri_notify_user($event);
        return (bool) apply_filters('sabri_notifications_ingest_event', false, $event);
    }

    public static function media_reference(array $input, int $actor_id): array|WP_Error {
        $result = apply_filters('sabri_media_create_reference', null, $input, $actor_id, 'marketplace');
        if (is_wp_error($result)) return $result;
        if (!is_array($result) || empty($result['public_id'])) {
            return new WP_Error('mkt_media_provider_unavailable', __('The secure media provider is unavailable.', 'marketplace'), ['status' => 503]);
        }
        return [
            'public_id' => sanitize_text_field((string) $result['public_id']),
            'kind' => sanitize_key((string) ($result['kind'] ?? 'image')),
            'url' => esc_url_raw((string) ($result['url'] ?? '')),
            'thumbnail_url' => esc_url_raw((string) ($result['thumbnail_url'] ?? '')),
            'rights_status' => sanitize_key((string) ($result['rights_status'] ?? 'pending')),
            'scan_status' => sanitize_key((string) ($result['scan_status'] ?? 'pending')),
            'metadata' => is_array($result['metadata'] ?? null) ? $result['metadata'] : [],
        ];
    }

    public static function shell_owns_context_navigation(): bool {
        $present = defined('SABRI_UNIFIED_SHELL_VERSION')
            || class_exists('Sabri\\UnifiedShell\\Layout')
            || function_exists('sabri_unified_shell_register_destination');
        return (bool) apply_filters('mkt_shell_owns_context_navigation', $present, 'marketplace');
    }

    public static function shell_navigation(array $destinations): array {
        $destinations['marketplace'] = [
            'label' => __('Marketplace', 'marketplace'), 'url' => home_url('/marketplace/'), 'icon' => 'store', 'group' => 'platform',
            'slugs' => ['marketplace'], 'shortcodes' => [], 'post_type' => '', 'order' => 180, 'owner' => 'file-18-marketplace',
        ];
        return $destinations;
    }

    public static function shell_layout($mode, $context = null) {
        return MKT_Routes::is_marketplace_request() ? 'two' : $mode;
    }

    public static function search_provider(array $providers): array {
        $providers['marketplace'] = [
            'owner' => 'file-18-marketplace', 'version' => MKT_CONTRACT_VERSION, 'label' => __('Marketplace', 'marketplace'),
            'query_callback' => ['MKT_Listings', 'search_provider_query'], 'public' => true,
        ];
        return $providers;
    }

    public static function contract_registry($registry = null): void {
        do_action('sabri_platform_register_contract', 'file-18-marketplace', MKT_Contracts::contract_manifest());
    }

    public static function assurance_controls(): void {
        do_action('sabri_assurance_register_native_control', [
            'owner' => 'file-18-marketplace',
            'controls' => ['authorization','listing-policy','zero-commission','deal-integrity','privacy','moderation','audit','retention','structured-evidence','recall-takedown'],
            'status_callback' => ['MKT_Admin', 'system_status'],
        ]);
    }

    public static function status(): array {
        $identity = self::identity_assertions(get_current_user_id());
        return [
            'identity' => ['available' => $identity['available'], 'compatible' => $identity['compatible'], 'version' => $identity['version'], 'contract_mode' => $identity['contract_mode']],
            'communication' => self::communication_status(),
            'notifications' => [
                'available' => (function_exists('sun_ingest_domain_event') && function_exists('sun_register_notification_producer'))
                    || function_exists('sabri_notify_event') || function_exists('sabri_notify_user') || has_filter('sabri_notifications_ingest_event'),
                'version' => defined('SUN_VERSION') ? (string) SUN_VERSION : '',
            ],
            'shell' => [
                'available' => defined('SABRI_UNIFIED_SHELL_VERSION') || class_exists('Sabri\\UnifiedShell\\Layout') || function_exists('sabri_unified_shell_register_destination'),
                'version' => defined('SABRI_UNIFIED_SHELL_VERSION') ? (string) SABRI_UNIFIED_SHELL_VERSION : '',
            ],
            'visual' => [
                'available' => defined('SABRI_VISUAL_SYSTEM_VERSION') || has_filter('sabri_design_tokens'),
                'version' => defined('SABRI_VISUAL_SYSTEM_VERSION') ? (string) SABRI_VISUAL_SYSTEM_VERSION : '',
            ],
        ];
    }
}