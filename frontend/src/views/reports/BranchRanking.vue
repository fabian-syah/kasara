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
    <div class="p-4 md:p-6 space-y-8 max-w-7xl mx-auto overflow-x-hidden">
        <!-- Compact Header & Filters -->
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-primary-500/10 rounded-xl">
                    <Trophy class="w-6 h-6 text-primary-500" />
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-black text-text-primary tracking-tight leading-none uppercase">Ranking Performa</h1>
                    <p class="text-text-secondary text-[10px] md:text-xs font-bold mt-1.5 uppercase tracking-widest opacity-80">Cabang & Toko Online (Omset > 0)</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="flex bg-surface-800 p-1 rounded-xl border border-surface-700/50">
                    <button @click="setRange('today')" 
                        class="px-4 py-1.5 rounded-lg text-[10px] font-black transition-all"
                        :class="filters.start_date === filters.end_date && filters.start_date !== '' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        HARI INI
                    </button>
                    <button @click="setRange('month')" 
                        class="px-4 py-1.5 rounded-lg text-[10px] font-black transition-all"
                        :class="filters.start_date !== filters.end_date && filters.start_date !== '' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        BULAN INI
                    </button>
                    <button @click="setRange('all')" 
                        class="px-4 py-1.5 rounded-lg text-[10px] font-black transition-all"
                        :class="!filters.start_date ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        SEMUA
                    </button>
                </div>

                <div class="flex items-center gap-2 bg-surface-800 p-1 rounded-xl border border-surface-700/50">
                    <Calendar class="w-4 h-4 text-primary-500 ml-2" />
                    <input type="date" v-model="filters.start_date" class="bg-transparent text-[10px] text-text-primary outline-none font-bold uppercase w-28" />
                    <span class="text-surface-600 font-bold">-</span>
                    <input type="date" v-model="filters.end_date" class="bg-transparent text-[10px] text-text-primary outline-none font-bold uppercase w-28" />
                    <button @click="fetchRanking" class="p-1.5 bg-primary-500 hover:bg-primary-600 text-white rounded-lg transition-all">
                        <Filter class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-20">
            <div class="w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else class="space-y-12 animate-in">
            <!-- Podium Layout - Better Trophy Spacing -->
            <div v-if="top3.length > 0" class="flex flex-wrap items-center md:items-end justify-center gap-6 md:gap-4 lg:gap-10 pt-14 pb-8 px-6 bg-surface-800/10 rounded-3xl border border-surface-800/50 relative">
                
                <!-- Rank 2 -->
                <div v-if="top3[1]" class="order-2 md:order-1 flex flex-col items-center w-full md:w-[240px] lg:w-[280px]">
                    <div class="relative group">
                        <div class="relative w-20 h-20 lg:w-24 lg:h-24 rounded-2xl bg-surface-800 border-2 border-slate-400/30 flex items-center justify-center shadow-xl">
                             <component :is="top3[1].type === 'Offline' ? Store : Globe" class="w-8 h-8 lg:w-10 lg:h-10 text-slate-400" />
                             <div class="absolute -top-3 -right-3 w-8 h-8 bg-slate-400 text-surface-900 rounded-xl flex items-center justify-center font-black text-lg border-4 border-surface-900">2</div>
                        </div>
                    </div>
                    <div class="mt-6 text-center w-full px-2">
                        <h3 class="font-black text-base lg:text-lg text-text-primary truncate uppercase leading-tight">{{ top3[1].name }}</h3>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1.5">{{ top3[1].type }} UNIT</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg">
                            <span class="text-lg lg:text-xl font-black text-slate-400 tabular-nums">{{ formatCurrency(top3[1].omset) }}</span>
                        </div>
                        <div class="flex gap-4 justify-center mt-4 text-[10px]">
                            <div class="text-center"><p class="text-primary-400 font-bold uppercase mb-0.5">iPhone</p><p class="text-base font-black">{{ top3[1].iphone_count }}</p></div>
                            <div class="w-px h-8 bg-surface-700"></div>
                            <div class="text-center"><p class="text-emerald-400 font-bold uppercase mb-0.5">Android</p><p class="text-base font-black">{{ top3[1].android_count }}</p></div>
                        </div>
                    </div>
                </div>

                <!-- Rank 1 (Centered) -->
                <div v-if="top3[0]" class="order-1 md:order-2 flex flex-col items-center w-full md:w-[280px] lg:w-[340px]">
                    <div class="relative group">
                        <div class="relative w-28 h-28 lg:w-36 lg:h-36 rounded-[32px] bg-surface-800 border-4 border-primary-500/50 flex items-center justify-center shadow-2xl ring-4 ring-primary-500/5">
                             <component :is="top3[0].type === 'Offline' ? Store : Globe" class="w-12 h-12 lg:w-16 lg:h-16 text-primary-500" />
                             <div class="absolute -top-4 -right-4 w-12 h-12 bg-primary-500 text-white rounded-2xl flex items-center justify-center font-black text-2xl border-4 border-surface-900 shadow-xl">1</div>
                             <!-- Brought Trophy down to avoid cutting off -->
                             <div class="absolute -top-12 left-1/2 -translate-x-1/2 drop-shadow-[0_0_15px_rgba(234,179,8,0.5)]">
                                <Trophy class="w-10 h-10 text-primary-500 animate-bounce" />
                             </div>
                        </div>
                    </div>
                    <div class="mt-8 text-center w-full px-2">
                        <h3 class="font-black text-xl lg:text-2xl text-text-primary truncate uppercase leading-tight">{{ top3[0].name }}</h3>
                        <p class="text-[10px] font-black text-primary-500 uppercase tracking-[0.2em] mt-2">{{ top3[0].type }} UNIT</p>
                        <div class="mt-6 px-6 py-3 bg-primary-500 shadow-lg shadow-primary-500/20 rounded-2xl border-2 border-primary-400/50">
                            <span class="text-xl lg:text-2xl font-black text-white tabular-nums">{{ formatCurrency(top3[0].omset) }}</span>
                        </div>
                        <div class="flex gap-8 justify-center mt-6">
                            <div class="text-center group"><p class="text-xs font-black text-primary-400 uppercase mb-1">iPhone</p><p class="text-2xl font-black underline decoration-primary-500/30 underline-offset-4">{{ top3[0].iphone_count }}</p></div>
                            <div class="w-px h-10 bg-primary-500/20"></div>
                            <div class="text-center group"><p class="text-xs font-black text-emerald-400 uppercase mb-1">Android</p><p class="text-2xl font-black underline decoration-emerald-500/30 underline-offset-4">{{ top3[0].android_count }}</p></div>
                        </div>
                    </div>
                </div>

                <!-- Rank 3 -->
                <div v-if="top3[2]" class="order-3 flex flex-col items-center w-full md:w-[240px] lg:w-[280px]">
                    <div class="relative group">
                        <div class="relative w-16 h-16 lg:w-20 lg:h-20 rounded-2xl bg-surface-800 border-2 border-amber-700/30 flex items-center justify-center shadow-xl">
                             <component :is="top3[2].type === 'Offline' ? Store : Globe" class="w-6 h-6 lg:w-8 lg:h-8 text-amber-700" />
                             <div class="absolute -top-2.5 -right-2.5 w-8 h-8 bg-amber-700 text-surface-900 rounded-xl flex items-center justify-center font-black text-base border-4 border-surface-900">3</div>
                        </div>
                    </div>
                    <div class="mt-6 text-center w-full px-2">
                        <h3 class="font-black text-base text-text-primary truncate uppercase leading-tight">{{ top3[2].name }}</h3>
                        <p class="text-[9px] font-bold text-amber-700 uppercase tracking-widest mt-1.5">{{ top3[2].type }} UNIT</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg">
                            <span class="text-lg font-black text-amber-700 tabular-nums">{{ formatCurrency(top3[2].omset) }}</span>
                        </div>
                        <div class="flex gap-4 justify-center mt-4 text-[10px]">
                            <div class="text-center"><p class="text-primary-400 font-bold uppercase mb-0.5">iPhone</p><p class="text-base font-black">{{ top3[2].iphone_count }}</p></div>
                            <div class="w-px h-8 bg-surface-700"></div>
                            <div class="text-center"><p class="text-emerald-400 font-bold uppercase mb-0.5">Android</p><p class="text-base font-black">{{ top3[2].android_count }}</p></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Table -->
            <div class="space-y-5">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-1">
                    <h2 class="text-lg md:text-xl font-black text-text-primary tracking-tight flex items-center gap-2">
                        <TrendingUp class="w-5 h-5 text-emerald-500" />
                        KUALIFIKASI LENGKAP
                    </h2>
                    <div class="relative w-full sm:w-72">
                        <Search class="absolute left-3.5 w-4 h-4 text-text-secondary top-1/2 -translate-y-1/2" />
                        <input v-model="searchQuery" type="text" placeholder="Cari unit..." 
                            class="bg-surface-800 text-xs py-2.5 pl-10 pr-4 rounded-xl border border-surface-700 shadow-inner w-full outline-none focus:border-primary-500 transition-all" />
                    </div>
                </div>

                <div class="bg-surface-800/10 rounded-2xl border border-surface-800 overflow-hidden shadow-2xl">
                    <div class="overflow-x-auto scrollbar-thin">
                        <table class="w-full text-left border-collapse min-w-[850px]">
                            <thead>
                                <tr class="bg-surface-800/50">
                                    <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800">No</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800">Unit Bisnis</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800 text-center">iPhone</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800 text-center">Android & Terlaris</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-text-secondary uppercase tracking-widest border-b border-surface-800 text-right">Omset</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-800/50">
                                <tr v-for="(item, index) in filteredRanking" :key="item.id" class="group hover:bg-surface-800/30 transition-all">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-lg font-black text-xs"
                                            :class="{
                                                'bg-primary-500 text-white': index === 0,
                                                'bg-slate-400 text-surface-900': index === 1,
                                                'bg-amber-700 text-white': index === 2,
                                                'bg-surface-800 text-text-secondary border border-surface-700': index > 2
                                            }">{{ index + 1 }}</div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-surface-800 flex items-center justify-center border border-surface-700 shadow-inner">
                                                <component :is="item.type === 'Offline' ? Store : Globe" class="w-4 h-4" :class="item.type === 'Offline' ? 'text-primary-500' : 'text-blue-400'" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-black text-text-primary text-sm uppercase group-hover:text-primary-400 transition-colors">{{ item.name }}</span>
                                                <span class="text-[8px] font-black text-surface-600 uppercase">{{ item.type }} UNIT</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="text-base font-black text-text-primary font-mono bg-primary-500/5 px-3 py-0.5 rounded-lg border border-primary-500/10">{{ formatNumber(item.iphone_count) }}</span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col items-center">
                                            <span class="text-base font-black text-text-primary font-mono bg-emerald-500/5 px-3 py-0.5 rounded-lg border border-emerald-500/10">{{ formatNumber(item.android_count) }}</span>
                                            <div v-if="item.top_android_models && item.top_android_models.length > 0" class="flex flex-wrap justify-center gap-1 mt-2 max-w-[250px]">
                                                <span v-for="model in item.top_android_models" :key="model" class="text-[7px] font-black bg-surface-900 border border-surface-700/50 text-text-secondary px-1.5 py-0.5 rounded-md uppercase">{{ model }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <span class="text-base font-black text-text-primary tabular-nums tracking-tight group-hover:text-emerald-400">{{ formatCurrency(item.omset) }}</span>
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
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.scrollbar-thin::-webkit-scrollbar { height: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #1e1e1e; border-radius: 10px; }
</style>
