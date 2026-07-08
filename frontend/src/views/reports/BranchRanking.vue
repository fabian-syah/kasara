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
    EyeOff,
    FileSpreadsheet
} from 'lucide-vue-next';
import { toJpeg } from 'html-to-image';
import { jsPDF } from 'jspdf';

import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const loading = ref(true);
const rankingData = ref([]);
const showZero = ref(false);
const filters = ref({
    start_date: '',
    end_date: ''
});

const getLogicalDate = () => {
    const now = new Date();
    if (now.getHours() < 5) now.setDate(now.getDate() - 1);
    return now;
};

const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    // Include both spellings for reliability
    return !['super_admin', 'audit', 'owner', 'leader', 'analist', 'analis', 'admin_produk'].some(r => role.includes(r));
});

const getTodayLocal = () => {
    const d = getLogicalDate();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const getMinDate = computed(() => {
    if (!isRestricted.value) return null;
    const d = getLogicalDate();
    d.setDate(d.getDate() - 7); // Allow past 7 days
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
});

const formatDateStr = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const setRange = (type) => {
    const logicalToday = getLogicalDate();

    if (type === 'today') {
        filters.value.start_date = getTodayLocal();
        filters.value.end_date = getTodayLocal();
    } else if (type === 'yesterday') {
        const yesterday = new Date(logicalToday);
        yesterday.setDate(logicalToday.getDate() - 1);
        filters.value.start_date = formatDateStr(yesterday);
        filters.value.end_date = formatDateStr(yesterday);
    } else if (type === 'month') {
        const startOfMonth = new Date(logicalToday.getFullYear(), logicalToday.getMonth(), 1);
        filters.value.start_date = formatDateStr(startOfMonth);
        filters.value.end_date = getTodayLocal();
    } else if (type === 'all') {
        filters.value.start_date = '';
        filters.value.end_date = '';
    }
    fetchRanking();
};

const rangeLabels = {
    'today': 'Hari Ini',
    'yesterday': 'Kemarin',
    'month': 'Bulan Ini',
    'all': 'Semua Waktu'
};

const activeRangeLabel = computed(() => {
    const range = activeRange.value;
    if (rangeLabels[range]) return rangeLabels[range];
    if (filters.value.start_date && filters.value.start_date === filters.value.end_date) return filters.value.start_date;
    return `${filters.value.start_date || 'Awal'} - ${filters.value.end_date || 'Akhir'}`;
});

const activeRange = computed(() => {
    const todayStr = getTodayLocal();
    
    const logicalToday = getLogicalDate();
    const yesterday = new Date(logicalToday);
    yesterday.setDate(logicalToday.getDate() - 1);
    const yesterdayStr = formatDateStr(yesterday);

    const startOfMonth = new Date(logicalToday.getFullYear(), logicalToday.getMonth(), 1);
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
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;
    fetchRanking();
});

const searchQuery = ref('');

const totalOmset = computed(() => {
    return filteredRanking.value.reduce((sum, item) => sum + (item.omset || 0), 0);
});

const totalOmsetBersih = computed(() => {
    return filteredRanking.value.reduce((sum, item) => sum + (item.omset_bersih || 0), 0);
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

const displayRanking = computed(() => {
    const branches = [];
    const shops = [];

    filteredRanking.value.forEach(item => {
        if (item.type === 'branch' || item.type === 'Offline') branches.push({ ...item });
        else shops.push({ ...item });
    });
    
    branches.forEach((b, idx) => b.localRank = idx + 1);
    shops.forEach((s, idx) => s.localRank = idx + 1);

    if (shops.length > 0) {
        return [...branches, { isSeparator: true, name: 'Kategori Toko Online', id: 'sep-1', type: 'separator' }, ...shops];
    }
    
    return branches;
});

const exportLoading = ref(false);
const exportExcelLoading = ref(false);
const exportPart = ref(0); // 0: none, 1...
const exportRef = ref(null);

const currentExportData = computed(() => {
    if (exportPart.value === 0) return displayRanking.value;
    const rowsPerPage = 10;
    const start = (exportPart.value - 1) * rowsPerPage;
    return displayRanking.value.slice(start, start + rowsPerPage);
});

const exportToExcel = async () => {
    exportExcelLoading.value = true;
    try {
        const response = await api.get('/reports/ranking/export-excel', {
            params: {
                start_date: filters.value.start_date,
                end_date: filters.value.end_date,
                include_zero: showZero.value ? 1 : 0,
                sort_by: sortBy.value
            },
            responseType: 'blob'
        });
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const fileName = `Ranking_Performa_${filters.value.start_date || 'All'}.xlsx`;
        link.setAttribute('download', fileName);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error exporting to Excel:', error);
    } finally {
        exportExcelLoading.value = false;
    }
};

const exportToPDF = async () => {
    if (!exportRef.value) return;
    exportLoading.value = true;
    
    window.scrollTo(0, 0);
    const isDark = document.documentElement.classList.contains('dark');

    const pdf = new jsPDF('p', 'mm', 'a4');
    const pageWidth = pdf.internal.pageSize.getWidth();

    const runExport = async (part, isFirst = false) => {
        exportPart.value = part;
        await new Promise(r => setTimeout(r, 1200));
        
        try {
            const el = exportRef.value;
            const dataUrl = await toJpeg(el, { 
                quality: 0.95,
                pixelRatio: 2,
                width: 1100,
                backgroundColor: isDark ? '#0f172a' : '#f8fafc',
                style: { 
                    width: '1100px',
                    maxWidth: 'none',
                    margin: '0',
                    display: 'flex',
                    flexDirection: 'column'
                }
            });

            const imgProps = pdf.getImageProperties(dataUrl);
            let pdfPageHeight = (imgProps.height * pageWidth) / imgProps.width;

            if (!isFirst) {
                pdf.addPage();
            }

            const a4Height = pdf.internal.pageSize.getHeight();
            let finalWidth = pageWidth;
            let finalHeight = pdfPageHeight;
            let xOffset = 0;

            if (pdfPageHeight > a4Height) {
                const ratio = a4Height / pdfPageHeight;
                finalHeight = a4Height;
                finalWidth = pageWidth * ratio;
                xOffset = (pageWidth - finalWidth) / 2;
            }

            pdf.addImage(dataUrl, 'JPEG', xOffset, 0, finalWidth, finalHeight, undefined, 'FAST');
        } catch (e) { 
            console.error('PDF Export part error:', e); 
        }
    };

    const totalItems = displayRanking.value.length;
    let totalPages = 1;
    if (totalItems > 10) {
        totalPages = 1 + Math.ceil((totalItems - 10) / 12);
    }

    for (let p = 1; p <= totalPages; p++) {
        await runExport(p, p === 1);
    }
    
    pdf.save(`Laporan-Omzet-${activeRangeLabel.value.replace(/ /g, '-')}.pdf`);
    
    exportPart.value = 0;
    exportLoading.value = false;
};

</script>

<template>
    <!-- EXPORT LOADING OVERLAY (Existing) -->
    <div v-if="exportLoading" class="fixed inset-0 z-[9999] bg-surface-900/98 backdrop-blur-xl flex flex-col items-center justify-center p-6 text-center">
        <!-- ... existing export overlay content ... -->
    </div>

    <div :class="[
        'transition-all duration-300 relative',
        exportPart === 0 ? 'p-4 md:p-8 space-y-8 max-w-7xl mx-auto pb-32' : 'absolute top-0 left-0 bg-surface-50 dark:bg-surface-900 min-w-max z-[100] pt-8 pb-20 origin-top-left',
        document?.documentElement?.classList?.contains('dark') ? 'dark' : ''
    ]">
        <!-- PREMIUM HEADER & FILTERS -->
        <div class="flex flex-col space-y-8 animate-in active" v-show="exportPart === 0">
            <!-- Header Row -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="flex items-center gap-4 md:gap-5">
                    <div class="relative group">
                        <div class="absolute -inset-2 bg-emerald-500/20 rounded-2xl blur-lg group-hover:bg-emerald-500/30 transition-all"></div>
                        <div class="relative p-3 md:p-4 bg-emerald-500/10 rounded-2xl border border-emerald-500/20">
                            <Trophy class="w-6 h-6 md:w-8 md:h-8 text-emerald-500" />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="h-px w-4 md:w-6 bg-emerald-500/50"></div>
                            <span class="text-[8px] md:text-[10px] font-black text-emerald-500 uppercase tracking-[0.3em]">Business Intelligence</span>
                        </div>
                        <h1 class="text-2xl md:text-3xl lg:text-4xl font-black text-text-primary tracking-tighter leading-none uppercase italic">
                            Ranking Performa
                        </h1>
                    </div>
                </div>

                <!-- Export Action -->
                <div class="flex flex-col md:flex-row items-center gap-3 w-full md:w-auto">
                    <button @click="exportToExcel" :disabled="loading || exportExcelLoading || (rankingData?.length || 0) === 0"
                        class="group relative flex items-center justify-center gap-3 px-6 py-3 md:py-3.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl transition-all duration-300 font-bold text-[10px] md:text-xs uppercase tracking-widest shadow-[0_10px_30px_rgba(37,99,235,0.2)] disabled:opacity-50 overflow-hidden w-full md:w-auto">
                        <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/10 to-white/0 -translate-x-full group-hover:animate-shine"></div>
                        <FileSpreadsheet v-if="!exportExcelLoading" class="w-4 h-4" />
                        <Loader2 v-else class="w-4 h-4 animate-spin" />
                        <span>Ekspor Excel</span>
                    </button>
                    <button @click="exportToPDF" :disabled="loading || exportLoading || (rankingData?.length || 0) === 0"
                        class="group relative flex items-center justify-center gap-3 px-6 py-3 md:py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl transition-all duration-300 font-bold text-[10px] md:text-xs uppercase tracking-widest shadow-[0_10px_30px_rgba(16,185,129,0.2)] disabled:opacity-50 overflow-hidden w-full md:w-auto">
                        <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/10 to-white/0 -translate-x-full group-hover:animate-shine"></div>
                        <Download v-if="!exportLoading" class="w-4 h-4" />
                        <Loader2 v-else class="w-4 h-4 animate-spin" />
                        <span>Ekspor PDF</span>
                    </button>
                </div>
            </div>

            <!-- MODERNISED FILTER BAR (Glassmorphism) -->
            <div class="relative group">
                <!-- Inner Glow -->
                <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500/20 via-surface-700/50 to-emerald-500/20 rounded-[28px] blur-sm opacity-50"></div>
                
                <div class="relative grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-6 p-5 md:p-8 bg-surface-800/80 backdrop-blur-2xl border border-surface-700/50 rounded-[24px] shadow-2xl">
                    
                    <!-- Presets Group -->
                    <div class="flex flex-col space-y-3 xl:col-span-3">
                        <label class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] ml-1">Pilih Cepat</label>
                        <div class="grid grid-cols-2 xs:grid-cols-4 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-2 gap-1 bg-surface-900/50 p-1.5 rounded-xl border border-surface-700/30">
                            <button v-for="key in ['today', 'yesterday', 'month', 'all']" :key="key"
                                v-show="key !== 'all' || !isRestricted"
                                @click="setRange(key)"
                                class="px-2 py-2.5 rounded-lg text-[9px] font-black transition-all duration-300 uppercase tracking-widest whitespace-nowrap"
                                :class="activeRange === key ? 'bg-emerald-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary hover:bg-surface-800'">
                                {{ rangeLabels[key] }}
                            </button>
                        </div>
                    </div>

                    <!-- Custom Range -->
                    <div class="flex flex-col space-y-3 md:col-span-1 xl:col-span-6">
                        <label class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] ml-1">Filter Periode</label>
                        <div class="flex flex-col lg:flex-row items-stretch gap-3">
                            <div class="flex-1 flex items-center bg-surface-900 border border-surface-700/50 rounded-2xl px-4 md:px-5 gap-3 md:gap-4 group/input focus-within:border-emerald-500/50 transition-all duration-500 shadow-inner h-[56px]">
                                <Calendar class="w-4 h-4 md:w-5 md:h-5 text-emerald-500 shrink-0" />
                                <div class="flex items-center gap-2 md:gap-3 w-full">
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <span class="text-[7px] md:text-[8px] font-black text-text-secondary uppercase tracking-tighter">Mulai</span>
                                        <input type="date" v-model="filters.start_date" 
                                            :min="getMinDate" :max="getTodayLocal()"
                                            class="bg-transparent text-[11px] md:text-sm text-text-primary outline-none font-black uppercase w-full cursor-pointer" />
                                    </div>
                                    <div class="h-6 w-px bg-surface-700 shrink-0"></div>
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <span class="text-[7px] md:text-[8px] font-black text-text-secondary uppercase tracking-tighter">Selesai</span>
                                        <input type="date" v-model="filters.end_date" 
                                            :min="getMinDate" :max="getTodayLocal()"
                                            class="bg-transparent text-[11px] md:text-sm text-text-primary outline-none font-black uppercase w-full cursor-pointer" />
                                    </div>
                                </div>
                            </div>

                            <button @click="fetchRanking" :disabled="loading"
                                class="group relative px-6 lg:px-10 bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl transition-all duration-500 flex items-center justify-center gap-3 font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-emerald-500/10 h-[56px] overflow-hidden">
                                <div class="absolute inset-x-0 bottom-0 h-1 bg-white/20 scale-x-0 group-hover:scale-x-100 transition-transform origin-left duration-500"></div>
                                <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
                                <Filter v-else class="w-4 h-4 transition-transform group-hover:rotate-12" />
                                <span>Tampilkan</span>
                            </button>
                        </div>
                    </div>

                    <!-- Additional Toggles -->
                    <div v-if="activeRange === 'today' || activeRange === 'yesterday'" 
                         class="flex flex-col space-y-3 xl:col-span-3">
                        <label class="text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] ml-1">Opsi Tampilan</label>
                        <button @click="toggleShowZero"
                            class="h-[56px] px-5 rounded-2xl transition-all duration-300 flex items-center justify-center gap-3 font-black text-[10px] uppercase tracking-widest border"
                            :class="showZero ? 'bg-orange-500/10 border-orange-500/50 text-orange-500' : 'bg-surface-900 border-surface-700/50 text-text-secondary hover:text-text-primary'">
                            <Eye v-if="!showZero" class="w-4 h-4" />
                            <EyeOff v-else class="w-4 h-4" />
                            <span>{{ showZero ? 'Sembunyikan' : 'Tampilkan 0' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-20">
            <div class="w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div ref="exportRef" class="space-y-12" :class="exportPart > 0 ? 'w-[1100px] mx-auto is-exporting-pdf pt-8 pb-20 px-12' : ''">
            <!-- HEADER KHUSUS PART > 1 -->
            <div v-show="exportPart > 1" class="text-center py-6 border-b border-surface-800 mb-8">
                <h2 class="text-3xl font-black text-primary-500 uppercase tracking-[0.2em]">Lanjutan Ranking</h2>
                <p class="text-text-secondary text-xs font-bold mt-2 uppercase tracking-widest">Halaman {{ exportPart }} / Lanjutan</p>
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
            <!-- Podium Layout (HIDE IN PART > 1) -->
            <div v-if="top3.length > 0 && exportPart <= 1"
                class="flex flex-col lg:flex-row items-center lg:items-end justify-center gap-10 lg:gap-4 xl:gap-14 pt-16 pb-12 px-6 relative bg-surface-800/5 rounded-[40px] overflow-hidden border border-surface-800/50">
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-primary-500/20 to-transparent">
                </div>

                <!-- Juara 2 -->
                <div v-if="top3[1]"
                    class="order-2 lg:order-1 flex flex-col items-center w-full lg:flex-1 max-w-[200px] md:max-w-[285px]">
                    <div class="relative group">
                        <div
                            class="absolute -inset-4 bg-slate-400/5 rounded-full blur-xl group-hover:bg-slate-400/10 transition-all">
                        </div>
                        <div
                            class="relative w-16 h-16 md:w-20 md:h-20 lg:w-24 lg:h-24 rounded-2xl flex items-center justify-center transition-colors bg-surface-800 border-2 border-slate-400/30 shadow-xl">
                            <component :is="top3[1].type === 'Offline' ? Store : Globe"
                                class="w-6 h-6 md:w-8 md:h-8 lg:w-10 lg:h-10 text-slate-400" />
                            <div
                                class="absolute -top-2 -right-2 md:-top-3 md:-right-3 w-6 h-6 md:w-8 md:h-8 bg-slate-400 text-surface-900 rounded-lg md:rounded-xl flex items-center justify-center font-black text-sm md:text-lg border-2 md:border-4 border-surface-900">
                                2</div>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-6 text-center w-full px-2">
                        <h3 class="font-black text-sm md:text-base lg:text-lg text-text-primary truncate uppercase leading-tight">
                            {{ top3[1].name }}</h3>
                        <p class="text-[8px] md:text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 md:mt-1.5">{{ top3[1].type
                            }} UNIT</p>
                        <div class="mt-3 md:mt-4 px-3 md:px-4 py-1.5 md:py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg">
                            <span class="text-sm md:text-lg lg:text-xl font-black text-slate-400 tabular-nums">{{
                                formatCurrency(top3[1].omset) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Juara 1 (THE KING) -->
                <div v-if="top3[0]"
                    class="order-1 lg:order-2 flex flex-col items-center w-full lg:w-[320px] xl:w-[400px] relative shrink-0">
                    <!-- BACKGROUND GLOW PULSE -->
                    <div class="absolute inset-0 bg-primary-500/10 blur-[80px] md:blur-[100px] rounded-full animate-pulse-slow"></div>

                    <div class="relative group mb-2 md:mb-4">
                        <!-- SHINY EFFECT -->
                        <div
                            class="absolute inset-0 bg-gradient-to-tr from-primary-500/0 via-white/20 to-primary-500/0 opacity-0 group-hover:animate-shine pointer-events-none rounded-[30px] md:rounded-[40px]">
                        </div>

                        <div
                            class="relative w-24 h-24 md:w-32 md:h-32 lg:w-44 lg:h-44 rounded-[30px] md:rounded-[40px] flex items-center justify-center transition-all overflow-visible bg-surface-800 border-4 border-primary-500 shadow-[0_0_50px_rgba(245,158,11,0.25)] hover:scale-105 duration-500 ring-4 md:ring-8 ring-primary-500/5">
                            <component :is="top3[0].type === 'Offline' ? Store : Globe"
                                class="w-10 h-10 md:w-16 md:h-16 lg:w-20 lg:h-20 text-primary-500" />

                            <!-- Floater Badge 1 -->
                            <div
                                class="absolute -top-3 -right-3 md:-top-6 md:-right-6 w-10 h-10 md:w-14 md:h-14 lg:w-16 lg:h-16 bg-primary-500 text-white rounded-xl md:rounded-[20px] flex items-center justify-center font-black text-xl md:text-3xl shadow-2xl animate-bounce-slow border-4 md:border-8 border-surface-900">
                                1</div>

                            <!-- TOP BADGE -->
                            <div class="absolute -top-8 md:-top-12 left-1/2 -translate-x-1/2 flex flex-col items-center">
                                <Crown
                                    class="w-6 h-6 md:w-10 md:h-10 text-primary-500 fill-primary-500 drop-shadow-[0_0_15px_rgba(245,158,11,0.5)] animate-bounce" />
                            </div>

                            <!-- KING OF SALES LABEL -->
                            <div
                                class="absolute -bottom-3 md:-bottom-4 left-1/2 -translate-x-1/2 bg-primary-500 text-white text-[7px] md:text-[9px] font-black px-3 md:px-4 py-1 md:py-1.5 rounded-full shadow-lg border-2 border-surface-900 whitespace-nowrap uppercase tracking-[0.2em] z-10">
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

                        <div class="mt-6 md:mt-8 relative group cursor-default">
                            <div
                                class="absolute -inset-4 bg-primary-500/20 blur-xl opacity-0 group-hover:opacity-100 transition-opacity rounded-full">
                            </div>
                            <div
                                class="relative px-6 md:px-12 py-4 md:py-6 bg-gradient-to-br from-primary-500 to-primary-600 shadow-[0_15px_35px_rgba(245,158,11,0.3)] rounded-2xl md:rounded-[32px] border-4 border-white/20 overflow-hidden group">
                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:animate-shine-fast">
                                </div>
                                <p
                                    class="text-white/70 text-[8px] md:text-[10px] font-black uppercase tracking-widest mb-1 leading-none">
                                    Total Omset Perolehan</p>
                                <span
                                    class="text-xl md:text-2xl lg:text-4xl font-black text-white tabular-nums drop-shadow-md drop-shadow-primary-900/50 leading-none">{{
                                    formatCurrency(top3[0].omset) }}</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Juara 3 -->
                <div v-if="top3[2]" class="order-3 flex flex-col items-center w-full lg:flex-1 max-w-[200px] md:max-w-[285px]">
                    <div class="relative group">
                        <div
                            class="absolute -inset-4 bg-amber-700/5 rounded-full blur-xl group-hover:bg-amber-700/10 transition-all">
                        </div>
                        <div
                            class="relative w-14 h-14 md:w-16 md:h-16 lg:w-20 lg:h-20 rounded-2xl flex items-center justify-center transition-colors bg-surface-800 border-2 border-amber-700/30 shadow-xl">
                            <component :is="top3[2].type === 'Offline' ? Store : Globe"
                                class="w-5 h-5 md:w-6 md:h-6 lg:w-8 lg:h-8 text-amber-700" />
                            <div
                                class="absolute -top-2 -right-2 md:-top-2.5 md:-right-2.5 w-6 h-6 md:w-8 md:h-8 bg-amber-700 text-surface-900 rounded-lg md:rounded-xl flex items-center justify-center font-black text-xs md:text-base border-2 md:border-4 border-surface-900">
                                3</div>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-6 text-center w-full px-2">
                        <h3 class="font-black text-sm md:text-base text-text-primary truncate uppercase leading-tight">{{
                            top3[2].name }}</h3>
                        <p class="text-[8px] md:text-[9px] font-bold text-amber-700 uppercase tracking-widest mt-1 md:mt-1.5">{{ top3[2].type
                            }} UNIT</p>
                        <div class="mt-3 md:mt-4 px-3 md:px-4 py-1.5 md:py-2 bg-surface-800/80 rounded-xl border border-surface-700 shadow-lg">
                            <span class="text-sm md:text-lg font-black text-amber-700 tabular-nums">{{
                                formatCurrency(top3[2].omset) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Table -->
            <div class="space-y-6">
                <!-- Similar styling for table... -->
                <div v-show="exportPart <= 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 px-1">
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
                    class="bg-surface-800/10 rounded-3xl border border-surface-800 shadow-2xl relative transition-colors"
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
                                        Total Omset</th>
                                    <th
                                        class="px-4 md:px-8 py-4 md:py-6 text-[10px] font-black text-text-secondary uppercase tracking-[0.2em] border-b border-surface-800 text-right">
                                        Omset Bersih</th>
                                </tr>
                             </thead>
                             <tbody :key="exportPart" class="divide-y divide-surface-800/50">
                                 <template v-for="(item, index) in (exportPart === 0 ? displayRanking : (exportPart === 1 ? (displayRanking?.slice(0, 10) || []) : (displayRanking?.slice(10 + (exportPart - 2) * 12, 10 + (exportPart - 1) * 12) || [])))" :key="item.type + '-' + (item.id || index)">
                                     <tr class="group hover:bg-surface-800/30 transition-all duration-300"
                                         :class="{'bg-surface-800/80' : item.isSeparator}">
                                         
                                         <!-- SEPARATOR TABLE ROW -->
                                         <template v-if="item.isSeparator">
                                             <td colspan="4" class="px-4 md:px-8 py-5 md:py-6 text-center shadow-inner">
                                                 <div class="flex items-center gap-3 justify-center">
                                                     <Store class="w-5 h-5 text-primary-500" />
                                                     <span class="text-sm md:text-base font-black text-text-primary uppercase tracking-[0.2em]">{{ item.name }}</span>
                                                     <Store class="w-5 h-5 text-primary-500" />
                                                 </div>
                                             </td>
                                         </template>
 
                                         <!-- STANDARD DATA ROW -->
                                         <template v-else>
                                             <td class="px-4 md:px-8 py-5 md:py-7">
                                                 <div class="flex items-center justify-center w-8 h-8 md:w-9 md:h-9 rounded-xl font-black text-xs md:text-sm"
                                                     :class="{
                                                         'bg-primary-500 text-white shadow-xl shadow-primary-500/20': item.localRank === 1,
                                                         'bg-slate-400 text-surface-900': item.localRank === 2,
                                                         'bg-amber-700 text-white': item.localRank === 3,
                                                         'bg-surface-800 text-text-secondary border border-surface-700': item.localRank > 3
                                                     }">{{ item.localRank }}</div>
                                             </td>
                                             <td class="px-4 md:px-8 py-5 md:py-7">
                                                 <div class="flex items-center gap-3 md:gap-4">
                                                     <div
                                                         class="w-9 h-9 md:w-10 md:h-10 rounded-2xl flex items-center justify-center shrink-0 transition-transform bg-surface-800 border border-surface-700 shadow-inner group-hover:scale-110">
                                                         <component :is="item.type === 'branch' || item.type === 'Offline' ? Store : Globe" class="w-4 h-4 md:w-5 md:h-5"
                                                             :class="item.type === 'branch' || item.type === 'Offline' ? 'text-primary-500' : 'text-blue-400'" />
                                                     </div>
                                                     <div class="flex flex-col min-w-0">
                                                         <span
                                                             class="font-black text-text-primary text-xs md:text-sm uppercase group-hover:text-primary-400 transition-colors tracking-tight truncate">{{
                                                             item.name }}</span>
                                                         <span
                                                             class="text-[8px] font-black text-surface-600 uppercase tracking-widest">{{
                                                             item.type === 'branch' ? 'CABANG' : item.type }} UNIT</span>
                                                     </div>
                                                 </div>
                                             </td>
                                             <td class="px-4 md:px-8 py-5 md:py-7 text-right">
                                                 <span v-if="item.omset > 0"
                                                     class="text-base md:text-lg font-black text-text-primary tabular-nums tracking-tight group-hover:text-emerald-400 transition-colors">
                                                     {{ formatCurrency(item.omset) }}
                                                 </span>
                                                 <span v-else class="text-[10px] md:text-sm font-bold text-orange-500 uppercase italic opacity-70">
                                                     Belum ada penjualan
                                                 </span>
                                             </td>
                                             <td class="px-4 md:px-8 py-5 md:py-7 text-right">
                                                 <span v-if="item.omset_bersih !== undefined"
                                                     class="text-base md:text-lg font-black text-emerald-500 tabular-nums tracking-tight">
                                                     {{ formatCurrency(item.omset_bersih) }}
                                                 </span>
                                                 <span v-else class="text-[10px] md:text-sm font-bold text-text-secondary uppercase italic opacity-70">
                                                     -
                                                 </span>
                                             </td>
                                         </template>
                                     </tr>
                                 </template>
                             </tbody>
                             <tfoot v-if="filteredRanking.length > 0 && (exportPart === 0 || exportPart === (displayRanking.length > 10 ? 1 + Math.ceil((displayRanking.length - 10) / 12) : 1))">
                                 <tr class="bg-surface-800/50 border-t border-surface-700">
                                     <td colspan="2" class="px-8 py-6 text-[10px] font-black text-text-secondary uppercase tracking-widest text-right">
                                         TOTAL PERIODE
                                     </td>
                                     <td class="px-8 py-6 text-right">
                                         <span class="text-xl font-black text-primary-500 tabular-nums tracking-tighter drop-shadow-sm">
                                             {{ formatCurrency(totalOmset) }}
                                         </span>
                                     </td>
                                     <td class="px-8 py-6 text-right">
                                         <span class="text-xl font-black text-emerald-500 tabular-nums tracking-tighter drop-shadow-sm">
                                             {{ formatCurrency(totalOmsetBersih) }}
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
    max-width: 1100px !important;
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
