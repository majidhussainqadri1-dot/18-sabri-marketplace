<?php
/**
 * Central platform integration adapters.
 *
 * File 18 must not duplicate identity, notification, or application-shell
 * responsibilities owned by Files 00, 19, and 20.
 */
defined('ABSPATH') || exit;

final class SMP_Integrations {
    public static function init(): void {
        add_filter('sabri_shell_layout_mode', [self::class, 'shell_layout_mode'], 20, 2);
        add_filter('sabri_shell_navigation_destinations', [self::class, 'shell_destination']);
        add_action('admin_init', [self::class, 'invalidate_shell_navigation_cache']);
    }

    public static function membership_active(): bool {
        return function_exists('smc_get_profile')
            && function_exists('smc_user_status')
            && class_exists('SMC_Security');
    }

    public static function notifications_active(): bool {
        return function_exists('sabri_notify_user') && class_exists('SUN_Core');
    }

    public static function shell_active(): bool {
        return class_exists('Sabri\\UnifiedShell\\Layout')
            && class_exists('Sabri\\UnifiedShell\\Navigation');
    }

    public static function approved_member(int $user_id): bool {
        if ($user_id <= 0 || !self::membership_active()) return false;
        return in_array((string) smc_user_status($user_id), ['approved', 'verified'], true);
    }

    public static function profile(int $user_id): array {
        if (!self::membership_active() || $user_id <= 0) return [];
        $profile = smc_get_profile($user_id);
        return is_array($profile) ? $profile : [];
    }

    public static function membership_contact(int $user_id): array {
        $profile = self::profile($user_id);
        $phone = isset($profile['phone']) ? (string) $profile['phone'] : (string) get_user_meta($user_id, '_smc_phone', true);
        $whatsapp = $phone;

        global $wpdb;
        if (self::membership_active()) {
            $clinic = $wpdb->get_row($wpdb->prepare(
                "SELECT phone,whatsapp FROM {$wpdb->prefix}smc_clinics WHERE owner_user_id=%d ORDER BY id DESC LIMIT 1",
                $user_id
            ), ARRAY_A);
            if (is_array($clinic)) {
                $phone = (string) ($clinic['phone'] ?: $phone);
                $whatsapp = (string) ($clinic['whatsapp'] ?: $phone);
            }
        }

        return [
            'phone' => SMP_Utils::sanitize_phone($phone),
            'whatsapp' => SMP_Utils::sanitize_phone($whatsapp),
            'mobileVerified' => (bool) get_user_meta($user_id, '_smc_mobile_verified', true),
            'identityVerified' => (bool) get_user_meta($user_id, '_smc_identity_verified', true),
        ];
    }

    public static function seller_eligibility(int $user_id): array {
        if (!self::membership_active()) {
            return ['eligible' => false, 'code' => 'membership_missing', 'message' => 'Sabri Membership Core is required before Marketplace selling can be enabled.'];
        }
        if (!self::approved_member($user_id)) {
            return ['eligible' => false, 'code' => 'membership_unapproved', 'message' => 'Your central Sabri membership must be approved before you can sell.'];
        }
        $profile = self::profile($user_id);
        if (!$profile) {
            return ['eligible' => false, 'code' => 'profile_missing', 'message' => 'Complete your central Sabri membership profile before applying as a seller.'];
        }
        $contact = self::membership_contact($user_id);
        if (!$contact['mobileVerified']) {
            return ['eligible' => false, 'code' => 'mobile_unverified', 'message' => 'Your mobile number must be verified in Sabri Membership Core before selling.'];
        }
        return ['eligible' => true, 'code' => 'eligible', 'message' => 'Eligible'];
    }

    public static function health_license_verified(int $user_id): bool {
        if (!self::approved_member($user_id)) return false;
        if (!get_user_meta($user_id, '_smc_identity_verified', true)) return false;

        $role = (string) get_user_meta($user_id, '_smc_requested_role', true);
        $allowed_roles = ['sabri_doctor', 'sabri_clinic', 'sabri_pharmacy'];
        if (!in_array($role, $allowed_roles, true)) return false;

        if ($role === 'sabri_doctor' && !get_user_meta($user_id, '_smc_doctor_verified', true)) return false;
        if ($role === 'sabri_clinic' && !get_user_meta($user_id, '_smc_clinic_verified', true)) return false;

        global $wpdb;
        $license = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(NULLIF(license_number,''),registration_number) FROM {$wpdb->prefix}smc_professional_credentials WHERE user_id=%d ORDER BY id DESC LIMIT 1",
            $user_id
        ));
        return trim($license) !== '';
    }

    public static function encrypt_sensitive(string $plain, string $purpose = 'marketplace-seller'): string|WP_Error {
        if ($plain === '') return '';
        if (!self::membership_active()) return new WP_Error('smp_membership_crypto_missing', 'Sabri Membership Core encryption is unavailable.');
        $encrypted = SMC_Security::encrypt($plain, $purpose);
        return is_wp_error($encrypted) ? $encrypted : 'smc:' . $encrypted;
    }

    public static function decrypt_sensitive(string $value, string $purpose = 'marketplace-seller'): string|false {
        if ($value === '') return '';
        if (!str_starts_with($value, 'smc:') || !self::membership_active()) return false;
        return SMC_Security::decrypt(substr($value, 4), $purpose);
    }

    public static function mask_sensitive(string $value, int $visible = 4): string {
        $plain = self::decrypt_sensitive($value);
        if ($plain === false) return '••••';
        if (function_exists('smc_mask')) return smc_mask($plain, $visible);
        $length = strlen($plain);
        return $length <= $visible ? str_repeat('•', max(4, $length)) : str_repeat('•', max(4, $length - $visible)) . substr($plain, -$visible);
    }

    public static function notify(array $notification): int {
        if (!self::notifications_active()) return 0;
        $notification = wp_parse_args($notification, [
            'category' => 'marketplace',
            'priority' => 'normal',
            'source' => 'sabri_marketplace',
        ]);
        return (int) sabri_notify_user($notification);
    }

    public static function notifications_url(): string {
        if (class_exists('SUN_Utils') && method_exists('SUN_Utils', 'page_url')) {
            return (string) SUN_Utils::page_url();
        }
        if (function_exists('smc_page_url')) return (string) smc_page_url('sabri_notifications', '/notifications/');
        return home_url('/notifications/');
    }

    public static function shell_layout_mode($mode, $settings = null) {
        if (SMP_Utils::is_marketplace_request()) return 'two';
        return $mode;
    }

    public static function shell_destination(array $destinations): array {
        if (!isset($destinations['marketplace'])) $destinations['marketplace'] = [];
        $destinations['marketplace'] = array_replace($destinations['marketplace'], [
            'label' => __('Marketplace', 'sabri-marketplace'),
            'group' => 'platform',
            'slugs' => ['marketplace'],
            'shortcodes' => ['sabri_marketplace'],
            'post_type' => '',
            'order' => 140,
        ]);
        return $destinations;
    }

    public static function invalidate_shell_navigation_cache(): void {
        if (defined('SABRI_UNIFIED_SHELL_VERSION') || self::shell_active()) {
            delete_transient('sabri_shell_navigation_cache_v1');
        }
    }

    public static function status(): array {
        return [
            'membership' => self::membership_active(),
            'notifications' => self::notifications_active(),
            'shell' => self::shell_active(),
        ];
    }
}
