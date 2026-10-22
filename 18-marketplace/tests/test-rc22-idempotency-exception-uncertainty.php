<?php
/** RC22 direct durable idempotency: thrown callback cannot mark uncertain owner effects retryable. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
defined('DAY_IN_SECONDS')||define('DAY_IN_SECONDS',86400);
function sanitize_text_field($x):string{return trim((string)$x);}
function wp_json_encode($x):string{return json_encode($x);}
function get_current_user_id():int{return 41;}
class MKT_DB {
 public static function table(string $n):string{return 'wp_mkt_'.$n;}
 public static function now():string{return gmdate('Y-m-d H:i:s');}
}
class AmbiguousCommitDB {
 public bool $reserved=false;
 public array $writes=[];
 public string $status='processing';
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 public function query(string $sql):int {
  if(!$this->reserved){$this->reserved=true;return 1;}
  return 0;
 }
 public function get_row($sql,$mode):array {
  return ['id'=>7,'request_hash'=>hash('sha256',json_encode(['listing'=>'a'])),
    'status'=>$this->status,'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)];
 }
 public function update($table,$data,$where):int{$this->writes[]=[$data,$where];return 1;}
}
$wpdb=new AmbiguousCommitDB();
require dirname(__DIR__).'/includes/class-mkt-idempotency.php';
$mutations=0;$key='rc22-ambiguous-commit-key-123';
$first=MKT_Idempotency::run('offer_create',$key,['listing'=>'a'],static function()use(&$mutations){
 $mutations++;
 // Mimics owner commit followed by a timeout or throwing integration hook.
 throw new RuntimeException('Injected ambiguous post-commit exception');
});
assert_true(is_wp_error($first),'An unknown callback outcome never reports a successful receipt.');
assert_same('mkt_idempotency_outcome_uncertain',$first->get_error_code(),'Thrown callback has an explicit uncertain outcome.');
assert_same([],$wpdb->writes,'An uncertain exception does not save a forged failed/retryable receipt.');
$second=MKT_Idempotency::run('offer_create',$key,['listing'=>'a'],static function()use(&$mutations){$mutations++;return ['offer'=>'duplicated'];});
assert_true(is_wp_error($second),'Immediate duplicate cannot replay ambiguous owner mutation.');
assert_same('mkt_request_in_progress',$second->get_error_code(),'Receipt remains processing pending reconciliation.');
assert_same(1,$mutations,'Same key invokes owner mutation at most once after uncertain failure.');
$wpdb->status='failed';
$retry=MKT_Idempotency::run('offer_create',$key,['listing'=>'a'],static function()use(&$mutations){$mutations++;return ['offer'=>'verified-retry'];});
assert_same(['offer'=>'verified-retry'],$retry,'Only positive verified failed state permits retry.');
assert_same(2,$mutations,'Verified failed receipt is the only retry path.');
fwrite(STDOUT,"RC22 uncertain idempotent callback and verified failed retry safeguards passed.\n");