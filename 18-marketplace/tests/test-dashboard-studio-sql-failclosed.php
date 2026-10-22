<?php
/** RC16 actual dashboard & Seller Studio SQL-outage negative paths. */
require __DIR__.'/bootstrap.php';
function get_current_user_id():int{return 23;}
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function sanitize_text_field($v): string { return trim(strip_tags((string)$v)); }
function wp_strip_all_tags($v): string { return strip_tags((string)$v); }
class MKT_DB {
    public static function table(string $name): string { return 'wp_mkt_'.$name; }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
}
class MKT_Listings {
    public static function search(array $filters=[], bool $private=false): array { return ['items'=>[],'has_more'=>false,'next_cursor'=>null]; }
}
class StudioDashboardDB {
    public string $last_error = '';
    public bool $fail_row = true;
    public bool $seller_exists = false;
    public bool $fail_results = true;
    public bool $count_failure = false;
    public function prepare(string $sql,...$args):string{
        if(count($args)===1&&is_array($args[0]))$args=$args[0];
        return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);
    }
    public function get_row(string $sql,$type){
        if ($this->fail_row) {$this->last_error='SQL seller lookup unavailable';return null;}
        return $this->seller_exists?['id'=>2,'public_id'=>'seller-2']:null;
    }
    public function get_results(string $sql,$type){
        if ($this->fail_results) {$this->last_error='SQL rows unavailable';return null;}
        if(str_contains($sql,'wp_mkt_listings'))return [[
            'id'=>4,'public_id'=>'listing4','status'=>'active','title'=>'Meaningful title',
            'description'=>str_repeat('description ',8),'category'=>'homeopathy_books',
            'currency'=>'PKR','price'=>200,'listing_type'=>'product'
        ]];
        return [];
    }
    public function get_var(string $sql){
        if($this->count_failure){$this->last_error='SQL COUNT unavailable';return null;}
        return '0';
    }
}
$wpdb=new StudioDashboardDB();
require dirname(__DIR__).'/includes/class-mkt-commerce.php';
require dirname(__DIR__).'/includes/class-mkt-governance.php';
$dashboard=MKT_Commerce::dashboard(23);
assert_true(is_wp_error($dashboard),'Failed dashboard SELECT returns explicit WP_Error, not array_map(NULL)/false zero.');
assert_same('mkt_dashboard_unavailable',$dashboard->get_error_code(),'Dashboard failure has stable API code.');
assert_same(503,$dashboard->get_error_data()['status'],'Dashboard outage requires HTTP 503.');
$studio=MKT_Governance::seller_studio(23);
assert_same(false,$studio['available'],'Seller Studio reports unavailable when seller SQL fails.');
assert_same(null,$studio['reports'],'Seller Studio does not invent zero reports.');
$wpdb->fail_row=false;$wpdb->seller_exists=true;$wpdb->fail_results=true;
assert_same(false,MKT_Governance::seller_studio(23)['available'],'Seller listings SQL failure cannot appear as no inventory.');
$wpdb->fail_results=false;$wpdb->count_failure=true;
assert_same(false,MKT_Governance::seller_studio(23)['available'],'Failed media count cannot lower quality score silently.');
$rest=file_get_contents(dirname(__DIR__).'/includes/class-mkt-rest.php');
$template=file_get_contents(dirname(__DIR__).'/templates/dashboard.php');
assert_true(str_contains($rest,'self::respond(MKT_Commerce::dashboard'), 'REST dashboard propagates error as HTTP 503.');
assert_true(str_contains($template,'is_wp_error($data)') && str_contains($template,'status_header(503)'), 'HTML dashboard renders a safe unavailable state without exposing fictitious totals.');
fwrite(STDOUT,"RC16 dashboard and Seller Studio SQL fail-closed regression complete.\n");
