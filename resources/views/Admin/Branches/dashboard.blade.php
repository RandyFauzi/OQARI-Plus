@extends('Admin.Users.layout')

@section('pageTitle', 'Dashboard Owner')

@section('content')
<div class="space-y-6 font-['DM_Sans',sans-serif]">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[25px] font-semibold leading-[30px] tracking-tight text-[#111827]">Dashboard Owner</h1>
            <p class="mt-1 text-[13px] leading-[18px] text-[#7f837e]">{{ auth()->user()->shop->name }} · Pantau performa penjualan seluruh outlet.</p>
        </div>
        <div>
            <a href="{{ route('admin.branches.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-[8px] bg-gradient-to-r from-[#f9731f] to-[#ea580c] px-4 py-2.5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(234,88,12,0.2)] transition-all hover:from-[#ea580c] hover:to-[#c2410c] active:scale-95">
                <i class="fas fa-store text-[13px]" aria-hidden="true"></i>
                <span>Semua Cabang</span>
            </a>
        </div>
    </header>

    @include('Admin.Branches.analytics')
</div>
@endsection
