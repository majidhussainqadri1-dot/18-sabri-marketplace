<?php
/** PHP runtime-shaped boundary tests for canonical marketplace money/stock validators. */
require __DIR__ . '/bootstrap.php';
define('ARRAY_A','ARRAY_A');
function sanitize_text_field($v): string {return trim(strip_tags((string)$v));}
function wp_strip_all_tags($v): string {return strip_tags((string)$v);}
function wp_kses_post($v): string {return (string)$v;}
function wp_json_encode($v): string|false {return json_encode($v);}
final class MKT_DB {
  public static function table(string $name): string {return 'wp_mkt_'.$name;}
  public static function currency(string $input): string{return strtoupper($input);}
  public static function settings(): array{return ['default_currency'=>'PKR'];}
  public static function now(): string{return '2026-10-21 00:00:00';}
}
final class MKT_Contracts {
  public static function allowed_currencies():array {return ['PKR','USD'];}
}
final class FakePolicyDB {
  public function prepare(string $sql,...$args):string {return vsprintf(str_replace('%s',"'%s'",$sql),$args);}
  public function get_row(string $sql,$array){
    if (str_contains($sql,"'category'")) {
      return ['public_id'=>'policy-category','version'=>1,'rules_json'=>json_encode(['required_fields'=>['title','description','price','currency']])];
    }
    return ['public_id'=>'policy-prohibited','version'=>1,'rules_json'=>json_encode(['categories'=>[],'phrases'=>[]])];
  }
}
$wpdb=new FakePolicyDB();
require dirname(__DIR__).'/includes/class-mkt-listings.php';
require dirname(__DIR__).'/includes/class-mkt-policy.php';
$method = new ReflectionMethod(MKT_Listings::class,'sanitize_input');
$input = ['title'=>'Safety test','category'=>'homeopathy_books','description'=>'An ordinary textbook','price'=>'-199.25','quantity'=>'-3','currency'=>'PKR','declarations'=>['truthful'=>true,'rights_owned'=>true,'no_patient_data'=>true,'zero_commission_understood'=>true]];
$data = $method->invoke(null,$input);
assert_same(-199.25,$data['price'],'Listing sanitizer retains negative price for policy rejection.');
assert_same(-3.0,$data['quantity'],'Listing sanitizer retains negative quantity for policy rejection.');
$result=MKT_Policy::evaluate_listing($data,[]);
$codes=array_column($result['errors'],'code');
assert_true(in_array('invalid_price',$codes,true),'Policy rejects previously silently zero-clamped negative price.');
assert_true(in_array('invalid_quantity',$codes,true),'Policy rejects previously silently zero-clamped negative goods quantity.');
$price_inf = $method->invoke(null, $input+[]); // retain the same field shape.
$input['price']='1e9999';
$input['quantity']='1e9999';
$data=$method->invoke(null,$input);
$result=MKT_Policy::evaluate_listing($data,[]);
$codes=array_column($result['errors'],'code');
assert_true(in_array('invalid_price',$codes,true),'Policy rejects a non-finite listing price.');
assert_true(in_array('invalid_quantity',$codes,true),'Policy rejects non-finite stock quantities.');
$input['price']='10.50';$input['quantity']='1';
$data=$method->invoke(null,$input);
$result=MKT_Policy::evaluate_listing($data,[]);
assert_true(!in_array('invalid_price',array_column($result['errors'],'code'),true),'Ordinary finite price remains permitted.');
assert_true(!in_array('invalid_quantity',array_column($result['errors'],'code'),true),'Ordinary finite stock remains permitted.');
fwrite(STDOUT,"File18 money and stock negative/nonfinite validation regression complete.\n");
