<template>
    <div class="space-y-8">
        <!-- Section: Audit Profit -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-text-primary">Audit Profit</h2>
                    <p class="text-sm text-gray-500 mt-1">Analisis profit per transaksi penjualan</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Period Filter -->
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedPeriod" @change="handlePeriodChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Daily: Date Picker -->
                    <div v-if="selectedPeriod === 'daily'" class="relative group">
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

                    <!-- Monthly: Month & Year Selectors -->
                    <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                        <div class="relative min-w-[140px]">
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
                    <div v-if="canFilterBranch && locations.length > 1" class="relative min-w-[200px]">
                        <select v-model="selectedLocationKey" @change="fetchData"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="all">Semua Cabang/Toko</option>
                            <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                                :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                                {{ loc.name }}
                            </option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                    <!-- Single Branch Display -->
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
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
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

            <!-- Summary Cards (Current Page) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" v-if="profitRecords.daily_sales.data && profitRecords.daily_sales.data.length > 0">
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Harga Jual (Hal ini)</p>
                    <p class="text-lg font-bold text-text-primary">{{ formatCurrency(totalHargaJual) }}</p>
                </div>
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Harga Modal (Hal ini)</p>
                    <p class="text-lg font-bold text-text-primary">{{ formatCurrency(totalHargaModal) }}</p>
                </div>
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Profit (Hal ini)</p>
                    <p class="text-lg font-bold"
                        :class="totalProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                        {{ formatCurrency(totalProfit) }}
                    </p>
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
                                <th class="px-4 py-4">No</th>
                                <th class="px-4 py-4">Waktu</th>
                                <th class="px-4 py-4">No Pesanan</th>
                                <th class="px-4 py-4">Cabang</th>
                                <th class="px-4 py-4">Nama</th>
                                <th class="px-4 py-4">Kategori</th>
                                <th colspan="4"
                                    class="p-0 border-b border-gray-200 dark:border-surface-700 bg-gray-50/50 dark:!bg-surface-700/50">
                                    <div class="grid grid-cols-[80px_100px_1fr_100px_150px_100px] w-full min-w-[700px]">
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Tipe</div>
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Brand</div>
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Rincian Barang</div>
                                        <div class="px-4 py-4 text-right font-semibold text-text-secondary uppercase">
                                            Harga Jual</div>
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Harga Modal</div>
                                        <div class="px-4 py-4 text-right font-semibold text-text-secondary uppercase">
                                            Profit</div>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center">Cek Audit</th>
                                <th class="px-4 py-4 text-center">#</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading">
                                <td colspan="12" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center text-text-secondary">
                                        <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                        <span class="text-sm font-medium">Memuat data profit...</span>
                                    </div>
                                </td>
                            </tr>
                             <tr v-else-if="!profitRecords.daily_sales.data || profitRecords.daily_sales.data.length === 0">
                                <td colspan="12" class="px-6 py-12 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                            <TrendingUp class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <span class="font-medium text-text-primary">Tidak ada data
                                            profit</span>
                                        <span class="text-xs mt-1">Belum ada transaksi pada periode ini</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in profitRecords.daily_sales.data" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors group text-text-primary">
                                <td class="px-4 py-4 text-text-secondary font-medium">{{ (profitRecords.daily_sales.current_page - 1) *
                                    profitRecords.daily_sales.per_page + index + 1 }}</td>
                                <td class="px-4 py-4 font-medium text-text-primary text-xs whitespace-nowrap">
                                    {{ formatDate(item.date) }}</td>
                                <td class="px-4 py-4 text-text-primary font-medium text-xs">{{ item.order_no }}</td>
                                <td class="px-4 py-4 text-xs font-semibold text-text-secondary">{{ item.outlet_name || '-' }}</td>
                                <td class="px-4 py-4 font-medium text-xs">{{ item.customer_name }}
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                        {{ (item.category === 'shopee' || item.category === 'orderan_online') ? 'Orderan Online' : item.category }}
                                    </span>
                                </td>
                                <td colspan="4" class="p-0 align-top">
                                    <div class="flex flex-col w-full h-full min-w-[700px]">
                                        <template v-if="item.items && item.items.length > 0">
                                            <div v-for="(detail, idx) in item.items" :key="idx"
                                                class="grid grid-cols-[80px_100px_1fr_100px_150px_100px] border-b border-gray-100 dark:!border-surface-700 last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                                <div
                                                    class="px-4 py-4 font-medium text-xs text-text-primary border-r border-gray-100 dark:!border-surface-700 flex flex-col items-start gap-1">
                                                    <span>{{ detail.type || item.type }}</span>
                                                    <span v-if="detail.category" class="px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-400 text-[10px] font-black uppercase tracking-tighter border border-blue-500/20">
                                                        {{ detail.category }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="px-4 py-4 text-xs font-semibold text-text-secondary border-r border-gray-100 dark:!border-surface-700 flex items-start break-words whitespace-pre-wrap">
                                                    {{ detail.brand || item.brand_names }}</div>
                                                <div
                                                    class="px-4 py-4 text-xs font-medium text-text-secondary flex flex-col justify-center border-r border-gray-100 dark:!border-surface-700">
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
                                                <!-- Harga Jual -->
                                                <div
                                                    class="px-4 py-4 text-text-primary font-mono text-xs font-semibold whitespace-nowrap text-right flex items-center justify-end border-r border-gray-100 dark:!border-surface-700">
                                                    {{ formatCurrency(detail.harga_jual || 0) }}
                                                </div>
                                                <!-- Harga Modal Input -->
                                                <div
                                                    class="px-4 py-3 flex items-center border-r border-gray-100 dark:!border-surface-700 group/modal">
                                                    <div class="relative w-full">
                                                        <span
                                                            class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-mono pointer-events-none">Rp</span>
                                                        <input type="text"
                                                            :value="formatModalDisplay(item.id, detail.id, detail.default_harga_modal)"
                                                            @input="onModalInput($event, item, detail)"
                                                            @focus="onModalFocus($event, item, detail)"
                                                            @blur="onModalBlur($event, item, detail)"
                                                            :placeholder="formatNumber(detail.default_harga_modal || 0)"
                                                            class="w-full pl-8 pr-2.5 py-1.5 text-xs font-mono rounded-lg border transition-all
                                                                bg-white dark:!bg-surface-700
                                                                focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                                            :class="detail.has_saved_modal
                                                                ? 'border-emerald-300 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-400'
                                                                : 'border-gray-200 dark:border-surface-600 text-text-primary'"
                                                            :disabled="isLeader" @keyup.enter="saveHargaModal(item)" />
                                                    </div>
                                                </div>
                                                <!-- Profit -->
                                                <div class="px-4 py-4 font-mono text-xs font-bold whitespace-nowrap text-right flex items-center justify-end"
                                                    :class="getEffectiveProfit(item, detail) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                    {{ formatCurrency(getEffectiveProfit(item, detail)) }}
                                                </div>
                                            </div>

                                            <div v-if="item.items && item.items.length > 1"
                                                class="px-4 py-3 border-t border-gray-100 dark:border-surface-700 text-xs text-text-secondary flex justify-between bg-gray-50/50 dark:!bg-surface-800/50">
                                                <div>
                                                    <span>Total Pesanan: <span
                                                            class="font-bold text-text-primary ml-1">{{ item.qty
                                                            }}</span></span>
                                                </div>
                                                <div class="flex items-center gap-4">
                                                    <span class="font-mono text-[10px] text-gray-500">Jual: {{
                                                        formatCurrency(item.harga_jual) }}</span>
                                                    <span class="font-mono text-[10px] text-gray-500">Modal: {{
                                                        formatCurrency(item.harga_modal ?? item.default_harga_modal)
                                                    }}</span>
                                                    <span class="font-bold font-mono text-[11px]"
                                                        :class="item.profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                        Profit: {{ formatCurrency(item.profit) }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="p-4 text-center text-sm text-gray-500">
                                                Data Rincian Barang Tidak Valid
                                            </div>
                                        </template>
                                    </div>
                                    <div v-if="!isLeader"
                                        class="flex justify-end p-2 bg-gray-50/30 dark:!bg-surface-800/30 border-t border-gray-100 dark:!border-surface-700 w-full min-w-[700px]">
                                        <button @click="saveHargaModal(item)" :disabled="savingModalId === item.id"
                                            class="px-3 py-1.5 flex items-center gap-1.5 rounded-lg text-xs font-medium bg-white dark:!bg-surface-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-surface-600 hover:text-emerald-600 hover:border-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-all shadow-sm"
                                            title="Simpan Semua Harga Modal Transaksi Ini">
                                            <Save v-if="savingModalId !== item.id" :size="14" />
                                            <Loader2 v-else :size="14" class="animate-spin" />
                                            <span>Simpan Rincian Modal</span>
                                        </button>
                                    </div>
                                </td>
                                <!-- Audit Score -->
                                <td class="px-4 py-4 text-center">
                                    <span v-if="item.audit_score === null" class="text-xs text-gray-400">-</span>
                                    <span v-else-if="item.audit_score === 100"
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20">100%
                                        ✅</span>
                                    <span v-else
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20">{{
                                            item.audit_score }}% ⚠️</span>
                                </td>
                                <!-- Actions -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2 transition-opacity">
                                        <button @click="openReceipt(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                            title="Lihat Nota">
                                            <Eye :size="16" />
                                        </button>
                                        <button @click="openChecklist(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                            title="Cek Audit Profit">
                                            <ClipboardCheck :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="profitRecords.daily_sales.total > 0"
                    class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-between items-center bg-gray-50/50 dark:bg-surface-700/30">
                    <span class="text-xs text-text-secondary font-medium">
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

    <!-- Audit Checklist Modal (Profit) - Two-stage: read-only / edit -->
    <Teleport to="body">
        <div v-if="showChecklistModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeChecklist"></div>
            <div
                class="relative bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 w-full max-w-lg shadow-2xl overflow-hidden">
                <!-- Header -->
                <div
                    class="px-6 py-4 border-b border-gray-100 dark:border-surface-700 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-text-primary">Cek Audit Profit</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Kategori: <span class="font-semibold text-purple-600 dark:text-purple-400">profit</span>
                            — {{ checklistData?.answered }}/{{ checklistData?.total }} dijawab
                            <span v-if="checklistData?.score !== undefined" class="font-semibold"
                                :class="checklistData.score === 100 ? 'text-emerald-600' : 'text-amber-600'">
                                ({{ checklistData.score }}%)
                            </span>
                        </p>
                        <p v-if="checklistData?.audited_at" class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <Calendar :size="12" />
                            Terakhir diaudit: {{ formatDate(checklistData.audited_at) }}
                        </p>
                        <p v-else class="text-xs text-amber-500 mt-1 font-medium">Belum pernah diaudit</p>
                    </div>
                    <!-- Edit toggle button -->
                    <button v-if="!checklistEditMode && !isLeader" @click="checklistEditMode = true"
                        class="p-2 bg-primary-500/10 hover:bg-primary-500/20 text-primary-600 rounded-xl transition-all"
                        title="Edit Audit">
                        <Pencil :size="16" />
                    </button>
                    <span v-else-if="!isLeader"
                        class="px-3 py-1.5 text-xs font-bold bg-primary-500/10 text-primary-600 rounded-lg">
                        Mode Edit
                    </span>
                </div>

                <!-- Questions -->
                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div v-if="checklistLoading" class="flex items-center justify-center py-8">
                        <Loader2 class="w-6 h-6 animate-spin text-primary-500" />
                    </div>
                    <div v-else-if="!checklistData?.questions?.length" class="text-center py-8 text-gray-500">
                        Belum ada pertanyaan untuk kategori <strong>profit</strong>.
                    </div>
                    <div v-else v-for="(q, i) in checklistData.questions" :key="i"
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

                            <!-- READ-ONLY mode: show only the answer badge -->
                            <div v-if="!checklistEditMode" class="flex-shrink-0">
                                <span v-if="q.answer === true"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-500 text-white">
                                    Yes
                                </span>
                                <span v-else-if="q.answer === false"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-red-500 text-white">
                                    No
                                </span>
                                <span v-else
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-medium bg-gray-100 dark:bg-surface-600 text-gray-400 italic">
                                    Belum di cek
                                </span>
                            </div>

                            <!-- EDIT mode: Yes/No toggle buttons -->
                            <div v-else class="flex gap-2 flex-shrink-0">
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

                        <!-- Notes: read-only shows text, edit shows textarea -->
                        <div v-if="checklistEditMode" class="ml-8">
                            <textarea v-model="q.notes" rows="2" placeholder="Catatan (opsional)..."
                                class="w-full text-xs px-3 py-2 rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-700 text-gray-700 dark:text-gray-300 placeholder-gray-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all resize-none">
                        </textarea>
                        </div>
                        <div v-else-if="q.notes" class="ml-8">
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:!bg-surface-700/50 px-3 py-2 rounded-lg">
                                <span class="font-medium text-gray-600 dark:text-gray-300">Catatan:</span> {{ q.notes }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="closeChecklist"
                        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">
                        Tutup
                    </button>
                    <button v-if="checklistEditMode" @click="saveChecklist" :disabled="checklistSaving"
                        class="px-5 py-2 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 rounded-xl shadow-lg shadow-primary-500/20 transition-all disabled:opacity-50">
                        {{ checklistSaving ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onMounted, computed, watch, reactive } from 'vue'
import { useEscapeKey } from '../../composables/useEscapeKey'
import { Loader2, Eye, FileText, ChevronDown, Calendar, TrendingUp, Save, ClipboardCheck, Pencil, Download } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'

const authStore = useAuthStore()
const isLeader = computed(() => (authStore.userRole || '').toLowerCase() === 'leader')

const loading = ref(false)
const exporting = ref(false)
const selectedPeriod = ref('daily')

// Receipt Modal State
const showReceiptModal = ref(false)
const selectedTransaction = ref(null)

const openReceipt = (item) => {
    selectedTransaction.value = item;
    showReceiptModal.value = true;
}

// Audit Checklist Modal State
const showChecklistModal = ref(false)
const checklistLoading = ref(false)
const checklistSaving = ref(false)
const checklistData = ref(null)
const checklistStockOutId = ref(null)
const checklistEditMode = ref(false)

const closeChecklist = () => {
    showChecklistModal.value = false
    checklistEditMode.value = false
}

useEscapeKey(() => {
    if (showChecklistModal.value) closeChecklist();
});

const openChecklist = async (item) => {
    checklistStockOutId.value = item.id
    checklistEditMode.value = false
    showChecklistModal.value = true
    checklistLoading.value = true
    try {
        const res = await axios.get(`/audit/profit-checklist/${item.id}`)
        checklistData.value = res.data
    } catch (e) {
        console.error('Failed to load profit checklist', e)
        alert('Gagal memuat checklist: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistLoading.value = false
    }
}

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
            answers: answeredQuestions.map(q => ({
                question_id: q.question_id,
                answer: q.answer,
                notes: q.notes || null,
                content: q.content
            }))
        }
        const res = await axios.post(`/audit/profit-checklist/${checklistStockOutId.value}`, payload)

        // Update the score in the table
        const item = profitRecords.value.daily_sales.data.find(s => s.id === checklistStockOutId.value)
        if (item) {
            item.audit_score = res.data.score
            item.audit_answered = res.data.answered
            item.audit_total = res.data.total
        }

        // Update modal data
        checklistData.value.score = res.data.score
        checklistData.value.answered = res.data.answered
        checklistData.value.total = res.data.total

        alert('Checklist profit berhasil disimpan!')
    } catch (e) {
        console.error('Failed to save checklist', e)
        alert('Gagal menyimpan: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistSaving.value = false
    }
}

// Harga Modal editing per item
// editableModal structure: { stock_out_id: { detail_id: value, detail_id_2: value } }
const editableModal = reactive({})
const savingModalId = ref(null)

const initEditableModal = () => {
    if (!profitRecords.value.daily_sales?.data) return;
    profitRecords.value.daily_sales.data.forEach(item => {
        editableModal[item.id] = {}
        if (item.items) {
            item.items.forEach(detail => {
                editableModal[item.id][detail.id] = detail.harga_modal != null ? Number(detail.harga_modal) : null
            })
        }
    })
}

// Format display for modal input (shows rupiah-formatted number)
const formatModalDisplay = (stockOutId, detailId, defaultVal) => {
    const val = editableModal[stockOutId]?.[detailId]
    if (val != null && val !== '') {
        return formatNumber(val)
    }
    return ''
}

// Handle typing in harga modal input - strip non-digits, store raw number
const onModalInput = (event, item, detail) => {
    const raw = event.target.value.replace(/[^0-9]/g, '')
    const num = raw ? parseInt(raw, 10) : null

    if (!editableModal[item.id]) editableModal[item.id] = {}
    editableModal[item.id][detail.id] = num

    // Reformat the display
    event.target.value = num != null ? formatNumber(num) : ''
}

const onModalFocus = (event, item, detail) => {
    // On focus, show raw number for easy editing
    const val = editableModal[item.id]?.[detail.id]
    if (val != null) {
        event.target.value = val.toString()
    }
}

const onModalBlur = (event, item, detail) => {
    // On blur, reformat to rupiah
    const val = editableModal[item.id]?.[detail.id]
    if (val != null) {
        event.target.value = formatNumber(val)
    } else {
        event.target.value = ''
    }
}

const getEffectiveProfit = (item, detail) => {
    const hargaJual = Number(detail.harga_jual) || 0
    const hargaModal = editableModal[item.id]?.[detail.id] ?? Number(detail.harga_modal) ?? Number(detail.default_harga_modal) ?? 0
    return hargaJual - hargaModal
}

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
        const res = await axios.post(`/audit/profit/${item.id}`, {
            items_modal: itemsModalPayload
        })

        // Update item total properties
        item.harga_modal = res.data.harga_modal
        item.profit = res.data.profit

        // Update individual item properties safely
        if (item.items) {
            item.items.forEach(detail => {
                detail.harga_modal = res.data.items_modal[detail.id]
                detail.has_saved_modal = true
                detail.profit = detail.harga_jual - detail.harga_modal
            })
        }
    } catch (e) {
        console.error('Failed to save harga modal', e)
        alert('Gagal menyimpan harga modal: ' + (e.response?.data?.message || e.message))
    } finally {
        savingModalId.value = null
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
        link.setAttribute('download', `audit-profit-export-${new Date().toISOString().split('T')[0]}.csv`);
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
    category: 'all'
})

const locations = ref([])
const selectedLocationKey = ref('all')

// Summary computeds
const totalHargaJual = computed(() =>
    (profitRecords.value.daily_sales?.data || []).reduce((sum, item) => sum + (Number(item.harga_jual) || 0), 0)
)
const totalHargaModal = computed(() =>
    (profitRecords.value.daily_sales?.data || []).reduce((sum, item) => {
        let itemModal = 0;
        if (item.items && item.items.length > 0) {
            item.items.forEach(detail => {
                const modalVal = editableModal[item.id]?.[detail.id] ?? Number(detail.harga_modal) ?? Number(detail.default_harga_modal) ?? 0;
                itemModal += Number(modalVal) || 0;
            });
        } else {
            itemModal = Number(item.harga_modal) || Number(item.default_harga_modal) || 0;
        }
        return sum + itemModal;
    }, 0)
)
const totalProfit = computed(() => totalHargaJual.value - totalHargaModal.value)

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';

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
    } else {
        handleMonthChange();
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
    return privilegedRoles.some(r => role.includes(r));
})

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

        const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk'];
        const isAlwaysGlobal = alwaysGlobalRoles.some(r => role.includes(r));

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

        if (isAlwaysGlobal) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                if (loc.type === 'distributor') return allowedDistributorIds.includes(Number(loc.id));
                return false;
            });
            if (locations.value.length === 1 && selectedLocationKey.value === 'all') {
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
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
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
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;

    if (canFilterBranch.value && locations.value.length === 0) {
        await fetchBranches()
    }

    fetchData()
})

watch(() => authStore.user, async (newUser) => {
    if (newUser && canFilterBranch.value) {
        await fetchBranches();
    }
});
</script>
