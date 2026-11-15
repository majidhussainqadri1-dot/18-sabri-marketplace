<?php
/** RC50: actual Governance rest post-dispatch public safety projection tests. */
declare(strict_types=1);
defined('ABSPATH') || define('ABSPATH',__DIR__);
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function __($msg,$domain=''){return $msg;}
function sanitize_key($s){return strtolower((string)$s);}
class WP_REST_Request {
 public function __construct(private string $route,private string $method='GET'){}
 public function get_method(){return $this->method;}
 public function get_route(){return $this->route;}
}
class WP_REST_Response {
 public function __construct(private array $data=[],private int $status=200){}
 public function get_status(){return $this->status;}
 public function get_data(){return $this->data;}
 public function set_data($data){$this->data=$data;}
}
class MKT_DB{public static function table($name){return 'wp_mkt_'.$name;}}
class GovernanceDB {
 public string $prefix='wp_';
 public string $last_error='';
 public string $failure='';
 public function esc_like($s){return (string)$s;}
 public function prepare($sql,...$args){return vsprintf(str_replace('%s',"'%s'",(string)$sql),$args);}
 public function get_var($sql){
   if($this->failure==='evidence_table'&&str_contains($sql,'listing_evidence')){$this->last_error='table inaccessible';return null;}
   if(str_contains($sql,'listing_evidence'))return 'wp_mkt_listing_evidence';
   if(str_contains($sql,'recalls'))return 'wp_mkt_recalls';
   return null;
 }
 public function get_row($sql,$t=null){
   if(str_contains($sql,'listing_evidence')){
     if($this->failure==='evidence_sql'){$this->last_error='evidence query failure';return null;}
     if($this->failure==='evidence_throw')throw new RuntimeException('private SQL diagnostics');
     return null;
   }
   if(str_contains($sql,'recalls')){
     if($this->failure==='recall_sql'){$this->last_error='recall query failure';return null;}
     if($this->failure==='recall_throw')throw new RuntimeException('private SQL diagnostics');
     return null;
   }
   return null;
 }
}
$wpdb=new GovernanceDB();$n=0;
function assertion50($v,$why){global $n;$n++;if(!$v)throw new RuntimeException('FAIL: '.$why);}
require dirname(__DIR__).'/includes/class-mkt-governance.php';
$id='00000000-0000-4000-8000-000000000001';
foreach(['evidence_sql','evidence_table','evidence_throw','recall_sql','recall_throw'] as $failure){
 foreach(['/marketplace/v1/listings/'.$id, '/marketplace/v1/listings'] as $route){
  $wpdb->failure=$failure;
  $data=$route==='/marketplace/v1/listings'?['items'=>[['public_id'=>$id,'title'=>'Public']]]:['public_id'=>$id,'title'=>'Public'];
  $r=MKT_Governance::augment_rest_response(new WP_REST_Response($data),null,new WP_REST_Request($route));
  assertion50($r->get_status()===503,'Source '.$failure.' returns 503 on route '.$route);
  assertion50(($r->get_data()['code']??'')==='mkt_public_safety_unavailable','No sensitive partial listing emitted');
  assertion50(!isset($r->get_data()['title'])&&!isset($r->get_data()['items']),'No partial public content leaks');
 }
}
$wpdb->failure='';
$r=MKT_Governance::augment_rest_response(new WP_REST_Response(['public_id'=>$id,'title'=>'Safe']),null,new WP_REST_Request('/marketplace/v1/listings/'.$id));
assertion50($r->get_status()===200&&array_key_exists('recall',$r->get_data()),'Healthy safety projection succeeds');
$prior=new WP_REST_Response(['code'=>'preexisting'],503);
$r=MKT_Governance::augment_rest_response($prior,null,new WP_REST_Request('/marketplace/v1/listings/'.$id));
assertion50($r===$prior,'Existing upstream 503 preserved unchanged');
echo 'PASS RC50: '.$n." real Governance REST safety post-dispatch assertions\n";
