<?php
/** RC22 real Commerce owner: a reused key must match the full persisted offer command. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
defined('HOUR_IN_SECONDS') || define('HOUR_IN_SECONDS', 3600);
function wp_kses_post($x):string{return (string)$x;}
function sanitize_text_field($x):string{return trim(strip_tags((string)$x));}
final class MKT_DB {
 public static function settings():array{return ['safe_mode'=>0,'offers_enabled'=>1];}
 public static function currency(string $c):string{return strtoupper($c);}
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function transaction(callable $c){return $c();}
}
final class MKT_Auth {
 public static function can(string $cap,array $ctx=[]):bool|WP_Error{return true;}
}
final class MKT_State_Machines {public static function assert($thing,$from,$to):bool{return true;}}
final class MKT_Listings {
 public static function get(string $id,bool $priv=false):array{return [
  'id'=>$id==='listing-b'?99:7,'public_id'=>$id,'status'=>'active',
  'availability'=>'available','seller_user_id'=>2,'currency'=>'PKR'];
 }
}
final class ReplayCollisionDB {
 public int $writes=0;
 public array $existing=[
  'id'=>55,'public_id'=>'offer-old','root_offer_id'=>0,'parent_offer_id'=>0,
  'listing_id'=>7,'buyer_user_id'=>41,'seller_user_id'=>2,
  'created_by'=>41,'amount'=>'101.00','currency'=>'PKR','terms'=>'delivery in person',
  'status'=>'open','version'=>1,'created_at'=>'2026-10-21 12:00:00',
  'updated_at'=>'2026-10-21 12:00:00','expires_at'=>'2026-10-23 12:00:00'
 ];
 public array $parent=[];
 public function __construct(){ $this->parent=$this->existing;$this->parent['public_id']='offer-parent';$this->parent['id']=10; }
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function get_row(string $sql,$type):?array{
   if(str_contains($sql,"WHERE public_id='offer-parent'"))return $this->parent;
   if(str_contains($sql,'idempotency_key='))return $this->existing;
   return null;
 }
 public function update($table,$rows,$where):int{$this->writes++;return 1;}
 public function insert($table,$rows):int{$this->writes++;return 1;}
}
$wpdb=new ReplayCollisionDB();
require dirname(__DIR__).'/includes/class-mkt-commerce.php';
$key='rc22-replay-key-123456';
$base=['amount'=>101,'currency'=>'PKR','terms'=>'delivery in person','expires_hours'=>48];
function deny($result,string $label):void{
 global $wpdb;
 assert_true(is_wp_error($result),$label.' must fail closed.');
 assert_same('mkt_idempotency_conflict',$result->get_error_code(),$label.' yields a specific conflict.');
 assert_same(0,$wpdb->writes,$label.' never creates a new offer or mutates the old one.');
}
$ok=MKT_Commerce::create_offer('listing-a',$base,41,$key);
assert_same('offer-old',$ok['public_id'],'Identical offer replay returns exactly the prior receipt.');
deny(MKT_Commerce::create_offer('listing-b',$base,41,$key),'Reused key with a different listing');
deny(MKT_Commerce::create_offer('listing-a',array_merge($base,['amount'=>102]),41,$key),'Reused key with a different amount');
deny(MKT_Commerce::create_offer('listing-a',array_merge($base,['terms'=>'different promise']),41,$key),'Reused key with different terms');
deny(MKT_Commerce::create_offer('listing-a',array_merge($base,['expires_hours'=>72]),41,$key),'Reused key with different expiry');
$wpdb->existing['buyer_user_id']=78;
deny(MKT_Commerce::create_offer('listing-a',$base,41,$key),'Reused key with a different buyer');
$wpdb->existing['buyer_user_id']=41;
$wpdb->existing['parent_offer_id']=10;
$wpdb->existing['public_id']='counter-old';
$wpdb->existing['created_by']=2;
$counter=['amount'=>101,'terms'=>'delivery in person','expires_hours'=>48];
$retry=MKT_Commerce::transition_offer('offer-parent','countered',$counter,2,1,$key);
assert_same('counter-old',$retry['public_id'],'Identical counter-offer replay returns the prior counter receipt.');
deny(MKT_Commerce::transition_offer('offer-parent','countered',array_merge($counter,['amount'=>105]),2,1,$key),'Changed counter amount');
deny(MKT_Commerce::transition_offer('offer-parent','countered',array_merge($counter,['terms'=>'different']),2,1,$key),'Changed counter terms');
deny(MKT_Commerce::transition_offer('offer-parent','countered',array_merge($counter,['expires_hours'=>36]),2,1,$key),'Changed counter expiry');
assert_same(0,$wpdb->writes,'All mismatched replays are read-only.');
fwrite(STDOUT,"RC22 actual Commerce idempotency key payload-conflict protections passed.\n");