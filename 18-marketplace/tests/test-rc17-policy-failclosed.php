<?php
/** RC17 actual-class policy absence, corrupt payload and incomplete seed regressions. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
defined('MKT_VERSION') || define('MKT_VERSION','2.1.0');
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function wp_strip_all_tags($v):string{return strip_tags((string)$v);}
function wp_json_encode($v):string|false{return json_encode($v);}
$options=[];
function get_option(string $name,$default=false){global $options;return $options[$name]??$default;}
function update_option(string $name,$value,$autoload=false):bool{global $options;$options[$name]=$value;return true;}
final class MKT_DB {
  private static int $sequence=0;
  public static function table(string $name):string{return 'wp_mkt_'.$name;}
  public static function table_exists(string $name):bool{return true;}
  public static function now():string{return '2026-10-22 00:00:00';}
  public static function uuid():string{return 'id-'.(++self::$sequence);}
}
final class MKT_Contracts {public static function allowed_currencies():array{return ['PKR'];}}
class PolicyFailureDB {
 public string $last_error='';
 public string $prohibited='valid';
 public bool $failed_seed_read=false;
 public bool $failed_seed_write=false;
 public int $insert_count=0;
 public function prepare(string $sql,...$args):string{
  if(count($args)===1&&is_array($args[0]))$args=$args[0];
  return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);
 }
 public function get_row(string $sql,$array):?array{
  if(str_contains($sql,"'category'")){
    return ['public_id'=>'category-id','version'=>1,'rules_json'=>json_encode(['required_fields'=>[]])];
  }
  if($this->prohibited==='missing')return null;
  if($this->prohibited==='corrupt')return ['public_id'=>'prohibited-id','version'=>1,'rules_json'=>'{malformed JSON'];
  return ['public_id'=>'prohibited-id','version'=>1,'rules_json'=>json_encode(['categories'=>[],'phrases'=>[]])];
 }
 public function get_var(string $sql){
  if($this->failed_seed_read){$this->last_error='Injected policy SELECT error';return null;}
  return null;
 }
 public function update(string $table,array $values,array $where):int|false{
  return $this->failed_seed_write?false:1;
 }
 public function insert(string $table,array $values):int|false{
  if($this->failed_seed_write)return false;
  $this->insert_count++;
  return 1;
 }
}
$wpdb=new PolicyFailureDB();
require dirname(__DIR__).'/includes/class-mkt-policy.php';
$data=[
 'title'=>'A textbook','description'=>'A legitimate textbook','category'=>'homeopathy_books',
 'listing_type'=>'product','quantity'=>1,'currency'=>'PKR','price'=>150,
 'declarations'=>['truthful'=>true,'rights_owned'=>true,'no_patient_data'=>true,'zero_commission_understood'=>true]
];
$wpdb->prohibited='missing';
$missing=MKT_Policy::evaluate_listing($data,[]);
assert_same(false,$missing['valid'],'No canonical prohibited policy means no new listing acceptance.');
assert_true(in_array('prohibited_policy_unavailable',array_column($missing['errors'],'code'),true),'Missing policy has a specific fail-closed code.');
$wpdb->prohibited='corrupt';
$corrupt=MKT_Policy::evaluate_listing($data,[]);
assert_same(false,$corrupt['valid'],'Malformed prohibited policy JSON must not become zero restrictions.');
$wpdb->prohibited='valid';
$normal=MKT_Policy::evaluate_listing($data,[]);
assert_same(true,$normal['valid'],'Verified active explicit category/phrase lists allow otherwise valid listing.');
$wpdb->failed_seed_read=true;
MKT_Policy::seed_builtin_policies();
assert_same(false,get_option('mkt_builtin_policy_version',false),'Seed SELECT outage must never advance policy option.');
$wpdb->failed_seed_read=false;$wpdb->failed_seed_write=true;
MKT_Policy::seed_builtin_policies();
assert_same(false,get_option('mkt_builtin_policy_version',false),'Policy INSERT or retire failure must not mark incomplete seeding finished.');
$wpdb->failed_seed_write=false;$wpdb->insert_count=0;
MKT_Policy::seed_builtin_policies();
assert_true($wpdb->insert_count>=10,'Healthy seed populates actual governed policy rows.');
assert_same('2.1.0-categories-4',get_option('mkt_builtin_policy_version'),'Only successful complete seed advances canonical policy marker.');
fwrite(STDOUT,"RC17 fail-closed prohibited policy and policy-seeding failures verified.\n");