<?php
/** RC21 actual classes: direct listing, report and dispute transitions cannot leave state without mandatory audit. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
function current_user_can($cap):bool{return false;}
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function wp_kses_post($v):string{return (string)$v;}
class MKT_DB {
 public static int $transactions=0;
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function now():string{return '2026-10-22 01:00:00';}
 public static function transaction(callable $callback){
  global $wpdb;
  self::$transactions++;
  $before=$wpdb->rows;
  try {
   $result=$callback();
   if(is_wp_error($result)){$wpdb->rows=$before;return $result;}
   return $result;
  }catch(Throwable $e){$wpdb->rows=$before;throw $e;}
 }
}
class MKT_Auth {
 public static function own_listing(array $li,int $actor):bool{return $actor===41;}
 public static function can($cap,array $ctx=[]):bool|WP_Error{return true;}
}
class MKT_State_Machines {public static function assert($kind,$from,$to){return true;}}
class MKT_Audit {
 public static function record(...$args):string{throw new RuntimeException('Simulated durable audit-chain insert outage');}
 public static function trace_id():string{return 'rc21-atomic';}
}
class MKT_Events {public static function enqueue(...$args):string{return 'event';}}
class MutationCrashDB {
 public int $writes=0;
 public array $rows=[
  'listings'=>['id'=>7,'public_id'=>'listing-a','version'=>1,'status'=>'active','seller_user_id'=>41,
     'seller_id'=>9,'listing_type'=>'service','last_reviewed_at'=>null,'published_at'=>'2026-10-22 00:00:00'],
  'reports'=>['id'=>8,'public_id'=>'report-a','status'=>'submitted','version'=>1,'reporter_user_id'=>22,
     'assigned_to'=>0,'decision_code'=>'','target_type'=>'seller','target_public_id'=>'seller-id'],
  'disputes'=>['id'=>9,'public_id'=>'dispute-a','status'=>'more_info','version'=>1,'buyer_user_id'=>41,
     'seller_user_id'=>2,'assigned_to'=>0,'decision_code'=>'','evidence_refs'=>'[]','deal_public_id'=>'deal-a']
 ];
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function get_row(string $sql,$mode) {
  if(str_contains($sql,'wp_mkt_listings'))return $this->rows['listings'];
  if(str_contains($sql,'wp_mkt_reports'))return $this->rows['reports'];
  if(str_contains($sql,'wp_mkt_disputes'))return $this->rows['disputes'];
  return null;
 }
 public function update(string $table,array $values,array $where):int{
  $this->writes++;
  foreach(['listings','reports','disputes'] as $name)
    if(str_ends_with($table,$name)){$this->rows[$name]=array_merge($this->rows[$name],$values);return 1;}
  throw new RuntimeException('Unexpected table update');
 }
}
$wpdb=new MutationCrashDB();
require dirname(__DIR__).'/includes/class-mkt-listings.php';
require dirname(__DIR__).'/includes/class-mkt-moderation.php';
$before=$wpdb->rows['listings'];
$listing=MKT_Listings::transition('listing-a','paused',41,1);
assert_true(is_wp_error($listing),'Direct listing state change fails on mandatory audit outage.');
assert_same('mkt_listing_atomic_failed',$listing->get_error_code(),'Listing owner surfaces safe commit failure.');
assert_same($before,$wpdb->rows['listings'],'Listing status/version rolled back.');
$before=$wpdb->rows['reports'];
$report=MKT_Moderation::transition_report('report-a','no_action',[],5,1);
assert_true(is_wp_error($report),'Direct report moderation fails on audit outage.');
assert_same('mkt_report_transition_atomic_failed',$report->get_error_code(),'Report owner surfaces safe commit failure.');
assert_same($before,$wpdb->rows['reports'],'Report state/decision rolled back.');
$before=$wpdb->rows['disputes'];
$dispute=MKT_Moderation::transition_dispute('dispute-a','evidence',[],41,1);
assert_true(is_wp_error($dispute),'Direct participant dispute transition fails on audit outage.');
assert_same('mkt_dispute_transition_atomic_failed',$dispute->get_error_code(),'Dispute owner surfaces safe commit failure.');
assert_same($before,$wpdb->rows['disputes'],'Dispute status/version rolled back.');
assert_same(3,$wpdb->writes,'All three protected state changes reached the real update method before failed audit.');
assert_same(3,MKT_DB::$transactions,'Every direct owner transition starts or joins the atomic database boundary.');
fwrite(STDOUT,"RC21 actual listing/report/dispute owner rollback regressions verified.\n");