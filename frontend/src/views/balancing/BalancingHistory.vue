<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { balancing } from '../../api/axios';
import {
    Scale, Calendar, Building2, User, Search, Filter, RotateCcw,
    TrendingDown, TrendingUp, ArrowLeftRight, Image as ImageIcon,
    Eye, X, ChevronLeft, ChevronRight, Download, CheckCircle2,
    Clock, AlertCircle, FileText, ExternalLink, ChevronDown, Sparkles
} from 'lucide-vue-next';

const router = useRouter();

// State
const loading = ref(false);
const loadingCs = ref(false);
const records = ref([]);
const branches = ref([]);
const csUsers = ref([]);

// Summary Totals
const summary = ref({
    total_omset_minus: 0,
    total_omset_plus: 0,
    total_selisih_pembayaran: 0,
    total_records: 0
});

// Helper for default current month (YYYY-MM)
function getDefaultMonth() {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    return `${yyyy}-${mm}`;
}

// Filters state
const selectedMonth = ref(getDefaultMonth());
const dateFilterMode = ref('month'); // 'month' | 'range'
const startDate = ref('');
const endDate = ref('');
const selectedBranch = ref('all');
const selectedCs = ref('all');
const searchQuery = ref('');

// Modals
const showDetailModal = ref(false);
const selectedItem = ref(null);

const showImageModal = ref(false);
const currentPhotos = ref([]);
const currentPhotoIndex = ref(0);

// Fetch data from backend
async function fetchHistory() {
    loading.value = true;
    try {
        const params = {};

        if (dateFilterMode.value === 'month') {
            params.month = selectedMonth.value || getDefaultMonth();
        } else {
            if (startDate.value && endDate.value) {
                params.start_date = startDate.value;
                params.end_date = endDate.value;
            } else if (startDate.value) {
                params.date = startDate.value;
            }
        }

        if (selectedBranch.value !== 'all') {
            params.branch_id = selectedBranch.value;
        }

        if (selectedCs.value !== 'all') {
            params.cs_id = selectedCs.value;
        }

        if (searchQuery.value.trim()) {
            params.search = searchQuery.value.trim();
        }

        const res = await balancing.history(params);
        if (res.data && res.data.success) {
            records.value = res.data.data || [];
            summary.value = res.data.summary || {
                total_omset_minus: 0,
                total_omset_plus: 0,
                total_selisih_pembayaran: 0,
                total_records: 0
            };
            if (res.data.filters) {
                branches.value = res.data.filters.branches || [];
                csUsers.value = res.data.filters.cs_users || [];
            }
        }
    } catch (err) {
        console.error('Failed to load balancing history:', err);
    } finally {
        loading.value = false;
    }
}

// Month Navigation helpers
function prevMonth() {
    if (!selectedMonth.value) return;
    const [y, m] = selectedMonth.value.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    selectedMonth.value = `${yyyy}-${mm}`;
}

function nextMonth() {
    if (!selectedMonth.value) return;
    const [y, m] = selectedMonth.value.split('-').map(Number);
    const d = new Date(y, m, 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    selectedMonth.value = `${yyyy}-${mm}`;
}

function resetToCurrentMonth() {
    selectedMonth.value = getDefaultMonth();
    dateFilterMode.value = 'month';
    startDate.value = '';
    endDate.value = '';
}

function resetAllFilters() {
    selectedMonth.value = getDefaultMonth();
    dateFilterMode.value = 'month';
    startDate.value = '';
    endDate.value = '';
    selectedBranch.value = 'all';
    selectedCs.value = 'all';
    searchQuery.value = '';
    fetchHistory();
}

// Watchers
watch(selectedBranch, async (newBranch) => {
    selectedCs.value = 'all'; // Reset selected CS when branch changes
    loadingCs.value = true;
    try {
        const res = await balancing.branchUsers(newBranch);
        if (res.data && res.data.data) {
            csUsers.value = res.data.data;
        }
    } catch (e) {
        console.error('Failed to fetch CS users for branch:', e);
    } finally {
        loadingCs.value = false;
    }
});

watch([selectedMonth, selectedBranch, selectedCs, dateFilterMode], () => {
    fetchHistory();
});

// Formatters
function formatCurrency(val) {
    const num = Number(val) || 0;
    return 'Rp ' + Math.abs(num).toLocaleString('id-ID');
}

function formatDate(dateStr) {
    if (!dateStr || dateStr === '-') return '-';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    } catch {
        return dateStr;
    }
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr || dateTimeStr === '-') return '-';
    try {
        const d = new Date(dateTimeStr);
        if (isNaN(d.getTime())) return dateTimeStr;
        return d.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch {
        return dateTimeStr;
    }
}

const monthNameFormatted = computed(() => {
    if (!selectedMonth.value) return '';
    const [y, m] = selectedMonth.value.split('-').map(Number);
    const d = new Date(y, m - 1, 1);
    return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

// Photo Lightbox
function openPhotoGallery(photos, index = 0) {
    if (!photos || photos.length === 0) return;
    currentPhotos.value = photos;
    currentPhotoIndex.value = index;
    showImageModal.value = true;
}

function nextPhoto() {
    if (currentPhotoIndex.value < currentPhotos.value.length - 1) {
        currentPhotoIndex.value++;
    } else {
        currentPhotoIndex.value = 0;
    }
}

function prevPhoto() {
    if (currentPhotoIndex.value > 0) {
        currentPhotoIndex.value--;
    } else {
        currentPhotoIndex.value = currentPhotos.value.length - 1;
    }
}

// Detail Modal
function openDetail(item) {
    selectedItem.value = item;
    showDetailModal.value = true;
}

// Export CSV
function exportCSV() {
    if (!records.value || records.value.length === 0) return;

    const headers = [
        'Tanggal Input',
        'No Invoice',
        'Kategori',
        'Cabang',
        'Tanggal Omset',
        'Nama CS',
        'Keterangan',
        'Nominal Omset Minus',
        'Nominal Omset Plus',
        'Salah Metode Pembayaran',
        'Status'
    ];

    const rows = records.value.map(r => [
        `"${r.tanggal}"`,
        `"${r.receipt_id}"`,
        `"${r.kategori}"`,
        `"${r.cabang}"`,
        `"${r.tanggal_omset}"`,
        `"${r.nama_cs}"`,
        `"${(r.keterangan || '').replace(/"/g, '""')}"`,
        r.nominal_omset_minus,
        r.nominal_omset_plus,
        r.salah_metode_pembayaran,
        `"${r.status}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,\uFEFF'
        + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `History_Balancing_${selectedMonth.value || 'All'}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

onMounted(() => {
    fetchHistory();
});
</script>

<template>
    <div class="space-y-5 sm:space-y-6 max-w-7xl mx-auto pb-16 px-2 sm:px-4 lg:px-0">
        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-neutral-500 dark:text-neutral-400 mb-1">
                    <span>Menu</span>
                    <span>/</span>
                    <router-link to="/balancing" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">Balancing</router-link>
                    <span>/</span>
                    <span class="text-neutral-900 dark:text-white font-bold">History Balancing</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-lg shadow-emerald-500/20 shrink-0">
                        <Scale :size="22" />
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black tracking-tight text-neutral-900 dark:text-white">
                            History Balancing
                        </h1>
                        <p class="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400">
                            Riwayat penyesuaian omset, salah metode pembayaran, dan bukti foto
                        </p>
                    </div>
                </div>
            </div>

            <!-- Month Quick Controller & Action Buttons -->
            <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                <!-- Month Navigator -->
                <div v-if="dateFilterMode === 'month'" class="flex items-center bg-white dark:bg-neutral-900 border border-neutral-200/90 dark:border-neutral-800 rounded-xl shadow-xs p-1">
                    <button
                        @click="prevMonth"
                        title="Bulan Sebelumnya"
                        class="p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 rounded-lg transition-colors cursor-pointer"
                    >
                        <ChevronLeft :size="18" />
                    </button>
                    
                    <input
                        type="month"
                        v-model="selectedMonth"
                        class="bg-transparent border-none text-xs sm:text-sm font-bold text-neutral-900 dark:text-white focus:outline-none focus:ring-0 px-2 cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
                    />

                    <button
                        @click="nextMonth"
                        title="Bulan Berikutnya"
                        class="p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 rounded-lg transition-colors cursor-pointer"
                    >
                        <ChevronRight :size="18" />
                    </button>
                </div>

                <!-- Refresh Button -->
                <button
                    @click="fetchHistory"
                    :disabled="loading"
                    title="Refresh Data"
                    class="p-2.5 bg-white dark:bg-neutral-900 border border-neutral-200/90 dark:border-neutral-800 rounded-xl text-neutral-600 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800 shadow-xs transition-all active:scale-95 cursor-pointer disabled:opacity-50"
                >
                    <RotateCcw :size="18" :class="{ 'animate-spin': loading }" />
                </button>

                <!-- Export Button (Emerald in both light & dark) -->
                <button
                    @click="exportCSV"
                    :disabled="records.length === 0"
                    class="flex items-center gap-1.5 px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer ml-auto lg:ml-0"
                >
                    <Download :size="15" />
                    <span>Export CSV</span>
                </button>
            </div>
        </div>

        <!-- 3 SUMMARY STAT CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            <!-- 1. Total Omset Minus -->
            <div class="relative overflow-hidden rounded-2xl p-4 sm:p-5 border bg-white dark:bg-neutral-900 border-rose-200/90 dark:border-rose-900/40 shadow-xs group transition-all">
                <div class="absolute top-0 right-0 w-28 h-28 bg-rose-500/5 dark:bg-rose-500/10 rounded-full -mr-10 -mt-10 pointer-events-none"></div>
                <div class="flex items-center justify-between mb-2 relative">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                        Total Omset Minus
                    </span>
                    <div class="p-2 rounded-xl bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-500/20">
                        <TrendingDown :size="18" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl lg:text-3xl font-black text-rose-600 dark:text-rose-400 tracking-tight mb-1 relative tabular-nums">
                    {{ summary.total_omset_minus > 0 ? '-' + formatCurrency(summary.total_omset_minus) : 'Rp 0' }}
                </div>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 relative">
                    Koreksi omset berkurang / pengurangan pendapatan
                </p>
            </div>

            <!-- 2. Total Omset Plus -->
            <div class="relative overflow-hidden rounded-2xl p-4 sm:p-5 border bg-white dark:bg-neutral-900 border-emerald-200/90 dark:border-emerald-900/40 shadow-xs group transition-all">
                <div class="absolute top-0 right-0 w-28 h-28 bg-emerald-500/5 dark:bg-emerald-500/10 rounded-full -mr-10 -mt-10 pointer-events-none"></div>
                <div class="flex items-center justify-between mb-2 relative">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                        Total Omset Plus
                    </span>
                    <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20">
                        <TrendingUp :size="18" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl lg:text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight mb-1 relative tabular-nums">
                    {{ summary.total_omset_plus > 0 ? '+' + formatCurrency(summary.total_omset_plus) : 'Rp 0' }}
                </div>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 relative">
                    Penjualan terlewat / penambahan omset
                </p>
            </div>

            <!-- 3. Total Selisih Pembayaran -->
            <div class="relative overflow-hidden rounded-2xl p-4 sm:p-5 border bg-white dark:bg-neutral-900 border-indigo-200/90 dark:border-indigo-900/40 shadow-xs group transition-all sm:col-span-2 lg:col-span-1">
                <div class="absolute top-0 right-0 w-28 h-28 bg-indigo-500/5 dark:bg-indigo-500/10 rounded-full -mr-10 -mt-10 pointer-events-none"></div>
                <div class="flex items-center justify-between mb-2 relative">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        Total Selisih Pembayaran
                    </span>
                    <div class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20">
                        <ArrowLeftRight :size="18" />
                    </div>
                </div>
                <div class="text-xl sm:text-2xl lg:text-3xl font-black text-indigo-600 dark:text-indigo-400 tracking-tight mb-1 relative tabular-nums">
                    {{ formatCurrency(summary.total_selisih_pembayaran) }}
                </div>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 relative">
                    Nilai mutasi koreksi salah metode pembayaran
                </p>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="bg-white dark:bg-neutral-900 border border-neutral-200/90 dark:border-neutral-800 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <!-- Left: Mode Switcher & Date Controls -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Date Mode Tabs -->
                    <div class="flex p-1 bg-neutral-100 dark:bg-neutral-800 rounded-xl border border-neutral-200/60 dark:border-neutral-700/60 text-xs font-semibold">
                        <button
                            @click="dateFilterMode = 'month'"
                            class="px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                            :class="dateFilterMode === 'month'
                                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs font-bold'
                                : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'"
                        >
                            Per Bulan
                        </button>
                        <button
                            @click="dateFilterMode = 'range'"
                            class="px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                            :class="dateFilterMode === 'range'
                                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs font-bold'
                                : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'"
                        >
                            Rentang Tanggal
                        </button>
                    </div>

                    <!-- Date range inputs if mode is range -->
                    <template v-if="dateFilterMode === 'range'">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <input
                                type="date"
                                v-model="startDate"
                                class="px-2.5 py-1.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-emerald-500 [color-scheme:light] dark:[color-scheme:dark]"
                            />
                            <span class="text-xs text-neutral-400">s/d</span>
                            <input
                                type="date"
                                v-model="endDate"
                                class="px-2.5 py-1.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-emerald-500 [color-scheme:light] dark:[color-scheme:dark]"
                            />
                            <button
                                @click="fetchHistory"
                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                            >
                                Terapkan
                            </button>
                        </div>
                    </template>

                    <!-- Indicator Active Period -->
                    <span v-if="dateFilterMode === 'month'" class="text-xs font-medium text-neutral-600 dark:text-neutral-300 px-2.5 py-1 bg-neutral-50 dark:bg-neutral-800/80 rounded-lg border border-neutral-200/80 dark:border-neutral-700">
                        Periode: <strong class="text-neutral-900 dark:text-white">{{ monthNameFormatted }}</strong>
                    </span>
                </div>

                <!-- Right: Reset button -->
                <button
                    v-if="selectedBranch !== 'all' || selectedCs !== 'all' || searchQuery || dateFilterMode !== 'month'"
                    @click="resetAllFilters"
                    class="flex items-center gap-1.5 text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline cursor-pointer self-start sm:self-auto"
                >
                    <RotateCcw :size="13" />
                    <span>Reset Filter</span>
                </button>
            </div>

            <!-- Second Row: Dropdown Filters & Search -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <!-- Filter Cabang -->
                <div class="relative">
                    <label class="block text-[11px] font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5">
                        Cabang
                    </label>
                    <div class="relative">
                        <select
                            v-model="selectedBranch"
                            class="w-full appearance-none px-3 py-2.5 pr-8 text-xs font-medium rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none cursor-pointer"
                        >
                            <option value="all" class="dark:bg-neutral-800 dark:text-white">Semua Cabang</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id" class="dark:bg-neutral-800 dark:text-white">
                                {{ b.name }}
                            </option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none" />
                    </div>
                </div>

                <!-- Filter CS -->
                <div class="relative">
                    <label class="block text-[11px] font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5">
                        Nama CS
                    </label>
                    <div class="relative">
                        <select
                            v-model="selectedCs"
                            :disabled="loadingCs"
                            class="w-full appearance-none px-3 py-2.5 pr-8 text-xs font-medium rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none cursor-pointer disabled:opacity-60"
                        >
                            <option value="all" class="dark:bg-neutral-800 dark:text-white">
                                {{ selectedBranch === 'all' ? 'Semua CS (Semua Cabang)' : `Semua CS Cabang Ini (${csUsers.length})` }}
                            </option>
                            <option v-for="cs in csUsers" :key="cs.id" :value="cs.id" class="dark:bg-neutral-800 dark:text-white">
                                {{ cs.name }}
                            </option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none" />
                    </div>
                </div>

                <!-- Search Input -->
                <div class="sm:col-span-2">
                    <label class="block text-[11px] font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1.5">
                        Pencarian
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @keyup.enter="fetchHistory"
                            placeholder="Cari no invoice, customer, atau keterangan..."
                            class="w-full pl-9 pr-4 py-2.5 text-xs font-medium rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 dark:placeholder-neutral-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                        />
                        <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT: TABLE & MOBILE CARDS -->
        <div class="bg-white dark:bg-neutral-900 border border-neutral-200/90 dark:border-neutral-800 rounded-2xl shadow-xs overflow-hidden">
            <!-- Header Bar -->
            <div class="px-4 sm:px-5 py-3.5 sm:py-4 border-b border-neutral-200/90 dark:border-neutral-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <FileText :size="18" class="text-emerald-600 dark:text-emerald-400" />
                    <h2 class="text-sm font-bold text-neutral-900 dark:text-white">
                        Daftar Transaksi Balancing
                    </h2>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                        {{ records.length }} data
                    </span>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="p-12 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-500 border-t-transparent mb-3"></div>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 font-medium">Memuat riwayat balancing...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="records.length === 0" class="p-12 sm:p-16 text-center">
                <div class="w-16 h-16 rounded-2xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mx-auto mb-3 text-neutral-400">
                    <Scale :size="32" />
                </div>
                <h3 class="text-sm font-bold text-neutral-900 dark:text-white mb-1">
                    Tidak Ada Riwayat Balancing
                </h3>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto">
                    Belum ada transaksi balancing yang tercatat pada filter periode atau cabang yang dipilih.
                </p>
            </div>

            <!-- DATA PRESENTATION: DUAL VIEW (DESKTOP TABLE + MOBILE CARDS) -->
            <div v-else>
                <!-- 1. DESKTOP & TABLET VIEW: FULL TABLE (>= 768px) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs text-neutral-600 dark:text-neutral-300">
                        <thead class="bg-neutral-50/90 dark:bg-neutral-800/70 text-[11px] font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider border-b border-neutral-200 dark:border-neutral-800">
                            <tr>
                                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Tanggal</th>
                                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Kategori</th>
                                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Cabang</th>
                                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Tanggal Omset</th>
                                <th scope="col" class="px-4 py-3.5 whitespace-nowrap">Nama CS</th>
                                <th scope="col" class="px-4 py-3.5 min-w-[170px]">Keterangan</th>
                                <th scope="col" class="px-4 py-3.5 text-right whitespace-nowrap">Nominal Omset Minus</th>
                                <th scope="col" class="px-4 py-3.5 text-right whitespace-nowrap">Nominal Omset Plus</th>
                                <th scope="col" class="px-4 py-3.5 text-right whitespace-nowrap">Salah Metode</th>
                                <th scope="col" class="px-4 py-3.5 text-center whitespace-nowrap">Foto</th>
                                <th scope="col" class="px-4 py-3.5 text-center whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200/70 dark:divide-neutral-800">
                            <tr
                                v-for="row in records"
                                :key="row.id"
                                class="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/40 transition-colors"
                                :class="{ 'opacity-60 bg-red-50/20 dark:bg-red-950/10': row.status === 'cancelled' }"
                            >
                                <!-- 1. Tanggal (Created At) -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-bold text-neutral-900 dark:text-white">
                                        {{ formatDateTime(row.tanggal) }}
                                    </div>
                                    <div class="text-[10px] text-neutral-400 font-mono">
                                        {{ row.receipt_id }}
                                    </div>
                                    <div v-if="row.status === 'cancelled'" class="inline-block mt-0.5 text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 font-bold uppercase">
                                        Dibatalkan
                                    </div>
                                </td>

                                <!-- 2. Kategori -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        v-if="row.sub_category === 'balancing_metode_pembayaran' || row.sub_category === 'payment_method'"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300 border border-violet-200 dark:border-violet-800/50"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                                        Metode Pembayaran
                                    </span>
                                    <span
                                        v-else-if="row.sub_category === 'balancing_penjualan_terlewat' || row.sub_category === 'missed_sale'"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Penjualan Terlewat
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"
                                    >
                                        {{ row.kategori }}
                                    </span>
                                </td>

                                <!-- 3. Cabang -->
                                <td class="px-4 py-3.5 whitespace-nowrap font-medium text-neutral-900 dark:text-white">
                                    <div class="flex items-center gap-1.5">
                                        <Building2 :size="14" class="text-neutral-400" />
                                        <span>{{ row.cabang }}</span>
                                    </div>
                                </td>

                                <!-- 4. Tanggal Omset -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 font-semibold text-neutral-800 dark:text-neutral-200 text-[11px] border border-neutral-200/60 dark:border-neutral-700/60">
                                        <Calendar :size="12" class="text-neutral-500" />
                                        <span>{{ formatDate(row.tanggal_omset) }}</span>
                                    </div>
                                </td>

                                <!-- 5. Nama CS -->
                                <td class="px-4 py-3.5 whitespace-nowrap font-medium text-neutral-900 dark:text-white">
                                    <div class="flex items-center gap-1.5">
                                        <User :size="13" class="text-neutral-400" />
                                        <span>{{ row.nama_cs }}</span>
                                    </div>
                                </td>

                                <!-- 6. Keterangan -->
                                <td class="px-4 py-3.5">
                                    <p class="line-clamp-2 text-neutral-700 dark:text-neutral-300 text-[11px] leading-relaxed" :title="row.keterangan">
                                        {{ row.keterangan || '-' }}
                                    </p>
                                </td>

                                <!-- 7. Nominal Omset Minus -->
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <span
                                        v-if="row.nominal_omset_minus > 0"
                                        class="font-extrabold text-rose-600 dark:text-rose-400 tabular-nums"
                                    >
                                        -{{ formatCurrency(row.nominal_omset_minus) }}
                                    </span>
                                    <span v-else class="text-neutral-400">-</span>
                                </td>

                                <!-- 8. Nominal Omset Plus -->
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <span
                                        v-if="row.nominal_omset_plus > 0"
                                        class="font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums"
                                    >
                                        +{{ formatCurrency(row.nominal_omset_plus) }}
                                    </span>
                                    <span v-else class="text-neutral-400">-</span>
                                </td>

                                <!-- 9. Salah Metode Pembayaran -->
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <div v-if="row.salah_metode_pembayaran > 0">
                                        <span class="font-extrabold text-indigo-600 dark:text-indigo-400 tabular-nums">
                                            {{ formatCurrency(row.salah_metode_pembayaran) }}
                                        </span>
                                        <div v-if="row.split_payments && row.split_payments.length > 0" class="text-[10px] text-neutral-400 truncate max-w-[150px]">
                                            {{ row.split_payments.map(s => s.method_name).join(' ➔ ') }}
                                        </div>
                                    </div>
                                    <span v-else class="text-neutral-400">-</span>
                                </td>

                                <!-- 10. Foto-foto -->
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <div v-if="row.photos && row.photos.length > 0" class="flex items-center justify-center -space-x-2">
                                        <button
                                            v-for="(photo, pIdx) in row.photos.slice(0, 3)"
                                            :key="pIdx"
                                            @click="openPhotoGallery(row.photos, pIdx)"
                                            class="relative w-8 h-8 rounded-lg overflow-hidden border-2 border-white dark:border-neutral-900 shadow-xs hover:scale-110 hover:z-10 transition-transform cursor-pointer"
                                            :title="photo.label"
                                        >
                                            <img :src="photo.url" :alt="photo.label" class="w-full h-full object-cover" />
                                        </button>
                                        <button
                                            v-if="row.photos.length > 3"
                                            @click="openPhotoGallery(row.photos, 3)"
                                            class="w-8 h-8 rounded-lg bg-neutral-800 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white dark:border-neutral-900 shadow-xs cursor-pointer"
                                        >
                                            +{{ row.photos.length - 3 }}
                                        </button>
                                    </div>
                                    <span v-else class="text-neutral-400 text-[11px]">-</span>
                                </td>

                                <!-- 11. Aksi -->
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <button
                                        @click="openDetail(row)"
                                        class="p-1.5 text-neutral-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
                                        title="Lihat Detail"
                                    >
                                        <Eye :size="16" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Table Footer Summary -->
                        <tfoot class="bg-neutral-50 dark:bg-neutral-800/80 font-bold border-t-2 border-neutral-200 dark:border-neutral-700">
                            <tr>
                                <td colspan="6" class="px-4 py-3.5 text-neutral-900 dark:text-white uppercase tracking-wider text-[11px]">
                                    Total Terpilih ({{ records.filter(r => r.status !== 'cancelled').length }} Transaksi Aktif)
                                </td>
                                <td class="px-4 py-3.5 text-right text-rose-600 dark:text-rose-400 text-xs font-black tabular-nums">
                                    {{ summary.total_omset_minus > 0 ? '-' + formatCurrency(summary.total_omset_minus) : 'Rp 0' }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-emerald-600 dark:text-emerald-400 text-xs font-black tabular-nums">
                                    {{ summary.total_omset_plus > 0 ? '+' + formatCurrency(summary.total_omset_plus) : 'Rp 0' }}
                                </td>
                                <td class="px-4 py-3.5 text-right text-indigo-600 dark:text-indigo-400 text-xs font-black tabular-nums">
                                    {{ formatCurrency(summary.total_selisih_pembayaran) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- 2. MOBILE CARD VIEW (< 768px): SMOOTH & CLEAN EXPERIENCE -->
                <div class="block md:hidden divide-y divide-neutral-200/80 dark:divide-neutral-800">
                    <div
                        v-for="row in records"
                        :key="'mobile-' + row.id"
                        class="p-4 space-y-3 transition-colors hover:bg-neutral-50/60 dark:hover:bg-neutral-800/30"
                        :class="{ 'opacity-60 bg-red-50/20 dark:bg-red-950/10': row.status === 'cancelled' }"
                    >
                        <!-- Card Top: Time, Badge, Action -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="font-bold text-neutral-900 dark:text-white text-xs block">
                                    {{ formatDateTime(row.tanggal) }}
                                </span>
                                <span class="text-[10px] text-neutral-400 font-mono">
                                    {{ row.receipt_id }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span
                                    v-if="row.sub_category === 'balancing_metode_pembayaran' || row.sub_category === 'payment_method'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300 border border-violet-200 dark:border-violet-800/50"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                                    Metode
                                </span>
                                <span
                                    v-else-if="row.sub_category === 'balancing_penjualan_terlewat' || row.sub_category === 'missed_sale'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Terlewat
                                </span>
                                <button
                                    @click="openDetail(row)"
                                    class="p-1.5 text-neutral-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
                                    title="Lihat Detail"
                                >
                                    <Eye :size="16" />
                                </button>
                            </div>
                        </div>

                        <!-- Card Metadata: Cabang, Tanggal Omset, CS -->
                        <div class="grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-center gap-1.5">
                                <Building2 :size="13" class="text-neutral-400 shrink-0" />
                                <span class="truncate font-medium text-neutral-800 dark:text-neutral-200">{{ row.cabang }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <User :size="13" class="text-neutral-400 shrink-0" />
                                <span class="truncate font-medium text-neutral-800 dark:text-neutral-200">{{ row.nama_cs }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 col-span-2">
                                <Calendar :size="13" class="text-neutral-400 shrink-0" />
                                <span class="text-[11px]">Tanggal Masuk Omset:</span>
                                <span class="font-bold text-neutral-800 dark:text-neutral-200 text-[11px] px-1.5 py-0.2 rounded bg-neutral-100 dark:bg-neutral-800 border border-neutral-200/60 dark:border-neutral-700/60">
                                    {{ formatDate(row.tanggal_omset) }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Notes -->
                        <div v-if="row.keterangan" class="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200/60 dark:border-neutral-700/60 text-[11px] text-neutral-700 dark:text-neutral-300 leading-relaxed">
                            {{ row.keterangan }}
                        </div>

                        <!-- Card Values Highlights -->
                        <div class="grid grid-cols-3 gap-2 pt-1">
                            <div class="p-2 rounded-xl border border-rose-200/80 dark:border-rose-900/40 bg-rose-50/50 dark:bg-rose-950/20 text-center">
                                <span class="text-[9px] font-bold text-rose-500 uppercase block">Omset -</span>
                                <span class="font-black text-xs text-rose-600 dark:text-rose-400 tabular-nums">
                                    {{ row.nominal_omset_minus > 0 ? '-' + formatCurrency(row.nominal_omset_minus) : '-' }}
                                </span>
                            </div>
                            <div class="p-2 rounded-xl border border-emerald-200/80 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20 text-center">
                                <span class="text-[9px] font-bold text-emerald-500 uppercase block">Omset +</span>
                                <span class="font-black text-xs text-emerald-600 dark:text-emerald-400 tabular-nums">
                                    {{ row.nominal_omset_plus > 0 ? '+' + formatCurrency(row.nominal_omset_plus) : '-' }}
                                </span>
                            </div>
                            <div class="p-2 rounded-xl border border-indigo-200/80 dark:border-indigo-900/40 bg-indigo-50/50 dark:bg-indigo-950/20 text-center">
                                <span class="text-[9px] font-bold text-indigo-500 uppercase block">Salah Metode</span>
                                <span class="font-black text-xs text-indigo-600 dark:text-indigo-400 tabular-nums">
                                    {{ row.salah_metode_pembayaran > 0 ? formatCurrency(row.salah_metode_pembayaran) : '-' }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Photos Preview -->
                        <div v-if="row.photos && row.photos.length > 0" class="flex items-center gap-2 pt-1">
                            <span class="text-[10px] font-bold text-neutral-400">Bukti:</span>
                            <div class="flex items-center -space-x-1.5">
                                <button
                                    v-for="(photo, pIdx) in row.photos.slice(0, 3)"
                                    :key="pIdx"
                                    @click="openPhotoGallery(row.photos, pIdx)"
                                    class="w-7 h-7 rounded-lg overflow-hidden border-2 border-white dark:border-neutral-900 shadow-xs cursor-pointer"
                                >
                                    <img :src="photo.url" :alt="photo.label" class="w-full h-full object-cover" />
                                </button>
                                <button
                                    v-if="row.photos.length > 3"
                                    @click="openPhotoGallery(row.photos, 3)"
                                    class="w-7 h-7 rounded-lg bg-neutral-800 text-white text-[9px] font-bold flex items-center justify-center border-2 border-white dark:border-neutral-900 cursor-pointer"
                                >
                                    +{{ row.photos.length - 3 }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIGHTBOX PHOTO GALLERY MODAL -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/90 backdrop-blur-md"
            @click.self="showImageModal = false"
        >
            <div class="relative max-w-4xl w-full max-h-[92vh] flex flex-col items-center">
                <!-- Top Controls -->
                <div class="w-full flex items-center justify-between text-white mb-3 px-2">
                    <div class="flex items-center gap-2">
                        <ImageIcon :size="18" />
                        <span class="text-xs sm:text-sm font-bold truncate max-w-[200px] sm:max-w-md">
                            {{ currentPhotos[currentPhotoIndex]?.label || 'Bukti Foto' }}
                        </span>
                        <span class="text-xs text-neutral-400">
                            ({{ currentPhotoIndex + 1 }}/{{ currentPhotos.length }})
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            :href="currentPhotos[currentPhotoIndex]?.url"
                            target="_blank"
                            title="Buka Gambar Asli"
                            class="p-2 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 rounded-xl transition-colors cursor-pointer"
                        >
                            <ExternalLink :size="16" />
                        </a>
                        <button
                            @click="showImageModal = false"
                            class="p-2 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 rounded-xl transition-colors cursor-pointer"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                </div>

                <!-- Main Image Preview -->
                <div class="relative flex items-center justify-center w-full max-h-[65vh] sm:max-h-[72vh] overflow-hidden rounded-2xl bg-neutral-950">
                    <img
                        :src="currentPhotos[currentPhotoIndex]?.url"
                        :alt="currentPhotos[currentPhotoIndex]?.label"
                        class="max-w-full max-h-[65vh] sm:max-h-[72vh] object-contain rounded-xl"
                    />

                    <!-- Prev/Next Overlay Buttons -->
                    <button
                        v-if="currentPhotos.length > 1"
                        @click="prevPhoto"
                        class="absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 p-2 sm:p-2.5 rounded-full bg-black/60 hover:bg-black/90 text-white transition-colors cursor-pointer"
                    >
                        <ChevronLeft :size="20" />
                    </button>
                    <button
                        v-if="currentPhotos.length > 1"
                        @click="nextPhoto"
                        class="absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 p-2 sm:p-2.5 rounded-full bg-black/60 hover:bg-black/90 text-white transition-colors cursor-pointer"
                    >
                        <ChevronRight :size="20" />
                    </button>
                </div>

                <!-- Bottom Thumbnails -->
                <div v-if="currentPhotos.length > 1" class="flex items-center gap-2 mt-3 overflow-x-auto max-w-full p-2">
                    <button
                        v-for="(ph, idx) in currentPhotos"
                        :key="idx"
                        @click="currentPhotoIndex = idx"
                        class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl overflow-hidden border-2 transition-all cursor-pointer shrink-0"
                        :class="currentPhotoIndex === idx ? 'border-emerald-500 scale-105' : 'border-white/20 opacity-60 hover:opacity-100'"
                    >
                        <img :src="ph.url" :alt="ph.label" class="w-full h-full object-cover" />
                    </button>
                </div>
            </div>
        </div>

        <!-- TRANSACTION DETAIL MODAL -->
        <div
            v-if="showDetailModal && selectedItem"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs"
            @click.self="showDetailModal = false"
        >
            <div class="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-neutral-900/95 backdrop-blur-md z-10">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                            <Scale :size="18" />
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-neutral-900 dark:text-white">
                                Detail Transaksi Balancing
                            </h3>
                            <p class="text-[11px] text-neutral-400 font-mono">{{ selectedItem.receipt_id }}</p>
                        </div>
                    </div>
                    <button
                        @click="showDetailModal = false"
                        class="p-2 text-neutral-400 hover:text-neutral-700 dark:hover:text-white rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                    >
                        <X :size="18" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-4 sm:p-6 space-y-4 sm:space-y-5 text-xs">
                    <!-- General Information Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200/80 dark:border-neutral-700/50">
                        <div>
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Tanggal Input</span>
                            <span class="font-bold text-neutral-900 dark:text-white">{{ formatDateTime(selectedItem.tanggal) }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Tanggal Masuk Omset</span>
                            <span class="font-bold text-neutral-900 dark:text-white">{{ formatDate(selectedItem.tanggal_omset) }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Cabang</span>
                            <span class="font-bold text-neutral-900 dark:text-white">{{ selectedItem.cabang }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Nama CS</span>
                            <span class="font-bold text-neutral-900 dark:text-white">{{ selectedItem.nama_cs }}</span>
                        </div>
                        <div v-if="selectedItem.customer_name">
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Nama Customer</span>
                            <span class="font-bold text-neutral-900 dark:text-white">{{ selectedItem.customer_name }}</span>
                        </div>
                        <div v-if="selectedItem.customer_phone">
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Kontak Customer</span>
                            <span class="font-bold text-neutral-900 dark:text-white">{{ selectedItem.customer_phone }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Dibuat Oleh</span>
                            <span class="font-bold text-neutral-900 dark:text-white">{{ selectedItem.created_by }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block mb-0.5 font-medium text-[11px]">Status</span>
                            <span class="font-bold capitalize" :class="selectedItem.status === 'cancelled' ? 'text-red-500' : 'text-emerald-500'">
                                {{ selectedItem.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Nominal Adjustment Summary -->
                    <div class="space-y-2">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Ringkasan Nilai Balancing
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3">
                            <div class="p-3 rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50/50 dark:bg-rose-950/20 text-left sm:text-right">
                                <span class="text-[10px] font-bold text-rose-500 block uppercase">Omset Minus</span>
                                <span class="font-black text-sm text-rose-600 dark:text-rose-400 tabular-nums">
                                    {{ selectedItem.nominal_omset_minus > 0 ? '-' + formatCurrency(selectedItem.nominal_omset_minus) : 'Rp 0' }}
                                </span>
                            </div>
                            <div class="p-3 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20 text-left sm:text-right">
                                <span class="text-[10px] font-bold text-emerald-500 block uppercase">Omset Plus</span>
                                <span class="font-black text-sm text-emerald-600 dark:text-emerald-400 tabular-nums">
                                    {{ selectedItem.nominal_omset_plus > 0 ? '+' + formatCurrency(selectedItem.nominal_omset_plus) : 'Rp 0' }}
                                </span>
                            </div>
                            <div class="p-3 rounded-xl border border-indigo-200 dark:border-indigo-900/40 bg-indigo-50/50 dark:bg-indigo-950/20 text-left sm:text-right">
                                <span class="text-[10px] font-bold text-indigo-500 block uppercase">Salah Metode</span>
                                <span class="font-black text-sm text-indigo-600 dark:text-indigo-400 tabular-nums">
                                    {{ formatCurrency(selectedItem.salah_metode_pembayaran) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Split Payments List -->
                    <div v-if="selectedItem.split_payments && selectedItem.split_payments.length > 0" class="space-y-2">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Rincian Mutasi Metode Pembayaran
                        </h4>
                        <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-800 overflow-hidden">
                            <div
                                v-for="(pm, idx) in selectedItem.split_payments"
                                :key="idx"
                                class="flex items-center justify-between px-3.5 py-2.5 text-xs"
                            >
                                <span class="font-semibold text-neutral-900 dark:text-white">{{ pm.method_name }}</span>
                                <span
                                    class="font-black tabular-nums"
                                    :class="pm.amount < 0 ? 'text-rose-500' : 'text-emerald-500'"
                                >
                                    {{ pm.amount < 0 ? '-' : '+' }}{{ formatCurrency(Math.abs(pm.amount)) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Product Items (if Missed Sale) -->
                    <div v-if="selectedItem.detail_items && selectedItem.detail_items.length > 0" class="space-y-2">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Barang Terkait (Penjualan Terlewat)
                        </h4>
                        <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-800 overflow-hidden">
                            <div
                                v-for="(it, idx) in selectedItem.detail_items"
                                :key="idx"
                                class="p-3 flex items-center justify-between gap-2"
                            >
                                <div>
                                    <div class="font-bold text-neutral-900 dark:text-white">{{ it.name }}</div>
                                    <div v-if="it.imei && it.imei !== '-'" class="text-[10px] text-neutral-400 font-mono">IMEI: {{ it.imei }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-bold text-neutral-900 dark:text-white">{{ formatCurrency(it.price) }}</div>
                                    <div class="text-[10px] text-neutral-400">{{ it.quantity }} unit</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Keterangan / Notes -->
                    <div class="space-y-1.5">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Keterangan / Kronologi
                        </h4>
                        <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200 leading-relaxed whitespace-pre-wrap text-xs">
                            {{ selectedItem.keterangan || '-' }}
                        </div>
                    </div>

                    <!-- Photos in Detail Modal -->
                    <div v-if="selectedItem.photos && selectedItem.photos.length > 0" class="space-y-2">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Bukti Foto ({{ selectedItem.photos.length }})
                        </h4>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 sm:gap-2.5">
                            <button
                                v-for="(ph, idx) in selectedItem.photos"
                                :key="idx"
                                @click="openPhotoGallery(selectedItem.photos, idx)"
                                class="relative aspect-square rounded-xl overflow-hidden border border-neutral-200 dark:border-neutral-700 group cursor-pointer"
                            >
                                <img :src="ph.url" :alt="ph.label" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white transition-opacity">
                                    <Eye :size="16" />
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 sm:px-6 py-3 border-t border-neutral-200 dark:border-neutral-800 flex justify-end">
                    <button
                        @click="showDetailModal = false"
                        class="px-4 py-2 bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 font-semibold rounded-xl transition-colors cursor-pointer text-xs"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
