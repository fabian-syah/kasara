<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Audit Penjualan</h1>

            <!-- Branch Filter (Only for Audit/Super Admin) -->
            <div v-if="canFilterBranch" class="min-w-[200px]">
                <select v-model="filters.branch_id" @change="fetchData"
                    class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:text-white dark:ring-surface-700">
                    <option :value="null">Semua Cabang</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                        {{ branch.name }}
                    </option>
                </select>
            </div>

            <!-- Date Filter -->
            <div
                class="flex items-center gap-2 bg-white dark:bg-surface-800 p-2 rounded-lg border border-gray-200 dark:border-surface-700 shadow-sm">
                <input type="date" v-model="filters.start_date"
                    class="border-gray-300 dark:border-surface-600 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 bg-transparent dark:bg-surface-700 text-gray-900 dark:text-white" />
                <span class="text-gray-500 dark:text-gray-400">-</span>
                <input type="date" v-model="filters.end_date"
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
                class="bg-white dark:bg-surface-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-surface-700">
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
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Tipe</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Jml</th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-surface-800 divide-y divide-gray-200 dark:divide-surface-700">
                            <tr v-for="(item, index) in data.daily_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{
                                    formatDate(item.date)
                                }}</td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-medium">
                                    {{
                                        item.order_no }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ item.customer_name }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">{{ item.customer_phone }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{
                                    item.category }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{
                                    item.type }}</td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                    {{ item.qty }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="item.status === 'Lunas' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                    {{
                                        formatCurrency(item.grand_total) }}</td>
                            </tr>
                            <tr v-if="data.daily_sales.length === 0">
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
                class="bg-white dark:bg-surface-800 shadow rounded-lg overflow-hidden max-w-4xl mx-auto border border-gray-200 dark:border-surface-700">
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
                    <tbody class="bg-white dark:bg-surface-800 divide-y divide-gray-200 dark:divide-surface-700">
                        <tr v-for="(item, index) in data.brand_sales" :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-surface-700/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{
                                item.brand }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">{{
                                item.qty }}</td>
                        </tr>
                        <tr v-if="data.brand_sales.length === 0">
                            <td colspan="2" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">Tidak ada
                                data brand</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- CS Sales Table -->
            <div v-if="currentTab === 'cs'"
                class="bg-white dark:bg-surface-800 shadow rounded-lg overflow-hidden max-w-4xl mx-auto border border-gray-200 dark:border-surface-700">
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
                    <tbody class="bg-white dark:bg-surface-800 divide-y divide-gray-200 dark:divide-surface-700">
                        <tr v-for="(item, index) in data.cs_sales" :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-surface-700/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{
                                item.cs_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">{{
                                item.total_sales
                            }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">{{
                                formatCurrency(item.grand_total) }}</td>
                        </tr>
                        <tr v-if="data.cs_sales.length === 0">
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">Tidak ada
                                data CS</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()

const tabs = [
    { id: 'daily', name: 'Penjualan Harian' },
    { id: 'brand', name: 'Laporan per Brand' },
    { id: 'cs', name: 'Laporan per CS' }
]

const currentTab = ref('daily')
const loading = ref(false)
const data = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

const filters = ref({
    start_date: new Date().toISOString().slice(0, 10), // Today
    end_date: new Date().toISOString().slice(0, 10),
    branch_id: null
})

const branches = ref([])

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
        const response = await axios.get('/branches')
        const allBranches = response.data.data || response.data || [];

        const user = authStore.user;
        const role = (authStore.userRole || '').toLowerCase();

        // Define unrestricted roles
        const isGlobalRole = ['super_admin', 'owner'].includes(role);

        // Collect allowed IDs from branch_id and placements
        let allowedIds = [];
        if (user?.branch_id) allowedIds.push(user.branch_id);

        if (user?.placements && Array.isArray(user.placements)) {
            const placementIds = user.placements
                .filter(p => p.model_type === 'branch')
                .map(p => p.model_id);
            allowedIds = [...allowedIds, ...placementIds];
        }

        // Deduplicate and ensure comparisons work (ids are usually numbers)
        allowedIds = [...new Set(allowedIds.map(id => Number(id)))];

        // LOGIC: If global role OR (Audit role AND no specific assignments) -> Show all
        if (isGlobalRole || (role === 'audit' && allowedIds.length === 0)) {
            branches.value = allBranches;
        } else if (allowedIds.length > 0) {
            branches.value = allBranches.filter(b => allowedIds.includes(Number(b.id)));
            // Auto-select first if needed
            if (branches.value.length > 0 && !filters.value.branch_id) {
                filters.value.branch_id = branches.value[0].id;
            }
        } else {
            branches.value = [];
        }
    } catch (error) {
        console.error('Error fetching branches:', error)
    }
}

const fetchData = async () => {
    loading.value = true
    try {
        const response = await axios.get('/audit/sales', {
            params: {
                ...filters.value,
                // If branch_id is null, send it as empty or don't send? 
                // Backend expects branch_id if specific.
                branch_id: filters.value.branch_id || undefined
            }
        })
        data.value = response.data
    } catch (error) {
        console.error('Error fetching sales data:', error)
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
