<?php
/** RC18: WordPress option for File18 listing language facets must match deployed schema truth. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
class FacetSchemaDB {
 public string $prefix='wp_';
 public string $last_error='';
 public bool $table_exists=true;
 public bool $columns_complete=true;
 public bool $primary_correct=true;
 public bool $read_error=false;
 public function esc_like(string $v):string{return $v;}
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace('%s',"'%s'",$sql),$args);}
 public function get_var(string $sql){
  if($this->read_error){$this->last_error='Injected schema inventory query outage';return null;}
  return $this->table_exists?'wp_mkt_listing_facets':null;
 }
 public function get_col(string $sql,int $index=0):array{
  return $this->columns_complete?['listing_id','language','version','created_at','updated_at']:['listing_id','language'];
 }
 public function get_results(string $sql,$type):array{
  return [['Key_name'=>'PRIMARY','Non_unique'=>0,'Seq_in_index'=>1,'Column_name'=>$this->primary_correct?'listing_id':'language']];
 }
}
$wpdb=new FacetSchemaDB();
require dirname(__DIR__).'/includes/class-mkt-plan-completion.php';
assert_same(true,MKT_Plan_Completion::schema_ready(),'Fully present local facets table with exact primary owner key is verified.');
$wpdb->table_exists=false;
assert_same(false,MKT_Plan_Completion::schema_ready(),'A current-looking options value does not prove the table exists.');
$wpdb->table_exists=true;$wpdb->columns_complete=false;
assert_same(false,MKT_Plan_Completion::schema_ready(),'Missing facet version and timestamps means partial migration.');
$wpdb->columns_complete=true;$wpdb->primary_correct=false;
assert_same(false,MKT_Plan_Completion::schema_ready(),'Wrong facets PRIMARY KEY cannot claim listing uniqueness.');
$wpdb->primary_correct=true;$wpdb->read_error=true;
assert_same(false,MKT_Plan_Completion::schema_ready(),'Schema SQL outage may not be treated as verified deployment.');
$src=file_get_contents(dirname(__DIR__).'/includes/class-mkt-plan-completion.php');
assert_true(str_contains($src,"|| !self::schema_ready()"),'Already-current WordPress option forces re-verification of real schema.');
assert_true(str_contains($src,"delete_option('mkt_plan_completion_schema_version')"),'Partial dbDelta explicitly removes stale version truth.');
assert_true(str_contains($src,"'mkt_plan_completion_schema_error', 'schema_verification_failed'"),'An actionable local migration failure marker is recorded.');
fwrite(STDOUT,"RC18 facets schema readiness, uniqueness and migration truth regression passed.\n");