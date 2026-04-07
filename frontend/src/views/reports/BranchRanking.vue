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
    Globe,
    Crown,
    Flame,
    Loader2,
    Download,
    SortAsc,
    Eye,
    EyeOff
} from 'lucide-vue-next';
import { toJpeg } from 'html-to-image';
import { jsPDF } from 'jspdf';

const loading = ref(true);
const rankingData = ref([]);
const showZero = ref(false);
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
    } else if (type === 'yesterday') {
        const yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        filters.value.start_date = formatDateStr(yesterday);
        filters.value.end_date = formatDateStr(yesterday);
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

const activeRange = computed(() => {
    const today = new Date();
    const todayStr = formatDateStr(today);
    
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    const yesterdayStr = formatDateStr(yesterday);

    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const startOfMonthStr = formatDateStr(startOfMonth);

    if (!filters.value.start_date && !filters.value.end_date) return 'all';
    
    if (filters.value.start_date === filters.value.end_date) {
        if (filters.value.start_date === todayStr) return 'today';
        if (filters.value.start_date === yesterdayStr) return 'yesterday';
    }
    
    if (filters.value.start_date === startOfMonthStr && filters.value.end_date === todayStr) return 'month';
    
    return 'custom';
});

const fetchRanking = async () => {
    loading.value = true;
    try {
        const response = await api.get('/reports/ranking', {
            params: {
                start_date: filters.value.start_date,
                end_date: filters.value.end_date,
                include_zero: showZero.value ? 1 : 0
            }
        });
        rankingData.value = response.data;
    } catch (error) {
        console.error('Error fetching ranking report:', error);
    } finally {
        loading.value = false;
    }
};

const toggleShowZero = () => {
    showZero.value = !showZero.value;
    fetchRanking();
};

onMounted(() => {
    const today = new Date();
    filters.value.start_date = formatDateStr(today);
    filters.value.end_date = formatDateStr(today);
    fetchRanking();
});

const searchQuery = ref('');

const totalOmset = computed(() => {
    return filteredRanking.value.reduce((sum, item) => sum + (item.omset || 0), 0);
});

const top3 = computed(() => {
    return filteredRanking.value.slice(0, 3);
});

const sortBy = ref('omset'); // 'omset', 'name'

const filteredRanking = computed(() => {
    let result = [...rankingData.value];
    
    // Filter
    if (searchQuery.value) {
        const search = searchQuery.value.toLowerCase();
        result = result.filter(item =>
            item.name.toLowerCase().includes(search) ||
            item.type.toLowerCase().includes(search)
        );
    }
    
    // Sort
    if (sortBy.value === 'omset') {
        result.sort((a, b) => b.omset - a.omset);
    } else {
        result.sort((a, b) => a.name.localeCompare(b.name));
    }
    
    return result;
});

const exportLoading = ref(false);
const exportPart = ref(0); // 0: none, 1: part 1 (Podium + 1-20), 2: part 2 (21-end)
const exportRef = ref(null);

const exportToPDF = async () => {
    if (!exportRef.value) return;
    exportLoading.value = true;
    
    // Ensure we are scrolled to top for correct capture
    window.scrollTo(0, 0);

    const pdf = new jsPDF('p', 'mm', 'a4');
    const pageWidth = pdf.internal.pageSize.getWidth();

    const runExport = async (part, isFirst = false) => {
        exportPart.value = part;
        // Give time for layout to adapt
        await new Promise(r => setTimeout(r, 800));
        
        try {
            const el = exportRef.value;
            // Use JPEG for MUCH smaller file size (100MB -> few MB)
            const dataUrl = await toJpeg(el, { 
                backgroundColor: '#ffffff',
                quality: 0.95, // Higher quality
                pixelRatio: 2, // Crisper rendering
                width: 1100,
                includeQueryParams: true,
                cacheBust: true,
                style: { 
                    padding: '80px 60px', 
                    background: '#ffffff',
                    width: '1100px',
                    maxWidth: 'none',
                    margin: '0',
                    display: 'flex',
                    flexDirection: 'column'
                }
            });

            const imgProps = pdf.getImageProperties(dataUrl);
            const pdfPageHeight = (imgProps.height * pageWidth) / imgProps.width;

            if (!isFirst) {
                pdf.addPage();
            }

            // Using FAST compression
            pdf.addImage(dataUrl, 'JPEG', 0, 0, pageWidth, pdfPageHeight, undefined, 'FAST');
        } catch (e) { 
            console.error('PDF Export part error:', e); 
        }
    };

    // Export Part 1
    await runExport(1, true);

    // Export Part 2 if there's more than 20 items
    if (rankingData.value.length > 20) {
        await runExport(2, false);
    }
    
    pdf.save(`peringkat-omzet-${formatDateStr(new Date())}.pdf`);
    
    exportPart.value = 0;
    exportLoading.value = false;
};

</script>

<template>
    <div :class="[
        'transition-all duration-300',
        exportPart === 0 ? 'p-3 md:p-6 space-y-6 md:space-y-8 max-w-7xl mx-auto' : 'absolute top-0 left-0 bg-white min-w-max z-[100] pb-20 origin-top-left'
    ]">
        <!-- Compact Header & Filters -->
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
            <div class="flex items-center gap-4 shrink-0">
                <div class="p-2.5 bg-primary-500/10 rounded-xl">
                    <Trophy class="w-6 h-6 text-primary-500" />
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-black text-text-primary tracking-tight leading-none uppercase">
                        Ranking Performa</h1>
                    <p
                        class="text-text-secondary text-[10px] md:text-xs font-bold mt-1.5 uppercase tracking-widest opacity-80">
                        Cabang & Toko Online (Seluruh Data)</p>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-center gap-4 w-full xl:w-auto">
                <div class="flex flex-wrap lg:flex-nowrap items-center gap-3">
                    <!-- Quick Presets -->
                    <div class="flex flex-wrap bg-surface-800 p-1 rounded-xl border border-surface-700/50 w-full sm:w-auto">
                        <button @click="setRange('today')" :disabled="loading"
                            class="px-4 py-2 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 flex-grow"
                            :class="activeRange === 'today' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                            <Loader2 v-if="loading && activeRange === 'today'" class="w-2.5 h-2.5 animate-spin" />
                            HARI INI
                        </button>
                        <button @click="setRange('yesterday')" :disabled="loading"
                            class="px-4 py-2 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 flex-grow"
                            :class="activeRange === 'yesterday' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                            <Loader2 v-if="loading && activeRange === 'yesterday'" class="w-2.5 h-2.5 animate-spin" />
                            KEMARIN
                        </button>
                        <button @click="setRange('month')" :disabled="loading"
                            class="px-4 py-2 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 flex-grow"
                            :class="activeRange === 'month' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                            <Loader2 v-if="loading && activeRange === 'month'" class="w-2.5 h-2.5 animate-spin" />
                            BULAN INI
                        </button>
                        <button @click="setRange('all')" :disabled="loading"
                            class="px-4 py-2 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 flex-grow"
                            :class="activeRange === 'all' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                            <Loader2 v-if="loading && activeRange === 'all'" class="w-2.5 h-2.5 animate-spin" />
                            SEMUA
                        </button>
                    </div>

                    <!-- Date Range Input -->
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 bg-surface-800 p-1 rounded-xl border border-surface-700/50 w-full sm:w-auto">
                        <div class="flex items-center flex-1 px-2 gap-2 min-w-[240px]">
                            <Calendar class="w-4 h-4 text-primary-500 shrink-0" />
                            <input type="date" v-model="filters.start_date"
                                class="bg-transparent text-[10px] text-text-primary outline-none font-bold uppercase w-full" />
                            <span class="text-surface-600 font-bold">-</span>
                            <input type="date" v-model="filters.end_date"
                                class="bg-transparent text-[10px] text-text-primary outline-none font-bold uppercase w-full" />
                        </div>
                        <button @click="fetchRanking" :disabled="loading"
                            class="w-full sm:w-auto px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg transition-all flex items-center justify-center gap-2 font-black text-[10px] uppercase whitespace-nowrap">
                            <Loader2 v-if="loading" class="w-3.5 h-3.5 animate-spin" />
                            <span v-else class="flex items-center gap-2 uppercase">
                                <Filter class="w-3.5 h-3.5" />
                                Terapkan
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Export Buttons -->
                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto pb-1 lg:pb-0">
                    <!-- Toggle Zero Omset (Only for Today/Yesterday) -->
                    <button v-if="activeRange === 'today' || activeRange === 'yesterday'"
                        @click="toggleShowZero"
                        class="flex-1 lg:flex-none px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 font-black text-[10px] uppercase whitespace-nowrap border"
                        :class="showZero ? 'bg-orange-500/10 border-orange-500 text-orange-500' : 'bg-surface-800 border-surface-700 text-text-secondary hover:text-text-primary'">
                        <Eye v-if="!showZero" class="w-3.5 h-3.5" />
                        <EyeOff v-else class="w-3.5 h-3.5" />
                        <span>{{ showZero ? 'Sembunyikan Kosong' : 'Tampilkan Belum Ada Penjualan' }}</span>
                    </button>

                    <button @click="exportToPDF" :disabled="loading || exportLoading || rankingData.length === 0"
                        class="flex-1 lg:flex-none px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white shadow-lg shadow-primary-500/10 rounded-xl transition-all flex items-center justify-center gap-2 font-black text-[10px] uppercase disabled:opacity-50 whitespace-nowrap">
                        <Download v-if="!exportLoading" class="w-3.5 h-3.5" />
                        <Loader2 v-else class="w-3.5 h-3.5 animate-spin" />
                        <span>Export PDF Report</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-20">
            <div class="w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div ref="exportRef" class="space-y-12" :class="exportPart > 0 ? 'w-[1100px] is-exporting-pdf py-20 px-8' : ''">
            <!-- HEADER KHUSUS PART 2 -->
            <div v-show="exportPart === 2" class="text-center py-6 border-b border-surface-800 mb-8">
                <h2 class="text-3xl font-black text-primary-500 uppercase tracking-[0.2em]">Lanjutan Ranking</h2>
                <p class="text-text-secondary text-xs font-bold mt-2 uppercase tracking-widest">Halaman 2 / Selesai</p>
            </div>

            <!-- Search & Sort Row (HIDE IN EXPORT) -->
            <div v-show="exportPart === 0" class="flex flex-col md:flex-row items-center gap-6">
                <!-- Search Bar -->
                <div class="relative group w-full md:flex-1">
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-primary-600 to-primary-400 rounded-[22px] blur opacity-25 group-hover:opacity-40 transition duration-1000 group-hover:duration-200">
                    </div>
                    <div class="relative flex items-center bg-surface-800 rounded-[18px] border border-surface-700/50">
                        <Search class="absolute left-5 w-5 h-5 text-surface-400" />
                        <input type="text" v-model="searchQuery" placeholder="Cari nama cabang atau tipe..."
                            class="w-full bg-transparent text-text-primary px-14 py-4.5 rounded-[18px] outline-none placeholder:text-surface-500 font-bold uppercase tracking-widest text-[11px]" />
                    </div>
                </div>

                <!-- Alphabet Filter -->
                <div class="flex w-full md:w-auto bg-surface-800 p-1 rounded-2xl border border-surface-700/50 shrink-0 overflow-x-auto no-scrollbar">
                    <button @click="sortBy = 'omset'"
                        class="flex-1 md:flex-none px-4 lg:px-6 py-2.5 rounded-xl text-[10px] font-black transition-all flex items-center justify-center gap-2 whitespace-nowrap"
                        :class="sortBy === 'omset' ? 'bg-primary-500 text-white shadow-xl shadow-primary-500/10' : 'text-text-secondary hover:text-text-primary'">
                        <TrendingUp class="w-3.5 h-3.5" />
                        OMSET TERBANYAK
                    </button>
                    <button @click="sortBy = 'name'"
                        class="flex-1 md:flex-none px-4 lg:px-6 py-2.5 rounded-xl text-[10px] font-black transition-all flex items-center justify-center gap-2 whitespace-nowrap"
                        :class="sortBy === 'name' ? 'bg-indigo-500 text-white shadow-xl shadow-indigo-500/10' : 'text-text-secondary hover:text-text-primary'">
                        <SortAsc class="w-3.5 h-3.5" />
                        ABJAD (A-Z)
                    </button>
                </div>
            </div>
            <!-- Podium Layout (HIDE IN PART 2) -->
            <div v-if="top3.length > 0 && exportPart !== 2"
                class="flex flex-col lg:flex-row items-center lg:items-end justify-center gap-10 lg:gap-4 xl:gap-14 pt-16 pb-12 px-6 relative bg-surface-800/5 rounded-[40px] overflow-hidden border border-surface-800/50">
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-primary-500/20 to-transparent">
                </div>

                <!-- Juara 2 -->
                <div v-if="top3[1]"
                    class="order-2 lg:order-1 flex flex-col items-center w-full lg:flex-1 max-w-[285px]">
                    <div class="relative group">
                        <div
                            class="absolute -inset-4 bg-slate-400/5 rounded-full blur-xl group-hover:bg-slate-400/10 transition-all">
                        </div>
                        <div
                            class="relative w-20 h-20 lg:w-24 lg:h-24 rounded-2xl bg-surface-800 border-2 border-slate-400/30 flex items-center justify-center shadow-xl">
                            <component :is="top3[1].type === 'Offline' ? Store : Globe"
                                class="w-8 h-8 lg:w-10 lg:h-10 text-slate-400" />
                            <div
                                class="absolute -top-3 -right-3 w-8 h-8 bg-slate-400 text-surface-900 rounded-xl flex items-center justify-center font-black text-lg border-4 border-surface-900">
                                2</div>
                        </div>
                    </div>
                    <div class="mt-6 text-center w-full px-2">
                        <h3 class="font-black text-base lg:text-lg text-text-primary truncate uppercase leading-tight">
                            {{ top3[1].name }}</h3>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1.5">{{ top3[1].type
                            }} UNIT</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg"
                            :class="{ '!bg-white !border-gray-200': exportPart > 0 }">
                            <span class="text-lg lg:text-xl font-black text-slate-400 tabular-nums"
                                :class="{ 'export-override-slate': exportPart > 0 }">{{
                                formatCurrency(top3[1].omset) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Juara 1 (THE KING - UNIK & BEDA) -->
                <div v-if="top3[0]"
                    class="order-1 lg:order-2 flex flex-col items-center w-full lg:w-[350px] xl:w-[400px] relative shrink-0">
                    <!-- BACKGROUND GLOW PULSE -->
                    <div class="absolute inset-0 bg-primary-500/10 blur-[100px] rounded-full animate-pulse-slow"></div>

                    <div class="relative group mb-4">
                        <!-- SHINY EFFECT -->
                        <div
                            class="absolute inset-0 bg-gradient-to-tr from-primary-500/0 via-white/20 to-primary-500/0 opacity-0 group-hover:animate-shine pointer-events-none rounded-[40px]">
                        </div>

                        <div
                            class="relative w-32 h-32 lg:w-44 lg:h-44 rounded-[40px] bg-surface-800 border-4 border-primary-500 flex items-center justify-center shadow-[0_0_50px_rgba(245,158,11,0.25)] transition-all hover:scale-105 duration-500 ring-8 ring-primary-500/5 overflow-visible">
                            <component :is="top3[0].type === 'Offline' ? Store : Globe"
                                class="w-16 h-16 lg:w-20 lg:h-20 text-primary-500" />

                            <!-- Floater Badge 1 -->
                            <div
                                class="absolute -top-6 -right-6 w-14 h-14 lg:w-16 lg:h-16 bg-primary-500 text-white rounded-[20px] flex items-center justify-center font-black text-3xl border-8 border-surface-900 shadow-2xl animate-bounce-slow">
                                1</div>

                            <!-- TOP BADGE -->
                            <div class="absolute -top-12 left-1/2 -translate-x-1/2 flex flex-col items-center">
                                <Crown
                                    class="w-10 h-10 text-primary-500 fill-primary-500 drop-shadow-[0_0_15px_rgba(245,158,11,0.5)] animate-bounce" />
                            </div>

                            <!-- KING OF SALES LABEL -->
                            <div
                                class="absolute -bottom-4 left-1/2 -translate-x-1/2 bg-primary-500 text-white text-[9px] font-black px-4 py-1.5 rounded-full shadow-lg border-2 border-surface-900 whitespace-nowrap uppercase tracking-[0.2em] z-10">
                                THE KING OF SALES
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 text-center w-full px-2 relative z-10">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <Flame class="w-4 h-4 text-primary-500 fill-primary-500 animate-pulse" />
                            <h3
                                class="font-black text-2xl lg:text-3xl text-text-primary uppercase tracking-tight leading-none">
                                {{ top3[0].name }}</h3>
                            <Flame class="w-4 h-4 text-primary-500 fill-primary-500 animate-pulse" />
                        </div>
                        <p class="text-xs font-black text-primary-500 uppercase tracking-[0.3em] mt-3">WINNER OF THE
                            PERIOD</p>

                        <div class="mt-8 relative group cursor-default">
                            <div
                                class="absolute -inset-4 bg-primary-500/20 blur-xl opacity-0 group-hover:opacity-100 transition-opacity rounded-full">
                            </div>
                            <div
                                class="relative px-12 py-6 bg-gradient-to-br from-primary-500 to-primary-600 shadow-[0_15px_35px_rgba(245,158,11,0.3)] rounded-[32px] border-4 border-white/20 overflow-hidden group">
                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:animate-shine-fast">
                                </div>
                                <p
                                    class="text-white/70 text-[10px] font-black uppercase tracking-widest mb-1.5 leading-none">
                                    Total Omset Perolehan</p>
                                <span
                                    class="text-2xl lg:text-4xl font-black text-white tabular-nums drop-shadow-md drop-shadow-primary-900/50 leading-none"
                                    :class="{ 'export-override-white': exportPart > 0 }">{{
                                    formatCurrency(top3[0].omset) }}</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Juara 3 -->
                <div v-if="top3[2]" class="order-3 flex flex-col items-center w-full lg:flex-1 max-w-[285px]">
                    <div class="relative group">
                        <div
                            class="absolute -inset-4 bg-amber-700/5 rounded-full blur-xl group-hover:bg-amber-700/10 transition-all">
                        </div>
                        <div
                            class="relative w-16 h-16 lg:w-20 lg:h-20 rounded-2xl bg-surface-800 border-2 border-amber-700/30 flex items-center justify-center shadow-xl">
                            <component :is="top3[2].type === 'Offline' ? Store : Globe"
                                class="w-6 h-6 lg:w-8 lg:h-8 text-amber-700" />
                            <div
                                class="absolute -top-2.5 -right-2.5 w-8 h-8 bg-amber-700 text-surface-900 rounded-xl flex items-center justify-center font-black text-base border-4 border-surface-900">
                                3</div>
                        </div>
                    </div>
                    <div class="mt-6 text-center w-full px-2">
                        <h3 class="font-black text-base text-text-primary truncate uppercase leading-tight">{{
                            top3[2].name }}</h3>
                        <p class="text-[9px] font-bold text-amber-700 uppercase tracking-widest mt-1.5">{{ top3[2].type
                            }} UNIT</p>
                        <div class="mt-4 px-4 py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg"
                            :class="{ '!bg-white !border-gray-200': exportPart > 0 }">
                            <span class="text-lg font-black text-amber-700 tabular-nums"
                                :class="{ 'export-override-slate': exportPart > 0 }">{{
                                formatCurrency(top3[2].omset) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Table -->
            <div class="space-y-6">
                <!-- Similar styling for table... -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-1">
                    <h2
                        class="text-lg md:text-xl font-black text-text-primary tracking-tight flex items-center gap-2 uppercase">
                        <TrendingUp class="w-5 h-5 text-emerald-500" />
                        Kualifikasi Lengkap
                    </h2>
                    <div class="relative w-full sm:w-72">
                        <Search class="absolute left-3.5 w-4 h-4 text-text-secondary top-1/2 -translate-y-1/2" />
                        <input v-model="searchQuery" type="text" placeholder="Cari unit..."
                            class="bg-surface-800 text-xs py-2.5 pl-10 pr-4 rounded-xl border border-surface-700 shadow-inner w-full outline-none focus:border-primary-500 transition-all font-bold" />
                    </div>
                </div>

                <div
                    class="bg-surface-800/10 rounded-3xl border border-surface-800 shadow-2xl relative"
                    :class="exportPart > 0 ? '!overflow-visible' : 'overflow-hidden'">
                    <div :class="exportPart > 0 ? '!overflow-visible' : 'overflow-x-auto no-scrollbar'">
                        <table class="w-full text-left border-collapse min-w-[700px] md:min-w-[900px]">
                            <thead>
                                <tr class="bg-surface-800/50">
                                    <th
                                        class="px-4 md:px-8 py-4 md:py-6 text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] border-b border-surface-800">
                                        No</th>
                                    <th
                                        class="px-4 md:px-8 py-4 md:py-6 text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] border-b border-surface-800">
                                        Unit Bisnis</th>
<!-- 
                                    <th
                                        class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] border-b border-surface-800 text-center">
                                        iPhone Unit</th>
                                    <th
                                        class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] border-b border-surface-800 text-center">
                                        Android & Terlaris</th>
-->
                                    <th
                                        class="px-4 md:px-8 py-4 md:py-6 text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] border-b border-surface-800 text-right">
                                        Hasil Omset</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-800/50">
                                <tr v-for="(item, index) in filteredRanking" :key="item.type + '-' + item.id"
                                    v-show="exportPart === 0 || (exportPart === 1 && index < 20) || (exportPart === 2 && index >= 20)"
                                    class="group hover:bg-surface-800/30 transition-all duration-300">
                                    <td class="px-4 md:px-8 py-5 md:py-7">
                                        <div class="flex items-center justify-center w-8 h-8 md:w-9 md:h-9 rounded-xl font-black text-xs md:text-sm"
                                            :class="{
                                                'bg-primary-500 text-white shadow-xl shadow-primary-500/20': index === 0,
                                                'bg-slate-400 text-surface-900': index === 1,
                                                'bg-amber-700 text-white': index === 2,
                                                'bg-surface-800 text-text-secondary border border-surface-700': index > 2
                                            }">{{ index + 1 }}</div>
                                    </td>
                                    <td class="px-4 md:px-8 py-5 md:py-7">
                                        <div class="flex items-center gap-3 md:gap-4">
                                            <div
                                                class="w-9 h-9 md:w-10 md:h-10 rounded-2xl bg-surface-800 flex items-center justify-center border border-surface-700 shadow-inner group-hover:scale-110 transition-transform shrink-0">
                                                <component :is="item.type === 'Offline' ? Store : Globe" class="w-4 h-4 md:w-5 md:h-5"
                                                    :class="item.type === 'Offline' ? 'text-primary-500' : 'text-blue-400'" />
                                            </div>
                                            <div class="flex flex-col min-w-0">
                                                <span
                                                    class="font-black text-text-primary text-xs md:text-sm uppercase group-hover:text-primary-400 transition-colors tracking-tight truncate">{{
                                                    item.name }}</span>
                                                <span
                                                    class="text-[8px] font-black text-surface-600 uppercase tracking-widest">{{
                                                    item.type }} UNIT</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 md:px-8 py-5 md:py-7 text-right">
                                        <span v-if="item.omset > 0"
                                            class="text-base md:text-lg font-black text-text-primary tabular-nums tracking-tight group-hover:text-emerald-400 transition-colors"
                                            :class="{ 'export-override-green': exportPart > 0 }">
                                            {{ formatCurrency(item.omset) }}
                                        </span>
                                        <span v-else class="text-[10px] md:text-sm font-bold text-orange-500 uppercase italic opacity-70">
                                            Belum ada penjualan
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="filteredRanking.length > 0 && (exportPart === 0 || exportPart === 2 || (exportPart === 1 && filteredRanking.length <= 20))">
                                <tr class="bg-surface-800/50 border-t border-surface-700">
                                    <td colspan="2" class="px-8 py-6 text-sm font-black text-text-primary uppercase tracking-widest text-right">
                                        TOTAL KESELURUHAN OMSET
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <span class="text-xl font-black text-primary-500 tabular-nums tracking-tighter drop-shadow-sm">
                                            {{ formatCurrency(totalOmset) }}
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
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

@keyframes shine {
    0% {
        transform: translateX(-150%) skewX(-30deg);
        opacity: 0;
    }

    50% {
        opacity: 0.5;
    }

    100% {
        transform: translateX(150%) skewX(-30deg);
        opacity: 0;
    }
}

@keyframes shine-fast {
    0% {
        transform: translateX(-150%) skewX(-20deg);
    }

    100% {
        transform: translateX(150%) skewX(-20deg);
    }
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.is-exporting-pdf {
    width: 1100px !important;
    max-width: none !important;
    background: #ffffff !important;
}

/* Sesuai saran user: ganti inherit dengan pendekatan spesifik */
.is-exporting-pdf .text-text-primary {
    color: #333333 !important; /* Jangan #000 pekat banget, abu tua gelap lebih natural */
}

.is-exporting-pdf .text-text-secondary {
    color: #4b5563 !important; /* warna abu tua biar kebaca */
}

/* Specific overrides for omzet values during export */
.export-override-white {
    color: #ffffff !important;
}

.export-override-slate {
    color: #64748b !important;
}

.export-override-green {
    color: #10b981 !important;
}


.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.animate-shine {
    animation: shine 2s infinite ease-in-out;
}

.group-hover\:animate-shine-fast {
    animation: shine-fast 0.8s ease-in-out;
}

@keyframes pulse-slow {

    0%,
    100% {
        opacity: 0.1;
        transform: scale(1);
    }

    50% {
        opacity: 0.3;
        transform: scale(1.1);
    }
}

.animate-pulse-slow {
    animation: pulse-slow 4s infinite ease-in-out;
}

@keyframes bounce-slow {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-10px);
    }
}

.animate-bounce-slow {
    animation: bounce-slow 2s infinite ease-in-out;
}

.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #1e1e1e;
    border-radius: 10px;
}
</style>
