<?php
/** RC19: immutable medical and zero-commission constitutional policy floor. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
function sanitize_key($v):string{return strtolower(preg_replace('/[^a-z0-9_-]/i','',(string)$v));}
function wp_strip_all_tags($v):string{return strip_tags((string)$v);}
function apply_filters($hook,$value,...$args){return $value;}
class MKT_DB {public static function table(string $name):string{return 'wp_mkt_'.$name;} public static function now():string{return '2026-10-22 00:00:00';}}
class MKT_Contracts {public static function allowed_currencies():array{return ['PKR'];}}
class PolicyFloorDB {
 public string $last_error='';
 public bool $medical_floor=false;
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace('%s',"'%s'",$sql),$args);}
 public function get_row(string $sql,$mode) {
  if(str_contains($sql,"'category'")) {
   $required=['required_fields'=>['title','description','price','currency']];
   if($this->medical_floor)$required+=['requires_verified_professional'=>true,'requires_human_review'=>true,'requires_structured_evidence'=>true];
   return ['public_id'=>'medicine-policy','version'=>5,'rules_json'=>json_encode($required)];
  }
  return ['public_id'=>'prohibited-policy','version'=>5,'rules_json'=>json_encode(['categories'=>[],'phrases'=>[]])];
 }
}
$wpdb=new PolicyFloorDB();
require dirname(__DIR__).'/includes/class-mkt-policy.php';
$valid=['commission_percent'=>0,'donation_advantage'=>false,'hidden_fees'=>false,'escrow_guarantee'=>false];
assert_same(true,MKT_Policy::valid_zero_commission_policy($valid),'Only an explicit four-part safe zero-commission contract passes.');
foreach(['commission_percent'=>5,'donation_advantage'=>true,'hidden_fees'=>true,'escrow_guarantee'=>true] as $field=>$v){
 $bad=$valid;$bad[$field]=$v;
 assert_same(false,MKT_Policy::valid_zero_commission_policy($bad),"Business covenant blocks {$field} override.");
}
foreach(array_keys($valid) as $field){
 $bad=$valid;unset($bad[$field]);
 assert_same(false,MKT_Policy::valid_zero_commission_policy($bad),"Omitted {$field} cannot silently default to a safe policy.");
}
$bad=$valid;$bad['commission_percent']='0evil';
assert_same(false,MKT_Policy::valid_zero_commission_policy($bad),'Partially numeric commission strings are rejected.');
$admin=file_get_contents(dirname(__DIR__).'/includes/class-mkt-admin.php');
assert_true(str_contains($admin,'!MKT_Policy::valid_zero_commission_policy($rules)'),'wp-admin policy writes honor the same immutable four-part contract.');
$data=['category'=>'homeopathic_medicines','title'=>'Properly titled item','description'=>'Neutral educational description',
'price'=>100,'currency'=>'PKR','quantity'=>1,'listing_type'=>'product',
'declarations'=>['truthful'=>true,'rights_owned'=>true,'no_patient_data'=>true,'zero_commission_understood'=>true]];
$assertions=['verified'=>true,'roles'=>['verified_doctor']];
$fail=MKT_Policy::evaluate_listing($data,$assertions);
assert_same(false,$fail['valid'],'A mutable medicine policy missing human review/verified seller/evidence cannot authorize a medicine listing.');
assert_true(in_array('regulated_policy_unavailable',array_column($fail['errors'],'code'),true),'Regulated safety-floor denial has an explicit code.');
$wpdb->medical_floor=true;
$okay=MKT_Policy::evaluate_listing($data,$assertions);
assert_same(true,$okay['valid'],'Verified complete medical policy permits further submission review.');
assert_true(in_array('human_review',$okay['holds'],true),'Required human review cannot be bypassed.');
assert_true(in_array('structured_evidence_review',$okay['holds'],true),'Structured regulated evidence hold remains mandatory.');
fwrite(STDOUT,"RC19 constitutional zero-commission and mandatory medicine policy floor verified.\n");