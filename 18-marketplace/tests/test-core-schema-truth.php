<?php
/** Core File18 schema vs wp_options truth, with real MKT_DB::schema_ready/maybe_upgrade methods. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
class CoreSchemaFakeDB {
 public string $prefix='wp_';
 public string $last_error='';
 public bool $missing_table=false;
 public bool $missing_column=false;
 public bool $bad_inventory=false;
 public bool $bad_unique=false;
 public int $upgrade_locks=0;
 public function esc_like(string $text):string{return str_replace(['_','%'],['\\_','\\%'],$text);}
 public function prepare(string $q,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$q),$args);}
 public function get_col(string $q):array {
  if(str_contains($q,'SHOW TABLES LIKE')){
   if($this->bad_inventory)return [];
   $names=['sellers','policies','listings','media_refs','saves','offers','deals','reports','disputes','outbox','inbox','idempotency','audit','metrics_daily','migration_handoffs'];
   if($this->missing_table)$names=array_values(array_diff($names,['migration_handoffs']));
   return array_map(fn($n)=>'wp_mkt_'.$n,$names);
  }
  if(str_contains($q,'SHOW COLUMNS FROM')){
   $columns=[
    'sellers'=>['id','user_id','status','version'],
    'listings'=>['id','seller_id','public_id','status','version','published_at'],
    'offers'=>['id','listing_id','idempotency_key','version'],
    'deals'=>['id','accepted_offer_id','buyer_user_id','seller_user_id','version'],
    'outbox'=>['id','event_id','status','available_at'],
    'inbox'=>['id','source','event_id','payload_hash','status'],
    'audit'=>['id','previous_hash','entry_hash'],
    'migration_handoffs'=>['id','legacy_type','legacy_id','status'],
   ];
   foreach($columns as $name=>$list)if(str_contains($q,'wp_mkt_'.$name)){
    if($this->missing_column&&$name==='listings')return array_values(array_diff($list,['version']));
    return $list;
   }
  }
  return [];
 }
 public function get_results(string $sql,$type):array {
  $table='';
  foreach(['sellers','listings','offers','deals','outbox','inbox','idempotency','migration_handoffs'] as $name)
    if(str_contains($sql,'wp_mkt_'.$name)){$table=$name;break;}
  $keys=[
   'sellers'=>['user_id'=>['user_id']], 'listings'=>['public_id'=>['public_id']],
   'offers'=>['idempotency_key'=>['idempotency_key']], 'deals'=>['accepted_offer_id'=>['accepted_offer_id']],
   'outbox'=>['event_id'=>['event_id']], 'inbox'=>['source_event'=>['source','event_id']],
   'idempotency'=>['actor_scope_key'=>['actor_user_id','scope','idempotency_key']],
   'migration_handoffs'=>['legacy_record'=>['legacy_type','legacy_id']]
  ];
  $rows=[['Key_name'=>'PRIMARY','Non_unique'=>0,'Seq_in_index'=>1,'Column_name'=>'id']];
  foreach($keys[$table]??[] as $key=>$parts)foreach($parts as $i=>$col){
   if($this->bad_unique && $table==='sellers' && $key==='user_id')$col='other_user_id';
   $rows[]=['Key_name'=>$key,'Non_unique'=>0,'Seq_in_index'=>$i+1,'Column_name'=>$col];
  }
  return $rows;
 }
 public function get_var(string $sql):int {
  if(str_contains($sql,'GET_LOCK')){$this->upgrade_locks++;return 0;} // simulates concurrent updater: no schema writes
  return 0;
 }
}
$wpdb=new CoreSchemaFakeDB();
$GLOBALS['core_schema_options']=[
 'mkt_schema_version'=>'2.1.0',
 'mkt_plugin_version'=>'2.1.0',
 'mkt_schema_verified_at'=>0,
];
function get_option($k,$default=''){return $GLOBALS['core_schema_options'][$k]??$default;}
function update_option($k,$v,$autoload=false):bool{$GLOBALS['core_schema_options'][$k]=$v;return true;}
function delete_option($k):bool{unset($GLOBALS['core_schema_options'][$k]);return true;}
defined('MKT_SCHEMA_VERSION')||define('MKT_SCHEMA_VERSION','2.1.0');
defined('MKT_VERSION')||define('MKT_VERSION','2.1.0');
require dirname(__DIR__).'/includes/class-mkt-db.php';

assert_same(true,MKT_DB::schema_ready(),'Complete core Marketplace table and column inventory must pass.');
$wpdb->missing_table=true;
assert_same(false,MKT_DB::schema_ready(),'A missing migration-handoffs table invalidates current schema.');
MKT_DB::maybe_upgrade();
assert_same(1,$wpdb->upgrade_locks,'Even current wp_options versions attempt guarded repair when a deployed table is missing.');
$wpdb->missing_table=false;
$wpdb->missing_column=true;
assert_same(false,MKT_DB::schema_ready(),'Version-critical missing listing column invalidates schema.');
MKT_DB::maybe_upgrade();
assert_same(2,$wpdb->upgrade_locks,'Current version with bad real columns must not silently skip repair.');
$wpdb->missing_column=false;
$wpdb->bad_unique=true;
assert_same(false,MKT_DB::schema_ready(),'Missing/wrong UNIQUE key is not disguised by correct table and column inventory.');
$wpdb->bad_unique=false;
$wpdb->bad_inventory=true;
assert_same(false,MKT_DB::schema_ready(),'Invalid table-inventory query never verifies schema.');
$wpdb->bad_inventory=false;
$wpdb->upgrade_locks=0;
$GLOBALS['core_schema_options']['mkt_schema_verified_at']=time();
MKT_DB::maybe_upgrade();
assert_same(0,$wpdb->upgrade_locks,'Recent successful verification avoids repeated metadata scans and redundant repair locks.');
fwrite(STDOUT,"File18 main schema version-to-deployed inventory negative regression complete.\n");