<?php
/** RC31 actual-class File18/File26 owner connector regression; fake WP/DB only. */
declare(strict_types=1);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
class WP_Error {
    private string $c;
    public function __construct(string $code, string $message=''){$this->c=$code;}
    public function get_error_code(): string{return $this->c;}
}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
function sanitize_text_field($v):string{return strip_tags((string)$v);}
function esc_url_raw($v):string{return (string)$v;}
function wp_http_validate_url($v):bool{
    $p=parse_url((string)$v);
    return is_array($p)&&($p['scheme']??'')==='https'&&($p['host']??'')==='sabrihomeopathy.com';
}
$GLOBALS['registered_filters']=[];
$GLOBALS['eager_registry_calls']=0;
function add_filter(...$a):void{$GLOBALS['registered_filters'][]=$a[0]??'';}
function add_action(...$a):void{}
function sabri_file26_register_connector($manifest):bool{
    $GLOBALS['eager_registry_calls']++;
    return true;
}
function get_option($n,$d=false){return $n==='mkt_safe_mode'?$GLOBALS['safe_mode']:$d;}
$GLOBALS['safe_mode']=true;
$GLOBALS['schema_ready']=true;
$GLOBALS['checks']=0;
function ok($yes,string $why):void{
    $GLOBALS['checks']++;
    if(!$yes)throw new RuntimeException('FAIL: '.$why);
}
class StubDB {
    public string $last_error='';
    public array $rows=[];
    public bool $sql_failure=false;
    public string $lookup_failure='';
    public string $dto_failure='';
    public function prepare($sql,...$args):string{return vsprintf($sql,$args);}
    public function get_results($sql,$mode=null){
        if($this->sql_failure){$this->last_error='injected failure';return null;}
        preg_match('/\bid>(\d+)/',$sql,$a);
        preg_match('/\bLIMIT (\d+)/',$sql,$b);
        $after=(int)($a[1]??0);$limit=(int)($b[1]??50);$found=[];
        foreach($this->rows as $row){
            if($row['id']>$after)$found[]=['id'=>$row['id'],'public_id'=>$row['public_id'],'version'=>$row['version']];
        }
        return array_slice($found,0,$limit);
    }
}
class MKT_DB {
    public static function schema_ready():bool{return $GLOBALS['schema_ready'];}
    public static function table($n):string{return 'wp_mkt_'.$n;}
}
class MKT_Listings {
    public static function not_expired(?string $date):bool{return $date!==null&&strtotime($date.' UTC')>time();}
    public static function get(string $id,bool $private=false):?array{
        global $wpdb;
        if($wpdb->lookup_failure===$id){$wpdb->last_error='injected owner failure';return null;}
        foreach($wpdb->rows as $row){
            if($row['public_id']!==$id)continue;
            if(!$private&&($row['status']!=='active'||$row['seller_status']!=='approved'
               ||$row['recalled']||!self::not_expired($row['expires_at'])))return null;
            return $row;
        }
        return null;
    }
    public static function public_dto(array $row):array{
        global $wpdb;
        if($wpdb->dto_failure===$row['public_id'])throw new RuntimeException('media unavailable');
        return [
            'title'=>$row['title'],
            'url'=>'https://sabrihomeopathy.com/marketplace/listing/'.$row['public_id'].'/',
            'category'=>'books','location_city'=>'Gujrat','language'=>'ur',
            'private_patient_document'=>'must-not-index'
        ];
    }
}
function row(int $id,string $status='active',string $seller='approved'):array{
    return ['id'=>$id,'public_id'=>sprintf('00000000-0000-4000-8000-%012d',$id),
        'version'=>1,'status'=>$status,'seller_status'=>$seller,
        'expires_at'=>'2099-01-01 00:00:00','title'=>'Listing '.$id,'recalled'=>false];
}
$wpdb=new StubDB();
$wpdb->rows=[row(1),row(2,'draft'),row(3),array_merge(row(4),['recalled'=>true]),row(5,'active','suspended')];
require dirname(__DIR__).'/includes/class-mkt-file26-connector.php';
MKT_File26_Connector::register();
ok($GLOBALS['eager_registry_calls']===0,'File26 schema-unverified eager registration prohibited');
ok($GLOBALS['registered_filters']===['sabri_file26_connector_manifests','mkt_required_event_consumers_acknowledged','mkt_file26_event_acknowledged'],'Manifest plus explicit receipt filters only');
$m=MKT_File26_Connector::manifest();
ok($m['status']==='proposed','connector remains unapproved');
ok(str_contains((string)($m['event_contract']??''),'pending-file26-cutover'),'File26 cutover must remain pending');
ok($GLOBALS['safe_mode']===true,'Safe Mode fixture');
$r=MKT_File26_Connector::list_batch('',2);
ok(is_array($r),'safe public browsing is retained in Safe Mode');
ok(count($r['items'])===1,'private draft excluded');
ok($r['next_cursor']==='2','nonpublic item still advances cursor');
ok(!isset($r['items'][0]['private_patient_document']),'no private data');
$r=MKT_File26_Connector::list_batch('2',2);
ok(is_array($r)&&count($r['items'])===1&&$r['next_cursor']==='4','recalled listing excluded');
$r=MKT_File26_Connector::list_batch('4',50);
ok(is_array($r)&&$r['done']===true&&count($r['items'])===0,'suspended seller excluded');
$d=MKT_File26_Connector::fetch_object('marketplace_listing',$wpdb->rows[0]['public_id']);
ok(is_array($d)&&MKT_File26_Connector::can_view($d),'fresh public owner reads');
$d['object_version']=0;
ok(!MKT_File26_Connector::can_view($d),'stale version denied');
ok(MKT_File26_Connector::fetch_object('marketplace_listing',$wpdb->rows[1]['public_id'])===null,'draft denied');
ok(MKT_File26_Connector::fetch_object('marketplace_listing',$wpdb->rows[3]['public_id'])===null,'recall denied');
ok(is_wp_error(MKT_File26_Connector::list_batch('bad-cursor',50)),'invalid cursor denied');
$wpdb->sql_failure=true;
$r=MKT_File26_Connector::list_batch('',50);
ok(is_wp_error($r)&&$r->get_error_code()==='file18_owner_unreadable','failed SQL never empty-success');
$wpdb->sql_failure=false;
$wpdb->lookup_failure=$wpdb->rows[0]['public_id'];
$r=MKT_File26_Connector::list_batch('',50);
ok(is_wp_error($r)&&$r->get_error_code()==='file18_owner_unreadable','failed owner lookup never partial-success');
$wpdb->lookup_failure='';
$wpdb->dto_failure=$wpdb->rows[0]['public_id'];
$r=MKT_File26_Connector::list_batch('',50);
ok(is_wp_error($r)&&$r->get_error_code()==='file18_search_projection_unavailable','media exception never partial-success');
ok(MKT_File26_Connector::fetch_object('marketplace_listing',$wpdb->rows[0]['public_id'])===null,'direct fetch fails closed');
$wpdb->dto_failure='';
$GLOBALS['schema_ready']=false;
$r=MKT_File26_Connector::list_batch('',50);
ok(is_wp_error($r)&&$r->get_error_code()==='file18_owner_schema_unverified','missing schema fails closed');
ok(MKT_File26_Connector::health()['state']==='degraded','schema failure reflects degraded health');
$GLOBALS['schema_ready']=true;
ok(MKT_File26_Connector::health()['source_event_cutover_approved']===false,'no false cutover');
echo 'PASS RC31: '.$GLOBALS['checks']." source-contract assertions\n";
