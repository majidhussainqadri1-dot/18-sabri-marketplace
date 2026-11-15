<?php
/** RC42 source ownership contract; exact real MySQL smoke validates SQL execution. */
declare(strict_types=1);
$root=dirname(__DIR__).'/includes/';
$files=[
 'class-mkt-listings.php' => 3,
 'class-mkt-finalization.php' => 1,
];
$checks=0;
foreach($files as $name=>$expected){
 $source=file_get_contents($root.$name);
 $actual=substr_count($source,"MKT_Governance::table('recalls')");
 if($actual!==$expected)throw new RuntimeException('FAIL: '.$name.' uses wrong governance boundary; expected '.$expected.' got '.$actual);
 $checks++;
 if(str_contains($source,"MKT_DB::table('recalls')"))
   throw new RuntimeException('FAIL: '.$name.' requests a nonexistent canonical core table.');
 $checks++;
}
$core=file_get_contents($root.'class-mkt-db.php');
$governance=file_get_contents($root.'class-mkt-governance.php');
if(!str_contains($governance,"'recalls' => 'mkt_recalls'"))
  throw new RuntimeException('FAIL: Governance owner table registry missing recalls.');
$checks++;
if(preg_match("~'recalls'(?=\s*[,\]])~",$core))
  throw new RuntimeException('FAIL: Core table registry must not duplicate governance ownership.');
$checks++;
echo 'PASS RC42: '.$checks." public listing ownership and recall query assertions\n";
