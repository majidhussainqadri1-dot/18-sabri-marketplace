<?php
/** Seller de-eligibility cannot be committed while public active listings remain for SQL pause failure. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
class SellerCronDB {
 public string $last_error='';
 public bool $fail_pause=false;
 public array $seller=['id'=>5,'user_id'=>41,'public_id'=>'seller-5','status'=>'approved','version'=>1];
 public array $sql=[];
 public function prepare(string $q,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$q),$args);}
 public function get_results(string $sql,$type):array{return [$this->seller];}
 public function update(string $table,array $updates,array $where):int {
  if(!str_contains($table,'sellers'))return 0;
  if((int)$where['version']!==$this->seller['version'])return 0;
  $this->seller=array_merge($this->seller,$updates);return 1;
 }
 public function query(string $sql):int|false {
  $this->sql[]=$sql;
  if(str_contains($sql,'UPDATE wp_mkt_listings')&&$this->fail_pause)return false;
  return 1;
 }
}
class MKT_DB {
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function now():string{return '2026-10-21 00:00:00';}
 public static function transaction(callable $fn) {
  $db=$GLOBALS['wpdb'];$before=$db->seller;
  try {return $fn();}catch(Throwable $e){$db->seller=$before;throw $e;}
 }
}
class MKT_Auth {
 public static function seller_eligibility(int $id):array{
  return ['eligible'=>false,'reasons'=>['account_unapproved'],'assertions'=>['version'=>'v3']];
 }
}
class MKT_Events {
 public static array $sent=[];
 public static function enqueue(...$args):string{self::$sent[]=$args;return 'event-1';}
}
class MKT_Audit {
 public static array $failures=[];
 public static function record(...$args):void{self::$failures[]=$args;}
}
$wpdb=new SellerCronDB();
require dirname(__DIR__).'/includes/class-mkt-maintenance.php';
$cron=new ReflectionMethod('MKT_Maintenance','reconcile_sellers');
$cron->setAccessible(true);
$wpdb->fail_pause=true;
$cron->invoke(null,50);
assert_same('approved',$wpdb->seller['status'],'SQL failure while pausing public listings rolls back seller suspension.');
assert_same(1,$wpdb->seller['version'],'Version is unchanged after rollback.');
assert_same([],MKT_Events::$sent,'Failed pause has no successful seller-status outbox handoff.');
assert_same(1,count(MKT_Audit::$failures),'Atomic failure is recorded for investigation.');
$wpdb->fail_pause=false;
$cron->invoke(null,50);
assert_same('suspended',$wpdb->seller['status'],'Healthy atomic maintenance suspends ineligible seller.');
assert_same(2,$wpdb->seller['version'],'A successful seller suspension advances version once.');
assert_same(1,count(MKT_Events::$sent),'Status change event enqueued only on successful local owner mutation.');
fwrite(STDOUT,"File18 seller-status atomic listing pause failure regression complete.\n");
