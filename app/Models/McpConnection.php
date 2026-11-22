<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpConnection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shop_id', 'user_id', 'name', 'role', 'oauth_client_id', 'token_hash', 'token_suffix',
        'abilities', 'branch_ids', 'account_version', 'expires_at', 'revoked_at', 'last_used_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'branch_ids' => 'array',
            'account_version' => 'integer',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function usableBy(User $user, string $role): bool
    {
        return (int) $this->user_id === (int) $user->id
            && $this->shop_id === $user->shop_id
            && (! $user->shop?->branches_enabled || $this->branch_ids !== null)
            && $this->role === $role
            && $this->account_version === (int) $user->account_version
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
