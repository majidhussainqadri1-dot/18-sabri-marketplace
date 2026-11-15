<?php
/** RC36 actual File26 connector + fake DB, bounded cursor and SQL failure regression. */
declare(strict_types=1);
if(!defined('ABSPATH'))define('ABSPATH',__DIR__);
if(!defined('ARRAY_A'))define('ARRAY_A','ARRAY_A');
class WP_Error{public function __construct(private string $name){}public function get_error_code():string{return $this->name;}}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
function sanitize_text_field($v):string{return strip_tags((string)$v);}
function esc_url_raw($v):string{return (string)$v;}
function wp_http_validate_url($v):bool{return str_starts_with((string)$v,'https://sabrihomeopathy.com/');}
function add_filter(...$args):void{}
function add_action(...$args):void{}
$GLOBALS['options']=[];
$GLOBALS['scheduled']=false;$GLOBALS['schedule_calls']=0;
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=false):bool{
    if($GLOBALS['block_options']??false)return false;
    $GLOBALS['options'][$key]=$value;return true;
}
function wp_next_scheduled($hook){return $GLOBALS['scheduled']?time()+300:false;}
function wp_schedule_single_event($time,$hook):bool{$GLOBALS['scheduled']=true;$GLOBALS['schedule_calls']++;return true;}
class FakeFile26 {
    public static bool $active=false;
    public static function instance():self{return new self;}
    public function connectors():self{return $this;}
    public function is_active($slug):bool{return self::$active&&$slug==='file18-public-listings';}
}
class_alias('FakeFile26','Sabri\\File26\\Plugin');
class MKT_DB {
    public static bool $ready=true;
    public static function schema_ready():bool{return self::$ready;}
    public static function table($name):string{return 'wp_mkt_'.$name;}
}
class MockDB {
    public string $last_error='';
    public bool $lock_available=true;
    public bool $read_failed=false;
    public array $rows=[];
    public function prepare($sql,...$args):string{return vsprintf((string)$sql,$args);}
    public function get_var($sql){
        if(str_contains($sql,'GET_LOCK'))return $this->lock_available?'1':'0';
        if(str_contains($sql,'RELEASE_LOCK'))return '1';
        return null;
    }
    public function get_results($sql,$type=null){
        if($this->read_failed){$this->last_error='query failure';return null;}
        preg_match('/id>([0-9]+)/',(string)$sql,$c);
        preg_match('/LIMIT ([0-9]+)/',(string)$sql,$l);
        $result=[];
        foreach($this->rows as $r)if($r['id']>(int)($c[1]??0))$result[]=['id'=>$r['id'],'public_id'=>$r['public_id']];
        return array_slice($result,0,(int)($l[1]??51));
    }
}
class MKT_Listings{
    public static array $rows=[];
    public static function get(string $id,bool $private=false):?array{
        foreach(self::$rows as $r){
            if($r['public_id']!==$id)continue;
            if(!$private&&($r['status']!=='active'||$r['seller_status']!=='approved'
                ||$r['recalled']||!self::not_expired($r['expires_at'])))return null;
            return $r;
        }
        return null;
    }
    public static function not_expired(?string $date):bool{return (bool)$date&&strtotime($date.' UTC')>time();}
    public static function public_dto(array $r):array{return [
        'title'=>'Safe listing '.$r['id'],
        'url'=>'https://sabrihomeopathy.com/marketplace/listing/'.$r['public_id'].'/example/',
        'category'=>'books','location_city'=>'Gujrat','language'=>'ur'];}
}
$wpdb=new MockDB();$upserts=[];$tombstones=[];$fail_writes=false;$checks=0;
function assert_ok($yes,$m):void{global $checks;$checks++;if(!$yes)throw new RuntimeException('FAIL: '.$m);}
function sabri_file26_upsert_document($doc){global $upserts,$fail_writes;$upserts[]=$doc;return $fail_writes?new WP_Error('failed'):'key';}
function sabri_file26_tombstone_document($slug,$domain,$id,$version,$reason){global $tombstones,$fail_writes;$tombstones[]=compact('id','version','reason');return !$fail_writes;}
function row(int $n,$state):array{return [
    'id'=>$n,'public_id'=>sprintf('00000000-0000-4000-8000-%012d',$n),
    'version'=>$n,'status'=>$state,'seller_status'=>'approved','recalled'=>false,
    'expires_at'=>'2099-01-01 00:00:00',
];}
$wpdb->rows=[row(1,'active'),row(2,'paused'),row(3,'active')];
MKT_Listings::$rows=$wpdb->rows;
require dirname(__DIR__).'/includes/class-mkt-file26-connector.php';
MKT_File26_Connector::maybe_schedule_reconcile();
assert_ok($GLOBALS['schedule_calls']===0,'Proposed connector schedules no background jobs');
FakeFile26::$active=true;
MKT_File26_Connector::maybe_schedule_reconcile();
assert_ok($GLOBALS['schedule_calls']===1,'Active connector schedules bounded owner sweep');
MKT_File26_Connector::maybe_schedule_reconcile();
assert_ok($GLOBALS['schedule_calls']===1,'Duplicate scheduling prevented');
$ok=MKT_File26_Connector::reconcile_batch();
assert_ok(is_array($ok)&&$ok['processed']===3&&$ok['done']===true,'Three owner rows reconciled');
assert_ok(count($upserts)===2,'Active safe listings indexed');
assert_ok(count($tombstones)===1,'Paused listing tombstoned');
assert_ok($GLOBALS['options']['mkt_file26_reconcile_cursor']==='0','Cursor resets at verified end of owner scan');
$GLOBALS['scheduled']=false;$wpdb->read_failed=true;
$r=MKT_File26_Connector::reconcile_batch();
assert_ok(is_wp_error($r)&&$r->get_error_code()==='file18_file26_reconcile_sql_unavailable','Source SQL failure returns error');
assert_ok($GLOBALS['options']['mkt_file26_reconcile_cursor']==='0','Source failure never advances cursor');
assert_ok($GLOBALS['schedule_calls']===2,'Reconciliation retries are scheduled');
$wpdb->read_failed=false;$fail_writes=true;
$r=MKT_File26_Connector::reconcile_batch();
assert_ok(is_wp_error($r)&&$r->get_error_code()==='file18_file26_reconcile_delivery_failed','Target search failure denied');
assert_ok($GLOBALS['options']['mkt_file26_reconcile_cursor']==='0','Target write failure does not advance cursor');
$fail_writes=false;$GLOBALS['options']['mkt_file26_reconcile_cursor']='1';$GLOBALS['block_options']=true;
$r=MKT_File26_Connector::reconcile_batch();
assert_ok(is_wp_error($r)&&$r->get_error_code()==='file18_file26_reconcile_checkpoint_failed','Cursor persistence failure rejected');
$GLOBALS['block_options']=false;
$wpdb->lock_available=false;
$GLOBALS['scheduled']=false; // cron consumed the single-shot event
$prior_calls=$GLOBALS['schedule_calls'];
$r=MKT_File26_Connector::reconcile_batch();
assert_ok(is_wp_error($r)&&$r->get_error_code()==='file18_file26_reconcile_locked','Concurrent worker denied without touching listing source');
assert_ok($GLOBALS['schedule_calls']===$prior_calls+1,'Contended one-shot worker schedules its next safe attempt');
$wpdb->lock_available=true;
$GLOBALS['options']['mkt_file26_reconcile_cursor']='bad!';
$r=MKT_File26_Connector::reconcile_batch();
assert_ok(is_wp_error($r)&&$r->get_error_code()==='file18_file26_reconcile_cursor_invalid','Tampered owner cursor denied');
$GLOBALS['options']['mkt_file26_reconcile_cursor']='0';
MKT_DB::$ready=false;
$GLOBALS['scheduled']=false; // cron event removed before schema check
$prior_calls=$GLOBALS['schedule_calls'];
$r=MKT_File26_Connector::reconcile_batch();
assert_ok(is_wp_error($r)&&$r->get_error_code()==='file18_file26_reconcile_inactive','Schema failure keeps source sweeper disabled');
assert_ok($GLOBALS['schedule_calls']===$prior_calls+1,'Transient owner migration failure reschedules read-only reconciliation');
assert_ok($GLOBALS['options']['mkt_file26_reconcile_cursor']==='0','Early schema denial preserves the last valid checkpoint');
echo 'PASS RC36: '.$checks." bounded owner reconciliation assertions\n";
