<?php
/** Real File18 privacy exporter, injected WordPress DB SELECT failures and missing owner evidence. */
require __DIR__.'/bootstrap.php';
define('ARRAY_A','ARRAY_A');
function add_filter(...$args):void{}
function add_action(...$args):void{}
function get_user_by(string $type,string $email){return (object)['ID'=>41];}
function wp_json_encode($v):string{return json_encode($v);}
class MKT_DB {public static function table(string $n):string{return 'wp_mkt_'.$n;}}
class MKT_Governance {
 public static bool $ready=true;
 public static function schema_ready():bool{return self::$ready;}
 public static function table(string $name):string{return 'wp_mkt_'.$name;}
}
class ExportDB {
 public string $last_error='';
 public string $fail='';
 public bool $null_result=false;
 public int $queries=0;
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%d','%s'],['%d',"'%s'"],$sql),$args);}
 public function get_row(string $sql,$style) {
  $this->queries++;
  if($this->fail==='seller'){$this->last_error='Injected seller SELECT outage';return null;}
  return ['public_id'=>'seller-41','user_id'=>41,'eligibility_snapshot'=>'DO_NOT_EXPORT','store_name'=>'Store'];
 }
 public function get_results(string $sql,$style):?array {
  $this->queries++;
  $name='';
  foreach(['listing_evidence','listings','offers','deals','reports','disputes'] as $n){
    if(str_contains($sql,'FROM wp_mkt_'.$n) || str_contains($sql,'FROM wp_mkt_'.$n.' ')){$name=$n;break;}
  }
  if($name===$this->fail){
    if(!$this->null_result)$this->last_error='Injected '.$name.' query error';
    return null;
  }
  if($name==='listing_evidence')return [['id'=>12,'listing_public_id'=>'listing-1','status'=>'approved']];
  if($name==='listings')return [['public_id'=>'listing-1','title'=>'Listing','policy_snapshot'=>'internal']];
  return [];
 }
}
$wpdb=new ExportDB();
require dirname(__DIR__).'/includes/class-mkt-privacy.php';

foreach(['seller','listings','offers','deals','reports','disputes','listing_evidence'] as $name){
 $wpdb->fail=$name;$wpdb->null_result=false;
 $result=MKT_Privacy::export('user@example.org');
 assert_same(false,$result['done'],'Failed '.$name.' SELECT must not complete personal export.');
 assert_same([], $result['data'],'Failed '.$name.' SELECT must not return a deceptively partial data bundle.');
 assert_same('mkt_export_source_unavailable',$result['error'],'Failed '.$name.' query exposes safe machine-coded failure.');
}
$wpdb->fail='reports';$wpdb->null_result=true;
$result=MKT_Privacy::export('user@example.org');
assert_same(false,$result['done'],'Null results without SQL last_error still fail closed.');
$wpdb->fail='';$wpdb->null_result=false;
MKT_Governance::$ready=false;
$result=MKT_Privacy::export('user@example.org');
assert_same(false,$result['done'],'Absent/partial governance evidence schema prevents false-complete export.');
MKT_Governance::$ready=true;
$ok=MKT_Privacy::export('user@example.org');
assert_same(true,$ok['done'],'Complete required table results can finish a privacy export.');
assert_same(3,count($ok['data']),'Known seller, listing and evidence are included on a complete export.');
assert_true(!str_contains(json_encode($ok),'DO_NOT_EXPORT'),'Seller identity eligibility snapshot is not exported.');
assert_true(!str_contains(json_encode($ok),'internal'),'Listing internal policy projection is not exported.');
fwrite(STDOUT,"File18 privacy export SQL-source completeness and legal evidence regression complete.\n");