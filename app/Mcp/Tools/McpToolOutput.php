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
            'low_stock' => $this->page([
                'material_id' => $schema->integer()->min(1)->required(),
                'name' => $schema->string()->required(),
                'unit' => $schema->string()->required(),
                'quantity' => $this->decimal(),
                'minimum_stock' => $this->decimal(),
                'location_id' => $schema->integer()->min(1)->required(),
                'location_name' => $schema->string()->required(),
            ]),
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
            'operations' => [
                ...$this->dates(),
                'counts' => $schema->object([
                    'stock_requests' => $this->statusCounts(['PENDING', 'APPROVED', 'REJECTED', 'DISPATCHED', 'RECEIVED']),
                    'stock_counts' => $this->statusCounts(['PENDING', 'POSTED', 'REJECTED']),
                    'petty_purchases' => $this->statusCounts(['PENDING', 'APPROVED', 'REJECTED']),
                ])->withoutAdditionalProperties()->required(),
            ],
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
