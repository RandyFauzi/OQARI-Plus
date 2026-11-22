<x-cafe-operation-page title="Pembelian" :shop="$shop">
    @php
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');
        $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $statusTone = fn (string $status): string => match ($status) {
            'DRAFT' => 'bg-amber-50 text-amber-700 border-amber-200',
            'APPROVED' => 'bg-sky-50 text-sky-700 border-sky-200',
            'PARTIAL' => 'bg-violet-50 text-violet-700 border-violet-200',
            'RECEIVED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            default => 'bg-gray-50 text-gray-700 border-gray-200',
        };
        $statusDot = fn (string $status): string => match ($status) {
            'DRAFT' => 'bg-amber-500',
            'APPROVED' => 'bg-sky-500',
            'PARTIAL' => 'bg-violet-500',
            'RECEIVED' => 'bg-emerald-500',
            default => 'bg-gray-400',
        };
    @endphp

    @isset($order)
        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- DETAIL PURCHASE ORDER                                      --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-1.5">
                    <a href="{{ route('admin.purchases.index') }}"
                       class="inline-flex w-fit items-center gap-1.5 text-[12px] font-semibold text-[#53565a] transition hover:text-[#df5826]">
                        <i class="fas fa-arrow-left text-[11px]" aria-hidden="true"></i>
                        <span>Kembali ke Pembelian</span>
                    </a>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-[22px] font-bold tracking-tight text-[#111827] md:text-[24px]">
                            PO #{{ $order->id }}
                        </h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold {{ $statusTone($order->status) }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($order->status) }}"></span>
                            {{ $order->statusLabel() }}
                        </span>
                    </div>
                    <p class="text-[13px] text-[#53565a]">
                        Supplier: <span class="font-semibold text-[#111827]">{{ $order->supplier->name }}</span>
                        @if ($order->supplier->contact)
                            <span class="text-[#9ca3af]">·</span> {{ $order->supplier->contact }}
                        @endif
                    </p>
                </div>

                @can('approve-purchases')
                    @if ($order->status === \App\Models\PurchaseOrder::Draft)
                        <form method="POST" action="{{ route('admin.purchases.approve', $order) }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <button type="submit" :disabled="submitting"
                                    class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="fas fa-check-circle text-[13px]" aria-hidden="true"></i>
                                <span x-text="submitting ? 'Menyetujui...' : 'Setujui PO'">Setujui PO</span>
                            </button>
                        </form>
                    @endif
                @endcan
            </div>

            {{-- Summary Info Cards --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Total Nilai PO</span>
                    <div class="mt-2">
                        <span class="text-[20px] font-bold tabular-nums text-[#111827]">
                            Rp{{ $number(round($order->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_cost), 2)) }}
                        </span>
                        <p class="mt-0.5 text-[11px] text-[#7f837e]">{{ $order->items->count() }} jenis bahan</p>
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Supplier</span>
                    <div class="mt-2">
                        <span class="block truncate text-[15px] font-bold text-[#111827]">{{ $order->supplier->name }}</span>
                        <p class="mt-0.5 truncate text-[11px] text-[#7f837e]">{{ $order->supplier->contact ?: 'Tanpa kontak' }}</p>
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Dibuat Oleh</span>
                    <div class="mt-2">
                        <span class="block truncate text-[15px] font-bold text-[#111827]">{{ $order->creator?->name ?? '—' }}</span>
                        <p class="mt-0.5 text-[11px] text-[#7f837e]">{{ $order->created_at?->format('d M Y, H:i') ?? '—' }}</p>
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <span class="text-[12px] font-medium text-[#53565a]">Persetujuan</span>
                    <div class="mt-2">
                        @if ($order->approved_at)
                            <span class="block truncate text-[15px] font-bold text-[#111827]">{{ $order->approver?->name ?? '—' }}</span>
                            <p class="mt-0.5 text-[11px] text-emerald-600">{{ $order->approved_at->format('d M Y, H:i') }}</p>
                        @else
                            <span class="text-[14px] font-semibold text-amber-600">Menunggu persetujuan</span>
                            <p class="mt-0.5 text-[11px] text-[#7f837e]">Belum disetujui</p>
                        @endif
                    </div>
                </div>
            </div>

            @if ($order->notes)
                <div class="flex items-start gap-2.5 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] px-4 py-3 text-[13px] text-[#53565a]">
                    <i class="fas fa-note-sticky mt-0.5 text-[#df5826]" aria-hidden="true"></i>
                    <div>
                        <span class="font-semibold text-[#111827]">Catatan PO:</span>
                        <span>{{ $order->notes }}</span>
                    </div>
                </div>
            @endif

            {{-- Tabel Barang Dipesan --}}
            <section class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]" aria-labelledby="purchase-items">
                <div class="flex items-center justify-between border-b border-[#e9ebf0] px-5 py-3.5">
                    <div>
                        <h2 id="purchase-items" class="text-[14px] font-bold text-[#111827]">Barang Dipesan</h2>
                        <p class="text-[12px] text-[#7f837e]">Rincian bahan baku, harga satuan, dan progres penerimaan.</p>
                    </div>
                </div>
                <div class="overflow-x-auto" role="region" aria-labelledby="purchase-items" tabindex="0">
                    <table class="w-full min-w-[600px] text-left text-[13px]">
                        <thead class="border-b border-[#e9ebf0] bg-[#f8f9fa] text-[11px] font-semibold uppercase tracking-wider text-[#53565a]">
                            <tr>
                                <th scope="col" class="px-5 py-3">Bahan</th>
                                <th scope="col" class="px-4 py-3 text-right">Harga per Satuan</th>
                                <th scope="col" class="px-4 py-3 text-right">Dipesan</th>
                                <th scope="col" class="px-4 py-3 text-right">Diterima</th>
                                <th scope="col" class="px-5 py-3 text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f1f3f5]">
                            @foreach ($order->items as $item)
                                @php $remaining = $item->remainingQuantity(); @endphp
                                <tr class="transition hover:bg-[#fafaf9]">
                                    <th scope="row" class="px-5 py-3.5 font-semibold text-[#111827]">
                                        {{ $item->rawMaterial->name }}
                                        <span class="ml-1 rounded-md bg-[#f1f3f5] px-1.5 py-0.5 text-[11px] font-medium text-[#53565a]">{{ $item->unit }}</span>
                                    </th>
                                    <td class="px-4 py-3.5 text-right tabular-nums text-[#53565a]">Rp{{ $number($item->unit_cost) }}</td>
                                    <td class="px-4 py-3.5 text-right tabular-nums font-medium text-[#111827]">{{ $number($item->quantity) }}</td>
                                    <td class="px-4 py-3.5 text-right tabular-nums font-semibold text-emerald-600">{{ $number($item->accepted_quantity) }}</td>
                                    <td class="px-5 py-3.5 text-right tabular-nums">
                                        @if ($remaining > 0)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">
                                                {{ $number($remaining) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">
                                                Selesai
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Form Penerimaan Barang --}}
            @can('receive-purchases')
                @if (in_array($order->status, [\App\Models\PurchaseOrder::Approved, \App\Models\PurchaseOrder::Partial], true))
                    @php $priorReceipt = collect(old('items', []))->keyBy('purchase_order_item_id'); @endphp
                    <form method="POST" action="{{ route('admin.purchases.receive', $order) }}"
                          x-data="{ submitting: false }" @submit="submitting = true"
                          class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] space-y-4">
                        @csrf
                        <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="flex flex-col gap-1 border-b border-[#e9ebf0] pb-3.5">
                            <h2 class="text-[15px] font-bold text-[#111827]">Terima Barang di Gudang Pusat</h2>
                            <p class="text-[12px] text-[#53565a]">Isi jumlah dari pengiriman ini. Jika ada barang ditolak, isi alasannya agar sisa PO bisa ditindaklanjuti.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="delivery-reference-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Nomor Surat Jalan <span class="text-rose-500">*</span>
                                </label>
                                <input id="delivery-reference-input" name="delivery_reference" maxlength="120" value="{{ old('delivery_reference') }}"
                                       placeholder="Contoh: SJ-2026-001"
                                       class="w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 py-2.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]" required>
                            </div>
                            <div class="space-y-1.5">
                                <label for="receipt-notes-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Catatan Penerimaan
                                </label>
                                <input id="receipt-notes-input" name="notes" maxlength="250" value="{{ old('notes') }}"
                                       placeholder="Opsional (kondisi barang, kurir, dll)"
                                       class="w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 py-2.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach ($order->items->filter(fn ($item) => $item->remainingQuantity() > 0) as $item)
                                @php $priorLine = $priorReceipt->get($item->id, []); @endphp
                                <fieldset class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <legend class="text-[13px] font-bold text-[#111827]">{{ $item->rawMaterial->name }}</legend>
                                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">
                                            Sisa: {{ $number($item->remainingQuantity()) }} {{ $item->unit }}
                                        </span>
                                    </div>
                                    <input type="hidden" name="items[{{ $item->id }}][purchase_order_item_id]" value="{{ $item->id }}">
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <label class="space-y-1 text-[12px] font-semibold text-[#111827]">
                                            <span>Diterima ({{ $item->unit }})</span>
                                            <input name="items[{{ $item->id }}][accepted_quantity]" type="number" min="0" max="{{ $item->remainingQuantity() }}" step="0.000001"
                                                   value="{{ $priorLine['accepted_quantity'] ?? 0 }}"
                                                   class="w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 py-2 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                        </label>
                                        <label class="space-y-1 text-[12px] font-semibold text-[#111827]">
                                            <span>Ditolak ({{ $item->unit }})</span>
                                            <input name="items[{{ $item->id }}][rejected_quantity]" type="number" min="0" max="{{ $item->remainingQuantity() }}" step="0.000001"
                                                   value="{{ $priorLine['rejected_quantity'] ?? 0 }}"
                                                   class="w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 py-2 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                        </label>
                                        <label class="space-y-1 text-[12px] font-semibold text-[#111827] sm:col-span-2 lg:col-span-1">
                                            <span>Alasan Penolakan</span>
                                            <input name="items[{{ $item->id }}][rejection_reason]" maxlength="250"
                                                   value="{{ $priorLine['rejection_reason'] ?? '' }}"
                                                   placeholder="Wajib diisi jika ada yang ditolak"
                                                   class="w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 py-2 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                                        </label>
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>

                        <div class="flex justify-end pt-1">
                            <button type="submit" :disabled="submitting"
                                    class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                                <span x-text="submitting ? 'Menyimpan...' : 'Catat Penerimaan'">Catat Penerimaan</span>
                            </button>
                        </div>
                    </form>
                @endif
            @endcan

            {{-- Riwayat Penerimaan --}}
            <section class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]" aria-labelledby="receipt-history">
                <h2 id="receipt-history" class="mb-3 text-[14px] font-bold text-[#111827]">Riwayat Penerimaan</h2>
                <div class="divide-y divide-[#f1f3f5]">
                    @forelse ($order->receipts as $receipt)
                        <article class="flex flex-col gap-2 py-3.5 text-[13px] first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-[#fff6f0] text-[#df5826]">
                                        <i class="fas fa-file-invoice text-xs" aria-hidden="true"></i>
                                    </span>
                                    <h3 class="font-bold text-[#111827]">{{ $receipt->delivery_reference }}</h3>
                                </div>
                                <span class="text-[12px] text-[#53565a]">
                                    {{ $receipt->created_at->format('d/m/Y H:i') }} · {{ $receipt->receiver?->name ?? '—' }}
                                </span>
                            </div>
                            @if ($receipt->notes)
                                <p class="text-[12px] text-[#53565a]">{{ $receipt->notes }}</p>
                            @endif
                            <ul class="mt-1 space-y-1.5 pl-9">
                                @foreach ($receipt->items as $line)
                                    <li class="flex flex-wrap items-center gap-2 text-[12px] text-[#111827]">
                                        <span class="font-semibold">{{ $line->purchaseOrderItem->rawMaterial->name }}</span>
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                            +{{ $number($line->accepted_quantity) }} diterima
                                        </span>
                                        @if ((float) $line->rejected_quantity > 0)
                                            <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                                {{ $number($line->rejected_quantity) }} ditolak ({{ $line->rejection_reason }})
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @empty
                        <p class="py-4 text-center text-[13px] text-[#7f837e]">Belum ada penerimaan barang untuk PO ini.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @else
        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- HALAMAN UTAMA PEMBELIAN (INDEX)                            --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        @php
            $previousRows = collect(old('items'))->filter(fn ($item) => is_array($item) && array_key_exists('raw_material_id', $item))->values();
            $purchaseRows = $previousRows->isNotEmpty()
                ? $previousRows->all()
                : [['raw_material_id' => '', 'quantity' => '', 'unit_cost' => '']];
            $hasPoErrors = $errors->has('supplier_id') || $errors->has('notes') || $errors->has('items') || $errors->has('items.*') || old('supplier_id') !== null || $previousRows->isNotEmpty();
            $hasSupplierErrors = ($errors->has('name') || $errors->has('contact')) && ! $hasPoErrors;
            $supplierPayload = $suppliers->map(fn ($s) => [
                'id' => (string) $s->id,
                'name' => $s->name,
                'contact' => $s->contact ?? '',
            ])->values();
            $materialPayload = $materials->map(fn ($m) => [
                'id' => (string) $m->id,
                'name' => $m->name,
                'unit_label' => $m->unit_label ?? $m->unit,
                'cost_per_unit' => (string) $m->cost_per_unit,
            ])->values();
            $pageOrders = $orders->getCollection();
            $activePoCount = $pageOrders->whereIn('status', [\App\Models\PurchaseOrder::Draft, \App\Models\PurchaseOrder::Approved, \App\Models\PurchaseOrder::Partial])->count();
            $draftPoCount = $pageOrders->where('status', \App\Models\PurchaseOrder::Draft)->count();
            $pettyPurchases = $pettyPurchases ?? collect();
            $sessions = $sessions ?? collect();
            $hasPettyErrors = $errors->hasAny(['description', 'amount', 'funding', 'cash_register_session_id', 'proof', 'raw_material_id', 'quantity']);
            $initialTab = request('tab') === 'petty' || $hasPettyErrors ? 'petty' : ($hasSupplierErrors ? 'suppliers' : 'orders');
            $sessionPayload = $sessions->map(fn ($s) => [
                'id' => (string) $s->id,
                'label' => ($s->register?->name ?? 'Register') . ' · #' . $s->id,
            ])->values();
        @endphp

        <div x-data="{
            activeTab: @js($initialTab),
            poFilterStatus: 'all',
            poSearch: '',
            createPoModal: @js($hasPoErrors),
            supplierModal: @js($hasSupplierErrors),
            suppliers: @js($supplierPayload),
            materials: @js($materialPayload),
            supplierId: @js((string) old('supplier_id', '')),
            supplierOpen: false,
            supplierSearch: '',
            poSubmitting: false,
            poClientError: '',
            supplierSubmitting: false,
            rows: @js($purchaseRows).map((item, index) => ({
                key: index,
                raw_material_id: item.raw_material_id ? String(item.raw_material_id) : '',
                quantity: item.quantity ?? '',
                unit_cost: item.unit_cost ?? '',
                open: false,
                search: '',
            })),
            nextKey: {{ count($purchaseRows) }},
            filteredSuppliers() {
                const q = this.supplierSearch.trim().toLowerCase();
                if (!q) return this.suppliers;
                return this.suppliers.filter(s =>
                    s.name.toLowerCase().includes(q) || (s.contact && s.contact.toLowerCase().includes(q))
                );
            },
            selectedSupplier() {
                return this.suppliers.find(s => s.id === String(this.supplierId)) || null;
            },
            selectSupplier(supplier) {
                this.supplierId = supplier.id;
                this.supplierOpen = false;
                this.supplierSearch = '';
                this.poClientError = '';
            },
            filteredMaterials(query) {
                const q = (query || '').trim().toLowerCase();
                if (!q) return this.materials;
                return this.materials.filter(m =>
                    m.name.toLowerCase().includes(q) || m.unit_label.toLowerCase().includes(q)
                );
            },
            materialById(id) {
                return this.materials.find(m => m.id === String(id)) || null;
            },
            selectMaterial(row, material) {
                row.raw_material_id = material.id;
                if (row.unit_cost === '' || row.unit_cost === null || row.unit_cost === undefined) {
                    row.unit_cost = material.cost_per_unit;
                } else {
                    row.unit_cost = material.cost_per_unit;
                }
                row.open = false;
                row.search = '';
                this.poClientError = '';
            },
            isMaterialUsedElsewhere(materialId, currentIndex) {
                return this.rows.some((r, idx) => idx !== currentIndex && r.raw_material_id === String(materialId));
            },
            addRow() {
                if (this.rows.length >= 50) return;
                this.rows.push({ key: this.nextKey++, raw_material_id: '', quantity: '', unit_cost: '', open: false, search: '' });
            },
            removeRow(index) {
                if (this.rows.length < 2) return;
                this.rows.splice(index, 1);
            },
            rowSubtotal(row) {
                const qty = parseFloat(row.quantity) || 0;
                const cost = parseFloat(row.unit_cost) || 0;
                return qty * cost;
            },
            poGrandTotal() {
                return this.rows.reduce((sum, row) => sum + this.rowSubtotal(row), 0);
            },
            formatRupiah(amount) {
                return 'Rp ' + Math.round(amount || 0).toLocaleString('id-ID');
            },
            openCreatePo(preselectedSupplierId = '') {
                if (preselectedSupplierId) {
                    this.supplierId = String(preselectedSupplierId);
                }
                this.poClientError = '';
                this.createPoModal = true;
            },
            validateAndSubmitPo(event) {
                if (this.poSubmitting) {
                    event.preventDefault();
                    return;
                }
                if (!this.supplierId) {
                    event.preventDefault();
                    this.poClientError = 'Pilih supplier terlebih dahulu.';
                    this.supplierOpen = true;
                    return;
                }
                const missingMaterial = this.rows.some(r => !r.raw_material_id);
                if (missingMaterial) {
                    event.preventDefault();
                    this.poClientError = 'Pastikan semua baris memiliki bahan baku yang dipilih.';
                    return;
                }
                this.poClientError = '';
                this.poSubmitting = true;
            },
            matchesPo(status, searchText) {
                const statusOk = this.poFilterStatus === 'all' || status === this.poFilterStatus;
                const q = this.poSearch.trim().toLowerCase();
                const searchOk = !q || searchText.toLowerCase().includes(q);
                return statusOk && searchOk;
            }
        }" @keydown.escape.window="createPoModal = false; supplierModal = false; supplierOpen = false">

            {{-- Page Header (Menyamai Inventory & Halaman Operasional Lain) --}}
            <div class="mb-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[20px] font-bold tracking-tight text-[#111827] md:text-[22px]">Pembelian</h1>
                    <p class="text-[13px] text-[#53565a]">Supplier, pesanan pembelian (PO), dan penerimaan Gudang Pusat.</p>
                </div>

                @can('manage-purchases')
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button type="button" @click="supplierModal = true"
                                class="inline-flex h-[36px] cursor-pointer items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[12px] font-semibold text-[#111827] shadow-[0px_1px_2px_rgba(20,21,20,0.03)] transition hover:bg-gray-50">
                            <i class="fas fa-truck-field text-[12px] text-[#53565a]" aria-hidden="true"></i>
                            <span>Tambah Supplier</span>
                        </button>

                        <button type="button" @click="openCreatePo()"
                                @if ($materials->isEmpty() || $suppliers->isEmpty()) title="Tambahkan supplier dan bahan baku terlebih dahulu" @endif
                                class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
                            <svg class="h-4 w-4 text-white" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 2a.75.75 0 01.75.75v4.5h4.5a.75.75 0 010 1.5h-4.5v4.5a.75.75 0 01-1.5 0v-4.5h-4.5a.75.75 0 010-1.5h4.5v-4.5A.75.75 0 018 2z" clip-rule="evenodd"/>
                            </svg>
                            <span>Buat PO</span>
                        </button>
                    </div>
                @endcan
            </div>

            {{-- KPI Summary Cards (3 Kolom seperti Manajemen Stok) --}}
            <div class="mb-5 grid grid-cols-1 gap-3.5 md:grid-cols-3">
                <div class="relative flex h-[112px] items-start justify-between overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-[#f9731f] p-4 text-white shadow-[0px_4px_14px_rgba(249,115,31,0.18)]">
                    <div class="pointer-events-none absolute -right-3 -top-2 flex h-[110px] w-[110px] items-center justify-center opacity-15">
                        <i class="fas fa-clipboard-list text-[84px]" aria-hidden="true"></i>
                    </div>
                    <div class="relative z-10 flex h-full flex-col justify-between">
                        <span class="text-[12px] font-medium leading-[15px] text-white/95">PO Menunggu / Aktif</span>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[26px] font-bold leading-tight tabular-nums text-white">{{ $activePoCount }}</span>
                            <span class="text-[11px] leading-[14px] text-white/90">{{ $draftPoCount }} menunggu persetujuan</span>
                        </div>
                    </div>
                </div>

                <div class="flex h-[112px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-medium text-[#53565a]">Total Pesanan Pembelian</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#fff6f0] text-[#df5826]">
                            <i class="fas fa-file-invoice text-[13px]" aria-hidden="true"></i>
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $orders->total() }}</span>
                        <span class="text-[11px] leading-[14px] text-[#7f837e]">Seluruh riwayat PO toko</span>
                    </div>
                </div>

                <div class="flex h-[112px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-medium text-[#53565a]">Supplier Terdaftar</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#ecfdf5] text-[#059669]">
                            <i class="fas fa-truck text-[13px]" aria-hidden="true"></i>
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $suppliers->count() }}</span>
                        <span class="text-[11px] leading-[14px] text-[#7f837e]">Mitra pemasok bahan baku</span>
                    </div>
                </div>
            </div>

            {{-- Sub-Tab Navigation --}}
            <div class="mb-4 flex items-center gap-6 border-b border-[#e9ebf0]">
                <button type="button" @click="activeTab = 'orders'"
                        :class="activeTab === 'orders' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                        class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                    <i class="fas fa-list-check text-[13px]" aria-hidden="true"></i>
                    <span>Daftar PO</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                          :class="activeTab === 'orders' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                        {{ $orders->total() }}
                    </span>
                </button>

                <button type="button" @click="activeTab = 'suppliers'"
                        :class="activeTab === 'suppliers' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                        class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                    <i class="fas fa-building text-[13px]" aria-hidden="true"></i>
                    <span>Daftar Supplier</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                          :class="activeTab === 'suppliers' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                        {{ $suppliers->count() }}
                    </span>
                </button>

                @canany(['record-inventory-controls', 'approve-inventory-controls'])
                    <button type="button" @click="activeTab = 'petty'"
                            :class="activeTab === 'petty' ? 'border-[#df5826] text-[#df5826] font-bold' : 'border-transparent text-[#53565a] hover:text-[#111827] font-medium'"
                            class="flex cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition">
                        <i class="fas fa-receipt text-[13px]" aria-hidden="true"></i>
                        <span>Pembelian Kecil</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                              :class="activeTab === 'petty' ? 'bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]' : 'bg-[#f1f3f5] text-[#53565a]'">
                            {{ $pettyPurchases->count() }}
                        </span>
                    </button>
                @endcanany
            </div>

            {{-- TAB 1: DAFTAR PO --}}
            <section x-show="activeTab === 'orders'" aria-labelledby="purchase-list" class="space-y-4">
                {{-- Filter Pills & Search --}}
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" @click="poFilterStatus = 'all'"
                                :class="poFilterStatus === 'all' ? 'bg-[#fff6f0] text-[#df5826] border-[#ffdec9] font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Semua
                        </button>
                        <button type="button" @click="poFilterStatus = 'DRAFT'"
                                :class="poFilterStatus === 'DRAFT' ? 'bg-amber-50 text-amber-700 border-amber-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Menunggu Persetujuan
                        </button>
                        <button type="button" @click="poFilterStatus = 'APPROVED'"
                                :class="poFilterStatus === 'APPROVED' ? 'bg-sky-50 text-sky-700 border-sky-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Disetujui
                        </button>
                        <button type="button" @click="poFilterStatus = 'PARTIAL'"
                                :class="poFilterStatus === 'PARTIAL' ? 'bg-violet-50 text-violet-700 border-violet-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Diterima Sebagian
                        </button>
                        <button type="button" @click="poFilterStatus = 'RECEIVED'"
                                :class="poFilterStatus === 'RECEIVED' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-3 py-1.5 text-[12px] transition">
                            Selesai
                        </button>
                    </div>

                    <div class="flex h-[36px] w-full items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3 shadow-2xs transition focus-within:border-[#df5826] sm:w-64">
                        <i class="fas fa-search text-[12px] text-gray-400" aria-hidden="true"></i>
                        <input type="text" x-model="poSearch" placeholder="Cari nomor PO atau supplier..."
                               class="w-full border-none bg-transparent text-[12px] text-[#111827] placeholder-[#7f837e] outline-none">
                        <button type="button" x-show="poSearch.length > 0" @click="poSearch = ''" class="cursor-pointer text-xs text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                {{-- PO Table Card --}}
                <div class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    <h2 id="purchase-list" class="sr-only">Daftar PO</h2>
                    <div class="overflow-x-auto" role="region" aria-labelledby="purchase-list" tabindex="0">
                        <table class="w-full min-w-[600px] text-left text-[13px]">
                            <thead class="border-b border-[#e9ebf0] bg-[#f8f9fa] text-[11px] font-semibold uppercase tracking-wider text-[#53565a]">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Nomor PO</th>
                                    <th scope="col" class="px-4 py-3">Supplier</th>
                                    <th scope="col" class="px-4 py-3 text-right">Jumlah Bahan</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#f1f3f5]">
                                @forelse ($orders as $purchase)
                                    <tr x-show="matchesPo(@js($purchase->status), @js('PO #' . $purchase->id . ' ' . $purchase->supplier->name))"
                                        class="transition hover:bg-[#fafaf9]">
                                        <th scope="row" class="px-5 py-3.5 font-semibold text-[#111827]">
                                            <a href="{{ route('admin.purchases.show', $purchase) }}"
                                               class="inline-flex items-center gap-2 font-bold text-[#111827] transition hover:text-[#df5826]">
                                                <span class="rounded-md bg-[#fff6f0] px-2 py-0.5 text-[12px] font-bold text-[#df5826]">PO #{{ $purchase->id }}</span>
                                                <span class="text-[11px] font-normal text-[#7f837e]">{{ $purchase->created_at?->format('d M Y') }}</span>
                                            </a>
                                        </th>
                                        <td class="px-4 py-3.5">
                                            <div class="font-medium text-[#111827]">{{ $purchase->supplier->name }}</div>
                                            @if ($purchase->supplier->contact)
                                                <div class="text-[11px] text-[#7f837e]">{{ $purchase->supplier->contact }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 text-right tabular-nums font-medium text-[#111827]">
                                            {{ $purchase->items_count }} item
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $statusTone($purchase->status) }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($purchase->status) }}"></span>
                                                {{ $purchase->statusLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            <a href="{{ route('admin.purchases.show', $purchase) }}"
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
                                                    <i class="fas fa-clipboard-list text-lg" aria-hidden="true"></i>
                                                </div>
                                                <p class="text-[14px] font-bold text-[#111827]">Belum ada Pesanan Pembelian</p>
                                                <p class="text-[12px] text-[#7f837e]">Buat PO pertama Anda untuk memesan bahan baku ke supplier.</p>
                                                @can('manage-purchases')
                                                    @if ($materials->isNotEmpty() && $suppliers->isNotEmpty())
                                                        <button type="button" @click="openCreatePo()"
                                                                class="mt-2 inline-flex h-[34px] cursor-pointer items-center gap-1.5 rounded-[9px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white transition hover:bg-[#c94d00]">
                                                            <i class="fas fa-plus text-[11px]" aria-hidden="true"></i>
                                                            <span>Buat PO</span>
                                                        </button>
                                                    @endif
                                                @endcan
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

            {{-- TAB 2: DAFTAR SUPPLIER --}}
            <section x-show="activeTab === 'suppliers'" x-cloak aria-labelledby="supplier-list" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="supplier-list" class="text-[14px] font-bold text-[#111827]">Mitra Supplier</h2>
                        <p class="text-[12px] text-[#7f837e]">Daftar pemasok bahan baku yang terhubung dengan Gudang Pusat.</p>
                    </div>
                    @can('manage-purchases')
                        <button type="button" @click="supplierModal = true"
                                class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
                            <i class="fas fa-plus text-[11px]" aria-hidden="true"></i>
                            <span>Tambah Supplier</span>
                        </button>
                    @endcan
                </div>

                @if ($suppliers->isEmpty())
                    <div class="rounded-[14px] border border-[#e9ebf0] bg-white p-10 text-center">
                        <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#fff6f0] text-[#df5826]">
                                <i class="fas fa-truck-field text-lg" aria-hidden="true"></i>
                            </div>
                            <p class="text-[14px] font-bold text-[#111827]">Belum ada Supplier</p>
                            <p class="text-[12px] text-[#7f837e]">Tambahkan supplier terlebih dahulu sebelum membuat Purchase Order.</p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($suppliers as $supplier)
                            <div class="flex flex-col justify-between gap-3 rounded-[14px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] transition hover:border-[#ffd2b8]">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-[#fff6f0] text-[14px] font-bold text-[#df5826]">
                                        {{ strtoupper(mb_substr($supplier->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate text-[14px] font-bold text-[#111827]">{{ $supplier->name }}</h3>
                                        <p class="mt-0.5 truncate text-[12px] text-[#53565a]">
                                            {{ $supplier->contact ?: 'Belum ada kontak tercatat' }}
                                        </p>
                                    </div>
                                </div>
                                @can('manage-purchases')
                                    @if ($materials->isNotEmpty())
                                        <div class="border-t border-[#f1f3f5] pt-3">
                                            <button type="button" @click="openCreatePo({{ $supplier->id }})"
                                                    class="inline-flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-[9px] border border-[#e9ebf0] bg-[#f8f9fa] px-3 py-2 text-[12px] font-semibold text-[#111827] transition hover:border-[#df5826] hover:bg-[#fff6f0] hover:text-[#df5826]">
                                                <i class="fas fa-plus text-[10px]" aria-hidden="true"></i>
                                                <span>Buat PO ke Supplier Ini</span>
                                            </button>
                                        </div>
                                    @endif
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- TAB 3: PEMBELIAN KECIL --}}
            @canany(['record-inventory-controls', 'approve-inventory-controls'])
                <section x-show="activeTab === 'petty'" x-cloak class="space-y-5">
                    @can('record-inventory-controls')
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.controls.purchases.store') }}"
                              x-data="{
                                  funding: @js(old('funding', 'EXTERNAL')),
                                  sessions: @js($sessionPayload),
                                  sessionId: @js((string) old('cash_register_session_id', '')),
                                  sessionOpen: false,
                                  rawMaterialId: @js((string) old('raw_material_id', '')),
                                  matOpen: false,
                                  matSearch: '',
                                  submitting: false,
                                  selectedSession() {
                                      return this.sessions.find(s => s.id === String(this.sessionId)) || null;
                                  },
                                  filteredPettyMaterials() {
                                      const q = this.matSearch.trim().toLowerCase();
                                      if (!q) return this.materials;
                                      return this.materials.filter(m => m.name.toLowerCase().includes(q) || m.unit_label.toLowerCase().includes(q));
                                  },
                                  selectedPettyMaterial() {
                                      return this.materials.find(m => m.id === String(this.rawMaterialId)) || null;
                                  }
                              }"
                              @submit="submitting = true"
                              class="space-y-4 rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                            @csrf
                            <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                            <input type="hidden" name="return_to" value="{{ route('admin.purchases.index', ['tab' => 'petty'], false) }}">
                            <input type="hidden" name="funding" :value="funding">
                            <input type="hidden" name="cash_register_session_id" :value="sessionId" :disabled="funding !== 'DRAWER'">
                            <input type="hidden" name="raw_material_id" :value="rawMaterialId">

                            <div class="border-b border-[#e9ebf0] pb-3">
                                <h2 class="text-[15px] font-bold text-[#111827]">Ajukan pembelian kecil</h2>
                                <p class="text-[12px] text-[#53565a]">Catat belanja operasional mendadak lengkap dengan bukti kuitansi.</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="space-y-1.5">
                                    <label for="po-petty-desc" class="block text-[12px] font-semibold text-[#111827]">Keperluan pembelian <span class="text-rose-500">*</span></label>
                                    <input id="po-petty-desc" name="description" maxlength="150" value="{{ old('description') }}" placeholder="Contoh: Beli es batu darurat"
                                           class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="po-petty-amount" class="block text-[12px] font-semibold text-[#111827]">Nominal (Rp) <span class="text-rose-500">*</span></label>
                                    <input id="po-petty-amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="0"
                                           class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                </div>

                                <div class="space-y-1.5">
                                    <span class="block text-[12px] font-semibold text-[#111827]">Sumber dana <span class="text-rose-500">*</span></span>
                                    <div class="grid h-[40px] grid-cols-2 gap-1 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] p-1">
                                        <button type="button" @click="funding = 'EXTERNAL'"
                                                :class="funding === 'EXTERNAL' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                                class="cursor-pointer rounded-[7px] text-[11px] transition">Luar Kasir</button>
                                        <button type="button" @click="if (sessions.length) funding = 'DRAWER'" :disabled="sessions.length === 0"
                                                :class="funding === 'DRAWER' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                                class="cursor-pointer rounded-[7px] text-[11px] transition disabled:cursor-not-allowed disabled:opacity-45">Laci Kasir</button>
                                    </div>
                                </div>

                                <div x-show="funding === 'DRAWER'" x-cloak class="relative space-y-1.5" @click.outside="sessionOpen = false">
                                    <span class="block text-[12px] font-semibold text-[#111827]">Shift kasir saya <span class="text-rose-500">*</span></span>
                                    <button type="button" @click="sessionOpen = !sessionOpen"
                                            class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                            :class="sessionOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                        <span class="truncate" :class="selectedSession() ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                              x-text="selectedSession() ? selectedSession().label : 'Pilih shift aktif'"></span>
                                        <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                                    </button>
                                    <div x-show="sessionOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white p-1 shadow-xl">
                                        <template x-for="s in sessions" :key="s.id">
                                            <button type="button" @click="sessionId = s.id; sessionOpen = false"
                                                    class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                    :class="String(sessionId) === s.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                <span x-text="s.label"></span>
                                                <i x-show="String(sessionId) === s.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <div class="relative space-y-1.5" @click.outside="matOpen = false">
                                    <span class="block text-[12px] font-semibold text-[#111827]">Bahan yang dibeli (opsional)</span>
                                    <button type="button" @click="matOpen = !matOpen"
                                            class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                            :class="matOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                        <span class="truncate" :class="selectedPettyMaterial() ? 'font-medium text-[#111827]' : 'text-[#53565a]'"
                                              x-text="selectedPettyMaterial() ? (selectedPettyMaterial().name + ' (' + selectedPettyMaterial().unit_label + ')') : 'Tanpa penerimaan stok'"></span>
                                        <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                                    </button>
                                    <div x-show="matOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                        <div class="border-b border-[#f1f3f5] p-2">
                                            <div class="flex items-center gap-2 rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 focus-within:border-[#df5826] focus-within:bg-white">
                                                <i class="fas fa-search text-[11px] text-gray-400"></i>
                                                <input type="text" x-model="matSearch" placeholder="Cari bahan..."
                                                       class="w-full border-none bg-transparent text-[12px] text-[#111827] outline-none">
                                            </div>
                                        </div>
                                        <div class="max-h-52 overflow-y-auto p-1">
                                            <button type="button" @click="rawMaterialId = ''; matOpen = false; matSearch = ''"
                                                    class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                    :class="rawMaterialId === '' ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#53565a] hover:bg-[#f8f9fa]'">
                                                <span>Tanpa penerimaan stok</span>
                                                <i x-show="rawMaterialId === ''" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                            </button>
                                            <template x-for="m in filteredPettyMaterials()" :key="m.id">
                                                <button type="button" @click="rawMaterialId = m.id; matOpen = false; matSearch = ''"
                                                        class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                        :class="String(rawMaterialId) === m.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                    <span class="truncate" x-text="m.name + ' (' + m.unit_label + ')'"></span>
                                                    <i x-show="String(rawMaterialId) === m.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="po-petty-qty" class="block text-[12px] font-semibold text-[#111827]">
                                        Jumlah bahan <span x-show="selectedPettyMaterial()" x-text="'(' + selectedPettyMaterial()?.unit_label + ')'"></span>
                                    </label>
                                    <input id="po-petty-qty" name="quantity" type="number" min="0.000001" step="0.000001" value="{{ old('quantity') }}" placeholder="0"
                                           class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]">
                                </div>

                                <div class="space-y-1.5">
                                    <label for="po-petty-proof" class="block text-[12px] font-semibold text-[#111827]">Bukti kuitansi (foto/PDF, maks. 5 MB) <span class="text-rose-500">*</span></label>
                                    <input id="po-petty-proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           class="block w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 py-2 text-[12px] text-[#53565a] file:mr-3 file:rounded-lg file:border-0 file:bg-[#fff6f0] file:px-3 file:py-1 file:text-[11px] file:font-semibold file:text-[#df5826] hover:file:bg-[#ffe8d9]" required>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="po-petty-notes" class="block text-[12px] font-semibold text-[#111827]">Catatan</label>
                                    <input id="po-petty-notes" name="notes" maxlength="250" value="{{ old('notes') }}" placeholder="Opsional"
                                           class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" :disabled="submitting"
                                        class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                    <i class="fas fa-paper-plane text-[11px]" aria-hidden="true"></i>
                                    <span x-text="submitting ? 'Mengajukan...' : 'Ajukan pembelian'">Ajukan pembelian</span>
                                </button>
                            </div>
                        </form>
                    @endcan

                    {{-- Riwayat Pembelian Kecil --}}
                    <div class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] space-y-3">
                        <h2 class="text-[15px] font-bold text-[#111827]">Riwayat pembelian kecil</h2>
                        <div class="space-y-3">
                            @forelse ($pettyPurchases as $petty)
                                @php
                                    $pettyTone = match ($petty->status) {
                                        'PENDING' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'REJECTED' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        default => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    };
                                @endphp
                                <article class="flex flex-col gap-3 rounded-[12px] border border-[#e9ebf0] bg-[#fafaf9]/60 p-4 text-[13px]">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2.5">
                                            <span class="rounded-md bg-[#fff6f0] px-2 py-0.5 text-[12px] font-bold text-[#df5826]">#{{ $petty->id }}</span>
                                            <h3 class="text-[14px] font-bold text-[#111827]">{{ $petty->description }}</h3>
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $pettyTone }}">
                                                {{ $petty->statusLabel() }}
                                            </span>
                                        </div>
                                        <span class="text-[11px] text-[#7f837e]">
                                            Dicatat oleh <strong class="text-[#53565a]">{{ $petty->requester?->name ?? '—' }}</strong>
                                            @if ($petty->reviewed_at) · Ditinjau oleh <strong class="text-[#53565a]">{{ $petty->reviewer?->name ?? '—' }}</strong> @endif
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-[12px]">
                                        <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 font-bold tabular-nums text-[#111827]">
                                            Rp{{ $number($petty->amount) }}
                                        </span>
                                        <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 text-[#53565a]">
                                            {{ $petty->funding === 'DRAWER' ? 'Laci kasir · shift #' . $petty->cash_register_session_id : 'Dana di luar kasir' }}
                                        </span>
                                        @if ($petty->rawMaterial)
                                            <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 font-medium text-[#111827]">
                                                {{ $number($petty->quantity) }} {{ $petty->unit }} {{ $petty->rawMaterial->name }}
                                            </span>
                                        @endif
                                        <a href="{{ route('admin.controls.purchases.proof', $petty) }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 font-semibold text-[#df5826] transition hover:bg-[#ffe8d9]">
                                            <i class="fas fa-download text-[10px]" aria-hidden="true"></i>
                                            <span>Unduh bukti #{{ $petty->id }}</span>
                                        </a>
                                    </div>

                                    @if ($petty->status === 'APPROVED' && $petty->raw_material_id && $petty->received_at === null)
                                        @can('record-inventory-controls')
                                            <form method="POST" action="{{ route('admin.controls.purchases.receive', $petty) }}">
                                                @csrf
                                                <input type="hidden" name="return_to" value="{{ route('admin.purchases.index', ['tab' => 'petty'], false) }}">
                                                <button class="inline-flex h-[34px] cursor-pointer items-center gap-1.5 rounded-[9px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white transition hover:bg-[#c94d00]">
                                                    <i class="fas fa-box-open text-[11px]" aria-hidden="true"></i>
                                                    <span>Konfirmasi barang masuk</span>
                                                </button>
                                            </form>
                                        @endcan
                                    @endif

                                    @can('approve-inventory-controls')
                                        @if ($petty->status === 'PENDING')
                                            <form method="POST" action="{{ route('admin.controls.purchases.review', $petty) }}"
                                                  class="flex flex-wrap items-end gap-3 border-t border-[#e9ebf0] pt-3">
                                                @csrf
                                                <input type="hidden" name="return_to" value="{{ route('admin.purchases.index', ['tab' => 'petty'], false) }}">
                                                <label class="min-w-[220px] flex-1 space-y-1 text-[12px] font-semibold text-[#111827]">
                                                    <span>Catatan keputusan (wajib jika ditolak)</span>
                                                    <input name="decision_note" maxlength="250" placeholder="Opsional saat setuju, wajib saat tolak"
                                                           class="h-[36px] w-full rounded-[9px] border border-[#e9ebf0] bg-white px-3 text-[12px] text-[#111827] outline-none focus:border-[#df5826]">
                                                </label>
                                                <div class="flex items-center gap-2">
                                                    <button name="decision" value="approve"
                                                            class="h-[36px] cursor-pointer rounded-[9px] bg-[#df5826] px-4 text-[12px] font-semibold text-white transition hover:bg-[#c94d00]">Setujui</button>
                                                    <button name="decision" value="reject"
                                                            class="h-[36px] cursor-pointer rounded-[9px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-rose-600 transition hover:bg-rose-50">Tolak</button>
                                                </div>
                                            </form>
                                        @endif
                                    @endcan
                                </article>
                            @empty
                                <p class="py-8 text-center text-[13px] text-[#7f837e]">Belum ada catatan pembelian kecil.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            @endcanany

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- MODAL: BUAT PO BARU (Custom Searchable Dropdowns)          --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            @can('manage-purchases')
                <div x-show="createPoModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]"
                     @click.self="createPoModal = false">
                    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-[18px] border border-[#e9ebf0] bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-[#e9ebf0] px-6 py-4">
                            <div>
                                <h2 class="text-[16px] font-bold text-[#111827]">Buat PO</h2>
                                <p class="text-[12px] text-[#53565a]">Pilih supplier dan daftar bahan baku yang akan dipesan ke Gudang Pusat.</p>
                            </div>
                            <button type="button" @click="createPoModal = false"
                                    class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#53565a] transition hover:bg-gray-100 hover:text-[#111827]"
                                    aria-label="Tutup modal Buat PO">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        @if ($materials->isEmpty() || $suppliers->isEmpty())
                            <div class="p-6 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                                    <i class="fas fa-circle-info text-lg"></i>
                                </div>
                                <p class="text-[14px] font-bold text-[#111827]">Data Belum Lengkap</p>
                                <p class="mt-1 text-[13px] text-[#53565a]">Tambahkan supplier dan bahan baku untuk membuat PO.</p>
                                <div class="mt-4 flex justify-center gap-2">
                                    @if ($suppliers->isEmpty())
                                        <button type="button" @click="createPoModal = false; supplierModal = true"
                                                class="cursor-pointer rounded-[10px] bg-[#df5826] px-4 py-2 text-[12px] font-semibold text-white hover:bg-[#c94d00]">
                                            Tambah Supplier
                                        </button>
                                    @endif
                                    <button type="button" @click="createPoModal = false"
                                            class="cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 py-2 text-[12px] font-semibold text-[#111827] hover:bg-gray-50">
                                        Tutup
                                    </button>
                                </div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('admin.purchases.store') }}" @submit="validateAndSubmitPo($event)" class="flex min-h-0 flex-1 flex-col">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                                <input type="hidden" name="supplier_id" :value="supplierId">

                                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                                    <div x-show="poClientError" x-cloak class="flex items-center gap-2 rounded-[10px] border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] font-semibold text-rose-700">
                                        <i class="fas fa-circle-exclamation"></i>
                                        <span x-text="poClientError"></span>
                                    </div>

                                    {{-- Supplier & Catatan --}}
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        {{-- Custom Searchable Supplier Dropdown --}}
                                        <div class="relative space-y-1.5" @click.outside="supplierOpen = false">
                                            <label class="block text-[12px] font-semibold text-[#111827]">
                                                Supplier <span class="text-rose-500">*</span>
                                            </label>
                                            <button type="button" @click="supplierOpen = !supplierOpen"
                                                    class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                                    :class="supplierOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                                <span class="truncate" :class="selectedSupplier() ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                                      x-text="selectedSupplier() ? selectedSupplier().name : 'Pilih supplier'"></span>
                                                <i class="fas fa-chevron-down text-[11px] text-[#7f837e] transition-transform"
                                                   :class="supplierOpen ? 'rotate-180 text-[#df5826]' : ''" aria-hidden="true"></i>
                                            </button>

                                            <div x-show="supplierOpen" x-cloak
                                                 class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                                <div class="border-b border-[#f1f3f5] p-2">
                                                    <div class="flex items-center gap-2 rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 focus-within:border-[#df5826] focus-within:bg-white">
                                                        <i class="fas fa-search text-[11px] text-gray-400"></i>
                                                        <input type="text" x-model="supplierSearch" placeholder="Cari supplier..."
                                                               class="w-full border-none bg-transparent text-[12px] text-[#111827] outline-none">
                                                    </div>
                                                </div>
                                                <div class="max-h-52 overflow-y-auto p-1">
                                                    <template x-for="sup in filteredSuppliers()" :key="sup.id">
                                                        <button type="button" @click="selectSupplier(sup)"
                                                                class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                                :class="String(supplierId) === sup.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                            <div class="min-w-0">
                                                                <div class="truncate" x-text="sup.name"></div>
                                                                <div x-show="sup.contact" class="truncate text-[11px] text-[#7f837e]" x-text="sup.contact"></div>
                                                            </div>
                                                            <i x-show="String(supplierId) === sup.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                                        </button>
                                                    </template>
                                                    <div x-show="filteredSuppliers().length === 0" class="px-3 py-4 text-center text-[12px] text-[#7f837e]">
                                                        Supplier tidak ditemukan.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-1.5">
                                            <label for="po-notes-input" class="block text-[12px] font-semibold text-[#111827]">Catatan PO</label>
                                            <input id="po-notes-input" name="notes" maxlength="250" value="{{ old('notes') }}"
                                                   placeholder="Opsional (jadwal kirim, catatan khusus)"
                                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826] focus:ring-2 focus:ring-[#df5826]/15">
                                        </div>
                                    </div>

                                    {{-- Daftar Item Barang --}}
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[13px] font-bold text-[#111827]">Daftar Bahan Baku</span>
                                            <button type="button" @click="addRow()" :disabled="rows.length >= 50"
                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-[8px] border border-[#ffdec9] bg-[#fff6f0] px-3 py-1.5 text-[12px] font-semibold text-[#df5826] transition hover:bg-[#ffe8d9] disabled:cursor-not-allowed disabled:opacity-50">
                                                <i class="fas fa-plus text-[10px]" aria-hidden="true"></i>
                                                <span>Tambah Bahan</span>
                                            </button>
                                        </div>

                                        <template x-for="(row, index) in rows" :key="row.key">
                                            <div class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-3.5">
                                                <div class="mb-2.5 flex items-center justify-between">
                                                    <span class="text-[12px] font-bold text-[#111827]" x-text="'Barang #' + (index + 1)"></span>
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-[11px] font-semibold tabular-nums text-[#53565a]"
                                                              x-show="rowSubtotal(row) > 0"
                                                              x-text="'Subtotal: ' + formatRupiah(rowSubtotal(row))"></span>
                                                        <button type="button" @click="removeRow(index)" :disabled="rows.length < 2"
                                                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-35"
                                                                title="Hapus baris">
                                                            <i class="fas fa-trash-can text-[12px]" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="grid gap-3 sm:grid-cols-12">
                                                    {{-- Custom Searchable Material Dropdown --}}
                                                    <div class="relative space-y-1 sm:col-span-6" @click.outside="row.open = false">
                                                        <label class="block text-[11px] font-semibold text-[#53565a]">Bahan Baku</label>
                                                        <input type="hidden" :name="'items[' + index + '][raw_material_id]'" :value="row.raw_material_id">
                                                        <button type="button" @click="row.open = !row.open"
                                                                class="flex h-[38px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3 text-left text-[12px] transition"
                                                                :class="row.open ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                                            <span class="truncate"
                                                                  :class="materialById(row.raw_material_id) ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                                                  x-text="materialById(row.raw_material_id) ? (materialById(row.raw_material_id).name + ' (' + materialById(row.raw_material_id).unit_label + ')') : 'Pilih bahan'"></span>
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
                                                            <div class="max-h-56 overflow-y-auto p-1">
                                                                <template x-for="mat in filteredMaterials(row.search)" :key="mat.id">
                                                                    <button type="button" @click="selectMaterial(row, mat)"
                                                                            class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                                            :class="row.raw_material_id === mat.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                                                        <div class="min-w-0">
                                                                            <div class="truncate">
                                                                                <span x-text="mat.name"></span>
                                                                                <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-[#53565a]" x-text="mat.unit_label"></span>
                                                                            </div>
                                                                            <div class="text-[11px] text-[#7f837e]" x-text="'HPP: ' + formatRupiah(mat.cost_per_unit)"></div>
                                                                        </div>
                                                                        <div class="flex shrink-0 items-center gap-1.5">
                                                                            <span x-show="isMaterialUsedElsewhere(mat.id, index)" class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Dipilih</span>
                                                                            <i x-show="row.raw_material_id === mat.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                                                        </div>
                                                                    </button>
                                                                </template>
                                                                <div x-show="filteredMaterials(row.search).length === 0" class="px-3 py-4 text-center text-[12px] text-[#7f837e]">
                                                                    Bahan tidak ditemukan.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="space-y-1 sm:col-span-3">
                                                        <label class="block text-[11px] font-semibold text-[#53565a]">
                                                            Jumlah <span x-show="materialById(row.raw_material_id)" x-text="'(' + materialById(row.raw_material_id)?.unit_label + ')'"></span>
                                                        </label>
                                                        <input :name="'items[' + index + '][quantity]'" x-model="row.quantity" type="number" min="0.000001" step="0.000001" placeholder="0"
                                                               class="h-[38px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 text-[12px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                                    </div>

                                                    <div class="space-y-1 sm:col-span-3">
                                                        <label class="block text-[11px] font-semibold text-[#53565a]">Harga Satuan (Rp)</label>
                                                        <input :name="'items[' + index + '][unit_cost]'" x-model="row.unit_cost" type="number" min="0" step="0.000001" placeholder="0"
                                                               class="h-[38px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 text-[12px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#e9ebf0] bg-[#f8f9fa] px-6 py-4">
                                    <div>
                                        <span class="block text-[11px] font-medium text-[#53565a]">Estimasi Total PO</span>
                                        <span class="text-[16px] font-bold tabular-nums text-[#111827]" x-text="formatRupiah(poGrandTotal())">Rp 0</span>
                                    </div>
                                    <div class="flex items-center gap-2.5">
                                        <button type="button" @click="createPoModal = false"
                                                class="h-[38px] cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] transition hover:bg-gray-50">
                                            Batal
                                        </button>
                                        <button type="submit" :disabled="poSubmitting"
                                                class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                            <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                                            <span x-text="poSubmitting ? 'Menyimpan...' : 'Simpan PO'">Simpan PO</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- MODAL: TAMBAH SUPPLIER                                     --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div x-show="supplierModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]"
                     @click.self="supplierModal = false">
                    <div class="w-full max-w-md overflow-hidden rounded-[18px] border border-[#e9ebf0] bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-[#e9ebf0] px-6 py-4">
                            <div>
                                <h2 class="text-[16px] font-bold text-[#111827]">Tambah Supplier</h2>
                                <p class="text-[12px] text-[#53565a]">Daftarkan mitra pemasok bahan baku baru.</p>
                            </div>
                            <button type="button" @click="supplierModal = false"
                                    class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#53565a] transition hover:bg-gray-100 hover:text-[#111827]"
                                    aria-label="Tutup modal Tambah Supplier">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.purchases.suppliers.store') }}" @submit="supplierSubmitting = true" class="space-y-4 p-6">
                            @csrf
                            <div class="space-y-1.5">
                                <label for="supplier-name-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Nama Supplier <span class="text-rose-500">*</span>
                                </label>
                                <input id="supplier-name-input" name="name" value="{{ old('name') }}" maxlength="120"
                                       placeholder="Contoh: PT Sumber Pangan Nusantara"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826] focus:ring-2 focus:ring-[#df5826]/15" required>
                            </div>
                            <div class="space-y-1.5">
                                <label for="supplier-contact-input" class="block text-[12px] font-semibold text-[#111827]">
                                    Kontak
                                </label>
                                <input id="supplier-contact-input" name="contact" value="{{ old('contact') }}" maxlength="160"
                                       placeholder="No. WhatsApp / Email / Alamat singkat"
                                       class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826] focus:ring-2 focus:ring-[#df5826]/15">
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-2">
                                <button type="button" @click="supplierModal = false"
                                        class="h-[38px] cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] transition hover:bg-gray-50">
                                    Batal
                                </button>
                                <button type="submit" :disabled="supplierSubmitting"
                                        class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                                    <i class="fas fa-plus text-[11px]" aria-hidden="true"></i>
                                    <span x-text="supplierSubmitting ? 'Menyimpan...' : 'Tambah Supplier'">Tambah Supplier</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    @endisset
</x-cafe-operation-page>
