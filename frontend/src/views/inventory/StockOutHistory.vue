<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    Search, ArrowLeft, RefreshCw, Box, Calendar, User, Truck, ClipboardList, Info, Smartphone, Package, Download
} from 'lucide-vue-next';
import { stockOut, inventory } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { formatDate, getLogicalDate, getTodayLocal } from '../../utils/formatters';

import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const router = useRouter();
const toast = useToast();

const props = defineProps({
    isEmbedded: {
        type: Boolean,
        default: false
    },
    branchId: {
        type: [Number, String],
        default: null
    },
    onlineShopId: {
        type: [Number, String],
        default: null
    }
});

const loading = ref(false);
const exporting = ref(false);
const items = ref([]);
const searchQuery = ref('');
const activeTab = ref('hp');

const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0
});

// Date Filter
const filterMode = ref('month');
const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return !privilegedRoles.some(r => role.includes(r));
});


// getTodayLocal is now imported

const getMinDate = computed(() => {
    if (!isRestricted.value) return null;
    const d = getLogicalDate();
    d.setDate(d.getDate() - 1); // Allow today and yesterday
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
    {
        label: currentDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }),
        value: { month: currentMonth, year: currentYear }
    },
    {
        label: prevDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }),
        value: { month: prevMonth, year: prevYear }
    }
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
    const logicalNow = getLogicalDate();
    if (filterMode.value === 'today') {
        return getTodayLocal();
    } else if (filterMode.value === 'yesterday') {
        const d = getLogicalDate();
        d.setDate(d.getDate() - 1);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
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
            search: searchQuery.value,
            branch_id: props.branchId || undefined,
            online_shop_id: props.onlineShopId || undefined
        };

        const dateParam = getDateParam();
        if (dateParam) {
            params.date = dateParam;
        } else {
            params.month = selectedMonth.value.month;
            params.year = selectedMonth.value.year;
        }

        let response;
        if (activeTab.value === 'hp') {
            response = await stockOut.list({ ...params, type: 'hp' });
        } else {
            response = await stockOut.list({ ...params, type: 'non-hp' });
        }

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
        const params = { search: searchQuery.value };
        const dateParam = getDateParam();
        if (dateParam) {
            params.date = dateParam;
        } else {
            params.month = selectedMonth.value.month;
            params.year = selectedMonth.value.year;
        }

        const response = await inventory.exportHistoryOut(params);
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `stok-keluar-${getTodayLocal()}.csv`);
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

watch([searchQuery, activeTab, filterMode, selectedMonth, selectedDate], () => {
    fetchData(1);
});

watch(() => props.branchId, () => {
    fetchData(1);
});

watch(() => props.onlineShopId, () => {
    fetchData(1);
});

onMounted(() => {
    fetchData();

    if (window.Echo) {
        window.Echo.channel('stock-out')
            .listen('.StockOutEvent', (e) => {
                const out = e.stockOut;
                // Determine if this stock out is relevant for current tab
                const hasHp = out.items && out.items.length > 0;

                // non_hp_items can be array or JSON string depending on serialization, but likely array due to casts
                let hasNonHp = false;
                if (Array.isArray(out.non_hp_items)) {
                    hasNonHp = out.non_hp_items.length > 0;
                } else if (typeof out.non_hp_items === 'string') {
                    hasNonHp = out.non_hp_items.length > 2; // "{}" or "[]"
                }

                if (activeTab.value === 'hp' && hasHp) {
                    items.value.unshift(out);
                    toast.info(`Stok keluar baru: ${out.receipt_id}`);
                } else if (activeTab.value === 'non-hp' && hasNonHp) {
                    items.value.unshift(out);
                    toast.info(`Stok keluar (Non-HP) baru: ${out.receipt_id}`);
                }
            });
    }
});

const getCategoryLabel = (cat) => {
    const labels = {
        'terjual': 'Terjual',
        'pindah_cabang': 'Pindah Cabang',
        'retur_suplier': 'Retur ke Suplier',
        'unit_rusak': 'Unit Rusak',
        'hilang': 'Hilang / Dicuri',
        'giveaway': 'Giveaway / Hadiah',
        'out': 'Stok Keluar',
        'shopee': 'Orderan Online',
        'orderan_online': 'Orderan Online',
        'retur': 'Retur',
        'kesalahan_input': 'Kesalahan Input',
        'hadiah': 'Hadiah',
        'brand_ambassador': 'Brand Ambassador',
        'event': 'Event',
        'promo': 'Promo',
        'inventaris': 'Inventaris',
        'keluar': 'Keluar'
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
        'keluar': 'text-purple-500 bg-purple-500/10 border-purple-500/20'
    };
    return colors[cat] || 'text-surface-400 bg-surface-400/10 border-surface-400/20';
};

const formatCurrency = (value) => {
    if (!value && value !== 0) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div v-if="!isEmbedded" class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push({ name: 'Inventory' })"
                    class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
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
                        :class="activeTab === 'hp'
                            ? 'bg-surface-700 text-text-primary shadow-lg ring-1 ring-white/10'
                            : 'text-text-secondary hover:text-text-primary hover:bg-surface-800/50'">
                        <Smartphone :size="16" />
                        <span>Unit / HP</span>
                    </button>
                    <button @click="activeTab = 'non-hp'"
                        class="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg text-sm font-medium transition-all duration-200"
                        :class="activeTab === 'non-hp'
                            ? 'bg-surface-700 text-text-primary shadow-lg ring-1 ring-white/10'
                            : 'text-text-secondary hover:text-text-primary hover:bg-surface-800/50'">
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

            <!-- Date Filter -->
            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                <div class="flex flex-wrap gap-2">
                    <button v-for="preset in filterPresets" :key="preset.value" @click="filterMode = preset.value"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all border" :class="filterMode === preset.value
                            ? 'bg-primary-500/20 text-primary-400 border-primary-500/30'
                            : 'bg-surface-900 text-text-secondary border-surface-700 hover:text-white'">
                        {{ preset.label }}
                    </button>
                </div>

                <input v-if="filterMode === 'date'" v-model="selectedDate" type="date"
                    :min="getMinDate" :max="getTodayLocal()"
                    class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50" />


                <select v-if="filterMode === 'month'" v-model="selectedMonth"
                    class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <option v-for="(option, index) in monthOptions" :key="index" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <span class="text-xs text-text-secondary ml-2">
                    Total: {{ pagination.total }} item
                </span>
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
                            <!-- <th class="px-6 py-4 whitespace-nowrap">Deskripsi / Catatan</th> -->
                            <th class="px-6 py-4 whitespace-nowrap hidden lg:table-cell">Admin / Inventory</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="item in items" :key="item.id" class="hover:bg-surface-700/30 transition-colors">
                            <!-- Tanggal for both -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs text-primary-400">
                                        {{ item.receipt_id || item.transaction_code || '-' }}
                                    </span>
                                    <span class="text-xs text-text-secondary flex items-center gap-1 mt-1">
                                        <Calendar :size="12" />
                                        {{ formatDate(item.created_at) }}
                                    </span>
                                </div>
                            </td>

                            <!-- HP Specific Columns -->
                            <template v-if="activeTab === 'hp'">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span
                                            class="px-2 py-1 rounded-lg text-xs font-medium border capitalize whitespace-nowrap"
                                            :class="getCategoryColor(item.category)">
                                            {{ getCategoryLabel(item.category) }}
                                        </span>
                                        <span v-if="item.sub_category" class="text-[10px] text-text-secondary px-1">
                                            {{ item.sub_category }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <Truck v-if="item.category === 'pindah_cabang'" :size="16"
                                            class="text-text-secondary" />
                                        <User v-else :size="16" class="text-text-secondary" />
                                        <span class="font-medium whitespace-nowrap max-w-[150px] truncate block">
                                            {{ item.category === 'pindah_cabang'
                                                ? (item.destination?.name || item.destination_branch?.name || '-')
                                                : (item.receiver_name || item.shopee_receiver || item.giveaway_receiver ||
                                                    item.recipient_name || '-')
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <!-- HP Items -->
                                        <div v-for="(detail, index) in (item.items || []).slice(0, 3)" :key="index"
                                            class="text-xs flex justify-between gap-4">
                                            <span class="text-text-secondary truncate max-w-[150px]">
                                                {{ detail.product ? detail.product.name : 'Unknown Product' }}
                                            </span>
                                            <span class="font-mono text-xs bg-surface-900 px-1 rounded">
                                                {{ detail.imei || `Qty: ${detail.quantity}` }}
                                            </span>
                                        </div>
                                        <div v-if="(item.items || []).length > 3"
                                            class="text-xs text-primary-400 italic">
                                            +{{ (item.items || []).length - 3 }} item lainnya
                                        </div>
                                        <!-- Non-HP Items (mixed) -->
                                        <div v-if="item.non_hp_items && item.non_hp_items.length > 0"
                                            class="mt-1 border-t border-surface-700/40 pt-1">
                                            <div v-for="(nhItem, idx) in item.non_hp_items" :key="'nh-' + idx"
                                                class="text-xs flex justify-between gap-4">
                                                <span class="text-amber-400 truncate max-w-[150px]">
                                                    {{ nhItem.product_name || 'Non-HP' }}
                                                </span>
                                                <span class="font-mono text-xs bg-surface-900 px-1 rounded">
                                                    ×{{ nhItem.quantity }}
                                                </span>
                                            </div>
                                        </div>
                                        <span v-if="!(item.items || []).length && !(item.non_hp_items || []).length"
                                            class="text-xs text-text-secondary">-</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-bold text-red-400">-{{ (item.items || []).length }}
                                            Unit</span>
                                        <span v-if="item.selling_price" class="text-xs text-emerald-400 font-medium">
                                            {{ formatCurrency(item.selling_price) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden lg:table-cell">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 text-sm font-bold text-white">
                                            <User :size="14" class="text-primary-400" />
                                            <span>{{ item.inventory_user ? (item.inventory_user.full_name ||
                                                item.inventory_user.name) : '-' }}</span>
                                        </div>
                                        <span class="text-[10px] text-text-secondary mt-1 ml-6">
                                            Admin: {{ item.user ? item.user.name : '-' }}
                                        </span>
                                    </div>
                                </td>
                            </template>

                            <!-- Non-HP Specific Columns -->
                            <template v-else>
                                <!-- Col 2: Kategori -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span
                                            class="px-2 py-1 rounded-lg text-xs font-medium border capitalize whitespace-nowrap"
                                            :class="getCategoryColor(item.category)">
                                            {{ getCategoryLabel(item.category) }}
                                        </span>
                                        <span v-if="item.sub_category" class="text-[10px] text-text-secondary px-1">
                                            {{ item.sub_category }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Col 3: Tujuan / Penerima -->
                                <td class="px-6 py-4 hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <Truck v-if="item.category === 'pindah_cabang'" :size="16"
                                            class="text-text-secondary" />
                                        <User v-else :size="16" class="text-text-secondary" />
                                        <span class="font-medium whitespace-nowrap max-w-[150px] truncate block">
                                            {{ item.category === 'pindah_cabang'
                                                ? (item.destination?.name || item.destination_branch?.name || '-')
                                                : (item.receiver_name || item.shopee_receiver || item.giveaway_receiver ||
                                                    item.recipient_name || '-')
                                            }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Col 4: Item (Product Name) -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <div v-for="(detail, index) in (item.non_hp_items || []).slice(0, 3)"
                                            :key="index" class="text-xs flex justify-between gap-4">
                                            <span class="text-text-secondary truncate max-w-[150px]">
                                                {{ detail.product_name || 'Unknown Product' }}
                                            </span>
                                            <span class="font-mono text-xs bg-surface-900 px-1 rounded">
                                                Qty: {{ detail.quantity }}
                                            </span>
                                        </div>
                                        <div v-if="(item.non_hp_items || []).length > 3"
                                            class="text-xs text-primary-400 italic">
                                            +{{ (item.non_hp_items || []).length - 3 }} item lainnya
                                        </div>
                                        <!-- HP Items (mixed) -->
                                        <div v-if="item.items && item.items.length > 0"
                                            class="mt-1 border-t border-surface-700/40 pt-1">
                                            <div v-for="(hpItem, idx) in item.items" :key="'hp-' + idx"
                                                class="text-xs flex justify-between gap-4">
                                                <span class="text-primary-400 truncate max-w-[150px]">
                                                    {{ hpItem.product ? hpItem.product.name : 'HP' }}
                                                </span>
                                                <span class="font-mono text-xs bg-surface-900 px-1 rounded">
                                                    {{ hpItem.imei }}
                                                </span>
                                            </div>
                                        </div>
                                        <span v-if="!(item.non_hp_items || []).length && !(item.items || []).length"
                                            class="text-xs text-text-secondary md:hidden block">-</span>
                                    </div>
                                </td>

                                <!-- Col 5: Quantity (Total) -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-bold text-red-400">
                                            -{{(item.non_hp_items || []).reduce((acc, curr) => acc +
                                                parseInt(curr.quantity), 0)}} Unit
                                        </span>
                                        <span v-if="item.selling_price" class="text-xs text-emerald-400 font-medium">
                                            {{ formatCurrency(item.selling_price) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Col 6: Admin / Inventory -->
                                <td class="px-6 py-4 hidden lg:table-cell">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2 text-sm font-bold text-white">
                                            <User :size="14" class="text-primary-400" />
                                            <span>{{ item.inventory_user ? (item.inventory_user.full_name ||
                                                item.inventory_user.name) : '-' }}</span>
                                        </div>
                                        <span class="text-[10px] text-text-secondary mt-1 ml-6">
                                            Admin: {{ item.user ? item.user.name : '-' }}
                                        </span>
                                    </div>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1"
                class="border-t border-surface-700/50 p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                <span class="text-sm text-text-secondary order-2 sm:order-1">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} items)
                </span>
                <div class="flex gap-2 order-1 sm:order-2">
                    <button @click="fetchData(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
                        class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">
                        Previous
                    </button>
                    <button @click="fetchData(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
