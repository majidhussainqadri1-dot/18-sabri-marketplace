<?php
/** RC27 actual Commerce owner: final receipt readback must verify command facts before transaction COMMIT. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
defined('HOUR_IN_SECONDS') || define('HOUR_IN_SECONDS',3600);
function get_current_user_id():int{return (int)($GLOBALS['actor']??41);}
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function wp_kses_post($v):string{return (string)$v;}
function wp_strip_all_tags($v):string{return strip_tags((string)$v);}
function wp_json_encode($v):string{return json_encode($v);}
function home_url($path):string{return 'https://example.invalid'.$path;}
function current_user_can($cap):bool{return false;}
class MKT_Auth {
 public static function can($cap,$ctx=[]):bool{return true;}
 public static function seller_eligibility(int $id):array{return ['eligible'=>true];}
 public static function deal_participant(array $row,int $actor):bool{return in_array($actor,[(int)$row['buyer_user_id'],(int)$row['seller_user_id']],true);}
}
class MKT_Listings {
 public static function get(string $pid,bool $private=false):?array{global $wpdb;return $wpdb->listing;}
 public static function not_expired($v):bool{return true;}
}
class MKT_Governance {public static function recall_publication_guard(string $id,bool $lock=false):bool{return false;}}
class MKT_State_Machines {public static function assert($kind,$from,$to):bool{return true;}}
class MKT_DB {
 private static int $serial=0;
 public static function uuid():string{return sprintf('00000000-0000-4000-8000-%012d',++self::$serial);}
 public static function settings():array{return ['safe_mode'=>0,'offers_enabled'=>1,'deal_transitions_enabled'=>1];}
 public static function currency(string $s):string{return strtoupper($s);}
 public static function table(string $t):string{return 'wp_mkt_'.$t;}
 public static function now():string{return '2026-10-22 09:00:00';}
 public static function transaction(callable $fn){
  global $wpdb;
  $snapshot=[$wpdb->offers,$wpdb->deals,$wpdb->listing,$wpdb->audit,$wpdb->events];
  try {
   $result=$fn();
   if(is_wp_error($result))[$wpdb->offers,$wpdb->deals,$wpdb->listing,$wpdb->audit,$wpdb->events]=$snapshot;
   return $result;
  } catch(Throwable $e) {
   [$wpdb->offers,$wpdb->deals,$wpdb->listing,$wpdb->audit,$wpdb->events]=$snapshot;
   throw $e;
  }
 }
}
class MKT_Audit {
 public static function trace_id():string{return 'rc27-audit';}
 public static function record(...$args):void{global $wpdb;$wpdb->audit[]=$args;}
}
class MKT_Events {
 public static function enqueue(...$args):void{global $wpdb;$wpdb->events[]=$args;}
}
class CommerceReceiptDB {
 public int $insert_id=0;
 public int $serial=70;
 public array $offers=[];
 public array $deals=[];
 public array $audit=[];
 public array $events=[];
 public array $listing;
 public string $corruption='normal';
 public string $corrupt_table='';
 public function __construct(){
  $this->listing=['id'=>7,'public_id'=>'listing-1','seller_id'=>19,
   'seller_user_id'=>2,'seller_public_id'=>'seller-1','seller_status'=>'approved',
   'store_name'=>'Store','status'=>'active','availability'=>'available',
   'expires_at'=>'2099-01-01 00:00:00','quantity'=>2,'version'=>1,
   'currency'=>'PKR','title'=>'Example product'];
  $this->offers[10]=['id'=>10,'public_id'=>'offer-1','root_offer_id'=>0,'parent_offer_id'=>0,
   'listing_id'=>7,'buyer_user_id'=>41,'seller_user_id'=>2,'created_by'=>41,
   'amount'=>100,'currency'=>'PKR','terms'=>'terms','status'=>'open','version'=>1,
   'expires_at'=>'2099-01-01 00:00:00','created_at'=>'2026-10-22 09:00:00','updated_at'=>'2026-10-22 09:00:00'];
  $this->deals[20]=['id'=>20,'public_id'=>'deal-1','listing_id'=>7,'accepted_offer_id'=>10,
   'buyer_user_id'=>41,'seller_user_id'=>2,'agreed_amount'=>100,'currency'=>'PKR',
   'agreed_snapshot'=>'{}','payment_mode'=>'direct','payment_status'=>'declared',
   'delivery_status'=>'arranging','status'=>'accepted','version'=>1,
   'buyer_completion_confirmed_at'=>null,'seller_completion_confirmed_at'=>null,
   'created_at'=>'2026-10-22 09:00:00','updated_at'=>'2026-10-22 09:00:00',
   'completed_at'=>null,'closed_at'=>null];
 }
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 private function alter(?array $row,string $table,bool $readback):?array{
  if(!$readback || $table!==$this->corrupt_table || $this->corruption==='normal')return $row;
  if($this->corruption==='missing')return null;
  if(!$row)return null;
  if($this->corruption==='wrong_status')$row['status']='invalid';
  if($this->corruption==='wrong_version')$row['version']=900;
  if($this->corruption==='wrong_identity')$row['public_id']='other-uuid';
  if($this->corruption==='wrong_owner')$row[$table==='deals'?'accepted_offer_id':'buyer_user_id']=9999;
  return $row;
 }
 public function get_row(string $sql,$kind):?array{
  if(str_contains($sql,'wp_mkt_listings'))return $this->listing;
  if(str_contains($sql,'wp_mkt_offers')){
   if(str_contains($sql,'idempotency_key'))return null;
   if(preg_match('/WHERE id=(\d+)/',$sql,$m))return $this->alter($this->offers[(int)$m[1]]??null,'offers',true);
   if(preg_match("/WHERE public_id='([^']+)'/",$sql,$m))
     foreach($this->offers as $offer)if($offer['public_id']===$m[1])return $offer;
   return null;
  }
  if(str_contains($sql,'wp_mkt_deals')){
   if(str_contains($sql,'accepted_offer_id'))return null;
   if(preg_match('/WHERE id=(\d+)/',$sql,$m))return $this->alter($this->deals[(int)$m[1]]??null,'deals',true);
   if(preg_match("/WHERE public_id='([^']+)'/",$sql,$m))
     foreach($this->deals as $deal)if($deal['public_id']===$m[1])return $deal;
  }
  return null;
 }
 public function get_var(string $sql):string{return '0';}
 public function insert(string $table,array $data):int {
  $this->insert_id=++$this->serial;
  if(str_ends_with($table,'offers')){$this->offers[$this->insert_id]=array_merge(['id'=>$this->insert_id,'root_offer_id'=>0,'parent_offer_id'=>0],$data);return 1;}
  if(str_ends_with($table,'deals')){$this->deals[$this->insert_id]=array_merge(['id'=>$this->insert_id,'completed_at'=>null,'closed_at'=>null,'buyer_completion_confirmed_at'=>null,'seller_completion_confirmed_at'=>null],$data);return 1;}
  throw new RuntimeException('Unknown insert target');
 }
 public function update(string $table,array $values,array $where):int {
  if(str_ends_with($table,'offers')){$id=(int)$where['id'];$this->offers[$id]=array_merge($this->offers[$id],$values);return 1;}
  if(str_ends_with($table,'deals')){$id=(int)$where['id'];$this->deals[$id]=array_merge($this->deals[$id],$values);return 1;}
  if(str_ends_with($table,'listings')){$this->listing=array_merge($this->listing,$values);return 1;}
  throw new RuntimeException('Unknown update target');
 }
 public function query(string $sql):int{return 0;}
}
require dirname(__DIR__).'/includes/class-mkt-commerce.php';
function check_case(string $kind,string $mode):void{
 global $wpdb;
 $wpdb=new CommerceReceiptDB();
 $wpdb->corruption=$mode;
 $wpdb->corrupt_table=in_array($kind,['create_offer','counter_offer','transition_offer'],true)?'offers':'deals';
 $GLOBALS['actor']=in_array($kind,['counter_offer','create_deal'],true)?2:41;
 $before=[$wpdb->offers,$wpdb->deals,$wpdb->listing,$wpdb->audit,$wpdb->events];
 if($kind==='create_offer'){
  $r=MKT_Commerce::create_offer('listing-1',['amount'=>120,'currency'=>'PKR'],41,'rc27-create-offer-key-123');
  $error='mkt_offer_readback_failed';
 }elseif($kind==='counter_offer'){
  $r=MKT_Commerce::transition_offer('offer-1','countered',['amount'=>121],2,1,'rc27-counter-offer-key-123');
  $error='mkt_counter_readback_failed';
 }elseif($kind==='transition_offer'){
  $r=MKT_Commerce::transition_offer('offer-1','withdrawn',[],41,1,'rc27-transition-offer-key-123');
  $error='mkt_offer_transition_readback_failed';
 }elseif($kind==='create_deal'){
  $method=new ReflectionMethod(MKT_Commerce::class,'create_deal_from_offer');
  $r=$method->invoke(null,$wpdb->offers[10],2);
  $error='mkt_deal_readback_failed';
 }else{
  $r=MKT_Commerce::transition_deal('deal-1','cancelled',[],41,1);
  $error='mkt_deal_transition_readback_failed';
 }
 if($mode==='normal'){
  assert_true(is_array($r),$kind.' succeeds with verified receipt.');
  return;
 }
 assert_true(is_wp_error($r),$kind.' refuses '.$mode.' receipt.');
 assert_same($error,$r->get_error_code(),$kind.' named safe rollback error.');
 assert_same($before[0],$wpdb->offers,$kind.' bad readback reverts offers.');
 assert_same($before[1],$wpdb->deals,$kind.' bad readback reverts deals.');
 assert_same($before[2],$wpdb->listing,$kind.' bad readback reverts listing stock/status.');
 assert_same($before[3],$wpdb->audit,$kind.' bad readback reverts mandatory audit.');
 assert_same($before[4],$wpdb->events,$kind.' bad readback reverts event outbox.');
}
foreach(['create_offer','counter_offer','transition_offer','create_deal','transition_deal'] as $kind)
 foreach(['normal','missing','wrong_status','wrong_version','wrong_identity'] as $mode)
  check_case($kind,$mode);
foreach(['create_offer','create_deal'] as $kind)check_case($kind,'wrong_owner');
fwrite(STDOUT,"RC27 real Commerce owner receipts: 5 native paths, success and unsafe readback rollback verified.\n");