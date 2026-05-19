<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    ArrowLeft, RefreshCw, Smartphone, Package, Box,
    Layers, Tag, Truck, ChevronRight, FileSpreadsheet,
    History, Download, Clock, Calendar, AlertCircle
} from 'lucide-vue-next';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../store/auth';
import axios from '../../api/axios';

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

const loading = ref(false);
const downloadLogs = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);
const totalLogs = ref(0);
const perPage = ref(20);

// Location Filter logic
const locations = ref([]);
const selectedLocationKey = ref('all');

// Date Range
const exportMode = ref('daily'); // 'daily' or 'monthly'
const exportMonth = ref(new Date().toISOString().slice(0, 7)); // 'YYYY-MM'
const exportStartDate = ref(new Date().toISOString().split('T')[0]);
const exportEndDate = ref(new Date().toISOString().split('T')[0]);
const activeExportButton = ref('today');

// Mutation filter type
const mutationType = ref('all'); // 'all', 'hp', 'non_hp'

const minExportDate = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() - 60); // 60 days limit for logs
    return d.toISOString().split('T')[0];
});

const maxExportDate = computed(() => {
    return new Date().toISOString().split('T')[0];
});

let isSettingRange = false;
const setExportRange = (type) => {
    isSettingRange = true;
    const today = new Date();
    exportMode.value = 'daily';
    activeExportButton.value = type;

    if (type === 'today') {
        const d = today.toISOString().split('T')[0];
        exportStartDate.value = d;
        exportEndDate.value = d;
    } else if (type === 'yesterday') {
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        const d = yesterday.toISOString().split('T')[0];
        exportStartDate.value = d;
        exportEndDate.value = d;
    } else if (type === 'month') {
        exportMode.value = 'monthly';
        updateMonthRange();
    }
    setTimeout(() => { isSettingRange = false; }, 50);
};

const updateMonthRange = () => {
    if (!exportMonth.value) return;
    const [year, month] = exportMonth.value.split('-').map(Number);
    
    const start = new Date(year, month - 1, 1);
    const end = new Date(year, month, 0);
    
    const formatDate = (date) => {
        const d = new Date(date);
        let m = '' + (d.getMonth() + 1);
        let day = '' + d.getDate();
        const y = d.getFullYear();
        if (m.length < 2) m = '0' + m;
        if (day.length < 2) day = '0' + day;
        return [y, m, day].join('-');
    };
    
    exportStartDate.value = formatDate(start);
    exportEndDate.value = formatDate(end);
};

watch(exportMonth, () => {
    if (exportMode.value === 'monthly') {
        updateMonthRange();
    }
});

watch([exportStartDate, exportEndDate], () => {
    if (!isSettingRange) {
        activeExportButton.value = 'manual';
    }
});

const selectedLocationName = computed(() => {
    if (selectedLocationKey.value === 'all') return 'SEMUA_CABANG';
    const [prefix, id] = selectedLocationKey.value.split(':');
    const typeMap = { 'B': 'branch', 'S': 'online_shop', 'W': 'warehouse', 'D': 'distributor' };
    const loc = locations.value.find(l => l.id == id && l.type === typeMap[prefix]);
    return loc ? loc.name.toUpperCase().replace(/\s+/g, '_') : id;
});

const selectedBranchId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('B:')) return null;
    return selectedLocationKey.value.split(':')[1];
});

const selectedOnlineShopId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('S:')) return null;
    return selectedLocationKey.value.split(':')[1];
});

const selectedWarehouseId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('W:')) return null;
    return selectedLocationKey.value.split(':')[1];
});

const selectedDistributorId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('D:')) return null;
    return selectedLocationKey.value.split(':')[1];
});

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some(r => role.includes(r));
});

const fetchLocations = async () => {
    try {
        const [branchRes, shopRes, warehouseRes, distributorRes, userRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/warehouses'),
            axios.get('/distributors'),
            axios.get('/user')
        ]);

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allWarehouses = (warehouseRes.data.data || warehouseRes.data || []).map(w => ({ ...w, type: 'warehouse' }));
        const allDistributors = (distributorRes.data.data || distributorRes.data || []).map(d => ({ ...d, type: 'distributor' }));
        const allLocations = [...allBranches, ...allShops, ...allWarehouses, ...allDistributors];

        const user = userRes.data.user || userRes.data.data || userRes.data;
        const role = (authStore.userRole || '').toLowerCase();

        const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk', 'analist'];
        const isAlwaysGlobal = alwaysGlobalRoles.some(r => role.includes(r));

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);
        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);
        let allowedWarehouseIds = [];
        if (user?.warehouse_id) allowedWarehouseIds.push(user.warehouse_id);
        let allowedDistributorIds = [];
        if (user?.distributor_id) allowedDistributorIds.push(user.distributor_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
                if (p.model_type === 'warehouse') allowedWarehouseIds.push(p.model_id);
                if (p.model_type === 'distributor') allowedDistributorIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];
        allowedWarehouseIds = [...new Set(allowedWarehouseIds.map(id => Number(id)))];
        allowedDistributorIds = [...new Set(allowedDistributorIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0 || allowedWarehouseIds.length > 0 || allowedDistributorIds.length > 0;

        const exclusionWords = ['testing', 'trial', 'anu', 'huft'];
        const filteredAllLocations = allLocations.filter(loc => {
            if (!isAlwaysGlobal) return true;
            const name = (loc.name || '').toLowerCase();
            return !exclusionWords.some(word => name.includes(word));
        });

        if (isAlwaysGlobal) {
            locations.value = filteredAllLocations;
        } else if (hasAnyRestriction) {
            locations.value = filteredAllLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                if (loc.type === 'warehouse') return allowedWarehouseIds.includes(Number(loc.id));
                if (loc.type === 'distributor') return allowedDistributorIds.includes(Number(loc.id));
                return false;
            });

            if (locations.value.length === 1) {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`;
            }
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error);
    }
};

const fetchDownloadLogs = async () => {
    if (!authStore.userRole?.toLowerCase().includes('admin') && !authStore.userRole?.toLowerCase().includes('audit') && !authStore.userRole?.toLowerCase().includes('owner') && !authStore.userRole?.toLowerCase().includes('analist')) return;
    try {
        const res = await axios.get('/reports/download-history', {
            params: {
                branch_id: selectedBranchId.value || undefined,
                online_shop_id: selectedOnlineShopId.value || undefined,
                warehouse_id: selectedWarehouseId.value || undefined,
                distributor_id: selectedDistributorId.value || undefined,
                page: currentPage.value,
                per_page: perPage.value
            }
        });
        downloadLogs.value = res.data.data || [];
        currentPage.value = res.data.current_page || 1;
        lastPage.value = res.data.last_page || 1;
        totalLogs.value = res.data.total || 0;
    } catch (err) {
        console.error('Error fetching logs:', err);
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= lastPage.value) {
        currentPage.value = page;
        fetchDownloadLogs();
    }
};

const pageRange = computed(() => {
    const range = [];
    const maxVisiblePages = 5;
    let start = Math.max(1, currentPage.value - 2);
    let end = Math.min(lastPage.value, start + maxVisiblePages - 1);
    
    if (end - start + 1 < maxVisiblePages) {
        start = Math.max(1, end - maxVisiblePages + 1);
    }
    
    for (let i = start; i <= end; i++) {
        range.push(i);
    }
    return range;
});

const getLocationNameFromParams = (params) => {
    if (!params) return 'Semua Lokasi';
    
    if (params.branch_id) {
        const loc = locations.value.find(l => l.type === 'branch' && l.id == params.branch_id);
        return loc ? `[Cabang] ${loc.name}` : `Cabang #${params.branch_id}`;
    }
    if (params.online_shop_id) {
        const loc = locations.value.find(l => l.type === 'online_shop' && l.id == params.online_shop_id);
        return loc ? `[Toko] ${loc.name}` : `Toko #${params.online_shop_id}`;
    }
    if (params.warehouse_id) {
        const loc = locations.value.find(l => l.type === 'warehouse' && l.id == params.warehouse_id);
        return loc ? `[Gudang] ${loc.name}` : `Gudang #${params.warehouse_id}`;
    }
    if (params.distributor_id) {
        const loc = locations.value.find(l => l.type === 'distributor' && l.id == params.distributor_id);
        return loc ? `[Distributor] ${loc.name}` : `Distributor #${params.distributor_id}`;
    }
    
    return 'Semua Lokasi';
};

const downloadExcel = async (type) => {
    let title = '';
    let endpoint = '';
    
    if (type === 'sales') {
        title = 'Laporan Penjualan';
        endpoint = '/audit/sales/export';
    } else if (type === 'inventory') {
        title = 'Laporan Data Inventory';
        endpoint = '/inventory/export';
    } else if (type === 'history') {
        title = 'Laporan Stok Masuk & Keluar';
        endpoint = '/inventory/history/export';
    } else if (type === 'mutation') {
        title = 'Laporan Mutasi Stok';
        endpoint = '/reports/export-stock-movement';
    }

    toast.info(`Sedang menyiapkan ${title}...`);
    try {
        const params = {
            branch_id: selectedBranchId.value || undefined,
            online_shop_id: selectedOnlineShopId.value || undefined,
            warehouse_id: selectedWarehouseId.value || undefined,
            distributor_id: selectedDistributorId.value || undefined,
            start_date: exportStartDate.value,
            end_date: exportEndDate.value,
            date: exportStartDate.value, // for legacy support and mutation date
            mode: exportMode.value,
        };

        if (type === 'mutation') {
            params.type = mutationType.value;
        }
        
        const response = await axios.get(endpoint, { 
            params,
            responseType: 'blob'
        });
        
        let filename = '';
        if (type === 'inventory') {
            filename = `DATA_INVENTORY_${selectedLocationName.value}.xlsx`;
        } else if (type === 'mutation') {
            const typeLabel = mutationType.value === 'hp' ? 'HP' : mutationType.value === 'non_hp' ? 'NON_HP' : 'SEMUA';
            filename = `MUTASI_STOK_${typeLabel}_${exportStartDate.value}.xlsx`;
        } else {
            filename = `${type.toUpperCase()}_${exportStartDate.value}_SD_${exportEndDate.value}.xlsx`;
        }
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        
        toast.success(`${title} berhasil didownload!`);
        fetchDownloadLogs(); 
    } catch (err) {
        console.error(err);
        toast.error(`Gagal mendownload ${title}.`);
    }
};

watch(selectedLocationKey, () => {
    currentPage.value = 1;
    fetchDownloadLogs();
});

onMounted(() => {
    fetchLocations();
    fetchDownloadLogs();
});
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push({ name: 'Inventory' })"
                    class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Download Center</h1>
                    <p class="text-text-secondary mt-0.5 text-sm">
                        Download Laporan Excel Data Inventory, Penjualan, dan Mutasi
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                <!-- Location Filter -->
                <div v-if="canFilterBranch && locations.length > 1" class="min-w-[180px] w-full sm:w-auto">
                    <select v-model="selectedLocationKey"
                        class="block w-full rounded-xl border-0 py-2.5 text-text-primary shadow-sm ring-1 ring-inset ring-surface-700/50 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 bg-surface-800 dark:ring-surface-700">
                        <option value="all">Semua Lokasi</option>
                        <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                            :value="`${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`">
                            {{ loc.type === 'branch' ? '[Cabang]' : loc.type === 'online_shop' ? '[Toko]' : loc.type ===
                                'distributor' ? '[Distributor]' : '[Gudang]' }} {{ loc.name }}
                        </option>
                    </select>
                </div>
                <!-- Single Location Display -->
                <div v-else-if="canFilterBranch && locations.length === 1"
                    class="px-4 py-2.5 bg-surface-800 border border-surface-700 rounded-xl flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                    <span class="text-xs font-bold text-text-secondary">
                        {{ locations[0].type === 'branch' ? '[Cabang]' : locations[0].type === 'online_shop' ? '[Toko]'
                            : locations[0].type === 'distributor' ? '[Distributor]' : '[Gudang]' }}
                    </span>
                    <span class="text-sm font-bold text-text-primary">{{ locations[0].name }}</span>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-5 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-end gap-6">
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <template v-if="exportMode === 'daily'">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                                <Calendar :size="12" /> Dari Tanggal
                            </label>
                            <input type="date" v-model="exportStartDate" :min="minExportDate" :max="maxExportDate"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 h-11 text-sm text-text-primary focus:border-primary-500 focus:ring-0 transition-all" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                                <Calendar :size="12" /> Sampai Tanggal
                            </label>
                            <input type="date" v-model="exportEndDate" :min="minExportDate" :max="maxExportDate"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 h-11 text-sm text-text-primary focus:border-primary-500 focus:ring-0 transition-all" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="col-span-full space-y-2">
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                                <Calendar :size="12" /> Pilih Bulan Laporan
                            </label>
                            <input type="month" v-model="exportMonth"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 h-11 text-sm text-text-primary focus:border-primary-500 focus:ring-0 transition-all" />
                        </div>
                    </template>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="setExportRange('today')" 
                        class="px-5 h-11 rounded-xl text-xs font-black transition-all border tracking-tight"
                        :class="activeExportButton === 'today' ? 'bg-primary-500 border-primary-500 text-white shadow-lg shadow-primary-500/25' : 'bg-surface-900 border border-surface-700 text-text-secondary hover:border-surface-600 hover:text-white'">
                        HARI INI
                    </button>
                    <button @click="setExportRange('yesterday')" 
                        class="px-5 h-11 rounded-xl text-xs font-black transition-all border tracking-tight"
                        :class="activeExportButton === 'yesterday' ? 'bg-primary-500 border-primary-500 text-white shadow-lg shadow-primary-500/25' : 'bg-surface-900 border border-surface-700 text-text-secondary hover:border-surface-600 hover:text-white'">
                        KEMARIN
                    </button>
                    <button @click="setExportRange('month')" 
                        class="px-5 h-11 rounded-xl text-xs font-black transition-all border tracking-tight"
                        :class="activeExportButton === 'month' ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/25' : 'bg-surface-900 border border-surface-700 text-text-secondary hover:border-surface-600 hover:text-white'">
                        BULANAN
                    </button>
                </div>
            </div>
        </div>

        <!-- Download Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Excel Data Inventory -->
            <div class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-blue-500/50 p-6 flex flex-col justify-between transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/5 hover:-translate-y-0.5">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-500/10 rounded-xl group-hover:bg-blue-500/20 transition-colors">
                            <Box :size="24" class="text-blue-400" />
                        </div>
                        <span class="text-[10px] font-black text-blue-400 bg-blue-400/10 px-2 py-1 rounded">STOCK CURRENT</span>
                    </div>
                    <h3 class="text-base font-bold text-text-primary mb-1.5">Data Inventory</h3>
                    <p class="text-xs text-text-secondary leading-relaxed">Ekspor daftar persediaan barang riil saat ini (HP & Non-HP) beserta status penempatannya.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-surface-700">
                    <button @click="downloadExcel('inventory')"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600/15 hover:bg-blue-600/35 border border-blue-500/30 rounded-xl text-blue-400 text-xs font-black transition-all active:scale-95">
                        <Download :size="14" /> DOWNLOAD DATA
                    </button>
                </div>
            </div>

            <!-- Excel Penjualan -->
            <div class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-emerald-500/50 p-6 flex flex-col justify-between transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5 hover:-translate-y-0.5">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-emerald-500/10 rounded-xl group-hover:bg-emerald-500/20 transition-colors">
                            <FileSpreadsheet :size="24" class="text-emerald-400" />
                        </div>
                        <span class="text-[10px] font-black text-emerald-400 bg-emerald-400/10 px-2 py-1 rounded">SALES HISTORY</span>
                    </div>
                    <h3 class="text-base font-bold text-text-primary mb-1.5">Penjualan (Sales)</h3>
                    <p class="text-xs text-text-secondary leading-relaxed">Ekspor riwayat transaksi penjualan barang untuk audit performa toko pada rentang waktu terpilih.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-surface-700">
                    <button @click="downloadExcel('sales')"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600/15 hover:bg-emerald-600/35 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs font-black transition-all active:scale-95">
                        <Download :size="14" /> DOWNLOAD DATA
                    </button>
                </div>
            </div>

            <!-- Excel Stok Masuk/Keluar -->
            <div class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-purple-500/50 p-6 flex flex-col justify-between transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/5 hover:-translate-y-0.5">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-purple-500/10 rounded-xl group-hover:bg-purple-500/20 transition-colors">
                            <History :size="24" class="text-purple-400" />
                        </div>
                        <span class="text-[10px] font-black text-purple-400 bg-purple-400/10 px-2 py-1 rounded">STOCK FLOW</span>
                    </div>
                    <h3 class="text-base font-bold text-text-primary mb-1.5">Stok Masuk & Keluar</h3>
                    <p class="text-xs text-text-secondary leading-relaxed">Laporan log barang masuk dan barang keluar. Terbagi dalam 4 sheet terpisah untuk HP & Non-HP.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-surface-700">
                    <button @click="downloadExcel('history')"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600/15 hover:bg-purple-600/35 border border-purple-500/30 rounded-xl text-purple-400 text-xs font-black transition-all active:scale-95">
                        <Download :size="14" /> DOWNLOAD DATA
                    </button>
                </div>
            </div>

            <!-- Excel Mutasi Stok -->
            <div class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-amber-500/50 p-6 flex flex-col justify-between transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5 hover:-translate-y-0.5">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-amber-500/10 rounded-xl group-hover:bg-amber-500/20 transition-colors">
                            <Layers :size="24" class="text-amber-400" />
                        </div>
                        <span class="text-[10px] font-black text-amber-400 bg-amber-400/10 px-2 py-1 rounded">MUTASI / STOCK OPNAME</span>
                    </div>
                    <h3 class="text-base font-bold text-text-primary mb-1.5">Mutasi Stok</h3>
                    <p class="text-xs text-text-secondary leading-relaxed mb-3">Laporan mutasi perolehan stok, meliputi stok Awal, total barang Masuk, total Keluar, dan Sisa stok.</p>
                    <div class="space-y-1.5 bg-surface-900/50 p-2.5 rounded-xl border border-surface-700/50">
                        <label class="text-[9px] font-bold text-text-secondary uppercase tracking-wider block">Pilih Tipe Barang</label>
                        <select v-model="mutationType" 
                            class="block w-full rounded-lg border-0 py-1 px-2 text-text-primary text-xs bg-surface-800 focus:ring-1 focus:ring-amber-500/50">
                            <option value="all">Semua Barang</option>
                            <option value="hp">Unit HP (IMEI)</option>
                            <option value="non_hp">Non-HP (Aksesoris)</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-surface-700">
                    <button @click="downloadExcel('mutation')"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-600/15 hover:bg-amber-600/35 border border-amber-500/30 rounded-xl text-amber-400 text-xs font-black transition-all active:scale-95">
                        <Download :size="14" /> DOWNLOAD DATA
                    </button>
                </div>
            </div>
        </div>

        <!-- Download History Section -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-surface-700 bg-surface-800/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <History :size="18" class="text-primary-400" />
                    <h3 class="font-bold text-text-primary text-sm sm:text-base">Riwayat Download Excel</h3>
                </div>
                <button @click="fetchDownloadLogs" class="p-1.5 hover:bg-surface-700 rounded-lg transition-colors">
                    <RefreshCw :size="14" class="text-text-secondary" />
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-surface-900/50 text-text-secondary text-[10px] uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3 font-bold">Waktu</th>
                            <th class="px-5 py-3 font-bold">User</th>
                            <th class="px-5 py-3 font-bold">Lokasi</th>
                            <th class="px-5 py-3 font-bold">Nama Laporan</th>
                            <th class="px-5 py-3 font-bold">File</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700">
                        <tr v-if="downloadLogs.length === 0">
                            <td colspan="5" class="px-5 py-8 text-center text-text-secondary italic">
                                Belum ada riwayat download.
                            </td>
                        </tr>
                        <tr v-else v-for="log in downloadLogs" :key="log.id" class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-5 py-3 text-text-secondary whitespace-nowrap">
                                {{ new Date(log.created_at).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium text-text-primary">{{ log.user?.name || 'User' }}</div>
                                <div class="text-[10px] text-text-secondary">{{ log.user?.roles?.[0]?.name }}</div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-lg bg-surface-900 border border-surface-700 text-[10px] font-black text-text-primary">
                                    {{ getLocationNameFromParams(log.params) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-text-primary font-medium">{{ log.report_name }}</td>
                            <td class="px-5 py-3 text-text-secondary italic text-xs">{{ log.filename }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div v-if="totalLogs > 0" class="px-5 py-4 bg-surface-900/30 border-t border-surface-700 flex flex-col sm:flex-row justify-between items-center gap-4">
                <span class="text-xs text-text-secondary">
                    Menampilkan <span class="font-bold text-text-primary">{{ (currentPage - 1) * perPage + 1 }}</span> - 
                    <span class="font-bold text-text-primary">{{ Math.min(currentPage * perPage, totalLogs) }}</span> dari 
                    <span class="font-bold text-text-primary">{{ totalLogs }}</span> riwayat download
                </span>
                
                <div class="flex items-center gap-1.5" v-if="lastPage > 1">
                    <button 
                        @click="changePage(currentPage - 1)" 
                        :disabled="currentPage === 1"
                        class="px-3.5 py-1.5 rounded-xl border border-surface-700 bg-surface-900 text-xs font-bold text-text-secondary hover:text-white hover:border-surface-600 disabled:opacity-40 disabled:hover:text-text-secondary disabled:hover:border-surface-700 transition-all active:scale-95"
                    >
                        Sebelumnya
                    </button>

                    <button 
                        v-for="page in pageRange" 
                        :key="page" 
                        @click="changePage(page)"
                        class="w-9 h-9 rounded-xl border text-xs font-black transition-all active:scale-95 flex items-center justify-center"
                        :class="page === currentPage 
                            ? 'bg-primary-500 border-primary-500 text-white shadow-lg shadow-primary-500/25' 
                            : 'bg-surface-900 border-surface-700 text-text-secondary hover:border-surface-600 hover:text-white'"
                    >
                        {{ page }}
                    </button>

                    <button 
                        @click="changePage(currentPage + 1)" 
                        :disabled="currentPage === lastPage"
                        class="px-3.5 py-1.5 rounded-xl border border-surface-700 bg-surface-900 text-xs font-bold text-text-secondary hover:text-white hover:border-surface-600 disabled:opacity-40 disabled:hover:text-text-secondary disabled:hover:border-surface-700 transition-all active:scale-95"
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
