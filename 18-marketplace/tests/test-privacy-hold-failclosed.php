<?php
/** Privacy fail-closed negative tests on actual governance and privacy classes. */
require __DIR__ . '/bootstrap.php';
define('ARRAY_A','ARRAY_A');
function add_filter(...$args):void{}
function add_action(...$args):void{}
function get_user_by($key,$value){return (object)['ID'=>41];}
class MKT_DB {
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
 public static function now():string{return '2026-10-21 00:00:00';}
}
class MKT_Audit {public static function record(...$args):void{$GLOBALS['wpdb']->audits++;}}
class PrivacyFakeDB {
 public string $prefix='wp_';
 public string $last_error='';
 public bool $holds_table=false;
 public array $columns=['object_type','object_public_id','status','expires_at'];
 public ?int $hold_count=0;
 public ?array $seller=null;
 public array $reports=[];
 public bool $fail_seller=false;
 public bool $fail_saves=false;
 public bool $fail_evidence=false;
 public int $writes=0;
 public int $audits=0;
 public function esc_like($s):string{return $s;}
 public function prepare(string $query,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$query),$args);}
 public function get_var(string $sql) {
  if(str_contains($sql,'SHOW TABLES LIKE'))return $this->holds_table?'wp_mkt_retention_holds':null;
  if(str_contains($sql,'COUNT(*) FROM wp_mkt_retention_holds'))return $this->hold_count;
  if(str_contains($sql,'COUNT(*) FROM wp_mkt_offers')||str_contains($sql,'COUNT(*) FROM wp_mkt_deals'))return 0;
  return null;
 }
 public function get_col(string $sql,int $index=0):array{return $this->columns;}
 public function get_row(string $sql,$type):?array{
  if(str_contains($sql,'wp_mkt_sellers'))return $this->seller;
  return null;
 }
 public function get_results(string $sql,$type):array{
  if(str_contains($sql,'wp_mkt_reports'))return $this->reports;
  return [];
 }
 public function update(string $table,array $changes,array $where):int|false {
  $this->writes++;
  if(str_contains($table,'sellers'))return $this->fail_seller?false:1;
  if(str_contains($table,'reports')||str_contains($table,'disputes'))return $this->fail_evidence?false:1;
  return 0;
 }
 public function delete(string $table,array $where):int|false{
  $this->writes++;return $this->fail_saves?false:1;
 }
}
$wpdb=new PrivacyFakeDB();
require dirname(__DIR__).'/includes/class-mkt-governance.php';
require dirname(__DIR__).'/includes/class-mkt-privacy.php';

$bad=MKT_Privacy::erase('person@example.org');
assert_same(false,$bad['done'],'Missing legal-hold table prevents any privacy deletion.');
assert_same(true,$bad['items_retained'],'Unavailable legal-hold truth is retained for repair.');
assert_same(0,$wpdb->writes,'No seller or private record was modified when schema is missing.');
assert_same(true,MKT_Governance::has_active_hold('report','report-a'),'Missing hold table is interpreted as hold unknown, never absent.');

$wpdb->holds_table=true;
$wpdb->columns=['object_type','object_public_id','status']; // Missing expiry.
$bad=MKT_Privacy::erase('person@example.org');
assert_same(false,$bad['done'],'Partial retention-hold schema blocks privacy erasure.');
assert_same(0,$wpdb->writes,'Partial schema cannot change canonical privacy data.');

$wpdb->columns=['object_type','object_public_id','status','expires_at'];
$wpdb->hold_count=null;
assert_same(true,MKT_Governance::has_active_hold('report','report-a'),'Failed/unknown hold COUNT query must fail closed.');
$wpdb->hold_count=0;
assert_same(false,MKT_Governance::has_active_hold('report','report-a'),'Verified zero active holds permits eligible privacy work.');
$wpdb->seller=['id'=>2,'public_id'=>'seller-1','user_id'=>41];
$wpdb->fail_seller=true;
$bad=MKT_Privacy::erase('person@example.org');
assert_same(false,$bad['done'],'Failed seller personal-data write is not declared complete.');
assert_same(0,$wpdb->audits,'No success audit is recorded for failed seller erasure.');
$wpdb->fail_seller=false;
$wpdb->fail_saves=true;
$bad=MKT_Privacy::erase('person@example.org');
assert_same(false,$bad['done'],'Failed saved-items deletion blocks completion.');
$wpdb->fail_saves=false;
$wpdb->reports=[['public_id'=>'report-a']];
$wpdb->fail_evidence=true;
$bad=MKT_Privacy::erase('person@example.org');
assert_same(false,$bad['done'],'Failed evidence minimization blocks success.');
$wpdb->fail_evidence=false;
$ok=MKT_Privacy::erase('person@example.org');
assert_same(true,$ok['done'],'Healthy schema and valid database writes complete eligible erasure.');
assert_true($wpdb->audits > 0,'Completed privacy erasure records an audit.');
fwrite(STDOUT,"File18 legal-hold privacy fail-closed and erasure error tests complete.\n");
