<?php
/**
 * File18 native PUBLIC marketplace listing → File26 read-only search source.
 * The sole source of seller approval, recalls, expiry and listing moderation
 * remains MKT_Listings. Private drafts, offers, deals, buyer information and
 * media grants are NEVER copied into search.
 *
 * PROPOSED until owner event-to-tombstone proof, outbox reconciliation,
 * staging, and separate authorized Founder acceptance.
 */
defined('ABSPATH') || exit;
final class MKT_File26_Connector {
    const SLUG='file18-public-listings';
    private static array $receipts = array();
    private const PUBLIC_EVENTS = array(
        'MarketplaceListingCreated.v1','MarketplaceListingSubmitted.v1',
        'MarketplaceListingPublished.v1','MarketplaceListingStatusChanged.v1',
        'MarketplaceListingEvidenceReviewed.v1',
        'MarketplaceRecallActivated.v1','MarketplaceRecallResolved.v1'
    );
    public static function register(){
        // Register through File26's schema-verified boot, never eagerly at plugin
        // file-load time. Otherwise plugin load order can write connector records
        // before File26's migrations and can register the same source twice.
        add_filter('sabri_file26_connector_manifests',array(__CLASS__,'append'),20);
        add_action('mkt_event',array(__CLASS__,'on_outbox_event'),30,1);
        add_filter('mkt_required_event_consumers_acknowledged',array(__CLASS__,'require_ack'),20,2);
    }
    public static function manifest(){
        return array(
            'slug'=>self::SLUG,'owner_file'=>'File 18','contract_version'=>'1.0',
            'entity_types'=>array('marketplace_listing'),
            'privacy_classes'=>array('public'),'visibility_fields'=>array('public'),
            'deletion_semantics'=>'owner_recall_expiry_tombstone_gate_pending',
            'status'=>'proposed','event_contract'=>'MarketplaceListingTransitioned.v1.pending-file26-cutover',
            'index_schema'=>'sabri.file26.document.v1.1',
            'list_batch'=>array(__CLASS__,'list_batch'),
            'can_view'=>array(__CLASS__,'can_view'),
            'fetch_object'=>array(__CLASS__,'fetch_object'),
            'health'=>array(__CLASS__,'health')
        );
    }
    public static function append($manifests){
        $manifests=is_array($manifests)?$manifests:array();
        foreach($manifests as $item){if(is_array($item)&&($item['slug']??'')===self::SLUG){return $manifests;}}
        $manifests[]=self::manifest();return $manifests;
    }
    public static function ready($registry){
        if(is_object($registry)&&method_exists($registry,'register')){$registry->register(self::manifest());}
    }
    private static function active(): bool {
        if(!class_exists('\\Sabri\\File26\\Plugin'))return false;
        try {
            $plugin=\\Sabri\\File26\\Plugin::instance();
            return is_object($plugin)&&method_exists($plugin,'connectors')
                &&$plugin->connectors()->is_active(self::SLUG)===true;
        } catch(Throwable $e){return false;}
    }
    private static function relevant($event): bool {
        return is_array($event)&&($event['aggregate']['type']??'')==='listing'
            &&in_array((string)($event['event_type']??''),self::PUBLIC_EVENTS,true);
    }
    /** Synchronous, retry-safe handoff; never acknowledge an unverified callback. */
    public static function on_outbox_event($event): void {
        if(!self::active()||!self::relevant($event))return;
        $id=(string)($event['event_id']??'');
        if($id==='')return;
        self::$receipts[$id]=self::sync_public_listing((string)($event['aggregate']['public_id']??''));
    }
    public static function require_ack($previous,$event): bool {
        if(!self::active()||!self::relevant($event))return $previous===true;
        $id=(string)($event['event_id']??'');
        $received=$id!==''&&(self::$receipts[$id]??false)===true;
        unset(self::$receipts[$id]);
        return $previous===true&&$received;
    }
    /** Read canonical current owner state; version protects out-of-order delivery. */
    private static function sync_public_listing(string $public_id): bool {
        global $wpdb;
        if(!preg_match('/^[a-f0-9-]{36}$/iD',$public_id)||!self::source_ready())return false;
        if(!function_exists('sabri_file26_upsert_document')||
           !function_exists('sabri_file26_tombstone_document'))return false;
        try{
            $wpdb->last_error='';
            $owner=MKT_Listings::get($public_id,true);
            if($wpdb->last_error!==''||!is_array($owner))return false;
            $version=(int)($owner['version']??0);
            if($version<1)return false;
            $wpdb->last_error='';
            $public=MKT_Listings::get($public_id,false);
            if($wpdb->last_error!=='')return false;
            if(is_array($public)){
                $wpdb->last_error='';
                $document=self::project($public,$version,true);
                if(is_wp_error($document)||$wpdb->last_error!=='')return false;
                if(is_array($document)){
                    $result=sabri_file26_upsert_document($document);
                    return !is_wp_error($result)&&($result===true||(is_string($result)&&$result!==''));
                }
            }
            $result=sabri_file26_tombstone_document(self::SLUG,'marketplace',$public_id,$version,'restricted');
            return $result===true;
        }catch(Throwable $e){return false;}
    }
    private static function source_ready(){
        // Safe Mode blocks mutations, not policy-checked public reading.
        // Unverified canonical schema still fails closed.
        return class_exists('MKT_DB')&&class_exists('MKT_Listings')&&
            MKT_DB::schema_ready();
    }
    private static function project($row,$known_version=0,$strict=false){
        if(!self::source_ready()||!is_array($row)||($row['status']??'')!=='active'||
            ($row['seller_status']??'')!=='approved'){return null;}
        $version=(int)($row['version']??0);
        if($version<1||($known_version>0&&$known_version!==$version)){return null;}
        if(!MKT_Listings::not_expired($row['expires_at']??null)){return null;}
        $id=(string)($row['public_id']??'');
        if(!preg_match('/^[a-f0-9-]{36}$/iD',$id)){return null;}
        // Fetch through the owner's PUBLIC listing policy (checks seller,
        // expiry and active recall). Do not trust an intermediate SQL row.
        $latest=MKT_Listings::get($id,false);
        if(!is_array($latest)||(int)$latest['version']!==$version){return null;}
        try{$dto=MKT_Listings::public_dto($latest);}catch(Throwable $e){
            return $strict ? new WP_Error('file18_search_projection_unavailable','Marketplace public listing projection is unavailable.') : null;
        }
        if(!is_array($dto)||empty($dto['title'])||empty($dto['url'])){
            return $strict ? new WP_Error('file18_search_projection_invalid','Marketplace public listing projection is incomplete.') : null;
        }
        $url=esc_url_raw((string)$dto['url']);
        if(!$url||!wp_http_validate_url($url)){
            return $strict ? new WP_Error('file18_search_projection_invalid','Marketplace public listing URL is invalid.') : null;
        }
        $title=sanitize_text_field((string)$dto['title']);
        $category=sanitize_text_field((string)($dto['category']??''));
        $city=sanitize_text_field((string)($dto['location_city']??''));
        return array(
            'connector_slug'=>self::SLUG,'domain'=>'marketplace',
            'object_id'=>$id,'object_version'=>$version,'source_event_sequence'=>$version,
            'entity_type'=>'marketplace_listing',
            'locale'=>substr(sanitize_text_field((string)($dto['language']??'en-US')),0,20),
            'state'=>'published','visibility'=>'public','title'=>$title,
            'excerpt'=>trim($category.' '.$city),'search_text'=>$title.' '.$category.' '.$city,
            'canonical_url'=>$url,'safety_class'=>'seller_approved_public_listing'
        );
    }
    public static function list_batch($cursor='',$limit=50,$scope=array()){
        global $wpdb;
        if(!self::source_ready()){return new WP_Error('file18_owner_schema_unverified','Marketplace schema/governance unavailable.');}
        if(!is_array($scope)||isset($scope['object_id'])){return new WP_Error('file18_search_partial_scope','Full owner snapshot required.');}
        if($cursor!==''&&(!is_string($cursor)||!preg_match('/^[0-9]{1,18}$/D',$cursor))){return new WP_Error('file18_search_invalid_cursor','Invalid owner cursor.');}
        $table=MKT_DB::table('listings');$limit=max(1,min(50,(int)$limit));$id=(int)$cursor;$wpdb->last_error='';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,public_id,version FROM $table WHERE id>%d ORDER BY id ASC LIMIT %d",$id,$limit+1),ARRAY_A);
        if(!is_array($rows)||$wpdb->last_error){return new WP_Error('file18_owner_unreadable','Marketplace owner cannot provide a safe source snapshot.');}
        $more=count($rows)>$limit;if($more){array_pop($rows);}
        $items=array();
        foreach($rows as $row){
            $wpdb->last_error='';
            $latest=MKT_Listings::get((string)$row['public_id'],false);
            if($wpdb->last_error!==''){
                return new WP_Error('file18_owner_unreadable','Marketplace public listing eligibility could not be verified.');
            }
            if(is_array($latest)){
                // Do not represent a failed owner/media read as a successful partial page.
                $wpdb->last_error='';
                $doc=self::project($latest,(int)$row['version'],true);
                if(is_wp_error($doc))return $doc;
                if($wpdb->last_error!==''){
                    return new WP_Error('file18_owner_unreadable','Marketplace public listing projection could not be verified.');
                }
                if($doc){$items[]=$doc;}
            }
            $id=max($id,(int)$row['id']);
        }
        return array('items'=>$items,'done'=>!$more,'next_cursor'=>$more?(string)$id:'');
    }
    public static function fetch_object($type,$public_id=''){
        if($type!=='marketplace_listing'||!is_string($public_id)||!preg_match('/^[a-f0-9-]{36}$/iD',$public_id)||!self::source_ready()){return null;}
        return self::project(MKT_Listings::get($public_id,false));
    }
    public static function can_view($document,$audience=array()){
        if(!is_array($document)||($document['connector_slug']??'')!==self::SLUG||
            ($document['visibility']??'')!=='public'){return false;}
        $latest=self::fetch_object((string)($document['entity_type']??''),(string)($document['object_id']??''));
        return is_array($latest)&&(int)$latest['object_version']===(int)($document['object_version']??0);
    }
    public static function health(){
        return self::source_ready()
            ? array('state'=>'healthy','owner_file'=>'File 18','status'=>'proposed','recall_expiry_recheck'=>true,'source_event_cutover_approved'=>false)
            : array('state'=>'degraded','owner_file'=>'File 18','reason'=>'marketplace_schema_or_governance_unavailable');
    }
}
