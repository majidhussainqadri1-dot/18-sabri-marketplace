<?php
defined('ABSPATH') || exit;

final class SMP_Activator {
    public static function activate(): void {
        SMP_DB::install();
        self::set_defaults();
        self::migrate_to_direct_deal();
        SMP_DB::migrate_sensitive_seller_values();
        SMP_DB::migrate_legacy_notifications();
        SMP_DB::migrate_legacy_attachments();
        self::ensure_marketplace_page(false);
        self::add_capabilities();
        if (!wp_next_scheduled('smp_daily_maintenance')) wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'smp_daily_maintenance');
        update_option('smp_plugin_version', SMP_VERSION, false);
        update_option('smp_db_version', SMP_DB_VERSION, false);
        update_option('smp_flush_rewrite_rules', 1, false);
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void {
        $ts = wp_next_scheduled('smp_daily_maintenance');
        if ($ts) wp_unschedule_event($ts, 'smp_daily_maintenance');
        flush_rewrite_rules(false);
    }

    public static function set_defaults(): void {
        $defaults = [
            'smp_default_currency'=>'PKR','smp_default_country'=>'Pakistan','smp_support_email'=>get_option('admin_email'),
            'smp_max_upload_mb'=>10,'smp_max_product_images'=>8,'smp_max_chat_upload_mb'=>20,'smp_require_seller_approval'=>1,
            'smp_require_product_approval'=>1,'smp_health_license_required'=>1,'smp_allow_guest_browse'=>1,
            'smp_deleted_chat_file_retention_days'=>30,'smp_message_retention_days'=>730,'smp_report_retention_days'=>730,'smp_audit_retention_days'=>1095,
            'smp_chat_poll_seconds'=>4,'smp_reveal_contacts_to_logged_in'=>1,'smp_require_buyer_contact_before_chat'=>1,'smp_message_edit_minutes'=>1440,
            'smp_delete_data_on_uninstall'=>0,
            'smp_direct_deal_disclaimer'=>'Marketplace only connects buyers and sellers. Price, payment, inspection, pickup and delivery are arranged independently between the parties. The platform does not receive or hold transaction funds.',
            'smp_prohibited_terms'=>"weapon,firearm,ammunition,explosive,illegal drug,cocaine,heroin,stolen,counterfeit,pornography,malware,spyware,fake degree,fake certificate,guaranteed cure,100% cure",
            'smp_restricted_categories'=>['Homeopathy & Health','Beauty & Personal Care','Food & Supplements','Agriculture & Gardening','Vehicles','Property'],
            'smp_categories'=>self::default_categories(),
        ];
        foreach ($defaults as $key => $value) {
            if (get_option($key, null) === null) add_option($key, $value, '', false);
        }
        update_option('smp_direct_deal_mode', 1, false);
    }

    public static function migrate_to_direct_deal(): void {
        global $wpdb;
        update_option('smp_enable_cod', 0, false);
        update_option('smp_enable_bank_transfer', 0, false);
        update_option('smp_payment_gateway_enabled', 0, false);
        update_option('smp_platform_commission', 0, false);
        if (SMP_DB::table_exists('products')) {
            $wpdb->query("UPDATE " . SMP_DB::table('products') . " SET deal_status='available' WHERE deal_status='' OR deal_status IS NULL");
        }
    }

    public static function default_categories(): array {
        return [
            ['name'=>'Homeopathy & Health','icon'=>'⚕','slug'=>'homeopathy-health','restricted'=>true],['name'=>'Books & Education','icon'=>'📚','slug'=>'books-education','restricted'=>false],
            ['name'=>'Electronics','icon'=>'💻','slug'=>'electronics','restricted'=>false],['name'=>'Fashion','icon'=>'👕','slug'=>'fashion','restricted'=>false],
            ['name'=>'Home & Living','icon'=>'🏠','slug'=>'home-living','restricted'=>false],['name'=>'Beauty & Personal Care','icon'=>'✨','slug'=>'beauty-personal-care','restricted'=>true],
            ['name'=>'Children & Family','icon'=>'🧸','slug'=>'children-family','restricted'=>false],['name'=>'Sports & Fitness','icon'=>'🏃','slug'=>'sports-fitness','restricted'=>false],
            ['name'=>'Agriculture & Gardening','icon'=>'🌱','slug'=>'agriculture-gardening','restricted'=>true],['name'=>'Automotive','icon'=>'🔧','slug'=>'automotive','restricted'=>false],
            ['name'=>'Vehicles','icon'=>'🚗','slug'=>'vehicles','restricted'=>true],['name'=>'Property','icon'=>'🏢','slug'=>'property','restricted'=>true],
            ['name'=>'Business & Wholesale','icon'=>'📦','slug'=>'business-wholesale','restricted'=>false],['name'=>'Handmade & Local Products','icon'=>'🧵','slug'=>'handmade-local','restricted'=>false],
            ['name'=>'Digital Products','icon'=>'⬇','slug'=>'digital-products','restricted'=>false],['name'=>'Services','icon'=>'🛠','slug'=>'services','restricted'=>false],
            ['name'=>'Food & Supplements','icon'=>'🥗','slug'=>'food-supplements','restricted'=>true],['name'=>'Other Approved Categories','icon'=>'➕','slug'=>'other-approved','restricted'=>true],
        ];
    }

    public static function safe_url(): string { return home_url('/marketplace-safe/'); }

    public static function marketplace_url(): string {
        $id = (int) get_option('smp_marketplace_page_id');
        if ($id && get_post_status($id) === 'publish') {
            $url = get_permalink($id);
            if ($url) return (string) $url;
        }
        return self::safe_url();
    }

    public static function ensure_marketplace_page(bool $force_content = false): int {
        $id = (int) get_option('smp_marketplace_page_id');
        $page = $id ? get_post($id) : null;
        if (!$page instanceof WP_Post) $page = get_page_by_path('marketplace', OBJECT, 'page');
        if (!$page) {
            $trashed = get_posts(['post_type'=>'page','name'=>'marketplace','post_status'=>'trash','numberposts'=>1]);
            if ($trashed) { wp_untrash_post($trashed[0]->ID); $page = get_post($trashed[0]->ID); }
        }
        if ($page instanceof WP_Post) {
            if (!has_shortcode((string) $page->post_content, 'sabri_marketplace') || $force_content) {
                wp_update_post(['ID'=>$page->ID,'post_title'=>'Marketplace','post_content'=>'[sabri_marketplace]','post_status'=>'publish','comment_status'=>'closed']);
            } elseif ($page->post_status !== 'publish') {
                wp_update_post(['ID'=>$page->ID,'post_status'=>'publish']);
            }
            update_option('smp_marketplace_page_id', (int) $page->ID, false);
            return (int) $page->ID;
        }
        $new = wp_insert_post(['post_title'=>'Marketplace','post_name'=>'marketplace','post_content'=>'[sabri_marketplace]','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed'], true);
        if (!is_wp_error($new) && $new) {
            update_option('smp_marketplace_page_id', (int) $new, false);
            return (int) $new;
        }
        return 0;
    }

    public static function add_capabilities(): void {
        $admin = get_role('administrator');
        if ($admin) foreach (['manage_sabri_marketplace','moderate_sabri_marketplace_chat','sell_on_sabri_marketplace','buy_on_sabri_marketplace'] as $cap) $admin->add_cap($cap);
        foreach (['subscriber','customer','sabri_patient','sabri_doctor','sabri_student','sabri_teacher','sabri_researcher','sabri_pharmacy','sabri_clinic','sabri_publisher','sabri_member','sabri_verified_doctor'] as $name) {
            $role = get_role($name);
            if ($role) $role->add_cap('buy_on_sabri_marketplace');
        }
        foreach (['sabri_doctor','sabri_pharmacy','sabri_clinic','sabri_publisher','sabri_verified_doctor'] as $name) {
            $role = get_role($name);
            if ($role) $role->add_cap('sell_on_sabri_marketplace');
        }
    }
}
