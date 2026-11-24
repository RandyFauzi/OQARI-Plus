<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Models\Branch;
use App\Models\InventoryLocation;
use App\Models\McpConnection;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockTransfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use League\OAuth2\Server\AuthorizationServer;
use Tests\TestCase;

class OqariMcpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oqari_mcp.enabled' => true, 'oqari_mcp.oauth_enabled' => false]);
    }

    public function test_manager_reads_only_assigned_outlet_and_cannot_call_owner_tools(): void
    {
        [$owner, $pramuka, $other] = $this->business();
        $manager = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'manager']);
        app(ManageBranches::class)->assign($pramuka, $manager, 'manager');
        $token = $this->token($manager, [$pramuka->id], ['sales', 'inventory', 'operations', 'platform']);

        $this->rpc($token, 'tools/list', modern: true)->assertOk()
            ->assertJsonPath('result.tools.0.annotations.readOnlyHint', true)
            ->assertJsonFragment(['name' => 'oqari_get_branch_snapshot'])
            ->assertJsonMissing(['name' => 'oqari_get_operations_summary'])
            ->assertJsonMissing(['name' => 'oqari_get_platform_summary']);
        $this->callTool($token, 'oqari_list_branches')->assertOk()
            ->assertJsonPath('result.structuredContent.data.branches.0.name', 'Outlet Pramuka')
            ->assertJsonCount(1, 'result.structuredContent.data.branches');

        $today = CarbonImmutable::today(config('attendance.timezone'));
        $this->customerOrder($pramuka, $today, 16000, 'Jawara', 4);
        $this->customerOrder($pramuka, $today->subSecond(), 99000, 'Menu Kemarin', 9);
        $this->customerOrder($pramuka, $today, 99000, 'Menu Belum Lunas', 9, 'UNPAID');
        $this->customerOrder($other, $today, 47000, 'Sultan', 9);
        $this->callTool($token, 'oqari_get_branch_snapshot', ['branch_name' => 'Outlet Pramuka'], modern: true)
            ->assertOk()->assertJsonPath('result.structuredContent.data.date_from', $today->toDateString())
            ->assertJsonPath('result.structuredContent.data.totals.net_sales', '16000.00')
            ->assertJsonPath('result.structuredContent.data.top_products.0.name', 'Jawara')
            ->assertJsonPath('result.structuredContent.data.top_products.0.units_sold', 4)
            ->assertJsonMissingPath('result.structuredContent.data.totals.gross_profit');
        $this->callTool($token, 'oqari_get_branch_snapshot', ['branch_id' => $other->id])
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->callTool($token, 'oqari_get_operations_summary')->assertJsonStructure(['error' => ['code']]);
        $this->assertDatabaseHas('mcp_tool_calls', ['tool' => 'oqari_get_branch_snapshot', 'outcome' => 'denied']);
    }

    public function test_owner_respects_connection_grant_and_cannot_read_other_business(): void
    {
        [$owner, $pramuka, $other] = $this->business();
        [, $foreign] = $this->business();
        $today = CarbonImmutable::today(config('attendance.timezone'));
        $this->customerOrder($pramuka, $today, 100000, 'Jawara', 5);
        $this->customerOrder($other, $today, 200000, 'Garuda', 8);
        $this->customerOrder($foreign, $today, 900000, 'Sultan', 30);
        $token = $this->token($owner, [$pramuka->id], ['sales', 'inventory', 'operations']);

        $this->callTool($token, 'oqari_compare_branch_sales')->assertOk()
            ->assertJsonPath('result.structuredContent.data.totals.net_sales', '100000.00')
            ->assertJsonPath('result.structuredContent.data.totals.gross_profit', '80000.00')
            ->assertJsonCount(1, 'result.structuredContent.data.branches');
        $this->callTool($token, 'oqari_get_branch_snapshot', ['branch_id' => $foreign->id])
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->callTool($token, 'oqari_get_sales_summary', ['shop_id' => $foreign->shop_id])
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->callTool($token, 'oqari_get_sales_summary', ['date_from' => $today->toDateString()])
            ->assertOk()->assertJsonPath('result.isError', true);
        $this->callTool($token, 'oqari_list_low_stock')->assertOk()->assertJsonStructure(['result' => ['structuredContent' => ['data' => ['items', 'next_page']]]]);
        $this->callTool($token, 'oqari_list_stock_transfers')->assertOk()->assertJsonStructure(['result' => ['structuredContent' => ['data' => ['items', 'next_page']]]]);
        $this->callTool($token, 'oqari_get_operations_summary')->assertOk()->assertJsonStructure(['result' => ['structuredContent' => ['data' => ['counts']]]]);
    }

    public function test_platform_admin_discovers_only_platform_tools_and_staff_is_denied(): void
    {
        [$owner, $branch] = $this->business();
        $platform = User::factory()->create(['shop_id' => null, 'role' => 'superadmin']);
        $cashier = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'cashier']);
        app(ManageBranches::class)->assign($branch, $cashier, 'cashier');
        $platformToken = $this->token($platform, null, ['platform', 'sales']);

        $this->rpc($platformToken, 'server/discover', modern: true)->assertOk()
            ->assertJsonFragment(['2026-07-28'])->assertJsonStructure(['result' => ['supportedVersions']]);
        $this->rpc($platformToken, 'tools/list')->assertOk()->assertJsonCount(2, 'result.tools');
        $this->callTool($platformToken, 'oqari_get_platform_summary')->assertOk()
            ->assertJsonStructure(['result' => ['structuredContent' => ['data' => ['businesses']]]]);
        $this->callTool($platformToken, 'oqari_get_sales_summary')->assertJsonStructure(['error' => ['code']]);

        $this->actingAs($cashier)->get(route('admin.integrations.mcp'))->assertForbidden();
        $this->rpc($this->token($cashier, [$branch->id], ['sales']), 'tools/list')->assertUnauthorized();
        $this->rpc('', 'tools/list')->assertUnauthorized();
    }

    public function test_key_is_shown_once_and_revoking_it_prevents_further_calls(): void
    {
        [$owner, $pramuka] = $this->business();
        $this->actingAs($owner)->get(route('admin.integrations.mcp'))->assertOk()
            ->assertSee('Salin prompt');
        $this->post(route('admin.integrations.mcp.store'), [
            'name' => 'Laporan AI', 'branch_ids' => [$pramuka->id], 'abilities' => ['sales'],
        ])->assertRedirect(route('admin.integrations.mcp'));

        $connection = McpConnection::where('user_id', $owner->id)->sole();
        $page = $this->get(route('admin.integrations.mcp'))->assertOk();
        $token = $page->viewData('newToken');
        $this->assertSame(hash('sha256', $token), $connection->token_hash);
        $this->get(route('admin.integrations.mcp'))->assertDontSee($token);
        $this->rpc($token, 'tools/list')->assertOk();
        $this->delete(route('admin.integrations.mcp.destroy', $connection->id))->assertRedirect();
        $this->rpc($token, 'tools/list')->assertUnauthorized();
    }

    public function test_quick_setup_uses_allowed_outlets_and_does_not_expand_manager_permissions(): void
    {
        [$owner, $assigned, $other] = $this->business();
        $manager = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'manager']);
        app(ManageBranches::class)->assign($assigned, $manager, 'manager');
        $setup = route('admin.integrations.mcp', ['client' => 'claude-code']);

        $this->actingAs($manager)->get($setup)->assertSee('Hubungkan Claude Code')->assertSee('Opsi lanjutan');
        $this->post(route('admin.integrations.mcp.store'), [
            'mode' => 'quick', 'client' => 'claude-code', 'abilities' => ['operations'], 'branch_ids' => [$other->id],
        ])->assertRedirect($setup);

        $connection = McpConnection::where('user_id', $manager->id)->sole();
        $this->assertSame('Claude Code', $connection->name);
        $this->assertSame([$assigned->id], $connection->branch_ids);
        $this->assertSame(['sales', 'inventory'], $connection->abilities);
        $page = $this->get($setup)->assertSee('Kunci Claude Code siap disalin')->assertSee('Bearer ${OQARI_MCP_TOKEN}');
        $token = $page->viewData('newToken');

        $tokens = route('admin.integrations.mcp', ['tab' => 'tokens']);
        $connected = route('admin.integrations.mcp', ['tab' => 'connected']);
        $this->get($tokens)->assertSee('Belum digunakan')->assertDontSee($token);
        $this->get($connected)->assertDontSee('Pernah digunakan');
        $this->rpc($token, 'tools/list')->assertOk()->assertJsonMissing(['name' => 'oqari_get_operations_summary']);
        $this->get($connected)->assertSee('Pernah digunakan')->assertSee('Terakhir digunakan');

        $this->post(route('admin.integrations.mcp.store'), [
            'mode' => 'custom', 'client' => 'claude-code', 'name' => 'Percobaan cakupan',
            'abilities' => ['operations'], 'branch_ids' => [$other->id],
        ])->assertSessionHasErrors(['abilities.0', 'branch_ids.0']);
        $this->assertDatabaseCount('mcp_connections', 1);
    }

    public function test_quick_setup_for_owner_covers_only_active_outlets_in_the_business(): void
    {
        [$owner, $first, $second] = $this->business();
        [, $foreign] = $this->business();
        $inactive = app(ManageBranches::class)->create($owner->shop, 'Outlet Tutup');
        $inactive->update(['is_active' => false]);
        $this->actingAs($owner)->post(route('admin.integrations.mcp.store'), [
            'mode' => 'quick', 'client' => 'codex',
        ])->assertRedirect(route('admin.integrations.mcp', ['client' => 'codex']));

        $connection = McpConnection::where('user_id', $owner->id)->sole();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $connection->branch_ids);
        $this->assertNotContains($foreign->id, $connection->branch_ids);
        $this->assertSame(['sales', 'inventory', 'operations'], $connection->abilities);

        $this->post(route('admin.integrations.mcp.store'), ['mode' => 'quick', 'client' => 'unsupported'])
            ->assertSessionHasErrors(['client']);
        $this->assertDatabaseCount('mcp_connections', 1);
    }

    public function test_assignment_and_account_version_are_checked_after_key_creation(): void
    {
        [$owner, $pramuka] = $this->business();
        $manager = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'manager']);
        app(ManageBranches::class)->assign($pramuka, $manager, 'manager');
        $token = $this->token($manager, [$pramuka->id], ['sales']);
        $this->rpc($token, 'tools/list')->assertOk();
        $pramuka->memberships()->where('user_id', $manager->id)->delete();
        $this->rpc($token, 'tools/list')->assertUnauthorized();

        $ownerToken = $this->token($owner, [$pramuka->id], ['sales']);
        $owner->forceFill(['account_version' => (int) $owner->account_version + 1])->save();
        $this->rpc($ownerToken, 'tools/list')->assertUnauthorized();
    }

    public function test_oauth_pkce_is_bound_to_the_mcp_resource_and_can_be_revoked(): void
    {
        [$owner, $branch] = $this->business();
        $options = ['private_key_bits' => 2048];
        $bundledConfiguration = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($bundledConfiguration)) {
            $options['config'] = $bundledConfiguration;
        }
        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey, null, $options);
        $resource = route('oqari.mcp');
        config([
            'oqari_mcp.oauth_enabled' => true,
            'passport.private_key' => $privateKey,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
        ]);

        $this->rpc('', 'tools/list')->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="OQARI MCP", resource_metadata="'.route('oqari.mcp.metadata').'", scope="mcp:use"');
        $this->getJson(route('oqari.mcp.metadata'))->assertOk()->assertJsonPath('resource', $resource);
        $this->getJson(route('oqari.mcp.authorization-metadata'))->assertOk()
            ->assertJsonPath('token_endpoint', route('passport.token'));
        $this->postJson(route('passport.token'), ['resource' => 'https://invalid.example'])
            ->assertBadRequest()->assertJsonPath('error', 'invalid_target');

        $redirect = 'http://127.0.0.1:9995/callback';
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Test OAuth', [$redirect], confidential: false);
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        config(['oqari_mcp.codex_client_id' => $client->id]);
        $authorize = route('passport.authorizations.authorize', [
            'client_id' => $client->id, 'redirect_uri' => $redirect, 'response_type' => 'code',
            'scope' => 'mcp:use', 'state' => 'test-state', 'resource' => $resource,
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]);
        $this->get($authorize)->assertRedirect(route('login'));
        $returnTo = $this->post(route('login'), ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect()->headers->get('Location');
        $this->assertSame('/oauth/authorize', parse_url($returnTo, PHP_URL_PATH));
        parse_str(parse_url($returnTo, PHP_URL_QUERY), $intended);
        $this->assertSame('test-state', $intended['state']);
        $this->get($returnTo)->assertOk()->assertSessionHas('authToken');
        $this->get(route('admin.integrations.mcp'))->assertSee('Login OQARI')
            ->assertSee('Salin perintah hubungkan')->assertSee($client->id);
        $approved = $this->post(route('passport.authorizations.approve'), ['auth_token' => session('authToken')])->assertRedirect();
        parse_str(parse_url($approved->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('test-state', $query['state']);
        $this->assertSame(rtrim(config('app.url'), '/'), $query['iss']);
        $issued = $this->post(route('passport.token'), [
            'grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => $redirect,
            'code' => $query['code'], 'code_verifier' => $verifier, 'resource' => $resource,
        ])->assertOk();
        $accessToken = $issued->json('access_token');

        $this->callTool($accessToken, 'oqari_get_context')->assertOk()
            ->assertJsonPath('result.structuredContent.data.role', 'owner')
            ->assertJsonPath('result.structuredContent.data.abilities', ['sales']);
        $foreignResource = 'https://other-resource.example/mcp';
        config(['oqari_mcp.public_url' => $foreignResource]);
        $this->rpc($accessToken, 'tools/list')->assertUnauthorized();

        $this->app->forgetInstance(AuthorizationServer::class);
        Route::getRoutes()->getByName('passport.token')->flushController();
        $this->post(route('passport.token'), [
            'grant_type' => 'refresh_token', 'client_id' => $client->id,
            'refresh_token' => $issued->json('refresh_token'), 'resource' => $foreignResource,
        ])->assertBadRequest()->assertJsonPath('error', 'invalid_grant');

        config(['oqari_mcp.public_url' => $resource]);
        $this->app->forgetInstance(AuthorizationServer::class);
        Route::getRoutes()->getByName('passport.token')->flushController();
        $refreshed = $this->post(route('passport.token'), [
            'grant_type' => 'refresh_token', 'client_id' => $client->id,
            'refresh_token' => $issued->json('refresh_token'), 'resource' => $resource,
        ])->assertOk()->json('access_token');
        $this->rpc($refreshed, 'tools/list')->assertOk();

        $connection = McpConnection::query()->where('oauth_client_id', $client->id)->sole();
        $this->assertContains($branch->id, $connection->branch_ids);
        $this->assertSame('Codex', $connection->name);
        $this->delete(route('admin.integrations.mcp.destroy', $connection->id))->assertRedirect();
        $this->rpc($refreshed, 'tools/list')->assertUnauthorized();
    }

    public function test_tool_output_schemas_match_real_responses_for_each_role(): void
    {
        [$owner, $branch, $other] = $this->business();
        $today = CarbonImmutable::today(config('attendance.timezone'));
        $this->customerOrder($branch, $today, 16000, 'Jawara', 2);
        $material = RawMaterial::create([
            'shop_id' => $owner->shop_id, 'name' => 'Saus', 'unit' => 'ml',
            'current_stock' => 0, 'minimum_stock' => 100,
        ]);
        $from = InventoryLocation::where('branch_id', $other->id)->sole();
        $to = InventoryLocation::where('branch_id', $branch->id)->sole();
        StockTransfer::create([
            'shop_id' => $owner->shop_id, 'raw_material_id' => $material->id,
            'from_location_id' => $from->id, 'to_location_id' => $to->id,
            'request_id' => (string) Str::uuid(), 'sent_quantity' => 20, 'received_quantity' => 5, 'sent_by' => $owner->id,
        ]);
        $ownerToken = $this->token($owner, [$branch->id, $other->id], ['sales', 'operations', 'inventory']);
        $platform = User::factory()->create(['shop_id' => null, 'role' => 'superadmin']);
        $platformToken = $this->token($platform, null, ['platform']);
        $manager = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'manager']);
        app(ManageBranches::class)->assign($branch, $manager, 'manager');
        $managerToken = $this->token($manager, [$branch->id], ['sales', 'inventory']);

        foreach ([$ownerToken => 19, $platformToken => 2, $managerToken => 15] as $token => $expectedCount) {
            $tools = $this->rpc($token, 'tools/list')->assertOk()->json('result.tools');
            $this->assertCount($expectedCount, $tools);
            foreach ($tools as $tool) {
                $arguments = $tool['name'] === 'oqari_get_branch_snapshot' ? ['branch_id' => $branch->id] : [];
                $result = json_decode($this->callTool($token, $tool['name'], $arguments)->assertOk()
                    ->assertJsonPath('result.isError', false)->getContent())->result;
                $this->assertOutputMatchesContract($tool['outputSchema'], $result->structuredContent, $tool['name']);
            }
        }
    }

    public function test_success_and_denials_can_be_traced_without_recording_raw_credentials(): void
    {
        [$owner, $branch] = $this->business();
        $manager = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'manager']);
        app(ManageBranches::class)->assign($branch, $manager, 'manager');
        $token = $this->token($manager, [$branch->id], ['sales']);
        $connection = McpConnection::where('user_id', $manager->id)->sole();

        $success = $this->callTool($token, 'oqari_get_context', modern: true)->assertOk();
        $traceId = $success->headers->get('X-OQARI-Trace-Id');
        $this->assertNotEmpty($traceId);
        $this->assertSame($traceId, $success->json('result._meta')['com.oqari/traceId']);
        $record = DB::table('mcp_tool_calls')->where('request_id', $traceId)->sole();
        $this->assertSame($connection->id, $record->mcp_connection_id);
        $this->assertSame('oqari_get_context', $record->tool);
        $this->assertSame('success', $record->outcome);
        $this->assertSame(200, $record->http_status);
        $this->assertGreaterThanOrEqual(0, $record->duration_ms);

        $denial = $this->callTool($token, 'oqari_get_operations_summary')->assertJsonStructure(['error']);
        $this->assertDatabaseHas('mcp_tool_calls', [
            'request_id' => $denial->headers->get('X-OQARI-Trace-Id'), 'mcp_connection_id' => $connection->id,
            'tool' => 'oqari_get_operations_summary', 'outcome' => 'denied', 'error_code' => 'tool_unavailable',
        ]);

        $invalid = $this->callTool($token, 'oqari_get_context', ['password' => 'test-secret'])->assertJsonPath('result.isError', true);
        $this->assertDatabaseHas('mcp_tool_calls', [
            'request_id' => $invalid->headers->get('X-OQARI-Trace-Id'),
            'outcome' => 'invalid_input', 'error_code' => 'validation_failed',
        ]);

        Log::spy();
        $unauthorized = $this->rpc('test-unknown-token', 'tools/list')->assertUnauthorized();
        $anonymousTrace = $unauthorized->headers->get('X-OQARI-Trace-Id');
        Log::shouldHaveReceived('notice')->once()->with('OQARI MCP authentication refused', \Mockery::on(
            fn (array $context): bool => $context['trace_id'] === $anonymousTrace
                && array_keys($context) === ['trace_id', 'http_status', 'error_code', 'duration_ms'],
        ));
        $this->assertDatabaseMissing('mcp_tool_calls', ['request_id' => $anonymousTrace]);
    }

    private function assertOutputMatchesContract(array $schema, mixed $value, string $path): void
    {
        $type = match (true) {
            $value === null => 'null',
            $value instanceof \stdClass => 'object',
            is_array($value) => 'array',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_bool($value) => 'boolean',
            default => 'string',
        };
        $declared = (array) $schema['type'];
        $this->assertTrue(in_array($type, $declared, true) || ($type === 'integer' && in_array('number', $declared, true)), $path);

        if (isset($schema['enum'])) {
            $this->assertContains($value, $schema['enum'], $path);
        }
        if (isset($schema['minimum']) && $value !== null) {
            $this->assertGreaterThanOrEqual($schema['minimum'], $value, $path);
        }
        if (isset($schema['pattern'])) {
            $this->assertMatchesRegularExpression('~'.$schema['pattern'].'~D', $value, $path);
        }

        if ($type === 'object') {
            $properties = $schema['properties'] ?? [];
            foreach ($schema['required'] ?? [] as $key) {
                $this->assertTrue(property_exists($value, $key), "$path.$key is required");
            }
            foreach (get_object_vars($value) as $key => $child) {
                if (! isset($properties[$key])) {
                    $this->assertNotFalse($schema['additionalProperties'] ?? true, "$path.$key is undeclared");

                    continue;
                }
                $this->assertOutputMatchesContract($properties[$key], $child, "$path.$key");
            }
        } elseif ($type === 'array') {
            foreach ($value as $index => $child) {
                $this->assertOutputMatchesContract($schema['items'], $child, "$path.$index");
            }
        }
    }

    private function rpc(string $token, string $method, array $params = [], bool $modern = false): TestResponse
    {
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => $modern ? '2026-07-28' : '2025-11-25'];
        if ($modern) {
            $headers['Mcp-Method'] = $method;
            $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []];
            if (isset($params['name'])) {
                $headers['Mcp-Name'] = $params['name'];
            }
        }

        return $this->postJson('/mcp/oqari', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], $headers);
    }

    public function test_owner_can_reveal_active_key_but_not_after_revoke_or_by_other_user(): void
    {
        [$owner, $pramuka] = $this->business();
        $otherShop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $otherOwner = User::factory()->create(['shop_id' => $otherShop->id, 'role' => 'owner']);

        $this->actingAs($owner)->post(route('admin.integrations.mcp.store'), [
            'name' => 'Aplikasi lain', 'branch_ids' => [$pramuka->id], 'abilities' => ['sales'],
        ])->assertRedirect(route('admin.integrations.mcp'));

        $connection = McpConnection::where('user_id', $owner->id)->sole();
        $token = $this->get(route('admin.integrations.mcp'))->viewData('newToken');

        $this->get(route('admin.integrations.mcp', ['tab' => 'tokens']))
            ->assertOk()
            ->assertSee('Lihat')
            ->assertSee('Salin')
            ->assertDontSee($token);

        $this->postJson(route('admin.integrations.mcp.reveal', $connection->id))
            ->assertOk()
            ->assertJson(['token' => $token]);

        $this->actingAs($otherOwner)
            ->postJson(route('admin.integrations.mcp.reveal', $connection->id))
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('admin.integrations.mcp.destroy', $connection->id))
            ->assertRedirect();

        $this->postJson(route('admin.integrations.mcp.reveal', $connection->id))
            ->assertNotFound();
    }

    public function test_catalog_and_analytics_tools_return_accurate_data(): void
    {
        [$owner, $branch] = $this->business();
        Product::factory()->create(['shop_id' => $owner->shop_id, 'name' => 'Burger Klasik', 'price' => 35000]);
        Product::factory()->create(['shop_id' => $owner->shop_id, 'name' => 'Es Teh', 'price' => 8000]);
        $today = CarbonImmutable::today(config('attendance.timezone'));
        $this->customerOrder($branch, $today, 35000, 'Burger Klasik', 1);

        $token = $this->token($owner, [$branch->id], ['sales']);

        $this->callTool($token, 'oqari_list_products', ['search' => 'Burger'])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.total_products', 1)
            ->assertJsonPath('result.structuredContent.data.items.0.name', 'Burger Klasik');

        $this->callTool($token, 'oqari_list_products')
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.total_products', 2);

        $this->callTool($token, 'oqari_get_sales_breakdown', ['branch_id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.by_payment_method.0.payment_method', 'CASH')
            ->assertJsonPath('result.structuredContent.data.by_payment_method.0.paid_orders', 1);

        $this->callTool($token, 'oqari_list_orders', ['branch_id' => $branch->id, 'payment_status' => 'PAID'])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.items.0.grand_total', '35000.00');
    }

    public function test_chatgpt_and_gemini_setup_tutorials_render_cleanly(): void
    {
        [$owner] = $this->business();

        $this->actingAs($owner)
            ->get(route('admin.integrations.mcp', ['client' => 'chatgpt']))
            ->assertOk()
            ->assertSee('Hubungkan ChatGPT')
            ->assertSee('Dukungan Paket ChatGPT untuk Custom MCP')
            ->assertSee('Add custom MCP server');

        $this->actingAs($owner)
            ->get(route('admin.integrations.mcp', ['client' => 'gemini']))
            ->assertOk()
            ->assertSee('Hubungkan Gemini')
            ->assertSee('Cara pasang di Gemini Web (gemini.google.com)')
            ->assertSee('Add a custom app');
    }

    private function callTool(string $token, string $name, array $arguments = [], bool $modern = false): TestResponse
    {
        return $this->rpc($token, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments], $modern);
    }

    private function business(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);

        return [$owner, $branches->enable($shop, 'Outlet Pramuka'), $branches->create($shop, 'Outlet Lain')];
    }

    private function token(User $actor, ?array $branches, array $abilities): string
    {
        $token = 'oqm_'.bin2hex(random_bytes(32));
        McpConnection::factory()->create([
            'user_id' => $actor->id, 'shop_id' => $actor->shop_id, 'role' => $actor->role,
            'branch_ids' => $branches, 'abilities' => $abilities, 'token_hash' => hash('sha256', $token),
            'account_version' => (int) $actor->account_version,
        ]);

        return $token;
    }

    private function customerOrder(Branch $branch, CarbonImmutable $date, int $total, string $product, int $units, string $payment = 'PAID'): void
    {
        $order = OrderFactory::new()->create([
            'shop_id' => $branch->shop_id, 'branch_id' => $branch->id, 'created_at' => $date->utc(),
            'subtotal' => $total, 'discount_amount' => 0, 'grand_total' => $total, 'total_cogs' => 20000,
            'payment_status' => $payment, 'order_status' => 'COMPLETED', 'payment_method' => 'CASH',
        ]);
        $order->items()->create(['product_name' => $product, 'quantity' => $units, 'status' => 'new',
            'unit_price' => 10000, 'subtotal' => $units * 10000]);
    }
}
