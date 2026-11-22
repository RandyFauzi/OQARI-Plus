<div x-show="currentTab === 'vouchers'" x-cloak class="flex-grow p-4 md:p-6 lg:p-7 overflow-y-auto hide-scroll bg-white font-['DM_Sans',sans-serif]" style="font-feature-settings: 'cv02', 'cv03', 'cv04', 'cv11';">

    <!-- Header Page Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-xl md:text-2xl font-bold text-[#111827] tracking-tight" x-text="canManageVouchers ? 'Kelola Voucher & Kupon Promosi' : 'Daftar Voucher Aktif'"></h1>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-[#fff0e6] text-[#df5826] border border-[#ffd2b8]" x-text="(vouchers.filter(v => v.is_active).length) + ' Kupon Aktif'"></span>
            </div>
            <p class="text-xs md:text-sm text-[#7f837e] mt-1 font-normal" x-text="canManageVouchers ? 'Buat kode kupon promo khusus pelanggan (contoh: HEMAT20, KOPIASIK) untuk meningkatkan penjualan kafe Anda.' : 'Daftar kupon promo yang sedang aktif dan dapat digunakan di outlet Anda.'"></p>
        </div>
        <template x-if="canManageVouchers">
            <button type="button" @click="openCreateVoucherModal()" class="px-5 py-2.5 rounded-[12px] bg-[#df5826] hover:bg-[#c94d00] text-white font-bold text-xs flex items-center justify-center gap-2 shadow-xs transition shrink-0 cursor-pointer">
                <i class="fas fa-plus"></i>
                <span>Buat Voucher Baru</span>
            </button>
        </template>
    </div>

    <!-- Read-Only Notice for Non-Owner -->
    <template x-if="!canManageVouchers">
        <div class="mb-5 rounded-[12px] border border-slate-200 bg-slate-50 px-4 py-3 flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg bg-slate-200/70 text-slate-600 flex items-center justify-center shrink-0">
                <i class="fas fa-lock text-xs"></i>
            </span>
            <p class="text-[12px] text-slate-600 leading-snug">
                <strong class="text-[#111827]">Mode Lihat Saja</strong> &bull; Anda hanya dapat melihat daftar voucher yang sedang aktif. Pembuatan dan pengaturan voucher hanya dapat dilakukan oleh <strong>Owner</strong>.
            </p>
        </div>
    </template>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-[16px] border border-[#e9ebf0] p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-[#7f837e] uppercase tracking-wider">Total Voucher</span>
                <div class="w-8 h-8 rounded-[10px] bg-[#fff0e6] text-[#df5826] flex items-center justify-center text-sm">
                    <i class="fas fa-ticket-alt"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-[#111827] mt-2" x-text="vouchers.length"></p>
            <p class="text-[11px] text-[#7f837e] mt-0.5">Semua voucher yang telah dibuat</p>
        </div>

        <div class="bg-white rounded-[16px] border border-[#e9ebf0] p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-[#7f837e] uppercase tracking-wider">Voucher Aktif</span>
                <div class="w-8 h-8 rounded-[10px] bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-emerald-600 mt-2" x-text="vouchers.filter(v => v.is_active).length"></p>
            <p class="text-[11px] text-[#7f837e] mt-0.5">Dapat digunakan pelanggan sekarang</p>
        </div>

        <div class="bg-white rounded-[16px] border border-[#e9ebf0] p-4 sm:p-5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-[#7f837e] uppercase tracking-wider">Total Terpakai</span>
                <div class="w-8 h-8 rounded-[10px] bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-[#111827] mt-2" x-text="vouchers.reduce((acc, v) => acc + (Number(v.used_count) || 0), 0)"></p>
            <p class="text-[11px] text-[#7f837e] mt-0.5">Total transaksi menggunakan kupon</p>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="bg-white rounded-[20px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-[#e9ebf0] overflow-hidden">
        <!-- Card Header -->
        <div class="p-5 md:p-6 border-b border-[#e9ebf0] bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[12px] bg-[#fff0e6] text-[#df5826] flex items-center justify-center text-base shrink-0 border border-[#ffd2b8]">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base md:text-lg text-[#111827]">Daftar Kupon & Voucher</h3>
                    <p class="text-xs text-[#7f837e]">Kelola promo, diskon, kuota pemakaian, dan status voucher kafe Anda.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="loadVouchers()" class="px-3 py-2 rounded-[10px] bg-white border border-[#e9ebf0] hover:bg-gray-50 text-[#53565a] font-semibold text-xs flex items-center gap-1.5 transition cursor-pointer shadow-2xs" title="Refresh data voucher">
                    <i class="fas fa-rotate" :class="isLoadingVouchers ? 'fa-spin' : ''"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        <div class="p-5 md:p-6">
            <!-- Loading State -->
            <div x-show="isLoadingVouchers" class="py-16 text-center text-[#7f837e]">
                <i class="fas fa-spinner fa-spin text-3xl text-[#df5826] mb-3"></i>
                <p class="text-xs font-medium">Memuat daftar voucher...</p>
            </div>

            <!-- Empty State -->
            <div x-show="!isLoadingVouchers && vouchers.length === 0" class="py-16 text-center text-[#7f837e] border-2 border-dashed border-[#e9ebf0] rounded-[16px] bg-[#fafbfc]">
                <div class="w-14 h-14 rounded-full bg-[#fff0e6] text-[#df5826] flex items-center justify-center mx-auto mb-3.5 shadow-2xs">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                </div>
                <h4 class="font-bold text-base text-[#111827] mb-1">Belum Ada Voucher</h4>
                <p class="text-xs text-[#7f837e] max-w-sm mx-auto mb-5" x-text="canManageVouchers ? 'Buat kode voucher pertama Anda untuk menarik lebih banyak pembeli atau beri reward khusus pelanggan setia.' : 'Belum ada voucher aktif yang tersedia untuk cabang ini.'"></p>
                <template x-if="canManageVouchers">
                    <button type="button" @click="openCreateVoucherModal()" class="px-5 py-2.5 rounded-[12px] bg-[#df5826] hover:bg-[#c94d00] text-white font-bold text-xs transition cursor-pointer shadow-xs">
                        + Buat Voucher Sekarang
                    </button>
                </template>
            </div>

            <!-- Vouchers Table -->
            <div x-show="!isLoadingVouchers && vouchers.length > 0" class="overflow-x-auto -mx-5 md:-mx-6 px-5 md:px-6">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-[#e9ebf0] text-[#7f837e] uppercase text-[10px] font-bold tracking-wider">
                            <th class="py-3 px-3.5">Kode & Nama Voucher</th>
                            <th class="py-3 px-3.5">Cabang</th>
                            <th class="py-3 px-3.5">Besaran Diskon</th>
                            <th class="py-3 px-3.5">Min. Belanja</th>
                            <th class="py-3 px-3.5">Kuota / Terpakai</th>
                            <th class="py-3 px-3.5">Masa Berlaku</th>
                            <th class="py-3 px-3.5 text-center">Status</th>
                            <th x-show="canManageVouchers" class="py-3 px-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f1f3f5]">
                        <template x-for="v in vouchers" :key="v.id">
                            <tr class="hover:bg-[#fafbfc] transition group">
                                <td class="py-3.5 px-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="font-mono font-black text-xs px-2.5 py-1 rounded-[6px] bg-[#f4f5f7] text-[#111827] border border-[#e5e7eb] tracking-wider" x-text="v.code"></span>
                                        <span class="font-bold text-[#111827]" x-text="v.name"></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-3.5">
                                    <span x-show="v.branch" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#fff6f0] text-[#df5826] border border-[#ffdec9]">
                                        <i class="fas fa-store text-[9px]"></i>
                                        <span x-text="v.branch ? v.branch.name : ''"></span>
                                    </span>
                                    <span x-show="!v.branch" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        Semua Cabang
                                    </span>
                                </td>
                                <td class="py-3.5 px-3.5 font-bold text-emerald-600">
                                    <span x-text="v.discount_type === 'percentage' ? v.discount_value + '%' : 'Rp ' + Number(v.discount_value).toLocaleString('id-ID')"></span>
                                    <span x-show="v.max_discount" class="text-[10px] text-[#7f837e] font-normal block mt-0.5" x-text="'Maks Rp ' + Number(v.max_discount).toLocaleString('id-ID')"></span>
                                </td>
                                <td class="py-3.5 px-3.5 text-[#53565a] font-medium">
                                    <span x-text="v.min_spend > 0 ? 'Rp ' + Number(v.min_spend).toLocaleString('id-ID') : 'Tanpa Min.'"></span>
                                </td>
                                <td class="py-3.5 px-3.5">
                                    <span class="font-bold text-[#111827]" x-text="v.used_count"></span>
                                    <span class="text-[#7f837e]" x-text="v.usage_limit ? ' / ' + v.usage_limit : ' (Tanpa batas)'"></span>
                                </td>
                                <td class="py-3.5 px-3.5 text-[#53565a]">
                                    <span x-text="v.expires_at ? new Date(v.expires_at).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) : 'Selamanya'"></span>
                                </td>
                                <td class="py-3.5 px-3.5 text-center">
                                    <template x-if="canManageVouchers">
                                        <button type="button" @click="toggleVoucher(v)"
                                            :class="v.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-500 border-gray-200'"
                                            class="px-3 py-1 rounded-full text-[11px] font-bold border transition cursor-pointer">
                                            <span x-text="v.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                        </button>
                                    </template>
                                    <template x-if="!canManageVouchers">
                                        <span :class="v.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-500 border-gray-200'"
                                            class="inline-block px-3 py-1 rounded-full text-[11px] font-bold border">
                                            <span x-text="v.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                        </span>
                                    </template>
                                </td>
                                <td x-show="canManageVouchers" class="py-3.5 px-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" @click="openEditVoucherModal(v)" class="w-8 h-8 rounded-[8px] bg-white border border-[#e9ebf0] hover:border-[#df5826] hover:bg-[#fff6f0] text-gray-500 hover:text-[#df5826] flex items-center justify-center transition cursor-pointer shadow-2xs" title="Edit Voucher">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button type="button" @click="deleteVoucher(v)" class="w-8 h-8 rounded-[8px] bg-white border border-[#e9ebf0] hover:border-red-300 hover:bg-red-50 text-gray-400 hover:text-red-500 flex items-center justify-center transition cursor-pointer shadow-2xs" title="Hapus Voucher">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
