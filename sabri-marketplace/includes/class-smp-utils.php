<?php
defined('ABSPATH') || exit;

final class SMP_Utils {
    public static function now(): string { return current_time('mysql', true); }

    public static function decode_json($value, array $fallback = []): array {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public static function encode_json($value): string {
        return (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function money($amount, ?string $currency = null): string {
        $currency = $currency ?: (string) get_option('smp_default_currency', 'PKR');
        return trim($currency . ' ' . number_format_i18n((float) $amount, 2));
    }

    public static function is_marketplace_request(): bool {
        $page_id = (int) get_option('smp_marketplace_page_id');
        return ($page_id && is_page($page_id))
            || (int) get_query_var('smp_marketplace_app') === 1
            || isset($_GET['smp-marketplace-safe']);
    }

    public static function product_types(): array {
        return [
            'physical' => 'Physical Product', 'service' => 'Service', 'digital' => 'Digital Product',
            'used' => 'Used Product', 'wholesale' => 'Wholesale', 'vehicle' => 'Vehicle',
            'property' => 'Property', 'health' => 'Homeopathy & Health',
        ];
    }

    public static function categories(): array {
        $saved = get_option('smp_categories', []);
        return is_array($saved) && $saved ? $saved : SMP_Activator::default_categories();
    }

    public static function prohibited_terms(): array {
        $raw = (string) get_option('smp_prohibited_terms', 'weapon,firearm,ammunition,explosive,illegal drug,stolen,counterfeit,pornography,malware,spyware,fake degree,fake certificate');
        $terms = preg_split('/[\r\n,]+/u', $raw) ?: [];
        return array_values(array_filter(array_map(static fn($v) => self::normalize_screening_text((string) $v), $terms)));
    }

    public static function normalize_screening_text(string $text): string {
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_KD);
            if (is_string($normalized)) $text = $normalized;
        }
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, ['0'=>'o','1'=>'i','3'=>'e','4'=>'a','5'=>'s','7'=>'t','@'=>'a','$'=>'s','!'=>'i']);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?: '';
        return trim(preg_replace('/\s+/u', ' ', $text) ?: '');
    }

    public static function contains_prohibited_terms(string $text): array {
        $normalized = self::normalize_screening_text($text);
        $compact = str_replace(' ', '', $normalized);
        $matches = [];
        foreach (self::prohibited_terms() as $term) {
            if ($term === '') continue;
            $quoted = preg_quote($term, '/');
            if (preg_match('/(?:^|\s)' . $quoted . '(?:$|\s)/u', $normalized)
                || mb_strpos($compact, str_replace(' ', '', $term)) !== false) {
                $matches[] = $term;
            }
        }
        $matches = array_values(array_unique($matches));
        return (array) apply_filters('smp_prohibited_term_matches', $matches, $normalized, $text);
    }

    public static function is_restricted_category(string $category, string $product_type = ''): bool {
        if ($product_type === 'health') return true;
        return in_array($category, (array) get_option('smp_restricted_categories', []), true);
    }

    public static function sanitize_phone(string $phone): string {
        $phone = trim(wp_strip_all_tags($phone));
        $has_plus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        return ($has_plus ? '+' : '') . substr($digits, 0, 18);
    }

    public static function phone_digits(string $phone): string {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }

    public static function whatsapp_url(string $phone, string $message = ''): string {
        $digits = self::phone_digits($phone);
        if ($digits === '') return '';
        return 'https://wa.me/' . rawurlencode($digits) . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }

    public static function current_seller(?int $user_id = null): ?array {
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('sellers') . ' WHERE user_id=%d LIMIT 1', $user_id), ARRAY_A);
        return is_array($row) ? self::public_seller($row, true) : null;
    }

    public static function seller_row(int $seller_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('sellers') . ' WHERE id=%d LIMIT 1', $seller_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function public_seller(array $row, bool $private = false, bool $reveal = false): array {
        $user_id = (int) ($row['user_id'] ?? 0);
        $central = class_exists('SMP_Integrations') ? SMP_Integrations::membership_contact($user_id) : [];
        $contact_verified = !empty($central['mobileVerified']) && class_exists('SMP_Integrations') && SMP_Integrations::approved_member($user_id);
        $data = [
            'id' => (int) ($row['id'] ?? 0), 'userId' => $user_id,
            'sellerType' => (string) ($row['seller_type'] ?? 'individual'), 'legalName' => (string) ($row['legal_name'] ?? ''),
            'storeName' => (string) ($row['store_name'] ?? ''), 'country' => (string) ($row['country'] ?? ''),
            'city' => (string) ($row['city'] ?? ''), 'verificationLevel' => $contact_verified ? 'central_membership' : 'pending',
            'contactVerified' => $contact_verified, 'status' => (string) ($row['status'] ?? 'pending'),
            'rating' => (float) ($row['rating'] ?? 0), 'salesCount' => (int) ($row['sales_count'] ?? 0),
            'showPhone' => !empty($row['show_phone']), 'showWhatsapp' => !empty($row['show_whatsapp']),
            'allowChat' => !isset($row['allow_chat']) || !empty($row['allow_chat']), 'allowCalls' => !isset($row['allow_calls']) || !empty($row['allow_calls']),
            'allowOffers' => !isset($row['allow_offers']) || !empty($row['allow_offers']),
            'preferredContact' => (string) ($row['preferred_contact'] ?? 'chat'), 'callHours' => (string) ($row['call_hours'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
        if ($private || $reveal) {
            $phone = (string) ($central['phone'] ?? $row['phone'] ?? '');
            $whatsapp = (string) ($central['whatsapp'] ?? $row['whatsapp'] ?? $phone);
            $data += [
                'phone' => $phone, 'alternatePhone' => (string) ($row['alternate_phone'] ?? ''),
                'whatsapp' => $whatsapp, 'email' => (string) ($row['email'] ?? ''),
                'address' => (string) ($row['address'] ?? ''),
            ];
        }
        if ($private) {
            $data += [
                'identityManagedBy' => 'Sabri Membership Core',
                'identityNumberMasked' => class_exists('SMP_Integrations') ? SMP_Integrations::mask_sensitive((string) ($row['identity_number'] ?? '')) : '••••',
                'businessRegistrationMasked' => class_exists('SMP_Integrations') ? SMP_Integrations::mask_sensitive((string) ($row['business_registration'] ?? '')) : '••••',
                'taxNumberMasked' => class_exists('SMP_Integrations') ? SMP_Integrations::mask_sensitive((string) ($row['tax_number'] ?? '')) : '••••',
                'licenseNumberMasked' => class_exists('SMP_Integrations') ? SMP_Integrations::mask_sensitive((string) ($row['license_number'] ?? '')) : '••••',
                'rejectionReason' => (string) ($row['rejection_reason'] ?? ''),
            ];
        }
        return $data;
    }

    public static function public_product(array $row): array {
        $price = (float) ($row['price'] ?? 0); $sale = (float) ($row['sale_price'] ?? 0);
        $images = self::decode_json($row['images'] ?? '[]');
        $image = '';
        if ($images) { $first = reset($images); $image = is_array($first) ? (string) ($first['url'] ?? '') : (string) $first; }
        return [
            'id'=>(int)($row['id']??0),'sellerId'=>(int)($row['seller_id']??0),'title'=>(string)($row['title']??''),'slug'=>(string)($row['slug']??''),
            'category'=>(string)($row['category']??''),'subcategory'=>(string)($row['subcategory']??''),'productType'=>(string)($row['product_type']??'physical'),
            'condition'=>(string)($row['condition_name']??'new'),'brand'=>(string)($row['brand']??''),'sku'=>(string)($row['sku']??''),
            'shortDescription'=>(string)($row['short_description']??''),'description'=>wp_kses_post((string)($row['description']??'')),
            'price'=>$price,'salePrice'=>$sale,'effectivePrice'=>($sale>0&&$sale<$price?$sale:$price),'currency'=>(string)($row['currency']??get_option('smp_default_currency','PKR')),
            'stockQty'=>(int)($row['stock_qty']??1),'stockStatus'=>(string)($row['stock_status']??'in_stock'),'images'=>$images,'image'=>$image,
            'videoUrl'=>(string)($row['video_url']??''),'attributes'=>self::decode_json($row['attributes']??'{}'),'shipping'=>self::decode_json($row['shipping']??'{}'),
            'compliance'=>self::decode_json($row['compliance']??'{}'),'allowChat'=>!isset($row['allow_chat'])||!empty($row['allow_chat']),
            'allowCalls'=>!isset($row['allow_calls'])||!empty($row['allow_calls']),'allowWhatsapp'=>!isset($row['allow_whatsapp'])||!empty($row['allow_whatsapp']),
            'allowOffers'=>!isset($row['allow_offers'])||!empty($row['allow_offers']),'pickupAvailable'=>!isset($row['pickup_available'])||!empty($row['pickup_available']),
            'deliveryDiscussion'=>!isset($row['delivery_discussion'])||!empty($row['delivery_discussion']),'dealStatus'=>(string)($row['deal_status']??'available'),
            'status'=>(string)($row['status']??'draft'),'featured'=>!empty($row['featured']),'views'=>(int)($row['views']??0),
            'rating'=>(float)($row['rating']??0),'reviewCount'=>(int)($row['review_count']??0),'createdAt'=>(string)($row['created_at']??''),'updatedAt'=>(string)($row['updated_at']??''),
        ];
    }

    public static function visible_product_row(int $product_id, int $viewer_id = 0, bool $require_available = false, bool $allow_owner = false): ?array {
        if ($product_id <= 0) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT p.*,s.user_id seller_user_id,s.status seller_status FROM ' . SMP_DB::table('products') . ' p JOIN ' . SMP_DB::table('sellers') . ' s ON s.id=p.seller_id WHERE p.id=%d LIMIT 1',
            $product_id
        ), ARRAY_A);
        if (!is_array($row)) return null;
        $owner = $viewer_id > 0 && $viewer_id === (int) $row['seller_user_id'];
        $moderator = $viewer_id > 0 && current_user_can('manage_sabri_marketplace');
        if (($allow_owner && $owner) || $moderator) return $row;
        if (!in_array((string) $row['status'], ['published','approved'], true)) return null;
        if ((string) $row['seller_status'] !== 'approved') return null;
        if (!class_exists('SMP_Integrations') || !SMP_Integrations::approved_member((int) $row['seller_user_id'])) return null;
        if ($require_available && ((string) $row['deal_status'] !== 'available' || (string) $row['stock_status'] === 'out_of_stock')) return null;
        return $row;
    }

    public static function buyer_contact(int $user_id): array {
        $central = class_exists('SMP_Integrations') ? SMP_Integrations::membership_contact($user_id) : [];
        return [
            'phone' => (string) (get_user_meta($user_id, 'smp_buyer_phone', true) ?: ($central['phone'] ?? '')),
            'whatsapp' => (string) (get_user_meta($user_id, 'smp_buyer_whatsapp', true) ?: ($central['whatsapp'] ?? '')),
            'preferredContact' => (string) (get_user_meta($user_id, 'smp_buyer_preferred_contact', true) ?: 'chat'),
            'allowCalls' => get_user_meta($user_id, 'smp_buyer_allow_calls', true) !== '0',
            'sharePhoneByDefault' => get_user_meta($user_id, 'smp_buyer_share_phone', true) === '1',
            'shareWhatsappByDefault' => get_user_meta($user_id, 'smp_buyer_share_whatsapp', true) === '1',
            'quietHours' => (string) get_user_meta($user_id, 'smp_buyer_quiet_hours', true),
            'mobileVerified' => !empty($central['mobileVerified']),
        ];
    }

    public static function save_buyer_contact(int $user_id, array $input): array {
        $phone = self::sanitize_phone((string) ($input['phone'] ?? ''));
        $whatsapp = self::sanitize_phone((string) ($input['whatsapp'] ?? ''));
        if (self::phone_digits($phone) === '' || self::phone_digits($whatsapp) === '') return [];
        update_user_meta($user_id, 'smp_buyer_phone', $phone);
        update_user_meta($user_id, 'smp_buyer_whatsapp', $whatsapp);
        update_user_meta($user_id, 'smp_buyer_preferred_contact', in_array(($input['preferredContact'] ?? ''), ['chat','phone','whatsapp'], true) ? $input['preferredContact'] : 'chat');
        update_user_meta($user_id, 'smp_buyer_allow_calls', !empty($input['allowCalls']) ? '1' : '0');
        update_user_meta($user_id, 'smp_buyer_share_phone', !empty($input['sharePhoneByDefault']) ? '1' : '0');
        update_user_meta($user_id, 'smp_buyer_share_whatsapp', !empty($input['shareWhatsappByDefault']) ? '1' : '0');
        update_user_meta($user_id, 'smp_buyer_quiet_hours', sanitize_text_field((string) ($input['quietHours'] ?? '')));
        return self::buyer_contact($user_id);
    }

    public static function buyer_contact_complete(int $user_id): bool {
        $c = self::buyer_contact($user_id);
        return self::phone_digits($c['phone']) !== '' && self::phone_digits($c['whatsapp']) !== '' && !empty($c['mobileVerified']);
    }

    public static function is_blocked(int $a, int $b): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . SMP_DB::table('blocks') . ' WHERE (blocker_id=%d AND blocked_id=%d) OR (blocker_id=%d AND blocked_id=%d) LIMIT 1', $a,$b,$b,$a));
    }

    public static function conversation_row(int $conversation_id, int $user_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SMP_DB::table('conversations') . ' WHERE id=%d AND (buyer_id=%d OR seller_user_id=%d) LIMIT 1', $conversation_id,$user_id,$user_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function other_user(array $conversation, int $user_id): int {
        return (int) $conversation['buyer_id'] === $user_id ? (int) $conversation['seller_user_id'] : (int) $conversation['buyer_id'];
    }

    public static function audit(string $action, string $object_type = '', int $object_id = 0, array $details = []): void {
        global $wpdb;
        $wpdb->insert(SMP_DB::table('audit_log'), [
            'actor_user_id'=>get_current_user_id(),'action'=>sanitize_key($action),'object_type'=>sanitize_key($object_type),'object_id'=>$object_id,
            'details'=>self::encode_json($details),'ip_address'=>self::client_ip(),'created_at'=>self::now(),
        ]);
        if (class_exists('SMC_Security') && method_exists('SMC_Security', 'audit')) {
            SMC_Security::audit('marketplace_' . sanitize_key($action), get_current_user_id(), $object_type, $object_id, $details);
        }
    }

    public static function client_ip(): string {
        return substr(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
    }

    public static function notify(int $user_id, string $type, string $title, string $body = '', string $link = '', array $extra = []): int {
        if ($user_id <= 0 || !class_exists('SMP_Integrations')) return 0;
        return SMP_Integrations::notify(array_merge([
            'user_id'=>$user_id,'type'=>sanitize_key($type),'title'=>sanitize_text_field($title),'body'=>sanitize_textarea_field($body),
            'link'=>esc_url_raw($link),'category'=>'marketplace','source'=>'sabri_marketplace',
        ], $extra));
    }

    public static function client_name(int $user_id): string {
        $u = get_userdata($user_id);
        return $u instanceof WP_User ? (string) $u->display_name : 'Marketplace user';
    }

    public static function touch_last_seen(): void {
        if (!is_user_logged_in() || wp_doing_cron()) return;
        $uid = get_current_user_id();
        $last = (int) get_user_meta($uid, 'smp_last_seen', true);
        if ($last > time() - 90) return;
        update_user_meta($uid, 'smp_last_seen', time());
    }

    public static function online_status(int $user_id): array {
        $ts = (int) get_user_meta($user_id, 'smp_last_seen', true);
        return ['online' => $ts > time() - 120, 'lastSeen' => $ts ? human_time_diff($ts, time()) . ' ago' : 'Unknown'];
    }

    public static function upload_product_images(string $field = 'images'): array {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) return [];
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $files = $_FILES[$field]; $count = is_array($files['name'] ?? null) ? count($files['name']) : 1;
        $max = max(1, (int) get_option('smp_max_product_images', 8)); $uploaded = [];
        for ($i=0; $i<min($count,$max); $i++) {
            $file=[]; foreach(['name','type','tmp_name','error','size'] as $key) $file[$key]=is_array($files[$key]??null)?$files[$key][$i]:($files[$key]??'');
            if ((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) continue;
            if ((int)($file['size']??0)>max(1,(int)get_option('smp_max_upload_mb',10))*MB_IN_BYTES) continue;
            $checked = wp_check_filetype_and_ext((string) $file['tmp_name'], sanitize_file_name((string) $file['name']), [
                'jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif',
            ]);
            if (empty($checked['type'])) continue;
            $_FILES['smp_single_upload']=$file; $id=media_handle_upload('smp_single_upload',0,[],['test_form'=>false]); unset($_FILES['smp_single_upload']);
            if (!is_wp_error($id)) $uploaded[]=['id'=>(int)$id,'url'=>(string)wp_get_attachment_url((int)$id)];
        }
        return $uploaded;
    }

    public static function attachment_scanner_available(): bool {
        return has_filter('smp_attachment_scan_result') || (defined('SMP_CLAMAV_COMMAND') && trim((string) SMP_CLAMAV_COMMAND) !== '');
    }

    private static function scan_attachment(string $path, string $mime, string $name): bool|WP_Error {
        $filtered = apply_filters('smp_attachment_scan_result', null, $path, $mime, $name);
        if (is_wp_error($filtered)) return $filtered;
        if (is_bool($filtered)) return $filtered ? true : new WP_Error('malware_detected', 'The attachment failed security scanning.');

        if (defined('SMP_CLAMAV_COMMAND') && trim((string) SMP_CLAMAV_COMMAND) !== '') {
            $command = trim((string) SMP_CLAMAV_COMMAND) . ' --no-summary ' . escapeshellarg($path) . ' 2>&1';
            $output = []; $code = 2;
            exec($command, $output, $code);
            if ($code === 0) return true;
            if ($code === 1) return new WP_Error('malware_detected', 'The attachment failed security scanning.');
            return new WP_Error('scanner_error', 'The attachment scanner could not complete the security check.');
        }

        return new WP_Error('scanner_unavailable', 'Private attachments are temporarily unavailable because the required malware scanner is not configured.');
    }

    public static function private_dir(): string {
        $dir = defined('SMP_PRIVATE_STORAGE_DIR') && trim((string) SMP_PRIVATE_STORAGE_DIR) !== ''
            ? trailingslashit((string) SMP_PRIVATE_STORAGE_DIR) . 'marketplace'
            : trailingslashit(dirname(rtrim(ABSPATH, '/\\'))) . 'sabri-private-files/marketplace';
        $dir = wp_normalize_path($dir);
        if (!is_dir($dir)) wp_mkdir_p($dir);
        if (is_dir($dir)) {
            if (!file_exists($dir . '/index.php')) file_put_contents($dir . '/index.php', "<?php\nhttp_response_code(404);\nexit;\n", LOCK_EX);
            if (!file_exists($dir . '/.htaccess')) file_put_contents($dir . '/.htaccess', "Require all denied\n", LOCK_EX);
            if (!file_exists($dir . '/web.config')) file_put_contents($dir . '/web.config', '<configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>', LOCK_EX);
        }
        return $dir;
    }

    public static function upload_private_chat_file(string $field = 'attachment'): array|WP_Error {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) return new WP_Error('missing_file','No attachment was provided.');
        if (!class_exists('SMP_Integrations') || !SMP_Integrations::membership_active()) return new WP_Error('crypto_unavailable','Secure attachment encryption is unavailable.');
        $file = $_FILES[$field];
        if ((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) return new WP_Error('upload_error','The attachment upload failed.');
        $max_bytes=max(1,(int)get_option('smp_max_chat_upload_mb',20))*MB_IN_BYTES;
        if ((int)($file['size']??0)>$max_bytes || (int)($file['size']??0)<1) return new WP_Error('too_large','The attachment is outside the allowed size limit.');
        $allowed=[
            'jpg|jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf','txt'=>'text/plain',
            'doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'webm'=>'audio/webm','ogg'=>'audio/ogg','mp3'=>'audio/mpeg','m4a'=>'audio/mp4','mp4'=>'video/mp4',
        ];
        $check=wp_check_filetype_and_ext((string)$file['tmp_name'],sanitize_file_name((string)$file['name']),$allowed);
        $mime=(string)($check['type']??''); $ext=(string)($check['ext']??'');
        if ($mime === '' || $ext === '') return new WP_Error('bad_type','This attachment type is not allowed.');
        $scan = self::scan_attachment((string) $file['tmp_name'], $mime, (string) $file['name']);
        if (is_wp_error($scan)) return $scan;

        $plain = file_get_contents((string) $file['tmp_name']);
        if ($plain === false) return new WP_Error('read_failed', 'The attachment could not be read securely.');
        $iv = random_bytes(12); $tag = '';
        $key = SMC_Security::key('marketplace-attachment');
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        unset($plain);
        if ($cipher === false) return new WP_Error('encrypt_failed', 'The attachment could not be encrypted.');

        $dir=self::private_dir();
        if (!is_dir($dir) || !is_writable($dir)) return new WP_Error('private_dir','The private attachment directory is unavailable.');
        $name=wp_unique_filename($dir,wp_generate_password(32,false,false).'.smp');
        $dest=trailingslashit($dir).$name;
        if (file_put_contents($dest, 'SMP1' . $iv . $tag . $cipher, LOCK_EX) === false) return new WP_Error('write_failed','The encrypted attachment could not be saved.');
        @chmod($dest,0640);
        return ['path'=>$name,'name'=>sanitize_file_name((string)$file['name']),'mime'=>$mime,'size'=>(int)$file['size'],'encrypted'=>true];
    }

    public static function private_file_path(string $name): string {
        return trailingslashit(self::private_dir()) . basename($name);
    }

    public static function read_private_file(string $name): string|WP_Error {
        $path = self::private_file_path($name);
        if (!is_file($path)) return new WP_Error('missing_file', 'Attachment not found.');
        $payload = file_get_contents($path);
        if ($payload === false) return new WP_Error('read_failed', 'Attachment could not be read.');
        if (substr($payload, 0, 4) !== 'SMP1' || strlen($payload) < 33) return new WP_Error('legacy_unsecured', 'This legacy attachment must be migrated before it can be downloaded.');
        if (!class_exists('SMC_Security')) return new WP_Error('crypto_unavailable', 'Attachment decryption is unavailable.');
        $iv=substr($payload,4,12);$tag=substr($payload,16,16);$cipher=substr($payload,32);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',SMC_Security::key('marketplace-attachment'),OPENSSL_RAW_DATA,$iv,$tag);
        return $plain === false ? new WP_Error('decrypt_failed','Attachment decryption failed.') : $plain;
    }

    public static function delete_private_file(string $name): void {
        $path=self::private_file_path($name); if (is_file($path)) @unlink($path);
    }

    public static function chat_file_url(int $message_id): string {
        return add_query_arg(['action'=>'smp_chat_file','message_id'=>$message_id,'nonce'=>wp_create_nonce('smp_chat_file_'.$message_id)],admin_url('admin-ajax.php'));
    }

    public static function disclaimer(): string {
        return (string) get_option('smp_direct_deal_disclaimer', 'Marketplace only connects buyers and sellers. Price, payment, inspection, pickup and delivery are arranged independently between the parties. The platform does not receive or hold transaction funds.');
    }
}
