<template>
    <div class="space-y-6">
        <!-- Header & Filters -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary">Cek Penjualan</h1>
                <p class="text-text-secondary text-sm mt-1">Lihat riwayat penjualan dari cabang Anda</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <!-- Period Filter -->
                <div class="relative min-w-[140px]">
                    <select v-model="selectedPeriod" @change="handlePeriodChange"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
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
                        <Calendar :size="18" class="text-gray-500 dark:text-gray-400 group-hover:text-primary-500" />
                        <span class="text-sm font-medium text-text-primary min-w-[100px]">
                            {{ formattedDateDisplay }}
                        </span>
                    </div>
                    <input type="date" v-model="filters.start_date" @change="handleDateChange"
                        @click="$event.target.showPicker()"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                </div>

                <!-- Monthly: Month & Year Selectors -->
                <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedMonth" @change="handleMonthChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                            <option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <div class="relative min-w-[100px]">
                        <select v-model="selectedYear" @change="handleMonthChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-4 border border-gray-100 dark:border-surface-700 shadow-sm">
                <p class="text-text-secondary text-xs font-medium uppercase tracking-wider">Total Penjualan</p>
                <p class="text-2xl font-bold text-text-primary mt-1">{{ totalSales }}</p>
            </div>
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-4 border border-gray-100 dark:border-surface-700 shadow-sm">
                <p class="text-text-secondary text-xs font-medium uppercase tracking-wider">Total Unit</p>
                <p class="text-2xl font-bold text-primary-500 mt-1">{{ totalUnits }}</p>
            </div>
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-4 border border-gray-100 dark:border-surface-700 shadow-sm">
                <p class="text-text-secondary text-xs font-medium uppercase tracking-wider">Lunas</p>
                <p class="text-2xl font-bold text-emerald-500 mt-1">{{ totalLunas }}</p>
            </div>
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-4 border border-gray-100 dark:border-surface-700 shadow-sm">
                <p class="text-text-secondary text-xs font-medium uppercase tracking-wider">Belum Lunas</p>
                <p class="text-2xl font-bold text-amber-500 mt-1">{{ totalBelumLunas }}</p>
            </div>
        </div>

        <!-- Sales Table -->
        <div
            class="bg-white dark:!bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead
                        class="text-xs font-semibold text-text-secondary uppercase bg-gray-50/50 dark:!bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                        <tr>
                            <th class="px-6 py-4">No</th>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">No Pesanan</th>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">No HP</th>
                            <th class="px-6 py-4">Kategori</th>
                            <th class="px-6 py-4">Produk</th>
                            <th class="px-6 py-4">IMEI</th>
                            <th class="px-6 py-4">Qty</th>
                            <th class="px-6 py-4">PIN / Notes</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                        <tr v-if="loading">
                            <td colspan="10" class="px-6 py-12">
                                <div class="flex flex-col items-center justify-center text-text-secondary">
                                    <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                    <span class="text-sm font-medium">Memuat data penjualan...</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="salesRecords.daily_sales.length === 0">
                            <td colspan="10" class="px-6 py-12 text-center text-text-secondary">
                                <div class="flex flex-col items-center justify-center">
                                    <div
                                        class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                        <FileText class="w-6 h-6 text-gray-400" />
                                    </div>
                                    <span class="font-medium text-text-primary">Tidak ada data penjualan</span>
                                    <span class="text-xs mt-1">Belum ada transaksi pada periode ini</span>
                                </div>
                            </td>
                        </tr>
                        <template v-else>
                            <template v-for="(item, index) in salesRecords.daily_sales" :key="index">
                                <!-- If item has sub-items -->
                                <tr v-if="item.items && item.items.length > 0" v-for="(detail, idx) in item.items"
                                    :key="`${index}-${idx}`"
                                    class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors text-text-primary">
                                    <td class="px-6 py-4 text-text-secondary" v-if="idx === 0"
                                        :rowspan="item.items.length">{{ index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium" v-if="idx === 0" :rowspan="item.items.length">{{
                                        formatDate(item.date) }}</td>
                                    <td class="px-6 py-4 font-mono text-xs" v-if="idx === 0"
                                        :rowspan="item.items.length">{{ item.order_no }}</td>
                                    <td class="px-6 py-4 font-medium" v-if="idx === 0" :rowspan="item.items.length">{{
                                        item.customer_name }}</td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">{{
                                        item.customer_wa || item.customer_phone }}</td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <span
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                            {{ categoryLabels[item.category] || item.category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div>{{ detail.name }}</div>
                                        <div v-if="detail.storage" class="text-[10px] text-gray-500">{{ detail.storage
                                        }}</div>
                                        <span v-if="detail.condition"
                                            class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                            :class="detail.condition === 'new' ? 'bg-emerald-500/10 text-emerald-500' : detail.condition === 'ex_ibox' ? 'bg-purple-500/10 text-purple-500' : 'bg-amber-500/10 text-amber-500'">
                                            {{ detail.condition === 'new' ? 'Baru' : detail.condition === 'ex_ibox' ?
                                                'Ex iBox' : 'Second' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-blue-500">{{ detail.imei && detail.imei
                                        !== '-' ? detail.imei : '-' }}</td>
                                    <td class="px-6 py-4 font-bold">{{ detail.qty }}</td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <div v-if="item.transaction_pin" class="flex flex-col">
                                            <span class="text-xs font-mono font-bold text-primary-500">PIN: {{
                                                item.transaction_pin }}</span>
                                            <span v-if="item.notes"
                                                class="text-[10px] text-text-secondary truncate max-w-[150px]"
                                                :title="item.notes">{{ item.notes }}</span>
                                        </div>
                                        <span v-else-if="item.notes"
                                            class="text-[10px] text-text-secondary truncate max-w-[150px]"
                                            :title="item.notes">{{ item.notes }}</span>
                                        <span v-else class="text-text-secondary">-</span>
                                    </td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                            :class="item.status === 'Lunas'
                                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                                : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <div class="flex items-center gap-2">
                                            <button v-if="item.proof_image" @click="viewProof(item.proof_image)"
                                                class="p-2 text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition-colors"
                                                title="Lihat Foto Bukti">
                                                <Image :size="18" />
                                            </button>
                                            <button @click="openReceipt(item)"
                                                class="p-2 text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 rounded-lg transition-colors"
                                                title="Buat Struk">
                                                <Printer :size="18" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Single item row -->
                                <tr v-else
                                    class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors text-text-primary">
                                    <td class="px-6 py-4 text-text-secondary">{{ index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ formatDate(item.date) }}</td>
                                    <td class="px-6 py-4 font-mono text-xs">{{ item.order_no }}</td>
                                    <td class="px-6 py-4 font-medium">{{ item.customer_name }}</td>
                                    <td class="px-6 py-4">{{ item.customer_wa || item.customer_phone }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                            {{ categoryLabels[item.category] || item.category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">{{ item.product_names || '-' }}</td>
                                    <td class="px-6 py-4 font-mono text-xs text-blue-500">{{ item.imeis && item.imeis
                                        !== '-' ? item.imeis : '-' }}</td>
                                    <td class="px-6 py-4 font-bold">{{ item.qty }}</td>
                                    <td class="px-6 py-4">
                                        <div v-if="item.transaction_pin" class="flex flex-col">
                                            <span class="text-xs font-mono font-bold text-primary-500">PIN: {{
                                                item.transaction_pin }}</span>
                                            <span v-if="item.notes"
                                                class="text-[10px] text-text-secondary truncate max-w-[150px]"
                                                :title="item.notes">{{ item.notes }}</span>
                                        </div>
                                        <span v-else-if="item.notes"
                                            class="text-[10px] text-text-secondary truncate max-w-[150px]"
                                            :title="item.notes">{{ item.notes }}</span>
                                        <span v-else class="text-text-secondary">-</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                            :class="item.status === 'Lunas'
                                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                                : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <button v-if="item.proof_image" @click="viewProof(item.proof_image)"
                                                class="p-2 text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition-colors"
                                                title="Lihat Foto Bukti">
                                                <Image :size="18" />
                                            </button>
                                            <button @click="openReceipt(item)"
                                                class="p-2 text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 rounded-lg transition-colors"
                                                title="Buat Struk">
                                                <Printer :size="18" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Proof Photo Modal -->
        <div v-if="showProofModal"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="relative max-w-4xl w-full">
                <button @click="showProofModal = false"
                    class="absolute -top-12 right-0 p-2 text-white hover:text-gray-300 transition-colors">
                    <X :size="32" />
                </button>
                <div class="bg-white dark:bg-surface-800 rounded-2xl overflow-hidden shadow-2xl">
                    <img :src="currentProofUrl" alt="Foto Bukti" class="w-full h-auto max-h-[80vh] object-contain" />
                    <div class="p-4 flex justify-between items-center bg-gray-50 dark:bg-surface-700">
                        <span class="text-sm font-medium text-text-primary">Foto Bukti Pembayaran / Serah Terima</span>
                        <a :href="currentProofUrl" download
                            class="flex items-center gap-2 px-4 py-2 bg-primary-500 text-white rounded-xl hover:bg-primary-600 transition-colors text-sm font-medium">
                            <Download :size="16" />
                            Unduh Foto
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt Modal -->
        <!-- Nota Penjualan Modal (PSTORE Style) -->
        <div v-if="showReceiptModal"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white">
            <div
                class="bg-white dark:bg-surface-800 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl print:shadow-none print:rounded-none print:max-w-full flex flex-col max-h-[90vh]">
                <!-- Modal Header (hide on print) -->
                <div
                    class="p-6 flex justify-between items-center border-b border-gray-100 dark:border-surface-700 print:hidden">
                    <h3 class="text-lg font-bold text-text-primary">Nota Penjualan</h3>
                    <button @click="showReceiptModal = false"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">
                        <X :size="20" class="text-gray-500" />
                    </button>
                </div>

                <!-- Nota Content -->
                <div id="receipt-content"
                    class="flex-1 overflow-y-auto p-6 print:p-0 bg-gray-100/50 dark:bg-surface-900/50 print:bg-white">
                    <div v-if="currentReceiptData"
                        class="nota-paper max-w-[480px] mx-auto bg-white p-6 text-black font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-4 border border-gray-200 print:border-none">

                        <!-- ===== NOTA HEADER ===== -->
                        <div class="flex items-start gap-4 mb-4 pb-4 border-b-2 border-black">
                            <img src="/images/logo-pstore.png" alt="PSTORE"
                                class="w-14 h-14 object-contain shrink-0" />
                            <div class="flex-1 min-w-0">
                                <h2 class="text-2xl font-extrabold tracking-wider text-black leading-none">PSTORE</h2>
                                <p class="text-[9px] leading-tight text-gray-700 mt-1">
                                    Pusat Perbelanjaan Online<br />
                                    HP, Laptop, Barang Elektronik Bergaransi Terjamin Dan Terpercaya
                                </p>
                                <p class="text-[9px] text-gray-600 mt-0.5">
                                    No Customer Service 0851 - 3300 - 5600
                                </p>
                                <div class="flex items-center gap-3 mt-1 text-[9px] text-gray-600">
                                    <span>📷 pstOre.</span>
                                    <span>🎵 pstOre.</span>
                                </div>
                                <div class="mt-2 text-[9px] text-gray-700 border-t border-black/10 pt-1">
                                    <span class="font-bold">Kami ada juga di :</span>
                                    <div class="flex items-center gap-4 mt-0.5">
                                        <span class="flex items-center gap-1">
                                            <span class="text-[10px]">🛒</span> shopee pstore_
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="text-[10px]">🏪</span> tokopedia pstore_
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== INFO NOTA ===== -->
                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs mb-4">
                            <span class="font-semibold text-black">No. Nota</span>
                            <span class="text-black">: {{ currentReceiptData.order_no || '-' }}</span>
                            <span class="font-semibold text-black">Atas Nama</span>
                            <span class="text-black">: {{ currentReceiptData.customer_name || '-' }}</span>
                            <span class="font-semibold text-black">Tanggal</span>
                            <span class="text-black">: {{ formatDate(currentReceiptData.date) }}</span>
                            <span class="font-semibold text-black">No. HP</span>
                            <span class="text-black">: {{ currentReceiptData.customer_wa || currentReceiptData.customer_phone || '-' }}</span>
                        </div>

                        <!-- ===== TABEL ITEMS ===== -->
                        <table class="w-full text-xs border-collapse mb-4">
                            <thead>
                                <tr class="border-t-2 border-b-2 border-black">
                                    <th class="py-2 px-1 text-left font-bold text-black w-[50px]">Banyak</th>
                                    <th class="py-2 px-1 text-left font-bold text-black">IMEI</th>
                                    <th class="py-2 px-1 text-left font-bold text-black">Keterangan</th>
                                    <th class="py-2 px-1 text-right font-bold text-black w-[90px]">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template
                                    v-if="currentReceiptData.items && currentReceiptData.items.length > 0">
                                    <tr v-for="(detail, dIdx) in currentReceiptData.items" :key="dIdx"
                                        class="border-b border-gray-300">
                                        <td class="py-2 px-1 text-black align-top text-center">{{ detail.qty }}</td>
                                        <td class="py-2 px-1 text-black align-top font-mono text-[10px]">
                                            {{ detail.imei && detail.imei !== '-' ? detail.imei : '-' }}
                                        </td>
                                        <td class="py-2 px-1 text-black align-top">
                                            <div class="font-semibold">{{ detail.name }}</div>
                                            <div v-if="detail.storage" class="text-[10px] text-gray-600">{{
                                                detail.storage }}</div>
                                            <div v-if="detail.condition" class="text-[10px] text-gray-600">
                                                {{ detail.condition === 'new' ? 'Baru' : detail.condition === 'ex_ibox' ? 'Ex iBox' : 'Second' }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-1 text-black align-top text-right font-medium">
                                            {{ detail.price ? formatNumber(detail.price * detail.qty) : '-' }}
                                        </td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr class="border-b border-gray-300">
                                        <td class="py-2 px-1 text-black align-top text-center">{{ currentReceiptData.qty || 1 }}</td>
                                        <td class="py-2 px-1 text-black align-top font-mono text-[10px]">
                                            {{ currentReceiptData.imeis && currentReceiptData.imeis !== '-' ? currentReceiptData.imeis : '-' }}
                                        </td>
                                        <td class="py-2 px-1 text-black align-top">
                                            <div class="font-semibold">{{ currentReceiptData.product_names || '-' }}</div>
                                        </td>
                                        <td class="py-2 px-1 text-black align-top text-right font-medium">
                                            {{ currentReceiptData.grand_total ? formatNumber(currentReceiptData.grand_total) : '-' }}
                                        </td>
                                    </tr>
                                </template>
                                <!-- Empty rows for physical nota feel -->
                                <tr v-for="n in Math.max(0, 3 - (currentReceiptData.items?.length || 1))"
                                    :key="'empty-' + n" class="border-b border-gray-300">
                                    <td class="py-3 px-1">&nbsp;</td>
                                    <td class="py-3 px-1"></td>
                                    <td class="py-3 px-1"></td>
                                    <td class="py-3 px-1"></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- ===== PAYMENT SECTION ===== -->
                        <div class="flex justify-end mb-4">
                            <div class="w-[220px] text-xs space-y-1">
                                <div class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black">TF :</span>
                                    <span class="text-black">
                                        {{ currentReceiptData.transfer ? 'Rp ' + formatNumber(currentReceiptData.transfer) : '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black">CASH :</span>
                                    <span class="text-black">
                                        {{ currentReceiptData.cash ? 'Rp ' + formatNumber(currentReceiptData.cash) : '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between border-t-2 border-black pt-1">
                                    <span class="font-extrabold text-black text-sm">TOTAL :</span>
                                    <span class="font-extrabold text-black text-sm">Rp {{
                                        formatNumber(currentReceiptData.grand_total) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- ===== GARANSI NOTES ===== -->
                        <div class="bg-gray-50 border border-gray-300 rounded p-2.5 mb-5 print:bg-white">
                            <ul class="text-[9px] text-gray-700 space-y-0.5 list-disc pl-3">
                                <li>Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
                                <li>Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
                                <li>Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD</li>
                            </ul>
                        </div>

                        <!-- ===== SIGNATURE AREA ===== -->
                        <div class="flex justify-between text-xs mt-6 mb-2">
                            <div class="text-center">
                                <p class="font-semibold text-black mb-12">Penerima,</p>
                                <div class="border-b border-gray-400 w-28"></div>
                            </div>
                            <div class="text-center">
                                <p class="font-semibold text-black mb-12">Hormat Kami,</p>
                                <div class="border-b border-gray-400 w-28"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons (hide on print) -->
                <div class="p-6 bg-gray-50 dark:bg-surface-700/50 flex gap-3 print:hidden">
                    <button @click="printReceipt"
                        class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-primary-500 text-white rounded-2xl font-bold hover:bg-primary-600 transition-all shadow-lg shadow-primary-500/20 active:scale-95">
                        <Printer :size="20" />
                        Cetak Nota
                    </button>
                    <button @click="showReceiptModal = false"
                        class="px-6 py-3 bg-white dark:bg-surface-800 text-text-primary border border-gray-200 dark:border-surface-600 rounded-2xl font-bold hover:bg-gray-50 transition-all active:scale-95">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Force nota-paper to always show black text on white background, even in dark mode */
.nota-paper,
.nota-paper * {
    color: #000 !important;
}

.nota-paper {
    background-color: #fff !important;
}

.nota-paper h2 {
    color: #000 !important;
}

.nota-paper p,
.nota-paper span,
.nota-paper div,
.nota-paper li,
.nota-paper td,
.nota-paper th {
    color: #000 !important;
}

.nota-paper .text-gray-700,
.nota-paper .text-gray-600 {
    color: #374151 !important;
}

.nota-paper table th {
    color: #000 !important;
    font-weight: 700 !important;
}

.nota-paper table td {
    color: #000 !important;
}

.nota-paper .border-black {
    border-color: #000 !important;
}

.nota-paper .border-gray-300 {
    border-color: #d1d5db !important;
}

.nota-paper .border-gray-400 {
    border-color: #9ca3af !important;
}

.nota-paper .bg-gray-50 {
    background-color: #f9fafb !important;
}

.nota-paper .border-gray-200 {
    border-color: #e5e7eb !important;
}

@media print {
    body * {
        visibility: hidden;
    }

    #receipt-content,
    #receipt-content * {
        visibility: visible;
        color: #000 !important;
        background-color: transparent !important;
    }

    #receipt-content .nota-paper {
        background-color: #fff !important;
    }

    #receipt-content {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .nota-paper {
        border: none !important;
        box-shadow: none !important;
        width: 100%;
        max-width: none !important;
        padding: 16px !important;
    }

    .nota-paper table {
        border-collapse: collapse !important;
    }

    .nota-paper table th,
    .nota-paper table td {
        border-color: #000 !important;
        color: #000 !important;
    }

    .nota-paper img {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Loader2, FileText, ChevronDown, Calendar, Image, Printer, X, Download } from 'lucide-vue-next'
import axios from '../../api/axios'

const loading = ref(false)
const selectedPeriod = ref('daily')

const categoryLabels = {
    'shopee': 'Shopee',
    'orderan_online': 'Order Online',
    'penjualan_offline': 'Penjualan Offline',
    'pindah_cabang': 'Pindah Cabang',
    'retur': 'Retur'
};

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(currentYear);

const salesRecords = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

// Modals State
const showProofModal = ref(false)
const currentProofUrl = ref('')
const showReceiptModal = ref(false)
const currentReceiptData = ref(null)

const viewProof = (url) => {
    currentProofUrl.value = url
    showProofModal.value = true
}

const openReceipt = (item) => {
    currentReceiptData.value = item
    showReceiptModal.value = true
}

const printReceipt = () => {
    window.print()
}

const getTodayLocal = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const filters = ref({
    start_date: getTodayLocal(),
    end_date: getTodayLocal(),
})

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';
    if (selectedPeriod.value === 'daily') {
        const date = new Date(filters.value.start_date);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } else {
        const monthIndex = selectedMonth.value - 1;
        return `${months[monthIndex]} ${selectedYear.value}`;
    }
})

// Summary stats
const totalSales = computed(() => salesRecords.value.daily_sales.length)
const totalUnits = computed(() => salesRecords.value.daily_sales.reduce((sum, item) => sum + (parseInt(item.qty) || 0), 0))
const totalLunas = computed(() => salesRecords.value.daily_sales.filter(item => item.status === 'Lunas').length)
const totalBelumLunas = computed(() => salesRecords.value.daily_sales.filter(item => item.status !== 'Lunas').length)

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

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const fetchData = async () => {
    loading.value = true
    try {
        const params = { ...filters.value };
        const response = await axios.get('/audit/sales', { params })
        salesRecords.value = response.data
    } catch (error) {
        console.error('Error fetching sales:', error)
    } finally {
        loading.value = false
    }
}

const formatNumber = (num) => {
    if (!num) return '0'
    return new Number(num).toLocaleString('id-ID')
}

onMounted(() => {
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;
    fetchData()
})
</script>
