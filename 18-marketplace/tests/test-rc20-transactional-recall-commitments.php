<?php
/** RC20: Actual canonical commerce class denies recalled commitments under locks. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
defined('HOUR_IN_SECONDS') || define('HOUR_IN_SECONDS',3600);
function wp_kses_post($v):string{return (string)$v;}
function home_url($v=''):string{return 'https://example.test'.$v;}
final class MKT_Auth {
 public static function can($cap,$ctx=[]){return true;}
 public static function seller_eligibility(int $user):array{return ['eligible'=>true];}
}
final class MKT_DB {
 public static function settings():array{return ['safe_mode'=>0,'offers_enabled'=>1];}
 public static function currency(string $v):string{return strtoupper($v);}
 public static function table(string $v):string{return 'wp_mkt_'.$v;}
 public static function transaction(callable $callback){return $callback();}
 public static function now():string{return '2026-10-22 00:00:00';}
}
final class MKT_Listings {
 public static function get(string $pid,bool $private=false):?array{return [
 'id'=>1,'public_id'=>$pid,'status'=>'active','availability'=>'available',
 'seller_user_id'=>2,'currency'=>'PKR','expires_at'=>'2099-10-01 00:00:00',
 ];}
 public static function not_expired($s):bool{return true;}
}
final class MKT_Governance {
 public static int $checks=0;
 public static bool $locked=true;
 public static string $mode='recalled';
 public static function recall_publication_guard(string $pid,bool $locking_read=false):bool|WP_Error{
  self::$checks++;
  self::$locked=self::$locked && $locking_read;
  if(self::$mode==='outage')return new WP_Error('mkt_recall_verification_unavailable','Recall verification unavailable',['status'=>503]);
  return self::$mode==='recalled';
 }
}
final class MKT_State_Machines {public static function assert($object,$from,$to){return true;}}
final class RecallCommerceDB {
 public int $writes=0;
 public function prepare(string $sql,...$params):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$params);}
 public function get_row(string $sql,$mode):?array{
  if(str_contains($sql,'wp_mkt_offers') && str_contains($sql,'idempotency_key'))return null;
  if(str_contains($sql,'wp_mkt_offers') && str_contains($sql,'public_id'))return [
   'id'=>10,'public_id'=>'offer-id','listing_id'=>1,'buyer_user_id'=>41,
   'seller_user_id'=>2,'created_by'=>41,'status'=>'open','version'=>1,
   'expires_at'=>'2099-10-01 00:00:00','currency'=>'PKR'
  ];
  if(str_contains($sql,'wp_mkt_listings'))return [
   'id'=>1,'public_id'=>'listing-id','status'=>'active',
   'availability'=>'available','seller_status'=>'approved','seller_user_id'=>2,
   'seller_id'=>3,'expires_at'=>'2099-10-01 00:00:00','currency'=>'PKR','version'=>1,
  ];
  return null;
 }
 public function update($table,$payload,$where):int{$this->writes++;return 1;}
 public function insert($table,$payload):int{$this->writes++;return 1;}
 public function query($sql):int{$this->writes++;return 1;}
}
$wpdb=new RecallCommerceDB();
require dirname(__DIR__).'/includes/class-mkt-commerce.php';
$key='rc20-idempotency-123456789';
function assert_recall_result($result,string $mode,string $action):void {
 global $wpdb;
 assert_true(is_wp_error($result),$action.' cannot commit when recall is '.$mode);
 assert_same($mode==='outage'?'mkt_recall_verification_unavailable':'mkt_active_recall',$result->get_error_code(),$action.' returns exact denial reason');
 assert_same(0,$wpdb->writes,$action.' leaves zero new Marketplace writes.');
}
foreach(['recalled','outage'] as $mode) {
 MKT_Governance::$mode=$mode;
 $one=MKT_Commerce::create_offer('listing-id',['amount'=>10,'currency'=>'PKR'],41,$key);
 assert_recall_result($one,$mode,'create_offer');
 $two=MKT_Commerce::transition_offer('offer-id','countered',['amount'=>12],2,1,$key.'-counter');
 assert_recall_result($two,$mode,'counter_offer');
 $ref=new ReflectionMethod(MKT_Commerce::class,'create_deal_from_offer');
 $three=$ref->invoke(null,['id'=>10,'public_id'=>'offer-id','listing_id'=>1,'buyer_user_id'=>41,'seller_user_id'=>2,'amount'=>10,'currency'=>'PKR'],2);
 assert_recall_result($three,$mode,'accept_offer_create_deal');
}
assert_same(6,MKT_Governance::$checks,'All three canonical paths revalidate recalls under two danger conditions.');
assert_same(true,MKT_Governance::$locked,'Each new commerce commitment takes a locking recall read.');
$actual_governance=file_get_contents(dirname(__DIR__).'/includes/class-mkt-governance.php');
assert_true(str_contains($actual_governance,"($locking_read ? ' FOR UPDATE' : '')"),'Real canonical recall guard can issue a locking read.');
fwrite(STDOUT,"RC20 recalled commerce, SQL outage and locking revalidation passed.\n");