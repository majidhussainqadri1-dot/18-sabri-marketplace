<?php
/** RC48: actual Listing search, both public/private projection SQL failure. */
declare(strict_types=1);
defined('ABSPATH') || define('ABSPATH',__DIR__);
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function sanitize_key($v){return strtolower((string)$v);}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function esc_url_raw($v){return (string)$v;}
function wp_parse_url($v,$component=null){return parse_url((string)$v,$component);}
function home_url($v=''){return 'https://sabrihomeopathy.com'.$v;}
function get_current_user_id(){return 10;}
class MKT_Contracts{
 public static function public_listing_fields():array{return ['public_id','title','category','description','price','currency','status'];}
}
class MKT_DB{public static function table($n):string{return 'wp_mkt_'.$n;}}
class MKT_Governance{public static function table($n):string{return 'wp_mkt_'.$n;}}
class QueryDB{
 public string $last_error='';
 public bool $break_media=false;
 public bool $break_media_exception=false;
 public bool $break_listing=false;
 public function esc_like($v){return $v;}
 public function prepare($sql,...$args){return $sql;}
 public function get_results($sql,$format=null){
    if(str_contains((string)$sql,'FROM wp_mkt_listings')){
      if($this->break_listing){$this->last_error='listing SQL failed';return null;}
      return [[
       'id'=>7,'public_id'=>'00000000-0000-4000-8000-000000000007',
       'seller_id'=>5,'seller_user_id'=>10,'seller_public_id'=>'00000000-0000-4000-8000-000000000005',
       'status'=>'active','seller_status'=>'approved','store_name'=>'Safe Seller','title'=>'Safe Book',
       'slug'=>'safe-book','category'=>'books','description'=>'Public information',
       'price'=>'100','currency'=>'PKR','quantity'=>'1','delivery_modes'=>'[]','contact_modes'=>'[]'
      ]];
    }
    if(str_contains((string)$sql,'FROM wp_mkt_media_refs')){
      if($this->break_media_exception)throw new RuntimeException('database private exception');
      if($this->break_media){$this->last_error='media SQL failed';return null;}
      return [];
    }
    return [];
 }
}
$wpdb=new QueryDB();$n=0;
function ok48($truth,$msg){global $n;$n++;if(!$truth)throw new RuntimeException('FAIL: '.$msg);}
require dirname(__DIR__).'/includes/class-mkt-listings.php';
$good=MKT_Listings::search(['limit'=>10]);
ok48(count($good['items']??[])===1&&!isset($good['error']),'Canonical public Listing SQL works');
ok48(($good['items'][0]['commission_percent']??-1)===0,'Zero fee remains on verified public projection');
$wpdb->break_media=true;
$r=MKT_Listings::search(['limit'=>10]);
ok48(($r['error']??'')==='search_unavailable'&&$r['items']===[],'Media SQL failed: no partial public page');
$r=MKT_Listings::search(['limit'=>10,'seller_user_id'=>10],true);
ok48(($r['error']??'')==='search_unavailable'&&$r['items']===[],'Media SQL failed: no private draft search leak');
$wpdb->break_media=false;$wpdb->break_media_exception=true;
$r=MKT_Listings::search(['limit'=>10]);
ok48(($r['error']??'')==='search_unavailable','Unexpected media exception safely contained');
$wpdb->break_media_exception=false;$wpdb->break_listing=true;
$r=MKT_Listings::search(['limit'=>10]);
ok48(($r['error']??'')==='search_unavailable','Owner SELECT failure contained');
$wpdb->break_listing=false;
$r=MKT_Listings::search(['limit'=>10]);
ok48(count($r['items']??[])===1,'Recovered core search returns eligible Listing');
echo 'PASS RC48: '.$n." actual Listing source projection assertions\n";
