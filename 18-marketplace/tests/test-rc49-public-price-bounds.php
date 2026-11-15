<?php
/** RC49 actual core/faceted price filter validation, no silent coercion. */
declare(strict_types=1);
defined('ABSPATH') || define('ABSPATH',__DIR__);
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function sanitize_key($x){return strtolower((string)$x);}
function sanitize_text_field($x){return strip_tags((string)$x);}
function is_wp_error($x):bool{return false;}
function rest_ensure_response($r){return new WP_REST_Response($r,200);}
class WP_REST_Response{
  public function __construct(public array $data=[],public int $status=200){}
  public function get_status(){return $this->status;}
}
class WP_REST_Request{
  public function __construct(private array $options=[]){}
  public function get_param($key){return $this->options[$key]??null;}
}
class MKT_Rate_Limiter{public static function check($n){return true;}}
class MKT_DB{
  public static function table($n){return 'wp_mkt_'.$n;}
  public static function currency($v){return $v;}
}
class MKT_Governance{public static function table($x){return 'wp_mkt_recalls';}}
class MKT_Plan_Completion{public static function table(){return 'wp_mkt_facets';}}
class PriceDB{
  public int $count=0;
  public string $last_error='';
  public function esc_like($x){return $x;}
  public function prepare($sql,...$args){return $sql;}
  public function get_results($sql,$mode=null){$this->count++;return [];}
}
$wpdb=new PriceDB();$asserts=0;
function good49($v,$label){global $asserts;$asserts++;if(!$v)throw new RuntimeException('FAIL: '.$label);}
require dirname(__DIR__).'/includes/class-mkt-listings.php';
require dirname(__DIR__).'/includes/class-mkt-finalization.php';
$bad=[
  ['price_min'=>'abc'],['price_max'=>'1e309'],['price_min'=>'-1'],
  ['price_max'=>'-0.1'],['price_min'=>[]],['price_min'=>INF],
  ['price_max'=>'100000000000000000'],['price_min'=>'900','price_max'=>'100'],
];
foreach($bad as $filter){
 $a=MKT_Listings::search($filter);
 $b=MKT_Finalization::search_array($filter);
 good49(($a['error']??'')==='invalid_price_range','Core owner rejects invalid money range');
 good49(($b['error']??'')==='invalid_price_range','Faceted owner rejects invalid money range');
}
good49($wpdb->count===0,'Invalid price filters do not touch DB');
foreach([[],['price_min'=>'0'],['price_min'=>'100.25'],['price_max'=>'500'],['price_min'=>'1','price_max'=>'2']] as $filter){
 good49(!isset(MKT_Listings::search($filter)['error']),'Core valid price filter accepted');
 good49(!isset(MKT_Finalization::search_array($filter)['error']),'Faceted valid price filter accepted');
}
foreach([['price_min'=>'not-price'],['price_min'=>'100','price_max'=>'1']] as $filter){
 $r=MKT_Finalization::search(new WP_REST_Request($filter));
 good49($r->get_status()===400,'HTTP 400 on invalid currency bounds');
}
echo 'PASS RC49: '.$asserts." actual bounded-price search assertions\n";
