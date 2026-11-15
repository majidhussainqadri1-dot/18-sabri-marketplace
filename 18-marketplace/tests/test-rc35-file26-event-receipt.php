<?php
/** RC35 actual File18 File26-bridge class under fake WP/DB; not a live cutover. */
declare(strict_types=1);
if(!defined('ABSPATH'))define('ABSPATH',__DIR__);
if(!defined('ARRAY_A'))define('ARRAY_A','ARRAY_A');
class WP_Error{public function __construct(private string $code){}}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
function sanitize_text_field($v):string{return strip_tags((string)$v);}
function esc_url_raw($v):string{return (string)$v;}
function wp_http_validate_url($v):bool{return str_starts_with((string)$v,'https://sabrihomeopathy.com/');}
function add_filter(...$args):void{}
function add_action(...$args):void{}
function get_option($key,$default=false){return $default;}
class FakeFile26 {
    public static bool $active=false;
    public static function instance():self{return new self;}
    public function connectors():self{return $this;}
    public function is_active(string $slug):bool{return self::$active&&$slug==='file18-public-listings';}
}
class_alias('FakeFile26','Sabri\\File26\\Plugin');
class MKT_DB{public static bool $ready=true;public static function schema_ready(){return self::$ready;}}
class TestDB{public string $last_error='';public bool $fail_owner=false;public bool $fail_public=false;public bool $fail_media=false;}
class MKT_Listings{
    public static array $row=[];
    public static function get(string $id,bool $private=false):?array{
        global $wpdb;
        if($private&&$wpdb->fail_owner){$wpdb->last_error='owner read error';return null;}
        if(!$private&&$wpdb->fail_public){$wpdb->last_error='public read error';return null;}
        $r=self::$row;
        if($id!==($r['public_id']??''))return null;
        if(!$private&&($r['status']!=='active'||$r['seller_status']!=='approved'
           ||$r['recalled']||!self::not_expired($r['expires_at'])))return null;
        return $r;
    }
    public static function not_expired(?string $date):bool{return $date&&strtotime($date.' UTC')>time();}
    public static function public_dto(array $row):array{
        global $wpdb;
        if($wpdb->fail_media)throw new RuntimeException('media not readable');
        return ['title'=>'Approved listing','url'=>'https://sabrihomeopathy.com/marketplace/listing/'.$row['public_id'].'/slug/',
            'category'=>'books','location_city'=>'Gujrat','language'=>'ur','secret'=>'must-never-copy'];
    }
}
$wpdb=new TestDB();$checks=0;$upserts=[];$tombstones=[];$fail_delivery=false;
function check_ok($yes,string $msg):void{global $checks;$checks++;if(!$yes)throw new RuntimeException('FAIL: '.$msg);}
function sabri_file26_upsert_document(array $doc){
    global $upserts,$fail_delivery;$upserts[]=$doc;
    return $fail_delivery?new WP_Error('write_error'):'canonical-key';
}
function sabri_file26_tombstone_document($slug,$domain,$id,$version,$reason){
    global $tombstones,$fail_delivery;$tombstones[]=compact('slug','domain','id','version','reason');return !$fail_delivery;
}
require dirname(__DIR__).'/includes/class-mkt-file26-connector.php';
$id='00000000-0000-4000-8000-000000000034';
MKT_Listings::$row=['public_id'=>$id,'version'=>7,'status'=>'active','seller_status'=>'approved','recalled'=>false,'expires_at'=>'2099-01-01 00:00:00'];
$event=['event_id'=>'11111111-1111-4111-8111-111111111111',
    'event_type'=>'MarketplaceListingPublished.v1','aggregate'=>['type'=>'listing','public_id'=>$id]];
MKT_File26_Connector::on_outbox_event($event);
check_ok(count($upserts)===0,'Proposed connector remains inert');
check_ok(MKT_File26_Connector::require_ack(true,$event),'Proposed connector does not change unrelated ACKs');
FakeFile26::$active=true;
MKT_File26_Connector::on_outbox_event($event);
check_ok(count($upserts)===1&&$upserts[0]['object_version']===7,'Active owner version projected');
check_ok(!isset($upserts[0]['secret']),'Private fields never indexed');
check_ok(MKT_File26_Connector::owner_ack(false,$event),'File26 confirmed delivery is a valid domain ACK');
check_ok(MKT_File26_Connector::require_ack(true,$event),'Verified upsert ACK');
check_ok(!MKT_File26_Connector::owner_ack(false,$event),'Consumed delivery receipt cannot be replayed');
check_ok(!MKT_File26_Connector::require_ack(true,$event),'Replay without action does not falsely ACK');
MKT_Listings::$row['status']='paused';MKT_Listings::$row['version']=8;
$event['event_id']='22222222-2222-4222-8222-222222222222';
$event['event_type']='MarketplaceListingStatusChanged.v1';
MKT_File26_Connector::on_outbox_event($event);
check_ok(count($tombstones)===1&&$tombstones[0]['version']===8,'Paused listing versioned tombstone');
check_ok(MKT_File26_Connector::require_ack(true,$event),'Tombstone ACK');
MKT_Listings::$row['status']='active';MKT_Listings::$row['recalled']=true;MKT_Listings::$row['version']=9;
$event['event_id']='33333333-3333-4333-8333-333333333333';
$event['event_type']='MarketplaceRecallActivated.v1';
MKT_File26_Connector::on_outbox_event($event);
check_ok(count($tombstones)===2,'Recall revokes search document');
check_ok(MKT_File26_Connector::require_ack(true,$event),'Recall receives positive receipt');
MKT_Listings::$row['recalled']=false;
$wpdb->fail_owner=true;$event['event_id']='44444444-4444-4444-8444-444444444444';
MKT_File26_Connector::on_outbox_event($event);
check_ok(!MKT_File26_Connector::require_ack(true,$event),'Owner SQL failure denies ACK');
$wpdb->fail_owner=false;$wpdb->fail_public=true;$event['event_id']='55555555-5555-4555-8555-555555555555';
MKT_File26_Connector::on_outbox_event($event);
check_ok(!MKT_File26_Connector::require_ack(true,$event),'Public policy SQL failure denies ACK');
$wpdb->fail_public=false;$wpdb->fail_media=true;$event['event_id']='66666666-6666-4666-8666-666666666666';
MKT_File26_Connector::on_outbox_event($event);
check_ok(!MKT_File26_Connector::require_ack(true,$event),'Media projection failure denies ACK');
$wpdb->fail_media=false;$fail_delivery=true;$event['event_id']='77777777-7777-4777-8777-777777777777';
MKT_File26_Connector::on_outbox_event($event);
check_ok(!MKT_File26_Connector::require_ack(true,$event),'File26 write failure denies ACK');
$fail_delivery=false;$event['event_id']='88888888-8888-4888-8888-888888888888';
MKT_File26_Connector::on_outbox_event($event);
check_ok(!MKT_File26_Connector::require_ack(false,$event),'Cannot overwrite another required consumer denial');
$other=['event_id'=>'other','event_type'=>'MarketplaceOfferCreated.v1','aggregate'=>['type'=>'offer','public_id'=>$id]];
check_ok(MKT_File26_Connector::require_ack(true,$other),'Unrelated private offer event ignored');
MKT_DB::$ready=false;$event['event_id']='99999999-9999-4999-8999-999999999999';
MKT_File26_Connector::on_outbox_event($event);
check_ok(!MKT_File26_Connector::require_ack(true,$event),'Unverified owner schema denies ACK');
echo 'PASS RC35: '.$checks." File26 owner event and tombstone assertions\n";
