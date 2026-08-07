<?php
defined('ABSPATH') || exit;

final class MKT_Routes {
    public static function register_rewrites(): void {
        add_rewrite_tag('%mkt_route%', '([^&]+)');
        add_rewrite_tag('%mkt_object_id%', '([a-f0-9-]+)');
        add_rewrite_rule('^marketplace/?$', 'index.php?mkt_route=archive', 'top');
        add_rewrite_rule('^marketplace/listing/([a-f0-9-]{36})/([^/]+)/?$', 'index.php?mkt_route=listing&mkt_object_id=$matches[1]', 'top');
        add_rewrite_rule('^marketplace/sell/?$', 'index.php?mkt_route=sell', 'top');
        add_rewrite_rule('^marketplace/dashboard/?$', 'index.php?mkt_route=dashboard', 'top');
        add_rewrite_rule('^marketplace/deal/([a-f0-9-]{36})/?$', 'index.php?mkt_route=deal&mkt_object_id=$matches[1]', 'top');
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'mkt_route';
        $vars[] = 'mkt_object_id';
        return $vars;
    }

    public static function is_marketplace_request(): bool {
        return (string) get_query_var('mkt_route') !== '' || str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/marketplace');
    }

    public static function template_include(string $template): string {
        $route = sanitize_key((string) get_query_var('mkt_route'));
        if ($route === '') return $template;
        $map = [
            'archive' => 'archive.php',
            'listing' => 'listing.php',
            'sell' => 'sell.php',
            'dashboard' => 'dashboard.php',
            'deal' => 'deal.php',
        ];
        if (!isset($map[$route])) return $template;
        if (in_array($route, ['sell','dashboard','deal'], true) && !is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url(add_query_arg([], wp_unslash($_SERVER['REQUEST_URI'] ?? '/marketplace/')))));
            exit;
        }
        status_header(200);
        self::enqueue_assets();
        return MKT_DIR . 'templates/' . $map[$route];
    }

    public static function safe_back_url(): string {
        $fallback = home_url('/marketplace/');
        $referer = wp_get_referer();
        if (!$referer) {
            return $fallback;
        }
        $validated = wp_validate_redirect($referer, '');
        if ($validated === '') {
            return $fallback;
        }
        $home = wp_parse_url(home_url('/'));
        $target = wp_parse_url($validated);
        if (!is_array($home) || !is_array($target) || empty($target['host']) || strcasecmp((string) ($home['host'] ?? ''), (string) $target['host']) !== 0) {
            return $fallback;
        }
        return $validated;
    }

    public static function register_assets(): void {
        wp_register_style('mkt-marketplace', MKT_URL . 'assets/css/marketplace.css', [], MKT_VERSION);
        wp_register_script('mkt-marketplace', MKT_URL . 'assets/js/marketplace.js', [], MKT_VERSION, true);
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style('mkt-marketplace');
        wp_enqueue_script('mkt-marketplace');
        wp_localize_script('mkt-marketplace', 'MKT_APP', [
            'restUrl' => esc_url_raw(rest_url(MKT_Contracts::REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'loggedIn' => is_user_logged_in(),
            'loginUrl' => wp_login_url(home_url('/marketplace/')),
            'commissionPercent' => 0,
            'strings' => [
                'working' => __('Working…', 'marketplace'),
                'error' => __('The action could not be completed.', 'marketplace'),
                'saved' => __('Saved.', 'marketplace'),
                'confirm' => __('Please confirm this action.', 'marketplace'),
            ],
        ]);
    }

    public static function privacy_headers(): void {
        if (!self::is_marketplace_request()) return;
        $route = sanitize_key((string) get_query_var('mkt_route'));
        if (in_array($route, ['sell','dashboard','deal'], true)) {
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
            nocache_headers();
            header('X-Robots-Tag: noindex, noarchive, nofollow', true);
            header('Cache-Control: private, no-store, max-age=0', true);
        }
        header('Referrer-Policy: strict-origin-when-cross-origin', true);
        header('X-Content-Type-Options: nosniff', true);
    }


    public static function structured_data(): void {
        $route = sanitize_key((string) get_query_var('mkt_route'));
        if ($route === 'archive') {
            echo '<link rel="canonical" href="' . esc_url(home_url('/marketplace/')) . '">' . "\n";
            return;
        }
        if ($route !== 'listing') {
            return;
        }
        $listing = MKT_Listings::get((string) get_query_var('mkt_object_id'), false);
        if (!$listing) {
            return;
        }
        $item = MKT_Listings::public_dto($listing);
        echo '<link rel="canonical" href="' . esc_url($item['url']) . '">' . "\n";
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $item['listing_type'] === 'service' ? 'Service' : 'Product',
            'name' => $item['title'],
            'description' => wp_trim_words(wp_strip_all_tags((string) $item['description']), 60, ''),
            'url' => $item['url'],
            'sku' => $item['public_id'],
            'offers' => [
                '@type' => 'Offer',
                'price' => $item['price'],
                'priceCurrency' => $item['currency'],
                'availability' => $item['availability'] === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/LimitedAvailability',
                'url' => $item['url'],
                'seller' => ['@type' => 'Organization', 'name' => $item['seller']['store_name']],
            ],
        ];
        $image = $item['media'][0]['metadata']['url'] ?? $item['media'][0]['metadata']['thumbnail_url'] ?? '';
        if ($image) {
            $schema['image'] = esc_url_raw((string) $image);
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    public static function document_title(array $parts): array {
        $route = sanitize_key((string) get_query_var('mkt_route'));
        if ($route === 'archive') $parts['title'] = __('Marketplace', 'marketplace');
        if ($route === 'sell') $parts['title'] = __('Create listing', 'marketplace');
        if ($route === 'dashboard') $parts['title'] = __('Marketplace dashboard', 'marketplace');
        if ($route === 'deal') $parts['title'] = __('Marketplace deal', 'marketplace');
        if ($route === 'listing') {
            $listing = MKT_Listings::get((string) get_query_var('mkt_object_id'), false);
            $parts['title'] = $listing ? (string) $listing['title'] : __('Listing', 'marketplace');
        }
        return $parts;
    }
}
