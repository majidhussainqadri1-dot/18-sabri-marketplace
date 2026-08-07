<?php
defined('ABSPATH') || exit;

final class MKT_Contracts {
    public const REST_NAMESPACE = 'marketplace/v1';
    public const EVENT_VERSION = 1;

    public static function capabilities(): array {
        return [
            'mkt_sell' => ['administrator', 'sabri_founder', 'sabri_marketplace_seller'],
            'mkt_buy' => ['administrator', 'subscriber', 'sabri_member', 'sabri_doctor', 'sabri_founder'],
            'mkt_moderate' => ['administrator', 'sabri_founder', 'sabri_marketplace_moderator'],
            'mkt_review_disputes' => ['administrator', 'sabri_founder', 'sabri_dispute_reviewer'],
            'mkt_review_marketplace' => ['administrator', 'sabri_founder', 'sabri_marketplace_moderator', 'sabri_dispute_reviewer'],
            'mkt_manage_policies' => ['administrator', 'sabri_founder'],
            'mkt_view_system' => ['administrator', 'sabri_founder', 'sabri_security_operator'],
        ];
    }

    public static function register_capabilities(): void {
        if ((string) get_option('mkt_caps_version', '') === MKT_VERSION . '-caps-2') {
            return;
        }
        foreach (self::capabilities() as $capability => $roles) {
            foreach ($roles as $role_name) {
                $role = get_role($role_name);
                if ($role) {
                    $role->add_cap($capability);
                }
            }
        }
        update_option('mkt_caps_version', MKT_VERSION . '-caps-2', false);
    }

    public static function listing_statuses(): array {
        return ['draft','review','active','paused','sold_unavailable','expired','rejected','removed','appealed'];
    }

    public static function offer_statuses(): array {
        return ['open','countered','accepted','declined','withdrawn','expired'];
    }

    public static function deal_statuses(): array {
        return ['accepted','arranging','completed','cancelled','disputed','resolved','closed'];
    }

    public static function report_statuses(): array {
        return ['submitted','triaged','restricted','no_action','decided','appealed','closed'];
    }

    public static function public_listing_fields(): array {
        return [
            'public_id','seller_public_id','title','slug','category','subcategory','listing_type',
            'condition_name','description','price','currency','quantity','availability','location_country',
            'location_region','location_city','delivery_modes','contact_modes','status','published_at',
            'updated_at','featured_label','last_reviewed_at','version',
        ];
    }

    public static function allowed_currencies(): array {
        return apply_filters('mkt_allowed_currencies', [
            'PKR','USD','EUR','GBP','AED','SAR','QAR','CAD','AUD','INR','BDT','TRY','MYR','IDR','NGN','ZAR',
        ]);
    }

    public static function zero_commission(): int {
        return 0;
    }

    public static function contract_manifest(): array {
        return [
            'module' => 'file-18-marketplace',
            'plugin_version' => MKT_VERSION,
            'schema_version' => MKT_SCHEMA_VERSION,
            'contract_version' => MKT_CONTRACT_VERSION,
            'commission_percent' => self::zero_commission(),
            'access_tier' => 'single_free',
            'donation_advantage' => false,
            'paid_ranking' => false,
            'canonical_owners' => [
                'identity' => 'File 00',
                'communication' => 'File 17',
                'notifications' => 'File 19',
                'shell' => 'File 20',
                'assurance' => 'File 24',
                'visual' => 'File 25',
                'search' => 'File 26',
            ],
        ];
    }
}
