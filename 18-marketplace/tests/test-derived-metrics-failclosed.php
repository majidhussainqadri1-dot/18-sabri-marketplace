<?php
/** Marketplace-derived daily metrics must not fabricate 0 on SQL errors or commit partial projections. */
require __DIR__.'/bootstrap.php';
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS',86400);
class MetricsDB {
 public string $last_error='';
 public ?int $fail_count_at=null;
 public ?int $null_count_at=null;
 public int $fail_replace_at=0,$reads=0,$writes=0;
 public array $projected=[];
 public function prepare(string $sql,...$a):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$a);}
 public function get_var(string $sql){
  ++$this->reads;
  if($this->reads===$this->fail_count_at){$this->last_error='Injected source database error';return null;}
  if($this->reads===$this->null_count_at)return null;
  return match($this->reads){1=>5,2=>4,3=>3,4=>2,default=>0};
 }
 public function replace(string $table,array $values):int|false{
  ++$this->writes;
  if($this->writes===$this->fail_replace_at)return false;
  $this->projected[$values['metric_key']]=$values['value_count'];
  return 1;
 }
}
class MKT_DB {
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function now():string{return '2026-10-21 00:00:00';}
 public static function transaction(callable $fn){
  $db=$GLOBALS['wpdb'];$snapshot=$db->projected;
  try{return $fn();}catch(Throwable $e){$db->projected=$snapshot;throw $e;}
 }
}
$GLOBALS['opts']=[];
function update_option($name,$v,$a=false){$GLOBALS['opts'][$name]=$v;return true;}
function delete_option($name){unset($GLOBALS['opts'][$name]);return true;}
$wpdb=new MetricsDB();
require dirname(__DIR__).'/includes/class-mkt-maintenance.php';
$method=new ReflectionMethod('MKT_Maintenance','aggregate_metrics');
$method->setAccessible(true);
$wpdb->null_count_at=3;$method->invoke(null);
assert_same('source_count_unavailable',$GLOBALS['opts']['mkt_metrics_last_error'],'Unavailable deal COUNT cannot invent zero.');
assert_same([], $wpdb->projected,'An incomplete metric source snapshot writes no derivative facts.');
$wpdb->reads=0;$wpdb->null_count_at=null;$wpdb->fail_count_at=2;
$method->invoke(null);
assert_same([], $wpdb->projected,'Source database error cannot alter previously trusted metrics.');
$wpdb->reads=0;$wpdb->fail_count_at=null;$wpdb->writes=0;$wpdb->fail_replace_at=3;
$method->invoke(null);
assert_same('projection_write_failed',$GLOBALS['opts']['mkt_metrics_last_error'],'Third metric SQL write failing is a failed projection.');
assert_same([], $wpdb->projected,'Metric projection failures roll back all earlier writes in that set.');
$wpdb->reads=0;$wpdb->writes=0;$wpdb->fail_replace_at=0;
$method->invoke(null);
assert_same(['active_listings'=>5,'offers_created'=>4,'deals_accepted'=>3,'reports_created'=>2],$wpdb->projected,'Valid all-source metric counts are projected without alteration.');
assert_true(!isset($GLOBALS['opts']['mkt_metrics_last_error']),'Successful rebuilt metrics clear error state.');
fwrite(STDOUT,"File18 CF05 conditional owner safe daily metrics SQL/transaction regression complete.\n");
