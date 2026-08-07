<?php
defined('ABSPATH') || exit;
get_header();
?>
<main id="mkt-main" class="mkt-page" data-mkt-route="<?php echo esc_attr((string) get_query_var('mkt_route')); ?>">
  <div class="mkt-container">
    <nav class="mkt-context-nav" aria-label="<?php esc_attr_e('Marketplace navigation', 'marketplace'); ?>">
      <?php if (!MKT_Integrations::shell_owns_context_navigation()): ?>
        <a class="mkt-icon-link" href="<?php echo esc_url(MKT_Routes::safe_back_url()); ?>" aria-label="<?php esc_attr_e('Back', 'marketplace'); ?>"><span aria-hidden="true">⟲</span> <span><?php esc_html_e('Back', 'marketplace'); ?></span></a>
        <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Home', 'marketplace'); ?>"><span aria-hidden="true">⌂</span> <span><?php esc_html_e('Home', 'marketplace'); ?></span></a>
      <?php endif; ?>
      <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/marketplace/')); ?>"><span aria-hidden="true">▦</span> <span><?php esc_html_e('Marketplace', 'marketplace'); ?></span></a>
      <?php if (is_user_logged_in()): ?>
        <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/marketplace/dashboard/')); ?>"><span aria-hidden="true">☷</span> <span><?php esc_html_e('Dashboard', 'marketplace'); ?></span></a>
        <a class="mkt-icon-link" href="<?php echo esc_url(home_url('/marketplace/sell/')); ?>"><span aria-hidden="true">＋</span> <span><?php esc_html_e('Create listing', 'marketplace'); ?></span></a>
      <?php endif; ?>
    </nav>
