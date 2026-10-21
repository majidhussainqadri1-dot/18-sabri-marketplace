<?php
/** RC17 actual governance recall guard and public query fail-closed contracts. */
require __DIR__.'/bootstrap.php';
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
class MKT_DB {public static function table(string $name):string{return 'wp_mkt_'.$name;} public static function currency(string $value):string{return strtoupper($value);}}
class MKT_Plan_Completion {public static function table():string{return 'wp_mkt_listing_fulfillment';}}
class RecallTruthDB {
 public string $prefix='wp_';
 public string $last_error='';
 public bool $missing_table=false;
 public bool $read_error=false;
 public bool $active_recall=false;
 public array $queries=[];
 public function esc_like(string $v):string{return $v;}
 public function prepare(string $sql,...$args):string{
   if(count($args)===1&&is_array($args[0]))$args=$args[0];
   return vsprintf(str_replace(['%s','%d'],["'%s'",'%d'],$sql),$args);
 }
 public function get_var(string $sql){
   $this->queries[]=$sql;
   if(str_contains($sql,'SHOW TABLES LIKE'))return $this->missing_table?null:'wp_mkt_recalls';
   return 0;
 }
 public function get_row(string $sql,$type){
   $this->queries[]=$sql;
   if($this->read_error){$this->last_error='Injected recall SELECT failure';return null;}
   if(str_contains($sql,'wp_mkt_recalls'))return $this->active_recall?['public_id'=>'recall-id']:null;
   return null;
 }
 public function get_results(string $sql,$type):array{$this->queries[]=$sql;return [];}
 public function esc_like_id(string $s):string{return $s;}
}
$wpdb=new RecallTruthDB();
require dirname(__DIR__).'/includes/class-mkt-governance.php';
require dirname(__DIR__).'/includes/class-mkt-listings.php';
require dirname(__DIR__).'/includes/class-mkt-finalization.php';
$wpdb->missing_table=true;
$missing=MKT_Governance::recall_publication_guard('listing-id');
assert_true(is_wp_error($missing),'Missing recall owner table never means no recall.');
assert_same(503,$missing->get_error_data()['status'],'Missing recall truth returns HTTP 503.');
$wpdb->missing_table=false;$wpdb->read_error=true;
$failed=MKT_Governance::recall_publication_guard('listing-id');
assert_true(is_wp_error($failed),'A failed recall SQL SELECT blocks publication.');
$wpdb->read_error=false;$wpdb->active_recall=true;
assert_same(true,MKT_Governance::recall_publication_guard('listing-id'),'Active recall prohibits publication.');
$wpdb->active_recall=false;
assert_same(false,MKT_Governance::recall_publication_guard('listing-id'),'Verified SQL absence of active recalls permits subsequent checks.');
$wpdb->queries=[];
MKT_Listings::get('listing-id',false);
assert_true(str_contains(end($wpdb->queries),"NOT EXISTS (SELECT 1 FROM wp_mkt_recalls"),'Public listing detail SQL excludes active recalls.');
$wpdb->queries=[];
MKT_Listings::get_by_id(1,false);
assert_true(str_contains(end($wpdb->queries),"NOT EXISTS (SELECT 1 FROM wp_mkt_recalls"),'Public ID lookups exclude active recalls.');
$wpdb->queries=[];
MKT_Listings::search([]);
assert_true(str_contains(end($wpdb->queries),"NOT EXISTS (SELECT 1 FROM wp_mkt_recalls"),'Canonical public search enforces recall safety in query.');
$wpdb->queries=[];
MKT_Finalization::search_array([]);
assert_true(str_contains(end($wpdb->queries),"NOT EXISTS (SELECT 1 FROM wp_mkt_recalls"),'Enriched File26 Marketplace search enforces the same recall protection.');
$listing=file_get_contents(dirname(__DIR__).'/includes/class-mkt-listings.php');
$plans=file_get_contents(dirname(__DIR__).'/includes/class-mkt-plan-completion.php');
$release=file_get_contents(dirname(__DIR__).'/includes/class-mkt-release-gates.php');
assert_true(str_contains($listing,'recall_publication_guard($public_id)'), 'Direct seller/admin reactivation checks canonical recall owner.');
assert_true(str_contains($plans,'recall_publication_guard($listing_public_id)'), 'Plan completion publication gate fails closed.');
assert_true(str_contains($release,'recall_publication_guard($public_id)'), 'Final release publication gate fails closed.');
fwrite(STDOUT,"RC17 governance recall SQL outage and public safety visibility regressions passed.\n");