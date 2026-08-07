<?php
defined('ABSPATH') || exit;

/**
 * CSRF boundary for Marketplace REST mutations.
 *
 * Cookie-authenticated requests must present a valid wp_rest nonce. The only
 * nonce-less first-party authentication mode accepted here is a WordPress
 * Application Password that has actually authenticated, not merely the
 * presence of an arbitrary Authorization header.
 */
final class MKT_REST_Security {
    public static function boot(): void {
        add_filter('rest_pre_dispatch', [self::class, 'enforce'], 5, 3);
    }

    public static function enforce($result, WP_REST_Server $server, WP_REST_Request $request) {
        $route = (string) $request->get_route();
        if (!str_starts_with($route, '/' . MKT_Contracts::REST_NAMESPACE . '/')) {
            return $result;
        }

        $method = strtoupper((string) $request->get_method());
        if (in_array($method, ['GET','HEAD','OPTIONS'], true)) {
            return $result;
        }

        // WordPress fires this action only after successful Application Password
        // authentication. Header presence by itself is deliberately insufficient.
        if (did_action('application_password_did_authenticate') > 0) {
            return $result;
        }

        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'mkt_invalid_nonce',
                __('Security token validation failed.', 'marketplace'),
                ['status' => 403]
            );
        }

        return $result;
    }
}
