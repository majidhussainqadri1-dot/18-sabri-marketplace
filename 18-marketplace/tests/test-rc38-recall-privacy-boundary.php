<?php
/** RC38 test of actual listing.php: private recalled row must never be displayed. */
declare(strict_types=1);
if(!defined('ABSPATH'))define('ABSPATH',__DIR__);
function get_query_var($key){return $key==='mkt_object_id'?'00000000-0000-4000-8000-000000000038':'';}
function status_header($status):void{$GLOBALS['status']=$status;}
function nocache_headers():void{$GLOBALS['no_cache']=true;}
function home_url($path='/'):string{return 'https://sabrihomeopathy.com'.$path;}
function esc_html_e($text,$domain=''):void{echo htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function esc_html($text):string{return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function esc_url($text):string{return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function __($text,$domain=''):string{return (string)$text;}
class MKT_Governance {
  public static bool $active=true;
  public static function public_recall($id):?array{return self::$active?
  ['reason'=>'Confirmed safety notice','batch_ref'=>'B-2026','regulator_reference'=>'R-2026']:null;}
}
class MKT_Listings {
  public static bool $published=true;
  public static bool $stale_public_read=false;
  public static int $dto_calls=0;
  public static function get($id,$private=false):?array{
    $row=[
      'published_at'=>self::$published?'2026-10-01 00:00:00':null,
      'title'=>'PRIVATE_PATIENT_TITLE','price'=>'9999','description'=>'SECRET_MEDICAL_DETAILS',
      'location_city'=>'PRIVATE_RESIDENCE','store_name'=>'PRIVATE_SELLER'
    ];
    return $private||self::$stale_public_read?$row:null;
  }
  public static function public_dto($row):array{self::$dto_calls++;throw new RuntimeException('Private DTO reached');}
}
$dir=sys_get_temp_dir().'/rc38-'.bin2hex(random_bytes(6));
mkdir($dir);mkdir($dir.'/templates');
file_put_contents($dir.'/templates/_header.php','<?php echo "<header>Public shell</header>";');
file_put_contents($dir.'/templates/_footer.php','<?php echo "<footer>End</footer>";');
define('MKT_DIR',$dir.'/');
$checks=0;
function check_rc38($yes,$name):void{global $checks;$checks++;if(!$yes)throw new RuntimeException('FAIL: '.$name);}
function run_template():string{ob_start();require dirname(__DIR__).'/templates/listing.php';return ob_get_clean();}
try{
  $html=run_template();
  check_rc38(str_contains($html,'Recall / takedown notice'),'Previously published recalled safety notice shown');
  check_rc38(str_contains($html,'Confirmed safety notice'),'Approved safety reason retained');
  check_rc38(str_contains($html,'B-2026')&&str_contains($html,'R-2026'),'Public batch and regulatory references retained');
  foreach(['PRIVATE_PATIENT_TITLE','SECRET_MEDICAL_DETAILS','PRIVATE_RESIDENCE','PRIVATE_SELLER','9999','data-mkt-offer'] as $private){
    check_rc38(!str_contains($html,$private),'No private content: '.$private);
  }
  check_rc38(MKT_Listings::$dto_calls===0,'Private object never enters public DTO');
  check_rc38(($GLOBALS['no_cache']??false)===true,'Recall response no-cache');
  MKT_Listings::$stale_public_read=true;
  $html=run_template();
  check_rc38(str_contains($html,'Recall / takedown notice'),'Concurrent stale public listing read still produces only recall safety notice');
  check_rc38(!str_contains($html,'PRIVATE_PATIENT_TITLE')&&!str_contains($html,'SECRET_MEDICAL_DETAILS'),'Late recall never republishes stale public Listing DTO');
  check_rc38(MKT_Listings::$dto_calls===0,'Late recall never invokes full public DTO');
  MKT_Listings::$stale_public_read=false;
  MKT_Listings::$published=false;$GLOBALS['status']=null;
  $html=run_template();
  check_rc38(($GLOBALS['status']??null)===404,'Unpublished draft remains HTTP 404');
  check_rc38(!str_contains($html,'Confirmed safety notice'),'No private draft recall disclosure');
  check_rc38(MKT_Listings::$dto_calls===0,'Unpublished draft never projected');
}finally{
  unlink($dir.'/templates/_header.php');unlink($dir.'/templates/_footer.php');
  rmdir($dir.'/templates');rmdir($dir);
}
echo 'PASS RC38: '.$checks." real-template recall privacy assertions\n";
