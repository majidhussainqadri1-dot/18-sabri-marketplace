<?php
/**
 * Non-destructive uninstall by default.
 *
 * Data is purged only when both the explicit option and the deployment constant
 * are enabled. This protects listings, offers, deals, reports, disputes and
 * migration handoffs from accidental deletion.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

$purge_requested = (bool) get_option('mkt_purge_on_uninstall', false);
$purge_authorized = defined('MKT_ALLOW_DESTRUCTIVE_PURGE') && MKT_ALLOW_DESTRUCTIVE_PURGE === true;
if (!$purge_requested || !$purge_authorized) {
    return;
}
if (!current_user_can('manage_options')) {
    return;
}

global $wpdb;
$tables = [
    'mkt_media_refs','mkt_saves','mkt_offers','mkt_deals','mkt_reports','mkt_disputes',
    'mkt_listings','mkt_sellers','mkt_policies','mkt_outbox','mkt_inbox','mkt_idempotency',
    'mkt_audit','mkt_metrics_daily','mkt_migration_handoffs',
];
foreach ($tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($wpdb->prefix . $table) . '`');
}
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mkt\\_%'");
foreach (['mkt_sell','mkt_buy','mkt_moderate','mkt_review_disputes','mkt_review_marketplace','mkt_manage_policies','mkt_view_system'] as $capability) {
    foreach (wp_roles()->roles as $role_name => $_role_data) {
        $role = get_role($role_name);
        if ($role) $role->remove_cap($capability);
    }
}
