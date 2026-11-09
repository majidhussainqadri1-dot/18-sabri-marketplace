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
    public static function register(){
        add_filter('sabri_file26_connector_manifests',array(__CLASS__,'append'),20);
        add_action('sabri_file26_connectors_ready',array(__CLASS__,'ready'),20);
        if(function_exists('sabri_file26_register_connector')){sabri_file26_register_connector(self::manifest());}
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
    private static function source_ready(){
        return class_exists('MKT_DB')&&class_exists('MKT_Listings')&&
            MKT_DB::schema_ready()&&!((bool)get_option('mkt_safe_mode',false));
    }
    private static function project($row,$known_version=0){
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
        try{$dto=MKT_Listings::public_dto($latest);}catch(Throwable $e){return null;}
        if(!is_array($dto)||empty($dto['title'])||empty($dto['url'])){return null;}
        $url=esc_url_raw((string)$dto['url']);
        if(!$url||!wp_http_validate_url($url)){return null;}
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
            $latest=MKT_Listings::get((string)$row['public_id'],false);
            if(is_array($latest)){$doc=self::project($latest,(int)$row['version']);if($doc){$items[]=$doc;}}
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
