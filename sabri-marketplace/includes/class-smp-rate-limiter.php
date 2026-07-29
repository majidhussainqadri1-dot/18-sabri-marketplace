<?php
/** Request-level abuse controls. */
defined('ABSPATH') || exit;

final class SMP_Rate_Limiter {
    private const RULES = [
        'bootstrap' => [120, 60],
        'product' => [120, 60],
        'store' => [90, 60],
        'seller_apply' => [3, 3600],
        'buyer_contact_save' => [10, 3600],
        'product_save' => [10, 3600],
        'product_status' => [30, 3600],
        'wishlist_toggle' => [60, 60],
        'contact_reveal' => [20, 3600],
        'conversation_start' => [20, 3600],
        'conversations' => [120, 60],
        'messages' => [180, 60],
        'message_send' => [60, 60],
        'message_edit' => [30, 60],
        'message_delete' => [30, 60],
        'reaction_toggle' => [60, 60],
        'typing' => [120, 60],
        'offer_action' => [30, 60],
        'share_contact' => [10, 3600],
        'conversation_status' => [30, 3600],
        'block_toggle' => [20, 3600],
        'report_submit' => [10, 3600],
        'seller_dashboard' => [60, 60],
    ];

    public static function enforce(string $operation): void {
        $rule = self::RULES[$operation] ?? [60, 60];
        $identity = get_current_user_id() > 0
            ? 'u:' . get_current_user_id()
            : 'ip:' . hash('sha256', SMP_Utils::client_ip());
        $key = 'smp_rl_' . substr(hash('sha256', $operation . '|' . $identity), 0, 40);

        $bucket = get_transient($key);
        if (!is_array($bucket) || empty($bucket['expires']) || (int) $bucket['expires'] <= time()) {
            set_transient($key, ['count' => 1, 'expires' => time() + (int) $rule[1]], (int) $rule[1]);
            return;
        }

        $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;
        $ttl = max(1, (int) $bucket['expires'] - time());
        set_transient($key, $bucket, $ttl);
        if ($bucket['count'] > (int) $rule[0]) {
            header('Retry-After: ' . $ttl);
            wp_send_json_error(['message' => 'Too many Marketplace requests. Please wait and try again.'], 429);
        }
    }
}
