<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BranchStorefrontResolver
{
    /**
     * All storefront, branding, contact, receipt, and operational keys
     * that a branch can override independently while falling back to Shop defaults.
     *
     * @var list<string>
     */
    public const OVERRIDABLE_KEYS = [
        'name',
        'slogan',
        'logo_path',
        'banner_paths',
        'is_banner_active',
        'primary_color',
        'theme_style',
        'font_family',
        'title_font_family',
        'background_type',
        'background_pattern',
        'background_color',
        'background_overlay_opacity',
        'background_image_path',
        'is_open',
        'operating_hours',
        'whatsapp_number',
        'address',
        'maps_link',
        'instagram_link',
        'tiktok_link',
        'social_settings',
        'receipt_header',
        'receipt_footer',
        'receipt_settings',
        'printer_settings',
    ];

    /**
     * Return a non-persistent Shop instance with branch overrides applied on top of Owner defaults.
     */
    public function apply(Shop $shop, ?Branch $branch): Shop
    {
        if (! $branch || (int) $branch->shop_id !== (int) $shop->id) {
            return $shop;
        }

        $overrides = is_array($branch->settings) ? $branch->settings : [];
        if ($overrides === []) {
            return $shop;
        }

        $resolved = clone $shop;
        foreach (self::OVERRIDABLE_KEYS as $key) {
            if (array_key_exists($key, $overrides) && $overrides[$key] !== null) {
                $resolved->setAttribute($key, $overrides[$key]);
            }
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    public function overriddenKeys(?Branch $branch): array
    {
        if (! $branch || ! is_array($branch->settings)) {
            return [];
        }

        return array_values(array_intersect(
            self::OVERRIDABLE_KEYS,
            array_keys(array_filter($branch->settings, fn ($value): bool => $value !== null)),
        ));
    }

    /**
     * Persist branch-level overrides. Scalar/JSON values matching the Owner's Shop default
     * are automatically cleared from overrides so the branch keeps inheriting future Owner updates.
     */
    public function updateFromRequest(Shop $shop, Branch $branch, Request $request): Branch
    {
        $overrides = is_array($branch->settings) ? $branch->settings : [];

        $scalarMap = [
            'name' => fn () => $request->input('name'),
            'theme_style' => fn () => $request->input('theme_style'),
            'is_open' => fn () => $request->boolean('is_open'),
            'slogan' => fn () => $request->input('slogan'),
            'font_family' => fn () => $request->input('font_family'),
            'title_font_family' => fn () => $request->input('title_font_family'),
            'receipt_header' => fn () => $request->input('receipt_header'),
            'receipt_footer' => fn () => $request->input('receipt_footer'),
            'instagram_link' => fn () => $request->input('instagram_link'),
            'tiktok_link' => fn () => $request->input('tiktok_link'),
            'whatsapp_number' => fn () => $request->input('whatsapp_number'),
            'address' => fn () => $request->input('address'),
            'maps_link' => fn () => $request->input('maps_link'),
            'is_banner_active' => fn () => $request->boolean('is_banner_active'),
            'background_type' => fn () => $request->input('background_type'),
            'background_pattern' => fn () => $request->input('background_pattern'),
            'background_color' => fn () => $request->input('background_color'),
            'background_overlay_opacity' => fn () => (int) $request->input('background_overlay_opacity'),
        ];

        foreach ($scalarMap as $key => $resolver) {
            if (! $request->has($key)) {
                continue;
            }
            $value = $resolver();
            $this->assignOrInherit($overrides, $key, $value, $shop->getAttribute($key));
        }

        if ($request->filled('primary_color')) {
            $this->assignOrInherit($overrides, 'primary_color', $request->input('primary_color'), $shop->primary_color);
        }

        foreach (['receipt_settings', 'printer_settings', 'social_settings', 'operating_hours'] as $jsonKey) {
            if (! $request->has($jsonKey)) {
                continue;
            }
            $raw = $request->input($jsonKey);
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            $this->assignOrInherit($overrides, $jsonKey, $decoded, $shop->getAttribute($jsonKey));
        }

        if ($request->hasFile('logo')) {
            $this->deleteBranchOwnedMedia($shop->id, $branch->id, $overrides['logo_path'] ?? null);
            $overrides['logo_path'] = $this->storeBranchMedia($request->file('logo'), $shop->id, $branch->id, 'logo');
        }

        if ($request->boolean('remove_background_image')) {
            $this->deleteBranchOwnedMedia($shop->id, $branch->id, $overrides['background_image_path'] ?? null);
            $overrides['background_image_path'] = null;
            if (($overrides['background_type'] ?? $shop->background_type) === 'image') {
                $this->assignOrInherit($overrides, 'background_type', 'pattern', $shop->background_type);
            }
        } elseif ($request->hasFile('background_image')) {
            $this->deleteBranchOwnedMedia($shop->id, $branch->id, $overrides['background_image_path'] ?? null);
            $overrides['background_image_path'] = $this->storeBranchMedia($request->file('background_image'), $shop->id, $branch->id, 'background');
            $this->assignOrInherit($overrides, 'background_type', 'image', $shop->background_type);
        }

        // Banners: only override if a new banner is uploaded or the banner list differs from Shop defaults
        $hasBannerUpload = false;
        $banners = [];
        $existingBranchBanners = is_array($overrides['banner_paths'] ?? null) ? $overrides['banner_paths'] : [];
        for ($i = 0; $i < 5; $i++) {
            if ($request->hasFile("banner_{$i}")) {
                $hasBannerUpload = true;
                $banners[] = $this->storeBranchMedia($request->file("banner_{$i}"), $shop->id, $branch->id, "banner_{$i}");
            } elseif ($request->filled("existing_banner_{$i}")) {
                $clean = $this->normalizeMediaPath((string) $request->input("existing_banner_{$i}"));
                if ($clean) {
                    $banners[] = $clean;
                }
            }
        }

        foreach ($existingBranchBanners as $oldBanner) {
            $cleanOld = $this->normalizeMediaPath((string) $oldBanner);
            if ($cleanOld && ! in_array($cleanOld, $banners, true)) {
                $this->deleteBranchOwnedMedia($shop->id, $branch->id, $cleanOld);
            }
        }

        if ($hasBannerUpload || $banners !== ($shop->banner_paths ?? [])) {
            $overrides['banner_paths'] = $banners;
        } else {
            unset($overrides['banner_paths']);
        }

        $branch->settings = $overrides === [] ? null : $overrides;
        $branch->save();

        return $branch->fresh();
    }

    public function updateOperatingHours(Shop $shop, Branch $branch, array $operatingHours): Branch
    {
        $overrides = is_array($branch->settings) ? $branch->settings : [];
        $this->assignOrInherit($overrides, 'operating_hours', $operatingHours, $shop->operating_hours);
        $branch->settings = $overrides === [] ? null : $overrides;
        $branch->save();

        return $branch->fresh();
    }

    public function reset(Branch $branch): Branch
    {
        $overrides = is_array($branch->settings) ? $branch->settings : [];
        $this->deleteBranchOwnedMedia($branch->shop_id, $branch->id, $overrides['logo_path'] ?? null);
        $this->deleteBranchOwnedMedia($branch->shop_id, $branch->id, $overrides['background_image_path'] ?? null);
        foreach ((array) ($overrides['banner_paths'] ?? []) as $banner) {
            $this->deleteBranchOwnedMedia($branch->shop_id, $branch->id, is_string($banner) ? $banner : null);
        }

        $branch->settings = null;
        $branch->save();

        return $branch->fresh();
    }

    private function assignOrInherit(array &$overrides, string $key, mixed $value, mixed $shopDefault): void
    {
        if ($value === $shopDefault || ($value === '' && ($shopDefault === null || $shopDefault === ''))) {
            unset($overrides[$key]);

            return;
        }

        $overrides[$key] = $value;
    }

    private function storeBranchMedia(UploadedFile $file, int $shopId, int $branchId, string $prefix): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = $prefix.'_'.Str::random(12).'.'.$extension;
        $directory = "shops/{$shopId}/branches/{$branchId}";

        return $file->storeAs($directory, $filename, 'public');
    }

    private function deleteBranchOwnedMedia(int $shopId, int $branchId, ?string $path): void
    {
        if (! $path) {
            return;
        }
        $prefix = "shops/{$shopId}/branches/{$branchId}/";
        if (str_starts_with($path, $prefix)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function normalizeMediaPath(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }
        if (str_contains($trimmed, '/storage/')) {
            $trimmed = Str::after($trimmed, '/storage/');
        }

        return ltrim($trimmed, '/');
    }
}
