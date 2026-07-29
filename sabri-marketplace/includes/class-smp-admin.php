<?php
defined('ABSPATH') || exit;

final class SMP_Admin {
    private const PER_PAGE = 50;

    public static function register_menu(): void {
        $cap = 'manage_sabri_marketplace';
        add_menu_page('Marketplace', 'Marketplace', $cap, 'sabri-marketplace', [self::class, 'render_overview'], 'dashicons-store', 28);
        add_submenu_page('sabri-marketplace', 'Marketplace Overview', 'Overview', $cap, 'sabri-marketplace', [self::class, 'render_overview']);
        add_submenu_page('sabri-marketplace', 'Sellers', 'Sellers', $cap, 'sabri-marketplace-sellers', [self::class, 'render_sellers']);
        add_submenu_page('sabri-marketplace', 'Listings', 'Listings', $cap, 'sabri-marketplace-products', [self::class, 'render_products']);
        add_submenu_page('sabri-marketplace', 'Chats & Reports', 'Chats & Reports', 'moderate_sabri_marketplace_chat', 'sabri-marketplace-reports', [self::class, 'render_reports']);
        add_submenu_page('sabri-marketplace', 'Settings', 'Settings', $cap, 'sabri-marketplace-settings', [self::class, 'render_settings']);
        add_submenu_page('sabri-marketplace', 'System Check', 'System Check', $cap, 'sabri-marketplace-system', [self::class, 'render_system']);
    }

    public static function register_settings(): void {
        foreach (['smp_default_currency','smp_default_country','smp_support_email','smp_direct_deal_disclaimer','smp_prohibited_terms'] as $key) {
            register_setting('smp_settings', $key, ['sanitize_callback' => $key === 'smp_support_email' ? 'sanitize_email' : 'sanitize_textarea_field']);
        }
        foreach (['smp_max_upload_mb','smp_max_product_images','smp_max_chat_upload_mb','smp_deleted_chat_file_retention_days','smp_message_retention_days','smp_report_retention_days','smp_audit_retention_days','smp_chat_poll_seconds','smp_message_edit_minutes'] as $key) {
            register_setting('smp_settings', $key, ['sanitize_callback' => 'absint']);
        }
        foreach (['smp_require_seller_approval','smp_require_product_approval','smp_health_license_required','smp_allow_guest_browse','smp_reveal_contacts_to_logged_in','smp_require_buyer_contact_before_chat','smp_delete_data_on_uninstall'] as $key) {
            register_setting('smp_settings', $key, ['sanitize_callback' => static fn($value) => $value ? 1 : 0]);
        }
        add_action('admin_post_smp_repair', [self::class, 'repair_marketplace']);
        add_action('admin_post_smp_seller_action', [self::class, 'seller_action']);
        add_action('admin_post_smp_product_action', [self::class, 'product_action']);
        add_action('admin_post_smp_report_action', [self::class, 'report_action']);
        add_action('admin_post_smp_message_action', [self::class, 'message_action']);
    }

    private static function require_admin(string $nonce, string $cap = 'manage_sabri_marketplace'): void {
        if (!current_user_can($cap)) wp_die('Not allowed.');
        check_admin_referer($nonce);
    }

    private static function redirect(string $page, string $message = 'Updated'): void {
        wp_safe_redirect(add_query_arg(['page' => $page, 'smp_notice' => $message], admin_url('admin.php')));
        exit;
    }

    public static function repair_marketplace(): void {
        self::require_admin('smp_repair');
        SMP_DB::install();
        SMP_Activator::set_defaults();
        SMP_Activator::migrate_to_direct_deal();
        SMP_DB::migrate_sensitive_seller_values();
        SMP_DB::migrate_legacy_notifications();
        SMP_DB::migrate_legacy_attachments();
        $page_id = SMP_Activator::ensure_marketplace_page(false);
        SMP_Activator::add_capabilities();
        flush_rewrite_rules(false);
        delete_transient('sabri_shell_navigation_cache_v1');
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        do_action('litespeed_purge_all');
        SMP_Utils::audit('complete_repair', 'system', $page_id);
        self::redirect('sabri-marketplace-system', 'Repair and migrations completed; review all checks below.');
    }

    public static function seller_action(): void {
        self::require_admin('smp_seller_action');
        $id = absint($_POST['seller_id'] ?? 0);
        $status = sanitize_key($_POST['seller_status'] ?? '');
        if (!in_array($status, ['approved','pending','rejected','suspended'], true)) wp_die('Invalid status.');
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('sellers') . ' WHERE id=%d', $id), ARRAY_A);
        if (!$row) wp_die('Seller not found.');
        if ($status === 'approved') {
            $eligibility = SMP_Integrations::seller_eligibility((int) $row['user_id']);
            if (empty($eligibility['eligible'])) wp_die(esc_html((string) $eligibility['message']));
        }
        $central = SMP_Integrations::membership_contact((int) $row['user_id']);
        $update = [
            'status' => $status,
            'verification_level' => $status === 'approved' ? 'central_membership' : 'pending',
            'contact_verified' => $status === 'approved' && !empty($central['mobileVerified']) ? 1 : 0,
            'rejection_reason' => sanitize_textarea_field($_POST['note'] ?? ''),
            'updated_at' => SMP_Utils::now(),
        ];
        if ($wpdb->update(SMP_DB::table('sellers'), $update, ['id' => $id]) === false) wp_die('Database update failed.');
        SMP_Utils::notify((int) $row['user_id'], 'seller_status', 'Seller account updated', 'Status: ' . $status, SMP_Activator::marketplace_url());
        SMP_Utils::audit('admin_seller_status', 'seller', $id, ['status' => $status]);
        self::redirect('sabri-marketplace-sellers', 'Seller updated');
    }

    public static function product_action(): void {
        self::require_admin('smp_product_action');
        $id = absint($_POST['product_id'] ?? 0);
        $status = sanitize_key($_POST['product_status'] ?? '');
        if (!in_array($status, ['published','submitted','rejected','suspended','draft'], true)) wp_die('Invalid status.');
        global $wpdb;
        $product = $wpdb->get_row($wpdb->prepare('SELECT p.*,s.user_id,s.status seller_status FROM ' . SMP_DB::table('products') . ' p JOIN ' . SMP_DB::table('sellers') . ' s ON s.id=p.seller_id WHERE p.id=%d', $id), ARRAY_A);
        if (!$product) wp_die('Listing not found.');
        if ($status === 'published') {
            $eligibility = SMP_Integrations::seller_eligibility((int) $product['user_id']);
            if ($product['seller_status'] !== 'approved' || empty($eligibility['eligible'])) wp_die('Seller is not centrally eligible for publication.');
            if ((bool) get_option('smp_health_license_required', 1) && SMP_Utils::is_restricted_category((string) $product['category'], (string) $product['product_type']) && !SMP_Integrations::health_license_verified((int) $product['user_id'])) {
                wp_die('A verified professional health license is required for this listing.');
            }
        }
        $update = [
            'status' => $status,
            'moderation_note' => sanitize_textarea_field($_POST['note'] ?? ''),
            'published_at' => $status === 'published' ? SMP_Utils::now() : $product['published_at'],
            'updated_at' => SMP_Utils::now(),
        ];
        if ($wpdb->update(SMP_DB::table('products'), $update, ['id' => $id]) === false) wp_die('Database update failed.');
        SMP_Utils::notify((int) $product['user_id'], 'listing_status', 'Listing updated', (string) $product['title'] . ' — ' . $status, SMP_Activator::marketplace_url());
        SMP_Utils::audit('admin_listing_status', 'product', $id, ['status' => $status]);
        self::redirect('sabri-marketplace-products', 'Listing updated');
    }

    public static function report_action(): void {
        self::require_admin('smp_report_action');
        $id = absint($_POST['report_id'] ?? 0);
        $status = sanitize_key($_POST['report_status'] ?? '');
        if (!in_array($status, ['open','under_review','resolved','dismissed'], true)) wp_die('Invalid status.');
        global $wpdb;
        if ($wpdb->update(SMP_DB::table('reports'), ['status' => $status, 'admin_note' => sanitize_textarea_field($_POST['note'] ?? ''), 'updated_at' => SMP_Utils::now()], ['id' => $id]) === false) wp_die('Database update failed.');
        SMP_Utils::audit('admin_report_status', 'report', $id, ['status' => $status]);
        self::redirect('sabri-marketplace-reports', 'Report updated');
    }

    public static function message_action(): void {
        self::require_admin('smp_message_action', 'moderate_sabri_marketplace_chat');
        $id = absint($_POST['message_id'] ?? 0);
        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('messages') . ' WHERE id=%d', $id), ARRAY_A);
        if (!$message) wp_die('Message not found.');
        // Keep encrypted attachment until the configured deletion-retention period expires.
        if ($wpdb->update(SMP_DB::table('messages'), ['message_text' => 'Removed by Marketplace moderation', 'contact_payload' => '', 'deleted_for_all' => 1, 'deleted_at' => SMP_Utils::now(), 'edited_at' => SMP_Utils::now()], ['id' => $id]) === false) wp_die('Database update failed.');
        SMP_Utils::audit('admin_message_remove', 'message', $id);
        self::redirect('sabri-marketplace-reports', 'Message removed and queued for retention cleanup');
    }

    private static function header(string $title, string $description = ''): void {
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
        if ($description) echo '<p>' . esc_html($description) . '</p>';
        if (isset($_GET['smp_notice'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['smp_notice']))) . '</p></div>';
        echo '<style>.smp-admin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:18px 0}.smp-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px}.smp-admin-card strong{display:block;font-size:28px;margin-top:7px}.smp-admin-table td{vertical-align:top}.smp-admin-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#f0f0f1}.smp-admin-actions{display:flex;gap:5px;flex-wrap:wrap}.smp-admin-actions input[type=text]{width:180px}.smp-fail{color:#b32d2e;font-weight:700}.smp-pass{color:#138a42;font-weight:700}</style>';
    }

    private static function footer(): void { echo '</div>'; }

    private static function pagination(string $page, int $total, int $current): void {
        echo wp_kses_post(paginate_links(['base' => add_query_arg(['page' => $page, 'paged' => '%#%'], admin_url('admin.php')), 'format' => '', 'current' => $current, 'total' => max(1, (int) ceil($total / self::PER_PAGE)), 'type' => 'list']));
    }

    public static function render_overview(): void {
        global $wpdb;
        self::header('Marketplace Direct-Deal Overview', 'Phone, WhatsApp and internal chat connect buyers and sellers. The platform does not process transaction funds.');
        $counts = [
            'Approved sellers' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . SMP_DB::table('sellers') . " WHERE status='approved'"),
            'Published listings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . SMP_DB::table('products') . " WHERE status IN ('published','approved')"),
            'Active chats' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . SMP_DB::table('conversations') . " WHERE status='active'"),
            'Messages' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . SMP_DB::table('messages')),
            'Open reports' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . SMP_DB::table('reports') . " WHERE status IN ('open','under_review')"),
            'Sold listings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . SMP_DB::table('products') . " WHERE deal_status='sold'"),
        ];
        echo '<div class="smp-admin-cards">'; foreach ($counts as $label => $count) echo '<div class="smp-admin-card">' . esc_html($label) . '<strong>' . number_format_i18n($count) . '</strong></div>'; echo '</div>';
        echo '<div class="smp-admin-card"><h2>Direct-deal rule</h2><p>' . esc_html(SMP_Utils::disclaimer()) . '</p><p><a class="button button-primary" href="' . esc_url(SMP_Activator::marketplace_url()) . '" target="_blank">Open Marketplace</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=sabri-marketplace-system')) . '">System Check</a></p></div>';
        self::footer();
    }

    public static function render_sellers(): void {
        global $wpdb;
        self::header('Marketplace Sellers', 'Marketplace approval confirms eligibility only; identity evidence remains controlled by Sabri Membership Core.');
        $paged = max(1, absint($_GET['paged'] ?? 1)); $offset = ($paged - 1) * self::PER_PAGE;
        $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SMP_DB::table('sellers'));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('sellers') . ' ORDER BY id DESC LIMIT %d OFFSET %d', self::PER_PAGE, $offset), ARRAY_A);
        echo '<table class="widefat striped smp-admin-table"><thead><tr><th>Seller</th><th>Contact</th><th>Central verification</th><th>Status / Action</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $contact = SMP_Integrations::membership_contact((int) $row['user_id']);
            $eligible = SMP_Integrations::seller_eligibility((int) $row['user_id']);
            echo '<tr><td><strong>' . esc_html($row['store_name']) . '</strong><br>' . esc_html($row['legal_name']) . '<br>' . esc_html($row['city'] . ' · ' . $row['country']) . '</td>';
            echo '<td>Phone: ' . esc_html(SMP_Utils::phone_digits((string) ($contact['phone'] ?? '')) ? '••••' . substr(SMP_Utils::phone_digits((string) $contact['phone']), -4) : 'Not available') . '<br>WhatsApp: ' . esc_html(SMP_Utils::phone_digits((string) ($contact['whatsapp'] ?? '')) ? '••••' . substr(SMP_Utils::phone_digits((string) $contact['whatsapp']), -4) : 'Not available') . '<br>Email: ' . esc_html($row['email']) . '</td>';
            echo '<td>' . (!empty($eligible['eligible']) ? '<span class="smp-pass">Eligible</span>' : '<span class="smp-fail">Not eligible</span>') . '<br>' . esc_html((string) $eligible['message']) . '<br>Mobile verified: ' . (!empty($contact['mobileVerified']) ? 'Yes' : 'No') . '</td>';
            echo '<td><span class="smp-admin-badge">' . esc_html($row['status']) . '</span><form class="smp-admin-actions" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('smp_seller_action'); echo '<input type="hidden" name="action" value="smp_seller_action"><input type="hidden" name="seller_id" value="' . (int) $row['id'] . '"><select name="seller_status">'; foreach (['approved','pending','rejected','suspended'] as $status) echo '<option value="' . esc_attr($status) . '" ' . selected($row['status'], $status, false) . '>' . esc_html(ucfirst($status)) . '</option>'; echo '</select><input type="text" name="note" placeholder="Required review note" value="' . esc_attr($row['rejection_reason']) . '"><button class="button">Update</button></form></td></tr>';
        }
        if (!$rows) echo '<tr><td colspan="4">No sellers found.</td></tr>';
        echo '</tbody></table>'; self::pagination('sabri-marketplace-sellers', $total, $paged); self::footer();
    }

    public static function render_products(): void {
        global $wpdb;
        self::header('Marketplace Listings', 'Publication is blocked unless the seller and any regulated health credentials are centrally verified.');
        $paged = max(1, absint($_GET['paged'] ?? 1)); $offset = ($paged - 1) * self::PER_PAGE;
        $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SMP_DB::table('products'));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT p.*,s.store_name,s.user_id FROM ' . SMP_DB::table('products') . ' p LEFT JOIN ' . SMP_DB::table('sellers') . ' s ON s.id=p.seller_id ORDER BY p.id DESC LIMIT %d OFFSET %d', self::PER_PAGE, $offset), ARRAY_A);
        echo '<table class="widefat striped smp-admin-table"><thead><tr><th>Listing</th><th>Seller / Safety</th><th>Status</th><th>Moderation</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $restricted = SMP_Utils::is_restricted_category((string) $row['category'], (string) $row['product_type']);
            $license_ok = !$restricted || !(bool) get_option('smp_health_license_required', 1) || SMP_Integrations::health_license_verified((int) $row['user_id']);
            echo '<tr><td><strong>' . esc_html($row['title']) . '</strong><br>' . esc_html($row['category'] . ' · ' . $row['product_type']) . '<br>' . esc_html(SMP_Utils::money($row['price'], $row['currency'])) . '</td><td>' . esc_html($row['store_name']) . '<br>' . ($license_ok ? '<span class="smp-pass">License rule passed</span>' : '<span class="smp-fail">Verified health license required</span>') . '</td><td>' . esc_html($row['status']) . '<br>Deal: ' . esc_html($row['deal_status']) . '</td><td><form class="smp-admin-actions" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('smp_product_action'); echo '<input type="hidden" name="action" value="smp_product_action"><input type="hidden" name="product_id" value="' . (int) $row['id'] . '"><select name="product_status">'; foreach (['published','submitted','rejected','suspended','draft'] as $status) echo '<option value="' . esc_attr($status) . '" ' . selected($row['status'], $status, false) . '>' . esc_html(ucfirst($status)) . '</option>'; echo '</select><input type="text" name="note" placeholder="Moderation note" value="' . esc_attr($row['moderation_note']) . '"><button class="button">Update</button></form></td></tr>';
        }
        if (!$rows) echo '<tr><td colspan="4">No listings found.</td></tr>';
        echo '</tbody></table>'; self::pagination('sabri-marketplace-products', $total, $paged); self::footer();
    }

    public static function render_reports(): void {
        global $wpdb;
        self::header('Chats & Reports', 'Only authorized moderators may inspect reports. Message content and attachments remain access controlled.');
        $paged = max(1, absint($_GET['paged'] ?? 1)); $offset = ($paged - 1) * self::PER_PAGE;
        $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SMP_DB::table('reports'));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('reports') . ' ORDER BY id DESC LIMIT %d OFFSET %d', self::PER_PAGE, $offset), ARRAY_A);
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Reason</th><th>Details</th><th>Status / Action</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . (int) $row['id'] . '</td><td>' . esc_html($row['reason']) . '</td><td>' . esc_html($row['details']) . '</td><td><form class="smp-admin-actions" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('smp_report_action'); echo '<input type="hidden" name="action" value="smp_report_action"><input type="hidden" name="report_id" value="' . (int) $row['id'] . '"><select name="report_status">'; foreach (['open','under_review','resolved','dismissed'] as $status) echo '<option value="' . esc_attr($status) . '" ' . selected($row['status'], $status, false) . '>' . esc_html(ucwords(str_replace('_', ' ', $status))) . '</option>'; echo '</select><input type="text" name="note" value="' . esc_attr($row['admin_note']) . '" placeholder="Admin note"><button class="button">Update</button></form></td></tr>';
        }
        if (!$rows) echo '<tr><td colspan="4">No reports found.</td></tr>';
        echo '</tbody></table>'; self::pagination('sabri-marketplace-reports', $total, $paged); self::footer();
    }

    public static function render_settings(): void {
        self::header('Marketplace Settings', 'Zero-commission direct-deal operation; File 00 owns identity and File 19 owns notifications.');
        echo '<form method="post" action="options.php">'; settings_fields('smp_settings'); echo '<table class="form-table">';
        $text = ['smp_default_currency' => 'Default currency', 'smp_default_country' => 'Default country', 'smp_support_email' => 'Support email', 'smp_direct_deal_disclaimer' => 'Direct-deal disclaimer', 'smp_prohibited_terms' => 'Additional prohibited terms'];
        foreach ($text as $key => $label) echo '<tr><th>' . esc_html($label) . '</th><td>' . ($key === 'smp_direct_deal_disclaimer' || $key === 'smp_prohibited_terms' ? '<textarea class="large-text" rows="4" name="' . esc_attr($key) . '">' . esc_textarea((string) get_option($key)) . '</textarea>' : '<input class="regular-text" name="' . esc_attr($key) . '" value="' . esc_attr((string) get_option($key)) . '">') . '</td></tr>';
        $numbers = ['smp_max_upload_mb'=>'Max image upload MB','smp_max_product_images'=>'Max product images','smp_max_chat_upload_mb'=>'Max chat upload MB','smp_deleted_chat_file_retention_days'=>'Deleted attachment retention days','smp_message_retention_days'=>'Deleted message retention days','smp_report_retention_days'=>'Resolved report retention days','smp_audit_retention_days'=>'Audit retention days','smp_chat_poll_seconds'=>'Chat polling seconds','smp_message_edit_minutes'=>'Message edit minutes'];
        foreach ($numbers as $key => $label) echo '<tr><th>' . esc_html($label) . '</th><td><input type="number" min="1" name="' . esc_attr($key) . '" value="' . (int) get_option($key) . '"></td></tr>';
        $checks = ['smp_require_seller_approval'=>'Require seller approval','smp_require_product_approval'=>'Require listing approval','smp_health_license_required'=>'Require verified license for health categories','smp_allow_guest_browse'=>'Allow guest browsing','smp_reveal_contacts_to_logged_in'=>'Require login before contact reveal','smp_require_buyer_contact_before_chat'=>'Require verified buyer contact before chat','smp_delete_data_on_uninstall'=>'Delete owned data on uninstall (destructive)'];
        foreach ($checks as $key => $label) echo '<tr><th>' . esc_html($label) . '</th><td><label><input type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked((bool) get_option($key), true, false) . '> Enabled</label></td></tr>';
        echo '</table>'; submit_button(); echo '</form>'; self::footer();
    }

    public static function render_system(): void {
        self::header('Marketplace System Check', 'A green result confirms static runtime prerequisites only; staging acceptance remains mandatory.');
        $checks = [];
        foreach (SMP_DB::tables() as $table) $checks['Table: ' . $table] = SMP_DB::table_exists($table);
        $integrations = SMP_Integrations::status();
        foreach ($integrations as $name => $ok) $checks['Integration: ' . $name] = $ok;
        $page_id = (int) get_option('smp_marketplace_page_id');
        $checks['Marketplace page published'] = $page_id > 0 && get_post_status($page_id) === 'publish';
        $checks['Private directory writable'] = is_dir(SMP_Utils::private_dir()) && is_writable(SMP_Utils::private_dir());
        $checks['Attachment scanner configured'] = SMP_Utils::attachment_scanner_available();
        $checks['OpenSSL AES-256-GCM available'] = in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
        $checks['Privacy exporter registered'] = has_filter('wp_privacy_personal_data_exporters') !== false;
        $checks['Privacy eraser registered'] = has_filter('wp_privacy_personal_data_erasers') !== false;
        echo '<table class="widefat striped"><tbody>'; foreach ($checks as $label => $ok) echo '<tr><th>' . esc_html($label) . '</th><td>' . ($ok ? '<span class="smp-pass">Ready</span>' : '<span class="smp-fail">Blocked</span>') . '</td></tr>'; echo '</tbody></table>';
        echo '<p><strong>Marketplace URL:</strong> <a href="' . esc_url(SMP_Activator::marketplace_url()) . '" target="_blank">' . esc_html(SMP_Activator::marketplace_url()) . '</a></p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('smp_repair'); echo '<input type="hidden" name="action" value="smp_repair"><button class="button button-primary button-hero">Run Repair and Migrations</button></form>';
        self::footer();
    }
}
