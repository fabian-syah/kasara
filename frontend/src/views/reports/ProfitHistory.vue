<template>
    <div class="space-y-4 sm:space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-bold text-text-primary tracking-tight">Riwayat Profit</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/20">
                        Default Bulanan
                    </span>
                </div>
                <p class="text-xs font-semibold text-text-secondary">
                    Rekapitulasi riwayat profit penjualan berdasarkan perhitungan sistem dan verifikasi audit
                </p>
            </div>

                <!-- Buka Audit Profit Link -->
                <router-link v-if="canAccessAudit" to="/audit/uc/profit"
                    class="flex items-center justify-center gap-1.5 px-3.5 sm:px-4 py-2.5 min-h-[42px] rounded-xl text-xs font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 border border-primary-200 dark:border-primary-500/30 transition-all shadow-sm whitespace-nowrap">
                    <ClipboardCheck :size="15" />
                    <span>Buka Audit Profit</span>
                </router-link>

                <!-- Refresh Button -->
                <button @click="fetchData" :disabled="loading"
                    class="p-2.5 min-w-[42px] min-h-[42px] flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-800 text-text-secondary hover:text-text-primary shadow-sm transition-all disabled:opacity-50"
                    title="Segarkan Data" aria-label="Segarkan Data">
                    <RefreshCw :size="16" :class="{ 'animate-spin': loading }" />
                </button>

                <!-- Export Excel -->
                <button @click="exportDataExcel" :disabled="loading || exporting || allFilteredSales.length === 0"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-2 px-4 sm:px-5 py-2.5 min-h-[42px] bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-500/20 transition-all disabled:opacity-50">
                    <Download :size="15" :class="{ 'animate-bounce': exporting }" />
                    <span class="whitespace-nowrap">{{ exporting ? 'Mengunduh...' : 'Export Excel' }}</span>
                </button>
            </div>
        </div>

        <!-- Notification Banner: Transaksi Belum Dikerjakan -->
        <div v-if="unAuditedCount > 0"
            class="p-4 rounded-2xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 text-text-primary transition-all flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-sm">
            <div class="flex items-start sm:items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <AlertTriangle :size="20" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-sm font-bold text-amber-800 dark:text-amber-300">
                            Perhatian: Transaksi Belum Selesai Audit Profit
                        </h2>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500 text-white shrink-0">
                            {{ unAuditedCount }} Transaksi
                        </span>
                    </div>
                    <p class="text-xs text-amber-700/80 dark:text-amber-200/70 mt-0.5">
                        Terdapat {{ unAuditedCount }} transaksi pada periode ini yang belum dikerjakan harga modal dan profitnya oleh tim Audit.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-amber-200/60 dark:border-amber-500/20">
                <button @click="togglePendingFilter"
                    class="flex-1 md:flex-initial px-3.5 py-2 min-h-[40px] rounded-xl text-xs font-bold transition-all border text-center whitespace-nowrap"
                    :class="filters.audit_status === 'belum'
                        ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                        : 'bg-white dark:!bg-surface-800 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-500/30 hover:bg-amber-100/50 dark:hover:bg-amber-500/20'">
                    {{ filters.audit_status === 'belum' ? 'Semua Status' : 'Filter Belum Dikerjakan' }}
                </button>

                <router-link v-if="canAccessAudit" to="/audit/uc/profit"
                    class="flex-1 md:flex-initial px-3.5 py-2 min-h-[40px] rounded-xl text-xs font-bold text-gray-700 dark:text-gray-200 bg-gray-100 dark:!bg-surface-700 hover:bg-gray-200 dark:hover:bg-surface-600 transition-all text-center flex items-center justify-center whitespace-nowrap">
                    Buka Audit Profit
                </router-link>
            </div>
        </div>

        <!-- Filter & Control Card -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-4">
            <!-- Row 1: Period Presets & Date Selection -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 sm:gap-4">
                <!-- Preset Buttons (Scrollable on small mobile) -->
                <div class="flex items-center gap-1 overflow-x-auto no-scrollbar p-1 bg-gray-100 dark:!bg-surface-900/60 rounded-xl border border-gray-200 dark:border-surface-700">
                    <button @click="setPeriodMode('month')"
                        class="px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap shrink-0 min-h-[36px]"
                        :class="activePeriodMode === 'month' ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                        Bulan Ini (Default)
                    </button>
                    <button @click="setPeriodMode('last_month')"
                        class="px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap shrink-0 min-h-[36px]"
                        :class="activePeriodMode === 'last_month' ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                        Bulan Lalu
                    </button>
                    <button @click="setPeriodMode('today')"
                        class="px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap shrink-0 min-h-[36px]"
                        :class="activePeriodMode === 'today' ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                        Hari Ini
                    </button>
                    <button @click="setPeriodMode('yesterday')"
                        class="px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap shrink-0 min-h-[36px]"
                        :class="activePeriodMode === 'yesterday' ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                        Kemarin
                    </button>
                    <button @click="setPeriodMode('custom')"
                        class="px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap shrink-0 min-h-[36px]"
                        :class="activePeriodMode === 'custom' ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                        Kustom
                    </button>
                </div>

                <!-- Calculation View Mode: By Sistem vs By Audit vs Perbandingan -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <span class="text-xs font-semibold text-text-secondary hidden xl:inline">Mode Perhitungan:</span>
                    <div class="grid grid-cols-3 sm:flex items-center bg-gray-100 dark:!bg-surface-900/60 p-1 rounded-xl border border-gray-200 dark:border-surface-700 w-full sm:w-auto">
                        <button @click="calcMode = 'compare'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-center min-h-[36px]"
                            :class="calcMode === 'compare' ? 'bg-primary-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                            Perbandingan
                        </button>
                        <button @click="calcMode = 'system'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-center min-h-[36px]"
                            :class="calcMode === 'system' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                            By Sistem
                        </button>
                        <button @click="calcMode = 'audit'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-center min-h-[36px]"
                            :class="calcMode === 'audit' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'">
                            By Audit
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 2: Date Selector Details (Month/Year Picker or Custom Range) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-gray-100 dark:border-surface-700/60">
                <!-- If Monthly Mode -->
                <div v-if="activePeriodMode === 'month' || activePeriodMode === 'last_month'" class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="flex items-center gap-2 bg-gray-50 dark:!bg-surface-800 px-3 py-2 rounded-xl border border-gray-200 dark:border-surface-600 w-full sm:w-auto min-h-[42px]">
                        <Calendar :size="16" class="text-primary-500 shrink-0" />
                        <span class="text-xs font-semibold text-text-secondary shrink-0">Bulan:</span>
                        <select v-model="selectedMonth" @change="onMonthOrYearChange"
                            class="bg-transparent text-xs sm:text-sm font-bold text-text-primary outline-none cursor-pointer flex-1 sm:flex-initial">
                            <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1"
                                class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">{{ name }}</option>
                        </select>
                        <select v-model="selectedYear" @change="onMonthOrYearChange"
                            class="bg-transparent text-xs sm:text-sm font-bold text-text-primary outline-none cursor-pointer ml-1">
                            <option v-for="y in availableYears" :key="y" :value="y"
                                class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">{{ y }}</option>
                        </select>
                    </div>
                </div>

                <!-- If Custom Date Range Mode -->
                <div v-else class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 bg-gray-50 dark:!bg-surface-800 px-3 py-2 rounded-xl border border-gray-200 dark:border-surface-600 w-full sm:w-auto min-h-[42px]">
                        <Calendar :size="16" class="text-primary-500 shrink-0" />
                        <input type="date" v-model="filters.start_date"
                            class="bg-transparent text-xs font-bold text-text-primary outline-none flex-1 min-w-[120px]" />
                        <span class="text-xs text-text-secondary font-bold">s/d</span>
                        <input type="date" v-model="filters.end_date"
                            class="bg-transparent text-xs font-bold text-text-primary outline-none flex-1 min-w-[120px]" />
                        <button @click="fetchData"
                            class="w-full sm:w-auto px-3 py-1.5 bg-primary-500 text-white rounded-lg text-xs font-bold hover:bg-primary-600 transition-all text-center">
                            Terapkan
                        </button>
                    </div>
                </div>

                <!-- Quick Category Filter: Barang Angkat & Tukar Tambah -->
                <div class="w-full sm:w-auto">
                    <button @click="toggleTradeInFilter"
                        class="w-full sm:w-auto px-3.5 py-2 rounded-xl text-xs font-bold transition-all border flex items-center justify-center gap-2 min-h-[42px]"
                        :class="isTradeInFilterActive
                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                            : 'bg-gray-50 dark:!bg-surface-800 text-text-secondary hover:text-text-primary border-gray-200 dark:border-surface-600'">
                        <PackageOpen :size="15" />
                        <span class="whitespace-nowrap">Barang Angkat & Tukar Tambah</span>
                    </button>
                </div>
            </div>

            <!-- Row 3: Dropdown Filters (Distributor, Kategori createsale, Cabang, Status Audit, Search) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 pt-2">
                <!-- Filter Distributor -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-1">
                        Distributor
                    </label>
                    <div class="relative">
                        <select v-model="filters.distributor_id" @change="fetchData"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-3 py-2.5 min-h-[42px] text-xs font-semibold text-text-primary focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer pr-8">
                            <option value="all" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Semua Distributor</option>
                            <option v-for="dist in distributorsList" :key="dist.id" :value="dist.id"
                                class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">
                                {{ dist.name }}
                            </option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>

                <!-- Filter Kategori (createsale.vue & online) -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-1">
                        Kategori Transaksi
                    </label>
                    <div class="relative">
                        <select v-model="filters.category" @change="onCategoryChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-3 py-2.5 min-h-[42px] text-xs font-semibold text-text-primary focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer pr-8">
                            <option value="all" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Semua Kategori</option>
                            <option value="angkat_tukar_tambah" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Angkat & Tukar Tambah</option>
                            <option value="penjualan_store" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Penjualan Store</option>
                            <option value="angkat_barang" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Angkat Barang</option>
                            <option value="tukar_tambah" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Tukar Tambah</option>
                            <option value="tukar_unit" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Tukar Unit</option>
                            <option value="downgrade" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Downgrade</option>
                            <option value="refund" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Refund</option>
                            <option value="dp" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">DP (Down Payment)</option>
                            <option value="pelunasan_dp" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Pelunasan DP</option>
                            <option value="refund_dp" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Refund DP</option>
                            <option value="orderan_online" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Orderan Online (Shopee)</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>

                <!-- Filter Lokasi / Cabang -->
                <div v-if="canFilterBranch">
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-1">
                        Cabang / Toko
                    </label>
                    <div class="relative">
                        <select v-model="selectedLocationKey" @change="fetchData"
                            :disabled="isRestrictedRole && filteredLocations.length <= 1"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-3 py-2.5 min-h-[42px] text-xs font-semibold text-text-primary focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer pr-8 disabled:opacity-80 disabled:cursor-not-allowed">
                            <option v-if="isGlobalRole || filteredLocations.length > 1" value="all" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">
                                {{ isGlobalRole ? 'Semua Cabang/Toko' : 'Semua Cabang Saya' }}
                            </option>
                            <option v-for="loc in filteredLocations" :key="`${loc.type}:${loc.id}`"
                                :value="`${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`"
                                class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">
                                {{ loc.type === 'branch' ? '[Cabang]' : loc.type === 'online_shop' ? '[Online]' : '[Distributor]' }} {{ loc.name }}
                            </option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>

                <!-- Filter Status Audit -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-1">
                        Status Pengerjaan
                    </label>
                    <div class="relative">
                        <select v-model="filters.audit_status" @change="fetchData"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-3 py-2.5 min-h-[42px] text-xs font-semibold text-text-primary focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer pr-8">
                            <option value="all" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Semua Status</option>
                            <option value="sudah" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Sudah Dikerjakan</option>
                            <option value="belum" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">Belum Dikerjakan</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>

                <!-- Search Input -->
                <div class="sm:col-span-2 md:col-span-3 xl:col-span-1">
                    <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-1">
                        Pencarian Cepat
                    </label>
                    <div class="relative">
                        <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        <input type="text" v-model="searchQuery"
                            placeholder="Cari nota, customer, IMEI..."
                            class="w-full bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl pl-8 pr-3 py-2.5 min-h-[42px] text-xs font-medium text-text-primary placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all outline-none" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- Total Penjualan -->
            <div class="bg-white dark:!bg-surface-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-blue-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500"></div>
                <div class="relative">
                    <p class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 sm:gap-2">
                        <ShoppingCart :size="14" class="text-blue-500" />
                        Total Penjualan
                    </p>
                    <p class="text-xl sm:text-2xl font-black text-text-primary mt-2 tracking-tight break-words">
                        {{ formatCurrency(summaryTotals.totalPenjualan) }}
                    </p>
                    <p class="text-[10px] text-text-secondary mt-1 font-medium">
                        {{ formatNumber(summaryTotals.totalTransaksi) }} Transaksi Penjualan
                    </p>
                </div>
            </div>

            <!-- Total Modal (Sistem & Audit) -->
            <div class="bg-white dark:!bg-surface-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500"></div>
                <div class="relative">
                    <p class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 sm:gap-2">
                        <Box :size="14" class="text-amber-500" />
                        Total Modal
                    </p>
                    <!-- If By Sistem Mode -->
                    <div v-if="calcMode === 'system'" class="mt-2">
                        <p class="text-xl sm:text-2xl font-black text-text-primary tracking-tight break-words">
                            {{ formatCurrency(summaryTotals.totalModalSystem) }}
                        </p>
                        <p class="text-[10px] text-blue-600 dark:text-blue-400 font-bold mt-1">
                            Modal Berdasarkan Sistem (HPP)
                        </p>
                    </div>
                    <!-- If By Audit Mode -->
                    <div v-else-if="calcMode === 'audit'" class="mt-2">
                        <p class="text-xl sm:text-2xl font-black text-text-primary tracking-tight break-words">
                            {{ formatCurrency(summaryTotals.totalModalAudit) }}
                        </p>
                        <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold mt-1">
                            Modal Berdasarkan Audit
                        </p>
                    </div>
                    <!-- If Compare Mode -->
                    <div v-else class="mt-2 space-y-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-xs font-bold text-text-secondary">Sistem:</span>
                            <span class="text-sm sm:text-base font-mono font-black text-text-primary truncate">{{ formatCurrency(summaryTotals.totalModalSystem) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-t border-gray-100 dark:border-surface-700/60 pt-1">
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Audit:</span>
                            <span class="text-sm sm:text-base font-mono font-black text-emerald-600 dark:text-emerald-400 truncate">{{ formatCurrency(summaryTotals.totalModalAudit) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Profit (Sistem & Audit) -->
            <div class="bg-white dark:!bg-surface-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500"></div>
                <div class="relative">
                    <p class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 sm:gap-2">
                        <TrendingUp :size="14" class="text-emerald-500" />
                        Total Profit
                    </p>
                    <!-- If By Sistem Mode -->
                    <div v-if="calcMode === 'system'" class="mt-2">
                        <p class="text-xl sm:text-2xl font-black tracking-tight break-words"
                            :class="summaryTotals.totalProfitSystem >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                            {{ formatCurrency(summaryTotals.totalProfitSystem) }}
                        </p>
                        <p class="text-[10px] font-bold text-text-secondary mt-1">
                            Margin: {{ formatPercent(summaryTotals.totalProfitSystem, summaryTotals.totalPenjualan) }}
                        </p>
                    </div>
                    <!-- If By Audit Mode -->
                    <div v-else-if="calcMode === 'audit'" class="mt-2">
                        <p class="text-xl sm:text-2xl font-black tracking-tight break-words"
                            :class="summaryTotals.totalProfitAudit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                            {{ formatCurrency(summaryTotals.totalProfitAudit) }}
                        </p>
                        <p class="text-[10px] font-bold text-text-secondary mt-1">
                            Margin: {{ formatPercent(summaryTotals.totalProfitAudit, summaryTotals.totalPenjualan) }}
                        </p>
                    </div>
                    <!-- If Compare Mode -->
                    <div v-else class="mt-2 space-y-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-xs font-bold text-text-secondary">Sistem:</span>
                            <span class="text-sm sm:text-base font-mono font-black truncate"
                                :class="summaryTotals.totalProfitSystem >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ formatCurrency(summaryTotals.totalProfitSystem) }}
                            </span>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-t border-gray-100 dark:border-surface-700/60 pt-1">
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Audit:</span>
                            <span class="text-sm sm:text-base font-mono font-black truncate"
                                :class="summaryTotals.totalProfitAudit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ formatCurrency(summaryTotals.totalProfitAudit) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Pengerjaan Audit -->
            <div class="bg-white dark:!bg-surface-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-purple-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500"></div>
                <div class="relative">
                    <p class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 sm:gap-2">
                        <Clock :size="14" class="text-purple-500" />
                        Progress Audit
                    </p>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">
                            {{ summaryTotals.sudahDiaudit }}
                        </span>
                        <span class="text-xs text-text-secondary font-bold">/ {{ summaryTotals.totalTransaksi }} Selesai</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:!bg-surface-700 h-2 rounded-full overflow-hidden mt-3">
                        <div class="bg-emerald-500 h-full rounded-full transition-all"
                            :style="{ width: `${summaryTotals.totalTransaksi ? Math.round((summaryTotals.sudahDiaudit / summaryTotals.totalTransaksi) * 100) : 0}%` }">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Tabs: Rekapitulasi per Tanggal vs Daftar Transaksi -->
        <div class="border-b border-gray-200 dark:border-surface-700 overflow-x-auto no-scrollbar">
            <div class="flex items-center gap-2 min-w-max sm:min-w-0">
                <button @click="activeTab = 'daily_summary'"
                    class="px-3 sm:px-4 py-3 text-xs font-black uppercase tracking-wider border-b-2 transition-all flex items-center gap-2 min-h-[44px] whitespace-nowrap"
                    :class="activeTab === 'daily_summary'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
                    <Calendar :size="15" />
                    <span>Rekapitulasi per Tanggal (Bulanan)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-gray-100 dark:!bg-surface-700 text-gray-700 dark:text-gray-300 font-bold">
                        {{ dailyAggregations.length }} Hari
                    </span>
                </button>

                <button @click="activeTab = 'transaction_list'"
                    class="px-3 sm:px-4 py-3 text-xs font-black uppercase tracking-wider border-b-2 transition-all flex items-center gap-2 min-h-[44px] whitespace-nowrap"
                    :class="activeTab === 'transaction_list'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
                    <Receipt :size="15" />
                    <span>Rincian Semua Transaksi</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-gray-100 dark:!bg-surface-700 text-gray-700 dark:text-gray-300 font-bold">
                        {{ allFilteredSales.length }} Trx
                    </span>
                </button>
            </div>
        </div>

        <!-- TAB 1: REKAPITULASI PER TANGGAL (BULANAN) -->
        <div v-if="activeTab === 'daily_summary'">
            <!-- Desktop / Tablet Table View (>= md) -->
            <div class="hidden md:block bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 dark:!bg-surface-700/50 border-b border-gray-200 dark:border-surface-700 uppercase font-bold text-gray-600 dark:text-gray-300 text-[11px]">
                            <tr>
                                <th class="px-4 py-3.5 w-12 text-center">No</th>
                                <th class="px-4 py-3.5">Tanggal</th>
                                <th class="px-4 py-3.5 text-center">Jumlah Transaksi</th>
                                <th class="px-4 py-3.5 text-right">Total Penjualan</th>
                                <th v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-3.5 text-right">
                                    Modal (Sistem)
                                </th>
                                <th v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-3.5 text-right">
                                    Modal (Audit)
                                </th>
                                <th v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-3.5 text-right">
                                    Profit (Sistem)
                                </th>
                                <th v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-3.5 text-right">
                                    Profit (Audit)
                                </th>
                                <th v-if="calcMode === 'compare'" class="px-4 py-3.5 text-right">
                                    Selisih Profit
                                </th>
                                <th class="px-4 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700 text-text-primary">
                            <!-- Loading State -->
                            <tr v-if="loading">
                                <td colspan="10" class="py-16 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <Loader2 :size="24" class="animate-spin text-primary-500" />
                                        <span class="text-xs font-semibold">Memuat rekapitulasi profit...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="dailyAggregations.length === 0">
                                <td colspan="10" class="py-16 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <div class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-1">
                                            <TrendingUp :size="24" class="text-gray-400" />
                                        </div>
                                        <span class="text-sm font-bold text-text-primary">Tidak Ada Data Profit</span>
                                        <span class="text-xs">Tidak ditemukan transaksi pada rentang periode ini.</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <template v-else v-for="(day, idx) in dailyAggregations" :key="day.dateStr">
                                <tr class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors font-medium">
                                    <td class="px-4 py-3.5 text-center text-text-secondary">{{ idx + 1 }}</td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-text-primary">{{ formatDateLabel(day.dateStr) }}</div>
                                        <div class="text-[10px] text-text-secondary font-mono">{{ day.dateStr }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="font-bold font-mono">{{ day.count }}</span>
                                            <span v-if="day.unAuditedCount > 0"
                                                class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20"
                                                :title="`${day.unAuditedCount} transaksi belum diaudit`">
                                                {{ day.unAuditedCount }} blm audit
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono font-bold">
                                        {{ formatCurrency(day.totalPenjualan) }}
                                    </td>
                                    <td v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-3.5 text-right font-mono">
                                        {{ formatCurrency(day.totalModalSystem) }}
                                    </td>
                                    <td v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-3.5 text-right font-mono">
                                        {{ formatCurrency(day.totalModalAudit) }}
                                    </td>
                                    <td v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-3.5 text-right font-mono font-bold"
                                        :class="day.totalProfitSystem >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                        {{ formatCurrency(day.totalProfitSystem) }}
                                    </td>
                                    <td v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-3.5 text-right font-mono font-bold"
                                        :class="day.totalProfitAudit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                        {{ formatCurrency(day.totalProfitAudit) }}
                                    </td>
                                    <td v-if="calcMode === 'compare'" class="px-4 py-3.5 text-right font-mono font-bold"
                                        :class="(day.totalProfitAudit - day.totalProfitSystem) >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-amber-600 dark:text-amber-400'">
                                        {{ formatCurrency(day.totalProfitAudit - day.totalProfitSystem) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <button @click="toggleExpandDay(day.dateStr)"
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold border transition-all inline-flex items-center gap-1"
                                            :class="expandedDays.includes(day.dateStr)
                                                ? 'bg-primary-600 text-white border-primary-600'
                                                : 'bg-gray-100 dark:!bg-surface-700 text-text-secondary hover:text-text-primary border-gray-200 dark:border-surface-600'">
                                            <span>{{ expandedDays.includes(day.dateStr) ? 'Tutup' : 'Lihat' }}</span>
                                            <ChevronDown :size="12" :class="{ 'rotate-180': expandedDays.includes(day.dateStr) }" />
                                        </button>
                                    </td>
                                </tr>

                                <!-- Inline Expanded Day Details -->
                                <tr v-if="expandedDays.includes(day.dateStr)" class="bg-gray-50/60 dark:bg-surface-900/30">
                                    <td colspan="10" class="p-3">
                                        <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-3 space-y-2 shadow-sm">
                                            <div class="flex items-center justify-between text-xs font-bold text-text-secondary px-2">
                                                <span>Rincian Transaksi {{ formatDateLabel(day.dateStr) }}</span>
                                                <span v-if="loadingDay[day.dateStr]" class="inline-flex items-center gap-1 text-primary-500 font-medium">
                                                    <Loader2 :size="12" class="animate-spin" /> Memuat rincian transaksi...
                                                </span>
                                                <span v-else>{{ (dayTransactions[day.dateStr] || day.items || []).length }} Transaksi</span>
                                            </div>

                                            <div v-if="loadingDay[day.dateStr]" class="py-8 flex items-center justify-center gap-2 text-xs text-text-secondary">
                                                <Loader2 :size="18" class="animate-spin text-primary-500" />
                                                <span>Memuat rincian transaksi tanggal {{ formatDateLabel(day.dateStr) }}...</span>
                                            </div>

                                            <div v-else-if="!(dayTransactions[day.dateStr] || day.items) || (dayTransactions[day.dateStr] || day.items).length === 0" class="py-6 text-center text-xs text-text-secondary">
                                                Tidak ada rincian transaksi ditemukan untuk tanggal ini.
                                            </div>

                                            <div v-else class="overflow-x-auto">
                                                <table class="w-full text-left text-[11px]">
                                                    <thead class="bg-gray-100 dark:!bg-surface-700/60 uppercase text-gray-600 dark:text-gray-300 font-bold">
                                                        <tr>
                                                            <th class="px-3 py-2">No Nota</th>
                                                            <th class="px-3 py-2">Cabang</th>
                                                            <th class="px-3 py-2">Customer/CS</th>
                                                            <th class="px-3 py-2">Kategori</th>
                                                            <th class="px-3 py-2 text-right">Jual</th>
                                                            <th class="px-3 py-2 text-right">Modal Sis</th>
                                                            <th class="px-3 py-2 text-right">Modal Aud</th>
                                                            <th class="px-3 py-2 text-right">Profit Aud</th>
                                                            <th class="px-3 py-2 text-center">Status</th>
                                                            <th class="px-3 py-2 text-center">Detail</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700/40">
                                                        <tr v-for="trx in (dayTransactions[day.dateStr] || day.items)" :key="trx.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/20 transition-colors">
                                                            <td class="px-3 py-2 font-mono font-bold">{{ trx.order_no }}</td>
                                                            <td class="px-3 py-2">{{ trx.outlet_name }}</td>
                                                            <td class="px-3 py-2">{{ trx.customer_name }}</td>
                                                            <td class="px-3 py-2">
                                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold" :class="getCategoryBadgeClass(trx.category)">
                                                                    {{ formatCategoryLabel(trx.category) }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-mono">{{ formatCurrency(trx.harga_jual) }}</td>
                                                            <td class="px-3 py-2 text-right font-mono">{{ formatCurrency(trx.default_harga_modal) }}</td>
                                                            <td class="px-3 py-2 text-right font-mono">{{ formatCurrency(trx.harga_modal ?? trx.default_harga_modal) }}</td>
                                                            <td class="px-3 py-2 text-right font-mono font-bold"
                                                                :class="(trx.profit_audit ?? trx.profit) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                                {{ formatCurrency(trx.profit_audit ?? trx.profit) }}
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <span v-if="trx.is_audited || trx.audit_score != null"
                                                                    class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                                                    Sudah Diaudit
                                                                </span>
                                                                <span v-else
                                                                    class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                                                                    Belum Audit
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-center">
                                                                <button @click="openDetailModal(trx)"
                                                                    class="p-1 rounded text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition-colors"
                                                                    title="Lihat Detail Transaksi">
                                                                    <Eye :size="13" />
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View (< md) -->
            <div class="block md:hidden space-y-3">
                <!-- Loading State Mobile -->
                <div v-if="loading" class="p-12 bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 text-center text-text-secondary">
                    <Loader2 :size="24" class="animate-spin text-primary-500 mx-auto mb-2" />
                    <span class="text-xs font-semibold">Memuat rekapitulasi profit...</span>
                </div>

                <!-- Empty State Mobile -->
                <div v-else-if="dailyAggregations.length === 0" class="p-8 bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 text-center text-text-secondary">
                    <div class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mx-auto mb-2">
                        <TrendingUp :size="24" class="text-gray-400" />
                    </div>
                    <p class="text-sm font-bold text-text-primary">Tidak Ada Data Profit</p>
                    <p class="text-xs mt-1">Tidak ditemukan transaksi pada rentang periode ini.</p>
                </div>

                <!-- Daily Cards -->
                <div v-else v-for="day in dailyAggregations" :key="`mob-${day.dateStr}`"
                    class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 p-4 shadow-sm space-y-3">
                    <!-- Day Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-text-primary">{{ formatDateLabel(day.dateStr) }}</h3>
                            <div class="text-[10px] text-text-secondary font-mono">{{ day.dateStr }}</div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap justify-end">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/20">
                                {{ day.count }} Trx
                            </span>
                            <span v-if="day.unAuditedCount > 0"
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                                {{ day.unAuditedCount }} blm audit
                            </span>
                        </div>
                    </div>

                    <!-- Day Financial Stats Box -->
                    <div class="bg-gray-50 dark:!bg-surface-900/60 rounded-xl p-3 border border-gray-100 dark:border-surface-700/60 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-text-secondary font-semibold">Total Penjualan:</span>
                            <span class="font-mono font-black text-text-primary">{{ formatCurrency(day.totalPenjualan) }}</span>
                        </div>

                        <!-- System Mode / Compare Mode -->
                        <div v-if="calcMode === 'compare' || calcMode === 'system'"
                            class="flex items-center justify-between border-t border-gray-200/60 dark:border-surface-700/60 pt-1.5">
                            <span class="text-text-secondary">Modal (Sistem):</span>
                            <span class="font-mono text-text-primary">{{ formatCurrency(day.totalModalSystem) }}</span>
                        </div>
                        <div v-if="calcMode === 'compare' || calcMode === 'system'" class="flex items-center justify-between">
                            <span class="text-text-secondary">Profit (Sistem):</span>
                            <span class="font-mono font-bold"
                                :class="day.totalProfitSystem >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ formatCurrency(day.totalProfitSystem) }}
                            </span>
                        </div>

                        <!-- Audit Mode / Compare Mode -->
                        <div v-if="calcMode === 'compare' || calcMode === 'audit'"
                            class="flex items-center justify-between border-t border-gray-200/60 dark:border-surface-700/60 pt-1.5">
                            <span class="text-text-secondary">Modal (Audit):</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ formatCurrency(day.totalModalAudit) }}</span>
                        </div>
                        <div v-if="calcMode === 'compare' || calcMode === 'audit'" class="flex items-center justify-between">
                            <span class="text-text-secondary font-bold text-emerald-700 dark:text-emerald-300">Profit (Audit):</span>
                            <span class="font-mono font-black"
                                :class="day.totalProfitAudit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ formatCurrency(day.totalProfitAudit) }}
                            </span>
                        </div>

                        <!-- Selisih if Compare Mode -->
                        <div v-if="calcMode === 'compare'"
                            class="flex items-center justify-between border-t border-gray-200/60 dark:border-surface-700/60 pt-1.5 font-bold">
                            <span class="text-text-secondary">Selisih Profit:</span>
                            <span class="font-mono"
                                :class="(day.totalProfitAudit - day.totalProfitSystem) >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-amber-600 dark:text-amber-400'">
                                {{ formatCurrency(day.totalProfitAudit - day.totalProfitSystem) }}
                            </span>
                        </div>
                    </div>

                    <!-- Expand Day Action -->
                    <div>
                        <button @click="toggleExpandDay(day.dateStr)"
                            class="w-full py-2.5 px-3 rounded-xl text-xs font-bold border transition-all flex items-center justify-center gap-1.5 min-h-[42px]"
                            :class="expandedDays.includes(day.dateStr)
                                ? 'bg-primary-600 text-white border-primary-600 shadow-sm'
                                : 'bg-gray-100 dark:!bg-surface-700 text-text-secondary hover:text-text-primary border-gray-200 dark:border-surface-600'">
                            <span>{{ expandedDays.includes(day.dateStr) ? 'Sembunyikan Rincian' : `Lihat Rincian (${day.count} Trx)` }}</span>
                            <ChevronDown :size="14" :class="{ 'rotate-180': expandedDays.includes(day.dateStr) }" />
                        </button>
                    </div>

                    <!-- Expanded Mobile Transactions -->
                    <div v-if="expandedDays.includes(day.dateStr)" class="pt-2 border-t border-gray-100 dark:border-surface-700 space-y-2">
                        <div v-if="loadingDay[day.dateStr]" class="py-6 text-center text-xs text-text-secondary flex items-center justify-center gap-2">
                            <Loader2 :size="16" class="animate-spin text-primary-500" />
                            <span>Memuat transaksi tanggal {{ formatDateLabel(day.dateStr) }}...</span>
                        </div>

                        <div v-else-if="!(dayTransactions[day.dateStr] || day.items) || (dayTransactions[day.dateStr] || day.items).length === 0"
                            class="py-4 text-center text-xs text-text-secondary">
                            Tidak ada data transaksi ditemukan untuk tanggal ini.
                        </div>

                        <div v-else class="space-y-2.5">
                            <div v-for="trx in (dayTransactions[day.dateStr] || day.items)" :key="`mob-sub-${trx.id}`"
                                class="bg-gray-50 dark:!bg-surface-900/40 rounded-xl p-3 border border-gray-200/70 dark:border-surface-700 space-y-2 text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-mono font-bold text-text-primary truncate">{{ trx.order_no }}</div>
                                        <div class="text-[10px] text-text-secondary truncate">{{ trx.outlet_name }} • {{ trx.customer_name }}</div>
                                    </div>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold shrink-0" :class="getCategoryBadgeClass(trx.category)">
                                        {{ formatCategoryLabel(trx.category) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-1.5 text-[11px] bg-white dark:!bg-surface-800 p-2 rounded-lg border border-gray-100 dark:border-surface-700">
                                    <div>
                                        <div class="text-[9px] text-text-secondary uppercase">Jual</div>
                                        <div class="font-mono font-bold text-text-primary truncate">{{ formatCurrency(trx.harga_jual) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[9px] text-text-secondary uppercase">Modal Aud</div>
                                        <div class="font-mono text-text-primary truncate">{{ formatCurrency(trx.harga_modal ?? trx.default_harga_modal) }}</div>
                                    </div>
                                    <div class="col-span-2 pt-1 border-t border-gray-100 dark:border-surface-700/60 flex items-center justify-between">
                                        <div class="text-[9px] text-text-secondary uppercase font-bold text-emerald-700 dark:text-emerald-300">Profit Aud</div>
                                        <div class="font-mono font-bold truncate"
                                            :class="(trx.profit_audit ?? trx.profit) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                            {{ formatCurrency(trx.profit_audit ?? trx.profit) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-1">
                                    <div>
                                        <span v-if="trx.is_audited || trx.audit_score != null"
                                            class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                            Sudah Diaudit
                                        </span>
                                        <span v-else
                                            class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                                            Belum Audit
                                        </span>
                                    </div>
                                    <button @click="openDetailModal(trx)"
                                        class="px-2.5 py-1.5 min-h-[38px] bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 hover:bg-primary-100 rounded-lg text-xs font-bold flex items-center gap-1 transition-all">
                                        <Eye :size="13" />
                                        <span>Rincian</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: DAFTAR TRANSAKSI DETAIL -->
        <div v-if="activeTab === 'transaction_list'">
            <!-- Desktop / Tablet Table View (>= md) -->
            <div class="hidden md:block bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 dark:!bg-surface-700/50 border-b border-gray-200 dark:border-surface-700 uppercase font-bold text-gray-600 dark:text-gray-300 text-[11px]">
                            <tr>
                                <th class="px-4 py-3.5 w-12 text-center">No</th>
                                <th class="px-4 py-3.5">Waktu</th>
                                <th class="px-4 py-3.5">No Pesanan</th>
                                <th class="px-4 py-3.5">Cabang / Toko</th>
                                <th class="px-4 py-3.5">Customer / Akun CS</th>
                                <th class="px-4 py-3.5">Kategori</th>
                                <th class="px-4 py-3.5 min-w-[200px]">Rincian Barang</th>
                                <th class="px-4 py-3.5 text-right">Penjualan</th>
                                <th v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-3.5 text-right">
                                    Modal (Sistem)
                                </th>
                                <th v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-3.5 text-right">
                                    Modal (Audit)
                                </th>
                                <th v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-3.5 text-right">
                                    Profit (Sistem)
                                </th>
                                <th v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-3.5 text-right">
                                    Profit (Audit)
                                </th>
                                <th class="px-4 py-3.5 text-center">Status Audit</th>
                                <th class="px-4 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700 text-text-primary">
                            <!-- Loading State -->
                            <tr v-if="loading">
                                <td colspan="14" class="py-16 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <Loader2 :size="24" class="animate-spin text-primary-500" />
                                        <span class="text-xs font-semibold">Memuat data transaksi...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="paginatedTransactions.length === 0">
                                <td colspan="14" class="py-16 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <div class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-1">
                                            <Receipt :size="24" class="text-gray-400" />
                                        </div>
                                        <span class="text-sm font-bold text-text-primary">Tidak Ada Transaksi</span>
                                        <span class="text-xs">Tidak ditemukan transaksi yang sesuai dengan kriteria filter.</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Transaction Rows -->
                            <tr v-else v-for="(item, index) in paginatedTransactions" :key="item.id"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors font-medium">
                                <td class="px-4 py-4 text-center text-text-secondary font-mono">
                                    {{ (currentPage - 1) * perPage + index + 1 }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="font-bold text-text-primary">{{ formatDateSimple(item.date) }}</div>
                                    <div class="text-[10px] text-text-secondary font-mono">{{ formatTimeSimple(item.date) }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-mono font-bold text-text-primary">{{ item.order_no }}</div>
                                    <button v-if="item.receipt_id || item.order_no" @click="openScreenshot(item)"
                                        class="mt-1 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-colors">
                                        Bukti Nota
                                    </button>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-text-primary">{{ item.outlet_name || '-' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-text-primary">{{ item.customer_name || '-' }}</div>
                                    <div class="text-[10px] text-text-secondary">CS: {{ item.inventory_account_name || '-' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                                        :class="getCategoryBadgeClass(item.category)">
                                        {{ formatCategoryLabel(item.category) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div v-if="item.items && item.items.length > 0" class="space-y-1">
                                        <div v-for="(detail, didx) in item.items.slice(0, 2)" :key="didx" class="text-[11px] leading-tight">
                                            <div class="font-bold text-text-primary flex items-center gap-1">
                                                <span>{{ detail.name }}</span>
                                                <span v-if="detail.distributor && detail.distributor !== '-'"
                                                    class="px-1 py-0.2 rounded text-[9px] bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/20 font-normal">
                                                    {{ detail.distributor }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-text-secondary font-mono flex items-center gap-1.5">
                                                <span v-if="detail.imei && detail.imei !== '-'">IMEI: {{ detail.imei }}</span>
                                                <span v-if="detail.storage">{{ detail.storage }}</span>
                                            </div>
                                        </div>
                                        <div v-if="item.items.length > 2" class="text-[10px] text-primary-600 dark:text-primary-400 font-bold">
                                            +{{ item.items.length - 2 }} item lainnya
                                        </div>
                                    </div>
                                    <span v-else class="text-text-secondary">-</span>
                                </td>
                                <td class="px-4 py-4 text-right font-mono font-bold">
                                    {{ formatCurrency(item.harga_jual) }}
                                </td>
                                <td v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-4 text-right font-mono">
                                    {{ formatCurrency(item.default_harga_modal) }}
                                </td>
                                <td v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-4 text-right font-mono">
                                    {{ formatCurrency(item.harga_modal ?? item.default_harga_modal) }}
                                </td>
                                <td v-if="calcMode === 'compare' || calcMode === 'system'" class="px-4 py-4 text-right font-mono font-bold"
                                    :class="item.profit_system >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    {{ formatCurrency(item.profit_system) }}
                                </td>
                                <td v-if="calcMode === 'compare' || calcMode === 'audit'" class="px-4 py-4 text-right font-mono font-bold"
                                    :class="(item.profit_audit ?? item.profit) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    {{ formatCurrency(item.profit_audit ?? item.profit) }}
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span v-if="item.is_audited || item.audit_score != null"
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                            Sudah Diaudit
                                        </span>
                                        <span v-else
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                                            Belum Dikerjakan
                                        </span>
                                        <span v-if="item.audit_score != null" class="text-[10px] text-text-secondary font-mono">
                                            Skor: {{ item.audit_score }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button @click="openDetailModal(item)"
                                        class="p-2 rounded-xl text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors"
                                        title="Rincian Lengkap">
                                        <Eye :size="16" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Transaction Cards View (< md) -->
            <div class="block md:hidden space-y-3">
                <!-- Loading State Mobile -->
                <div v-if="loading" class="p-12 bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 text-center text-text-secondary">
                    <Loader2 :size="24" class="animate-spin text-primary-500 mx-auto mb-2" />
                    <span class="text-xs font-semibold">Memuat data transaksi...</span>
                </div>

                <!-- Empty State Mobile -->
                <div v-else-if="paginatedTransactions.length === 0" class="p-8 bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 text-center text-text-secondary">
                    <div class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mx-auto mb-2">
                        <Receipt :size="24" class="text-gray-400" />
                    </div>
                    <p class="text-sm font-bold text-text-primary">Tidak Ada Transaksi</p>
                    <p class="text-xs mt-1">Tidak ditemukan transaksi yang sesuai dengan filter.</p>
                </div>

                <!-- Transaction Cards -->
                <div v-else v-for="item in paginatedTransactions" :key="`mob-trx-${item.id}`"
                    class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 p-4 shadow-sm space-y-3">
                    <!-- Top Row: Order No + Category Badge -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-mono font-black text-sm text-text-primary">{{ item.order_no }}</div>
                            <div class="text-[10px] text-text-secondary flex items-center gap-1.5 mt-0.5">
                                <span>{{ formatDateSimple(item.date) }}</span>
                                <span>•</span>
                                <span class="font-mono">{{ formatTimeSimple(item.date) }}</span>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold shrink-0"
                            :class="getCategoryBadgeClass(item.category)">
                            {{ formatCategoryLabel(item.category) }}
                        </span>
                    </div>

                    <!-- Meta: Cabang & Customer / CS -->
                    <div class="text-xs space-y-1 bg-gray-50 dark:!bg-surface-900/40 p-2.5 rounded-xl border border-gray-100 dark:border-surface-700/60">
                        <div class="flex items-center justify-between">
                            <span class="text-text-secondary">Cabang / Toko:</span>
                            <span class="font-bold text-text-primary truncate ml-2">{{ item.outlet_name || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-text-secondary">Customer:</span>
                            <span class="font-semibold text-text-primary truncate ml-2">{{ item.customer_name || '-' }}</span>
                        </div>
                        <div v-if="item.inventory_account_name" class="flex items-center justify-between text-[11px]">
                            <span class="text-text-secondary">Akun CS:</span>
                            <span class="text-text-primary truncate ml-2">{{ item.inventory_account_name }}</span>
                        </div>
                    </div>

                    <!-- Items Summary -->
                    <div v-if="item.items && item.items.length > 0" class="text-xs space-y-1">
                        <div class="text-[10px] font-bold text-text-secondary uppercase">Barang:</div>
                        <div v-for="(detail, didx) in item.items.slice(0, 2)" :key="didx"
                            class="text-xs border-l-2 border-primary-500 pl-2 py-0.5">
                            <div class="font-bold text-text-primary flex items-center gap-1.5 flex-wrap">
                                <span>{{ detail.name }}</span>
                                <span v-if="detail.distributor && detail.distributor !== '-'"
                                    class="px-1.5 py-0.2 rounded text-[9px] bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/20 font-normal">
                                    {{ detail.distributor }}
                                </span>
                            </div>
                            <div class="text-[10px] text-text-secondary font-mono">
                                <span v-if="detail.imei && detail.imei !== '-'">IMEI: {{ detail.imei }}</span>
                                <span v-if="detail.storage"> • {{ detail.storage }}</span>
                            </div>
                        </div>
                        <div v-if="item.items.length > 2" class="text-[10px] text-primary-600 dark:text-primary-400 font-bold pl-2">
                            +{{ item.items.length - 2 }} item lainnya
                        </div>
                    </div>

                    <!-- Financial Box -->
                    <div class="bg-gray-50 dark:!bg-surface-900/60 rounded-xl p-3 border border-gray-100 dark:border-surface-700/60 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-text-secondary font-semibold">Harga Jual:</span>
                            <span class="font-mono font-black text-text-primary">{{ formatCurrency(item.harga_jual) }}</span>
                        </div>

                        <!-- System Modal / Profit -->
                        <div v-if="calcMode === 'compare' || calcMode === 'system'"
                            class="flex items-center justify-between border-t border-gray-200/60 dark:border-surface-700/60 pt-1.5">
                            <span class="text-text-secondary">Modal (Sistem):</span>
                            <span class="font-mono text-text-primary">{{ formatCurrency(item.default_harga_modal) }}</span>
                        </div>
                        <div v-if="calcMode === 'compare' || calcMode === 'system'" class="flex items-center justify-between">
                            <span class="text-text-secondary">Profit (Sistem):</span>
                            <span class="font-mono font-bold"
                                :class="item.profit_system >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ formatCurrency(item.profit_system) }}
                            </span>
                        </div>

                        <!-- Audit Modal / Profit -->
                        <div v-if="calcMode === 'compare' || calcMode === 'audit'"
                            class="flex items-center justify-between border-t border-gray-200/60 dark:border-surface-700/60 pt-1.5">
                            <span class="text-text-secondary">Modal (Audit):</span>
                            <span class="font-mono font-medium text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(item.harga_modal ?? item.default_harga_modal) }}
                            </span>
                        </div>
                        <div v-if="calcMode === 'compare' || calcMode === 'audit'" class="flex items-center justify-between">
                            <span class="text-text-secondary font-bold text-emerald-700 dark:text-emerald-300">Profit (Audit):</span>
                            <span class="font-mono font-black"
                                :class="(item.profit_audit ?? item.profit) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ formatCurrency(item.profit_audit ?? item.profit) }}
                            </span>
                        </div>
                    </div>

                    <!-- Footer: Status Audit + Buttons -->
                    <div class="flex items-center justify-between pt-1 gap-2 flex-wrap">
                        <div class="flex items-center gap-1.5">
                            <span v-if="item.is_audited || item.audit_score != null"
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                Sudah Diaudit
                            </span>
                            <span v-else
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                                Belum Dikerjakan
                            </span>
                            <span v-if="item.audit_score != null" class="text-[10px] text-text-secondary font-mono">
                                ({{ item.audit_score }}%)
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button v-if="item.receipt_id || item.order_no" @click="openScreenshot(item)"
                                class="px-2.5 py-1.5 min-h-[38px] text-[11px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl hover:bg-emerald-100 transition-colors">
                                Bukti Nota
                            </button>
                            <button @click="openDetailModal(item)"
                                class="px-3 py-1.5 min-h-[38px] bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 hover:bg-primary-100 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all">
                                <Eye :size="14" />
                                <span>Rincian</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination Bar (Responsive for Mobile & Desktop) -->
            <div class="mt-3 bg-white dark:!bg-surface-800 p-3 sm:px-4 sm:py-3 rounded-2xl border border-gray-200 dark:border-surface-700 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs shadow-sm">
                <div class="text-text-secondary font-medium text-center sm:text-left w-full sm:w-auto">
                    Menampilkan {{ allFilteredSales.length ? (currentPage - 1) * perPage + 1 : 0 }} - {{ Math.min(currentPage * perPage, allFilteredSales.length) }} dari {{ allFilteredSales.length }} transaksi
                </div>

                <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-auto">
                    <select v-model="perPage"
                        class="bg-gray-50 dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-lg px-2.5 py-1.5 min-h-[38px] text-xs font-bold text-text-primary outline-none cursor-pointer">
                        <option :value="25" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">25 / hal</option>
                        <option :value="50" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">50 / hal</option>
                        <option :value="100" class="bg-white text-gray-900 dark:!bg-surface-800 dark:text-white">100 / hal</option>
                    </select>

                    <div class="flex items-center gap-1">
                        <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                            class="p-2 min-w-[38px] min-h-[38px] flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 bg-gray-50 dark:!bg-surface-700 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white disabled:opacity-30 transition-all"
                            title="Halaman Sebelumnya" aria-label="Halaman Sebelumnya">
                            <ChevronLeft :size="16" />
                        </button>
                        <span class="px-2.5 py-1 font-bold font-mono text-text-primary text-xs whitespace-nowrap">
                            {{ currentPage }} / {{ totalPages || 1 }}
                        </span>
                        <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage >= totalPages"
                            class="p-2 min-w-[38px] min-h-[38px] flex items-center justify-center rounded-lg border border-gray-200 dark:border-surface-700 bg-gray-50 dark:!bg-surface-700 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white disabled:opacity-30 transition-all"
                            title="Halaman Berikutnya" aria-label="Halaman Berikutnya">
                            <ChevronRight :size="16" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal Transaksi (Responsive Bottom-Sheet / Dialog) -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="selectedTransaction"
                    class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="selectedTransaction = null"
                    @keydown.esc="selectedTransaction = null">
                    <div class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 shadow-2xl w-full max-w-3xl max-h-[92vh] sm:max-h-[90vh] flex flex-col overflow-hidden">
                        <!-- Modal Header -->
                        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-gray-200 dark:border-surface-700 flex items-center justify-between">
                            <div class="min-w-0 pr-2">
                                <h3 class="text-sm sm:text-base font-black text-text-primary uppercase tracking-tight truncate">Detail Profit Transaksi</h3>
                                <div class="flex items-center gap-2 mt-0.5 text-xs text-text-secondary flex-wrap">
                                    <span class="font-mono font-bold">{{ selectedTransaction.order_no }}</span>
                                    <span>•</span>
                                    <span class="truncate">{{ selectedTransaction.outlet_name }}</span>
                                </div>
                            </div>
                            <button @click="selectedTransaction = null"
                                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg transition-colors min-w-[36px] min-h-[36px] flex items-center justify-center shrink-0">
                                <X :size="18" />
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-4 sm:p-6 overflow-y-auto space-y-4 sm:space-y-5 text-xs">
                            <!-- Info Cards -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 bg-gray-50 dark:!bg-surface-900/60 p-3 sm:p-4 rounded-xl border border-gray-200 dark:border-surface-700">
                                <div>
                                    <div class="text-[10px] text-text-secondary font-bold uppercase">Customer</div>
                                    <div class="font-bold text-text-primary mt-0.5 break-words">{{ selectedTransaction.customer_name || '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-text-secondary font-bold uppercase">Kategori</div>
                                    <div class="font-bold text-text-primary mt-0.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] inline-block" :class="getCategoryBadgeClass(selectedTransaction.category)">
                                            {{ formatCategoryLabel(selectedTransaction.category) }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-text-secondary font-bold uppercase">Status Audit</div>
                                    <div class="font-bold text-text-primary mt-0.5">
                                        {{ selectedTransaction.is_audited ? 'Sudah Diaudit' : 'Belum Dikerjakan' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-text-secondary font-bold uppercase">Tanggal Transaksi</div>
                                    <div class="font-bold text-text-primary mt-0.5">{{ formatDateSimple(selectedTransaction.date) }}</div>
                                </div>
                            </div>

                            <!-- Items Table (Desktop & Mobile) -->
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-text-secondary mb-2">Rincian Barang & Modal</h4>
                                
                                <!-- Desktop items table (>= sm) -->
                                <div class="hidden sm:block overflow-x-auto border border-gray-200 dark:border-surface-700 rounded-xl">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-gray-50 dark:!bg-surface-900/50 uppercase font-bold text-gray-600 dark:text-gray-300 text-[10px] border-b border-gray-200 dark:border-surface-700">
                                            <tr>
                                                <th class="px-3 py-2.5">Barang</th>
                                                <th class="px-3 py-2.5">Distributor</th>
                                                <th class="px-3 py-2.5 text-center">Qty</th>
                                                <th class="px-3 py-2.5 text-right">Harga Jual</th>
                                                <th class="px-3 py-2.5 text-right">Modal Sistem</th>
                                                <th class="px-3 py-2.5 text-right">Modal Audit</th>
                                                <th class="px-3 py-2.5 text-right">Profit Sistem</th>
                                                <th class="px-3 py-2.5 text-right">Profit Audit</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700/60 font-medium text-text-primary">
                                            <tr v-for="(detail, didx) in selectedTransaction.items" :key="didx">
                                                <td class="px-3 py-2.5">
                                                    <div class="font-bold text-text-primary">{{ detail.name }}</div>
                                                    <div class="text-[10px] text-text-secondary font-mono">
                                                        <span v-if="detail.imei && detail.imei !== '-'">IMEI: {{ detail.imei }}</span>
                                                        <span v-if="detail.brand"> • {{ detail.brand }}</span>
                                                        <span v-if="detail.condition"> • {{ detail.condition }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5 text-[11px] text-text-secondary font-semibold">
                                                    {{ detail.distributor || '-' }}
                                                </td>
                                                <td class="px-3 py-2.5 text-center font-mono">{{ detail.qty }}</td>
                                                <td class="px-3 py-2.5 text-right font-mono">{{ formatCurrency(detail.harga_jual) }}</td>
                                                <td class="px-3 py-2.5 text-right font-mono">{{ formatCurrency(detail.default_harga_modal) }}</td>
                                                <td class="px-3 py-2.5 text-right font-mono">
                                                    {{ formatCurrency(detail.harga_modal ?? detail.default_harga_modal) }}
                                                </td>
                                                <td class="px-3 py-2.5 text-right font-mono font-bold"
                                                    :class="detail.profit_system >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                    {{ formatCurrency(detail.profit_system) }}
                                                </td>
                                                <td class="px-3 py-2.5 text-right font-mono font-bold"
                                                    :class="(detail.profit_audit ?? detail.profit) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                    {{ formatCurrency(detail.profit_audit ?? detail.profit) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile items card list (< sm) -->
                                <div class="block sm:hidden space-y-2.5">
                                    <div v-for="(detail, didx) in selectedTransaction.items" :key="`mob-modal-item-${didx}`"
                                        class="bg-gray-50 dark:!bg-surface-900/50 border border-gray-200 dark:border-surface-700 rounded-xl p-3 space-y-2 text-xs">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="font-bold text-text-primary">{{ detail.name }}</div>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-gray-200 dark:!bg-surface-700">
                                                Qty: {{ detail.qty }}
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-text-secondary font-mono">
                                            <span v-if="detail.imei && detail.imei !== '-'">IMEI: {{ detail.imei }}</span>
                                            <span v-if="detail.distributor && detail.distributor !== '-'"> • Dist: {{ detail.distributor }}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-1.5 bg-white dark:!bg-surface-800 p-2 rounded-lg border border-gray-100 dark:border-surface-700 text-[11px]">
                                            <div>
                                                <span class="text-[9px] text-text-secondary uppercase block">Harga Jual</span>
                                                <span class="font-mono font-bold text-text-primary">{{ formatCurrency(detail.harga_jual) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-[9px] text-text-secondary uppercase block">Modal Audit</span>
                                                <span class="font-mono text-text-primary">{{ formatCurrency(detail.harga_modal ?? detail.default_harga_modal) }}</span>
                                            </div>
                                            <div class="col-span-2 pt-1 border-t border-gray-100 dark:border-surface-700/60 flex items-center justify-between">
                                                <span class="text-[9px] text-text-secondary uppercase font-bold text-emerald-700 dark:text-emerald-300">Profit Audit</span>
                                                <span class="font-mono font-bold"
                                                    :class="(detail.profit_audit ?? detail.profit) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                    {{ formatCurrency(detail.profit_audit ?? detail.profit) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Split Payments -->
                            <div v-if="selectedTransaction.split_payments_data && selectedTransaction.split_payments_data.length > 0">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-text-secondary mb-2">Metode Pembayaran</h4>
                                <div class="flex flex-wrap gap-2">
                                    <div v-for="(pm, pidx) in selectedTransaction.split_payments_data" :key="pidx"
                                        class="bg-gray-50 dark:!bg-surface-900/50 border border-gray-200 dark:border-surface-700 px-3 py-2 rounded-xl text-xs">
                                        <span class="font-bold text-text-primary">{{ pm.method_name }}: </span>
                                        <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ formatCurrency(pm.amount) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-4 sm:px-6 py-3 sm:py-3.5 bg-gray-50 dark:!bg-surface-900/50 border-t border-gray-200 dark:border-surface-700 flex items-center justify-between gap-3">
                            <div class="text-xs font-bold text-text-secondary truncate">
                                Auditor: {{ selectedTransaction.latest_auditor_name || '-' }}
                            </div>
                            <button @click="selectedTransaction = null"
                                class="px-4 py-2 min-h-[40px] bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-surface-700 dark:hover:bg-surface-600 dark:text-white rounded-xl text-xs font-bold transition-all shrink-0">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Screenshot Modal Component -->
        <SaleScreenshot
            :is-open="showScreenshotModal"
            :item="selectedScreenshotItem"
            @close="showScreenshotModal = false"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from '../../api/axios';
import { useAuthStore } from '../../store/auth';
import SaleScreenshot from '../../components/sales/SaleScreenshot.vue';
import {
    Calendar,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    RefreshCw,
    Download,
    AlertTriangle,
    Search,
    ShoppingCart,
    Box,
    TrendingUp,
    Clock,
    Receipt,
    PackageOpen,
    Eye,
    X,
    Loader2,
    ClipboardCheck
} from 'lucide-vue-next';

const authStore = useAuthStore();

// State
const loading = ref(false);
const exporting = ref(false);
const activePeriodMode = ref('month'); // 'month', 'last_month', 'today', 'yesterday', 'custom'
const calcMode = ref('compare'); // 'compare', 'system', 'audit'
const activeTab = ref('daily_summary'); // 'daily_summary', 'transaction_list'
const searchQuery = ref('');

// Month and Year Pickers
const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());
const availableYears = computed(() => {
    const cy = new Date().getFullYear();
    return [cy - 2, cy - 1, cy, cy + 1];
});

// Filters
const filters = ref({
    start_date: '',
    end_date: '',
    category: 'all',
    distributor_id: 'all',
    audit_status: 'all',
    branch_id: undefined,
    online_shop_id: undefined,
});

// Dropdowns lists
const distributorsList = ref([]);
const rawLocationsList = ref([]);
const selectedLocationKey = ref('all');

// Raw profit records from API
const profitRecords = ref({
    audit_stats: {
        sudah_diaudit: 0,
        belum_diaudit: 0,
        total_cancel: 0,
        total_transaksi: 0
    },
    daily_sales: {
        data: [],
        total: 0
    }
});

// Pagination for Tab 2
const currentPage = ref(1);
const perPage = ref(50);

// Expandable rows for Tab 1
const expandedDays = ref([]);
const loadingDay = ref({});
const dayTransactions = ref({});

// Modals
const selectedTransaction = ref(null);
const showScreenshotModal = ref(false);
const selectedScreenshotItem = ref(null);

// Privileges
const canAccessAudit = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'audit', 'owner', 'leader', 'analist', 'analis'].some(r => role.includes(r));
});

// Role Computations
const isGlobalRole = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'owner', 'analist', 'analis', 'developer', 'director', 'general_manager', 'regional_manager'].some(r => role.includes(r));
});

const isRestrictedRole = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['leader', 'audit'].some(r => role.includes(r)) && !isGlobalRole.value;
});

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'audit', 'owner', 'leader', 'analist', 'analis', 'admin_produk'].some(r => role.includes(r));
});

const allowedBranchIds = computed(() => {
    if (isGlobalRole.value) return null;
    const serverIds = profitRecords.value?.user_access?.branch_ids;
    if (serverIds && serverIds.length > 0) {
        return serverIds.map(Number);
    }
    const userBranchId = authStore.user?.branch_id;
    const placementBranchIds = (authStore.user?.placements || [])
        .map(p => p.branch_id)
        .filter(Boolean);
    const combined = [...new Set([userBranchId, ...placementBranchIds].filter(Boolean).map(Number))];
    return combined.length > 0 ? combined : null;
});

const allowedShopIds = computed(() => {
    if (isGlobalRole.value) return null;
    const serverIds = profitRecords.value?.user_access?.online_shop_ids;
    if (serverIds && serverIds.length > 0) {
        return serverIds.map(Number);
    }
    const userShopId = authStore.user?.online_shop_id;
    const placementShopIds = (authStore.user?.placements || [])
        .map(p => p.online_shop_id)
        .filter(Boolean);
    const combined = [...new Set([userShopId, ...placementShopIds].filter(Boolean).map(Number))];
    return combined.length > 0 ? combined : null;
});

const filteredLocations = computed(() => {
    if (isGlobalRole.value) {
        return rawLocationsList.value;
    }
    if (!allowedBranchIds.value && !allowedShopIds.value) {
        return rawLocationsList.value;
    }
    return rawLocationsList.value.filter(loc => {
        if (loc.type === 'branch') {
            return allowedBranchIds.value ? allowedBranchIds.value.includes(Number(loc.id)) : false;
        }
        if (loc.type === 'online_shop') {
            return allowedShopIds.value ? allowedShopIds.value.includes(Number(loc.id)) : false;
        }
        return false;
    });
});

watch(filteredLocations, (newLocs) => {
    if (isRestrictedRole.value && newLocs.length === 1) {
        const single = newLocs[0];
        selectedLocationKey.value = (single.type === 'branch' ? 'B:' : 'S:') + single.id;
    }
}, { immediate: true });

// Helpers for dates
const pad = (n) => String(n).padStart(2, '0');
const formatDateStr = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

const setPeriodMode = (mode) => {
    activePeriodMode.value = mode;
    const today = new Date();

    if (mode === 'month') {
        selectedMonth.value = today.getMonth() + 1;
        selectedYear.value = today.getFullYear();
        const start = new Date(selectedYear.value, selectedMonth.value - 1, 1);
        const end = new Date(selectedYear.value, selectedMonth.value, 0);
        filters.value.start_date = formatDateStr(start);
        filters.value.end_date = formatDateStr(end);
    } else if (mode === 'last_month') {
        const lastMonthDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        selectedMonth.value = lastMonthDate.getMonth() + 1;
        selectedYear.value = lastMonthDate.getFullYear();
        const start = new Date(selectedYear.value, selectedMonth.value - 1, 1);
        const end = new Date(selectedYear.value, selectedMonth.value, 0);
        filters.value.start_date = formatDateStr(start);
        filters.value.end_date = formatDateStr(end);
    } else if (mode === 'today') {
        const tStr = formatDateStr(today);
        filters.value.start_date = tStr;
        filters.value.end_date = tStr;
    } else if (mode === 'yesterday') {
        const y = new Date(today);
        y.setDate(today.getDate() - 1);
        const yStr = formatDateStr(y);
        filters.value.start_date = yStr;
        filters.value.end_date = yStr;
    }

    fetchData();
};

const onMonthOrYearChange = () => {
    const start = new Date(selectedYear.value, selectedMonth.value - 1, 1);
    const end = new Date(selectedYear.value, selectedMonth.value, 0);
    filters.value.start_date = formatDateStr(start);
    filters.value.end_date = formatDateStr(end);
    fetchData();
};

// Filter Quick Toggles
const isTradeInFilterActive = computed(() => {
    return filters.value.category === 'angkat_tukar_tambah';
});

const toggleTradeInFilter = () => {
    if (filters.value.category === 'angkat_tukar_tambah') {
        filters.value.category = 'all';
    } else {
        filters.value.category = 'angkat_tukar_tambah';
    }
    fetchData();
};

const onCategoryChange = () => {
    fetchData();
};

const togglePendingFilter = () => {
    if (filters.value.audit_status === 'belum') {
        filters.value.audit_status = 'all';
    } else {
        filters.value.audit_status = 'belum';
    }
    fetchData();
};

// Raw list of transactions
const rawTransactions = computed(() => {
    return profitRecords.value?.daily_sales?.data || [];
});

// Filtered transactions (including client-side search query)
const allFilteredSales = computed(() => {
    let list = rawTransactions.value;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(item => {
            const orderNo = (item.order_no || '').toLowerCase();
            const customer = (item.customer_name || '').toLowerCase();
            const cs = (item.inventory_account_name || '').toLowerCase();
            const outlet = (item.outlet_name || '').toLowerCase();
            const hasItemMatch = (item.items || []).some(detail =>
                (detail.name || '').toLowerCase().includes(q) ||
                (detail.imei || '').toLowerCase().includes(q) ||
                (detail.brand || '').toLowerCase().includes(q)
            );
            return orderNo.includes(q) || customer.includes(q) || cs.includes(q) || outlet.includes(q) || hasItemMatch;
        });
    }

    return list;
});

// Un-audited count from records or computed
const unAuditedCount = computed(() => {
    if (!searchQuery.value.trim() && profitRecords.value?.audit_stats?.belum_diaudit !== undefined) {
        return Number(profitRecords.value.audit_stats.belum_diaudit);
    }
    return allFilteredSales.value.filter(t => !t.is_audited && t.audit_score == null && t.category !== 'cancel_penjualan').length;
});

// Summary Totals
const summaryTotals = computed(() => {
    if (!searchQuery.value.trim() && profitRecords.value?.summary_totals) {
        const st = profitRecords.value.summary_totals;
        return {
            totalPenjualan: st.total_penjualan || 0,
            totalModalSystem: st.total_modal_system || 0,
            totalModalAudit: st.total_modal_audit || 0,
            totalProfitSystem: st.total_profit_system || 0,
            totalProfitAudit: st.total_profit_audit || 0,
            totalTransaksi: st.total_transaksi || 0,
            sudahDiaudit: st.sudah_diaudit || 0
        };
    }

    const list = allFilteredSales.value;
    let totalPenjualan = 0;
    let totalModalSystem = 0;
    let totalModalAudit = 0;
    let totalProfitSystem = 0;
    let totalProfitAudit = 0;
    let sudahDiaudit = 0;

    list.forEach(item => {
        if (item.category === 'cancel_penjualan') return;

        totalPenjualan += Number(item.harga_jual) || 0;
        totalModalSystem += Number(item.default_harga_modal) || 0;

        const effectiveAuditModal = (item.harga_modal !== null && item.harga_modal !== undefined)
            ? Number(item.harga_modal)
            : Number(item.default_harga_modal);
        totalModalAudit += effectiveAuditModal || 0;

        totalProfitSystem += Number(item.profit_system) || 0;
        const effectiveAuditProfit = (item.profit_audit !== null && item.profit_audit !== undefined)
            ? Number(item.profit_audit)
            : Number(item.profit);
        totalProfitAudit += effectiveAuditProfit || 0;

        if (item.is_audited || item.audit_score != null) {
            sudahDiaudit++;
        }
    });

    return {
        totalPenjualan,
        totalModalSystem,
        totalModalAudit,
        totalProfitSystem,
        totalProfitAudit,
        totalTransaksi: list.filter(i => i.category !== 'cancel_penjualan').length,
        sudahDiaudit
    };
});

// Daily Aggregations (Grouped by Date for Tab 1)
const dailyAggregations = computed(() => {
    // If backend provides pre-aggregated daily_recap for the entire period, use it directly!
    if (!searchQuery.value.trim() && profitRecords.value?.daily_recap && Array.isArray(profitRecords.value.daily_recap) && profitRecords.value.daily_recap.length > 0) {
        return profitRecords.value.daily_recap;
    }

    const grouped = {};

    allFilteredSales.value.forEach(item => {
        if (item.category === 'cancel_penjualan') return;

        const dateStr = item.reporting_date || (item.date ? item.date.slice(0, 10) : 'Tanpa Tanggal');
        if (!grouped[dateStr]) {
            grouped[dateStr] = {
                dateStr,
                count: 0,
                unAuditedCount: 0,
                totalPenjualan: 0,
                totalModalSystem: 0,
                totalModalAudit: 0,
                totalProfitSystem: 0,
                totalProfitAudit: 0,
                items: []
            };
        }

        grouped[dateStr].count += 1;
        if (!item.is_audited && item.audit_score == null) {
            grouped[dateStr].unAuditedCount += 1;
        }

        grouped[dateStr].totalPenjualan += Number(item.harga_jual) || 0;
        grouped[dateStr].totalModalSystem += Number(item.default_harga_modal) || 0;

        const auditModal = (item.harga_modal !== null && item.harga_modal !== undefined)
            ? Number(item.harga_modal)
            : Number(item.default_harga_modal);
        grouped[dateStr].totalModalAudit += auditModal || 0;

        grouped[dateStr].totalProfitSystem += Number(item.profit_system) || 0;
        const auditProfit = (item.profit_audit !== null && item.profit_audit !== undefined)
            ? Number(item.profit_audit)
            : Number(item.profit);
        grouped[dateStr].totalProfitAudit += auditProfit || 0;

        grouped[dateStr].items.push(item);
    });

    // Sort descending by date
    return Object.values(grouped).sort((a, b) => b.dateStr.localeCompare(a.dateStr));
});

// Paginated Transactions for Tab 2
const totalPages = computed(() => Math.ceil(allFilteredSales.value.length / perPage.value) || 1);
const paginatedTransactions = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return allFilteredSales.value.slice(start, start + perPage.value);
});

// UI Actions
const toggleExpandDay = async (dateStr) => {
    const idx = expandedDays.value.indexOf(dateStr);
    if (idx >= 0) {
        expandedDays.value.splice(idx, 1);
        return;
    }

    expandedDays.value.push(dateStr);

    if (dayTransactions.value[dateStr] && dayTransactions.value[dateStr].length > 0) {
        return;
    }

    // Check if daily_sales already contains all transactions for this date
    const fromDailySales = rawTransactions.value.filter(t => (t.reporting_date || (t.date ? t.date.slice(0, 10) : '')) === dateStr);
    const targetDayRecap = profitRecords.value?.daily_recap?.find(d => d.dateStr === dateStr);
    if (fromDailySales.length > 0 && targetDayRecap && fromDailySales.length >= targetDayRecap.count) {
        dayTransactions.value[dateStr] = fromDailySales;
        return;
    }

    // Fetch transactions on-demand for this specific single date
    loadingDay.value[dateStr] = true;
    try {
        const params = {
            start_date: dateStr,
            end_date: dateStr,
            category: filters.value.category !== 'all' ? filters.value.category : undefined,
            distributor_id: filters.value.distributor_id !== 'all' ? filters.value.distributor_id : undefined,
            audit_status: filters.value.audit_status !== 'all' ? filters.value.audit_status : undefined,
            per_page: 500
        };

        if (selectedLocationKey.value !== 'all') {
            const [type, id] = selectedLocationKey.value.split(':');
            if (type === 'B') params.branch_id = id;
            if (type === 'S') params.online_shop_id = id;
            if (type === 'W') params.warehouse_id = id;
            if (type === 'D') params.distributor_id = id;
        }

        const res = await axios.get('/audit/profit', { params });
        dayTransactions.value[dateStr] = res.data?.daily_sales?.data || [];
    } catch (e) {
        console.error('Error fetching transactions for date:', dateStr, e);
        dayTransactions.value[dateStr] = fromDailySales;
    } finally {
        loadingDay.value[dateStr] = false;
    }
};

const openDetailModal = (trx) => {
    selectedTransaction.value = trx;
};

const openScreenshot = (trx) => {
    selectedScreenshotItem.value = trx;
    showScreenshotModal.value = true;
};

// Formatting utilities
const formatCurrency = (val) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(num);
};

const formatNumber = (val) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('id-ID').format(num);
};

const formatPercent = (profit, revenue) => {
    const rev = Number(revenue) || 0;
    const prof = Number(profit) || 0;
    if (rev <= 0) return '0%';
    const pct = ((prof / rev) * 100).toFixed(1);
    return `${pct}%`;
};

const formatDateSimple = (dStr) => {
    if (!dStr) return '-';
    return new Date(dStr).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const formatTimeSimple = (dStr) => {
    if (!dStr) return '';
    return new Date(dStr).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatDateLabel = (dStr) => {
    if (!dStr || dStr === 'Tanpa Tanggal') return dStr;
    const d = new Date(dStr);
    return d.toLocaleDateString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

const formatCategoryLabel = (cat) => {
    const map = {
        'penjualan_store': 'Penjualan Store',
        'angkat_barang': 'Angkat Barang',
        'refund': 'Refund',
        'tukar_unit': 'Tukar Unit',
        'tukar_tambah': 'Tukar Tambah',
        'downgrade': 'Downgrade',
        'dp': 'DP',
        'pelunasan_dp': 'Pelunasan DP',
        'refund_dp': 'Refund DP',
        'shopee': 'Orderan Online',
        'orderan_online': 'Orderan Online',
        'cancel_penjualan': 'Dibatalkan'
    };
    return map[cat] || cat;
};

const getCategoryBadgeClass = (cat) => {
    switch (cat) {
        case 'penjualan_store':
            return 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20';
        case 'angkat_barang':
            return 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20';
        case 'tukar_tambah':
            return 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20';
        case 'tukar_unit':
            return 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-500/20';
        case 'downgrade':
            return 'bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400 border border-orange-200 dark:border-orange-500/20';
        case 'refund':
        case 'refund_dp':
        case 'cancel_penjualan':
            return 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/20';
        case 'dp':
        case 'pelunasan_dp':
            return 'bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-500/20';
        case 'orderan_online':
        case 'shopee':
            return 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20';
        default:
            return 'bg-gray-50 dark:bg-surface-700/50 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-surface-600';
    }
};

// Data Fetching
const fetchMetadata = async () => {
    try {
        const [distRes, branchRes, shopRes] = await Promise.all([
            axios.get('/distributors').catch(() => ({ data: [] })),
            axios.get('/branches').catch(() => ({ data: [] })),
            axios.get('/online-shops').catch(() => ({ data: [] }))
        ]);

        distributorsList.value = distRes.data?.data || distRes.data || [];

        const allBranches = (branchRes.data?.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data?.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        rawLocationsList.value = [...allBranches, ...allShops];

        if (isRestrictedRole.value && filteredLocations.value.length === 1) {
            const single = filteredLocations.value[0];
            selectedLocationKey.value = (single.type === 'branch' ? 'B:' : 'S:') + single.id;
        }
    } catch (e) {
        console.error('Error fetching metadata:', e);
    }
};

const fetchData = async () => {
    loading.value = true;
    dayTransactions.value = {};
    expandedDays.value = [];
    try {
        const params = {
            start_date: filters.value.start_date,
            end_date: filters.value.end_date,
            category: filters.value.category !== 'all' ? filters.value.category : undefined,
            distributor_id: filters.value.distributor_id !== 'all' ? filters.value.distributor_id : undefined,
            audit_status: filters.value.audit_status !== 'all' ? filters.value.audit_status : undefined,
            per_page: 100
        };

        if (selectedLocationKey.value !== 'all') {
            const [type, id] = selectedLocationKey.value.split(':');
            if (type === 'B') params.branch_id = id;
            if (type === 'S') params.online_shop_id = id;
            if (type === 'W') params.warehouse_id = id;
            if (type === 'D') params.distributor_id = id;
        }

        const res = await axios.get('/audit/profit', { params });
        profitRecords.value = res.data || {
            audit_stats: { sudah_diaudit: 0, belum_diaudit: 0, total_transaksi: 0 },
            daily_recap: [],
            daily_sales: { data: [] }
        };

        if (isRestrictedRole.value && filteredLocations.value.length === 1 && selectedLocationKey.value === 'all') {
            const single = filteredLocations.value[0];
            selectedLocationKey.value = (single.type === 'branch' ? 'B:' : 'S:') + single.id;
        }

        currentPage.value = 1;
    } catch (e) {
        console.error('Error fetching profit data:', e);
    } finally {
        loading.value = false;
    }
};

// Export to Excel / CSV
const exportDataExcel = () => {
    if (exporting.value) return;
    exporting.value = true;

    try {
        let headers = [];
        let rows = [];
        let filename = '';

        if (activeTab.value === 'daily_summary') {
            headers = [
                'No',
                'Tanggal',
                'Jumlah Transaksi',
                'Belum Diaudit',
                'Total Penjualan',
                'Modal Sistem',
                'Modal Audit',
                'Profit Sistem',
                'Profit Audit',
                'Selisih Profit'
            ];
            rows = dailyAggregations.value.map((day, idx) => [
                idx + 1,
                day.dateStr,
                day.count || 0,
                day.unAuditedCount || 0,
                day.totalPenjualan || 0,
                day.totalModalSystem || 0,
                day.totalModalAudit || 0,
                day.totalProfitSystem || 0,
                day.totalProfitAudit || 0,
                (day.totalProfitAudit || 0) - (day.totalProfitSystem || 0)
            ]);
            filename = `Rekap_Profit_Bulanan_${filters.value.start_date}_sd_${filters.value.end_date}.csv`;
        } else {
            headers = [
                'No',
                'Tanggal',
                'No Pesanan',
                'Cabang/Outlet',
                'Customer',
                'Akun CS',
                'Kategori',
                'Rincian Barang',
                'Harga Jual',
                'Modal Sistem',
                'Modal Audit',
                'Profit Sistem',
                'Profit Audit',
                'Status Audit'
            ];

            rows = allFilteredSales.value.map((trx, idx) => {
                const itemNames = (trx.items || []).map(i => `${i.name} (Qty:${i.qty})`).join('; ');
                return [
                    idx + 1,
                    trx.date || '',
                    `"${trx.order_no || ''}"`,
                    `"${trx.outlet_name || ''}"`,
                    `"${trx.customer_name || ''}"`,
                    `"${trx.inventory_account_name || ''}"`,
                    formatCategoryLabel(trx.category),
                    `"${itemNames}"`,
                    trx.harga_jual || 0,
                    trx.default_harga_modal || 0,
                    trx.harga_modal ?? trx.default_harga_modal ?? 0,
                    trx.profit_system || 0,
                    trx.profit_audit ?? trx.profit ?? 0,
                    trx.is_audited ? 'Sudah Diaudit' : 'Belum Dikerjakan'
                ];
            });
            filename = `Riwayat_Profit_Transaksi_${filters.value.start_date}_sd_${filters.value.end_date}.csv`;
        }

        const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' +
            [headers.join(','), ...rows.map(r => r.join(','))].join('\n');

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (e) {
        console.error('Gagal export data:', e);
    } finally {
        exporting.value = false;
    }
};

onMounted(async () => {
    setPeriodMode('month');
    await fetchMetadata();
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
