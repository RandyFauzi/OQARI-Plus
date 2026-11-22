<x-cafe-operation-page title="Central Kitchen" :shop="$shop">
    @php
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');
        $statusTone = fn (string $status): string => match ($status) {
            'POSTED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'REVIEW' => 'bg-sky-50 text-sky-700 border-sky-200',
            default => 'bg-amber-50 text-amber-700 border-amber-200',
        };
        $statusDot = fn (string $status): string => match ($status) {
            'POSTED' => 'bg-emerald-500',
            'REVIEW' => 'bg-sky-500',
            default => 'bg-amber-500',
        };
    @endphp

    @isset($order)
        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- DETAIL SURAT KERJA PRODUKSI                                --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-1.5">
                    <a href="{{ route('admin.production.index') }}"
                       class="inline-flex w-fit items-center gap-1.5 text-[12px] font-semibold text-[#53565a] transition hover:text-[#df5826]">
                        <i class="fas fa-arrow-left text-[11px]" aria-hidden="true"></i>
                        <span>Kembali ke Central Kitchen</span>
                    </a>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-[22px] font-bold tracking-tight text-[#111827] md:text-[24px]">
                            Surat kerja #{{ $order->id }}
                        </h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold {{ $statusTone($order->status) }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($order->status) }}"></span>
                            {{ $order->statusLabel() }}
                        </span>
                    </div>
                    <p class="text-[13px] text-[#53565a]">
                        <span class="font-semibold text-[#111827]">{{ $order->recipe->outputMaterial->name }}</span>
                        <span class="text-[#9ca3af]">·</span>
                        Resep versi {{ $order->recipe->version }}
                    </p>
                </div>

                @can('post-production')
                    @if ($order->status === \App\Models\ProductionWorkOrder::Review)
                        <form method="POST" action="{{ route('admin.production.post', $order) }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <input type="hidden" name="revision" value="{{ $order->result_revision }}">
                            <button type="submit" :disabled="submitting"
                                    class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="fas fa-check-double text-[12px]" aria-hidden="true"></i>
                                <span x-text="submitting ? 'Membukukan...' : 'Validasi dan bukukan'">Validasi dan bukukan</span>
                            </button>
                        </form>
                    @endif
                @endcan
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Target Produksi</span>
                    <div class="mt-2">
                        <span class="text-[20px] font-bold tabular-nums text-[#111827]">{{ $number($order->target_quantity) }} {{ $order->recipe->unit }}</span>
                        <p class="mt-0.5 text-[11px] text-[#7f837e]">{{ $order->items->count() }} bahan baku resep</p>
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Hasil Layak Pakai &amp; Waste</span>
                    <div class="mt-2">
                        @if ($order->good_quantity !== null)
                            <span class="text-[18px] font-bold tabular-nums text-emerald-600">{{ $number($order->good_quantity) }} {{ $order->recipe->unit }}</span>
                            <p class="mt-0.5 text-[11px] text-[#7f837e]">
                                Waste: <span class="font-semibold text-rose-600">{{ $number($order->waste_quantity) }} {{ $order->recipe->unit }}</span>
                                · Selisih: {{ $number((float) $order->good_quantity - (float) $order->target_quantity) }}
                            </p>
                        @else
                            <span class="text-[14px] font-semibold text-amber-600">Belum dicatat</span>
                            <p class="mt-0.5 text-[11px] text-[#7f837e]">Menunggu input hasil produksi</p>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Biaya &amp; HPP Batch</span>
                    <div class="mt-2">
                        @if ($order->total_cost !== null)
                            <span class="text-[18px] font-bold tabular-nums text-[#111827]">Rp{{ $number($order->total_cost) }}</span>
                            <p class="mt-0.5 text-[11px] text-[#7f837e]">
                                HPP/{{ $order->recipe->unit }}: {{ $order->output_unit_cost !== null ? 'Rp' . $number($order->output_unit_cost) : '—' }}
                            </p>
                        @else
                            <span class="text-[14px] font-semibold text-[#7f837e]">—</span>
                            <p class="mt-0.5 text-[11px] text-[#7f837e]">Dihitung saat pembukuan</p>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Riwayat Validasi</span>
                    <div class="mt-2 space-y-1 text-[11px] text-[#53565a]">
                        @if ($order->recorded_at)
                            <div>Dicatat: <span class="font-semibold text-[#111827]">{{ $order->recorder?->name ?? '—' }}</span> ({{ $order->recorded_at->format('d/m H:i') }})</div>
                        @else
                            <div>Dicatat: <span class="text-[#7f837e]">Belum dicatat</span></div>
                        @endif
                        @if ($order->posted_at)
                            <div>Dibukukan: <span class="font-semibold text-emerald-700">{{ $order->validator?->name ?? '—' }}</span> ({{ $order->posted_at->format('d/m H:i') }})</div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($order->notes || $order->waste_reason)
                <div class="grid gap-3 sm:grid-cols-2">
                    @if ($order->notes)
                        <div class="flex items-start gap-2.5 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] px-4 py-3 text-[13px] text-[#53565a]">
                            <i class="fas fa-note-sticky mt-0.5 text-[#df5826]" aria-hidden="true"></i>
                            <div>
                                <span class="font-semibold text-[#111827]">Catatan:</span>
                                <span>{{ $order->notes }}</span>
                            </div>
                        </div>
                    @endif
                    @if ($order->waste_reason)
                        <div class="flex items-start gap-2.5 rounded-[12px] border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] text-rose-800">
                            <i class="fas fa-triangle-exclamation mt-0.5 text-rose-600" aria-hidden="true"></i>
                            <div>
                                <span class="font-semibold">Alasan waste:</span>
                                <span>{{ $order->waste_reason }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Tabel Bahan Produksi --}}
            <section class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]" aria-labelledby="batch-ingredients">
                <div class="border-b border-[#e9ebf0] px-5 py-3.5">
                    <h2 id="batch-ingredients" class="text-[14px] font-bold text-[#111827]">Bahan Produksi</h2>
                    <p class="text-[12px] text-[#7f837e]">Perbandingan takaran rencana, pemakaian aktual, dan saldo Central Kitchen saat ini.</p>
                </div>
                <div class="overflow-x-auto" role="region" aria-labelledby="batch-ingredients" tabindex="0">
                    <table class="w-full min-w-[540px] text-left text-[13px]">
                        <thead class="border-b border-[#e9ebf0] bg-[#f8f9fa] text-[11px] font-semibold uppercase tracking-wider text-[#53565a]">
                            <tr>
                                <th scope="col" class="px-5 py-3">Bahan</th>
                                <th scope="col" class="px-4 py-3 text-right">Rencana</th>
                                <th scope="col" class="px-4 py-3 text-right">Aktual</th>
                                <th scope="col" class="px-5 py-3 text-right">Saldo CK Saat Ini</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f1f3f5]">
                            @foreach ($order->items as $item)
                                @php $ckBalance = (float) ($balances[$item->raw_material_id] ?? 0); @endphp
                                <tr class="transition hover:bg-[#fafaf9]">
                                    <th scope="row" class="px-5 py-3.5 font-semibold text-[#111827]">
                                        {{ $item->rawMaterial->name }}
                                        <span class="ml-1 rounded-md bg-[#f1f3f5] px-1.5 py-0.5 text-[11px] font-medium text-[#53565a]">{{ $item->unit }}</span>
                                    </th>
                                    <td class="px-4 py-3.5 text-right tabular-nums font-medium text-[#111827]">{{ $number($item->planned_quantity) }}</td>
                                    <td class="px-4 py-3.5 text-right tabular-nums text-[#53565a]">{{ $item->actual_quantity === null ? '—' : $number($item->actual_quantity) }}</td>
                                    <td class="px-5 py-3.5 text-right tabular-nums">
                                        <span class="{{ ($order->status !== \App\Models\ProductionWorkOrder::Posted && $ckBalance < (float) $item->planned_quantity) ? 'font-semibold text-rose-600' : 'font-medium text-[#111827]' }}">
                                            {{ $number($ckBalance) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Form Catat Hasil Produksi --}}
            @can('manage-production-orders')
                @if ($order->status !== \App\Models\ProductionWorkOrder::Posted)
                    <form method="POST" action="{{ route('admin.production.result', $order) }}"
                          x-data="{ submitting: false }" @submit="submitting = true"
                          class="space-y-4 rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $order->result_revision }}">
                        <div class="border-b border-[#e9ebf0] pb-3.5">
                            <h2 class="text-[15px] font-bold text-[#111827]">Catat Hasil Produksi</h2>
                            <p class="text-[12px] text-[#53565a]">Masukkan jumlah hasil layak pakai, waste (jika ada), serta pemakaian bahan aktual.</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-1.5">
                                <label for="good-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Hasil layak pakai ({{ $order->recipe->unit }}) <span class="text-rose-500">*</span>
                                </label>
                                <input id="good-qty-input" name="good_quantity" type="number" min="0" step="0.000001"
                                       value="{{ old('good_quantity', $order->good_quantity) }}"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                            </div>
                            <div class="space-y-1.5">
                                <label for="waste-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Waste ({{ $order->recipe->unit }}) <span class="text-rose-500">*</span>
                                </label>
                                <input id="waste-qty-input" name="waste_quantity" type="number" min="0" step="0.000001"
                                       value="{{ old('waste_quantity', $order->waste_quantity ?? 0) }}"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                            </div>
                            <div class="space-y-1.5">
                                <label for="waste-reason-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Alasan waste
                                </label>
                                <input id="waste-reason-input" name="waste_reason" maxlength="250"
                                       value="{{ old('waste_reason', $order->waste_reason) }}"
                                       placeholder="Wajib diisi jika waste > 0"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                            </div>
                        </div>

                        <fieldset class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                            <legend class="px-1 text-[12px] font-bold text-[#111827]">Pemakaian Aktual Bahan Baku</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($order->items as $item)
                                    <input type="hidden" name="items[{{ $item->id }}][work_order_item_id]" value="{{ $item->id }}">
                                    <label class="space-y-1 text-[12px] font-semibold text-[#111827]">
                                        <span>{{ $item->rawMaterial->name }} <span class="text-[#7f837e]">({{ $item->unit }})</span></span>
                                        <input name="items[{{ $item->id }}][actual_quantity]" type="number" min="0" step="0.000001"
                                               value="{{ old('items.'.$item->id.'.actual_quantity', $item->actual_quantity ?? $item->planned_quantity) }}"
                                               class="h-[38px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="submitting"
                                    class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                                <span x-text="submitting ? 'Menyimpan...' : 'Simpan hasil untuk validasi'">Simpan hasil untuk validasi</span>
                            </button>
                        </div>
                    </form>
                @endif
            @endcan

            @can('post-production')
                @if ($order->status === \App\Models\ProductionWorkOrder::Review)
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-[14px] border border-sky-200 bg-sky-50/70 p-4">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                                <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h2 class="text-[14px] font-bold text-[#111827]">Siap Divalidasi &amp; Dibukukan</h2>
                                <p class="text-[12px] text-[#53565a]">Setelah dibukukan, bahan aktual keluar dan hasil layak pakai masuk ke saldo Central Kitchen.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.production.post', $order) }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <input type="hidden" name="revision" value="{{ $order->result_revision }}">
                            <button type="submit" :disabled="submitting"
                                    class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="fas fa-check-double text-[12px]" aria-hidden="true"></i>
                                <span x-text="submitting ? 'Membukukan...' : 'Validasi dan bukukan'">Validasi dan bukukan</span>
                            </button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    @else
        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- HALAMAN UTAMA CENTRAL KITCHEN (INDEX)                      --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        @php
            $previousRows = collect(old('items'))->filter(fn ($item) => is_array($item) && array_key_exists('raw_material_id', $item))->values();
            $recipeRows = $previousRows->isNotEmpty() ? $previousRows->all() : [['raw_material_id' => '', 'quantity' => '']];
            $hasWorkOrderErrors = $errors->has('production_recipe_id') || $errors->has('target_quantity') || old('production_recipe_id') !== null;
            $hasRecipeErrors = ($errors->has('output_material_id') || $errors->has('yield_quantity') || $errors->has('items') || $errors->has('items.*') || old('output_material_id') !== null || $previousRows->isNotEmpty()) && ! $hasWorkOrderErrors;

            $recipePayload = $recipes->map(fn ($r) => [
                'id' => (string) $r->id,
                'name' => $r->outputMaterial->name,
                'version' => $r->version,
                'unit' => $r->unit,
                'yield_quantity' => $number($r->yield_quantity),
            ])->values();

            $materialPayload = $materials->map(fn ($m) => [
                'id' => (string) $m->id,
                'name' => $m->name,
                'unit' => $m->unit,
            ])->values();

            $pageOrders = $orders->getCollection();
            $activeWorkOrdersCount = $pageOrders->whereIn('status', [\App\Models\ProductionWorkOrder::Planned, \App\Models\ProductionWorkOrder::Review])->count();
            $reviewCount = $pageOrders->where('status', \App\Models\ProductionWorkOrder::Review)->count();
        @endphp

        <div x-data="{
            activeTab: @js($hasRecipeErrors ? 'recipes' : 'orders'),
            woFilterStatus: 'all',
            woSearch: '',
            recipeSearch: '',
            workOrderModal: @js($hasWorkOrderErrors),
            recipeModal: @js($hasRecipeErrors),
            recipes: @js($recipePayload),
            materials: @js($materialPayload),

            // Work Order modal state
            selectedRecipeId: @js((string) old('production_recipe_id', '')),
            recipeSelectOpen: false,
            recipeSelectSearch: '',
            woSubmitting: false,
            woClientError: '',

            // Recipe modal state
            outputMaterialId: @js((string) old('output_material_id', '')),
            outputSelectOpen: false,
            outputSelectSearch: '',
            recipeSubmitting: false,
            recipeClientError: '',
            rows: @js($recipeRows).map((item, index) => ({
                key: index,
                raw_material_id: item.raw_material_id ? String(item.raw_material_id) : '',
                quantity: item.quantity ?? '',
                open: false,
                search: '',
            })),
            nextKey: {{ count($recipeRows) }},

            filteredRecipesForSelect() {
                const q = this.recipeSelectSearch.trim().toLowerCase();
                if (!q) return this.recipes;
                return this.recipes.filter(r => r.name.toLowerCase().includes(q) || ('v' + r.version).includes(q));
            },
            selectedRecipe() {
                return this.recipes.find(r => r.id === String(this.selectedRecipeId)) || null;
            },
            selectRecipe(r) {
                this.selectedRecipeId = r.id;
                this.recipeSelectOpen = false;
                this.recipeSelectSearch = '';
                this.woClientError = '';
            },
            filteredMaterials(query) {
                const q = (query || '').trim().toLowerCase();
                if (!q) return this.materials;
                return this.materials.filter(m => m.name.toLowerCase().includes(q) || m.unit.toLowerCase().includes(q));
            },
            materialById(id) {
                return this.materials.find(m => m.id === String(id)) || null;
            },
            selectOutputMaterial(mat) {
                this.outputMaterialId = mat.id;
                this.outputSelectOpen = false;
                this.outputSelectSearch = '';
                this.recipeClientError = '';
            },
            selectRowMaterial(row, mat) {
                row.raw_material_id = mat.id;
                row.open = false;
                row.search = '';
                this.recipeClientError = '';
            },
            isMaterialUsedElsewhere(materialId, currentIndex) {
                return this.rows.some((r, idx) => idx !== currentIndex && r.raw_material_id === String(materialId));
            },
            addRow() {
                if (this.rows.length >= 50) return;
                this.rows.push({ key: this.nextKey++, raw_material_id: '', quantity: '', open: false, search: '' });
            },
            removeRow(index) {
                if (this.rows.length < 2) return;
                this.rows.splice(index, 1);
            },
            openWorkOrderModal(preselectedRecipeId = '') {
                if (preselectedRecipeId) {
                    this.selectedRecipeId = String(preselectedRecipeId);
                }
                this.woClientError = '';
                this.workOrderModal = true;
            },
            validateAndSubmitWorkOrder(event) {
                if (this.woSubmitting) {
                    event.preventDefault();
                    return;
                }
                if (!this.selectedRecipeId) {
                    event.preventDefault();
                    this.woClientError = 'Pilih versi resep terlebih dahulu.';
                    this.recipeSelectOpen = true;
                    return;
                }
                this.woClientError = '';
                this.woSubmitting = true;
            },
            validateAndSubmitRecipe(event) {
                if (this.recipeSubmitting) {
                    event.preventDefault();
                    return;
                }
                if (!this.outputMaterialId) {
                    event.preventDefault();
                    this.recipeClientError = 'Pilih bahan hasil produksi terlebih dahulu.';
                    this.outputSelectOpen = true;
                    return;
                }
                if (this.rows.some(r => !r.raw_material_id)) {
                    event.preventDefault();
                    this.recipeClientError = 'Pastikan semua baris bahan baku sudah dipilih.';
                    return;
                }
                this.recipeClientError = '';
                this.recipeSubmitting = true;
            },
            matchesWo(status, text) {
                const statusOk = this.woFilterStatus === 'all' || status === this.woFilterStatus;
                const q = this.woSearch.trim().toLowerCase();
                const searchOk = !q || text.toLowerCase().includes(q);
                return statusOk && searchOk;
            },
            matchesRecipe(text) {
                const q = this.recipeSearch.trim().toLowerCase();
                return !q || text.toLowerCase().includes(q);
            }
        }" @keydown.escape.window="workOrderModal = false; recipeModal = false; recipeSelectOpen = false; outputSelectOpen = false">

            {{-- Page Header --}}
            <div class="mb-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[20px] font-bold tracking-tight text-[#111827] md:text-[22px]">Central Kitchen</h1>
                    <p class="text-[13px] text-[#53565a]">Kelola surat kerja, resep berversi, dan hasil produksi.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    @can('manage-inventory')
                        <a href="{{ route('admin.inventory.locations.index') }}"
                           class="inline-flex h-[36px] items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[12px] font-semibold text-[#111827] shadow-[0px_1px_2px_rgba(20,21,20,0.03)] transition hover:bg-gray-50">
                            <img src="{{ asset('images/sparta/nav-stok.svg') }}" class="h-4 w-4" alt="">
                            <span>Saldo &amp; Transfer Bahan</span>
                        </a>
                    @endcan

                    @can('manage-production-recipes')
                        <button type="button" @click="recipeModal = true"
                                class="inline-flex h-[36px] cursor-pointer items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[12px] font-semibold text-[#111827] shadow-[0px_1px_2px_rgba(20,21,20,0.03)] transition hover:bg-gray-50">
                            <i class="fas fa-book-open text-[12px] text-[#53565a]" aria-hidden="true"></i>
                            <span>Tambah versi resep</span>
                        </button>
                    @endcan

                    @can('manage-production-orders')
                        <button type="button" @click="openWorkOrderModal()"
                                class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
                            <svg class="h-4 w-4 text-white" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 2a.75.75 0 01.75.75v4.5h4.5a.75.75 0 010 1.5h-4.5v4.5a.75.75 0 01-1.5 0v-4.5h-4.5a.75.75 0 010-1.5h4.5v-4.5A.75.75 0 018 2z" clip-rule="evenodd"/>
                            </svg>
                            <span>Buat surat kerja</span>
                        </button>
                    @endcan
                </div>
            </div>

            {{-- KPI Summary Cards --}}
            <div class="mb-5 grid grid-cols-1 gap-3.5 md:grid-cols-3">
                <div class="relative flex h-[112px] items-start justify-between overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-[#f9731f] p-4 text-white shadow-[0px_4px_14px_rgba(249,115,31,0.18)]">
                    <div class="pointer-events-none absolute -right-3 -top-2 flex h-[110px] w-[110px] items-center justify-center opacity-15">
                        <i class="fas fa-kitchen-set text-[84px]" aria-hidden="true"></i>
                    </div>
                    <div class="relative z-10 flex h-full flex-col justify-between">
                        <span class="text-[12px] font-medium leading-[15px] text-white/95">Surat Kerja Aktif</span>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[26px] font-bold leading-tight tabular-nums text-white">{{ $activeWorkOrdersCount }}</span>
                            <span class="text-[11px] leading-[14px] text-white/90">{{ $reviewCount }} menunggu validasi pembukuan</span>
                        </div>
                    </div>
                </div>

                <div class="flex h-[112px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-medium text-[#53565a]">Total Surat Kerja</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#fff6f0] text-[#df5826]">
                            <i class="fas fa-clipboard-list text-[13px]" aria-hidden="true"></i>
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $orders->total() }}</span>
                        <span class="text-[11px] leading-[14px] text-[#7f837e]">Seluruh batch produksi toko</span>
                    </div>
                </div>

                <div class="flex h-[112px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-medium text-[#53565a]">Versi Resep Produksi</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#ecfdf5] text-[#059669]">
                            <i class="fas fa-mortar-pestle text-[13px]" aria-hidden="true"></i>
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $recipes->count() }}</span>
                        <span class="text-[11px] leading-[14px] text-[#7f837e]">Formula produksi terstandarisasi</span>
                    </div>
                </div>
            </div>

            {{-- Sub-Tab Navigation --}}
            <div class="mb-4 flex items-center gap-6 border-b border-[#e9ebf0]">
                <button type="button" @click="activeTab = 'orders'"
                        :class="activeTab === 'orders' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                        class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                    <i class="fas fa-list-check text-[13px]" aria-hidden="true"></i>
                    <span>Surat Kerja Produksi</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                          :class="activeTab === 'orders' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                        {{ $orders->total() }}
                    </span>
                </button>

                <button type="button" @click="activeTab = 'recipes'"
                        :class="activeTab === 'recipes' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                        class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                    <i class="fas fa-book-open text-[13px]" aria-hidden="true"></i>
                    <span>Versi Resep Produksi</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                          :class="activeTab === 'recipes' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                        {{ $recipes->count() }}
                    </span>
                </button>
            </div>

            {{-- TAB 1: SURAT KERJA PRODUKSI --}}
            <section x-show="activeTab === 'orders'" aria-labelledby="production-orders" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" @click="woFilterStatus = 'all'"
                                :class="woFilterStatus === 'all' ? 'bg-[#fff6f0] text-[#df5826] border-[#ffdec9] font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Semua
                        </button>
                        <button type="button" @click="woFilterStatus = 'PLANNED'"
                                :class="woFilterStatus === 'PLANNED' ? 'bg-amber-50 text-amber-700 border-amber-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Direncanakan
                        </button>
                        <button type="button" @click="woFilterStatus = 'REVIEW'"
                                :class="woFilterStatus === 'REVIEW' ? 'bg-sky-50 text-sky-700 border-sky-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Menunggu Validasi
                        </button>
                        <button type="button" @click="woFilterStatus = 'POSTED'"
                                :class="woFilterStatus === 'POSTED' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Dibukukan
                        </button>
                    </div>

                    <div class="flex h-[36px] w-full items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3 shadow-2xs transition focus-within:border-[#df5826] sm:w-64">
                        <i class="fas fa-search text-[12px] text-gray-400" aria-hidden="true"></i>
                        <input type="text" x-model="woSearch" placeholder="Cari nomor batch atau produk..."
                               class="w-full border-none bg-transparent text-[12px] text-[#111827] placeholder-[#7f837e] outline-none">
                        <button type="button" x-show="woSearch.length > 0" @click="woSearch = ''" class="cursor-pointer text-xs text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <h2 id="production-orders" class="sr-only">Surat kerja produksi</h2>
                    <div class="overflow-x-auto" role="region" aria-labelledby="production-orders" tabindex="0">
                        <table class="w-full min-w-[600px] text-left text-[13px]">
                            <thead class="border-b border-[#e9ebf0] bg-[#f8f9fa] text-[11px] font-semibold uppercase tracking-wider text-[#53565a]">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Surat Kerja</th>
                                    <th scope="col" class="px-4 py-3">Hasil Produksi</th>
                                    <th scope="col" class="px-4 py-3 text-right">Target Hasil</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#f1f3f5]">
                                @forelse ($orders as $workOrder)
                                    <tr x-show="matchesWo(@js($workOrder->status), @js('#' . $workOrder->id . ' ' . $workOrder->recipe->outputMaterial->name))"
                                        class="transition hover:bg-[#fafaf9]">
                                        <th scope="row" class="px-5 py-3.5 font-semibold text-[#111827]">
                                            <a href="{{ route('admin.production.show', $workOrder) }}"
                                               class="inline-flex items-center gap-2 font-bold text-[#111827] transition hover:text-[#df5826]">
                                                <span class="rounded-md bg-[#fff6f0] px-2 py-0.5 text-[12px] font-bold text-[#df5826]">#{{ $workOrder->id }}</span>
                                                <span class="text-[11px] font-normal text-[#7f837e]">{{ $workOrder->created_at?->format('d M Y') }}</span>
                                            </a>
                                        </th>
                                        <td class="px-4 py-3.5">
                                            <div class="font-semibold text-[#111827]">{{ $workOrder->recipe->outputMaterial->name }}</div>
                                            <div class="text-[11px] text-[#7f837e]">Resep versi {{ $workOrder->recipe->version }}</div>
                                        </td>
                                        <td class="px-4 py-3.5 text-right tabular-nums font-medium text-[#111827]">
                                            {{ $number($workOrder->target_quantity) }} {{ $workOrder->recipe->unit }}
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $statusTone($workOrder->status) }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($workOrder->status) }}"></span>
                                                {{ $workOrder->statusLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            <a href="{{ route('admin.production.show', $workOrder) }}"
                                               class="inline-flex items-center gap-1.5 rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-1.5 text-[12px] font-semibold text-[#111827] transition hover:border-[#df5826] hover:text-[#df5826]">
                                                <span>Detail</span>
                                                <i class="fas fa-chevron-right text-[10px]" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center">
                                            <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#fff6f0] text-[#df5826]">
                                                    <i class="fas fa-kitchen-set text-lg" aria-hidden="true"></i>
                                                </div>
                                                <p class="text-[14px] font-bold text-[#111827]">Belum ada surat kerja</p>
                                                <p class="text-[12px] text-[#7f837e]">Buat surat kerja produksi berdasarkan versi resep yang tersedia.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($orders->hasPages())
                        <div class="border-t border-[#e9ebf0] px-5 py-3">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>
            </section>

            {{-- TAB 2: VERSI RESEP PRODUKSI --}}
            <section x-show="activeTab === 'recipes'" x-cloak aria-labelledby="recipes-heading" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="recipes-heading" class="text-[14px] font-bold text-[#111827]">Versi Resep Produksi</h2>
                        <p class="text-[12px] text-[#7f837e]">Setiap surat kerja mengunci versi resep yang dipilih saat pembuatan.</p>
                    </div>
                    <div class="flex h-[36px] w-full items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3 shadow-2xs transition focus-within:border-[#df5826] sm:w-64">
                        <i class="fas fa-search text-[12px] text-gray-400" aria-hidden="true"></i>
                        <input type="text" x-model="recipeSearch" placeholder="Cari resep atau bahan..."
                               class="w-full border-none bg-transparent text-[12px] text-[#111827] placeholder-[#7f837e] outline-none">
                    </div>
                </div>

                @if ($recipes->isEmpty())
                    <div class="rounded-[14px] border border-[#e9ebf0] bg-white p-10 text-center">
                        <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#fff6f0] text-[#df5826]">
                                <i class="fas fa-mortar-pestle text-lg" aria-hidden="true"></i>
                            </div>
                            <p class="text-[14px] font-bold text-[#111827]">Belum ada resep produksi</p>
                            <p class="text-[12px] text-[#7f837e]">Tambahkan versi resep pertama untuk mulai menjadwalkan produksi Central Kitchen.</p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach ($recipes as $recipe)
                            <article x-show="matchesRecipe(@js($recipe->outputMaterial->name . ' v' . $recipe->version . ' ' . ($recipe->notes ?? '')))"
                                     class="flex flex-col justify-between gap-3 rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] transition hover:border-[#ffd2b8]">
                                <div class="space-y-2.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-[14px] font-bold text-[#111827]">{{ $recipe->outputMaterial->name }}</h3>
                                                <span class="rounded-full bg-[#fff6f0] px-2 py-0.5 text-[11px] font-bold text-[#df5826]">v{{ $recipe->version }}</span>
                                            </div>
                                            <p class="mt-0.5 text-[12px] text-[#53565a]">
                                                Hasil standar: <span class="font-semibold text-[#111827]">{{ $number($recipe->yield_quantity) }} {{ $recipe->unit }}</span>
                                            </p>
                                        </div>
                                        @can('manage-production-orders')
                                            <button type="button" @click="openWorkOrderModal({{ $recipe->id }})"
                                                    class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 text-[11px] font-semibold text-[#111827] transition hover:border-[#df5826] hover:bg-[#fff6f0] hover:text-[#df5826]">
                                                <i class="fas fa-plus text-[10px]" aria-hidden="true"></i>
                                                <span>Buat Surat Kerja</span>
                                            </button>
                                        @endcan
                                    </div>

                                    @if ($recipe->notes)
                                        <p class="rounded-lg bg-[#f8f9fa] px-3 py-2 text-[12px] text-[#53565a]">{{ $recipe->notes }}</p>
                                    @endif

                                    <div>
                                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-[#7f837e]">Komposisi Bahan</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($recipe->items as $ingredient)
                                                <span class="inline-flex items-center gap-1 rounded-lg border border-[#e9ebf0] bg-[#fafaf9] px-2.5 py-1 text-[11px] text-[#111827]">
                                                    <span class="font-medium">{{ $ingredient->rawMaterial->name }}</span>
                                                    <span class="font-bold tabular-nums text-[#df5826]">{{ $number($ingredient->quantity) }} {{ $ingredient->unit }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- MODAL: BUAT SURAT KERJA                                    --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            @can('manage-production-orders')
                <div x-show="workOrderModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]"
                     @click.self="workOrderModal = false">
                    <div class="w-full max-w-lg overflow-hidden rounded-[18px] border border-[#e9ebf0] bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-[#e9ebf0] px-6 py-4">
                            <div>
                                <h2 class="text-[16px] font-bold text-[#111827]">Buat surat kerja</h2>
                                <p class="text-[12px] text-[#53565a]">Jadwalkan batch produksi baru berdasarkan versi resep.</p>
                            </div>
                            <button type="button" @click="workOrderModal = false"
                                    class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#53565a] transition hover:bg-gray-100 hover:text-[#111827]"
                                    aria-label="Tutup modal Buat Surat Kerja">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        @if ($recipes->isEmpty())
                            <div class="p-6 text-center">
                                <p class="text-[13px] text-[#53565a]">Tambahkan versi resep terlebih dahulu sebelum membuat surat kerja.</p>
                            </div>
                        @else
                            <form method="POST" action="{{ route('admin.production.work-order') }}" @submit="validateAndSubmitWorkOrder($event)" class="space-y-4 p-6">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                                <input type="hidden" name="production_recipe_id" :value="selectedRecipeId">

                                <div x-show="woClientError" x-cloak class="flex items-center gap-2 rounded-[10px] border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] font-semibold text-rose-700">
                                    <i class="fas fa-circle-exclamation"></i>
                                    <span x-text="woClientError"></span>
                                </div>

                                {{-- Custom Searchable Recipe Dropdown --}}
                                <div class="relative space-y-1.5" @click.outside="recipeSelectOpen = false">
                                    <label class="block text-[12px] font-semibold text-[#111827]">
                                        Versi Resep <span class="text-rose-500">*</span>
                                    </label>
                                    <button type="button" @click="recipeSelectOpen = !recipeSelectOpen"
                                            class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                            :class="recipeSelectOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                        <span class="truncate" :class="selectedRecipe() ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                              x-text="selectedRecipe() ? (selectedRecipe().name + ' · v' + selectedRecipe().version + ' (' + selectedRecipe().unit + ')') : 'Pilih versi resep'"></span>
                                        <i class="fas fa-chevron-down text-[11px] text-[#7f837e] transition-transform"
                                           :class="recipeSelectOpen ? 'rotate-180 text-[#df5826]' : ''" aria-hidden="true"></i>
                                    </button>

                                    <div x-show="recipeSelectOpen" x-cloak
                                         class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                        <div class="border-b border-[#f1f3f5] p-2">
                                            <div class="flex items-center gap-2 rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 focus-within:border-[#df5826] focus-within:bg-white">
                                                <i class="fas fa-search text-[11px] text-gray-400"></i>
                                                <input type="text" x-model="recipeSelectSearch" placeholder="Cari resep..."
                                                       class="w-full border-none bg-transparent text-[12px] text-[#111827] outline-none">
                                            </div>
                                        </div>
                                        <div class="max-h-52 overflow-y-auto p-1">
                                            <template x-for="rec in filteredRecipesForSelect()" :key="rec.id">
                                                <button type="button" @click="selectRecipe(rec)"
                                                        class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                        :class="String(selectedRecipeId) === rec.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                    <div class="min-w-0">
                                                        <div class="truncate">
                                                            <span x-text="rec.name"></span>
                                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold" x-text="'v' + rec.version"></span>
                                                        </div>
                                                        <div class="text-[11px] text-[#7f837e]" x-text="'Standar: ' + rec.yield_quantity + ' ' + rec.unit"></div>
                                                    </div>
                                                    <i x-show="String(selectedRecipeId) === rec.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="target-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                        Target hasil dalam satuan resep <span x-show="selectedRecipe()" x-text="'(' + selectedRecipe()?.unit + ')'"></span> <span class="text-rose-500">*</span>
                                    </label>
                                    <input id="target-qty-input" name="target_quantity" type="number" min="0.000001" step="0.000001"
                                           value="{{ old('target_quantity') }}" placeholder="Contoh: 5000"
                                           class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="wo-notes-input" class="block text-[12px] font-semibold text-[#111827]">Catatan</label>
                                    <input id="wo-notes-input" name="notes" value="{{ old('notes') }}" maxlength="250"
                                           placeholder="Opsional (shift produksi, prioritas, dll)"
                                           class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                                </div>

                                <div class="flex items-center justify-end gap-2.5 pt-2">
                                    <button type="button" @click="workOrderModal = false"
                                            class="h-[38px] cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] transition hover:bg-gray-50">
                                        Batal
                                    </button>
                                    <button type="submit" :disabled="woSubmitting"
                                            class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                        <i class="fas fa-plus text-[11px]" aria-hidden="true"></i>
                                        <span x-text="woSubmitting ? 'Membuat...' : 'Buat surat kerja'">Buat surat kerja</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- MODAL: TAMBAH VERSI RESEP                                  --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            @can('manage-production-recipes')
                <div x-show="recipeModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]"
                     @click.self="recipeModal = false">
                    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-[18px] border border-[#e9ebf0] bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-[#e9ebf0] px-6 py-4">
                            <div>
                                <h2 class="text-[16px] font-bold text-[#111827]">Tambah versi resep</h2>
                                <p class="text-[12px] text-[#53565a]">Buat formula baru untuk produk setengah jadi atau saus Central Kitchen.</p>
                            </div>
                            <button type="button" @click="recipeModal = false"
                                    class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#53565a] transition hover:bg-gray-100 hover:text-[#111827]"
                                    aria-label="Tutup modal Tambah Versi Resep">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        @if ($materials->count() < 2)
                            <div class="p-6 text-center">
                                <p class="text-[13px] text-[#53565a]">Minimal 2 bahan baku diperlukan (1 bahan hasil produksi dan minimal 1 bahan penyusun).</p>
                            </div>
                        @else
                            <form method="POST" action="{{ route('admin.production.recipe') }}" @submit="validateAndSubmitRecipe($event)" class="flex min-h-0 flex-1 flex-col">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                                <input type="hidden" name="output_material_id" :value="outputMaterialId">

                                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                                    <div x-show="recipeClientError" x-cloak class="flex items-center gap-2 rounded-[10px] border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] font-semibold text-rose-700">
                                        <i class="fas fa-circle-exclamation"></i>
                                        <span x-text="recipeClientError"></span>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-3">
                                        {{-- Custom Searchable Output Material Dropdown --}}
                                        <div class="relative space-y-1.5" @click.outside="outputSelectOpen = false">
                                            <label class="block text-[12px] font-semibold text-[#111827]">
                                                Bahan hasil produksi <span class="text-rose-500">*</span>
                                            </label>
                                            <button type="button" @click="outputSelectOpen = !outputSelectOpen"
                                                    class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                                    :class="outputSelectOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                                <span class="truncate" :class="materialById(outputMaterialId) ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                                      x-text="materialById(outputMaterialId) ? (materialById(outputMaterialId).name + ' (' + materialById(outputMaterialId).unit + ')') : 'Pilih hasil'"></span>
                                                <i class="fas fa-chevron-down text-[11px] text-[#7f837e] transition-transform"
                                                   :class="outputSelectOpen ? 'rotate-180 text-[#df5826]' : ''" aria-hidden="true"></i>
                                            </button>

                                            <div x-show="outputSelectOpen" x-cloak
                                                 class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                                <div class="border-b border-[#f1f3f5] p-2">
                                                    <div class="flex items-center gap-2 rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 focus-within:border-[#df5826] focus-within:bg-white">
                                                        <i class="fas fa-search text-[11px] text-gray-400"></i>
                                                        <input type="text" x-model="outputSelectSearch" placeholder="Cari bahan hasil..."
                                                               class="w-full border-none bg-transparent text-[12px] text-[#111827] outline-none">
                                                    </div>
                                                </div>
                                                <div class="max-h-52 overflow-y-auto p-1">
                                                    <template x-for="mat in filteredMaterials(outputSelectSearch)" :key="mat.id">
                                                        <button type="button" @click="selectOutputMaterial(mat)"
                                                                class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                                :class="String(outputMaterialId) === mat.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                            <span class="truncate" x-text="mat.name + ' (' + mat.unit + ')'"></span>
                                                            <i x-show="String(outputMaterialId) === mat.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-1.5">
                                            <label for="yield-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                                Hasil standar <span x-show="materialById(outputMaterialId)" x-text="'(' + materialById(outputMaterialId)?.unit + ')'"></span> <span class="text-rose-500">*</span>
                                            </label>
                                            <input id="yield-qty-input" name="yield_quantity" type="number" min="0.000001" step="0.000001"
                                                   value="{{ old('yield_quantity') }}" placeholder="Contoh: 4000"
                                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                        </div>

                                        <div class="space-y-1.5">
                                            <label for="recipe-notes-input" class="block text-[12px] font-semibold text-[#111827]">Catatan versi</label>
                                            <input id="recipe-notes-input" name="notes" value="{{ old('notes') }}" maxlength="250"
                                                   placeholder="Opsional"
                                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[13px] font-bold text-[#111827]">Komposisi Bahan Baku</span>
                                            <button type="button" @click="addRow()" :disabled="rows.length >= 50"
                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-[8px] border border-[#ffdec9] bg-[#fff6f0] px-3 py-1.5 text-[12px] font-semibold text-[#df5826] transition hover:bg-[#ffe8d9] disabled:cursor-not-allowed disabled:opacity-50">
                                                <i class="fas fa-plus text-[10px]" aria-hidden="true"></i>
                                                <span>Tambah bahan</span>
                                            </button>
                                        </div>

                                        <template x-for="(row, index) in rows" :key="row.key">
                                            <div class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-3.5">
                                                <div class="mb-2.5 flex items-center justify-between">
                                                    <span class="text-[12px] font-bold text-[#111827]" x-text="'Bahan #' + (index + 1)"></span>
                                                    <button type="button" @click="removeRow(index)" :disabled="rows.length < 2"
                                                            class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-35"
                                                            title="Hapus bahan">
                                                        <i class="fas fa-trash-can text-[12px]" aria-hidden="true"></i>
                                                    </button>
                                                </div>

                                                <div class="grid gap-3 sm:grid-cols-12">
                                                    <div class="relative space-y-1 sm:col-span-7" @click.outside="row.open = false">
                                                        <label class="block text-[11px] font-semibold text-[#53565a]">Bahan Baku</label>
                                                        <input type="hidden" :name="'items[' + index + '][raw_material_id]'" :value="row.raw_material_id">
                                                        <button type="button" @click="row.open = !row.open"
                                                                class="flex h-[38px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3 text-left text-[12px] transition"
                                                                :class="row.open ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                                            <span class="truncate"
                                                                  :class="materialById(row.raw_material_id) ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                                                  x-text="materialById(row.raw_material_id) ? (materialById(row.raw_material_id).name + ' (' + materialById(row.raw_material_id).unit + ')') : 'Pilih bahan'"></span>
                                                            <i class="fas fa-chevron-down text-[10px] text-[#7f837e] transition-transform"
                                                               :class="row.open ? 'rotate-180 text-[#df5826]' : ''" aria-hidden="true"></i>
                                                        </button>

                                                        <div x-show="row.open" x-cloak
                                                             class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                                            <div class="border-b border-[#f1f3f5] p-2">
                                                                <div class="flex items-center gap-2 rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 focus-within:border-[#df5826] focus-within:bg-white">
                                                                    <i class="fas fa-search text-[11px] text-gray-400"></i>
                                                                    <input type="text" x-model="row.search" placeholder="Cari bahan baku..."
                                                                           class="w-full border-none bg-transparent text-[12px] text-[#111827] outline-none">
                                                                </div>
                                                            </div>
                                                            <div class="max-h-52 overflow-y-auto p-1">
                                                                <template x-for="mat in filteredMaterials(row.search)" :key="mat.id">
                                                                    <button type="button" @click="selectRowMaterial(row, mat)"
                                                                            class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                                            :class="row.raw_material_id === mat.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                                        <span class="truncate" x-text="mat.name + ' (' + mat.unit + ')'"></span>
                                                                        <div class="flex shrink-0 items-center gap-1.5">
                                                                            <span x-show="isMaterialUsedElsewhere(mat.id, index)" class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Dipilih</span>
                                                                            <i x-show="row.raw_material_id === mat.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                                                        </div>
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="space-y-1 sm:col-span-5">
                                                        <label class="block text-[11px] font-semibold text-[#53565a]">
                                                            Takaran <span x-show="materialById(row.raw_material_id)" x-text="'(' + materialById(row.raw_material_id)?.unit + ')'"></span>
                                                        </label>
                                                        <input :name="'items[' + index + '][quantity]'" x-model="row.quantity" type="number" min="0.000001" step="0.000001" placeholder="0"
                                                               class="h-[38px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 text-[12px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2.5 border-t border-[#e9ebf0] bg-[#f8f9fa] px-6 py-4">
                                    <button type="button" @click="recipeModal = false"
                                            class="h-[38px] cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] transition hover:bg-gray-50">
                                        Batal
                                    </button>
                                    <button type="submit" :disabled="recipeSubmitting"
                                            class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                        <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                                        <span x-text="recipeSubmitting ? 'Menyimpan...' : 'Simpan versi baru'">Simpan versi baru</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan
        </div>
    @endisset
</x-cafe-operation-page>
