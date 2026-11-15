<?php
/** RC37 exact PHP translation catalog and dynamic UI hooks; stub, not browser QA. */
declare(strict_types=1);
$root=dirname(__DIR__);
$catalogues=['ur','ur_PK','ar'];
$counts=[];
$must=[
  'Back','Home','Marketplace','Search','Price','Seller','Listing',
  'View listing','Create listing','Zero commission policy','Make an offer',
  'Submit structured offer','Report listing','Recall / takedown notice',
  'False cure claim','Privacy violation','Open dispute','Submit for review',
  'Seller Studio','Approved media reference','Currency','Language',
  'Report submitted for review.','Offer submitted.','Remove this media reference?',
  'Batch/reference: %s','Regulatory reference: %s',
  'Search results unavailable','Search temporarily unavailable. Please try again later.',
  'Please correct the search filters and try again.'
];
$checks=0;
function verify_lang(bool $condition,string $description):void{
 global $checks;$checks++;
 if(!$condition)throw new RuntimeException('FAIL: '.$description);
}
foreach($catalogues as $locale){
 $path="$root/languages/marketplace-$locale.l10n.php";
 verify_lang(is_file($path),'Catalogue exists: '.$locale);
 $data=require $path;
 verify_lang(($data['language']??null)===$locale,'Locale identity matches file');
 verify_lang(is_array($data['messages']??null),'WordPress PHP l10n messages shape');
 $messages=$data['messages'];
 verify_lang(count($messages)>=130,'Catalog has broad translated UI coverage');
 foreach($must as $key){
   verify_lang(isset($messages[$key])&&is_string($messages[$key])&&$messages[$key]!==''&&$messages[$key]!==$key,'UI key translated: '.$locale.' / '.$key);
 }
 foreach($messages as $original=>$translated){
   preg_match_all('/%(?:[0-9]+\$)?[sd]/',$original,$a);
   preg_match_all('/%(?:[0-9]+\$)?[sd]/',$translated,$b);
   sort($a[0]);sort($b[0]);
   verify_lang($a[0]===$b[0],'Formatting placeholders preserved: '.$locale.' / '.$original);
 }
 $counts[$locale]=count($messages);
}
$routes=file_get_contents("$root/includes/class-mkt-routes.php");
$js=file_get_contents("$root/assets/js/marketplace.js");
$css=file_get_contents("$root/assets/css/marketplace.css");
verify_lang(str_contains($routes,"'offerSubmitted' => __('Offer submitted.'"),'WP localizes offer success');
verify_lang(str_contains($routes,"'reportSubmitted' => __('Report submitted for review.'"),'WP localizes report success');
verify_lang(str_contains($js,"tr('offerSubmitted'"),'Client receives localized offer notice');
verify_lang(str_contains($js,"tr('removeMediaConfirm'"),'Client receives localized confirm');
verify_lang(str_contains($css,'[dir="rtl"] .mkt-container'),'Explicit RTL readability styles');
verify_lang(str_contains($css,'overflow-wrap:anywhere'),'Long Urdu/Arabic labels avoid forced clipping');
echo 'PASS RC37: '.json_encode($counts).' language entries; '.$checks." catalog/RTL assertions\n";
