@extends('Admin.Users.layout')

@section('pageTitle', 'Domain Kustom')

@section('content')
<div class="space-y-6" x-data="{
    copiedKey: '',
    timer: null,
    async copyText(key, value) {
        try {
            await navigator.clipboard.writeText(value);
            this.copiedKey = key;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.copiedKey = ''; }, 2000);
        } catch {
            alert('Salin manual: ' + value);
        }
    }
}">
    {{-- Page Header --}}
    <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[24px] font-semibold leading-tight tracking-tight text-[#111827]">Domain Kustom</h1>
            <p class="mt-1 text-[13px] text-[#53565a]">
                Pakai nama website toko Anda sendiri (contoh: <span class="font-medium text-[#111827]">order.tokoanda.com</span>) untuk dashboard &amp; menu pelanggan.
            </p>
        </div>

        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-3 py-1.5 text-[11px] font-semibold text-[#df5826]">
            <i class="fas fa-crown text-[10px]" aria-hidden="true"></i>
            <span>Khusus Owner</span>
        </span>
    </header>

    <div class="grid gap-5 lg:grid-cols-12">
        {{-- Left: Add Domain Form --}}
        <section class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] lg:col-span-5" aria-labelledby="custom-domain-add-heading">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-[#fff6f0] text-[#df5826]">
                    <i class="fas fa-globe text-[15px]" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 id="custom-domain-add-heading" class="text-[16px] font-bold text-[#111827]">Pasang Domain Baru</h2>
                    <p class="mt-0.5 text-[12px] text-[#7f837e]">Cukup masukkan alamat domain atau subdomain yang Anda punya.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.custom-domain.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="custom-domain-input" class="block text-[12px] font-semibold text-[#111827]">Alamat Domain Anda</label>
                    <input id="custom-domain-input" name="domain" type="text" required maxlength="253"
                           value="{{ old('domain') }}" placeholder="Contoh: order.burgerkat.com"
                           class="mt-1.5 block h-[42px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] placeholder-[#7f837e] transition focus:border-[#df5826] focus:outline-none focus:ring-2 focus:ring-[#df5826]/15">
                    <p class="mt-1.5 text-[11px] text-[#7f837e]">
                        Tulis langsung nama domainnya saja (tanpa <code class="font-mono">https://</code>).
                    </p>
                </div>

                @php
                    $defaultShopUrl = rtrim((string) config('app.url'), '/').'/'.$shop->slug;
                @endphp
                <div class="rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa] p-3.5 text-[12px] text-[#53565a] space-y-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-[#111827]">Link Cadangan Toko</p>
                        <button type="button" @click="copyText('default-url', '{{ $defaultShopUrl }}')" class="text-[11px] font-semibold text-[#df5826] hover:underline cursor-pointer">
                            <span x-text="copiedKey === 'default-url' ? 'Tersalin!' : 'Salin'">Salin</span>
                        </button>
                    </div>
                    <p class="break-all font-mono text-[11.5px] text-[#df5826]">{{ $defaultShopUrl }}</p>
                    <p class="text-[11px] text-[#7f837e]">Link ini tetap aktif sebagai cadangan kapan pun dibutuhkan.</p>
                </div>

                <button type="submit"
                        class="inline-flex h-[42px] w-full cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[13px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
                    <i class="fas fa-plus text-[12px]" aria-hidden="true"></i>
                    <span>Daftarkan Domain</span>
                </button>
            </form>
        </section>

        {{-- Right: Domain List & Simple Setup --}}
        <section class="rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)] lg:col-span-7 overflow-hidden" aria-labelledby="custom-domain-list-heading">
            <div class="flex items-center justify-between gap-2 border-b border-[#e9ebf0] px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <h2 id="custom-domain-list-heading" class="text-[16px] font-bold text-[#111827]">Domain Anda</h2>
                    <span class="rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-0.5 text-[11px] font-bold text-[#df5826]">
                        {{ $domains->count() }}
                    </span>
                </div>
            </div>

            <div class="divide-y divide-[#f1f3f5]">
                @forelse($domains as $item)
                    <div class="p-5 space-y-4" x-data="{ showDns: {{ $item->isActive() || $item->isPendingApproval() ? 'false' : 'true' }} }">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($item->isActive())
                                        <a href="https://{{ $item->domain }}" target="_blank" rel="noopener" class="truncate text-[16px] font-bold text-[#111827] hover:text-[#df5826] transition">
                                            {{ $item->domain }} <i class="fas fa-arrow-up-right-from-square text-[11px] ml-0.5 text-[#df5826]"></i>
                                        </a>
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif &amp; Siap Dipakai
                                        </span>
                                    @elseif($item->isPendingApproval())
                                        <p class="truncate text-[16px] font-bold text-[#111827]">{{ $item->domain }}</p>
                                        <span class="inline-flex items-center gap-1 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                            Sedang Diaktifkan Admin
                                        </span>
                                    @elseif($item->status === \App\Models\ShopDomain::STATUS_FAILED)
                                        <p class="truncate text-[16px] font-bold text-[#111827]">{{ $item->domain }}</p>
                                        <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-700">
                                            Belum Terhubung
                                        </span>
                                    @else
                                        <p class="truncate text-[16px] font-bold text-[#111827]">{{ $item->domain }}</p>
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">
                                            Perlu Pengaturan DNS
                                        </span>
                                    @endif
                                </div>

                                @if($item->isPendingApproval())
                                    <p class="text-[12px] text-sky-700">Pengaturan DNS sudah benar! Tim kami sedang mengaktifkan domain Anda.</p>
                                @elseif($item->last_error && !$item->isActive())
                                    <p class="text-[12px] text-red-600">DNS belum terdeteksi. Pastikan 2 data di bawah sudah dimasukkan dengan benar, lalu klik <strong>Cek Koneksi</strong>.</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                @if(!$item->isActive())
                                    <form method="POST" action="{{ route('admin.custom-domain.verify', $item->id) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#df5826] px-3.5 text-[12px] font-semibold text-white shadow-xs transition hover:bg-[#c94d00]">
                                            <i class="fas fa-rotate text-[11px]" aria-hidden="true"></i>
                                            <span>Cek Koneksi</span>
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.custom-domain.destroy', $item->id) }}"
                                      @submit="if (!confirm('Hapus domain {{ $item->domain }}?')) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] border border-[#e9ebf0] bg-white px-3 text-[12px] font-semibold text-[#53565a] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600">
                                        <i class="fas fa-trash-can text-[11px]" aria-hidden="true"></i>
                                        <span>Hapus</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Quick Links when Active --}}
                        @if($item->isActive())
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <div class="flex items-center justify-between gap-2 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] px-3.5 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold text-[#7f837e]">Dashboard &amp; Kasir</p>
                                        <a href="https://{{ $item->domain }}" target="_blank" rel="noopener" class="block truncate text-[12.5px] font-semibold text-[#111827] hover:text-[#df5826]">
                                            https://{{ $item->domain }}
                                        </a>
                                    </div>
                                    <button type="button" @click="copyText('dash-{{ $item->id }}', 'https://{{ $item->domain }}')" class="shrink-0 rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 text-[11px] font-semibold text-[#53565a] hover:text-[#df5826] cursor-pointer">
                                        <span x-text="copiedKey === 'dash-{{ $item->id }}' ? 'Tersalin!' : 'Salin'">Salin</span>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between gap-2 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] px-3.5 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold text-[#7f837e]">Menu Pelanggan (QR)</p>
                                        <a href="https://{{ $item->domain }}/menu" target="_blank" rel="noopener" class="block truncate text-[12.5px] font-semibold text-[#111827] hover:text-[#df5826]">
                                            https://{{ $item->domain }}/menu
                                        </a>
                                    </div>
                                    <button type="button" @click="copyText('menu-{{ $item->id }}', 'https://{{ $item->domain }}/menu')" class="shrink-0 rounded-lg border border-[#e9ebf0] bg-white px-2.5 py-1 text-[11px] font-semibold text-[#53565a] hover:text-[#df5826] cursor-pointer">
                                        <span x-text="copiedKey === 'menu-{{ $item->id }}' ? 'Tersalin!' : 'Salin'">Salin</span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-[#7f837e]">
                                <button type="button" @click="showDns = !showDns" class="font-medium text-[#53565a] hover:text-[#df5826] cursor-pointer">
                                    <span x-text="showDns ? 'Sembunyikan pengaturan DNS ▲' : 'Lihat pengaturan DNS ▼'">Lihat pengaturan DNS ▼</span>
                                </button>
                                <span>Terverifikasi {{ $item->verified_at?->diffForHumans() }}</span>
                            </div>
                        @elseif($item->isPendingApproval())
                            <button type="button" @click="showDns = !showDns" class="text-[11.5px] font-medium text-[#53565a] hover:text-[#df5826] cursor-pointer">
                                <span x-text="showDns ? 'Sembunyikan pengaturan DNS ▲' : 'Lihat pengaturan DNS ▼'">Lihat pengaturan DNS ▼</span>
                            </button>
                        @endif

                        {{-- DNS Setup Instructions (Shown automatically only when needed, collapsible when active) --}}
                        <div x-show="showDns" x-cloak class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4 space-y-3">
                            <p class="text-[12px] font-semibold text-[#111827]">
                                Buka menu <strong>DNS</strong> di tempat Anda beli domain (Niagahoster, Rumahweb, Cloudflare, dll.), lalu tambahkan 2 data ini:
                            </p>

                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <div class="rounded-[10px] border border-[#e9ebf0] bg-white p-3 space-y-2">
                                    <span class="inline-block rounded bg-[#fff6f0] px-2 py-0.5 text-[11px] font-bold text-[#df5826]">Data 1 · Tipe CNAME</span>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-[11px] text-[#7f837e]">Nama / Host:</p>
                                            <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $item->domain }}</p>
                                        </div>
                                        <button type="button" @click="copyText('cname-host-{{ $item->id }}', '{{ $item->domain }}')" class="shrink-0 text-[11px] font-semibold text-[#df5826] hover:underline cursor-pointer">
                                            <span x-text="copiedKey === 'cname-host-{{ $item->id }}' ? 'Tersalin!' : 'Salin'">Salin</span>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 border-t border-[#f1f3f5] pt-1.5">
                                        <div class="min-w-0">
                                            <p class="text-[11px] text-[#7f837e]">Arahkan ke (Target):</p>
                                            <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $cnameTarget }}</p>
                                        </div>
                                        <button type="button" @click="copyText('cname-{{ $item->id }}', '{{ $cnameTarget }}')" class="shrink-0 text-[11px] font-semibold text-[#df5826] hover:underline cursor-pointer">
                                            <span x-text="copiedKey === 'cname-{{ $item->id }}' ? 'Tersalin!' : 'Salin'">Salin</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="rounded-[10px] border border-[#e9ebf0] bg-white p-3 space-y-2">
                                    <span class="inline-block rounded bg-sky-50 px-2 py-0.5 text-[11px] font-bold text-sky-700">Data 2 · Tipe TXT</span>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-[11px] text-[#7f837e]">Nama / Host:</p>
                                            <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $item->txtRecordName() }}</p>
                                        </div>
                                        <button type="button" @click="copyText('txt-host-{{ $item->id }}', '{{ $item->txtRecordName() }}')" class="shrink-0 text-[11px] font-semibold text-[#df5826] hover:underline cursor-pointer">
                                            <span x-text="copiedKey === 'txt-host-{{ $item->id }}' ? 'Tersalin!' : 'Salin'">Salin</span>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 border-t border-[#f1f3f5] pt-1.5">
                                        <div class="min-w-0">
                                            <p class="text-[11px] text-[#7f837e]">Isi / Value:</p>
                                            <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $item->txtRecordValue() }}</p>
                                        </div>
                                        <button type="button" @click="copyText('txt-{{ $item->id }}', '{{ $item->txtRecordValue() }}')" class="shrink-0 text-[11px] font-semibold text-[#df5826] hover:underline cursor-pointer">
                                            <span x-text="copiedKey === 'txt-{{ $item->id }}' ? 'Tersalin!' : 'Salin'">Salin</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-[14px] bg-[#fff6f0] text-[#df5826]">
                            <i class="fas fa-globe text-[18px]" aria-hidden="true"></i>
                        </div>
                        <p class="text-[14px] font-semibold text-[#111827]">Belum ada domain kustom</p>
                        <p class="mt-1 text-[12px] text-[#7f837e]">Masukkan nama domain Anda di sebelah kiri untuk mulai menghubungkan toko.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Panduan Singkat Pemilik Toko --}}
    <section class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]" aria-labelledby="custom-domain-guide-heading">
        <div class="flex items-center gap-3 border-b border-[#e9ebf0] px-5 py-3.5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] bg-[#fff6f0] text-[#df5826]">
                <i class="fas fa-book-open text-[13px]" aria-hidden="true"></i>
            </span>
            <h2 id="custom-domain-guide-heading" class="text-[14px] font-bold text-[#111827]">Cara Menghubungkan Domain Kustom (3 Langkah Mudah)</h2>
        </div>

        <div class="grid gap-3 p-5 md:grid-cols-3">
            <div class="rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa] p-4 space-y-1.5">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#fff6f0] text-[11px] font-bold text-[#df5826] ring-1 ring-[#ffdec9]">1</span>
                <p class="text-[13px] font-bold text-[#111827]">Tulis Nama Domain</p>
                <p class="text-[12px] leading-relaxed text-[#53565a]">Ketik alamat domain Anda (misalnya <span class="font-medium text-[#111827]">order.tokoanda.com</span>) lalu tekan <strong>Daftarkan Domain</strong>.</p>
            </div>
            <div class="rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa] p-4 space-y-1.5">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#fff6f0] text-[11px] font-bold text-[#df5826] ring-1 ring-[#ffdec9]">2</span>
                <p class="text-[13px] font-bold text-[#111827]">Salin 2 Data ke Provider Domain</p>
                <p class="text-[12px] leading-relaxed text-[#53565a]">Salin <strong>Data 1 (CNAME)</strong> dan <strong>Data 2 (TXT)</strong> yang muncul ke pengaturan DNS tempat Anda membeli domain.</p>
            </div>
            <div class="rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa] p-4 space-y-1.5">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#fff6f0] text-[11px] font-bold text-[#df5826] ring-1 ring-[#ffdec9]">3</span>
                <p class="text-[13px] font-bold text-[#111827]">Klik Cek Koneksi</p>
                <p class="text-[12px] leading-relaxed text-[#53565a]">Tunggu beberapa menit, lalu klik <strong>Cek Koneksi</strong>. Setelah disetujui admin, domain langsung aktif otomatis.</p>
            </div>
        </div>
    </section>
</div>
@endsection
