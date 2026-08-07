<?php
defined('ABSPATH') || exit;
require MKT_DIR . 'templates/_header.php';
$user_id = get_current_user_id();
$eligibility = MKT_Auth::seller_eligibility($user_id);
$categories = MKT_Policy::categories();
$edit_id = sanitize_text_field(wp_unslash((string) ($_GET['id'] ?? '')));
$listing = $edit_id && wp_is_uuid($edit_id) ? MKT_Listings::get($edit_id, true) : null;
if ($listing && !MKT_Auth::own_listing($listing, $user_id) && !current_user_can('mkt_moderate')) $listing = null;
$values = $listing ? MKT_Listings::private_dto($listing) : [
    'public_id' => '', 'version' => 0, 'title' => '', 'category' => '', 'subcategory' => '', 'listing_type' => 'product',
    'condition_name' => 'new', 'description' => '', 'price' => '', 'currency' => MKT_DB::settings()['default_currency'],
    'quantity' => '1', 'availability' => 'available', 'location_country' => '', 'location_region' => '', 'location_city' => '',
    'delivery_modes' => [], 'contact_modes' => ['file17_chat'], 'declarations' => [], 'media' => [], 'status' => 'draft',
];
$is_edit = !empty($values['public_id']);
?>
<section class="mkt-form-page">
<header><p class="mkt-eyebrow"><?php esc_html_e('Seller workspace', 'marketplace'); ?></p><h1><?php echo esc_html($is_edit ? __('Edit listing', 'marketplace') : __('Create a listing', 'marketplace')); ?></h1><p><?php esc_html_e('Listings are reviewed under category, safety, rights, privacy and medical-claim policies. Platform commission is 0%.', 'marketplace'); ?></p></header>
<?php if (!$eligibility['eligible']): ?>
<div class="mkt-alert mkt-alert-error"><h2><?php esc_html_e('Selling is not available for this account', 'marketplace'); ?></h2><p><?php echo esc_html(implode(', ', array_map(static fn($v) => ucwords(str_replace('_',' ',(string) $v)), $eligibility['reasons']))); ?></p></div>
<?php elseif ($edit_id && !$listing): ?>
<div class="mkt-alert mkt-alert-error"><h2><?php esc_html_e('Listing unavailable', 'marketplace'); ?></h2><p><?php esc_html_e('The listing does not exist or you cannot edit it.', 'marketplace'); ?></p></div>
<?php else: ?>
<form class="mkt-form" data-mkt-listing-form data-listing-id="<?php echo esc_attr((string) $values['public_id']); ?>" data-listing-version="<?php echo esc_attr((string) $values['version']); ?>">
  <div class="mkt-form-section"><h2>1. <?php esc_html_e('Listing identity', 'marketplace'); ?></h2>
    <label><span><?php esc_html_e('Title', 'marketplace'); ?></span><input required maxlength="255" name="title" value="<?php echo esc_attr((string) $values['title']); ?>"></label>
    <div class="mkt-form-row">
      <label><span><?php esc_html_e('Category', 'marketplace'); ?></span><select required name="category"><option value=""><?php esc_html_e('Choose category', 'marketplace'); ?></option><?php foreach ($categories as $key => $rules): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) $values['category'], $key); ?>><?php echo esc_html((string) ($rules['label'] ?? $key)); ?></option><?php endforeach; ?></select></label>
      <label><span><?php esc_html_e('Subcategory', 'marketplace'); ?></span><input maxlength="120" name="subcategory" value="<?php echo esc_attr((string) $values['subcategory']); ?>"></label>
      <label><span><?php esc_html_e('Type', 'marketplace'); ?></span><select name="listing_type"><?php foreach (['product' => __('Product','marketplace'),'service' => __('Service','marketplace'),'digital' => __('Digital item','marketplace')] as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) $values['listing_type'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
      <label><span><?php esc_html_e('Condition', 'marketplace'); ?></span><select name="condition_name"><?php foreach (['new' => __('New','marketplace'),'used' => __('Used','marketplace'),'refurbished' => __('Refurbished','marketplace'),'not_applicable' => __('Not applicable','marketplace')] as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) $values['condition_name'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
    </div>
    <label><span><?php esc_html_e('Description', 'marketplace'); ?></span><textarea required rows="9" name="description"><?php echo esc_textarea((string) $values['description']); ?></textarea></label>
  </div>
  <div class="mkt-form-section"><h2>2. <?php esc_html_e('Price and availability', 'marketplace'); ?></h2><div class="mkt-form-row">
    <label><span><?php esc_html_e('Price', 'marketplace'); ?></span><input required type="number" min="0" step="0.01" name="price" value="<?php echo esc_attr((string) $values['price']); ?>"></label>
    <label><span><?php esc_html_e('Currency', 'marketplace'); ?></span><select required name="currency"><?php foreach (MKT_Contracts::allowed_currencies() as $currency): ?><option value="<?php echo esc_attr($currency); ?>" <?php selected((string) $values['currency'], $currency); ?>><?php echo esc_html($currency); ?></option><?php endforeach; ?></select></label>
    <label><span><?php esc_html_e('Quantity', 'marketplace'); ?></span><input required type="number" min="0" step="0.001" name="quantity" value="<?php echo esc_attr((string) $values['quantity']); ?>"></label>
    <label><span><?php esc_html_e('Availability', 'marketplace'); ?></span><select name="availability"><?php foreach (['available' => __('Available','marketplace'),'limited' => __('Limited','marketplace'),'preorder' => __('Pre-order','marketplace'),'service_schedule' => __('By schedule','marketplace')] as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) $values['availability'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
  </div></div>
  <div class="mkt-form-section"><h2>3. <?php esc_html_e('Location and delivery', 'marketplace'); ?></h2><div class="mkt-form-row">
    <label><span><?php esc_html_e('Country code', 'marketplace'); ?></span><input maxlength="2" name="location_country" placeholder="PK" value="<?php echo esc_attr((string) $values['location_country']); ?>"></label>
    <label><span><?php esc_html_e('Region', 'marketplace'); ?></span><input name="location_region" value="<?php echo esc_attr((string) $values['location_region']); ?>"></label>
    <label><span><?php esc_html_e('City', 'marketplace'); ?></span><input name="location_city" value="<?php echo esc_attr((string) $values['location_city']); ?>"></label>
  </div><fieldset><legend><?php esc_html_e('Delivery modes', 'marketplace'); ?></legend><?php foreach (['pickup' => __('Pickup','marketplace'),'courier' => __('Courier arranged directly','marketplace'),'digital_delivery' => __('Digital delivery','marketplace'),'online_service' => __('Online service','marketplace')] as $key => $label): ?><label class="mkt-check"><input type="checkbox" name="delivery_modes[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, (array) $values['delivery_modes'], true)); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset></div>
  <div class="mkt-form-section"><h2>4. <?php esc_html_e('Communication', 'marketplace'); ?></h2><p><?php esc_html_e('Product-linked messages are provided only by File 17. File 18 creates no parallel chat database.', 'marketplace'); ?></p><label class="mkt-check"><input type="checkbox" name="contact_modes[]" value="file17_chat" <?php checked(in_array('file17_chat', (array) $values['contact_modes'], true)); ?>> <?php esc_html_e('Allow secure File 17 chat', 'marketplace'); ?></label></div>
  <div class="mkt-form-section"><h2>5. <?php esc_html_e('Approved media reference', 'marketplace'); ?></h2><p><?php esc_html_e('Use an approved reference from the central secure media service. The original file remains with its canonical media owner.', 'marketplace'); ?></p><div class="mkt-form-row"><label><span><?php esc_html_e('Provider public ID', 'marketplace'); ?></span><input name="media_provider_public_id" placeholder="UUID or provider reference"></label><label><span><?php esc_html_e('Alternative text', 'marketplace'); ?></span><input name="media_alt_text" maxlength="255"></label></div>
  <?php if (!empty($values['media'])): ?><ul class="mkt-media-list" data-mkt-media-list><?php foreach ((array) $values['media'] as $media): ?><li data-media-id="<?php echo esc_attr((string) $media['public_id']); ?>"><span><?php echo esc_html((string) ($media['alt_text'] ?: $media['media_kind'])); ?></span> <button type="button" class="mkt-button mkt-button-secondary" data-mkt-remove-media="<?php echo esc_attr((string) $media['public_id']); ?>"><?php esc_html_e('Remove', 'marketplace'); ?></button></li><?php endforeach; ?></ul><?php endif; ?></div>
  <div class="mkt-form-section"><h2>6. <?php esc_html_e('Declarations', 'marketplace'); ?></h2><?php foreach (['truthful' => __('The listing is truthful and not misleading.','marketplace'),'rights_owned' => __('I own or lawfully use all text and media.','marketplace'),'no_patient_data' => __('The listing contains no patient-identifying data.','marketplace'),'zero_commission_understood' => __('I understand that the platform charges 0% commission and does not guarantee payment or delivery.','marketplace')] as $key => $label): ?><label class="mkt-check"><input required type="checkbox" name="declarations[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($values['declarations'][$key])); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></div>
  <div class="mkt-form-actions"><button class="mkt-button mkt-button-primary" type="submit">✓ <?php echo esc_html($is_edit ? __('Save draft','marketplace') : __('Create draft','marketplace')); ?></button><?php if ($is_edit && (string) $values['status'] === 'draft'): ?><button class="mkt-button mkt-button-primary" type="button" data-mkt-submit-review><?php esc_html_e('Submit for review', 'marketplace'); ?></button><?php endif; ?><a class="mkt-button mkt-button-secondary" href="<?php echo esc_url(home_url('/marketplace/dashboard/')); ?>"><?php esc_html_e('Cancel', 'marketplace'); ?></a></div>
</form>
<?php endif; ?></section><div class="mkt-toast" hidden role="status" aria-live="polite" data-mkt-toast></div>
<?php require MKT_DIR . 'templates/_footer.php'; ?>
