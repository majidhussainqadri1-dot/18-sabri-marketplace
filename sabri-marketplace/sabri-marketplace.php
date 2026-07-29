<?php
/**
 * Plugin Name: Sabri Marketplace
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Direct-deal, zero-commission marketplace integrated with Sabri Membership Core, Unified Notifications, and the Unified Application Shell.
 * Version: 1.2.0
 * Author: Sabri Homeopathy
 * Text Domain: sabri-marketplace
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

define('SMP_VERSION', '1.2.0');
define('SMP_DB_VERSION', '1.2.0');
define('SMP_FILE', __FILE__);
define('SMP_DIR', plugin_dir_path(__FILE__));
define('SMP_URL', plugin_dir_url(__FILE__));

require_once SMP_DIR . 'includes/class-smp-utils.php';
require_once SMP_DIR . 'includes/class-smp-integrations.php';
require_once SMP_DIR . 'includes/class-smp-rate-limiter.php';
require_once SMP_DIR . 'includes/class-smp-db.php';
require_once SMP_DIR . 'includes/class-smp-privacy.php';
require_once SMP_DIR . 'includes/class-smp-activator.php';
require_once SMP_DIR . 'includes/class-smp-admin.php';
require_once SMP_DIR . 'includes/class-smp-ajax.php';
require_once SMP_DIR . 'includes/class-smp-rest.php';
require_once SMP_DIR . 'includes/class-smp-shortcode.php';

register_activation_hook(__FILE__, ['SMP_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SMP_Activator', 'deactivate']);

final class Sabri_Marketplace {
    private static ?Sabri_Marketplace $instance = null;

    public static function instance(): Sabri_Marketplace {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('plugins_loaded', ['SMP_Integrations', 'init'], 15);
        add_action('plugins_loaded', ['SMP_Privacy', 'init'], 15);
        add_action('init', [$this, 'init']);
        add_action('template_redirect', [$this, 'disable_marketplace_cache'], 0);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('rest_api_init', ['SMP_REST', 'register_routes']);
        add_action('admin_menu', ['SMP_Admin', 'register_menu']);
        add_action('admin_init', ['SMP_Admin', 'register_settings']);
        add_action('wp_enqueue_scripts', ['SMP_Shortcode', 'register_assets'], 5);
        add_action('wp_enqueue_scripts', ['SMP_Shortcode', 'enqueue_if_marketplace'], 20);
        add_shortcode('sabri_marketplace', ['SMP_Shortcode', 'render']);

        add_action('wp_ajax_smp_api', ['SMP_Ajax', 'handle']);
        add_action('wp_ajax_nopriv_smp_api', ['SMP_Ajax', 'handle']);
        add_action('wp_ajax_smp_chat_file', ['SMP_Ajax', 'download_chat_file']);

        add_filter('query_vars', [$this, 'query_vars']);
        add_filter('template_include', [$this, 'safe_template'], 99);
        add_filter('redirect_canonical', [$this, 'disable_safe_canonical'], 10, 2);
        add_filter('the_content', [$this, 'force_marketplace_content'], 9999);

        add_action('smp_daily_maintenance', ['SMP_DB', 'daily_maintenance']);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('sabri-marketplace', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function init(): void {
        SMP_DB::maybe_upgrade();
        add_rewrite_tag('%smp_marketplace_app%', '1');
        add_rewrite_rule('^marketplace-safe/?$', 'index.php?smp_marketplace_app=1', 'top');
        SMP_Utils::touch_last_seen();
    }

    public function query_vars(array $vars): array {
        $vars[] = 'smp_marketplace_app';
        return $vars;
    }

    public function disable_safe_canonical($redirect_url, $requested_url) {
        if ((int) get_query_var('smp_marketplace_app') === 1 || isset($_GET['smp-marketplace-safe'])) return false;
        return $redirect_url;
    }

    public function safe_template(string $template): string {
        if ((int) get_query_var('smp_marketplace_app') === 1 || isset($_GET['smp-marketplace-safe'])) {
            status_header(200);
            return SMP_DIR . 'templates/marketplace-standalone.php';
        }
        return $template;
    }

    public function force_marketplace_content(string $content): string {
        $page_id = (int) get_option('smp_marketplace_page_id');
        if ($page_id && is_page($page_id) && in_the_loop() && is_main_query()) return do_shortcode('[sabri_marketplace]');
        return $content;
    }

    public function disable_marketplace_cache(): void {
        if (!SMP_Utils::is_marketplace_request()) return;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        do_action('litespeed_control_set_nocache', 'Sabri Marketplace authenticated and direct-deal pages are dynamic.');
    }

    public function admin_notices(): void {
        if (!current_user_can('manage_options')) return;
        $page_id = (int) get_option('smp_marketplace_page_id');
        if (!$page_id || get_post_status($page_id) !== 'publish') {
            echo '<div class="notice notice-error"><p><strong>Sabri Marketplace:</strong> Marketplace page needs repair. Open <a href="' . esc_url(admin_url('admin.php?page=sabri-marketplace-system')) . '">System Check</a>.</p></div>';
        }
        $integrations = SMP_Integrations::status();
        $missing = array_keys(array_filter($integrations, static fn($active) => !$active));
        if ($missing) {
            echo '<div class="notice notice-error"><p><strong>Sabri Marketplace:</strong> Mandatory platform integrations are missing: ' . esc_html(implode(', ', $missing)) . '. Selling, notifications, or layout integration will remain fail-closed until corrected.</p></div>';
        }
        if (!SMP_Utils::attachment_scanner_available()) {
            echo '<div class="notice notice-warning"><p><strong>Sabri Marketplace:</strong> Private chat uploads are fail-closed because no malware scanner is configured. Define <code>SMP_CLAMAV_COMMAND</code> or provide the <code>smp_attachment_scan_result</code> filter.</p></div>';
        }
    }
}

Sabri_Marketplace::instance();
