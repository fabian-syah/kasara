<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import {
    Search, ArrowLeft, RefreshCw, Box, Calendar, User, Truck, ClipboardList, Info, Smartphone, Package, Download, AlertTriangle, MapPin, Globe
} from 'lucide-vue-next';
import api, { stockOut, inventory } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { formatDate, formatCurrency, getLogicalDate, getTodayLocal } from '../../utils/formatters';
import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const router = useRouter();
const toast = useToast();

const props = defineProps({
    isEmbedded: { type: Boolean, default: false },
    branchId: { type: [Number, String], default: null },
    onlineShopId: { type: [Number, String], default: null }
});

const loading = ref(false);
const exporting = ref(false);
const items = ref([]);
const searchQuery = ref('');
const activeTab = ref('hp');
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const filterMode = ref('month');
const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return !['super_admin', 'analist', 'admin_produk'].some(r => role.includes(r));
});

const canChangeLocation = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'analist', 'audit', 'leader', 'owner', 'admin_produk'].some(r => role.includes(r));
});

const locationType = ref('branch');
const filters = ref({
    branch_id: props.branchId || null,
    online_shop_id: props.onlineShopId || null,
    warehouse_id: null,
    distributor_id: null,
});

const branches = ref([]);
const onlineShops = ref([]);
const warehouses = ref([]);
const distributors = ref([]);

const filteredBranches = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = branches.value || [];
    if (!['super_admin', 'analist', 'admin_produk', 'owner', 'audit', 'leader'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.branch_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'branch').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(b => allowed.includes(Number(b.id)));
    }
    return result;
});

const filteredOnlineShops = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = onlineShops.value || [];
    if (!['super_admin', 'analist', 'admin_produk', 'owner', 'audit', 'leader'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.online_shop_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'online_shop').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(s => allowed.includes(Number(s.id)));
    }
    return result;
});

const filteredWarehouses = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = warehouses.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk', 'audit', 'leader'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.warehouse_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'warehouse').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(w => allowed.includes(Number(w.id)));
    }
    return result;
});

const filteredDistributors = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = distributors.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk', 'audit', 'leader'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.distributor_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'distributor').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(d => allowed.includes(Number(d.id)));
    }
    return result;
});

const fetchLocations = async () => {
    try {
        const response = await api.get('/inventory/meta-locations');
        branches.value = response.data.branches || [];
        onlineShops.value = response.data.online_shops || [];
        warehouses.value = response.data.warehouses || [];
        distributors.value = response.data.distributors || [];
    } catch (err) {
        console.error(err);
    }
};

const handleLocationTypeChange = () => {
    filters.value.branch_id = null;
    filters.value.online_shop_id = null;
    filters.value.warehouse_id = null;
    filters.value.distributor_id = null;
    fetchData(1);
};

const getMinDate = computed(() => {
    if (!isRestricted.value) return null;
    const d = getLogicalDate();
    d.setDate(d.getDate() - 7);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

const currentDate = getLogicalDate();
const currentMonth = currentDate.getMonth() + 1;
const currentYear = currentDate.getFullYear();
const prevDate = new Date(currentDate);
prevDate.setMonth(prevDate.getMonth() - 1);
const prevMonth = prevDate.getMonth() + 1;
const prevYear = prevDate.getFullYear();

const monthOptions = [
    { label: currentDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }), value: { month: currentMonth, year: currentYear } },
    { label: prevDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }), value: { month: prevMonth, year: prevYear } }
];
const selectedMonth = ref(monthOptions[0].value);
const selectedDate = ref(getTodayLocal());

const filterPresets = [
    { label: 'Hari Ini', value: 'today' },
    { label: 'Kemarin', value: 'yesterday' },
    { label: 'Pilih Tanggal', value: 'date' },
    { label: 'Per Bulan', value: 'month' },
];

const getDateParam = () => {
    if (filterMode.value === 'today') return getTodayLocal();
    if (filterMode.value === 'yesterday') {
        const d = getLogicalDate();
        d.setDate(d.getDate() - 1);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
    if (filterMode.value === 'date') return selectedDate.value;
    return null;
};

const fetchData = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            search: searchQuery.value,
            branch_id: props.isEmbedded ? props.branchId : filters.value.branch_id,
            online_shop_id: props.isEmbedded ? props.onlineShopId : filters.value.online_shop_id,
            warehouse_id: props.isEmbedded ? null : filters.value.warehouse_id,
            distributor_id: props.isEmbedded ? null : filters.value.distributor_id,
        };
        const dateParam = getDateParam();
        if (dateParam) {
            params.date = dateParam;
        } else {
            params.month = selectedMonth.value.month;
            params.year = selectedMonth.value.year;
        }
        const type = activeTab.value === 'hp' ? 'hp' : 'non-hp';
        const response = await stockOut.list({ ...params, type });
        items.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total
        };
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat daftar stok keluar.');
        items.value = [];
    } finally {
        loading.value = false;
    }
};

const exportExcel = async () => {
    exporting.value = true;
    try {
        const params = {
            type: activeTab.value,
            search: searchQuery.value,
            branch_id: props.isEmbedded ? props.branchId : filters.value.branch_id,
            online_shop_id: props.isEmbedded ? props.onlineShopId : filters.value.online_shop_id,
            warehouse_id: props.isEmbedded ? null : filters.value.warehouse_id,
            distributor_id: props.isEmbedded ? null : filters.value.distributor_id,
        };
        const dateParam = getDateParam();
        if (dateParam) { params.date = dateParam; } else {
            params.month = selectedMonth.value.month;
            params.year = selectedMonth.value.year;
        }
        const response = await inventory.exportHistoryOut(params);
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        let filename = `stok-keluar-${activeTab.value}`;
        filename += filterMode.value === 'month' ? `-${selectedMonth.value.month}-${selectedMonth.value.year}` : `-${dateParam || getTodayLocal()}`;
        link.setAttribute('download', `${filename}.xlsx`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        toast.success('File berhasil diunduh!');
    } catch (error) {
        console.error(error);
        toast.error('Gagal mengunduh file.');
    } finally {
        exporting.value = false;
    }
};

watch([searchQuery, activeTab, filterMode, selectedMonth, selectedDate], () => { fetchData(1); });
watch(() => props.branchId, () => { fetchData(1); });
watch(() => props.onlineShopId, () => { fetchData(1); });
watch(filters, () => { fetchData(1); }, { deep: true });

onMounted(() => {
    if (canChangeLocation.value && !props.isEmbedded) fetchLocations();
    fetchData();
    if (window.Echo) {
        window.Echo.channel('stock-out').listen('.StockOutEvent', (e) => {
            const out = e.stockOut;
            const hasHp = out.items && out.items.length > 0;
            let hasNonHp = Array.isArray(out.non_hp_items) ? out.non_hp_items.length > 0 : (typeof out.non_hp_items === 'string' && out.non_hp_items.length > 2);
            if (activeTab.value === 'hp' && hasHp) { items.value.unshift(out); toast.info(`Stok keluar baru: ${out.receipt_id}`); }
            else if (activeTab.value === 'non-hp' && hasNonHp) { items.value.unshift(out); toast.info(`Stok keluar (Non-HP) baru: ${out.receipt_id}`); }
        });
    }
});

onUnmounted(() => { if (window.Echo) window.Echo.leave('stock-out'); });

const getCategoryLabel = (cat) => {
    const labels = {
        'terjual': 'Terjual', 'pindah_cabang': 'Pindah Cabang', 'retur_suplier': 'Retur ke Suplier',
        'unit_rusak': 'Unit Rusak', 'hilang': 'Hilang / Dicuri', 'giveaway': 'Giveaway / Hadiah',
        'out': 'Stok Keluar', 'shopee': 'Orderan Online', 'orderan_online': 'Orderan Online',
        'retur': 'Retur', 'kesalahan_input': 'Kesalahan Input', 'hadiah': 'Hadiah',
        'brand_ambassador': 'Brand Ambassador', 'event': 'Event', 'promo': 'Promo',
        'inventaris': 'Inventaris', 'event_sponsorship': 'Event / Sponsorship', 'keluar': 'Keluar'
    };
    return labels[cat] || cat;
};

const getCategoryColor = (cat) => {
    const colors = {
        'terjual': 'text-green-400 bg-green-400/10 border-green-400/20',
        'pindah_cabang': 'text-blue-400 bg-blue-400/10 border-blue-400/20',
        'retur_suplier': 'text-orange-400 bg-orange-400/10 border-orange-400/20',
        'unit_rusak': 'text-red-400 bg-red-400/10 border-red-400/20',
        'hilang': 'text-red-500 bg-red-500/10 border-red-500/20',
        'giveaway': 'text-purple-400 bg-purple-400/10 border-purple-400/20',
        'shopee': 'text-orange-500 bg-orange-500/10 border-orange-500/20',
        'orderan_online': 'text-orange-500 bg-orange-500/10 border-orange-500/20',
        'retur': 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20',
        'out': 'text-surface-400 bg-surface-400/10 border-surface-400/20',
        'keluar': 'text-purple-500 bg-purple-500/10 border-purple-500/20',
        'event_sponsorship': 'text-cyan-400 bg-cyan-400/10 border-cyan-400/20',
    };
    return colors[cat] || 'text-surface-400 bg-surface-400/10 border-surface-400/20';
};
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div v-if="!isEmbedded" class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push({ name: 'Inventory' })" class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Daftar Stok Keluar</h1>
                    <p class="text-text-secondary mt-1">Riwayat barang keluar dari inventory</p>
                </div>
            </div>
            <div class="flex items-center gap-3 w-full md:w-auto">
                <button @click="exportExcel" :disabled="exporting"
                    class="flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-xl text-sm font-medium transition-all">
                    <Download :size="16" :class="{ 'animate-bounce': exporting }" />
                    <span>{{ exporting ? 'Downloading...' : 'Export Excel' }}</span>
                </button>
                <button @click="fetchData(pagination.current_page)"
                    class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all">
                    <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
                </button>
            </div>
        </div>

        <!-- Controls & Tabs -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4 space-y-4">
            <div class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
                <!-- Tabs -->
                <div class="flex p-1 bg-surface-900/50 rounded-xl border border-surface-700/50 w-full md:w-auto">
                    <button @click="activeTab = 'hp'"
                        class="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg text-sm font-medium transition-all duration-200"
                        :class="activeTab === 'hp' ? 'bg-surface-700 text-text-primary shadow-lg ring-1 ring-white/10' : 'text-text-secondary hover:text-text-primary hover:bg-surface-800/50'">
                        <Smartphone :size="16" />
                        <span>Unit / HP</span>
                    </button>
                    <button @click="activeTab = 'non-hp'"
                        class="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg text-sm font-medium transition-all duration-200"
                        :class="activeTab === 'non-hp' ? 'bg-surface-700 text-text-primary shadow-lg ring-1 ring-white/10' : 'text-text-secondary hover:text-text-primary hover:bg-surface-800/50'">
                        <Package :size="16" />
                        <span>NON HP / NON IMEI</span>
                    </button>
                </div>
                <!-- Search -->
                <div class="relative w-full md:w-72">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari ID, Penerima, atau Item..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
            </div>

            <!-- Location Filter -->
            <div v-if="canChangeLocation && !isEmbedded"
                class="flex items-center gap-2 bg-surface-900 border border-surface-700 rounded-xl p-1 shadow-sm w-fit">
                <div class="flex items-center gap-1 group">
                    <div class="p-1.5 bg-surface-800 rounded-lg group-hover:bg-primary-500/10 transition-colors">
                        <MapPin v-if="locationType === 'branch'" :size="14" class="text-text-secondary group-hover:text-primary-500" />
                        <Globe v-else :size="14" class="text-text-secondary group-hover:text-primary-500" />
                    </div>
                    <select v-model="locationType" @change="handleLocationTypeChange"
                        class="bg-transparent border-none text-[10px] uppercase tracking-wider font-black text-text-secondary focus:ring-0 cursor-pointer pr-6">
                        <option value="branch">Cabang</option>
                        <option value="online_shop">Toko Online</option>
                        <option value="warehouse">Gudang</option>
                        <option value="distributor">Distributor</option>
                    </select>
                </div>
                <div class="w-px h-4 bg-surface-700 mr-1"></div>
                <select v-if="locationType === 'branch'" v-model="filters.branch_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null">Semua Cabang</option>
                    <option v-for="b in filteredBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-else-if="locationType === 'online_shop'" v-model="filters.online_shop_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null">Semua Toko Online</option>
                    <option v-for="s in filteredOnlineShops" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-else-if="locationType === 'warehouse'" v-model="filters.warehouse_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null">Semua Gudang</option>
                    <option v-for="w in filteredWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
                <select v-else-if="locationType === 'distributor'" v-model="filters.distributor_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null">Semua Distributor</option>
                    <option v-for="d in filteredDistributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>

            <!-- Date Filter -->
            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                <div class="flex flex-wrap gap-2">
                    <button v-for="preset in filterPresets" :key="preset.value" @click="filterMode = preset.value"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all border"
                        :class="filterMode === preset.value ? 'bg-primary-500/20 text-primary-400 border-primary-500/30' : 'bg-surface-900 text-text-secondary border-surface-700 hover:text-white'">
                        {{ preset.label }}
                    </button>
                </div>
                <input v-if="filterMode === 'date'" v-model="selectedDate" type="date" :min="getMinDate" :max="getTodayLocal()"
                    class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50" />
                <select v-if="filterMode === 'month'" v-model="selectedMonth"
                    class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <option v-for="(option, index) in monthOptions" :key="index" :value="option.value">{{ option.label }}</option>
                </select>
                <span class="text-xs text-text-secondary ml-2">Total: {{ pagination.total }} item</span>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <div v-if="loading" class="p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="32" />
                <span class="ml-3 text-text-secondary">Memuat data...</span>
            </div>
            <div v-else-if="items.length === 0" class="p-12 text-center text-text-secondary">
                <Box :size="48" class="mx-auto mb-3 opacity-50" />
                <p>Belum ada riwayat stok keluar</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4 whitespace-nowrap">Tanggal / ID</th>
                            <th class="px-6 py-4 whitespace-nowrap">Kategori</th>
                            <th class="px-6 py-4 whitespace-nowrap hidden md:table-cell">Tujuan / Penerima</th>
                            <th class="px-6 py-4 whitespace-nowrap">Item</th>
                            <th class="px-6 py-4 whitespace-nowrap">Quantity / Info</th>
                            <th class="px-6 py-4 whitespace-nowrap hidden lg:table-cell">Admin / Inventory</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="item in items" :key="item.id" class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs text-primary-400">{{ item.receipt_id || item.transaction_code || '-' }}</span>
                                    <span class="text-xs text-text-secondary flex items-center gap-1 mt-1">
                                        <Calendar :size="12" />
                                        {{ formatDate(item.created_at) }}
                                    </span>
                                </div>
                            </td>
                            <template v-if="activeTab === 'hp'">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium border capitalize whitespace-nowrap flex items-center gap-1.5"
                                            :class="item.category === 'hilang' ? 'bg-red-500/20 text-red-500 border-red-500/30 animate-pulse font-bold' : getCategoryColor(item.category)">
                                            <AlertTriangle v-if="item.category === 'hilang'" :size="12" />
                                            {{ getCategoryLabel(item.category) }}
                                        </span>
                                        <span v-if="item.sub_category" class="text-[10px] text-text-secondary px-1">{{ item.sub_category }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <Truck v-if="item.category === 'pindah_cabang'" :size="16" class="text-text-secondary" />
                                        <User v-else :size="16" class="text-text-secondary" />
                                        <span class="font-medium whitespace-nowrap max-w-[150px] truncate block">
                                            {{ item.category === 'pindah_cabang' ? (item.destination?.name || item.destination_branch?.name || '-') : (item.recipient_label || '-') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <div v-for="(detail, index) in (item.consolidated_items || []).slice(0, 5)" :key="index" class="text-xs flex justify-between gap-4">
                                            <span class="text-text-secondary truncate max-w-[150px]" :class="{'text-primary-400 font-bold': detail.type === 'Bundle'}">{{ detail.name }}</span>
                                            <span class="font-mono text-xs bg-surface-900 px-1 rounded">{{ detail.imei && detail.imei !== '-' ? detail.imei : `Qty: ${detail.qty}` }}</span>
                                        </div>
                                        <div v-if="(item.consolidated_items || []).length > 5" class="text-xs text-primary-400 italic">+{{ (item.consolidated_items || []).length - 5 }} item lainnya</div>
                                        <span v-if="!(item.consolidated_items || []).length" class="text-xs text-text-secondary">-</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-bold text-red-400">-{{ item.items_count || 0 }} Unit</span>
                                        <span v-if="item.selling_price" class="text-xs text-emerald-400 font-medium">{{ formatCurrency(item.selling_price) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden lg:table-cell">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 text-sm font-bold text-white">
                                            <User :size="14" class="text-primary-400" />
                                            <span>{{ item.inventory_user ? (item.inventory_user.full_name || item.inventory_user.name) : '-' }}</span>
                                        </div>
                                        <span class="text-[10px] text-text-secondary mt-1 ml-6">Admin: {{ item.user ? item.user.name : '-' }}</span>
                                    </div>
                                </td>
                            </template>
                            <template v-else>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium border capitalize whitespace-nowrap flex items-center gap-1.5"
                                            :class="item.category === 'hilang' ? 'bg-red-500/20 text-red-500 border-red-500/30 animate-pulse font-bold' : getCategoryColor(item.category)">
                                            <AlertTriangle v-if="item.category === 'hilang'" :size="12" />
                                            {{ getCategoryLabel(item.category) }}
                                        </span>
                                        <span v-if="item.sub_category" class="text-[10px] text-text-secondary px-1">{{ item.sub_category }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <Truck v-if="item.category === 'pindah_cabang'" :size="16" class="text-text-secondary" />
                                        <User v-else :size="16" class="text-text-secondary" />
                                        <span class="font-medium whitespace-nowrap max-w-[150px] truncate block">
                                            {{ item.category === 'pindah_cabang' ? (item.destination?.name || item.destination_branch?.name || '-') : (item.recipient_label || '-') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <div v-for="(detail, index) in (item.consolidated_items || []).slice(0, 5)" :key="index" class="text-xs flex justify-between gap-4">
                                            <span class="text-text-secondary truncate max-w-[150px]" :class="{'text-primary-400 font-bold': detail.type === 'Bundle'}">{{ detail.name }}</span>
                                            <span class="font-mono text-xs bg-surface-900 px-1 rounded">Qty: {{ detail.qty }}</span>
                                        </div>
                                        <div v-if="(item.consolidated_items || []).length > 5" class="text-xs text-primary-400 italic">+{{ (item.consolidated_items || []).length - 5 }} item lainnya</div>
                                        <span v-if="!(item.consolidated_items || []).length" class="text-xs text-text-secondary md:hidden block">-</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-bold text-red-400">-{{ item.items_count || 0 }} Unit</span>
                                        <span v-if="item.selling_price" class="text-xs text-emerald-400 font-medium">{{ formatCurrency(item.selling_price) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden lg:table-cell">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 text-sm font-bold text-white">
                                            <User :size="14" class="text-primary-400" />
                                            <span>{{ item.inventory_user ? (item.inventory_user.full_name || item.inventory_user.name) : '-' }}</span>
                                        </div>
                                        <span class="text-[10px] text-text-secondary mt-1 ml-6">Admin: {{ item.user ? item.user.name : '-' }}</span>
                                    </div>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="border-t border-surface-700/50 p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                <span class="text-sm text-text-secondary order-2 sm:order-1">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} items)</span>
                <div class="flex gap-2 order-1 sm:order-2">
                    <button @click="fetchData(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
                        class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">Previous</button>
                    <button @click="fetchData(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page"
                        class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">Next</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
