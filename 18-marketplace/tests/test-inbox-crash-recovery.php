<?php
/** Runtime-shaped Inbox crash recovery, dedupe, transactional row-lock simulation. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
function sanitize_text_field($x): string{return (string)$x;}
function wp_json_encode($x):string{return json_encode($x);}
class MKT_DB {
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function now():string{return gmdate('Y-m-d H:i:s');}
 public static function transaction(callable $fn) {
  global $wpdb;
  $wpdb->ops[]='START TRANSACTION';
  $snapshot=$wpdb->row;
  try{
   $v=$fn();
   if(is_wp_error($v)||$v===false){$wpdb->row=$snapshot;$wpdb->ops[]='ROLLBACK';return $v;}
   $wpdb->ops[]='COMMIT';return $v;
  }catch(Throwable $e){$wpdb->row=$snapshot;$wpdb->ops[]='ROLLBACK';throw $e;}
 }
}
final class FakeInboxDB {
 public ?array $row=null;
 public array $ops=[];
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function query(string $sql):int|false {
  if(str_contains($sql,'INSERT IGNORE INTO wp_mkt_inbox')){
   $this->ops[]='INSERT_RESERVATION';
   if($this->row)return 0;
   $this->row=['id'=>1,'source'=>'identity','event_id'=>'evt-a','event_type'=>'SellerSuspended.v1','payload_hash'=>$GLOBALS['expected_hash'],'status'=>'received','received_at'=>gmdate('Y-m-d H:i:s')];
   return 1;
  }
  if(str_contains($sql,'SET received_at=')&&str_contains($sql,"status='received'")) {
   $this->ops[]='RECLAIM_STALE';
   if(($this->row['status']??'')!=='received')return 0;
   $this->row['received_at']=gmdate('Y-m-d H:i:s');return 1;
  }
  if(str_contains($sql,"SET status='received'")){$this->ops[]='RECLAIM_FAILED';$this->row['status']='received';return 1;}
  return 1;
 }
 public function get_row(string $sql,$type) {
  $this->ops[]=str_contains($sql,'FOR UPDATE')?'LOCK_FOR_UPDATE':'READ_RESERVATION';
  return $this->row;
 }
 public function update(string $table,array $data,array $where):int {
  $this->ops[]='MARK_'.strtoupper($data['status']??'UNKNOWN');
  if(($this->row['status']??'')!==($where['status']??'received'))return 0;
  $this->row=array_merge($this->row,$data);return 1;
 }
}
$wpdb=new FakeInboxDB();
require dirname(__DIR__).'/includes/class-mkt-events.php';
$event=['event_id'=>'evt-a','event_type'=>'SellerSuspended.v1','payload'=>['user_id'=>101]];
$GLOBALS['expected_hash']=hash('sha256',json_encode($event));
$called=0;
$handler=function($v) use (&$called,$wpdb) {
 assert_true(in_array('LOCK_FOR_UPDATE',$wpdb->ops,true),'Inbox must lock the canonical event row before domain action.');
 $called++;return true;
};
$result=MKT_Events::consume_external('identity',$event,$handler);
assert_same(true,$result,'New event successfully processes.');
assert_same('processed',$wpdb->row['status'],'Inbox ack persisted atomically.');
assert_same(1,$called,'New event executes once.');
assert_same(true,MKT_Events::consume_external('identity',$event,$handler),'Processed same-hash replay returns success.');
assert_same(1,$called,'Completed event does not rerun owner handler.');
$different=$event;$different['payload']['user_id']=200;
assert_same('mkt_event_payload_conflict',MKT_Events::consume_external('identity',$different,$handler)->get_error_code(),'Conflicting payload hash fails closed.');
$wpdb->row['status']='received';
$wpdb->row['received_at']=gmdate('Y-m-d H:i:s',time()-40);
assert_same('mkt_event_in_progress',MKT_Events::consume_external('identity',$event,$handler)->get_error_code(),'Fresh in-flight event cannot be concurrently executed.');
assert_same(1,$called,'Fresh in-flight event does not enter handler.');
$wpdb->row['received_at']=gmdate('Y-m-d H:i:s',time()-900);
assert_same(true,MKT_Events::consume_external('identity',$event,$handler),'Stale received reservation safely recovers after worker crash.');
assert_same(2,$called,'Recovered event executes once after row locking.');
assert_true(in_array('RECLAIM_STALE',$wpdb->ops,true),'Stale recovery uses observed lease/CAS update.');
$wpdb->row['status']='failed';
assert_same(true,MKT_Events::consume_external('identity',$event,$handler),'Durably failed event can be retried.');
assert_same(3,$called,'Retry calls handler exactly once.');
$wpdb->row['status']='received';
$wpdb->row['received_at']=gmdate('Y-m-d H:i:s',time()-900);
$error=MKT_Events::consume_external('identity',$event,static fn()=>new WP_Error('simulated_owner_failure'));
assert_true(is_wp_error($error),'Failed owner mutation never returns success.');
assert_same('failed',$wpdb->row['status'],'Transactional failure produces explicit retryable inbox state.');
fwrite(STDOUT,"File18 Inbox stale-worker recovery and transactional dedupe regression complete.\n");
