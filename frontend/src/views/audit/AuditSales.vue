<template>
    <div class="space-y-8">
        <!-- Section 1: Penjualan -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
                <h2 class="text-xl font-bold text-text-primary">Penjualan</h2>

                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Period Filter (Modern UI) -->
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedPeriod" @change="handlePeriodChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Daily: Date Picker (Modern UI) -->
                    <div v-if="selectedPeriod === 'daily'" class="relative group">
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl hover:border-primary-500 hover:ring-2 hover:ring-primary-500/10 transition-all cursor-pointer">
                            <Calendar :size="18"
                                class="text-gray-500 dark:text-gray-400 group-hover:text-primary-500" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200 min-w-[100px]">
                                {{ formattedDateDisplay }}
                            </span>
                        </div>
                        <!-- Use showPicker() explicitly on click to ensure calendar opens consistently -->
                        <input type="date" v-model="filters.start_date" @change="handleDateChange"
                            @click="$event.target.showPicker()" :min="getMinDate" :max="getTodayLocal()"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />

                    </div>

                    <!-- Monthly: Month & Year Selectors (Modern UI) -->
                    <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                        <!-- Month Selector -->
                        <div class="relative min-w-[140px]">
                            <select v-model="selectedMonth" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="m in restrictedMonths" :key="m.value" :value="m.value">{{ m.name }}
                                </option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>

                        <!-- Year Selector -->
                        <div class="relative min-w-[100px]">
                            <select v-model="selectedYear" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>
                    </div>

                    <!-- Branch Filter (Modern UI) -->
                    <div v-if="canFilterBranch && locations.length > 1" class="relative min-w-[200px]">
                        <select v-model="selectedLocationKey" @change="fetchData()"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option v-if="isAlwaysGlobal" value="all">Semua Cabang/Toko/Distributor</option>
                            <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                                :value="`${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`">
                                {{ loc.type === 'branch' ? '[Cabang]' : loc.type === 'online_shop' ? '[Toko]' : loc.type
                                    === 'warehouse' ? '[Gudang]' : '[Distributor]' }} {{ loc.name }}
                            </option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                    <!-- Single Branch Display (if only 1 branch available) -->
                    <div v-else-if="canFilterBranch && locations.length === 1"
                        class="px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-100 dark:border-surface-700 rounded-xl flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                        <span class="text-sm font-bold text-text-primary">{{ locations[0].name }}</span>
                    </div>

                    <!-- Category Filter -->
                    <div class="relative min-w-[160px]">
                        <select v-model="filters.category" @change="fetchData"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="all">Semua Kategori</option>
                            <option value="penjualan_store">Penjualan Store</option>
                            <option value="orderan_online">Orderan Online</option>
                            <option value="tukar_unit">Tukar Unit</option>
                            <option value="tukar_tambah">Tukar Tambah</option>
                            <option value="downgrade">Downgrade</option>
                            <option value="cancel_penjualan">Cancel Penjualan</option>
                            <option value="refund">Refund</option>
                            <option value="angkat_barang">Angkat Barang</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Search Filter -->
                    <div class="relative min-w-[200px] flex-1 lg:flex-none">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search :size="16" class="text-gray-400" />
                        </div>
                        <input type="text" v-model="searchQuery" @input="debouncedSearch"
                            placeholder="Cari Nama/Nota/IMEI..."
                            class="w-full bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl pl-10 pr-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all" />
                        <button v-if="searchQuery" @click="clearSearch"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <X :size="14" />
                        </button>
                    </div>

                    <!-- Export Button -->
                    <button @click="exportExcel" :disabled="exporting"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg hover:transform hover:-translate-y-0.5 transition-all disabled:opacity-50"
                        :style="{ backgroundColor: '#10b981', color: '#ffffff' }">
                        <Download :size="18" :class="{ 'animate-bounce': exporting }" />
                        <span>{{ exporting ? 'Exporting...' : 'Export' }}</span>
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6" v-if="salesRecords.daily_sales.data && salesRecords.daily_sales.data.length > 0">
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Total Transaksi</p>
                    <p class="text-lg font-bold text-text-primary">{{ summaryStats.totalTransaksi }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Total Cancel</p>
                    <p class="text-lg font-bold text-red-500">{{ summaryStats.totalCancel }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Belum Diaudit (Hal ini)</p>
                    <p class="text-lg font-bold text-amber-500">{{ summaryStats.belumDiaudit }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Sudah Diaudit (Hal ini)</p>
                    <p class="text-lg font-bold text-emerald-500">{{ summaryStats.sudahDiaudit }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Total All Omset</p>
                    <p class="text-lg font-bold text-blue-500">{{ formatCurrency(summaryStats.totalOmset) }}</p>
                </div>
                <div class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Omset Bersih</p>
                    <p class="text-lg font-bold text-purple-500">{{ formatCurrency(summaryStats.omsetBersih) }}</p>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-text-secondary uppercase bg-gray-50/50 dark:!bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-6 py-4">No</th>
                                <th class="px-6 py-4">Waktu Pesanan</th>
                                <th class="px-6 py-4">Nomor Pesanan</th>
                                <th class="px-6 py-4">Cabang</th>
                                <th class="px-6 py-4">Nama</th>
                                <th class="px-6 py-4">No HP</th>
                                <th class="px-6 py-4">Kategori</th>
                                <th colspan="3"
                                    class="p-0 border-b border-gray-200 dark:border-surface-700 bg-gray-50/50 dark:!bg-surface-700/50">
                                    <div
                                        class="grid grid-cols-[80px_100px_1fr] md:grid-cols-[100px_120px_1fr] w-full min-w-[320px]">
                                        <div class="px-6 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Tipe</div>
                                        <div class="px-6 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Brand</div>
                                        <div class="px-6 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Rincian Barang</div>
                                    </div>
                                </th>
                                <th class="px-6 py-4">Status Pembayaran</th>
                                <th class="px-6 py-4">Cash Toko</th>
                                <th class="px-6 py-4">Transfer</th>
                                <th class="px-6 py-4">EDC</th>
                                <th class="px-6 py-4 text-center">Cek Audit</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading">
                                <td colspan="14" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center text-text-secondary">
                                        <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                        <span class="text-sm font-medium">Memuat data penjualan...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-else-if="!salesRecords.daily_sales.data || salesRecords.daily_sales.data.length === 0">
                                <td colspan="14" class="px-6 py-12 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                            <FileText class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <span class="font-medium text-text-primary">Tidak ada data
                                            penjualan</span>
                                        <span class="text-xs mt-1">Belum ada transaksi pada periode ini</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in salesRecords.daily_sales.data" :key="item.id"
                                class="transition-colors group text-text-primary"
                                :class="[
                                    item.category === 'cancel_penjualan' 
                                        ? 'bg-red-50/80 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20' 
                                        : item.audit_score != null 
                                            ? 'bg-emerald-50/80 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20' 
                                            : 'hover:bg-gray-50 dark:hover:bg-surface-700/30'
                                ]">
                                <td class="px-6 py-4 text-text-secondary">{{ (salesRecords.daily_sales.current_page - 1)
                                    *
                                    salesRecords.daily_sales.per_page + index + 1 }}</td>
                                <td class="px-6 py-4 font-medium text-text-primary">{{ formatDate(item.date)
                                }}</td>
                                <td class="px-6 py-4 text-text-primary font-medium">
                                    <div>{{ item.order_no }}</div>
                                    <button @click="openScreenshot(item)" class="mt-1.5 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/20 rounded-md transition-colors">Screenshot Nota</button>
                                </td>
                                <td class="px-6 py-4 text-xs font-semibold text-text-secondary">{{ item.outlet_name ||
                                    '-' }}</td>
                                <td class="px-6 py-4 font-medium">{{ item.customer_name }}</td>
                                <td class="px-6 py-4 text-text-primary font-medium">{{
                                    item.customer_phone }}</td>
                                <td class="px-6 py-4">
                                    <div v-if="item.category === 'cancel_penjualan'" class="flex flex-col gap-1">
                                        <span class="inline-flex w-fit px-2.5 py-1 text-xs font-semibold rounded-lg bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20">
                                            Dibatalkan
                                        </span>
                                        <div v-if="item.cancelled_by_name" class="text-[10px] text-red-500/80 font-medium leading-tight">Oleh: {{ item.cancelled_by_name }}</div>
                                        <div v-if="item.cancel_reason" class="text-[10px] text-text-secondary italic leading-tight max-w-[120px] break-words">"{{ item.cancel_reason }}"</div>
                                    </div>
                                    <span v-else
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                        {{ (item.category === 'shopee' || item.category === 'orderan_online') ? 'Orderan Online' : item.category }}
                                    </span>
                                </td>
                                <td colspan="3" class="p-0 align-top">
                                    <div class="flex flex-col w-full h-full min-w-[320px]">
                                        <template v-if="item.items && item.items.length > 0">
                                            <div v-for="(detail, idx) in item.items" :key="idx"
                                                class="grid grid-cols-[80px_100px_1fr] md:grid-cols-[100px_120px_1fr] border-b border-gray-100 dark:border-surface-700 last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                                <div
                                                    class="px-6 py-4 font-medium text-sm text-text-secondary border-r border-gray-100 dark:border-surface-700 flex items-start break-words">
                                                    {{ detail.type || item.type }}</div>
                                                <div
                                                    class="px-6 py-4 text-xs font-semibold text-text-secondary border-r border-gray-100 dark:border-surface-700 flex items-start break-words whitespace-pre-wrap">
                                                    {{ detail.brand || item.brand_names }}</div>
                                                <div
                                                    class="px-6 py-4 text-xs font-medium text-text-secondary flex flex-col justify-center">
                                                    <div class="flex justify-between items-start gap-3 w-full">
                                                        <div class="whitespace-normal flex-1 leading-relaxed">
                                                            <div>{{ detail.name }}</div>
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
                                            </div>
                                            <div v-if="item.items && item.items.length > 1"
                                                class="px-6 py-3 border-t border-gray-100 dark:border-surface-700 text-xs text-text-secondary flex justify-end bg-gray-50/50 dark:!bg-surface-800/50">
                                                <span>Total: <span class="font-bold text-text-primary ml-1">{{ item.qty
                                                        }}</span></span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div
                                                class="grid grid-cols-[80px_100px_1fr] md:grid-cols-[100px_120px_1fr] border-b border-gray-100 dark:border-surface-700 last:border-0">
                                                <div
                                                    class="px-6 py-4 font-medium text-sm text-text-secondary border-r border-gray-100 dark:border-surface-700 flex items-start break-words">
                                                    {{ item.type }}</div>
                                                <div
                                                    class="px-6 py-4 text-xs font-semibold text-text-secondary border-r border-gray-100 dark:border-surface-700 flex items-start break-words whitespace-pre-wrap">
                                                    {{ item.brand_names }}</div>
                                                <div
                                                    class="px-6 py-4 text-xs font-medium text-text-secondary flex flex-col justify-center">
                                                    <div class="flex justify-between items-start gap-3 w-full">
                                                        <div class="whitespace-normal flex-1 leading-relaxed">
                                                            <div>{{ item.product_names || '-' }}</div>
                                                            <div v-if="item.storages"
                                                                class="mt-0.5 text-[10px] text-gray-500">{{
                                                                    item.storages }}</div>
                                                            <div v-if="item.imeis && item.imeis !== '-'"
                                                                class="mt-0.5 text-xs text-blue-500 font-mono">IMEI: {{
                                                                    item.imeis }}</div>
                                                            <span v-if="item.conditions"
                                                                class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                                                :class="item.conditions === 'Baru' ? 'bg-emerald-500/10 text-emerald-500' : item.conditions === 'Ex iBox' ? 'bg-purple-500/10 text-purple-500' : 'bg-amber-500/10 text-amber-500'">{{
                                                                    item.conditions }}</span>
                                                        </div>
                                                        <div
                                                            class="bg-gray-100 dark:!bg-surface-700 px-2 py-0.5 rounded text-xs font-bold text-text-primary whitespace-nowrap mt-0.5">
                                                            {{ item.qty }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                        :class="item.status === 'Lunas'
                                            ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                            : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs font-semibold text-text-secondary">{{
                                    formatCurrency(item.cash) }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-text-secondary">{{
                                    formatCurrency(item.transfer) }}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-text-secondary">{{
                                    formatCurrency(item.edc) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="item.audit_score == null" class="text-xs text-gray-400">-</span>
                                    <span v-else-if="item.audit_score === 100"
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20">100%
                                        ✅</span>
                                    <span v-else
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20">{{
                                            item.audit_score }}% ⚠️</span>
                                </td>
                                <td class="px-6 py-4">
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
                                        <button v-if="!isLeader" @click="openChecklist(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                            title="Cek Audit">
                                            <ClipboardCheck :size="16" />
                                        </button>
                                        <button
                                            v-if="item.category !== 'cancel_penjualan' && canCancel(item.created_at || item.date)"
                                            @click="handleCancelSale(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-red-500 hover:text-red-600 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                            title="Batalkan Penjualan">
                                            <Trash2 :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div v-if="salesRecords.daily_sales.total > 0"
                    class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-between items-center bg-gray-50/50 dark:bg-surface-700/30">
                    <span class="text-xs text-text-secondary font-medium">
                        Menampilkan {{ (salesRecords.daily_sales.current_page - 1) * salesRecords.daily_sales.per_page +
                            1 }} -
                        {{ Math.min(salesRecords.daily_sales.current_page * salesRecords.daily_sales.per_page,
                            salesRecords.daily_sales.total) }}
                        dari {{ salesRecords.daily_sales.total }} data
                    </span>
                    <div class="flex gap-2">
                        <button @click="fetchData(salesRecords.daily_sales.current_page - 1)"
                            :disabled="salesRecords.daily_sales.current_page === 1 || loading"
                            class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                            <ChevronLeft :size="18" />
                        </button>
                        <div class="flex items-center px-3 text-sm font-bold text-text-primary">
                            {{ salesRecords.daily_sales.current_page }} / {{ salesRecords.daily_sales.last_page }}
                        </div>
                        <button @click="fetchData(salesRecords.daily_sales.current_page + 1)"
                            :disabled="salesRecords.daily_sales.current_page === salesRecords.daily_sales.last_page || loading"
                            class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                            <ChevronRight :size="18" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <ReceiptModal :isOpen="showReceiptModal" :transaction="selectedTransaction" :showEditIcon="false"
        @close="showReceiptModal = false" />

    <!-- Audit Checklist Modal -->
    <Teleport to="body">
        <div v-if="showChecklistModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showChecklistModal = false"></div>
            <div
                class="relative bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 w-full max-w-lg shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 dark:border-surface-700">
                    <h3 class="text-lg font-bold text-text-primary">Cek Audit</h3>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ checklistData?.category }} — {{ checklistData?.answered }}/{{ checklistData?.total }} dijawab
                        <span v-if="checklistData?.score !== undefined" class="font-semibold"
                            :class="checklistData.score === 100 ? 'text-emerald-600' : 'text-amber-600'">
                            ({{ checklistData.score }}%)
                        </span>
                    </p>
                    <p v-if="checklistData?.audited_at" class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <Calendar :size="12" />
                        Terakhir diaudit: {{ formatDate(checklistData.audited_at) }}
                    </p>
                </div>

                <!-- Questions -->
                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div v-if="checklistLoading" class="flex items-center justify-center py-8">
                        <Loader2 class="w-6 h-6 animate-spin text-primary-500" />
                    </div>
                    <div v-else-if="!checklistData?.questions?.length" class="text-center py-8 text-gray-500">
                        Belum ada pertanyaan untuk kategori ini.
                    </div>
                    <div v-else v-for="(q, i) in checklistData.questions" :key="q.question_id"
                        class="flex flex-col gap-2 p-4 rounded-xl border transition-all"
                        :class="q.answer === true ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' : q.answer === false ? 'border-red-200 dark:border-red-500/30 bg-red-50/50 dark:bg-red-500/5' : 'border-gray-200 dark:border-surface-600 bg-gray-50/50 dark:bg-surface-700/30'">
                        <div class="flex items-start gap-4">
                            <span class="text-sm font-bold text-gray-400 mt-0.5">{{ i + 1 }}.</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-text-primary">{{ q.content }}</p>
                                <p v-if="q.is_deleted" class="text-[10px] text-red-400 mt-0.5 italic">Pertanyaan ini
                                    sudah dihapus/diubah</p>
                                <p v-if="q.answered_at" class="text-[10px] text-gray-400 mt-0.5">
                                    Dijawab: {{ formatDate(q.answered_at) }}
                                </p>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="setAnswer(i, true)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="q.answer === true ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30' : 'bg-gray-100 dark:bg-surface-600 text-gray-500 dark:text-gray-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 hover:text-emerald-600'">
                                    Yes
                                </button>
                                <button @click="setAnswer(i, false)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="q.answer === false ? 'bg-red-500 text-white shadow-lg shadow-red-500/30' : 'bg-gray-100 dark:bg-surface-600 text-gray-500 dark:text-gray-400 hover:bg-red-100 dark:hover:bg-red-500/20 hover:text-red-600'">
                                    No
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Global Notes textarea -->
                    <div class="mt-6 flex flex-col gap-2 p-4 rounded-xl border border-gray-200 dark:border-surface-600 bg-gray-50/50 dark:bg-surface-700/30">
                        <label class="text-sm font-bold text-text-primary">Catatan Audit (Opsional)</label>
                        <textarea v-model="checklistData.notes" rows="3" placeholder="Masukkan catatan keseluruhan audit di sini..."
                            class="w-full text-xs px-3 py-2 rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-700 text-gray-700 dark:text-gray-300 placeholder-gray-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all resize-none"></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="showChecklistModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">
                        Tutup
                    </button>
                    <button @click="saveChecklist" :disabled="checklistSaving"
                        class="px-5 py-2 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 rounded-xl shadow-lg shadow-primary-500/20 transition-all disabled:opacity-50">
                        {{ checklistSaving ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Proof Photo Modal (HD Gallery) -->
    <div v-if="showProofModal"
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4 px-6 sm:px-20 bg-black/90 backdrop-blur-md">
        <div class="relative w-full max-w-5xl h-full flex flex-col items-center justify-center py-12">
            <!-- Close Button -->
            <button @click="showProofModal = false"
                class="absolute top-4 right-0 p-3 bg-white/10 hover:bg-white/20 text-white rounded-full transition-all z-[110] backdrop-blur-md border border-white/20 active:scale-95"
                title="Tutup (ESC)">
                <X :size="28" stroke-width="3" />
            </button>

            <!-- Images Container (HD Grid/Gallery) -->
            <div class="w-full flex-1 overflow-y-auto custom-scrollbar p-2">
                <div :class="[
                    'w-full max-w-7xl mx-auto',
                    currentProofImages.length === 2 ? 'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' : 'flex flex-col gap-10 items-center'
                ]">
                    <div v-for="(imgUrl, index) in currentProofImages" :key="index"
                        class="w-full bg-white dark:bg-surface-800 rounded-[2.5rem] overflow-hidden shadow-[0_30px_60px_-15px_rgba(0,0,0,0.5)] border border-white/10 group">

                        <!-- HD Image Wrapper -->
                        <div class="relative overflow-hidden bg-gray-900 aspect-square sm:aspect-auto">
                            <img :src="imgUrl" :alt="'Foto Bukti ' + (index + 1)"
                                class="w-full h-auto min-h-[400px] max-h-[70vh] object-contain transition-all duration-700 group-hover:scale-105"
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

    <!-- Cancel Sale Modal -->
    <CancelSaleModal :show="showCancelModal" :sale="selectedSaleForCancel" @close="showCancelModal = false"
        @success="fetchData" />
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useEscapeKey } from '../../composables/useEscapeKey'
import { Loader2, Download, Eye, FileText, ChevronLeft, ChevronRight, ChevronDown, Calendar, ClipboardCheck, Trash2, Search, X, Image, Wallet, TrendingUp } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'
import { debounce } from '../../utils/debounce'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'
import CancelSaleModal from '../../components/modals/CancelSaleModal.vue'
import SaleScreenshot from '../../components/sales/SaleScreenshot.vue'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const showScreenshotModal = ref(false)
const selectedSaleForScreenshot = ref(null)

const openScreenshot = (item) => {
    selectedSaleForScreenshot.value = item;
    showScreenshotModal.value = true;
}

const authStore = useAuthStore()
const isLeader = computed(() => (authStore.userRole || '').toLowerCase() === 'leader')

// Search & Filters
const searchQuery = ref("")
const debouncedSearch = debounce(() => {
    fetchData(1)
}, 500)

const clearSearch = () => {
    searchQuery.value = ""
    fetchData(1)
}

// Dropped Tabs Logic - Now displaying all sections vertically
const loading = ref(false)
const exporting = ref(false)
const selectedPeriod = ref('daily') // For filter dropdown

const exportExcel = async () => {
    if (exporting.value) return;
    exporting.value = true;
    try {
        const params = { ...filters.value };
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
            params.distributor_id = type === 'D' ? id : undefined;
        }

        const response = await axios.get('/audit/sales/export', {
            params,
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const filename = `Laporan-Penjualan-${filters.start_date}-to-${filters.end_date}.xlsx`;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (e) {
        console.error('Export failed', e);
        alert('Gagal export data: ' + (e.response?.data?.message || e.message));
    } finally {
        exporting.value = false;
    }
}

// Receipt Modal State
const showReceiptModal = ref(false)
const selectedTransaction = ref(null)

const openReceipt = (item) => {
    selectedTransaction.value = item;
    showReceiptModal.value = true;
}

const openChecklistFromReceipt = () => {
    showReceiptModal.value = false
    if (selectedTransaction.value) {
        openChecklist(selectedTransaction.value)
    }
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

useEscapeKey(() => {
    if (showProofModal.value) showProofModal.value = false
})

// Audit Checklist Modal State
const showChecklistModal = ref(false)
const checklistLoading = ref(false)
const checklistSaving = ref(false)
const checklistData = ref(null)
const checklistStockOutId = ref(null)

// Cancellation logic
const showCancelModal = ref(false)
const selectedSaleForCancel = ref(null)

const canCancel = (date) => {
    const role = (authStore.userRole || '').toLowerCase();
    if (role === 'super_admin' || role === 'owner') return true;
    if (!date) return false;
    const itemDate = new Date(date);
    if (isNaN(itemDate.getTime())) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    itemDate.setHours(0, 0, 0, 0);
    const msPerDay = 24 * 60 * 60 * 1000;
    const diffDays = Math.round((today.getTime() - itemDate.getTime()) / msPerDay);
    return diffDays <= 5;
};

const handleCancelSale = (item) => {
    selectedSaleForCancel.value = item;
    showCancelModal.value = true;
};

const openChecklist = async (item) => {
    checklistStockOutId.value = item.id
    showChecklistModal.value = true
    checklistLoading.value = true
    try {
        const res = await axios.get(`/audit/checklist/${item.id}`)
        // Find global note from any question (assuming it's attached to the first one saved)
        const globalNote = res.data.questions?.find(q => q.notes)?.notes || '';
        checklistData.value = {
            ...res.data,
            notes: globalNote
        };
    } catch (e) {
        console.error('Failed to load checklist', e)
        alert('Gagal memuat checklist: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistLoading.value = false
    }
}

useEscapeKey(() => {
    if (showChecklistModal.value) showChecklistModal.value = false;
});

const setAnswer = (index, value) => {
    if (checklistData.value?.questions?.[index]) {
        checklistData.value.questions[index].answer = value
    }
}

const saveChecklist = async () => {
    if (!checklistData.value?.questions) return

    const answeredQuestions = checklistData.value.questions.filter(q => q.answer !== null)
    if (answeredQuestions.length === 0) {
        alert('Silakan jawab minimal 1 pertanyaan')
        return
    }

    checklistSaving.value = true
    try {
        const payload = {
            answers: answeredQuestions.map((q, idx) => ({
                question_id: q.question_id,
                answer: q.answer,
                notes: idx === 0 ? (checklistData.value.notes || null) : null,
                content: q.content
            }))
        }
        const res = await axios.post(`/audit/checklist/${checklistStockOutId.value}`, payload)

        // Update the score in the table
        const item = salesRecords.value.daily_sales.data.find(s => s.id === checklistStockOutId.value)
        if (item) {
            item.audit_score = res.data.score
            item.audit_answered = res.data.answered
            item.audit_total = res.data.total
        }

        // Update modal data
        checklistData.value.score = res.data.score
        checklistData.value.answered = res.data.answered
        checklistData.value.total = res.data.total

        toast.success('Checklist berhasil disimpan!')
        showChecklistModal.value = false
    } catch (e) {
        console.error('Failed to save checklist', e)
        toast.error('Gagal menyimpan: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistSaving.value = false
    }
}

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

const years = computed(() => {
    const d = getLogicalDate();
    const currentYear = d.getFullYear();
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    const isRestricted = !privilegedRoles.some(r => role.includes(r));

    if (isRestricted) {
        return [currentYear];
    }
    return Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);
});

const restrictedMonths = computed(() => {
    const d = getLogicalDate();
    const currentMonth = d.getMonth() + 1; // 1-indexed
    const currentYear = d.getFullYear();
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    const isRestricted = !privilegedRoles.some(r => role.includes(r));

    if (isRestricted && selectedYear.value === currentYear) {
        const lastMonth = new Date(d.getFullYear(), d.getMonth() - 1, 1).getMonth() + 1;
        return months.map((m, i) => ({ name: m, value: i + 1 }))
            .filter(m => m.value === currentMonth || m.value === lastMonth);
    }
    return months.map((m, i) => ({ name: m, value: i + 1 }));
});

const getMinDate = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    const isRestricted = !privilegedRoles.some(r => role.includes(r));
    if (!isRestricted) return null;

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


const salesRecords = ref({
    daily_sales: {
        data: [],
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 50
    },
    brand_sales: [],
    cs_sales: []
})

// Helper to get logical today (resets at 5 AM) already defined above
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
    category: 'all'
})

const locations = ref([])
const selectedLocationKey = ref('all')

const summaryStats = computed(() => {
    const list = salesRecords.value.daily_sales?.data || []
    const activeRecords = list.filter(item => item.category !== 'cancel_penjualan')
    const cancelRecords = list.filter(item => item.category === 'cancel_penjualan')
    
    let totalCancelGlobal = cancelRecords.length;
    let globalTransaksi = salesRecords.value.daily_sales?.total || 0;
    
    // If we have report_summary from backend, we could calculate Omset properly
    const summary = salesRecords.value?.report_summary;
    const hasSummary = summary !== undefined && summary !== null;
    
    if (hasSummary && summary.activities && summary.activities.cancel_penjualan) {
        totalCancelGlobal = summary.activities.cancel_penjualan.length;
    }

    let baseSales = 0;
    let tradeSelisih = 0;
    let tradeOutgoingTotal = 0;
    let tradeOutgoingTT = 0;
    let tradeIncomingTotal = 0;
    let outlay = 0;

    const resolveActualCategory = (category, notes, salesAccount) => {
        const cat = (category || '').toLowerCase();
        const n = (notes || '').toLowerCase();
        const sa = (salesAccount || '').toLowerCase();

        if (['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'tukar_tambah'].includes(cat)) {
            if (n.includes('tukar tambah') || n.includes('tukar_tambah') || sa.includes('tukar tambah') || sa.includes('tukar_tambah')) {
                return 'tukar_tambah';
            }
            return cat;
        }

        if (n.includes('barang angkat') || n.includes('angkat barang') || n.includes('angkat_barang') || sa.includes('barang angkat') || sa.includes('angkat barang') || sa.includes('angkat_barang')) {
            return 'angkat_barang';
        }
        if (n.includes('refund') || sa.includes('refund')) {
            return 'refund';
        }
        if (n.includes('downgrade') || sa.includes('downgrade')) {
            return 'downgrade';
        }
        if (n.includes('tukar tambah') || n.includes('tukar_tambah') || sa.includes('tukar tambah') || sa.includes('tukar_tambah')) {
            return 'tukar_tambah';
        }
        return cat;
    };

    activeRecords.forEach(item => {
        const cat = resolveActualCategory(item.category, item.notes, item.sales_account || item.inventory_user_name);
        
        let total = 0;
        const discount = parseFloat(item.total_discount) || 0;
        if (item.items && item.items.length > 0) {
            total = item.items.reduce((sum, detail) => sum + (Math.abs(parseFloat(detail.price || detail.harga_jual)) * parseFloat(detail.qty || 1)), 0);
        } else {
            total = Math.abs(parseFloat(item.original_price || item.total_amount || item.grand_total) || 0);
        }
        total = Math.max(0, total - discount);

        const isBaseSale = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship'].includes(cat);
        const isTradeIn = ['tukar_tambah', 'downgrade'].includes(cat);
        const isDeduction = ['refund', 'angkat_barang'].includes(cat);

        if (isBaseSale) {
            baseSales += total;
        } else if (isTradeIn) {
            const outVal = Math.abs(parseFloat(item.price_out) || (cat === 'tukar_tambah' ? total : 0));
            const inVal = Math.abs(parseFloat(item.price_in) || (cat === 'downgrade' ? (parseFloat(item.price_out) || 0) + total : 0));
            if (cat === 'tukar_tambah') {
                tradeOutgoingTotal += outVal;
                tradeIncomingTotal += inVal;
            } else if (cat === 'downgrade') {
                tradeIncomingTotal += Math.max(0, inVal - outVal);
            }
        }

        if (isDeduction) {
            outlay += total;
        }
    });

    let finalOmset = hasSummary ? (summary.payment_total ?? 0) : (baseSales + tradeOutgoingTotal);
    let finalOmsetBersih = hasSummary ? (summary.omset_bersih ?? 0) : (finalOmset - (outlay + tradeIncomingTotal));

    if (hasSummary && summary.activities && summary.activities.details) {
        const acts = summary.activities.details;
        let summaryOutlay = 0;
        let summaryTradeIncoming = 0;
        
        ['refund', 'angkat_barang'].forEach(cat => {
            if (acts[cat]) {
                acts[cat].forEach(item => {
                    summaryOutlay += parseFloat(item.price || 0);
                });
            }
        });
        
        ['downgrade', 'in_tt'].forEach(cat => {
            if (acts[cat]) {
                acts[cat].forEach(item => {
                    summaryTradeIncoming += parseFloat(item.price || 0);
                });
            }
        });

        if (activeRecords.length <= 150 && activeRecords.length > 0) {
            finalOmset = baseSales + tradeOutgoingTotal;
            finalOmsetBersih = finalOmset - (outlay + tradeIncomingTotal);
        } else {
            finalOmsetBersih = finalOmset - (summaryOutlay + summaryTradeIncoming);
        }
    } else if (!hasSummary) {
        finalOmset = baseSales + tradeOutgoingTotal;
        finalOmsetBersih = finalOmset - (outlay + tradeIncomingTotal);
    }

    return {
        totalTransaksi: globalTransaksi - totalCancelGlobal,
        totalCancel: totalCancelGlobal,
        belumDiaudit: activeRecords.filter(r => r.audit_score == null).length,
        sudahDiaudit: activeRecords.filter(r => r.audit_score != null).length,
        totalOmset: finalOmset,
        omsetBersih: finalOmsetBersih
    }
})

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';

    if (selectedPeriod.value === 'daily') {
        const date = new Date(filters.value.start_date);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } else { // monthly
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
    } else {
        handleMonthChange(); // This will set start_date and end_date for the selected month
    }
    fetchData();
}

const handleDateChange = () => {
    // Force End Date to match Start Date if in daily mode
    if (selectedPeriod.value === 'daily') {
        filters.value.end_date = filters.value.start_date;
    }
    fetchData();
}

const handleMonthChange = () => {
    // Calculate start and end of month
    const year = selectedYear.value;
    const month = selectedMonth.value; // 1-12

    // Start Date: YYYY-MM-01
    const startDate = new Date(year, month - 1, 1);
    // End Date: Last day of month
    const endDate = new Date(year, month, 0);

    // Adjust for timezone offset if needed (but YYYY-MM-DD strings are safer)
    // To safe ISO string (local time concept):
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
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value)
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
    if (loading.value) return; // Prevent concurrent fetches

    try {
        // Reduced requests: use authStore.user if available instead of fetching again
        const requests = [
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/distributors')
        ];

        // Only fetch user if not available in store
        if (!authStore.user) {
            requests.push(axios.get('/user'));
        }

        const results = await Promise.all(requests);
        const branchRes = results[0];
        const shopRes = results[1];
        const distributorRes = results[2];
        const userRes = results[3]; // This will be the user response if requested

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allDistributors = (distributorRes?.data?.data || distributorRes?.data || []).map(d => ({ ...d, type: 'distributor' }));
        const allLocations = [...allBranches, ...allShops, ...allDistributors];

        const user = userRes ? (userRes.data.user || userRes.data.data || userRes.data) : authStore.user;
        const role = (authStore.userRole || '').toLowerCase();

        // Collect allowed IDs from primary and placements
        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);

        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);

        let allowedWarehouseIds = [];
        if (user?.warehouse_id) allowedWarehouseIds.push(user.warehouse_id);

        let allowedDistributorIds = [];
        if (user?.distributor_id) allowedDistributorIds.push(user.distributor_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
                if (p.model_type === 'warehouse') allowedWarehouseIds.push(p.model_id); // although not usually in sales
                if (p.model_type === 'distributor') allowedDistributorIds.push(p.model_id);
            });
        }

        // Deduplicate
        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];
        allowedWarehouseIds = [...new Set(allowedWarehouseIds.map(id => Number(id)))];
        allowedDistributorIds = [...new Set(allowedDistributorIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0 || allowedWarehouseIds.length > 0 || allowedDistributorIds.length > 0;

        // Determine final location list
        if (isAlwaysGlobal.value) {
            // Can see everything
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            // Restricted to their assigned branches/shops
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                if (loc.type === 'distributor') return allowedDistributorIds.includes(Number(loc.id));
                return false;
            });

            // Auto-select first if currently 'all' but only one location exists
            if (locations.value.length > 0 && selectedLocationKey.value === 'all') {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`;
            }
        } else if (role.includes('audit') || role.includes('leader') || role.includes('analist')) {
            // Global Auditor role with NO specific assignments -> Show all
            locations.value = allLocations;
        } else {
            // Non-audit user with no assignments -> Show nothing or maybe should show own? 
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const fetchData = async (page = 1) => {
    loading.value = true
    try {
        // Map selected location key to specific filter params
        const params = { ...filters.value, page, search: searchQuery.value };
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
            params.distributor_id = type === 'D' ? id : undefined;
        }

        const response = await axios.get('/audit/sales', { params })
        salesRecords.value = response.data
    } catch (error) {
        console.error('Error fetching sales:', error)
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    // Optimization: Initialize filters
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;

    // Fetch locations if needed and not already loaded by watcher
    if (canFilterBranch.value && locations.value.length === 0) {
        await fetchBranches()
    }

    fetchData()
})

// Watch for user changes (e.g. on page reload if store initializes late)
watch(() => authStore.user, async (newUser) => {
    if (newUser && canFilterBranch.value) {
        await fetchBranches();
    }
});
</script>
