<?php
defined('ABSPATH') || exit;

final class MKT_Policy {
    public static function seed_builtin_policies(): void {
        if (get_option('mkt_builtin_policy_version') === MKT_VERSION . '-categories-2' || !MKT_DB::table_exists('policies')) {
            return;
        }
        global $wpdb;
        $now = MKT_DB::now();
        $policies = [
            [
                'policy_type' => 'category',
                'policy_key' => 'homeopathy_books',
                'rules' => ['label' => 'Homeopathy books', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'educational_services',
                'rules' => ['label' => 'Educational services', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'clinic_equipment',
                'rules' => ['label' => 'Clinic equipment', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','condition_name','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'homeopathic_medicines',
                'rules' => ['label' => 'Homeopathic medicines', 'risk' => 'regulated', 'requires_human_review' => true, 'requires_verified_professional' => true, 'prohibits_prescription_claims' => true],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'homeopathy_journals',
                'rules' => ['label' => 'Homeopathy journals and periodicals', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'digital_learning_resources',
                'rules' => ['label' => 'Digital learning resources', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'professional_software',
                'rules' => ['label' => 'Professional software and tools', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'clinic_furniture',
                'rules' => ['label' => 'Clinic furniture', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','condition_name','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'dispensing_supplies',
                'rules' => ['label' => 'Dispensing and packaging supplies', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','condition_name','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'research_services',
                'rules' => ['label' => 'Research and editorial services', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'translation_publishing_services',
                'rules' => ['label' => 'Translation and publishing services', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'professional_events',
                'rules' => ['label' => 'Professional events and workshops', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'other_approved',
                'rules' => ['label' => 'Other approved homeopathy-related item', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency']],
            ],
            [
                'policy_type' => 'prohibited',
                'policy_key' => 'global_prohibited_goods_services',
                'rules' => [
                    'categories' => ['weapons','explosives','gambling','adult_services','illegal_drugs','stolen_goods','counterfeit_goods','patient_data','prescription_only_unlicensed'],
                    'phrases' => ['guaranteed cure','100% cure','no side effects guaranteed','patient database for sale'],
                    'reasons' => ['illegal','unsafe','fraud','privacy','medical_claim','shariah'],
                ],
            ],
            [
                'policy_type' => 'business',
                'policy_key' => 'zero_commission',
                'rules' => ['commission_percent' => 0, 'donation_advantage' => false, 'hidden_fees' => false, 'escrow_guarantee' => false],
            ],
        ];
        foreach ($policies as $policy) {
            $exists = $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . MKT_DB::table('policies') . ' WHERE policy_type=%s AND policy_key=%s AND jurisdiction=%s AND version=1',
                $policy['policy_type'], $policy['policy_key'], 'GLOBAL'
            ));
            if ($exists) {
                continue;
            }
            $wpdb->insert(MKT_DB::table('policies'), [
                'public_id' => MKT_DB::uuid(),
                'policy_type' => $policy['policy_type'],
                'policy_key' => $policy['policy_key'],
                'jurisdiction' => 'GLOBAL',
                'version' => 1,
                'status' => 'active',
                'rules_json' => wp_json_encode($policy['rules']),
                'created_by' => 0,
                'approved_by' => 0,
                'effective_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        update_option('mkt_builtin_policy_version', MKT_VERSION . '-categories-2', false);
    }

    public static function categories(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT policy_key,rules_json FROM " . MKT_DB::table('policies') . " WHERE policy_type='category' AND status='active' AND (effective_at IS NULL OR effective_at<=UTC_TIMESTAMP()) AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY policy_key ASC",
            ARRAY_A
        );
        $categories = [];
        foreach ($rows as $row) {
            $rules = json_decode((string) $row['rules_json'], true);
            if (is_array($rules)) {
                $categories[(string) $row['policy_key']] = $rules;
            }
        }
        return $categories;
    }

    public static function active(string $type, string $key, string $jurisdiction = 'GLOBAL'): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . MKT_DB::table('policies') . " WHERE policy_type=%s AND policy_key=%s AND jurisdiction IN (%s,'GLOBAL') AND status='active' AND (effective_at IS NULL OR effective_at<=UTC_TIMESTAMP()) AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY (jurisdiction=%s) DESC,version DESC LIMIT 1",
            sanitize_key($type), sanitize_key($key), strtoupper($jurisdiction), strtoupper($jurisdiction)
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $row['rules'] = json_decode((string) $row['rules_json'], true) ?: [];
        return $row;
    }

    public static function evaluate_listing(array $data, array $seller_assertions): array {
        $errors = [];
        $holds = [];
        $category = sanitize_key((string) ($data['category'] ?? ''));
        $category_policy = self::active('category', $category, (string) ($data['location_country'] ?? 'GLOBAL'));
        if (!$category_policy) {
            $errors[] = ['code' => 'unknown_category', 'message' => __('The selected category is not currently approved.', 'marketplace')];
        } else {
            $rules = $category_policy['rules'];
            foreach ((array) ($rules['required_fields'] ?? []) as $field) {
                if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                    $errors[] = ['code' => 'required_field', 'field' => $field, 'message' => sprintf(__('The field %s is required for this category.', 'marketplace'), $field)];
                }
            }
            if (!empty($rules['requires_verified_professional']) && empty($seller_assertions['verified'])) {
                $errors[] = ['code' => 'professional_verification_required', 'message' => __('This regulated category requires a verified professional account.', 'marketplace')];
            }
            if (!empty($rules['requires_human_review'])) {
                $holds[] = 'human_review';
            }
        }

        $prohibited = self::active('prohibited', 'global_prohibited_goods_services');
        $haystack = strtolower(wp_strip_all_tags(implode(' ', [
            (string) ($data['title'] ?? ''),
            (string) ($data['description'] ?? ''),
            $category,
        ])));
        foreach ((array) ($prohibited['rules']['categories'] ?? []) as $blocked_category) {
            if ($category === sanitize_key((string) $blocked_category)) {
                $errors[] = ['code' => 'prohibited_category', 'message' => __('This category is prohibited.', 'marketplace')];
            }
        }
        foreach ((array) ($prohibited['rules']['phrases'] ?? []) as $phrase) {
            if ($phrase !== '' && str_contains($haystack, strtolower((string) $phrase))) {
                $errors[] = ['code' => 'prohibited_claim', 'message' => __('The listing contains a prohibited or unsubstantiated claim.', 'marketplace')];
            }
        }

        if ((float) ($data['price'] ?? 0) < 0) {
            $errors[] = ['code' => 'invalid_price', 'message' => __('Price cannot be negative.', 'marketplace')];
        }
        if ((string) ($data['listing_type'] ?? 'product') !== 'service' && (float) ($data['quantity'] ?? 0) <= 0) {
            $errors[] = ['code' => 'invalid_quantity', 'message' => __('Product quantity must be greater than zero.', 'marketplace')];
        }
        if (!in_array(strtoupper((string) ($data['currency'] ?? '')), MKT_Contracts::allowed_currencies(), true)) {
            $errors[] = ['code' => 'invalid_currency', 'message' => __('Unsupported currency.', 'marketplace')];
        }
        if (!empty($seller_assertions['is_minor']) && empty($seller_assertions['guardian_verified'])) {
            $errors[] = ['code' => 'guardian_required', 'message' => __('Verified guardian consent is required.', 'marketplace')];
        }
        $declarations = is_array($data['declarations'] ?? null) ? $data['declarations'] : [];
        foreach (['truthful','rights_owned','no_patient_data','zero_commission_understood'] as $declaration) {
            if (empty($declarations[$declaration])) {
                $errors[] = ['code' => 'declaration_required', 'field' => 'declarations[' . $declaration . ']', 'message' => __('All marketplace declarations must be accepted.', 'marketplace')];
            }
        }

        return [
            'valid' => !$errors,
            'errors' => $errors,
            'holds' => array_values(array_unique($holds)),
            'policy_snapshot' => [
                'category_policy' => $category_policy ? ['public_id' => $category_policy['public_id'], 'version' => (int) $category_policy['version']] : null,
                'prohibited_policy' => $prohibited ? ['public_id' => $prohibited['public_id'], 'version' => (int) $prohibited['version']] : null,
                'evaluated_at' => MKT_DB::now(),
                'zero_commission' => true,
            ],
        ];
    }
}
