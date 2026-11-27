@extends('SuperAdmin.layouts.app')

@section('title', 'Domain Kustom - Oqari Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Persetujuan Domain Kustom</h1>
            <p class="text-sm text-gray-600 mt-0.5">Kelola pengajuan domain toko dan aktifkan setelah ditambahkan sebagai Parked Domain di Hostinger.</p>
        </div>
        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-800">
            <i class="fas fa-server text-[11px]" aria-hidden="true"></i>
            <span>Hostinger Parked Domain Mode</span>
        </span>
    </div>

    {{-- Filter Tabs --}}
    <div class="flex flex-wrap gap-2">
        @php
            $filterTabs = [
                'all' => 'Semua',
                'pending_approval' => 'Siap Disetujui',
                'active' => 'Aktif',
                'pending' => 'Menunggu DNS',
                'failed' => 'Gagal',
            ];
        @endphp
        @foreach($filterTabs as $key => $label)
            <a href="{{ route('superadmin.custom-domains.index', $key === 'all' ? [] : ['status' => $key]) }}"
               class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold transition {{ $status === $key ? 'border-[#df5826] bg-[#fff6f0] text-[#df5826]' : 'border-line bg-white text-gray-600 hover:bg-gray-50' }}">
                <span>{{ $label }}</span>
                <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-bold border border-gray-200">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    {{-- Domains List --}}
    <div class="bg-white rounded-2xl shadow-sm border border-line overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-gray-50 border-b border-line text-[11px] font-bold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-5 py-3.5">Domain</th>
                        <th class="px-4 py-3.5">Toko</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5">Diajukan</th>
                        <th class="px-5 py-3.5 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($domains as $item)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="px-5 py-4">
                                <div class="font-bold text-gray-900">{{ $item->domain }}</div>
                                @if($item->last_error)
                                    <div class="text-xs text-red-600 mt-0.5">{{ $item->last_error }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-800">{{ $item->shop?->name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">/{{ $item->shop?->slug }}</div>
                            </td>
                            <td class="px-4 py-4">
                                @if($item->isActive())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @elseif($item->isPendingApproval())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">
                                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                        Siap Disetujui
                                    </span>
                                @elseif($item->status === \App\Models\ShopDomain::STATUS_FAILED)
                                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Ditolak / Gagal</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Menunggu DNS</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs text-gray-500">
                                <div>{{ $item->created_at->format('d/m/Y H:i') }}</div>
                                @if($item->verified_at)
                                    <div class="text-emerald-700">Aktif {{ $item->verified_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @unless($item->isActive())
                                        <form method="POST" action="{{ route('superadmin.custom-domains.approve', $item->id) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white transition cursor-pointer">
                                                Setujui &amp; Aktifkan
                                            </button>
                                        </form>
                                    @endunless
                                    @if($item->isPendingApproval() || $item->isActive())
                                        <form method="POST" action="{{ route('superadmin.custom-domains.reject', $item->id) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-red-200 bg-white hover:bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition cursor-pointer">
                                                {{ $item->isActive() ? 'Nonaktifkan' : 'Tolak' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-gray-500 text-sm">
                                Belum ada pengajuan domain kustom pada filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($domains->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $domains->links() }}</div>
        @endif
    </div>
</div>
@endsection
