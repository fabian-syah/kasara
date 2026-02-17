<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Analisa Cabang</h1>

        <!-- Header Controls -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-2 flex-wrap">
                <select v-model="selectedYear" @change="fetchData"
                    class="block rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:text-white dark:ring-surface-700">
                    <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                </select>
                <select v-model="selectedMonth" @change="fetchData"
                    class="block rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:text-white dark:ring-surface-700">
                    <option :value="null">Semua Bulan</option>
                    <option v-for="(name, index) in months" :key="index" :value="index + 1">{{ name }}</option>
                </select>
                <!-- Date Filter -->
                <input type="date" v-model="selectedDate" @change="fetchData"
                    class="block rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:text-white dark:ring-surface-700" />

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
                class="bg-white dark:bg-surface-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-surface-700">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <DollarSign class="h-6 w-6 text-green-500" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate dark:text-gray-400">Total Profit
                                    (Est)
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
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
                class="bg-white dark:bg-surface-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-surface-700">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <TrendingUp class="h-6 w-6 text-blue-500" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate dark:text-gray-400">Total Omset
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
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
                class="bg-white dark:bg-surface-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-surface-700">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ShoppingBag class="h-6 w-6 text-purple-500" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate dark:text-gray-400">Total Item
                                    Terjual
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">{{
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
                class="bg-white dark:bg-surface-800 p-6 rounded-lg shadow border border-gray-200 dark:border-surface-700">
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">Tren Profit Harian</h3>
                <div class="h-80 relative">
                    <Line v-if="chartData.trend" :data="chartData.trend" :options="lineChartOptions" />
                    <div v-else class="flex items-center justify-center h-full text-gray-500">Tidak ada data</div>
                </div>
            </div>

            <!-- Sales By Branch Chart -->
            <div
                class="bg-white dark:bg-surface-800 p-6 rounded-lg shadow border border-gray-200 dark:border-surface-700">
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">Kontribusi Sales per
                    Cabang/Shop (by CS)</h3>
                <div class="h-80 relative flex justify-center">
                    <Pie v-if="chartData.breakdown" :data="chartData.breakdown" :options="pieChartOptions" />
                    <div v-else class="flex items-center justify-center h-full text-gray-500">Tidak ada data</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Loader2, DollarSign, TrendingUp, ShoppingBag } from 'lucide-vue-next';
import api from '../../api/axios';
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
    ArcElement
} from 'chart.js';
import { Line, Pie } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement
);

const toast = useToast();
const loading = ref(false);

const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(null); // All months by default
const selectedDate = ref(null);

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
const fetchAnalysis = async () => {
    loading.value = true;
    try {
        const params = {
            year: selectedYear.value,
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
                    backgroundColor: '#10B981', // Emerald 500
                    borderColor: '#10B981',
                    data: profits,
                    tension: 0.3
                },
                {
                    label: 'Omset',
                    backgroundColor: '#3B82F6', // Blue 500
                    borderColor: '#3B82F6',
                    data: revenues,
                    tension: 0.3,
                    borderDash: [5, 5]
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

onMounted(() => {
    fetchData();
});
</script>
