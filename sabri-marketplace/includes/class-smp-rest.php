<?php
defined('ABSPATH') || exit;

final class SMP_REST {
    public static function register_routes(): void {
        register_rest_route('sabri-marketplace/v1', '/health', [
            'methods' => 'GET', 'callback' => [self::class, 'health'], 'permission_callback' => '__return_true',
        ]);
        register_rest_route('sabri-marketplace/v1', '/health/details', [
            'methods' => 'GET', 'callback' => [self::class, 'health_details'],
            'permission_callback' => static fn() => current_user_can('manage_sabri_marketplace'),
        ]);
        register_rest_route('sabri-marketplace/v1', '/products', [
            'methods' => 'GET', 'callback' => [self::class, 'products'],
            'permission_callback' => static fn() => is_user_logged_in() || (bool) get_option('smp_allow_guest_browse', 1),
            'args' => [
                'limit' => ['sanitize_callback' => 'absint', 'default' => 20],
                'page' => ['sanitize_callback' => 'absint', 'default' => 1],
                'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            ],
        ]);
    }

    public static function health(WP_REST_Request $request): WP_REST_Response {
        $response = new WP_REST_Response(['ok' => true, 'service' => 'marketplace'], 200);
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }

    public static function health_details(WP_REST_Request $request): WP_REST_Response {
        $tables = [];
        foreach (SMP_DB::tables() as $table) $tables[$table] = SMP_DB::table_exists($table);
        $data = [
            'ok' => !in_array(false, $tables, true) && !in_array(false, SMP_Integrations::status(), true),
            'version' => SMP_VERSION,
            'schema' => SMP_DB_VERSION,
            'mode' => 'direct-deal-zero-commission',
            'tables' => $tables,
            'integrations' => SMP_Integrations::status(),
            'privateDirectory' => is_dir(SMP_Utils::private_dir()) && is_writable(SMP_Utils::private_dir()),
            'attachmentScanner' => SMP_Utils::attachment_scanner_available(),
        ];
        $response = new WP_REST_Response($data, 200);
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }

    public static function products(WP_REST_Request $request): WP_REST_Response {
        SMP_Rate_Limiter::enforce('bootstrap');
        global $wpdb;
        $limit = min(50, max(1, (int) $request->get_param('limit')));
        $page = max(1, (int) $request->get_param('page'));
        $offset = ($page - 1) * $limit;
        $search = (string) $request->get_param('search');
        $where = "p.status IN ('published','approved') AND s.status='approved'";
        $params = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (p.title LIKE %s OR p.description LIKE %s OR s.store_name LIKE %s)';
            array_push($params, $like, $like, $like);
        }
        $params[] = $limit; $params[] = $offset;
        $sql = 'SELECT p.* FROM ' . SMP_DB::table('products') . ' p JOIN ' . SMP_DB::table('sellers') . ' s ON s.id=p.seller_id WHERE ' . $where . ' ORDER BY p.published_at DESC,p.id DESC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $response = new WP_REST_Response(['products' => array_map(['SMP_Utils', 'public_product'], $rows), 'page' => $page, 'limit' => $limit], 200);
        $response->header('Cache-Control', 'public, max-age=60');
        return $response;
    }
}
