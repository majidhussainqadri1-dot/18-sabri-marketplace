<?php
/** RC47 actual core + advanced search cursor syntax and HTTP response. */
declare(strict_types=1);
defined('ABSPATH') || define('ABSPATH', __DIR__);
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function sanitize_key($v):string{return strtolower((string)$v);}
function sanitize_text_field($v):string{return strip_tags((string)$v);}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
function rest_ensure_response($r){return new WP_REST_Response($r,200);}
class WP_REST_Response{
 public function __construct(public array $data=[],public int $status=200){}
 public function get_status():int{return $this->status;}
 public function get_data():array{return $this->data;}
}
class WP_REST_Request{
 public function __construct(private array $values=[]){}
 public function get_param($key){return $this->values[$key]??null;}
}
class MKT_Rate_Limiter{public static function check($n){return true;}}
class MKT_DB{
 public static function table($n){return 'wp_mkt_'.$n;}
 public static function currency($v){return strtoupper((string)$v);}
}
class MKT_Plan_Completion{public static function table(){return 'wp_mkt_listing_facets';}}
class MKT_Governance{
 public static function table($n){return 'wp_mkt_recalls';}
 public static function public_evidence($v){return null;}
 public static function public_recall($v){return null;}
}
class CursorDB{
 public string $last_error='';
 public int $results=0;
 public function esc_like($v){return $v;}
 public function prepare($sql,...$args){return $sql;}
 public function get_results($sql,$t=null){$this->results++;return [];}
 public function get_row($sql,$t=null){return null;}
}
$wpdb=new CursorDB();$n=0;
function rc47($ok,$why){global $n;$n++;if(!$ok)throw new RuntimeException('FAIL: '.$why);}
require dirname(__DIR__).'/includes/class-mkt-listings.php';
require dirname(__DIR__).'/includes/class-mkt-finalization.php';
foreach(['-1','not-a-number','1.0','01','1e3','+42','18446744073709551616',true,[],new stdClass] as $bad){
 foreach(['MKT_Listings','MKT_Finalization'] as $owner){
  $r=$owner==='MKT_Finalization' ? MKT_Finalization::search_array(['cursor'=>$bad]) : MKT_Listings::search(['cursor'=>$bad]);
  rc47(($r['error']??'')==='invalid_cursor','Invalid cursor rejected by '.$owner);
 }
}
rc47($wpdb->results===0,'Invalid cursors do not run SQL');
foreach(['',0,'0','1','999999999999999999'] as $ok){
 $a=MKT_Listings::search(['cursor'=>$ok]);$b=MKT_Finalization::search_array(['cursor'=>$ok]);
 rc47(!isset($a['error'])||$a['error']==='cursor_expired','Valid core cursor is parsed');
 rc47(!isset($b['error'])||$b['error']==='cursor_expired','Valid advanced cursor is parsed');
}
$r=MKT_Finalization::search(new WP_REST_Request(['cursor'=>'1e3']));
rc47($r->get_status()===400&&($r->get_data()['error']??'')==='invalid_cursor','Malformed cursor is HTTP 400');
$r=MKT_Finalization::search(new WP_REST_Request(['cursor'=>'42']));
rc47($r->get_status()===410&&($r->get_data()['error']??'')==='cursor_expired','Missing bounded cursor anchor is HTTP 410');
$r=MKT_Finalization::search(new WP_REST_Request(['cursor'=>'']));
rc47($r->get_status()===200,'Empty valid cursor keeps successful public search');
echo 'PASS RC47: '.$n." strict public cursor contract assertions\n";
