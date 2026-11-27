@extends('SuperAdmin.layouts.app')

@section('title', 'Dashboard - Oqari Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-2 mb-6">
        <h1 class="text-2xl font-black text-gray-800">Dashboard</h1>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-primary to-[#1e5a42] rounded-2xl p-6 text-white shadow-md">
            <p class="text-white/80 font-medium text-sm">Toko aktif</p>
            <h3 class="text-3xl font-black mt-2">{{ number_format($activeShops) }} <span class="text-base font-medium text-white/70">/ {{ number_format($totalShops) }}</span></h3>
        </div>
        <div class="bg-gradient-to-br from-gold-600 to-[#e88a42] rounded-2xl p-6 text-white shadow-md">
            <p class="text-white/80 font-medium text-sm">Total pengguna</p>
            <h3 class="text-3xl font-black mt-2">{{ number_format($totalUsers) }}</h3>
        </div>
        <div class="bg-gradient-to-br from-primary to-[#287095] rounded-2xl p-6 text-white shadow-md">
            <p class="text-white/80 font-medium text-sm">Order &middot; 30 hari terakhir</p>
            <h3 class="text-3xl font-black mt-2">{{ number_format($ordersLast30d) }}</h3>
        </div>
        <div class="bg-gradient-to-br from-[#6B4AA6] to-[#8562c4] rounded-2xl p-6 text-white shadow-md">
            <p class="text-white/80 font-medium text-sm">Omzet platform &middot; 30 hari</p>
            <h3 class="text-2xl font-black mt-2">Rp{{ number_format($revenueLast30d, 0, ',', '.') }}</h3>
        </div>
    </div>

    <!-- Custom Domain Requests Notification -->
    <div class="bg-white rounded-2xl shadow-sm border {{ $pendingDomains->where('status', \App\Models\ShopDomain::STATUS_PENDING_APPROVAL)->isNotEmpty() ? 'border-orange-300' : 'border-line' }} overflow-hidden">
        <div class="p-4 border-b border-line bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2.5">
                <h3 class="font-bold text-gray-800"><i class="fas fa-globe mr-1.5 text-orange-600"></i> Notifikasi Pengajuan Domain Kustom</h3>
                @if($pendingDomains->isNotEmpty())
                    <span class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-800">{{ $pendingDomains->count() }}</span>
                @endif
            </div>
            <span class="text-xs text-gray-500">Tambahkan sebagai Parked Domain di Hostinger sebelum menyetujui.</span>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($pendingDomains as $reqDomain)
                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-gray-900">{{ $reqDomain->domain }}</span>
                            @if($reqDomain->isPendingApproval())
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800">DNS Terverifikasi · Siap Diaktifkan</span>
                            @elseif($reqDomain->status === \App\Models\ShopDomain::STATUS_FAILED)
                                <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-700">DNS Gagal</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800">Menunggu DNS Pemilik</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-600">
                            Toko: <strong>{{ $reqDomain->shop?->name ?? '-' }}</strong> · Diajukan {{ $reqDomain->created_at->diffForHumans() }}
                        </p>
                        @if($reqDomain->last_error)
                            <p class="text-xs text-red-600">{{ $reqDomain->last_error }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('superadmin.custom-domains.approve', $reqDomain->id) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3.5 py-2 text-xs font-bold text-white transition cursor-pointer">
                                Setujui &amp; Aktifkan
                            </button>
                        </form>
                        <form method="POST" action="{{ route('superadmin.custom-domains.reject', $reqDomain->id) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-red-200 bg-white hover:bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition cursor-pointer">
                                Tolak
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-gray-500 text-sm">Tidak ada pengajuan domain kustom yang menunggu persetujuan.</div>
            @endforelse
        </div>
    </div>

    <!-- Activity Log -->
    <div class="bg-white rounded-2xl shadow-sm border border-line overflow-hidden">
        <div class="p-4 border-b border-line bg-gray-50 flex items-center justify-between">
            <h3 class="font-bold text-gray-800"><i class="fas fa-clock-rotate-left mr-2"></i> Aktivitas Super Admin Terbaru</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($recentActivity as $log)
            <div class="p-4 text-sm flex justify-between">
                <span>
                    <span class="font-bold">{{ $log->actor->name ?? 'System' }}</span> 
                    &middot; {{ str_replace('.', ' ', $log->action) }} 
                    &middot; <span class="text-gray-500">{{ $log->target_label }}</span>
                </span>
                <span class="text-gray-400 text-xs">{{ $log->created_at->diffForHumans() }}</span>
            </div>
            @empty
            <div class="p-4 text-center text-gray-500 text-sm">Belum ada aktivitas tercatat</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
