<?php
/** RC19: actual policy seeder, database transaction rollback and immutable owner version. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
defined('MKT_VERSION') || define('MKT_VERSION','2.1.0');
function wp_json_encode($x):string{return json_encode($x);}
$options=[];
function get_option(string $name,$default=false){global $options;return $options[$name]??$default;}
function update_option(string $name,$value,$autoload=false):bool{global $options;$options[$name]=$value;return true;}
class SeedDB {
 public array $rows=[];
 public string $last_error='';
 public int $insert_id=0;
 public bool $fail_retire=false;
 public bool $fail_insert=false;
 public int $rollbacks=0;
 public int $commits=0;
 public function prepare(string $sql,...$args):string{
  return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);
 }
 private function parse(string $sql):array{
  preg_match("/policy_type='([^']+)' AND policy_key='([^']+)' AND jurisdiction='([^']+)'/",$sql,$p);
  return [$p[1]??'',$p[2]??'',$p[3]??''];
 }
 public function get_var(string $sql) {
  [$type,$key,$juris]=$this->parse($sql);
  $matching=array_values(array_filter($this->rows,static fn($r)=>$r['policy_type']===$type&&$r['policy_key']===$key&&$r['jurisdiction']===$juris));
  if(str_contains($sql,'SELECT MAX(version)')){
   return $matching?max(array_column($matching,'version')):null;
  }
  preg_match('/AND version=(\d+)/',$sql,$m);
  foreach($matching as $row)if($row['version']===(int)($m[1]??0))return $row['id'];
  return null;
 }
 public function update(string $table,array $values,array $where):int|false {
  if($this->fail_retire && $where['policy_key']==='global_prohibited_goods_services')return false;
  $n=0;
  foreach($this->rows as &$row){
   $match=true;foreach($where as $k=>$val){if(($row[$k]??null)!==$val){$match=false;break;}}
   if($match){$row=array_merge($row,$values);$n++;}
  }
  unset($row);
  return $n;
 }
 public function insert(string $table,array $data):int|false {
  if($this->fail_insert && $data['policy_key']==='global_prohibited_goods_services')return false;
  $data['id']=++$this->insert_id;
  $this->rows[]=$data;return 1;
 }
 public function policyVersions(string $type,string $key):array{
  return array_values(array_filter($this->rows,static fn($r)=>$r['policy_type']===$type&&$r['policy_key']===$key));
 }
}
$wpdb=new SeedDB();
class MKT_DB {
 private static int $counter=0;
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function table_exists(string $name):bool{return true;}
 public static function now():string{return '2026-10-22 01:00:00';}
 public static function uuid():string{return 'policy-'.(++self::$counter);}
 public static function transaction(callable $callback) {
  global $wpdb;
  $rows=$wpdb->rows;
  try {
   $result=$callback();
   if(is_wp_error($result)||$result===false){$wpdb->rows=$rows;$wpdb->rollbacks++;return $result;}
   $wpdb->commits++;return $result;
  }catch(Throwable $e){$wpdb->rows=$rows;$wpdb->rollbacks++;throw $e;}
 }
}
require dirname(__DIR__).'/includes/class-mkt-policy.php';
$wpdb->rows[]=[
 'id'=>99,'policy_type'=>'prohibited','policy_key'=>'global_prohibited_goods_services',
 'jurisdiction'=>'GLOBAL','version'=>1,'status'=>'active',
 'rules_json'=>json_encode(['categories'=>['fraud'],'phrases'=>[]])
];
$wpdb->fail_insert=true;
MKT_Policy::seed_builtin_policies();
$versions=$wpdb->policyVersions('prohibited','global_prohibited_goods_services');
assert_same(1,count($versions),'Failed new policy INSERT does not create an invalid version.');
assert_same('active',$versions[0]['status'],'A failed INSERT must ROLLBACK retirement of the prior active safety policy.');
assert_true($wpdb->rollbacks>0,'The failed retire/insert group was inside a rollback-capable transaction.');
assert_same(false,get_option('mkt_builtin_policy_version',false),'Failed policy seed does not claim the new version.');
$wpdb->fail_insert=false;$wpdb->fail_retire=true;
MKT_Policy::seed_builtin_policies();
$versions=$wpdb->policyVersions('prohibited','global_prohibited_goods_services');
assert_same(1,count($versions),'Retire UPDATE failure also cannot create a new prohibited policy.');
assert_same('active',$versions[0]['status'],'Retire failure keeps prior active version unchanged.');
$wpdb->fail_retire=false;
MKT_Policy::seed_builtin_policies();
$versions=$wpdb->policyVersions('prohibited','global_prohibited_goods_services');
assert_same(2,count($versions),'Recovered seed installs the immutable newer baseline version.');
$states=[];foreach($versions as $p)$states[$p['version']]=$p['status'];
assert_same('retired',$states[1],'Old prohibited policy retires only after a successful insertion.');
assert_same('active',$states[2],'New prohibited policy becomes active.');
assert_same('2.1.0-categories-5',get_option('mkt_builtin_policy_version'),'Successful seed alone advances option marker.');
// Simulate an approved newer policy whose intermediate v2 is absent.
$options=[];
$wpdb->rows=array_values(array_filter($wpdb->rows,static fn($x)=>!($x['policy_type']==='prohibited'&&$x['policy_key']==='global_prohibited_goods_services')));
$wpdb->rows[]=[
 'id'=>501,'policy_type'=>'prohibited','policy_key'=>'global_prohibited_goods_services',
 'jurisdiction'=>'GLOBAL','version'=>3,'status'=>'active','rules_json'=>'{}'
];
MKT_Policy::seed_builtin_policies();
$versions=$wpdb->policyVersions('prohibited','global_prohibited_goods_services');
assert_same(1,count($versions),'A newer approved v3 policy is never overwritten or filled underneath by a v2 built-in seed.');
assert_same(3,$versions[0]['version'],'The canonical newer policy version remains unchanged.');
assert_same('active',$versions[0]['status'],'The canonical newer policy remains active.');
fwrite(STDOUT,"RC19 atomic policy seeding, rollback and no downgrade verified.\n");