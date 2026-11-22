<x-cafe-operation-page title="Kontrol Operasional" :shop="$shop">
    @php
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');
        $prior = collect(old('items'))->filter(fn ($line) => is_array($line) && array_key_exists('raw_material_id', $line))->values();
        $countRows = $prior->isNotEmpty() ? $prior->all() : [['raw_material_id' => '', 'counted_quantity' => '']];
        $materialPayload = $materials->map(fn ($m) => [
            'id' => (string) $m->id,
            'name' => $m->name,
            'unit' => $m->unit,
        ])->values();
        $sessionPayload = $sessions->map(fn ($s) => [
            'id' => (string) $s->id,
            'label' => ($s->register?->name ?? 'Register') . ' · #' . $s->id,
        ])->values();
        $pageRecords = $records->getCollection();
        $pendingCount = $pageRecords->where('status', 'PENDING')->count();
        $processedCount = $pageRecords->whereNotIn('status', ['PENDING', 'REJECTED'])->count();
        $statusTone = fn (string $status): string => match ($status) {
            'PENDING' => 'bg-amber-50 text-amber-700 border-amber-200',
            'REJECTED' => 'bg-rose-50 text-rose-700 border-rose-200',
            'POSTED', 'APPROVED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            default => 'bg-sky-50 text-sky-700 border-sky-200',
        };
        $statusDot = fn (string $status): string => match ($status) {
            'PENDING' => 'bg-amber-500',
            'REJECTED' => 'bg-rose-500',
            'POSTED', 'APPROVED' => 'bg-emerald-500',
            default => 'bg-sky-500',
        };
    @endphp

    <div x-data="{
        historyStatus: 'all',
        historySearch: '',
        matchesRecord(status, text) {
            const statusOk = this.historyStatus === 'all' || status === this.historyStatus;
            const q = this.historySearch.trim().toLowerCase();
            const searchOk = !q || text.toLowerCase().includes(q);
            return statusOk && searchOk;
        }
    }" class="space-y-5">
        {{-- Page Header --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div class="flex flex-col gap-1">
                <h1 class="text-[20px] font-bold tracking-tight text-[#111827] md:text-[22px]">Kontrol Operasional</h1>
                <p class="text-[13px] text-[#53565a]">Permintaan pengiriman stok dari Gudang Pusat atau Central Kitchen ke Outlet.</p>
            </div>

            @can('view-inventory-variance')
                <a href="{{ route('admin.controls.variance') }}"
                   class="inline-flex h-[36px] w-fit items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[12px] font-semibold text-[#111827] shadow-[0px_1px_2px_rgba(20,21,20,0.03)] transition hover:border-[#df5826] hover:text-[#df5826]">
                    <i class="fas fa-scale-balanced text-[12px] text-[#df5826]" aria-hidden="true"></i>
                    <span>Laporan selisih</span>
                </a>
            @endcan
        </div>

        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-1 gap-3.5 md:grid-cols-3">
            <div class="relative flex h-[108px] items-start justify-between overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-[#f9731f] p-4 text-white shadow-[0px_4px_14px_rgba(249,115,31,0.18)]">
                <div class="pointer-events-none absolute -right-3 -top-2 flex h-[106px] w-[106px] items-center justify-center opacity-15">
                    <i class="fas fa-sliders text-[76px]" aria-hidden="true"></i>
                </div>
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <span class="text-[12px] font-medium leading-[15px] text-white/95">Menunggu Tinjauan</span>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[26px] font-bold leading-tight tabular-nums text-white">{{ $pendingCount }}</span>
                        <span class="text-[11px] leading-[14px] text-white/90">Catatan berstatus menunggu di halaman ini</span>
                    </div>
                </div>
            </div>

            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <span class="text-[12px] font-medium text-[#53565a]">Total {{ $tabs[$tab] }}</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#fff6f0] text-[#df5826]">
                        <i class="fas fa-clipboard-list text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $records->total() }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Riwayat tercatat pada tab aktif</span>
                </div>
            </div>

            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <span class="text-[12px] font-medium text-[#53565a]">Disetujui / Selesai</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#ecfdf5] text-[#059669]">
                        <i class="fas fa-circle-check text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $processedCount }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Catatan sudah ditindaklanjuti</span>
                </div>
            </div>
        </div>

        {{-- Sub-Tab Navigation (Fokus Permintaan Barang; Stok Opname dipindah ke Bahan & Stok, Pembelian Kecil ke Pembelian) --}}
        <nav class="flex flex-wrap items-center justify-between gap-4 border-b border-[#e9ebf0]" aria-label="Kontrol operasional">
            <div class="flex items-center gap-6">
                <a href="{{ route('admin.controls.index', ['tab' => 'requests']) }}"
                   @if ($tab === 'requests') aria-current="page" @endif
                   class="flex items-center gap-2 border-b-2 pb-3 pt-1 text-[13px] transition {{ $tab === 'requests' ? 'border-[#df5826] font-bold text-[#df5826]' : 'border-transparent font-medium text-[#53565a] hover:text-[#111827]' }}">
                    <i class="fas fa-boxes-packing text-[13px]" aria-hidden="true"></i>
                    <span>Permintaan barang</span>
                </a>
            </div>
            <div class="flex flex-wrap items-center gap-3 pb-2 text-[11px] text-[#7f837e]">
                <a href="{{ route('admin.inventory.index', ['subtab' => 'opname']) }}" class="inline-flex items-center gap-1 hover:text-[#df5826]">
                    <i class="fas fa-list-check" aria-hidden="true"></i>
                    <span>Stok opname di Bahan &amp; Stok</span>
                </a>
                <span>·</span>
                <a href="{{ route('admin.purchases.index', ['tab' => 'petty']) }}" class="inline-flex items-center gap-1 hover:text-[#df5826]">
                    <i class="fas fa-receipt" aria-hidden="true"></i>
                    <span>Pembelian kecil di Pembelian</span>
                </a>
            </div>
        </nav>

        {{-- FORM INPUT SESUAI TAB --}}
        @can('record-inventory-controls')
            @if ($tab === 'requests' && $materials->isNotEmpty())
                <form method="POST" action="{{ route('admin.controls.requests.store') }}"
                      x-data="{
                          materials: @js($materialPayload),
                          source: @js(old('source', 'warehouse')),
                          rawMaterialId: @js((string) old('raw_material_id', '')),
                          matOpen: false,
                          matSearch: '',
                          submitting: false,
                          clientError: '',
                          filteredMaterials() {
                              const q = this.matSearch.trim().toLowerCase();
                              if (!q) return this.materials;
                              return this.materials.filter(m => m.name.toLowerCase().includes(q) || m.unit.toLowerCase().includes(q));
                          },
                          selectedMaterial() {
                              return this.materials.find(m => m.id === String(this.rawMaterialId)) || null;
                          },
                          selectMaterial(m) {
                              this.rawMaterialId = m.id;
                              this.matOpen = false;
                              this.matSearch = '';
                              this.clientError = '';
                          },
                          submitForm(e) {
                              if (this.submitting) { e.preventDefault(); return; }
                              if (!this.rawMaterialId) {
                                  e.preventDefault();
                                  this.clientError = 'Pilih bahan baku terlebih dahulu.';
                                  this.matOpen = true;
                                  return;
                              }
                              this.clientError = '';
                              this.submitting = true;
                          }
                      }"
                      @submit="submitForm($event)"
                      class="space-y-4 rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                    <input type="hidden" name="source" :value="source">
                    <input type="hidden" name="raw_material_id" :value="rawMaterialId">

                    <div class="border-b border-[#e9ebf0] pb-3">
                        <h2 class="text-[15px] font-bold text-[#111827]">Ajukan barang untuk outlet</h2>
                        <p class="text-[12px] text-[#53565a]">Minta pengiriman bahan baku dari Gudang Pusat atau Central Kitchen ke Outlet.</p>
                    </div>

                    <div x-show="clientError" x-cloak class="flex items-center gap-2 rounded-[10px] border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] font-semibold text-rose-700">
                        <i class="fas fa-circle-exclamation"></i>
                        <span x-text="clientError"></span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1.5">
                            <span class="block text-[12px] font-semibold text-[#111827]">Sumber <span class="text-rose-500">*</span></span>
                            <div class="grid h-[40px] grid-cols-2 gap-1 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] p-1">
                                <button type="button" @click="source = 'warehouse'"
                                        :class="source === 'warehouse' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                        class="cursor-pointer rounded-[7px] text-[12px] transition">
                                    Gudang Pusat
                                </button>
                                <button type="button" @click="source = 'kitchen'"
                                        :class="source === 'kitchen' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                        class="cursor-pointer rounded-[7px] text-[12px] transition">
                                    Central Kitchen
                                </button>
                            </div>
                        </div>

                        <div class="relative space-y-1.5" @click.outside="matOpen = false">
                            <span class="block text-[12px] font-semibold text-[#111827]">Bahan <span class="text-rose-500">*</span></span>
                            <button type="button" @click="matOpen = !matOpen"
                                    class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                                    :class="matOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                                <span class="truncate" :class="selectedMaterial() ? 'font-medium text-[#111827]' : 'text-[#7f837e]'"
                                      x-text="selectedMaterial() ? (selectedMaterial().name + ' (' + selectedMaterial().unit + ')') : 'Pilih bahan'"></span>
                                <i class="fas fa-chevron-down text-[11px] text-[#7f837e] transition-transform"
                                   :class="matOpen ? 'rotate-180 text-[#df5826]' : ''" aria-hidden="true"></i>
                            </button>

                            <div x-show="matOpen" x-cloak
                                 class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                                <div class="border-b border-[#f1f3f5] p-2">
                                    <div class="flex items-center gap-2 rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 focus-within:border-[#df5826] focus-within:bg-white">
                                        <i class="fas fa-search text-[11px] text-gray-400"></i>
                                        <input type="text" x-model="matSearch" placeholder="Cari bahan..."
                                               class="w-full border-none bg-transparent text-[12px] text-[#111827] outline-none">
                                    </div>
                                </div>
                                <div class="max-h-56 overflow-y-auto p-1">
                                    <template x-for="m in filteredMaterials()" :key="m.id">
                                        <button type="button" @click="selectMaterial(m)"
                                                class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                :class="String(rawMaterialId) === m.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                            <span class="truncate" x-text="m.name + ' (' + m.unit + ')'"></span>
                                            <i x-show="String(rawMaterialId) === m.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                        </button>
                                    </template>
                                    <div x-show="filteredMaterials().length === 0" class="px-3 py-4 text-center text-[12px] text-[#7f837e]">
                                        Bahan tidak ditemukan.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="req-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                Jumlah <span x-show="selectedMaterial()" x-text="'(' + selectedMaterial()?.unit + ')'"></span> <span class="text-rose-500">*</span>
                            </label>
                            <input id="req-qty-input" name="quantity" type="number" min="0.000001" step="0.000001" value="{{ old('quantity') }}" placeholder="0"
                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                        </div>

                        <div class="space-y-1.5">
                            <label for="req-notes-input" class="block text-[12px] font-semibold text-[#111827]">Catatan</label>
                            <input id="req-notes-input" name="notes" maxlength="250" value="{{ old('notes') }}" placeholder="Opsional (keperluan shift, dll)"
                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="submitting"
                                class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                            <i class="fas fa-paper-plane text-[11px]" aria-hidden="true"></i>
                            <span x-text="submitting ? 'Mengajukan...' : 'Ajukan permintaan'">Ajukan permintaan</span>
                        </button>
                    </div>
                </form>
            @elseif ($tab === 'counts' && $materials->isNotEmpty())
                <form method="POST" action="{{ route('admin.controls.counts.store') }}"
                      x-data="{
                          materials: @js($materialPayload),
                          location: @js(old('location', 'outlet')),
                          frequency: @js(old('frequency', 'DAILY')),
                          rows: @js($countRows).map((item, index) => ({
                              key: index,
                              raw_material_id: item.raw_material_id ? String(item.raw_material_id) : '',
                              counted_quantity: item.counted_quantity ?? '',
                              open: false,
                              search: '',
                          })),
                          nextKey: {{ count($countRows) }},
                          submitting: false,
                          clientError: '',
                          filteredMaterials(query) {
                              const q = (query || '').trim().toLowerCase();
                              if (!q) return this.materials;
                              return this.materials.filter(m => m.name.toLowerCase().includes(q) || m.unit.toLowerCase().includes(q));
                          },
                          materialById(id) {
                              return this.materials.find(m => m.id === String(id)) || null;
                          },
                          selectRowMaterial(row, mat) {
                              row.raw_material_id = mat.id;
                              row.open = false;
                              row.search = '';
                              this.clientError = '';
                          },
                          isMaterialUsedElsewhere(materialId, currentIndex) {
                              return this.rows.some((r, idx) => idx !== currentIndex && r.raw_material_id === String(materialId));
                          },
                          addRow() {
                              if (this.rows.length >= 50) return;
                              this.rows.push({ key: this.nextKey++, raw_material_id: '', counted_quantity: '', open: false, search: '' });
                          },
                          removeRow(index) {
                              if (this.rows.length < 2) return;
                              this.rows.splice(index, 1);
                          },
                          submitForm(e) {
                              if (this.submitting) { e.preventDefault(); return; }
                              if (this.rows.some(r => !r.raw_material_id)) {
                                  e.preventDefault();
                                  this.clientError = 'Pastikan semua baris bahan baku sudah dipilih.';
                                  return;
                              }
                              this.clientError = '';
                              this.submitting = true;
                          }
                      }"
                      @submit="submitForm($event)"
                      class="space-y-4 rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                    <input type="hidden" name="location" :value="location">
                    <input type="hidden" name="frequency" :value="frequency">

                    <div class="border-b border-[#e9ebf0] pb-3">
                        <h2 class="text-[15px] font-bold text-[#111827]">Catat hitung fisik</h2>
                        <p class="text-[12px] text-[#53565a]">Bandingkan stok sistem dengan hasil hitung fisik di lokasi terpilih.</p>
                    </div>

                    <div x-show="clientError" x-cloak class="flex items-center gap-2 rounded-[10px] border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] font-semibold text-rose-700">
                        <i class="fas fa-circle-exclamation"></i>
                        <span x-text="clientError"></span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1.5">
                            <span class="block text-[12px] font-semibold text-[#111827]">Lokasi <span class="text-rose-500">*</span></span>
                            <div class="grid h-[40px] grid-cols-3 gap-1 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] p-1">
                                <button type="button" @click="location = 'outlet'"
                                        :class="location === 'outlet' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                        class="cursor-pointer rounded-[7px] text-[11px] transition">Outlet</button>
                                <button type="button" @click="location = 'warehouse'"
                                        :class="location === 'warehouse' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                        class="cursor-pointer rounded-[7px] text-[11px] transition">Gudang</button>
                                <button type="button" @click="location = 'kitchen'"
                                        :class="location === 'kitchen' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                        class="cursor-pointer rounded-[7px] text-[11px] transition">Kitchen</button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <span class="block text-[12px] font-semibold text-[#111827]">Periode <span class="text-rose-500">*</span></span>
                            <div class="grid h-[40px] grid-cols-2 gap-1 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] p-1">
                                <button type="button" @click="frequency = 'DAILY'"
                                        :class="frequency === 'DAILY' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                        class="cursor-pointer rounded-[7px] text-[12px] transition">Harian</button>
                                <button type="button" @click="frequency = 'WEEKLY'"
                                        :class="frequency === 'WEEKLY' ? 'bg-white text-[#df5826] font-bold shadow-2xs' : 'text-[#53565a] font-medium hover:text-[#111827]'"
                                        class="cursor-pointer rounded-[7px] text-[12px] transition">Mingguan</button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="count-date-input" class="block text-[12px] font-semibold text-[#111827]">Tanggal hitung <span class="text-rose-500">*</span></label>
                            <input id="count-date-input" name="count_date" type="date" max="{{ $today }}" value="{{ old('count_date', $today) }}"
                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]" required>
                        </div>

                        <div class="space-y-1.5">
                            <label for="count-notes-input" class="block text-[12px] font-semibold text-[#111827]">Catatan hitung</label>
                            <input id="count-notes-input" name="notes" maxlength="250" value="{{ old('notes') }}" placeholder="Opsional"
                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]">
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-bold text-[#111827]">Daftar Bahan Opname</span>
                            <button type="button" @click="addRow()" :disabled="rows.length >= 50"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-[8px] border border-[#ffdec9] bg-[#fff6f0] px-3 py-1.5 text-[12px] font-semibold text-[#df5826] transition hover:bg-[#ffe8d9] disabled:cursor-not-allowed disabled:opacity-50">
                                <i class="fas fa-plus text-[10px]" aria-hidden="true"></i>
                                <span>Tambah bahan</span>
                            </button>
                        </div>

                        <template x-for="(row, index) in rows" :key="row.key">
                            <div class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-3.5">
                                <div class="mb-2 flex items-center justify-between">
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
                                                    <input type="text" x-model="row.search" placeholder="Cari bahan..."
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
                                            Jumlah fisik <span x-show="materialById(row.raw_material_id)" x-text="'(' + materialById(row.raw_material_id)?.unit + ')'"></span>
                                        </label>
                                        <input :name="'items[' + index + '][counted_quantity]'" x-model="row.counted_quantity" type="number" min="0" step="0.000001" placeholder="0"
                                               class="h-[38px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 text-[12px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]" required>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="submitting"
                                class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                            <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                            <span x-text="submitting ? 'Menyimpan...' : 'Simpan untuk persetujuan'">Simpan untuk persetujuan</span>
                        </button>
                    </div>
                </form>
            @elseif ($tab === 'purchases')
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.controls.purchases.store') }}"
                      x-data="{
                          funding: @js(old('funding', 'EXTERNAL')),
                          sessions: @js($sessionPayload),
                          sessionId: @js((string) old('cash_register_session_id', '')),
                          sessionOpen: false,
                          materials: @js($materialPayload),
                          rawMaterialId: @js((string) old('raw_material_id', '')),
                          matOpen: false,
                          matSearch: '',
                          submitting: false,
                          selectedSession() {
                              return this.sessions.find(s => s.id === String(this.sessionId)) || null;
                          },
                          filteredMaterials() {
                              const q = this.matSearch.trim().toLowerCase();
                              if (!q) return this.materials;
                              return this.materials.filter(m => m.name.toLowerCase().includes(q) || m.unit.toLowerCase().includes(q));
                          },
                          selectedMaterial() {
                              return this.materials.find(m => m.id === String(this.rawMaterialId)) || null;
                          }
                      }"
                      @submit="submitting = true"
                      class="space-y-4 rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                    <input type="hidden" name="funding" :value="funding">
                    <input type="hidden" name="cash_register_session_id" :value="sessionId" :disabled="funding !== 'DRAWER'">
                    <input type="hidden" name="raw_material_id" :value="rawMaterialId">

                    <div class="border-b border-[#e9ebf0] pb-3">
                        <h2 class="text-[15px] font-bold text-[#111827]">Ajukan pembelian kecil</h2>
                        <p class="text-[12px] text-[#53565a]">Catat belanja operasional mendadak lengkap dengan bukti kuitansi.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1.5">
                            <label for="petty-desc-input" class="block text-[12px] font-semibold text-[#111827]">Keperluan pembelian <span class="text-rose-500">*</span></label>
                            <input id="petty-desc-input" name="description" maxlength="150" value="{{ old('description') }}" placeholder="Contoh: Beli es batu darurat"
                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826]" required>
                        </div>

                        <div class="space-y-1.5">
                            <label for="petty-amount-input" class="block text-[12px] font-semibold text-[#111827]">Nominal (Rp) <span class="text-rose-500">*</span></label>
                            <input id="petty-amount-input" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="0"
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
                                <span class="truncate" :class="selectedMaterial() ? 'font-medium text-[#111827]' : 'text-[#53565a]'"
                                      x-text="selectedMaterial() ? (selectedMaterial().name + ' (' + selectedMaterial().unit + ')') : 'Tanpa penerimaan stok'"></span>
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
                                    <template x-for="m in filteredMaterials()" :key="m.id">
                                        <button type="button" @click="rawMaterialId = m.id; matOpen = false; matSearch = ''"
                                                class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px] transition"
                                                :class="String(rawMaterialId) === m.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                            <span class="truncate" x-text="m.name + ' (' + m.unit + ')'"></span>
                                            <i x-show="String(rawMaterialId) === m.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="petty-qty-input" class="block text-[12px] font-semibold text-[#111827]">
                                Jumlah bahan <span x-show="selectedMaterial()" x-text="'(' + selectedMaterial()?.unit + ')'"></span>
                            </label>
                            <input id="petty-qty-input" name="quantity" type="number" min="0.000001" step="0.000001" value="{{ old('quantity') }}" placeholder="0"
                                   class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] tabular-nums text-[#111827] outline-none transition focus:border-[#df5826]">
                        </div>

                        <div class="space-y-1.5">
                            <label for="petty-proof-input" class="block text-[12px] font-semibold text-[#111827]">Bukti kuitansi (foto/PDF, maks. 5 MB) <span class="text-rose-500">*</span></label>
                            <input id="petty-proof-input" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                   class="block w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3 py-2 text-[12px] text-[#53565a] file:mr-3 file:rounded-lg file:border-0 file:bg-[#fff6f0] file:px-3 file:py-1 file:text-[11px] file:font-semibold file:text-[#df5826] hover:file:bg-[#ffe8d9]" required>
                        </div>

                        <div class="space-y-1.5">
                            <label for="petty-notes-input" class="block text-[12px] font-semibold text-[#111827]">Catatan</label>
                            <input id="petty-notes-input" name="notes" maxlength="250" value="{{ old('notes') }}" placeholder="Opsional"
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
            @else
                <div class="rounded-[14px] border border-[#e9ebf0] bg-white p-6 text-center text-[13px] text-[#53565a]">
                    Tambahkan bahan baku untuk menggunakan fitur ini.
                </div>
            @endif
        @endcan

        {{-- RIWAYAT --}}
        <section class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] space-y-4" aria-labelledby="control-history">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e9ebf0] pb-3.5">
                <div class="flex items-center gap-3">
                    <h2 id="control-history" class="text-[15px] font-bold text-[#111827]">Riwayat {{ strtolower($tabs[$tab]) }}</h2>
                    @if ($recordId)
                        <a href="{{ route('admin.controls.index', ['tab' => $tab]) }}"
                           class="inline-flex items-center gap-1 rounded-lg bg-[#fff6f0] px-2.5 py-1 text-[12px] font-semibold text-[#df5826] hover:bg-[#ffe8d9]">
                            <span>Lihat semua catatan</span>
                        </a>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex flex-wrap items-center gap-1">
                        <button type="button" @click="historyStatus = 'all'"
                                :class="historyStatus === 'all' ? 'bg-[#fff6f0] text-[#df5826] border-[#ffdec9] font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-2.5 py-1 text-[11px] transition">Semua</button>
                        <button type="button" @click="historyStatus = 'PENDING'"
                                :class="historyStatus === 'PENDING' ? 'bg-amber-50 text-amber-700 border-amber-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-2.5 py-1 text-[11px] transition">Menunggu</button>
                        <button type="button" @click="historyStatus = 'APPROVED'"
                                :class="historyStatus === 'APPROVED' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-2.5 py-1 text-[11px] transition">Disetujui</button>
                        <button type="button" @click="historyStatus = 'REJECTED'"
                                :class="historyStatus === 'REJECTED' ? 'bg-rose-50 text-rose-700 border-rose-200 font-bold' : 'bg-white border-[#e9ebf0] text-[#53565a] hover:bg-gray-50 font-medium'"
                                class="cursor-pointer rounded-[8px] border px-2.5 py-1 text-[11px] transition">Ditolak</button>
                    </div>

                    <div class="flex h-[34px] w-full items-center gap-2 rounded-[9px] border border-[#e9ebf0] bg-[#fafaf9] px-3 focus-within:border-[#df5826] focus-within:bg-white sm:w-56">
                        <i class="fas fa-search text-[11px] text-gray-400" aria-hidden="true"></i>
                        <input type="text" x-model="historySearch" placeholder="Cari catatan..."
                               class="w-full border-none bg-transparent text-[12px] text-[#111827] placeholder-[#7f837e] outline-none">
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                @forelse ($records as $record)
                    @php
                        $recordTitle = match ($tab) {
                            'requests' => $record->rawMaterial->name,
                            'counts' => $record->location->name,
                            'purchases' => $record->description,
                        };
                    @endphp
                    <article id="{{ $tab }}-{{ $record->id }}" data-operation-anchor
                             x-show="matchesRecord(@js($record->status), @js('#' . $record->id . ' ' . $recordTitle . ' ' . ($record->notes ?? '')))"
                             class="flex flex-col gap-3 rounded-[12px] border border-[#e9ebf0] bg-[#fafaf9]/60 p-4 text-[13px] transition hover:border-[#ffd2b8] hover:bg-white">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="rounded-md bg-[#fff6f0] px-2 py-0.5 text-[12px] font-bold text-[#df5826]">#{{ $record->id }}</span>
                                <h3 class="text-[14px] font-bold text-[#111827]">{{ $recordTitle }}</h3>
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $statusTone($record->status) }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $statusDot($record->status) }}"></span>
                                    {{ $record->statusLabel() }}
                                </span>
                            </div>
                            <span class="text-[11px] text-[#7f837e]">
                                Dicatat oleh <strong class="text-[#53565a]">{{ $record->requester?->name ?? '—' }}</strong>
                                @if ($record->reviewed_at)
                                    · Ditinjau oleh <strong class="text-[#53565a]">{{ $record->reviewer?->name ?? '—' }}</strong>
                                @endif
                            </span>
                        </div>

                        @if ($tab === 'requests')
                            <div class="flex flex-wrap items-center gap-2 text-[12px] text-[#111827]">
                                <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 font-semibold">{{ $record->sourceName() }} → Outlet</span>
                                <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 tabular-nums">
                                    Diminta: <strong>{{ $number($record->requested_quantity) }} {{ $record->unit }}</strong>
                                </span>
                                @if ($record->approved_quantity !== null)
                                    <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 tabular-nums text-emerald-800">
                                        Disetujui: <strong>{{ $number($record->approved_quantity) }} {{ $record->unit }}</strong>
                                    </span>
                                @endif
                            </div>

                            @if ($record->transfer)
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-sky-200 bg-sky-50/70 px-3 py-2 text-[12px] text-sky-900">
                                    <span>
                                        <strong>Transfer #{{ $record->transfer->id }}</strong> ·
                                        Diterima {{ $number($record->transfer->received_quantity) }} {{ $record->unit }} ·
                                        Sisa {{ $number($record->transfer->remainingQuantity()) }} {{ $record->unit }}
                                    </span>
                                    @can('manage-inventory')
                                        <a href="{{ route('admin.inventory.locations.index', ['transfer' => $record->transfer->id]) }}#transfer-{{ $record->transfer->id }}"
                                           class="inline-flex items-center gap-1 font-semibold text-[#df5826] hover:underline">
                                            <span>Lihat transfer dan konfirmasi penerimaan</span>
                                            <i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i>
                                        </a>
                                    @endcan
                                </div>
                            @elseif ($record->status === 'APPROVED')
                                @can('dispatch-stock')
                                    <form method="POST" action="{{ route('admin.controls.requests.dispatch', $record) }}">
                                        @csrf
                                        <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button class="inline-flex h-[34px] cursor-pointer items-center gap-1.5 rounded-[9px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white transition hover:bg-[#c94d00]">
                                            <i class="fas fa-truck-fast text-[11px]" aria-hidden="true"></i>
                                            <span>Kirim barang</span>
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        @elseif ($tab === 'counts')
                            <p class="text-[12px] text-[#53565a]">
                                Dihitung <strong class="text-[#111827]">{{ $record->counted_on->format('d/m/Y') }}</strong> ·
                                {{ $record->frequency === 'WEEKLY' ? 'Mingguan' : 'Harian' }} ·
                                {{ $record->period_start->format('d/m/Y') }}
                                @if ($record->frequency === 'WEEKLY') – {{ $record->period_end->format('d/m/Y') }} @endif
                            </p>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($record->items as $item)
                                    @php $diff = (float) $item->difference; @endphp
                                    <div class="flex items-center justify-between gap-2 rounded-lg border border-[#e9ebf0] bg-white px-3 py-2 text-[12px]">
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-[#111827]">{{ $item->rawMaterial->name }}</div>
                                            <div class="text-[11px] tabular-nums text-[#53565a]">
                                                Sistem {{ $number($item->system_quantity) }} · Fisik {{ $number($item->counted_quantity) }} {{ $item->unit }}
                                            </div>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums {{ $diff === 0.0 ? 'bg-emerald-50 text-emerald-700' : ($diff > 0 ? 'bg-sky-50 text-sky-700' : 'bg-rose-50 text-rose-700') }}">
                                            {{ $diff > 0 ? '+' : '' }}{{ $number($item->difference) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex flex-wrap items-center gap-2 text-[12px]">
                                <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 font-bold tabular-nums text-[#111827]">
                                    Rp{{ $number($record->amount) }}
                                </span>
                                <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 text-[#53565a]">
                                    {{ $record->funding === 'DRAWER' ? 'Laci kasir · shift #' . $record->cash_register_session_id : 'Dana di luar kasir' }}
                                </span>
                                @if ($record->rawMaterial)
                                    <span class="rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 font-medium text-[#111827]">
                                        {{ $number($record->quantity) }} {{ $record->unit }} {{ $record->rawMaterial->name }}
                                    </span>
                                @endif
                                <a href="{{ route('admin.controls.purchases.proof', $record) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 font-semibold text-[#df5826] transition hover:bg-[#ffe8d9]">
                                    <i class="fas fa-download text-[10px]" aria-hidden="true"></i>
                                    <span>Unduh bukti pembelian #{{ $record->id }}</span>
                                </a>
                            </div>
                            @if ($record->status === 'APPROVED' && $record->raw_material_id && $record->received_at === null)
                                @can('record-inventory-controls')
                                    <form method="POST" action="{{ route('admin.controls.purchases.receive', $record) }}">
                                        @csrf
                                        <button class="inline-flex h-[34px] cursor-pointer items-center gap-1.5 rounded-[9px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white transition hover:bg-[#c94d00]">
                                            <i class="fas fa-box-open text-[11px]" aria-hidden="true"></i>
                                            <span>Konfirmasi barang masuk</span>
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        @endif

                        @if ($record->notes || $record->decision_note)
                            <div class="space-y-1 text-[12px] text-[#53565a]">
                                @if ($record->notes)
                                    <p><strong class="text-[#111827]">Catatan:</strong> {{ $record->notes }}</p>
                                @endif
                                @if ($record->decision_note)
                                    <p><strong class="text-[#111827]">Catatan keputusan:</strong> {{ $record->decision_note }}</p>
                                @endif
                            </div>
                        @endif

                        @can('approve-inventory-controls')
                            @if ($record->status === 'PENDING')
                                <form method="POST" action="{{ route('admin.controls.'.$tab.'.review', $record) }}"
                                      class="flex flex-wrap items-end gap-3 border-t border-[#e9ebf0] pt-3">
                                    @csrf
                                    @if ($tab === 'requests')
                                        <label class="space-y-1 text-[12px] font-semibold text-[#111827]">
                                            <span>Jumlah disetujui ({{ $record->unit }})</span>
                                            <input name="approved_quantity" type="number" min="0.000001" max="{{ $record->requested_quantity }}" step="0.000001"
                                                   value="{{ $record->requested_quantity }}"
                                                   class="h-[36px] w-36 rounded-[9px] border border-[#e9ebf0] bg-white px-3 text-[12px] tabular-nums text-[#111827] outline-none focus:border-[#df5826]">
                                        </label>
                                    @endif
                                    <label class="min-w-[220px] flex-1 space-y-1 text-[12px] font-semibold text-[#111827]">
                                        <span>Catatan keputusan (wajib jika ditolak)</span>
                                        <input name="decision_note" maxlength="250" placeholder="Opsional saat setuju, wajib saat tolak"
                                               class="h-[36px] w-full rounded-[9px] border border-[#e9ebf0] bg-white px-3 text-[12px] text-[#111827] outline-none focus:border-[#df5826]">
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <button name="decision" value="approve"
                                                class="h-[36px] cursor-pointer rounded-[9px] bg-[#df5826] px-4 text-[12px] font-semibold text-white transition hover:bg-[#c94d00]">
                                            Setujui
                                        </button>
                                        <button name="decision" value="reject"
                                                class="h-[36px] cursor-pointer rounded-[9px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-rose-600 transition hover:bg-rose-50">
                                            Tolak
                                        </button>
                                    </div>
                                </form>
                            @endif
                        @endcan
                    </article>
                @empty
                    <div class="py-10 text-center text-[13px] text-[#7f837e]">Belum ada catatan.</div>
                @endforelse
            </div>

            @if ($records->hasPages())
                <div class="border-t border-[#e9ebf0] pt-3">
                    {{ $records->links() }}
                </div>
            @endif
        </section>
    </div>
</x-cafe-operation-page>
