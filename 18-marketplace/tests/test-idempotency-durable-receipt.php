<?php
/** Fault-injection for File18 durable idempotency receipts. */
require __DIR__.'/bootstrap.php';
define('DAY_IN_SECONDS',86400);
define('ARRAY_A','ARRAY_A');
function sanitize_text_field($x):string{return (string)$x;}
function wp_json_encode($x):string{return json_encode($x);}
function get_current_user_id():int{return 41;}
class MKT_DB {public static function table($n){return 'wp_mkt_'.$n;}public static function now(){return '2026-10-21 00:00:00';}}
class FakeIdempotencyDB {
 public int|false $receipt_result=1;
 public array $updates=[];
 public bool $already_exists=false;
 public ?array $existing=null;
 public function prepare($sql,...$args):string {return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function query($sql){return $this->already_exists&&str_starts_with($sql,'INSERT IGNORE')?0:1;}
 public function update($table,$data,$where) {$this->updates[]=['data'=>$data,'where'=>$where];return $this->receipt_result;}
 public function get_row($query,$type){return $this->existing;}
}
$wpdb=new FakeIdempotencyDB();
require dirname(__DIR__).'/includes/class-mkt-idempotency.php';
$wpdb->receipt_result=false;
$result=MKT_Idempotency::run('listing_create','created-object-abcdef0123',['title'=>'Textbook'],static fn()=>['public_id'=>'created']);
assert_true(is_wp_error($result),'Failed durable completion marker cannot be presented as success.');
assert_same('mkt_idempotency_receipt_failed',$result->get_error_code(),'Idempotency write failure uses an explicit machine-readable error.');
assert_same('processing',$wpdb->updates[0]['where']['status'],'Completion CAS updates only the reserved processing lease.');
$wpdb->updates=[];
$failed=MKT_Idempotency::run('listing_create','invalid-object-abcdef0123',['title'=>'Forbidden'],static fn()=>new WP_Error('mkt_prohibited'));
assert_true(is_wp_error($failed),'Failed durable error receipt also fails closed.');
assert_same('mkt_idempotency_receipt_failed',$failed->get_error_code(),'Write failure does not falsely report persisted command error.');
$wpdb->receipt_result=1;
$success=MKT_Idempotency::run('listing_create','valid-object-abcdef0123',['title'=>'Book'],static fn()=>['public_id'=>'ok']);
assert_same(['public_id'=>'ok'],$success,'Normal successful durable receipt preserves command result.');
$error=MKT_Idempotency::run('listing_create','failed-object-abcdef0123',['title'=>'Bad'],static fn()=>new WP_Error('mkt_invalid'));
assert_same('mkt_invalid',$error->get_error_code(),'Normal durable failure preserves domain error.');
$wpdb->already_exists=true;
$hash=hash('sha256',json_encode(['title'=>'Book']));
$wpdb->existing=['id'=>71,'request_hash'=>$hash,'status'=>'completed','response_json'=>'{malformed'];
$called=0;
$bad=MKT_Idempotency::run('listing_create','cached-invalid-abcdef123',['title'=>'Book'],static function() use (&$called){$called++;return true;});
assert_same('mkt_idempotency_receipt_corrupt',$bad->get_error_code(),'Malformed completed receipt never silently turns into success.');
assert_same(0,$called,'Malformed cached receipt does not invoke owner mutation.');
$wpdb->existing['response_json']='{"public_id":"book-safe"}';
$ok=MKT_Idempotency::run('listing_create','cached-valid-abcdef123',['title'=>'Book'],static function()use(&$called){$called++;return false;});
assert_same(['public_id'=>'book-safe'],$ok,'Valid completed receipt is returned without replay.');
assert_same(0,$called,'Valid cached receipt never invokes owner mutation.');
$wpdb->existing=['id'=>71,'request_hash'=>$hash,'status'=>'processing','expires_at'=>'2020-01-01 00:00:00'];
$wpdb->updates=[];
$uncertain=MKT_Idempotency::run('listing_create','old-reservation-abcdef123',['title'=>'Book'],static function()use(&$called){$called++;return true;});
assert_same('mkt_idempotency_outcome_uncertain',$uncertain->get_error_code(),'Expired uncertain mutation blocks unsafe blind replay.');
assert_same(0,$called,'Uncertain stale reservation cannot execute the mutation again.');
assert_same([],$wpdb->updates,'No completion or failure receipt is forged for an uncertain prior result.');
$wpdb->already_exists=false;
fwrite(STDOUT,"File18 durable idempotency acknowledgment negative regression complete.\n");
