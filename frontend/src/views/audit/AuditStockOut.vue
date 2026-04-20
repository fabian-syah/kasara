<template>
    <div class="space-y-8">
        <!-- Section: Audit Barang Keluar -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        Audit Barang Keluar
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Review dan audit barang keluar (Inventory & Transfer)
                    </p>
                </div>

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
                        <input type="date" v-model="filters.start_date" @change="handleDateChange"
                            @click="$event.target.showPicker()"
                            :min="isPrivileged ? undefined : getMinDate()"
                            :max="isPrivileged ? undefined : getMaxDate()"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                    </div>

                    <!-- Monthly: Month & Year Selectors (Modern UI) -->
                    <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                        <!-- Month Selector -->
                        <div class="relative min-w-[140px]">
                            <select v-model="selectedMonth" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="(m, i) in restrictedMonths" :key="i" :value="i + 1" :disabled="!isPrivileged && m.disabled">{{ m.name || m }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>

                        <!-- Year Selector -->
                        <div class="relative min-w-[100px]">
                            <select v-model="selectedYear" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="y in restrictedYears" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>
                    </div>

                    <!-- Branch Filter -->
                    <div v-if="canFilterBranch && locations.length > 1" class="relative min-w-[200px]">
                        <select v-model="selectedLocationKey" @change="fetchData"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="all">Semua Lokasi</option>
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
                        class="px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-100 dark:border-surface-700 rounded-xl flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                        <span class="text-xs font-bold text-text-secondary">
                            {{ locations[0].type === 'branch' ? '[Cabang]' : locations[0].type === 'online_shop' ? '[Toko]' : '[Gudang]' }}
                        </span>
                        <span class="text-sm font-bold text-text-primary">{{ locations[0].name }}</span>
                    </div>

                    <!-- Category Filter -->
                    <div class="relative min-w-[160px]">
                        <select v-model="filters.category" @change="fetchData"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="all">Semua Kategori</option>
                            <option value="penjualan_store">Penjualan Store</option>
                            <option value="orderan_online">Orderan Online</option>
                            <option value="bundling">Bundling</option>
                            <option value="tukar_unit">Tukar Unit</option>
                            <option value="tukar_tambah">Tukar Tambah</option>
                            <option value="downgrade">Downgrade</option>
                            <option value="pindah_cabang">Pindah Cabang</option>
                            <option value="rusak">Rusak</option>
                            <option value="mati">Mati</option>
                            <option value="hilang">Hilang</option>
                            <option value="retur_distributor">Retur Distributor</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">
                        Total Transaksi
                    </p>
                    <p class="text-lg font-bold text-text-primary">
                        {{ stockOutRecords.total || 0 }}
                    </p>
                </div>
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">
                        Belum Diaudit (Hal ini)
                    </p>
                    <p class="text-lg font-bold text-amber-500">
                        {{ (stockOutRecords.data || []).filter((r) => r.audit_score === null).length }}
                    </p>
                </div>
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">
                        Sudah Diaudit (Hal ini)
                    </p>
                    <p class="text-lg font-bold text-emerald-500">
                        {{ (stockOutRecords.data || []).filter((r) => r.audit_score !== null).length }}
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
                                <th class="px-4 py-4">ID Transaksi</th>
                                <th class="px-4 py-4">Lokasi</th>
                                <th class="px-4 py-4">Kategori</th>
                                <th class="px-4 py-4">Tipe</th>
                                <th class="px-4 py-4">Brand</th>
                                <th class="px-4 py-4">Nama Produk</th>
                                <th class="px-4 py-4">IMEI</th>
                                <th class="px-4 py-4">Kapasitas</th>
                                <th class="px-4 py-4">Kondisi</th>
                                <th class="px-4 py-4">Qty</th>
                                <th class="px-4 py-4">Sumber</th>
                                <th class="px-4 py-4 text-center">Score Audit</th>
                                <th class="px-4 py-4 text-center">#</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading">
                                <td colspan="14" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center text-text-secondary">
                                        <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                        <span class="text-sm font-medium">Memuat data barang keluar...</span>
                                    </div>
                                </td>
                            </tr>
                             <tr v-else-if="!stockOutRecords.data || stockOutRecords.data.length === 0">
                                <td colspan="14" class="px-6 py-12 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                            <PackageSearch class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">Tidak ada data barang
                                            keluar</span>
                                        <span class="text-xs mt-1">Belum ada aktivitas pada periode ini</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in stockOutRecords.data" :key="item.id"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors group text-text-primary">
                                <td class="px-4 py-4 text-text-secondary font-medium">
                                    {{ (stockOutRecords.current_page - 1) * stockOutRecords.per_page + index + 1 }}
                                </td>
                                <td class="px-4 py-4 font-medium text-text-primary text-xs whitespace-nowrap">
                                    {{ formatDate(item.date) }}
                                </td>
                                <td class="px-4 py-4 text-text-primary font-medium text-xs">
                                    {{ item.receipt_id }}
                                </td>
                                <td class="px-4 py-4 text-xs font-semibold text-text-secondary">
                                    {{ item.outlet_name || '-' }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center">
                                        <span
                                            v-if="item.category === 'hilang'"
                                            class="flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg bg-red-50 text-red-600 dark:bg-red-500/20 dark:text-red-400 border border-red-100 dark:border-red-500/30 whitespace-nowrap animate-pulse shadow-sm shadow-red-200/50 dark:shadow-none"
                                        >
                                            <AlertTriangle :size="12" class="text-red-500" />
                                            {{ getCategoryLabel(item.category) }}
                                        </span>
                                        <span
                                            v-else
                                            class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400 border border-purple-100 dark:border-purple-500/20 whitespace-nowrap"
                                        >
                                            {{ getCategoryLabel(item.category) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 font-medium text-xs">{{ item.type }}</td>
                                <td class="px-4 py-4 text-xs font-semibold text-text-secondary">
                                    {{ item.brand_names }}
                                </td>
                                <td class="px-4 py-4 text-xs font-medium text-text-secondary">
                                    {{ item.product_names }}
                                </td>
                                <td class="px-4 py-4 text-xs font-mono text-blue-500">
                                    {{ item.imeis }}
                                </td>
                                <td class="px-4 py-4 text-xs text-text-secondary">
                                    {{ item.storages || '-' }}
                                </td>
                                <td class="px-4 py-4">
                                    <span v-if="item.conditions" class="px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                        :class="item.conditions === 'Baru' ? 'bg-emerald-500/10 text-emerald-500' : item.conditions === 'Ex iBox' ? 'bg-purple-500/10 text-purple-500' : 'bg-amber-500/10 text-amber-500'"
                                    >{{ item.conditions }}</span>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-4 text-text-primary font-semibold">
                                    {{ item.qty }}
                                </td>
                                <td class="px-4 py-4 text-xs font-medium">
                                    {{ item.source }}
                                </td>
                                <!-- Audit Score -->
                                <td class="px-4 py-4 text-center">
                                    <span v-if="['penjualan_offline', 'orderan_online'].includes(item.category)"
                                        class="text-xs text-gray-400">-</span>
                                    <span v-else-if="item.audit_score === null" class="text-xs text-gray-400">-</span>
                                    <span v-else-if="item.audit_score === 100"
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20">100%
                                        ✅</span>
                                    <span v-else
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20">{{
                                            item.audit_score }}% ⚠️</span>
                                </td>
                                <!-- Actions -->
                                <td class="px-4 py-4 text-center">
                                    <button v-if="!['penjualan_offline', 'orderan_online'].includes(item.category)"
                                        @click="openChecklist(item)"
                                        class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                        title="Cek Audit Barang Keluar">
                                        <ClipboardCheck :size="16" />
                                    </button>
                                    <span v-else class="text-xs text-gray-400 italic">History</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="stockOutRecords.total > 0"
                class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-between items-center bg-gray-50/50 dark:bg-surface-700/30">
                <span class="text-xs text-text-secondary font-medium">
                    Menampilkan {{ (stockOutRecords.current_page - 1) * stockOutRecords.per_page + 1 }} -
                    {{ Math.min(stockOutRecords.current_page * stockOutRecords.per_page, stockOutRecords.total) }}
                    dari {{ stockOutRecords.total }} data
                </span>
                <div class="flex gap-2">
                    <button @click="fetchData(stockOutRecords.current_page - 1)"
                        :disabled="stockOutRecords.current_page === 1 || loading"
                        class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                        <ChevronLeft :size="18" />
                    </button>
                    <div class="flex items-center px-3 text-sm font-bold border-x dark:border-surface-600 px-4 text-text-primary">
                        {{ stockOutRecords.current_page }} / {{ stockOutRecords.last_page }}
                    </div>
                    <button @click="fetchData(stockOutRecords.current_page + 1)"
                        :disabled="stockOutRecords.current_page === stockOutRecords.last_page || loading"
                        class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                        <ChevronRight :size="18" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Audit Checklist Modal -->
        <Teleport to="body">
            <div v-if="showChecklistModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeChecklist"></div>
                <div
                    class="relative bg-white dark:!bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 w-full max-w-lg shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div
                        class="px-6 py-4 border-b border-gray-100 dark:border-surface-700 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                Cek Audit Barang Keluar
                            </h3>
                            <p class="text-sm text-gray-500 mt-0.5">
                                Kategori:
                                <span class="font-semibold text-purple-600 dark:text-purple-400">{{
                                    checklistData?.category
                                }}</span>
                                — {{ checklistData?.answered }}/{{ checklistData?.total }} dijawab
                                <span v-if="checklistData?.score !== undefined" class="font-semibold" :class="checklistData.score === 100
                                    ? 'text-emerald-600'
                                    : 'text-amber-600'
                                    ">
                                    ({{ checklistData.score }}%)
                                </span>
                            </p>
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
                            Belum ada pertanyaan untuk kategori
                            <strong>{{ checklistData?.category }}</strong>.
                        </div>
                        <div v-else v-for="(q, i) in checklistData.questions" :key="i"
                            class="flex flex-col gap-2 p-4 rounded-xl border transition-all" :class="q.answer === true
                                ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5'
                                : q.answer === false
                                    ? 'border-red-200 dark:border-red-500/30 bg-red-50/50 dark:bg-red-500/5'
                                    : 'border-gray-200 dark:border-surface-600 bg-gray-50/50 dark:bg-surface-700/30'
                                ">
                            <div class="flex items-start gap-4">
                                <span class="text-sm font-bold text-gray-400 mt-0.5">{{ i + 1 }}.</span>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ q.content }}
                                    </p>
                                    <p v-if="q.is_deleted" class="text-[10px] text-red-400 mt-0.5 italic">
                                        Pertanyaan ini sudah dihapus/diubah
                                    </p>
                                </div>

                                <!-- READ-ONLY mode -->
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

                                <!-- EDIT mode -->
                                <div v-else class="flex gap-2 flex-shrink-0">
                                    <button @click="setAnswer(i, true)"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all" :class="q.answer === true
                                            ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30'
                                            : 'bg-gray-100 dark:bg-surface-600 text-gray-500 dark:text-gray-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 hover:text-emerald-600'
                                            ">
                                        Yes
                                    </button>
                                    <button @click="setAnswer(i, false)"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all" :class="q.answer === false
                                            ? 'bg-red-500 text-white shadow-lg shadow-red-500/30'
                                            : 'bg-gray-100 dark:bg-surface-600 text-gray-500 dark:text-gray-400 hover:bg-red-100 dark:hover:bg-red-500/20 hover:text-red-600'
                                            ">
                                        No
                                    </button>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div v-if="checklistEditMode" class="ml-8">
                                <textarea v-model="q.notes" rows="2" placeholder="Catatan (opsional)..."
                                    class="w-full text-xs px-3 py-2 rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-700 text-gray-700 dark:text-gray-300 placeholder-gray-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all resize-none">
                </textarea>
                            </div>
                            <div v-else-if="q.notes" class="ml-8">
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:!bg-surface-700/50 px-3 py-2 rounded-lg">
                                    <span class="font-medium text-gray-600 dark:text-gray-300">Catatan:</span>
                                    {{ q.notes }}
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
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Loader2,
    ChevronDown,
    Calendar,
    ClipboardCheck,
    Pencil,
    PackageSearch,
    ChevronLeft,
    ChevronRight,
    AlertTriangle,
} from 'lucide-vue-next';
import axios from '../../api/axios';
import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const isLeader = computed(() => (authStore.userRole || '').toLowerCase() === 'leader');

const loading = ref(false);
const stockOutRecords = ref({
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 50
});

const getCategoryLabel = (val) => {
    const categories = {
        'penjualan_offline': 'Penjualan Store',
        'orderan_online': 'Penjualan Online',
        'pindah_cabang': 'Keluar Pindah Cabang',
        'retur': 'Keluar Retur',
        'kesalahan_input': 'Keluar Salah Input',
        'giveaway_customer': 'Giveaway Customer',
        'hadiah': 'Keluar Hadiah',
        'brand_ambassador': 'Brand Ambassador',
        'promo': 'Keluar Promo',
        'inventaris': 'Keluar Inventaris',
        'event_sponsorship': 'Event / Sponsorship',
        'hilang': 'Hilang / Dicuri',
    };
    return categories[val] || val;
};

// Audit Checklist Modal State
const showChecklistModal = ref(false);
const checklistLoading = ref(false);
const checklistSaving = ref(false);
const checklistData = ref(null);
const checklistStockOutId = ref(null);
const checklistEditMode = ref(false);

const closeChecklist = () => {
    showChecklistModal.value = false;
    checklistEditMode.value = false;
};

useEscapeKey(() => {
    if (showChecklistModal.value) closeChecklist();
});

const openChecklist = async (item) => {
    checklistStockOutId.value = item.id;
    checklistEditMode.value = false;
    showChecklistModal.value = true;
    checklistLoading.value = true;
    try {
        const res = await axios.get(`/audit/stock-out-checklist/${item.id}`);
        checklistData.value = res.data;
    } catch (e) {
        console.error('Failed to load stock-in checklist', e);
        alert(
            'Gagal memuat checklist: ' + (e.response?.data?.message || e.message)
        );
    } finally {
        checklistLoading.value = false;
    }
};

const setAnswer = (index, value) => {
    if (checklistData.value?.questions?.[index]) {
        checklistData.value.questions[index].answer = value;
    }
};

const saveChecklist = async () => {
    if (!checklistData.value?.questions) return;

    const answeredQuestions = checklistData.value.questions.filter(
        (q) => q.answer !== null
    );
    if (answeredQuestions.length === 0) {
        alert('Silakan jawab minimal 1 pertanyaan');
        return;
    }

    checklistSaving.value = true;
    try {
        const payload = {
            answers: answeredQuestions.map((q) => ({
                question_id: q.question_id,
                answer: q.answer,
                notes: q.notes || null,
                content: q.content,
            })),
        };
        const res = await axios.post(
            `/audit/stock-out-checklist/${checklistStockOutId.value}`,
            payload
        );

        // Update the score in the table
        const item = stockOutRecords.value.data.find(
            (s) => s.id === checklistStockOutId.value
        );
        if (item) {
            item.audit_score = res.data.score;
            item.audit_answered = res.data.answered;
            item.audit_total = res.data.total;
        }

        // Update modal data
        checklistData.value.score = res.data.score;
        checklistData.value.answered = res.data.answered;
        checklistData.value.total = res.data.total;

        alert('Checklist barang keluar berhasil disimpan!');
    } catch (e) {
        console.error('Failed to save checklist', e);
        alert('Gagal menyimpan: ' + (e.response?.data?.message || e.message));
    } finally {
        checklistSaving.value = false;
    }
};

const getLogicalDate = () => {
    const d = new Date();
    if (d.getHours() < 5) d.setDate(d.getDate() - 1);
    return d;
};

const getTodayLocal = () => {
    const d = getLogicalDate();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const isPrivileged = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some(r => role.includes(r));
});

const restrictedMonths = computed(() => {
    if (isPrivileged.value) return months.map(m => ({ name: m, disabled: false }));
    
    const d = getLogicalDate();
    const currentM = d.getMonth(); // 0-indexed
    const prevM = (currentM === 0) ? 11 : currentM - 1;
    
    return months.map((m, i) => ({
        name: m,
        disabled: i !== currentM && i !== prevM
    }));
});

const restrictedYears = computed(() => {
    if (isPrivileged.value) return years;
    const d = getLogicalDate();
    return [d.getFullYear()];
});

const getMinDate = () => {
    const d = getLogicalDate();
    d.setDate(d.getDate() - 7); // Allow past 7 days
    return d.toISOString().split('T')[0];
};

const getMaxDate = () => {
    const d = getLogicalDate();
    return d.toISOString().split('T')[0];
};


const filters = ref({
    start_date: getTodayLocal(),
    end_date: getTodayLocal(),
    branch_id: null,
    category: 'all'
});

// Daily vs Monthly state variables
const selectedPeriod = ref('daily'); // 'daily' | 'monthly'
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

const locations = ref([]);
const selectedLocationKey = ref('all');

const formattedDateDisplay = computed(() => {
    if (selectedPeriod.value === 'daily') {
        if (!filters.value.start_date) return 'Pilih Tanggal';
        const date = new Date(filters.value.start_date);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }
    return '';
});

const handleDateChange = () => {
    filters.value.end_date = filters.value.start_date;
    fetchData();
};

const handleMonthChange = () => {
    if (selectedPeriod.value === 'monthly') {
        const year = selectedYear.value;
        const month = String(selectedMonth.value).padStart(2, '0');
        // Set to start of month and end of month
        const lastDay = new Date(year, selectedMonth.value, 0).getDate();
        filters.value.start_date = `${year}-${month}-01`;
        filters.value.end_date = `${year}-${month}-${lastDay}`;
        fetchData();
    }
};

const handlePeriodChange = () => {
    if (selectedPeriod.value === 'daily') {
        filters.value.start_date = getTodayLocal();
        filters.value.end_date = getTodayLocal();
    } else {
        selectedMonth.value = new Date().getMonth() + 1;
        selectedYear.value = new Date().getFullYear();
        handleMonthChange();
    }
    fetchData();
};

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some((r) => role.includes(r));
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).replace(/\./g, ':');
};

const fetchLocations = async () => {
    if (loading.value) return;
    try {
        const [branchRes, shopRes, warehouseRes, distributorRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/warehouses'),
            axios.get('/distributors'),
        ]);

        const allBranches = (branchRes.data.data || branchRes.data || []).map(
            (b) => ({ ...b, type: 'branch' })
        );
        const allShops = (shopRes.data.data || shopRes.data || []).map((s) => ({
            ...s,
            type: 'online_shop',
        }));
        const allWarehouses = (
            warehouseRes.data.data ||
            warehouseRes.data ||
            []
        ).map((w) => ({ ...w, type: 'warehouse' }));
        const allDistributors = (
            distributorRes?.data?.data ||
            distributorRes?.data ||
            []
        ).map((d) => ({ ...d, type: 'distributor' }));

        const allLocations = [...allBranches, ...allShops, ...allWarehouses, ...allDistributors];

        const user = authStore.user;
        const role = (authStore.userRole || '').toLowerCase();
        const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk'];
        const isAlwaysGlobal = alwaysGlobalRoles.some(r => role.includes(r));

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);
        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);
        let allowedWarehouseIds = [];
        if (user?.warehouse_id) allowedWarehouseIds.push(user.warehouse_id);
        let allowedDistributorIds = [];
        if (user?.distributor_id) allowedDistributorIds.push(user.distributor_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach((p) => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
                if (p.model_type === 'warehouse') allowedWarehouseIds.push(p.model_id);
                if (p.model_type === 'distributor') allowedDistributorIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map((id) => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map((id) => Number(id)))];
        allowedWarehouseIds = [...new Set(allowedWarehouseIds.map((id) => Number(id)))];
        allowedDistributorIds = [...new Set(allowedDistributorIds.map((id) => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0 || allowedWarehouseIds.length > 0 || allowedDistributorIds.length > 0;

        if (isAlwaysGlobal) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter((loc) => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                if (loc.type === 'warehouse') return allowedWarehouseIds.includes(Number(loc.id));
                if (loc.type === 'distributor') return allowedDistributorIds.includes(Number(loc.id));
                return false;
            });
            if (locations.value.length === 1 && selectedLocationKey.value === 'all') {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`;
            }
        } else if (role.includes('audit') || role.includes('leader') || role.includes('analist')) {
            locations.value = allLocations;
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error);
    }
};

const fetchData = async (page = 1) => {
    loading.value = true;
    try {
        const params = { ...filters.value, page };
        if (selectedLocationKey.value !== 'all') {
            const [type, id] = selectedLocationKey.value.split(':');
            if (type === 'B') params.branch_id = id;
            else if (type === 'S') params.online_shop_id = id;
            else if (type === 'W') params.warehouse_id = id;
            else if (type === 'D') params.distributor_id = id;
        }

        const response = await axios.get('/audit/stock-out', { params });
        stockOutRecords.value = response.data;
    } catch (error) {
        console.error('Error fetching stock-out data:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;

    if (canFilterBranch.value) {
        await fetchLocations();
    }

    fetchData();
});

watch(
    () => authStore.user,
    async (newUser) => {
        if (newUser && canFilterBranch.value) {
            await fetchLocations();
        }
    }
);
</script>
