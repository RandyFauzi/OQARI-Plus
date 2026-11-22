<x-cafe-operation-page title="Laporan Selisih" :shop="$shop">
        <header class="flex flex-col gap-2">
            <a class="cafe-operation-link text-[13px]" href="{{ route('admin.controls.index') }}">Kembali ke Kontrol Operasional</a>
            <h1 class="text-[22px] font-bold leading-tight tracking-tight text-[#111827] md:text-[25px]">Laporan Selisih</h1>
        </header>
        @php
            $input = 'cafe-operation-input';
            $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');
            $timezone = config('attendance.timezone');
            [$expectedLabel, $actualLabel] = match ($type) {
                'counts' => ['Saldo sistem', 'Hitung fisik'],
                'transfers' => ['Dikirim', 'Diterima'],
                'production' => ['Target', 'Layak pakai'],
                'cash' => ['Kas seharusnya', 'Kas fisik'],
            };
        @endphp
        <form method="GET" action="{{ route('admin.controls.variance') }}" class="cafe-operation-panel grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-sm font-medium">Jenis selisih
                <select name="type" class="{{ $input }}">
                    @foreach ($types as $key => $label)<option value="{{ $key }}" @selected($key === $type)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Dari tanggal
                <input name="from" type="date" value="{{ $from }}" class="{{ $input }}">
            </label>
            <label class="text-sm font-medium">Sampai tanggal
                <input name="through" type="date" value="{{ $through }}" class="{{ $input }}">
            </label>
            <button class="cafe-operation-button cafe-operation-primary">Terapkan filter</button>
        </form>

        <section class="cafe-operation-panel">
            <h2 id="variance-results" class="font-semibold">{{ $types[$type] }}</h2>
            <div class="overflow-x-auto rounded-lg border border-[#e9ebf0]" role="region" aria-labelledby="variance-results" tabindex="0">
                <table class="cafe-operation-table min-w-[620px] text-left">
                    <thead class="border-b border-gray-200 text-[#53565a]">
                        <tr>
                            <th scope="col" class="py-2 pr-3">Catatan asal</th>
                            <th scope="col" class="py-2 pr-3">{{ $expectedLabel }}</th>
                            <th scope="col" class="py-2 pr-3">{{ $actualLabel }}</th>
                            <th scope="col" class="py-2 pr-3">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            @php
                                [$title, $subtitle, $expected, $actual, $difference, $unit, $href] = match ($type) {
                                    'counts' => [
                                        'Opname #'.$row->stockCount->id.' · '.$row->rawMaterial->name,
                                        $row->stockCount->location->name.' · '.$row->stockCount->period_start->format('d/m/Y').' · '.$row->stockCount->statusLabel(),
                                        $row->system_quantity, $row->counted_quantity, $row->difference, $row->unit,
                                        route('admin.controls.index', ['tab' => 'counts', 'record' => $row->stockCount->id]).'#counts-'.$row->stockCount->id,
                                    ],
                                    'transfers' => [
                                        'Transfer #'.$row->id.' · '.$row->rawMaterial->name,
                                        $row->fromLocation->name.' → '.$row->toLocation->name.' · '.$row->created_at->copy()->timezone($timezone)->format('d/m/Y').' · Belum diterima '.$number($row->remainingQuantity()),
                                        $row->sent_quantity, $row->received_quantity, -$row->remainingQuantity(), $row->rawMaterial->unit,
                                        auth()->user()->can('manage-inventory') ? route('admin.inventory.locations.index', ['transfer' => $row->id]).'#transfer-'.$row->id : null,
                                    ],
                                    'production' => [
                                        'Surat kerja #'.$row->id.' · '.$row->recipe->outputMaterial->name,
                                        $row->posted_at->copy()->timezone($timezone)->format('d/m/Y').' · Waste '.$number($row->waste_quantity).' '.$row->recipe->unit,
                                        $row->target_quantity, $row->good_quantity, (float) $row->good_quantity - (float) $row->target_quantity, $row->recipe->unit,
                                        auth()->user()->can('view-production') ? route('admin.production.show', $row) : null,
                                    ],
                                    'cash' => [
                                        'Shift #'.$row->id.' · '.$row->register->name,
                                        $row->closed_at?->copy()->timezone($timezone)->format('d/m/Y').' · '.($row->user?->name ?? '—'),
                                        $row->expected_cash, $row->actual_cash, $row->difference, 'Rp',
                                        auth()->user()->can('manage-register') ? route('admin.register.index') : null,
                                    ],
                                };
                            @endphp
                            <tr>
                                <th scope="row" class="py-3 pr-3 font-medium">
                                    @if ($href)<a href="{{ $href }}" class="cafe-operation-link">{{ $title }}</a>@else{{ $title }}@endif
                                    <span class="block font-normal text-[#53565a]">{{ $subtitle }}</span>
                                </th>
                                <td class="py-3 pr-3 tabular-nums">{{ $number($expected) }} {{ $unit }}</td>
                                <td class="py-3 pr-3 tabular-nums">{{ $number($actual) }} {{ $unit }}</td>
                                <td class="py-3 pr-3 font-semibold tabular-nums">{{ (float) $difference > 0 ? '+' : '' }}{{ $number($difference) }} {{ $unit }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-[#53565a]">Tidak ada selisih pada filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $rows->links() }}
        </section>
</x-cafe-operation-page>
