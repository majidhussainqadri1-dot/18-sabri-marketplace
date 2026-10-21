<?php
/** Schema truth: an option must never attest tables/columns not actually deployed. */
require __DIR__.'/bootstrap.php';
class SchemaDB {
 public string $prefix='wp_';
 public string $last_error='';
 public bool $tables_ok=true;
 public bool $columns_ok=false;
 public int $delta_calls=0;
 public function esc_like(string $x):string{return $x;}
 public function get_charset_collate():string{return 'DEFAULT CHARACTER SET utf8mb4';}
 public function prepare(string $q,...$args):string{return vsprintf(str_replace('%s',"'%s'",$q),$args);}
 public function get_var(string $q) {
  if(!str_contains($q,'SHOW TABLES LIKE'))return null;
  if(!$this->tables_ok)return null;
  foreach(['wp_mkt_listing_evidence','wp_mkt_retention_holds','wp_mkt_recalls'] as $t)
    if(str_contains($q,$t))return $t;
  return null;
 }
 public function get_col(string $q,int $num=0):array {
  if(str_contains($q,'wp_mkt_listing_evidence'))return $this->columns_ok
    ?['id','listing_public_id','evidence_json','status','version','reviewed_by','reviewed_at']
    :['id','listing_public_id','status']; // Not migrated.
  if(str_contains($q,'wp_mkt_retention_holds'))return ['id','public_id','object_type','object_public_id','status','expires_at','released_at'];
  if(str_contains($q,'wp_mkt_recalls'))return ['id','public_id','listing_public_id','status','version','activated_at','resolved_at'];
  return [];
 }
}
$wpdb=new SchemaDB();
$GLOBALS['options']=['mkt_governance_schema_version'=>'1.0.0'];
function get_option($n,$fallback=''){return $GLOBALS['options'][$n]??$fallback;}
function update_option($n,$v,$autoload=false):bool{$GLOBALS['options'][$n]=$v;return true;}
function delete_option($n):bool{unset($GLOBALS['options'][$n]);return true;}
function dbDelta(string $sql):array{$GLOBALS['wpdb']->delta_calls++;return [];}
require dirname(__DIR__).'/includes/class-mkt-governance.php';

assert_same(false,MKT_Governance::schema_ready(),'Partially created tables do not satisfy canonical governance schema.');
MKT_Governance::maybe_upgrade();
assert_true($wpdb->delta_calls===3,'Even previously current wp_options version is rechecked against actual tables.');
assert_true(!array_key_exists('mkt_governance_schema_version',$GLOBALS['options']),'Failed dbDelta cannot retain a false version claim.');
assert_same('schema_verification_failed',$GLOBALS['options']['mkt_governance_schema_error'],'Partial migration records an explicit repair state.');

$wpdb->columns_ok=true;
MKT_Governance::maybe_upgrade();
assert_same('1.0.0',$GLOBALS['options']['mkt_governance_schema_version'],'Schema version is promoted only after all required columns exist.');
assert_true(!isset($GLOBALS['options']['mkt_governance_schema_error']),'Successful repair clears previous migration failure marker.');
assert_same(true,MKT_Governance::schema_ready(),'Repaired actual database state verifies.');

$wpdb->tables_ok=false;
MKT_Governance::maybe_upgrade();
assert_true(!array_key_exists('mkt_governance_schema_version',$GLOBALS['options']),'A missing governance table invalidates an old schema version claim.');
assert_same('schema_verification_failed',$GLOBALS['options']['mkt_governance_schema_error'],'Missing table explicitly records migration failure.');
fwrite(STDOUT,"File18 governance schema exact-deployed verification and option-promotion regressions complete.\n");
