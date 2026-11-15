<?php
/** RC52: canonical REST actual class public listing and search SQL failure. */
declare(strict_types=1);
defined('ABSPATH')||define('ABSPATH',__DIR__);
class WP_Error{
 public function __construct(private string $c,private string $m='',private array $d=[]){}
 public function get_error_code(){return $this->c;}
 public function get_error_message(){return $this->m;}
 public function get_error_data(){return $this->d;}
}
function is_wp_error($x){return $x instanceof WP_Error;}
function __($s,$d=''){return $s;}
function rest_ensure_response($data){return new WP_REST_Response($data,200);}
class WP_REST_Response{
 public function __construct(public array $data=[],public int $status=200){}
 public function get_status(){return $this->status;}
 public function get_data(){return $this->data;}
}
class WP_REST_Request implements ArrayAccess{
 public function __construct(private array $x=[]){}
 public function get_param($x){return $this->x[$x]??null;}
 public function offsetExists($o):bool{return isset($this->x[$o]);}
 public function offsetGet($o):mixed{return $this->x[$o]??null;}
 public function offsetSet($o,$v):void{$this->x[$o]=$v;}
 public function offsetUnset($o):void{unset($this->x[$o]);}
}
class MKT_Rate_Limiter{public static function check($bucket){return true;}}
class MKT_Audit{public static function trace_id(){return 'safe-trace';}}
class MKT_Listings{
 public static string $state='ok';
 public static function search($filters=[]){
  if(self::$state==='search_throw')throw new RuntimeException('secret read exception');
  if(self::$state==='search_sql')return ['items'=>[],'error'=>'search_unavailable'];
  if(self::$state==='bad_cursor')return ['items'=>[],'error'=>'invalid_cursor'];
  if(self::$state==='old_cursor')return ['items'=>[],'error'=>'cursor_expired'];
  return ['items'=>[],'has_more'=>false,'next_cursor'=>null];
 }
 public static function get(string $id,bool $private=false){
  global $wpdb;
  if(self::$state==='get_throw')throw new RuntimeException('secret read error');
  if(self::$state==='get_sql'){$wpdb->last_error='owner select failed';return null;}
  if(self::$state==='not_found')return null;
  return ['public_id'=>$id];
 }
 public static function public_dto(array $r){
  global $wpdb;
  if(self::$state==='dto_throw')throw new RuntimeException('media read unavailable');
  if(self::$state==='dto_sql'){$wpdb->last_error='media SELECT failed';return null;}
  return $r;
 }
}
class MKT_REST_Security{}
$wpdb=(object)['last_error'=>''];
require dirname(__DIR__).'/includes/class-mkt-rest.php';
$n=0;
function assert52($x,$msg){global $n;$n++;if(!$x)throw new RuntimeException('FAIL: '.$msg);}
$id='00000000-0000-4000-8000-000000000001';
$req=new WP_REST_Request(['id'=>$id]);
foreach(['get_sql','get_throw','dto_sql','dto_throw'] as $mode){
 MKT_Listings::$state=$mode;
 $r=MKT_REST::listing($req);
 assert52($r->get_status()===503,'Listing '.$mode.' correctly reports server failure');
 assert52(($r->get_data()['code']??'')==='mkt_public_read_unavailable','No private SQL detail on '.$mode);
}
MKT_Listings::$state='not_found';
assert52(MKT_REST::listing($req)->get_status()===404,'Genuine missing listing still 404');
MKT_Listings::$state='ok';
assert52(MKT_REST::listing($req)->get_status()===200,'Verified public listing still 200');
foreach(['search_throw','search_sql'] as $mode){
 MKT_Listings::$state=$mode;
 $r=MKT_REST::listings($req);
 assert52($r->get_status()===503,'Search '.$mode.' correctly reports 503');
}
MKT_Listings::$state='bad_cursor';
assert52(MKT_REST::listings($req)->get_status()===400,'Malformed core cursor HTTP400');
MKT_Listings::$state='old_cursor';
assert52(MKT_REST::listings($req)->get_status()===410,'Expired core cursor HTTP410');
MKT_Listings::$state='ok';
assert52(MKT_REST::listings($req)->get_status()===200,'Healthy core search HTTP200');
echo 'PASS RC52: '.$n." core REST Listing/search response assertions\n";
