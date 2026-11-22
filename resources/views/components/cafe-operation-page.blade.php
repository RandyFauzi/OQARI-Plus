@props(['title', 'shop'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ $shop->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme-head :shop="$shop" />
</head>
<body class="min-h-screen bg-white font-sans antialiased">
    <div class="cafe-page-shell">
        <x-cafe-sidebar />
        <div class="cafe-page-content cafe-operations bg-white">
            <a href="#operation-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-30 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Lewati navigasi</a>
            <header class="sticky top-0 z-20 flex h-[52px] items-center justify-between gap-3 border-b border-[#e9ebf0] bg-white px-4 lg:px-6">
                <div class="flex min-w-0 items-center gap-2.5">
                    <button type="button" x-data @click="$dispatch('toggle-mobile-nav')" aria-label="Buka menu navigasi" aria-controls="app-sidebar"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[#f8f9fa] text-[#53565a] transition-colors hover:bg-gray-100 md:hidden">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>
                </div>
                <x-topbar-datetime />
            </header>

            <main id="operation-content" tabindex="-1" class="mx-auto flex w-full max-w-[1440px] flex-col gap-5 p-4 md:p-6 lg:px-7">
                @if (session('status'))
                    <div role="status" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] font-semibold text-emerald-800">
                        <i class="fas fa-circle-check shrink-0" aria-hidden="true"></i>
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <ul role="alert" class="list-disc rounded-xl border border-red-200 bg-red-50 py-3 pl-8 pr-4 text-[13px] text-red-800">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
