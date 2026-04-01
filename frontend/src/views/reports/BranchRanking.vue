<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../api/axios';
import { formatCurrency, formatNumber } from '../../utils/formatters';
import {
    Trophy,
    TrendingUp,
    Building2,
    Smartphone,
    Search,
    Calendar,
    Filter,
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
    Store,
    Globe
} from 'lucide-vue-next';

const loading = ref(true);
const rankingData = ref([]);
const filters = ref({
    start_date: '',
    end_date: ''
});

// For convenience, adding predefined date ranges
const setRange = (type) => {
    const today = new Date();
    const start = new Date();
    
    if (type === 'today') {
        filters.value.start_date = today.toISOString().split('T')[0];
        filters.value.end_date = today.toISOString().split('T')[0];
    } else if (type === 'month') {
        start.setDate(1);
        filters.value.start_date = start.toISOString().split('T')[0];
        filters.value.end_date = today.toISOString().split('T')[0];
    } else if (type === 'all') {
        filters.value.start_date = '';
        filters.value.end_date = '';
    }
    fetchRanking();
};

const fetchRanking = async () => {
    loading.value = true;
    try {
        const response = await api.get('/reports/ranking', { 
            params: {
                start_date: filters.value.start_date,
                end_date: filters.value.end_date
            } 
        });
        rankingData.value = response.data;
    } catch (error) {
        console.error('Error fetching ranking report:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    // Default to this month
    const start = new Date();
    start.setDate(1);
    filters.value.start_date = start.toISOString().split('T')[0];
    filters.value.end_date = new Date().toISOString().split('T')[0];
    fetchRanking();
});

const searchQuery = ref('');
const filteredRanking = computed(() => {
    if (!searchQuery.value) return rankingData.value;
    const search = searchQuery.value.toLowerCase();
    return rankingData.value.filter(item => 
        item.name.toLowerCase().includes(search) || 
        item.type.toLowerCase().includes(search)
    );
});

// Top 3 for the podium
const top3 = computed(() => {
    return rankingData.value.slice(0, 3);
});

// Remaining for the table
const remainingRanking = computed(() => {
    return filteredRanking.value.slice(3);
});

</script>

<template>
    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div class="space-y-1">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-primary-500/10 rounded-lg">
                        <Trophy class="w-6 h-6 text-primary-500" />
                    </div>
                    <h1 class="text-3xl font-black text-text-primary tracking-tight">Ranking Performa Cabang</h1>
                </div>
                <p class="text-text-secondary text-sm ml-11">
                    Peringkat cabang offline dan toko online berdasarkan total omset penjualan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex bg-surface-800 p-1 rounded-xl border border-surface-700">
                    <button @click="setRange('today')" 
                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="filters.start_date === filters.end_date && filters.start_date !== '' ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary'">
                        Hari Ini
                    </button>
                    <button @click="setRange('month')" 
                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="filters.start_date !== filters.end_date ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary'">
                        Bulan Ini
                    </button>
                    <button @click="setRange('all')" 
                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="!filters.start_date ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary'">
                        Semua
                    </button>
                </div>

                <div class="flex items-center gap-2 bg-surface-800 p-1.5 rounded-xl border border-surface-700">
                    <Calendar class="w-4 h-4 text-text-secondary ml-2" />
                    <input type="date" v-model="filters.start_date" class="bg-transparent text-xs text-text-primary outline-none px-1 uppercase font-bold" />
                    <span class="text-surface-600 font-bold">-</span>
                    <input type="date" v-model="filters.end_date" class="bg-transparent text-xs text-text-primary outline-none px-1 uppercase font-bold" />
                    <button @click="fetchRanking" class="ml-2 p-1.5 bg-primary-500 hover:bg-primary-600 rounded-lg transition-colors group">
                        <Filter class="w-3.5 h-3.5 text-white group-hover:scale-110 transition-transform" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-32 space-y-4">
            <div class="relative w-20 h-20">
                <div class="absolute inset-0 border-4 border-primary-500/10 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                <Trophy class="absolute inset-0 m-auto w-8 h-8 text-primary-500 animate-pulse" />
            </div>
            <p class="text-text-secondary font-medium animate-pulse">Menghitung peringkat performa...</p>
        </div>

        <div v-else class="space-y-10 animate-in">
            <!-- Podium Section -->
            <div v-if="top3.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-8 items-end pt-8 pb-4 px-4 bg-gradient-to-b from-surface-800/20 to-transparent rounded-3xl border border-surface-800/50">
                
                <!-- Rank 2 (Silver) -->
                <div v-if="top3[1]" class="order-2 md:order-1 flex flex-col items-center">
                    <div class="relative group">
                        <div class="absolute -inset-4 bg-slate-400/10 rounded-full blur-xl group-hover:bg-slate-400/20 transition-all duration-500"></div>
                        <div class="relative w-24 h-24 rounded-2xl bg-surface-800 border-2 border-slate-400/30 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-2">
                             <component :is="top3[1].type === 'Offline' ? Store : Globe" class="w-10 h-10 text-slate-400" />
                             <div class="absolute -top-3 -right-3 w-10 h-10 bg-slate-400 text-surface-900 rounded-xl flex items-center justify-center font-black text-xl border-4 border-surface-900 shadow-lg">2</div>
                        </div>
                    </div>
                    <div class="mt-6 text-center">
                        <h3 class="font-bold text-lg text-text-primary">{{ top3[1].name }}</h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">{{ top3[1].type }} Branch</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg">
                            <span class="text-xl font-black text-slate-400">{{ formatCurrency(top3[1].omset) }}</span>
                        </div>
                    </div>
                    <div class="w-full h-32 mt-6 bg-gradient-to-t from-surface-800 to-surface-800/40 rounded-t-2xl border-x border-t border-surface-700/50"></div>
                </div>

                <!-- Rank 1 (Gold) -->
                <div v-if="top3[0]" class="order-1 md:order-2 flex flex-col items-center">
                    <div class="relative group mb-4">
                        <div class="absolute -inset-8 bg-primary-500/20 rounded-full blur-2xl group-hover:bg-primary-500/30 transition-all duration-500 animate-pulse"></div>
                        <div class="relative w-32 h-32 rounded-3xl bg-surface-800 border-2 border-primary-500/50 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-3">
                             <component :is="top3[0].type === 'Offline' ? Store : Globe" class="w-14 h-14 text-primary-500" />
                             <div class="absolute -top-4 -right-4 w-12 h-12 bg-primary-500 text-white rounded-2xl flex items-center justify-center font-black text-2xl border-4 border-surface-900 shadow-xl ring-4 ring-primary-500/20">1</div>
                             <Trophy class="absolute -top-12 left-1/2 -translate-x-1/2 w-10 h-10 text-primary-500 drop-shadow-[0_0_15px_rgba(245,158,11,0.5)] animate-bounce" />
                        </div>
                    </div>
                    <div class="mt-6 text-center">
                        <h3 class="font-black text-2xl text-text-primary">{{ top3[0].name }}</h3>
                        <p class="text-xs font-black text-primary-500 uppercase tracking-widest mt-1">{{ top3[0].type }} Branch</p>
                        <div class="mt-4 px-6 py-3 bg-primary-500/10 rounded-2xl border-2 border-primary-500/20 shadow-xl">
                            <span class="text-2xl font-black text-primary-400">{{ formatCurrency(top3[0].omset) }}</span>
                        </div>
                    </div>
                    <div class="w-full h-44 mt-6 bg-gradient-to-t from-primary-500/20 to-primary-500/5 rounded-t-3xl border-x border-t border-primary-500/30 relative overflow-hidden">
                        <div class="absolute inset-0 bg-grid-white/[0.02]"></div>
                    </div>
                </div>

                <!-- Rank 3 (Bronze) -->
                <div v-if="top3[2]" class="order-3 flex flex-col items-center">
                    <div class="relative group">
                        <div class="absolute -inset-4 bg-amber-700/10 rounded-full blur-xl group-hover:bg-amber-700/20 transition-all duration-500"></div>
                        <div class="relative w-20 h-20 rounded-2xl bg-surface-800 border-2 border-amber-700/30 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-2">
                             <component :is="top3[2].type === 'Offline' ? Store : Globe" class="w-8 h-8 text-amber-700" />
                             <div class="absolute -top-3 -right-3 w-8 h-8 bg-amber-700 text-surface-900 rounded-lg flex items-center justify-center font-black text-lg border-4 border-surface-900 shadow-lg">3</div>
                        </div>
                    </div>
                    <div class="mt-6 text-center">
                        <h3 class="font-bold text-text-primary">{{ top3[2].name }}</h3>
                        <p class="text-[10px] font-bold text-amber-700 uppercase tracking-widest mt-1">{{ top3[2].type }} Branch</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg">
                            <span class="text-lg font-black text-amber-700">{{ formatCurrency(top3[2].omset) }}</span>
                        </div>
                    </div>
                    <div class="w-full h-24 mt-6 bg-gradient-to-t from-surface-800 to-surface-800/40 rounded-t-2xl border-x border-t border-surface-700/50"></div>
                </div>
            </div>

            <!-- List Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-xl font-black text-text-primary flex items-center gap-2">
                        <TrendingUp class="w-5 h-5 text-emerald-500" />
                        Peringkat Lengkap
                    </h2>
                    <div class="relative flex items-center group">
                        <Search class="absolute left-3 w-4 h-4 text-text-secondary group-focus-within:text-primary-500 transition-colors" />
                        <input v-model="searchQuery" type="text" placeholder="Cari cabang..." 
                            class="bg-surface-800 text-sm py-2 pl-10 pr-4 rounded-xl border border-surface-700 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none w-64 transition-all" />
                    </div>
                </div>

                <div class="bg-surface-800/50 rounded-3xl border border-surface-800 overflow-hidden shadow-2xl backdrop-blur-xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-800/80">
                                <th class="px-8 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-700">Rank</th>
                                <th class="px-8 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-700">Cabang</th>
                                <th class="px-8 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-700">Tipe</th>
                                <th class="px-8 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-700 text-center">Transaksi</th>
                                <th class="px-8 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-700 text-right">Total Omset</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in filteredRanking" :key="item.id" 
                                class="group hover:bg-surface-800/40 transition-all duration-300">
                                <td class="px-8 py-5 border-b border-surface-800/50">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg font-black text-sm"
                                        :class="{
                                            'bg-primary-500 text-white shadow-lg shadow-primary-500/20': index === 0,
                                            'bg-slate-400 text-surface-900': index === 1,
                                            'bg-amber-700 text-white': index === 2,
                                            'bg-surface-700 text-text-secondary': index > 2
                                        }">
                                        {{ index + 1 }}
                                    </div>
                                </td>
                                <td class="px-8 py-5 border-b border-surface-800/50">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-surface-800 flex items-center justify-center group-hover:scale-110 transition-transform border border-surface-700 shadow-lg">
                                             <component :is="item.type === 'Offline' ? Store : Globe" class="w-5 h-5" :class="item.type === 'Offline' ? 'text-primary-500' : 'text-blue-400'" />
                                        </div>
                                        <span class="font-black text-text-primary group-hover:text-primary-400 transition-colors">{{ item.name }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 border-b border-surface-800/50">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border shadow-sm"
                                        :class="item.type === 'Offline' ? 'bg-amber-500/5 text-amber-500 border-amber-500/20' : 'bg-blue-500/5 text-blue-400 border-blue-500/20'">
                                        {{ item.type }}
                                    </span>
                                </td>
                                <td class="px-8 py-5 border-b border-surface-800/50 text-center">
                                    <span class="text-sm font-bold text-text-secondary group-hover:text-text-primary">{{ formatNumber(item.transaction_count) }}</span>
                                </td>
                                <td class="px-8 py-5 border-b border-surface-800/50 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-lg font-black text-emerald-400 tabular-nums">{{ formatCurrency(item.omset) }}</span>
                                        <div class="h-1.5 w-32 bg-surface-800 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full transition-all duration-1000" 
                                                :style="{ width: rankingData[0]?.omset > 0 ? (item.omset / rankingData[0].omset * 100) + '%' : '0%' }"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredRanking.length === 0">
                                <td colspan="5" class="px-8 py-20 text-center space-y-3">
                                    <div class="flex justify-center">
                                        <Search class="w-12 h-12 text-surface-700" />
                                    </div>
                                    <p class="text-text-secondary font-medium">Tidak ada data cabang yang ditemukan untuk periode ini.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-grid-white {
    background-size: 20px 20px;
    background-image: linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                      linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
}
</style>
