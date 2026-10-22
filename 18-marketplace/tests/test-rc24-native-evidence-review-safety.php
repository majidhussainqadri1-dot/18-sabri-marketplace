<?php
/** RC24 actual governance owner: guarded evidence review and rejection cannot silently leave a medicine listing live. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
function get_current_user_id():int{return 23;}
function wp_kses_post($v):string{return (string)$v;}
function wp_strip_all_tags($v):string{return strip_tags((string)$v);}
class MKT_Auth {public static function can($cap,$context=[]):bool{return true;}}
class MKT_DB {
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function now():string{return '2026-10-22 09:00:00';}
 public static function transaction(callable $cb){
  global $wpdb;
  $before=[$wpdb->listing,$wpdb->evidence,$wpdb->events,$wpdb->audits];
  try {
   $result=$cb();
   if(is_wp_error($result))[$wpdb->listing,$wpdb->evidence,$wpdb->events,$wpdb->audits]=$before;
   return $result;
  }catch(Throwable $e){[$wpdb->listing,$wpdb->evidence,$wpdb->events,$wpdb->audits]=$before;throw $e;}
 }
}
class MKT_Listings {
 public static function get(string $pid,bool $private=false):?array {
  global $wpdb;return $wpdb->pre_listing ?? $wpdb->listing;
 }
}
class MKT_Audit {
 public static function trace_id():string{return 'rc24-review';}
 public static function record(...$args):void{global $wpdb;$wpdb->audits[]=$args;}
}
class MKT_Events {
 public static function enqueue(...$args):void{global $wpdb;$wpdb->events[]=$args;}
}
class ReviewDB {
 public string $prefix='wp_';
 public string $last_error='';
 public array $listing;
 public array $evidence;
 public ?array $pre_listing=null;
 public array $events=[];
 public array $audits=[];
 public int $writes=0;
 public bool $pause_fails=false;
 public function __construct(){$this->listing=[
  'id'=>6,'public_id'=>'listing-01','created_by'=>42,'seller_user_id'=>42,
  'status'=>'active','version'=>5,'category'=>'homeopathic_medicines',
 ];$this->evidence=[
  'id'=>15,'listing_public_id'=>'listing-01','status'=>'pending','version'=>2,
  'evidence_json'=>'{"manufacturer":"Example"}'
 ];}
 public function prepare(string $sql,...$args):string{
  return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);
 }
 public function esc_like(string $s):string{return $s;}
 public function get_var(string $sql):string{return 'wp_mkt_listing_evidence';}
 public function get_row(string $sql,$kind):?array {
  if(str_contains($sql,'wp_mkt_listings'))return $this->listing;
  if(str_contains($sql,'wp_mkt_listing_evidence'))return $this->evidence;
  return null;
 }
 public function update(string $table,array $values,array $where):int|false{
  $this->writes++;
  if($table==='wp_mkt_listings') {
   if($this->pause_fails)return false;
   $this->listing=array_merge($this->listing,$values);return 1;
  }
  if($table==='wp_mkt_listing_evidence') {
   $this->evidence=array_merge($this->evidence,$values);return 1;
  }
  throw new RuntimeException('Unexpected table');
 }
}
$wpdb=new ReviewDB();
require dirname(__DIR__).'/includes/class-mkt-governance.php';
$input=['decision'=>'rejected','expected_version'=>2,'note'=>'Missing medicine batch evidence'];
$before=$wpdb->evidence;
$wpdb->pre_listing=array_merge($wpdb->listing,['created_by'=>23,'seller_user_id'=>23]);
$own=MKT_Governance::review_evidence('listing-01',$input,23);
assert_true(is_wp_error($own),'Seller-moderator may not self-review evidence.');
assert_same('mkt_self_review_forbidden',$own->get_error_code(),'Self-review has explicit policy rejection.');
assert_same(0,$wpdb->writes,'Self-review denied before any write.');
$wpdb->pre_listing=null;
$wpdb->listing['seller_user_id']=23;
$locked=MKT_Governance::review_evidence('listing-01',$input,23);
assert_same('mkt_self_review_forbidden',$locked->get_error_code(),'Locked listing revalidates changed seller before mutation.');
assert_same(0,$wpdb->writes,'Locked self-review does not update evidence.');
$wpdb->listing['seller_user_id']=42;
$good=MKT_Governance::review_evidence('listing-01',$input,23);
assert_true(is_array($good),'Independent authorized evidence review succeeds.');
assert_same('rejected',$wpdb->evidence['status'],'Evidence stored as rejected.');
assert_same('paused',$wpdb->listing['status'],'Active medical listing paused in same transaction.');
assert_same(2,count($wpdb->events),'Evidence and listing pause outbox facts both emitted.');
assert_same(1,count($wpdb->audits),'Audited evidence change has a durable owner fact.');
assert_same(true,$wpdb->audits[0][3]['listing_paused'],'Audit reflects mandatory safety pause.');
$wpdb=new ReviewDB();
$wpdb->pause_fails=true;
$failure=MKT_Governance::review_evidence('listing-01',$input,23);
assert_true(is_wp_error($failure),'Listing pause SQL error aborts full review.');
assert_same('mkt_listing_evidence_pause_conflict',$failure->get_error_code(),'Listing pause conflict does not become approved/rejected success.');
assert_same('pending',$wpdb->evidence['status'],'Evidence rejected state rolled back with failed listing pause.');
assert_same('active',$wpdb->listing['status'],'Listing remains intact, review needs retry.');
assert_same(0,count($wpdb->events),'Failed owner transaction emits no outbox facts.');
fwrite(STDOUT,"RC24 native medical evidence self-review, locked recheck and atomic pause passed.\n");