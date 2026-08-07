<?php
defined('ABSPATH') || exit;
require MKT_DIR . 'templates/_header.php';
$listing = MKT_Listings::get((string) get_query_var('mkt_object_id'), false);
if (!$listing) { status_header(404); ?>
<section class="mkt-empty"><span aria-hidden="true">!</span><h1><?php esc_html_e('Listing unavailable', 'marketplace'); ?></h1><p><?php esc_html_e('The listing may have expired, been removed, or no longer be publicly eligible.', 'marketplace'); ?></p><a class="mkt-button mkt-button-primary" href="<?php echo esc_url(home_url('/marketplace/')); ?>"><?php esc_html_e('Return to Marketplace', 'marketplace'); ?></a></section>
<?php require MKT_DIR . 'templates/_footer.php'; return; }
$item = MKT_Listings::public_dto($listing);
?>
<article class="mkt-listing-detail" data-listing-id="<?php echo esc_attr($item['public_id']); ?>">
  <div class="mkt-gallery" aria-label="<?php esc_attr_e('Listing media', 'marketplace'); ?>">
    <?php if (!$item['media']): ?><div class="mkt-gallery-empty" aria-hidden="true">▧</div><?php endif; ?>
    <?php foreach ($item['media'] as $media): $url = $media['metadata']['url'] ?? $media['metadata']['thumbnail_url'] ?? ''; if (!$url) continue; ?><figure><img src="<?php echo esc_url((string) $url); ?>" alt="<?php echo esc_attr((string) $media['alt_text']); ?>" loading="lazy"></figure><?php endforeach; ?>
  </div>
  <div class="mkt-detail-panel">
    <p class="mkt-eyebrow"><?php echo esc_html(ucwords(str_replace('_',' ',(string) $item['category']))); ?></p>
    <h1><?php echo esc_html((string) $item['title']); ?></h1>
    <p class="mkt-price mkt-price-large"><bdi><?php echo esc_html((string) $item['currency'] . ' ' . (string) $item['price']); ?></bdi></p>
    <div class="mkt-trust-strip"><span>✓ <?php esc_html_e('Approved listing', 'marketplace'); ?></span><span>0% <?php esc_html_e('platform commission', 'marketplace'); ?></span><span>↻ <?php echo esc_html(sprintf(__('Updated %s', 'marketplace'), mysql2date(get_option('date_format'), (string) $item['updated_at']))); ?></span></div>
    <div class="mkt-description"><?php echo wp_kses_post(wpautop((string) $item['description'])); ?></div>
    <dl class="mkt-facts"><div><dt><?php esc_html_e('Seller', 'marketplace'); ?></dt><dd><?php echo esc_html((string) $item['seller']['store_name']); ?></dd></div><div><dt><?php esc_html_e('Condition', 'marketplace'); ?></dt><dd><?php echo esc_html(ucwords(str_replace('_',' ',(string) $item['condition_name']))); ?></dd></div><div><dt><?php esc_html_e('Location', 'marketplace'); ?></dt><dd><?php echo esc_html(trim(implode(', ', array_filter([(string) $item['location_city'],(string) $item['location_region'],(string) $item['location_country']])))); ?></dd></div><div><dt><?php esc_html_e('Availability', 'marketplace'); ?></dt><dd><?php echo esc_html(ucwords(str_replace('_',' ',(string) $item['availability']))); ?></dd></div></dl>
    <div class="mkt-actions">
      <?php if (is_user_logged_in()): ?>
      <button class="mkt-button mkt-button-primary" type="button" data-mkt-chat>◉ <?php esc_html_e('Message seller', 'marketplace'); ?></button>
      <button class="mkt-button mkt-button-secondary" type="button" data-mkt-offer>¤ <?php esc_html_e('Make an offer', 'marketplace'); ?></button>
      <button class="mkt-button mkt-button-quiet" type="button" data-mkt-save>♡ <?php esc_html_e('Save', 'marketplace'); ?></button>
      <button class="mkt-button mkt-button-quiet" type="button" data-mkt-report>⚑ <?php esc_html_e('Report', 'marketplace'); ?></button>
      <?php else: ?><a class="mkt-button mkt-button-primary" href="<?php echo esc_url(wp_login_url($item['url'])); ?>"><?php esc_html_e('Sign in to contact seller', 'marketplace'); ?></a><?php endif; ?>
      <button class="mkt-button mkt-button-quiet" type="button" data-mkt-share data-share-url="<?php echo esc_url($item['url']); ?>">⇧ <?php esc_html_e('Share', 'marketplace'); ?></button>
    </div>
    <aside class="mkt-safety-note"><h2><?php esc_html_e('Transaction safety', 'marketplace'); ?></h2><p><?php esc_html_e('Marketplace records offers and deal status but does not guarantee payment, delivery, authenticity or medical outcomes. Verify the seller and product before paying. Do not share patient records.', 'marketplace'); ?></p></aside>
  </div>
</article>
<div class="mkt-modal" hidden data-mkt-offer-modal role="dialog" aria-modal="true" aria-labelledby="mkt-offer-title"><div class="mkt-modal-panel"><button class="mkt-modal-close" type="button" data-mkt-close aria-label="<?php esc_attr_e('Close', 'marketplace'); ?>">×</button><h2 id="mkt-offer-title"><?php esc_html_e('Make an offer', 'marketplace'); ?></h2><form data-mkt-offer-form><label><span><?php esc_html_e('Amount', 'marketplace'); ?></span><input required type="number" min="0.01" step="0.01" name="amount"></label><label><span><?php esc_html_e('Currency', 'marketplace'); ?></span><input required maxlength="3" name="currency" value="<?php echo esc_attr((string) $item['currency']); ?>"></label><label><span><?php esc_html_e('Terms', 'marketplace'); ?></span><textarea name="terms" rows="4"></textarea></label><button class="mkt-button mkt-button-primary" type="submit"><?php esc_html_e('Submit structured offer', 'marketplace'); ?></button></form></div></div>
<div class="mkt-modal" hidden data-mkt-report-modal role="dialog" aria-modal="true" aria-labelledby="mkt-report-title"><div class="mkt-modal-panel"><button class="mkt-modal-close" type="button" data-mkt-close aria-label="<?php esc_attr_e('Close', 'marketplace'); ?>">×</button><h2 id="mkt-report-title"><?php esc_html_e('Report listing', 'marketplace'); ?></h2><form data-mkt-report-form><label><span><?php esc_html_e('Reason', 'marketplace'); ?></span><select name="reason"><option value="fraud"><?php esc_html_e('Fraud or scam', 'marketplace'); ?></option><option value="counterfeit"><?php esc_html_e('Counterfeit', 'marketplace'); ?></option><option value="unsafe"><?php esc_html_e('Unsafe product', 'marketplace'); ?></option><option value="false_cure_claim"><?php esc_html_e('False cure claim', 'marketplace'); ?></option><option value="privacy"><?php esc_html_e('Privacy violation', 'marketplace'); ?></option><option value="other"><?php esc_html_e('Other', 'marketplace'); ?></option></select></label><label><span><?php esc_html_e('Details', 'marketplace'); ?></span><textarea name="details" rows="5"></textarea></label><button class="mkt-button mkt-button-primary" type="submit"><?php esc_html_e('Submit report', 'marketplace'); ?></button></form></div></div>
<div class="mkt-toast" hidden role="status" aria-live="polite" data-mkt-toast></div>
<?php require MKT_DIR . 'templates/_footer.php'; ?>
