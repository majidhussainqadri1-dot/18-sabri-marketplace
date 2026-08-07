<?php
defined('ABSPATH') || exit;

final class MKT_Release_Gates {
    public static function boot(): void {
        add_action('rest_api_init', [self::class, 'override_routes'], 250);
        add_action('template_redirect', [self::class, 'recall_privacy_headers'], -1);
        add_filter('mkt_assurance_manifest', [self::class, 'assurance_manifest']);
    }

    public static function override_routes(): void {
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/listings/(?P<id>[a-f0-9-]{36})/submit', [
            'methods'=>WP_REST_Server::CREATABLE,
            'callback'=>[self::class,'submit'],
            'permission_callback'=>[MKT_REST::class,'logged_in'],
        ], true);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/listings/(?P<id>[a-f0-9-]{36})/transition', [
            'methods'=>WP_REST_Server::CREATABLE,
            'callback'=>[self::class,'transition'],
            'permission_callback'=>[MKT_REST::class,'logged_in'],
        ], true);
        register_rest_route(MKT_Contracts::REST_NAMESPACE, '/listings/(?P<id>[a-f0-9-]{36})/evidence/review', [
            'methods'=>WP_REST_Server::CREATABLE,
            'callback'=>[self::class,'review_evidence'],
            'permission_callback'=>[MKT_REST::class,'logged_in'],
        ], true);
    }

    public static function submit(WP_REST_Request $request): WP_REST_Response {
        $gate=self::publication_gate((string)$request['id']);
        if (is_wp_error($gate)) return self::error_response($gate);
        return MKT_Plan_Completion::submit_listing($request);
    }

    public static function transition(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        if (sanitize_key((string)($data['to'] ?? ''))==='active') {
            $gate=self::publication_gate((string)$request['id']);
            if (is_wp_error($gate)) return self::error_response($gate);
        }
        return MKT_Plan_Completion::transition_listing($request);
    }

    public static function review_evidence(WP_REST_Request $request): WP_REST_Response {
        $data=(array)($request->get_json_params() ?: []);
        $nonce_error=self::validate_mutation_request($request);
        if (is_wp_error($nonce_error)) return self::error_response($nonce_error);
        $rate=MKT_Rate_Limiter::check('evidence_review');
        if (is_wp_error($rate)) return self::error_response($rate);
        $key=sanitize_text_field((string)($request->get_header('Idempotency-Key') ?: ($data['idempotency_key'] ?? '')));
        try {
            $result=MKT_Idempotency::run('evidence_review_strict_'.$request['id'],$key,$data,function() use ($request,$data) {
                return MKT_DB::transaction(function() use ($request,$data) {
                    return self::review_evidence_atomic((string)$request['id'],$data,get_current_user_id());
                });
            });
        } catch (Throwable $e) {
            $result=new WP_Error('mkt_evidence_review_failed',__('The evidence review could not be committed safely.','marketplace'),['status'=>500,'trace_id'=>MKT_Audit::trace_id()]);
        }
        return is_wp_error($result) ? self::error_response($result) : rest_ensure_response($result);
    }

    private static function review_evidence_atomic(string $listing_public_id,array $input,int $actor_id): array|WP_Error {
        $auth=MKT_Auth::can('mkt_moderate',['action'=>'review_listing_evidence','listing_public_id'=>$listing_public_id]);
        if (is_wp_error($auth)) return $auth;
        $decision=sanitize_key((string)($input['decision'] ?? ''));
        if (!in_array($decision,['approved','rejected'],true)) return new WP_Error('mkt_invalid_evidence_decision',__('Choose approved or rejected.','marketplace'),['status'=>422]);
        global $wpdb;
        $listing=$wpdb->get_row($wpdb->prepare(
            'SELECT l.*,s.user_id AS seller_user_id FROM '.MKT_DB::table('listings').' l INNER JOIN '.MKT_DB::table('sellers').' s ON s.id=l.seller_id WHERE l.public_id=%s FOR UPDATE',
            $listing_public_id
        ),ARRAY_A);
        if (!$listing) return new WP_Error('mkt_listing_not_found',__('Listing not found.','marketplace'),['status'=>404]);
        if ($actor_id===(int)$listing['created_by'] || $actor_id===(int)$listing['seller_user_id']) {
            return new WP_Error('mkt_self_review_forbidden',__('A seller cannot approve or reject evidence for their own listing. A separate authorized reviewer is required.','marketplace'),['status'=>403]);
        }
        $table=MKT_Governance::table('listing_evidence');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE listing_public_id=%s FOR UPDATE",$listing_public_id),ARRAY_A);
        if (!$row) return new WP_Error('mkt_evidence_not_found',__('Evidence record not found.','marketplace'),['status'=>404]);
        $expected=(int)($input['expected_version'] ?? 0);
        if ($expected!==(int)$row['version']) return new WP_Error('mkt_stale_evidence_version',__('The evidence record changed. Reload and try again.','marketplace'),['status'=>409,'current_version'=>(int)$row['version']]);
        $evidence=json_decode((string)$row['evidence_json'],true) ?: [];
        if ($decision==='approved') {
            $errors=self::validate_regulated_evidence($evidence,(string)$listing['category']);
            if ($errors) return new WP_Error('mkt_evidence_invalid',__('Evidence cannot be approved until all required facts are valid.','marketplace'),['status'=>422,'errors'=>$errors]);
        }
        $updated=$wpdb->update($table,[
            'status'=>$decision,
            'version'=>(int)$row['version']+1,
            'reviewed_by'=>$actor_id,
            'review_note'=>wp_kses_post((string)($input['note'] ?? '')),
            'updated_at'=>MKT_DB::now(),
            'reviewed_at'=>MKT_DB::now(),
        ],['id'=>(int)$row['id'],'version'=>(int)$row['version']]);
        if ($updated!==1) return new WP_Error('mkt_stale_evidence_version',__('The evidence record changed. Reload and try again.','marketplace'),['status'=>409]);

        $paused=false;
        if ($decision==='rejected' && (string)$listing['status']==='active') {
            $paused_update=$wpdb->update(MKT_DB::table('listings'),[
                'status'=>'paused','updated_at'=>MKT_DB::now(),'updated_by'=>$actor_id,'version'=>(int)$listing['version']+1,
            ],['id'=>(int)$listing['id'],'version'=>(int)$listing['version'],'status'=>'active']);
            if ($paused_update!==1) return new WP_Error('mkt_listing_evidence_pause_conflict',__('The listing changed during evidence rejection. Reload and try again.','marketplace'),['status'=>409]);
            $paused=true;
        }

        MKT_Events::enqueue('MarketplaceListingEvidenceReviewed.v1','listing',$listing_public_id,[
            'status'=>$decision,
            'seller_user_id'=>(int)$listing['seller_user_id'],
            'notify_user_ids'=>[(int)$listing['seller_user_id']],
            'safe_summary'=>sprintf(__('Listing evidence review status changed to %s.','marketplace'),$decision),
            'url'=>MKT_Listings::url($listing),
        ],'restricted');
        if ($paused) {
            MKT_Events::enqueue('MarketplaceListingStatusChanged.v1','listing',$listing_public_id,[
                'from'=>'active','to'=>'paused','reason'=>'evidence_rejected','notify_user_ids'=>[(int)$listing['seller_user_id']],
                'safe_summary'=>__('The listing was paused because its regulated product evidence was rejected.','marketplace'),
                'url'=>MKT_Listings::url($listing),
            ],'restricted');
        }
        MKT_Audit::record('listing_evidence_reviewed','listing',$listing_public_id,['decision'=>$decision,'self_review_prevented'=>true,'listing_paused'=>$paused],'success','','marketplace_evidence');
        return ['listing_public_id'=>$listing_public_id,'status'=>$decision,'version'=>(int)$row['version']+1,'listing_paused'=>$paused];
    }

    private static function validate_regulated_evidence(array $evidence,string $category): array {
        $errors=[];
        if ($category==='homeopathic_medicines') {
            if (empty($evidence['ingredients']) || !is_array($evidence['ingredients'])) $errors[]=['code'=>'ingredients_required','field'=>'ingredients'];
            if (trim((string)($evidence['manufacturer'] ?? ''))==='') $errors[]=['code'=>'manufacturer_required','field'=>'manufacturer'];
            foreach (['license_or_registration','batch_number','expiry_date'] as $field) {
                if (trim((string)($evidence[$field] ?? ''))==='' && trim((string)($evidence['not_applicable'][$field] ?? ''))==='') $errors[]=['code'=>'evidence_value_or_na_required','field'=>$field];
            }
        }
        $expiry=trim((string)($evidence['expiry_date'] ?? ''));
        if ($expiry!=='') {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$expiry,new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d')!==$expiry) $errors[]=['code'=>'invalid_expiry_date','field'=>'expiry_date'];
            elseif ($date < new DateTimeImmutable('today',new DateTimeZone('UTC'))) $errors[]=['code'=>'expired_batch','field'=>'expiry_date'];
        }
        foreach ((array)($evidence['claims'] ?? []) as $claim) {
            if (MKT_Governance::prohibited_claim((string)$claim)) { $errors[]=['code'=>'prohibited_claim','field'=>'claims']; break; }
        }
        return $errors;
    }

    private static function publication_gate(string $public_id): bool|WP_Error {
        $listing=MKT_Listings::get($public_id,true);
        if (!$listing) return new WP_Error('mkt_listing_not_found',__('Listing not found.','marketplace'),['status'=>404]);
        $language=MKT_Finalization::listing_language((int)$listing['id']);
        if ($language==='' || $language==='und') return new WP_Error('mkt_listing_language_required',__('A specific listing language such as en, ur or ar is required before submission or publication.','marketplace'),['status'=>422]);
        if ((string)$listing['category']==='homeopathic_medicines') {
            $evidence=MKT_Governance::public_evidence($public_id);
            if (!$evidence) return new WP_Error('mkt_evidence_review_required',__('Approved structured ingredients, manufacturer, licensing/batch/expiry evidence is required before this regulated listing can be submitted or published.','marketplace'),['status'=>422]);
        }
        if (MKT_Governance::public_recall($public_id)) return new WP_Error('mkt_active_recall',__('This listing cannot be submitted or activated while an active recall or takedown exists.','marketplace'),['status'=>409]);
        return true;
    }

    public static function recall_privacy_headers(): void {
        $public_id=(string)get_query_var('mkt_object_id');
        if (!wp_is_uuid($public_id) || !class_exists('MKT_Governance')) return;
        if (!MKT_Governance::public_recall($public_id)) return;
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive',true);
            header('Cache-Control: private, no-store, max-age=0, must-revalidate',true);
            header('Pragma: no-cache',true);
        }
    }

    private static function validate_mutation_request(WP_REST_Request $request): bool|WP_Error {
        $authorization=(string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($authorization!=='' || !empty($_SERVER['PHP_AUTH_USER'])) return true;
        $nonce=(string)$request->get_header('X-WP-Nonce');
        if ($nonce==='' || !wp_verify_nonce($nonce,'wp_rest')) return new WP_Error('mkt_invalid_nonce',__('The security token is missing or expired.','marketplace'),['status'=>403]);
        return true;
    }

    private static function error_response(WP_Error $error): WP_REST_Response {
        $data=$error->get_error_data();
        $status=is_array($data)&&isset($data['status'])?(int)$data['status']:400;
        return new WP_REST_Response(['code'=>$error->get_error_code(),'message'=>$error->get_error_message(),'data'=>is_array($data)?array_diff_key($data,['status'=>true]):[],'trace_id'=>MKT_Audit::trace_id()],$status);
    }

    public static function assurance_manifest(array $manifest): array {
        $manifest['controls']['strict_publication_language_gate']=true;
        $manifest['controls']['regulated_evidence_publication_gate']=true;
        $manifest['controls']['active_recall_publication_gate']=true;
        $manifest['controls']['evidence_self_review_prevention']=true;
        $manifest['controls']['evidence_rejection_atomic_listing_pause']=true;
        $manifest['controls']['recall_public_noindex_nostore']=true;
        return $manifest;
    }
}
