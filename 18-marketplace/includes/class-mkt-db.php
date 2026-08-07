<?php
defined('ABSPATH') || exit;

final class MKT_DB {
    private const TABLES = [
        'sellers','policies','listings','media_refs','saves','offers','deals','reports','disputes',
        'outbox','inbox','idempotency','audit','metrics_daily','migration_handoffs',
    ];

    public static function table(string $name): string {
        global $wpdb;
        if (!in_array($name, self::TABLES, true)) {
            throw new InvalidArgumentException('Unknown marketplace table.');
        }
        return $wpdb->prefix . 'mkt_' . $name;
    }

    public static function table_exists(string $name): bool {
        global $wpdb;
        $table = self::table($name);
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    public static function activate(): void {
        self::install_schema();
        self::seed_defaults();
        self::migrate_legacy_foundation();
        MKT_Contracts::register_capabilities();
        MKT_Routes::register_rewrites();
        flush_rewrite_rules(false);
        MKT_Maintenance::schedule();
        update_option('mkt_plugin_version', MKT_VERSION, false);
    }

    public static function deactivate(): void {
        MKT_Maintenance::unschedule();
        flush_rewrite_rules(false);
    }

    public static function maybe_upgrade(): void {
        $schema_current = (string) get_option('mkt_schema_version', '') === MKT_SCHEMA_VERSION;
        $runtime_current = (string) get_option('mkt_plugin_version', '') === MKT_VERSION;
        if ($schema_current && $runtime_current) {
            return;
        }
        if (!self::acquire_upgrade_lock()) {
            return;
        }
        try {
            if (!$schema_current) {
                self::install_schema();
                self::seed_defaults();
                self::migrate_legacy_foundation();
            }
            update_option('mkt_plugin_version', MKT_VERSION, false);
        } finally {
            self::release_upgrade_lock();
        }
    }

    private static function acquire_upgrade_lock(): bool {
        global $wpdb;
        $name = substr($wpdb->prefix . 'mkt_file18_upgrade', 0, 64);
        return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $name)) === 1;
    }

    private static function release_upgrade_lock(): void {
        global $wpdb;
        $name = substr($wpdb->prefix . 'mkt_file18_upgrade', 0, 64);
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }

    public static function install_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $sql = [];

        $sql[] = 'CREATE TABLE ' . self::table('sellers') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            user_id bigint unsigned NOT NULL,
            store_name varchar(190) NOT NULL DEFAULT '',
            seller_type varchar(40) NOT NULL DEFAULT 'individual',
            status varchar(30) NOT NULL DEFAULT 'pending',
            eligibility_snapshot longtext NULL,
            public_contact_modes longtext NULL,
            country varchar(2) NOT NULL DEFAULT '',
            region varchar(120) NOT NULL DEFAULT '',
            city varchar(120) NOT NULL DEFAULT '',
            version bigint unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            approved_at datetime NULL,
            suspended_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY user_id (user_id),
            KEY status (status),
            KEY location (country,region,city)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('policies') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            policy_type varchar(40) NOT NULL,
            policy_key varchar(120) NOT NULL,
            jurisdiction varchar(20) NOT NULL DEFAULT 'GLOBAL',
            version bigint unsigned NOT NULL DEFAULT 1,
            status varchar(30) NOT NULL DEFAULT 'active',
            rules_json longtext NOT NULL,
            created_by bigint unsigned NOT NULL DEFAULT 0,
            approved_by bigint unsigned NOT NULL DEFAULT 0,
            effective_at datetime NULL,
            expires_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY policy_version (policy_type,policy_key,jurisdiction,version),
            KEY active_lookup (policy_type,policy_key,jurisdiction,status)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('listings') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            seller_id bigint unsigned NOT NULL,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            category varchar(120) NOT NULL,
            subcategory varchar(120) NOT NULL DEFAULT '',
            listing_type varchar(30) NOT NULL DEFAULT 'product',
            condition_name varchar(30) NOT NULL DEFAULT 'new',
            description longtext NOT NULL,
            price decimal(18,2) NOT NULL DEFAULT 0.00,
            currency char(3) NOT NULL DEFAULT 'PKR',
            quantity decimal(18,3) NOT NULL DEFAULT 1.000,
            availability varchar(30) NOT NULL DEFAULT 'available',
            location_country varchar(2) NOT NULL DEFAULT '',
            location_region varchar(120) NOT NULL DEFAULT '',
            location_city varchar(120) NOT NULL DEFAULT '',
            delivery_modes longtext NULL,
            contact_modes longtext NULL,
            declarations longtext NULL,
            policy_snapshot longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            moderation_reason varchar(120) NOT NULL DEFAULT '',
            moderation_note longtext NULL,
            featured_label varchar(80) NOT NULL DEFAULT '',
            version bigint unsigned NOT NULL DEFAULT 1,
            created_by bigint unsigned NOT NULL,
            updated_by bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            submitted_at datetime NULL,
            published_at datetime NULL,
            expires_at datetime NULL,
            last_reviewed_at datetime NULL,
            removed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY slug (slug),
            KEY seller_status (seller_id,status),
            KEY category_status (category,status),
            KEY location_status (location_country,location_region,location_city,status),
            KEY price_status (currency,price,status),
            KEY published_at (published_at),
            KEY expires_at (expires_at)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('media_refs') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            listing_id bigint unsigned NOT NULL,
            owner_user_id bigint unsigned NOT NULL,
            provider varchar(80) NOT NULL,
            provider_public_id varchar(190) NOT NULL,
            media_kind varchar(30) NOT NULL DEFAULT 'image',
            rights_status varchar(30) NOT NULL DEFAULT 'pending',
            scan_status varchar(30) NOT NULL DEFAULT 'pending',
            alt_text varchar(255) NOT NULL DEFAULT '',
            sort_order int NOT NULL DEFAULT 0,
            metadata_json longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY provider_ref (provider,provider_public_id),
            KEY listing_order (listing_id,sort_order),
            KEY safety (rights_status,scan_status,status)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('saves') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint unsigned NOT NULL,
            listing_id bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_listing (user_id,listing_id),
            KEY listing_id (listing_id)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('offers') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            root_offer_id bigint unsigned NOT NULL DEFAULT 0,
            parent_offer_id bigint unsigned NOT NULL DEFAULT 0,
            listing_id bigint unsigned NOT NULL,
            buyer_user_id bigint unsigned NOT NULL,
            seller_user_id bigint unsigned NOT NULL,
            created_by bigint unsigned NOT NULL,
            amount decimal(18,2) NOT NULL,
            currency char(3) NOT NULL,
            terms longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'open',
            idempotency_key varchar(190) NOT NULL,
            version bigint unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            responded_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY listing_status (listing_id,status),
            KEY buyer_status (buyer_user_id,status),
            KEY seller_status (seller_user_id,status),
            KEY expires_at (expires_at)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('deals') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            listing_id bigint unsigned NOT NULL,
            accepted_offer_id bigint unsigned NOT NULL,
            buyer_user_id bigint unsigned NOT NULL,
            seller_user_id bigint unsigned NOT NULL,
            agreed_amount decimal(18,2) NOT NULL,
            currency char(3) NOT NULL,
            agreed_snapshot longtext NOT NULL,
            payment_mode varchar(40) NOT NULL DEFAULT 'direct',
            payment_status varchar(30) NOT NULL DEFAULT 'declared',
            delivery_status varchar(30) NOT NULL DEFAULT 'arranging',
            buyer_completion_confirmed_at datetime NULL,
            seller_completion_confirmed_at datetime NULL,
            status varchar(30) NOT NULL DEFAULT 'accepted',
            version bigint unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            completed_at datetime NULL,
            closed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY accepted_offer_id (accepted_offer_id),
            KEY participant_buyer (buyer_user_id,status),
            KEY participant_seller (seller_user_id,status),
            KEY listing_id (listing_id)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('reports') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            reporter_user_id bigint unsigned NOT NULL,
            target_type varchar(30) NOT NULL,
            target_public_id varchar(190) NOT NULL,
            reason varchar(80) NOT NULL,
            details longtext NULL,
            evidence_refs longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'submitted',
            assigned_to bigint unsigned NOT NULL DEFAULT 0,
            decision_code varchar(80) NOT NULL DEFAULT '',
            decision_note longtext NULL,
            version bigint unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            decided_at datetime NULL,
            closed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            KEY target (target_type,target_public_id),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('disputes') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            deal_id bigint unsigned NOT NULL,
            opened_by bigint unsigned NOT NULL,
            reason varchar(80) NOT NULL,
            statement longtext NOT NULL,
            evidence_refs longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'opened',
            assigned_to bigint unsigned NOT NULL DEFAULT 0,
            decision_code varchar(80) NOT NULL DEFAULT '',
            decision_note longtext NULL,
            access_grants longtext NULL,
            version bigint unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            decided_at datetime NULL,
            closed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            KEY deal_status (deal_id,status),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('outbox') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            event_id char(36) NOT NULL,
            event_type varchar(120) NOT NULL,
            aggregate_type varchar(60) NOT NULL,
            aggregate_public_id varchar(190) NOT NULL,
            actor_user_id bigint unsigned NOT NULL DEFAULT 0,
            payload_json longtext NOT NULL,
            privacy_class varchar(30) NOT NULL DEFAULT 'internal',
            status varchar(30) NOT NULL DEFAULT 'pending',
            attempts int unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            processed_at datetime NULL,
            last_error varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_id (event_id),
            KEY queue (status,available_at),
            KEY aggregate (aggregate_type,aggregate_public_id)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('inbox') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            source varchar(100) NOT NULL,
            event_id varchar(190) NOT NULL,
            event_type varchar(120) NOT NULL,
            payload_hash char(64) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'received',
            received_at datetime NOT NULL,
            processed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_event (source,event_id),
            KEY status (status)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('idempotency') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            scope varchar(80) NOT NULL,
            actor_user_id bigint unsigned NOT NULL,
            idempotency_key varchar(190) NOT NULL,
            request_hash char(64) NOT NULL,
            response_code int NOT NULL DEFAULT 0,
            response_json longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'processing',
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY actor_scope_key (actor_user_id,scope,idempotency_key),
            KEY expires_at (expires_at)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('audit') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            entry_id char(36) NOT NULL,
            trace_id char(36) NOT NULL,
            actor_user_id bigint unsigned NOT NULL DEFAULT 0,
            action varchar(120) NOT NULL,
            object_type varchar(60) NOT NULL,
            object_public_id varchar(190) NOT NULL DEFAULT '',
            purpose varchar(120) NOT NULL DEFAULT '',
            outcome varchar(30) NOT NULL DEFAULT 'success',
            reason_code varchar(100) NOT NULL DEFAULT '',
            details_json longtext NULL,
            previous_hash char(64) NOT NULL DEFAULT '',
            entry_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY entry_id (entry_id),
            KEY trace_id (trace_id),
            KEY actor_time (actor_user_id,created_at),
            KEY object_time (object_type,object_public_id,created_at),
            KEY action_time (action,created_at)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('metrics_daily') . " (
            metric_date date NOT NULL,
            metric_key varchar(100) NOT NULL,
            dimension_hash char(64) NOT NULL,
            value_count bigint NOT NULL DEFAULT 0,
            value_sum decimal(24,4) NOT NULL DEFAULT 0,
            metadata_json longtext NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (metric_date,metric_key,dimension_hash)
        ) $c;";

        $sql[] = 'CREATE TABLE ' . self::table('migration_handoffs') . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            legacy_type varchar(50) NOT NULL,
            legacy_id bigint unsigned NOT NULL,
            target_owner varchar(80) NOT NULL,
            target_reference varchar(190) NOT NULL DEFAULT '',
            payload_hash char(64) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            attempts int unsigned NOT NULL DEFAULT 0,
            last_error varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY legacy_record (legacy_type,legacy_id),
            KEY status (status)
        ) $c;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
        update_option('mkt_schema_version', MKT_SCHEMA_VERSION, false);
    }

    public static function transaction(callable $callback) {
        global $wpdb;
        $wpdb->last_error = '';
        $wpdb->query('START TRANSACTION');
        try {
            $result = $callback($wpdb);
            if (is_wp_error($result)) {
                $wpdb->query('ROLLBACK');
                return $result;
            }
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

    public static function uuid(): string {
        return wp_generate_uuid4();
    }

    public static function now(): string {
        return current_time('mysql', true);
    }

    public static function seed_defaults(): void {
        if (!get_option('mkt_settings')) {
            add_option('mkt_settings', [
                'safe_mode' => 0,
                'selling_enabled' => 1,
                'offers_enabled' => 1,
                'deal_transitions_enabled' => 1,
                'promotions_enabled' => 0,
                'listing_expiry_days' => 90,
                'max_media_refs' => 10,
                'default_currency' => 'PKR',
                'retention_audit_days' => 1095,
                'retention_reports_days' => 730,
                'retention_disputes_days' => 1825,
            ], '', false);
        }
        MKT_Policy::seed_builtin_policies();
    }

    public static function migrate_legacy_foundation(): void {
        if ((string) get_option('mkt_legacy_migration_version', '') === MKT_SCHEMA_VERSION) {
            return;
        }
        global $wpdb;
        $legacy_products = $wpdb->prefix . 'smp_products';
        $legacy_sellers = $wpdb->prefix . 'smp_sellers';
        $has_products = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_products))) === $legacy_products;
        $has_sellers = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_sellers))) === $legacy_sellers;
        if (!$has_products && !$has_sellers) {
            update_option('mkt_legacy_migration_version', MKT_SCHEMA_VERSION, false);
            return;
        }

        $report = ['sellers' => 0, 'listings' => 0, 'quarantined' => 0, 'communication_handoffs' => 0];
        if ($has_sellers) {
            $last_seller_id = 0;
            do {
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$legacy_sellers} WHERE id>%d ORDER BY id ASC LIMIT 200", $last_seller_id), ARRAY_A);
                foreach ($rows as $row) {
                    $last_seller_id = max($last_seller_id, (int) $row['id']);
                $user_id = (int) ($row['user_id'] ?? 0);
                if ($user_id <= 0) {
                    $report['quarantined']++;
                    continue;
                }
                $exists = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table('sellers') . ' WHERE user_id=%d', $user_id));
                if ($exists) {
                    continue;
                }
                $wpdb->insert(self::table('sellers'), [
                    'public_id' => self::uuid(),
                    'user_id' => $user_id,
                    'store_name' => sanitize_text_field((string) ($row['store_name'] ?? '')),
                    'seller_type' => sanitize_key((string) ($row['seller_type'] ?? 'individual')),
                    'status' => in_array((string) ($row['status'] ?? ''), ['approved','active'], true) ? 'approved' : 'pending',
                    'eligibility_snapshot' => wp_json_encode(['legacy_id' => (int) $row['id'], 'migrated_at' => self::now()]),
                    'public_contact_modes' => wp_json_encode([]),
                    'country' => '',
                    'region' => '',
                    'city' => sanitize_text_field((string) ($row['city'] ?? '')),
                    'version' => 1,
                    'created_at' => self::now(),
                    'updated_at' => self::now(),
                ]);
                    $report['sellers']++;
                }
            } while (count($rows) === 200);
        }
        if ($has_products && $has_sellers) {
            $last_product_id = 0;
            do {
                $rows = $wpdb->get_results($wpdb->prepare("SELECT p.*,s.user_id AS legacy_user_id FROM {$legacy_products} p LEFT JOIN {$legacy_sellers} s ON s.id=p.seller_id WHERE p.id>%d ORDER BY p.id ASC LIMIT 200", $last_product_id), ARRAY_A);
                foreach ($rows as $row) {
                    $last_product_id = max($last_product_id, (int) $row['id']);
                $user_id = (int) ($row['legacy_user_id'] ?? 0);
                $seller_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table('sellers') . ' WHERE user_id=%d', $user_id));
                if ($seller_id <= 0) {
                    $report['quarantined']++;
                    continue;
                }
                $legacy_slug = sanitize_title((string) ($row['slug'] ?? $row['title'] ?? 'listing'));
                $slug = $legacy_slug . '-legacy-' . (int) $row['id'];
                if ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table('listings') . ' WHERE slug=%s', $slug))) {
                    continue;
                }
                $status = in_array((string) ($row['status'] ?? ''), ['published','active'], true) ? 'review' : 'draft';
                $wpdb->insert(self::table('listings'), [
                    'public_id' => self::uuid(),
                    'seller_id' => $seller_id,
                    'title' => sanitize_text_field((string) ($row['title'] ?? 'Legacy listing')),
                    'slug' => $slug,
                    'category' => sanitize_key((string) ($row['category'] ?? 'other')),
                    'subcategory' => sanitize_key((string) ($row['subcategory'] ?? '')),
                    'listing_type' => sanitize_key((string) ($row['product_type'] ?? 'product')),
                    'condition_name' => sanitize_key((string) ($row['condition_name'] ?? 'used')),
                    'description' => wp_kses_post((string) ($row['description'] ?? '')),
                    'price' => max(0, (float) ($row['price'] ?? 0)),
                    'currency' => self::currency((string) ($row['currency'] ?? 'PKR')),
                    'quantity' => max(0, (float) ($row['stock_qty'] ?? 1)),
                    'availability' => 'available',
                    'delivery_modes' => wp_json_encode([]),
                    'contact_modes' => wp_json_encode(['file17_chat']),
                    'declarations' => wp_json_encode(['legacy_import' => true]),
                    'policy_snapshot' => wp_json_encode(['requires_review' => true]),
                    'status' => $status,
                    'version' => 1,
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'created_at' => self::now(),
                    'updated_at' => self::now(),
                ]);
                    $report['listings']++;
                }
            } while (count($rows) === 200);
        } elseif ($has_products) {
            $report['quarantined'] += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$legacy_products}");
        }

        $legacy_conversations = $wpdb->prefix . 'smp_conversations';
        $legacy_messages = $wpdb->prefix . 'smp_messages';
        foreach ([['conversation',$legacy_conversations],['message',$legacy_messages]] as [$type,$table]) {
            if ((string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
                continue;
            }
            $last_handoff_id = 0;
            do {
                $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE id>%d ORDER BY id ASC LIMIT 500", $last_handoff_id));
                foreach ($ids as $legacy_id) {
                    $last_handoff_id = max($last_handoff_id, (int) $legacy_id);
                    $payload_hash = hash('sha256', $type . ':' . (int) $legacy_id . ':' . MKT_SCHEMA_VERSION);
                    $wpdb->query($wpdb->prepare(
                        'INSERT IGNORE INTO ' . self::table('migration_handoffs') . ' (legacy_type,legacy_id,target_owner,target_reference,payload_hash,status,attempts,last_error,created_at,updated_at) VALUES (%s,%d,%s,%s,%s,%s,0,%s,%s,%s)',
                        $type, (int) $legacy_id, 'file-17-communication', '', $payload_hash, 'pending', '', self::now(), self::now()
                    ));
                    $report['communication_handoffs']++;
                }
            } while (count($ids) === 500);
        }

        update_option('mkt_legacy_migration_report', $report, false);
        update_option('mkt_legacy_migration_version', MKT_SCHEMA_VERSION, false);
    }

    public static function currency(string $currency): string {
        $currency = strtoupper(preg_replace('/[^A-Z]/i', '', $currency));
        return in_array($currency, MKT_Contracts::allowed_currencies(), true) ? $currency : 'PKR';
    }

    public static function settings(): array {
        return wp_parse_args((array) get_option('mkt_settings', []), [
            'safe_mode' => 0,
            'selling_enabled' => 1,
            'offers_enabled' => 1,
            'deal_transitions_enabled' => 1,
            'promotions_enabled' => 0,
            'listing_expiry_days' => 90,
            'max_media_refs' => 10,
            'default_currency' => 'PKR',
            'retention_audit_days' => 1095,
            'retention_reports_days' => 730,
            'retention_disputes_days' => 1825,
        ]);
    }
}
