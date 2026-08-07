<?php
defined('ABSPATH') || exit;

/**
 * Final four-plan completion layer for File 18.
 *
 * This class remains inside File 18's canonical owner. It supplies the local
 * listing-language facet, atomic REST mutations, complete report taxonomy and
 * fresh operational authorization without creating a second Marketplace store.
 */
final class MKT_Plan_Completion {
    public const SCHEMA_VERSION = '1.0.1';

    public static function activate(): void { self::install_schema(); }
    public static function boot(): void {
        self::maybe_upgrade();
        add_action('rest_api_init', [self::class, 'override_routes'], 100);
        add_filter('mkt_assurance_manifest', [self::class, 'assurance_manifest']);
    }
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'mkt_listing_facets'; }
    private static function maybe_upgrade(): void { if ((string)get_option('mkt_plan_completion_schema_version','') !== self::SCHEMA_VERSION) self::install_schema(); }
    private static function install_schema(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb; $table=self::table(); $c=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            listing_id bigint unsigned NOT NULL,
            language varchar(35) NOT NULL DEFAULT 'und',
            version bigint unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (listing_id),
            KEY language (language)
        ) {$c};");
        update_option('mkt_plan_completion_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function override_routes(): void {
        $ns=MKT_Contracts::REST_NAMESPACE;
        register_rest_route($ns,'/listings',[
            ['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'search'],'permission_callback'=>'__return_true'],
            ['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'create_listing'],'permission_callback'=>[MKT_REST::class,'logged_in']],
        ],true);
        register_rest_route($ns,'/listings/(?P<id>[a-f0-9-]{36})',[
            ['methods'=>WP_REST_Server::READABLE,'callback'=>[MKT_REST::class,'listing'],'permission_callback'=>'__return_true'],
            ['methods'=>WP_REST_Server::EDITABLE,'callback'=>[self::class,'update_listing'],'permission_callback'=>[MKT_REST::class,'logged_in']],
        ],true);
        register_rest_route($ns,'/listings/(?P<id>[a-f0-9-]{36})/submit',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'submit_listing'],'permission_callback'=>[MKT_REST::class,'logged_in']],true);
        register_rest_route($ns,'/listings/(?P<id>[a-f0-9-]{36})/transition',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'transition_listing'],'permission_callback'=>[MKT_REST::class,'logged_in']],true);
        register_rest_route($ns,'/deals/(?P<id>[a-f0-9-]{36})/transition',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'transition_deal'],'permission_callback'=>[MKT_REST::class,'logged_in']],true);
        register_rest_route($ns,'/reports',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'create_report'],'permission_callback'=>[MKT_REST::class,'logged_in']],true);
        register_rest_route($ns,'/reports/(?P<id>[a-f0-9-]{36})/transition',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'transition_report'],'permission_callback'=>[MKT_REST::class,'logged_in']],true);
        register_rest_route($ns,'/disputes/(?P<id>[a-f0-9-]{36})/transition',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'transition_dispute'],'permission_callback'=>[MKT_REST::class,'logged_in']],true);
        register_rest_route($ns,'/system-check',['methods'=>WP_REST_Server::READABLE,'callback'=>[MKT_REST::class,'system_check'],'permission_callback'=>[self::class,'can_view_system']],true);
        register_rest_route($ns,'/repair',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[MKT_REST::class,'repair'],'permission_callback'=>[self::class,'can_repair']],true);
    }

    public static function can_view_system(): bool|WP_Error {
        if (!current_user_can('mkt_view_system')) return false;
        return MKT_Auth::can('mkt_view_system',['action'=>'system_check']);
    }
    public static function can_repair(): bool|WP_Error {
        if (!current_user_can('manage_options')) return false;
        return MKT_Auth::can('mkt_manage_policies',['action'=>'marketplace_repair']);
    }

    public static function search(WP_REST_Request $request): WP_REST_Response {
        if (class_exists('MKT_Finalization')) return MKT_Finalization::search($request);
        return rest_ensure_response(['items'=>[],'next_cursor'=>null,'has_more'=>false]);
    }

    public static function create_listing(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $result=self::mutate_atomic('create_listing_atomic',$request,$data,function() use ($data) {
            $listing=MKT_Listings::create($data,get_current_user_id());
            if (is_wp_error($listing)) return $listing;
            $synced=self::sync_facets((string)$listing['public_id'],$data,get_current_user_id());
            if (is_wp_error($synced)) return $synced;
            return self::decorate_listing($listing);
        });
        return self::respond($result);
    }

    public static function update_listing(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $result=self::mutate_atomic('update_listing_atomic',$request,$data,function() use ($request,$data) {
            $listing=MKT_Listings::update((string)$request['id'],$data,get_current_user_id(),(int)($data['version'] ?? 0));
            if (is_wp_error($listing)) return $listing;
            $synced=self::sync_facets((string)$request['id'],$data,get_current_user_id());
            if (is_wp_error($synced)) return $synced;
            return self::decorate_listing($listing);
        });
        return self::respond($result);
    }

    public static function submit_listing(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $result=self::mutate_atomic('submit_listing_atomic',$request,$data,function() use ($request,$data) {
            $gate=self::publication_gate((string)$request['id']);
            if (is_wp_error($gate)) return $gate;
            return MKT_Listings::submit((string)$request['id'],get_current_user_id(),(int)($data['version'] ?? 0));
        });
        return self::respond($result);
    }

    public static function transition_listing(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []); $to=sanitize_key((string)($data['to'] ?? ''));
        $result=self::mutate_atomic('transition_listing_atomic',$request,$data,function() use ($request,$data,$to) {
            if ($to==='active') { $gate=self::publication_gate((string)$request['id']); if (is_wp_error($gate)) return $gate; }
            return MKT_Listings::transition((string)$request['id'],$to,get_current_user_id(),(int)($data['version'] ?? 0),(string)($data['reason'] ?? ''),(string)($data['note'] ?? ''));
        });
        return self::respond($result);
    }

    public static function transition_deal(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $result=self::mutate_atomic('transition_deal_atomic',$request,$data,fn()=>MKT_Commerce::transition_deal((string)$request['id'],sanitize_key((string)($data['to'] ?? '')),$data,get_current_user_id(),(int)($data['version'] ?? 0)));
        return self::respond($result);
    }
    public static function transition_report(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $result=self::mutate_atomic('transition_report_atomic',$request,$data,fn()=>MKT_Moderation::transition_report((string)$request['id'],sanitize_key((string)($data['to'] ?? '')),$data,get_current_user_id(),(int)($data['version'] ?? 0)));
        return self::respond($result);
    }
    public static function transition_dispute(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $result=self::mutate_atomic('transition_dispute_atomic',$request,$data,fn()=>MKT_Moderation::transition_dispute((string)$request['id'],sanitize_key((string)($data['to'] ?? '')),$data,get_current_user_id(),(int)($data['version'] ?? 0)));
        return self::respond($result);
    }

    public static function create_report(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $result=self::mutate_atomic('create_report_complete_taxonomy',$request,$data,fn()=>self::create_report_record($data,get_current_user_id()));
        return self::respond($result);
    }

    private static function create_report_record(array $input,int $user_id): array|WP_Error {
        $auth=MKT_Auth::can('mkt_buy',['action'=>'report']); if (is_wp_error($auth)) return $auth;
        $target_type=sanitize_key((string)($input['target_type'] ?? 'listing')); $target_public_id=sanitize_text_field((string)($input['target_public_id'] ?? ''));
        if (!in_array($target_type,['listing','seller','offer','deal'],true) || !wp_is_uuid($target_public_id)) return new WP_Error('mkt_invalid_report',__('Invalid report target.','marketplace'),['status'=>422]);
        $target=self::report_target($target_type,$target_public_id,$user_id); if (is_wp_error($target)) return $target;
        $allowed=['illegal','harm','fraud','scam','counterfeit','unsafe','unsafe_claim','false_claim','false_cure_claim','privacy','harassment','abuse','impersonation','copyright','minor_safety','child_safety','non_delivery','misleading_price','other'];
        $reason=sanitize_key((string)($input['reason'] ?? 'other')); if (!in_array($reason,$allowed,true)) $reason='other';
        $details=wp_kses_post((string)($input['details'] ?? '')); if (mb_strlen(wp_strip_all_tags($details))<10) return new WP_Error('mkt_report_details_required',__('Please describe the concern clearly.','marketplace'),['status'=>422]);
        $refs=array_slice(array_values(array_unique(array_filter(array_map(static function($value): string { $value=sanitize_text_field((string)$value); return strlen($value)<=190?$value:''; },(array)($input['evidence_refs'] ?? []))))),0,20);
        global $wpdb; $public_id=MKT_DB::uuid();
        $inserted=$wpdb->insert(MKT_DB::table('reports'),['public_id'=>$public_id,'reporter_user_id'=>$user_id,'target_type'=>$target_type,'target_public_id'=>$target_public_id,'reason'=>$reason,'details'=>$details,'evidence_refs'=>wp_json_encode($refs),'status'=>'submitted','version'=>1,'created_at'=>MKT_DB::now(),'updated_at'=>MKT_DB::now()]);
        if (!$inserted) return new WP_Error('mkt_report_failed',__('The report could not be submitted.','marketplace'),['status'=>500]);
        MKT_Audit::record('report_submitted','report',$public_id,['target_type'=>$target_type,'target_public_id'=>$target_public_id,'reason'=>$reason],'success','','marketplace_safety');
        MKT_Events::enqueue('MarketplaceReportSubmitted.v1','report',$public_id,['safe_summary'=>__('A marketplace safety report was submitted.','marketplace'),'target_type'=>$target_type,'target_public_id'=>$target_public_id,'reason_taxonomy'=>$reason],'restricted');
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.MKT_DB::table('reports').' WHERE public_id=%s',$public_id),ARRAY_A);
        return $row?MKT_Moderation::report_dto($row):new WP_Error('mkt_report_failed',__('The report could not be read after creation.','marketplace'),['status'=>500]);
    }

    private static function report_target(string $type,string $public_id,int $user_id): array|WP_Error {
        global $wpdb;
        if ($type==='listing') { $row=MKT_Listings::get($public_id,false); return $row?:new WP_Error('mkt_report_target_not_found',__('The report target is not available.','marketplace'),['status'=>404]); }
        if ($type==='seller') { $row=$wpdb->get_row($wpdb->prepare('SELECT public_id,user_id,status FROM '.MKT_DB::table('sellers').' WHERE public_id=%s',$public_id),ARRAY_A); return $row && (string)$row['status']==='approved'?$row:new WP_Error('mkt_report_target_not_found',__('The report target is not available.','marketplace'),['status'=>404]); }
        $table=$type==='offer'?'offers':'deals'; $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.MKT_DB::table($table).' WHERE public_id=%s',$public_id),ARRAY_A);
        if (!$row || !in_array($user_id,[(int)$row['buyer_user_id'],(int)$row['seller_user_id']],true)) return new WP_Error('mkt_report_target_not_found',__('The report target is not available.','marketplace'),['status'=>404]);
        return $row;
    }

    private static function publication_gate(string $listing_public_id): bool|WP_Error {
        $listing=MKT_Listings::get($listing_public_id,true); if (!$listing) return new WP_Error('mkt_listing_not_found',__('Listing not found.','marketplace'),['status'=>404]);
        $language=self::language_for_listing((int)$listing['id']);
        if ($language==='' || $language==='und') return new WP_Error('mkt_listing_language_required',__('A specific listing language must be set before submission.','marketplace'),['status'=>422]);
        if ((string)$listing['category']==='homeopathic_medicines') { $evidence=MKT_Governance::public_evidence($listing_public_id); if (!$evidence) return new WP_Error('mkt_evidence_review_required',__('Approved structured product evidence is required before this regulated listing can be submitted or published.','marketplace'),['status'=>422]); }
        if (MKT_Governance::public_recall($listing_public_id)) return new WP_Error('mkt_active_recall',__('This listing cannot be submitted or activated while an active recall exists.','marketplace'),['status'=>409]);
        return true;
    }

    private static function sync_facets(string $listing_public_id,array $input,int $actor_id): bool|WP_Error {
        $listing=MKT_Listings::get($listing_public_id,true); if (!$listing || !MKT_Auth::own_listing($listing,$actor_id)) return new WP_Error('mkt_listing_not_found',__('Listing not found.','marketplace'),['status'=>404]);
        $present=array_key_exists('language',$input); $language=$present?self::normalize_language((string)$input['language']):self::language_for_listing((int)$listing['id']);
        if ($present && $language==='') return new WP_Error('mkt_invalid_listing_language',__('Enter a valid BCP-47 language code such as en, ur or ar.','marketplace'),['status'=>422]);
        if ($language==='') $language='und';
        global $wpdb; $table=self::table(); $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE listing_id=%d FOR UPDATE",(int)$listing['id']),ARRAY_A);
        if ($existing) {
            $updated=$wpdb->update($table,['language'=>$language,'version'=>(int)$existing['version']+1,'updated_at'=>MKT_DB::now()],['listing_id'=>(int)$listing['id'],'version'=>(int)$existing['version']]);
            if ($updated!==1) return new WP_Error('mkt_listing_facet_conflict',__('Listing facets changed. Reload and try again.','marketplace'),['status'=>409]);
        } else {
            $inserted=$wpdb->insert($table,['listing_id'=>(int)$listing['id'],'language'=>$language,'version'=>1,'created_at'=>MKT_DB::now(),'updated_at'=>MKT_DB::now()]);
            if (!$inserted) return new WP_Error('mkt_listing_facet_failed',__('Listing language could not be stored.','marketplace'),['status'=>500]);
        }
        return true;
    }

    private static function language_for_listing(int $listing_id): string {
        if ($listing_id<=0) return ''; global $wpdb; $value=$wpdb->get_var($wpdb->prepare('SELECT language FROM '.self::table().' WHERE listing_id=%d',$listing_id)); return is_string($value)?$value:'';
    }
    private static function normalize_language(string $language): string {
        $language=strtolower(trim(str_replace('_','-',$language))); if ($language==='') return ''; if ($language==='und') return 'und'; return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/',$language)?$language:'';
    }
    private static function decorate_listing(array $listing): array {
        $id=(int)($listing['id'] ?? 0); if ($id>0) $listing['language']=self::language_for_listing($id) ?: 'und';
        if (!empty($listing['public_id']) && class_exists('MKT_Governance')) { $listing['evidence']=MKT_Governance::public_evidence((string)$listing['public_id']); $listing['recall']=MKT_Governance::public_recall((string)$listing['public_id']); }
        return $listing;
    }

    private static function mutate_atomic(string $bucket,WP_REST_Request $request,array $data,callable $callback) {
        $authorization=(string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''); $app_auth=$authorization!=='' || !empty($_SERVER['PHP_AUTH_USER']);
        if (!$app_auth && !wp_verify_nonce((string)($_SERVER['HTTP_X_WP_NONCE'] ?? ''),'wp_rest')) return new WP_Error('mkt_invalid_nonce',__('Security token validation failed.','marketplace'),['status'=>403]);
        $rate=MKT_Rate_Limiter::check($bucket); if (is_wp_error($rate)) return $rate;
        $key=sanitize_text_field((string)($request->get_header('Idempotency-Key') ?: ($data['idempotency_key'] ?? ''))); $scope=sanitize_key($bucket.'_'.strtolower($request->get_method()).'_'.trim($request->get_route(),'/'));
        try { return MKT_Idempotency::run($scope,$key,$data,static function() use ($callback) { return MKT_DB::transaction(static fn()=>$callback()); }); }
        catch (Throwable $e) { return new WP_Error('mkt_atomic_mutation_failed',__('The Marketplace action could not be committed safely.','marketplace'),['status'=>500,'trace_id'=>MKT_Audit::trace_id()]); }
    }
    private static function respond($result): WP_REST_Response { return is_wp_error($result)?self::error_response($result):rest_ensure_response($result); }
    private static function error_response(WP_Error $error): WP_REST_Response {
        $data=$error->get_error_data(); $status=is_array($data)&&isset($data['status'])?(int)$data['status']:400; $response=new WP_REST_Response(['code'=>$error->get_error_code(),'message'=>$error->get_error_message(),'data'=>is_array($data)?array_diff_key($data,['status'=>true]):[],'trace_id'=>MKT_Audit::trace_id()],$status); if (is_array($data)&&isset($data['retry_after'])) $response->header('Retry-After',(string)(int)$data['retry_after']); return $response;
    }
    public static function assurance_manifest(array $manifest): array {
        $manifest['plan_completion_schema']=self::SCHEMA_VERSION;
        $manifest['controls']['atomic_owner_outbox_rest_mutations']=true;
        $manifest['controls']['language_service_availability_seller_facets']=true;
        $manifest['controls']['full_marketplace_report_taxonomy']=true;
        $manifest['controls']['fresh_high_trust_operational_authorization']=true;
        return $manifest;
    }
}
