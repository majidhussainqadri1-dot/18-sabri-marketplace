<?php
defined('ABSPATH') || exit;

final class MKT_Admin {
    public static function register_menu(): void {
        add_menu_page(__('Marketplace', 'marketplace'), __('Marketplace', 'marketplace'), 'mkt_view_system', 'mkt-marketplace', [self::class, 'render'], 'dashicons-store', 58);
        add_submenu_page('mkt-marketplace', __('System Status', 'marketplace'), __('System Status', 'marketplace'), 'mkt_view_system', 'mkt-marketplace', [self::class, 'render']);
        add_submenu_page('mkt-marketplace', __('Moderation', 'marketplace'), __('Moderation', 'marketplace'), 'mkt_review_marketplace', 'mkt-marketplace-moderation', [self::class, 'render_moderation']);
        add_submenu_page('mkt-marketplace', __('Policies', 'marketplace'), __('Policies', 'marketplace'), 'mkt_manage_policies', 'mkt-marketplace-policies', [self::class, 'render_policies']);
    }

    public static function register_settings(): void {
        register_setting('mkt_settings_group', 'mkt_settings', [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
            'default' => MKT_DB::settings(),
        ]);
    }

    public static function sanitize_settings($input): array {
        $current = MKT_DB::settings();
        if (!current_user_can('manage_options')) return $current;
        $input = is_array($input) ? $input : [];
        return [
            'safe_mode' => !empty($input['safe_mode']) ? 1 : 0,
            'selling_enabled' => !empty($input['selling_enabled']) ? 1 : 0,
            'offers_enabled' => !empty($input['offers_enabled']) ? 1 : 0,
            'deal_transitions_enabled' => !empty($input['deal_transitions_enabled']) ? 1 : 0,
            'promotions_enabled' => !empty($input['promotions_enabled']) ? 1 : 0,
            'listing_expiry_days' => min(365, max(1, (int) ($input['listing_expiry_days'] ?? $current['listing_expiry_days']))),
            'max_media_refs' => min(20, max(0, (int) ($input['max_media_refs'] ?? $current['max_media_refs']))),
            'default_currency' => MKT_DB::currency((string) ($input['default_currency'] ?? 'PKR')),
            'retention_audit_days' => min(3650, max(90, (int) ($input['retention_audit_days'] ?? $current['retention_audit_days']))),
            'retention_reports_days' => min(3650, max(30, (int) ($input['retention_reports_days'] ?? $current['retention_reports_days']))),
            'retention_disputes_days' => min(7300, max(365, (int) ($input['retention_disputes_days'] ?? $current['retention_disputes_days']))),
        ];
    }

    public static function dependency_notices(): void {
        if (!current_user_can('mkt_view_system')) return;
        $status = self::system_status();
        $blocking = array_filter($status['checks'], static fn(array $check): bool => $check['severity'] === 'blocker' && !$check['pass']);
        if (!$blocking) return;
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Sabri Marketplace:', 'marketplace') . '</strong> ' . esc_html__('Protected actions are fail-closed because required platform contracts are unavailable. Open Marketplace → System Status.', 'marketplace') . '</p></div>';
    }

    public static function system_status(): array {
        global $wpdb;
        $checks = [];
        $tables = ['sellers','policies','listings','media_refs','saves','offers','deals','reports','disputes','outbox','inbox','idempotency','audit','metrics_daily','migration_handoffs'];
        $table_status = [];
        foreach ($tables as $table) {
            $table_status[$table] = MKT_DB::table_exists($table);
            $checks[] = ['id' => 'table_' . $table, 'label' => 'Table: ' . $table, 'pass' => $table_status[$table], 'severity' => 'blocker'];
        }
        $integrations = MKT_Integrations::status();
        $checks[] = ['id' => 'identity', 'label' => 'File 00 identity contract', 'pass' => !empty($integrations['identity']['available']), 'severity' => 'blocker'];
        $checks[] = ['id' => 'communication', 'label' => 'File 17 product-linked conversation contract', 'pass' => !empty($integrations['communication']['available']), 'severity' => 'blocker'];
        $checks[] = ['id' => 'notifications', 'label' => 'File 19 notification contract', 'pass' => !empty($integrations['notifications']['available']), 'severity' => 'warning'];
        $checks[] = ['id' => 'shell', 'label' => 'File 20 application shell contract', 'pass' => !empty($integrations['shell']['available']), 'severity' => 'warning'];
        $checks[] = ['id' => 'zero_commission', 'label' => 'Zero commission invariant', 'pass' => $table_status['listings'] && $table_status['offers'] && $table_status['deals'] && MKT_Contracts::zero_commission() === 0 && !self::schema_contains_commission(), 'severity' => 'blocker'];
        $rewrite_rules = get_option('rewrite_rules', []);
        $route_ok = is_array($rewrite_rules) && isset($rewrite_rules['^marketplace/?$']);
        $checks[] = ['id' => 'routes', 'label' => 'Canonical rewrite rules', 'pass' => $route_ok, 'severity' => 'warning'];
        $checks[] = ['id' => 'cron_hourly', 'label' => 'Hourly maintenance', 'pass' => (bool) wp_next_scheduled('mkt_hourly_maintenance'), 'severity' => 'warning'];
        $checks[] = ['id' => 'cron_daily', 'label' => 'Daily maintenance', 'pass' => (bool) wp_next_scheduled('mkt_daily_maintenance'), 'severity' => 'warning'];
        $dead = $table_status['outbox'] ? (int) $wpdb->get_var("SELECT COUNT(*) FROM " . MKT_DB::table('outbox') . " WHERE status='dead'") : -1;
        $checks[] = ['id' => 'dead_letter', 'label' => 'No unresolved dead-letter events', 'pass' => $dead === 0, 'severity' => 'warning', 'count' => $dead];
        $blocked_handoffs = $table_status['migration_handoffs'] ? (int) $wpdb->get_var("SELECT COUNT(*) FROM " . MKT_DB::table('migration_handoffs') . " WHERE status='blocked'") : -1;
        $checks[] = ['id' => 'migration_handoffs', 'label' => 'No blocked File 17 migration handoffs', 'pass' => $blocked_handoffs === 0, 'severity' => 'warning', 'count' => $blocked_handoffs];
        $audit_check = $table_status['audit'] ? MKT_Audit::verify_chain(500) : ['valid' => false, 'checked' => 0, 'breaks' => 1, 'lock_gaps' => 0];
        $checks[] = ['id' => 'audit_chain', 'label' => 'Audit-chain integrity (latest 500)', 'pass' => !empty($audit_check['valid']), 'severity' => 'warning', 'count' => (int) ($audit_check['breaks'] ?? 0) + (int) ($audit_check['lock_gaps'] ?? 0)];
        return [
            'module' => 'file-18-marketplace',
            'version' => MKT_VERSION,
            'schema_version' => (string) get_option('mkt_schema_version', ''),
            'commission_percent' => 0,
            'settings' => MKT_DB::settings(),
            'integrations' => $integrations,
            'checks' => $checks,
            'overall' => !array_filter($checks, static fn(array $check): bool => $check['severity'] === 'blocker' && !$check['pass']) ? 'ready_for_staging_tests' : 'fail_closed',
            'trace_id' => MKT_Audit::trace_id(),
        ];
    }

    private static function schema_contains_commission(): bool {
        global $wpdb;
        foreach (['listings','offers','deals'] as $name) {
            if (!MKT_DB::table_exists($name)) return true;
            $columns = (array) $wpdb->get_col('DESCRIBE ' . MKT_DB::table($name), 0);
            foreach ($columns as $column) {
                if (str_contains(strtolower((string) $column), 'commission')) return true;
            }
        }
        return false;
    }

    public static function repair(bool $dry_run = true): array|WP_Error {
        if (!current_user_can('manage_options')) return new WP_Error('mkt_repair_forbidden', __('You cannot run repair.', 'marketplace'), ['status' => 403]);
        $plan = ['install_or_update_schema','seed_builtin_policies','register_capabilities','schedule_maintenance','register_rewrite_rules','migrate_legacy_foundation_non_destructively'];
        if ($dry_run) {
            MKT_Audit::record('repair_dry_run', 'system', 'file-18-marketplace', ['plan' => $plan]);
            return ['dry_run' => true, 'plan' => $plan, 'before' => self::system_status()];
        }
        $snapshot = ['settings' => MKT_DB::settings(), 'schema_version' => get_option('mkt_schema_version'), 'plugin_version' => get_option('mkt_plugin_version'), 'created_at' => MKT_DB::now()];
        update_option('mkt_pre_repair_snapshot', $snapshot, false);
        MKT_DB::install_schema();
        MKT_DB::seed_defaults();
        MKT_Contracts::register_capabilities();
        MKT_Maintenance::schedule();
        MKT_Routes::register_rewrites();
        flush_rewrite_rules(false);
        MKT_DB::migrate_legacy_foundation();
        MKT_Audit::record('repair_executed', 'system', 'file-18-marketplace', ['plan' => $plan]);
        return ['dry_run' => false, 'plan' => $plan, 'after' => self::system_status()];
    }

    public static function handle_moderation_action(): void {
        if (!current_user_can('mkt_review_marketplace')) wp_die(esc_html__('Access denied.', 'marketplace'));
        check_admin_referer('mkt_moderation_action');
        $type = sanitize_key(wp_unslash((string) ($_POST['object_type'] ?? '')));
        $public_id = sanitize_text_field(wp_unslash((string) ($_POST['public_id'] ?? '')));
        $to = sanitize_key(wp_unslash((string) ($_POST['to'] ?? '')));
        $version = (int) wp_unslash((string) ($_POST['version'] ?? 0));
        $reason = sanitize_key(wp_unslash((string) ($_POST['decision_code'] ?? '')));
        $note = wp_kses_post(wp_unslash((string) ($_POST['decision_note'] ?? '')));
        $result = new WP_Error('mkt_invalid_admin_action', __('Invalid moderation action.', 'marketplace'));
        if ($type === 'listing') $result = MKT_Listings::transition($public_id, $to, get_current_user_id(), $version, $reason, $note);
        if ($type === 'report') $result = MKT_Moderation::transition_report($public_id, $to, ['decision_code' => $reason, 'decision_note' => $note], get_current_user_id(), $version);
        if ($type === 'dispute') $result = MKT_Moderation::transition_dispute($public_id, $to, ['decision_code' => $reason, 'decision_note' => $note], get_current_user_id(), $version);
        $url = add_query_arg(is_wp_error($result) ? ['mkt_error' => rawurlencode($result->get_error_message())] : ['mkt_updated' => 1], admin_url('admin.php?page=mkt-marketplace-moderation'));
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_policy_save(): void {
        if (!current_user_can('mkt_manage_policies')) wp_die(esc_html__('Access denied.', 'marketplace'));
        check_admin_referer('mkt_policy_save');
        $type = sanitize_key(wp_unslash((string) ($_POST['policy_type'] ?? 'category')));
        $key = sanitize_key(wp_unslash((string) ($_POST['policy_key'] ?? '')));
        $jurisdiction = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', wp_unslash((string) ($_POST['jurisdiction'] ?? 'GLOBAL'))));
        $status = sanitize_key(wp_unslash((string) ($_POST['status'] ?? 'active')));
        $rules = json_decode(wp_unslash((string) ($_POST['rules_json'] ?? '')), true);
        if (!in_array($type, ['category','prohibited','business'], true) || $key === '' || !is_array($rules) || !in_array($status, ['draft','active','retired'], true)) {
            wp_safe_redirect(add_query_arg(['mkt_error' => rawurlencode(__('Invalid policy data.', 'marketplace'))], admin_url('admin.php?page=mkt-marketplace-policies')));
            exit;
        }
        if ($type === 'business' && $key === 'zero_commission' && ((float) ($rules['commission_percent'] ?? 0) !== 0.0 || !empty($rules['donation_advantage']))) {
            wp_safe_redirect(add_query_arg(['mkt_error' => rawurlencode(__('The zero-commission and no-donor-advantage laws cannot be overridden.', 'marketplace'))], admin_url('admin.php?page=mkt-marketplace-policies')));
            exit;
        }
        global $wpdb;
        $version = 1 + (int) $wpdb->get_var($wpdb->prepare('SELECT MAX(version) FROM ' . MKT_DB::table('policies') . ' WHERE policy_type=%s AND policy_key=%s AND jurisdiction=%s', $type, $key, $jurisdiction));
        if ($status === 'active') {
            $wpdb->update(MKT_DB::table('policies'), ['status' => 'retired', 'updated_at' => MKT_DB::now()], ['policy_type' => $type, 'policy_key' => $key, 'jurisdiction' => $jurisdiction, 'status' => 'active']);
        }
        $wpdb->insert(MKT_DB::table('policies'), [
            'public_id' => MKT_DB::uuid(), 'policy_type' => $type, 'policy_key' => $key, 'jurisdiction' => $jurisdiction,
            'version' => $version, 'status' => $status, 'rules_json' => wp_json_encode($rules), 'created_by' => get_current_user_id(),
            'approved_by' => $status === 'active' ? get_current_user_id() : 0, 'effective_at' => $status === 'active' ? MKT_DB::now() : null,
            'created_at' => MKT_DB::now(), 'updated_at' => MKT_DB::now(),
        ]);
        MKT_Audit::record('policy_version_created', 'policy', $key, ['type' => $type, 'jurisdiction' => $jurisdiction, 'version' => $version, 'status' => $status]);
        wp_safe_redirect(add_query_arg(['mkt_updated' => 1], admin_url('admin.php?page=mkt-marketplace-policies')));
        exit;
    }

    public static function render(): void {
        if (!current_user_can('mkt_view_system')) wp_die(esc_html__('Access denied.', 'marketplace'));
        $status = self::system_status();
        $settings = MKT_DB::settings();
        ?>
        <div class="wrap"><h1><?php esc_html_e('Sabri Marketplace — System Status', 'marketplace'); ?></h1>
        <p><strong><?php esc_html_e('Canonical rule:', 'marketplace'); ?></strong> <?php esc_html_e('File 18 owns listings, offers, deals, reports and disputes. File 17 owns conversations. Platform commission is 0%.', 'marketplace'); ?></p>
        <p><strong><?php esc_html_e('Current gate:', 'marketplace'); ?></strong> <?php echo esc_html((string) $status['overall']); ?> — <?php esc_html_e('This screen does not claim staging or production acceptance.', 'marketplace'); ?></p>
        <table class="widefat striped"><thead><tr><th><?php esc_html_e('Check', 'marketplace'); ?></th><th><?php esc_html_e('Result', 'marketplace'); ?></th><th><?php esc_html_e('Severity', 'marketplace'); ?></th></tr></thead><tbody>
        <?php foreach ($status['checks'] as $check): ?><tr><td><?php echo esc_html($check['label']); ?></td><td><?php echo $check['pass'] ? '✅ PASS' : '❌ FAIL'; ?><?php if (isset($check['count'])) echo ' (' . esc_html((string) $check['count']) . ')'; ?></td><td><?php echo esc_html($check['severity']); ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php if (current_user_can('manage_options')): ?>
        <h2><?php esc_html_e('Operational controls', 'marketplace'); ?></h2>
        <form method="post" action="options.php"><?php settings_fields('mkt_settings_group'); ?>
        <table class="form-table"><tbody>
        <?php foreach (['safe_mode','selling_enabled','offers_enabled','deal_transitions_enabled','promotions_enabled'] as $key): ?><tr><th><?php echo esc_html(ucwords(str_replace('_',' ',$key))); ?></th><td><label><input type="checkbox" name="mkt_settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>> <?php esc_html_e('Enabled', 'marketplace'); ?></label></td></tr><?php endforeach; ?>
        <tr><th><?php esc_html_e('Listing expiry days', 'marketplace'); ?></th><td><input type="number" min="1" max="365" name="mkt_settings[listing_expiry_days]" value="<?php echo esc_attr((string) $settings['listing_expiry_days']); ?>"></td></tr>
        <tr><th><?php esc_html_e('Maximum media references', 'marketplace'); ?></th><td><input type="number" min="0" max="20" name="mkt_settings[max_media_refs]" value="<?php echo esc_attr((string) $settings['max_media_refs']); ?>"></td></tr>
        <tr><th><?php esc_html_e('Default currency', 'marketplace'); ?></th><td><input type="text" maxlength="3" name="mkt_settings[default_currency]" value="<?php echo esc_attr((string) $settings['default_currency']); ?>"></td></tr>
        </tbody></table><?php submit_button(); ?></form>
        <?php endif; ?></div>
        <?php
    }

    public static function render_moderation(): void {
        if (!current_user_can('mkt_review_marketplace')) wp_die(esc_html__('Access denied.', 'marketplace'));
        global $wpdb;
        $listings = $wpdb->get_results("SELECT public_id,title,status,version,updated_at FROM " . MKT_DB::table('listings') . " WHERE status IN ('review','appealed') ORDER BY updated_at ASC LIMIT 100", ARRAY_A);
        $reports = $wpdb->get_results("SELECT public_id,target_type,target_public_id,reason,status,version,updated_at FROM " . MKT_DB::table('reports') . " WHERE status<>'closed' ORDER BY updated_at ASC LIMIT 100", ARRAY_A);
        $disputes = $wpdb->get_results("SELECT public_id,deal_id,reason,status,version,updated_at FROM " . MKT_DB::table('disputes') . " WHERE status NOT IN ('closed','withdrawn') ORDER BY updated_at ASC LIMIT 100", ARRAY_A);
        self::notice_from_query();
        echo '<div class="wrap"><h1>' . esc_html__('Marketplace moderation', 'marketplace') . '</h1><p>' . esc_html__('Every action is server-authorized, version-checked, reasoned and audited. Message content remains with File 17.', 'marketplace') . '</p>';
        if (current_user_can('mkt_moderate')) {
            self::render_queue_table('listing', $listings, ['active','rejected','draft','review','removed']);
            self::render_queue_table('report', $reports, ['triaged','restricted','no_action','decided','closed']);
        }
        if (current_user_can('mkt_review_disputes')) {
            self::render_queue_table('dispute', $disputes, ['evidence','review','more_info','decided','closed']);
        }
        echo '</div>';
    }

    private static function render_queue_table(string $type, array $rows, array $states): void {
        echo '<h2>' . esc_html(ucfirst($type) . ' queue') . '</h2>';
        if (!$rows) { echo '<p>' . esc_html__('No open items.', 'marketplace') . '</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__('Summary', 'marketplace') . '</th><th>' . esc_html__('Status', 'marketplace') . '</th><th>' . esc_html__('Decision', 'marketplace') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $summary = $row['title'] ?? (($row['target_type'] ?? 'deal') . ': ' . ($row['reason'] ?? ''));
            echo '<tr><td><code>' . esc_html(substr((string) $row['public_id'], 0, 12)) . '</code></td><td>' . esc_html((string) $summary) . '</td><td>' . esc_html((string) $row['status']) . '</td><td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('mkt_moderation_action');
            echo '<input type="hidden" name="action" value="mkt_moderation_action"><input type="hidden" name="object_type" value="' . esc_attr($type) . '"><input type="hidden" name="public_id" value="' . esc_attr((string) $row['public_id']) . '"><input type="hidden" name="version" value="' . esc_attr((string) $row['version']) . '">';
            echo '<select name="to" required><option value="">' . esc_html__('Select state', 'marketplace') . '</option>';
            foreach ($states as $state) echo '<option value="' . esc_attr($state) . '">' . esc_html($state) . '</option>';
            echo '</select> <input name="decision_code" required maxlength="100" placeholder="' . esc_attr__('Reason code', 'marketplace') . '"> <input name="decision_note" maxlength="500" placeholder="' . esc_attr__('Decision note', 'marketplace') . '"> <button class="button button-primary">' . esc_html__('Apply', 'marketplace') . '</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function render_policies(): void {
        if (!current_user_can('mkt_manage_policies')) wp_die(esc_html__('Access denied.', 'marketplace'));
        global $wpdb;
        $rows = $wpdb->get_results('SELECT policy_type,policy_key,jurisdiction,version,status,rules_json,effective_at,updated_at FROM ' . MKT_DB::table('policies') . ' ORDER BY policy_type,policy_key,jurisdiction,version DESC LIMIT 300', ARRAY_A);
        self::notice_from_query();
        ?>
        <div class="wrap"><h1><?php esc_html_e('Marketplace policies', 'marketplace'); ?></h1><p><?php esc_html_e('Policies are append-only versioned records. Activating a new version retires the previous active version for the same key and jurisdiction.', 'marketplace'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('mkt_policy_save'); ?><input type="hidden" name="action" value="mkt_policy_save">
        <table class="form-table"><tbody><tr><th><?php esc_html_e('Type', 'marketplace'); ?></th><td><select name="policy_type"><option value="category">category</option><option value="prohibited">prohibited</option><option value="business">business</option></select></td></tr>
        <tr><th><?php esc_html_e('Key', 'marketplace'); ?></th><td><input required name="policy_key" pattern="[a-z0-9_-]+"></td></tr><tr><th><?php esc_html_e('Jurisdiction', 'marketplace'); ?></th><td><input name="jurisdiction" value="GLOBAL"></td></tr>
        <tr><th><?php esc_html_e('Status', 'marketplace'); ?></th><td><select name="status"><option value="draft">draft</option><option value="active">active</option><option value="retired">retired</option></select></td></tr>
        <tr><th><?php esc_html_e('Rules JSON', 'marketplace'); ?></th><td><textarea required name="rules_json" rows="12" class="large-text code">{"label":"Example","risk":"standard","required_fields":["title","description","price","currency"]}</textarea></td></tr></tbody></table><?php submit_button(__('Create policy version', 'marketplace')); ?></form>
        <h2><?php esc_html_e('Policy history', 'marketplace'); ?></h2><table class="widefat striped"><thead><tr><th>Type</th><th>Key</th><th>Jurisdiction</th><th>Version</th><th>Status</th><th>Rules</th><th>Updated</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><td><?php echo esc_html($row['policy_type']); ?></td><td><code><?php echo esc_html($row['policy_key']); ?></code></td><td><?php echo esc_html($row['jurisdiction']); ?></td><td><?php echo esc_html((string) $row['version']); ?></td><td><?php echo esc_html($row['status']); ?></td><td><code><?php echo esc_html(wp_trim_words((string) $row['rules_json'], 20)); ?></code></td><td><?php echo esc_html((string) $row['updated_at']); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    private static function notice_from_query(): void {
        if (!empty($_GET['mkt_updated'])) echo '<div class="notice notice-success"><p>' . esc_html__('Marketplace record updated.', 'marketplace') . '</p></div>';
        if (!empty($_GET['mkt_error'])) echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash((string) $_GET['mkt_error']))) . '</p></div>';
    }
}
