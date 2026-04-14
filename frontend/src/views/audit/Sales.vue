<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">Audit Penjualan</h1>

            <!-- Location Filter (Branch + Online Shop) -->
            <div v-if="canFilterBranch" class="min-w-[200px]">
                <select v-model="selectedLocationKey" @change="fetchData"
                    class="block w-full rounded-2xl border-0 py-2.5 text-text-primary shadow-sm ring-1 ring-inset ring-surface-200 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:ring-surface-700">
                    <option value="all">Semua Cabang/Toko</option>
                    <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                        :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                        {{ loc.type === 'branch' ? '[Cabang]' : '[Online]' }} {{ loc.name }}
                    </option>
                </select>
            </div>

            <!-- Date Filter -->
            <div
                class="flex items-center gap-2 bg-white dark:!bg-surface-800 p-2 rounded-lg border border-gray-200 dark:border-surface-700 shadow-sm">
                <input type="date" v-model="filters.start_date" :min="getMinDate" :max="getTodayLocal()"
                    class="border-gray-300 dark:border-surface-600 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 bg-transparent dark:bg-surface-700 text-gray-900 dark:text-white" />
                <span class="text-gray-500 dark:text-gray-400">-</span>
                <input type="date" v-model="filters.end_date" :min="getMinDate" :max="getTodayLocal()"
                    class="border-gray-300 dark:border-surface-600 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 bg-transparent dark:bg-surface-700 text-gray-900 dark:text-white" />
                <button @click="fetchData"
                    class="px-3 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 transition-colors"
                    :disabled="loading">
                    <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
                    <span v-else>Filter</span>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 dark:border-surface-700">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button v-for="tab in tabs" :key="tab.id" @click="currentTab = tab.id" :class="[
                    currentTab === tab.id
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                ]">
                    {{ tab.name }}
                </button>
            </nav>
        </div>

        <!-- Content -->
        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-blue-600 animate-spin" />
        </div>

        <div v-else>
            <!-- Daily Sales Table -->
            <div v-if="currentTab === 'daily'"
                class="bg-white dark:!bg-surface-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-surface-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-surface-700">
                        <thead class="bg-gray-50 dark:bg-surface-900">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Waktu</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    No Pesanan</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Nama</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Kategori</th>
                                <th scope="col" colspan="3"
                                    class="p-0 border-b border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-900">
                                    <div
                                        class="grid grid-cols-[80px_100px_1fr] md:grid-cols-[100px_120px_1fr] w-full min-w-[320px]">
                                        <div
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Tipe</div>
                                        <div
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Brand</div>
                                        <div
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Rincian Barang</div>
                                    </div>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:!bg-surface-800 divide-y divide-gray-200 dark:divide-surface-700">
                            <tr v-for="(item, index) in salesRecords.daily_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary">{{
                                    formatDate(item.date)
                                }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary font-medium">
                                    {{
                                        item.order_no }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">
                                    <div>{{ item.customer_name }}</div>
                                    <div class="text-xs text-text-secondary">{{ item.customer_phone }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">{{
                                    item.category }}</td>
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
                                                        <div class="whitespace-normal flex-1 leading-relaxed">{{
                                                            detail.name }}</div>
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
                                                        <div class="whitespace-normal flex-1 leading-relaxed">{{
                                                            item.product_names || '-' }}</div>
                                                        <div
                                                            class="bg-gray-100 dark:!bg-surface-700 px-2 py-0.5 rounded text-xs font-bold text-text-primary whitespace-nowrap mt-0.5">
                                                            {{ item.qty }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="item.status === 'Lunas' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary text-right font-bold">
                                    {{
                                        formatCurrency(item.grand_total) }}</td>
                            </tr>
                            <tr v-if="salesRecords.daily_sales.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">Tidak
                                    ada data penjualan
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Brand Sales Table -->
            <div v-if="currentTab === 'brand'"
                class="bg-white dark:!bg-surface-800 shadow rounded-lg overflow-hidden max-w-4xl mx-auto border border-gray-200 dark:border-surface-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-surface-700">
                    <thead class="bg-gray-50 dark:bg-surface-900">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Brand</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Total Terjual (Unit)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:!bg-surface-800 divide-y divide-gray-200 dark:divide-surface-700">
                        <tr v-for="(item, index) in salesRecords.brand_sales" :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-surface-700/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-primary">{{
                                item.brand }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary text-right">{{
                                item.qty }}</td>
                        </tr>
                        <tr v-if="salesRecords.brand_sales.length === 0">
                            <td colspan="2" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">Tidak ada
                                data brand</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- CS Sales Table -->
            <div v-if="currentTab === 'cs'"
                class="bg-white dark:!bg-surface-800 shadow rounded-lg overflow-hidden max-w-4xl mx-auto border border-gray-200 dark:border-surface-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-surface-700">
                    <thead class="bg-gray-50 dark:bg-surface-900">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Nama CS</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Total Transaksi</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Grand Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:!bg-surface-800 divide-y divide-gray-200 dark:divide-surface-700">
                        <tr v-for="(item, index) in salesRecords.cs_sales" :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-surface-700/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-primary">{{
                                item.cs_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary text-right">{{
                                item.total_sales
                            }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-primary text-right">{{
                                formatCurrency(item.grand_total) }}</td>
                        </tr>
                        <tr v-if="salesRecords.cs_sales.length === 0">
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">Tidak ada
                                data CS</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Profit Report Tab -->
            <div v-if="currentTab === 'profit'">
                <ProfitReport />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'
import ProfitReport from '../reports/ProfitReport.vue'

import { formatCurrency, formatNumber, getLogicalDate, getTodayLocal } from '../../utils/formatters'
const authStore = useAuthStore()

const tabs = [
    { id: 'daily', name: 'Penjualan Harian' },
    { id: 'brand', name: 'Laporan per Brand' },
    { id: 'cs', name: 'Laporan per CS' },
    { id: 'profit', name: 'Laporan Profit' }
]

const currentTab = ref('daily')
const loading = ref(false)
const salesRecords = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

// getLogicalDate and getTodayLocal are now imported

const filters = ref({
    start_date: getTodayLocal(), // Use standardized helper
    end_date: getTodayLocal(),   // Use standardized helper
    branch_id: null
})

const locations = ref([])
const selectedLocationKey = ref('all')

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some(r => role.includes(r));
})

// formatCurrency and formatNumber are now imported

const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return !privilegedRoles.some(r => role.includes(r));
});

const getMinDate = computed(() => {
    if (!isRestricted.value) return null;
    const d = getLogicalDate();
    d.setDate(d.getDate() - 7); // Allow past 7 days
    return formatDateStr(d);
});

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

        const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk'];
        const isAlwaysGlobal = alwaysGlobalRoles.some(r => role.includes(r));

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

        if (isAlwaysGlobal) {
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
        } else if (role.includes('audit') || role.includes('leader') || role.includes('analist')) {
            locations.value = allLocations;
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
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;

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
