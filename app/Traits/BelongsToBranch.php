<?php

namespace App\Traits;

use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            if ($branchId = app(BranchContext::class)->id()) {
                $query->where($query->qualifyColumn('branch_id'), $branchId);
            }
        });

        static::creating(function ($record): void {
            $branchId = app(BranchContext::class)->id((int) ($record->shop_id ?? auth()->user()?->shop_id));
            if ($branchId) {
                abort_if($record->branch_id && (int) $record->branch_id !== $branchId, 404);
                $record->branch_id = $branchId;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
