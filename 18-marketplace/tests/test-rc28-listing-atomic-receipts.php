<?php
/** RC28 real Listing owner: direct create/update/submit/transition require atomic state/audit/outbox and final readback. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
defined('DAY_IN_SECONDS')||define('DAY_IN_SECONDS',86400);
function get_current_user_id():int{return 41;}
function current_user_can($cap):bool{return false;}
function get_userdata($id):object{return (object)['display_name'=>'Seller'];}
function sanitize_text_field($x):string{return trim(strip_tags((string)$x));}
function sanitize_title($x):string{return strtolower(str_replace(' ','-',trim((string)$x)));}
function wp_kses_post($x):string{return (string)$x;}
function wp_json_encode($x):string{return json_encode($x);}
function wp_strip_all_tags($x):string{return strip_tags((string)$x);}
function home_url($path):string{return 'https://example.test'.$path;}
class MKT_Auth {
 public static function can($cap,$ctx=[]):bool{return true;}
 public static function seller_eligibility($user):array{return ['eligible'=>true,'assertions'=>['status'=>'active','verified'=>true,'is_minor'=>false,'guardian_verified'=>false,'risk_state'=>'ok','version'=>1]];}
 public static function assertions($id):array{return ['verified'=>true];}
 public static function own_listing($listing,$id):bool{return (int)$listing['seller_user_id']===$id;}
}
class MKT_DB {
 private static int $serial=0;
 public static function uuid():string{return sprintf('00000000-0000-4000-8000-%012d',++self::$serial);}
 public static function now():string{return '2026-10-22 09:00:00';}
 public static function table($t):string{return 'wp_mkt_'.$t;}
 public static function settings():array{return ['safe_mode'=>0,'selling_enabled'=>true,'listing_expiry_days'=>30];}
 public static function transaction(callable $f) {
  global $wpdb;
  $before=[$wpdb->listings,$wpdb->sellers,$wpdb->audit,$wpdb->events,$wpdb->write_count];
  try{
   $result=$f();
   if(is_wp_error($result))[$wpdb->listings,$wpdb->sellers,$wpdb->audit,$wpdb->events,$wpdb->write_count]=$before;
   return $result;
  }catch(Throwable $e){
   [$wpdb->listings,$wpdb->sellers,$wpdb->audit,$wpdb->events,$wpdb->write_count]=$before;
   throw $e;
  }
 }
}
class MKT_Policy {public static function evaluate_listing($data,$assertions):array{return ['valid'=>true,'errors'=>[],'policy_snapshot'=>[],'holds'=>[]];}}
class MKT_Plan_Completion {public static function publication_gate(string $id):bool{return true;}}
class MKT_Governance {public static function recall_publication_guard(string $id):bool{return false;}}
class MKT_State_Machines {public static function assert($kind,$from,$to):bool{return true;}}
class MKT_Contracts {public static function public_listing_fields():array{return ['public_id','title','status','version','category'];}}
class MKT_Audit {
 public static bool $throw=false;
 public static function trace_id():string{return 'rc28-atomic-test';}
 public static function record(...$args):void{
  global $wpdb;
  if(self::$throw)throw new RuntimeException('Injected audit crash after Listing mutation');
  $wpdb->audit[]=$args;
 }
}
class MKT_Events {public static function enqueue(...$args):string{global $wpdb;$wpdb->events[]=$args;return 'event';}}
class ListingDB {
 public int $insert_id=0;
 public int $write_count=0;
 public string $last_error='';
 public string $receipt_mode='normal';
 public array $listings=[];
 public array $sellers=[];
 public array $audit=[];
 public array $events=[];
 public function __construct(){
  $this->sellers[6]=[
   'id'=>6,'public_id'=>'seller-1','user_id'=>41,'status'=>'approved','version'=>1,
   'seller_type'=>'individual','store_name'=>'Seller','public_contact_modes'=>'["file17_chat"]'
  ];
  $this->listings[7]=[
   'id'=>7,'public_id'=>'listing-1','seller_id'=>6,'seller_user_id'=>41,
   'seller_public_id'=>'seller-1','seller_status'=>'approved','store_name'=>'Seller',
   'title'=>'Listing One','slug'=>'listing-one','category'=>'books','listing_type'=>'service',
   'description'=>'One description','price'=>'100','currency'=>'PKR','quantity'=>'1',
   'status'=>'draft','version'=>1,'delivery_modes'=>'[]','contact_modes'=>'["file17_chat"]',
   'created_at'=>'2026-10-22 09:00:00','updated_at'=>'2026-10-22 09:00:00',
   'expires_at'=>'2099-10-22 00:00:00','last_reviewed_at'=>null,'published_at'=>null
  ];
 }
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 private function adjust(?array $row):?array{
  if($row===null||$this->write_count===0)return $row;
  if($this->receipt_mode==='missing')return null;
  if($this->receipt_mode==='wrong_version')$row['version']=999;
  if($this->receipt_mode==='wrong_status')$row['status']='removed';
  if($this->receipt_mode==='wrong_identity')$row['public_id']='other';
  return $row;
 }
 public function get_row(string $sql,$kind):?array{
  if(str_contains($sql,'wp_mkt_sellers') && !str_contains($sql,'wp_mkt_listings'))return $this->sellers[6]??null;
  if(str_contains($sql,'wp_mkt_listings')){
   $row=null;
   if(preg_match('/WHERE l.id=(\d+)/',$sql,$m))$row=$this->listings[(int)$m[1]]??null;
   elseif(preg_match("/WHERE l.public_id='([^']+)'/",$sql,$m))
    foreach($this->listings as $item)if($item['public_id']===$m[1])$row=$item;
   if($row&&isset($this->sellers[(int)$row['seller_id']]))
     $row=array_merge($row,[
      'seller_user_id'=>(int)$this->sellers[(int)$row['seller_id']]['user_id'],
      'seller_public_id'=>(string)$this->sellers[(int)$row['seller_id']]['public_id'],
      'seller_status'=>(string)$this->sellers[(int)$row['seller_id']]['status'],
      'store_name'=>(string)$this->sellers[(int)$row['seller_id']]['store_name']
     ]);
   return $this->adjust($row);
  }
  return null;
 }
 public function get_var(string $sql):string{return '0';}
 public function get_results(string $sql,$mode):array{return [];}
 public function insert(string $table,array $data):int{
  $this->write_count++;
  if($table==='wp_mkt_listings'){
   $this->insert_id=22;$this->listings[22]=array_merge($this->listings[7],$data,['id'=>22]);return 1;
  }
  throw new RuntimeException('unexpected insert');
 }
 public function update(string $table,array $data,array $where):int{
  $this->write_count++;
  if($table==='wp_mkt_sellers'){$this->sellers[(int)$where['id']]=array_merge($this->sellers[(int)$where['id']],$data);return 1;}
  if($table==='wp_mkt_listings'){$this->listings[(int)$where['id']]=array_merge($this->listings[(int)$where['id']],$data);return 1;}
  throw new RuntimeException('Unexpected update');
 }
}
require dirname(__DIR__).'/includes/class-mkt-listings.php';
$input=['title'=>'A complete listing','category'=>'books','listing_type'=>'service','description'=>'Description','price'=>100,'quantity'=>1,'currency'=>'PKR'];
function run_case(string $type,string $mode):void{
 global $wpdb,$input;
 $wpdb=new ListingDB();MKT_Audit::$throw=($mode==='audit_crash');
 $wpdb->receipt_mode=$mode==='audit_crash'?'normal':$mode;
 if($type==='transition')$wpdb->listings[7]['status']='active';
 $before=[$wpdb->listings,$wpdb->sellers,$wpdb->audit,$wpdb->events];
 if($type==='create'){$r=MKT_Listings::create($input,41);$err='mkt_listing_create_readback_failed';}
 elseif($type==='update'){$r=MKT_Listings::update('listing-1',$input,41,1);$err='mkt_listing_update_readback_failed';}
 elseif($type==='submit'){$r=MKT_Listings::submit('listing-1',41,1);$err='mkt_listing_submit_readback_failed';}
 else {$r=MKT_Listings::transition('listing-1','paused',41,1);$err='mkt_listing_transition_readback_failed';}
 if($mode==='normal'){
   assert_true(is_array($r),$type.' normal authorized owner command succeeds.');
   return;
 }
 assert_true(is_wp_error($r),$type.' must deny '.$mode.' without partial owner commit.');
 if($mode!=='audit_crash')assert_same($err,$r->get_error_code(),$type.' has explicit final readback denial '.$mode);
 assert_same($before[0],$wpdb->listings,$type.' listing records roll back on '.$mode);
 assert_same($before[1],$wpdb->sellers,$type.' seller owner projection rolls back on '.$mode);
 assert_same($before[2],$wpdb->audit,$type.' audit facts roll back on '.$mode);
 assert_same($before[3],$wpdb->events,$type.' notification outbox rolls back on '.$mode);
}
foreach(['create','update','submit','transition'] as $kind)
 foreach(['normal','audit_crash','missing','wrong_status','wrong_version','wrong_identity'] as $mode)
  run_case($kind,$mode);
fwrite(STDOUT,"RC28 actual Listing owner create/update/submit/transition atomicity and readback failure protections passed.\n");