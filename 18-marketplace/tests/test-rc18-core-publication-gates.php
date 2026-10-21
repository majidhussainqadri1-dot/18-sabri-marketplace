<?php
/** RC18: direct PHP listing owner paths must enforce the same canonical safety gates as REST. */
require __DIR__ . '/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function current_user_can($cap): bool {return $cap==='mkt_moderate';}
function wp_kses_post($x): string {return (string)$x;}
final class MKT_Auth {
 public static function can($permission,array $context=[]){return true;}
 public static function own_listing($listing,$actor): bool{return $actor===41;}
 public static function assertions(int $actor):array{return ['verified'=>true];}
}
final class MKT_State_Machines {public static function assert($kind,$from,$to){return true;}}
final class MKT_Policy {
 public static int $calls=0;
 public static function evaluate_listing($listing,$claims):array{self::$calls++;return ['valid'=>true,'errors'=>[]];}
}
final class MKT_Plan_Completion {
 public static int $calls=0;
 public static string $deny='mkt_listing_language_required';
 public static function publication_gate(string $public_id):bool|WP_Error{
  self::$calls++;
  if(self::$deny!=='')return new WP_Error(self::$deny,'Required publication fact missing',['status'=>422]);
  return true;
 }
}
final class MKT_DB {public static function table($name):string{return 'wp_mkt_'.$name;}}
final class MKT_Governance {public static function recall_publication_guard($id):bool{return false;}}
final class GateDB {
 public int $writes=0;
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function get_row($sql,$output):array{return [
  'id'=>7,'public_id'=>'listing-123','version'=>1,'status'=>'paused','seller_user_id'=>41,
  'seller_id'=>2,'listing_type'=>'service','expires_at'=>'2099-01-01 00:00:00'
 ];}
 public function update($table,$data,$where):int{$this->writes++;return 1;}
}
$wpdb=new GateDB();
require dirname(__DIR__).'/includes/class-mkt-listings.php';
$submit=MKT_Listings::submit('listing-123',41,1);
assert_true(is_wp_error($submit),'Direct submit fails without required listing language.');
assert_same('mkt_listing_language_required',$submit->get_error_code(),'Direct submit propagates the canonical gate reason.');
assert_same(0,$wpdb->writes,'Direct submit must not write any review state before safety gate.');
assert_same(1,MKT_Plan_Completion::$calls,'Canonical gate actually executed on direct submit.');
MKT_Plan_Completion::$deny='mkt_evidence_review_required';
$transition=MKT_Listings::transition('listing-123','active',41,1);
assert_true(is_wp_error($transition),'Direct seller/admin reactivation fails without regulated evidence.');
assert_same('mkt_evidence_review_required',$transition->get_error_code(),'Direct active transition propagates canonical evidence reason.');
assert_same(0,$wpdb->writes,'Direct activation must not write active status before safety gate.');
assert_same(2,MKT_Plan_Completion::$calls,'Owner transition independently invokes canonical publication gate.');
$source=file_get_contents(dirname(__DIR__).'/includes/class-mkt-plan-completion.php');
assert_true(str_contains($source,'public static function publication_gate(string $listing_public_id)'), 'Canonical publication gate is exposed for other File18 PHP owner entry points.');
$admin=file_get_contents(dirname(__DIR__).'/includes/class-mkt-admin.php');
$begin=strpos($admin,'public static function handle_moderation_action');
$end=strpos($admin,'public static function handle_policy_save',$begin);
$handler=substr($admin,$begin,$end-$begin);
assert_true(str_contains($handler,'MKT_DB::transaction(static function()') && str_contains($handler,'mkt_moderation_atomic_failed'), 'Native wp-admin moderation commits listing/report/dispute transitions with audit in one local transaction.');
fwrite(STDOUT,"RC18 canonical publication and atomic wp-admin moderation source regression passed.\n");