@extends('Admin.Users.layout')

@section('pageTitle', 'Persetujuan koneksi MCP')

@section('content')
<div class="mx-auto max-w-[540px] py-4 sm:py-8">
    <section class="overflow-hidden rounded-[16px] border border-[#e9ebf0] bg-white shadow-[0px_4px_16px_rgba(38,39,35,0.05)]" aria-labelledby="mcp-consent-heading">
        {{-- Top Banner --}}
        <div class="border-b border-[#e9ebf0] bg-gradient-to-b from-[#fff6f0] to-white px-6 py-6 text-center">
            <div class="mx-auto mb-4 flex items-center justify-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-[14px] border border-[#ffdec9] bg-white text-[#df5826] shadow-2xs">
                    <i class="fas fa-store text-[18px]" aria-hidden="true"></i>
                </span>
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#fff6f0] text-[#df5826]">
                    <i class="fas fa-arrow-right-arrow-left text-[11px]" aria-hidden="true"></i>
                </span>
                <span class="flex h-12 w-12 items-center justify-center rounded-[14px] bg-[#df5826] text-white shadow-[0px_2px_6px_rgba(223,88,38,0.28)]">
                    <i class="fas fa-plug text-[18px]" aria-hidden="true"></i>
                </span>
            </div>

            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-white px-3 py-1 text-[11px] font-semibold text-[#df5826]">
                <i class="fas fa-shield-halved text-[11px]" aria-hidden="true"></i>
                <span>OAuth 2.1 · Akses Baca</span>
            </span>

            <h1 id="mcp-consent-heading" class="mt-3 text-[20px] font-bold tracking-tight text-[#111827]">
                Hubungkan {{ $client->name }} ke OQARI
            </h1>
            <p class="mt-1 text-[13px] text-[#53565a]">
                <strong>{{ $client->name }}</strong> meminta izin untuk membaca data dari akun
                <span class="font-semibold text-[#111827]">{{ $user->shop?->name ?? 'OQARI Platform' }}</span>.
            </p>
        </div>

        {{-- Body --}}
        <div class="space-y-4 p-6">
            {{-- Permissions Scope --}}
            <div class="space-y-2.5 rounded-[12px] border border-[#ffdec9] bg-[#fff6f0]/60 p-4">
                <p class="text-[12px] font-bold uppercase tracking-wider text-[#c94d00]">Izin yang diberikan</p>
                <ul class="space-y-2 text-[13px] text-[#111827]">
                    @if($user->role === 'superadmin')
                        <li class="flex items-start gap-2.5">
                            <i class="fas fa-circle-check mt-0.5 text-[13px] text-emerald-600" aria-hidden="true"></i>
                            <span>Melihat jumlah bisnis berdasarkan status akun pada platform OQARI.</span>
                        </li>
                    @else
                        <li class="flex items-start gap-2.5">
                            <i class="fas fa-circle-check mt-0.5 text-[13px] text-emerald-600" aria-hidden="true"></i>
                            <span>Membaca ringkasan penjualan, katalog menu, dan transaksi pada cabang yang Anda kelola.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <i class="fas fa-circle-check mt-0.5 text-[13px] text-emerald-600" aria-hidden="true"></i>
                            <span>Melihat laporan stok dan operasional sesuai hak akses peran Anda.</span>
                        </li>
                    @endif
                    <li class="flex items-start gap-2.5 text-[#53565a]">
                        <i class="fas fa-lock mt-0.5 text-[13px] text-[#7f837e]" aria-hidden="true"></i>
                        <span><strong>Hanya baca (Read-only):</strong> aplikasi tidak dapat mengubah, menambah, atau menghapus data.</span>
                    </li>
                </ul>
            </div>

            {{-- Account & Revoke Info --}}
            <div class="flex items-start gap-2.5 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] px-3.5 py-3 text-[12px] text-[#53565a]">
                <i class="fas fa-circle-info mt-0.5 text-[13px] text-[#7f837e]" aria-hidden="true"></i>
                <span>Masuk sebagai <strong class="text-[#111827]">{{ $user->email }}</strong>. Anda dapat mencabut koneksi ini kapan saja dari halaman <strong>Integrasi MCP</strong>.</span>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col-reverse gap-2.5 pt-2 sm:flex-row sm:items-center sm:justify-end">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="w-full sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="inline-flex h-[42px] w-full cursor-pointer items-center justify-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-5 text-[13px] font-semibold text-[#53565a] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 sm:w-auto">
                        <i class="fas fa-xmark text-[12px]" aria-hidden="true"></i>
                        <span>Tolak</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="inline-flex h-[42px] w-full cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[13px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] sm:w-auto">
                        <i class="fas fa-check text-[12px]" aria-hidden="true"></i>
                        <span>Izinkan akses baca</span>
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection

