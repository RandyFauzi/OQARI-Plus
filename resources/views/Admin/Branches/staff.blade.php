@extends('Admin.Users.layout')

@section('pageTitle', 'Staf Cabang')

@section('content')
@php
    $userPayload = $users->map(fn ($u) => [
        'id' => (string) $u->id,
        'name' => $u->name,
        'email' => $u->email,
    ])->values();
    $rolePayload = collect(config('access.roles'))->map(fn ($label, $role) => [
        'value' => $role,
        'label' => $role === 'manager' ? 'Kepala Cabang' : $label,
    ])->values();
@endphp

<div class="space-y-5">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.branches.index') }}"
               class="inline-flex w-fit items-center gap-1.5 text-[12px] font-semibold text-[#53565a] transition hover:text-[#df5826]">
                <i class="fas fa-arrow-left text-[11px]" aria-hidden="true"></i>
                <span>Semua Cabang</span>
            </a>
            <h1 class="mt-1 text-[20px] font-bold tracking-tight text-[#111827] md:text-[22px]">Staf {{ $branch->name }}</h1>
            <p class="mt-0.5 text-[13px] text-[#53565a]">Kelola anggota tim yang memiliki akses operasional di cabang ini.</p>
        </div>
        <a href="{{ route('branch.admin.users.create', ['operatingBranch' => $branch->slug]) }}"
           class="inline-flex h-[38px] items-center gap-2 rounded-[10px] bg-[#df5826] px-4 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00]">
            <i class="fas fa-user-plus text-[11px]" aria-hidden="true"></i>
            <span>Buat akun staf</span>
        </a>
    </header>

    @if(session('success'))
        <div role="status" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] font-semibold text-emerald-800">
            <i class="fas fa-circle-check shrink-0" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <section class="overflow-hidden rounded-[14px] border border-[#e9ebf0] bg-white shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
        <div class="flex items-center justify-between border-b border-[#e9ebf0] px-5 py-3.5">
            <h2 class="text-[14px] font-bold text-[#111827]">Anggota Cabang</h2>
            <span class="rounded-full bg-[#f1f3f5] px-2.5 py-0.5 text-[11px] font-semibold text-[#53565a]">{{ $members->count() }} staf</span>
        </div>
        <ul class="divide-y divide-[#f1f3f5]">
            @forelse($members as $member)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-[#fafaf9]">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#fff6f0] text-[12px] font-bold text-[#df5826]">
                            {{ strtoupper(mb_substr($member->user->name, 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-bold text-[#111827]">{{ $member->user->name }}</p>
                            <p class="break-all text-[12px] text-[#53565a]">
                                {{ $member->user->email }}
                                <span class="mx-1 text-[#9ca3af]">·</span>
                                <span class="rounded bg-[#f1f3f5] px-1.5 py-0.5 text-[11px] font-semibold text-[#111827]">
                                    {{ $member->role === 'manager' ? 'Kepala Cabang' : config('access.roles.'.$member->role, $member->role) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a class="inline-flex h-[34px] items-center gap-1.5 rounded-[9px] border border-[#e9ebf0] bg-white px-3 text-[12px] font-semibold text-[#111827] transition hover:border-[#df5826] hover:text-[#df5826]"
                           href="{{ route('branch.admin.users.edit', ['operatingBranch' => $branch->slug, 'cafeUser' => $member->user_id]) }}">
                            <i class="fas fa-pen text-[10px]" aria-hidden="true"></i>
                            <span>Edit akun</span>
                        </a>
                        <form action="{{ route('admin.branches.unassign', [$branch, $member->user_id]) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex h-[34px] cursor-pointer items-center gap-1.5 rounded-[9px] border border-rose-200 bg-rose-50 px-3 text-[12px] font-semibold text-rose-700 transition hover:bg-rose-100">
                                <i class="fas fa-user-minus text-[10px]" aria-hidden="true"></i>
                                <span>Cabut akses</span>
                            </button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-[13px] text-[#7f837e]">Belum ada staf yang ditugaskan.</li>
            @endforelse
        </ul>
    </section>

    @if($users->isNotEmpty())
        <section x-data="{
            users: @js($userPayload),
            roles: @js($rolePayload),
            userId: @js((string) ($users->first()->id ?? '')),
            userOpen: false,
            userSearch: '',
            roleValue: @js((string) ($rolePayload->first()['value'] ?? 'cashier')),
            roleOpen: false,
            submitting: false,
            filteredUsers() {
                const q = this.userSearch.trim().toLowerCase();
                if (!q) return this.users;
                return this.users.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
            },
            selectedUser() {
                return this.users.find(u => u.id === String(this.userId)) || null;
            },
            selectedRole() {
                return this.roles.find(r => r.value === String(this.roleValue)) || null;
            }
        }" @keydown.escape.window="userOpen = false; roleOpen = false"
                 class="rounded-[14px] border border-[#e9ebf0] bg-white p-5 shadow-[0px_2px_4px_rgba(38,39,35,0.03)]">
            <div class="border-b border-[#e9ebf0] pb-3">
                <h2 class="text-[15px] font-bold text-[#111827]">Tugaskan akun yang sudah ada</h2>
                <p class="text-[12px] text-[#53565a]">Berikan akses cabang ini kepada staf yang sudah terdaftar di bisnis Anda.</p>
            </div>

            <form action="{{ route('admin.branches.assign', $branch) }}" method="POST" @submit="submitting = true"
                  class="mt-4 grid items-end gap-4 md:grid-cols-[1.5fr_1fr_auto]">
                @csrf
                <input type="hidden" name="user_id" :value="userId">
                <input type="hidden" name="role" :value="roleValue">

                <div class="relative space-y-1.5" @click.outside="userOpen = false">
                    <span class="block text-[12px] font-semibold text-[#111827]">Akun staf</span>
                    <button type="button" @click="userOpen = !userOpen"
                            class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                            :class="userOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                        <span class="truncate font-medium text-[#111827]"
                              x-text="selectedUser() ? (selectedUser().name + ' · ' + selectedUser().email) : 'Pilih akun staf'"></span>
                        <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                    </button>
                    <div x-show="userOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white shadow-xl">
                        <div class="border-b border-[#f1f3f5] p-2">
                            <input type="text" x-model="userSearch" placeholder="Cari nama atau email..."
                                   class="w-full rounded-[8px] border border-[#e9ebf0] bg-[#f8f9fa] px-2.5 py-1.5 text-[12px] outline-none focus:border-[#df5826] focus:bg-white">
                        </div>
                        <div class="max-h-52 overflow-y-auto p-1">
                            <template x-for="u in filteredUsers()" :key="u.id">
                                <button type="button" @click="userId = u.id; userOpen = false; userSearch = ''"
                                        class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px]"
                                        :class="String(userId) === u.id ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                    <div class="min-w-0">
                                        <div class="truncate" x-text="u.name"></div>
                                        <div class="truncate text-[11px] text-[#7f837e]" x-text="u.email"></div>
                                    </div>
                                    <i x-show="String(userId) === u.id" class="fas fa-check text-[11px] text-[#df5826]"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="relative space-y-1.5" @click.outside="roleOpen = false">
                    <span class="block text-[12px] font-semibold text-[#111827]">Peran di cabang</span>
                    <button type="button" @click="roleOpen = !roleOpen"
                            class="flex h-[40px] w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] border bg-white px-3.5 text-left text-[13px] transition"
                            :class="roleOpen ? 'border-[#df5826] ring-2 ring-[#df5826]/15' : 'border-[#e9ebf0] hover:border-gray-300'">
                        <span class="truncate font-medium text-[#111827]" x-text="selectedRole()?.label || 'Pilih peran'"></span>
                        <i class="fas fa-chevron-down text-[11px] text-[#7f837e]" aria-hidden="true"></i>
                    </button>
                    <div x-show="roleOpen" x-cloak class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-[12px] border border-[#e9ebf0] bg-white p-1 shadow-xl">
                        <template x-for="r in roles" :key="r.value">
                            <button type="button" @click="roleValue = r.value; roleOpen = false"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-left text-[12px]"
                                    :class="roleValue === r.value ? 'bg-[#fff6f0] font-semibold text-[#df5826]' : 'text-[#111827] hover:bg-[#f8f9fa]'">
                                <span x-text="r.label"></span>
                                <i x-show="roleValue === r.value" class="fas fa-check text-[11px] text-[#df5826]"></i>
                            </button>
                        </template>
                    </div>
                </div>

                <button type="submit" :disabled="submitting"
                        class="inline-flex h-[40px] cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-[#df5826] px-5 text-[12px] font-semibold text-white shadow-[0px_2px_4px_rgba(223,88,38,0.25)] transition hover:bg-[#c94d00] disabled:opacity-60">
                    <i class="fas fa-check text-[11px]" aria-hidden="true"></i>
                    <span>Simpan penugasan</span>
                </button>
            </form>

            @foreach($errors->all() as $error)
                <p role="alert" class="mt-2 text-[12px] font-semibold text-rose-600">{{ $error }}</p>
            @endforeach
        </section>
    @endif
</div>
@endsection
