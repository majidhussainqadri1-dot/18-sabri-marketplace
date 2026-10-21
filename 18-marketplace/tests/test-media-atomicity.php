<?php
/** Media owner transaction/compensation fault simulation, real File18 methods. */
require __DIR__.'/bootstrap.php';
define('ARRAY_A','ARRAY_A');
function sanitize_text_field($v):string{return strip_tags((string)$v);}
function wp_json_encode($v):string{return json_encode($v);}
function do_action(string $tag,...$args):void {$GLOBALS['wpdb']->ops[]='RELEASE';}
class MKT_DB {
 private static int $id=0;
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function now():string{return '2026-10-21 00:00:00';}
 public static function uuid():string{return sprintf('00000000-0000-4000-8000-%012d',++self::$id);}
 public static function settings():array{return ['max_media_refs'=>10];}
 public static function transaction(callable $fn) {
  global $wpdb;
  $snapshot=$wpdb->media;$wpdb->ops[]='START';
  try {
   $result=$fn();
   if(is_wp_error($result)||$result===false){$wpdb->media=$snapshot;$wpdb->ops[]='ROLLBACK';return $result;}
   $wpdb->ops[]='COMMIT';return $result;
  }catch(Throwable $e){$wpdb->media=$snapshot;$wpdb->ops[]='ROLLBACK';throw $e;}
 }
}
class MKT_Auth {
 public static function can($action,$context):bool{return true;}
 public static function own_listing($listing,$user_id):bool{return $user_id===41;}
}
class MKT_Integrations {
 public static function media_reference(array $v,int $actor):array {
 return ['public_id'=>'file10-media-123','kind'=>'image','url'=>'https://sabri.example/asset',
 'thumbnail_url'=>'https://sabri.example/thumb','rights_status'=>'approved','scan_status'=>'clean','metadata'=>[]];
 }
}
class MKT_Audit {
 public static bool $fail=false;
 public static function record(...$args):void{
  $GLOBALS['wpdb']->ops[]='AUDIT';
  if(self::$fail)throw new RuntimeException('Simulated audit storage failure');
 }
}
class FakeMediaDB {
 public array $ops=[];
 public ?array $media=null;
 public bool $insert_fail=false;
 public string $last_error='';
 public int $count_calls=0;
 public int $fail_count_at=0;
 public bool $race_limit=false;
 public string $locked_status='draft';
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function get_row(string $sql,$type) {
  if(str_contains($sql,'INNER JOIN'))return ['id'=>11,'public_id'=>'listing-0001','status'=>'draft','seller_user_id'=>41];
  if(str_contains($sql,'FOR UPDATE'))return ['id'=>11,'status'=>$this->locked_status];
  return $this->media;
 }
 public function get_var($query){
  ++$this->count_calls;
  if($this->count_calls===$this->fail_count_at){$this->last_error='Injected media COUNT outage';return null;}
  if($this->race_limit&&$this->count_calls===2)return 10;
  return $this->media&&($this->media['status']??'')==='active'?1:0;
 }
 public function insert($table,$data):int|false {
  $this->ops[]='INSERT';
  if($this->insert_fail)return false;
  $this->media=$data+['id'=>51];return 1;
 }
 public function update($table,$data,$where):int {
  $this->ops[]='UPDATE';
  if(!$this->media||$this->media['status']!=='active')return 0;
  $this->media=array_merge($this->media,$data);return 1;
 }
}
$wpdb=new FakeMediaDB();
require dirname(__DIR__).'/includes/class-mkt-listings.php';
$media=MKT_Listings::attach_media('listing-0001',['alt_text'=>'Book cover'],41);
assert_true(is_array($media),'File10 media reference can attach successfully.');
assert_same(['START','INSERT','AUDIT','COMMIT'],$wpdb->ops,'Media DB insert and audit commit together.');
$wpdb->media=null;$wpdb->ops=[];MKT_Audit::$fail=true;
$failure=MKT_Listings::attach_media('listing-0001',['alt_text'=>'Cover'],41);
assert_true(is_wp_error($failure),'Audit failure fails the media attach API.');
assert_same(null,$wpdb->media,'Failed media audit rolls back owner media reference.');
assert_same(['START','INSERT','AUDIT','ROLLBACK','RELEASE'],$wpdb->ops,'File10 compensation is invoked only after rollback.');
$wpdb->media=['id'=>51,'listing_id'=>11,'public_id'=>'media-link','provider_public_id'=>'file10-media-123','status'=>'active'];$wpdb->ops=[];
$fail_remove=MKT_Listings::remove_media('listing-0001','media-link',41);
assert_true(is_wp_error($fail_remove),'Audit outage cannot report media removal success.');
assert_same('active',$wpdb->media['status'],'Audit failure restores active media row.');
assert_same(['START','UPDATE','AUDIT','ROLLBACK'],$wpdb->ops,'Failed removal does not release File10 canonical media.');
$wpdb->ops=[];MKT_Audit::$fail=false;
$success=MKT_Listings::remove_media('listing-0001','media-link',41);
assert_same(true,$success,'Audited removal succeeds.');
assert_same('removed',$wpdb->media['status'],'Media owner record is removed.');
assert_same(['START','UPDATE','AUDIT','COMMIT','RELEASE'],$wpdb->ops,'File10 release is post-commit, never before immutable audit.');
$wpdb->media=null;$wpdb->ops=[];$wpdb->count_calls=0;$wpdb->fail_count_at=1;
$preflight=MKT_Listings::attach_media('listing-0001',['alt_text'=>'Cover'],41);
assert_same('mkt_media_count_unavailable',$preflight->get_error_code(),'Media COUNT outage before File10 allocation must fail closed.');
assert_same([], $wpdb->ops,'Preflight failure creates neither marketplace media ref nor File10 allocation.');
$wpdb->fail_count_at=2;$wpdb->count_calls=0;$wpdb->last_error='';$wpdb->ops=[];
$inlock=MKT_Listings::attach_media('listing-0001',['alt_text'=>'Cover'],41);
assert_same('mkt_media_count_unavailable',$inlock->get_error_code(),'Media COUNT failure under listing row lock must block write.');
assert_same(['START','ROLLBACK','RELEASE'],$wpdb->ops,'In-lock SQL failure rolls back and compensates File10 reference.');
$wpdb->fail_count_at=0;$wpdb->count_calls=0;$wpdb->last_error='';$wpdb->ops=[];$wpdb->race_limit=true;
$race=MKT_Listings::attach_media('listing-0001',['alt_text'=>'Cover'],41);
assert_same('mkt_media_limit',$race->get_error_code(),'Concurrent attach after preflight must enforce max_media_refs inside canonical row lock.');
assert_same(['START','ROLLBACK','RELEASE'],$wpdb->ops,'Concurrent overflow never writes a media reference and releases File10 allocation.');
assert_same(null,$wpdb->media,'Failed concurrent insert must not leave a media ref.');
$wpdb->race_limit=false;$wpdb->ops=[];$wpdb->count_calls=0;$wpdb->locked_status='active';
$stateRace=MKT_Listings::attach_media('listing-0001',['alt_text'=>'Cover'],41);
assert_same('mkt_media_state_locked',$stateRace->get_error_code(),'Listing moved to active after preflight cannot receive a new media ref.');
assert_same(['START','ROLLBACK','RELEASE'],$wpdb->ops,'Stale listing state under canonical lock compensates File10 allocation.');
$wpdb->locked_status='draft';
fwrite(STDOUT,"File18 File10 media transaction and compensation regressions complete.\n");
