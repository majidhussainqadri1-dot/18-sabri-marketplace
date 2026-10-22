<?php
/** Direct File18 seller gateway fault-injection, fake transactional WPDB. */
require __DIR__.'/bootstrap.php';
function get_current_user_id():int{return 41;}
define('ARRAY_A','ARRAY_A');
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function wp_json_encode($v):string{return json_encode($v);}
function get_userdata($id){return (object)['display_name'=>'Verified seller'];}
class MKT_Auth {
 public static function seller_eligibility(int $id):array {
  return ['eligible'=>true,'assertions'=>['status'=>'active','verified'=>true,'is_minor'=>false,'guardian_verified'=>true,'risk_state'=>'clear','version'=>3],'reasons'=>[]];
 }
}
class MKT_Audit {
 public static bool $fail=false;
 public static function record(...$args):void {
  $GLOBALS['wpdb']->ops[]='AUDIT';
  if(self::$fail)throw new RuntimeException('Audit outage');
 }
}
class MKT_DB {
 private static int $counter=0;
 public static function table($name):string{return 'wp_mkt_'.$name;}
 public static function now():string{return '2026-10-21 09:00:00';}
 public static function uuid():string{return sprintf('00000000-0000-4000-8000-%012d',++self::$counter);}
 public static function transaction(callable $fn) {
  global $wpdb;
  $old=$wpdb->sellers;$wpdb->ops[]='BEGIN';
  try {$result=$fn();
    if(is_wp_error($result)||$result===false){$wpdb->sellers=$old;$wpdb->ops[]='ROLLBACK';return $result;}
    $wpdb->ops[]='COMMIT';return $result;
  }catch(Throwable $e){$wpdb->sellers=$old;$wpdb->ops[]='ROLLBACK';throw $e;}
 }
}
class FakeSellerDB {
 public int $insert_id=0;
 public array $sellers=[];
 public array $ops=[];
 public bool $insert_fail=false;
 public bool $update_fail=false;
 public bool $missing_readback=false;
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function get_row($sql,$type) {
  if($this->missing_readback)return null;
  foreach($this->sellers as $row) {
   if(preg_match('/WHERE user_id=(\d+)/',$sql,$m)&&(int)$m[1]===(int)$row['user_id'])return $row;
   if(preg_match('/WHERE id=(\d+)/',$sql,$m)&&(int)$m[1]===(int)$row['id'])return $row;
  }
  return null;
 }
 public function insert(string $table,array $data):int|false {
  $this->ops[]='INSERT';
  if($this->insert_fail)return false;
  $this->insert_id++;
  $this->sellers[$this->insert_id]=['id'=>$this->insert_id]+$data;return 1;
 }
 public function update(string $table,array $data,array $where):int|false {
  $this->ops[]='UPDATE';
  if($this->update_fail)return false;
  $id=(int)$where['id'];
  if(!isset($this->sellers[$id])||(int)$this->sellers[$id]['version']!==(int)$where['version'])return 0;
  $this->sellers[$id]=array_merge($this->sellers[$id],$data);return 1;
 }
}
$wpdb=new FakeSellerDB();
require dirname(__DIR__).'/includes/class-mkt-listings.php';
$wpdb->insert_fail=true;$wpdb->insert_id=99;
$r=MKT_Listings::ensure_seller(41);
assert_true(is_wp_error($r),'Failed insert cannot reuse a prior stale insert ID.');
assert_same(0,count($wpdb->sellers),'Seller creation failure leaves no row.');
$wpdb->insert_fail=false;MKT_Audit::$fail=true;$wpdb->ops=[];
$r=MKT_Listings::ensure_seller(41);
assert_true(is_wp_error($r),'Failed mandatory seller audit is reported.');
assert_same(0,count($wpdb->sellers),'Failed seller audit rolls back identity projection.');
assert_same(['BEGIN','INSERT','AUDIT','ROLLBACK'],$wpdb->ops,'Seller insert and audit share transaction.');
MKT_Audit::$fail=false;$wpdb->ops=[];
$r=MKT_Listings::ensure_seller(41);
assert_true(is_array($r),'Valid seller is created.');
assert_same('approved',$r['status'],'Seller is approved only after fresh identity eligibility.');
assert_same(['BEGIN','INSERT','AUDIT','COMMIT'],$wpdb->ops,'Seller create and audit both commit.');
$before=$wpdb->sellers;$wpdb->update_fail=true;$wpdb->ops=[];
$r=MKT_Listings::ensure_seller(41);
assert_true(is_wp_error($r),'Failed refresh cannot silently claim current seller eligibility.');
assert_same($before,$wpdb->sellers,'Failed seller refresh preserves the canonical snapshot.');
$wpdb->update_fail=false;$wpdb->ops=[];
$r=MKT_Listings::ensure_seller(41);
assert_true(is_array($r),'Seller refresh succeeds when DB accepts CAS update.');
assert_same(2,$r['version'],'Versioned seller eligibility refresh is returned.');
assert_same(['BEGIN','UPDATE','COMMIT'],$wpdb->ops,'Seller refresh follows a verified transaction.');
$wpdb->missing_readback=true;$wpdb->ops=[];
$r=MKT_Listings::ensure_seller(41);
assert_true(is_wp_error($r),'Unavailable seller readback does not fabricate verified status.');
fwrite(STDOUT,"File18 seller transactional identity gateway regression complete.\n");
