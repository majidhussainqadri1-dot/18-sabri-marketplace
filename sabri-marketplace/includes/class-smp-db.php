<?php
defined('ABSPATH') || exit;

final class SMP_DB {
    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'smp_' . $name;
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = [];

        $sql[] = 'CREATE TABLE ' . self::table('sellers') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            seller_type varchar(30) NOT NULL DEFAULT 'individual',
            legal_name varchar(190) NOT NULL DEFAULT '',
            store_name varchar(190) NOT NULL DEFAULT '',
            phone varchar(40) NOT NULL DEFAULT '',
            alternate_phone varchar(40) NOT NULL DEFAULT '',
            whatsapp varchar(40) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            country varchar(100) NOT NULL DEFAULT '',
            city varchar(120) NOT NULL DEFAULT '',
            address text NULL,
            identity_type varchar(50) NOT NULL DEFAULT '',
            identity_number longtext NULL,
            business_registration longtext NULL,
            tax_number longtext NULL,
            license_number longtext NULL,
            verification_level varchar(50) NOT NULL DEFAULT 'phone',
            contact_verified tinyint(1) NOT NULL DEFAULT 0,
            show_phone tinyint(1) NOT NULL DEFAULT 1,
            show_whatsapp tinyint(1) NOT NULL DEFAULT 1,
            allow_chat tinyint(1) NOT NULL DEFAULT 1,
            allow_calls tinyint(1) NOT NULL DEFAULT 1,
            allow_offers tinyint(1) NOT NULL DEFAULT 1,
            preferred_contact varchar(30) NOT NULL DEFAULT 'chat',
            call_hours varchar(190) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'pending',
            rejection_reason text NULL,
            rating decimal(4,2) NOT NULL DEFAULT 0.00,
            sales_count bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY status (status),
            KEY store_name (store_name)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('products') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            seller_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            category varchar(190) NOT NULL DEFAULT '',
            subcategory varchar(190) NOT NULL DEFAULT '',
            product_type varchar(40) NOT NULL DEFAULT 'physical',
            condition_name varchar(40) NOT NULL DEFAULT 'new',
            brand varchar(190) NOT NULL DEFAULT '',
            sku varchar(120) NOT NULL DEFAULT '',
            short_description text NULL,
            description longtext NULL,
            price decimal(18,2) NOT NULL DEFAULT 0.00,
            sale_price decimal(18,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT 'PKR',
            stock_qty int(11) NOT NULL DEFAULT 1,
            stock_status varchar(30) NOT NULL DEFAULT 'in_stock',
            images longtext NULL,
            video_url text NULL,
            attributes longtext NULL,
            shipping longtext NULL,
            compliance longtext NULL,
            allow_chat tinyint(1) NOT NULL DEFAULT 1,
            allow_calls tinyint(1) NOT NULL DEFAULT 1,
            allow_whatsapp tinyint(1) NOT NULL DEFAULT 1,
            allow_offers tinyint(1) NOT NULL DEFAULT 1,
            pickup_available tinyint(1) NOT NULL DEFAULT 1,
            delivery_discussion tinyint(1) NOT NULL DEFAULT 1,
            deal_status varchar(30) NOT NULL DEFAULT 'available',
            status varchar(30) NOT NULL DEFAULT 'draft',
            moderation_note text NULL,
            featured tinyint(1) NOT NULL DEFAULT 0,
            views bigint(20) unsigned NOT NULL DEFAULT 0,
            rating decimal(4,2) NOT NULL DEFAULT 0.00,
            review_count bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            published_at datetime NULL,
            sold_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY seller_id (seller_id),
            KEY category (category),
            KEY product_type (product_type),
            KEY status (status),
            KEY deal_status (deal_status),
            KEY featured (featured),
            KEY price (price)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('wishlist') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_product (user_id,product_id),
            KEY product_id (product_id)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('conversations') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            buyer_id bigint(20) unsigned NOT NULL,
            seller_id bigint(20) unsigned NOT NULL,
            seller_user_id bigint(20) unsigned NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            deal_status varchar(30) NOT NULL DEFAULT 'discussing',
            agreed_price decimal(18,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT 'PKR',
            last_message_id bigint(20) unsigned NOT NULL DEFAULT 0,
            last_message_at datetime NULL,
            buyer_last_read_id bigint(20) unsigned NOT NULL DEFAULT 0,
            seller_last_read_id bigint(20) unsigned NOT NULL DEFAULT 0,
            buyer_archived tinyint(1) NOT NULL DEFAULT 0,
            seller_archived tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_buyer_seller (product_id,buyer_id,seller_id),
            KEY buyer_id (buyer_id),
            KEY seller_user_id (seller_user_id),
            KEY seller_id (seller_id),
            KEY last_message_at (last_message_at),
            KEY status (status)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('messages') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender_id bigint(20) unsigned NOT NULL,
            message_type varchar(30) NOT NULL DEFAULT 'text',
            message_text longtext NULL,
            attachment_path text NULL,
            attachment_name varchar(255) NOT NULL DEFAULT '',
            attachment_mime varchar(120) NOT NULL DEFAULT '',
            attachment_size bigint(20) unsigned NOT NULL DEFAULT 0,
            offer_amount decimal(18,2) NOT NULL DEFAULT 0.00,
            offer_status varchar(30) NOT NULL DEFAULT '',
            contact_payload longtext NULL,
            reply_to bigint(20) unsigned NOT NULL DEFAULT 0,
            delivered_at datetime NULL,
            read_at datetime NULL,
            edited_at datetime NULL,
            deleted_for_all tinyint(1) NOT NULL DEFAULT 0,
            deleted_at datetime NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY conversation_id (conversation_id),
            KEY sender_id (sender_id),
            KEY message_type (message_type),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('message_reactions') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            reaction varchar(20) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY message_user (message_id,user_id),
            KEY message_id (message_id)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('blocks') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            blocker_id bigint(20) unsigned NOT NULL,
            blocked_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY blocker_blocked (blocker_id,blocked_id),
            KEY blocked_id (blocked_id)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('reports') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reporter_id bigint(20) unsigned NOT NULL,
            reported_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            message_id bigint(20) unsigned NOT NULL DEFAULT 0,
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reason varchar(100) NOT NULL DEFAULT '',
            details longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'open',
            admin_note text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY reporter_id (reporter_id),
            KEY reported_user_id (reported_user_id),
            KEY conversation_id (conversation_id),
            KEY status (status)
        ) $charset;";

        $sql[] = 'CREATE TABLE ' . self::table('audit_log') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(100) NOT NULL DEFAULT '',
            object_type varchar(80) NOT NULL DEFAULT '',
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            details longtext NULL,
            ip_address varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY actor_user_id (actor_user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY created_at (created_at)
        ) $charset;";

        foreach ($sql as $statement) dbDelta($statement);
        update_option('smp_db_version', SMP_DB_VERSION, false);
    }

    public static function tables(): array {
        return ['sellers','products','wishlist','conversations','messages','message_reactions','blocks','reports','audit_log'];
    }

    public static function table_exists(string $name): bool {
        global $wpdb;
        $table = self::table($name);
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function maybe_upgrade(): void {
        if ((string) get_option('smp_db_version', '') === SMP_DB_VERSION) return;
        self::install();
        SMP_Activator::set_defaults();
        SMP_Activator::migrate_to_direct_deal();
        self::migrate_sensitive_seller_values();
        self::migrate_legacy_notifications();
        self::migrate_legacy_attachments();
        SMP_Activator::ensure_marketplace_page(false);
        SMP_Activator::add_capabilities();
        update_option('smp_plugin_version', SMP_VERSION, false);
        update_option('smp_db_version', SMP_DB_VERSION, false);
        update_option('smp_flush_rewrite_rules', 1, false);
    }

    public static function transaction(callable $callback) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $result = $callback($wpdb);
            if ($result === false || $wpdb->last_error !== '') {
                throw new RuntimeException($wpdb->last_error ?: 'Marketplace database operation failed.');
            }
            $wpdb->query('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public static function migrate_sensitive_seller_values(): void {
        if (!SMP_Integrations::membership_active() || !self::table_exists('sellers')) return;
        global $wpdb;
        $rows = $wpdb->get_results('SELECT id,identity_number,business_registration,tax_number,license_number FROM ' . self::table('sellers'), ARRAY_A);
        foreach ($rows as $row) {
            $updates = [];
            foreach (['identity_number','business_registration','tax_number','license_number'] as $column) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value === '' || str_starts_with($value, 'smc:')) continue;
                $encrypted = SMP_Integrations::encrypt_sensitive($value);
                if (!is_wp_error($encrypted)) $updates[$column] = $encrypted;
            }
            if ($updates) $wpdb->update(self::table('sellers'), $updates, ['id' => (int) $row['id']]);
        }
        update_option('smp_sensitive_migration_version', SMP_DB_VERSION, false);
    }

    public static function migrate_legacy_notifications(): void {
        global $wpdb;
        $legacy = self::table('notifications');
        if (!SMP_Integrations::notifications_active() || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy)) !== $legacy) return;
        $last_id = (int) get_option('smp_legacy_notification_last_id', 0);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $legacy . ' WHERE id>%d ORDER BY id ASC LIMIT 250', $last_id), ARRAY_A);
        foreach ($rows as $row) {
            SMP_Integrations::notify([
                'user_id' => (int) $row['user_id'],
                'type' => (string) $row['type'],
                'category' => 'marketplace',
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
                'link' => (string) $row['link'],
                'source' => 'sabri_marketplace_legacy',
                'source_id' => (int) $row['id'],
                'dedupe_key' => 'smp-legacy-' . (int) $row['id'],
                'context' => ['legacy_read' => !empty($row['is_read'])],
            ]);
            $last_id = (int) $row['id'];
        }
        update_option('smp_legacy_notification_last_id', $last_id, false);
        if (!$rows || count($rows) < 250) update_option('smp_legacy_notifications_decommissioned', 1, false);
    }

    public static function migrate_legacy_attachments(): void {
        if (!SMP_Integrations::membership_active() || !self::table_exists('messages')) return;
        global $wpdb;
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) return;
        $legacy_dir = trailingslashit($uploads['basedir']) . 'sabri-marketplace-private';
        if (!is_dir($legacy_dir)) return;
        $rows = $wpdb->get_results("SELECT id,attachment_path FROM " . self::table('messages') . " WHERE attachment_path<>''", ARRAY_A);
        foreach ($rows as $row) {
            $name = basename((string) $row['attachment_path']);
            $new_path = SMP_Utils::private_file_path($name);
            if (is_file($new_path)) continue;
            $old_path = trailingslashit($legacy_dir) . $name;
            if (!is_file($old_path)) continue;
            $plain = file_get_contents($old_path);
            if ($plain === false) continue;
            $iv = random_bytes(12); $tag = '';
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', SMC_Security::key('marketplace-attachment'), OPENSSL_RAW_DATA, $iv, $tag);
            unset($plain);
            if ($cipher === false || file_put_contents($new_path, 'SMP1' . $iv . $tag . $cipher, LOCK_EX) === false) continue;
            @chmod($new_path, 0640);
            @unlink($old_path);
        }
        update_option('smp_attachment_migration_version', SMP_DB_VERSION, false);
    }

    public static function daily_maintenance(): void {
        global $wpdb;
        $chat_days = max(1, (int) get_option('smp_deleted_chat_file_retention_days', 30));
        $chat_cutoff = gmdate('Y-m-d H:i:s', time() - ($chat_days * DAY_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,attachment_path FROM " . self::table('messages') . " WHERE deleted_for_all=1 AND attachment_path<>'' AND COALESCE(deleted_at,created_at)<%s",
            $chat_cutoff
        ), ARRAY_A);
        foreach ($rows as $row) {
            SMP_Utils::delete_private_file((string) $row['attachment_path']);
            $wpdb->update(self::table('messages'), ['attachment_path' => '', 'attachment_name' => '', 'attachment_mime' => '', 'attachment_size' => 0], ['id' => (int) $row['id']]);
        }

        $message_days = max(30, (int) get_option('smp_message_retention_days', 730));
        $report_days = max(30, (int) get_option('smp_report_retention_days', 730));
        $audit_days = max(90, (int) get_option('smp_audit_retention_days', 1095));
        $message_cutoff = gmdate('Y-m-d H:i:s', time() - ($message_days * DAY_IN_SECONDS));
        $report_cutoff = gmdate('Y-m-d H:i:s', time() - ($report_days * DAY_IN_SECONDS));
        $audit_cutoff = gmdate('Y-m-d H:i:s', time() - ($audit_days * DAY_IN_SECONDS));

        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table('messages') . " SET message_text='[Expired by retention policy]',contact_payload='',sender_id=0 WHERE created_at<%s AND deleted_for_all=1",
            $message_cutoff
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table('reports') . " SET details='[Expired by retention policy]',admin_note='' WHERE updated_at<%s AND status IN ('resolved','dismissed')",
            $report_cutoff
        ));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table('audit_log') . ' WHERE created_at<%s', $audit_cutoff));

        self::migrate_legacy_notifications();
        self::migrate_legacy_attachments();
    }
}
