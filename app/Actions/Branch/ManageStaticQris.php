<?php

namespace App\Actions\Branch;

use App\Models\Branch;
use App\Models\Order;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManageStaticQris
{
    public function __construct(private MediaService $media) {}

    public function execute(Branch $branch, ?UploadedFile $image): void
    {
        $newPath = $image ? $this->media->storeStaticQris($image, $branch->shop_id, $branch->id) : null;

        try {
            $previousPath = DB::transaction(function () use ($branch, $newPath): ?string {
                $lockedBranch = Branch::whereKey($branch->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
                $previousPath = $lockedBranch->static_qris_path;
                $lockedBranch->forceFill(['static_qris_path' => $newPath])->save();

                return $previousPath;
            }, 3);
        } catch (Throwable $exception) {
            if ($newPath) {
                rescue(fn () => $this->media->delete($newPath));
            }

            throw $exception;
        }

        if ($previousPath && ! Order::withoutGlobalScopes()->where('branch_id', $branch->id)->where('payment_qr_path', $previousPath)->exists()) {
            rescue(fn () => $this->media->delete($previousPath));
        }
    }
}
