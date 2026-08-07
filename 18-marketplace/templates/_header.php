<?php
defined('ABSPATH') || exit;
get_header();
?>
<main id="mkt-main" class="mkt-page" data-mkt-route="<?php echo esc_attr((string) get_query_var('mkt_route')); ?>">
  <div class="mkt-container">
    <nav class="mkt-context-nav" aria-label="<?php esc_attr_e('Marketplace navigation', 'marketplace'); ?>">
      <a class="mkt-icon-link" href="<?php echo esc_url(wp_get_referer() && wp_validate_redirect(wp_get_referer()) ? wp_get_referer() : home_url('/marketplace/')); ?>" aria-label="<?php esc_attr_e('Back', 'marketplace'); ?>">← <span><?php esc_html_e('Back', 'marketplace'); ?></span></a>
      <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Home', 'marketplace'); ?>">⌂ <span><?php esc_html_e('Home', 'marketplace'); ?></span></a>
      <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/marketplace/')); ?>">▦ <span><?php esc_html_e('Marketplace', 'marketplace'); ?></span></a>
      <?php if (is_user_logged_in()): ?>
        <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/marketplace/dashboard/')); ?>">☷ <span><?php esc_html_e('Dashboard', 'marketplace'); ?></span></a>
        <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/marketplace/sell/')); ?>">＋ <span><?php esc_html_e('Create listing', 'marketplace'); ?></span></a>
      <?php endif; ?>
    </nav>
