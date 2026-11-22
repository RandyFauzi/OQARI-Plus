<?php

namespace App\Mcp\Tools;

use App\Models\McpConnection;
use App\Services\McpAccess;
use App\Services\McpReadQueries;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

abstract class ReadTool extends Tool
{
    protected const Operation = '';

    protected const Ability = null;

    private const Periods = ['today', 'yesterday', 'last_7_days', 'last_30_days'];

    public function annotations(): array
    {
        return [...parent::annotations(), 'readOnlyHint' => true];
    }

    public function shouldRegister(McpAccess $access): bool
    {
        $connection = request()->attributes->get(McpConnection::class);
        if (! $connection instanceof McpConnection) {
            return false;
        }

        return match (static::Ability) {
            null => true,
            'tenant' => $connection->shop_id !== null,
            default => $access->allows($connection, static::Ability),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        $available = [
            'branch_id' => $schema->integer()->min(1)->description('ID dari oqari_list_branches.'),
            'branch_name' => $schema->string()->description('Nama atau slug cabang.'),
            'period' => $schema->string()->enum(self::Periods)->description('Default hari ini. Untuk tanggal tertentu gunakan date_from dan date_to.'),
            'date_from' => $schema->string()->description('Tanggal awal inklusif dalam format YYYY-MM-DD.'),
            'date_to' => $schema->string()->description('Tanggal akhir inklusif dalam format YYYY-MM-DD.'),
            'limit' => $schema->integer()->min(1)->max(config('oqari_mcp.max_page_size')),
            'page' => $schema->integer()->min(1)->max(1000),
        ];

        return array_intersect_key($available, $this->rules());
    }

    public function outputSchema(JsonSchema $schema): array
    {
        $connection = request()->attributes->get(McpConnection::class);

        return ['data' => (new McpToolOutput($schema))->forOperation(static::Operation, $connection?->role === 'owner')];
    }

    public function handle(Request $request, McpAccess $access, McpReadQueries $queries): ResponseFactory
    {
        $connection = request()->attributes->get(McpConnection::class);
        abort_unless($this->shouldRegister($access), 403);

        $outcome = 'failed';
        $errorCode = 'query_failed';
        $response = Response::make(Response::error('Data OQARI belum dapat dibaca. Coba lagi.'));
        try {
            $rules = $this->rules();
            if (array_diff_key($request->all(), $rules) !== []) {
                throw ValidationException::withMessages(['arguments' => 'Argumen tidak didukung. Gunakan parameter dari tools/list.']);
            }
            $arguments = Validator::make($request->all(), $rules)->validate();
            $data = match (static::Operation) {
                'context' => $queries->context($connection),
                'branches' => $queries->branchList($connection),
                'snapshot', 'summary', 'compare', 'products' => $queries->sales($connection, $arguments, static::Operation),
                'low_stock' => $queries->lowStock($connection, $arguments),
                'transfers' => $queries->transfers($connection, $arguments),
                'operations' => $queries->operations($connection, $arguments),
                'platform' => $queries->platform(),
            };
            $outcome = 'success';
            $errorCode = null;

            $response = Response::structured(['data' => $data]);
        } catch (AuthorizationException $exception) {
            $outcome = 'denied';
            $errorCode = 'branch_unavailable';
            $response = Response::make(Response::error($exception->getMessage()));
        } catch (ValidationException $exception) {
            $outcome = 'invalid_input';
            $errorCode = 'validation_failed';
            $response = Response::make(Response::error($exception->validator->errors()->first()));
        } catch (Throwable $exception) {
            report($exception);
        }

        request()->attributes->set('oqari_mcp.tool_call', [
            'tool' => $this->name(),
            'outcome' => $outcome,
            'error_code' => $errorCode,
        ]);

        return $response->withMeta('com.oqari/traceId', request()->attributes->get('oqari_mcp.trace_id'));
    }

    private function rules(): array
    {
        $branch = ['branch_id' => ['sometimes', 'integer', 'min:1'], 'branch_name' => ['sometimes', 'string', 'max:150']];
        $dates = [
            'period' => ['sometimes', 'prohibits:date_from,date_to', 'in:'.implode(',', self::Periods)],
            'date_from' => ['required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['required_with:date_from', 'date_format:Y-m-d'],
        ];
        $limit = ['limit' => ['sometimes', 'integer', 'min:1', 'max:'.config('oqari_mcp.max_page_size')]];

        return match (static::Operation) {
            'snapshot', 'products' => $branch + $dates + $limit,
            'compare', 'summary', 'operations' => $branch + $dates,
            'low_stock', 'transfers' => $branch + $limit + ['page' => ['sometimes', 'integer', 'min:1', 'max:1000']],
            default => [],
        };
    }
}
