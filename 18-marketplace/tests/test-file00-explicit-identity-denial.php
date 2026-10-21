<?php
/** Real File18 identity projection must never turn explicit File00 denial into allowed capability. */
require __DIR__.'/bootstrap.php';
function sanitize_text_field($text):string{return strip_tags((string)$text);}
function smc_get_marketplace_assertions(int $user_id):array{return $GLOBALS['file00_assertions'];}
require dirname(__DIR__).'/includes/class-mkt-integrations.php';
$base=[
 'available'=>true,'compatible'=>true,'version'=>'1.0.0',
 'status'=>'active','approved'=>true,'verified'=>true,'suspended'=>false,
 'is_minor'=>false,'guardian_verified'=>false,'risk_state'=>'normal',
 'capabilities'=>['mkt_buy','mkt_sell'],'roles'=>['member']
];
$GLOBALS['file00_assertions']=$base;
$ok=MKT_Integrations::identity_assertions(41);
assert_same(true,$ok['available'],'Healthy File00 assertions remain available.');
assert_same(true,$ok['approved'],'Explicit File00 approval is preserved.');
assert_same(true,$ok['verified'],'Explicit File00 verification is preserved.');
$GLOBALS['file00_assertions']=$base+[];
$GLOBALS['file00_assertions']['approved']=false;
$blocked=MKT_Integrations::identity_assertions(41);
assert_same(false,$blocked['approved'],'Explicit File00 unapproved flag takes precedence over active status.');
assert_same(true,$blocked['verified'],'Explicit verification remains independently true.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['verified']=false;
$blocked=MKT_Integrations::identity_assertions(41);
assert_same(false,$blocked['verified'],'Explicit File00 verification denial must not be overridden by status.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['status']='verified';
$GLOBALS['file00_assertions']['verified']=false;
$blocked=MKT_Integrations::identity_assertions(41);
assert_same(false,$blocked['verified'],'Even a verified coarse status cannot override explicit negative verification assertion.');
$GLOBALS['file00_assertions']=$base;
unset($GLOBALS['file00_assertions']['approved'],$GLOBALS['file00_assertions']['verified']);
$fallback=MKT_Integrations::identity_assertions(41);
assert_same(true,$fallback['approved'],'Legacy File00 missing approval field still uses supported active status compatibility.');
assert_same(false,$fallback['verified'],'Active status without verified assertion does not invent verified identity.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['available']=false;
$unavailable=MKT_Integrations::identity_assertions(41);
assert_same(false,$unavailable['available'],'Explicit File00 outage remains authoritative.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['approved']='false';
$GLOBALS['file00_assertions']['verified']='false';
$denied=MKT_Integrations::identity_assertions(41);
assert_same(false,$denied['approved'],'Textual File00 false must not become truthy-approved.');
assert_same(false,$denied['verified'],'Textual File00 false must not become truthy-verified.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['is_minor']='true';
$GLOBALS['file00_assertions']['guardian_verified']='false';
$minor=MKT_Integrations::identity_assertions(41);
assert_same(true,$minor['is_minor'],'Textual File00 minor flag is explicitly parsed.');
assert_same(false,$minor['guardian_verified'],'Textual false guardian approval cannot authorize a minor.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['is_minor']='false';
$adult=MKT_Integrations::identity_assertions(41);
assert_same(false,$adult['is_minor'],'Textual false minor flag does not falsely classify adult as minor.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['available']='false';
$outage=MKT_Integrations::identity_assertions(41);
assert_same(false,$outage['available'],'Textual false File00 provider available flag fails closed.');
$GLOBALS['file00_assertions']=$base;
$GLOBALS['file00_assertions']['compatible']='false';
$badContract=MKT_Integrations::identity_assertions(41);
assert_same(false,$badContract['available'],'Textual false File00 provider contract cannot grant availability.');
fwrite(STDOUT,"File18 File00 negative identity assertion precedence regression complete.\n");