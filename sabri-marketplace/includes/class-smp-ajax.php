<?php
defined('ABSPATH') || exit;

final class SMP_Ajax {
    public static function handle(): void {
        check_ajax_referer('smp_ajax', 'nonce');
        $op = sanitize_key($_REQUEST['op'] ?? 'bootstrap');
        self::enforce_access_policy($op);
        SMP_Rate_Limiter::enforce($op);
        try {
            match ($op) {
                'bootstrap' => self::bootstrap(),
                'product' => self::product(),
                'store' => self::store(),
                'seller_apply' => self::seller_apply(),
                'buyer_contact_save' => self::buyer_contact_save(),
                'product_save' => self::product_save(),
                'product_status' => self::product_status(),
                'wishlist_toggle' => self::wishlist_toggle(),
                'contact_reveal' => self::contact_reveal(),
                'conversation_start' => self::conversation_start(),
                'conversations' => self::conversations(),
                'messages' => self::messages(),
                'message_send' => self::message_send(),
                'message_edit' => self::message_edit(),
                'message_delete' => self::message_delete(),
                'reaction_toggle' => self::reaction_toggle(),
                'typing' => self::typing(),
                'offer_action' => self::offer_action(),
                'share_contact' => self::share_contact(),
                'conversation_status' => self::conversation_status(),
                'block_toggle' => self::block_toggle(),
                'report_submit' => self::report_submit(),
                'seller_dashboard' => self::seller_dashboard(),
                'notifications' => self::notifications(),
                'notification_read' => self::notification_read(),
                default => wp_send_json_error(['message' => 'Unknown Marketplace operation.'], 400),
            };
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Sabri Marketplace ' . SMP_VERSION . ' error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Marketplace could not complete the request.'], 500);
        }
    }

    private static function enforce_access_policy(string $op): void {
        $public = ['bootstrap', 'product', 'store'];
        if (is_user_logged_in()) return;
        if (!in_array($op, $public, true) || !(bool) get_option('smp_allow_guest_browse', 1)) {
            wp_send_json_error([
                'message' => 'Please log in to continue.',
                'loginUrl' => wp_login_url(SMP_Activator::marketplace_url()),
            ], 401);
        }
    }

    private static function payload(): array {
        $payload = $_POST['payload'] ?? '';
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode(wp_unslash($payload), true);
            if (is_array($decoded)) return $decoded;
        }
        return array_map(static fn($v) => is_string($v) ? wp_unslash($v) : $v, $_POST);
    }

    private static function require_login(): int {
        $id = get_current_user_id();
        if (!$id) wp_send_json_error(['message' => 'Please log in to continue.', 'loginUrl' => wp_login_url(SMP_Activator::marketplace_url())], 401);
        return $id;
    }

    private static function bootstrap(): void {
        global $wpdb;
        $in = self::payload();
        $limit = min(80, max(8, absint($in['limit'] ?? 40)));
        $search = sanitize_text_field($in['search'] ?? '');
        $category = sanitize_text_field($in['category'] ?? '');
        $type = sanitize_key($in['type'] ?? '');
        $sort = sanitize_key($in['sort'] ?? 'recommended');
        $where = "p.status IN ('published','approved') AND s.status='approved'";
        $params = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (p.title LIKE %s OR p.description LIKE %s OR p.short_description LIKE %s OR p.brand LIKE %s OR s.store_name LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($category !== '') { $where .= ' AND p.category=%s'; $params[] = $category; }
        if ($type !== '') { $where .= ' AND p.product_type=%s'; $params[] = $type; }
        $order = match ($sort) {
            'price_low' => 'COALESCE(NULLIF(p.sale_price,0),p.price) ASC',
            'price_high' => 'COALESCE(NULLIF(p.sale_price,0),p.price) DESC',
            'newest' => 'p.published_at DESC,p.id DESC',
            'popular' => 'p.views DESC,p.id DESC',
            default => "CASE WHEN p.deal_status='available' THEN 0 ELSE 1 END,p.featured DESC,p.published_at DESC,p.id DESC",
        };
        $sql = 'SELECT p.*,s.store_name,s.user_id seller_user_id,s.verification_level seller_verification,s.contact_verified,s.status seller_status,s.city seller_city,s.country seller_country,s.allow_chat seller_allow_chat,s.allow_calls seller_allow_calls,s.allow_offers seller_allow_offers FROM ' . SMP_DB::table('products') . ' p LEFT JOIN ' . SMP_DB::table('sellers') . ' s ON s.id=p.seller_id WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT %d';
        $params[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $rows = array_values(array_filter($rows, static fn($row) => SMP_Integrations::approved_member((int) ($row['seller_user_id'] ?? 0))));
        $uid = get_current_user_id();
        $wishlist = []; $seller = null; $contact = null; $unread_notifications = 0; $unread_chats = 0;
        if ($uid) {
            $wishlist = array_map('intval', $wpdb->get_col($wpdb->prepare('SELECT product_id FROM ' . SMP_DB::table('wishlist') . ' WHERE user_id=%d', $uid)));
            $seller = SMP_Utils::current_seller($uid);
            $contact = SMP_Utils::buyer_contact($uid);
            $unread_notifications = 0;
            $unread_chats = self::unread_chat_count($uid);
        }
        wp_send_json_success([
            'products' => array_map([self::class, 'product_with_seller'], $rows),
            'categories' => SMP_Utils::categories(), 'productTypes' => SMP_Utils::product_types(), 'wishlist' => $wishlist,
            'seller' => $seller, 'buyerContact' => $contact, 'unreadNotifications' => $unread_notifications, 'unreadChats' => $unread_chats,
            'settings' => [
                'currency' => (string) get_option('smp_default_currency', 'PKR'), 'country' => (string) get_option('smp_default_country', 'Pakistan'),
                'supportEmail' => (string) get_option('smp_support_email', get_option('admin_email')), 'directDeal' => true,
                'disclaimer' => SMP_Utils::disclaimer(), 'pollSeconds' => max(3, (int) get_option('smp_chat_poll_seconds', 4)),
                'requireBuyerContact' => (bool) get_option('smp_require_buyer_contact_before_chat', 1),
                'contactRequiresLogin' => (bool) get_option('smp_reveal_contacts_to_logged_in', 1),
                'notificationsUrl' => SMP_Integrations::notifications_url(),
                'shellActive' => SMP_Integrations::shell_active(),
                'membershipActive' => SMP_Integrations::membership_active(),
            ],
        ]);
    }

    private static function product_with_seller(array $row): array {
        $p = SMP_Utils::public_product($row);
        $p['seller'] = [
            'id' => (int) ($row['seller_id'] ?? 0), 'userId' => (int) ($row['seller_user_id'] ?? 0),
            'storeName' => (string) ($row['store_name'] ?? ''), 'verification' => (string) ($row['seller_verification'] ?? ''),
            'contactVerified' => SMP_Integrations::approved_member((int) ($row['seller_user_id'] ?? 0)) && !empty(SMP_Integrations::membership_contact((int) ($row['seller_user_id'] ?? 0))['mobileVerified']), 'status' => (string) ($row['seller_status'] ?? ''),
            'city' => (string) ($row['seller_city'] ?? ''), 'country' => (string) ($row['seller_country'] ?? ''),
            'allowChat' => !isset($row['seller_allow_chat']) || !empty($row['seller_allow_chat']),
            'allowCalls' => !isset($row['seller_allow_calls']) || !empty($row['seller_allow_calls']),
            'allowOffers' => !isset($row['seller_allow_offers']) || !empty($row['seller_allow_offers']),
        ];
        return $p;
    }

    private static function product(): void {
        $in = self::payload();
        $id = absint($in['id'] ?? 0); if (!$id) wp_send_json_error(['message' => 'Invalid listing.'], 400);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT p.*,s.store_name,s.user_id seller_user_id,s.verification_level seller_verification,s.contact_verified,s.status seller_status,s.city seller_city,s.country seller_country,s.allow_chat seller_allow_chat,s.allow_calls seller_allow_calls,s.allow_offers seller_allow_offers FROM " . SMP_DB::table('products') . " p LEFT JOIN " . SMP_DB::table('sellers') . " s ON s.id=p.seller_id WHERE p.id=%d AND p.status IN ('published','approved') AND s.status='approved'", $id), ARRAY_A);
        if (!$row || !SMP_Integrations::approved_member((int) ($row['seller_user_id'] ?? 0))) wp_send_json_error(['message' => 'Listing not found.'], 404);
        $wpdb->query($wpdb->prepare('UPDATE ' . SMP_DB::table('products') . ' SET views=views+1 WHERE id=%d', $id));
        wp_send_json_success(['product' => self::product_with_seller($row), 'disclaimer' => SMP_Utils::disclaimer()]);
    }

    private static function store(): void {
        $in = self::payload();
        $id = absint($in['seller_id'] ?? 0); if (!$id) wp_send_json_error(['message' => 'Invalid store.'], 400);
        global $wpdb;
        $seller = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . SMP_DB::table('sellers') . " WHERE id=%d AND status='approved'", $id), ARRAY_A);
        if (!$seller || !SMP_Integrations::approved_member((int) $seller['user_id'])) wp_send_json_error(['message' => 'Store not found.'], 404);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT p.*,s.store_name,s.user_id seller_user_id,s.verification_level seller_verification,s.contact_verified,s.status seller_status,s.city seller_city,s.country seller_country,s.allow_chat seller_allow_chat,s.allow_calls seller_allow_calls,s.allow_offers seller_allow_offers FROM " . SMP_DB::table('products') . " p LEFT JOIN " . SMP_DB::table('sellers') . " s ON s.id=p.seller_id WHERE p.seller_id=%d AND p.status IN ('published','approved') ORDER BY p.deal_status='available' DESC,p.id DESC LIMIT 150", $id), ARRAY_A);
        $rows = array_values(array_filter($rows, static fn($row) => SMP_Integrations::approved_member((int) ($row['seller_user_id'] ?? 0))));
        wp_send_json_success(['seller' => SMP_Utils::public_seller($seller), 'products' => array_map([self::class,'product_with_seller'],$rows)]);
    }

    private static function seller_apply(): void {
        $uid = self::require_login();
        $eligibility = SMP_Integrations::seller_eligibility($uid);
        if (empty($eligibility['eligible'])) wp_send_json_error(['message' => (string) $eligibility['message']], 403);

        $in = self::payload();
        foreach (['legalName','storeName','email','country','city','address'] as $field) {
            if (trim((string) ($in[$field] ?? '')) === '') wp_send_json_error(['message' => 'Complete all required seller profile fields.'], 422);
        }
        $type = sanitize_key($in['sellerType'] ?? 'individual');
        if (!in_array($type, ['individual','business','service','health'], true)) $type = 'individual';
        $central = SMP_Integrations::membership_contact($uid);
        if (SMP_Utils::phone_digits((string) ($central['phone'] ?? '')) === '') {
            wp_send_json_error(['message' => 'A verified phone number is required in Sabri Membership Core.'], 422);
        }

        global $wpdb;
        $existing = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . SMP_DB::table('sellers') . ' WHERE user_id=%d', $uid));
        $data = [
            'user_id' => $uid,
            'seller_type' => $type,
            'legal_name' => sanitize_text_field((string) $in['legalName']),
            'store_name' => sanitize_text_field((string) $in['storeName']),
            'phone' => SMP_Utils::sanitize_phone((string) ($central['phone'] ?? '')),
            'alternate_phone' => SMP_Utils::sanitize_phone((string) ($in['alternatePhone'] ?? '')),
            'whatsapp' => SMP_Utils::sanitize_phone((string) ($central['whatsapp'] ?? $central['phone'] ?? '')),
            'email' => sanitize_email((string) $in['email']),
            'country' => sanitize_text_field((string) $in['country']),
            'city' => sanitize_text_field((string) $in['city']),
            'address' => sanitize_textarea_field((string) $in['address']),
            'verification_level' => 'central_membership',
            'contact_verified' => !empty($central['mobileVerified']) ? 1 : 0,
            'show_phone' => !empty($in['showPhone']) ? 1 : 0,
            'show_whatsapp' => !empty($in['showWhatsapp']) ? 1 : 0,
            'allow_chat' => 1,
            'allow_calls' => !empty($in['allowCalls']) ? 1 : 0,
            'allow_offers' => !empty($in['allowOffers']) ? 1 : 0,
            'preferred_contact' => in_array(($in['preferredContact'] ?? ''), ['chat','phone','whatsapp'], true) ? $in['preferredContact'] : 'chat',
            'call_hours' => sanitize_text_field((string) ($in['callHours'] ?? '')),
            'updated_at' => SMP_Utils::now(),
        ];

        if ($existing) {
            $current = (string) $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . SMP_DB::table('sellers') . ' WHERE id=%d', $existing));
            if ($current === 'rejected') $data['status'] = 'pending';
            if ($wpdb->update(SMP_DB::table('sellers'), $data, ['id' => $existing]) === false) throw new RuntimeException($wpdb->last_error ?: 'Seller profile update failed.');
            $id = $existing;
        } else {
            $data['status'] = (bool) get_option('smp_require_seller_approval', 1) ? 'pending' : 'approved';
            $data['created_at'] = SMP_Utils::now();
            if (!$wpdb->insert(SMP_DB::table('sellers'), $data)) throw new RuntimeException($wpdb->last_error ?: 'Seller profile creation failed.');
            $id = (int) $wpdb->insert_id;
        }
        SMP_Utils::audit('seller_apply', 'seller', $id, ['identityOwner' => 'file-00']);
        wp_send_json_success(['message' => 'Seller profile saved for review.', 'seller' => SMP_Utils::current_seller($uid)]);
    }

    private static function buyer_contact_save(): void {
        $uid=self::require_login(); $saved=SMP_Utils::save_buyer_contact($uid,self::payload());
        if(!$saved)wp_send_json_error(['message'=>'Both phone and WhatsApp numbers are required. Include country code.'],422);
        SMP_Utils::audit('buyer_contact_save','user',$uid); wp_send_json_success(['message'=>'Contact details saved.','contact'=>$saved]);
    }

    private static function product_save(): void {
        $uid = self::require_login();
        $seller = SMP_Utils::current_seller($uid);
        $eligibility = SMP_Integrations::seller_eligibility($uid);
        if (!$seller || ($seller['status'] ?? '') !== 'approved' || empty($eligibility['eligible'])) {
            wp_send_json_error(['message' => 'An approved, centrally verified seller account is required.'], 403);
        }
        $in = self::payload();
        foreach (['title','category','productType','description','price'] as $field) {
            if (trim((string) ($in[$field] ?? '')) === '') wp_send_json_error(['message' => 'Complete all required listing fields.'], 422);
        }
        $title = sanitize_text_field((string) $in['title']);
        $description = wp_kses_post((string) $in['description']);
        $matches = SMP_Utils::contains_prohibited_terms($title . ' ' . wp_strip_all_tags($description));
        if ($matches) wp_send_json_error(['message' => 'The listing contains prohibited or restricted terms: ' . implode(', ', $matches)], 422);

        $type = sanitize_key((string) $in['productType']);
        if (!array_key_exists($type, SMP_Utils::product_types())) $type = 'physical';
        $category = sanitize_text_field((string) $in['category']);
        $restricted = SMP_Utils::is_restricted_category($category, $type);
        if ($restricted && (bool) get_option('smp_health_license_required', 1) && !SMP_Integrations::health_license_verified($uid)) {
            wp_send_json_error(['message' => 'A centrally verified professional health license is required for this category.'], 403);
        }

        global $wpdb;
        $id = absint($in['productId'] ?? 0);
        if ($id) {
            $owner = (int) $wpdb->get_var($wpdb->prepare('SELECT seller_id FROM ' . SMP_DB::table('products') . ' WHERE id=%d', $id));
            if ($owner !== (int) $seller['id']) wp_send_json_error(['message' => 'Listing not found.'], 404);
        }
        $images = SMP_Utils::upload_product_images('images');
        if (!$images && $id) $images = SMP_Utils::decode_json((string) $wpdb->get_var($wpdb->prepare('SELECT images FROM ' . SMP_DB::table('products') . ' WHERE id=%d', $id)));
        $status = ((bool) get_option('smp_require_product_approval', 1) || $restricted) ? 'submitted' : 'published';
        $stock_qty = max(0, absint($in['stockQty'] ?? 1));
        $data = [
            'seller_id' => (int) $seller['id'], 'title' => $title, 'category' => $category,
            'subcategory' => sanitize_text_field((string) ($in['subcategory'] ?? '')), 'product_type' => $type,
            'condition_name' => sanitize_key((string) ($in['condition'] ?? 'new')), 'brand' => sanitize_text_field((string) ($in['brand'] ?? '')),
            'sku' => sanitize_text_field((string) ($in['sku'] ?? '')), 'short_description' => sanitize_textarea_field((string) ($in['shortDescription'] ?? '')),
            'description' => $description, 'price' => max(0, (float) $in['price']), 'sale_price' => max(0, (float) ($in['salePrice'] ?? 0)),
            'currency' => strtoupper(substr(sanitize_text_field((string) ($in['currency'] ?? get_option('smp_default_currency', 'PKR'))), 0, 10)),
            'stock_qty' => $stock_qty, 'stock_status' => $stock_qty > 0 ? 'in_stock' : 'out_of_stock', 'images' => SMP_Utils::encode_json($images),
            'video_url' => esc_url_raw((string) ($in['videoUrl'] ?? '')),
            'attributes' => SMP_Utils::encode_json(['location' => sanitize_text_field((string) ($in['location'] ?? '')), 'negotiable' => !empty($in['negotiable'])]),
            'shipping' => SMP_Utils::encode_json(['pickup' => !empty($in['pickupAvailable']), 'deliveryDiscussion' => !empty($in['deliveryDiscussion'])]),
            'compliance' => SMP_Utils::encode_json(['license' => sanitize_text_field((string) ($in['productLicense'] ?? '')), 'batch' => sanitize_text_field((string) ($in['batchNumber'] ?? '')), 'expiry' => sanitize_text_field((string) ($in['expiryDate'] ?? ''))]),
            'allow_chat' => 1, 'allow_calls' => !empty($in['allowCalls']) ? 1 : 0, 'allow_whatsapp' => !empty($in['allowWhatsapp']) ? 1 : 0,
            'allow_offers' => !empty($in['allowOffers']) ? 1 : 0, 'pickup_available' => !empty($in['pickupAvailable']) ? 1 : 0,
            'delivery_discussion' => !empty($in['deliveryDiscussion']) ? 1 : 0, 'status' => $status, 'updated_at' => SMP_Utils::now(),
        ];
        SMP_DB::transaction(static function ($db) use (&$id, $data, $title, $status): bool {
            if ($id) {
                if ($db->update(SMP_DB::table('products'), $data, ['id' => $id]) === false) return false;
            } else {
                $insert = $data + ['slug' => sanitize_title($title) . '-' . strtolower(wp_generate_password(7, false, false)), 'deal_status' => 'available', 'created_at' => SMP_Utils::now(), 'published_at' => $status === 'published' ? SMP_Utils::now() : null];
                if (!$db->insert(SMP_DB::table('products'), $insert)) return false;
                $id = (int) $db->insert_id;
            }
            return true;
        });
        SMP_Utils::audit('product_save', 'product', $id, ['status' => $status, 'restricted' => $restricted]);
        wp_send_json_success(['message' => $status === 'published' ? 'Listing published.' : 'Listing submitted for review.', 'productId' => $id]);
    }

    private static function product_status(): void {
        $uid=self::require_login();$seller=SMP_Utils::current_seller($uid);$in=self::payload();$id=absint($in['productId']??0);$status=sanitize_key($in['status']??'');
        if(!$seller||!in_array($status,['available','reserved','sold','paused'],true))wp_send_json_error(['message'=>'Invalid request.'],422);
        global $wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SMP_DB::table('products').' WHERE id=%d AND seller_id=%d',$id,(int)$seller['id']),ARRAY_A);
        if(!$row)wp_send_json_error(['message'=>'Listing not found.'],404);
        $update=['deal_status'=>$status,'updated_at'=>SMP_Utils::now()];if($status==='sold'){$update['sold_at']=SMP_Utils::now();$update['stock_status']='out_of_stock';}
        if($status==='available'){$update['sold_at']=null;$update['stock_status']='in_stock';}
        $wpdb->update(SMP_DB::table('products'),$update,['id'=>$id]);SMP_Utils::audit('product_status','product',$id,['status'=>$status]);wp_send_json_success(['message'=>'Listing status updated.']);
    }

    private static function wishlist_toggle(): void {
        $uid=self::require_login();$id=absint(self::payload()['productId']??0);if(!SMP_Utils::visible_product_row($id,$uid,false,false))wp_send_json_error(['message'=>'Listing not found.'],404);global$wpdb;
        $existing=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.SMP_DB::table('wishlist').' WHERE user_id=%d AND product_id=%d',$uid,$id));
        if($existing){$wpdb->delete(SMP_DB::table('wishlist'),['id'=>(int)$existing]);$saved=false;}else{$wpdb->insert(SMP_DB::table('wishlist'),['user_id'=>$uid,'product_id'=>$id,'created_at'=>SMP_Utils::now()]);$saved=true;}
        wp_send_json_success(['saved'=>$saved]);
    }

    private static function contact_reveal(): void {
        $uid = get_current_user_id();
        if ((bool) get_option('smp_reveal_contacts_to_logged_in', 1) && !$uid) $uid = self::require_login();
        $in = self::payload();
        $product_id = absint($in['productId'] ?? 0);
        $seller_id = absint($in['sellerId'] ?? 0);
        $product = null;
        if ($product_id) {
            $product = SMP_Utils::visible_product_row($product_id, $uid, true, false);
            if (!$product) wp_send_json_error(['message' => 'Listing not found or no longer available.'], 404);
            $seller_id = (int) $product['seller_id'];
        }
        $seller = SMP_Utils::seller_row($seller_id);
        if (!$seller || $seller['status'] !== 'approved' || !SMP_Integrations::approved_member((int) $seller['user_id'])) wp_send_json_error(['message' => 'Seller contact is unavailable.'], 404);
        if ($uid && SMP_Utils::is_blocked($uid, (int) $seller['user_id'])) wp_send_json_error(['message' => 'Contact is unavailable because one party has blocked the other.'], 403);
        $public = SMP_Utils::public_seller($seller, false, true);
        $show_phone = !empty($seller['show_phone']) && (!$product || !empty($product['allow_calls']));
        $show_whatsapp = !empty($seller['show_whatsapp']) && (!$product || !empty($product['allow_whatsapp']));
        $phone = $show_phone ? (string) ($public['phone'] ?? '') : '';
        $wa = $show_whatsapp ? (string) ($public['whatsapp'] ?? '') : '';
        $message = $product ? 'Hello, I am interested in your Marketplace listing: ' . (string) $product['title'] . ' ' . SMP_Activator::marketplace_url() : 'Hello, I am contacting you through Marketplace.';
        SMP_Utils::audit('contact_reveal', 'seller', $seller_id, ['productId' => $product_id, 'authenticated' => (bool) $uid]);
        wp_send_json_success(['phone' => $phone, 'alternatePhone' => $show_phone ? (string) ($public['alternatePhone'] ?? '') : '', 'whatsapp' => $wa, 'callHref' => $phone ? 'tel:' . $phone : '', 'whatsappUrl' => $wa ? SMP_Utils::whatsapp_url($wa, $message) : '', 'callHours' => (string) $seller['call_hours'], 'preferredContact' => (string) $seller['preferred_contact']]);
    }

    private static function conversation_start(): void {
        $uid = self::require_login();
        $product_id = absint(self::payload()['productId'] ?? 0);
        if (!$product_id) wp_send_json_error(['message' => 'Select a listing first.'], 422);
        if ((bool) get_option('smp_require_buyer_contact_before_chat', 1) && !SMP_Utils::buyer_contact_complete($uid)) wp_send_json_error(['message' => 'A centrally verified phone number is required before starting a chat.', 'needsContact' => true], 422);
        $product = SMP_Utils::visible_product_row($product_id, $uid, true, false);
        if (!$product || empty($product['allow_chat'])) wp_send_json_error(['message' => 'Internal chat is unavailable for this listing.'], 403);
        $seller = SMP_Utils::seller_row((int) $product['seller_id']);
        if (!$seller || empty($seller['allow_chat']) || !SMP_Integrations::approved_member((int) $seller['user_id'])) wp_send_json_error(['message' => 'Internal chat is unavailable for this seller.'], 403);
        $seller_uid = (int) $seller['user_id'];
        if ($uid === $seller_uid) wp_send_json_error(['message' => 'You cannot start a buyer chat with your own listing.'], 422);
        if (SMP_Utils::is_blocked($uid, $seller_uid)) wp_send_json_error(['message' => 'Chat is unavailable because one party has blocked the other.'], 403);
        global $wpdb;
        $id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . SMP_DB::table('conversations') . ' WHERE product_id=%d AND buyer_id=%d AND seller_id=%d', $product_id, $uid, (int) $product['seller_id']));
        if (!$id) {
            $id = (int) SMP_DB::transaction(static function ($db) use ($product, $product_id, $uid, $seller_uid) {
                if (!$db->insert(SMP_DB::table('conversations'), ['product_id' => $product_id, 'buyer_id' => $uid, 'seller_id' => (int) $product['seller_id'], 'seller_user_id' => $seller_uid, 'status' => 'active', 'deal_status' => 'discussing', 'currency' => $product['currency'] ?: get_option('smp_default_currency', 'PKR'), 'created_at' => SMP_Utils::now(), 'updated_at' => SMP_Utils::now()])) return false;
                return (int) $db->insert_id;
            });
            self::insert_system_message($id, 'Conversation started about “' . (string) $product['title'] . '”. Price, payment, pickup and delivery are arranged directly by the parties.');
            SMP_Utils::notify($seller_uid, 'new_chat', 'New Marketplace inquiry', SMP_Utils::client_name($uid) . ' contacted you about ' . (string) $product['title'], SMP_Activator::marketplace_url() . '#chats');
        }
        wp_send_json_success(['conversationId' => $id]);
    }

    private static function conversations(): void {
        $uid=self::require_login();global$wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT c.*,p.title product_title,p.images product_images,p.deal_status product_deal_status,s.store_name,u.display_name buyer_name,m.message_type last_type,m.message_text last_text,m.created_at last_created FROM ".SMP_DB::table('conversations')." c LEFT JOIN ".SMP_DB::table('products')." p ON p.id=c.product_id LEFT JOIN ".SMP_DB::table('sellers')." s ON s.id=c.seller_id LEFT JOIN {$wpdb->users} u ON u.ID=c.buyer_id LEFT JOIN ".SMP_DB::table('messages')." m ON m.id=c.last_message_id WHERE (c.buyer_id=%d OR c.seller_user_id=%d) AND ((c.buyer_id=%d AND c.buyer_archived=0) OR (c.seller_user_id=%d AND c.seller_archived=0)) ORDER BY COALESCE(c.last_message_at,c.created_at) DESC LIMIT 200",$uid,$uid,$uid,$uid),ARRAY_A);
        $out=[];foreach($rows as$r){$other=SMP_Utils::other_user($r,$uid);$imgs=SMP_Utils::decode_json($r['product_images']??'[]');$img='';if($imgs){$f=reset($imgs);$img=is_array($f)?($f['url']??''):$f;}$out[]=[
            'id'=>(int)$r['id'],'productId'=>(int)$r['product_id'],'productTitle'=>(string)$r['product_title'],'productImage'=>(string)$img,'productDealStatus'=>(string)$r['product_deal_status'],
            'otherUserId'=>$other,'otherName'=>$uid===(int)$r['buyer_id']?(string)$r['store_name']:(string)$r['buyer_name'],'otherAvatar'=>get_avatar_url($other,['size'=>96]),
            'status'=>(string)$r['status'],'dealStatus'=>(string)$r['deal_status'],'agreedPrice'=>(float)$r['agreed_price'],'currency'=>(string)$r['currency'],
            'lastMessage'=>(string)($r['last_text']?:self::message_type_label((string)$r['last_type'])),'lastMessageAt'=>(string)($r['last_created']?:$r['created_at']),
            'unread'=>self::conversation_unread($r,$uid),'blocked'=>SMP_Utils::is_blocked($uid,$other),'presence'=>SMP_Utils::online_status($other),
        ];}
        wp_send_json_success(['conversations'=>$out,'unreadTotal'=>array_sum(array_column($out,'unread'))]);
    }

    private static function messages(): void {
        $uid=self::require_login();$in=self::payload();$cid=absint($in['conversation_id']??0);$after=absint($in['after_id']??0);$c=SMP_Utils::conversation_row($cid,$uid);if(!$c)wp_send_json_error(['message'=>'Conversation not found.'],404);global$wpdb;
        $now=SMP_Utils::now();$wpdb->query($wpdb->prepare('UPDATE '.SMP_DB::table('messages').' SET delivered_at=COALESCE(delivered_at,%s),read_at=COALESCE(read_at,%s) WHERE conversation_id=%d AND sender_id<>%d',$now,$now,$cid,$uid));
        $max=(int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(MAX(id),0) FROM '.SMP_DB::table('messages').' WHERE conversation_id=%d',$cid));
        $read_field=(int)$c['buyer_id']===$uid?'buyer_last_read_id':'seller_last_read_id';$wpdb->update(SMP_DB::table('conversations'),[$read_field=>$max,'updated_at'=>$now],['id'=>$cid]);
        if($after){$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.SMP_DB::table('messages').' WHERE conversation_id=%d AND id>%d ORDER BY id ASC LIMIT 200',$cid,$after),ARRAY_A);}else{$rows=array_reverse($wpdb->get_results($wpdb->prepare('SELECT * FROM '.SMP_DB::table('messages').' WHERE conversation_id=%d ORDER BY id DESC LIMIT 150',$cid),ARRAY_A));}
        $typing=(bool)get_transient('smp_typing_'.$cid.'_'.SMP_Utils::other_user($c,$uid));
        wp_send_json_success(['messages'=>array_map(static fn($r)=>self::hydrate_message($r,$uid),$rows),'typing'=>$typing,'blocked'=>SMP_Utils::is_blocked($uid,SMP_Utils::other_user($c,$uid)),'conversation'=>['id'=>$cid,'dealStatus'=>$c['deal_status'],'agreedPrice'=>(float)$c['agreed_price'],'currency'=>$c['currency'],'otherPresence'=>SMP_Utils::online_status(SMP_Utils::other_user($c,$uid))]]);
    }

    private static function message_send(): void {
        $uid=self::require_login();$in=self::payload();$cid=absint($in['conversationId']??0);$c=SMP_Utils::conversation_row($cid,$uid);if(!$c)wp_send_json_error(['message'=>'Conversation not found.'],404);$other=SMP_Utils::other_user($c,$uid);if(SMP_Utils::is_blocked($uid,$other))wp_send_json_error(['message'=>'Messages cannot be sent while either party is blocked.'],403);
        $type=sanitize_key($in['messageType']??'text');$allowed=['text','image','document','audio','video','offer','location'];if(!in_array($type,$allowed,true))$type='text';$text=sanitize_textarea_field($in['text']??'');$upload=null;
        if(!empty($_FILES['attachment'])){$upload=SMP_Utils::upload_private_chat_file('attachment');if(is_wp_error($upload))wp_send_json_error(['message'=>$upload->get_error_message()],422);$mime=$upload['mime'];$requested=$type;$type=str_starts_with($mime,'image/')?'image':(str_starts_with($mime,'audio/')?'audio':(str_starts_with($mime,'video/')?($requested==='audio'?'audio':'video'):'document'));}
        $offer=max(0,(float)($in['offerAmount']??0));if($type==='offer'&&$offer<=0)wp_send_json_error(['message'=>'Enter a valid offer amount.'],422);if($type==='text'&&$text==='')wp_send_json_error(['message'=>'Write a message.'],422);if(!$upload&&$text===''&&$type!=='offer'&&$type!=='location')wp_send_json_error(['message'=>'Message content is empty.'],422);
        global$wpdb;$data=['conversation_id'=>$cid,'sender_id'=>$uid,'message_type'=>$type,'message_text'=>$text,'offer_amount'=>$offer,'offer_status'=>$type==='offer'?'pending':'','reply_to'=>absint($in['replyTo']??0),'created_at'=>SMP_Utils::now()];
        if($upload){$data+=['attachment_path'=>$upload['path'],'attachment_name'=>$upload['name'],'attachment_mime'=>$upload['mime'],'attachment_size'=>$upload['size']];}
        $mid=(int)SMP_DB::transaction(static function($db)use($data,$cid){if(!$db->insert(SMP_DB::table('messages'),$data))return false;$message_id=(int)$db->insert_id;if($db->update(SMP_DB::table('conversations'),['last_message_id'=>$message_id,'last_message_at'=>SMP_Utils::now(),'updated_at'=>SMP_Utils::now()],['id'=>$cid])===false)return false;return$message_id;});
        delete_transient('smp_typing_'.$cid.'_'.$uid);$preview=$type==='text'?mb_substr($text,0,120):self::message_type_label($type);SMP_Utils::notify($other,'chat_message','New Marketplace message',SMP_Utils::client_name($uid).': '.$preview,SMP_Activator::marketplace_url().'#chats');SMP_Utils::audit('message_send','conversation',$cid,['messageId'=>$mid,'type'=>$type]);
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SMP_DB::table('messages').' WHERE id=%d',$mid),ARRAY_A);wp_send_json_success(['message'=>self::hydrate_message($row,$uid)]);
    }

    private static function message_edit(): void {
        $uid=self::require_login();$in=self::payload();$id=absint($in['messageId']??0);$text=sanitize_textarea_field($in['text']??'');if(!$id||$text==='')wp_send_json_error(['message'=>'Invalid edit.'],422);global$wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SMP_DB::table('messages').' WHERE id=%d AND sender_id=%d',$id,$uid),ARRAY_A);if(!$row||$row['message_type']!=='text'||!empty($row['deleted_for_all']))wp_send_json_error(['message'=>'This message cannot be edited.'],403);
        $minutes=max(1,(int)get_option('smp_message_edit_minutes',1440));if(strtotime($row['created_at'])<time()-$minutes*MINUTE_IN_SECONDS)wp_send_json_error(['message'=>'The edit time has expired.'],403);
        $wpdb->update(SMP_DB::table('messages'),['message_text'=>$text,'edited_at'=>SMP_Utils::now()],['id'=>$id]);SMP_Utils::audit('message_edit','message',$id);wp_send_json_success(['message'=>'Message edited.']);
    }

    private static function message_delete(): void {
        $uid=self::require_login();$id=absint(self::payload()['messageId']??0);global$wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SMP_DB::table('messages').' WHERE id=%d AND sender_id=%d',$id,$uid),ARRAY_A);
        if(!$row)wp_send_json_error(['message'=>'Message not found.'],404);
        $wpdb->update(SMP_DB::table('messages'),['message_text'=>'','contact_payload'=>'','deleted_for_all'=>1,'deleted_at'=>SMP_Utils::now(),'edited_at'=>SMP_Utils::now()],['id'=>$id]);SMP_Utils::audit('message_delete','message',$id);wp_send_json_success(['message'=>'Message deleted for both parties.']);
    }

    private static function reaction_toggle(): void {
        $uid=self::require_login();$in=self::payload();$mid=absint($in['messageId']??0);$reaction=mb_substr(sanitize_text_field($in['reaction']??''),0,8);global$wpdb;
        $m=$wpdb->get_row($wpdb->prepare('SELECT m.*,c.buyer_id,c.seller_user_id FROM '.SMP_DB::table('messages').' m JOIN '.SMP_DB::table('conversations').' c ON c.id=m.conversation_id WHERE m.id=%d',$mid),ARRAY_A);if(!$m||($uid!==(int)$m['buyer_id']&&$uid!==(int)$m['seller_user_id']))wp_send_json_error(['message'=>'Message not found.'],404);
        $existing=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.SMP_DB::table('message_reactions').' WHERE message_id=%d AND user_id=%d',$mid,$uid));
        if($existing){if($reaction==='')$wpdb->delete(SMP_DB::table('message_reactions'),['id'=>(int)$existing]);else$wpdb->update(SMP_DB::table('message_reactions'),['reaction'=>$reaction],['id'=>(int)$existing]);}elseif($reaction!=='')$wpdb->insert(SMP_DB::table('message_reactions'),['message_id'=>$mid,'user_id'=>$uid,'reaction'=>$reaction,'created_at'=>SMP_Utils::now()]);
        wp_send_json_success(['message'=>'Reaction updated.']);
    }

    private static function typing(): void {
        $uid=self::require_login();$cid=absint(self::payload()['conversationId']??0);if(!SMP_Utils::conversation_row($cid,$uid))wp_send_json_error(['message'=>'Conversation not found.'],404);set_transient('smp_typing_'.$cid.'_'.$uid,1,12);wp_send_json_success(['ok'=>true]);
    }

    private static function offer_action(): void {
        $uid=self::require_login();$in=self::payload();$mid=absint($in['messageId']??0);$action=sanitize_key($in['offerAction']??'');if(!in_array($action,['accept','reject','counter'],true))wp_send_json_error(['message'=>'Invalid offer action.'],422);global$wpdb;
        $m=$wpdb->get_row($wpdb->prepare("SELECT m.*,c.buyer_id,c.seller_user_id,c.id conversation_id,c.currency FROM ".SMP_DB::table('messages')." m JOIN ".SMP_DB::table('conversations')." c ON c.id=m.conversation_id WHERE m.id=%d AND m.message_type='offer'",$mid),ARRAY_A);
        if(!$m||($uid!==(int)$m['buyer_id']&&$uid!==(int)$m['seller_user_id'])||$uid===(int)$m['sender_id'])wp_send_json_error(['message'=>'Offer not available.'],403);
        if($action==='counter'){$amount=max(0,(float)($in['counterAmount']??0));if($amount<=0)wp_send_json_error(['message'=>'Enter a valid counter-offer.'],422);$wpdb->update(SMP_DB::table('messages'),['offer_status'=>'countered'],['id'=>$mid]);$_POST['payload']=wp_json_encode(['conversationId'=>(int)$m['conversation_id'],'messageType'=>'offer','offerAmount'=>$amount,'text'=>'Counter-offer']);self::message_send();}
        $status=$action==='accept'?'accepted':'rejected';$wpdb->update(SMP_DB::table('messages'),['offer_status'=>$status],['id'=>$mid]);if($action==='accept')$wpdb->update(SMP_DB::table('conversations'),['deal_status'=>'agreed','agreed_price'=>(float)$m['offer_amount'],'updated_at'=>SMP_Utils::now()],['id'=>(int)$m['conversation_id']]);
        self::insert_system_message((int)$m['conversation_id'],'Offer '.($action==='accept'?'accepted':'rejected').'.');wp_send_json_success(['message'=>'Offer '.$status.'.']);
    }

    private static function share_contact(): void {
        $uid=self::require_login();$in=self::payload();$cid=absint($in['conversationId']??0);$kind=sanitize_key($in['contactType']??'phone');$c=SMP_Utils::conversation_row($cid,$uid);if(!$c)wp_send_json_error(['message'=>'Conversation not found.'],404);
        $payload=[];$seller=SMP_Utils::current_seller($uid);if($seller&&$uid===(int)$c['seller_user_id']){$payload=['name'=>$seller['storeName'],'phone'=>$seller['phone'],'whatsapp'=>$seller['whatsapp']];}else{$b=SMP_Utils::buyer_contact($uid);$payload=['name'=>SMP_Utils::client_name($uid),'phone'=>$b['phone'],'whatsapp'=>$b['whatsapp']];}
        if($kind==='phone')$payload=['name'=>$payload['name'],'phone'=>$payload['phone']];elseif($kind==='whatsapp')$payload=['name'=>$payload['name'],'whatsapp'=>$payload['whatsapp']];
        global$wpdb;$wpdb->insert(SMP_DB::table('messages'),['conversation_id'=>$cid,'sender_id'=>$uid,'message_type'=>'contact','message_text'=>$kind==='whatsapp'?'WhatsApp contact shared':'Phone contact shared','contact_payload'=>SMP_Utils::encode_json($payload),'created_at'=>SMP_Utils::now()]);$mid=(int)$wpdb->insert_id;$wpdb->update(SMP_DB::table('conversations'),['last_message_id'=>$mid,'last_message_at'=>SMP_Utils::now(),'updated_at'=>SMP_Utils::now()],['id'=>$cid]);SMP_Utils::notify(SMP_Utils::other_user($c,$uid),'contact_shared','Contact details shared',SMP_Utils::client_name($uid).' shared contact details.',SMP_Activator::marketplace_url().'#chats');wp_send_json_success(['message'=>'Contact shared in chat.']);
    }

    private static function conversation_status(): void {
        $uid=self::require_login();$in=self::payload();$cid=absint($in['conversationId']??0);$status=sanitize_key($in['status']??'');if(!in_array($status,['discussing','agreed','sold','completed','cancelled','archived'],true))wp_send_json_error(['message'=>'Invalid deal status.'],422);$c=SMP_Utils::conversation_row($cid,$uid);if(!$c)wp_send_json_error(['message'=>'Conversation not found.'],404);global$wpdb;
        if($status==='sold'&&$uid!==(int)$c['seller_user_id'])wp_send_json_error(['message'=>'Only the seller can mark a listing sold.'],403);
        if($status==='archived'){$field=$uid===(int)$c['buyer_id']?'buyer_archived':'seller_archived';$wpdb->update(SMP_DB::table('conversations'),[$field=>1,'updated_at'=>SMP_Utils::now()],['id'=>$cid]);}
        else{$wpdb->update(SMP_DB::table('conversations'),['deal_status'=>$status,'updated_at'=>SMP_Utils::now()],['id'=>$cid]);if($status==='sold')$wpdb->update(SMP_DB::table('products'),['deal_status'=>'sold','stock_status'=>'out_of_stock','sold_at'=>SMP_Utils::now(),'updated_at'=>SMP_Utils::now()],['id'=>(int)$c['product_id']]);self::insert_system_message($cid,'Deal status changed to '.ucwords(str_replace('_',' ',$status)).'.');}
        wp_send_json_success(['message'=>'Deal status updated.']);
    }

    private static function block_toggle(): void {
        $uid=self::require_login();$cid=absint(self::payload()['conversationId']??0);$c=SMP_Utils::conversation_row($cid,$uid);if(!$c)wp_send_json_error(['message'=>'Conversation not found.'],404);$other=SMP_Utils::other_user($c,$uid);global$wpdb;
        $existing=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.SMP_DB::table('blocks').' WHERE blocker_id=%d AND blocked_id=%d',$uid,$other));
        if($existing){$wpdb->delete(SMP_DB::table('blocks'),['id'=>(int)$existing]);$blocked=false;}else{$wpdb->insert(SMP_DB::table('blocks'),['blocker_id'=>$uid,'blocked_id'=>$other,'created_at'=>SMP_Utils::now()]);$blocked=true;}
        SMP_Utils::audit($blocked?'user_block':'user_unblock','user',$other,['conversationId'=>$cid]);wp_send_json_success(['blocked'=>$blocked,'message'=>$blocked?'User blocked.':'User unblocked.']);
    }

    private static function report_submit(): void {
        $uid=self::require_login();$in=self::payload();$cid=absint($in['conversationId']??0);$c=$cid?SMP_Utils::conversation_row($cid,$uid):null;$reported=absint($in['reportedUserId']??($c?SMP_Utils::other_user($c,$uid):0));$reason=sanitize_key($in['reason']??'other');$details=sanitize_textarea_field($in['details']??'');
        if(!$reported||$details==='')wp_send_json_error(['message'=>'Select a reason and provide details.'],422);global$wpdb;$wpdb->insert(SMP_DB::table('reports'),['reporter_id'=>$uid,'reported_user_id'=>$reported,'conversation_id'=>$cid,'message_id'=>absint($in['messageId']??0),'product_id'=>absint($in['productId']??($c['product_id']??0)),'reason'=>$reason,'details'=>$details,'status'=>'open','created_at'=>SMP_Utils::now(),'updated_at'=>SMP_Utils::now()]);$id=(int)$wpdb->insert_id;SMP_Utils::audit('report_submit','report',$id);wp_send_json_success(['message'=>'Report submitted to Marketplace administration.']);
    }

    private static function seller_dashboard(): void {
        $uid=self::require_login();$seller=SMP_Utils::current_seller($uid);if(!$seller)wp_send_json_success(['seller'=>null,'products'=>[],'stats'=>[]]);global$wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.SMP_DB::table('products').' WHERE seller_id=%d ORDER BY id DESC LIMIT 100',(int)$seller['id']),ARRAY_A);
        $chats=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.SMP_DB::table('conversations').' WHERE seller_user_id=%d',$uid));$sold=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".SMP_DB::table('products')." WHERE seller_id=%d AND deal_status='sold'",(int)$seller['id']));
        wp_send_json_success(['seller'=>$seller,'products'=>array_map(['SMP_Utils','public_product'],$rows),'stats'=>['products'=>count($rows),'chats'=>$chats,'sold'=>$sold,'available'=>count(array_filter($rows,static fn($r)=>($r['deal_status']??'')==='available'))]]);
    }

    private static function notifications(): void {
        self::require_login();
        wp_send_json_success(['notifications' => [], 'redirectUrl' => SMP_Integrations::notifications_url(), 'centralized' => true]);
    }

    private static function notification_read(): void {
        self::require_login();
        wp_send_json_success(['message' => 'Notifications are managed by the unified notification center.', 'redirectUrl' => SMP_Integrations::notifications_url()]);
    }

    private static function hydrate_message(array $row, int $viewer): array {
        global$wpdb;$reactions=$wpdb->get_results($wpdb->prepare('SELECT user_id,reaction FROM '.SMP_DB::table('message_reactions').' WHERE message_id=%d',(int)$row['id']),ARRAY_A);$groups=[];foreach($reactions as$r){$groups[$r['reaction']][]=(int)$r['user_id'];}
        $deleted=!empty($row['deleted_for_all']);
        return ['id'=>(int)$row['id'],'conversationId'=>(int)$row['conversation_id'],'senderId'=>(int)$row['sender_id'],'senderName'=>SMP_Utils::client_name((int)$row['sender_id']),'mine'=>(int)$row['sender_id']===$viewer,
            'type'=>$deleted?'deleted':(string)$row['message_type'],'text'=>$deleted?'This message was deleted.':(string)$row['message_text'],'attachmentName'=>$deleted?'':(string)$row['attachment_name'],'attachmentMime'=>$deleted?'':(string)$row['attachment_mime'],'attachmentSize'=>$deleted?0:(int)$row['attachment_size'],'attachmentUrl'=>!$deleted&&$row['attachment_path']?SMP_Utils::chat_file_url((int)$row['id']):'',
            'offerAmount'=>(float)$row['offer_amount'],'offerStatus'=>(string)$row['offer_status'],'contact'=>$deleted?[]:SMP_Utils::decode_json($row['contact_payload']??'{}'),'replyTo'=>(int)$row['reply_to'],'delivered'=>!empty($row['delivered_at']),'read'=>!empty($row['read_at']),'edited'=>!empty($row['edited_at']),'reactions'=>$groups,'createdAt'=>$row['created_at']];
    }

    private static function unread_chat_count(int $uid): int {
        global$wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.SMP_DB::table('conversations').' WHERE buyer_id=%d OR seller_user_id=%d',$uid,$uid),ARRAY_A);$n=0;foreach($rows as$r)$n+=self::conversation_unread($r,$uid);return$n;
    }

    private static function conversation_unread(array $c,int$uid):int{
        global$wpdb;$last=(int)$c['buyer_id']===$uid?(int)$c['buyer_last_read_id']:(int)$c['seller_last_read_id'];return(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.SMP_DB::table('messages').' WHERE conversation_id=%d AND sender_id<>%d AND id>%d AND deleted_for_all=0',(int)$c['id'],$uid,$last));
    }

    private static function insert_system_message(int$cid,string$text):void{
        global$wpdb;if(!$wpdb->insert(SMP_DB::table('messages'),['conversation_id'=>$cid,'sender_id'=>0,'message_type'=>'system','message_text'=>sanitize_text_field($text),'created_at'=>SMP_Utils::now()]))throw new RuntimeException($wpdb->last_error?:'System message failed.');$mid=(int)$wpdb->insert_id;if($wpdb->update(SMP_DB::table('conversations'),['last_message_id'=>$mid,'last_message_at'=>SMP_Utils::now(),'updated_at'=>SMP_Utils::now()],['id'=>$cid])===false)throw new RuntimeException($wpdb->last_error?:'Conversation update failed.');
    }

    private static function message_type_label(string$type):string{return match($type){'image'=>'Photo','document'=>'Document','audio'=>'Voice note','video'=>'Video','offer'=>'Price offer','contact'=>'Contact details','location'=>'Location','system'=>'System update',default=>'Message'};}

    public static function download_chat_file(): void {
        $uid=get_current_user_id();$mid=absint($_GET['message_id']??0);if(!$uid||!$mid||!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce']??'')),'smp_chat_file_'.$mid)){status_header(403);exit('Forbidden');}
        global$wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT m.*,c.buyer_id,c.seller_user_id FROM '.SMP_DB::table('messages').' m JOIN '.SMP_DB::table('conversations').' c ON c.id=m.conversation_id WHERE m.id=%d',$mid),ARRAY_A);
        if(!$row||($uid!==(int)$row['buyer_id']&&$uid!==(int)$row['seller_user_id'])||empty($row['attachment_path'])){status_header(404);exit('Not found');}
        $plain=SMP_Utils::read_private_file((string)$row['attachment_path']);if(is_wp_error($plain)){status_header(404);exit('Not found');}
        nocache_headers();header('Content-Type: '.($row['attachment_mime']?:'application/octet-stream'));header('Content-Length: '.strlen($plain));header('Content-Disposition: inline; filename="'.sanitize_file_name($row['attachment_name']?:'marketplace-attachment').'"');header('X-Content-Type-Options: nosniff');echo$plain;exit;
    }
}
