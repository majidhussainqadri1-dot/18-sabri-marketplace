<?php
/** RC16: runtime-shaped expiry and fail-closed deal count; source checks for atomic offers. */
require __DIR__ . '/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
function sanitize_text_field($v): string { return trim(strip_tags((string) $v)); }
function wp_strip_all_tags($v): string { return strip_tags((string) $v); }
function wp_trim_words($v, $n=20): string { return (string) $v; }
final class MKT_DB {
    public static function table(string $name): string { return 'wp_mkt_' . $name; }
    public static function currency(string $value): string { return strtoupper($value); }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
}
// This earlier expiry regression isolates the listing/COUNT failure path;
// RC20 has a separate six-case recalled/SQL-outage commitment regression.
final class MKT_Governance {
    public static function recall_publication_guard(string $public_id, bool $locking_read=false): bool|WP_Error { return false; }
}
final class MKT_Plan_Completion {
    public static function table(): string { return 'wp_mkt_listing_fulfillment'; }
}
final class MKT_Auth {
    public static function seller_eligibility(int $user_id): array { return ['eligible'=>true]; }
}
final class ExpiryFaultDB {
    public string $last_error = '';
    public array $queries = [];
    public array $listing = [];
    public int $count_reads = 0;
    public function __construct() {
        $this->listing = [
            'id'=>5, 'status'=>'active', 'seller_status'=>'approved',
            'seller_user_id'=>12, 'availability'=>'available', 'quantity'=>1,
            'expires_at'=>gmdate('Y-m-d H:i:s', time()+3600)
        ];
    }
    public function esc_like(string $s): string { return $s; }
    public function prepare(string $sql, ...$args): string {
        if (count($args) === 1 && is_array($args[0])) $args=$args[0];
        return vsprintf(str_replace(['%s','%d','%f'], ["'%s'",'%d','%F'], $sql), $args);
    }
    public function get_row(string $sql, $type) {
        $this->queries[]=$sql;
        if (str_contains($sql, ' FOR UPDATE') && str_contains($sql, 'wp_mkt_listings')) return $this->listing;
        return null;
    }
    public function get_results(string $sql, $type): array {
        $this->queries[]=$sql;
        return [];
    }
    public function get_var(string $sql) {
        $this->queries[]=$sql;
        $this->count_reads++;
        $this->last_error='Injected SQL count failure';
        return null;
    }
}
$wpdb = new ExpiryFaultDB();
require dirname(__DIR__) . '/includes/class-mkt-listings.php';
require dirname(__DIR__) . '/includes/class-mkt-finalization.php';
require dirname(__DIR__) . '/includes/class-mkt-commerce.php';
assert_same(false, MKT_Listings::not_expired(null), 'Missing expiry is never publicly eligible.');
assert_same(false, MKT_Listings::not_expired('garbage'), 'Malformed expiry cannot reauthorize publication.');
assert_same(false, MKT_Listings::not_expired(gmdate('Y-m-d H:i:s', time()-1)), 'Past UTC deadline is expired even if Cron did not run.');
assert_same(true, MKT_Listings::not_expired(gmdate('Y-m-d H:i:s', time()+3600)), 'A verified future UTC deadline remains eligible.');
MKT_Listings::get('00000000-0000-4000-8000-000000000001');
assert_true(str_contains(end($wpdb->queries), 'l.expires_at>UTC_TIMESTAMP()'), 'Public listing detail excludes a stale active row.');
MKT_Listings::get_by_id(5);
assert_true(str_contains(end($wpdb->queries), 'l.expires_at>UTC_TIMESTAMP()'), 'Public listing ID lookups exclude expired state.');
MKT_Listings::search([]);
assert_true(str_contains(end($wpdb->queries), 'l.expires_at>UTC_TIMESTAMP()'), 'Canonical File18 public search enforces expiry in SQL.');
MKT_Finalization::search_array([]);
assert_true(str_contains(end($wpdb->queries), 'l.expires_at>UTC_TIMESTAMP()'), 'File26-facing enriched marketplace search enforces expiry.');
$offer=['id'=>3, 'listing_id'=>5];
$deal = new ReflectionMethod(MKT_Commerce::class, 'create_deal_from_offer');
$wpdb->listing['expires_at'] = gmdate('Y-m-d H:i:s', time()-3600);
$wpdb->count_reads=0;
$expired=$deal->invoke(null,$offer,20);
assert_true(is_wp_error($expired) && $expired->get_error_code()==='mkt_listing_unavailable', 'A locked expired listing cannot become a newly accepted deal.');
assert_same(0,$wpdb->count_reads,'Expired listing rejected before stock/count checks.');
$wpdb->listing['expires_at'] = gmdate('Y-m-d H:i:s', time()+3600);
$wpdb->count_reads=0;
$failed_count=$deal->invoke(null,$offer,20);
assert_true(is_wp_error($failed_count) && $failed_count->get_error_code()==='mkt_deal_count_unavailable', 'Failed COUNT must not be interpreted as zero outstanding deals.');
assert_same(1,$wpdb->count_reads,'Unit-stock commitment count consulted once and verified.');
$commerce=file_get_contents(dirname(__DIR__).'/includes/class-mkt-commerce.php');
$listingSource=file_get_contents(dirname(__DIR__).'/includes/class-mkt-listings.php');
assert_true(substr_count($commerce,'MKT_Listings::not_expired(')>=3, 'Offer, counter-offer and deal creation revalidate expiry under owner transaction.');
assert_true(str_contains($listingSource, "mkt_listing_expired"), 'Reactivation of an expired listing has a positive owner-side denial.');
assert_same(2, substr_count($commerce, "if (\$declined === false) throw new RuntimeException("), 'Both competing-offer UPDATE branches fail transaction on SQL failure.');
fwrite(STDOUT,"RC16 expiry, deal-count, and offer-atomicity negative regression complete.\n");
