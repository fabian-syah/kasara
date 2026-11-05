<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    History, Search, Calendar, Building2, User, Filter,
    CheckCircle2, AlertTriangle, Eye, ChevronDown, ChevronRight,
    RefreshCw, Plus, ArrowLeft, Camera, X, Smartphone, Package, Check, Download
} from 'lucide-vue-next';
import axios from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../store/auth';

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

// State
const loading = ref(false);
const records = ref([]);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15
});

// Filters
const branches = ref([]);
const csUsers = ref([]);
const filters = ref({
    branch_id: '',
    cs_user_id: '',
    category_type: '',
    method: '',
    status: '',
    date: new Date().toISOString().slice(0, 10),
    search: ''
});

// Detail Modal
const selectedDetail = ref(null);
const loadingDetail = ref(false);
const showDetailModal = ref(false);
const selectedPhotoModal = ref(null);

// User role check
const userRole = computed(() => (authStore.userRole || '').toLowerCase());
const isPrivilegedRole = computed(() => {
    const privileged = ['super_admin', 'leader', 'audit', 'owner', 'analist'];
    return privileged.some(r => userRole.value.includes(r));
});

// Fetch branches
const fetchBranches = async () => {
    try {
        const res = await axios.get('/inventory/opname/branches');
        branches.value = res.data.data || [];
        if (branches.value.length === 1) {
            filters.value.branch_id = branches.value[0].id;
        }
    } catch (err) {
        console.error('Error fetching branches:', err);
    }
};

// Fetch CS users
const fetchCsUsers = async () => {
    if (!filters.value.branch_id) {
        csUsers.value = [];
        return;
    }
    try {
        const res = await axios.get(`/inventory/opname/branch-cs/${filters.value.branch_id}`);
        csUsers.value = res.data.data || [];
    } catch (err) {
        console.error('Error fetching CS users:', err);
    }
};

// Fetch History Data
const fetchHistory = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            per_page: pagination.value.per_page,
            ...filters.value
        };

        // Clean empty parameters
        Object.keys(params).forEach(k => {
            if (params[k] === '' || params[k] === null || params[k] === undefined) {
                delete params[k];
            }
        });

        const res = await axios.get('/inventory/opname/history', { params });
        records.value = res.data.data || [];
        pagination.value = {
            current_page: res.data.current_page || 1,
            last_page: res.data.last_page || 1,
            total: res.data.total || 0,
            per_page: res.data.per_page || 15
        };
    } catch (err) {
        console.error('Error fetching SO history:', err);
        toast.error('Gagal memuat riwayat stok opname.');
    } finally {
        loading.value = false;
    }
};

// View Detail
const openDetail = async (item) => {
    loadingDetail.value = true;
    showDetailModal.value = true;
    try {
        const res = await axios.get(`/inventory/opname/history/${item.id}`);
        selectedDetail.value = res.data.data;
    } catch (err) {
        toast.error('Gagal memuat rincian stok opname.');
        showDetailModal.value = false;
    } finally {
        loadingDetail.value = false;
    }
};

// Format Date & Time
const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatDateOnly = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

watch(() => filters.value.branch_id, () => {
    fetchCsUsers();
    fetchHistory(1);
});

watch(() => [filters.value.cs_user_id, filters.value.category_type, filters.value.method, filters.value.status, filters.value.date], () => {
    fetchHistory(1);
});

onMounted(async () => {
    await fetchBranches();
    fetchHistory(1);
});
</script>

<template>
    <div class="space-y-6 pb-20">
        <!-- Top Navigation & Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
            <div>
                <div class="flex items-center gap-2">
                    <button @click="router.back()" class="p-2 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                        <ArrowLeft :size="20" />
                    </button>
                    <h1 class="text-xl sm:text-2xl font-bold text-text-primary tracking-tight">History Stok Opname</h1>
                </div>
                <p class="text-text-secondary text-xs sm:text-sm mt-1 ml-10">
                    Riwayat laporan dan hasil audit fisik stok toko harian
                </p>
            </div>

            <div class="flex items-center gap-2 self-stretch sm:self-auto">
                <router-link to="/inventory/opname"
                    class="flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-md transition-all flex-1 sm:flex-initial min-h-[44px]">
                    <Plus :size="16" />
                    <span>Mulai Stok Opname</span>
                </router-link>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Tanggal -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">Tanggal</label>
                    <div class="relative">
                        <input type="date" v-model="filters.date"
                            class="w-full py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500" />
                    </div>
                </div>

                <!-- Cabang (Jika Privileged) -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">Cabang</label>
                    <div class="relative">
                        <select v-model="filters.branch_id" :disabled="!isPrivilegedRole && branches.length <= 1"
                            class="w-full appearance-none py-2 px-3 pr-8 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                            <option value="">Semua Cabang</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>

                <!-- CS Pelaksana -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">CS Pelaksana</label>
                    <div class="relative">
                        <select v-model="filters.cs_user_id"
                            class="w-full appearance-none py-2 px-3 pr-8 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                            <option value="">Semua CS</option>
                            <option v-for="cs in csUsers" :key="cs.id" :value="cs.id">{{ cs.name }}</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>

                <!-- Status Hasil -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">Status Hasil</label>
                    <div class="relative">
                        <select v-model="filters.status"
                            class="w-full appearance-none py-2 px-3 pr-8 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                            <option value="">Semua Status</option>
                            <option value="completed">Cocok (Lengkap) ✅</option>
                            <option value="has_difference">Ada Selisih ⚠️</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="pt-1 flex items-center gap-2">
                <div class="relative flex-1">
                    <input type="text" v-model="filters.search" @keydown.enter="fetchHistory(1)"
                        placeholder="Cari No SO / Brand / Tipe / CS..."
                        class="w-full py-2 pl-9 pr-4 bg-gray-50 dark:bg-surface-700/50 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm text-text-primary placeholder:text-gray-400 focus:ring-1 focus:ring-emerald-500" />
                    <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                </div>
                <button type="button" @click="fetchHistory(1)"
                    class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-surface-700 hover:bg-gray-200 dark:hover:bg-surface-600 text-text-primary text-xs font-bold transition-all">
                    Cari
                </button>
                <button type="button" @click="filters = { branch_id: '', cs_user_id: '', category_type: '', method: '', status: '', date: '', search: '' }; fetchHistory(1)"
                    class="px-3 py-2 rounded-xl border border-gray-200 dark:border-surface-600 text-text-secondary hover:text-text-primary text-xs font-semibold">
                    Reset
                </button>
            </div>
        </div>

        <!-- History Records Table -->
        <div class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm overflow-hidden">
            <!-- Loading -->
            <div v-if="loading" class="py-16 flex flex-col items-center justify-center text-gray-400 gap-2">
                <RefreshCw :size="24" class="animate-spin text-emerald-500" />
                <span class="text-xs font-medium">Memuat riwayat stok opname...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="records.length === 0" class="py-16 text-center text-text-secondary">
                <History :size="32" class="mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                <p class="text-sm font-semibold text-text-primary">Belum ada riwayat Stok Opname</p>
                <p class="text-xs text-text-secondary mt-1">Gunakan filter lain atau mulai sesi Stok Opname baru.</p>
            </div>

            <!-- Records List Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[760px]">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-surface-700 bg-gray-50/50 dark:bg-surface-700/30 text-[11px] font-bold text-text-secondary uppercase">
                            <th class="py-3 px-4">No SO & Waktu</th>
                            <th class="py-3 px-4">Cabang & CS</th>
                            <th class="py-3 px-4">Kategori & Metode</th>
                            <th class="py-3 px-4 text-center">Sistem</th>
                            <th class="py-3 px-4 text-center">Fisik Ada</th>
                            <th class="py-3 px-4 text-center">Selisih</th>
                            <th class="py-3 px-4 text-center">Lebih</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Foto</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700 text-xs sm:text-sm">
                        <tr v-for="rec in records" :key="rec.id" class="hover:bg-gray-50/60 dark:hover:bg-surface-700/30 transition-colors">
                            <!-- No SO & Waktu -->
                            <td class="py-3.5 px-4">
                                <div class="font-mono font-bold text-xs text-text-primary">{{ rec.opname_number }}</div>
                                <div class="text-[11px] text-text-secondary mt-0.5">{{ formatDateTime(rec.created_at) }}</div>
                            </td>

                            <!-- Cabang & CS -->
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-text-primary flex items-center gap-1.5">
                                    <Building2 :size="13" class="text-emerald-500 shrink-0" />
                                    <span>{{ rec.branch?.name || '-' }}</span>
                                </div>
                                <div class="text-[11px] text-text-secondary flex items-center gap-1.5 mt-0.5">
                                    <User :size="12" class="text-gray-400 shrink-0" />
                                    <span>CS: {{ rec.cs_user?.name || '-' }}</span>
                                </div>
                            </td>

                            <!-- Kategori & Metode -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                        :class="rec.category_type === 'hp' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300'">
                                        {{ rec.category_type === 'hp' ? 'HP' : 'Non-HP' }}
                                    </span>
                                    <span class="text-xs font-semibold text-text-primary">
                                        {{ rec.method === 'by_imei' ? 'By IMEI' : 'By Unit' }}
                                    </span>
                                </div>
                                <div v-if="rec.brand || rec.product_type" class="text-[11px] text-text-secondary mt-0.5">
                                    {{ rec.brand ? rec.brand + ' - ' : '' }}{{ rec.product_type || 'Semua Tipe' }}
                                </div>
                            </td>

                            <!-- Total Sistem -->
                            <td class="py-3.5 px-4 text-center font-mono font-bold">
                                {{ rec.total_system }}
                            </td>

                            <!-- Total Fisik Ada -->
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                {{ rec.total_physical }}
                            </td>

                            <!-- Total Missing (Kurang) -->
                            <td class="py-3.5 px-4 text-center font-mono font-bold">
                                <span v-if="rec.total_missing > 0" class="text-rose-600 dark:text-rose-400">
                                    -{{ rec.total_missing }}
                                </span>
                                <span v-else class="text-gray-400">0</span>
                            </td>

                            <!-- Total Excess (Lebih) -->
                            <td class="py-3.5 px-4 text-center font-mono font-bold">
                                <span v-if="rec.total_excess > 0" class="text-amber-600 dark:text-amber-400">
                                    +{{ rec.total_excess }}
                                </span>
                                <span v-else class="text-gray-400">0</span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-4 text-center">
                                <span v-if="rec.status === 'completed'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300">
                                    <CheckCircle2 :size="12" />
                                    <span>Cocok</span>
                                </span>
                                <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-950/50 text-rose-800 dark:text-rose-300">
                                    <AlertTriangle :size="12" />
                                    <span>Selisih</span>
                                </span>
                            </td>

                            <!-- Foto SO -->
                            <td class="py-3.5 px-4 text-center">
                                <span v-if="rec.photos && rec.photos.length > 0" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded">
                                    <Camera :size="12" />
                                    <span>{{ rec.photos.length }}</span>
                                </span>
                                <span v-else class="text-gray-400 text-xs">-</span>
                            </td>

                            <!-- Action Detail -->
                            <td class="py-3.5 px-4 text-right">
                                <button type="button" @click="openDetail(rec)"
                                    class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 text-xs font-bold text-text-primary hover:border-emerald-500 hover:text-emerald-600 transition-colors inline-flex items-center gap-1">
                                    <Eye :size="14" />
                                    <span>Rincian</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-gray-100 dark:border-surface-700 flex flex-col sm:flex-row justify-between items-center gap-3">
                <span class="text-xs text-text-secondary">
                    Menampilkan halaman {{ pagination.current_page }} dari {{ pagination.last_page }} (Total {{ pagination.total }} sesi SO)
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" :disabled="pagination.current_page <= 1" @click="fetchHistory(pagination.current_page - 1)"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 text-xs font-semibold disabled:opacity-40">
                        Sebelumnya
                    </button>
                    <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="fetchHistory(pagination.current_page + 1)"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 text-xs font-semibold disabled:opacity-40">
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetailModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:!bg-surface-800 rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col border border-gray-100 dark:border-surface-700 shadow-2xl overflow-hidden">
                <!-- Modal Header -->
                <div class="p-5 border-b border-gray-100 dark:border-surface-700 flex items-center justify-between shrink-0">
                    <div>
                        <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ selectedDetail?.opname_number }}</div>
                        <h3 class="text-lg font-bold text-text-primary mt-0.5">Rincian Hasil Stok Opname</h3>
                        <p class="text-xs text-text-secondary">
                            {{ selectedDetail?.branch?.name }} • CS: {{ selectedDetail?.cs_user?.name }} • {{ formatDateTime(selectedDetail?.created_at) }}
                        </p>
                    </div>
                    <button @click="showDetailModal = false" class="p-2 text-gray-400 hover:text-gray-600">
                        <X :size="20" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 overflow-y-auto space-y-5 flex-1">
                    <div v-if="loadingDetail" class="py-12 flex justify-center">
                        <RefreshCw :size="24" class="animate-spin text-emerald-500" />
                    </div>

                    <template v-else-if="selectedDetail">
                        <!-- Metrics Cards -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-surface-700/50 border border-gray-100 dark:border-surface-700 text-center">
                                <div class="text-[10px] uppercase font-bold text-text-secondary">Stok Sistem</div>
                                <div class="text-lg font-bold font-mono text-text-primary mt-0.5">{{ selectedDetail.total_system }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 text-center">
                                <div class="text-[10px] uppercase font-bold text-emerald-700 dark:text-emerald-300">Fisik Cocok</div>
                                <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">{{ selectedDetail.total_physical }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/40 text-center">
                                <div class="text-[10px] uppercase font-bold text-rose-700 dark:text-rose-300">Selisih (Kurang)</div>
                                <div class="text-lg font-bold font-mono text-rose-600 dark:text-rose-400 mt-0.5">{{ selectedDetail.total_missing }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/40 text-center">
                                <div class="text-[10px] uppercase font-bold text-amber-700 dark:text-amber-300">Barang Lebih</div>
                                <div class="text-lg font-bold font-mono text-amber-600 dark:text-amber-400 mt-0.5">{{ selectedDetail.total_excess }}</div>
                            </div>
                        </div>

                        <!-- Checked Items Section -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-text-secondary uppercase tracking-wider">
                                Daftar Unit Yang Diperiksa ({{ selectedDetail.items?.length || 0 }} item)
                            </h4>

                            <div class="border border-gray-200 dark:border-surface-700 rounded-xl overflow-hidden max-h-64 overflow-y-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50 dark:bg-surface-700/60 font-bold text-text-secondary sticky top-0">
                                        <tr>
                                            <th class="py-2.5 px-3">Barang / IMEI</th>
                                            <th class="py-2.5 px-3 text-center">GB</th>
                                            <th class="py-2.5 px-3 text-center">Sistem</th>
                                            <th class="py-2.5 px-3 text-center">Fisik</th>
                                            <th class="py-2.5 px-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                                        <tr v-for="it in selectedDetail.items" :key="it.id" class="hover:bg-gray-50/50">
                                            <td class="py-2 px-3">
                                                <div class="font-bold text-text-primary">{{ it.product_name }}</div>
                                                <div v-if="it.imei" class="font-mono text-[11px] text-text-secondary">IMEI: {{ it.imei }}</div>
                                                <div v-if="it.notes" class="text-[10px] text-gray-400 italic">"{{ it.notes }}"</div>
                                            </td>
                                            <td class="py-2 px-3 text-center font-mono">{{ it.storage || '-' }}</td>
                                            <td class="py-2 px-3 text-center font-mono">{{ it.system_qty }}</td>
                                            <td class="py-2 px-3 text-center font-mono font-bold">{{ it.physical_qty }}</td>
                                            <td class="py-2 px-3 text-center">
                                                <span v-if="it.is_present && it.difference >= 0" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">
                                                    Ada ✅
                                                </span>
                                                <span v-else class="text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded">
                                                    Tidak Ada ❌
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Excess Items Section -->
                        <div v-if="selectedDetail.excess_items && selectedDetail.excess_items.length > 0" class="space-y-2">
                            <h4 class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                <AlertTriangle :size="14" />
                                <span>Barang Lebih ({{ selectedDetail.excess_items.length }} item)</span>
                            </h4>

                            <div class="divide-y divide-gray-100 dark:divide-surface-700 border border-amber-200 dark:border-amber-900/60 rounded-xl overflow-hidden bg-amber-50/20">
                                <div v-for="ex in selectedDetail.excess_items" :key="ex.id" class="p-3 text-xs flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-text-primary">{{ ex.product_name }} ({{ ex.brand }})</div>
                                        <div class="text-[11px] text-text-secondary font-mono">
                                            {{ ex.imei ? 'IMEI: ' + ex.imei : 'Qty: ' + ex.quantity }}
                                            <span v-if="ex.notes" class="font-sans italic text-gray-400 ml-2">"{{ ex.notes }}"</span>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">
                                        +{{ ex.quantity }} Unit
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Foto Bukti Section -->
                        <div v-if="selectedDetail.photos && selectedDetail.photos.length > 0" class="space-y-2">
                            <h4 class="text-xs font-bold text-text-secondary uppercase tracking-wider flex items-center gap-1.5">
                                <Camera :size="14" class="text-emerald-500" />
                                <span>Foto Bukti Fisik SO</span>
                            </h4>

                            <div class="flex flex-wrap gap-2.5">
                                <div v-for="(p, pIdx) in selectedDetail.photos" :key="pIdx"
                                    class="w-24 h-24 rounded-xl border border-gray-200 dark:border-surface-600 overflow-hidden cursor-pointer hover:opacity-90 transition-opacity"
                                    @click="selectedPhotoModal = p">
                                    <img :src="'/' + p.replace(/^\//, '')" alt="Foto SO" class="w-full h-full object-cover" />
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Section -->
                        <div v-if="selectedDetail.notes" class="space-y-1 bg-gray-50 dark:bg-surface-700/40 p-3 rounded-xl border border-gray-100 dark:border-surface-700 text-xs">
                            <span class="font-bold text-text-secondary">Catatan:</span>
                            <p class="text-text-primary whitespace-pre-wrap">{{ selectedDetail.notes }}</p>
                        </div>
                    </template>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-gray-100 dark:border-surface-700 flex justify-end shrink-0">
                    <button type="button" @click="showDetailModal = false"
                        class="px-5 py-2 rounded-xl bg-gray-100 dark:bg-surface-700 hover:bg-gray-200 text-text-primary text-xs font-bold">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Lightbox Photo Preview Modal -->
        <div v-if="selectedPhotoModal" class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
            @click.self="selectedPhotoModal = null">
            <div class="relative max-w-4xl max-h-[90vh]">
                <button @click="selectedPhotoModal = null" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                    <X :size="24" />
                </button>
                <img :src="'/' + selectedPhotoModal.replace(/^\//, '')" alt="Foto Bukti SO" class="max-w-full max-h-[85vh] rounded-2xl object-contain shadow-2xl" />
            </div>
        </div>
    </div>
</template>
