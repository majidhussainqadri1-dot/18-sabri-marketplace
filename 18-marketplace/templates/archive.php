<?php
defined('ABSPATH') || exit;
require MKT_DIR . 'templates/_header.php';
$filters = [
    'q' => isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '',
    'category' => isset($_GET['category']) ? sanitize_key(wp_unslash((string) $_GET['category'])) : '',
    'country' => isset($_GET['country']) ? sanitize_text_field(wp_unslash((string) $_GET['country'])) : '',
    'city' => isset($_GET['city']) ? sanitize_text_field(wp_unslash((string) $_GET['city'])) : '',
    'currency' => isset($_GET['currency']) ? sanitize_text_field(wp_unslash((string) $_GET['currency'])) : '',
    'price_min' => isset($_GET['price_min']) ? (float) $_GET['price_min'] : '',
    'price_max' => isset($_GET['price_max']) ? (float) $_GET['price_max'] : '',
    'limit' => 24,
];
$result = MKT_Listings::search($filters);
$categories = MKT_Policy::categories();
?>
<section class="mkt-hero" aria-labelledby="mkt-title">
  <div><p class="mkt-eyebrow"><?php esc_html_e('Direct • Ethical • Zero commission', 'marketplace'); ?></p>
  <h1 id="mkt-title"><?php esc_html_e('Marketplace', 'marketplace'); ?></h1>
  <p><?php esc_html_e('Discover approved homeopathy books, learning services, clinic equipment and other permitted products. The platform charges no commission and does not guarantee payment, delivery or cure outcomes.', 'marketplace'); ?></p></div>
  <div class="mkt-zero-card" aria-label="<?php esc_attr_e('Zero commission policy', 'marketplace'); ?>"><strong>0%</strong><span><?php esc_html_e('platform commission', 'marketplace'); ?></span></div>
</section>

<form class="mkt-filter-panel" method="get" action="<?php echo esc_url(home_url('/marketplace/')); ?>" role="search" aria-label="<?php esc_attr_e('Search marketplace', 'marketplace'); ?>">
  <label><span><?php esc_html_e('Search', 'marketplace'); ?></span><input type="search" name="q" value="<?php echo esc_attr($filters['q']); ?>" placeholder="<?php esc_attr_e('Books, equipment, services…', 'marketplace'); ?>"></label>
  <label><span><?php esc_html_e('Category', 'marketplace'); ?></span><select name="category"><option value=""><?php esc_html_e('All categories', 'marketplace'); ?></option><?php foreach ($categories as $key => $rules): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['category'], $key); ?>><?php echo esc_html((string) ($rules['label'] ?? $key)); ?></option><?php endforeach; ?></select></label>
  <label><span><?php esc_html_e('Country', 'marketplace'); ?></span><input name="country" maxlength="2" value="<?php echo esc_attr($filters['country']); ?>" placeholder="PK"></label>
  <label><span><?php esc_html_e('City', 'marketplace'); ?></span><input name="city" value="<?php echo esc_attr($filters['city']); ?>"></label>
  <label><span><?php esc_html_e('Minimum price', 'marketplace'); ?></span><input type="number" min="0" step="0.01" name="price_min" value="<?php echo esc_attr((string) $filters['price_min']); ?>"></label>
  <label><span><?php esc_html_e('Maximum price', 'marketplace'); ?></span><input type="number" min="0" step="0.01" name="price_max" value="<?php echo esc_attr((string) $filters['price_max']); ?>"></label>
  <button class="mkt-button mkt-button-primary" type="submit">⌕ <?php esc_html_e('Search', 'marketplace'); ?></button>
</form>

<div class="mkt-results-header"><h2><?php esc_html_e('Approved listings', 'marketplace'); ?></h2><p><?php echo esc_html(sprintf(_n('%d listing', '%d listings', count($result['items']), 'marketplace'), count($result['items']))); ?></p></div>
<div class="mkt-card-grid" id="mkt-listings" aria-live="polite">
<?php if (!$result['items']): ?>
  <div class="mkt-empty"><span aria-hidden="true">⌕</span><h3><?php esc_html_e('No matching listings', 'marketplace'); ?></h3><p><?php esc_html_e('Try broader filters or return later after additional listings are reviewed.', 'marketplace'); ?></p></div>
<?php endif; ?>
<?php foreach ($result['items'] as $item): ?>
  <article class="mkt-card">
    <a class="mkt-card-media" href="<?php echo esc_url($item['url']); ?>" tabindex="-1" aria-hidden="true">
      <?php $thumb = $item['media'][0]['metadata']['thumbnail_url'] ?? $item['media'][0]['metadata']['url'] ?? ''; ?>
      <?php if ($thumb): ?><img src="<?php echo esc_url((string) $thumb); ?>" alt="" loading="lazy"><?php else: ?><span class="mkt-media-placeholder">▧</span><?php endif; ?>
    </a>
    <div class="mkt-card-body">
      <div class="mkt-card-meta"><span><?php echo esc_html(ucwords(str_replace('_',' ',(string) $item['category']))); ?></span><?php if ($item['featured_label']): ?><span class="mkt-label"><?php echo esc_html((string) $item['featured_label']); ?></span><?php endif; ?></div>
      <h3><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html((string) $item['title']); ?></a></h3>
      <p class="mkt-price"><bdi><?php echo esc_html((string) $item['currency'] . ' ' . (string) $item['price']); ?></bdi></p>
      <p class="mkt-seller">♙ <?php echo esc_html((string) $item['seller']['store_name']); ?></p>
      <p class="mkt-location">⌖ <?php echo esc_html(trim(implode(', ', array_filter([(string) $item['location_city'], (string) $item['location_country']])))); ?></p>
      <a class="mkt-button mkt-button-secondary" href="<?php echo esc_url($item['url']); ?>"><?php esc_html_e('View listing', 'marketplace'); ?> →</a>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if ($result['has_more']): ?><button type="button" class="mkt-button mkt-load-more" data-mkt-load-more data-cursor="<?php echo esc_attr((string) $result['next_cursor']); ?>"><?php esc_html_e('Load more', 'marketplace'); ?></button><?php endif; ?>
<?php require MKT_DIR . 'templates/_footer.php'; ?>
