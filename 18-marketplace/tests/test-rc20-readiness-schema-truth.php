<?php
/** RC20 actual admin readiness: three verified schemas and SQL proof are blockers. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
defined('MKT_VERSION') || define('MKT_VERSION','2.1.0');
function get_option($name,$default=[]){return $name==='rewrite_rules'?['^marketplace/?$'=>true]:$default;}
function wp_next_scheduled($event){return true;}
final class MKT_DB {
 public static bool $core=true;
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function table_exists(string $name):bool{return true;}
 public static function schema_ready():bool{return self::$core;}
 public static function settings():array{return ['safe_mode'=>0,'selling_enabled'=>1];}
}
final class MKT_Governance {public static bool $governance=true;public static function schema_ready():bool{return self::$governance;}}
final class MKT_Plan_Completion {public static bool $facets=true;public static function schema_ready():bool{return self::$facets;}}
final class MKT_Integrations {public static function status():array{return [
 'identity'=>['available'=>true],'communication'=>['available'=>true],
 'notifications'=>['available'=>true],'shell'=>['available'=>true],
 ];}}
final class MKT_Contracts {public static function zero_commission():int{return 0;}}
final class MKT_Audit {
 public static function verify_chain($limit):array{return ['valid'=>true,'checked'=>10,'breaks'=>0,'lock_gaps'=>0];}
 public static function trace_id():string{return 'rc20-test';}
}
class ReadinessFakeDB {
 public string $last_error='';
 public bool $broken_describe=false;
 public function get_var(string $sql){return 0;}
 public function get_col(string $sql,int $col=0) {
  if($this->broken_describe){$this->last_error='Simulated SQL DESCRIBE outage';return null;}
  return ['id','status','public_id'];
 }
}
$wpdb=new ReadinessFakeDB();
require dirname(__DIR__).'/includes/class-mkt-admin.php';
$ok=MKT_Admin::system_status();
assert_same('ready_for_staging_tests',$ok['overall'],'All verified source-owner schemas permit staging preparation (not live acceptance).');
foreach(['core'=>'core_schema_truth','governance'=>'governance_schema_truth','facets'=>'facet_schema_truth'] as $name=>$id){
 if($name==='core')MKT_DB::$core=false;
 if($name==='governance')MKT_Governance::$governance=false;
 if($name==='facets')MKT_Plan_Completion::$facets=false;
 $out=MKT_Admin::system_status();
 $entry=array_values(array_filter($out['checks'],static fn($c)=>$c['id']===$id));
 assert_same(1,count($entry),'Canonical stage readiness includes '.$name.' schema truth.');
 assert_same(false,$entry[0]['pass'],'Missing '.$name.' schema truth fails closed.');
 assert_same('blocker',$entry[0]['severity'],'Missing '.$name.' schema is a release blocker.');
 assert_same('fail_closed',$out['overall'],'Partial '.$name.' migration cannot report ready_for_staging_tests.');
 MKT_DB::$core=MKT_Governance::$governance=MKT_Plan_Completion::$facets=true;
}
$wpdb->broken_describe=true;
$failed=MKT_Admin::system_status();
$zero=array_values(array_filter($failed['checks'],static fn($c)=>$c['id']==='zero_commission'));
assert_same(false,$zero[0]['pass'],'Unknown commission columns from SQL outage cannot attest zero-fee invariant.');
assert_same('fail_closed',$failed['overall'],'SQL DESCRIBE outage must block staging readiness.');
fwrite(STDOUT,"RC20 three schema truth blockers and commission SQL outage passed.\n");