<?php
/**
 * File 18 ↔ File 17 REST-owner bridge simulation.
 * Real File 17 WordPress/MySQL and browser staging is still mandatory.
 */
require __DIR__ . '/bootstrap.php';
define('MKT_CONTRACT_VERSION','1.2.0');
function sanitize_text_field($v): string { return trim(strip_tags((string)$v)); }
function esc_url_raw($v): string { return (string)$v; }
function wp_parse_url(string $url) { return parse_url($url); }
function home_url(string $path='/'): string {return 'https://sabri.example'.$path;}
function get_current_user_id(): int {return $GLOBALS['actor'] ?? 41;}
function has_filter(string $hook) {return isset($GLOBALS['filters'][$hook]) ? 10 : false;}
function add_filter(string $hook,$cb,int $priority=10,int $accepted_args=1): void {$GLOBALS['filters'][$hook]=$cb;}
function add_action(...$args): void {}
class WP_REST_Request {
    private array $params=[];
    public function __construct(public string $method,public string $route) {}
    public function set_param(string $name,$val):void {$this->params[$name]=$val;}
    public function get_param(string $name){return $this->params[$name]??null;}
}
class WP_REST_Response {
    public function __construct(private array $data,private int $status=200){}
    public function get_status():int{return $this->status;}
    public function get_data():array{return $this->data;}
}
class SN_REST {}
class SN_Context_Adapters {}
class SN_Messages {public static function messages_url(int $id):string{return 'https://sabri.example/messages/'.$id.'/';}}
class SN_DB {public static function is_member(int $conversation,int $user):bool{return $conversation===987&&in_array($user,[41,82],true);}}
class MKT_Listings {
    public static bool $active=true;
    public static function get(string $id,bool $private=false):?array {
        return self::$active&&$id==='f18-listing-123456'?[
            'public_id'=>$id,'title'=>'Approved textbook','seller_user_id'=>82,
            'slug'=>'approved-textbook','status'=>'active'
        ]:null;
    }
    public static function url(array $r):string {return home_url('/marketplace/listing/'.$r['public_id'].'/'.$r['slug'].'/');}
}
class MKT_Auth {public static function seller_eligibility(int $uid):array{return ['eligible'=>$uid===82&&empty($GLOBALS['suspended'])];}}
$GLOBALS['actor']=41;$GLOBALS['filters']=[];$GLOBALS['routes']=[];$GLOBALS['context_status']=201;
function rest_do_request(WP_REST_Request $r):WP_REST_Response {
    $GLOBALS['routes'][]=$r->route;
    if($r->route==='/sabri-network/v2/conversations') {
        if($r->get_param('type')!=='direct'||$r->get_param('user_id')!==82)
            return new WP_REST_Response(['error'=>'invalid_direct'],403);
        return new WP_REST_Response(['conversation'=>['id'=>987]]);
    }
    if($r->route==='/sabri-network/v2/conversations/987/contexts') {
        if($GLOBALS['context_status']!==201)return new WP_REST_Response(['error'=>'provider_failure'],$GLOBALS['context_status']);
        if($r->get_param('provider')!=='file18_marketplace'||$r->get_param('provider_object_id')!=='f18-listing-123456')return new WP_REST_Response([],403);
        $authorization=($GLOBALS['filters']['sn_network_context_authorize'])(null,'file18_marketplace',$r->get_param('provider_object_id'),987,41,'attach');
        return new WP_REST_Response(['context'=>['provider'=>'file18_marketplace']],$authorization===true?201:403);
    }
    return new WP_REST_Response([],404);
}
function sun_ingest_domain_event(array $event): array {return ['status'=>'processed'];}
function sun_register_notification_producer($key,array $config): bool {return true;}
require dirname(__DIR__).'/includes/class-mkt-integrations.php';
MKT_Integrations::boot();
assert_true(MKT_Integrations::status()['notifications']['available']===true, 'Native File19 SUN producer/ingest functions register notification availability.');
assert_true(has_filter('sn_network_context_authorize')!==false,'File 18 provides File17 context owner authorization.');
assert_true(has_filter('sn_network_context_projection')!==false,'File 18 provides File17 public-safe context projection.');
assert_true(MKT_Integrations::communication_status()['available']===true,'Native File17 REST bridge is recognized as available.');
$ctx=['object_public_id'=>'f18-listing-123456','title'=>'Approved textbook','url'=>home_url('/marketplace/listing/f18-listing-123456/approved-textbook/')];
$ok=MKT_Integrations::open_context_conversation(41,82,$ctx);
assert_true(is_array($ok),'Buyer can create/open the File17-owned conversation and attach listing context.');
assert_same('987',$ok['conversation_id'],'Response retains the canonical File17 conversation ID.');
assert_same('https://sabri.example/messages/987/',$ok['url'],'Navigation uses the File17-owned canonical messages URL.');
assert_same(['/sabri-network/v2/conversations','/sabri-network/v2/conversations/987/contexts'],$GLOBALS['routes'],'Only File17 REST owner routes perform conversation/context writes.');
$projection=($GLOBALS['filters']['sn_network_context_projection'])(null,'file18_marketplace','f18-listing-123456',987,41);
assert_same('Approved textbook',$projection['label'],'Projection exposes only the current publicly eligible listing label.');
assert_true(is_wp_error(($GLOBALS['filters']['sn_network_context_authorize'])(null,'file18_marketplace','f18-listing-123456',987,12,'read')),'Unrelated user cannot read File17 Marketplace context.');
$GLOBALS['context_status']=503;
assert_true(is_wp_error(MKT_Integrations::open_context_conversation(41,82,$ctx)),'File17 context attachment failure is never reported as successful handoff.');
$GLOBALS['context_status']=201;
$GLOBALS['actor']=9;
assert_true(is_wp_error(MKT_Integrations::open_context_conversation(41,82,$ctx)),'Non-actor cannot forge a buyer-to-seller File17 REST handoff.');
$GLOBALS['actor']=41;$GLOBALS['suspended']=true;
assert_true(is_wp_error(MKT_Integrations::open_context_conversation(41,82,$ctx)),'Suspended seller blocks new buyer contact.');
$GLOBALS['suspended']=false;MKT_Listings::$active=false;
assert_true(is_wp_error(MKT_Integrations::open_context_conversation(41,82,$ctx)),'Removed listing blocks new conversation/context handoff.');
fwrite(STDOUT,"File18 native File17 REST conversation/context bridge contract simulation complete.\n");
