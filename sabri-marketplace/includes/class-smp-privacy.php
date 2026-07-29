<?php
/** WordPress personal-data export and erasure integration. */
defined('ABSPATH') || exit;

final class SMP_Privacy {
    public static function init(): void {
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_eraser']);
    }

    public static function register_exporter(array $exporters): array {
        $exporters['sabri-marketplace'] = [
            'exporter_friendly_name' => __('Sabri Marketplace', 'sabri-marketplace'),
            'callback' => [self::class, 'export'],
        ];
        return $exporters;
    }

    public static function register_eraser(array $erasers): array {
        $erasers['sabri-marketplace'] = [
            'eraser_friendly_name' => __('Sabri Marketplace', 'sabri-marketplace'),
            'callback' => [self::class, 'erase'],
        ];
        return $erasers;
    }

    public static function export(string $email, int $page = 1): array {
        $user = get_user_by('email', $email);
        if (!$user instanceof WP_User) return ['data' => [], 'done' => true];
        $uid = (int) $user->ID;
        global $wpdb;
        $data = [];

        $seller = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('sellers') . ' WHERE user_id=%d', $uid), ARRAY_A);
        if (is_array($seller)) {
            $safe = SMP_Utils::public_seller($seller, true);
            unset($safe['identityNumber'], $safe['businessRegistration'], $safe['taxNumber'], $safe['licenseNumber']);
            $data[] = self::group('seller-' . (int) $seller['id'], 'Marketplace Seller Profile', $safe);
        }

        $buyer = SMP_Utils::buyer_contact($uid);
        if (array_filter($buyer, static fn($value) => $value !== '' && $value !== false)) {
            $data[] = self::group('buyer-contact-' . $uid, 'Marketplace Buyer Contact Preferences', $buyer);
        }

        $products = $wpdb->get_results($wpdb->prepare(
            'SELECT p.* FROM ' . SMP_DB::table('products') . ' p JOIN ' . SMP_DB::table('sellers') . ' s ON s.id=p.seller_id WHERE s.user_id=%d ORDER BY p.id ASC',
            $uid
        ), ARRAY_A);
        foreach ($products as $product) {
            $data[] = self::group('listing-' . (int) $product['id'], 'Marketplace Listing', SMP_Utils::public_product($product));
        }

        $messages = $wpdb->get_results($wpdb->prepare(
            'SELECT id,conversation_id,message_type,message_text,attachment_name,attachment_mime,attachment_size,offer_amount,offer_status,created_at FROM ' . SMP_DB::table('messages') . ' WHERE sender_id=%d ORDER BY id ASC LIMIT 500',
            $uid
        ), ARRAY_A);
        foreach ($messages as $message) {
            $data[] = self::group('message-' . (int) $message['id'], 'Marketplace Message', $message);
        }

        $reports = $wpdb->get_results($wpdb->prepare(
            'SELECT id,reason,details,status,created_at,updated_at FROM ' . SMP_DB::table('reports') . ' WHERE reporter_id=%d ORDER BY id ASC LIMIT 500',
            $uid
        ), ARRAY_A);
        foreach ($reports as $report) {
            $data[] = self::group('report-' . (int) $report['id'], 'Marketplace Report', $report);
        }

        return ['data' => $data, 'done' => true];
    }

    private static function group(string $item_id, string $group_label, array $values): array {
        $items = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_bool($value)) $value = $value ? 'Yes' : 'No';
            $items[] = ['name' => ucwords(str_replace(['_', '-'], ' ', (string) $key)), 'value' => (string) $value];
        }
        return [
            'group_id' => 'sabri-marketplace',
            'group_label' => $group_label,
            'item_id' => $item_id,
            'data' => $items,
        ];
    }

    public static function erase(string $email, int $page = 1): array {
        $user = get_user_by('email', $email);
        if (!$user instanceof WP_User) return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        $uid = (int) $user->ID;
        global $wpdb;
        $removed = false;
        $retained = false;
        $messages = [];

        foreach (['smp_buyer_phone','smp_buyer_whatsapp','smp_buyer_preferred_contact','smp_buyer_allow_calls','smp_buyer_share_phone','smp_buyer_share_whatsapp','smp_buyer_quiet_hours','smp_last_seen'] as $meta_key) {
            $removed = delete_user_meta($uid, $meta_key) || $removed;
        }

        $seller = $wpdb->get_row($wpdb->prepare('SELECT id FROM ' . SMP_DB::table('sellers') . ' WHERE user_id=%d', $uid), ARRAY_A);
        if (is_array($seller)) {
            $updated = $wpdb->update(SMP_DB::table('sellers'), [
                'legal_name' => 'Erased Marketplace User',
                'phone' => '', 'alternate_phone' => '', 'whatsapp' => '', 'email' => '', 'address' => '',
                'identity_type' => '', 'identity_number' => '', 'business_registration' => '', 'tax_number' => '', 'license_number' => '',
                'show_phone' => 0, 'show_whatsapp' => 0, 'allow_chat' => 0, 'allow_calls' => 0, 'allow_offers' => 0,
                'status' => 'privacy_erased', 'updated_at' => SMP_Utils::now(),
            ], ['id' => (int) $seller['id']]);
            $removed = $updated !== false || $removed;
            $wpdb->update(SMP_DB::table('products'), ['status' => 'suspended', 'moderation_note' => 'Seller requested personal-data erasure.', 'updated_at' => SMP_Utils::now()], ['seller_id' => (int) $seller['id']]);
        }

        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,attachment_path FROM ' . SMP_DB::table('messages') . ' WHERE sender_id=%d', $uid), ARRAY_A);
        foreach ($rows as $row) {
            if (!empty($row['attachment_path'])) SMP_Utils::delete_private_file((string) $row['attachment_path']);
            $wpdb->update(SMP_DB::table('messages'), [
                'sender_id' => 0,
                'message_text' => '[Erased by privacy request]',
                'attachment_path' => '', 'attachment_name' => '', 'attachment_mime' => '', 'attachment_size' => 0,
                'contact_payload' => '', 'deleted_for_all' => 1, 'deleted_at' => SMP_Utils::now(),
            ], ['id' => (int) $row['id']]);
            $removed = true;
        }

        $wpdb->delete(SMP_DB::table('wishlist'), ['user_id' => $uid]);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . SMP_DB::table('blocks') . ' WHERE blocker_id=%d OR blocked_id=%d', $uid, $uid));
        $wpdb->update(SMP_DB::table('reports'), ['details' => '[Erased by privacy request]', 'updated_at' => SMP_Utils::now()], ['reporter_id' => $uid]);

        $conversation_count = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . SMP_DB::table('conversations') . ' WHERE buyer_id=%d OR seller_user_id=%d',
            $uid,
            $uid
        ));
        if ($conversation_count > 0) {
            $retained = true;
            $messages[] = __('Minimal conversation relationship identifiers are retained where required to protect the other participant, resolve disputes, and preserve audit integrity.', 'sabri-marketplace');
        }

        SMP_Utils::audit('privacy_erasure', 'user', $uid, ['conversationCount' => $conversation_count]);
        return ['items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => true];
    }
}
