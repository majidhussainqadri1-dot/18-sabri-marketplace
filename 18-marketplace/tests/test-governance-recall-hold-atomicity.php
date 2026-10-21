<?php
/** Actual governance class; simulated database insert/audit errors and rollback. */
require __DIR__ . '/bootstrap.php';
define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function wp_kses_post($v):string{return strip_tags((string)$v);}
function wp_json_encode($v):string{return json_encode($v);}
function mb_substr($v,$start,$len=null):string{return substr((string)$v,$start,$len);}
function get_current_user_id():int{return 23;}
class MKT_Auth {public static function can($cap,array $ctx=[]){return true;}}
class MKT_Listings {public static function get(string $id,bool $private=false):?array{return $id==='listing_99'?['public_id'=>$id,'seller_user_id'=>51]:null;}}
class MKT_DB {
 private static int $counter=0;
 public static function uuid():string{return '00000000-0000-4000-8000-'.str_pad((string) (++self::$counter),12,'0',STR_PAD_LEFT);}
 public static function now():string{return '2026-10-21 09:00:00';}
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function transaction(callable $callback) {
  global $wpdb;
  $old=[$wpdb->recalls,$wpdb->holds];$wpdb->ops[]='BEGIN';
  try {
   $result=$callback();
   if(is_wp_error($result)||$result===false){[$wpdb->recalls,$wpdb->holds]=$old;$wpdb->ops[]='ROLLBACK';return $result;}
   $wpdb->ops[]='COMMIT';return $result;
  }catch(Throwable $e){[$wpdb->recalls,$wpdb->holds]=$old;$wpdb->ops[]='ROLLBACK';throw $e;}
 }
}
class MKT_Audit {
 public static bool $fail=false;
 public static function record(...$args):string {
  global $wpdb;$wpdb->ops[]='AUDIT';
  if(self::$fail)throw new RuntimeException('Injected audit persistence failure');
  return 'ok';
 }
}
class FakeGovDB {
 public string $prefix='wp_';
 public array $ops=[];
 public array $recalls=[];
 public array $holds=[];
 public bool $insert_fail=false;
 public bool $update_fail=false;
 public function prepare(string $s,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$s),$args);}
 public function get_var(string $sql) {
  if(str_contains($sql,'SELECT public_id FROM wp_mkt_retention_holds'))return null;
  if(str_contains($sql,'SELECT COUNT(*)'))return 1;
  return null;
 }
 public function insert(string $table,array $data):int|false {
  $this->ops[]='INSERT';
  if($this->insert_fail)return false;
  if(str_contains($table,'recalls')){$this->recalls[$data['public_id']]=$data;return 1;}
  if(str_contains($table,'retention_holds')){$this->holds[$data['public_id']]=$data+['id'=>17];return 1;}
  return false;
 }
 public function get_row(string $sql,$array) {
  if(str_contains($sql,'wp_mkt_recalls')){foreach($this->recalls as $r)if(str_contains($sql,$r['public_id']))return $r;return null;}
  if(str_contains($sql,'wp_mkt_retention_holds')){foreach($this->holds as $r)if(str_contains($sql,$r['public_id']))return $r;return null;}
  return null;
 }
 public function update(string $table,array $changes,array $where):int|false {
  $this->ops[]='UPDATE';
  if($this->update_fail)return false;
  foreach($this->holds as &$r) {
   if(($r['id']??null)===$where['id'] && $r['status']===$where['status']){$r=array_merge($r,$changes);return 1;}
  }
  return 0;
 }
}
$wpdb=new FakeGovDB();
require dirname(__DIR__).'/includes/class-mkt-governance.php';
$recall=['listing_public_id'=>'listing_99','reason'=>'Confirmed unsafe batch'];
MKT_Audit::$fail=true;
$r=MKT_Governance::create_recall($recall,23);
assert_true(is_wp_error($r),'Recall audit failure returns an error.');
assert_same(0,count($wpdb->recalls),'Failed recall audit must rollback recall row.');
assert_same(['BEGIN','INSERT','AUDIT','ROLLBACK'],$wpdb->ops,'Recall insert/audit rollback occurs in one transaction.');
MKT_Audit::$fail=false;$wpdb->insert_fail=true;$wpdb->ops=[];
$r=MKT_Governance::create_recall($recall,23);
assert_true(is_wp_error($r),'Recall insert failure is not successful.');
assert_same(0,count($wpdb->recalls),'No recall persisted on insert error.');
$wpdb->insert_fail=false;$wpdb->ops=[];
$r=MKT_Governance::create_recall($recall,23);
assert_true(is_array($r),'Successful recall returns verified canonical record.');
assert_same(['BEGIN','INSERT','AUDIT','COMMIT'],$wpdb->ops,'Recall create commits only with matching audit.');
$hold=['object_type'=>'listing','object_public_id'=>'listing_99','reason'=>'Court order','authority_reference'=>'Court-123','expires_at'=>gmdate('Y-m-d H:i:s',time()+86400)];
MKT_Audit::$fail=true;$wpdb->ops=[];
$bad=MKT_Governance::create_hold($hold,23);
assert_true(is_wp_error($bad),'Hold audit error returns failure.');
assert_same(0,count($wpdb->holds),'Failed legal hold audit does not leave an unaudited hold.');
assert_same(['BEGIN','INSERT','AUDIT','ROLLBACK'],$wpdb->ops,'Legal hold creation rolls back after audit error.');
MKT_Audit::$fail=false;$wpdb->ops=[];
$ok=MKT_Governance::create_hold($hold,23);
assert_true(is_array($ok),'Valid active hold succeeds.');
assert_same(['BEGIN','INSERT','AUDIT','COMMIT'],$wpdb->ops,'Hold is committed atomically with audit.');
$holdId=$ok['public_id'];
MKT_Audit::$fail=true;$wpdb->ops=[];
$no=MKT_Governance::release_hold($holdId,['reason'=>'Order superseded'],23);
assert_true(is_wp_error($no),'Legal hold release audit failure returns error.');
assert_same('active',$wpdb->holds[$holdId]['status'],'Audit outage must never deactivate the legal hold.');
assert_same(['BEGIN','UPDATE','AUDIT','ROLLBACK'],$wpdb->ops,'Release failure rolls back hold state.');
MKT_Audit::$fail=false;$wpdb->ops=[];
$yes=MKT_Governance::release_hold($holdId,['reason'=>'Order superseded'],23);
assert_same('released',$yes['status'],'Audited legal hold release succeeds.');
assert_same('released',$wpdb->holds[$holdId]['status'],'Canonical hold status updated.');
assert_same(['BEGIN','UPDATE','AUDIT','COMMIT'],$wpdb->ops,'Audited hold release commits.');
fwrite(STDOUT,"File18 governance recall and legal-hold atomicity fault injection complete.\n");
