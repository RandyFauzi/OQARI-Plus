@extends('Admin.Users.layout')

@section('pageTitle', auth()->user()->role === 'owner' ? 'Semua Cabang' : 'Cabang Saya')

@section('content')
@php
    $isOwner = auth()->user()->role === 'owner';
    $hasBranchErrors = $errors->hasAny(['name', 'address']) || old('name') !== null || old('address') !== null;
@endphp

<div x-data="{
    branchSearch: '',
    addBranchModal: @js($hasBranchErrors),
    submitting: false,
    matchesBranch(text) {
        const q = this.branchSearch.trim().toLowerCase();
        return !q || text.toLowerCase().includes(q);
    }
}" @keydown.escape.window="addBranchModal = false" class="space-y-5">

    {{-- Page Header --}}
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[25px] font-semibold leading-tight tracking-tight text-[#111827]">
                {{ $isOwner ? 'Semua Cabang' : 'Cabang Saya' }}
            </h1>
            <p class="mt-0.5 text-[13px] text-[#53565a]">
                {{ auth()->user()->shop->name }} · {{ $isOwner ? 'Kelola outlet dan penugasan staf per cabang.' : 'Buka outlet yang ditugaskan ke akun Anda.' }}
            </p>
        </div>

        @if($isOwner)
            <button type="button" @click="addBranchModal = true"
                    class="inline-flex h-[38px] w-fit cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
                <i class="fas fa-plus text-[11px]" aria-hidden="true"></i>
                <span>Tambah cabang</span>
            </button>
        @endif
    </header>

    @if(session('success'))
        <div role="status" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] font-semibold text-emerald-800">
            <i class="fas fa-circle-check shrink-0" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($isOwner && isset($dashboard) && $dashboard)
        <dl class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <dt class="text-[12px] font-medium text-[#53565a]">Penjualan periode ini</dt>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#fff6f0] text-[#df5826]">
                        <i class="fas fa-coins text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <dd class="flex flex-col">
                    <span class="text-[22px] font-bold leading-[28px] tabular-nums text-[#111827]">Rp {{ number_format($dashboard['sales'], 0, ',', '.') }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Akumulasi omzet semua cabang</span>
                </dd>
            </div>

            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <dt class="text-[12px] font-medium text-[#53565a]">Transaksi lunas</dt>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#ecfdf5] text-[#059669]">
                        <i class="fas fa-receipt text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <dd class="flex flex-col">
                    <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $dashboard['orders'] }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Pesanan terbayar</span>
                </dd>
            </div>

            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <dt class="text-[12px] font-medium text-[#53565a]">Cabang aktif</dt>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                        <i class="fas fa-store text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <dd class="flex flex-col">
                    <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $branches->count() }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Outlet terdaftar &amp; beroperasi</span>
                </dd>
            </div>

            <div class="flex h-[108px] flex-col justify-between rounded-[14px] border border-[#e9ebf0] bg-white p-4 drop-shadow-[0px_2px_4px_rgba(38,39,35,0.04)]">
                <div class="flex items-center justify-between">
                    <dt class="text-[12px] font-medium text-[#53565a]">Penugasan staf</dt>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                        <i class="fas fa-users text-[13px]" aria-hidden="true"></i>
                    </span>
                </div>
                <dd class="flex flex-col">
                    <span class="text-[24px] font-bold leading-[28px] tabular-nums text-[#111827]">{{ $branches->sum('memberships_count') }}</span>
                    <span class="text-[11px] leading-[14px] text-[#7f837e]">Total akses staf di seluruh cabang</span>
                </dd>
            </div>
        </dl>
    @endif

    {{-- Search & Filter Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <h2 id="daftar-outlet" class="scroll-mt-6 text-[16px] font-bold text-[#111827]">Daftar Outlet</h2>
            <span class="rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-0.5 text-[11px] font-bold text-[#df5826]">
                {{ $branches->count() }}
            </span>
        </div>

        @if($branches->count() > 1)
            <div class="flex h-[36px] w-full items-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-3 shadow-2xs transition focus-within:border-[#df5826] focus-within:ring-2 focus-within:ring-[#df5826]/15 sm:w-64">
                <i class="fas fa-search text-[12px] text-gray-400" aria-hidden="true"></i>
                <input type="search" aria-label="Cari cabang" x-model="branchSearch" placeholder="Cari nama cabang atau alamat..."
                       class="w-full border-0 bg-transparent p-0 text-[12px] text-[#111827] placeholder-[#7f837e] outline-none focus:outline-none focus:ring-0 focus:shadow-none">
                <button type="button" aria-label="Bersihkan pencarian" x-cloak x-show="branchSearch.length > 0" @click="branchSearch = ''" class="cursor-pointer text-xs text-gray-400 hover:text-gray-600 focus:outline-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif
    </div>

    {{-- Branch Cards Grid --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($branches as $branch)
            <article x-show="matchesBranch(@js($branch->name . ' ' . ($branch->address ?? '')))"
                     class="flex flex-col justify-between gap-4 rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] transition hover:border-[#ffd2b8]">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-[#fff6f0] text-[#df5826]">
                                <i class="fas fa-store text-[15px]" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <h3 class="truncate text-[15px] font-bold text-[#111827]">{{ $branch->name }}</h3>
                                <p class="mt-0.5 truncate text-[12px] text-[#7f837e]">
                                    {{ $branch->address ?: 'Alamat belum diatur' }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Aktif
                        </span>
                    </div>

                    @if($isOwner)
                        <p class="flex items-center gap-2 rounded-[10px] border border-[#f1f3f5] bg-[#f8f9fa] px-3 py-2.5 text-[12px] font-semibold text-[#53565a]">
                            <i class="fas fa-users text-[#a83c17]" aria-hidden="true"></i>
                            {{ $branch->memberships_count }} penugasan staf
                        </p>
                    @endif
                    @php
                        $branchMenuUrl = auth()->user()?->shop?->storefrontBaseUrl($branch->slug);
                    @endphp
                    @if($branchMenuUrl)
                        <div class="flex items-center justify-between gap-2 rounded-[10px] border border-[#f1f3f5] bg-[#f8f9fa] px-3 py-2 text-[11.5px]" x-data="{ copied: false }">
                            <div class="min-w-0">
                                <span class="block text-[10px] font-semibold text-[#7f837e]">Link Menu Outlet</span>
                                <a href="{{ $branchMenuUrl }}" target="_blank" rel="noopener" class="block truncate font-mono font-semibold text-[#df5826] hover:underline">
                                    {{ $branchMenuUrl }}
                                </a>
                            </div>
                            <button type="button"
                                    @click="navigator.clipboard?.writeText('{{ $branchMenuUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="shrink-0 rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 text-[11px] font-semibold text-[#53565a] hover:text-[#df5826] cursor-pointer">
                                <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                            </button>
                        </div>
                    @endif
                    @if($staticQrisReady)
                        <details class="group/qris rounded-[12px] border border-[#e9ebf0] bg-white transition open:border-[#ffd2b8]" @if($errors->getBag('qris'.$branch->id)->any()) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-[12px] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#111827] transition hover:bg-[#fafbfc]">
                                <span class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[8px] bg-[#fff6f0] text-[#df5826]">
                                        <i class="fas fa-qrcode text-[12px]" aria-hidden="true"></i>
                                    </span>
                                    <span>QRIS Statis</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    @if($staticQrisUrls[$branch->id])
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-[#e9ebf0] bg-[#f8f9fa] px-2 py-0.5 text-[10px] font-medium text-[#7f837e]">
                                            Belum diatur
                                        </span>
                                    @endif
                                    <i class="fas fa-chevron-down text-[10px] text-[#7f837e] transition-transform duration-200 group-open/qris:rotate-180" aria-hidden="true"></i>
                                </span>
                            </summary>

                            <div class="space-y-3 border-t border-[#f1f3f5] p-3.5"
                                 x-data="{
                                     fileName: '',
                                     previewUrl: @js($staticQrisUrls[$branch->id] ?? ''),
                                     handleFile(event) {
                                         const file = event.target.files?.[0];
                                         if (!file) return;
                                         this.fileName = file.name;
                                         this.previewUrl = URL.createObjectURL(file);
                                     }
                                 }">
                                <div x-show="previewUrl" x-cloak class="flex flex-col items-center justify-center rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] p-3">
                                    <img :src="previewUrl" alt="QRIS {{ $branch->name }}" class="h-32 w-32 rounded-lg border border-[#e9ebf0] bg-white object-contain p-1.5" loading="lazy">
                                    <span x-show="fileName" x-text="'Pratinjau: ' + fileName" class="mt-2 max-w-full truncate text-[11px] font-medium text-[#df5826]"></span>
                                </div>

                                <form action="{{ route('admin.branches.qris.update', $branch) }}" method="POST" enctype="multipart/form-data" class="space-y-2.5">
                                    @csrf
                                    @method('PUT')
                                    <label for="static-qris-{{ $branch->id }}"
                                           class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-[10px] border border-dashed border-[#ffd2b8] bg-[#fff6f0]/40 px-3 py-3.5 text-center transition hover:border-[#df5826] hover:bg-[#fff6f0]">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-[#df5826] shadow-2xs">
                                            <i class="fas fa-cloud-arrow-up text-[13px]" aria-hidden="true"></i>
                                        </span>
                                        <span class="max-w-full truncate text-[12px] font-semibold text-[#111827]" x-text="fileName || 'Pilih gambar QRIS outlet'">Pilih gambar QRIS outlet</span>
                                        <span class="text-[11px] text-[#7f837e]">PNG atau JPG · Maks. 3 MB (200–2400 px)</span>
                                        <input type="file" id="static-qris-{{ $branch->id }}" name="static_qris" accept="image/png,image/jpeg" required @change="handleFile($event)" class="sr-only">
                                    </label>

                                    @foreach($errors->getBag('qris'.$branch->id)->get('static_qris') as $error)
                                        <p class="flex items-center gap-1.5 text-[11px] font-medium text-red-600" role="alert">
                                            <i class="fas fa-circle-exclamation shrink-0" aria-hidden="true"></i>
                                            <span>{{ $error }}</span>
                                        </p>
                                    @endforeach

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="submit" class="inline-flex h-[36px] flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-[9px] bg-[#df5826] px-3 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.2)] transition hover:bg-[#c94d00]">
                                            <i class="fas fa-check text-[11px]" aria-hidden="true"></i>
                                            <span>{{ $staticQrisUrls[$branch->id] ? 'Ganti gambar' : 'Simpan QRIS' }}</span>
                                        </button>
                                        @if($staticQrisUrls[$branch->id])
                                            <button type="submit" form="remove-qris-{{ $branch->id }}" class="inline-flex h-[36px] cursor-pointer items-center justify-center gap-1.5 rounded-[9px] border border-red-200 bg-white px-3 text-[12px] font-semibold text-red-600 transition hover:bg-red-50">
                                                <i class="fas fa-ban text-[11px]" aria-hidden="true"></i>
                                                <span>Nonaktifkan QRIS</span>
                                            </button>
                                        @endif
                                    </div>
                                </form>

                                @if($staticQrisUrls[$branch->id])
                                    <form id="remove-qris-{{ $branch->id }}" action="{{ route('admin.branches.qris.destroy', $branch) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-[#f1f3f5] pt-3.5">
                    <a class="inline-flex h-[38px] flex-1 items-center justify-center gap-1.5 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.2)] transition hover:bg-[#c94d00]"
                       href="{{ route('branch.admin.dashboard', ['operatingBranch' => $branch->slug]) }}">
                        <span>Buka cabang</span>
                        <i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i>
                    </a>
                    @if($isOwner)
                        <a class="inline-flex h-[38px] items-center justify-center gap-1.5 rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[12px] font-semibold text-[#111827] transition hover:border-[#df5826] hover:text-[#df5826]"
                           href="{{ route('admin.branches.staff', $branch) }}">
                            <i class="fas fa-users text-[11px]" aria-hidden="true"></i>
                            <span>Kelola staf</span>
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-[14px] border border-[#e9ebf0] bg-white p-10 text-center">
                <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#fff6f0] text-[#df5826]">
                        <i class="fas fa-store text-lg" aria-hidden="true"></i>
                    </div>
                    <p class="text-[14px] font-bold text-[#111827]">Belum ada cabang aktif</p>
                    <p class="text-[12px] text-[#7f837e]">Tambahkan cabang pertama Anda untuk mulai memisahkan operasional per outlet.</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- MODAL: TAMBAH CABANG --}}
    @if($isOwner)
        <div x-show="addBranchModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]"
             @click.self="addBranchModal = false">
            <div role="dialog" aria-modal="true" aria-labelledby="add-branch-title" class="w-full max-w-md overflow-hidden rounded-[18px] border border-[#e9ebf0] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-[#e9ebf0] px-6 py-4">
                    <div>
                        <h2 id="add-branch-title" class="text-[16px] font-bold text-[#111827]">Tambah cabang</h2>
                        <p class="text-[12px] text-[#53565a]">Daftarkan outlet baru di bawah {{ auth()->user()->shop->name }}.</p>
                    </div>
                    <button type="button" @click="addBranchModal = false"
                            class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#53565a] transition hover:bg-gray-100 hover:text-[#111827]"
                            aria-label="Tutup modal Tambah cabang">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form action="{{ route('admin.branches.store') }}" method="POST" @submit="submitting = true" class="space-y-4 p-6">
                    @csrf
                    @if($errors->any())
                        <div role="alert" class="rounded-[10px] border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] text-rose-700">
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="space-y-1.5">
                        <label for="branch-name-input" class="block text-[12px] font-semibold text-[#111827]">
                            Nama cabang <span class="text-rose-500">*</span>
                        </label>
                        <input id="branch-name-input" name="name" value="{{ old('name') }}" required maxlength="120"
                               placeholder="Contoh: Outlet Kemang"
                               class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826] focus:ring-2 focus:ring-[#df5826]/15">
                    </div>

                    <div class="space-y-1.5">
                        <label for="branch-address-input" class="block text-[12px] font-semibold text-[#111827]">
                            Alamat (opsional)
                        </label>
                        <input id="branch-address-input" name="address" value="{{ old('address') }}" maxlength="255"
                               placeholder="Contoh: Jl. Kemang Raya No. 12"
                               class="h-[40px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] outline-none transition focus:border-[#df5826] focus:ring-2 focus:ring-[#df5826]/15">
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button type="button" @click="addBranchModal = false"
                                class="h-[38px] cursor-pointer rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] transition hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" :disabled="submitting"
                                class="inline-flex h-[38px] cursor-pointer items-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60">
                            <i class="fas fa-plus text-[11px]" aria-hidden="true"></i>
                            <span x-text="submitting ? 'Menambahkan...' : 'Tambah'">Tambah</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
