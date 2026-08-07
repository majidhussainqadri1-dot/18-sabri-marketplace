<?php
defined('ABSPATH') || exit;

final class MKT_Privacy {
    public static function init(): void {
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'exporters']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'erasers']);
        add_action('plugins_loaded', [self::class, 'register_policy'], 50);
    }

    public static function register_policy(): void {
        if (!function_exists('wp_add_privacy_policy_content')) return;
        wp_add_privacy_policy_content(__('Sabri Marketplace', 'marketplace'), wp_kses_post(wpautop(__('Marketplace stores seller eligibility projections, listings, saves, structured offers, direct-deal snapshots, safety reports, disputes and audit records. Private records are restricted to participants or purpose-limited reviewers. Conversations and message contents are owned by the platform communication module, not by Marketplace. The platform charges 0% commission.', 'marketplace'))));
    }

    public static function exporters(array $exporters): array {
        $exporters['mkt-marketplace'] = ['exporter_friendly_name' => __('Sabri Marketplace', 'marketplace'), 'callback' => [self::class, 'export']];
        return $exporters;
    }

    public static function erasers(array $erasers): array {
        $erasers['mkt-marketplace'] = ['eraser_friendly_name' => __('Sabri Marketplace', 'marketplace'), 'callback' => [self::class, 'erase']];
        return $erasers;
    }

    public static function export(string $email_address, int $page = 1): array {
        $user = get_user_by('email', $email_address);
        if (!$user) return ['data' => [], 'done' => true];
        $user_id = (int) $user->ID;
        global $wpdb;
        $data = [];
        $seller = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('sellers') . ' WHERE user_id=%d', $user_id), ARRAY_A);
        if ($seller) $data[] = self::group('seller', $seller['public_id'], $seller, ['eligibility_snapshot']);
        $listings = $wpdb->get_results($wpdb->prepare('SELECT l.* FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id WHERE s.user_id=%d', $user_id), ARRAY_A);
        foreach ($listings as $row) $data[] = self::group('listing', $row['public_id'], $row, ['policy_snapshot','declarations']);
        $offers = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('offers') . ' WHERE buyer_user_id=%d OR seller_user_id=%d', $user_id, $user_id), ARRAY_A);
        foreach ($offers as $row) $data[] = self::group('offer', $row['public_id'], $row, ['idempotency_key']);
        $deals = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('deals') . ' WHERE buyer_user_id=%d OR seller_user_id=%d', $user_id, $user_id), ARRAY_A);
        foreach ($deals as $row) $data[] = self::group('deal', $row['public_id'], $row, []);
        $reports = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('reports') . ' WHERE reporter_user_id=%d', $user_id), ARRAY_A);
        foreach ($reports as $row) $data[] = self::group('report', $row['public_id'], $row, []);
        $disputes = $wpdb->get_results($wpdb->prepare('SELECT x.* FROM ' . MKT_DB::table('disputes') . ' x INNER JOIN ' . MKT_DB::table('deals') . ' d ON d.id=x.deal_id WHERE d.buyer_user_id=%d OR d.seller_user_id=%d OR x.opened_by=%d', $user_id, $user_id, $user_id), ARRAY_A);
        foreach ($disputes as $row) $data[] = self::group('dispute', $row['public_id'], $row, []);
        return ['data' => $data, 'done' => true];
    }

    public static function erase(string $email_address, int $page = 1): array {
        $user = get_user_by('email', $email_address);
        if (!$user) return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        $user_id = (int) $user->ID;
        global $wpdb;
        $retained = false; $removed = false; $messages = [];
        $seller = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . MKT_DB::table('sellers') . ' WHERE user_id=%d', $user_id), ARRAY_A);
        if ($seller) {
            $wpdb->update(MKT_DB::table('sellers'), ['store_name' => 'Deleted seller', 'public_contact_modes' => '[]', 'country' => '', 'region' => '', 'city' => '', 'eligibility_snapshot' => '{}', 'updated_at' => MKT_DB::now()], ['id' => (int) $seller['id']]);
            $removed = true;
        }
        $wpdb->delete(MKT_DB::table('saves'), ['user_id' => $user_id]);
        $wpdb->query($wpdb->prepare("UPDATE " . MKT_DB::table('reports') . " SET details='[Erased by privacy request]',evidence_refs='[]' WHERE reporter_user_id=%d", $user_id));
        $has_transaction_records = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table('offers') . ' WHERE buyer_user_id=%d OR seller_user_id=%d', $user_id, $user_id))
            + (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . MKT_DB::table('deals') . ' WHERE buyer_user_id=%d OR seller_user_id=%d', $user_id, $user_id));
        if ($has_transaction_records) {
            $retained = true;
            $messages[] = __('Structured offer, deal and dispute records—including participant account IDs where legally or operationally required—were retained under the marketplace transaction/dispute policy; narrative evidence and direct contact data were minimized where permitted.', 'marketplace');
        }
        MKT_Audit::record('privacy_erasure_processed', 'user', (string) $user_id, ['transaction_records_retained' => (bool) $has_transaction_records], 'success', '', 'privacy_request');
        return ['items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => true];
    }

    private static function group(string $type, string $id, array $row, array $exclude): array {
        $data = [];
        foreach ($row as $key => $value) {
            if (in_array($key, $exclude, true)) continue;
            $data[] = ['name' => $key, 'value' => is_scalar($value) || $value === null ? (string) $value : wp_json_encode($value)];
        }
        return ['group_id' => 'mkt-' . $type, 'group_label' => 'Marketplace ' . ucfirst($type), 'item_id' => 'mkt-' . $type . '-' . $id, 'data' => $data];
    }
}
MKT_Privacy::init();
