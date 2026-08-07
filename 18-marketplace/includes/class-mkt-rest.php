<?php
defined('ABSPATH') || exit;

final class MKT_REST {
    public static function register_routes(): void {
        $ns = MKT_Contracts::REST_NAMESPACE;
        register_rest_route($ns, '/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/policies/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response(['categories' => MKT_Policy::categories(), 'commission_percent' => 0]),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/listings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'listings'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_listing'],
                'permission_callback' => [self::class, 'logged_in'],
            ],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'listing'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_listing'],
                'permission_callback' => [self::class, 'logged_in'],
            ],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})/submit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'submit_listing'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'transition_listing'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})/media', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'attach_media'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})/media/(?P<media_id>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'remove_media'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})/save', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'save_listing'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})/chat', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'open_chat'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/listings/(?P<id>[a-f0-9-]{36})/offers', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_offer'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/offers/(?P<id>[a-f0-9-]{36})/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'transition_offer'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/deals/(?P<id>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'deal'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/deals/(?P<id>[a-f0-9-]{36})/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'transition_deal'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/deals/(?P<id>[a-f0-9-]{36})/disputes', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'open_dispute'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/reports', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_report'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/reports/(?P<id>[a-f0-9-]{36})/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'transition_report'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/disputes/(?P<id>[a-f0-9-]{36})/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'transition_dispute'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/dashboard', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'dashboard'],
            'permission_callback' => [self::class, 'logged_in'],
        ]);
        register_rest_route($ns, '/system-check', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'system_check'],
            'permission_callback' => static fn() => current_user_can('mkt_view_system'),
        ]);
        register_rest_route($ns, '/repair', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'repair'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
    }

    public static function logged_in(): bool|WP_Error {
        return is_user_logged_in() ? true : new WP_Error('mkt_authentication_required', __('Please sign in to continue.', 'marketplace'), ['status' => 401]);
    }

    public static function status(): WP_REST_Response {
        $rate = MKT_Rate_Limiter::check('public_detail');
        if (is_wp_error($rate)) return self::error_response($rate);
        return rest_ensure_response([
            'module' => 'file-18-marketplace',
            'version' => MKT_VERSION,
            'commission_percent' => 0,
            'public_browse' => true,
            'integrations' => MKT_Integrations::status(),
            'safe_mode' => !empty(MKT_DB::settings()['safe_mode']),
            'trace_id' => MKT_Audit::trace_id(),
        ]);
    }

    public static function listings(WP_REST_Request $request): WP_REST_Response {
        $rate = MKT_Rate_Limiter::check('public_search');
        if (is_wp_error($rate)) return self::error_response($rate);
        return rest_ensure_response(MKT_Listings::search([
            'q' => $request->get_param('q'),
            'category' => $request->get_param('category'),
            'country' => $request->get_param('country'),
            'city' => $request->get_param('city'),
            'currency' => $request->get_param('currency'),
            'price_min' => $request->get_param('price_min'),
            'price_max' => $request->get_param('price_max'),
            'cursor' => $request->get_param('cursor'),
            'limit' => $request->get_param('limit'),
        ]));
    }

    public static function listing(WP_REST_Request $request): WP_REST_Response {
        $rate = MKT_Rate_Limiter::check('public_detail');
        if (is_wp_error($rate)) return self::error_response($rate);
        $listing = MKT_Listings::get((string) $request['id'], false);
        if (!$listing) return self::error_response(new WP_Error('mkt_listing_not_found', __('Listing not found.', 'marketplace'), ['status' => 404]));
        return rest_ensure_response(MKT_Listings::public_dto($listing));
    }

    public static function create_listing(WP_REST_Request $request): WP_REST_Response {
        return self::respond(self::mutate('create_listing', $request, (array) $request->get_json_params(), fn() => MKT_Listings::create((array) $request->get_json_params(), get_current_user_id())));
    }

    public static function update_listing(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('update_listing', $request, $data, fn() => MKT_Listings::update((string) $request['id'], $data, get_current_user_id(), (int) ($data['version'] ?? 0))));
    }

    public static function submit_listing(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('submit_listing', $request, $data, fn() => MKT_Listings::submit((string) $request['id'], get_current_user_id(), (int) ($data['version'] ?? 0))));
    }

    public static function transition_listing(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('transition_listing', $request, $data, fn() => MKT_Listings::transition((string) $request['id'], sanitize_key((string) ($data['to'] ?? '')), get_current_user_id(), (int) ($data['version'] ?? 0), (string) ($data['reason'] ?? ''), (string) ($data['note'] ?? ''))));
    }

    public static function attach_media(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('attach_media', $request, $data, fn() => MKT_Listings::attach_media((string) $request['id'], $data, get_current_user_id())));
    }

    public static function remove_media(WP_REST_Request $request): WP_REST_Response {
        return self::respond(self::mutate('remove_media', $request, [], fn() => MKT_Listings::remove_media((string) $request['id'], (string) $request['media_id'], get_current_user_id())));
    }

    public static function save_listing(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('save_listing', $request, $data, fn() => MKT_Listings::save((string) $request['id'], get_current_user_id(), !isset($data['save']) || (bool) $data['save'])));
    }

    public static function open_chat(WP_REST_Request $request): WP_REST_Response {
        return self::respond(self::mutate('open_chat', $request, [], fn() => MKT_Listings::open_chat((string) $request['id'], get_current_user_id())));
    }

    public static function create_offer(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('create_offer', $request, $data, fn() => MKT_Commerce::create_offer((string) $request['id'], $data, get_current_user_id(), self::idempotency($request, $data))));
    }

    public static function transition_offer(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('transition_offer', $request, $data, fn() => MKT_Commerce::transition_offer((string) $request['id'], sanitize_key((string) ($data['to'] ?? '')), $data, get_current_user_id(), (int) ($data['version'] ?? 0), self::idempotency($request, $data))));
    }

    public static function deal(WP_REST_Request $request): WP_REST_Response {
        return self::respond(MKT_Commerce::get_deal((string) $request['id'], get_current_user_id()));
    }

    public static function transition_deal(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('transition_deal', $request, $data, fn() => MKT_Commerce::transition_deal((string) $request['id'], sanitize_key((string) ($data['to'] ?? '')), $data, get_current_user_id(), (int) ($data['version'] ?? 0))));
    }

    public static function open_dispute(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('open_dispute', $request, $data, fn() => MKT_Moderation::open_dispute((string) $request['id'], $data, get_current_user_id())));
    }

    public static function create_report(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('create_report', $request, $data, fn() => MKT_Moderation::create_report($data, get_current_user_id())));
    }

    public static function transition_report(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('transition_report', $request, $data, fn() => MKT_Moderation::transition_report((string) $request['id'], sanitize_key((string) ($data['to'] ?? '')), $data, get_current_user_id(), (int) ($data['version'] ?? 0))));
    }

    public static function transition_dispute(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        return self::respond(self::mutate('transition_dispute', $request, $data, fn() => MKT_Moderation::transition_dispute((string) $request['id'], sanitize_key((string) ($data['to'] ?? '')), $data, get_current_user_id(), (int) ($data['version'] ?? 0))));
    }

    public static function dashboard(): WP_REST_Response {
        return rest_ensure_response(MKT_Commerce::dashboard(get_current_user_id()));
    }

    public static function system_check(): WP_REST_Response {
        $rate = MKT_Rate_Limiter::check('system');
        if (is_wp_error($rate)) return self::error_response($rate);
        return rest_ensure_response(MKT_Admin::system_status());
    }

    public static function repair(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        $dry_run = !isset($data['execute']) || !(bool) $data['execute'];
        return self::respond(self::mutate('repair', $request, $data, fn() => MKT_Admin::repair($dry_run)));
    }

    private static function mutate(string $bucket, WP_REST_Request $request, array $data, callable $callback) {
        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $application_password_auth = $authorization !== '' || !empty($_SERVER['PHP_AUTH_USER']);
        if (!$application_password_auth && !wp_verify_nonce((string) ($_SERVER['HTTP_X_WP_NONCE'] ?? ''), 'wp_rest')) {
            return new WP_Error('mkt_invalid_nonce', __('Security token validation failed.', 'marketplace'), ['status' => 403]);
        }
        $rate = MKT_Rate_Limiter::check($bucket);
        if (is_wp_error($rate)) return $rate;
        $scope = sanitize_key($bucket . '_' . strtolower($request->get_method()) . '_' . trim($request->get_route(), '/'));
        return MKT_Idempotency::run($scope, self::idempotency($request, $data), $data, $callback);
    }

    private static function idempotency(WP_REST_Request $request, array $data): string {
        return sanitize_text_field((string) ($request->get_header('Idempotency-Key') ?: ($data['idempotency_key'] ?? '')));
    }

    private static function respond($result): WP_REST_Response {
        return is_wp_error($result) ? self::error_response($result) : rest_ensure_response($result);
    }

    private static function error_response(WP_Error $error): WP_REST_Response {
        $data = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 400;
        $response = new WP_REST_Response([
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data' => is_array($data) ? array_diff_key($data, ['status' => true]) : [],
            'trace_id' => MKT_Audit::trace_id(),
        ], $status);
        if (is_array($data) && isset($data['retry_after'])) {
            $response->header('Retry-After', (string) (int) $data['retry_after']);
        }
        return $response;
    }
}
