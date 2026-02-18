<template>
    <div class="space-y-8">
        <!-- Section 1: Penjualan -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Penjualan</h2>

                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Period Filter -->
                    <select v-model="selectedPeriod"
                        class="bg-white dark:bg-surface-800 border-gray-200 dark:border-surface-600 rounded-lg text-sm focus:ring-primary-500 focus:border-primary-500">
                        <option value="daily">Harian</option>
                        <option value="monthly">Bulanan</option>
                    </select>

                    <!-- Date Picker -->
                    <div class="relative">
                        <input type="date" v-model="filters.start_date"
                            class="bg-white dark:bg-surface-800 border-gray-200 dark:border-surface-600 rounded-lg text-sm focus:ring-primary-500 focus:border-primary-500 pl-3 pr-8" />
                    </div>

                    <!-- Branch Filter -->
                    <select v-if="canFilterBranch" v-model="selectedLocationKey" @change="fetchData"
                        class="bg-white dark:bg-surface-800 border-gray-200 dark:border-surface-600 rounded-lg text-sm focus:ring-primary-500 focus:border-primary-500 min-w-[150px]">
                        <option value="all">Semua Cabang/Toko</option>
                        <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                            :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                            {{ loc.name }}
                        </option>
                    </select>

                    <!-- Status Filter (Dummy for UI match) -->
                    <select
                        class="bg-white dark:bg-surface-800 border-gray-200 dark:border-surface-600 rounded-lg text-sm focus:ring-primary-500 focus:border-primary-500">
                        <option value="all">Semua Status</option>
                        <option value="lunas">Lunas</option>
                        <option value="pending">Pending</option>
                    </select>

                    <!-- Export Button -->
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-black dark:bg-white text-white dark:text-black rounded-lg text-sm font-medium hover:opacity-90 transition-opacity">
                        <Download :size="16" />
                        <span>Export</span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-surface-700/50">
                            <tr>
                                <th class="px-6 py-3 font-medium">No</th>
                                <th class="px-6 py-3 font-medium">Waktu Pesanan</th>
                                <th class="px-6 py-3 font-medium">Nomor Pesanan</th>
                                <th class="px-6 py-3 font-medium">Nama</th>
                                <th class="px-6 py-3 font-medium">No HP</th>
                                <th class="px-6 py-3 font-medium">Kategori</th>
                                <th class="px-6 py-3 font-medium">Tipe</th>
                                <th class="px-6 py-3 font-medium">Jumlah Barang</th>
                                <th class="px-6 py-3 font-medium">Status Pembayaran</th>
                                <th class="px-6 py-3 font-medium">Cash</th>
                                <th class="px-6 py-3 font-medium">Transfer</th>
                                <th class="px-6 py-3 font-medium">Debit</th>
                                <th class="px-6 py-3 font-medium text-center">#</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading" class="animate-pulse">
                                <td colspan="13" class="px-6 py-4 text-center text-gray-400">Memuat data...</td>
                            </tr>
                            <tr v-else-if="salesRecords.daily_sales.length === 0">
                                <td colspan="13" class="px-6 py-8 text-center text-gray-500">Tidak ada data penjualan
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in salesRecords.daily_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors">
                                <td class="px-6 py-4 text-gray-500">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ formatDate(item.date)
                                }}</td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white">{{ item.order_no }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ item.customer_name }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ item.customer_phone }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-100 dark:border-blue-800">
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ item.type }}</td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white">{{ item.qty }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <!-- Backend doesn't split payment methods yet, hardcoding 0 or logic if available later -->
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">Rp 0</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">Rp 0</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">Rp 0</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            class="p-1.5 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-lg text-black dark:text-white transition-colors">
                                            <Eye :size="16" />
                                        </button>
                                        <button
                                            class="p-1.5 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-lg text-black dark:text-white transition-colors">
                                            <FileText :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Dummy for UI -->
                <div class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-2">
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-full border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-gray-50 dark:hover:bg-surface-700 disabled:opacity-50">
                        <ChevronLeft :size="16" />
                    </button>
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-full border border-black dark:border-white bg-black dark:bg-white text-white dark:text-black font-medium text-xs">
                        1
                    </button>
                    <button
                        class="w-8 h-8 flex items-center justify-center rounded-full border border-gray-200 dark:border-surface-600 text-gray-500 hover:bg-gray-50 dark:hover:bg-surface-700 disabled:opacity-50">
                        <ChevronRight :size="16" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 2: Laporan per Brand -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Laporan per Brand</h2>

            <div
                class="bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-surface-700/50">
                        <tr>
                            <th class="px-6 py-3 font-medium w-16">No</th>
                            <th class="px-6 py-3 font-medium">Brand</th>
                            <th class="px-6 py-3 font-medium">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                        <tr v-if="salesRecords.brand_sales.length === 0">
                            <td colspan="3" class="px-6 py-8 text-center text-gray-500">Tidak ada data brand</td>
                        </tr>
                        <tr v-else v-for="(item, index) in salesRecords.brand_sales" :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-surface-700/30">
                            <td class="px-6 py-4 text-gray-500">{{ index + 1 }}</td>
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ item.brand }}</td>
                            <td class="px-6 py-4 text-gray-900 dark:text-white">{{ item.qty }}</td>
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
                class="bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-surface-700/50">
                            <tr>
                                <th class="px-6 py-3 font-medium w-16">No</th>
                                <th class="px-6 py-3 font-medium">Nama CS</th>
                                <th class="px-6 py-3 font-medium">Total Penjualan (Unit)</th>
                                <th class="px-6 py-3 font-medium">Total Tukar Tambah</th>
                                <th class="px-6 py-3 font-medium">Total Refund / Angkut Barang</th>
                                <th class="px-6 py-3 font-medium">Grand Total Penjualan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="salesRecords.cs_sales.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada data CS</td>
                            </tr>
                            <tr v-else v-for="(item, index) in salesRecords.cs_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30">
                                <td class="px-6 py-4 text-gray-500">{{ index + 1 }}</td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ item.cs_name }}</td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white pl-12">{{ item.total_sales }}</td>
                                <td class="px-6 py-4 text-gray-500 pl-12">{{ item.total_trade_in || 0 }}</td>
                                <td class="px-6 py-4 text-gray-500 pl-12">{{ item.total_refund || 0 }}</td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{
                                    formatCurrency(item.grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { Loader2, Download, Eye, FileText, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()

// Dropped Tabs Logic - Now displaying all sections vertically
const loading = ref(false)
const selectedPeriod = ref('daily') // For filter dropdown

const salesRecords = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

const filters = ref({
    start_date: new Date().toISOString().slice(0, 10), // Today
    end_date: new Date().toISOString().slice(0, 10),
    branch_id: null
})

const locations = ref([])
const selectedLocationKey = ref('all')

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

        console.log('[DEBUG] Fresh User Data:', user);
        console.log('[DEBUG] All Available Shops:', allShops);

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

        console.log('[DEBUG] Allowed Branch IDs:', allowedBranchIds);
        console.log('[DEBUG] Allowed Shop IDs:', allowedShopIds);

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

            console.log('[DEBUG] Filtered Locations:', locations.value);

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
    // Set start date to first day of month by default for better view? 
    // User usually wants to see daily sales for current day or month. 
    // Let's set start date to first day of current month.
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    filters.value.start_date = firstDay.toISOString().slice(0, 10);

    if (canFilterBranch.value) {
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
