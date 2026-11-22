<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mcp\McpOAuthResource;
use App\Models\McpConnection;
use App\Models\User;
use App\Services\McpAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class McpConnectionController extends Controller
{
    public function __construct(private McpAccess $access) {}

    public function index(Request $request): Response
    {
        [$actor, $role] = $this->actor($request);
        $abilities = $this->access->abilities($actor, $role);
        $branches = $this->access->branches($actor);
        $clients = config('oqari_mcp.clients');
        $requestedClient = $request->query('client');
        $client = is_string($requestedClient) && isset($clients[$requestedClient]) ? $requestedClient : 'codex';
        $tab = match ($request->query('tab')) {
            'connected' => 'connected',
            'tokens' => 'tokens',
            default => 'setup',
        };
        $connections = match ($tab) {
            'connected' => $this->connections($actor)->whereNotNull('last_used_at')->orderByDesc('last_used_at')->paginate(15)->withQueryString(),
            'tokens' => $this->connections($actor)->whereNotNull('token_suffix')->latest()->paginate(15)->withQueryString(),
            default => null,
        };
        $encryptedToken = $request->session()->pull('oqari_mcp_new_token');
        $newToken = $encryptedToken ? Crypt::decryptString($encryptedToken) : null;
        $endpoint = McpOAuthResource::url();
        $hasBranches = (bool) $actor->shop?->branches_enabled;

        return response()->view('Admin.Integrations.mcp', compact('role', 'branches', 'hasBranches', 'abilities', 'clients', 'client', 'tab', 'connections', 'newToken', 'endpoint'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        [$actor, $role] = $this->actor($request);
        $clients = config('oqari_mcp.clients');
        $options = $request->validate([
            'mode' => ['sometimes', 'string', Rule::in(['quick', 'custom'])],
            'client' => ['required_if:mode,quick', 'string', Rule::in(array_keys($clients))],
        ]);
        $allowedBranches = $this->access->branches($actor)->modelKeys();
        $allowedAbilities = $this->access->abilities($actor, $role);
        $hasBranches = (bool) $actor->shop?->branches_enabled;

        if (($options['mode'] ?? null) === 'quick') {
            if ($allowedAbilities === [] || ($hasBranches && $allowedBranches === [])) {
                throw ValidationException::withMessages(['mode' => 'Akun ini belum memiliki cakupan data untuk disambungkan.']);
            }
            $data = [
                'name' => $clients[$options['client']]['label'],
                'abilities' => array_slice($allowedAbilities, 0, 1),
                'branch_ids' => $allowedBranches,
            ];
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'abilities' => ['required', 'array', 'list', 'max:4'],
                'abilities.*' => ['required', 'string', 'distinct', Rule::in($allowedAbilities)],
                'branch_ids' => [Rule::requiredIf($hasBranches), 'array', 'list'],
                'branch_ids.*' => ['required', 'integer', 'distinct', Rule::in($allowedBranches)],
            ]);
        }
        $token = 'oqm_'.bin2hex(random_bytes(32));

        DB::transaction(function () use ($actor, $role, $data, $token): void {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $activeCount = $this->connections($actor)->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
            if ($activeCount >= config('oqari_mcp.max_connections')) {
                throw ValidationException::withMessages(['name' => 'Batas koneksi aktif tercapai. Cabut koneksi yang sudah tidak dipakai.']);
            }

            McpConnection::create([
                'user_id' => $actor->id, 'shop_id' => $actor->shop_id, 'role' => $role, 'name' => $data['name'],
                'abilities' => $data['abilities'], 'branch_ids' => $actor->shop?->branches_enabled ? array_map('intval', $data['branch_ids']) : null,
                'token_hash' => hash('sha256', $token), 'token_suffix' => substr($token, -4),
                'account_version' => $actor->account_version ?? 0,
                'expires_at' => now()->addDays(config('oqari_mcp.token_days')),
            ]);
        });

        $query = isset($options['client']) ? ['client' => $options['client']] : [];

        return redirect()->route('admin.integrations.mcp', $query)->with('oqari_mcp_new_token', Crypt::encryptString($token));
    }

    public function destroy(Request $request, string $connection): RedirectResponse
    {
        [$actor] = $this->actor($request);
        $connection = $this->connections($actor)->whereKey($connection)->firstOrFail();
        $connection->update(['revoked_at' => now(), 'token_hash' => null]);

        $tab = $request->input('tab') === 'connected' ? 'connected' : 'tokens';

        return redirect()->route('admin.integrations.mcp', ['tab' => $tab])->with('success', 'Koneksi MCP dicabut.');
    }

    private function connections(User $actor): Builder
    {
        return McpConnection::where('user_id', $actor->id)->where('shop_id', $actor->shop_id);
    }

    /** @return array{User, string} */
    private function actor(Request $request): array
    {
        abort_unless(config('oqari_mcp.enabled'), 404);
        $actor = $request->user();
        $role = $this->access->role($actor);
        abort_unless($role, 403);

        return [$actor, $role];
    }
}
