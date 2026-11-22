<?php

namespace App\Providers;

use App\Mcp\McpOAuthAccessToken;
use App\Mcp\McpOAuthResource;
use App\Models\McpConnection;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\McpAccess;
use App\Services\SchemaCompatibility;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SchemaCompatibility::class);
        $this->app->scoped(BranchContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! is_array(config('database_schema.groups'))) {
            config(['database_schema' => require config_path('database_schema.php')]);
        }
        if (! is_array(config('access.permissions'))) {
            config(['access' => require config_path('access.php')]);
        }
        if (! is_array(config('admin_pages'))) {
            config(['admin_pages' => require config_path('admin_pages.php')]);
        }

        Auth::viaRequest('oqari-mcp-request', fn (Request $request): ?User => $request->attributes->get(McpConnection::class)?->user);
        Passport::useAccessTokenEntity(McpOAuthAccessToken::class);
        Passport::encryptTokensUsing(McpOAuthResource::grantKey(...));
        Passport::tokensCan(['mcp:use' => 'Baca data OQARI melalui MCP']);
        Passport::authorizationView(function (array $data) {
            abort_unless(app(McpAccess::class)->role($data['user']), 403);

            return response()->view('Admin.Integrations.mcp-authorize', $data)
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Frame-Options', 'DENY');
        });
        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addDays(14));

        URL::formatPathUsing(function (string $path, ?Route $route): string {
            if ($route === null || str_starts_with($path, '/admin/branches') || $route->named('admin.owner.dashboard', 'admin.integrations.mcp*') ||
                (! str_starts_with($path, '/admin/') && ! str_starts_with($path, '/shift/'))) {
                return $path;
            }

            return app(BranchContext::class)->prefix().$path;
        });

        Gate::before(function (User $user): ?bool {
            if (! $user->isAccountActive()) {
                return false;
            }

            return $user->role === 'superadmin' ? true : null;
        });
        foreach (array_keys(config('access.permissions')) as $permission) {
            Gate::define($permission, fn (User $user): bool => in_array($permission, $user->effectivePermissions(), true));
        }
        Gate::define('manage-branch-tables', fn (User $user): bool => $user->can('manage-settings') || in_array('manage-branch-tables', $user->effectivePermissions(), true));
        Gate::define('manage-inventory-master', fn (User $user): bool => $user->can('manage-inventory') &&
            (app(BranchContext::class)->roleFor($user) === null || $user->role === 'owner'));
        Gate::define('manage-vouchers', fn (User $user): bool => $user->role === 'owner');
        Gate::define('view-vouchers', fn (User $user): bool => $user->role === 'owner'
            || $user->can('access-pos')
            || $user->can('view-orders')
            || $user->can('manage-settings')
            || $user->can('view-reports'));
        Gate::define('view-dashboard', fn (User $user): bool => count(array_intersect(array_merge(config('access.dashboard'), ['view-orders']), $user->effectivePermissions())) > 0);
    }
}
