<?php
/**
 * Plugin Name: Sabri Marketplace
 * Plugin URI: https://sabrihomeopathy.com/
 * Description: Canonical zero-commission marketplace for the Sabri Social Homeopathy Platform.
 * Version: 2.1.0
 * Author: Dr. Allamah Majid Hussain Sabri
 * Text Domain: marketplace
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

define('MKT_VERSION', '2.1.0');
define('MKT_SCHEMA_VERSION', '2.1.0');
define('MKT_CONTRACT_VERSION', '1.2.0');
define('MKT_FILE', __FILE__);
define('MKT_DIR', plugin_dir_path(__FILE__));
define('MKT_URL', plugin_dir_url(__FILE__));
define('MKT_BASENAME', plugin_basename(__FILE__));

$includes = [
    'class-mkt-contracts.php',
    'class-mkt-integrations.php',
    'class-mkt-state-machines.php',
    'class-mkt-db.php',
    'class-mkt-policy.php',
    'class-mkt-auth.php',
    'class-mkt-rate-limiter.php',
    'class-mkt-idempotency.php',
    'class-mkt-audit.php',
    'class-mkt-events.php',
    'class-mkt-listings.php',
    'class-mkt-commerce.php',
    'class-mkt-moderation.php',
    'class-mkt-governance.php',
    'class-mkt-plan-completion.php',
    'class-mkt-finalization.php',
    'class-mkt-privacy.php',
    'class-mkt-maintenance.php',
    'class-mkt-rest.php',
    'class-mkt-routes.php',
    'class-mkt-admin.php',
];
foreach ($includes as $include) {
    require_once MKT_DIR . 'includes/' . $include;
}

register_activation_hook(MKT_FILE, ['MKT_DB', 'activate']);
register_activation_hook(MKT_FILE, ['MKT_Governance', 'activate']);
register_activation_hook(MKT_FILE, ['MKT_Plan_Completion', 'activate']);
register_deactivation_hook(MKT_FILE, ['MKT_DB', 'deactivate']);

final class MKT_Plugin {
    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('plugins_loaded', ['MKT_Integrations', 'boot'], 20);
        add_action('plugins_loaded', ['MKT_Governance', 'boot'], 25);
        add_action('plugins_loaded', ['MKT_Plan_Completion', 'boot'], 30);
        add_action('plugins_loaded', ['MKT_Finalization', 'boot'], 35);
        add_action('init', [$this, 'init'], 5);
        add_action('rest_api_init', ['MKT_REST', 'register_routes']);
        add_action('sabri_platform_event', ['MKT_Events', 'handle_external_event'], 20, 2);
        add_action('admin_menu', ['MKT_Admin', 'register_menu']);
        add_action('admin_init', ['MKT_Admin', 'register_settings']);
        add_action('wp_enqueue_scripts', ['MKT_Routes', 'register_assets'], 5);
        add_filter('template_include', ['MKT_Routes', 'template_include'], 99);
        add_filter('query_vars', ['MKT_Routes', 'query_vars']);
        add_filter('document_title_parts', ['MKT_Routes', 'document_title']);
        add_action('wp_head', ['MKT_Routes', 'structured_data']);
        add_action('template_redirect', ['MKT_Routes', 'privacy_headers'], 0);
        add_action('mkt_process_outbox', ['MKT_Events', 'process_outbox']);
        add_action('mkt_hourly_maintenance', ['MKT_Maintenance', 'hourly']);
        add_action('mkt_daily_maintenance', ['MKT_Maintenance', 'daily']);
        add_action('admin_notices', ['MKT_Admin', 'dependency_notices']);
        add_action('admin_post_mkt_moderation_action', ['MKT_Admin', 'handle_moderation_action']);
        add_action('admin_post_mkt_policy_save', ['MKT_Admin', 'handle_policy_save']);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('marketplace', false, dirname(MKT_BASENAME) . '/languages');
    }

    public function init(): void {
        MKT_DB::maybe_upgrade();
        MKT_Routes::register_rewrites();
        MKT_Contracts::register_capabilities();
        MKT_Integrations::register_providers();
    }
}

MKT_Plugin::instance();
