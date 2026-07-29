<?php
defined('ABSPATH') || exit;

final class SMP_Admin {
    public static function register_menu(): void {
        $cap='manage_sabri_marketplace';
        add_menu_page('Marketplace','Marketplace',$cap,'sabri-marketplace',[self::class,'render_overview'],'dashicons-store',28);
        add_submenu_page('sabri-marketplace','Marketplace Overview','Overview',$cap,'sabri-marketplace',[self::class,'render_overview']);
        add_submenu_page('sabri-marketplace','Sellers','Sellers',$cap,'sabri-marketplace-sellers',[self::class,'render_sellers']);
        add_submenu_page('sabri-marketplace','Listings','Listings',$cap,'sabri-marketplace-products',[self::class,'render_products']);
        add_submenu_page('sabri-marketplace','Chats & Reports','Chats & Reports','moderate_sabri_marketplace_chat','sabri-marketplace-reports',[self::class,'render_reports']);
        add_submenu_page('sabri-marketplace','Settings','Settings',$cap,'sabri-marketplace-settings',[self::class,'render_settings']);
        add_submenu_page('sabri-marketplace','System Check','System Check',$cap,'sabri-marketplace-system',[self::class,'render_system']);
    }

    public static function register_settings(): void {
        foreach(['smp_default_currency','smp_default_country','smp_support_email','smp_direct_deal_disclaimer','smp_prohibited_terms'] as $key) register_setting('smp_settings',$key,['sanitize_callback'=>$key==='smp_support_email'?'sanitize_email':'sanitize_textarea_field']);
        foreach(['smp_max_upload_mb','smp_max_product_images','smp_max_chat_upload_mb','smp_notification_retention_days','smp_deleted_chat_file_retention_days','smp_chat_poll_seconds','smp_message_edit_minutes'] as $key) register_setting('smp_settings',$key,['sanitize_callback'=>'absint']);
        foreach(['smp_require_seller_approval','smp_require_product_approval','smp_health_license_required','smp_allow_guest_browse','smp_reveal_contacts_to_logged_in','smp_require_buyer_contact_before_chat'] as $key) register_setting('smp_settings',$key,['sanitize_callback'=>static fn($v)=>$v?1:0]);
        add_action('admin_post_smp_repair',[self::class,'repair_marketplace']);
        add_action('admin_post_smp_seller_action',[self::class,'seller_action']);
        add_action('admin_post_smp_product_action',[self::class,'product_action']);
        add_action('admin_post_smp_report_action',[self::class,'report_action']);
        add_action('admin_post_smp_message_action',[self::class,'message_action']);
    }

    private static function require_admin(string$nonce):void{if(!current_user_can('manage_sabri_marketplace'))wp_die('Not allowed.');check_admin_referer($nonce);}
    private static function redirect(string$page,string$msg='Updated'):void{wp_safe_redirect(add_query_arg(['page'=>$page,'smp_notice'=>$msg],admin_url('admin.php')));exit;}

    public static function repair_marketplace():void{
        self::require_admin('smp_repair');SMP_DB::install();SMP_Activator::set_defaults();SMP_Activator::migrate_to_direct_deal();$id=SMP_Activator::ensure_marketplace_page(false);flush_rewrite_rules(false);if(function_exists('wp_cache_flush'))wp_cache_flush();do_action('litespeed_purge_all');SMP_Utils::audit('complete_repair','system',$id);self::redirect('sabri-marketplace-system','Repair completed');
    }

    public static function seller_action():void{
        self::require_admin('smp_seller_action');$id=absint($_POST['seller_id']??0);$status=sanitize_key($_POST['seller_status']??'');if(!in_array($status,['approved','pending','rejected','suspended'],true))wp_die('Invalid status.');global$wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SMP_DB::table('sellers').' WHERE id=%d',$id),ARRAY_A);if(!$row)wp_die('Seller not found.');
        $wpdb->update(SMP_DB::table('sellers'),['status'=>$status,'verification_level'=>$status==='approved'?'identity':$row['verification_level'],'contact_verified'=>$status==='approved'?1:(int)$row['contact_verified'],'rejection_reason'=>sanitize_textarea_field($_POST['note']??''),'updated_at'=>SMP_Utils::now()],['id'=>$id]);
        SMP_Utils::notify((int)$row['user_id'],'seller_status','Seller account updated','Status: '.$status,SMP_Activator::marketplace_url());SMP_Utils::audit('admin_seller_status','seller',$id,['status'=>$status]);self::redirect('sabri-marketplace-sellers','Seller updated');
    }

    public static function product_action():void{
        self::require_admin('smp_product_action');$id=absint($_POST['product_id']??0);$status=sanitize_key($_POST['product_status']??'');if(!in_array($status,['published','submitted','rejected','suspended','draft'],true))wp_die('Invalid status.');global$wpdb;
        $p=$wpdb->get_row($wpdb->prepare('SELECT p.*,s.user_id FROM '.SMP_DB::table('products').' p LEFT JOIN '.SMP_DB::table('sellers').' s ON s.id=p.seller_id WHERE p.id=%d',$id),ARRAY_A);if(!$p)wp_die('Listing not found.');
        $wpdb->update(SMP_DB::table('products'),['status'=>$status,'moderation_note'=>sanitize_textarea_field($_POST['note']??''),'published_at'=>$status==='published'?SMP_Utils::now():$p['published_at'],'updated_at'=>SMP_Utils::now()],['id'=>$id]);
        SMP_Utils::notify((int)$p['user_id'],'listing_status','Listing updated',$p['title'].' — '.$status,SMP_Activator::marketplace_url());SMP_Utils::audit('admin_listing_status','product',$id,['status'=>$status]);self::redirect('sabri-marketplace-products','Listing updated');
    }

    public static function report_action():void{
        self::require_admin('smp_report_action');$id=absint($_POST['report_id']??0);$status=sanitize_key($_POST['report_status']??'');if(!in_array($status,['open','under_review','resolved','dismissed'],true))wp_die('Invalid status.');global$wpdb;
        $wpdb->update(SMP_DB::table('reports'),['status'=>$status,'admin_note'=>sanitize_textarea_field($_POST['note']??''),'updated_at'=>SMP_Utils::now()],['id'=>$id]);SMP_Utils::audit('admin_report_status','report',$id,['status'=>$status]);self::redirect('sabri-marketplace-reports','Report updated');
    }

    public static function message_action():void{
        self::require_admin('smp_message_action');$id=absint($_POST['message_id']??0);global$wpdb;$m=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SMP_DB::table('messages').' WHERE id=%d',$id),ARRAY_A);if(!$m)wp_die('Message not found.');
        if($m['attachment_path'])SMP_Utils::delete_private_file($m['attachment_path']);$wpdb->update(SMP_DB::table('messages'),['message_text'=>'Removed by Marketplace moderation','attachment_path'=>'','attachment_name'=>'','attachment_mime'=>'','attachment_size'=>0,'deleted_for_all'=>1,'edited_at'=>SMP_Utils::now()],['id'=>$id]);SMP_Utils::audit('admin_message_remove','message',$id);self::redirect('sabri-marketplace-reports','Message removed');
    }

    private static function header(string$title,string$desc=''):void{
        echo '<div class="wrap"><h1>'.esc_html($title).'</h1>';if($desc)echo'<p>'.esc_html($desc).'</p>';if(isset($_GET['smp_notice']))echo'<div class="notice notice-success is-dismissible"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['smp_notice']))).'</p></div>';
        echo '<style>.smp-admin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:18px 0}.smp-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px}.smp-admin-card strong{display:block;font-size:28px;margin-top:7px}.smp-admin-table td{vertical-align:top}.smp-admin-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#f0f0f1}.smp-admin-actions{display:flex;gap:5px;flex-wrap:wrap}.smp-admin-actions input[type=text]{width:170px}</style>';
    }
    private static function footer():void{echo'</div>';}

    public static function render_overview():void{
        global$wpdb;self::header('Marketplace Direct-Deal Overview','Phone, WhatsApp and internal chat connect buyers and sellers. The platform does not process transaction funds.');
        $counts=['Approved sellers'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".SMP_DB::table('sellers')." WHERE status='approved'"),'Published listings'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".SMP_DB::table('products')." WHERE status IN ('published','approved')"),'Active chats'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".SMP_DB::table('conversations')." WHERE status='active'"),'Messages'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".SMP_DB::table('messages')),'Open reports'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".SMP_DB::table('reports')." WHERE status IN ('open','under_review')"),'Sold listings'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".SMP_DB::table('products')." WHERE deal_status='sold'")];
        echo'<div class="smp-admin-cards">';foreach($counts as$k=>$v)echo'<div class="smp-admin-card">'.esc_html($k).'<strong>'.number_format_i18n($v).'</strong></div>';echo'</div><div class="smp-admin-card"><h2>Direct-deal rule</h2><p>'.esc_html(SMP_Utils::disclaimer()).'</p><p><a class="button button-primary" href="'.esc_url(SMP_Activator::marketplace_url()).'" target="_blank">Open Marketplace</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=sabri-marketplace-system')).'">System Check</a></p></div>';self::footer();
    }

    public static function render_sellers():void{
        global$wpdb;self::header('Marketplace Sellers','Approve identity and contact details before public selling.');$rows=$wpdb->get_results('SELECT * FROM '.SMP_DB::table('sellers').' ORDER BY id DESC LIMIT 500',ARRAY_A);
        echo'<table class="widefat striped smp-admin-table"><thead><tr><th>Seller</th><th>Contact</th><th>Verification</th><th>Status / Action</th></tr></thead><tbody>';
        foreach($rows as$r){echo'<tr><td><strong>'.esc_html($r['store_name']).'</strong><br>'.esc_html($r['legal_name']).'<br>'.esc_html($r['city'].' · '.$r['country']).'</td><td>Phone: '.esc_html($r['phone']).'<br>WhatsApp: '.esc_html($r['whatsapp']).'<br>Email: '.esc_html($r['email']).'</td><td>'.esc_html($r['identity_type'].' · '.$r['identity_number']).'<br>Licence: '.esc_html($r['license_number']).'</td><td><span class="smp-admin-badge">'.esc_html($r['status']).'</span><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="smp-admin-actions">';wp_nonce_field('smp_seller_action');echo'<input type="hidden" name="action" value="smp_seller_action"><input type="hidden" name="seller_id" value="'.(int)$r['id'].'"><select name="seller_status"><option>approved</option><option>pending</option><option>rejected</option><option>suspended</option></select><input type="text" name="note" placeholder="Reason or note"><button class="button">Update</button></form></td></tr>';}
        if(!$rows)echo'<tr><td colspan="4">No sellers yet.</td></tr>';echo'</tbody></table>';self::footer();
    }

    public static function render_products():void{
        global$wpdb;self::header('Marketplace Listings','Moderate listings and restricted categories.');$rows=$wpdb->get_results('SELECT p.*,s.store_name FROM '.SMP_DB::table('products').' p LEFT JOIN '.SMP_DB::table('sellers').' s ON s.id=p.seller_id ORDER BY p.id DESC LIMIT 500',ARRAY_A);
        echo'<table class="widefat striped smp-admin-table"><thead><tr><th>Listing</th><th>Seller / Price</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach($rows as$r){echo'<tr><td><strong>'.esc_html($r['title']).'</strong><br>'.esc_html($r['category'].' · '.$r['product_type']).'<br>Deal: '.esc_html($r['deal_status']).'</td><td>'.esc_html($r['store_name']).'<br>'.esc_html(SMP_Utils::money($r['price'],$r['currency'])).'</td><td><span class="smp-admin-badge">'.esc_html($r['status']).'</span><br>'.esc_html($r['moderation_note']).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="smp-admin-actions">';wp_nonce_field('smp_product_action');echo'<input type="hidden" name="action" value="smp_product_action"><input type="hidden" name="product_id" value="'.(int)$r['id'].'"><select name="product_status"><option>published</option><option>submitted</option><option>rejected</option><option>suspended</option><option>draft</option></select><input type="text" name="note" placeholder="Moderation note"><button class="button">Update</button></form></td></tr>';}
        if(!$rows)echo'<tr><td colspan="4">No listings yet.</td></tr>';echo'</tbody></table>';self::footer();
    }

    public static function render_reports():void{
        global$wpdb;self::header('Chat Reports and Safety Moderation','Only reported conversation references are shown for moderation.');$rows=$wpdb->get_results('SELECT r.*,ru.display_name reporter_name,tu.display_name reported_name,m.message_text,m.message_type,p.title product_title FROM '.SMP_DB::table('reports').' r LEFT JOIN '.$wpdb->users.' ru ON ru.ID=r.reporter_id LEFT JOIN '.$wpdb->users.' tu ON tu.ID=r.reported_user_id LEFT JOIN '.SMP_DB::table('messages').' m ON m.id=r.message_id LEFT JOIN '.SMP_DB::table('products').' p ON p.id=r.product_id ORDER BY r.id DESC LIMIT 500',ARRAY_A);
        echo'<table class="widefat striped smp-admin-table"><thead><tr><th>Report</th><th>Context</th><th>Status</th><th>Moderation</th></tr></thead><tbody>';
        foreach($rows as$r){echo'<tr><td><strong>#'.(int)$r['id'].' · '.esc_html($r['reason']).'</strong><br>Reporter: '.esc_html($r['reporter_name']).'<br>Reported: '.esc_html($r['reported_name']).'<p>'.nl2br(esc_html($r['details'])).'</p></td><td>Listing: '.esc_html($r['product_title']).'<br>Conversation #'.(int)$r['conversation_id'].'<br>Message #'.(int)$r['message_id'].' '.esc_html($r['message_type']).'<p>'.esc_html($r['message_text']).'</p>';
            if((int)$r['message_id']){echo'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('smp_message_action');echo'<input type="hidden" name="action" value="smp_message_action"><input type="hidden" name="message_id" value="'.(int)$r['message_id'].'"><button class="button button-link-delete">Remove reported message</button></form>';}
            echo'</td><td><span class="smp-admin-badge">'.esc_html($r['status']).'</span><br>'.esc_html($r['admin_note']).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="smp-admin-actions">';wp_nonce_field('smp_report_action');echo'<input type="hidden" name="action" value="smp_report_action"><input type="hidden" name="report_id" value="'.(int)$r['id'].'"><select name="report_status"><option>under_review</option><option>resolved</option><option>dismissed</option><option>open</option></select><input type="text" name="note" placeholder="Decision note"><button class="button">Update</button></form></td></tr>';}
        if(!$rows)echo'<tr><td colspan="4">No reports.</td></tr>';echo'</tbody></table>';self::footer();
    }

    public static function render_settings():void{
        self::header('Marketplace Direct-Deal Settings');echo'<form method="post" action="options.php">';settings_fields('smp_settings');echo'<table class="form-table">';
        self::text('Default currency','smp_default_currency');self::text('Default country','smp_default_country');self::text('Support email','smp_support_email');self::textarea('Direct-deal disclaimer','smp_direct_deal_disclaimer');
        self::number('Product upload limit (MB)','smp_max_upload_mb',1,100);self::number('Maximum product images','smp_max_product_images',1,30);self::number('Chat attachment limit (MB)','smp_max_chat_upload_mb',1,100);self::number('Chat polling seconds','smp_chat_poll_seconds',3,60);self::number('Message edit window (minutes)','smp_message_edit_minutes',1,10080);self::number('Notification retention days','smp_notification_retention_days',30,3650);self::number('Deleted chat file retention days','smp_deleted_chat_file_retention_days',1,365);
        self::check('Require seller approval','smp_require_seller_approval');self::check('Require listing approval','smp_require_product_approval');self::check('Require health licence data','smp_health_license_required');self::check('Allow visitors to browse','smp_allow_guest_browse');self::check('Reveal seller contact only to logged-in users','smp_reveal_contacts_to_logged_in');self::check('Require buyer phone and WhatsApp before chat','smp_require_buyer_contact_before_chat');self::textarea('Prohibited terms','smp_prohibited_terms');
        echo'</table>';submit_button();echo'</form>';self::footer();
    }

    private static function text(string$l,string$n):void{echo'<tr><th>'.esc_html($l).'</th><td><input class="regular-text" name="'.esc_attr($n).'" value="'.esc_attr((string)get_option($n,'')).'"></td></tr>';}
    private static function textarea(string$l,string$n):void{echo'<tr><th>'.esc_html($l).'</th><td><textarea class="large-text" rows="5" name="'.esc_attr($n).'">'.esc_textarea((string)get_option($n,'')).'</textarea></td></tr>';}
    private static function number(string$l,string$n,int$min,int$max):void{echo'<tr><th>'.esc_html($l).'</th><td><input type="number" name="'.esc_attr($n).'" min="'.$min.'" max="'.$max.'" value="'.(int)get_option($n,0).'"></td></tr>';}
    private static function check(string$l,string$n):void{echo'<tr><th>'.esc_html($l).'</th><td><input type="hidden" name="'.esc_attr($n).'" value="0"><label><input type="checkbox" name="'.esc_attr($n).'" value="1" '.checked(1,(int)get_option($n,0),false).'> Enabled</label></td></tr>';}

    public static function render_system():void{
        global$wpdb;self::header('Marketplace System Check','Version '.SMP_VERSION.' — Direct-deal communication update');$page=(int)get_option('smp_marketplace_page_id');$tables=['sellers','products','conversations','messages','message_reactions','blocks','reports','notifications','audit_log'];$checks=['Marketplace page'=>$page&&get_post_status($page)==='publish','Shortcode registered'=>shortcode_exists('sabri_marketplace'),'AJAX endpoint'=>has_action('wp_ajax_smp_api')&&has_action('wp_ajax_nopriv_smp_api'),'Private file endpoint'=>has_action('wp_ajax_smp_chat_file'),'HTTPS'=>is_ssl(),'Direct-deal mode'=>(bool)get_option('smp_direct_deal_mode',0)];foreach($tables as$t)$checks['Table: '.$t]=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',SMP_DB::table($t)))===SMP_DB::table($t);
        echo'<table class="widefat striped"><tbody>';foreach($checks as$k=>$ok)echo'<tr><th>'.esc_html($k).'</th><td>'.($ok?'<span style="color:#138a42;font-weight:700">Ready</span>':'<span style="color:#b32d2e;font-weight:700">Needs repair</span>').'</td></tr>';echo'</tbody></table><p><strong>Marketplace URL:</strong> <a href="'.esc_url(SMP_Activator::marketplace_url()).'" target="_blank">'.esc_html(SMP_Activator::marketplace_url()).'</a></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('smp_repair');echo'<input type="hidden" name="action" value="smp_repair"><button class="button button-primary button-hero">Complete Repair</button></form>';self::footer();
    }
}
