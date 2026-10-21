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
fwrite(STDOUT,"File18 File00 negative identity assertion precedence regression complete.\n");