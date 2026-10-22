<?php
/** RC26 real Moderation owners: every mutable report/dispute receipt must verify committed status/version. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A')||define('ARRAY_A','ARRAY_A');
function get_current_user_id():int{return 41;}
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function wp_is_uuid($v):bool{return (bool)preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i',(string)$v);}
function wp_kses_post($v):string{return (string)$v;}
function wp_strip_all_tags($v):string{return strip_tags((string)$v);}
function wp_json_encode($v):string{return json_encode($v);}
function home_url($p):string{return 'https://example.invalid'.$p;}
class MKT_Auth {
 public static function can($cap,$context=[]):bool{return true;}
 public static function deal_participant(array $row,int $uid):bool{return in_array($uid,[(int)$row['buyer_user_id'],(int)$row['seller_user_id']],true);}
}
class MKT_Listings {public static function get(string $pid,bool $p=false):?array{return ['id'=>7,'public_id'=>$pid,'status'=>'active'];}}
class MKT_Commerce {
 public static function get_deal(string $p,int $uid):array{return ['public_id'=>$p];}
 public static function transition_deal(string $p,string $to,array $input,int $uid,int $ver):array{return ['status'=>$to];}
}
class MKT_State_Machines {public static function assert($t,$from,$to):bool{return true;}}
class MKT_DB {
 private static int $seq=100;
 public static function uuid():string{return sprintf('00000000-0000-4000-8000-%012d',++self::$seq);}
 public static function table(string $t):string{return 'wp_mkt_'.$t;}
 public static function now():string{return '2026-10-22 00:00:00';}
 public static function transaction(callable $f){
  global $wpdb;
  $backup=[$wpdb->reports,$wpdb->disputes,$wpdb->audit,$wpdb->events,$wpdb->write_seen];
  try{$v=$f();if(is_wp_error($v))[$wpdb->reports,$wpdb->disputes,$wpdb->audit,$wpdb->events,$wpdb->write_seen]=$backup;return $v;}
  catch(Throwable $e){[$wpdb->reports,$wpdb->disputes,$wpdb->audit,$wpdb->events,$wpdb->write_seen]=$backup;throw $e;}
 }
}
class MKT_Audit {
 public static function trace_id():string{return 'rc26-audit';}
 public static function record(...$args):string{global $wpdb;$wpdb->audit[]=$args;return 'ok';}
}
class MKT_Events {
 public static function enqueue(...$args):string{global $wpdb;$wpdb->events[]=$args;return 'ok';}
}
class ReviewDB {
 public int $insert_id=0;public bool $write_seen=false;public string $readback_mode='normal';
 public array $reports=[],$disputes=[],$audit=[],$events=[];
 public array $deal=['id'=>51,'public_id'=>'deal-1','version'=>1,'buyer_user_id'=>41,'seller_user_id'=>9];
 public function __construct(){
  $this->reports[4]=['id'=>4,'public_id'=>'report-1','reporter_user_id'=>22,'target_type'=>'seller',
    'target_public_id'=>'seller-1','status'=>'submitted','version'=>1,'assigned_to'=>0,
    'decision_code'=>'','details'=>'question','reason'=>'fraud','created_at'=>'2026-10-22 00:00:00','updated_at'=>'2026-10-22 00:00:00'];
  $this->disputes[8]=['id'=>8,'public_id'=>'dispute-1','deal_id'=>51,'opened_by'=>41,
    'buyer_user_id'=>41,'seller_user_id'=>9,'deal_public_id'=>'deal-1',
    'reason'=>'other','statement'=>'statement statement','status'=>'more_info','version'=>1,
    'assigned_to'=>0,'decision_code'=>'','decision_note'=>'','evidence_refs'=>'[]',
    'created_at'=>'2026-10-22 00:00:00','updated_at'=>'2026-10-22 00:00:00'];
 }
 public function prepare(string $sql,...$args):string{return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);}
 private function corrupt(?array $row,bool $is_readback):?array{
  if(!$is_readback||!$row)return $row;
  if($this->readback_mode==='missing')return null;
  if($this->readback_mode==='wrong_status')$row['status']='outdated';
  if($this->readback_mode==='wrong_version')$row['version']=999;
  if($this->readback_mode==='wrong_identity')$row['public_id']='other-record';
  return $row;
 }
 public function get_row(string $sql,$type):?array{
  if(str_contains($sql,'wp_mkt_deals') && !str_contains($sql,'wp_mkt_disputes'))return $this->deal;
  if(str_contains($sql,'wp_mkt_reports')){
   $row=null;
   if(preg_match('/WHERE id=(\d+)/',$sql,$m))$row=$this->reports[(int)$m[1]]??null;
   elseif(str_contains($sql,"WHERE public_id='report-1'"))$row=$this->reports[4];
   return $this->corrupt($row,$this->write_seen && str_contains($sql,'WHERE id='));
  }
  if(str_contains($sql,'wp_mkt_disputes')){
   if(str_contains($sql,'status NOT IN'))return null;
   $row=null;
   if(preg_match('/WHERE id=(\d+)/',$sql,$m))$row=$this->disputes[(int)$m[1]]??null;
   elseif(str_contains($sql,"WHERE public_id='dispute-1'"))$row=$this->disputes[8];
   elseif(preg_match("/WHERE public_id='([^']+)'/",$sql,$m))foreach($this->disputes as $d)if($d['public_id']===$m[1])$row=$d;
   return $this->corrupt($row,$this->write_seen && !str_contains($sql,'FOR UPDATE'));
  }
  return null;
 }
 public function insert(string $table,array $row):int{
  $this->write_seen=true;
  if(str_ends_with($table,'reports')){
   $this->insert_id=20+count($this->reports);
   $this->reports[$this->insert_id]=array_merge(['id'=>$this->insert_id,'decision_code'=>''], $row);
   return 1;
  }
  if(str_ends_with($table,'disputes')){
   $this->insert_id=30+count($this->disputes);
   $this->disputes[$this->insert_id]=array_merge(['id'=>$this->insert_id,'decision_code'=>'','decision_note'=>''],$row);
   return 1;
  }
  throw new RuntimeException('Unexpected table '.$table);
 }
 public function update(string $table,array $data,array $where):int {
  $this->write_seen=true;
  if(str_ends_with($table,'reports')){$id=(int)$where['id'];$this->reports[$id]=array_merge($this->reports[$id],$data);return 1;}
  if(str_ends_with($table,'disputes')){$id=(int)$where['id'];$this->disputes[$id]=array_merge($this->disputes[$id],$data);return 1;}
  throw new RuntimeException('Unexpected update '.$table);
 }
}
require dirname(__DIR__).'/includes/class-mkt-moderation.php';
$target='00000000-0000-4000-8000-000000000999';
$input=['target_type'=>'listing','target_public_id'=>$target,'details'=>'A clear fraud report for safety review','reason'=>'fraud'];
foreach(['normal','missing','wrong_status','wrong_version','wrong_identity'] as $mode){
 $wpdb=new ReviewDB();$wpdb->readback_mode=$mode;
 $before=$wpdb->reports;
 $result=MKT_Moderation::create_report($input,41);
 if($mode==='normal'){assert_true(is_array($result),'Correct new report readback succeeds.');continue;}
 assert_true(is_wp_error($result),'Invalid new report readback denied '.$mode);
 assert_same('mkt_report_readback_failed',$result->get_error_code(),'New report cannot use corrupt receipt '.$mode);
 assert_same($before,$wpdb->reports,'Invalid report receipt rolls back writes '.$mode);
 assert_same([],$wpdb->audit,'Invalid report receipt rolls back audit '.$mode);
 assert_same([],$wpdb->events,'Invalid report receipt rolls back outbox '.$mode);
}
foreach(['normal','missing','wrong_status','wrong_version','wrong_identity'] as $mode){
 $wpdb=new ReviewDB();$wpdb->readback_mode=$mode;$before=$wpdb->reports;
 $result=MKT_Moderation::transition_report('report-1','no_action',[],41,1);
 if($mode==='normal'){assert_same('no_action',$result['status'],'Correct report transition receipt succeeds.');continue;}
 assert_same('mkt_report_transition_readback_failed',$result->get_error_code(),'Report transition readback fail closed '.$mode);
 assert_same($before,$wpdb->reports,'Unverified report transition rolls back state '.$mode);
 assert_same([],$wpdb->events,'Unverified report transition does not emit outbox '.$mode);
}
foreach(['normal','missing','wrong_status','wrong_version','wrong_identity'] as $mode){
 $wpdb=new ReviewDB();$wpdb->readback_mode=$mode;$before=$wpdb->disputes;
 $result=MKT_Moderation::transition_dispute('dispute-1','evidence',[],41,1);
 if($mode==='normal'){assert_same('evidence',$result['status'],'Correct dispute transition receipt succeeds.');continue;}
 assert_same('mkt_dispute_transition_readback_failed',$result->get_error_code(),'Dispute transition readback fail closed '.$mode);
 assert_same($before,$wpdb->disputes,'Unverified dispute transition rolls back state '.$mode);
 assert_same([],$wpdb->audit,'Unverified dispute transition rolls back audit '.$mode);
}
foreach(['normal','missing','wrong_status','wrong_version','wrong_identity'] as $mode){
 $wpdb=new ReviewDB();$wpdb->readback_mode=$mode;$before=$wpdb->disputes;
 $result=MKT_Moderation::open_dispute('deal-1',['statement'=>'A disputed delivery was not fulfilled','reason'=>'non_delivery'],41);
 if($mode==='normal'){assert_same('opened',$result['status'],'Correct new dispute receipt succeeds.');continue;}
 assert_same('mkt_dispute_readback_failed',$result->get_error_code(),'New dispute readback fail closed '.$mode);
 assert_same($before,$wpdb->disputes,'New dispute unverified receipt rolls back record '.$mode);
 assert_same([],$wpdb->events,'New dispute unverified receipt rolls back outbox '.$mode);
}
fwrite(STDOUT,"RC26 actual Report and Dispute owner receipt fail-closed/rollback safeguards passed.\n");