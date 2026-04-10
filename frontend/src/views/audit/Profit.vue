<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold tracking-tight text-text-primary">Audit Profit</h1>

        <!-- Header Controls -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Location Filter (Branch + Online Shop) -->
                <div v-if="canFilterBranch" class="min-w-[200px]">
                    <select v-model="selectedLocationKey" @change="fetchData"
                        class="block w-full rounded-md border-0 py-1.5 text-text-primary shadow-sm ring-1 ring-inset ring-surface-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:ring-surface-700">
                        <option value="all">Semua Cabang/Toko</option>
                        <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                            :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                            {{ loc.type === 'branch' ? '[Cabang]' : '[Online]' }} {{ loc.name }}
                        </option>
                    </select>
                </div>

                <select v-model="selectedYear" @change="fetchData"
                    class="block rounded-md border-0 py-1.5 text-text-primary shadow-sm ring-1 ring-inset ring-surface-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:ring-surface-700">
                    <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                </select>
                <select v-model="selectedMonth" @change="fetchData"
                    class="block rounded-md border-0 py-1.5 text-text-primary shadow-sm ring-1 ring-inset ring-surface-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:ring-surface-700">
                    <option :value="null">Semua Bulan</option>
                    <option v-for="(name, index) in months" :key="index" :value="index + 1">{{ name }}</option>
                </select>
                <!-- Date Filter -->
                <input type="date" v-model="selectedDate" @change="fetchData"
                    :min="isRestricted ? getMinDate() : undefined"
                    :max="isRestricted ? getMaxDate() : undefined"
                    class="block rounded-md border-0 py-1.5 text-text-primary shadow-sm ring-1 ring-inset ring-surface-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:ring-surface-700" />

                <button @click="fetchData" :disabled="loading"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50">
                    <Loader2 v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
                    Apply
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div
                class="bg-white dark:!bg-surface-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-surface-700">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <DollarSign class="h-6 w-6 text-green-500" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-text-secondary truncate">Total Profit
                                    (Est)
                                </dt>
                                <dd class="text-lg font-semibold text-text-primary">
                                    {{ formatCurrency(summary.total_profit) }}
                                    <span v-if="comparison" class="text-xs ml-2"
                                        :class="comparison.profit_diff >= 0 ? 'text-green-500' : 'text-red-500'">
                                        {{ comparison.profit_diff >= 0 ? '+' : '' }}{{ comparison.percentage }}% (Rp {{
                                            formatNumber(comparison.profit_diff) }})
                                    </span>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:!bg-surface-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-surface-700">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <TrendingUp class="h-6 w-6 text-blue-500" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-text-secondary truncate">Total Omset
                                </dt>
                                <dd class="text-lg font-semibold text-text-primary">
                                    {{ formatCurrency(summary.total_revenue) }}
                                    <span v-if="comparison" class="text-xs ml-2"
                                        :class="comparison.revenue_diff >= 0 ? 'text-green-500' : 'text-red-500'">
                                        {{ comparison.revenue_diff >= 0 ? '+' : '' }}{{
                                            formatNumber(comparison.revenue_diff) }}
                                    </span>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:!bg-surface-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-surface-700">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ShoppingBag class="h-6 w-6 text-purple-500" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-text-secondary truncate">Total Item
                                    Terjual
                                </dt>
                                <dd class="text-lg font-semibold text-text-primary">{{
                                    formatNumber(summary.total_items) }} Unit</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Profit Trend Chart -->
            <div
                class="bg-white dark:!bg-surface-800 p-6 rounded-lg shadow border border-gray-200 dark:border-surface-700">
                <h3 class="text-lg font-medium leading-6 text-text-primary mb-4">Tren Profit Harian</h3>
                <div class="h-80 relative">
                    <Line v-if="chartData.trend" :data="chartData.trend" :options="lineChartOptions" />
                    <div v-else class="flex items-center justify-center h-full text-gray-500">Tidak ada data</div>
                </div>
            </div>

            <!-- Sales By Branch Chart -->
            <div
                class="bg-white dark:!bg-surface-800 p-6 rounded-lg shadow border border-gray-200 dark:border-surface-700">
                <h3 class="text-lg font-medium leading-6 text-text-primary mb-4">Kontribusi Sales per CS (Akun
                    Inventory)</h3>
                <div class="h-80 relative flex justify-center">
                    <Doughnut v-if="chartData.breakdown" :data="chartData.breakdown" :options="pieChartOptions" />
                    <div v-else class="flex items-center justify-center h-full text-gray-500">Tidak ada data</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Loader2, DollarSign, TrendingUp, ShoppingBag } from 'lucide-vue-next';
import api from '../../api/axios';
import { useAuthStore } from '../../store/auth';
import axios from '../../api/axios'; // Ensure we have axios for branch fetch if api helper doesn't support generic
import { useToast } from '../../composables/useToast';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler
} from 'chart.js';
import { Line, Doughnut } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler
);

const toast = useToast();
const authStore = useAuthStore();
const loading = ref(false);

const getLogicalDate = () => {
    const d = new Date();
    if (d.getHours() < 5) d.setDate(d.getDate() - 1);
    return d;
};

const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return !privilegedRoles.some(r => role.includes(r));
});

const getMinDate = () => {
    const d = getLogicalDate();
    d.setDate(d.getDate() - 1); // Yesterday
    return d.toISOString().split('T')[0];
};

const getMaxDate = () => {
    const d = getLogicalDate();
    return d.toISOString().split('T')[0];
};

const selectedYear = ref(getLogicalDate().getFullYear());
const selectedMonth = ref(null); // All months by default
const selectedDate = ref(null);

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

const years = [2024, 2025, 2026];
const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const summary = ref({
    total_profit: 0,
    total_revenue: 0,
    total_items: 0
});

const comparison = ref(null);

const chartData = ref({
    trend: null,
    breakdown: null
});

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some(r => role.includes(r));
})

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function (value) {
                    if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + ' jt';
                    if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + ' k';
                    return value;
                }
            }
        }
    }
};

const pieChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%', // makes it a doughnut
    plugins: {
        legend: {
            position: 'right',
        }
    }
};

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

        console.log('[DEBUG-PROFIT] Fresh User Data:', user);
        console.log('[DEBUG-PROFIT] All Available Shops:', allShops);

        const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
        const isGlobalRole = privilegedRoles.some(r => role.includes(r));

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

const fetchAnalysis = async () => {
    loading.value = true;
    try {
        const params = {
            year: selectedYear.value,
            branch_id: selectedBranchId.value || undefined,
            online_shop_id: selectedOnlineShopId.value || undefined
        };
        if (selectedDate.value) {
            params.date = selectedDate.value;
        } else if (selectedMonth.value) {
            params.month = selectedMonth.value;
        }

        const response = await api.get('/audit/analysis', { params });
        const data = response.data;

        summary.value = data.summary;
        comparison.value = data.comparison || null;
        processCharts(data);

    } catch (error) {
        console.error("Failed to fetch analysis:", error);
        toast.error("Gagal memuat data analisa");
    } finally {
        loading.value = false;
    }
};

const processCharts = (data) => {
    // 1. Trend Line Chart
    if (data.profit_trend && data.profit_trend.length > 0) {
        const labels = data.profit_trend.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        });
        const profits = data.profit_trend.map(item => item.profit);
        const revenues = data.profit_trend.map(item => item.revenue);

        chartData.value.trend = {
            labels,
            datasets: [
                {
                    label: 'Profit (Est)',
                    backgroundColor: 'rgba(16, 185, 129, 0.2)', // Emerald 500 with opacity
                    borderColor: '#10B981',
                    pointBackgroundColor: '#10B981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    data: profits,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Omset',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)', // Blue 500 with opacity
                    borderColor: '#3B82F6',
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    data: revenues,
                    fill: true,
                    tension: 0.4
                }
            ]
        };
    } else {
        chartData.value.trend = null;
    }

    // 2. Breakdown Pie Chart
    if (data.sales_breakdown && data.sales_breakdown.length > 0) {
        const labels = data.sales_breakdown.map(item => item.name);
        const counts = data.sales_breakdown.map(item => item.items);
        // Using distinct colors
        const backgroundColors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#6366F1'
        ];

        chartData.value.breakdown = {
            labels,
            datasets: [
                {
                    backgroundColor: backgroundColors.slice(0, labels.length),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4,
                    data: counts
                }
            ]
        };
    } else {
        chartData.value.breakdown = null;
    }
};

const fetchData = () => {
    fetchAnalysis();
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(val);
};

const formatNumber = (val) => {
    return new Intl.NumberFormat('id-ID').format(val);
};

onMounted(async () => {
    if (canFilterBranch.value) {
        await fetchBranches()
    }
    fetchData();
});
// Watch for user changes (e.g. on page reload if store initializes late)
watch(() => authStore.user, async (newUser) => {
    if (newUser && canFilterBranch.value) {
        await fetchBranches();
    }
});
</script>
