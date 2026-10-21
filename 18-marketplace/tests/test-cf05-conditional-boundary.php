<?php
/** CF05 is conditional: File18 is a domain owner, not an analytics warehouse. */
require __DIR__.'/bootstrap.php';
$root=dirname(__DIR__).'/includes';
$events=(string)file_get_contents($root.'/class-mkt-events.php');
$db=(string)file_get_contents($root.'/class-mkt-db.php');
$integrations=(string)file_get_contents($root.'/class-mkt-integrations.php');
assert_true(str_contains($events, "'producer' => 'file-18-marketplace'") &&
    str_contains($events, "'event_version' => MKT_Contracts::EVENT_VERSION") &&
    str_contains($events, "'aggregate' => ['type' => "),
    'Marketplace declares versioned domain facts, not CF05 canonical metric truth.');
assert_true(str_contains($db,"'metrics_daily'") && str_contains($db,'value_sum'),
    'Marketplace native daily operational metrics do not activate a new CF05 owner.');
assert_true(str_contains($integrations,"function_exists('sun_ingest_domain_event')") &&
    str_contains($integrations,'SN_Messages::messages_url'),
    'File17/19 consumer owner boundaries remain separate.');
foreach(glob($root.'/*.php') as $p) {
    $source=(string)file_get_contents($p);
    assert_true(!preg_match('/(?:CREATE TABLE|INSERT INTO|UPDATE|DELETE FROM)\s+(?:[a-z0-9_]+_)?(?:cf05_|smai_warehouse|smai_analytics)/i',$source),
        'No File18 direct CF05 warehouse/entity writes: '.basename($p));
}
fwrite(STDOUT,"File18 conditional CF05 derivative-only ownership and File17/19 boundaries checked at source level.\n");