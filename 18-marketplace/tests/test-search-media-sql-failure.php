<?php
/** Test actual File18 search paths and media rights gating under failed SQL results. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function wp_strip_all_tags($v):string{return strip_tags((string)$v);}
function wp_trim_words($v,$n=20):string{return (string)$v;}
function rest_ensure_response($data):WP_REST_Response{return new WP_REST_Response($data,200);}
class WP_REST_Response {
 public function __construct(public array $data=[],public int $status=200){}
 public function get_status():int{return $this->status;}
 public function get_data():array{return $this->data;}
}
class WP_REST_Request {
 public function __construct(private array $args=[]){}
 public function get_param(string $key){return $this->args[$key]??null;}
}
class MKT_Rate_Limiter {public static function check(string $name):bool{return true;}}
class MKT_DB {
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function currency(string $x):string{return strtoupper($x);}
}
class MKT_Plan_Completion {public static function table():string{return 'wp_mkt_listing_fulfillment';}}
class SearchMediaDB {
 public string $last_error='';
 public bool $fail_rows=false;
 public bool $rows_null=false;
 public bool $fail_anchor=false;
 public bool $fail_counts=false;
 public bool $null_counts=false;
 public int $result_calls=0;
 public int $count_calls=0;
 public array $count_values=[1,0];
 public function prepare(string $sql,...$args):string {
  if(count($args)===1&&is_array($args[0]))$args=$args[0];
  return vsprintf(str_replace(['%s','%d','%f'],["'%s'",'%d','%F'],$sql),$args);
 }
 public function esc_like(string $v):string{return $v;}
 public function get_row(string $sql,$type):?array{
  if($this->fail_anchor){$this->last_error='Injected cursor SELECT error';return null;}
  return ['published_at'=>'2026-10-19 12:00:00'];
 }
 public function get_results(string $sql,$type):?array{
  $this->result_calls++;
  if($this->fail_rows){$this->last_error='Injected SELECT outage';return null;}
  if($this->rows_null)return null;
  return [];
 }
 public function get_var(string $sql) {
  $this->count_calls++;
  if($this->fail_counts){$this->last_error='Injected COUNT outage';return null;}
  if($this->null_counts)return null;
  return $this->count_values[$this->count_calls-1]??0;
 }
}
$wpdb=new SearchMediaDB();
require dirname(__DIR__).'/includes/class-mkt-listings.php';
require dirname(__DIR__).'/includes/class-mkt-finalization.php';

$wpdb->fail_rows=true;
$broken=MKT_Listings::search();
assert_same('search_unavailable',$broken['error'],'Core File18 source search SQL failure is NOT successful zero results.');
assert_same(false,$broken['has_more'],'Failed search cannot fabricate pagination.');
$broken=MKT_Finalization::search_array([]);
assert_same('search_unavailable',$broken['error'],'Advanced Marketplace search SQL failure must not count(null).');
$http=MKT_Finalization::search(new WP_REST_Request());
assert_same(503,$http->get_status(),'Actual public REST route reports temporary database unavailability as HTTP 503.');
assert_same('search_unavailable',$http->get_data()['error'],'REST error is machine actionable but safe.');
$wpdb->fail_rows=false;
$wpdb->rows_null=true;
assert_same('search_unavailable',MKT_Listings::search()['error'],'NULL without last_error is still a failed core query.');
assert_same('search_unavailable',MKT_Finalization::search_array([])['error'],'NULL without last_error is still a failed enhanced query.');
$wpdb->rows_null=false;
assert_same([],MKT_Listings::search()['items'],'Successful zero-row core search is legitimately empty.');
assert_same([],MKT_Finalization::search_array([])['items'],'Successful zero-row enhanced search is legitimately empty.');

$wpdb->fail_anchor=true;
assert_same('search_unavailable',MKT_Listings::search(['cursor'=>99])['error'],'Broken core cursor SELECT must not claim cursor expired.');
assert_same('search_unavailable',MKT_Finalization::search_array(['cursor'=>99])['error'],'Broken enhanced cursor SELECT must not claim cursor expired.');
$wpdb->fail_anchor=false;

$ready=new ReflectionMethod(MKT_Listings::class,'media_ready');
$ready->setAccessible(true);
$wpdb->count_calls=0;$wpdb->fail_counts=true;
assert_same(false,$ready->invoke(null,10,false),'Unknown media count always blocks safety gate.');
assert_same(false,$ready->invoke(null,10,true),'Unknown media count blocks mandatory media gate.');
$wpdb->fail_counts=false;$wpdb->null_counts=true;$wpdb->count_calls=0;
assert_same(false,$ready->invoke(null,10,false),'Silent NULL media count must fail closed.');
$wpdb->null_counts=false;$wpdb->count_calls=0;$wpdb->count_values=[1,0];
assert_same(true,$ready->invoke(null,10,true),'Verified approved media set permits eligible listing.');
$wpdb->count_calls=0;$wpdb->count_values=[1,1];
assert_same(false,$ready->invoke(null,10,false),'One unsafe media attachment blocks publication.');
$wpdb->count_calls=0;$wpdb->count_values=[0,0];
assert_same(false,$ready->invoke(null,10,true),'Required media gate rejects verified zero attachments.');
$wpdb->fail_rows=true;
try {MKT_Listings::media(10,true);$throws=false;}catch(RuntimeException $e){$throws=true;}
assert_same(true,$throws,'Failed media SELECT cannot be silently converted to an empty approved media projection.');
fwrite(STDOUT,"File18 SQL failure search/REST and fail-closed media-rights negative regression complete.\n");