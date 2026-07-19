<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import {
    Search, ArrowLeft, RefreshCw, Smartphone, Box, Calendar, User, FileText, Database, Download, Trash2, MapPin, Globe
} from 'lucide-vue-next';
import api, { inventory } from '../../api/axios';
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
const activeTab = ref('hp');
const searchQuery = ref('');
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
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.branch_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'branch').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(b => allowed.includes(Number(b.id)));
    }
    return result;
});

const filteredOnlineShops = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = onlineShops.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.online_shop_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'online_shop').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(s => allowed.includes(Number(s.id)));
    }
    return result;
});

const filteredWarehouses = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = warehouses.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.warehouse_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'warehouse').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(w => allowed.includes(Number(w.id)));
    }
    return result;
});

const filteredDistributors = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = distributors.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
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

        setTimeout(() => {
            if (filteredBranches.value.length === 0 && locationType.value === 'branch') {
                if (filteredWarehouses.value.length > 0) locationType.value = 'warehouse';
                else if (filteredOnlineShops.value.length > 0) locationType.value = 'online_shop';
                else if (filteredDistributors.value.length > 0) locationType.value = 'distributor';
                handleLocationTypeChange();
            }
        }, 50);
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
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
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
    if (filterMode.value === 'today') {
        const d = getLogicalDate();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    } else if (filterMode.value === 'yesterday') {
        const d = getLogicalDate();
        d.setDate(d.getDate() - 1);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    } else if (filterMode.value === 'date') {
        return selectedDate.value;
    }
    return null;
};

const fetchData = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            type: activeTab.value,
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
        const response = await inventory.historyIn(params);
        items.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total
        };
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat history stok masuk.');
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
        if (dateParam) {
            params.date = dateParam;
        } else {
            params.month = selectedMonth.value.month;
            params.year = selectedMonth.value.year;
        }
        const response = await inventory.exportHistoryIn(params);
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        
        let locationName = 'Semua-Lokasi';
        if (props.isEmbedded) {
            if (props.branchId) {
                const b = branches.value.find(x => Number(x.id) === Number(props.branchId));
                if (b) locationName = b.name.replace(/\s+/g, '-');
            } else if (props.onlineShopId) {
                const s = onlineShops.value.find(x => Number(x.id) === Number(props.onlineShopId));
                if (s) locationName = s.name.replace(/\s+/g, '-');
            }
        } else {
            if (locationType.value === 'branch' && filters.value.branch_id) {
                const b = branches.value.find(x => Number(x.id) === Number(filters.value.branch_id));
                if (b) locationName = b.name.replace(/\s+/g, '-');
            } else if (locationType.value === 'online_shop' && filters.value.online_shop_id) {
                const s = onlineShops.value.find(x => Number(x.id) === Number(filters.value.online_shop_id));
                if (s) locationName = s.name.replace(/\s+/g, '-');
            } else if (locationType.value === 'warehouse' && filters.value.warehouse_id) {
                const w = warehouses.value.find(x => Number(x.id) === Number(filters.value.warehouse_id));
                if (w) locationName = w.name.replace(/\s+/g, '-');
            } else if (locationType.value === 'distributor' && filters.value.distributor_id) {
                const d = distributors.value.find(x => Number(x.id) === Number(filters.value.distributor_id));
                if (d) locationName = d.name.replace(/\s+/g, '-');
            }
        }

        let filename = `stok-masuk-${activeTab.value}-${locationName}`;
        if (filterMode.value === 'month') {
            filename += `-${selectedMonth.value.month}-${selectedMonth.value.year}`;
        } else {
            filename += `-${dateParam || getTodayLocal()}`;
        }
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

watch([activeTab, searchQuery, filterMode, selectedMonth, selectedDate], () => { fetchData(1); });
watch(() => props.branchId, () => { fetchData(1); });
watch(() => props.onlineShopId, () => { fetchData(1); });
watch(filters, () => { fetchData(1); }, { deep: true });

onMounted(() => {
    if (canChangeLocation.value && !props.isEmbedded) fetchLocations();
    fetchData();
    if (window.Echo) {
        window.Echo.channel('inventory-log').listen('.InventoryLogEvent', (e) => {
            const log = e.log;
            const isHp = log.product && log.product.type === 'hp';
            const isNonHp = log.product && log.product.type === 'non-hp';
            if (activeTab.value === 'hp' && isHp) {
                items.value.unshift(log);
                if (items.value.length > 20) items.value.pop();
                toast.success(`History Masuk: ${log.product.name}`);
            } else if (activeTab.value === 'non-hp' && isNonHp) {
                items.value.unshift(log);
                if (items.value.length > 20) items.value.pop();
                toast.success(`History Masuk: ${log.product.name}`);
            }
        });
    }
});

onUnmounted(() => {
    if (window.Echo) window.Echo.leave('inventory-log');
});

const handleVoid = async (item) => {
    const itemName = item.product ? item.product.name : 'Unknown Item';
    const detail = activeTab.value === 'hp' ? `IMEI: ${item.imei}` : `Qty: ${item.quantity} unit`;
    if (!confirm(`Hapus/Void data stok masuk ini?\n${itemName}\n${detail}\n\nTindakan ini akan menghapus barang dari inventory.`)) return;
    try {
        await inventory.voidStockIn(item.id, activeTab.value);
        toast.success('Berhasil membatalkan stok masuk.');
        fetchData(pagination.value.current_page);
    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.message || 'Gagal membatalkan stok masuk.');
    }
};
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div v-if="!isEmbedded" class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push({ name: 'Inventory' })" class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Daftar Stok Masuk</h1>
                    <p class="text-text-secondary mt-1">Riwayat barang masuk ke inventory</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
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

        <!-- Controls -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4 space-y-4">
            <div class="flex flex-col sm:flex-row gap-4 justify-between">
                <!-- Tab Switcher -->
                <div class="flex space-x-1 rounded-xl bg-surface-900 p-1 w-fit">
                    <button v-for="tab in ['hp', 'non-hp']" :key="tab" @click="activeTab = tab"
                        class="px-4 py-2 rounded-lg text-sm font-medium leading-5 transition-all duration-200"
                        :class="activeTab === tab ? 'bg-surface-700 text-white shadow' : 'text-text-secondary hover:text-white'">
                        {{ tab === 'hp' ? 'Unit / HP' : 'NON HP / NON IMEI' }}
                    </button>
                </div>
                <!-- Search -->
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari SKU, Produk, atau..."
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
                        <option v-if="filteredBranches.length > 0" value="branch" class="bg-surface-800 text-text-primary">Cabang</option>
                        <option v-if="filteredOnlineShops.length > 0" value="online_shop" class="bg-surface-800 text-text-primary">Toko Online</option>
                        <option v-if="filteredWarehouses.length > 0" value="warehouse" class="bg-surface-800 text-text-primary">Gudang</option>
                        <option v-if="filteredDistributors.length > 0" value="distributor" class="bg-surface-800 text-text-primary">Distributor</option>
                    </select>
                </div>
                <div class="w-px h-4 bg-surface-700 mr-1"></div>
                <select v-if="locationType === 'branch'" v-model="filters.branch_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null" class="bg-surface-800 text-text-primary">Semua Cabang</option>
                    <option v-for="b in filteredBranches" :key="b.id" :value="b.id" class="bg-surface-800 text-text-primary">{{ b.name }}</option>
                </select>
                <select v-else-if="locationType === 'online_shop'" v-model="filters.online_shop_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null" class="bg-surface-800 text-text-primary">Semua Toko Online</option>
                    <option v-for="s in filteredOnlineShops" :key="s.id" :value="s.id" class="bg-surface-800 text-text-primary">{{ s.name }}</option>
                </select>
                <select v-else-if="locationType === 'warehouse'" v-model="filters.warehouse_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null" class="bg-surface-800 text-text-primary">Semua Gudang</option>
                    <option v-for="w in filteredWarehouses" :key="w.id" :value="w.id" class="bg-surface-800 text-text-primary">{{ w.name }}</option>
                </select>
                <select v-else-if="locationType === 'distributor'" v-model="filters.distributor_id" @change="fetchData(1)"
                    class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                    <option :value="null" class="bg-surface-800 text-text-primary">Semua Distributor</option>
                    <option v-for="d in filteredDistributors" :key="d.id" :value="d.id" class="bg-surface-800 text-text-primary">{{ d.name }}</option>
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
                    <option v-for="(option, index) in monthOptions" :key="index" :value="option.value" class="bg-surface-800 text-text-primary">{{ option.label }}</option>
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
                <p>Belum ada riwayat stok masuk</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Produk</th>
                            <th class="px-6 py-4" v-if="activeTab === 'hp'">IMEI / Detail</th>
                            <th class="px-6 py-4" v-else>Quantity / Info</th>
                            <th class="px-6 py-4 hidden md:table-cell">Sumber / Distributor</th>
                            <th class="px-6 py-4 hidden lg:table-cell">Catatan</th>
                            <th class="px-6 py-4 hidden lg:table-cell">Diinput Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="item in items" :key="item.id" class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-text-secondary">
                                <div class="flex items-center gap-2">
                                    <Calendar :size="14" />
                                    {{ formatDate(item.created_at) }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-white">{{ item.product ? item.product.name : 'Unknown' }}</div>
                                    <div class="text-xs text-text-secondary">{{ item.product ? item.product.sku : '-' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4" v-if="activeTab === 'hp'">
                                <div class="font-mono text-xs bg-surface-900 px-2 py-1 rounded inline-block mb-1">{{ item.imei }}</div>
                                <div class="text-xs text-text-secondary flex gap-2">
                                    <span v-if="item.ram || item.storage">{{ [item.ram, item.storage].filter(Boolean).join('/') }}</span>
                                    <span class="capitalize" :class="item.condition === 'new' ? 'text-green-400' : 'text-amber-400'">{{ item.condition }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4" v-else>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg font-bold text-green-400">+{{ item.quantity }}</span>
                                    <span class="text-xs text-text-secondary">Unit</span>
                                </div>
                                <div class="text-xs text-text-secondary mt-1 max-w-xs truncate">{{ item.description || '-' }}</div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <div v-if="activeTab === 'hp'">
                                    {{ item.distributor ? item.distributor.name : (item.supplier_name || '-') }}
                                    <div class="text-xs text-text-secondary">{{ item.placement_name || '-' }}</div>
                                </div>
                                <div v-else>
                                    {{ item.distributor ? item.distributor.name : (item.supplier_name || '-') }}
                                    <div class="text-xs text-text-secondary" v-if="!item.distributor && item.description">{{ item.description }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden lg:table-cell">
                                <span v-if="item.notes" class="text-xs text-text-secondary italic max-w-[200px] block truncate" :title="item.notes">{{ item.notes }}</span>
                                <span v-else class="text-text-secondary/30">-</span>
                            </td>
                            <td class="px-6 py-4 hidden lg:table-cell">
                                <div class="flex items-center gap-2">
                                    <User :size="14" class="text-text-secondary" />
                                    <span>{{ item.user ? item.user.name : '-' }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="border-t border-surface-700/50 p-4 flex justify-center gap-2">
                <button @click="fetchData(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
                    class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">Previous</button>
                <span class="px-4 py-2 text-sm text-text-secondary">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                <button @click="fetchData(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page"
                    class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">Next</button>
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
