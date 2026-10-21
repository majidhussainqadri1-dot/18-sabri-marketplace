<?php
/** Full runtime-shaped negative regression for File 18 outbox consumer acknowledgements. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A','ARRAY_A');
class WP_Error {public function __construct(public string $code='') {} public function get_error_code(): string{return $this->code;}}
function is_wp_error($v): bool {return $v instanceof WP_Error;}
function __($v,$domain=''): string {return (string)$v;}
function sanitize_text_field($v): string {return (string)$v;}
function sanitize_key($v): string {return strtolower((string)$v);}
function esc_url_raw($v): string {return (string)$v;}
function home_url($p='/'): string {return 'https://sabri.example'.$p;}
function apply_filters($hook,$default,...$args){return $hook==='sabri_platform_ingest_event' ? ($GLOBALS['platform_ack']??false) : $default;}
function has_action($name): int{return 0;}
function assert_true($v,$m):void {if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}
function assert_same($expect,$got,$m):void {assert_true($expect===$got,$m.' expected='.var_export($expect,true).' got='.var_export($got,true));}
final class MKT_DB {
 public static function table($name): string{return 'wp_mkt_'.$name;}
 public static function now(): string{return '2026-10-21 00:00:00';}
}
final class MKT_Integrations {
 public static $ack = false;
 public static $recipients = [];
 public static function notify(int $user,string $type,array $context,string $key):bool{
   self::$recipients[] = $user;
   return self::$ack;
 }
}
final class FakeDB {
 public array $row = [];
 public array $changes = [];
 public array $where_clauses = [];
 public bool $simulate_stolen_lease = false;
 public function get_results($sql,$type){return [$this->row];}
 public function prepare($sql,...$args){return vsprintf(str_replace(['%d','%s'],['%d',"'%s'"],$sql),$args);}
 public function query($sql){return 1;}
 public function update($table,$changes,$where){
  $this->changes[]=$changes;$this->where_clauses[]=$where;
  if ($this->simulate_stolen_lease) {
    // Another worker recovered the old claim; the first worker's final
    // UPDATE must have its affected-row count fenced to zero.
    $this->row['status']='processing';
    $this->row['available_at']='2099-01-01 00:00:00';
    return 0;
  }
  $this->row['status']=$changes['status'];return 1;
 }
}
$wpdb=new FakeDB();
require dirname(__DIR__).'/includes/class-mkt-events.php';
function scenario(bool $platform,bool $notify,array $targets,int $actor,int $attempts=0):string {
 global $wpdb;
 $GLOBALS['platform_ack']=$platform;
 MKT_Integrations::$ack=$notify;
 MKT_Integrations::$recipients=[];
 $event=['event_id'=>'evt-01','event_type'=>'MarketplaceOfferCreated.v1','aggregate'=>['public_id'=>'test'],'actor_user_id'=>$actor,'payload'=>['notify_user_ids'=>$targets,'safe_summary'=>'Offer updated']];
 $wpdb->row=['id'=>1,'attempts'=>$attempts,'status'=>'pending','payload_json'=>json_encode($event)];
 $wpdb->changes=[];$wpdb->where_clauses=[];
 MKT_Events::process_outbox();
 return $wpdb->row['status'];
}
assert_same('retry',scenario(true,false,[23],7),'Platform acceptance never masks a required failed notification.');
assert_same([23],MKT_Integrations::$recipients,'Notification handoff to the required non-actor recipient was attempted.');
assert_same('processed',scenario(true,true,[23],7),'All required handoffs may complete the outbox.');
assert_same('processed',scenario(false,true,[23],7),'File19 receipt itself is a valid acknowledgement.');
assert_same('retry',scenario(false,false,[23],7),'Missing all consumer acknowledgements stays retryable.');
assert_same('dead',scenario(true,false,[23],7,7),'At retry limit, missing notification is visible as dead-letter.');
assert_same('processed',scenario(true,false,[7],7),'Originating actor does not require self-notification.');
assert_same([],MKT_Integrations::$recipients,'No unnecessary actor notification was attempted.');
assert_same('processed',scenario(true,false,[],7),'Events without recipients may finish when a domain consumer acknowledges.');
assert_true(isset($wpdb->where_clauses[0]['available_at']), 'The Outbox final ACK is fenced by its exact processing-lease timestamp.');
$wpdb->simulate_stolen_lease=true;
assert_same('processing',scenario(true,true,[23],7),'A recovered new worker retains the processing claim when an old worker finalizes.');
assert_same('2099-01-01 00:00:00',$wpdb->row['available_at'],'Outbox old worker may not overwrite the recovered lease.');
$wpdb->simulate_stolen_lease=false;
fwrite(STDOUT, "File18 outbox strict per-recipient handoff acknowledgement regression complete.\n");
