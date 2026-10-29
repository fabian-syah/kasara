<template>
    <div class="space-y-8">
        <!-- Section: Audit Profit -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-3.5 sm:p-6 rounded-xl sm:rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-4">
                <div>
                    <h2 class="text-xl font-bold text-text-primary">Audit Profit</h2>
                    <p class="text-sm text-gray-500 mt-1">Analisis profit per transaksi penjualan</p>
                </div>

                <!-- Tabs: All - HP - Non-HP -->
                <div class="flex items-center gap-1 sm:gap-1.5 p-1 bg-white dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 w-full sm:w-auto shadow-sm">
                    <button @click="activeTab = 'all'"
                        class="flex-1 sm:flex-initial px-4 py-2 text-xs font-bold rounded-lg transition-all"
                        :class="activeTab === 'all' ? 'bg-primary-600 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary hover:bg-gray-100 dark:hover:bg-surface-700'">
                        All (Semua)
                    </button>
                    <button @click="activeTab = 'hp'"
                        class="flex-1 sm:flex-initial px-4 py-2 text-xs font-bold rounded-lg transition-all"
                        :class="activeTab === 'hp' ? 'bg-primary-600 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary hover:bg-gray-100 dark:hover:bg-surface-700'">
                        HP / IMEI
                    </button>
                    <button @click="activeTab = 'non_hp'"
                        class="flex-1 sm:flex-initial px-4 py-2 text-xs font-bold rounded-lg transition-all"
                        :class="activeTab === 'non_hp' ? 'bg-primary-600 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary hover:bg-gray-100 dark:hover:bg-surface-700'">
                        Non-HP
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full mb-6">
                <!-- Period Filter -->
                <div class="relative w-full sm:w-auto min-w-[130px]">
                    <select v-model="selectedPeriod" @change="handlePeriodChange"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                        <option value="daily">Harian</option>
                        <option value="monthly">Bulanan</option>
                        <option value="all">Semua</option>
                    </select>
                    <ChevronDown :size="16"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                </div>

                <!-- Daily: Date Picker -->
                <div v-if="selectedPeriod === 'daily'" class="relative group w-full sm:w-auto">
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl hover:border-primary-500 hover:ring-2 hover:ring-primary-500/10 transition-all cursor-pointer">
                        <Calendar :size="18"
                            class="text-gray-500 dark:text-gray-400 group-hover:text-primary-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 min-w-[100px]">
                            {{ formattedDateDisplay }}
                        </span>
                    </div>
                    <input type="date" v-model="filters.start_date" @change="handleDateChange"
                        @click="$event.target.showPicker()"
                        :min="getMinDate" :max="getTodayLocal()"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                </div>

                <!-- All: All Dates Indicator -->
                <div v-else-if="selectedPeriod === 'all'"
                    class="flex items-center gap-2 px-4 py-2.5 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl shadow-sm w-full sm:w-auto">
                    <Calendar :size="18" class="text-primary-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Semua Tanggal
                    </span>
                </div>

                <!-- Monthly: Month & Year Selectors -->
                <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2 w-full sm:w-auto flex-wrap sm:flex-nowrap">
                    <div class="relative flex-1 sm:flex-initial min-w-[130px]">
                        <select v-model="selectedMonth" @change="handleMonthChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option v-for="m in restrictedMonths" :key="m.value" :value="m.value">{{ m.name }}</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                    <div class="relative min-w-[100px]">
                        <select v-model="selectedYear" @change="handleMonthChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>

                <!-- Branch Filter -->
                <div v-if="canFilterBranch && locations.length > 1" class="relative w-full sm:w-auto min-w-[160px] sm:min-w-[200px]">
                    <select v-model="selectedLocationKey" @change="fetchData()"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                        <option v-if="isAlwaysGlobal" value="all">Semua Cabang/Toko</option>
                        <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                            :value="`${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`">
                            {{ loc.type === 'branch' ? '[Cabang]' : loc.type === 'online_shop' ? '[Toko]' : loc.type === 'warehouse' ? '[Gudang]' : '[Distributor]' }} {{ loc.name }}
                        </option>
                    </select>
                    <ChevronDown :size="16"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                </div>
                <!-- Single Branch Display -->
                <div v-else-if="canFilterBranch && locations.length === 1"
                    class="px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-100 dark:border-surface-700 rounded-xl flex items-center gap-2 w-full sm:w-auto">
                    <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                    <span class="text-sm font-bold text-text-primary">{{ locations[0].name }}</span>
                </div>

                <!-- Category Filter (Removed: tukar_unit, refund, refund_dp, angkat_barang) -->
                <div class="relative w-full sm:w-auto min-w-[140px] sm:min-w-[160px]">
                    <select v-model="filters.category" @change="fetchData"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                        <option value="all">Semua Kategori</option>
                        <option value="penjualan_store">Penjualan Store</option>
                        <option value="penjualan_online_toko">Penjualan Online Toko</option>
                        <option value="orderan_online">Orderan Online</option>
                        <option value="tukar_tambah">Tukar Tambah</option>
                        <option value="dp">DP (Down Payment)</option>
                        <option value="pelunasan_dp">Pelunasan DP</option>
                    </select>
                    <ChevronDown :size="16"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                </div>

                <!-- Filter Sumber Masuk Barang (Multiple Choice) -->
                <div class="relative w-full sm:w-auto min-w-[160px] sm:min-w-[210px]" ref="sourceDropdownRef">
                    <button type="button" @click="toggleSourceDropdown"
                        class="w-full flex items-center justify-between bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 text-sm font-medium text-left focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer shadow-sm">
                        <span class="truncate pr-2 text-text-primary font-medium">
                            {{ sourceFilterLabel }}
                        </span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span v-if="selectedSources.length > 0 && selectedSources.length < sourceOptions.length"
                                class="px-1.5 py-0.5 rounded-md bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 text-[10px] font-bold">
                                {{ selectedSources.length }}
                            </span>
                            <ChevronDown :size="16" class="text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': isSourceDropdownOpen }" />
                        </div>
                    </button>

                    <!-- Dropdown Menu -->
                    <div v-if="isSourceDropdownOpen"
                        class="absolute left-0 mt-2 w-64 max-h-80 overflow-y-auto bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-2xl shadow-xl z-50 p-2 space-y-1">
                        
                        <!-- Option: Semua Sumber Masuk -->
                        <button type="button" @click="toggleAllSources"
                            class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold rounded-xl transition-colors"
                            :class="isAllSourcesSelected ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400' : 'hover:bg-gray-50 dark:hover:bg-surface-700 text-text-primary'">
                            <span>Semua Sumber Masuk</span>
                            <Check v-if="isAllSourcesSelected" :size="14" class="text-primary-600 dark:text-primary-400" />
                        </button>

                        <div class="border-t border-gray-100 dark:border-surface-700 my-1"></div>

                        <!-- Individual options with checkboxes -->
                        <label v-for="opt in sourceOptions" :key="opt.key"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-surface-700 text-text-primary select-none">
                            <input type="checkbox"
                                :checked="selectedSources.includes(opt.key)"
                                @change="toggleSource(opt.key)"
                                class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-surface-600 dark:bg-surface-700 cursor-pointer" />
                            <span class="flex-1">{{ opt.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Filter Status Audit -->
                <div class="relative w-full sm:w-auto min-w-[130px] sm:min-w-[150px]">
                    <select v-model="filters.audit_status" @change="fetchData"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                        <option value="all">Semua Status Audit</option>
                        <option value="sudah">Sudah Diaudit</option>
                        <option value="belum">Belum Diaudit</option>
                    </select>
                    <ChevronDown :size="16"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                </div>

                <!-- Riwayat Profit Link -->
                <router-link to="/reports/profit-history"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-500/20 border border-primary-200 dark:border-primary-500/30 transition-all shadow-sm w-full sm:w-auto">
                    <TrendingUp :size="18" />
                    <span>Riwayat Profit</span>
                </router-link>

                <!-- Export Button -->
                <button @click="exportExcel" :disabled="exporting"
                    class="flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg hover:transform hover:-translate-y-0.5 transition-all disabled:opacity-50 w-full sm:w-auto"
                    :style="{ backgroundColor: '#10b981', color: '#ffffff' }">
                    <Download :size="18" :class="{ 'animate-bounce': exporting }" />
                    <span>{{ exporting ? 'Exporting...' : 'Export' }}</span>
                </button>
            </div>

            <!-- Summary Cards (Current Page & Filter) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 sm:gap-4 mb-6" v-if="displayedSales && displayedSales.length > 0">
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-3 sm:p-4">
                    <p class="text-[10px] sm:text-xs font-semibold text-text-secondary uppercase mb-1 truncate">Total Transaksi</p>
                    <p class="text-sm sm:text-lg font-bold text-text-primary">{{ summaryStats.totalTransaksi }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-3 sm:p-4">
                    <p class="text-[10px] sm:text-xs font-semibold text-text-secondary uppercase mb-1 truncate">Belum Diaudit</p>
                    <p class="text-sm sm:text-lg font-bold text-amber-500">{{ summaryStats.belumDiaudit }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-3 sm:p-4">
                    <p class="text-[10px] sm:text-xs font-semibold text-text-secondary uppercase mb-1 truncate">Sudah Diaudit</p>
                    <p class="text-sm sm:text-lg font-bold text-emerald-500">{{ summaryStats.sudahDiaudit }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-3 sm:p-4">
                    <p class="text-[10px] sm:text-xs font-semibold text-text-secondary uppercase mb-1 truncate">Harga Jual</p>
                    <p class="text-sm sm:text-lg font-bold text-text-primary truncate">{{ formatCurrency(totalHargaJual) }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-3 sm:p-4">
                    <p class="text-[10px] sm:text-xs font-semibold text-text-secondary uppercase mb-1 truncate">Harga Modal</p>
                    <p class="text-sm sm:text-lg font-bold text-text-primary truncate">{{ formatCurrency(totalHargaModal) }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-3 sm:p-4">
                    <p class="text-[10px] sm:text-xs font-semibold text-text-secondary uppercase mb-1 truncate">Profit</p>
                    <p class="text-sm sm:text-lg font-bold truncate"
                        :class="totalProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                        {{ formatCurrency(totalProfit) }}
                    </p>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-xl sm:rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <!-- Mobile Horizontal Scroll Hint -->
                <div class="xl:hidden px-3.5 py-2 bg-gray-50/80 dark:!bg-surface-700/50 border-b border-gray-100 dark:border-surface-700 flex items-center justify-between text-[11px] text-text-secondary">
                    <span class="flex items-center gap-1.5 font-medium">
                        <span>👉</span> Geser tabel ke kanan untuk melihat rincian barang, sumber, modal & aksi
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-text-secondary uppercase bg-gray-50/50 dark:!bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-4 py-4">No</th>
                                <th class="px-4 py-4">Waktu</th>
                                <th class="px-4 py-4">No Pesanan</th>
                                <th class="px-4 py-4">Cabang</th>
                                <th class="px-4 py-4">Nama</th>
                                <th class="px-4 py-4">Kategori</th>
                                <th colspan="4"
                                    class="p-0 border-b border-gray-200 dark:border-surface-700 bg-gray-50/50 dark:!bg-surface-700/50">
                                    <div class="grid grid-cols-[70px_85px_1fr_130px_95px_265px_100px] w-full min-w-[960px]">
                                        <div class="px-3 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Tipe</div>
                                        <div class="px-3 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Brand</div>
                                        <div class="px-3 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Rincian Barang</div>
                                        <div class="px-3 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Sumber</div>
                                        <div class="px-3 py-4 text-right font-semibold text-text-secondary uppercase">
                                            Harga Jual</div>
                                        <div class="px-3 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Harga Modal</div>
                                        <div class="px-3 py-4 text-right font-semibold text-text-secondary uppercase">
                                            Profit</div>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center">#</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading">
                                <td colspan="11" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center text-text-secondary">
                                        <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                        <span class="text-sm font-medium">Memuat data profit...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="!displayedSales || displayedSales.length === 0">
                                <td colspan="11" class="px-6 py-12 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                            <TrendingUp class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <span class="font-medium text-text-primary">Tidak ada data profit</span>
                                        <span class="text-xs mt-1">Belum ada transaksi pada periode atau filter ini</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in displayedSales" :key="index"
                                class="transition-colors group text-text-primary"
                                :class="[
                                    isRowAudited(item) 
                                        ? 'bg-emerald-50/70 hover:bg-emerald-100/70 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 border-l-4 border-l-emerald-500' 
                                        : 'hover:bg-gray-50 dark:hover:bg-surface-700/30'
                                ]">
                                <td class="px-4 py-4 text-text-secondary font-medium">{{ (profitRecords.daily_sales.current_page - 1) *
                                    profitRecords.daily_sales.per_page + index + 1 }}</td>
                                <td class="px-4 py-4 font-medium text-text-primary text-xs whitespace-nowrap">
                                    {{ formatDate(item.date) }}</td>
                                <td class="px-4 py-4 text-text-primary font-medium text-xs">
                                    <div>{{ item.order_no }}</div>
                                    <!-- Audit Status Indicator -->
                                    <div v-if="isRowAudited(item)" class="mt-1 flex flex-col gap-0.5 text-[10px]">
                                        <span class="inline-flex items-center gap-1 font-bold text-emerald-700 dark:text-emerald-400">
                                            <span>✓</span> Sudah Diaudit
                                        </span>
                                        <span v-if="item.audited_at" class="text-gray-500 dark:text-gray-400 font-mono text-[9px]">
                                            {{ formatDate(item.audited_at) }}
                                        </span>
                                        <span v-if="item.auditor_name || item.latest_auditor_name" class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-500/20 text-emerald-800 dark:text-emerald-300 font-bold text-[10px] border border-emerald-200 dark:border-emerald-500/30 truncate max-w-[160px]" :title="'Auditor: ' + (item.auditor_name || item.latest_auditor_name)">
                                            👤 {{ item.auditor_name || item.latest_auditor_name }}
                                        </span>
                                    </div>
                                    <div v-else class="mt-1 flex flex-col gap-0.5 text-[10px]">
                                        <span class="inline-flex px-1.5 py-0.5 text-[10px] font-semibold rounded bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20 w-fit">
                                            Belum Diaudit
                                        </span>
                                        <span v-if="item.auditor_name || item.latest_auditor_name" class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-surface-700 text-gray-700 dark:text-gray-300 font-medium text-[10px] border border-gray-200 dark:border-surface-600 truncate max-w-[160px]" :title="'Auditor: ' + (item.auditor_name || item.latest_auditor_name)">
                                            👤 {{ item.auditor_name || item.latest_auditor_name }}
                                        </span>
                                    </div>
                                    <!-- Saved Audit Note Badge -->
                                    <div v-if="item.audit_notes" class="mt-1.5 text-[10px] text-amber-700 dark:text-amber-400 bg-amber-50/80 dark:bg-amber-500/10 border border-amber-200/80 dark:border-amber-500/20 rounded px-1.5 py-0.5 flex items-start gap-1 max-w-[160px] break-words" :title="'Catatan Audit: ' + item.audit_notes">
                                        <FileText :size="11" class="mt-0.5 shrink-0 text-amber-600" />
                                        <span class="truncate">Catatan: {{ item.audit_notes }}</span>
                                    </div>
                                    <button @click="openScreenshot(item)" class="mt-1.5 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/20 rounded-md transition-colors">Screenshot Nota</button>
                                </td>
                                <td class="px-4 py-4 text-xs font-semibold text-text-secondary">{{ item.outlet_name || '-' }}</td>
                                <td class="px-4 py-4 font-medium text-xs">{{ item.customer_name }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col gap-1.5 items-start">
                                        <span
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg border"
                                            :class="getCategoryBadgeClass(item.category)">
                                            {{ formatCategoryLabel(item.category) }}
                                        </span>
                                        <!-- Keterangan Nominal DP Sebelumnya untuk Pelunasan DP -->
                                        <div v-if="item.category === 'pelunasan_dp'" class="flex flex-col text-[10px] leading-tight bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-700 dark:text-blue-300 rounded px-1.5 py-0.5 w-fit">
                                            <span class="font-bold">DP Awal: {{ formatCurrency(item.previous_dp_amount || 0) }}</span>
                                            <span v-if="item.parent_dp_receipt_id" class="text-[9px] text-gray-500 dark:text-gray-400 font-mono mt-0.5">Nota: {{ item.parent_dp_receipt_id }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td colspan="4" class="p-0 align-top">
                                    <div class="flex flex-col w-full h-full min-w-[960px]">
                                        <template v-if="getDisplayItems(item) && getDisplayItems(item).length > 0">
                                            <div v-for="(detail, idx) in getDisplayItems(item)" :key="idx"
                                                class="grid grid-cols-[70px_85px_1fr_130px_95px_265px_100px] border-b border-gray-100 dark:!border-surface-700 last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                                <div
                                                    class="px-3 py-4 font-medium text-xs text-text-primary border-r border-gray-100 dark:!border-surface-700 flex flex-col items-start gap-1">
                                                    <span>{{ detail.type || item.type }}</span>
                                                    <span v-if="detail.category" class="px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-400 text-[10px] font-black uppercase tracking-tighter border border-blue-500/20">
                                                        {{ detail.category }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="px-3 py-4 text-xs font-semibold text-text-secondary border-r border-gray-100 dark:!border-surface-700 flex items-start break-words whitespace-pre-wrap">
                                                    {{ detail.brand || item.brand_names }}</div>
                                                <div
                                                    class="px-3 py-4 text-xs font-medium text-text-secondary flex flex-col justify-center border-r border-gray-100 dark:!border-surface-700">
                                                    <div class="flex justify-between items-start gap-3 w-full">
                                                        <div class="whitespace-normal flex-1 leading-relaxed">
                                                            <div>{{ detail.name }}</div>
                                                            <div v-if="detail.bundle_name"
                                                                class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 font-bold text-[9px] border border-purple-200 dark:border-purple-800/40">
                                                                <span>📦 {{ detail.bundle_name }}</span>
                                                            </div>
                                                            <div v-if="detail.storage"
                                                                class="mt-0.5 text-[10px] text-gray-500">{{
                                                                    detail.storage }}</div>
                                                            <div v-if="detail.imei && detail.imei !== '-'"
                                                                class="mt-0.5 text-xs text-blue-500 font-mono">IMEI: {{
                                                                    detail.imei }}</div>
                                                            <span v-if="detail.condition"
                                                                class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                                                :class="detail.condition === 'new' ? 'bg-emerald-500/10 text-emerald-500' : detail.condition === 'ex_ibox' ? 'bg-purple-500/10 text-purple-500' : 'bg-amber-500/10 text-amber-500'">{{
                                                                    detail.condition === 'new' ? 'Baru' : detail.condition
                                                                        === 'ex_ibox' ? 'Ex iBox' : 'Second' }}</span>
                                                        </div>
                                                        <div
                                                            class="bg-gray-100 dark:!bg-surface-700 px-2 py-0.5 rounded text-xs font-bold text-text-primary whitespace-nowrap mt-0.5">
                                                            {{ detail.qty }}</div>
                                                    </div>
                                                </div>
                                                <!-- Sumber Barang -->
                                                <div class="px-3 py-4 text-xs font-medium text-text-secondary border-r border-gray-100 dark:!border-surface-700 flex flex-col justify-center items-start">
                                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold border"
                                                        :class="getSourceBadgeClass(detail.source_type)">
                                                        {{ detail.source_label || 'In Store' }}
                                                    </span>
                                                    <span v-if="detail.distributor && detail.distributor !== '-'" class="mt-1 text-[10px] text-gray-400 truncate max-w-[120px]" :title="detail.distributor">
                                                        {{ detail.distributor }}
                                                    </span>
                                                </div>
                                                <!-- Harga Jual -->
                                                <div
                                                    class="px-3 py-4 text-text-primary font-mono text-xs font-semibold whitespace-nowrap text-right flex flex-col justify-center items-end border-r border-gray-100 dark:!border-surface-700">
                                                    <span>{{ formatCurrency(detail.harga_jual || 0) }}</span>
                                                    <span v-if="item.category === 'pelunasan_dp' && item.previous_dp_amount" class="text-[10px] text-blue-500 font-medium font-sans">
                                                        (DP: {{ formatCurrency(item.previous_dp_amount) }})
                                                    </span>
                                                </div>
                                                <!-- Harga Modal Dropdown & Input -->
                                                <div
                                                    class="px-3 py-2 flex flex-col justify-center gap-1.5 border-r border-gray-100 dark:!border-surface-700 group/modal">
                                                    <!-- Dropdown Pilihan Hitung Modal -->
                                                    <select :value="getDetailPreset(item.id, detail.id)"
                                                        @change="handlePresetChange(item, detail, $event.target.value)"
                                                        :disabled="isLeader"
                                                        class="w-full text-[11px] font-medium py-1 px-2 rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-700 text-text-primary focus:ring-1 focus:ring-primary-500/30 transition-all cursor-pointer truncate">
                                                        <option value="pct_2">{{ getPresetOptionLabel(item, detail, 'pct_2') }}</option>
                                                        <option value="pct_5">{{ getPresetOptionLabel(item, detail, 'pct_5') }}</option>
                                                        <option value="pct_10">{{ getPresetOptionLabel(item, detail, 'pct_10') }}</option>
                                                        <option v-if="isItemNonHp(detail)" value="pct_20_nonhp">{{ getPresetOptionLabel(item, detail, 'pct_20_nonhp') }}</option>
                                                        <option v-if="isItemNonHp(detail)" value="pct_30_nonhp">{{ getPresetOptionLabel(item, detail, 'pct_30_nonhp') }}</option>
                                                        <option v-if="isItemIphoneSecond(detail)" value="range_iphone">{{ getPresetOptionLabel(item, detail, 'range_iphone') }}</option>
                                                        <option value="admin_harga">{{ getPresetOptionLabel(item, detail, 'admin_harga') }}</option>
                                                        <option value="default">{{ getPresetOptionLabel(item, detail, 'default') }}</option>
                                                        <option value="manual">Input Manual</option>
                                                    </select>

                                                    <div class="relative w-full">
                                                        <span
                                                            class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-mono pointer-events-none">Rp</span>
                                                        <input type="text"
                                                            :value="formatModalDisplay(item.id, detail.id)"
                                                            @input="onModalInput($event, item, detail)"
                                                            @focus="onModalFocus($event, item, detail)"
                                                            @blur="onModalBlur($event, item, detail)"
                                                            :placeholder="detail.has_saved_modal ? formatNumber(detail.harga_modal) : '0'"
                                                            class="w-full pl-8 pr-2.5 py-1 text-xs font-mono rounded-lg border transition-all
                                                                bg-white dark:!bg-surface-700
                                                                focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                                            :class="detail.has_saved_modal
                                                                ? 'border-emerald-400 dark:border-emerald-500/40 text-emerald-700 dark:text-emerald-400 font-bold bg-emerald-50/30'
                                                                : 'border-gray-200 dark:border-surface-600 text-text-primary'"
                                                            :disabled="isLeader" @keyup.enter="saveHargaModal(item)" />
                                                    </div>
                                                </div>
                                                <!-- Profit -->
                                                <div class="px-3 py-4 font-mono text-xs font-bold whitespace-nowrap text-right flex items-center justify-end"
                                                    :class="getEffectiveProfit(item, detail) > 0 ? 'text-emerald-600 dark:text-emerald-400' : getEffectiveProfit(item, detail) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400'">
                                                    {{ formatCurrency(getEffectiveProfit(item, detail)) }}
                                                </div>
                                            </div>

                                            <div v-if="getDisplayItems(item) && getDisplayItems(item).length > 1"
                                                class="px-4 py-3 border-t border-gray-100 dark:border-surface-700 text-xs text-text-secondary flex justify-between bg-gray-50/50 dark:!bg-surface-800/50">
                                                <div>
                                                    <span>Total Pesanan: <span
                                                            class="font-bold text-text-primary ml-1">{{ getDisplayItems(item).length
                                                            }}</span></span>
                                                </div>
                                                <div class="flex items-center gap-4">
                                                    <span class="font-mono text-[10px] text-gray-500">Jual: {{
                                                        formatCurrency(isRowAudited(item) ? getItemDisplayJual(item) : 0) }}</span>
                                                    <span class="font-mono text-[10px] text-gray-500">Modal: {{
                                                        formatCurrency(isRowAudited(item) ? getItemDisplayModal(item) : 0)
                                                    }}</span>
                                                    <span class="font-bold font-mono text-[11px]"
                                                        :class="getItemDisplayProfit(item) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                        Profit: {{ formatCurrency(isRowAudited(item) ? getItemDisplayProfit(item) : 0) }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="p-4 text-center text-sm text-gray-500">
                                                Tidak ada barang yang sesuai filter tab ini
                                            </div>
                                        </template>
                                    </div>
                                    <div
                                        class="flex items-center justify-between p-2.5 bg-gray-50/30 dark:!bg-surface-800/30 border-t border-gray-100 dark:!border-surface-700 w-full min-w-[960px] gap-3">
                                        <div class="text-[11px] text-text-secondary pl-2 shrink-0">
                                            <span v-if="isRowAudited(item)" class="text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                                                <span>✓</span> Data modal transaksi ini telah tersimpan
                                                <strong v-if="item.auditor_name || item.latest_auditor_name" class="ml-1 text-emerald-800 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-500/20 px-1.5 py-0.5 rounded text-[10px]">
                                                    (Auditor: {{ item.auditor_name || item.latest_auditor_name }})
                                                </strong>
                                            </span>
                                            <span v-else class="text-gray-400 italic">
                                                Belum diaudit — pilih preset atau input modal lalu simpan
                                            </span>
                                        </div>

                                        <!-- Catatan Audit Profit Input -->
                                        <div class="flex items-center gap-2 flex-1 max-w-md">
                                            <div class="relative w-full">
                                                <FileText :size="13" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                                                <input type="text"
                                                    v-model="profitNotes[item.id]"
                                                    placeholder="Tambah catatan audit profit..."
                                                    @keyup.enter="saveHargaModal(item)"
                                                    :disabled="isLeader"
                                                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-700 text-text-primary focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all placeholder:text-gray-400 placeholder:italic" />
                                            </div>
                                        </div>

                                        <button v-if="!isLeader" @click="saveHargaModal(item)" :disabled="savingModalId === item.id"
                                            class="px-3.5 py-1.5 flex items-center gap-1.5 rounded-lg text-xs font-bold bg-white dark:!bg-surface-700 border transition-all shadow-sm shrink-0"
                                            :class="isRowAudited(item) ? 'text-emerald-700 border-emerald-300 bg-emerald-50/40 hover:bg-emerald-100' : 'text-gray-700 dark:text-gray-300 border-gray-200 dark:border-surface-600 hover:text-emerald-600 hover:border-emerald-300 hover:bg-emerald-50'"
                                            title="Simpan Harga Modal dan Catatan Audit">
                                            <Save v-if="savingModalId !== item.id" :size="14" />
                                            <Loader2 v-else :size="14" class="animate-spin" />
                                            <span>{{ isRowAudited(item) ? 'Perbarui Modal' : 'Simpan Rincian Modal' }}</span>
                                        </button>
                                    </div>
                                </td>
                                <!-- Actions -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2 transition-opacity">
                                        <!-- Bukti Buttons -->
                                        <div v-if="item.proof_images && item.proof_images.length > 0" class="flex flex-wrap gap-1.5 max-w-[200px]">
                                            <button v-for="(img, imgIdx) in item.proof_images" :key="imgIdx"
                                                @click="viewProof(img)"
                                                class="flex items-center gap-1.5 px-2.5 py-1.5 text-[9px] font-black uppercase tracking-tighter text-primary-600 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 rounded-lg transition-all border border-primary-100 dark:border-primary-500/20 whitespace-nowrap"
                                                :title="'Lihat ' + (item.proof_images.length === 2 ? (imgIdx === 0 ? 'Foto Unit' : 'Foto Customer') : 'Foto #' + (imgIdx + 1))">
                                                <Image :size="12" stroke-width="3" />
                                                <span>{{ item.proof_images.length === 2 ? (imgIdx === 0 ? 'Unit' : 'Cust') : (item.proof_images.length === 1 ? 'Bukti' : '#' + (imgIdx + 1)) }}</span>
                                            </button>
                                        </div>
                                        <button v-else-if="item.proof_image" @click="viewProof(item.proof_image)"
                                            class="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-primary-600 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 rounded-lg transition-all border border-primary-100 dark:border-primary-500/20"
                                            title="Lihat Foto Bukti">
                                            <Image :size="14" stroke-width="2.5" />
                                            <span>Lihat Bukti</span>
                                        </button>

                                        <button v-if="item.payment_proof_image" @click="viewProof(item.payment_proof_image)"
                                            class="flex items-center gap-1.5 px-2.5 py-1.5 text-[9px] font-black uppercase tracking-tighter text-amber-600 bg-amber-50 dark:bg-amber-500/10 hover:bg-amber-100 dark:hover:bg-amber-500/20 rounded-lg transition-all border border-amber-100 dark:border-amber-500/20 whitespace-nowrap"
                                            title="Lihat Foto Bukti Pembayaran/Transfer">
                                            <Wallet :size="12" stroke-width="3" />
                                            <span>Bayar</span>
                                        </button>

                                        <button @click="openReceipt(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                            title="Lihat Nota">
                                            <Eye :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="profitRecords.daily_sales.total > 0"
                    class="px-4 sm:px-6 py-3.5 sm:py-4 border-t border-gray-100 dark:border-surface-700 flex flex-col sm:flex-row justify-between items-center gap-3 bg-gray-50/50 dark:bg-surface-700/30">
                    <span class="text-xs text-text-secondary font-medium text-center sm:text-left">
                        Menampilkan {{ (profitRecords.daily_sales.current_page - 1) * profitRecords.daily_sales.per_page +
                            1 }} -
                        {{ Math.min(profitRecords.daily_sales.current_page * profitRecords.daily_sales.per_page,
                            profitRecords.daily_sales.total) }}
                        dari {{ profitRecords.daily_sales.total }} data
                    </span>
                    <div class="flex gap-2">
                        <button @click="fetchData(profitRecords.daily_sales.current_page - 1)"
                            :disabled="profitRecords.daily_sales.current_page === 1 || loading"
                            class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                            <ChevronLeft :size="18" />
                        </button>
                        <div class="flex items-center px-3 text-sm font-bold text-text-primary">
                            {{ profitRecords.daily_sales.current_page }} / {{ profitRecords.daily_sales.last_page }}
                        </div>
                        <button @click="fetchData(profitRecords.daily_sales.current_page + 1)"
                            :disabled="profitRecords.daily_sales.current_page === profitRecords.daily_sales.last_page || loading"
                            class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                            <ChevronRight :size="18" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal (no edit icon for profit - separate checklist button) -->
    <ReceiptModal :isOpen="showReceiptModal" :transaction="selectedTransaction" @close="showReceiptModal = false" />


    <!-- Proof Photo Modal (HD Gallery) -->
    <div v-if="showProofModal"
        class="fixed inset-0 z-[99999] flex items-center justify-center p-2 sm:p-4 sm:px-10 bg-black/90 backdrop-blur-md">
        <div class="relative w-full max-w-3xl h-full flex flex-col items-center justify-start py-6 sm:py-10">
            <!-- Close Button -->
            <button @click="showProofModal = false"
                class="absolute top-1 right-1 sm:top-4 sm:right-0 p-2 sm:p-3 bg-white/10 hover:bg-white/20 text-white rounded-full transition-all z-[110] backdrop-blur-md border border-white/20 active:scale-95"
                title="Tutup (ESC)">
                <X :size="24" stroke-width="3" />
            </button>

            <!-- Images Container (HD Grid/Gallery) -->
            <div class="w-full flex-1 overflow-y-auto custom-scrollbar p-1 sm:p-2">
                <div class="w-full max-w-3xl mx-auto flex flex-col gap-6 items-center">
                    <div v-for="(imgUrl, index) in currentProofImages" :key="index"
                        class="w-full bg-white dark:bg-surface-800 rounded-2xl sm:rounded-3xl overflow-hidden shadow-[0_20px_40px_-10px_rgba(0,0,0,0.5)] border border-white/10 group">

                        <!-- HD Image Wrapper -->
                        <div class="relative overflow-hidden bg-black flex items-center justify-center w-full">
                            <img :src="imgUrl" :alt="'Foto Bukti ' + (index + 1)"
                                class="w-full max-h-[50vh] sm:max-h-[60vh] object-contain"
                                style="image-rendering: -webkit-optimize-contrast; filter: contrast(1.05) brightness(1.02) saturate(1.1);" />

                            <!-- HD Badge -->
                            <div
                                class="absolute top-4 left-4 px-3 py-1 bg-black/40 backdrop-blur-md rounded-full border border-white/20 text-[10px] font-black text-white uppercase tracking-widest flex items-center gap-1.5">
                                <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div>
                                HD Processing Active
                            </div>
                        </div>

                        <!-- Image Info Overlay (Glassmorphism) -->
                        <div
                            class="p-6 bg-white/90 dark:bg-surface-800/90 backdrop-blur-xl border-t border-gray-100 dark:border-surface-700 flex flex-col gap-4">
                            <div class="flex justify-between items-start">
                                <div class="flex flex-col">
                                    <span
                                        class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-600 mb-1">
                                        FOTO #{{ index + 1 }}
                                        <span v-if="index === 0 && currentProofImages.length > 1"
                                            class="ml-2 text-gray-400">— UNIT</span>
                                        <span v-else-if="index === 1" class="ml-2 text-gray-400">— CUSTOMER</span>
                                    </span>
                                    <h4 class="text-lg font-black text-text-primary leading-tight">Bukti Transaksi
                                    </h4>
                                </div>
                                <div class="flex gap-2">
                                    <a :href="imgUrl" target="_blank"
                                        class="p-3 bg-gray-100 dark:bg-surface-700 text-gray-600 dark:text-gray-300 rounded-2xl hover:bg-gray-200 dark:hover:bg-surface-600 transition-all active:scale-95"
                                        title="Buka Ukuran Penuh">
                                        <TrendingUp :size="20" />
                                    </a>
                                    <button @click="downloadImage(imgUrl)"
                                        class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-primary-600 text-white rounded-2xl hover:bg-primary-700 transition-all text-xs font-black uppercase tracking-widest shadow-lg shadow-primary-500/30 active:scale-95">
                                        <Download :size="18" />
                                        Download
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Screenshot Modal -->
    <SaleScreenshot 
        :is-open="showScreenshotModal" 
        :sale="selectedSaleForScreenshot" 
        @close="showScreenshotModal = false" 
    />
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch, reactive } from 'vue'
import { useEscapeKey } from '../../composables/useEscapeKey'
import { Loader2, Eye, FileText, ChevronDown, Calendar, TrendingUp, Save, Pencil, Download, Image, Wallet, X, Check } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'
import { useToast } from '../../composables/useToast'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'
import SaleScreenshot from '../../components/sales/SaleScreenshot.vue'

const toast = useToast()
const showScreenshotModal = ref(false)
const selectedSaleForScreenshot = ref(null)

const openScreenshot = (item) => {
    selectedSaleForScreenshot.value = item;
    showScreenshotModal.value = true;
}

const authStore = useAuthStore()
const isLeader = computed(() => (authStore.userRole || '').toLowerCase() === 'leader')

const loading = ref(false)
const exporting = ref(false)
const selectedPeriod = ref('daily')
const activeTab = ref('all') // 'all', 'hp', 'non_hp'

// Receipt Modal State
const showReceiptModal = ref(false)
const selectedTransaction = ref(null)

const openReceipt = (item) => {
    selectedTransaction.value = item;
    showReceiptModal.value = true;
}

// Proof Modal
const showProofModal = ref(false)
const currentProofImages = ref([])

const viewProof = (imgUrl) => {
    currentProofImages.value = [imgUrl]
    showProofModal.value = true
}

const downloadImage = async (url) => {
    try {
        const response = await axios.get('/audit/sales/download-proof', {
            params: { url },
            responseType: 'blob'
        });

        const blob = new Blob([response.data]);
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        const filename = url.split('/').pop() || 'bukti.jpg';
        link.download = filename;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        console.error('Download failed:', error);
        window.open(url, '_blank');
    }
}

// Filter Sumber Masuk Barang (Multiple Choice)
const sourceOptions = [
    { key: 'store', label: 'In Store (Input Awal)' },
    { key: 'angkat_barang', label: 'In Angkat Barang' },
    { key: 'refund', label: 'In Refund' },
    { key: 'tukar_tambah', label: 'In Tukar Tambah' },
    { key: 'downgrade', label: 'In Downgrade' },
    { key: 'tukar_unit', label: 'In Tukar Unit' },
]
const selectedSources = ref([])
const isSourceDropdownOpen = ref(false)
const sourceDropdownRef = ref(null)

const isAllSourcesSelected = computed(() => {
    return selectedSources.value.length === 0 || selectedSources.value.length === sourceOptions.length
})

const sourceFilterLabel = computed(() => {
    if (isAllSourcesSelected.value) {
        return 'Semua Sumber Masuk'
    }
    if (selectedSources.value.length === 1) {
        const found = sourceOptions.find(o => o.key === selectedSources.value[0])
        return found ? found.label : selectedSources.value[0]
    }
    if (selectedSources.value.length === 2) {
        return selectedSources.value.map(k => {
            const found = sourceOptions.find(o => o.key === k)
            return found ? found.label.replace('In ', '') : k
        }).join(', ')
    }
    return `${selectedSources.value.length} Sumber Masuk`
})

const toggleSource = (key) => {
    if (isAllSourcesSelected.value) {
        selectedSources.value = [key]
    } else {
        const idx = selectedSources.value.indexOf(key)
        if (idx > -1) {
            selectedSources.value.splice(idx, 1)
        } else {
            selectedSources.value.push(key)
        }
        if (selectedSources.value.length === sourceOptions.length) {
            selectedSources.value = []
        }
    }
    fetchData()
}

const toggleAllSources = () => {
    selectedSources.value = []
    fetchData()
}

const toggleSourceDropdown = () => {
    isSourceDropdownOpen.value = !isSourceDropdownOpen.value
}

const handleClickOutside = (event) => {
    if (sourceDropdownRef.value && !sourceDropdownRef.value.contains(event.target)) {
        isSourceDropdownOpen.value = false
    }
}

useEscapeKey(() => {
    if (isSourceDropdownOpen.value) isSourceDropdownOpen.value = false;
    if (showProofModal.value) showProofModal.value = false;
});

// Harga Modal editing per item
// editableModal structure: { stock_out_id: { detail_id: value, detail_id_2: value } }
const editableModal = reactive({})
const detailPresets = reactive({})
const profitNotes = ref({})
const savingModalId = ref(null)

const formatCategoryLabel = (category) => {
    const cat = (category || '').toLowerCase();
    switch (cat) {
        case 'penjualan_store':
        case 'pos':
        case 'sale':
            return 'Penjualan Store';
        case 'shopee':
        case 'orderan_online':
            return 'Orderan Online';
        case 'tukar_unit':
            return 'Tukar Unit';
        case 'tukar_tambah':
            return 'Tukar Tambah';
        case 'downgrade':
            return 'Downgrade';
        case 'dp':
            return 'DP';
        case 'pelunasan_dp':
            return 'Pelunasan DP';
        case 'refund_dp':
            return 'Refund DP';
        case 'refund':
            return 'Refund';
        case 'angkat_barang':
            return 'Angkat Barang';
        case 'cancel_penjualan':
            return 'Dibatalkan';
        default:
            return category || '-';
    }
};

const getCategoryBadgeClass = (category) => {
    const cat = (category || '').toLowerCase();
    if (cat === 'dp' || cat === 'pelunasan_dp') {
        return 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400 border-purple-200 dark:border-purple-500/20';
    }
    if (cat === 'refund_dp' || cat === 'refund') {
        return 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/20';
    }
    if (cat === 'cancel_penjualan') {
        return 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border-red-200 dark:border-red-500/20';
    }
    if (cat === 'tukar_tambah' || cat === 'tukar_unit' || cat === 'downgrade') {
        return 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border-amber-200 dark:border-amber-500/20';
    }
    return 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border-blue-200 dark:border-blue-500/20';
};

const isItemHp = (detail) => {
    if (!detail) return false;
    if (detail.imei && detail.imei !== '-' && detail.imei.toString().trim() !== '') return true;
    const type = (detail.type || '').toUpperCase();
    return type === 'HP' || type === 'DP';
};

const isItemNonHp = (detail) => {
    return !isItemHp(detail);
};

const isItemIphone = (detail) => {
    if (!detail) return false;
    const brand = (detail.brand || '').toLowerCase();
    const name = (detail.name || '').toLowerCase();
    const isApple = brand.includes('apple') || brand.includes('iphone') || name.includes('iphone') || name.includes('ipad');
    return isApple && isItemHp(detail);
};

const isItemIphoneSecond = (detail) => {
    if (!isItemIphone(detail)) return false;
    const cond = (detail?.condition || '').toLowerCase().trim();
    return cond !== 'new' && !cond.includes('baru');
};

// iPhone second range profit rules:
// 0-2 jt: 100rb
// 2-3 jt: 200rb
// 3-5 jt: 250rb
// 5-7 jt: 350rb
// 7-10 jt: 400rb
// >10 jt: 500rb
const calculateIphoneSecondProfit = (jual) => {
    if (jual <= 2000000) return 100000;
    if (jual <= 3000000) return 200000;
    if (jual <= 5000000) return 250000;
    if (jual <= 7000000) return 350000;
    if (jual <= 10000000) return 400000;
    return 500000;
};

const getSourceBadgeClass = (sourceType) => {
    switch (sourceType) {
        case 'angkat_barang':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20';
        case 'refund':
            return 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20';
        case 'tukar_tambah':
            return 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20';
        case 'downgrade':
            return 'bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-500/20';
        case 'tukar_unit':
            return 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/20';
        default:
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20';
    }
};

const isRowAudited = (item) => {
    if (!item) return false;
    if (item.is_audited) return true;
    if (item.audit_score != null) return true;
    if (item.items && item.items.length > 0 && item.items.some(d => d.has_saved_modal)) return true;
    return false;
};

const getPresetOptionLabel = (item, detail, presetKey) => {
    const jual = Number(detail?.harga_jual) || 0;

    switch (presetKey) {
        case 'pct_2':
            return `2% — Rp ${formatNumber(Math.round(jual * 0.98))}`;

        case 'pct_5':
            return `5% — Rp ${formatNumber(Math.round(jual * 0.95))}`;

        case 'pct_10':
            return `10% — Rp ${formatNumber(Math.round(jual * 0.90))}`;

        case 'pct_20_nonhp':
            return `20% (khusus non hp) — Rp ${formatNumber(Math.round(jual * 0.80))}`;

        case 'pct_30_nonhp':
            return `30% (khusus non hp) — Rp ${formatNumber(Math.round(jual * 0.70))}`;

        case 'range_iphone': {
            const profit = calculateIphoneSecondProfit(jual);
            const modal = Math.max(0, jual - profit);
            return `Range (khusus iphone) — Rp ${formatNumber(modal)}`;
        }

        case 'admin_harga': {
            const cost = Number(detail?.admin_harga_modal) || 0;
            return cost > 0 ? `admin harga — Rp ${formatNumber(cost)}` : 'admin harga — (belum ada)';
        }

        case 'default': {
            const cost = Number(detail?.raw_cost_price) || 0;
            const label = detail?.default_preset_label || 'Default (input awal)';
            return cost > 0 ? `${label} — Rp ${formatNumber(cost)}` : `${label} — (tidak ada)`;
        }

        case 'manual':
            return 'Input Manual';

        default:
            return presetKey;
    }
};

const getDetailPreset = (itemId, detailId) => {
    return detailPresets[itemId]?.[detailId] || 'manual';
};

const calculatePresetModal = (item, detail, presetKey) => {
    const jual = Number(detail.harga_jual) || 0;

    switch (presetKey) {
        case 'default':
            return Number(detail.raw_cost_price) || 0;

        case 'admin_harga':
            return Number(detail.admin_harga_modal) || 0;

        case 'range_iphone': {
            const profit = calculateIphoneSecondProfit(jual);
            return Math.max(0, jual - profit);
        }

        case 'pct_2':
            return Math.round(jual * 0.98);

        case 'pct_5':
            return Math.round(jual * 0.95);

        case 'pct_10':
            return Math.round(jual * 0.90);

        case 'pct_20_nonhp':
            return Math.round(jual * 0.80);

        case 'pct_30_nonhp':
            return Math.round(jual * 0.70);

        default:
            return null;
    }
};

const handlePresetChange = (item, detail, presetKey) => {
    if (!detailPresets[item.id]) detailPresets[item.id] = {};
    detailPresets[item.id][detail.id] = presetKey;

    if (presetKey === 'manual') {
        return;
    }

    const modalVal = calculatePresetModal(item, detail, presetKey);
    if (modalVal !== null) {
        if (!editableModal[item.id]) editableModal[item.id] = {};
        editableModal[item.id][detail.id] = modalVal;
    }
};

const initEditableModal = () => {
    if (!profitRecords.value.daily_sales?.data) return;
    profitRecords.value.daily_sales.data.forEach(item => {
        editableModal[item.id] = {}
        detailPresets[item.id] = {}
        profitNotes.value[item.id] = item.audit_notes || ''
        if (item.items) {
            item.items.forEach(detail => {
                // Ketika belum diaudit, nilai modal di input dikosongkan (placeholder 0), profit 0
                editableModal[item.id][detail.id] = detail.has_saved_modal ? Number(detail.harga_modal) : null
                detailPresets[item.id][detail.id] = 'manual'
            })
        }
    })
};

// Format display for modal input (shows rupiah-formatted number)
const formatModalDisplay = (stockOutId, detailId, defaultVal) => {
    const val = editableModal[stockOutId]?.[detailId]
    if (val != null && val !== '') {
        return formatNumber(val)
    }
    return ''
};

// Handle typing in harga modal input - strip non-digits, store raw number
const onModalInput = (event, item, detail) => {
    const raw = event.target.value.replace(/[^0-9]/g, '')
    const num = raw ? parseInt(raw, 10) : null

    if (!editableModal[item.id]) editableModal[item.id] = {}
    editableModal[item.id][detail.id] = num

    if (!detailPresets[item.id]) detailPresets[item.id] = {}
    detailPresets[item.id][detail.id] = 'manual'

    // Reformat the display
    event.target.value = num != null ? formatNumber(num) : ''
};

const onModalFocus = (event, item, detail) => {
    // On focus, show raw number for easy editing
    const val = editableModal[item.id]?.[detail.id]
    if (val != null) {
        event.target.value = val.toString()
    }
};

const onModalBlur = (event, item, detail) => {
    // On blur, reformat to rupiah
    const val = editableModal[item.id]?.[detail.id]
    if (val != null) {
        event.target.value = formatNumber(val)
    } else {
        event.target.value = ''
    }
};

const getEffectiveProfit = (item, detail) => {
    const modal = editableModal[item.id]?.[detail.id];
    if (modal != null) {
        return (Number(detail.harga_jual) || 0) - Number(modal);
    }
    if (detail.has_saved_modal && detail.harga_modal != null) {
        return (Number(detail.harga_jual) || 0) - Number(detail.harga_modal);
    }
    return 0; // Nol ketika belum diaudit
};

const getDisplayItems = (item) => {
    if (!item?.items) return [];
    if (activeTab.value === 'hp') {
        return item.items.filter(isItemHp);
    }
    if (activeTab.value === 'non_hp') {
        return item.items.filter(isItemNonHp);
    }
    return item.items;
};

const getItemDisplayJual = (item) => {
    const items = getDisplayItems(item);
    return items.reduce((sum, d) => sum + (Number(d.harga_jual) || 0), 0);
};

const getItemDisplayModal = (item) => {
    const items = getDisplayItems(item);
    return items.reduce((sum, d) => {
        const modal = editableModal[item.id]?.[d.id];
        if (modal != null) return sum + Number(modal);
        if (d.has_saved_modal && d.harga_modal != null) return sum + Number(d.harga_modal);
        return sum + 0;
    }, 0);
};

const getItemDisplayProfit = (item) => {
    const items = getDisplayItems(item);
    return items.reduce((sum, d) => sum + getEffectiveProfit(item, d), 0);
};

const saveHargaModal = async (item) => {
    // Gather all details for this transaction
    const itemsModalPayload = {}
    if (item.items) {
        item.items.forEach(detail => {
            const currentEditVal = editableModal[item.id]?.[detail.id]
            const finalVal = currentEditVal ?? Number(detail.harga_modal) ?? Number(detail.default_harga_modal) ?? 0

            // Assign back to editable state immediately so it matches
            if (!editableModal[item.id]) editableModal[item.id] = {}
            editableModal[item.id][detail.id] = finalVal

            itemsModalPayload[detail.id] = finalVal
        })
    }

    savingModalId.value = item.id
    try {
        const noteVal = profitNotes.value[item.id] !== undefined ? profitNotes.value[item.id] : (item.audit_notes || '')
        const res = await axios.post(`/audit/profit/${item.id}`, {
            items_modal: itemsModalPayload,
            notes: noteVal
        })

        // Update item total properties
        item.harga_modal = res.data.harga_modal
        item.profit = res.data.profit
        item.is_audited = true
        item.audit_notes = res.data.notes !== undefined ? res.data.notes : (profitNotes.value[item.id] || null)
        item.auditor_name = res.data.auditor_name || authStore.userName || 'Auditor'
        item.latest_auditor_name = res.data.auditor_name || authStore.userName || 'Auditor'
        item.audited_at = res.data.audited_at || new Date().toISOString()

        // Update individual item properties safely
        if (item.items) {
            item.items.forEach(detail => {
                if (res.data.items_modal && res.data.items_modal[detail.id] !== undefined) {
                    detail.harga_modal = res.data.items_modal[detail.id]
                }
                detail.has_saved_modal = true
                detail.profit = (Number(detail.harga_jual) || 0) - (Number(detail.harga_modal) || 0)
            })
        }
        toast.success('Data modal & catatan audit berhasil disimpan!')
    } catch (e) {
        console.error('Failed to save harga modal', e)
        toast.error('Gagal menyimpan harga modal: ' + (e.response?.data?.message || e.message))
    } finally {
        savingModalId.value = null
    }
};

// Monthly Logic
const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const getLogicalDate = () => {
    const now = new Date();
    if (now.getHours() < 5) now.setDate(now.getDate() - 1);
    return now;
};

const isPrivilegedUser = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'analis', 'admin_produk'];
    if (privilegedRoles.some(r => role.includes(r))) return true;
    const user = authStore.user;
    if (!user) return false;
    if (typeof user.role === 'string' && privilegedRoles.some(r => user.role.toLowerCase().includes(r))) return true;
    if (Array.isArray(user.roles)) {
        return user.roles.some(r => {
            const name = typeof r === 'string' ? r : (r.name || '');
            return privilegedRoles.some(priv => name.toLowerCase().includes(priv));
        });
    }
    if (authStore.hasRole && (authStore.hasRole('audit') || authStore.hasRole('super_admin') || authStore.hasRole('leader'))) return true;
    return false;
});

const years = computed(() => {
    const d = getLogicalDate();
    const currentYear = d.getFullYear();
    if (!isPrivilegedUser.value) {
        return [currentYear];
    }
    return Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);
});

const restrictedMonths = computed(() => {
    const d = getLogicalDate();
    const currentMonth = d.getMonth() + 1; // 1-indexed
    const currentYear = d.getFullYear();

    if (!isPrivilegedUser.value && selectedYear.value === currentYear) {
        const lastMonth = new Date(d.getFullYear(), d.getMonth() - 1, 1).getMonth() + 1;
        return months.map((m, i) => ({ name: m, value: i + 1 }))
            .filter(m => m.value === currentMonth || m.value === lastMonth);
    }
    return months.map((m, i) => ({ name: m, value: i + 1 }));
});

const getMinDate = computed(() => {
    if (isPrivilegedUser.value) return null;

    const d = getLogicalDate();
    d.setDate(d.getDate() - 7); // Allow past 7 days
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
});

const selectedMonth = ref(new Date().getMonth() + 1);
const d_now = getLogicalDate();
const selectedYear = ref(d_now.getFullYear());


const exportExcel = async () => {
    if (exporting.value) return;
    exporting.value = true;
    try {
        const params = { ...filters.value };
        if (selectedSources.value.length > 0 && selectedSources.value.length < sourceOptions.length) {
            params.source = selectedSources.value.join(',');
        } else {
            params.source = undefined;
        }
        if (selectedPeriod.value === 'all') {
            params.period = 'all';
            params.start_date = '2000-01-01';
            params.end_date = getTodayLocal();
        }
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
            params.warehouse_id = undefined;
            params.distributor_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
            params.warehouse_id = type === 'W' ? id : undefined;
            params.distributor_id = type === 'D' ? id : undefined;
        }

        if (activeTab.value && activeTab.value !== 'all') {
            params.tab = activeTab.value;
        }

        const response = await axios.get('/audit/profit/export-excel', {
            params,
            responseType: 'blob'
        });

        const blob = new Blob([response.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const startStr = params.start_date || 'ALL';
        const endStr = params.end_date || 'ALL';
        link.setAttribute('download', `LAPORAN_AUDIT_PROFIT_${startStr}_SD_${endStr}.xlsx`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (e) {
        console.error('Export failed', e);
        let errorMsg = e.message;
        if (e.response?.data instanceof Blob) {
            try {
                const text = await e.response.data.text();
                const json = JSON.parse(text);
                if (json.message) errorMsg = json.message;
            } catch (_) {}
        } else if (e.response?.data?.message) {
            errorMsg = e.response.data.message;
        }
        alert('Gagal export data: ' + errorMsg);
    } finally {
        exporting.value = false;
    }
}

const profitRecords = ref({
    daily_sales: {
        data: [],
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 50
    },
})

const getTodayLocal = () => {
    const d = getLogicalDate();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const filters = ref({
    start_date: getTodayLocal(), // Start with today in local time
    end_date: getTodayLocal(),
    branch_id: null,
    category: 'all',
    source: 'all',
    audit_status: 'all'
})

const locations = ref([])
const selectedLocationKey = ref('all')

const displayedSales = computed(() => {
    let list = profitRecords.value.daily_sales?.data || [];

    // Exclude downgrade & cancel_penjualan entirely from Audit Profit
    list = list.filter(item => item.category !== 'cancel_penjualan' && item.category !== 'downgrade');

    // Filter by Active Tab (All, HP, Non-HP)
    if (activeTab.value === 'hp') {
        list = list.filter(item => item.items && item.items.some(isItemHp));
    } else if (activeTab.value === 'non_hp') {
        list = list.filter(item => item.items && item.items.some(isItemNonHp));
    }

    // Filter by Source (Client-side safety)
    if (selectedSources.value.length > 0 && selectedSources.value.length < sourceOptions.length) {
        list = list.filter(item => {
            if (!item.items || item.items.length === 0) return false;
            return item.items.some(d => selectedSources.value.includes(d.source_type));
        });
    }

    // Filter by Audit Status (Client-side safety)
    if (filters.value.audit_status === 'sudah') {
        list = list.filter(item => isRowAudited(item));
    } else if (filters.value.audit_status === 'belum') {
        list = list.filter(item => !isRowAudited(item));
    }

    return list;
});

const summaryStats = computed(() => {
    const list = displayedSales.value || []
    const sudahDiauditCount = list.filter(item => isRowAudited(item)).length
    const belumDiauditCount = list.filter(item => !isRowAudited(item)).length

    return {
        totalTransaksi: list.length,
        totalCancel: 0,
        belumDiaudit: belumDiauditCount,
        sudahDiaudit: sudahDiauditCount,
    }
})

// Summary computeds based on displayedSales (hanya menghitung transaksi yang sudah diaudit)
const totalHargaJual = computed(() =>
    (displayedSales.value || []).reduce((sum, item) => {
        if (!isRowAudited(item)) return sum;
        return sum + getItemDisplayJual(item);
    }, 0)
)
const totalHargaModal = computed(() =>
    (displayedSales.value || []).reduce((sum, item) => {
        if (!isRowAudited(item)) return sum;
        return sum + getItemDisplayModal(item);
    }, 0)
)
const totalProfit = computed(() =>
    (displayedSales.value || []).reduce((sum, item) => {
        if (!isRowAudited(item)) return sum;
        return sum + getItemDisplayProfit(item);
    }, 0)
)

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';

    if (selectedPeriod.value === 'all') {
        return 'Semua Tanggal';
    }

    if (selectedPeriod.value === 'daily') {
        const date = new Date(filters.value.start_date);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } else {
        const monthIndex = selectedMonth.value - 1;
        const year = selectedYear.value;
        return `${months[monthIndex]} ${year}`;
    }
})

const handlePeriodChange = () => {
    if (selectedPeriod.value === 'daily') {
        const today = getTodayLocal();
        filters.value.start_date = today;
        filters.value.end_date = today;
    } else if (selectedPeriod.value === 'monthly') {
        handleMonthChange();
    } else if (selectedPeriod.value === 'all') {
        filters.value.start_date = '';
        filters.value.end_date = '';
    }
    fetchData();
}

const handleDateChange = () => {
    if (selectedPeriod.value === 'daily') {
        filters.value.end_date = filters.value.start_date;
    }
    fetchData();
}

const handleMonthChange = () => {
    const year = selectedYear.value;
    const month = selectedMonth.value;
    const endDate = new Date(year, month, 0);
    const pad = (n) => n < 10 ? '0' + n : n;
    filters.value.start_date = `${year}-${pad(month)}-01`;
    filters.value.end_date = `${year}-${pad(month)}-${pad(endDate.getDate())}`;
    if (selectedPeriod.value === 'monthly') {
        fetchData();
    }
}


const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some((r) => role.includes(r));
});

const isAlwaysGlobal = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk'];
    return alwaysGlobalRoles.some(r => role.includes(r));
});

const formatCurrency = (value) => {
    const num = Number(value) || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(num)
}

const formatNumber = (value) => {
    const num = Number(value) || 0
    return new Intl.NumberFormat('id-ID').format(num)
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).replace(/\./g, ':')
}

const fetchBranches = async () => {
    if (loading.value) return;
    try {
        const requests = [
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/distributors')
        ];
        if (!authStore.user) {
            requests.push(axios.get('/user'));
        }
        const results = await Promise.all(requests);
        const branchRes = results[0];
        const shopRes = results[1];
        const distributorRes = results[2];
        const userRes = results[3]; // Capture if it was requested

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allDistributors = (distributorRes?.data?.data || distributorRes?.data || []).map(d => ({ ...d, type: 'distributor' }));
        const allLocations = [...allBranches, ...allShops, ...allDistributors];

        const user = userRes ? (userRes.data.user || userRes.data.data || userRes.data) : authStore.user;
        const role = (authStore.userRole || '').toLowerCase();

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);

        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);

        let allowedDistributorIds = [];
        if (user?.distributor_id) allowedDistributorIds.push(user.distributor_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
                if (p.model_type === 'distributor') allowedDistributorIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];
        allowedDistributorIds = [...new Set(allowedDistributorIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0 || allowedDistributorIds.length > 0;

        if (isAlwaysGlobal.value) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                if (loc.type === 'distributor') return allowedDistributorIds.includes(Number(loc.id));
                return false;
            });
            if (locations.value.length > 0 && selectedLocationKey.value === 'all') {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'distributor' ? 'D' : 'W'}:${loc.id}`;
            }
        } else if (role.includes('audit') || role.includes('leader') || role.includes('analist')) {
            locations.value = allLocations;
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const fetchData = async (page = 1) => {
    loading.value = true
    try {
        const params = { ...filters.value, page };
        if (selectedSources.value.length > 0 && selectedSources.value.length < sourceOptions.length) {
            params.source = selectedSources.value.join(',');
        } else {
            params.source = undefined;
        }
        if (selectedPeriod.value === 'all') {
            params.period = 'all';
            params.start_date = '2000-01-01';
            params.end_date = getTodayLocal();
        }
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
            params.warehouse_id = type === 'W' ? id : undefined;
            params.distributor_id = type === 'D' ? id : undefined;
        }

        const response = await axios.get('/audit/profit', { params })
        profitRecords.value = response.data
        initEditableModal()
    } catch (error) {
        console.error('Error fetching profit data:', error)
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    document.addEventListener('mousedown', handleClickOutside);
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;

    if (canFilterBranch.value && locations.value.length === 0) {
        await fetchBranches()
    }

    fetchData()
})

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

watch(() => authStore.user, async (newUser) => {
    if (newUser && canFilterBranch.value) {
        await fetchBranches();
    }
});
</script>
