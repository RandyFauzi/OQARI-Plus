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
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[25px] font-semibold leading-tight tracking-tight text-[#111827]">Domain Kustom</h1>
            <p class="mt-0.5 text-[13px] text-[#53565a]">
                Gunakan domain atau subdomain bisnis Anda sendiri untuk halaman menu &amp; pemesanan QR pelanggan.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-3 py-1.5 text-[11px] font-semibold text-[#df5826]">
                <i class="fas fa-crown text-[10px]" aria-hidden="true"></i>
                <span>Owner Only</span>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#e9ebf0] bg-white px-3 py-1.5 text-[11px] font-medium text-[#53565a]">
                <span class="h-1.5 w-1.5 rounded-full {{ $cloudflareEnabled ? 'bg-emerald-500' : 'bg-amber-400' }}" aria-hidden="true"></span>
                <span>Cloudflare for SaaS SSL</span>
            </span>
        </div>
    </header>

    <div class="grid gap-5 lg:grid-cols-12">
        {{-- Left: Add Domain Form --}}
        <section class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)] lg:col-span-5" aria-labelledby="custom-domain-add-heading">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-[#fff6f0] text-[#df5826]">
                    <i class="fas fa-globe text-[15px]" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 id="custom-domain-add-heading" class="text-[16px] font-bold text-[#111827]">Tambah Domain Bisnis</h2>
                    <p class="mt-0.5 text-[12px] text-[#7f837e]">Disarankan memakai subdomain seperti <code class="rounded bg-[#f8f9fa] px-1 py-0.5 font-mono text-[11px]">order.tokoanda.com</code>.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.custom-domain.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="custom-domain-input" class="block text-[12px] font-semibold text-[#111827]">Nama Domain / Subdomain</label>
                    <input id="custom-domain-input" name="domain" type="text" required maxlength="253"
                           value="{{ old('domain') }}" placeholder="Contoh: order.burgerkat.com"
                           class="mt-1.5 block h-[42px] w-full rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 text-[13px] text-[#111827] placeholder-[#7f837e] transition focus:border-[#df5826] focus:outline-none focus:ring-2 focus:ring-[#df5826]/15">
                    <p class="mt-1.5 text-[11px] text-[#7f837e]">
                        Masukkan tanpa <code class="font-mono">https://</code> atau garis miring di akhir.
                    </p>
                </div>

                <div class="rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa] p-3.5 text-[12px] text-[#53565a] space-y-1.5">
                    <p class="font-semibold text-[#111827]">URL Default Toko Anda:</p>
                    <p class="break-all font-mono text-[11.5px] text-[#df5826]">{{ route('shop.menu', ['slug' => $shop->slug]) }}</p>
                    <p class="text-[11px] text-[#7f837e]">URL default tetap aktif sebagai cadangan meskipun domain kustom dipasang.</p>
                </div>

                <button type="submit"
                        class="inline-flex h-[42px] w-full cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[13px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
                    <i class="fas fa-plus text-[12px]" aria-hidden="true"></i>
                    <span>Daftarkan Domain</span>
                </button>
            </form>
        </section>

        {{-- Right: Domain List & DNS Setup --}}
        <section class="rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)] lg:col-span-7 overflow-hidden" aria-labelledby="custom-domain-list-heading">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e9ebf0] px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <h2 id="custom-domain-list-heading" class="text-[16px] font-bold text-[#111827]">Domain Terdaftar</h2>
                    <span class="rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-0.5 text-[11px] font-bold text-[#df5826]">
                        {{ $domains->count() }}
                    </span>
                </div>
                <span class="text-[12px] text-[#7f837e]">Target CNAME: <code class="font-mono text-[#111827]">{{ $cnameTarget }}</code></span>
            </div>

            <div class="divide-y divide-[#f1f3f5]">
                @forelse($domains as $item)
                    <div class="p-5 space-y-4">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-[15px] font-bold text-[#111827]">{{ $item->domain }}</p>
                                    @if($item->isActive())
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif &amp; Terhubung
                                        </span>
                                    @elseif($item->status === \App\Models\ShopDomain::STATUS_FAILED)
                                        <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[10px] font-semibold text-red-700">
                                            Verifikasi Gagal
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                            Menunggu DNS
                                        </span>
                                    @endif
                                </div>
                                @if($item->verified_at)
                                    <p class="text-[11px] text-[#7f837e]">Terverifikasi {{ $item->verified_at->diffForHumans() }} · SSL: {{ strtoupper($item->ssl_status) }}</p>
                                @elseif($item->last_error)
                                    <p class="text-[12px] font-medium text-red-600">{{ $item->last_error }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <form method="POST" action="{{ route('admin.custom-domain.verify', $item->id) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] border border-[#ffd2b8] bg-[#fff6f0] px-3 text-[12px] font-semibold text-[#df5826] transition hover:bg-[#ffe9db]">
                                        <i class="fas fa-rotate text-[11px]" aria-hidden="true"></i>
                                        <span>Verifikasi DNS</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.custom-domain.destroy', $item->id) }}"
                                      @submit="if (!confirm('Hapus domain {{ $item->domain }}? Pelanggan tidak lagi dapat mengakses toko melalui domain ini.')) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-[36px] cursor-pointer items-center gap-1.5 rounded-[10px] border border-red-200 bg-white px-3 text-[12px] font-semibold text-red-600 transition hover:bg-red-50">
                                        <i class="fas fa-trash-can text-[11px]" aria-hidden="true"></i>
                                        <span>Hapus</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- DNS Setup Instructions --}}
                        <div class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4 space-y-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#53565a]">Pengaturan DNS di Provider Domain Anda (Cloudflare / Niagahoster / GoDaddy / dll.)</p>

                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <div class="rounded-[10px] border border-[#e9ebf0] bg-white p-3 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="rounded bg-[#fff6f0] px-2 py-0.5 font-mono text-[10px] font-bold text-[#df5826]">1. CNAME</span>
                                        <button type="button" @click="copyText('cname-{{ $item->id }}', '{{ $cnameTarget }}')" class="text-[11px] font-semibold text-[#df5826] hover:underline cursor-pointer">
                                            <span x-text="copiedKey === 'cname-{{ $item->id }}' ? 'Tersalin!' : 'Salin Target'">Salin Target</span>
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-[#7f837e]">Host / Name:</p>
                                    <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $item->domain }}</p>
                                    <p class="text-[11px] text-[#7f837e]">Target / Value:</p>
                                    <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $cnameTarget }}</p>
                                </div>

                                <div class="rounded-[10px] border border-[#e9ebf0] bg-white p-3 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="rounded bg-sky-50 px-2 py-0.5 font-mono text-[10px] font-bold text-sky-700">2. TXT (Verifikasi)</span>
                                        <button type="button" @click="copyText('txt-{{ $item->id }}', '{{ $item->txtRecordValue() }}')" class="text-[11px] font-semibold text-[#df5826] hover:underline cursor-pointer">
                                            <span x-text="copiedKey === 'txt-{{ $item->id }}' ? 'Tersalin!' : 'Salin Token'">Salin Token</span>
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-[#7f837e]">Host / Name:</p>
                                    <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $item->txtRecordName() }}</p>
                                    <p class="text-[11px] text-[#7f837e]">Value:</p>
                                    <p class="break-all font-mono text-[11.5px] font-semibold text-[#111827]">{{ $item->txtRecordValue() }}</p>
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
                        <p class="mt-1 text-[12px] text-[#7f837e]">Tambahkan domain bisnis Anda di sebelah kiri untuk mulai menghubungkan toko.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Panduan Lengkap Setup Custom Domain --}}
    <section class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]" aria-labelledby="custom-domain-guide-heading">
        <div class="flex items-center gap-3 border-b border-[#e9ebf0] px-5 py-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[#fff6f0] text-[#df5826]">
                <i class="fas fa-book-open text-[14px]" aria-hidden="true"></i>
            </span>
            <div>
                <h2 id="custom-domain-guide-heading" class="text-[16px] font-bold text-[#111827]">Panduan Setting Domain Kustom</h2>
                <p class="mt-0.5 text-[12px] text-[#7f837e]">Ikuti langkah berikut untuk menghubungkan domain bisnis dengan SSL HTTPS otomatis.</p>
            </div>
        </div>

        <div class="grid gap-5 p-5 md:grid-cols-2">
            {{-- Langkah Pemilik Toko --}}
            <div class="space-y-3 rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa] p-4">
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-[#fff6f0] px-2.5 py-0.5 text-[11px] font-bold text-[#df5826] border border-[#ffdec9]">Untuk Pemilik Toko</span>
                    <h3 class="text-[13px] font-bold text-[#111827]">Menghubungkan Domain Sendiri</h3>
                </div>
                <ol class="space-y-2.5 text-[12.5px] text-[#53565a]">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#fff6f0] text-[10px] font-bold text-[#df5826] ring-1 ring-[#ffdec9]">1</span>
                        <span>Masukkan subdomain bisnis Anda di form <strong>Tambah Domain Bisnis</strong> (misal <code class="rounded bg-white px-1 font-mono text-[11px]">order.namatoko.com</code>), lalu klik <strong>Daftarkan Domain</strong>.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#fff6f0] text-[10px] font-bold text-[#df5826] ring-1 ring-[#ffdec9]">2</span>
                        <span>Buka panel DNS domain Anda (Cloudflare / Niagahoster / GoDaddy), lalu tambahkan <strong>CNAME</strong> mengarah ke <code class="rounded bg-white px-1 font-mono text-[11px]">{{ $cnameTarget }}</code> dan <strong>TXT</strong> verifikasi yang muncul di kartu domain.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#fff6f0] text-[10px] font-bold text-[#df5826] ring-1 ring-[#ffdec9]">3</span>
                        <span>Tunggu propagasi DNS (1–10 menit), lalu klik tombol <strong>Verifikasi DNS</strong>. Jika berhasil, status berubah menjadi <strong>Aktif &amp; Terhubung</strong>.</span>
                    </li>
                </ol>
            </div>

            {{-- Langkah Setup Cloudflare for SaaS (Server) --}}
            <div class="space-y-3 rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa] p-4">
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-sky-50 px-2.5 py-0.5 text-[11px] font-bold text-sky-700 border border-sky-200">Setup Awal Server (1x)</span>
                    <h3 class="text-[13px] font-bold text-[#111827]">Cloudflare for SaaS + Hostinger</h3>
                </div>
                <ol class="space-y-2.5 text-[12.5px] text-[#53565a]">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-sky-700 ring-1 ring-sky-200">1</span>
                        <span>Tambahkan domain utama SaaS (<code class="rounded bg-white px-1 font-mono text-[11px]">oqariplus.appku.site</code>) ke <strong>Cloudflare</strong>, lalu aktifkan menu <strong>SSL/TLS → Custom Hostnames</strong> (Cloudflare for SaaS).</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-sky-700 ring-1 ring-sky-200">2</span>
                        <span>Di Cloudflare DNS, buat record <code class="rounded bg-white px-1 font-mono text-[11px]">A</code> / <code class="rounded bg-white px-1 font-mono text-[11px]">CNAME</code> untuk <code class="rounded bg-white px-1 font-mono text-[11px]">{{ $cnameTarget }}</code> (Proxied / awan oranye) menuju IP/origin Hostinger, dan jadikan sebagai <strong>Fallback Origin</strong>.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-sky-700 ring-1 ring-sky-200">3</span>
                        <span>Isi <code class="rounded bg-white px-1 font-mono text-[11px]">CLOUDFLARE_SAAS_ENABLED=true</code>, <code class="rounded bg-white px-1 font-mono text-[11px]">CLOUDFLARE_ZONE_ID</code>, dan <code class="rounded bg-white px-1 font-mono text-[11px]">CLOUDFLARE_API_TOKEN</code> di <code class="rounded bg-white px-1 font-mono text-[11px]">.env</code> server lalu jalankan <code class="rounded bg-white px-1 font-mono text-[11px]">php artisan migrate --force</code>.</span>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</div>
@endsection
