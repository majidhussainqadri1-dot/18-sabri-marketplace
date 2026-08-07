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
    }

    public static function register_providers(): void {
        do_action('mkt_contract_ready', MKT_Contracts::contract_manifest());
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
            || function_exists('sn_open_context_conversation');
        return self::$status_cache['communication'] = [
            'available' => $available,
            'version' => defined('SABRI_NETWORK_VERSION') ? (string) SABRI_NETWORK_VERSION : '',
        ];
    }

    public static function open_context_conversation(int $actor_id, int $other_user_id, array $context): array|WP_Error {
        if ($actor_id <= 0 || $other_user_id <= 0 || $actor_id === $other_user_id) {
            return new WP_Error('mkt_invalid_conversation_participants', __('Invalid conversation participants.', 'marketplace'), ['status' => 400]);
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
        } else {
            $result = apply_filters('sabri_communication_open_context_conversation', null, $actor_id, $other_user_id, $context);
        }
        if (is_wp_error($result)) return $result;
        if (!is_array($result) || (empty($result['conversation_id']) && empty($result['public_id'])) || empty($result['url'])) {
            return new WP_Error('mkt_communication_unavailable', __('Product-linked conversation is temporarily unavailable.', 'marketplace'), ['status' => 503]);
        }
        return [
            'conversation_id' => sanitize_text_field((string) ($result['conversation_id'] ?? $result['public_id'])),
            'url' => esc_url_raw((string) $result['url']),
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
                'available' => function_exists('sabri_notify_event') || function_exists('sabri_notify_user') || has_filter('sabri_notifications_ingest_event'),
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
