<?php
defined('ABSPATH') || exit;

final class SMP_Shortcode {
    private static bool $registered = false;

    public static function register_assets(): void {
        if (self::$registered) return;
        self::$registered = true;
        wp_register_style('sabri-marketplace', SMP_URL . 'assets/css/marketplace.css', [], SMP_VERSION);
        wp_register_script('sabri-marketplace', SMP_URL . 'assets/js/marketplace.js', [], SMP_VERSION, true);
    }

    public static function enqueue_if_marketplace(): void {
        $page_id = (int) get_option('smp_marketplace_page_id');
        $safe = (int) get_query_var('smp_marketplace_app') === 1 || isset($_GET['smp-marketplace-safe']);
        if (($page_id && is_page($page_id)) || $safe) self::enqueue_assets();
    }

    private static function enqueue_assets(): void {
        self::register_assets();
        wp_enqueue_style('sabri-marketplace');
        wp_enqueue_script('sabri-marketplace');
        $uid = get_current_user_id();
        $user = $uid ? wp_get_current_user() : null;
        wp_localize_script('sabri-marketplace', 'SMP_CONFIG', [
            'version' => SMP_VERSION,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smp_ajax'),
            'isLoggedIn' => (bool) $uid,
            'currentUser' => $uid ? ['id' => $uid, 'name' => $user instanceof WP_User ? $user->display_name : '', 'email' => $user instanceof WP_User ? $user->user_email : '', 'avatar' => get_avatar_url($uid, ['size' => 96])] : null,
            'marketplacePage' => SMP_Activator::marketplace_url(),
            'loginUrl' => wp_login_url(SMP_Activator::marketplace_url()),
            'registerUrl' => wp_registration_url(),
            'notificationsUrl' => SMP_Integrations::notifications_url(),
            'shellActive' => SMP_Integrations::shell_active(),
            'contactRequiresLogin' => (bool) get_option('smp_reveal_contacts_to_logged_in', 1),
            'guestBrowse' => (bool) get_option('smp_allow_guest_browse', 1),
            'currency' => (string) get_option('smp_default_currency', 'PKR'),
            'strings' => ['genericError' => 'Marketplace could not complete the request.', 'loginRequired' => 'Please log in to continue.'],
        ]);
    }

    public static function render($atts = []): string {
        self::enqueue_assets();
        if (!is_user_logged_in() && !(bool) get_option('smp_allow_guest_browse', 1)) {
            return '<section class="smp-alert"><h2>' . esc_html__('Marketplace sign-in required', 'sabri-marketplace') . '</h2><p>' . esc_html__('Please log in to browse and use Marketplace.', 'sabri-marketplace') . '</p><p><a class="smp-button smp-button-primary" href="' . esc_url(wp_login_url(SMP_Activator::marketplace_url())) . '">' . esc_html__('Log In', 'sabri-marketplace') . '</a></p></section>';
        }
        ob_start();
        include SMP_DIR . 'templates/marketplace-app.php';
        return (string) ob_get_clean();
    }
}
