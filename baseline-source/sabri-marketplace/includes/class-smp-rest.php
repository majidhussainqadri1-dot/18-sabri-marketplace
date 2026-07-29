<?php
defined('ABSPATH') || exit;
final class SMP_REST {
    public static function register_routes():void{
        register_rest_route('sabri-marketplace/v1','/health',['methods'=>'GET','callback'=>[self::class,'health'],'permission_callback'=>'__return_true']);
        register_rest_route('sabri-marketplace/v1','/products',['methods'=>'GET','callback'=>[self::class,'products'],'permission_callback'=>'__return_true']);
    }
    public static function health(WP_REST_Request$request):WP_REST_Response{
        global$wpdb;$tables=[];foreach(['sellers','products','conversations','messages','reports']as$t)$tables[$t]=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',SMP_DB::table($t)))===SMP_DB::table($t);
        return new WP_REST_Response(['ok'=>!in_array(false,$tables,true),'version'=>SMP_VERSION,'mode'=>'direct-deal','page'=>SMP_Activator::marketplace_url(),'chat'=>true,'phone'=>true,'whatsapp'=>true,'tables'=>$tables],200);
    }
    public static function products(WP_REST_Request$request):WP_REST_Response{
        global$wpdb;$limit=min(50,max(1,(int)$request->get_param('limit')));$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".SMP_DB::table('products')." WHERE status IN ('published','approved') ORDER BY id DESC LIMIT %d",$limit),ARRAY_A);return new WP_REST_Response(['products'=>array_map(['SMP_Utils','public_product'],$rows)],200);
    }
}
