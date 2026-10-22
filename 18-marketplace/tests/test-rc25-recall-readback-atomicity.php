<?php
/** RC25 real recall owner: a missing post-update canonical readback cannot commit recall, audit or outbox. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function get_current_user_id():int{return 23;}
function sanitize_text_field($x):string{return trim(strip_tags((string)$x));}
function wp_json_encode($x):string{return json_encode($x);}
class MKT_Auth {public static function can($cap,array $ctx=[]):bool{return true;}}
class MKT_Listings {public static function url(array $listing):string{return '/marketplace/listing-one/';}}
class MKT_DB {
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function now():string{return '2026-10-22 10:00:00';}
 public static function transaction(callable $fn){
  global $wpdb;
  $previous=[$wpdb->recall,$wpdb->listing,$wpdb->events,$wpdb->audits];
  try {
   $result=$fn();
   if(is_wp_error($result))[$wpdb->recall,$wpdb->listing,$wpdb->events,$wpdb->audits]=$previous;
   return $result;
  }catch(Throwable $e){[$wpdb->recall,$wpdb->listing,$wpdb->events,$wpdb->audits]=$previous;throw $e;}
 }
}
class MKT_Audit {
 public static function trace_id():string{return 'rc25-recall';}
 public static function record(...$args):string{global $wpdb;$wpdb->audits[]=$args;return 'audit';}
}
class MKT_Events {
 public static function enqueue(...$args):string{global $wpdb;$wpdb->events[]=$args;return 'event';}
}
class RecallDB {
 public string $prefix='wp_';
 public array $recall;
 public array $listing;
 public array $events=[];
 public array $audits=[];
 public bool $readback_fails=false;
 public int $updates=0;
 public function __construct() {
  $this->recall=['id'=>5,'public_id'=>'recall-one','listing_public_id'=>'listing-one',
    'status'=>'draft','version'=>1,'batch_ref'=>'B-10','reason'=>'Unsafe batch','regulator_reference'=>'ABC'];
  $this->listing=['id'=>8,'public_id'=>'listing-one','created_by'=>41,'status'=>'paused','version'=>3];
 }
 public function prepare(string $sql,...$args):string{
  return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);
 }
 public function get_row(string $sql,$mode):?array{
  if(str_contains($sql,'wp_mkt_recalls')) {
   if($this->readback_fails && $this->updates>0)return null;
   return $this->recall;
  }
  if(str_contains($sql,'wp_mkt_listings'))return $this->listing;
  return null;
 }
 public function get_col(string $sql):array{return [];}
 public function update(string $table,array $data,array $where):int{
  $this->updates++;
  if($table==='wp_mkt_recalls') {$this->recall=array_merge($this->recall,$data);return 1;}
  if($table==='wp_mkt_listings') {$this->listing=array_merge($this->listing,$data);return 1;}
  return 0;
 }
}
$wpdb=new RecallDB();
require dirname(__DIR__).'/includes/class-mkt-governance.php';
$approved=MKT_Governance::transition_recall('recall-one',['to'=>'active','expected_version'=>1],23);
assert_true(is_array($approved),'Real recall owner can activate a draft recall.');
assert_same('active',$wpdb->recall['status'],'Positive recall transition persists.');
assert_same(2,$wpdb->recall['version'],'Positive recall transition increments version.');
assert_same(1,count($wpdb->events),'Positive recall transition queues one event.');
assert_same(1,count($wpdb->audits),'Positive recall transition records one audit.');
$wpdb=new RecallDB();
$wpdb->readback_fails=true;
$failed=MKT_Governance::transition_recall('recall-one',['to'=>'active','expected_version'=>1],23);
assert_true(is_wp_error($failed),'Recall readback outage is explicit error.');
assert_same('mkt_recall_readback_failed',$failed->get_error_code(),'Missing recall readback blocks owner COMMIT.');
assert_same('draft',$wpdb->recall['status'],'Recall state reverted after readback failure.');
assert_same(1,$wpdb->recall['version'],'Recall version reverted after readback failure.');
assert_same([],$wpdb->events,'No outbox success fact persists on readback outage.');
assert_same([],$wpdb->audits,'No audit success persists on readback outage.');
fwrite(STDOUT,"RC25 native recall final-readback rollback verified.\n");