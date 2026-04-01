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

const formatDateStr = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

// For convenience, adding predefined date ranges
const setRange = (type) => {
    const today = new Date();
    
    if (type === 'today') {
        filters.value.start_date = formatDateStr(today);
        filters.value.end_date = formatDateStr(today);
    } else if (type === 'month') {
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        filters.value.start_date = formatDateStr(startOfMonth);
        filters.value.end_date = formatDateStr(today);
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
    const today = new Date();
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    filters.value.start_date = formatDateStr(startOfMonth);
    filters.value.end_date = formatDateStr(today);
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
    return filteredRanking.value.slice(0, 3);
});

</script>

<template>
    <div class="p-4 md:p-6 space-y-6 md:space-y-8 max-w-7xl mx-auto overflow-x-hidden">
        <!-- Header Section -->
        <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-primary-500/10 rounded-lg shrink-0">
                        <Trophy class="w-6 h-6 text-primary-500" />
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black text-text-primary tracking-tight">Ranking Performa Cabang</h1>
                </div>
                <p class="text-text-secondary text-xs md:text-sm ml-0 md:ml-11">
                    Peringkat unit bisnis berdasarkan total omset penjualan (cabang yang beromset 0 tidak ditampilkan).
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="flex bg-surface-800 p-1 rounded-xl border border-surface-700 w-full sm:w-auto">
                    <button @click="setRange('today')" 
                        class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="filters.start_date === filters.end_date && filters.start_date !== '' ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary'">
                        Hari Ini
                    </button>
                    <button @click="setRange('month')" 
                        class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="filters.start_date !== filters.end_date ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary'">
                        Bulan Ini
                    </button>
                    <button @click="setRange('all')" 
                        class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="!filters.start_date ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary'">
                        Semua
                    </button>
                </div>

                <div class="flex items-center gap-2 bg-surface-800 p-1.5 rounded-xl border border-surface-700">
                    <Calendar class="w-4 h-4 text-text-secondary ml-2 shrink-0" />
                    <input type="date" v-model="filters.start_date" class="bg-transparent text-[10px] sm:text-xs text-text-primary outline-none px-1 uppercase font-bold w-full" />
                    <span class="text-surface-600 font-bold">-</span>
                    <input type="date" v-model="filters.end_date" class="bg-transparent text-[10px] sm:text-xs text-text-primary outline-none px-1 uppercase font-bold w-full" />
                    <button @click="fetchRanking" class="ml-2 p-1.5 bg-primary-500 hover:bg-primary-600 rounded-lg transition-colors group shrink-0">
                        <Filter class="w-3.5 h-3.5 text-white group-hover:scale-110 transition-transform" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-24 md:py-32 space-y-4">
            <div class="relative w-16 h-16 md:w-20 md:h-20">
                <div class="absolute inset-0 border-4 border-primary-500/10 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                <Trophy class="absolute inset-0 m-auto w-6 h-6 md:w-8 md:h-8 text-primary-500 animate-pulse" />
            </div>
            <p class="text-text-secondary text-sm md:text-base font-medium animate-pulse">Menghitung peringkat performa...</p>
        </div>

        <div v-else class="space-y-8 md:space-y-12 animate-in">
            <!-- Podium Section -->
            <div v-if="top3.length > 0" class="flex flex-col md:flex-row gap-6 md:gap-8 items-center md:items-end pt-8 pb-4 px-4 bg-gradient-to-b from-surface-800/20 to-transparent rounded-3xl border border-surface-800/50">
                
                <!-- Rank 2 (Silver) -->
                <div v-if="top3[1]" class="order-2 md:order-1 flex flex-col items-center w-full md:w-1/3">
                    <div class="relative group">
                        <div class="absolute -inset-4 bg-slate-400/10 rounded-full blur-xl group-hover:bg-slate-400/20 transition-all duration-500"></div>
                        <div class="relative w-20 h-20 md:w-24 md:h-24 rounded-2xl bg-surface-800 border-2 border-slate-400/30 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-2">
                             <component :is="top3[1].type === 'Offline' ? Store : Globe" class="w-8 h-8 md:w-10 md:h-10 text-slate-400" />
                             <div class="absolute -top-3 -right-3 w-8 h-8 md:w-10 md:h-10 bg-slate-400 text-surface-900 rounded-xl flex items-center justify-center font-black text-lg md:text-xl border-4 border-surface-900 shadow-lg">2</div>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-6 text-center w-full">
                        <h3 class="font-bold text-base md:text-lg text-text-primary truncate px-2">{{ top3[1].name }}</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">{{ top3[1].type }}</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg mb-2 inline-block">
                            <span class="text-lg md:text-xl font-black text-slate-400">{{ formatCurrency(top3[1].omset) }}</span>
                        </div>
                        <div class="flex gap-2 justify-center mt-2">
                            <div class="flex flex-col items-center">
                                <span class="text-[9px] font-bold text-primary-400 uppercase tracking-tighter">iPhone</span>
                                <span class="text-xs font-black text-text-primary">{{ top3[1].iphone_count }}</span>
                            </div>
                            <div class="w-px h-6 bg-surface-700 mx-1"></div>
                            <div class="flex flex-col items-center">
                                <span class="text-[9px] font-bold text-emerald-400 uppercase tracking-tighter">Android</span>
                                <span class="text-xs font-black text-text-primary">{{ top3[1].android_count }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block w-full h-32 mt-6 bg-gradient-to-t from-surface-800 to-surface-800/40 rounded-t-2xl border-x border-t border-surface-700/50"></div>
                </div>

                <!-- Rank 1 (Gold) -->
                <div v-if="top3[0]" class="order-1 md:order-2 flex flex-col items-center w-full md:w-1/3">
                    <div class="relative group mb-4">
                        <div class="absolute -inset-8 bg-primary-500/20 rounded-full blur-2xl group-hover:bg-primary-500/30 transition-all duration-500 animate-pulse"></div>
                        <div class="relative w-28 h-28 md:w-32 md:h-32 rounded-3xl bg-surface-800 border-2 border-primary-500/50 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-3">
                             <component :is="top3[0].type === 'Offline' ? Store : Globe" class="w-12 h-12 md:w-14 md:h-14 text-primary-500" />
                             <div class="absolute -top-4 -right-4 w-10 h-10 md:w-12 md:h-12 bg-primary-500 text-white rounded-2xl flex items-center justify-center font-black text-xl md:text-2xl border-4 border-surface-900 shadow-xl ring-4 ring-primary-500/20">1</div>
                             <Trophy class="absolute -top-10 left-1/2 -translate-x-1/2 w-8 h-8 md:w-10 md:h-10 text-primary-500 drop-shadow-[0_0_15px_rgba(245,158,11,0.5)] animate-bounce" />
                        </div>
                    </div>
                    <div class="mt-4 md:mt-6 text-center w-full">
                        <h3 class="font-black text-xl md:text-2xl text-text-primary truncate px-2">{{ top3[0].name }}</h3>
                        <p class="text-xs font-black text-primary-500 uppercase tracking-widest mt-1">{{ top3[0].type }}</p>
                        <div class="mt-4 px-6 py-3 bg-primary-500/10 rounded-2xl border-2 border-primary-500/20 shadow-xl mb-3 inline-block">
                            <span class="text-xl md:text-2xl font-black text-primary-400">{{ formatCurrency(top3[0].omset) }}</span>
                        </div>
                        <div class="flex gap-4 justify-center mb-1">
                            <div class="flex flex-col items-center">
                                <span class="text-[10px] font-black text-primary-400 uppercase tracking-tighter">iPhone</span>
                                <span class="text-base font-black text-text-primary">{{ top3[0].iphone_count }}</span>
                            </div>
                            <div class="w-px h-8 bg-primary-500/20 mx-1"></div>
                            <div class="flex flex-col items-center">
                                <span class="text-[10px] font-black text-emerald-400 uppercase tracking-tighter">Android</span>
                                <span class="text-base font-black text-text-primary">{{ top3[0].android_count }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block w-full h-44 mt-6 bg-gradient-to-t from-primary-500/20 to-primary-500/5 rounded-t-3xl border-x border-t border-primary-500/30 relative overflow-hidden">
                        <div class="absolute inset-0 bg-grid-white/[0.02]"></div>
                    </div>
                </div>

                <!-- Rank 3 (Bronze) -->
                <div v-if="top3[2]" class="order-3 flex flex-col items-center w-full md:w-1/3">
                    <div class="relative group">
                        <div class="absolute -inset-4 bg-amber-700/10 rounded-full blur-xl group-hover:bg-amber-700/20 transition-all duration-500"></div>
                        <div class="relative w-16 h-16 md:w-20 md:h-20 rounded-2xl bg-surface-800 border-2 border-amber-700/30 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-2">
                             <component :is="top3[2].type === 'Offline' ? Store : Globe" class="w-6 h-6 md:w-8 md:h-8 text-amber-700" />
                             <div class="absolute -top-2 -right-2 w-6 h-6 md:w-8 md:h-8 bg-amber-700 text-surface-900 rounded-lg flex items-center justify-center font-black text-sm md:text-lg border-4 border-surface-900 shadow-lg">3</div>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-6 text-center w-full">
                        <h3 class="font-bold text-sm md:text-base text-text-primary truncate px-2">{{ top3[2].name }}</h3>
                        <p class="text-[9px] font-bold text-amber-700 uppercase tracking-widest mt-1">{{ top3[2].type }}</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg mb-2 inline-block">
                            <span class="text-base md:text-lg font-black text-amber-700">{{ formatCurrency(top3[2].omset) }}</span>
                        </div>
                        <div class="flex gap-2 justify-center mt-2">
                            <div class="flex flex-col items-center">
                                <span class="text-[9px] font-bold text-primary-400 uppercase tracking-tighter">iPhone</span>
                                <span class="text-xs font-black text-text-primary">{{ top3[2].iphone_count }}</span>
                            </div>
                            <div class="w-px h-6 bg-surface-700 mx-1"></div>
                            <div class="flex flex-col items-center">
                                <span class="text-[9px] font-bold text-emerald-400 uppercase tracking-tighter">Android</span>
                                <span class="text-xs font-black text-text-primary">{{ top3[2].android_count }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block w-full h-24 mt-6 bg-gradient-to-t from-surface-800 to-surface-800/40 rounded-t-2xl border-x border-t border-surface-700/50"></div>
                </div>
            </div>

            <!-- List Section -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-2">
                    <h2 class="text-lg md:text-xl font-black text-text-primary flex items-center gap-2">
                        <TrendingUp class="w-5 h-5 text-emerald-500" />
                        Peringkat Lengkap
                    </h2>
                    <div class="relative flex items-center group w-full sm:w-64">
                        <Search class="absolute left-3 w-4 h-4 text-text-secondary group-focus-within:text-primary-500 transition-colors" />
                        <input v-model="searchQuery" type="text" placeholder="Cari cabang..." 
                            class="bg-surface-800 text-sm py-2 pl-10 pr-4 rounded-xl border border-surface-700 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none w-full transition-all" />
                    </div>
                </div>

                <div class="bg-surface-800/50 rounded-3xl border border-surface-800 overflow-hidden shadow-2xl backdrop-blur-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-surface-800/80">
                                    <th class="px-6 py-5 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-700">Rank</th>
                                    <th class="px-6 py-5 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-700">Cabang</th>
                                    <th class="px-6 py-5 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-700 text-center">iPhone</th>
                                    <th class="px-6 py-5 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-700 text-center">Android & Terlaris</th>
                                    <th class="px-6 py-5 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-700 text-right">Total Omset</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in filteredRanking" :key="item.id" 
                                    class="group hover:bg-surface-800/40 transition-all duration-300">
                                    <td class="px-6 py-6 border-b border-surface-800/50">
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
                                    <td class="px-6 py-6 border-b border-surface-800/50">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-surface-800 flex items-center justify-center shrink-0 border border-surface-700">
                                                    <component :is="item.type === 'Offline' ? Store : Globe" class="w-4 h-4" :class="item.type === 'Offline' ? 'text-primary-500' : 'text-blue-400'" />
                                                </div>
                                                <span class="font-black text-text-primary group-hover:text-primary-400 transition-colors uppercase tracking-tight">{{ item.name }}</span>
                                            </div>
                                            <span class="text-[9px] font-bold text-text-secondary mt-1 ml-11 uppercase group-hover:text-text-primary transition-colors">{{ item.type }} BRANCH</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6 border-b border-surface-800/50 text-center">
                                        <div class="flex flex-col items-center">
                                            <span class="text-base font-black text-primary-400 font-mono">{{ formatNumber(item.iphone_count) }}</span>
                                            <span class="text-[9px] font-bold text-text-secondary uppercase">Units</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6 border-b border-surface-800/50">
                                        <div class="flex flex-col items-center">
                                            <span class="text-base font-black text-emerald-400 font-mono">{{ formatNumber(item.android_count) }}</span>
                                            <div v-if="item.top_android_models && item.top_android_models.length > 0" 
                                                class="flex flex-wrap justify-center gap-1 mt-2 max-w-[250px]">
                                                <span v-for="model in item.top_android_models" :key="model"
                                                    class="text-[8px] font-black bg-emerald-500/5 text-emerald-500 px-1.5 py-0.5 rounded border border-emerald-500/10 whitespace-nowrap uppercase tracking-tighter">
                                                    {{ model }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6 border-b border-surface-800/50 text-right">
                                        <div class="flex flex-col items-end">
                                            <span class="text-lg font-black text-text-primary tabular-nums">{{ formatCurrency(item.omset) }}</span>
                                            <div class="h-1 w-24 bg-surface-800 rounded-full mt-2 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-primary-500 to-emerald-500 rounded-full transition-all duration-1000" 
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
                                        <p class="text-text-secondary font-medium uppercase tracking-widest text-xs">Tidak ada data cabang yang ditemukan.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
