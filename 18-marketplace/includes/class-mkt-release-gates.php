<?php
defined('ABSPATH') || exit;

final class MKT_Release_Gates {
    public static function boot(): void {
        add_action('rest_api_init', [self::class, 'override_routes'], 250);
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

    private static function publication_gate(string $public_id): true|WP_Error {
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

    private static function error_response(WP_Error $error): WP_REST_Response {
        $data=$error->get_error_data();
        $status=is_array($data)&&isset($data['status'])?(int)$data['status']:400;
        return new WP_REST_Response(['code'=>$error->get_error_code(),'message'=>$error->get_error_message(),'data'=>is_array($data)?array_diff_key($data,['status'=>true]):[],'trace_id'=>MKT_Audit::trace_id()],$status);
    }

    public static function assurance_manifest(array $manifest): array {
        $manifest['controls']['strict_publication_language_gate']=true;
        $manifest['controls']['regulated_evidence_publication_gate']=true;
        $manifest['controls']['active_recall_publication_gate']=true;
        return $manifest;
    }
}
