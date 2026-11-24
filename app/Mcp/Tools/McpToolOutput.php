<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\StringType;

class McpToolOutput
{
    public function __construct(private JsonSchema $schema) {}

    public function forOperation(string $operation, bool $owner): ObjectType
    {
        $schema = $this->schema;

        return $schema->object(match ($operation) {
            'context' => [
                'role' => $schema->string()->enum(['platform_admin', 'owner', 'outlet_manager'])->required(),
                'business' => $schema->string()->nullable()->required(),
                'timezone' => $schema->string()->required(),
                'read_only' => $schema->boolean()->enum([true])->required(),
                'abilities' => $schema->array()->items($schema->string()->enum(array_keys(config('oqari_mcp.abilities'))))->required(),
                'branches' => $this->branches(),
            ],
            'branches' => ['branches' => $this->branches()],
            'snapshot', 'summary', 'compare', 'products' => $this->sales($operation, $owner),
            'catalog_products' => [
                'total_products' => $schema->integer()->min(0)->required(),
                ...$this->page([
                    'product_id' => $schema->integer()->min(1)->required(),
                    'name' => $schema->string()->required(),
                    'category' => $schema->string()->nullable()->required(),
                    'price' => $this->decimal(),
                    'discount_price' => $this->decimalNullable(),
                    'is_discount_active' => $schema->boolean()->required(),
                    'is_best_seller' => $schema->boolean()->required(),
                    'is_sold_out' => $schema->boolean()->required(),
                    'is_available' => $schema->boolean()->required(),
                ]),
            ],
            'sales_breakdown' => [
                ...$this->dates(),
                'timezone' => $schema->string()->required(),
                'currency' => $schema->string()->enum(['IDR'])->required(),
                'by_payment_method' => $this->listOf([
                    'payment_method' => $schema->string()->required(),
                    'paid_orders' => $schema->integer()->min(0)->required(),
                    'net_sales' => $this->decimal(),
                ]),
                'by_fulfillment_type' => $this->listOf([
                    'fulfillment_type' => $schema->string()->required(),
                    'paid_orders' => $schema->integer()->min(0)->required(),
                    'net_sales' => $this->decimal(),
                ]),
                'by_day' => $this->listOf([
                    'date' => $schema->string()->format('date')->required(),
                    'paid_orders' => $schema->integer()->min(0)->required(),
                    'net_sales' => $this->decimal(),
                ]),
                'peak_hours' => $this->listOf([
                    'hour' => $schema->integer()->min(0)->max(23)->required(),
                    'paid_orders' => $schema->integer()->min(0)->required(),
                    'net_sales' => $this->decimal(),
                ]),
            ],
            'orders' => $this->page([
                'order_id' => $schema->integer()->min(1)->required(),
                'branch_id' => $schema->integer()->min(1)->nullable()->required(),
                'branch_name' => $schema->string()->nullable()->required(),
                'payment_method' => $schema->string()->nullable()->required(),
                'order_status' => $schema->string()->nullable()->required(),
                'payment_status' => $schema->string()->nullable()->required(),
                'fulfillment_type' => $schema->string()->nullable()->required(),
                'subtotal' => $this->decimal(),
                'discount_amount' => $this->decimal(),
                'grand_total' => $this->decimal(),
                'voucher_code' => $schema->string()->nullable()->required(),
                'items_count' => $schema->integer()->min(0)->required(),
                'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            ]),
            'vouchers' => $this->page([
                'voucher_id' => $schema->integer()->min(1)->required(),
                'code' => $schema->string()->required(),
                'name' => $schema->string()->required(),
                'discount_type' => $schema->string()->required(),
                'discount_value' => $this->decimal(),
                'min_spend' => $this->decimalNullable(),
                'max_discount' => $this->decimalNullable(),
                'usage_limit' => $schema->integer()->min(0)->nullable()->required(),
                'used_count' => $schema->integer()->min(0)->required(),
                'is_active' => $schema->boolean()->required(),
                'expires_at' => $schema->string()->format('date-time')->nullable()->required(),
            ]),
            'low_stock' => $this->page([
                'material_id' => $schema->integer()->min(1)->required(),
                'name' => $schema->string()->required(),
                'unit' => $schema->string()->required(),
                'quantity' => $this->decimal(),
                'minimum_stock' => $this->decimal(),
                'location_id' => $schema->integer()->min(1)->required(),
                'location_name' => $schema->string()->required(),
            ]),
            'raw_materials' => [
                'total_materials' => $schema->integer()->min(0)->required(),
                ...$this->page([
                    'material_id' => $schema->integer()->min(1)->required(),
                    'name' => $schema->string()->required(),
                    'sku' => $schema->string()->nullable()->required(),
                    'unit' => $schema->string()->required(),
                    'quantity' => $this->decimal(),
                    'minimum_stock' => $this->decimal(),
                    'cost_per_unit' => $this->decimalNullable(),
                    'location_id' => $schema->integer()->min(1)->required(),
                    'location_name' => $schema->string()->required(),
                    'is_low_stock' => $schema->boolean()->required(),
                ]),
            ],
            'transfers' => $this->page([
                'transfer_id' => $schema->integer()->min(1)->required(),
                'material' => $schema->string()->nullable()->required(),
                'unit' => $schema->string()->nullable()->required(),
                'from' => $schema->string()->nullable()->required(),
                'to' => $schema->string()->nullable()->required(),
                'sent' => $this->decimal(),
                'received' => $this->decimal(),
                'outstanding' => $schema->number()->required(),
                'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            ]),
            'stock_mutations' => $this->page([
                'mutation_id' => $schema->integer()->min(1)->required(),
                'material' => $schema->string()->nullable()->required(),
                'unit' => $schema->string()->nullable()->required(),
                'type' => $schema->string()->required(),
                'amount' => $this->decimal(),
                'balance_after' => $this->decimalNullable(),
                'location_name' => $schema->string()->nullable()->required(),
                'notes' => $schema->string()->nullable()->required(),
                'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            ]),
            'purchase_orders' => $this->page([
                'po_id' => $schema->integer()->min(1)->required(),
                'supplier' => $schema->string()->nullable()->required(),
                'status' => $schema->string()->required(),
                'items_count' => $schema->integer()->min(0)->required(),
                'notes' => $schema->string()->nullable()->required(),
                'approved_at' => $schema->string()->format('date-time')->nullable()->required(),
                'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            ]),
            'operations' => [
                ...$this->dates(),
                'counts' => $schema->object([
                    'stock_requests' => $this->statusCounts(['PENDING', 'APPROVED', 'REJECTED', 'DISPATCHED', 'RECEIVED']),
                    'stock_counts' => $this->statusCounts(['PENDING', 'POSTED', 'REJECTED']),
                    'petty_purchases' => $this->statusCounts(['PENDING', 'APPROVED', 'REJECTED']),
                ])->withoutAdditionalProperties()->required(),
            ],
            'cash_sessions' => $this->page([
                'session_id' => $schema->integer()->min(1)->required(),
                'register_name' => $schema->string()->nullable()->required(),
                'cashier' => $schema->string()->nullable()->required(),
                'status' => $schema->string()->required(),
                'reconciliation_status' => $schema->string()->required(),
                'opening_cash' => $this->decimal(),
                'expected_cash' => $this->decimalNullable(),
                'actual_cash' => $this->decimalNullable(),
                'difference' => $this->decimalNullable(),
                'total_cash_sales' => $this->decimalNullable(),
                'total_qris_sales' => $this->decimalNullable(),
                'total_other_sales' => $this->decimalNullable(),
                'opened_at' => $schema->string()->format('date-time')->nullable()->required(),
                'closed_at' => $schema->string()->format('date-time')->nullable()->required(),
            ]),
            'attendance' => [
                ...$this->dates(),
                'total_records' => $schema->integer()->min(0)->required(),
                'checked_in' => $schema->integer()->min(0)->required(),
                'checked_out' => $schema->integer()->min(0)->required(),
                'late_count' => $schema->integer()->min(0)->required(),
                'total_late_minutes' => $schema->integer()->min(0)->required(),
                'total_worked_minutes' => $schema->integer()->min(0)->required(),
            ],
            'production_orders' => $this->page([
                'work_order_id' => $schema->integer()->min(1)->required(),
                'recipe' => $schema->string()->nullable()->required(),
                'location_name' => $schema->string()->nullable()->required(),
                'status' => $schema->string()->required(),
                'target_quantity' => $this->decimal(),
                'good_quantity' => $this->decimalNullable(),
                'waste_quantity' => $this->decimalNullable(),
                'total_cost' => $this->decimalNullable(),
                'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            ]),
            'platform' => ['businesses' => $this->statusCounts(['active', 'suspended'])],
        })->withoutAdditionalProperties()->required();
    }

    private function sales(string $operation, bool $owner): array
    {
        $schema = $this->schema;
        $branch = $this->branchFields();
        $money = [
            'net_sales' => $this->decimal(),
            'paid_orders' => $schema->integer()->min(0)->required(),
            'average_order_value' => $this->decimal(),
            ...($owner ? ['gross_profit' => $this->decimal()] : []),
        ];
        $fields = [
            ...$this->dates(),
            'timezone' => $schema->string()->required(),
            'currency' => $schema->string()->enum(['IDR'])->required(),
            'branch' => $schema->object($branch)->withoutAdditionalProperties()->required($operation === 'snapshot'),
        ];
        if ($operation !== 'products') {
            $fields['totals'] = $schema->object($money)->withoutAdditionalProperties()->required();
        }
        if ($operation === 'compare') {
            $fields['branches'] = $this->listOf($branch + $money);
        }
        if (in_array($operation, ['snapshot', 'products'], true)) {
            $fields['top_products'] = $this->listOf([
                ...$branch,
                'units_sold' => $schema->integer()->required(),
            ]);
        }

        return $fields;
    }

    private function dates(): array
    {
        return [
            'date_from' => $this->schema->string()->format('date')->required(),
            'date_to' => $this->schema->string()->format('date')->required(),
        ];
    }

    private function branchFields(): array
    {
        return [
            'branch_id' => $this->schema->integer()->min(1)->nullable()->required(),
            'name' => $this->schema->string()->required(),
        ];
    }

    private function branches(): ArrayType
    {
        return $this->listOf($this->branchFields() + ['slug' => $this->schema->string()->required()]);
    }

    private function page(array $fields): array
    {
        return [
            'items' => $this->listOf($fields)->max(config('oqari_mcp.max_page_size')),
            'next_page' => $this->schema->integer()->min(2)->nullable()->required(),
        ];
    }

    private function listOf(array $fields): ArrayType
    {
        return $this->schema->array()->items($this->schema->object($fields)->withoutAdditionalProperties())->required();
    }

    private function decimal(): StringType
    {
        return $this->schema->string()->pattern('^-?[0-9]+([.][0-9]+)?$')->required();
    }

    private function decimalNullable(): StringType
    {
        return $this->schema->string()->pattern('^-?[0-9]+([.][0-9]+)?$')->nullable()->required();
    }

    /** @param list<string> $statuses */
    private function statusCounts(array $statuses): ObjectType
    {
        $counts = [];
        foreach ($statuses as $status) {
            $counts[$status] = $this->schema->integer()->min(0);
        }

        return $this->schema->object($counts)->description('Jumlah per status yang tercatat pada periode atau cakupan ini.')->required();
    }
}
