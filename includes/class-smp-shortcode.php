<?php
defined('ABSPATH') || exit;
final class SMP_Shortcode {
    private static bool$registered=false;
    public static function register_assets():void{if(self::$registered)return;self::$registered=true;wp_register_style('sabri-marketplace',SMP_URL.'assets/css/marketplace.css',[],SMP_VERSION);wp_register_script('sabri-marketplace',SMP_URL.'assets/js/marketplace.js',[],SMP_VERSION,true);}
    public static function enqueue_if_marketplace():void{$id=(int)get_option('smp_marketplace_page_id');$safe=(int)get_query_var('smp_marketplace_app')===1||isset($_GET['smp-marketplace-safe']);if(($id&&is_page($id))||$safe)self::enqueue_assets();}
    private static function enqueue_assets():void{
        self::register_assets();wp_enqueue_style('sabri-marketplace');wp_enqueue_script('sabri-marketplace');$uid=get_current_user_id();$u=$uid?wp_get_current_user():null;
        wp_localize_script('sabri-marketplace','SMP_CONFIG',['version'=>SMP_VERSION,'ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('smp_ajax'),'isLoggedIn'=>(bool)$uid,'currentUser'=>$uid?['id'=>$uid,'name'=>$u instanceof WP_User?$u->display_name:'','email'=>$u instanceof WP_User?$u->user_email:'','avatar'=>get_avatar_url($uid,['size'=>96])]:null,'marketplacePage'=>SMP_Activator::marketplace_url(),'loginUrl'=>wp_login_url(SMP_Activator::marketplace_url()),'registerUrl'=>wp_registration_url(),'currency'=>(string)get_option('smp_default_currency','PKR'),'strings'=>['genericError'=>'Marketplace could not complete the request.','loginRequired'=>'Please log in to continue.']]);
    }
    public static function render($atts=[]):string{self::enqueue_assets();ob_start();include SMP_DIR.'templates/marketplace-app.php';return(string)ob_get_clean();}
}
