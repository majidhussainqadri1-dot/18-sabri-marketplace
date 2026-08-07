<?php
defined('ABSPATH') || exit;

final class MKT_Rate_Limiter {
    private const LIMITS = [
        'public_search' => [120, 60],
        'public_detail' => [180, 60],
        'create_listing' => [20, 3600],
        'update_listing' => [80, 3600],
        'offer' => [40, 3600],
        'chat' => [30, 3600],
        'report' => [20, 86400],
        'moderation' => [240, 3600],
        'system' => [60, 3600],
    ];

    public static function check(string $bucket, ?int $user_id = null): bool|WP_Error {
        [$limit, $window] = self::LIMITS[$bucket] ?? [60, 60];
        $identity = self::identity($user_id ?? get_current_user_id());
        $slot = (int) floor(time() / $window);
        $key = 'mkt_rl_' . hash_hmac('sha256', $bucket . '|' . $identity . '|' . $slot, wp_salt('nonce'));
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            return new WP_Error('mkt_rate_limited', __('Too many requests. Please try again later.', 'marketplace'), [
                'status' => 429,
                'retry_after' => max(1, ($slot + 1) * $window - time()),
            ]);
        }
        set_transient($key, $count + 1, $window + 5);
        return true;
    }

    private static function identity(int $user_id): string {
        if ($user_id > 0) {
            return 'u:' . $user_id;
        }
        $remote = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
        $ip = (string) apply_filters('mkt_rate_limit_client_ip', $remote, $_SERVER);
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
        return 'g:' . hash_hmac('sha256', $ip, wp_salt('auth'));
    }
}
