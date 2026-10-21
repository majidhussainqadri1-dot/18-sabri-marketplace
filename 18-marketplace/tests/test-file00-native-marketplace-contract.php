<?php
/** Real File18 adapter against exact File00 SMC_Contracts::assertions() data shape from File00 repo main. */
require __DIR__.'/bootstrap.php';
function sanitize_text_field($text):string{return strip_tags((string)$text);}
function is_user_logged_in():bool{return true;}
function get_current_user_id():int{return 41;}
function current_user_can(string $cap):bool{return false;} // Prove scoped File00 rights work without synthetic WordPress mkt_* grants.
class SMC_Contracts {
 public static array $payload=[];
 public static bool $fail=false;
 public static function assertions(int $id):array{
  if(self::$fail)throw new RuntimeException('Private source provider failure');
  return self::$payload;
 }
}
require dirname(__DIR__).'/includes/class-mkt-integrations.php';
require dirname(__DIR__).'/includes/class-mkt-auth.php';
$base=[
 'contract_version'=>'1.2.44','user_id'=>41,'status'=>'approved',
 'approved'=>true,'eligible'=>true,'suspended'=>false,
 'guardian_verified'=>false,'identity_documents_current'=>true,
 'age_context'=>['known'=>true,'age_band'=>'adult','minor'=>false,'guardian_required'=>false],
 'clinical_commerce'=>[
  'marketplace_browse_allowed'=>true,
  'marketplace_direct_deal_allowed'=>true,
  'marketplace_seller_actions_allowed'=>true,
  'consumer_authorization_recheck_required'=>true,
 ],
];
SMC_Contracts::$payload=$base;
$adult=MKT_Integrations::identity_assertions(41);
assert_same('file00_smc_contract',$adult['contract_mode'],'Use canonical File00 SMC_Contracts when dedicated marketplace assertions function absent.');
assert_same(true,$adult['available'],'Canonical compatible and scoped assertions are available.');
assert_same(null,$adult['age'],'No fabricated exact age from File00 age band.');
assert_same(false,$adult['is_minor'],'Age classification honors canonical known adult.');
assert_same(true,$adult['file00_marketplace_seller_allowed'],'Canonical File00 seller scope preserved.');
assert_same(true,MKT_Auth::can('mkt_buy'),'Actual File18 buyer auth honors explicit positive File00 direct-deal grant.');
assert_same(true,MKT_Auth::can('mkt_sell'),'Actual File18 seller auth honors explicit positive File00 seller grant.');

SMC_Contracts::$payload=$base;
SMC_Contracts::$payload['age_context']=['known'=>true,'age_band'=>'minor','minor'=>true,'guardian_required'=>true];
SMC_Contracts::$payload['guardian_verified']=true;
SMC_Contracts::$payload['clinical_commerce']['marketplace_direct_deal_allowed']=false;
SMC_Contracts::$payload['clinical_commerce']['marketplace_seller_actions_allowed']=false;
assert_same('mkt_file00_marketplace_scope_denied',MKT_Auth::can('mkt_buy')->get_error_code(),'Guardian consent does not override File00 minor direct-deal prohibition.');
assert_same('mkt_file00_marketplace_scope_denied',MKT_Auth::can('mkt_sell')->get_error_code(),'Guardian consent does not override File00 minor seller prohibition.');
assert_true(in_array('file00_marketplace_seller_denied',MKT_Auth::seller_eligibility(41)['reasons'],true),'Seller eligibility explicitly records File00 source deny.');

SMC_Contracts::$payload=$base;
SMC_Contracts::$payload['eligible']=false;
assert_same('mkt_account_not_eligible',MKT_Auth::can('mkt_buy')->get_error_code(),'File00 advanced-trust/contacts effective eligible=false blocks protected actions.');

SMC_Contracts::$payload=$base;
unset(SMC_Contracts::$payload['clinical_commerce']['marketplace_direct_deal_allowed']);
assert_same(false,MKT_Integrations::identity_assertions(41)['available'],'Missing File00 direct-deal contract field is incompatible, not an implicit true.');
assert_same('mkt_identity_provider_unavailable',MKT_Auth::can('mkt_buy')->get_error_code(),'Incomplete canonical File00 permission contract fails closed.');

SMC_Contracts::$payload=$base;
SMC_Contracts::$payload['age_context']['known']=false;
assert_same('mkt_age_verification_required',MKT_Auth::can('mkt_buy')->get_error_code(),'File00 missing age proof never becomes adult.');

SMC_Contracts::$payload=$base;
SMC_Contracts::$payload['risk_state']='high';
assert_same('mkt_account_risk_hold',MKT_Auth::can('mkt_sell')->get_error_code(),'Explicit File00 high-risk state overrides positive scoped seller decision.');
SMC_Contracts::$fail=true;
assert_same(false,MKT_Integrations::identity_assertions(41)['available'],'Canonical File00 exception becomes unavailable, never a weaker profile fallback.');
assert_same('mkt_identity_provider_unavailable',MKT_Auth::can('mkt_buy')->get_error_code(),'File00 provider runtime exception returns safe 503-style denial.');
SMC_Contracts::$fail=false;
fwrite(STDOUT,"File18 real File00 SMC_Contracts marketplace scoped-authorization bridge regressions complete.\n");