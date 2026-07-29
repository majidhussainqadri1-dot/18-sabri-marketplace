<?php
/**
 * Sabri Marketplace uninstall boundary.
 *
 * Data is preserved by default. Destructive deletion occurs only when the
 * administrator explicitly enables `smp_delete_data_on_uninstall`.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

if (!(bool) get_option('smp_delete_data_on_uninstall', 0)) return;

global $wpdb;
$tables = ['sellers','products','wishlist','conversations','messages','message_reactions','blocks','reports','audit_log','notifications'];
foreach ($tables as $name) {
    $table = $wpdb->prefix . 'smp_' . $name;
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$option_names = [
    'smp_db_version','smp_plugin_version','smp_marketplace_page_id','smp_default_currency','smp_default_country','smp_support_email',
    'smp_max_upload_mb','smp_max_product_images','smp_max_chat_upload_mb','smp_require_seller_approval','smp_require_product_approval',
    'smp_health_license_required','smp_allow_guest_browse','smp_deleted_chat_file_retention_days','smp_message_retention_days',
    'smp_report_retention_days','smp_audit_retention_days','smp_chat_poll_seconds','smp_reveal_contacts_to_logged_in',
    'smp_require_buyer_contact_before_chat','smp_message_edit_minutes','smp_delete_data_on_uninstall','smp_direct_deal_disclaimer',
    'smp_prohibited_terms','smp_restricted_categories','smp_categories','smp_direct_deal_mode','smp_enable_cod','smp_enable_bank_transfer',
    'smp_payment_gateway_enabled','smp_platform_commission','smp_sensitive_migration_version','smp_attachment_migration_version',
    'smp_legacy_notification_last_id','smp_legacy_notifications_decommissioned','smp_flush_rewrite_rules',
];
foreach ($option_names as $name) delete_option($name);

$private = WP_CONTENT_DIR . '/sabri-private-files/marketplace';
if (is_dir($private)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    @rmdir($private);
}
