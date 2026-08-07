<?php
defined('ABSPATH') || exit;

final class MKT_Auth {
    public static function require_login(): bool|WP_Error {
        return is_user_logged_in() ? true : new WP_Error('mkt_authentication_required', __('Please sign in to continue.', 'marketplace'), ['status' => 401]);
    }

    public static function assertions(?int $user_id = null): array {
        return MKT_Integrations::identity_assertions($user_id ?? get_current_user_id());
    }

    public static function can(string $capability, array $context = []): bool|WP_Error {
        $login = self::require_login();
        if (is_wp_error($login)) {
            return $login;
        }
        $user_id = get_current_user_id();
        $assertions = self::assertions($user_id);
        if (!$assertions['available']) {
            return new WP_Error('mkt_identity_provider_unavailable', __('Identity verification is temporarily unavailable.', 'marketplace'), ['status' => 503]);
        }
        if (!$assertions['approved'] || !$assertions['verified'] || $assertions['suspended']) {
            return new WP_Error('mkt_account_not_eligible', __('An approved and verified account is required for this action.', 'marketplace'), ['status' => 403]);
        }
        if (in_array((string) $assertions['risk_state'], ['blocked','high','critical'], true)) {
            return new WP_Error('mkt_account_risk_hold', __('This action is unavailable while the account is under a marketplace risk hold.', 'marketplace'), ['status' => 403]);
        }
        if (!empty($assertions['is_minor']) && empty($assertions['guardian_verified'])) {
            return new WP_Error('mkt_guardian_required', __('Verified guardian consent is required for this action.', 'marketplace'), ['status' => 403]);
        }

        $allowed = current_user_can($capability)
            || in_array($capability, (array) $assertions['capabilities'], true)
            || apply_filters('mkt_authorize_capability', false, $user_id, $capability, $context, $assertions);
        if (!$allowed) {
            return new WP_Error('mkt_forbidden', __('You are not authorized to perform this action.', 'marketplace'), ['status' => 403]);
        }
        return true;
    }

    public static function seller(int $user_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('sellers') . ' WHERE user_id=%d', $user_id), ARRAY_A);
        return $row ?: null;
    }

    public static function seller_eligibility(int $user_id): array {
        $assertions = self::assertions($user_id);
        $reasons = [];
        if (!$assertions['available']) {
            $reasons[] = 'identity_provider_unavailable';
        }
        if (!$assertions['approved']) {
            $reasons[] = 'account_unapproved';
        }
        if (!$assertions['verified']) {
            $reasons[] = 'account_unverified';
        }
        if ($assertions['suspended']) {
            $reasons[] = 'account_suspended';
        }
        if (!empty($assertions['is_minor']) && empty($assertions['guardian_verified'])) {
            $reasons[] = 'guardian_unverified';
        }
        if (in_array((string) $assertions['risk_state'], ['blocked','high','critical'], true)) {
            $reasons[] = 'risk_hold';
        }
        $eligible = !$reasons && (user_can($user_id, 'mkt_sell') || in_array('mkt_sell', (array) $assertions['capabilities'], true) || apply_filters('mkt_seller_eligibility', false, $user_id, $assertions));
        if (!$eligible && !$reasons) {
            $reasons[] = 'seller_capability_missing';
        }
        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'assertions' => $assertions,
        ];
    }

    public static function own_listing(array $listing, int $user_id): bool {
        $seller = self::seller($user_id);
        return $seller && (int) $listing['seller_id'] === (int) $seller['id'];
    }

    public static function deal_participant(array $deal, int $user_id): bool {
        return in_array($user_id, [(int) $deal['buyer_user_id'], (int) $deal['seller_user_id']], true);
    }
}
