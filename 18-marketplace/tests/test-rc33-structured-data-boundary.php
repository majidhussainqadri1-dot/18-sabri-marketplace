<?php
/**
 * RC33 actual MKT_Routes JSON-LD output regression with hostile listing data.
 * Stubbed WordPress environment: does not prove production security.
 */
declare(strict_types=1);
if(!defined('ABSPATH'))define('ABSPATH',__DIR__);
function sanitize_key($x):string{return strtolower((string)$x);}
function get_query_var($name){
    return $name==='mkt_route'?'listing':($name==='mkt_object_id'?'00000000-0000-4000-8000-000000000001':'');
}
function esc_url($x):string{return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}
function esc_url_raw($x):string{return (string)$x;}
function wp_trim_words($s,$n=60,$more=''):string{return (string)$s;}
function wp_strip_all_tags($s):string{return strip_tags((string)$s);}
function wp_json_encode($v,$opts=0):string{return json_encode($v,$opts|JSON_THROW_ON_ERROR);}
$GLOBALS['wpdb']=(object)['last_error'=>''];
class MKT_Listings {
    public static string $fail='';
    public static function get(string $id,bool $private=false):?array{
        global $wpdb;
        if(self::$fail==='owner_throw')throw new RuntimeException('owner unavailable');
        if(self::$fail==='owner_sql'){$wpdb->last_error='SQL private failure';return null;}
        return ['public_id'=>$id];
    }
    public static function public_dto(array $row):array{
        global $wpdb;
        if(self::$fail==='dto_throw')throw new RuntimeException('media unavailable');
        if(self::$fail==='dto_sql')$wpdb->last_error='media read failed';
        return [
            'url'=>'https://sabrihomeopathy.com/marketplace/listing/00000000-0000-4000-8000-000000000001/example/',
            'public_id'=>$row['public_id'],'listing_type'=>'product',
            'title'=>'A </script><script>alert(1)</script> book',
            'description'=>'A <script>alert(2)</script> item',
            'price'=>'10','currency'=>'PKR','availability'=>'available',
            'seller'=>['store_name'=>'Seller </script><svg onload=alert(3)>'],
            'media'=>[],
        ];
    }
}
require dirname(__DIR__).'/includes/class-mkt-routes.php';
ob_start();
MKT_Routes::structured_data();
$out=ob_get_clean();
$count=0;
function check(bool $ok,string $desc):void{
    global $count;
    $count++;
    if(!$ok)throw new RuntimeException('FAIL: '.$desc);
}
check(substr_count($out,'<script type="application/ld+json">')===1,'One JSON-LD tag only');
check(substr_count($out,'</script>')===1,'Only closing script is the wrapper');
check(!str_contains($out,'</script><script>'),'Hostile nested script tags escaped');
check(!str_contains($out,'<svg onload'),'Hostile SVG markup escaped');
check(str_contains($out,'\u003C'),'HTML LT encoded inside JSON');
check(str_contains($out,'\u003E'),'HTML GT encoded inside JSON');
preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$out,$matches);
check(isset($matches[1]),'Embedded data located');
$data=json_decode($matches[1],true,512,JSON_THROW_ON_ERROR);
check($data['name']==='A </script><script>alert(1)</script> book','Data remains semantically intact');
check($data['offers']['seller']['name']==='Seller </script><svg onload=alert(3)>','Seller text safely roundtrips');
check($data['offers']['priceCurrency']==='PKR','Price information preserved');
check(str_contains($out,'rel="canonical"'),'Canonical route still rendered');
foreach(['owner_throw','owner_sql','dto_throw','dto_sql'] as $fault){
    MKT_Listings::$fail=$fault;
    ob_start();
    MKT_Routes::structured_data();
    $unavailable=ob_get_clean();
    check($unavailable==='','No canonical or JSON-LD leaks on '. $fault);
}
MKT_Listings::$fail='';
ob_start();
MKT_Routes::structured_data();
$recovered=ob_get_clean();
check(str_contains($recovered,'application/ld+json'),'Valid metadata returns after source recovery');
echo 'PASS RC33: '.$count." JSON-LD script-boundary assertions\n";
