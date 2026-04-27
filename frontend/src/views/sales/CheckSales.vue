<template>
    <div class="space-y-6">
        <!-- Header & Filters -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary">Cek Penjualan</h1>
                <p class="text-text-secondary text-sm mt-1">Lihat riwayat penjualan dari cabang Anda</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <!-- Location Filter -->
                <div v-if="canFilterBranch" class="relative min-w-[200px]">
                    <select v-model="selectedLocationKey" @change="handleLocationChange"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                        <option value="all">Semua Cabang/Toko</option>
                        <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                            :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                            {{ loc.type === 'branch' ? '[Cabang]' : '[Online]' }} {{ loc.name }}
                        </option>
                    </select>
                    <ChevronDown :size="16"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                </div>

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
                        :min="getMinDate" :max="getTodayLocal()"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                </div>

                <!-- Monthly: Month & Year Selectors -->
                <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedMonth" @change="handleMonthChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                            <option v-for="m in restrictedMonths" :key="m.value" :value="m.value">{{ m.name }}</option>
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
                <p class="text-2xl font-bold text-text-primary mt-1">{{ formatCurrency(totalSales) }}</p>
            </div>
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-4 border border-gray-100 dark:border-surface-700 shadow-sm">
                <p class="text-text-secondary text-xs font-medium uppercase tracking-wider">Total Unit</p>
                <p class="text-2xl font-bold text-primary-500 mt-1">{{ totalUnits }}</p>
            </div>
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-4 border border-gray-100 dark:border-surface-700 shadow-sm">
                <p class="text-text-secondary text-xs font-medium uppercase tracking-wider">Lunas</p>
                <p class="text-2xl font-bold text-emerald-500 mt-1">{{ formatCurrency(totalLunas) }}</p>
            </div>
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-4 border border-gray-100 dark:border-surface-700 shadow-sm">
                <p class="text-text-secondary text-xs font-medium uppercase tracking-wider">Belum Lunas</p>
                <p class="text-2xl font-bold text-amber-500 mt-1">{{ formatCurrency(totalBelumLunas) }}</p>
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
                            <th class="px-6 py-4">Harga</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Akun / Catatan</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Distributor</th>
                            <th class="px-6 py-4">Aksi</th>
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
                        <tr v-else-if="(salesRecords.daily_sales?.data || salesRecords.daily_sales).length === 0">
                            <td colspan="14" class="px-6 py-12 text-center text-text-secondary">
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
                            <template v-for="(item, index) in (salesRecords.daily_sales?.data || salesRecords.daily_sales)" :key="index">
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
                                        <div v-if="detail.ram || detail.storage" class="text-[10px] text-gray-500">
                                            {{ [...new Set([detail.ram, detail.storage].filter(Boolean))].join('/') }}
                                        </div>
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
                                    <td class="px-6 py-4 font-bold text-emerald-600 whitespace-nowrap">{{ formatCurrency(detail.price) }}</td>
                                    <td class="px-6 py-4 font-black text-text-primary whitespace-nowrap">{{ formatCurrency(detail.price * detail.qty) }}</td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <div class="flex flex-col gap-1.5 items-start">
                                            <!-- Account Priority: inventory_user_name -> sales_account -> 9090 Mask -> PIN Mask -->
                                            <div v-if="item.inventory_user_name || item.sales_account"
                                                class="px-2.5 py-1 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400 rounded-lg text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 border border-primary-100 dark:border-primary-500/20">
                                                <User :size="12" stroke-width="2.5" /> 
                                                {{ item.inventory_user_name || item.sales_account }}
                                            </div>
                                            <div v-else-if="String(item.transaction_pin) === '9090'"
                                                class="px-2.5 py-1 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400 rounded-lg text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 border border-primary-100 dark:border-primary-500/20">
                                                <User :size="12" stroke-width="2.5" /> Akun Inventory
                                            </div>
                                            <div v-else-if="item.transaction_pin"
                                                class="px-2.5 py-1 bg-surface-100 dark:bg-surface-800 text-text-secondary rounded-lg text-[10px] font-bold italic uppercase tracking-wider border border-surface-200 dark:border-surface-700">
                                                PIN disembunyikan
                                            </div>

                                            <div v-if="item.notes" class="text-xs text-text-primary leading-tight px-0.5"
                                                :title="item.notes">
                                                {{ item.notes }}
                                            </div>
                                            <div v-else-if="!item.inventory_user_name && !item.sales_account && String(item.transaction_pin) !== '9090'" 
                                                class="text-text-secondary italic text-xs px-0.5">
                                                Tanpa Catatan
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                            :class="item.category === 'cancel_penjualan'
                                                ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20'
                                                : item.status === 'Lunas'
                                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                                    : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                            {{ item.category === 'cancel_penjualan' ? 'Dibatalkan' : item.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-medium text-text-secondary italic">
                                            {{ detail.distributor_name || 'KOSONG' }}
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
                                            <button v-if="item.category !== 'cancel_penjualan' && canCancel(item.created_at || item.date)" @click="handleCancelSale(item)"
                                                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors"
                                                title="Batalkan Penjualan">
                                                <Trash2 :size="18" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Single item row -->
                                <tr v-else
                                    class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors text-text-primary">
                                    <td class="px-6 py-4 text-text-primary">{{ index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ formatDate(item.date) }}</td>
                                    <td class="px-6 py-4 font-mono text-xs text-text-primary">{{ item.order_no }}</td>
                                    <td class="px-6 py-4 font-medium">{{ item.customer_name }}</td>
                                    <td class="px-6 py-4 text-text-primary">{{ item.customer_wa || item.customer_phone }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                            {{ categoryLabels[item.category] || item.category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-text-primary">{{ item.product_names || '-' }}</td>
                                    <td class="px-6 py-4 font-mono text-xs text-blue-500">{{ item.imeis && item.imeis
                                        !== '-' ? item.imeis : '-' }}</td>
                                    <td class="px-6 py-4 font-bold text-text-primary">{{ item.qty }}</td>
                                    <td class="px-6 py-4 font-bold text-emerald-600 whitespace-nowrap">{{ formatCurrency(item.grand_total / (item.qty || 1)) }}</td>
                                    <td class="px-6 py-4 font-black text-text-primary whitespace-nowrap">{{ formatCurrency(item.grand_total) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span v-if="String(item.transaction_pin) === '9090'" class="text-xs font-bold text-primary-500">
                                                Akun Inventory
                                            </span>
                                            <span v-if="item.notes"
                                                class="text-xs text-text-primary leading-tight"
                                                :title="item.notes">{{ item.notes }}</span>
                                            <span v-else-if="String(item.transaction_pin) !== '9090'" class="text-text-secondary italic text-xs">
                                                Tanpa Catatan
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                            :class="item.category === 'cancel_penjualan'
                                                ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20'
                                                : item.status === 'Lunas'
                                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                                    : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                            {{ item.category === 'cancel_penjualan' ? 'Dibatalkan' : item.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-medium text-text-secondary italic">
                                            {{ item.distributor_name || item.items?.[0]?.distributor_name || 'KOSONG' }}
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
                                            <button v-if="item.category !== 'cancel_penjualan' && canCancel(item.created_at || item.date)" @click="handleCancelSale(item)"
                                                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors"
                                                title="Batalkan Penjualan">
                                                <Trash2 :size="18" />
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

        <!-- Unified Receipt Modal -->
        <ReceiptModal :is-open="showReceiptModal" :transaction="currentReceiptData" @close="showReceiptModal = false" />

        <!-- Cancel Sale Modal -->
        <CancelSaleModal :show="showCancelModal" :sale="selectedSaleForCancel" @close="showCancelModal = false" @success="fetchData" />
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
import { Loader2, FileText, ChevronDown, Calendar, Image, Printer, X, Download, Trash2, AlertCircle } from 'lucide-vue-next'
import axios from '../../api/axios'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'
import CancelSaleModal from '../../components/modals/CancelSaleModal.vue'
import { getLogicalDate, getTodayLocal } from '../../utils/formatters'

import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()
const loading = ref(false)
const selectedPeriod = ref('daily')

const categoryLabels = {
    'shopee': 'Shopee',
    'orderan_online': 'Order Online',
    'penjualan_offline': 'Penjualan Offline',
    'pindah_cabang': 'Pindah Cabang',
    'retur': 'Retur',
    'cancel_penjualan': 'Dibatalkan'
};

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];// helpers getLogicalDate and getTodayLocal are now imported

const selectedMonth = ref(getLogicalDate().getMonth() + 1);
const selectedYear = ref(getLogicalDate().getFullYear());

const salesRecords = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

const locations = ref([])
const selectedLocationKey = ref('all')

const selectedBranchId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('B:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const selectedOnlineShopId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('S:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return privilegedRoles.some(r => role.includes(r));
})

const fetchLocations = async () => {
    try {
        const [branchRes, shopRes, userRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/user')
        ])

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allLocations = [...allBranches, ...allShops];

        const user = userRes.data.user || userRes.data.data || userRes.data;
        const role = (authStore.userRole || '').toLowerCase();

        const isGlobalRole = privilegedRoles.some(r => role.includes(r));

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);

        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0;

        if (isGlobalRole || (['leader'].includes(role) && !hasAnyRestriction)) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                return false;
            });
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const handleLocationChange = () => {
    fetchData()
}

// Modals State
const showProofModal = ref(false)
const currentProofUrl = ref('')
const showReceiptModal = ref(false)
const currentReceiptData = ref(null)
const showCancelModal = ref(false)
const selectedSaleForCancel = ref(null)

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

const handleCancelSale = (item) => {
    selectedSaleForCancel.value = item;
    showCancelModal.value = true;
}

const years = computed(() => {
    const d = getLogicalDate();
    const currentYear = d.getFullYear();
    const role = (authStore.userRole || '').toLowerCase();
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
    const isRestricted = !privilegedRoles.some(r => role.includes(r));
    if (!isRestricted) return null;

    const d = getLogicalDate();
    d.setDate(d.getDate() - 7); // Allow past 7 days
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
});

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
const activeRecords = computed(() => {
    const list = salesRecords.value.daily_sales?.data || salesRecords.value.daily_sales || []
    return Array.isArray(list) ? list.filter(item => item.category !== 'cancel_penjualan') : []
})
const totalSales = computed(() => activeRecords.value.reduce((sum, item) => sum + (parseFloat(item.grand_total) || 0), 0))
const totalUnits = computed(() => activeRecords.value.filter(item => !['refund', 'angkat_barang'].includes(item.category)).reduce((sum, item) => sum + (parseInt(item.qty) || 0), 0))
const totalLunas = computed(() => activeRecords.value.filter(item => item.status === 'Lunas').reduce((sum, item) => sum + (parseFloat(item.grand_total) || 0), 0))
const totalBelumLunas = computed(() => activeRecords.value.filter(item => item.status !== 'Lunas').reduce((sum, item) => sum + (parseFloat(item.grand_total) || 0), 0))

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(val || 0);
}

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

const canCancel = (date) => {
    // Roles that can always cancel regardless of date
    const role = (authStore.userRole || '').toLowerCase();
    if (role === 'super_admin' || role === 'owner') return true;
    
    if (!date) return false;
    
    // Normalisasi waktu ke WITA/WIB sesuai zona server (UTC+7/8) atau ke local midnight
    // item.date formatnya adalah "YYYY-MM-DD HH:mm:ss" dari backend
    const itemDate = new Date(date);
    if (isNaN(itemDate.getTime())) return false;

    const today = getLogicalDate();
    
    // Reset ke jam 00:00:00 untuk perbandingan hari yang murni
    today.setHours(0, 0, 0, 0);
    itemDate.setHours(0, 0, 0, 0);
    
    const msPerDay = 24 * 60 * 60 * 1000;
    const diffDays = Math.round((today.getTime() - itemDate.getTime()) / msPerDay);
    
    // Jika hari ini tanggal 6, maka:
    // 6 - 6 = 0 (OK)
    // 6 - 1 = 5 (OK)
    // 6 - 31 = 6 (BLOCKED)
    return diffDays <= 5;
}

const fetchData = async () => {
    loading.value = true
    try {
        const params = { 
            ...filters.value,
            branch_id: selectedBranchId.value,
            online_shop_id: selectedOnlineShopId.value
        };
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
    fetchLocations()
    fetchData()
})
</script>
