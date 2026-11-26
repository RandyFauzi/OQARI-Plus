<?php

return [
    'groups' => [
        'branches' => [
            'shops' => ['branches_enabled'],
            'branches' => ['id', 'shop_id', 'slug', 'name', 'address', 'is_primary', 'is_active'],
            'branch_memberships' => ['id', 'branch_id', 'user_id', 'role'],
            'orders' => ['branch_id'],
            'tables' => ['branch_id'],
            'cash_registers' => ['branch_id'],
            'attendances' => ['branch_id'],
            'crew_shifts' => ['branch_id'],
            'shift_templates' => ['branch_id'],
            'printers' => ['branch_id'],
            'inventory_locations' => ['branch_id'],
            'outlet_stock_requests' => ['branch_id'],
            'stock_counts' => ['branch_id'],
            'petty_purchases' => ['branch_id'],
            'activity_logs' => ['branch_id'],
            'stock_mutations' => ['branch_id'],
        ],
        'inventory_controls' => [
            'cash_drawer_transactions' => ['shop_id', 'user_id'],
            'outlet_stock_requests' => ['id', 'shop_id', 'raw_material_id', 'stock_transfer_id', 'request_id', 'source', 'unit', 'requested_quantity', 'approved_quantity', 'status', 'notes', 'decision_note', 'created_by', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at'],
            'stock_counts' => ['id', 'shop_id', 'inventory_location_id', 'request_id', 'frequency', 'counted_on', 'period_start', 'period_end', 'status', 'notes', 'decision_note', 'created_by', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at'],
            'stock_count_items' => ['id', 'stock_count_id', 'raw_material_id', 'last_mutation_id', 'unit', 'system_quantity', 'counted_quantity', 'difference', 'created_at', 'updated_at'],
            'petty_purchases' => ['id', 'shop_id', 'request_id', 'description', 'notes', 'proof_path', 'proof_name', 'amount', 'funding', 'status', 'cash_register_session_id', 'cash_drawer_transaction_id', 'raw_material_id', 'quantity', 'unit', 'decision_note', 'created_by', 'reviewed_by', 'received_by', 'reviewed_at', 'received_at', 'created_at', 'updated_at'],
        ],
        'summary' => [
            'categories' => ['id', 'shop_id', 'name', 'sort_order'],
            'tables' => ['id', 'shop_id', 'name'],
            'users' => ['id', 'shop_id', 'name', 'role'],
        ],
        'catalog' => [
            'shop_slug_histories' => ['id', 'shop_id', 'old_slug'],
            'categories' => ['id', 'shop_id', 'name', 'slug', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'products' => ['id', 'shop_id', 'category_id', 'category_name', 'name', 'description', 'price', 'discount_price', 'is_discount_active', 'cogs', 'image_path', 'is_sold_out', 'deleted_at', 'created_at', 'updated_at'],
            'product_variants' => ['id', 'product_id', 'name', 'price_adjustment', 'is_active'],
            'modifier_groups' => ['id', 'product_id', 'name', 'is_required', 'min_selections', 'max_selections', 'is_active'],
            'modifiers' => ['id', 'modifier_group_id', 'name', 'price_adjustment', 'is_active'],
        ],
        'orders' => [
            'orders' => ['id', 'shop_id', 'table_id', 'customer_name', 'customer_phone', 'customer_email', 'subtotal', 'discount_amount', 'voucher_code', 'discount_reason', 'tax_amount', 'service_charge_amount', 'grand_total', 'total_cogs', 'order_status', 'payment_status', 'payment_method', 'fulfillment_type', 'created_at', 'updated_at'],
            'order_items' => ['id', 'order_id', 'product_id', 'product_name', 'variant_name', 'quantity', 'unit_price', 'subtotal', 'discount_amount', 'tax_amount', 'cogs_total', 'modifiers', 'notes', 'created_at', 'updated_at'],
            'tables' => ['id', 'shop_id', 'name', 'public_token'],
        ],
        'variant_costs' => [
            'product_variants' => ['cogs_adjustment'],
            'modifiers' => ['cogs_adjustment'],
            'cash_drawer_transactions' => ['payment_id'],
        ],
        'checkout' => [
            'orders' => ['checkout_request_id', 'amount_paid', 'change_amount', 'payment_reference', 'paid_at'],
            'order_items' => ['variant_id'],
            'payments' => ['id', 'shop_id', 'order_id', 'payment_method', 'amount', 'reference_number', 'cancelled_at', 'created_at', 'updated_at'],
        ],
        'register' => [
            'cash_registers' => ['id', 'shop_id', 'name', 'is_active'],
            'cash_register_sessions' => ['id', 'cash_register_id', 'user_id', 'status', 'opened_at', 'closed_at', 'opening_cash', 'expected_cash', 'actual_cash', 'difference', 'total_cash_sales', 'total_qris_sales', 'total_other_sales', 'notes', 'created_at', 'updated_at'],
            'cash_drawer_transactions' => ['id', 'cash_register_session_id', 'type', 'amount', 'description', 'cancelled_at'],
        ],
        'inventory' => [
            'raw_materials' => ['id', 'shop_id', 'name', 'sku', 'unit', 'current_stock', 'minimum_stock', 'cost_per_unit', 'deleted_at', 'created_at', 'updated_at'],
            'product_recipes' => ['id', 'product_id', 'raw_material_id', 'amount', 'created_at', 'updated_at'],
            'stock_mutations' => ['id', 'shop_id', 'raw_material_id', 'type', 'amount', 'balance_after', 'unit', 'reference_type', 'reference_id', 'notes', 'user_id', 'created_at', 'updated_at'],
        ],
        'location_inventory' => [
            'inventory_locations' => ['id', 'shop_id', 'name', 'code'],
            'inventory_balances' => ['inventory_location_id', 'raw_material_id', 'quantity'],
            'stock_transfers' => ['id', 'shop_id', 'request_id', 'from_location_id', 'to_location_id', 'raw_material_id', 'sent_quantity', 'received_quantity', 'sent_by', 'received_by', 'received_at'],
            'stock_mutations' => ['inventory_location_id', 'unit_cost'],
            'modifier_recipes' => ['modifier_id', 'raw_material_id', 'amount'],
            'orders' => ['qr_request_id'],
            'order_items' => ['stock_posted_at', 'batch_request_id', 'batch_no', 'status'],
        ],
        'production' => [
            'production_recipes' => ['id', 'shop_id', 'output_material_id', 'request_id', 'version', 'yield_quantity', 'unit', 'notes', 'created_by', 'created_at', 'updated_at'],
            'production_recipe_items' => ['id', 'production_recipe_id', 'raw_material_id', 'quantity', 'unit', 'created_at', 'updated_at'],
            'production_work_orders' => ['id', 'shop_id', 'production_recipe_id', 'inventory_location_id', 'request_id', 'status', 'target_quantity', 'good_quantity', 'waste_quantity', 'waste_reason', 'notes', 'result_revision', 'total_cost', 'output_unit_cost', 'created_by', 'recorded_by', 'posted_by', 'recorded_at', 'posted_at', 'created_at', 'updated_at'],
            'production_work_order_items' => ['id', 'production_work_order_id', 'raw_material_id', 'unit', 'planned_quantity', 'actual_quantity', 'unit_cost', 'created_at', 'updated_at'],
        ],
        'purchasing' => [
            'suppliers' => ['id', 'shop_id', 'name', 'contact', 'created_at', 'updated_at'],
            'purchase_orders' => ['id', 'shop_id', 'supplier_id', 'request_id', 'request_hash', 'status', 'notes', 'created_by', 'approved_by', 'approved_at', 'created_at', 'updated_at'],
            'purchase_order_items' => ['id', 'purchase_order_id', 'raw_material_id', 'unit', 'quantity', 'unit_cost', 'accepted_quantity', 'created_at', 'updated_at'],
            'goods_receipts' => ['id', 'purchase_order_id', 'inventory_location_id', 'request_id', 'request_hash', 'delivery_reference', 'notes', 'received_by', 'created_at', 'updated_at'],
            'goods_receipt_items' => ['id', 'goods_receipt_id', 'purchase_order_item_id', 'accepted_quantity', 'rejected_quantity', 'rejection_reason', 'created_at', 'updated_at'],
        ],
        'hpp' => [
            'products' => ['hpp_overhead_cost', 'hpp_target_margin', 'hpp_settings'],
            'product_recipes' => ['batch_amount', 'notes'],
        ],
        'users' => [
            'users' => ['id', 'shop_id', 'name', 'email', 'password', 'role', 'is_active', 'permissions', 'account_version', 'remember_token', 'email_verified_at', 'created_at', 'updated_at'],
        ],
        'user_archive' => [
            'users' => ['archived_at'],
        ],
        'shifts' => [
            'crew_shifts' => ['id', 'shop_id', 'user_id', 'shift_template_id', 'position', 'date', 'start_time', 'end_time', 'notes', 'created_at', 'updated_at'],
        ],
        'attendance' => [
            'attendances' => ['id', 'shop_id', 'user_id', 'employee_name', 'crew_shift_id', 'work_date', 'timezone', 'scheduled_start_at', 'scheduled_end_at', 'checked_in_at', 'checked_out_at', 'late_minutes', 'early_leave_minutes', 'worked_minutes', 'check_in_note', 'check_out_note', 'check_in_photo_path', 'check_in_latitude', 'check_in_longitude', 'check_in_accuracy', 'check_in_distance_meters', 'check_in_in_radius', 'check_out_photo_path', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy', 'check_out_distance_meters', 'check_out_in_radius', 'created_at', 'updated_at'],
        ],
        'audit' => [
            'activity_logs' => ['id', 'shop_id', 'user_id', 'action', 'description', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at', 'updated_at'],
        ],
        'settings' => [
            'shop_slug_histories' => ['id', 'shop_id', 'old_slug'],
            'shops' => ['id', 'name', 'slug', 'logo_path', 'banner_paths', 'primary_color', 'theme_style', 'business_type', 'sales_modes', 'onboarding_status', 'onboarding_step', 'is_open', 'slogan', 'font_family', 'title_font_family', 'receipt_header', 'receipt_footer', 'receipt_settings', 'printer_settings', 'discount_settings', 'instagram_link', 'whatsapp_number', 'maps_link', 'is_banner_active', 'operating_hours', 'address', 'email', 'latitude', 'longitude', 'attendance_radius_meters', 'attendance_require_photo', 'attendance_require_gps', 'attendance_strict_location'],
            'payment_methods' => ['id', 'shop_id', 'name', 'code', 'type', 'is_active'],
            'printers' => ['id', 'shop_id', 'name', 'location', 'connection_type', 'ip_address', 'port', 'paper_width', 'category_ids', 'is_active', 'auto_print', 'created_at', 'updated_at'],
            'vouchers' => ['id', 'shop_id', 'code', 'name', 'discount_type', 'discount_value', 'min_spend', 'max_discount', 'usage_limit', 'used_count', 'expires_at', 'is_active', 'created_at', 'updated_at'],
        ],
        'reports' => [
            'payments' => ['id', 'shop_id', 'order_id', 'payment_method', 'amount', 'cancelled_at', 'created_at'],
        ],
        'platform' => [
            'shops' => ['status', 'trial_ends_at', 'last_active_at', 'mrr', 'suspended_at', 'suspended_reason', 'suspended_by'],
            'superadmin_audit_logs' => ['id', 'actor_id', 'action', 'target_type', 'target_id', 'target_label', 'meta', 'ip_address', 'created_at', 'updated_at'],
        ],
        'static_qris' => [
            'branches' => ['static_qris_path'],
            'orders' => ['payment_channel', 'payment_qr_path'],
        ],
        'receipts' => [
            'receipt_deliveries' => ['id', 'order_id', 'shop_id', 'channel', 'destination', 'status', 'attempts', 'provider_message_id', 'failure_reason', 'sent_at', 'failed_at', 'created_at', 'updated_at'],
        ],
    ],
    'features' => [
        'branches' => ['branches', 'users', 'user_archive', 'location_inventory'],
        'summary' => ['summary'],
        'catalog' => ['catalog'], 'orders' => ['orders', 'catalog'],
        'pos' => ['catalog', 'orders', 'register'],
        'checkout' => ['catalog', 'orders', 'checkout', 'register', 'inventory', 'location_inventory', 'receipts'],
        'static_qris' => ['static_qris'],
        'location_inventory' => ['location_inventory'],
        'inventory_controls' => ['inventory_controls', 'inventory', 'location_inventory', 'production', 'register'],
        'production' => ['production', 'inventory', 'location_inventory', 'audit'],
        'purchasing' => ['purchasing', 'inventory', 'location_inventory', 'audit'],
        'inventory' => ['inventory', 'location_inventory', 'audit'], 'hpp' => ['catalog', 'inventory', 'hpp', 'audit'],
        'users' => ['users', 'audit'], 'user_archive' => ['users', 'user_archive', 'audit'], 'shifts' => ['shifts'],
        'attendance' => ['attendance', 'shifts', 'audit'], 'register' => ['register', 'audit'],
        'settings' => ['settings', 'orders'], 'reports' => ['reports', 'orders'],
        'analytics' => ['orders', 'catalog'], 'audit' => ['audit'], 'platform' => ['platform'],
    ],
];
