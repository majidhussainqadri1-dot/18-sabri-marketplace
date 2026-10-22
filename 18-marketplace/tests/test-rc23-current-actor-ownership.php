<?php
/** RC23: 25 real canonical entry points reject actor/user ID spoofing before owner DB reads or writes. */
require __DIR__.'/bootstrap.php';
function get_current_user_id():int {return 41;}
defined('ARRAY_A') || define('ARRAY_A','ARRAY_A');
class ForbiddenDB {
 public int $reads=0;public int $writes=0;
 public function __call($method,$args){if(in_array($method,['get_row','get_var','get_results','get_col','prepare'],true))$this->reads++;else $this->writes++;throw new RuntimeException('DB access before actor authorization: '.$method);}
}
$wpdb=new ForbiddenDB();
require dirname(__DIR__).'/includes/class-mkt-commerce.php';
require dirname(__DIR__).'/includes/class-mkt-listings.php';
require dirname(__DIR__).'/includes/class-mkt-moderation.php';
require dirname(__DIR__).'/includes/class-mkt-governance.php';

$checks=[
 'Commerce.create_offer'=>static fn($n)=>MKT_Commerce::create_offer('listing-a',['amount'=>200],$n,'rc23-idempotency-123'),
 'Commerce.transition_offer'=>static fn($n)=>MKT_Commerce::transition_offer('offer-a','accepted',[],$n,1,'rc23-idempotency-456'),
 'Commerce.transition_deal'=>static fn($n)=>MKT_Commerce::transition_deal('deal-a','completed',[],$n,1),
 'Commerce.dashboard'=>static fn($n)=>MKT_Commerce::dashboard($n),
 'Commerce.get_deal'=>static fn($n)=>MKT_Commerce::get_deal('deal-a',$n),
 'Listings.ensure_seller'=>static fn($n)=>MKT_Listings::ensure_seller($n),
 'Listings.create'=>static fn($n)=>MKT_Listings::create(['title'=>'Test'],$n),
 'Listings.update'=>static fn($n)=>MKT_Listings::update('listing-a',['title'=>'Changed'],$n,1),
 'Listings.submit'=>static fn($n)=>MKT_Listings::submit('listing-a',$n,1),
 'Listings.transition'=>static fn($n)=>MKT_Listings::transition('listing-a','paused',$n,1),
 'Listings.attach_media'=>static fn($n)=>MKT_Listings::attach_media('listing-a',[],$n),
 'Listings.remove_media'=>static fn($n)=>MKT_Listings::remove_media('listing-a','media-a',$n),
 'Listings.save'=>static fn($n)=>MKT_Listings::save('listing-a',$n),
 'Listings.open_chat'=>static fn($n)=>MKT_Listings::open_chat('listing-a',$n),
 'Moderation.create_report'=>static fn($n)=>MKT_Moderation::create_report([],$n),
 'Moderation.transition_report'=>static fn($n)=>MKT_Moderation::transition_report('report-a','closed',[],$n,1),
 'Moderation.open_dispute'=>static fn($n)=>MKT_Moderation::open_dispute('deal-a',[],$n),
 'Moderation.transition_dispute'=>static fn($n)=>MKT_Moderation::transition_dispute('dispute-a','closed',[],$n,1),
 'Governance.save_evidence'=>static fn($n)=>MKT_Governance::save_evidence('listing-a',[],$n),
 'Governance.review_evidence'=>static fn($n)=>MKT_Governance::review_evidence('listing-a',[],$n),
 'Governance.seller_studio'=>static fn($n)=>MKT_Governance::seller_studio($n),
 'Governance.create_recall'=>static fn($n)=>MKT_Governance::create_recall([],$n),
 'Governance.transition_recall'=>static fn($n)=>MKT_Governance::transition_recall('recall-a',[],$n),
 'Governance.create_hold'=>static fn($n)=>MKT_Governance::create_hold([],$n),
 'Governance.release_hold'=>static fn($n)=>MKT_Governance::release_hold('hold-a',[],$n),
];
assert_same(25,count($checks),'RC23 must test all 25 current-actor entrypoints.');
foreach([99,0] as $spoof){
 foreach($checks as $name=>$call){
  $result=$call($spoof);
  assert_true(is_wp_error($result),$name.' denies spoofed actor '.(string)$spoof);
  assert_same('mkt_actor_mismatch',$result->get_error_code(),$name.' returns stable IDOR denial.');
 }
}
// A private search is a separate read surface with no mandatory actor argument.
foreach([[], ['seller_user_id'=>99], ['seller_user_id'=>0]] as $filter) {
 $private=MKT_Listings::search($filter,true);
 assert_same('mkt_private_search_forbidden',$private['error']??null,'Private listing search requires the exact logged-in seller ID.');
 assert_same([],$private['items'],'Private listing search never leaks another seller draft.');
}
assert_same(0,$wpdb->reads,'No private owner read occurs on 50 mismatched actor paths or 3 unscoped private searches.');
assert_same(0,$wpdb->writes,'No state/audit/outbox write occurs on 50 identity-mismatched paths.');
fwrite(STDOUT,"RC23 25 canonical owner actions x 2 spoofed users fail closed before DB access.\n");