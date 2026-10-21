<?php
/** Actual File18 maintenance: retention must never silently erase held evidence or break audit chain. */
require __DIR__.'/bootstrap.php';
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS',86400);
class RetentionDB {
 public string $last_error='';
 public bool $fail_report=false,$fail_dispute=false;
 public array $statements=[],$committed=[];
 public function prepare(string $query,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$query),$args);}
 public function query(string $sql):int|false {
  $this->statements[]=$sql;
  if(str_contains($sql,'UPDATE wp_mkt_reports')&&$this->fail_report)return false;
  if(str_contains($sql,'UPDATE wp_mkt_disputes')&&$this->fail_dispute)return false;
  $this->committed[]=$sql;
  return 1;
 }
}
class MKT_DB {
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function settings():array{return ['retention_reports_days'=>100,'retention_disputes_days'=>200,'retention_audit_days'=>30];}
 public static function now():string{return '2026-10-21 00:00:00';}
 public static function transaction(callable $fn) {
  $db=$GLOBALS['wpdb'];$previous=$db->committed;
  try {$out=$fn();if(is_wp_error($out)){$db->committed=$previous;return $out;}return $out;}
  catch(Throwable $e){$db->committed=$previous;throw $e;}
 }
}
class MKT_Governance {
 public static bool $hold_ready=false;
 public static function hold_guard_available():bool{return self::$hold_ready;}
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
}
$GLOBALS['opts']=[];
function update_option($key,$value,$auto=false){$GLOBALS['opts'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['opts'][$key]);return true;}
$wpdb=new RetentionDB();
require dirname(__DIR__).'/includes/class-mkt-maintenance.php';
$purge=new ReflectionMethod('MKT_Maintenance','purge_retention');
$purge->setAccessible(true);

$purge->invoke(null);
assert_same('legal_hold_schema_unavailable',$GLOBALS['opts']['mkt_retention_last_error'],'Missing legal-hold schema blocks all report/dispute retention writes.');
assert_same('verified_checkpoint_required',$GLOBALS['opts']['mkt_retention_audit_prune_state'],'Audit prune explicitly requires verified chain-preserving checkpoint.');
assert_same([], $wpdb->committed,'Missing hold evidence performs no destructive report/dispute/audit writes.');

MKT_Governance::$hold_ready=true;
$wpdb->statements=[];$wpdb->committed=[];
$purge->invoke(null);
assert_true(!isset($GLOBALS['opts']['mkt_retention_last_error']),'Healthy legal holds and completed SQL clear retention error.');
assert_same(1,count(array_filter($wpdb->statements,fn($x)=>str_contains($x,'UPDATE wp_mkt_reports'))),'One report retention query.');
assert_same(1,count(array_filter($wpdb->statements,fn($x)=>str_contains($x,'UPDATE wp_mkt_disputes'))),'One dispute retention query.');
assert_true(str_contains(implode("\n",$wpdb->statements),'NOT EXISTS'),'Active legal holds exclude sensitive record minimization.');
assert_true(!str_contains(implode("\n",$wpdb->statements),'DELETE FROM wp_mkt_audit'),'Audit chain entries are never blindly deleted.');
$wpdb->statements=[];$wpdb->committed=[];$wpdb->fail_dispute=true;
$purge->invoke(null);
assert_same('retention_minimization_failed',$GLOBALS['opts']['mkt_retention_last_error'],'Failed dispute minimization creates retryable error.');
assert_true(!count(array_filter($wpdb->committed,fn($x)=>str_contains($x,'UPDATE wp_mkt_reports'))),'A failed dispute SQL statement rolls back report mutation in same transaction.');
assert_true(!str_contains(implode("\n",$wpdb->statements),'DELETE FROM wp_mkt_audit'),'Failed pass cannot delete immutable audit history.');
$wpdb->fail_dispute=false;$wpdb->fail_report=true;$wpdb->statements=[];$wpdb->committed=[];
$purge->invoke(null);
assert_same('retention_minimization_failed',$GLOBALS['opts']['mkt_retention_last_error'],'Failed report SQL statement never claims success.');
assert_true(!count(array_filter($wpdb->statements,fn($x)=>str_contains($x,'UPDATE wp_mkt_disputes'))),'Fail-fast report SQL prevents dispute mutation.');
fwrite(STDOUT,"File18 retention audit hash preservation and legal-hold SQL failure fault regression complete.\n");
