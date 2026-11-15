<?php
/** RC44: real Finalization class projection exceptions/SQL faults are HTTP 503. */
declare(strict_types=1);
defined('ABSPATH') || define('ABSPATH', __DIR__);
defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
function sanitize_key($v):string{return strtolower((string)$v);}
function sanitize_text_field($v):string{return strip_tags((string)$v);}
function rest_ensure_response($v){return new WP_REST_Response($v,200);}
class WP_REST_Response {
    public function __construct(public array $data,public int $status=200){}
    public function get_status(){return $this->status;}
    public function get_data(){return $this->data;}
}
class WP_REST_Request {
    public function get_param($name){return null;}
}
class MKT_Rate_Limiter{public static function check($key){return true;}}
class MKT_DB{
    public static function table($name){return 'wp_mkt_'.$name;}
    public static function currency($s){return $s;}
}
class MKT_Plan_Completion{public static function table(){return 'wp_mkt_listing_facets';}}
class MKT_Governance{
    public static function table($name){return 'wp_mkt_recalls';}
    public static function public_evidence($id){
        global $wpdb;
        if($wpdb->mode==='evidence_sql')$wpdb->last_error='injected evidence error';
        if($wpdb->mode==='evidence_throw')throw new RuntimeException('private database error');
        return null;
    }
    public static function public_recall($id){
        global $wpdb;
        if($wpdb->mode==='recall_sql')$wpdb->last_error='injected recall error';
        if($wpdb->mode==='recall_throw')throw new RuntimeException('private recall error');
        return null;
    }
}
class MKT_Listings{
    public static function public_dto($row){
        global $wpdb;
        if($wpdb->mode==='media_throw')throw new RuntimeException('private media error');
        if($wpdb->mode==='media_sql')$wpdb->last_error='injected media error';
        if($wpdb->mode==='dto_invalid')return ['public_id'=>''];
        return ['public_id'=>$row['public_id'],'title'=>'A good book','media'=>[],'price'=>'1'];
    }
}
class FaultDB{
    public string $last_error='';
    public string $mode='ok';
    public function prepare($sql,...$args){return $sql;}
    public function get_results($sql,$t=null){
        return [['id'=>1,'public_id'=>'00000000-0000-4000-8000-000000000001','marketplace_language'=>'ur']];
    }
    public function esc_like($s){return (string)$s;}
}
$wpdb=new FaultDB();$checks=0;
function check_rc44($yes,$reason){global $checks;$checks++;if(!$yes)throw new RuntimeException('FAIL: '.$reason);}
require dirname(__DIR__).'/includes/class-mkt-finalization.php';
$good=MKT_Finalization::search_array([]);
check_rc44(count($good['items'])===1&&($good['items'][0]['language']??'')==='ur','Verified owner data may render');
foreach(['media_throw','media_sql','dto_invalid','evidence_sql','evidence_throw','recall_sql','recall_throw'] as $case){
    $wpdb->mode=$case;
    $result=MKT_Finalization::search_array([]);
    check_rc44(($result['error']??'')==='search_unavailable'&&$result['items']===[],'Failure '.$case.' returns empty fail-closed 503 payload');
    $response=MKT_Finalization::search(new WP_REST_Request());
    check_rc44($response->get_status()===503&&($response->get_data()['error']??'')==='search_unavailable','Failure '.$case.' sends HTTP 503');
}
$wpdb->mode='ok';$again=MKT_Finalization::search_array([]);
check_rc44(count($again['items'])===1,'Recovery allows genuine public listing again');
echo 'PASS RC44: '.$checks." actual-class second-stage search projection assertions\n";
