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

const top3 = computed(() => {
    return filteredRanking.value.slice(0, 3);
});

</script>

<template>
    <div class="p-3 md:p-6 space-y-8 md:space-y-12 max-w-7xl mx-auto overflow-x-hidden">
        <!-- Header Section -->
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-8">
            <div class="space-y-3 max-w-2xl">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-primary-500/10 rounded-2xl shrink-0 shadow-inner">
                        <Trophy class="w-7 h-7 text-primary-500" />
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-4xl font-black text-text-primary tracking-tight leading-none uppercase">Ranking Performa</h1>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="w-8 h-1 bg-primary-500 rounded-full"></span>
                            <span class="text-text-secondary text-[10px] md:text-xs font-black uppercase tracking-[0.2em]">Cabang & Toko Online</span>
                        </div>
                    </div>
                </div>
                <p class="text-text-secondary text-xs md:text-base leading-relaxed pl-1 max-w-xl">
                    Analisis peringkat unit bisnis berdasarkan total perolehan omset. <br class="hidden md:block" />
                    Data yang ditampilkan hanya untuk unit dengan performa aktif (Omset > 0).
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 w-full lg:w-auto">
                <!-- Preset Buttons -->
                <div class="flex bg-surface-800 p-1.5 rounded-2xl border border-surface-700/50 shadow-2xl w-full sm:w-auto">
                    <button @click="setRange('today')" 
                        class="flex-1 sm:flex-none px-5 py-2 rounded-xl text-[10px] font-black transition-all duration-300"
                        :class="filters.start_date === filters.end_date && filters.start_date !== '' ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary hover:bg-surface-700'">
                        HARI INI
                    </button>
                    <button @click="setRange('month')" 
                        class="flex-1 sm:flex-none px-5 py-2 rounded-xl text-[10px] font-black transition-all duration-300"
                        :class="filters.start_date !== filters.end_date && filters.start_date !== '' ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary hover:bg-surface-700'">
                        BULAN INI
                    </button>
                    <button @click="setRange('all')" 
                        class="flex-1 sm:flex-none px-5 py-2 rounded-xl text-[10px] font-black transition-all duration-300"
                        :class="!filters.start_date ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-text-primary hover:bg-surface-700'">
                        SEMUA
                    </button>
                </div>

                <!-- Date Range Picker -->
                <div class="flex items-center gap-3 bg-surface-800 p-2 rounded-2xl border border-surface-700/50 shadow-xl w-full sm:w-auto">
                    <Calendar class="w-4 h-4 text-primary-500 ml-2 shrink-0" />
                    <div class="flex items-center gap-2 flex-1 sm:flex-none min-w-0">
                        <input type="date" v-model="filters.start_date" class="bg-transparent text-[10px] md:text-xs text-text-primary outline-none font-bold w-full uppercase" />
                        <span class="text-surface-600 font-bold">-</span>
                        <input type="date" v-model="filters.end_date" class="bg-transparent text-[10px] md:text-xs text-text-primary outline-none font-bold w-full uppercase" />
                    </div>
                    <button @click="fetchRanking" class="p-2 bg-primary-500 hover:bg-primary-600 text-white rounded-xl transition-all duration-300 shadow-lg shadow-primary-500/20 hover:scale-105 active:scale-95 shrink-0">
                        <Filter class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <div class="h-px w-full bg-gradient-to-r from-transparent via-surface-700 to-transparent"></div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-32 space-y-6">
            <div class="relative w-24 h-24">
                <div class="absolute inset-0 border-8 border-primary-500/10 rounded-full"></div>
                <div class="absolute inset-0 border-8 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                <Trophy class="absolute inset-0 m-auto w-10 h-10 text-primary-500 animate-pulse" />
            </div>
            <div class="text-center">
                <p class="text-text-primary text-lg font-black tracking-widest uppercase">Menganalisis Performa</p>
                <p class="text-text-secondary text-sm font-medium mt-1">Harap tunggu sebentar...</p>
            </div>
        </div>

        <div v-else class="space-y-16 md:space-y-24 animate-in">
            <!-- Podium Centered Layout -->
            <div v-if="top3.length > 0" class="flex flex-wrap items-center md:items-end justify-center gap-8 md:gap-4 lg:gap-12 pt-12 pb-6 md:pb-12 px-6 bg-surface-800/10 rounded-[40px] border border-surface-800/50 relative overflow-hidden backdrop-blur-sm">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary-500/30 to-transparent"></div>
                
                <!-- Juara 2 -->
                <div v-if="top3[1]" class="order-2 md:order-1 flex flex-col items-center w-full md:w-[260px] lg:w-[320px]">
                    <div class="relative group">
                        <div class="absolute -inset-6 bg-slate-400/10 rounded-full blur-2xl group-hover:bg-slate-400/20 transition-all duration-500"></div>
                        <div class="relative w-24 h-24 lg:w-28 lg:h-28 rounded-3xl bg-surface-800 border-2 border-slate-400/30 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-2">
                             <component :is="top3[1].type === 'Offline' ? Store : Globe" class="w-10 h-10 lg:w-12 lg:h-12 text-slate-400" />
                             <div class="absolute -top-3 -right-3 w-10 h-10 bg-slate-400 text-surface-900 rounded-2xl flex items-center justify-center font-black text-xl border-4 border-surface-900 shadow-lg">2</div>
                        </div>
                    </div>
                    <div class="mt-8 text-center w-full">
                        <h3 class="font-black text-lg lg:text-xl text-text-primary line-clamp-1 px-4 leading-tight uppercase">{{ top3[1].name }}</h3>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-2">{{ top3[1].type }} UNIT</p>
                        <div class="mt-6 px-6 py-3 bg-surface-800/80 rounded-2xl border border-surface-700 shadow-xl mb-4">
                            <span class="text-xl lg:text-2xl font-black text-slate-400 tabular-nums">{{ formatCurrency(top3[1].omset) }}</span>
                        </div>
                        <div class="flex gap-6 justify-center">
                            <div class="text-center">
                                <p class="text-[9px] font-bold text-primary-400 uppercase mb-1">iPhone</p>
                                <p class="text-xl font-black text-text-primary">{{ top3[1].iphone_count }}</p>
                            </div>
                            <div class="w-px h-10 bg-surface-700"></div>
                            <div class="text-center">
                                <p class="text-[9px] font-bold text-emerald-400 uppercase mb-1">Android</p>
                                <p class="text-xl font-black text-text-primary">{{ top3[1].android_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block w-full h-12 mt-8 bg-gradient-to-t from-surface-800 to-transparent rounded-t-3xl border-x border-t border-surface-700/50"></div>
                </div>

                <!-- Juara 1 (Always middle/centered) -->
                <div v-if="top3[0]" class="order-1 md:order-2 flex flex-col items-center w-full md:w-[320px] lg:w-[400px]">
                    <div class="relative group mb-4">
                        <div class="absolute -inset-12 bg-primary-500/20 rounded-full blur-3xl group-hover:bg-primary-500/30 transition-all duration-700 animate-pulse"></div>
                        <div class="relative w-32 h-32 lg:w-44 lg:h-44 rounded-[40px] bg-surface-800 border-4 border-primary-500/50 flex items-center justify-center shadow-[0_20px_50px_rgba(0,0,0,0.5)] transition-transform hover:-translate-y-4 ring-8 ring-primary-500/5">
                             <component :is="top3[0].type === 'Offline' ? Store : Globe" class="w-16 h-16 lg:w-20 lg:h-20 text-primary-500" />
                             <div class="absolute -top-6 -right-6 w-14 h-14 lg:w-16 lg:h-16 bg-primary-500 text-white rounded-3xl flex items-center justify-center font-black text-3xl border-8 border-surface-900 shadow-2xl ring-4 ring-primary-500/20">1</div>
                             <div class="absolute -top-16 left-1/2 -translate-x-1/2">
                                <Trophy class="w-12 h-12 text-primary-500 animate-bounce drop-shadow-[0_0_15px_rgba(234,179,8,0.5)]" />
                             </div>
                        </div>
                    </div>
                    <div class="mt-8 text-center w-full">
                        <h3 class="font-black text-2xl lg:text-3xl text-text-primary line-clamp-1 px-4 leading-none tracking-tight uppercase">{{ top3[0].name }}</h3>
                        <p class="text-xs font-black text-primary-500 uppercase tracking-[0.3em] mt-3">{{ top3[0].type }} UNIT</p>
                        <div class="mt-8 px-10 py-5 bg-primary-500 shadow-2xl shadow-primary-500/20 rounded-[32px] border-2 border-primary-400/50 mb-6 group hover:scale-105 transition-transform">
                            <span class="text-2xl lg:text-3xl font-black text-white tabular-nums">{{ formatCurrency(top3[0].omset) }}</span>
                        </div>
                        <div class="flex gap-10 justify-center">
                            <div class="text-center group">
                                <p class="text-xs font-black text-primary-400 uppercase tracking-tighter mb-1">iPhone</p>
                                <p class="text-3xl font-black text-text-primary group-hover:scale-110 transition-transform underline decoration-primary-500/50 underline-offset-8">{{ top3[0].iphone_count }}</p>
                            </div>
                            <div class="w-px h-12 bg-primary-500/20"></div>
                            <div class="text-center group">
                                <p class="text-xs font-black text-emerald-400 uppercase tracking-tighter mb-1">Android</p>
                                <p class="text-3xl font-black text-text-primary group-hover:scale-110 transition-transform underline decoration-emerald-500/50 underline-offset-8">{{ top3[0].android_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block w-full h-20 mt-12 bg-gradient-to-t from-primary-500/20 to-transparent rounded-t-[40px] border-x border-t border-primary-500/30"></div>
                </div>

                <!-- Juara 3 -->
                <div v-if="top3[2]" class="order-3 flex flex-col items-center w-full md:w-[260px] lg:w-[320px]">
                    <div class="relative group">
                        <div class="absolute -inset-4 bg-amber-700/10 rounded-full blur-xl group-hover:bg-amber-700/20 transition-all duration-500"></div>
                        <div class="relative w-20 h-20 lg:w-24 lg:h-24 rounded-3xl bg-surface-800 border-2 border-amber-700/30 flex items-center justify-center shadow-2xl transition-transform hover:-translate-y-2">
                             <component :is="top3[2].type === 'Offline' ? Store : Globe" class="w-8 h-8 lg:w-10 lg:h-10 text-amber-700" />
                             <div class="absolute -top-3 -right-3 w-8 h-8 lg:w-10 lg:h-10 bg-amber-700 text-surface-900 rounded-xl flex items-center justify-center font-black text-lg border-4 border-surface-900 shadow-lg">3</div>
                        </div>
                    </div>
                    <div class="mt-8 text-center w-full">
                        <h3 class="font-black text-lg text-text-primary line-clamp-1 px-4 leading-tight uppercase">{{ top3[2].name }}</h3>
                        <p class="text-[9px] font-bold text-amber-700 uppercase tracking-widest mt-2">{{ top3[2].type }} UNIT</p>
                        <div class="mt-6 px-6 py-3 bg-surface-800/80 rounded-2xl border border-surface-700 shadow-xl mb-4">
                            <span class="text-xl font-black text-amber-700 tabular-nums">{{ formatCurrency(top3[2].omset) }}</span>
                        </div>
                        <div class="flex gap-6 justify-center">
                            <div class="text-center group">
                                <p class="text-[9px] font-bold text-primary-400 uppercase mb-1">iPhone</p>
                                <p class="text-lg font-black text-text-primary">{{ top3[2].iphone_count }}</p>
                            </div>
                            <div class="w-px h-8 bg-surface-700"></div>
                            <div class="text-center group">
                                <p class="text-[9px] font-bold text-emerald-400 uppercase mb-1">Android</p>
                                <p class="text-lg font-black text-text-primary">{{ top3[2].android_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block w-full h-8 mt-8 bg-gradient-to-t from-surface-800 to-transparent rounded-t-3xl border-x border-t border-surface-700/50"></div>
                </div>
            </div>

            <!-- List Section -->
            <div class="space-y-6">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-6 px-1">
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-8 bg-primary-500 rounded-full"></div>
                        <h2 class="text-xl md:text-2xl font-black text-text-primary tracking-tight">Kualifikasi Lengkap</h2>
                    </div>
                    <div class="relative group w-full sm:w-80">
                        <div class="absolute -inset-0.5 bg-primary-500/20 rounded-2xl blur opacity-0 group-focus-within:opacity-100 transition-opacity"></div>
                        <div class="relative flex items-center">
                            <Search class="absolute left-4 w-4 h-4 text-text-secondary group-focus-within:text-primary-500 transition-colors" />
                            <input v-model="searchQuery" type="text" placeholder="Cari unit atau tipe..." 
                                class="bg-surface-800 text-sm py-3.5 pl-12 pr-4 rounded-xl border border-surface-700/50 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none w-full shadow-lg transition-all" />
                        </div>
                    </div>
                </div>

                <div class="bg-surface-800/10 rounded-[32px] border border-surface-800/50 overflow-hidden shadow-2xl backdrop-blur-xl">
                    <div class="overflow-x-auto scrollbar-thin">
                        <table class="w-full text-left border-collapse min-w-[900px]">
                            <thead>
                                <tr class="bg-surface-800/50">
                                    <th class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800">Rank</th>
                                    <th class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800">Unit Bisnis</th>
                                    <th class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800 text-center">iPhone Sold</th>
                                    <th class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800 text-center">Android & Terlaris</th>
                                    <th class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800 text-right">Omset Perolehan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-800/50">
                                <tr v-for="(item, index) in filteredRanking" :key="item.id" 
                                    class="group hover:bg-surface-800/30 transition-all duration-300">
                                    <td class="px-8 py-8">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-xl font-black text-sm"
                                            :class="{
                                                'bg-primary-500 text-white shadow-xl shadow-primary-500/20 ring-4 ring-primary-500/10': index === 0,
                                                'bg-slate-400 text-surface-900': index === 1,
                                                'bg-amber-700 text-white': index === 2,
                                                'bg-surface-800 text-text-secondary border border-surface-700/50': index > 2
                                            }">
                                            {{ index + 1 }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-8">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-4">
                                                <div class="w-11 h-11 rounded-2xl bg-surface-800 flex items-center justify-center shrink-0 border border-surface-700 shadow-inner group-hover:scale-110 transition-transform">
                                                    <component :is="item.type === 'Offline' ? Store : Globe" class="w-5 h-5" :class="item.type === 'Offline' ? 'text-primary-500' : 'text-blue-400'" />
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="font-black text-text-primary text-base group-hover:text-primary-400 transition-colors uppercase tracking-tight">{{ item.name }}</span>
                                                    <div class="flex items-center gap-2 mt-0.5">
                                                        <span class="text-[9px] font-black px-2 py-0.5 rounded-full border tracking-widest uppercase"
                                                            :class="item.type === 'Offline' ? 'bg-amber-500/10 border-amber-500/20 text-amber-500' : 'bg-blue-500/10 border-blue-500/20 text-blue-400'">
                                                            {{ item.type }}
                                                        </span>
                                                        <span class="text-[9px] font-black text-surface-600 uppercase">Unit Performance</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-8 text-center">
                                        <div class="flex flex-col items-center">
                                            <span class="text-xl font-black text-text-primary font-mono bg-primary-500/5 px-4 py-1 rounded-xl border border-primary-500/10">{{ formatNumber(item.iphone_count) }}</span>
                                            <span class="text-[9px] font-black text-text-secondary uppercase mt-2">Units</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-8 items-center flex justify-center">
                                        <div class="flex flex-col items-center">
                                            <span class="text-xl font-black text-text-primary font-mono bg-emerald-500/5 px-4 py-1 rounded-xl border border-emerald-500/10">{{ formatNumber(item.android_count) }}</span>
                                            <div v-if="item.top_android_models && item.top_android_models.length > 0" 
                                                class="flex flex-wrap justify-center gap-1.5 mt-3 max-w-[300px]">
                                                <span v-for="model in item.top_android_models" :key="model"
                                                    class="text-[8px] font-black bg-surface-900 border border-surface-700/50 text-text-secondary px-2 py-1 rounded-lg uppercase tracking-tight">
                                                    {{ model }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-8 text-right">
                                        <div class="flex flex-col items-end">
                                            <span class="text-xl font-black text-text-primary tabular-nums tracking-tight group-hover:text-emerald-400 transition-colors">{{ formatCurrency(item.omset) }}</span>
                                            <div class="h-1.5 w-32 bg-surface-800 rounded-full mt-3 overflow-hidden shadow-inner">
                                                <div class="h-full bg-gradient-to-r from-primary-500 to-emerald-500 rounded-full transition-all duration-1000" 
                                                    :style="{ width: rankingData[0]?.omset > 0 ? (item.omset / rankingData[0].omset * 100) + '%' : '0%' }"></div>
                                            </div>
                                        </div>
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
    animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Custom Scrollbar */
.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #1e1e1e;
    border-radius: 10px;
}
</style>
