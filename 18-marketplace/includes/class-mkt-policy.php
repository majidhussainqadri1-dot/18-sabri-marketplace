<?php
defined('ABSPATH') || exit;

final class MKT_Policy {
    public static function seed_builtin_policies(): void {
        if (get_option('mkt_builtin_policy_version') === MKT_VERSION . '-categories-3' || !MKT_DB::table_exists('policies')) {
            return;
        }
        global $wpdb;
        $now = MKT_DB::now();
        $policies = [
            [
                'policy_type' => 'category',
                'policy_key' => 'homeopathy_books',
                'version' => 1,
                'rules' => ['label' => 'Homeopathy books', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'educational_services',
                'version' => 1,
                'rules' => ['label' => 'Educational services', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'clinic_equipment',
                'version' => 1,
                'rules' => ['label' => 'Clinic equipment', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','condition_name','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'homeopathic_medicines',
                'version' => 2,
                'rules' => [
                    'label' => 'Homeopathic medicines',
                    'risk' => 'regulated',
                    'requires_human_review' => true,
                    'requires_verified_professional' => true,
                    'requires_structured_evidence' => true,
                    'prohibits_prescription_claims' => true,
                    'required_fields' => ['title','description','price','currency'],
                ],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'homeopathy_journals',
                'version' => 1,
                'rules' => ['label' => 'Homeopathy journals and periodicals', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'digital_learning_resources',
                'version' => 1,
                'rules' => ['label' => 'Digital learning resources', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'professional_software',
                'version' => 1,
                'rules' => ['label' => 'Professional software and tools', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'clinic_furniture',
                'version' => 1,
                'rules' => ['label' => 'Clinic furniture', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','condition_name','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'dispensing_supplies',
                'version' => 1,
                'rules' => ['label' => 'Dispensing and packaging supplies', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','condition_name','price','currency']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'research_services',
                'version' => 1,
                'rules' => ['label' => 'Research and editorial services', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'translation_publishing_services',
                'version' => 1,
                'rules' => ['label' => 'Translation and publishing services', 'risk' => 'standard', 'requires_human_review' => false, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'professional_events',
                'version' => 1,
                'rules' => ['label' => 'Professional events and workshops', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency','delivery_modes']],
            ],
            [
                'policy_type' => 'category',
                'policy_key' => 'other_approved',
                'version' => 1,
                'rules' => ['label' => 'Other approved homeopathy-related item', 'risk' => 'conditional', 'requires_human_review' => true, 'required_fields' => ['title','description','price','currency']],
            ],
            [
                'policy_type' => 'prohibited',
                'policy_key' => 'global_prohibited_goods_services',
                'version' => 2,
                'rules' => [
                    'categories' => ['weapons','explosives','gambling','adult_services','illegal_drugs','stolen_goods','counterfeit_goods','patient_data','prescription_only_unlicensed'],
                    'phrases' => ['guaranteed cure','100% cure','no side effects guaranteed','patient database for sale','guaranteed treatment result','replaces hospital care'],
                    'reasons' => ['illegal','unsafe','fraud','privacy','medical_claim','shariah','copyright','impersonation','child_safety'],
                ],
            ],
            [
                'policy_type' => 'business',
                'policy_key' => 'zero_commission',
                'version' => 1,
                'rules' => ['commission_percent' => 0, 'donation_advantage' => false, 'hidden_fees' => false, 'escrow_guarantee' => false],
            ],
        ];
        foreach ($policies as $policy) {
            $version = (int) ($policy['version'] ?? 1);
            $exists = $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . MKT_DB::table('policies') . ' WHERE policy_type=%s AND policy_key=%s AND jurisdiction=%s AND version=%d',
                $policy['policy_type'], $policy['policy_key'], 'GLOBAL', $version
            ));
            if ($exists) continue;
            if ($version > 1) {
                $wpdb->update(MKT_DB::table('policies'), ['status' => 'retired', 'updated_at' => $now], [
                    'policy_type' => $policy['policy_type'],
                    'policy_key' => $policy['policy_key'],
                    'jurisdiction' => 'GLOBAL',
                    'status' => 'active',
                ]);
            }
            $wpdb->insert(MKT_DB::table('policies'), [
                'public_id' => MKT_DB::uuid(),
                'policy_type' => $policy['policy_type'],
                'policy_key' => $policy['policy_key'],
                'jurisdiction' => 'GLOBAL',
                'version' => $version,
                'status' => 'active',
                'rules_json' => wp_json_encode($policy['rules']),
                'created_by' => 0,
                'approved_by' => 0,
                'effective_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        update_option('mkt_builtin_policy_version', MKT_VERSION . '-categories-3', false);
    }

    public static function categories(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT policy_key,rules_json,version FROM " . MKT_DB::table('policies') . " WHERE policy_type='category' AND status='active' AND (effective_at IS NULL OR effective_at<=UTC_TIMESTAMP()) AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY policy_key ASC,version ASC",
            ARRAY_A
        );
        $categories = [];
        foreach ($rows as $row) {
            $rules = json_decode((string) $row['rules_json'], true);
            if (is_array($rules)) $categories[(string) $row['policy_key']] = $rules;
        }
        return $categories;
    }

    public static function active(string $type, string $key, string $jurisdiction = 'GLOBAL'): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . MKT_DB::table('policies') . " WHERE policy_type=%s AND policy_key=%s AND jurisdiction IN (%s,'GLOBAL') AND status='active' AND (effective_at IS NULL OR effective_at<=UTC_TIMESTAMP()) AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY (jurisdiction=%s) DESC,version DESC LIMIT 1",
            sanitize_key($type), sanitize_key($key), strtoupper($jurisdiction), strtoupper($jurisdiction)
        ), ARRAY_A);
        if (!$row) return null;
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
                $value = $data[$field] ?? null;
                $empty = is_array($value) ? !$value : trim((string) $value) === '';
                if ($empty) {
                    $errors[] = ['code' => 'required_field', 'field' => $field, 'message' => sprintf(__('The field %s is required for this category.', 'marketplace'), $field)];
                }
            }
            if (!empty($rules['requires_verified_professional']) && !self::verified_professional($seller_assertions)) {
                $errors[] = ['code' => 'professional_verification_required', 'message' => __('This regulated category requires a currently verified professional account.', 'marketplace')];
            }
            if (!empty($rules['requires_human_review'])) $holds[] = 'human_review';
            if (class_exists('MKT_Governance')) {
                $gate = MKT_Governance::evidence_gate($data, $rules);
                $errors = array_merge($errors, (array) ($gate['errors'] ?? []));
                $holds = array_merge($holds, (array) ($gate['holds'] ?? []));
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
        if (class_exists('MKT_Governance') && MKT_Governance::prohibited_claim($haystack)) {
            $errors[] = ['code' => 'prohibited_claim', 'message' => __('The listing contains a prohibited or unsubstantiated medical claim.', 'marketplace')];
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

        $errors = self::dedupe_errors($errors);
        return [
            'valid' => !$errors,
            'errors' => $errors,
            'holds' => array_values(array_unique($holds)),
            'policy_snapshot' => [
                'category_policy' => $category_policy ? ['public_id' => $category_policy['public_id'], 'version' => (int) $category_policy['version']] : null,
                'prohibited_policy' => $prohibited ? ['public_id' => $prohibited['public_id'], 'version' => (int) $prohibited['version']] : null,
                'evaluated_at' => MKT_DB::now(),
                'zero_commission' => true,
                'structured_evidence_gate' => class_exists('MKT_Governance'),
            ],
        ];
    }

    private static function verified_professional(array $assertions): bool {
        if (empty($assertions['verified'])) return false;
        $roles = array_map('sanitize_key', (array) ($assertions['roles'] ?? []));
        $capabilities = array_map('sanitize_key', (array) ($assertions['capabilities'] ?? []));
        $professional_roles = ['verified_doctor','doctor','homeopathic_doctor','professional','founder','administrator'];
        $professional_caps = ['mkt_sell_regulated','sabri_verified_professional','publish_professional_content','manage_options'];
        $role_match = (bool) array_intersect($roles, $professional_roles);
        $cap_match = (bool) array_intersect($capabilities, $professional_caps);
        return (bool) apply_filters('mkt_verified_professional_assertion', $role_match || $cap_match, $assertions);
    }

    private static function dedupe_errors(array $errors): array {
        $seen = [];
        $out = [];
        foreach ($errors as $error) {
            $key = (string) ($error['code'] ?? '') . ':' . (string) ($error['field'] ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $error;
        }
        return $out;
    }
}
