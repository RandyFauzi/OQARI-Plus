<x-cafe-operation-page title="Stok per Lokasi" :shop="$shop">
    @php
        $format = fn ($value) => rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        $materialPayload = $materials->map(fn ($m) => [
            'id' => (string) $m->id,
            'name' => $m->name,
            'unit_label' => $m->unit_label,
            'warehouse' => (float) ($warehouseBalances[$m->id] ?? 0),
            'kitchen' => (float) ($kitchenBalances[$m->id] ?? 0),
            'outlet' => (float) ($outletBalances[$m->id] ?? ($outlet->code === 'outlet' ? $m->current_stock : 0)),
        ])->values();
        $routePayload = collect(\App\Actions\Inventory\TransferStock::Routes)->map(fn ($pair, $code) => [
            'code' => $code,
            'label' => $locations[$pair[0]]->name . ' → ' . $locations[$pair[1]]->name,
        ])->values();
        $modifierPayload = $modifiers->map(fn ($mod) => [
            'id' => (string) $mod->id,
            'name' => $mod->name,
            'group' => $mod->group->name,
        ])->values();
        $pageTransfers = $transfers->getCollection();
        $pendingTransfersCount = $pageTransfers->filter(fn ($t) => $t->remainingQuantity() > 0)->count();
        $hasStockErrors = $errors->hasAny(['notes']) || (old('notes') !== null && old('raw_material_id') !== null);
        $hasTransferErrors = $errors->hasAny(['route']) || old('route') !== null || request()->has('transfer');
        $initialTab = request()->has('transfer') ? 'transfers' : ($errors->has('modifier_id') || $errors->has('amount') ? 'modifiers' : 'balances');
    @endphp

    <div x-data="{
        activeTab: @js($initialTab),
        balanceSearch: '',
        balanceFilter: 'all',
        transferFilter: 'all',
        transferSearch: '',
        stockModal: @js($hasStockErrors),
        transferModal: @js($hasTransferErrors && ! request()->has('transfer')),
        materials: @js($materialPayload),
        routes: @js($routePayload),
        modifiers: @js($modifierPayload),

        // Stock Modal State
        stockMatId: @js((string) old('raw_material_id', '')),
        stockMatOpen: false,
        stockMatSearch: '',
        stockSubmitting: false,

        // Transfer Modal State
        transferRoute: @js((string) old('route', $routePayload->first()['code'] ?? '')),
        transferRouteOpen: false,
        transferMatId: @js((string) old('raw_material_id', '')),
        transferMatOpen: false,
        transferMatSearch: '',
        transferSubmitting: false,

        // Modifier Recipe State
        modId: @js((string) old('modifier_id', $modifierPayload->first()['id'] ?? '')),
        modOpen: false,
        modSearch: '',
        modMatId: @js((string) old('raw_material_id', $materialPayload->first()['id'] ?? '')),
        modMatOpen: false,
        modMatSearch: '',
        modSubmitting: false,

        filterMats(q) {
            const term = (q || '').trim().toLowerCase();
            if (!term) return this.materials;
            return this.materials.filter(m => m.name.toLowerCase().includes(term) || m.unit_label.toLowerCase().includes(term));
        },
        matById(id) {
            return this.materials.find(m => m.id === String(id)) || null;
        },
        routeByCode(code) {
            return this.routes.find(r => r.code === String(code)) || null;
        },
        filterMods(q) {
            const term = (q || '').trim().toLowerCase();
            if (!term) return this.modifiers;
            return this.modifiers.filter(m => m.name.toLowerCase().includes(term) || m.group.toLowerCase().includes(term));
        },
        modById(id) {
            return this.modifiers.find(m => m.id === String(id)) || null;
        },
        matchesBalance(name, wh, ck) {
            const q = this.balanceSearch.trim().toLowerCase();
            const searchOk = !q || name.toLowerCase().includes(q);
            const filterOk = this.balanceFilter === 'all'
                || (this.balanceFilter === 'warehouse' && wh > 0)
                || (this.balanceFilter === 'kitchen' && ck > 0);
            return searchOk && filterOk;
        },
        matchesTransfer(isDone, text) {
            const statusOk = this.transferFilter === 'all'
                || (this.transferFilter === 'pending' && !isDone)
                || (this.transferFilter === 'done' && isDone);
            const q = this.transferSearch.trim().toLowerCase();
            const searchOk = !q || text.toLowerCase().includes(q);
            return statusOk && searchOk;
        }
    }" @keydown.escape.window="stockModal = false; transferModal = false; stockMatOpen = false; transferRouteOpen = false; transferMatOpen = false; modOpen = false; modMatOpen = false" class="space-y-5">

        {{-- Page Header --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div class="flex flex-col gap-1">
                <a href="{{ route('admin.inventory.index') }}"
                   class="inline-flex w-fit items-center gap-1.5 text-[12px] font-semibold text-[#53565a] transition hover:text-[#df5826]">
                    <i class="fas fa-arrow-left text-[11px]" aria-hidden="true"></i>
                    <span>Kembali ke Bahan &amp; Stok</span>
                </a>
                <h1 class="text-[20px] font-bold tracking-tight text-[#111827] md:text-[22px]">Stok per Lokasi</h1>
                <p class="text-[13px] text-[#53565a]">{{ $warehouse->name }} · {{ $kitchen->name }} · {{ $outlet->name }}</p>
            </div>

            @can('dispatch-stock')
                @if ($materials->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button type="button" @click="stockModal = true"
                                class="inline-flex h-[36px] cursor-pointer items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[12px] font-semibold text-[#111827] shadow-[0px_1px_2px_rgba(20,21,20,0.03)] transition hover:bg-gray-50">
                            <i class="fas fa-warehouse text-[12px] text-[#53565a]" aria-hidden="true"></i>
                            <span>Catat saldo gudang</span>
                        </button>

                        <button type="button" @click="transferModal = true"
                                class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
                            <i class="fas fa-truck-fast text-[11px]" aria-hidden="true"></i>
                            <span>Kirim transfer</span>
                        </button>
                    </div>
                @endif
            @endcan
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3.5 md:grid-cols-3">
            <div class="relative flex h-[108px] items-start justify-between overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-[#f9731f] p-4 text-white shadow-[0px_4px_14px_rgba(249,115,31,0.18)]">
                <div class="pointer-events-none absolute -right-3 -top-2 flex h-[106px] w-[106px] items-center justify-center opacity-15">
                    <i class="fas fa-truck-ramp-box text-[74px]" aria-hidden="true"></i>
                </div>
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <span class="text-[12px] font-medium leading-[15px] text-white/95">Transfer Menunggu Konfirmasi</span>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[26px] font-bold leading-tight tabular-nums text-white">{{ $pendingTransfersCount }}</span>
                        <span class="text-[11px] leading-[14px] text-white/90">Pengiriman dalam perjalanan / parsial</span>
                    </div>
                </div>
            </div>

            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <span class="text-[12px] font-medium text-[#53565a]">Total Bahan Baku</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#fff6f0] text-[#df5826]">
                        <i class="fas fa-boxes-stacked text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $materials->count() }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Dipantau di 3 lokasi stok</span>
                </div>
            </div>

            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <span class="text-[12px] font-medium text-[#53565a]">Total Riwayat Transfer</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#ecfdf5] text-[#059669]">
                        <i class="fas fa-right-left text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $transfers->total() }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Perpindahan stok antar lokasi</span>
                </div>
            </div>
        </div>

        {{-- Sub-Tab Navigation --}}
        <div class="flex flex-wrap items-center gap-6 border-b border-[#e9ebf0]">
            <button type="button" @click="activeTab = 'balances'"
                    :class="activeTab === 'balances' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                    class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                <i class="fas fa-warehouse text-[13px]" aria-hidden="true"></i>
                <span>Saldo Bahan</span>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                      :class="activeTab === 'balances' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                    {{ $materials->count() }}
                </span>
            </button>

            <button type="button" @click="activeTab = 'transfers'"
                    :class="activeTab === 'transfers' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                    class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                <i class="fas fa-truck-fast text-[13px]" aria-hidden="true"></i>
                <span>Riwayat Transfer</span>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                      :class="activeTab === 'transfers' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                    {{ $transfers->total() }}
                </span>
            </button>

            @can('manage-hpp')
                @if ($modifiers->isNotEmpty() && $materials->isNotEmpty())
                    <button type="button" @click="activeTab = 'modifiers'"
                            :class="activeTab === 'modifiers' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                            class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                        <i class="fas fa-puzzle-piece text-[13px]" aria-hidden="true"></i>
                        <span>Resep Pilihan Tambahan</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                              :class="activeTab === 'modifiers' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                            {{ $modifiers->count() }}
                        </span>
                    </button>
                @endif
            @endcan
        </div>

        {{-- TAB 1: SALDO BAHAN PER LOKASI --}}
        <section x-show="activeTab === 'balances'" aria-labelledby="balances-heading" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" @click="balanceFilter = 'all'"
                            :class="balanceFilter === 'all' ? 'bg-[#fff6f0] text-[#df5826] border-[#ffdec9] font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                            class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">Semua Bahan</button>
                    <button type="button" @click="balanceFilter = 'warehouse'"
                            :class="balanceFilter === 'warehouse' ? 'bg-[#fff6f0] text-[#df5826] border-[#ffdec9] font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                            class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">Ada di {{ $warehouse->name }}</button>
                    <button type="button" @click="balanceFilter = 'kitchen'"
                            :class="balanceFilter === 'kitchen' ? 'bg-[#fff6f0] text-[#df5826] border-[#ffdec9] font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                            class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">Ada di {{ $kitchen->name }}</button>
                </div>

                <div class="flex h-[36px] w-full items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3 shadow-2xs transition focus-within:border-[#df5826] sm:w-64">
                    <i class="fas fa-search text-[12px] text-gray-400" aria-hidden="true"></i>
                    <input type="text" x-model="balanceSearch" placeholder="Cari nama bahan..."
                           class="w-full border-none bg-transparent text-[12px] text-[#111827] placeholder-[#7f837e] outline-none">
                </div>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                <h2 id="balances-heading" class="sr-only">Saldo bahan</h2>
                <div class="overflow-x-auto" role="region" aria-labelledby="balances-heading" tabindex="0">
                    <table class="w-full min-w-[540px] text-left text-[13px]">
                        <thead class="border-b border-[#e9ebf0] bg-[#f8f9fa] text-[11px] font-semibold uppercase tracking-wider text-[#53565a]">
                            <tr>
                                <th scope="col" class="px-5 py-3">Bahan</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ $warehouse->name }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ $kitchen->name }}</th>
                                <th scope="col" class="px-5 py-3 text-right">{{ $outlet->name }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f1f3f5]">
                            @forelse ($materials as $material)
                                @php
                                    $whQty = (float) ($warehouseBalances[$material->id] ?? 0);
                                    $ckQty = (float) ($kitchenBalances[$material->id] ?? 0);
                                    $outQty = (float) ($outletBalances[$material->id] ?? ($outlet->code === 'outlet' ? $material->current_stock : 0));
                                @endphp
                                <tr x-show="matchesBalance(@js($material->name), {{ $whQty }}, {{ $ckQty }})"
                                    class="transition hover:bg-[#fafaf9]">
                                    <th scope="row" class="px-5 py-3.5 font-semibold text-[#111827]">
                                        {{ $material->name }}
                                        <span class="ml-1 rounded-md bg-[#f1f3f5] px-1.5 py-0.5 text-[11px] font-medium text-[#53565a]">{{ $material->unit_label }}</span>
                                    </th>
                                    <td class="px-4 py-3.5 text-right tabular-nums {{ $whQty > 0 ? 'font-semibold text-[#111827]' : 'text-[#9ca3af]' }}">
                                        {{ $format($whQty) }} {{ $material->unit_label }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right tabular-nums {{ $ckQty > 0 ? 'font-semibold text-[#111827]' : 'text-[#9ca3af]' }}">
                                        {{ $format($ckQty) }} {{ $material->unit_label }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right tabular-nums {{ $outQty > 0 ? 'font-bold text-emerald-700' : 'text-[#9ca3af]' }}">
                                        {{ $format($outQty) }} {{ $material->unit_label }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-10 text-center text-[13px] text-[#7f837e]">
                                        Tambahkan bahan baku untuk mencatat saldo dan transfer.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- TAB 2: RIWAYAT TRANSFER --}}
        <section x-show="activeTab === 'transfers'" x-cloak aria-labelledby="transfers-heading" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" @click="transferFilter = 'all'"
                            :class="transferFilter === 'all' ? 'bg-[#fff6f0] text-[#df5826] border-[#ffdec9] font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                            class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">Semua</button>
                    <button type="button" @click="transferFilter = 'pending'"
                            :class="transferFilter === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                            class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">Dalam Proses</button>
                    <button type="button" @click="transferFilter = 'done'"
                            :class="transferFilter === 'done' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                            class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">Selesai</button>
                </div>

                <div class="flex h-[36px] w-full items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3 shadow-2xs transition focus-within:border-[#df5826] sm:w-64">
                    <i class="fas fa-search text-[12px] text-gray-400" aria-hidden="true"></i>
                    <input type="text" x-model="transferSearch" placeholder="Cari ID atau nama bahan..."
                           class="w-full border-none bg-transparent text-[12px] text-[#111827] placeholder-[#7f837e] outline-none">
                </div>
            </div>

            <div class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] space-y-3">
                <h2 id="transfers-heading" class="text-[15px] font-bold text-[#111827]">Riwayat transfer</h2>
                <div class="space-y-3">
                    @forelse ($transfers as $transfer)
                        @php $isDone = $transfer->remainingQuantity() === 0.0; @endphp
                        <article id="transfer-{{ $transfer->id }}" data-operation-anchor
                                 x-show="matchesTransfer(@js($isDone), @js('#' . $transfer->id . ' ' . $transfer->rawMaterial->name . ' ' . $transfer->fromLocation->name . ' ' . $transfer->toLocation->name))"
                                 class="flex flex-col justify-between gap-4 rounded-[12px] border border-[#e9ebf0] bg-[#fafaf9]/60 p-4 transition hover:border-[#ffd2b8] hover:bg-white sm:flex-row sm:items-center">
                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-[#fff6f0] px-2 py-0.5 text-[12px] font-bold text-[#df5826]">#{{ $transfer->id }}</span>
                                    <h3 class="text-[14px] font-bold text-[#111827]">{{ $transfer->rawMaterial->name }}</h3>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $isDone ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $isDone ? 'bg-emerald-500' : 'bg-sky-500' }}"></span>
                                        {{ $isDone ? 'Selesai' : ((float) $transfer->received_quantity > 0 ? 'Diterima sebagian' : 'Dalam perjalanan') }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-[12px] text-[#53565a]">
                                    <span class="rounded-md border border-[#e9ebf0] bg-white px-2 py-0.5 font-semibold text-[#111827]">
                                        {{ $transfer->fromLocation->name }} → {{ $transfer->toLocation->name }}
                                    </span>
                                    <span class="tabular-nums">
                                        Dikirim <strong>{{ $format($transfer->sent_quantity) }}</strong> ·
                                        Diterima <strong class="text-emerald-700">{{ $format($transfer->received_quantity) }}</strong>
                                        @if ($transfer->remainingQuantity() > 0)
                                            · Sisa <strong class="text-amber-700">{{ $format($transfer->remainingQuantity()) }}</strong>
                                        @endif
                                        {{ $transfer->rawMaterial->unit_label }}
                                    </span>
                                </div>
                            </div>

                            @can('receive-stock')
                                @if ($transfer->remainingQuantity() > 0)
                                    <form method="POST" action="{{ route('admin.inventory.locations.receive', $transfer) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <label class="space-y-1 text-[11px] font-semibold text-[#53565a]">
                                            <span class="block">Total sudah diterima</span>
                                            <input name="quantity" type="number" step="0.000001"
                                                   min="{{ max(0.000001, (float) $transfer->received_quantity) }}"
                                                   max="{{ $transfer->sent_quantity }}"
                                                   value="{{ $format($transfer->sent_quantity) }}"
                                                   class="h-[36px] w-32 rounded-[9px] border border-[#e9ebf0] bg-white px-3 text-[12px] tabular-nums text-[#111827] outline-none focus:border-[#df5826]" required>
                                        </label>
                                        <button class="h-[36px] cursor-pointer rounded-[9px] bg-[#df5826] px-4 text-[12px] font-semibold text-white transition hover:bg-[#c94d00]">
                                            Konfirmasi
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </article>
                    @empty
                        <p class="py-8 text-center text-[13px] text-[#7f837e]">Belum ada transfer.</p>
                    @endforelse
                </div>

                @if ($transfers->hasPages())
                    <div class="border-t border-[#e9ebf0] pt-3">
                        {{ $transfers->links() }}
                    </div>
                @endif
            </div>
        </section>

        {{-- TAB 3: RESEP PILIHAN TAMBAHAN (MODIFIERS) --}}
        @can('manage-hpp')
            @if ($modifiers->isNotEmpty() && $materials->isNotEmpty())
                <section x-show="activeTab === 'modifiers'" x-cloak aria-labelledby="modifier-heading" class="space-y-4">
                    <form method="POST" action="{{ route('admin.inventory.locations.modifier-recipe') }}" @submit="modSubmitting = true"
                          class="space-y-4 rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                        @csrf
                        <input type="hidden" name="modifier_id" :value="modId">
                        <input type="hidden" name="raw_material_id" :value="modMatId">

                        <div class="border-b border-[#e9ebf0] pb-3">
                            <h2 id="modifier-heading" class="text-[15px] font-bold text-[#111827]">Resep pilihan tambahan</h2>
                            <p id="recipe-amount-help" class="text-[12px] text-[#53565a]">Gunakan satuan stok bahan. Isi 0 untuk menghapus bahan dari resep.</p>
                        </div>

                        <div class="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="relative space-y-1.5" @click.outside="modOpen = false">
                                <span class="block text-[12px] font-semibold text-[#111827]">Pilihan tambahan</span>
                                <button type="button" @click="modOpen = !modOpen"
                                        class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                        :class="modOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                    <span class="truncate font-medium text-[#111827]"
                                          x-text="modById(modId) ? (modById(modId).name + ' · ' + modById(modId).group) : 'Pilih tambahan'"></span>
                                    <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                                </button>
                                <div x-show="modOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                    <div class="border-b border-[#f1f3f5] p-2">
                                        <input type="text" x-model="modSearch" placeholder="Cari pilihan tambahan..."
                                               class="w-full rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 text-[12px] outline-none focus:border-[#df5826] focus:bg-white">
                                    </div>
                                    <div class="max-h-52 overflow-y-auto p-1">
                                        <template x-for="mod in filterMods(modSearch)" :key="mod.id">
                                            <button type="button" @click="modId = mod.id; modOpen = false; modSearch = ''"
                                                    class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px]"
                                                    :class="String(modId) === mod.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                <span class="truncate" x-text="mod.name + ' · ' + mod.group"></span>
                                                <i x-show="String(modId) === mod.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="relative space-y-1.5" @click.outside="modMatOpen = false">
                                <span class="block text-[12px] font-semibold text-[#111827]">Bahan</span>
                                <button type="button" @click="modMatOpen = !modMatOpen"
                                        class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                        :class="modMatOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                    <span class="truncate font-medium text-[#111827]"
                                          x-text="matById(modMatId) ? (matById(modMatId).name + ' (' + matById(modMatId).unit_label + ')') : 'Pilih bahan'"></span>
                                    <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                                </button>
                                <div x-show="modMatOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                    <div class="border-b border-[#f1f3f5] p-2">
                                        <input type="text" x-model="modMatSearch" placeholder="Cari bahan..."
                                               class="w-full rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 text-[12px] outline-none focus:border-[#df5826] focus:bg-white">
                                    </div>
                                    <div class="max-h-52 overflow-y-auto p-1">
                                        <template x-for="m in filterMats(modMatSearch)" :key="m.id">
                                            <button type="button" @click="modMatId = m.id; modMatOpen = false; modMatSearch = ''"
                                                    class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px]"
                                                    :class="String(modMatId) === m.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                <span class="truncate" x-text="m.name + ' (' + m.unit_label + ')'"></span>
                                                <i x-show="String(modMatId) === m.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label for="mod-amount-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Takaran per tambahan <span x-show="matById(modMatId)" x-text="'(' + matById(modMatId)?.unit_label + ')'"></span>
                                </label>
                                <input id="mod-amount-input" name="amount" type="number" step="0.000001" min="0" aria-describedby="recipe-amount-help"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none focus:border-[#df5826]" required>
                            </div>

                            <button type="submit" :disabled="modSubmitting"
                                    class="inline-flex h-[40px] cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:opacity-60">
                                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                                <span>Simpan takaran</span>
                            </button>
                        </div>
                    </form>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($modifiers as $modifier)
                            <div class="rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <h3 class="text-[13px] font-bold text-[#111827]">{{ $modifier->name }}</h3>
                                    <span class="rounded-md bg-[#f1f3f5] px-2 py-0.5 text-[10px] font-semibold text-[#53565a]">{{ $modifier->group->name }}</span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($modifier->ingredients as $ingredient)
                                        <span class="inline-flex items-center gap-1 rounded-lg border border-[#e9ebf0] bg-[#fafaf9] px-2.5 py-1 text-[11px] text-[#111827]">
                                            <span class="font-bold text-[#df5826]">{{ $format($ingredient->pivot->amount) }} {{ $ingredient->unit_label }}</span>
                                            <span>{{ $ingredient->name }}</span>
                                        </span>
                                    @empty
                                        <span class="text-[12px] text-[#7f837e]">Belum ada resep stok</span>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endcan

        {{-- MODAL: CATAT SALDO GUDANG --}}
        @can('dispatch-stock')
            @if ($materials->isNotEmpty())
                <div x-show="stockModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="stockModal = false">
                    <div class="w-full max-w-md overflow-hidden rounded-[18px] border border-[#e9ebf0] bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-[#e9ebf0] px-6 py-4">
                            <div>
                                <h2 class="text-[16px] font-bold text-[#111827]">Catat saldo gudang</h2>
                                <p class="text-[12px] text-[#53565a]">Sesuaikan total stok fisik di {{ $warehouse->name }}.</p>
                            </div>
                            <button type="button" @click="stockModal = false" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#53565a] hover:bg-gray-100">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.inventory.locations.stock') }}" @submit="if (!stockMatId) { $event.preventDefault(); stockMatOpen = true; return; } stockSubmitting = true" class="space-y-4 p-6">
                            @csrf
                            <input type="hidden" name="raw_material_id" :value="stockMatId">

                            <div class="relative space-y-1.5" @click.outside="stockMatOpen = false">
                                <span class="block text-[12px] font-semibold text-[#111827]">Bahan <span class="text-rose-500">*</span></span>
                                <button type="button" @click="stockMatOpen = !stockMatOpen"
                                        class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                        :class="stockMatOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                    <span class="truncate" :class="matById(stockMatId) ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                          x-text="matById(stockMatId) ? (matById(stockMatId).name + ' (' + matById(stockMatId).unit_label + ')') : 'Pilih bahan'"></span>
                                    <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                                </button>
                                <div x-show="stockMatOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                    <div class="border-b border-[#f1f3f5] p-2">
                                        <input type="text" x-model="stockMatSearch" placeholder="Cari bahan..."
                                               class="w-full rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 text-[12px] outline-none focus:border-[#df5826] focus:bg-white">
                                    </div>
                                    <div class="max-h-52 overflow-y-auto p-1">
                                        <template x-for="m in filterMats(stockMatSearch)" :key="m.id">
                                            <button type="button" @click="stockMatId = m.id; stockMatOpen = false; stockMatSearch = ''"
                                                    class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px]"
                                                    :class="String(stockMatId) === m.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                <span class="truncate" x-text="m.name + ' (' + m.unit_label + ')'"></span>
                                                <i x-show="String(stockMatId) === m.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label for="wh-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Total stok fisik <span x-show="matById(stockMatId)" x-text="'(' + matById(stockMatId)?.unit_label + ')'"></span> <span class="text-rose-500">*</span>
                                </label>
                                <input id="wh-qty-input" name="quantity" type="number" step="0.000001" min="0" placeholder="0"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none focus:border-[#df5826]" required>
                            </div>

                            <div class="space-y-1.5">
                                <label for="wh-notes-input" class="block text-[12px] font-semibold text-[#111827]">Alasan pencatatan <span class="text-rose-500">*</span></label>
                                <input id="wh-notes-input" name="notes" maxlength="200" placeholder="Contoh: Stok awal gudang"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none focus:border-[#df5826]" required>
                            </div>

                            <div class="flex justify-end gap-2.5 pt-2">
                                <button type="button" @click="stockModal = false" class="h-[38px] cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] hover:bg-gray-50">Batal</button>
                                <button type="submit" :disabled="stockSubmitting" class="h-[38px] cursor-pointer rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] hover:bg-[#c94d00] disabled:opacity-60">
                                    Simpan saldo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- MODAL: KIRIM TRANSFER --}}
                <div x-show="transferModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="transferModal = false">
                    <div class="w-full max-w-md overflow-hidden rounded-[18px] border border-[#e9ebf0] bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-[#e9ebf0] px-6 py-4">
                            <div>
                                <h2 class="text-[16px] font-bold text-[#111827]">Kirim transfer</h2>
                                <p class="text-[12px] text-[#53565a]">Pindahkan stok antar lokasi (Gudang, Central Kitchen, Outlet).</p>
                            </div>
                            <button type="button" @click="transferModal = false" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#53565a] hover:bg-gray-100">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.inventory.locations.send') }}" @submit="if (!transferMatId) { $event.preventDefault(); transferMatOpen = true; return; } transferSubmitting = true" class="space-y-4 p-6">
                            @csrf
                            <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                            <input type="hidden" name="route" :value="transferRoute">
                            <input type="hidden" name="raw_material_id" :value="transferMatId">

                            <div class="relative space-y-1.5" @click.outside="transferRouteOpen = false">
                                <span class="block text-[12px] font-semibold text-[#111827]">Jalur transfer <span class="text-rose-500">*</span></span>
                                <button type="button" @click="transferRouteOpen = !transferRouteOpen"
                                        class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                        :class="transferRouteOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                    <span class="truncate font-medium text-[#111827]" x-text="routeByCode(transferRoute)?.label || 'Pilih jalur'"></span>
                                    <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                                </button>
                                <div x-show="transferRouteOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white p-1 shadow-xl">
                                    <template x-for="r in routes" :key="r.code">
                                        <button type="button" @click="transferRoute = r.code; transferRouteOpen = false"
                                                class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px]"
                                                :class="transferRoute === r.code ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                            <span x-text="r.label"></span>
                                            <i x-show="transferRoute === r.code" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="relative space-y-1.5" @click.outside="transferMatOpen = false">
                                <span class="block text-[12px] font-semibold text-[#111827]">Bahan <span class="text-rose-500">*</span></span>
                                <button type="button" @click="transferMatOpen = !transferMatOpen"
                                        class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                        :class="transferMatOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                    <span class="truncate" :class="matById(transferMatId) ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                          x-text="matById(transferMatId) ? (matById(transferMatId).name + ' (' + matById(transferMatId).unit_label + ')') : 'Pilih bahan'"></span>
                                    <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                                </button>
                                <div x-show="transferMatOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                    <div class="border-b border-[#f1f3f5] p-2">
                                        <input type="text" x-model="transferMatSearch" placeholder="Cari bahan..."
                                               class="w-full rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 text-[12px] outline-none focus:border-[#df5826] focus:bg-white">
                                    </div>
                                    <div class="max-h-52 overflow-y-auto p-1">
                                        <template x-for="m in filterMats(transferMatSearch)" :key="m.id">
                                            <button type="button" @click="transferMatId = m.id; transferMatOpen = false; transferMatSearch = ''"
                                                    class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px]"
                                                    :class="String(transferMatId) === m.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                <span class="truncate" x-text="m.name + ' (' + m.unit_label + ')'"></span>
                                                <i x-show="String(transferMatId) === m.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label for="tr-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Jumlah kirim <span x-show="matById(transferMatId)" x-text="'(' + matById(transferMatId)?.unit_label + ')'"></span> <span class="text-rose-500">*</span>
                                </label>
                                <input id="tr-qty-input" name="quantity" type="number" step="0.000001" min="0.000001" placeholder="0"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none focus:border-[#df5826]" required>
                            </div>

                            <div class="flex justify-end gap-2.5 pt-2">
                                <button type="button" @click="transferModal = false" class="h-[38px] cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] hover:bg-gray-50">Batal</button>
                                <button type="submit" :disabled="transferSubmitting" class="h-[38px] cursor-pointer rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] hover:bg-[#c94d00] disabled:opacity-60">
                                    Catat pengiriman
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</x-cafe-operation-page>
