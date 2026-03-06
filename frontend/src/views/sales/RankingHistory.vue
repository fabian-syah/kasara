<template>
    <div class="space-y-6 max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center py-8">
            <div class="w-20 h-20 mx-auto bg-amber-500/20 rounded-3xl flex items-center justify-center mb-4">
                <Trophy :size="36" class="text-amber-500" />
            </div>
            <h1 class="text-3xl font-bold text-text-primary">Peringkat & Foto</h1>
            <p class="text-text-secondary mt-2">Lihat peringkat penjualan dan foto sales</p>
        </div>

        <!-- Period Filter -->
        <div class="flex items-center justify-center gap-3">
            <div class="relative min-w-[140px]">
                <select v-model="selectedPeriod" @change="handlePeriodChange"
                    class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                    <option value="daily">Harian</option>
                    <option value="monthly">Bulanan</option>
                </select>
                <ChevronDown :size="16"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
            </div>

            <div v-if="selectedPeriod === 'daily'" class="relative group">
                <div
                    class="flex items-center gap-2 px-4 py-2.5 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl hover:border-primary-500 transition-all cursor-pointer">
                    <Calendar :size="18" class="text-gray-500 group-hover:text-primary-500" />
                    <span class="text-sm font-medium text-text-primary min-w-[100px]">
                        {{ formattedDateDisplay }}
                    </span>
                </div>
                <input type="date" v-model="filters.start_date" @change="handleDateChange"
                    @click="$event.target.showPicker()"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
            </div>

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

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-primary-500 animate-spin" />
        </div>

        <!-- Ranking Cards -->
        <div v-else-if="rankings.length === 0" class="text-center py-12 text-text-secondary">
            <Trophy :size="48" class="mx-auto mb-4 opacity-30" />
            <p class="font-medium text-text-primary">Belum ada data peringkat</p>
            <p class="text-sm mt-1">Data penjualan belum tersedia pada periode ini</p>
        </div>

        <div v-else class="space-y-4">
            <!-- Top 3 Podium -->
            <div v-if="rankings.length >= 1" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <template v-for="(cs, idx) in rankings.slice(0, 3)" :key="cs.cs_name">
                    <div class="relative bg-white dark:!bg-surface-800 rounded-2xl p-6 border shadow-sm text-center transition-all hover:shadow-lg"
                        :class="{
                            'border-amber-300 dark:border-amber-500/30 md:order-2 md:-mt-4': idx === 0,
                            'border-gray-300 dark:border-surface-600 md:order-1': idx === 1,
                            'border-amber-700 dark:border-amber-800/30 md:order-3': idx === 2,
                        }">
                        <!-- Rank Badge -->
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span class="px-3 py-1 rounded-full text-xs font-black shadow-lg" :class="{
                                'bg-amber-400 text-amber-900': idx === 0,
                                'bg-gray-300 text-gray-700 dark:bg-gray-600 dark:text-gray-200': idx === 1,
                                'bg-amber-700 text-amber-100': idx === 2,
                            }">
                                #{{ idx + 1 }}
                            </span>
                        </div>

                        <!-- Avatar -->
                        <div class="mt-4 mb-3">
                            <img :src="cs.photo
                                ? (cs.photo.startsWith('http') ? cs.photo : `${storageBaseUrl}/storage/${cs.photo}`)
                                : `https://ui-avatars.com/api/?name=${encodeURIComponent(cs.cs_name)}&background=10b981&color=fff&size=128`"
                                class="w-20 h-20 rounded-full mx-auto border-4 object-cover shadow-md" :class="{
                                    'border-amber-400': idx === 0,
                                    'border-gray-300 dark:border-gray-500': idx === 1,
                                    'border-amber-700': idx === 2,
                                }" :alt="cs.cs_name"
                                @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(cs.cs_name)}&background=10b981&color=fff&size=128`" />
                        </div>

                        <h3 class="text-lg font-bold text-text-primary truncate">{{ cs.cs_name }}</h3>
                        <p class="text-3xl font-black mt-2" :class="{
                            'text-amber-500': idx === 0,
                            'text-gray-500 dark:text-gray-400': idx === 1,
                            'text-amber-700 dark:text-amber-400': idx === 2,
                        }">{{ cs.total_sales }}</p>
                        <p class="text-text-secondary text-xs uppercase tracking-wider font-semibold mt-1">Unit Terjual
                        </p>
                    </div>
                </template>
            </div>

            <!-- Full Ranking Table -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-surface-700">
                    <h2 class="text-lg font-bold text-text-primary">Peringkat Lengkap</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-text-secondary uppercase bg-gray-50/50 dark:!bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-6 py-4 w-16">Rank</th>
                                <th class="px-6 py-4">Sales</th>
                                <th class="px-6 py-4 text-center">Total Penjualan</th>
                                <th class="px-6 py-4 text-center">Tukar Tambah</th>
                                <th class="px-6 py-4 text-center">Refund</th>
                                <th class="px-6 py-4 text-right">Grand Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-for="(cs, index) in rankings" :key="cs.cs_name"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <span
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black"
                                        :class="{
                                            'bg-amber-400 text-amber-900': index === 0,
                                            'bg-gray-300 text-gray-700 dark:bg-gray-600 dark:text-gray-200': index === 1,
                                            'bg-amber-700 text-amber-100': index === 2,
                                            'bg-surface-100 dark:bg-surface-700 text-text-secondary': index > 2,
                                        }">
                                        {{ index + 1 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img :src="cs.photo
                                            ? (cs.photo.startsWith('http') ? cs.photo : `${storageBaseUrl}/storage/${cs.photo}`)
                                            : `https://ui-avatars.com/api/?name=${encodeURIComponent(cs.cs_name)}&background=10b981&color=fff&size=48`"
                                            class="w-9 h-9 rounded-full object-cover border-2 border-surface-200 dark:border-surface-600"
                                            :alt="cs.cs_name"
                                            @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(cs.cs_name)}&background=10b981&color=fff&size=48`" />
                                        <span class="font-semibold text-text-primary">{{ cs.cs_name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-primary-500">{{ cs.total_sales }}</td>
                                <td class="px-6 py-4 text-center text-text-secondary">{{ cs.total_trade_in || 0 }}</td>
                                <td class="px-6 py-4 text-center text-text-secondary">{{ cs.total_refund || 0 }}</td>
                                <td class="px-6 py-4 text-right font-bold text-text-primary font-mono">{{
                                    formatCurrency(cs.grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Loader2, ChevronDown, Calendar, Trophy } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()
const storageBaseUrl = computed(() => authStore.storageBaseUrl)

const loading = ref(false)
const selectedPeriod = ref('daily')

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(currentYear);

const salesData = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

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
        return `${months[selectedMonth.value - 1]} ${selectedYear.value}`;
    }
})

// Rankings sorted by total_sales descending
const rankings = computed(() => {
    return [...(salesData.value.cs_sales || [])].sort((a, b) => (b.total_sales || 0) - (a.total_sales || 0))
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value || 0)
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

const fetchData = async () => {
    loading.value = true
    try {
        const params = { ...filters.value };
        const response = await axios.get('/audit/sales', { params })
        salesData.value = response.data
    } catch (error) {
        console.error('Error fetching ranking data:', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;
    fetchData()
})
</script>
