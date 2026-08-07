<?php
defined('ABSPATH') || exit;

/**
 * Final corrected surface/operations layer for the four-plan File 18 candidate.
 * This remains inside the canonical File 18 owner; it does not introduce a
 * second Marketplace data store or business workflow owner.
 */
final class MKT_Finalization {
    public static function boot(): void {
        add_action('rest_api_init', [self::class, 'override_search_route'], 200);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets'], 30);
        add_action('admin_post_mkt_moderation_action', [self::class, 'handle_admin_moderation_atomic'], 1);
        add_filter('mkt_assurance_manifest', [self::class, 'assurance_manifest']);
    }

    public static function override_search_route(): void {
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/listings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'search'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [MKT_Plan_Completion::class, 'create_listing'],
                'permission_callback' => [MKT_REST::class, 'logged_in'],
            ],
        ], true);
    }

    public static function enqueue_assets(): void {
        if (!MKT_Routes::is_marketplace_request()) return;
        wp_enqueue_script('mkt-marketplace-completion', MKT_URL . 'assets/js/marketplace-completion.js', ['mkt-marketplace'], MKT_VERSION, true);
    }

    public static function search(WP_REST_Request $request): WP_REST_Response {
        $rate = MKT_Rate_Limiter::check('public_search');
        if (is_wp_error($rate)) return self::error_response($rate);
        return rest_ensure_response(self::search_array([
            'q' => $request->get_param('q'),
            'category' => $request->get_param('category'),
            'country' => $request->get_param('country'),
            'city' => $request->get_param('city'),
            'currency' => $request->get_param('currency'),
            'price_min' => $request->get_param('price_min'),
            'price_max' => $request->get_param('price_max'),
            'availability' => $request->get_param('availability'),
            'listing_type' => $request->get_param('listing_type') ?: $request->get_param('service_type'),
            'seller_status' => $request->get_param('seller_status') ?: $request->get_param('seller_eligibility'),
            'language' => $request->get_param('language'),
            'rights' => $request->get_param('rights'),
            'cursor' => $request->get_param('cursor'),
            'limit' => $request->get_param('limit'),
        ]));
    }

    public static function search_array(array $filters): array {
        global $wpdb;
        $limit = min(50, max(1, (int) ($filters['limit'] ?? 20)));
        $cursor = max(0, (int) ($filters['cursor'] ?? 0));
        $where = ["l.status='active'", "s.status='approved'"];
        $params = [];

        $category = sanitize_key((string) ($filters['category'] ?? ''));
        if ($category !== '') { $where[] = 'l.category=%s'; $params[] = $category; }
        $country = strtoupper(substr(preg_replace('/[^A-Z]/i', '', (string) ($filters['country'] ?? '')), 0, 2));
        if ($country !== '') { $where[] = 'l.location_country=%s'; $params[] = $country; }
        $city = sanitize_text_field((string) ($filters['city'] ?? ''));
        if ($city !== '') { $where[] = 'l.location_city=%s'; $params[] = $city; }
        $currency = sanitize_text_field((string) ($filters['currency'] ?? ''));
        if ($currency !== '') { $where[] = 'l.currency=%s'; $params[] = MKT_DB::currency($currency); }
        if (($filters['price_min'] ?? '') !== '' && $filters['price_min'] !== null) { $where[] = 'l.price>=%f'; $params[] = max(0, (float) $filters['price_min']); }
        if (($filters['price_max'] ?? '') !== '' && $filters['price_max'] !== null) { $where[] = 'l.price<=%f'; $params[] = max(0, (float) $filters['price_max']); }

        $availability = sanitize_key((string) ($filters['availability'] ?? ''));
        if ($availability !== '') {
            if (!in_array($availability, ['available','limited','preorder','service_schedule'], true)) return ['items'=>[], 'next_cursor'=>null, 'has_more'=>false, 'error'=>'invalid_availability'];
            $where[] = 'l.availability=%s'; $params[] = $availability;
        }
        $listing_type = sanitize_key((string) ($filters['listing_type'] ?? ''));
        if ($listing_type !== '') {
            if (!in_array($listing_type, ['product','service','digital'], true)) return ['items'=>[], 'next_cursor'=>null, 'has_more'=>false, 'error'=>'invalid_listing_type'];
            $where[] = 'l.listing_type=%s'; $params[] = $listing_type;
        }
        $seller_status = sanitize_key((string) ($filters['seller_status'] ?? ''));
        if ($seller_status !== '') {
            if ($seller_status !== 'approved') return ['items'=>[], 'next_cursor'=>null, 'has_more'=>false];
            $where[] = "s.status='approved'";
        }
        $language = self::normalize_language((string) ($filters['language'] ?? ''));
        if ((string) ($filters['language'] ?? '') !== '' && $language === '') return ['items'=>[], 'next_cursor'=>null, 'has_more'=>false, 'error'=>'invalid_language'];
        if ($language !== '') {
            $where[] = '(f.language=%s OR f.language LIKE %s)';
            $params[] = $language;
            $params[] = $wpdb->esc_like($language) . '-%';
        }
        $rights = sanitize_key((string) ($filters['rights'] ?? ''));
        if ($rights !== '') {
            if ($rights !== 'approved') return ['items'=>[], 'next_cursor'=>null, 'has_more'=>false, 'error'=>'invalid_rights'];
            $where[] = 'NOT EXISTS (SELECT 1 FROM ' . MKT_DB::table('media_refs') . " mr WHERE mr.listing_id=l.id AND mr.status='active' AND (mr.rights_status<>'approved' OR mr.scan_status<>'clean'))";
        }
        $q = sanitize_text_field((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(l.title LIKE %s OR l.description LIKE %s OR l.category LIKE %s OR l.subcategory LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($cursor > 0) { $where[] = 'l.id<%d'; $params[] = $cursor; }

        $sql = 'SELECT l.*,s.public_id AS seller_public_id,s.user_id AS seller_user_id,s.store_name,s.status AS seller_status,s.public_contact_modes,COALESCE(f.language,\'und\') AS marketplace_language FROM ' . MKT_DB::table('listings') . ' l INNER JOIN ' . MKT_DB::table('sellers') . ' s ON s.id=l.seller_id LEFT JOIN ' . MKT_Plan_Completion::table() . ' f ON f.listing_id=l.id WHERE ' . implode(' AND ', array_values(array_unique($where))) . ' ORDER BY l.published_at DESC,l.id DESC LIMIT %d';
        $params[] = $limit + 1;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        $has_more = count($rows) > $limit;
        if ($has_more) array_pop($rows);
        $items = [];
        foreach ($rows as $row) {
            $item = MKT_Listings::public_dto($row);
            $item['language'] = (string) ($row['marketplace_language'] ?? 'und');
            if (class_exists('MKT_Governance')) {
                $item['evidence'] = MKT_Governance::public_evidence((string) $item['public_id']);
                $item['recall'] = MKT_Governance::public_recall((string) $item['public_id']);
            }
            $items[] = $item;
        }
        $next_cursor = $has_more && $rows ? (int) end($rows)['id'] : null;
        return ['items'=>$items, 'next_cursor'=>$next_cursor, 'has_more'=>$has_more];
    }

    public static function listing_language(int $listing_id): string {
        if ($listing_id <= 0) return '';
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare('SELECT language FROM ' . MKT_Plan_Completion::table() . ' WHERE listing_id=%d', $listing_id));
        return is_string($value) ? $value : '';
    }

    public static function handle_admin_moderation_atomic(): void {
        if (!current_user_can('mkt_review_marketplace')) wp_die(esc_html__('Access denied.', 'marketplace'));
        $auth = MKT_Auth::can('mkt_moderate', ['action'=>'admin_marketplace_moderation']);
        if (is_wp_error($auth)) wp_die(esc_html__('Access denied.', 'marketplace'));
        check_admin_referer('mkt_moderation_action');
        $type = sanitize_key(wp_unslash((string) ($_POST['object_type'] ?? '')));
        $public_id = sanitize_text_field(wp_unslash((string) ($_POST['public_id'] ?? '')));
        $to = sanitize_key(wp_unslash((string) ($_POST['to'] ?? '')));
        $version = (int) wp_unslash((string) ($_POST['version'] ?? 0));
        $reason = sanitize_key(wp_unslash((string) ($_POST['decision_code'] ?? '')));
        $note = wp_kses_post(wp_unslash((string) ($_POST['decision_note'] ?? '')));
        try {
            $result = MKT_DB::transaction(function() use ($type,$public_id,$to,$version,$reason,$note) {
                if ($type === 'listing') return MKT_Listings::transition($public_id, $to, get_current_user_id(), $version, $reason, $note);
                if ($type === 'report') return MKT_Moderation::transition_report($public_id, $to, ['decision_code'=>$reason,'decision_note'=>$note], get_current_user_id(), $version);
                if ($type === 'dispute') return MKT_Moderation::transition_dispute($public_id, $to, ['decision_code'=>$reason,'decision_note'=>$note], get_current_user_id(), $version);
                return new WP_Error('mkt_invalid_admin_action', __('Invalid moderation action.', 'marketplace'));
            });
        } catch (Throwable $e) {
            $result = new WP_Error('mkt_admin_atomic_failure', __('The moderation action could not be committed safely.', 'marketplace'));
        }
        $url = add_query_arg(is_wp_error($result) ? ['mkt_error'=>rawurlencode($result->get_error_message())] : ['mkt_updated'=>1], admin_url('admin.php?page=mkt-marketplace-moderation'));
        wp_safe_redirect($url);
        exit;
    }

    private static function normalize_language(string $language): string {
        $language = strtolower(trim(str_replace('_', '-', $language)));
        if ($language === '') return '';
        if ($language === 'und') return 'und';
        return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $language) ? $language : '';
    }

    private static function error_response(WP_Error $error): WP_REST_Response {
        $data = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 400;
        return new WP_REST_Response(['code'=>$error->get_error_code(),'message'=>$error->get_error_message(),'trace_id'=>MKT_Audit::trace_id()], $status);
    }

    public static function assurance_manifest(array $manifest): array {
        $manifest['controls']['corrected_marketplace_facets'] = ['category','location','price','availability','seller_status','rights','language','listing_type'];
        $manifest['controls']['admin_moderation_atomic_owner_outbox'] = true;
        return $manifest;
    }
}
