<?php
/** RC45 actual File18 Listing::media() rights and public metadata regression. */
declare(strict_types=1);
defined('ABSPATH') || define('ABSPATH', __DIR__);
defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
class MKT_DB{public static function table($name){return 'wp_mkt_'.$name;}}
function esc_url_raw($u):string{return (string)$u;}
function wp_parse_url($u,$component=null){return parse_url((string)$u,$component);}
class StubMediaDatabase{
    public string $last_error='';
    public bool $fail=false;
    public string $last_sql='';
    public function prepare($sql,...$args){return vsprintf((string)$sql,$args);}
    public function get_results($sql,$kind){
        $this->last_sql=$sql;
        if($this->fail){$this->last_error='read failed';return null;}
        $record=[
            'public_id'=>'00000000-0000-4000-8000-000000000016',
            'provider'=>'central-media','provider_public_id'=>'public-media',
            'media_kind'=>'image','rights_status'=>'approved','scan_status'=>'clean',
            'alt_text'=>'Book cover','sort_order'=>1,'status'=>'active',
            'metadata_json'=>json_encode([
                'url'=>'https://cdn.example.org/book.png',
                'thumbnail_url'=>'https://cdn.example.org/thumb.png',
                'width'=>1280,'height'=>720,
                'gps_latitude'=>'31.5001','gps_longitude'=>'74.3',
                'patient_id'=>'confidential-patient',
                'secret_token'=>'private-token',
                'scanner_payload'=>['original_file'=>'medical-record.jpg'],
                'email'=>'owner-private@example.org',
                'unsafe_link'=>'javascript:alert(1)',
            ],JSON_THROW_ON_ERROR),
        ];
        return [$record];
    }
}
$wpdb=new StubMediaDatabase();
require dirname(__DIR__).'/includes/class-mkt-listings.php';
$n=0;
function rc45($ok,$why){global $n;$n++;if(!$ok)throw new RuntimeException('FAIL: '.$why);}
$public=MKT_Listings::media(1,true);
rc45(count($public)===1,'Approved public media present');
rc45(str_contains($wpdb->last_sql,"rights_status='approved'"),'Approved rights required at SQL boundary');
rc45(str_contains($wpdb->last_sql,"scan_status='clean'"),'Clean scan required at SQL boundary');
$meta=$public[0]['metadata'];
rc45(count($meta)===4,'Only two display URLs and bounded dimensions are exposed');
rc45(($meta['width']??null)===1280&&($meta['height']??null)===720,'Safe dimensions preserved');
rc45(($meta['url']??'')==='https://cdn.example.org/book.png','Public image URL preserved');
rc45(!array_key_exists('patient_id',$meta),'Patient identity redacted');
rc45(!array_key_exists('gps_latitude',$meta),'EXIF location redacted');
rc45(!array_key_exists('secret_token',$meta),'Provider secret redacted');
rc45(!array_key_exists('scanner_payload',$meta),'Scanner metadata redacted');
rc45(!array_key_exists('email',$meta),'Private email redacted');
rc45(!array_key_exists('metadata_json',$public[0]),'Opaque raw metadata removed from DTO');
$private=MKT_Listings::media(1,false);
rc45(($private[0]['metadata']['patient_id']??'')==='confidential-patient','Private owner DTO retains raw metadata within its separate authorization gate');
rc45(!str_contains($wpdb->last_sql,"rights_status='approved'"),'Private owner fetch is distinct from public-safe query');
$wpdb->fail=true;
try{MKT_Listings::media(1,true);$failed=false;}catch(RuntimeException $e){$failed=true;}
rc45($failed,'Failed SQL media read denies public projection');
echo 'PASS RC45: '.$n." real-class public metadata redaction assertions\n";
