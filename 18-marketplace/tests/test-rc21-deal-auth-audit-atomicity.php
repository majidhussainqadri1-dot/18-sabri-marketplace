<?php
/** RC21 actual commerce: File00 risk still governs deal cancel/dispute; audit crash rolls back direct PHP mutation. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
function current_user_can($cap):bool{return false;}
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
class MKT_DB {
 public static int $transaction_calls=0;
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function now():string{return '2026-10-22 01:00:00';}
 public static function settings():array{return ['deal_transitions_enabled'=>1];}
 public static function transaction(callable $callback){
  global $wpdb;
  self::$transaction_calls++;
  $snapshot=$wpdb->deal;
  try {
   $result=$callback();
   if(is_wp_error($result)){ $wpdb->deal=$snapshot; return $result; }
   return $result;
  }catch(Throwable $e){$wpdb->deal=$snapshot;throw $e;}
 }
}
class MKT_Auth {
 public static bool $denied=true;
 public static int $checks=0;
 public static function deal_participant(array $row,int $user):bool{return in_array($user,[(int)$row['buyer_user_id'],(int)$row['seller_user_id']],true);}
 public static function can(string $cap,array $context=[]):bool|WP_Error {
  self::$checks++;
  if(self::$denied)return new WP_Error('mkt_file00_marketplace_scope_denied','File00 does not permit this action',['status'=>403]);
  return true;
 }
}
class MKT_State_Machines {public static function assert($kind,$from,$to){return true;}}
class MKT_Audit {
 public static function record(...$args):string{throw new RuntimeException('Injected mandatory audit failure');}
 public static function trace_id():string{return 'rc21-audit-crash';}
}
class MKT_Events {public static function enqueue(...$args):string{return 'event';}}
class DealAuditDB {
 public array $deal=['id'=>8,'public_id'=>'deal-a','buyer_user_id'=>41,'seller_user_id'=>2,'version'=>1,'status'=>'accepted',
 'buyer_completion_confirmed_at'=>null,'seller_completion_confirmed_at'=>null];
 public int $updates=0;
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function get_row(string $sql,$mode):?array {return $this->deal;}
 public function update(string $table,array $values,array $where):int{
  $this->updates++;$this->deal=array_merge($this->deal,$values);return 1;
 }
}
$wpdb=new DealAuditDB();
require dirname(__DIR__).'/includes/class-mkt-commerce.php';
foreach(['cancelled','disputed'] as $state) {
 MKT_Auth::$denied=true;
 $before=$wpdb->deal;
 $result=MKT_Commerce::transition_deal('deal-a',$state,[],41,1);
 assert_true(is_wp_error($result),'File00 denial prevents direct deal transition to '.$state);
 assert_same('mkt_file00_marketplace_scope_denied',$result->get_error_code(),'Deal '.$state.' retains true owner authorization denial.');
 assert_same($before,$wpdb->deal,'Deal denied '.$state.' before any DB mutation.');
}
assert_same(0,$wpdb->updates,'No deal status was written while File00 disallowed operations.');
assert_same(2,MKT_Auth::$checks,'Both cancel and dispute invoke current File00 permission.');
MKT_Auth::$denied=false;
$before=$wpdb->deal;
$result=MKT_Commerce::transition_deal('deal-a','cancelled',[],41,1);
assert_true(is_wp_error($result),'Injected audit crash makes direct deal mutation fail.');
assert_same('mkt_deal_atomic_failed',$result->get_error_code(),'Canonical owner returns a safe atomic error.');
assert_same($before,$wpdb->deal,'Deal rollback restores the original status and version after audit crash.');
assert_same(1,$wpdb->updates,'Authorized attempt exercised real deal owner state update before injected audit failure.');
assert_same(3,MKT_DB::$transaction_calls,'All three direct deal transitions used owner database transactions.');
fwrite(STDOUT,"RC21 File00 deal cancel/dispute and direct deal audit rollback verified.\n");