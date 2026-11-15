<?php
/** RC40 source manifest and migration snapshot fail-closed negative regression. */
declare(strict_types=1);
if(!defined('ABSPATH'))define('ABSPATH',__DIR__);
define('MKT_VERSION','2.1.0');
define('MKT_SCHEMA_VERSION','2.1.0');
$root=sys_get_temp_dir().'/file18-reality-'.bin2hex(random_bytes(6));
mkdir($root);
define('MKT_DIR',$root.'/');
function get_option($key,$default=''){return $key==='mkt_schema_version'?'1.2.0':$default;}
function get_bloginfo($key){return '7.0.1';}
class FakeDB{public function db_version(){return '8.0.36';}}
$wpdb=new FakeDB();
require dirname(__DIR__).'/includes/class-mkt-deployment-reality.php';
$checks=0;
function check_reality($truth,string $what):void{global $checks;$checks++;if(!$truth)throw new RuntimeException('FAIL: '.$what);}
function manifest(string $folder):void{
    $paths=['marketplace.php','includes/owner.php','languages/marketplace-ur.l10n.php'];
    $lines=[];
    foreach($paths as $item)$lines[]=hash_file('sha256',$folder.'/'.$item).'  ./'.$item;
    file_put_contents($folder.'/MANIFEST.sha256',implode("\n",$lines)."\n");
}
try{
 mkdir($root.'/includes');mkdir($root.'/languages');
 file_put_contents($root.'/marketplace.php','<?php echo "canonical";');
 file_put_contents($root.'/includes/owner.php','<?php class Owner {}');
 file_put_contents($root.'/languages/marketplace-ur.l10n.php','<?php return [];');
 $s=MKT_Deployment_Reality::verify_manifest($root);
 check_reality($s['status']==='manifest_missing','No manifest never passes');
 manifest($root);
 $s=MKT_Deployment_Reality::verify_manifest($root);
 check_reality($s['status']==='verified'&&$s['files_checked']===3,'Exact three-file manifest verified');
 check_reality(preg_match('/^[a-f0-9]{64}$/',$s['manifest_sha256'])===1,'Manifest fingerprint available');
 file_put_contents($root.'/includes/owner.php','<?php echo "tampered";');
 $s=MKT_Deployment_Reality::verify_manifest($root);
 check_reality($s['status']==='mismatch'&&$s['mismatch_count']===1,'Modified owned code fails closed');
 manifest($root);
 file_put_contents($root.'/unexpected.php','<?php echo "extra";');
 $s=MKT_Deployment_Reality::verify_manifest($root);
 check_reality($s['status']==='mismatch'&&$s['extra_file_count']===1,'Undeclared installed code detected');
 unlink($root.'/unexpected.php');
 $m=file_get_contents($root.'/MANIFEST.sha256');
 file_put_contents($root.'/MANIFEST.sha256',$m.hash('sha256','evil').'  ./../wp-config.php'."\n");
 $s=MKT_Deployment_Reality::verify_manifest($root);
 check_reality($s['status']==='manifest_invalid','Path traversal in manifest denied');
 manifest($root);
 $snap=MKT_Deployment_Reality::snapshot();
 check_reality($snap['source_manifest']['status']==='verified','Clean installed source snapshot');
 check_reality($snap['runtime']['plugin']==='2.1.0','Exact runtime plugin version');
 check_reality($snap['migration_markers']['mkt_schema_version']==='1.2.0','Migration marker remains distinguishable');
 check_reality($snap['repository_head']==='unverified','Repository HEAD never inferred from local file hashes');
 check_reality($snap['archive_sha256']===null,'Manifest hash never misrepresented as deployment ZIP checksum');
 check_reality($snap['deployment_parity']==='unverified'&&$snap['live_acceptance']==='unverified','No false live or repository parity');
}finally{
 foreach(['MANIFEST.sha256','marketplace.php','includes/owner.php','languages/marketplace-ur.l10n.php'] as $item)if(is_file($root.'/'.$item))unlink($root.'/'.$item);
 rmdir($root.'/includes');rmdir($root.'/languages');rmdir($root);
}
echo 'PASS RC40: '.$checks." installed-manifest reality and migration-marker assertions\n";
