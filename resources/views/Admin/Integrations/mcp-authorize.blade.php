@extends('Admin.Users.layout')

@section('pageTitle', 'Persetujuan koneksi MCP')

@section('content')
<section class="mx-auto max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6" aria-labelledby="mcp-consent-heading">
    <h1 id="mcp-consent-heading" class="text-xl font-bold text-gray-900">Hubungkan aplikasi ke OQARI</h1>
    <p class="text-sm text-gray-700"><strong>{{ $client->name }}</strong> meminta akses baca melalui MCP.</p>
    <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
        @if($user->role === 'superadmin')
            Aplikasi dapat melihat jumlah bisnis menurut status akun.
        @else
            Aplikasi dapat membaca penjualan dan menu terlaris di cabang yang Anda kelola.
        @endif
    </p>
    <p class="text-sm text-gray-600">Anda bisa mencabut koneksi dari halaman Integrasi MCP.</p>
    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button class="rounded-lg bg-[#c54b00] px-4 py-2 text-sm font-semibold text-white hover:bg-orange-800">Izinkan akses baca</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold hover:bg-gray-50">Tolak</button>
        </form>
    </div>
</section>
@endsection

