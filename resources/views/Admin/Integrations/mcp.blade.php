@extends('Admin.Users.layout')

@section('pageTitle', 'Integrasi MCP')

@section('content')
@php
    $roleLabel = match ($role) {
        'superadmin' => 'Admin Platform',
        'manager' => 'Manager Outlet',
        default => 'Owner',
    };
    $clientLabel = $clients[$client]['label'];
    $defaultAbility = $abilities[0] ?? null;
    $canConnect = $defaultAbility !== null && (! $hasBranches || $branches->isNotEmpty());
    $example = match ($defaultAbility) {
        'platform' => 'Berapa jumlah bisnis OQARI menurut status akunnya?',
        'inventory' => 'Bahan apa yang stoknya di bawah batas minimum di outlet saya?',
        'operations' => 'Berapa permintaan barang, opname, dan pembelian kecil hari ini?',
        default => 'Berapa penjualan hari ini dan menu apa yang paling laku di outlet saya?',
    };
    $codexCommand = 'codex mcp add oqari --url '.json_encode($endpoint, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).' --bearer-token-env-var OQARI_MCP_TOKEN';
    $codexClientId = config('oqari_mcp.codex_client_id');
    $codexLogin = $client === 'codex' && config('oqari_mcp.oauth_enabled') && filled($codexClientId)
        && in_array($defaultAbility, ['sales', 'platform'], true);
    $callbackPort = config('oqari_mcp.codex_callback_port');
    $callback = "http://127.0.0.1:{$callbackPort}/callback";
    $endpointArgument = json_encode($endpoint, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $oauthCommand = "codex -c 'mcp_oauth_callback_port={$callbackPort}' -c 'mcp_oauth_callback_url=\"{$callback}\"' mcp add oqari-advance --url ".$endpointArgument.' --oauth-client-id '.json_encode($codexClientId, JSON_THROW_ON_ERROR);
    $oauthRetry = "codex -c 'mcp_servers.oqari-advance.oauth.callback_port={$callbackPort}' -c 'mcp_servers.oqari-advance.oauth.callback_url=\"{$callback}\"' mcp login oqari-advance";
    $claudeConfig = json_encode([
        'mcpServers' => ['oqari' => ['type' => 'http', 'url' => $endpoint, 'headers' => ['Authorization' => 'Bearer ${OQARI_MCP_TOKEN}']]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $setupPrompt = "Bantu saya menambahkan server MCP OQARI Advance ke {$clientLabel}. URL: {$endpoint}. Gunakan Streamable HTTP dengan Bearer token dari pengaturan rahasia, atau variabel lingkungan OQARI_MCP_TOKEN jika didukung. Arahkan saya memasukkan kunci secara langsung ke pengaturan tersebut. Jangan meminta kunci di chat. Setelah terhubung, jalankan oqari_get_context.";
    $tabs = [
        'setup' => ['label' => 'Hubungkan aplikasi', 'icon' => 'fa-plug'],
        'connected' => ['label' => 'Aplikasi terhubung', 'icon' => 'fa-link'],
        'tokens' => ['label' => 'Kunci akses', 'icon' => 'fa-key'],
    ];
    $primaryButton = 'inline-flex h-[40px] cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:cursor-not-allowed disabled:opacity-60';
    $secondaryButton = 'inline-flex h-[38px] cursor-pointer items-center justify-center gap-2 rounded-[10px] border border-[#e9ebf0] bg-white px-4 text-[12px] font-semibold text-[#111827] transition hover:border-[#ffd2b8] hover:bg-[#fff6f0] hover:text-[#df5826]';
    $field = 'w-full min-w-0 rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] px-3.5 py-2.5 text-[13px] text-[#111827] transition focus:border-[#df5826] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#df5826]/15';
@endphp

<div class="space-y-5" x-data="{
    message: '',
    copied: '',
    timer: null,
    revealedTokens: {},
    visibleTokens: {},
    loadingToken: '',
    async copy(field, label) {
        const input = this.$refs[field];
        if (!input) return;
        try {
            await navigator.clipboard.writeText(input.value);
            this.copied = field;
            this.message = label + ' disalin ke clipboard.';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.copied = ''; }, 2200);
        } catch {
            input.focus();
            input.select();
            this.copied = '';
            this.message = 'Teks dipilih. Tekan Ctrl+C atau Command+C untuk menyalin.';
        }
    },
    async fetchConnectionToken(id, url) {
        if (this.revealedTokens[id]) return this.revealedTokens[id];
        this.loadingToken = id;
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) {
                this.message = 'Kunci akses tidak dapat dimuat.';
                return null;
            }
            const data = await response.json();
            if (!data.token) return null;
            this.revealedTokens[id] = data.token;
            return data.token;
        } catch {
            this.message = 'Gagal mengambil kunci akses.';
            return null;
        } finally {
            this.loadingToken = '';
        }
    },
    async toggleRevealToken(id, url) {
        if (this.visibleTokens[id]) {
            this.visibleTokens[id] = false;
            return;
        }
        const token = await this.fetchConnectionToken(id, url);
        if (token) {
            this.visibleTokens[id] = true;
        }
    },
    async copyConnectionToken(id, url) {
        const token = await this.fetchConnectionToken(id, url);
        if (!token) return;
        try {
            await navigator.clipboard.writeText(token);
            this.copied = 'conn-' + id;
            this.message = 'Kunci akses disalin ke clipboard.';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.copied = ''; }, 2200);
        } catch {
            this.visibleTokens[id] = true;
            this.message = 'Kunci ditampilkan. Silakan salin secara manual.';
        }
    },
    toggleAllBranches(checked) {
        if (!this.$refs.branchList) return;
        this.$refs.branchList.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.checked = checked;
        });
    }
}">
    <p class="sr-only" role="status" aria-live="polite" x-text="message"></p>

    {{-- Page Header --}}
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[25px] font-semibold leading-tight tracking-tight text-[#111827]">Integrasi MCP</h1>
            <p class="mt-0.5 text-[13px] text-[#53565a]">Tanya penjualan, stok, atau ringkasan OQARI langsung dari aplikasi AI Anda.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-3 py-1.5 text-[11px] font-semibold text-[#df5826]">
                <i class="fas fa-shield-halved text-[11px]" aria-hidden="true"></i>
                <span>{{ $roleLabel }} · Akses baca</span>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#e9ebf0] bg-white px-3 py-1.5 text-[11px] font-medium text-[#53565a]">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <span>Streamable HTTP</span>
            </span>
        </div>
    </header>

    {{-- Main Navigation Tabs --}}
    <nav aria-label="Bagian integrasi MCP" class="flex flex-wrap gap-2 border-b border-[#e9ebf0] pb-px">
        @foreach($tabs as $key => $meta)
            <a href="{{ route('admin.integrations.mcp', array_filter(['tab' => $key === 'setup' ? null : $key, 'client' => $client === 'codex' ? null : $client])) }}"
               @if($tab === $key) aria-current="page" @endif
               @class([
                   'inline-flex min-h-[42px] items-center gap-2 border-b-2 px-3 text-[13px] font-semibold transition',
                   'border-[#df5826] text-[#df5826]' => $tab === $key,
                   'border-transparent text-[#53565a] hover:border-gray-200 hover:text-[#111827]' => $tab !== $key,
               ])>
                <i class="fas {{ $meta['icon'] }} text-[12px]" aria-hidden="true"></i>
                <span>{{ $meta['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- One-time New Token Banner --}}
    @if($newToken)
        <section id="mcp-new-key" class="rounded-[14px] border border-[#ffd2b8] bg-[#fff6f0] p-5 shadow-[0px_2px_4px_rgba(223,88,38,0.08)]" aria-labelledby="mcp-token-heading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-[#df5826] text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)]">
                    <i class="fas fa-key text-[15px]" aria-hidden="true"></i>
                </span>
                <div class="min-w-0 flex-1 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="mcp-token-heading" class="text-[15px] font-bold text-[#111827]">Kunci {{ $clientLabel }} siap disalin</h2>
                        <span class="rounded-full border border-[#ffd2b8] bg-white px-2.5 py-0.5 text-[10px] font-semibold text-[#c94d00]">
                            Ditampilkan satu kali
                        </span>
                    </div>
                    <p class="text-[13px] text-[#53565a]">
                        Simpan kunci ini di pengaturan rahasia (<code class="rounded bg-white px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>) aplikasi AI Anda. Kunci tidak akan ditampilkan lagi setelah halaman ini ditutup.
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input class="{{ $field }} flex-1 border-[#ffd2b8] bg-white font-mono" readonly spellcheck="false" aria-label="Kunci koneksi baru" x-ref="token" value="{{ $newToken }}">
                        <button class="{{ $primaryButton }} shrink-0" type="button" @click="copy('token', 'Kunci')">
                            <i class="fas text-[12px]" :class="copied === 'token' ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                            <span x-text="copied === 'token' ? 'Kunci tersalin' : 'Salin kunci'">Salin kunci</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if($tab === 'setup')
        <section class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
            {{-- Client Selector Bar --}}
            <nav class="flex flex-wrap items-center gap-2 border-b border-[#e9ebf0] bg-[#f8f9fa] p-3 sm:px-5 sm:py-3.5" aria-label="Pilih aplikasi">
                @foreach($clients as $key => $option)
                    <a href="{{ route('admin.integrations.mcp', $key === 'codex' ? [] : ['client' => $key]) }}"
                       @if($client === $key) aria-current="page" @endif
                       @class([
                           'inline-flex h-[38px] items-center gap-2 rounded-[10px] border px-3.5 text-[13px] font-semibold transition',
                           'border-[#ffd2b8] bg-[#fff6f0] text-[#df5826] shadow-2xs' => $client === $key,
                           'border-transparent bg-transparent text-[#53565a] hover:border-[#e9ebf0] hover:bg-white hover:text-[#111827]' => $client !== $key,
                       ])>
                        <i class="fas {{ $option['icon'] }} text-[12px]" aria-hidden="true"></i>
                        <span>{{ $option['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="space-y-5 p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-[#fff6f0] text-[#df5826]">
                        <i class="fas {{ $clients[$client]['icon'] ?? 'fa-plug' }} text-[15px]" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="text-[17px] font-bold text-[#111827]">{{ $client === 'other' ? 'Hubungkan aplikasi lain' : 'Hubungkan '.$clientLabel }}</h2>
                        <p class="mt-0.5 text-[13px] text-[#7f837e]">{{ $codexLogin ? 'Masuk dengan akun OQARI untuk memberi Codex akses baca.' : 'Buat satu kunci akses, pasang di aplikasi AI, lalu coba pertanyaan di bawah.' }}</p>
                    </div>
                </div>

                @if($codexLogin && $canConnect)
                    <section class="space-y-3 rounded-[12px] border border-[#ffdec9] bg-[#fff6f0]/55 p-4 sm:p-5" aria-labelledby="mcp-codex-login">
                        <h3 id="mcp-codex-login" class="font-bold text-[#111827]">Login OQARI</h3>
                        <ol class="list-decimal space-y-1 pl-5 text-[13px] text-[#53565a]">
                            <li>Salin perintah ini dan jalankan di terminal pada komputer yang menjalankan Codex.</li>
                            <li>Masuk ke OQARI di browser, pilih <strong>Izinkan akses baca</strong>, lalu buka sesi Codex baru.</li>
                        </ol>
                        <label for="mcp-oauth-command" class="sr-only">Perintah hubungkan Codex</label>
                        <textarea id="mcp-oauth-command" x-ref="oauthCommand" class="{{ $field }} font-mono text-[12px]" readonly rows="2">{{ $oauthCommand }}</textarea>
                        <button type="button" class="{{ $primaryButton }}" @click="copy('oauthCommand', 'Perintah login')" x-text="copied === 'oauthCommand' ? 'Perintah tersalin' : 'Salin perintah hubungkan'">Salin perintah hubungkan</button>
                        <details class="border-t border-[#ffdec9] pt-2">
                            <summary class="cursor-pointer py-2 text-[13px] font-semibold">Jika browser belum terbuka</summary>
                            <label for="mcp-oauth-retry" class="sr-only">Perintah login Codex</label>
                            <textarea id="mcp-oauth-retry" x-ref="oauthRetry" class="{{ $field }} font-mono text-[12px]" readonly rows="2">{{ $oauthRetry }}</textarea>
                            <button type="button" class="{{ $secondaryButton }} mt-2" @click="copy('oauthRetry', 'Perintah login')">Salin perintah login</button>
                        </details>
                    </section>
                    <details class="rounded-[12px] border border-[#e9ebf0] p-4" @if($newToken || old('mode') === 'custom') open @endif>
                        <summary class="cursor-pointer py-2 text-[13px] font-semibold">Pemasangan dengan kunci akses</summary>
                        <div class="space-y-4 pt-3">
                @endif

                {{-- Quick Key Creation Card --}}
                @if($canConnect && !$newToken)
                    <div class="flex flex-col justify-between gap-4 rounded-[12px] border border-[#ffdec9] bg-[#fff6f0]/55 p-4 sm:flex-row sm:items-center">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border border-[#ffdec9] bg-white text-[#df5826]">
                                <i class="fas fa-bolt text-[13px]" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-[14px] font-bold text-[#111827]">
                                    {{ collect($abilities)->map(fn (string $a) => config("oqari_mcp.abilities.$a.label", $a))->implode(' · ') }}
                                </p>
                                <p class="mt-0.5 text-[12px] text-[#53565a]">
                                    @if($hasBranches)
                                        {{ $branches->count() }} outlet yang dapat Anda akses ·
                                    @endif
                                    Berlaku {{ config('oqari_mcp.token_days') }} hari
                                </p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.integrations.mcp.store') }}" x-data="{busy: false}" @submit="busy = true" class="shrink-0">
                            @csrf
                            <input type="hidden" name="mode" value="quick">
                            <input type="hidden" name="client" value="{{ $client }}">
                            <button type="submit" class="{{ $primaryButton }} w-full sm:w-auto" :disabled="busy">
                                <i class="fas fa-key text-[12px]" aria-hidden="true"></i>
                                <span x-text="busy ? 'Membuat kunci…' : 'Buat kunci akses'">Buat kunci akses</span>
                            </button>
                        </form>
                    </div>
                @elseif(!$canConnect)
                    <div class="rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4 text-[13px] text-[#53565a]">
                        Akun ini belum memiliki data atau outlet yang dapat dihubungkan.
                    </div>
                @endif

                @if($canConnect)
                    {{-- Step-by-step Setup Accordion --}}
                    <details class="group rounded-[12px] border border-[#e9ebf0] bg-white transition open:border-[#ffd2b8]" @if($newToken || in_array($client, ['chatgpt', 'gemini'], true)) open @endif>
                        <summary class="flex min-h-[46px] cursor-pointer list-none items-center justify-between gap-3 rounded-[12px] px-4 py-3 text-[13px] font-semibold text-[#111827] transition hover:bg-[#fafbfc]">
                            <span class="flex items-center gap-2.5">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-[#fff6f0] text-[#df5826]">
                                    <i class="fas fa-book-open text-[11px]" aria-hidden="true"></i>
                                </span>
                                <span>Cara pasang di {{ $clientLabel }}</span>
                            </span>
                            <i class="fas fa-chevron-down text-[11px] text-[#7f837e] transition-transform duration-200 group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        <div class="space-y-4 border-t border-[#e9ebf0] p-4 sm:p-5">
                            @if($client === 'codex')
                                <p class="text-[13px] text-[#53565a]">Simpan kunci sebagai variabel lingkungan <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>. Jalankan perintah ini di terminal, lalu buka ulang Codex dari lingkungan yang memuat kunci.</p>
                                <label class="sr-only" for="mcp-command">Perintah Codex</label>
                                <textarea id="mcp-command" x-ref="command" class="{{ $field }} font-mono text-[12px]" readonly rows="2">{{ $codexCommand }}</textarea>
                                <button type="button" class="{{ $secondaryButton }}" @click="copy('command', 'Perintah')">
                                    <i class="fas text-[12px]" :class="copied === 'command' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                    <span x-text="copied === 'command' ? 'Perintah tersalin' : 'Salin perintah'">Salin perintah</span>
                                </button>
                            @elseif($client === 'chatgpt')
                                {{-- Ringkasan Konfigurasi MCP OQARI --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Custom MCP Server</p>
                                            <p class="text-[12px] text-[#53565a]">Salin URL ini untuk dipasang di menu Apps / Plugins ChatGPT.</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-chatgpt">URL server MCP</label>
                                        <input id="mcp-endpoint-chatgpt" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-3">
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Autentikasi</p>
                                            <p class="font-semibold text-[#111827]">OAuth / Bearer Token</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Hak Akses</p>
                                            <p class="font-semibold text-[#111827]">Akses baca (Read-only)</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Cakupan & Masa Berlaku</p>
                                            <p class="font-semibold text-[#111827]">{{ $hasBranches ? $branches->count().' outlet' : 'Outlet utama' }} · {{ config('oqari_mcp.token_days') }} hari</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dukungan Paket ChatGPT --}}
                                <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-4">
                                    <p class="mb-2.5 text-[12px] font-bold text-[#111827]">Dukungan Paket ChatGPT untuk Custom MCP</p>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-2">
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Free</span>
                                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Tidak mendukung</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Plus</span>
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Terbatas (tergantung rollout)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Pro</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung (Read / Fetch)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Business / Enterprise / Edu</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung penuh</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Langkah Pemasangan ChatGPT --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">Langkah menghubungkan di ChatGPT Web</p>
                                    <ol class="list-decimal space-y-1.5 pl-5 text-[13px] text-[#53565a]">
                                        <li>Buka <strong class="text-[#111827]">chatgpt.com</strong> lalu masuk ke menu <strong class="text-[#111827]">Apps / Plugins</strong>.</li>
                                        <li>Klik tombol <strong class="text-[#111827]">+</strong> dan pilih <strong class="text-[#111827]">Add custom MCP server</strong>.</li>
                                        <li>Masukkan nama <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI Advance</code> dan tempel <strong class="text-[#111827]">Server URL</strong> di atas.</li>
                                        <li>Pilih metode autentikasi yang tersedia (<strong class="text-[#111827]">OAuth</strong> atau <strong class="text-[#111827]">Bearer Token</strong> dengan kunci akses OQARI).</li>
                                        <li>Centang persetujuan lalu klik <strong class="text-[#111827]">Create as a plugin</strong>.</li>
                                        <li>Setelah terhubung, uji dengan memanggil <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">oqari_get_context</code> atau pertanyaan contoh di bawah.</li>
                                    </ol>
                                </div>
                            @elseif($client === 'gemini')
                                {{-- Ringkasan Konfigurasi MCP OQARI untuk Gemini CLI & Antigravity --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Gemini CLI &amp; Antigravity</p>
                                            <p class="text-[12px] text-[#53565a]">Gunakan <strong>Gemini CLI</strong>, <strong>Antigravity</strong>, atau <strong>Gemini Code Assist</strong>. (Gemini Web di <code>gemini.google.com</code> saat ini belum mendukung URL MCP kustom.)</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-gemini">URL server MCP</label>
                                        <input id="mcp-endpoint-gemini" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Cara 1: Perintah Gemini CLI --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">1. Pasang cepat lewat Gemini CLI</p>
                                    <p class="text-[13px] text-[#53565a]">Simpan kunci akses sebagai variabel lingkungan <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>, lalu jalankan perintah ini di terminal:</p>
                                    <label class="sr-only" for="mcp-gemini-cli">Perintah Gemini CLI</label>
                                    <textarea id="mcp-gemini-cli" x-ref="geminiCli" class="{{ $field }} font-mono text-[12px]" readonly rows="2">gemini mcp add --transport http oqari {{ $endpoint }} -H "Authorization: Bearer ${OQARI_MCP_TOKEN}"</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('geminiCli', 'Perintah Gemini CLI')">
                                        <i class="fas text-[12px]" :class="copied === 'geminiCli' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'geminiCli' ? 'Perintah tersalin' : 'Salin perintah Gemini CLI'">Salin perintah Gemini CLI</span>
                                    </button>
                                </div>

                                {{-- Cara 2: Konfigurasi JSON (Gemini CLI / Antigravity) --}}
                                <div class="space-y-2 border-t border-[#f1f3f5] pt-4">
                                    <p class="text-[12px] font-bold text-[#111827]">2. Atau pasang lewat file konfigurasi JSON</p>
                                    <p class="text-[13px] text-[#53565a]">Tambahkan konfigurasi berikut ke <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">~/.gemini/settings.json</code> (Gemini CLI) atau <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">mcp_config.json</code> (Antigravity):</p>
                                    <label class="sr-only" for="mcp-gemini-config">Konfigurasi JSON Gemini</label>
                                    <textarea id="mcp-gemini-config" x-ref="command" class="{{ $field }} font-mono text-[12px]" readonly rows="8">{{ $claudeConfig }}</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('command', 'Konfigurasi')">
                                        <i class="fas text-[12px]" :class="copied === 'command' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'command' ? 'Konfigurasi tersalin' : 'Salin konfigurasi'">Salin konfigurasi</span>
                                    </button>
                                </div>
                            @elseif($client === 'claude-code')
                                <p class="text-[13px] text-[#53565a]">Simpan kunci sebagai variabel lingkungan <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>. Tambahkan entri ini ke <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">.mcp.json</code> di folder proyek, lalu buka Claude Code dan cek <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">/mcp</code>.</p>
                                <label class="sr-only" for="mcp-config">Konfigurasi Claude Code</label>
                                <textarea id="mcp-config" x-ref="command" class="{{ $field }} font-mono text-[12px]" readonly rows="8">{{ $claudeConfig }}</textarea>
                                <button type="button" class="{{ $secondaryButton }}" @click="copy('command', 'Konfigurasi')">
                                    <i class="fas text-[12px]" :class="copied === 'command' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                    <span x-text="copied === 'command' ? 'Konfigurasi tersalin' : 'Salin konfigurasi'">Salin konfigurasi</span>
                                </button>
                            @elseif($client === 'chatgpt')
                                {{-- Ringkasan Konfigurasi MCP OQARI --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Custom MCP Server</p>
                                            <p class="text-[12px] text-[#53565a]">Salin URL ini untuk dipasang di menu Apps / Plugins ChatGPT.</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-chatgpt">URL server MCP</label>
                                        <input id="mcp-endpoint-chatgpt" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-3">
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Autentikasi</p>
                                            <p class="font-semibold text-[#111827]">OAuth / Bearer Token</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Hak Akses</p>
                                            <p class="font-semibold text-[#111827]">Akses baca (Read-only)</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Cakupan & Masa Berlaku</p>
                                            <p class="font-semibold text-[#111827]">{{ $hasBranches ? $branches->count().' outlet' : 'Outlet utama' }} · {{ config('oqari_mcp.token_days') }} hari</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dukungan Paket ChatGPT --}}
                                <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-4">
                                    <p class="mb-2.5 text-[12px] font-bold text-[#111827]">Dukungan Paket ChatGPT untuk Custom MCP</p>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-2">
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Free</span>
                                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Tidak mendukung</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Plus</span>
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Terbatas (tergantung rollout)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Pro</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung (Read / Fetch)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Business / Enterprise / Edu</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung penuh</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Langkah Pemasangan ChatGPT --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">Langkah menghubungkan di ChatGPT Web</p>
                                    <ol class="list-decimal space-y-1.5 pl-5 text-[13px] text-[#53565a]">
                                        <li>Buka <strong class="text-[#111827]">chatgpt.com</strong> lalu masuk ke menu <strong class="text-[#111827]">Apps / Plugins</strong>.</li>
                                        <li>Klik tombol <strong class="text-[#111827]">+</strong> dan pilih <strong class="text-[#111827]">Add custom MCP server</strong>.</li>
                                        <li>Masukkan nama <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI Advance</code> dan tempel <strong class="text-[#111827]">Server URL</strong> di atas.</li>
                                        <li>Pilih metode autentikasi yang tersedia (<strong class="text-[#111827]">OAuth</strong> atau <strong class="text-[#111827]">Bearer Token</strong> dengan kunci akses OQARI).</li>
                                        <li>Centang persetujuan lalu klik <strong class="text-[#111827]">Create as a plugin</strong>.</li>
                                        <li>Setelah terhubung, uji dengan memanggil <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">oqari_get_context</code> atau pertanyaan contoh di bawah.</li>
                                    </ol>
                                </div>
                            @elseif($client === 'gemini')
                                {{-- Ringkasan Konfigurasi MCP OQARI untuk Gemini CLI & Antigravity --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Gemini CLI &amp; Antigravity</p>
                                            <p class="text-[12px] text-[#53565a]">Gunakan <strong>Gemini CLI</strong>, <strong>Antigravity</strong>, atau <strong>Gemini Code Assist</strong>. (Gemini Web di <code>gemini.google.com</code> saat ini belum mendukung URL MCP kustom.)</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-gemini">URL server MCP</label>
                                        <input id="mcp-endpoint-gemini" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Cara 1: Perintah Gemini CLI --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">1. Pasang cepat lewat Gemini CLI</p>
                                    <p class="text-[13px] text-[#53565a]">Simpan kunci akses sebagai variabel lingkungan <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>, lalu jalankan perintah ini di terminal:</p>
                                    <label class="sr-only" for="mcp-gemini-cli">Perintah Gemini CLI</label>
                                    <textarea id="mcp-gemini-cli" x-ref="geminiCli" class="{{ $field }} font-mono text-[12px]" readonly rows="2">gemini mcp add --transport http oqari {{ $endpoint }} -H "Authorization: Bearer ${OQARI_MCP_TOKEN}"</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('geminiCli', 'Perintah Gemini CLI')">
                                        <i class="fas text-[12px]" :class="copied === 'geminiCli' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'geminiCli' ? 'Perintah tersalin' : 'Salin perintah Gemini CLI'">Salin perintah Gemini CLI</span>
                                    </button>
                                </div>

                                {{-- Cara 2: Konfigurasi JSON (Gemini CLI / Antigravity) --}}
                                <div class="space-y-2 border-t border-[#f1f3f5] pt-4">
                                    <p class="text-[12px] font-bold text-[#111827]">2. Atau pasang lewat file konfigurasi JSON</p>
                                    <p class="text-[13px] text-[#53565a]">Tambahkan konfigurasi berikut ke <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">~/.gemini/settings.json</code> (Gemini CLI) atau <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">mcp_config.json</code> (Antigravity):</p>
                                    <label class="sr-only" for="mcp-gemini-config">Konfigurasi JSON Gemini</label>
                                    <textarea id="mcp-gemini-config" x-ref="command" class="{{ $field }} font-mono text-[12px]" readonly rows="8">{{ $claudeConfig }}</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('command', 'Konfigurasi')">
                                        <i class="fas text-[12px]" :class="copied === 'command' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'command' ? 'Konfigurasi tersalin' : 'Salin konfigurasi'">Salin konfigurasi</span>
                                    </button>
                                </div>
                            @else
                                <p class="text-[13px] text-[#53565a]">Tambahkan server MCP dengan transport <strong class="text-[#111827]">Streamable HTTP</strong>. Masukkan URL di bawah dan pilih autentikasi <strong class="text-[#111827]">Bearer token</strong> dengan kunci OQARI.</p>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <label class="sr-only" for="mcp-endpoint">URL server MCP</label>
                                    <input id="mcp-endpoint" x-ref="endpoint" class="{{ $field }} flex-1 font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                    <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                        <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                    </button>
                                </div>
                            @endif

                            @if(in_array($client, ['codex', 'claude-code', 'gemini'], true))
                                <details class="group/win rounded-[10px] border border-[#f1f3f5] bg-[#f8f9fa] px-3.5">
                                    <summary class="flex min-h-[42px] cursor-pointer list-none items-center justify-between py-2.5 text-[12px] font-semibold text-[#111827]">
                                        <span>Cara menyimpan kunci di Windows</span>
                                        <i class="fas fa-chevron-down text-[10px] text-[#7f837e] transition-transform group-open/win:rotate-180" aria-hidden="true"></i>
                                    </summary>
                                    <p class="pb-3 text-[12px] leading-relaxed text-[#53565a]">Cari <strong>Edit environment variables for your account</strong> dari menu Start. Tambah variabel pengguna bernama <code class="rounded bg-white px-1.5 py-0.5 font-mono text-[11px] text-[#c94d00]">OQARI_MCP_TOKEN</code> dengan kunci yang disalin, lalu tutup dan buka ulang aplikasi AI.</p>
                                </details>
                            @endif

                            <div class="space-y-2 border-t border-[#f1f3f5] pt-4">
                                <label for="mcp-prompt" class="block text-[12px] font-semibold text-[#111827]">Minta bantuan AI untuk memasang</label>
                                <textarea id="mcp-prompt" x-ref="prompt" class="{{ $field }} text-[12px] leading-relaxed" readonly rows="3">{{ $setupPrompt }}</textarea>
                                <button type="button" class="{{ $secondaryButton }}" @click="copy('prompt', 'Prompt')">
                                    <i class="fas text-[12px]" :class="copied === 'prompt' ? 'fa-check text-emerald-600' : 'fa-wand-magic-sparkles text-[#df5826]'" aria-hidden="true"></i>
                                    <span x-text="copied === 'prompt' ? 'Prompt tersalin' : 'Salin prompt'">Salin prompt</span>
                                </button>
                            </div>
                        </div>
                    </details>

                    @if($codexLogin)
                        </div>
                    </details>
                    @endif

                    {{-- Example Query --}}
                    <div class="space-y-2 rounded-[12px] border border-[#f1f3f5] bg-[#f8f9fa]/70 p-4">
                        <label class="flex items-center gap-2 text-[12px] font-semibold text-[#111827]" for="mcp-example">
                            <i class="fas fa-comment-dots text-[#df5826]" aria-hidden="true"></i>
                            <span>Coba setelah terhubung</span>
                        </label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <input id="mcp-example" x-ref="example" class="{{ $field }} flex-1 bg-white" readonly value="{{ $example }}">
                            <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('example', 'Pertanyaan')">
                                <i class="fas text-[12px]" :class="copied === 'example' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                <span x-text="copied === 'example' ? 'Pertanyaan tersalin' : 'Salin pertanyaan'">Salin pertanyaan</span>
                            </button>
                        </div>
                    </div>

                    {{-- Advanced Custom Key Accordion --}}
                    @unless($newToken)
                        <details class="group/adv rounded-[12px] border border-[#e9ebf0] bg-white" @if(old('mode') === 'custom') open @endif>
                            <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-between gap-3 rounded-[12px] px-4 py-3 text-[13px] font-semibold text-[#53565a] transition hover:bg-[#fafbfc] hover:text-[#111827]">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-sliders text-[12px] text-[#7f837e]" aria-hidden="true"></i>
                                    <span>Opsi lanjutan</span>
                                </span>
                                <i class="fas fa-chevron-down text-[11px] text-[#7f837e] transition-transform duration-200 group-open/adv:rotate-180" aria-hidden="true"></i>
                            </summary>
                            <form method="POST" action="{{ route('admin.integrations.mcp.store') }}" class="space-y-4 border-t border-[#e9ebf0] p-4 sm:p-5">
                                @csrf
                                <input type="hidden" name="mode" value="custom">
                                <input type="hidden" name="client" value="{{ $client }}">
                                <div>
                                    <label for="mcp-name" class="mb-1.5 block text-[12px] font-semibold text-[#111827]">Nama koneksi</label>
                                    <input id="mcp-name" name="name" required maxlength="100" value="{{ is_string(old('name')) ? old('name') : $clientLabel }}" class="{{ $field }} bg-white">
                                </div>
                                <fieldset class="space-y-2">
                                    <legend class="mb-1.5 text-[12px] font-semibold text-[#111827]">Data yang diizinkan</legend>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach($abilities as $ability)
                                            <label class="flex cursor-pointer items-center gap-2.5 rounded-[10px] border border-[#e9ebf0] bg-white px-3.5 py-2.5 text-[13px] text-[#111827] transition hover:border-[#ffd2b8] hover:bg-[#fff6f0]/40">
                                                <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, (array) old('abilities', [$defaultAbility]), true)) class="h-4 w-4 rounded border-gray-300 text-[#df5826] focus:ring-[#df5826]">
                                                <span class="font-medium">{{ config("oqari_mcp.abilities.$ability.label") }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                @if($hasBranches)
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-[12px] font-semibold text-[#111827]">Outlet yang diizinkan</span>
                                            @if($branches->count() > 1)
                                                <div class="flex items-center gap-2 text-[11px] font-semibold">
                                                    <button type="button" @click="toggleAllBranches(true)" class="cursor-pointer text-[#df5826] hover:underline">Pilih semua</button>
                                                    <span class="text-[#e9ebf0]" aria-hidden="true">|</span>
                                                    <button type="button" @click="toggleAllBranches(false)" class="cursor-pointer text-[#7f837e] hover:text-[#111827]">Reset</button>
                                                </div>
                                            @endif
                                        </div>
                                        <fieldset x-ref="branchList" class="max-h-48 space-y-1.5 overflow-y-auto rounded-[10px] border border-[#e9ebf0] bg-[#f8f9fa] p-2.5">
                                            <legend class="sr-only">Outlet yang diizinkan</legend>
                                            @foreach($branches as $branch)
                                                <label class="flex cursor-pointer items-center gap-2.5 rounded-[8px] px-2.5 py-1.5 text-[13px] text-[#53565a] transition hover:bg-white hover:text-[#111827]">
                                                    <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}"
                                                           @checked(in_array((string) $branch->id, (array) old('branch_ids', $branches->count() === 1 ? [(string) $branch->id] : [])))
                                                           class="h-4 w-4 rounded border-gray-300 text-[#df5826] focus:ring-[#df5826]">
                                                    <span class="truncate">{{ $branch->name }}</span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                    </div>
                                @endif
                                <button type="submit" class="{{ $secondaryButton }}">
                                    <i class="fas fa-key text-[11px] text-[#df5826]" aria-hidden="true"></i>
                                    <span>Buat kunci dengan akses ini</span>
                                </button>
                            </form>
                        </details>
                    @endunless
                @endif
            </div>
        </section>
    @else
        {{-- Connected Apps & Access Tokens List --}}
        <section class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]" aria-labelledby="mcp-connections-heading">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e9ebf0] px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[#fff6f0] text-[#df5826]">
                        <i class="fas {{ $tab === 'connected' ? 'fa-link' : 'fa-key' }} text-[14px]" aria-hidden="true"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 id="mcp-connections-heading" class="text-[16px] font-bold text-[#111827]">{{ $tabs[$tab]['label'] }}</h2>
                            <span class="rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-0.5 text-[11px] font-bold text-[#df5826]">
                                {{ $connections->total() }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-[12px] text-[#7f837e]">{{ $tab === 'connected' ? 'Koneksi yang pernah berhasil menghubungi OQARI.' : 'Kelola kunci akses untuk pemasangan manual.' }}</p>
                    </div>
                </div>
                @if($tab === 'tokens')
                    <a class="{{ $primaryButton }}" href="{{ route('admin.integrations.mcp') }}">
                        <i class="fas fa-plus text-[11px]" aria-hidden="true"></i>
                        <span>Buat kunci</span>
                    </a>
                @endif
            </header>

            <div class="divide-y divide-[#f1f3f5]">
                @forelse($connections as $connection)
                    @php
                        $expired = $connection->expires_at?->isPast();
                        $isActive = ! $connection->revoked_at && ! $expired;
                        $canRevealToken = $isActive && $connection->getRawOriginal('token_encrypted') !== null;
                        $revealUrl = $canRevealToken ? route('admin.integrations.mcp.reveal', $connection->id) : '';
                        $status = $connection->revoked_at ? 'Dicabut' : ($expired ? 'Kedaluwarsa' : ($connection->last_used_at ? 'Pernah digunakan' : 'Belum digunakan'));
                    @endphp
                    <div class="flex flex-col justify-between gap-4 px-5 py-4 transition hover:bg-[#fafbfc] sm:flex-row sm:items-center">
                        <div class="flex min-w-0 items-start gap-3.5">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] {{ $isActive ? 'bg-[#fff6f0] text-[#df5826]' : 'bg-[#f3f4f6] text-[#7f837e]' }}">
                                <i class="fas {{ $connection->token_suffix ? 'fa-key' : 'fa-link' }} text-[14px]" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="break-words text-[14px] font-bold text-[#111827]">{{ $connection->name }}</p>
                                    @if($connection->revoked_at)
                                        <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[10px] font-semibold text-red-700">{{ $status }}</span>
                                    @elseif($client === 'chatgpt')
                                {{-- Ringkasan Konfigurasi MCP OQARI --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Custom MCP Server</p>
                                            <p class="text-[12px] text-[#53565a]">Salin URL ini untuk dipasang di menu Apps / Plugins ChatGPT.</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-chatgpt">URL server MCP</label>
                                        <input id="mcp-endpoint-chatgpt" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-3">
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Autentikasi</p>
                                            <p class="font-semibold text-[#111827]">OAuth / Bearer Token</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Hak Akses</p>
                                            <p class="font-semibold text-[#111827]">Akses baca (Read-only)</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Cakupan & Masa Berlaku</p>
                                            <p class="font-semibold text-[#111827]">{{ $hasBranches ? $branches->count().' outlet' : 'Outlet utama' }} · {{ config('oqari_mcp.token_days') }} hari</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dukungan Paket ChatGPT --}}
                                <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-4">
                                    <p class="mb-2.5 text-[12px] font-bold text-[#111827]">Dukungan Paket ChatGPT untuk Custom MCP</p>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-2">
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Free</span>
                                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Tidak mendukung</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Plus</span>
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Terbatas (tergantung rollout)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Pro</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung (Read / Fetch)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Business / Enterprise / Edu</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung penuh</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Langkah Pemasangan ChatGPT --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">Langkah menghubungkan di ChatGPT Web</p>
                                    <ol class="list-decimal space-y-1.5 pl-5 text-[13px] text-[#53565a]">
                                        <li>Buka <strong class="text-[#111827]">chatgpt.com</strong> lalu masuk ke menu <strong class="text-[#111827]">Apps / Plugins</strong>.</li>
                                        <li>Klik tombol <strong class="text-[#111827]">+</strong> dan pilih <strong class="text-[#111827]">Add custom MCP server</strong>.</li>
                                        <li>Masukkan nama <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI Advance</code> dan tempel <strong class="text-[#111827]">Server URL</strong> di atas.</li>
                                        <li>Pilih metode autentikasi yang tersedia (<strong class="text-[#111827]">OAuth</strong> atau <strong class="text-[#111827]">Bearer Token</strong> dengan kunci akses OQARI).</li>
                                        <li>Centang persetujuan lalu klik <strong class="text-[#111827]">Create as a plugin</strong>.</li>
                                        <li>Setelah terhubung, uji dengan memanggil <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">oqari_get_context</code> atau pertanyaan contoh di bawah.</li>
                                    </ol>
                                </div>
                            @elseif($client === 'gemini')
                                {{-- Ringkasan Konfigurasi MCP OQARI untuk Gemini CLI & Antigravity --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Gemini CLI &amp; Antigravity</p>
                                            <p class="text-[12px] text-[#53565a]">Gunakan <strong>Gemini CLI</strong>, <strong>Antigravity</strong>, atau <strong>Gemini Code Assist</strong>. (Gemini Web di <code>gemini.google.com</code> saat ini belum mendukung URL MCP kustom.)</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-gemini">URL server MCP</label>
                                        <input id="mcp-endpoint-gemini" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Cara 1: Perintah Gemini CLI --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">1. Pasang cepat lewat Gemini CLI</p>
                                    <p class="text-[13px] text-[#53565a]">Simpan kunci akses sebagai variabel lingkungan <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>, lalu jalankan perintah ini di terminal:</p>
                                    <label class="sr-only" for="mcp-gemini-cli">Perintah Gemini CLI</label>
                                    <textarea id="mcp-gemini-cli" x-ref="geminiCli" class="{{ $field }} font-mono text-[12px]" readonly rows="2">gemini mcp add --transport http oqari {{ $endpoint }} -H "Authorization: Bearer ${OQARI_MCP_TOKEN}"</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('geminiCli', 'Perintah Gemini CLI')">
                                        <i class="fas text-[12px]" :class="copied === 'geminiCli' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'geminiCli' ? 'Perintah tersalin' : 'Salin perintah Gemini CLI'">Salin perintah Gemini CLI</span>
                                    </button>
                                </div>

                                {{-- Cara 2: Konfigurasi JSON (Gemini CLI / Antigravity) --}}
                                <div class="space-y-2 border-t border-[#f1f3f5] pt-4">
                                    <p class="text-[12px] font-bold text-[#111827]">2. Atau pasang lewat file konfigurasi JSON</p>
                                    <p class="text-[13px] text-[#53565a]">Tambahkan konfigurasi berikut ke <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">~/.gemini/settings.json</code> (Gemini CLI) atau <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">mcp_config.json</code> (Antigravity):</p>
                                    <label class="sr-only" for="mcp-gemini-config">Konfigurasi JSON Gemini</label>
                                    <textarea id="mcp-gemini-config" x-ref="command" class="{{ $field }} font-mono text-[12px]" readonly rows="8">{{ $claudeConfig }}</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('command', 'Konfigurasi')">
                                        <i class="fas text-[12px]" :class="copied === 'command' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'command' ? 'Konfigurasi tersalin' : 'Salin konfigurasi'">Salin konfigurasi</span>
                                    </button>
                                </div>
                            @elseif($expired)
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold text-amber-700">{{ $status }}</span>
                                    @elseif($client === 'chatgpt')
                                {{-- Ringkasan Konfigurasi MCP OQARI --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Custom MCP Server</p>
                                            <p class="text-[12px] text-[#53565a]">Salin URL ini untuk dipasang di menu Apps / Plugins ChatGPT.</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-chatgpt">URL server MCP</label>
                                        <input id="mcp-endpoint-chatgpt" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-3">
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Autentikasi</p>
                                            <p class="font-semibold text-[#111827]">OAuth / Bearer Token</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Hak Akses</p>
                                            <p class="font-semibold text-[#111827]">Akses baca (Read-only)</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Cakupan & Masa Berlaku</p>
                                            <p class="font-semibold text-[#111827]">{{ $hasBranches ? $branches->count().' outlet' : 'Outlet utama' }} · {{ config('oqari_mcp.token_days') }} hari</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dukungan Paket ChatGPT --}}
                                <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-4">
                                    <p class="mb-2.5 text-[12px] font-bold text-[#111827]">Dukungan Paket ChatGPT untuk Custom MCP</p>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-2">
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Free</span>
                                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Tidak mendukung</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Plus</span>
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Terbatas (tergantung rollout)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Pro</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung (Read / Fetch)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Business / Enterprise / Edu</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung penuh</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Langkah Pemasangan ChatGPT --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">Langkah menghubungkan di ChatGPT Web</p>
                                    <ol class="list-decimal space-y-1.5 pl-5 text-[13px] text-[#53565a]">
                                        <li>Buka <strong class="text-[#111827]">chatgpt.com</strong> lalu masuk ke menu <strong class="text-[#111827]">Apps / Plugins</strong>.</li>
                                        <li>Klik tombol <strong class="text-[#111827]">+</strong> dan pilih <strong class="text-[#111827]">Add custom MCP server</strong>.</li>
                                        <li>Masukkan nama <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI Advance</code> dan tempel <strong class="text-[#111827]">Server URL</strong> di atas.</li>
                                        <li>Pilih metode autentikasi yang tersedia (<strong class="text-[#111827]">OAuth</strong> atau <strong class="text-[#111827]">Bearer Token</strong> dengan kunci akses OQARI).</li>
                                        <li>Centang persetujuan lalu klik <strong class="text-[#111827]">Create as a plugin</strong>.</li>
                                        <li>Setelah terhubung, uji dengan memanggil <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">oqari_get_context</code> atau pertanyaan contoh di bawah.</li>
                                    </ol>
                                </div>
                            @elseif($client === 'gemini')
                                {{-- Ringkasan Konfigurasi MCP OQARI untuk Gemini CLI & Antigravity --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Gemini CLI &amp; Antigravity</p>
                                            <p class="text-[12px] text-[#53565a]">Gunakan <strong>Gemini CLI</strong>, <strong>Antigravity</strong>, atau <strong>Gemini Code Assist</strong>. (Gemini Web di <code>gemini.google.com</code> saat ini belum mendukung URL MCP kustom.)</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-gemini">URL server MCP</label>
                                        <input id="mcp-endpoint-gemini" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Cara 1: Perintah Gemini CLI --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">1. Pasang cepat lewat Gemini CLI</p>
                                    <p class="text-[13px] text-[#53565a]">Simpan kunci akses sebagai variabel lingkungan <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>, lalu jalankan perintah ini di terminal:</p>
                                    <label class="sr-only" for="mcp-gemini-cli">Perintah Gemini CLI</label>
                                    <textarea id="mcp-gemini-cli" x-ref="geminiCli" class="{{ $field }} font-mono text-[12px]" readonly rows="2">gemini mcp add --transport http oqari {{ $endpoint }} -H "Authorization: Bearer ${OQARI_MCP_TOKEN}"</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('geminiCli', 'Perintah Gemini CLI')">
                                        <i class="fas text-[12px]" :class="copied === 'geminiCli' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'geminiCli' ? 'Perintah tersalin' : 'Salin perintah Gemini CLI'">Salin perintah Gemini CLI</span>
                                    </button>
                                </div>

                                {{-- Cara 2: Konfigurasi JSON (Gemini CLI / Antigravity) --}}
                                <div class="space-y-2 border-t border-[#f1f3f5] pt-4">
                                    <p class="text-[12px] font-bold text-[#111827]">2. Atau pasang lewat file konfigurasi JSON</p>
                                    <p class="text-[13px] text-[#53565a]">Tambahkan konfigurasi berikut ke <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">~/.gemini/settings.json</code> (Gemini CLI) atau <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">mcp_config.json</code> (Antigravity):</p>
                                    <label class="sr-only" for="mcp-gemini-config">Konfigurasi JSON Gemini</label>
                                    <textarea id="mcp-gemini-config" x-ref="command" class="{{ $field }} font-mono text-[12px]" readonly rows="8">{{ $claudeConfig }}</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('command', 'Konfigurasi')">
                                        <i class="fas text-[12px]" :class="copied === 'command' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'command' ? 'Konfigurasi tersalin' : 'Salin konfigurasi'">Salin konfigurasi</span>
                                    </button>
                                </div>
                            @elseif($connection->last_used_at)
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ $status }}
                                        </span>
                                    @elseif($client === 'chatgpt')
                                {{-- Ringkasan Konfigurasi MCP OQARI --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Custom MCP Server</p>
                                            <p class="text-[12px] text-[#53565a]">Salin URL ini untuk dipasang di menu Apps / Plugins ChatGPT.</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-chatgpt">URL server MCP</label>
                                        <input id="mcp-endpoint-chatgpt" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-3">
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Autentikasi</p>
                                            <p class="font-semibold text-[#111827]">OAuth / Bearer Token</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Hak Akses</p>
                                            <p class="font-semibold text-[#111827]">Akses baca (Read-only)</p>
                                        </div>
                                        <div class="rounded-[8px] border border-[#e9ebf0] bg-white px-3 py-2">
                                            <p class="text-[11px] text-[#7f837e]">Cakupan & Masa Berlaku</p>
                                            <p class="font-semibold text-[#111827]">{{ $hasBranches ? $branches->count().' outlet' : 'Outlet utama' }} · {{ config('oqari_mcp.token_days') }} hari</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dukungan Paket ChatGPT --}}
                                <div class="rounded-[12px] border border-[#e9ebf0] bg-white p-4">
                                    <p class="mb-2.5 text-[12px] font-bold text-[#111827]">Dukungan Paket ChatGPT untuk Custom MCP</p>
                                    <div class="grid gap-2 text-[12px] sm:grid-cols-2">
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Free</span>
                                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Tidak mendukung</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Plus</span>
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Terbatas (tergantung rollout)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Pro</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung (Read / Fetch)</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-[8px] bg-[#f8f9fa] px-3 py-2">
                                            <span class="font-medium text-[#111827]">Business / Enterprise / Edu</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Mendukung penuh</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Langkah Pemasangan ChatGPT --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">Langkah menghubungkan di ChatGPT Web</p>
                                    <ol class="list-decimal space-y-1.5 pl-5 text-[13px] text-[#53565a]">
                                        <li>Buka <strong class="text-[#111827]">chatgpt.com</strong> lalu masuk ke menu <strong class="text-[#111827]">Apps / Plugins</strong>.</li>
                                        <li>Klik tombol <strong class="text-[#111827]">+</strong> dan pilih <strong class="text-[#111827]">Add custom MCP server</strong>.</li>
                                        <li>Masukkan nama <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI Advance</code> dan tempel <strong class="text-[#111827]">Server URL</strong> di atas.</li>
                                        <li>Pilih metode autentikasi yang tersedia (<strong class="text-[#111827]">OAuth</strong> atau <strong class="text-[#111827]">Bearer Token</strong> dengan kunci akses OQARI).</li>
                                        <li>Centang persetujuan lalu klik <strong class="text-[#111827]">Create as a plugin</strong>.</li>
                                        <li>Setelah terhubung, uji dengan memanggil <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">oqari_get_context</code> atau pertanyaan contoh di bawah.</li>
                                    </ol>
                                </div>
                            @elseif($client === 'gemini')
                                {{-- Ringkasan Konfigurasi MCP OQARI untuk Gemini CLI & Antigravity --}}
                                <div class="space-y-3 rounded-[12px] border border-[#e9ebf0] bg-[#f8f9fa] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#111827]">OQARI Advance · Gemini CLI &amp; Antigravity</p>
                                            <p class="text-[12px] text-[#53565a]">Gunakan <strong>Gemini CLI</strong>, <strong>Antigravity</strong>, atau <strong>Gemini Code Assist</strong>. (Gemini Web di <code>gemini.google.com</code> saat ini belum mendukung URL MCP kustom.)</p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#ffdec9] bg-[#fff6f0] px-2.5 py-1 text-[11px] font-semibold text-[#df5826]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#df5826]" aria-hidden="true"></span>
                                            <span>Streamable HTTP</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <label class="sr-only" for="mcp-endpoint-gemini">URL server MCP</label>
                                        <input id="mcp-endpoint-gemini" x-ref="endpoint" class="{{ $field }} flex-1 bg-white font-mono text-[12px]" readonly value="{{ $endpoint }}">
                                        <button type="button" class="{{ $secondaryButton }} shrink-0" @click="copy('endpoint', 'URL')">
                                            <i class="fas text-[12px]" :class="copied === 'endpoint' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'endpoint' ? 'URL tersalin' : 'Salin URL'">Salin URL</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Cara 1: Perintah Gemini CLI --}}
                                <div class="space-y-2">
                                    <p class="text-[12px] font-bold text-[#111827]">1. Pasang cepat lewat Gemini CLI</p>
                                    <p class="text-[13px] text-[#53565a]">Simpan kunci akses sebagai variabel lingkungan <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px] text-[#c94d00]">OQARI_MCP_TOKEN</code>, lalu jalankan perintah ini di terminal:</p>
                                    <label class="sr-only" for="mcp-gemini-cli">Perintah Gemini CLI</label>
                                    <textarea id="mcp-gemini-cli" x-ref="geminiCli" class="{{ $field }} font-mono text-[12px]" readonly rows="2">gemini mcp add --transport http oqari {{ $endpoint }} -H "Authorization: Bearer ${OQARI_MCP_TOKEN}"</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('geminiCli', 'Perintah Gemini CLI')">
                                        <i class="fas text-[12px]" :class="copied === 'geminiCli' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'geminiCli' ? 'Perintah tersalin' : 'Salin perintah Gemini CLI'">Salin perintah Gemini CLI</span>
                                    </button>
                                </div>

                                {{-- Cara 2: Konfigurasi JSON (Gemini CLI / Antigravity) --}}
                                <div class="space-y-2 border-t border-[#f1f3f5] pt-4">
                                    <p class="text-[12px] font-bold text-[#111827]">2. Atau pasang lewat file konfigurasi JSON</p>
                                    <p class="text-[13px] text-[#53565a]">Tambahkan konfigurasi berikut ke <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">~/.gemini/settings.json</code> (Gemini CLI) atau <code class="rounded bg-[#f8f9fa] px-1.5 py-0.5 font-mono text-[12px]">mcp_config.json</code> (Antigravity):</p>
                                    <label class="sr-only" for="mcp-gemini-config">Konfigurasi JSON Gemini</label>
                                    <textarea id="mcp-gemini-config" x-ref="command" class="{{ $field }} font-mono text-[12px]" readonly rows="8">{{ $claudeConfig }}</textarea>
                                    <button type="button" class="{{ $secondaryButton }}" @click="copy('command', 'Konfigurasi')">
                                        <i class="fas text-[12px]" :class="copied === 'command' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                        <span x-text="copied === 'command' ? 'Konfigurasi tersalin' : 'Salin konfigurasi'">Salin konfigurasi</span>
                                    </button>
                                </div>
                            @else
                                        <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-[10px] font-semibold text-sky-700">{{ $status }}</span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-[12px] text-[#7f837e]">
                                    <span class="inline-flex max-w-full items-center rounded-md border border-[#e9ebf0] bg-[#f8f9fa] px-2 py-0.5 font-mono text-[11px] font-medium text-[#53565a] break-all"
                                          @if($canRevealToken) x-text="visibleTokens['{{ $connection->id }}'] ? revealedTokens['{{ $connection->id }}'] : '••••{{ $connection->token_suffix }}'" @endif>
                                        {{ $connection->token_suffix ? '••••'.$connection->token_suffix : 'OAuth' }}
                                    </span>
                                    @if($canRevealToken)
                                        <button type="button"
                                                @click="toggleRevealToken('{{ $connection->id }}', '{{ $revealUrl }}')"
                                                :disabled="loadingToken === '{{ $connection->id }}'"
                                                class="inline-flex h-[26px] cursor-pointer items-center gap-1 rounded-md border border-[#e9ebf0] bg-white px-2 text-[11px] font-semibold text-[#53565a] transition hover:border-[#ffd2b8] hover:bg-[#fff6f0] hover:text-[#df5826] disabled:opacity-60">
                                            <i class="fas text-[10px]" :class="visibleTokens['{{ $connection->id }}'] ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                            <span x-text="visibleTokens['{{ $connection->id }}'] ? 'Sembunyikan' : 'Lihat'">Lihat</span>
                                        </button>
                                        <button type="button"
                                                @click="copyConnectionToken('{{ $connection->id }}', '{{ $revealUrl }}')"
                                                :disabled="loadingToken === '{{ $connection->id }}'"
                                                class="inline-flex h-[26px] cursor-pointer items-center gap-1 rounded-md border border-[#e9ebf0] bg-white px-2 text-[11px] font-semibold text-[#53565a] transition hover:border-[#ffd2b8] hover:bg-[#fff6f0] hover:text-[#df5826] disabled:opacity-60">
                                            <i class="fas text-[10px]" :class="copied === 'conn-{{ $connection->id }}' ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                            <span x-text="copied === 'conn-{{ $connection->id }}' ? 'Tersalin' : 'Salin'">Salin</span>
                                        </button>
                                    @endif
                                    @if($connection->last_used_at)
                                        <span>Terakhir digunakan {{ $connection->last_used_at->diffForHumans() }}</span>
                                    @endif
                                    @if($connection->expires_at)
                                        <span>Berakhir {{ $connection->expires_at->format('d/m/Y') }}</span>
                                    @endif
                                </div>

                                @if(is_array($connection->abilities) && count($connection->abilities))
                                    <div class="flex flex-wrap gap-1.5 pt-0.5">
                                        @foreach($connection->abilities as $connAbility)
                                            <span class="rounded-md border border-[#e9ebf0] bg-white px-2 py-0.5 text-[11px] font-medium text-[#53565a]">
                                                {{ config("oqari_mcp.abilities.$connAbility.label", $connAbility) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if($isActive)
                            <form method="POST" action="{{ route('admin.integrations.mcp.destroy', $connection->id) }}"
                                  @submit="if (!confirm('Cabut koneksi ini? Aplikasi yang memakainya akan kehilangan akses.')) $event.preventDefault()"
                                  class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <button type="submit" class="inline-flex h-[36px] cursor-pointer items-center justify-center gap-1.5 rounded-[10px] border border-red-200 bg-white px-3.5 text-[12px] font-semibold text-red-600 transition hover:bg-red-50 hover:text-red-700">
                                    <i class="fas fa-ban text-[11px]" aria-hidden="true"></i>
                                    <span>Cabut koneksi</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-[14px] bg-[#fff6f0] text-[#df5826]">
                            <i class="fas {{ $tab === 'connected' ? 'fa-link' : 'fa-key' }} text-[18px]" aria-hidden="true"></i>
                        </div>
                        <p class="text-[14px] font-semibold text-[#111827]">
                            {{ $tab === 'connected' ? 'Belum ada aplikasi terhubung' : 'Belum ada kunci akses' }}
                        </p>
                        <p class="mt-1 text-[12px] text-[#7f837e]">
                            {{ $tab === 'connected' ? 'Aplikasi yang pernah menghubungi server MCP OQARI akan muncul di sini.' : 'Buat kunci akses dari tab Hubungkan aplikasi untuk mulai menggunakan MCP.' }}
                        </p>
                    </div>
                @endforelse
            </div>

            @if($connections->hasPages())
                <div class="border-t border-[#e9ebf0] px-5 py-4">{{ $connections->links() }}</div>
            @endif
        </section>
    @endif
</div>
@endsection
