<?php
/** Regression: old File17 conversation/message rows must be inventoried without legacy sellers/products. */
require __DIR__.'/bootstrap.php';
defined('MKT_SCHEMA_VERSION')||define('MKT_SCHEMA_VERSION','2.1.0');
class LegacyOnlyFakeDB {
 public string $prefix='wp_';
 public array $tables=['wp_smp_conversations'=>true, 'wp_smp_messages'=>true];
 public array $handoffs=[];
 public bool $fail_write=false;
 public function esc_like(string $s):string{return $s;}
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function get_var(string $sql) {
  if(str_contains($sql,'SHOW TABLES LIKE')){
   foreach(array_keys($this->tables) as $table)if(str_contains($sql,$table))return $table;
   return null;
  }
  return null;
 }
 public function get_col(string $sql) {
  if(str_contains($sql,'wp_smp_conversations'))return [17,23];
  if(str_contains($sql,'wp_smp_messages'))return [100];
  return [];
 }
 public function query(string $sql):int|false {
  if(!str_contains($sql,'INSERT IGNORE INTO wp_mkt_migration_handoffs'))return 0;
  if($this->fail_write)return false;
  $this->handoffs[]=$sql;return 1;
 }
}
$wpdb=new LegacyOnlyFakeDB();
$GLOBALS['migration_options']=[];
function get_option($name,$default=''){return $GLOBALS['migration_options'][$name]??$default;}
function update_option($name,$v,$autoload=false):bool{$GLOBALS['migration_options'][$name]=$v;return true;}
require dirname(__DIR__).'/includes/class-mkt-db.php';

MKT_DB::migrate_legacy_foundation();
assert_same(3,count($wpdb->handoffs),'Two conversations and one message produce File17 handoff rows despite no seller/product tables.');
assert_same(3,$GLOBALS['migration_options']['mkt_legacy_migration_report']['communication_handoffs'],'Real migration report counts all File17 legacy handoffs.');
assert_same('2.1.0',$GLOBALS['migration_options']['mkt_legacy_migration_version'],'Migration version advances only after all File17 handoff writes succeed.');

unset($GLOBALS['migration_options']['mkt_legacy_migration_version']);
$wpdb->tables=['wp_smp_messages'=>true];
$wpdb->handoffs=[];
MKT_DB::migrate_legacy_foundation();
assert_same(1,count($wpdb->handoffs),'Message-only legacy installation also creates a File17 handoff.');

unset($GLOBALS['migration_options']['mkt_legacy_migration_version']);
$wpdb->handoffs=[];$wpdb->fail_write=true;
try {MKT_DB::migrate_legacy_foundation();$failed=false;}catch(RuntimeException $e){$failed=true;}
assert_same(true,$failed,'File17 owner handoff failure raises a hard migration error.');
assert_true(!isset($GLOBALS['migration_options']['mkt_legacy_migration_version']),'Failed File17 handoff must never stamp migration as complete.');
fwrite(STDOUT,"File18 File17 legacy-only migration handoff and failed-write regression complete.\n");