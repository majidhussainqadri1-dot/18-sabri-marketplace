<?php
/** CF-06 is conditional. Marketplace owns original listing language, not translation bundles. */
require __DIR__.'/bootstrap.php';
$root=dirname(__DIR__).'/includes';
$final=(string)file_get_contents($root.'/class-mkt-finalization.php');
$integrations=(string)file_get_contents($root.'/class-mkt-integrations.php');
$listing=(string)file_get_contents($root.'/class-mkt-listings.php');
assert_true(str_contains($final,'normalize_language') &&
    str_contains($final,'marketplace_language') &&
    str_contains($final,'MKT_Plan_Completion::table()'),
    'Listing original language is File18 source metadata, not translated content truth.');
assert_true(str_contains($integrations,'sabri_shell_navigation_destinations') &&
    str_contains($integrations,'sabri_shell_layout_mode'),
    'File20 remains owner of global shell/navigation.');
assert_true(!preg_match('/(?:update_user_meta|add_user_meta)\s*\([^;]*(?:language_preference|locale_preference|timezone_preference)/i',$integrations.$listing.$final),
    'File18 does not take over File00/20 preference ownership.');
foreach(glob($root.'/*.php') as $file) {
    $src=(string)file_get_contents($file);
    assert_true(!preg_match('/(?:CREATE TABLE|INSERT INTO|UPDATE|DELETE FROM)\s+(?:[a-z0-9_]+_)?(?:cf06_|localization_(?:resources|units|bundles|terminology|memory))/i',$src),
        'File18 does not directly write conditional CF06 translation records: '.basename($file));
}
assert_true(!str_contains($final,'machine_translate(')&&!str_contains($listing,'machine_translate('),
    'File18 does not auto-publish unreviewed machine translations.');
fwrite(STDOUT,"File18 conditional CF06 source-language and translation owner boundary regression complete.\n");