<template>
    <div class="space-y-8">
        <!-- Section 1: Penjualan -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Penjualan</h2>

                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Period Filter (Modern UI) -->
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedPeriod" @change="handlePeriodChange"
                            class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Daily: Date Picker (Modern UI) -->
                    <div v-if="selectedPeriod === 'daily'" class="relative group">
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl hover:border-primary-500 hover:ring-2 hover:ring-primary-500/10 transition-all cursor-pointer">
                            <Calendar :size="18"
                                class="text-gray-500 dark:text-gray-400 group-hover:text-primary-500" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200 min-w-[100px]">
                                {{ formattedDateDisplay }}
                            </span>
                        </div>
                        <!-- Use showPicker() explicitly on click to ensure calendar opens consistently -->
                        <input type="date" v-model="filters.start_date" @change="handleDateChange"
                            @click="$event.target.showPicker()"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                    </div>

                    <!-- Monthly: Month & Year Selectors (Modern UI) -->
                    <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                        <!-- Month Selector -->
                        <div class="relative min-w-[140px]">
                            <select v-model="selectedMonth" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>

                        <!-- Year Selector -->
                        <div class="relative min-w-[100px]">
                            <select v-model="selectedYear" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>
                    </div>

                    <!-- Branch Filter (Modern UI) -->
                    <div v-if="canFilterBranch" class="relative min-w-[200px]">
                        <select v-model="selectedLocationKey" @change="fetchData"
                            class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="all">Semua Cabang/Toko</option>
                            <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                                :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                                {{ loc.name }}
                            </option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Export Button -->
                    <button
                        class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold shadow-lg shadow-gray-200 dark:shadow-none hover:transform hover:-translate-y-0.5 transition-all">
                        <Download :size="18" />
                        <span>Export</span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white dark:bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-gray-500 uppercase bg-gray-50/50 dark:bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-6 py-4">No</th>
                                <th class="px-6 py-4">Waktu Pesanan</th>
                                <th class="px-6 py-4">Nomor Pesanan</th>
                                <th class="px-6 py-4">Nama</th>
                                <th class="px-6 py-4">No HP</th>
                                <th class="px-6 py-4">Kategori</th>
                                <th class="px-6 py-4">Tipe</th>
                                <th class="px-6 py-4">Jumlah Barang</th>
                                <th class="px-6 py-4">Status Pembayaran</th>
                                <th class="px-6 py-4">Cash</th>
                                <th class="px-6 py-4">Transfer</th>
                                <th class="px-6 py-4">Debit</th>
                                <th class="px-6 py-4 text-center">#</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading">
                                <td colspan="13" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center text-gray-500">
                                        <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                        <span class="text-sm font-medium">Memuat data penjualan...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="salesRecords.daily_sales.length === 0">
                                <td colspan="13" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                            <FileText class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">Tidak ada data
                                            penjualan</span>
                                        <span class="text-xs mt-1">Belum ada transaksi pada periode ini</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in salesRecords.daily_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors group">
                                <td class="px-6 py-4 text-gray-500">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ formatDate(item.date)
                                }}</td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">{{ item.order_no }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ item.customer_name }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ item.customer_phone }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300 font-medium">{{ item.type }}</td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white font-semibold">{{ item.qty }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                        :class="item.status === 'Lunas'
                                            ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                            : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <!-- Backend doesn't split payment methods yet, hardcoding 0 or logic if available later -->
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-mono text-xs">Rp 0</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-mono text-xs">Rp 0</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-mono text-xs">Rp 0</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2 transition-opacity">
                                        <button @click="openReceipt(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm">
                                            <Eye :size="16" />
                                        </button>
                                        <button
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm">
                                            <FileText :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Dummy for UI -->
                <div
                    class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-2 bg-gray-50/50 dark:bg-surface-700/30">
                    <button
                        class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                        <ChevronLeft :size="18" />
                    </button>
                    <button
                        class="w-9 h-9 flex items-center justify-center rounded-xl bg-primary-600 text-white font-bold text-sm shadow-lg shadow-primary-500/20">
                        1
                    </button>
                    <button
                        class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-white dark:hover:bg-surface-600 disabled:opacity-50 transition-colors">
                        <ChevronRight :size="18" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 2: Laporan per Brand -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Laporan per Brand</h2>

            <div
                class="bg-white dark:bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead
                        class="text-xs font-semibold text-gray-500 uppercase bg-gray-50/50 dark:bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                        <tr>
                            <th class="px-6 py-4 w-16">No</th>
                            <th class="px-6 py-4">Brand</th>
                            <th class="px-6 py-4">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                        <tr v-if="salesRecords.brand_sales.length === 0">
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500">Tidak ada data brand</td>
                        </tr>
                        <tr v-else v-for="(item, index) in salesRecords.brand_sales" :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 text-gray-500">{{ index + 1 }}</td>
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ item.brand }}</td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white font-semibold">{{ item.qty }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 3: Laporan per CS -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Laporan per CS</h2>

            <div
                class="bg-white dark:bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-gray-500 uppercase bg-gray-50/50 dark:bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-6 py-4 w-16">No</th>
                                <th class="px-6 py-4">Nama CS</th>
                                <th class="px-6 py-4">Total Penjualan (Unit)</th>
                                <th class="px-6 py-4">Total Tukar Tambah</th>
                                <th class="px-6 py-4">Total Refund / Angkut Barang</th>
                                <th class="px-6 py-4">Grand Total Penjualan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="salesRecords.cs_sales.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">Tidak ada data CS</td>
                            </tr>
                            <tr v-else v-for="(item, index) in salesRecords.cs_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors">
                                <td class="px-6 py-4 text-gray-500">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ item.cs_name }}</td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white pl-12 font-semibold">{{
                                    item.total_sales }}</td>
                                <td class="px-6 py-4 text-gray-500 pl-12">{{ item.total_trade_in || 0 }}</td>
                                <td class="px-6 py-4 text-gray-500 pl-12">{{ item.total_refund || 0 }}</td>
                                <td class="px-6 py-4 font-medium text-purple-600 dark:text-purple-400 font-mono">{{
                                    formatCurrency(item.grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <ReceiptModal :isOpen="showReceiptModal" :transaction="selectedTransaction" @close="showReceiptModal = false" />
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { Loader2, Download, Eye, FileText, ChevronLeft, ChevronRight, ChevronDown, Calendar } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'

const authStore = useAuthStore()

// Dropped Tabs Logic - Now displaying all sections vertically
const loading = ref(false)
const selectedPeriod = ref('daily') // For filter dropdown

// Receipt Modal State
const showReceiptModal = ref(false)
const selectedTransaction = ref(null)

const openReceipt = (item) => {
    selectedTransaction.value = item;
    showReceiptModal.value = true;
}

// Monthly Logic
const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i); // e.g. 2024 to 2028

const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(currentYear);

const salesRecords = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

// Helper to get local YYYY-MM-DD
const getTodayLocal = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const filters = ref({
    start_date: getTodayLocal(), // Start with today in local time
    end_date: getTodayLocal(),
    branch_id: null
})

const locations = ref([])
const selectedLocationKey = ref('all')

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
    // Only Audit, Super Admin, Owner can filter branches
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'audit', 'owner'].some(r => role.includes(r));
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value)
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


const fetchBranches = async () => {
    if (loading.value) return; // Prevent concurrent fetches

    try {
        // Reduced requests: use authStore.user if available instead of fetching again
        const requests = [
            axios.get('/branches'),
            axios.get('/online-shops')
        ];

        // Only fetch user if not available in store
        if (!authStore.user) {
            requests.push(axios.get('/user'));
        }

        const results = await Promise.all(requests);
        const branchRes = results[0];
        const shopRes = results[1];
        const userRes = results[2];

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allLocations = [...allBranches, ...allShops];

        const user = userRes ? (userRes.data.user || userRes.data.data || userRes.data) : authStore.user;
        const role = (authStore.userRole || '').toLowerCase();

        // Define unrestricted roles
        const isGlobalRole = ['super_admin', 'owner'].includes(role);

        // Collect allowed IDs
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

        // Deduplicate
        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0;

        // LOGIC: If global role OR (Audit role AND no specific assignments) -> Show all
        if (isGlobalRole || (role === 'audit' && !hasAnyRestriction)) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                return false;
            });

            // Auto-select first if needed
            if (locations.value.length === 1 && selectedLocationKey.value === 'all') {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`;
            }
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const fetchData = async () => {
    loading.value = true
    try {
        // Map selected location key to specific filter params
        const params = { ...filters.value };
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
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
