@php
    $rp = fn ($val) => 'Rp ' . number_format((float) $val, 0, ',', '.');
    $maxTrendSales = max(1, (float) $dashboard['trend']->max('sales'));
    $maxBranchSales = max(1, (float) collect($dashboard['branchStats'])->max('sales'));
    $maxHourOrders = max(1, (int) $dashboard['peakHours']->max('orders'));
    $focusSlug = $dashboard['focus']?->slug;
    $hasTrendData = (float) $dashboard['trend']->sum('sales') > 0;
    $hasPeakData = (int) $dashboard['peakHours']->sum('orders') > 0;
    $routeName = request()->routeIs('admin.owner.dashboard') ? 'admin.owner.dashboard' : 'admin.branches.index';

    $ac = $dashboard['actionCenter'];
    $totalPendingActions = $ac['pendingPo'] + $ac['pendingRequests'] + $ac['pendingCounts'] + $ac['pendingPetty'] + $ac['pendingTransfers'] + $ac['lowStock'];

    $paymentColors = ['#ea580c', '#0ea5e9', '#10b981', '#8b5cf6', '#94a3b8'];
    $donutSegments = [];
    $cursor = 0.0;
    foreach ($dashboard['paymentBreakdown'] as $idx => $pm) {
        $color = $paymentColors[$idx % count($paymentColors)];
        $next = min(100.0, $cursor + $pm['pct']);
        $donutSegments[] = "{$color} {$cursor}% {$next}%";
        $cursor = $next;
    }
    $donutStyle = count($donutSegments) > 0
        ? 'background: conic-gradient(' . implode(', ', $donutSegments) . ($cursor < 100 ? ", #f1f3f5 {$cursor}% 100%" : '') . ');'
        : 'background: conic-gradient(#f1f3f5 0% 100%);';
@endphp

<section x-data="{
    hoveredTrend: null,
    hoveredHour: null,
    mounted: false,
    init() {
        requestAnimationFrame(() => { this.mounted = true; });
    }
}" class="space-y-5 font-['DM_Sans',sans-serif]" aria-label="Analitik Bisnis">

    {{-- Filter Bar: Periode & Fokus Cabang --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[12px] border border-[#e9ebf0] bg-white px-4 py-3 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="mr-1 text-[12px] font-medium text-[#53565a]">Periode:</span>
            @foreach ([1 => 'Hari ini', 7 => '7 Hari', 14 => '14 Hari', 30 => '30 Hari'] as $d => $label)
                <a href="{{ route($routeName, array_filter(['days' => $d, 'branch' => $focusSlug])) }}"
                   class="rounded-[8px] border px-3 py-1.5 text-[12px] transition {{ (int) $days === $d ? 'border-[#ffd6bd] bg-[#fff6f0] font-semibold text-[#ea580c]' : 'border-[#e9ebf0] bg-white font-medium text-[#53565a] hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
            <span class="ml-1.5 text-[11px] font-normal text-[#7f837e]">
                ({{ $dashboard['from']->format('d M Y') }} – {{ $dashboard['until']->format('d M Y') }})
            </span>
        </div>

        @if ($branches->count() > 1)
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ route($routeName, ['days' => $days]) }}"
                   class="rounded-[8px] border px-2.5 py-1 text-[11px] transition {{ ! $focusSlug ? 'border-[#ffd6bd] bg-[#fff6f0] font-semibold text-[#ea580c]' : 'border-[#e9ebf0] bg-white font-medium text-[#53565a] hover:bg-gray-50' }}">
                    Semua Cabang
                </a>
                @foreach ($branches as $b)
                    <a href="{{ route($routeName, ['days' => $days, 'branch' => $b->slug]) }}"
                       class="rounded-[8px] border px-2.5 py-1 text-[11px] transition {{ $focusSlug === $b->slug ? 'border-[#ffd6bd] bg-[#fff6f0] font-semibold text-[#ea580c]' : 'border-[#e9ebf0] bg-white font-medium text-[#53565a] hover:bg-gray-50' }}">
                        {{ $b->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- 4 KPI Summary Cards Row (Menyamai Dashboard Cabang overview.blade.php) --}}
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2 lg:grid-cols-4">
        <div class="relative flex h-[116px] flex-col justify-between overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-gradient-to-r from-[#f9731f] to-[#ea580c] p-4 text-white shadow-[0px_4px_16px_rgba(249,115,31,0.25)]">
            <div class="pointer-events-none absolute inset-0 select-none overflow-hidden" aria-hidden="true">
                <div class="h-full w-full" style="
                    background: repeating-linear-gradient(
                        45deg,
                        rgba(255, 255, 255, 0.14),
                        rgba(255, 255, 255, 0.14) 2.5px,
                        transparent 2.5px,
                        transparent 8.5px
                    );
                    mask-image: linear-gradient(to right, transparent 0%, transparent 20%, rgba(0, 0, 0, 0.5) 45%, black 75%);
                    -webkit-mask-image: linear-gradient(to right, transparent 0%, transparent 20%, rgba(0, 0, 0, 0.5) 45%, black 75%);
                "></div>
            </div>
            <img src="{{ asset('images/sparta/cart-watermark.svg') }}" class="pointer-events-none absolute -right-1 bottom-0 h-[106px] w-auto select-none object-contain" alt="">

            <div class="relative z-10">
                <dt class="text-[12px] font-normal leading-[15px] text-white/90">Omzet Bersih</dt>
            </div>
            <dd class="relative z-10 flex flex-col gap-1.5">
                <div class="text-[24px] font-medium leading-tight tabular-nums text-white">{{ $rp($dashboard['sales']) }}</div>
                <div class="flex items-center gap-1.5">
                    @if ($dashboard['salesChange'] !== null)
                        @php $up = $dashboard['salesChange'] >= 0; @endphp
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold leading-tight {{ $up ? 'bg-[#eafbf2] text-[#00643b]' : 'bg-[#fff0f4] text-[#b91f50]' }}">
                            {{ $up ? '+' : '' }}{{ number_format($dashboard['salesChange'], 1, ',', '.') }}%
                        </span>
                        <span class="text-[12px] leading-tight text-white/90">vs. periode lalu</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-semibold leading-tight text-white">
                            {{ $dashboard['focus'] ? $dashboard['focus']->name : 'Semua cabang' }}
                        </span>
                    @endif
                </div>
            </dd>
        </div>

        <div class="flex h-[116px] flex-col justify-between rounded-[12px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_8px_rgba(0,0,0,0.03)] transition-all hover:border-[#f9731f]/40">
            <div class="flex items-center justify-between">
                <dt class="text-[12px] font-normal leading-[15px] text-[#53565a]">Transaksi Lunas</dt>
                <div class="flex h-6 w-6 items-center justify-center">
                    <img src="{{ asset('images/sparta/box-bold-duotone.svg') }}" class="h-5 w-5" alt="">
                </div>
            </div>
            <dd class="flex flex-col gap-1.5">
                <div class="text-[24px] font-medium leading-tight tabular-nums text-[#111827]">{{ number_format($dashboard['orders'], 0, ',', '.') }}</div>
                <span class="text-[12px] leading-tight text-[#53565a]">Pesanan dalam periode terpilih</span>
            </dd>
        </div>

        <div class="flex h-[116px] flex-col justify-between rounded-[12px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_8px_rgba(0,0,0,0.03)] transition-all hover:border-[#f9731f]/40">
            <div class="flex items-center justify-between">
                <dt class="text-[12px] font-normal leading-[15px] text-[#53565a]">Rata-rata Transaksi</dt>
                <div class="flex h-6 w-6 items-center justify-center">
                    <img src="{{ asset('images/sparta/tag-price-bold-duotone.svg') }}" class="h-5 w-5" alt="">
                </div>
            </div>
            <dd class="flex flex-col gap-1.5">
                <div class="text-[22px] font-medium leading-tight tabular-nums text-[#111827]">{{ $rp($dashboard['average']) }}</div>
                <span class="text-[12px] leading-tight text-[#53565a]">Nilai rata-rata per struk</span>
            </dd>
        </div>

        <div class="flex h-[116px] flex-col justify-between rounded-[12px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_8px_rgba(0,0,0,0.03)] transition-all hover:border-[#f9731f]/40">
            <div class="flex items-center justify-between">
                <dt class="text-[12px] font-normal leading-[15px] text-[#53565a]">Estimasi Laba Kotor</dt>
                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-[#fff6f0] text-[#ea580c]">
                    <i class="fas fa-chart-line text-[12px]" aria-hidden="true"></i>
                </span>
            </div>
            <dd class="flex flex-col gap-1.5">
                <div class="text-[22px] font-medium leading-tight tabular-nums text-[#111827]">{{ $rp($dashboard['grossProfit']) }}</div>
                @php $overallMargin = $dashboard['sales'] > 0 ? round(($dashboard['grossProfit'] / $dashboard['sales']) * 100, 1) : 0; @endphp
                <span class="text-[12px] leading-tight text-[#53565a]">Margin kotor {{ number_format($overallMargin, 1, ',', '.') }}%</span>
            </dd>
        </div>
    </dl>

    {{-- PUSAT TINDAKAN OWNER (ACTION CENTER) --}}
    <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-4 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <h2 class="text-[14px] font-semibold text-[#111827]">Pusat Tindakan Owner</h2>
                @if ($totalPendingActions > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#fff6f0] px-2.5 py-0.5 text-[11px] font-semibold text-[#ea580c]">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#ea580c]"></span>
                        {{ $totalPendingActions }} perlu perhatian
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">
                        <i class="fas fa-check text-[9px]" aria-hidden="true"></i>
                        Semua terkendali
                    </span>
                @endif
            </div>
            <span class="text-[11px] text-[#7f837e]">Klik kartu untuk meninjau &amp; menyetujui</span>
        </div>

        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
            @php
                $actionCards = [
                    ['label' => 'PO Menunggu', 'count' => $ac['pendingPo'], 'route' => route('admin.purchases.index'), 'icon' => 'fa-file-invoice', 'tone' => 'text-amber-600 bg-amber-50'],
                    ['label' => 'Permintaan Stok', 'count' => $ac['pendingRequests'], 'route' => route('admin.controls.index'), 'icon' => 'fa-boxes-packing', 'tone' => 'text-sky-600 bg-sky-50'],
                    ['label' => 'Stok Opname', 'count' => $ac['pendingCounts'], 'route' => route('admin.inventory.index', ['subtab' => 'opname']), 'icon' => 'fa-list-check', 'tone' => 'text-violet-600 bg-violet-50'],
                    ['label' => 'Pembelian Kecil', 'count' => $ac['pendingPetty'], 'route' => route('admin.purchases.index', ['tab' => 'petty']), 'icon' => 'fa-receipt', 'tone' => 'text-[#ea580c] bg-[#fff6f0]'],
                    ['label' => 'Transfer Tertunda', 'count' => $ac['pendingTransfers'], 'route' => route('admin.inventory.locations.index'), 'icon' => 'fa-truck-fast', 'tone' => 'text-indigo-600 bg-indigo-50'],
                    ['label' => 'Stok Menipis', 'count' => $ac['lowStock'], 'route' => route('admin.inventory.index'), 'icon' => 'fa-triangle-exclamation', 'tone' => 'text-rose-600 bg-rose-50'],
                ];
            @endphp
            @foreach ($actionCards as $card)
                <a href="{{ $card['route'] }}"
                   class="group flex items-center justify-between gap-2 rounded-[10px] border p-3 transition-all hover:-translate-y-0.5 hover:shadow-sm {{ $card['count'] > 0 ? 'border-[#ffd6bd] bg-[#fffaf7]' : 'border-[#f1f3f5] bg-[#fafaf9] hover:border-[#e9ebf0]' }}">
                    <div class="min-w-0">
                        <span class="block truncate text-[11px] font-medium text-[#53565a]">{{ $card['label'] }}</span>
                        <span class="mt-0.5 block text-[18px] font-bold tabular-nums {{ $card['count'] > 0 ? 'text-[#111827]' : 'text-[#9ca3af]' }}">
                            {{ $card['count'] }}
                        </span>
                    </div>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $card['tone'] }} transition-transform group-hover:scale-105">
                        <i class="fas {{ $card['icon'] }} text-[12px]" aria-hidden="true"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Tren Penjualan & Penjualan per cabang --}}
    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Tren Harian dengan Interactive Hover Tooltip --}}
        <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
            <div class="mb-4 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-[15px] font-semibold text-[#111827]">Tren Penjualan</h3>
                    <p class="text-[12px] text-[#7f837e]">Omzet harian · {{ $dashboard['focus'] ? $dashboard['focus']->name : 'Semua cabang' }}</p>
                </div>
                <div x-show="hoveredTrend" x-cloak class="rounded-lg border border-[#ffd6bd] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#ea580c]" x-text="hoveredTrend"></div>
            </div>
            @if ($hasTrendData)
                <div class="flex h-44 items-end gap-1.5 pt-2">
                    @foreach ($dashboard['trend'] as $point)
                        @php $pct = $point['sales'] > 0 ? max(8, round(($point['sales'] / $maxTrendSales) * 100)) : 4; @endphp
                        <div class="group flex flex-1 cursor-pointer flex-col items-center gap-1.5"
                             @mouseenter="hoveredTrend = @js($point['date']->format('d M Y') . ': ' . $rp($point['sales']))"
                             @mouseleave="hoveredTrend = null">
                            <div class="flex h-36 w-full items-end">
                                <div class="w-full rounded-t-md transition-all duration-500 ease-out {{ $point['sales'] > 0 ? 'bg-gradient-to-t from-[#ea580c] to-[#f9731f] group-hover:brightness-110' : 'bg-[#f1f3f5]' }}"
                                     :style="'height: ' + (mounted ? '{{ $pct }}%' : '0%')"></div>
                            </div>
                            @if ($dashboard['trend']->count() <= 14 || $loop->first || $loop->last || $loop->iteration % 5 === 0)
                                <span class="text-[10px] tabular-nums text-[#7f837e]">{{ $point['date']->format('d/m') }}</span>
                            @else
                                <span class="text-[10px] text-transparent">.</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex h-44 items-center justify-center rounded-[10px] border border-dashed border-[#e9ebf0] bg-[#fafaf9] text-[13px] text-[#7f837e]">
                    Belum ada transaksi lunas pada periode ini.
                </div>
            @endif
        </div>

        {{-- Penjualan per cabang (dengan Margin %) --}}
        <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-[15px] font-semibold text-[#111827]">Penjualan per cabang</h3>
                    <p class="text-[12px] text-[#7f837e]">Urutan omzet &amp; margin laba kotor</p>
                </div>
            </div>
            <div class="space-y-3.5">
                @foreach ($dashboard['comparison'] as $compBranch)
                    @php
                        $cStats = $dashboard['branchStats'][$compBranch->id] ?? ['sales' => 0, 'orders' => 0, 'marginPct' => 0];
                        $barPct = $cStats['sales'] > 0 ? max(4, round(($cStats['sales'] / $maxBranchSales) * 100)) : 0;
                    @endphp
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2 text-[12px]">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="truncate font-semibold text-[#111827]">{{ $compBranch->name }}</span>
                                @if ($cStats['sales'] > 0)
                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                        Margin {{ number_format($cStats['marginPct'], 1, ',', '.') }}%
                                    </span>
                                @endif
                            </div>
                            <span class="shrink-0 tabular-nums text-[#53565a]">
                                <strong class="text-[#111827]">{{ $rp($cStats['sales']) }}</strong> · {{ $cStats['orders'] }} transaksi
                            </span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-[#f1f3f5]">
                            <div class="h-full rounded-full bg-gradient-to-r from-[#f9731f] to-[#ea580c] transition-all duration-700 ease-out"
                                 :style="'width: ' + (mounted ? '{{ $barPct }}%' : '0%')"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- BARIS BARU: Metode Pembayaran, Kanal Pesanan, & Jam Sibuk --}}
    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Donut Metode Pembayaran --}}
        <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
            <h3 class="text-[15px] font-semibold text-[#111827]">Metode Pembayaran</h3>
            <p class="mb-4 text-[12px] text-[#7f837e]">Proporsi omzet tunai vs non-tunai</p>

            @if ($dashboard['paymentBreakdown']->isNotEmpty())
                <div class="flex items-center gap-5">
                    <div class="relative h-28 w-28 shrink-0 rounded-full" style="{{ $donutStyle }}">
                        <div class="absolute inset-3.5 flex flex-col items-center justify-center rounded-full bg-white text-center shadow-inner">
                            <span class="text-[10px] text-[#7f837e]">Total</span>
                            <span class="text-[12px] font-bold tabular-nums text-[#111827]">{{ $dashboard['orders'] }}x</span>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        @foreach ($dashboard['paymentBreakdown'] as $idx => $pm)
                            <div class="flex items-center justify-between gap-2 text-[12px]">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $paymentColors[$idx % count($paymentColors)] }}"></span>
                                    <span class="truncate text-[#53565a]">{{ $pm['label'] }}</span>
                                </div>
                                <span class="shrink-0 font-semibold tabular-nums text-[#111827]">{{ number_format($pm['pct'], 1, ',', '.') }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="flex h-28 items-center justify-center rounded-[10px] border border-dashed border-[#e9ebf0] bg-[#fafaf9] text-[12px] text-[#7f837e]">
                    Belum ada data pembayaran.
                </div>
            @endif
        </div>

        {{-- Komposisi Kanal Pesanan --}}
        <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
            <h3 class="text-[15px] font-semibold text-[#111827]">Kanal Pesanan</h3>
            <p class="mb-4 text-[12px] text-[#7f837e]">Distribusi Dine-In, Takeaway &amp; Delivery</p>

            @if ($dashboard['channelBreakdown']->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($dashboard['channelBreakdown'] as $ch)
                        @php
                            $chColor = match ($ch['key']) {
                                'DINE_IN' => 'from-[#f9731f] to-[#ea580c]',
                                'TAKEAWAY' => 'from-sky-500 to-sky-600',
                                default => 'from-emerald-500 to-emerald-600',
                            };
                        @endphp
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-[12px]">
                                <span class="font-medium text-[#111827]">{{ $ch['label'] }}</span>
                                <span class="tabular-nums text-[#53565a]">
                                    <strong class="text-[#111827]">{{ $ch['orders'] }}x</strong> ({{ number_format($ch['pct'], 1, ',', '.') }}%)
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-[#f1f3f5]">
                                <div class="h-full rounded-full bg-gradient-to-r {{ $chColor }} transition-all duration-700"
                                     :style="'width: ' + (mounted ? '{{ max(4, $ch['pct']) }}%' : '0%')"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex h-28 items-center justify-center rounded-[10px] border border-dashed border-[#e9ebf0] bg-[#fafaf9] text-[12px] text-[#7f837e]">
                    Belum ada data kanal pesanan.
                </div>
            @endif
        </div>

        {{-- Grafik Jam Sibuk --}}
        <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
            <div class="mb-4 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-[15px] font-semibold text-[#111827]">Jam Sibuk</h3>
                    <p class="text-[12px] text-[#7f837e]">Volume transaksi (08:00 – 22:00)</p>
                </div>
                <span x-show="hoveredHour" x-cloak class="rounded bg-[#fff6f0] px-2 py-0.5 text-[10px] font-semibold text-[#ea580c]" x-text="hoveredHour"></span>
            </div>

            @if ($hasPeakData)
                <div class="flex h-28 items-end gap-1 pt-2">
                    @foreach ($dashboard['peakHours'] as $slot)
                        @php $hPct = $slot['orders'] > 0 ? max(12, round(($slot['orders'] / $maxHourOrders) * 100)) : 6; @endphp
                        <div class="group flex flex-1 cursor-pointer flex-col items-center gap-1"
                             @mouseenter="hoveredHour = @js($slot['label'] . ': ' . $slot['orders'] . ' order')"
                             @mouseleave="hoveredHour = null">
                            <div class="flex h-20 w-full items-end">
                                <div class="w-full rounded-t transition-all duration-500 {{ $slot['orders'] > 0 ? 'bg-[#f9731f] group-hover:bg-[#ea580c]' : 'bg-[#f1f3f5]' }}"
                                     :style="'height: ' + (mounted ? '{{ $hPct }}%' : '0%')"></div>
                            </div>
                            @if ($slot['hour'] % 3 === 0)
                                <span class="text-[9px] tabular-nums text-[#7f837e]">{{ str_pad((string) $slot['hour'], 2, '0', STR_PAD_LEFT) }}</span>
                            @else
                                <span class="text-[9px] text-transparent">.</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex h-28 items-center justify-center rounded-[10px] border border-dashed border-[#e9ebf0] bg-[#fafaf9] text-[12px] text-[#7f837e]">
                    Belum ada aktivitas transaksi.
                </div>
            @endif
        </div>
    </div>

    {{-- TRANSAKSI TERBARU LINTAS CABANG --}}
    <div class="overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e9ebf0] px-5 py-3.5">
            <div>
                <h3 class="text-[15px] font-semibold text-[#111827]">Transaksi Terbaru Lintas Cabang</h3>
                <p class="text-[12px] text-[#7f837e]">Order lunas terakhir pada periode ini beserta cabang, kanal, dan metode pembayaran.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                <i class="fas fa-receipt text-[10px]" aria-hidden="true"></i>
                <span>{{ $dashboard['recentOrders']->count() }} terbaru</span>
            </span>
        </div>
        @if ($dashboard['recentOrders']->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-[13px]">
                    <thead class="border-b border-[#e9ebf0] bg-[#f8f9fa] text-[11px] font-semibold uppercase tracking-wider text-[#53565a]">
                        <tr>
                            <th class="px-5 py-3">Order &amp; Pelanggan</th>
                            <th class="px-4 py-3">Cabang</th>
                            <th class="px-4 py-3">Kanal</th>
                            <th class="px-4 py-3">Metode Bayar</th>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-5 py-3 text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f1f3f5]">
                        @foreach ($dashboard['recentOrders'] as $order)
                            <tr class="transition hover:bg-[#fafaf9]">
                                <td class="px-5 py-3.5">
                                    <div class="font-semibold text-[#111827]">#{{ $order['id'] }}</div>
                                    <div class="text-[11px] text-[#7f837e]">{{ $order['customerName'] }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($order['branchSlug'])
                                        <a href="{{ route('branch.admin.dashboard', ['operatingBranch' => $order['branchSlug']]) }}"
                                           class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-0.5 text-[11px] font-semibold text-[#df5826] transition hover:bg-[#ffe9db]">
                                            <i class="fas fa-store text-[10px]" aria-hidden="true"></i>
                                            <span>{{ $order['branchName'] }}</span>
                                        </a>
                                    @else
                                        <span class="text-[12px] font-medium text-[#53565a]">{{ $order['branchName'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-md border border-[#e9ebf0] bg-[#f8f9fa] px-2 py-0.5 text-[11px] font-medium text-[#53565a]">
                                        {{ $order['channelLabel'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">
                                        {{ $order['paymentLabel'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-[12px] tabular-nums text-[#53565a]">
                                    <div>{{ $order['createdAt']?->format('d/m/Y H:i') ?? '—' }}</div>
                                    @if ($order['createdAt'])
                                        <div class="text-[11px] text-[#7f837e]">{{ $order['createdAt']->diffForHumans() }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold tabular-nums text-[#111827]">
                                    {{ $rp($order['grandTotal']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center">
                <div class="mx-auto mb-2.5 flex h-10 w-10 items-center justify-center rounded-xl bg-[#fff6f0] text-[#df5826]">
                    <i class="fas fa-receipt text-[15px]" aria-hidden="true"></i>
                </div>
                <p class="text-[13px] font-semibold text-[#111827]">Belum ada transaksi lunas</p>
                <p class="mt-0.5 text-[12px] text-[#7f837e]">Order yang sudah dibayar pada periode terpilih akan muncul di sini.</p>
            </div>
        @endif
    </div>

    {{-- Menu Terlaris per Cabang --}}
    @if ($dashboard['topProducts']->isNotEmpty())
        <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_8px_rgba(0,0,0,0.03)]">
            <div class="mb-3">
                <h3 class="text-[15px] font-semibold text-[#111827]">Menu Terlaris per Cabang</h3>
                <p class="text-[12px] text-[#7f837e]">3 produk dengan penjualan unit tertinggi pada periode ini</p>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($dashboard['focusedBranches'] as $fb)
                    @php $items = $dashboard['topProducts']->get($fb->id, collect()); @endphp
                    <div class="rounded-[10px] border border-[#f1f3f5] bg-[#fafaf9] p-3.5">
                        <h4 class="mb-2 text-[12px] font-bold text-[#111827]">{{ $fb->name }}</h4>
                        @forelse ($items as $prod)
                            <div class="flex items-center justify-between py-1 text-[12px]">
                                <span class="truncate text-[#53565a]">{{ $prod->ranking }}. {{ $prod->product_name }}</span>
                                <span class="ml-2 shrink-0 font-bold tabular-nums text-[#111827]">{{ (int) $prod->units }}x</span>
                            </div>
                        @empty
                            <p class="text-[12px] text-[#7f837e]">Belum ada penjualan.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
