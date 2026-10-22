<?php
/** RC25 real Governance save_evidence owner: lock ordering, fresh owner/state/category, explicit evidence CAS. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
function get_current_user_id():int{return 41;}
function sanitize_text_field($x):string{return trim(strip_tags((string)$x));}
function wp_json_encode($x):string{return json_encode($x);}
class MKT_Auth {
 public static function can($cap,$ctx=[]):bool{return true;}
 public static function own_listing(array $listing,int $actor):bool{return $actor===41 && (int)$listing['seller_id']===6;}
}
class MKT_DB {
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function now():string{return '2026-10-22 09:00:00';}
 public static function transaction(callable $fn){
  global $wpdb;
  $before=[$wpdb->listing,$wpdb->evidence,$wpdb->events,$wpdb->audits];
  $wpdb->in_tx=true;
  try{
   $result=$fn();
   if(is_wp_error($result))[$wpdb->listing,$wpdb->evidence,$wpdb->events,$wpdb->audits]=$before;
   return $result;
  }catch(Throwable $e){[$wpdb->listing,$wpdb->evidence,$wpdb->events,$wpdb->audits]=$before;throw $e;}
  finally{$wpdb->in_tx=false;}
 }
}
class MKT_Listings {
 public static function get(string $pid,bool $private=false):?array {
  global $wpdb;return $wpdb->pre_listing ?? $wpdb->listing;
 }
}
class MKT_Audit {
 public static function trace_id():string{return 'rc25-lock';
 }
 public static function record(...$args):void{global $wpdb;$wpdb->audits[]=$args;}
}
class MKT_Events {
 public static function enqueue(...$args):void{global $wpdb;$wpdb->events[]=$args;}
}
class SaveEvidenceDB {
 public string $prefix='wp_';
 public string $last_error='';
 public bool $in_tx=false;
 public array $reads=[];
 public array $listing;
 public array $evidence;
 public ?array $pre_listing=null;
 public array $events=[];
 public array $audits=[];
 public int $writes=0;
 public bool $readback_fails=false;
 public function __construct(){
  $this->listing=['id'=>8,'seller_id'=>6,'seller_user_id'=>41,'public_id'=>'listing-one',
   'status'=>'draft','version'=>5,'category'=>'homeopathic_medicines'];
  $this->evidence=['id'=>71,'listing_public_id'=>'listing-one','status'=>'approved',
   'version'=>2,'evidence_json'=>'{}'];
 }
 public function prepare(string $sql,...$args):string{
  return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);
 }
 public function esc_like(string $v):string{return $v;}
 public function get_var(string $sql):string{return 'wp_mkt_listing_evidence';}
 public function get_row(string $sql,$mode):?array{
  if($this->in_tx && str_contains($sql,'FOR UPDATE')){
    $this->reads[]=str_contains($sql,'wp_mkt_listings')?'listing':(str_contains($sql,'wp_mkt_listing_evidence')?'evidence':'unknown');
  }
  if(str_contains($sql,'wp_mkt_listings'))return $this->listing;
  if($this->readback_fails && $this->writes>0
     && str_contains($sql,'wp_mkt_listing_evidence') && !str_contains($sql,'FOR UPDATE'))return null;
  if(str_contains($sql,'wp_mkt_listing_evidence'))return $this->evidence;
  return null;
 }
 public function update(string $table,array $data,array $where):int{
  $this->writes++;
  if($table==='wp_mkt_listings'){$this->listing=array_merge($this->listing,$data);return 1;}
  if($table==='wp_mkt_listing_evidence'){$this->evidence=array_merge($this->evidence,$data);return 1;}
  throw new RuntimeException('Unexpected update table');
 }
}
$valid=[
 'ingredients'=>['Magnesia phosphorica'],'manufacturer'=>'Example producer',
 'license_or_registration'=>'LAB-1','batch_number'=>'B-1','expiry_date'=>'2027-10-22'
];
$payload=['expected_listing_version'=>5,'expected_evidence_version'=>2,'evidence'=>$valid];
$wpdb=new SaveEvidenceDB();
require dirname(__DIR__).'/includes/class-mkt-governance.php';
$result=MKT_Governance::save_evidence('listing-one',$payload,41);
assert_true(is_array($result),'Fresh seller can update draft listing evidence.');
assert_same(['listing','evidence'],$wpdb->reads,'Saving and approving evidence use the SAME listing-then-evidence lock order.');
assert_same(6,$wpdb->listing['version'],'Listing optimistic version moves exactly once.');
assert_same(3,$wpdb->evidence['version'],'Evidence optimistic version moves exactly once.');
assert_same('pending',$wpdb->evidence['status'],'Changed evidence returns to human review.');
assert_same(1,count($wpdb->events),'One canonical update outbox fact written.');
assert_same(1,count($wpdb->audits),'One canonical evidence audit fact written.');
$wpdb=new SaveEvidenceDB();
$missing=$payload;unset($missing['expected_evidence_version']);
$result=MKT_Governance::save_evidence('listing-one',$missing,41);
assert_same('mkt_stale_evidence_version',$result->get_error_code(),'Missing expected evidence version cannot silently overwrite approved evidence.');
assert_same(0,$wpdb->writes,'Missing CAS proof cannot write.');
$wpdb=new SaveEvidenceDB();
$stale=$payload;$stale['expected_evidence_version']=1;
$result=MKT_Governance::save_evidence('listing-one',$stale,41);
assert_same('mkt_stale_evidence_version',$result->get_error_code(),'Outdated evidence CAS rejected.');
assert_same(0,$wpdb->writes,'Outdated CAS leaves evidence unchanged.');
$wpdb=new SaveEvidenceDB();
$wpdb->pre_listing=$wpdb->listing;
$wpdb->listing['status']='active'; // concurrent publish without trusting any non-locking snapshot
$result=MKT_Governance::save_evidence('listing-one',$payload,41);
assert_same('mkt_evidence_state_locked',$result->get_error_code(),'Fresh active listing cannot be modified as if draft.');
assert_same(0,$wpdb->writes,'Concurrent publication blocks writes.');
$wpdb=new SaveEvidenceDB();
$wpdb->pre_listing=$wpdb->listing;
$wpdb->listing['seller_user_id']=77;
$result=MKT_Governance::save_evidence('listing-one',$payload,41);
assert_same('mkt_listing_not_found',$result->get_error_code(),'Concurrent owner reassignment must deny prior seller.');
assert_same(0,$wpdb->writes,'Concurrent ownership mutation prevents stale seller write.');
$wpdb=new SaveEvidenceDB();
$wpdb->pre_listing=$wpdb->listing;
$wpdb->pre_listing['category']='books';
$wpdb->listing['category']='homeopathic_medicines';
$invalid=$payload;$invalid['evidence']=[];
$result=MKT_Governance::save_evidence('listing-one',$invalid,41);
assert_same('mkt_evidence_invalid',$result->get_error_code(),'Fresh regulated category is validated under listing lock.');
assert_same(0,$wpdb->writes,'Category safety failure remains read-only.');
$wpdb=new SaveEvidenceDB();
$wpdb->readback_fails=true;
$missing=MKT_Governance::save_evidence('listing-one',$payload,41);
assert_true(is_wp_error($missing),'Save must fail safely when post-commit-style evidence readback is unavailable.');
assert_same('mkt_evidence_readback_failed',$missing->get_error_code(),'Readback failure is an explicit rollback trigger.');
assert_same(5,$wpdb->listing['version'],'Listing version rolled back on missing evidence readback.');
assert_same(2,$wpdb->evidence['version'],'Evidence version rolled back on missing evidence readback.');
assert_same('approved',$wpdb->evidence['status'],'Previous review state preserved on readback failure.');
assert_same([],$wpdb->events,'Outbox messages rolled back if evidence readback is not confirmed.');
assert_same([],$wpdb->audits,'Audit fact rolled back if evidence command could not finish.');
fwrite(STDOUT,"RC25 actual governance evidence save lock-order/owner/state/category/version regressions passed.\n");