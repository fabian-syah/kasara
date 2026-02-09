<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    Search, ArrowLeft, RefreshCw, Box, Calendar, User, Truck, ClipboardList, Info, Smartphone, Package
} from 'lucide-vue-next';
import { stockOut, inventory } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { formatDate } from '../../utils/formatters';

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const items = ref([]);
const searchQuery = ref('');
const activeTab = ref('hp'); // 'hp' or 'non-hp'

const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0
});

// Month Filter
const currentDate = new Date();
const currentMonth = currentDate.getMonth() + 1;
const currentYear = currentDate.getFullYear();
const prevDate = new Date();
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

const fetchData = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            page,
            search: searchQuery.value,
            month: selectedMonth.value.month,
            year: selectedMonth.value.year
        };

        let response;
        if (activeTab.value === 'hp') {
            response = await stockOut.list(params);
        } else {
            response = await inventory.historyOut(params);
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

watch([searchQuery, activeTab, selectedMonth], () => {
    fetchData(1);
});

onMounted(() => {
    fetchData();
});

const getCategoryLabel = (cat) => {
    const labels = {
        'terjual': 'Terjual',
        'pindah_cabang': 'Pindah Cabang',
        'retur_suplier': 'Retur ke Suplier',
        'unit_rusak': 'Unit Rusak',
        'hilang': 'Hilang / Dicuri',
        'giveaway': 'Giveaway / Hadiah',
        'out': 'Stok Keluar', // Default for inventory log
        'shopee': 'Shopee',
        'retur': 'Retur',
        'kesalahan_input': 'Kesalahan Input',
        'hadiah': 'Hadiah',
        'brand_ambassador': 'Brand Ambassador',
        'event': 'Event',
        'promo': 'Promo',
        'inventaris': 'Inventaris'
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
        'retur': 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20',
        'out': 'text-surface-400 bg-surface-400/10 border-surface-400/20'
    };
    return colors[cat] || 'text-surface-400 bg-surface-400/10 border-surface-400/20';
};
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
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
                <button @click="fetchData(pagination.current_page)"
                    class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all">
                    <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
                </button>
            </div>
        </div>

        <!-- Controls & Tabs -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
            <div class="flex flex-col px-4 pt-4 md:flex-row gap-4 justify-between items-start md:items-center">
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

                <!-- Search & Month -->
                <div class="flex flex-col md:flex-row gap-4 items-center w-full md:w-auto">
                    <select v-model="selectedMonth"
                        class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 w-full md:w-48">
                        <option v-for="(option, index) in monthOptions" :key="index" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <div class="relative w-full md:w-72">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Cari ID, Penerima, atau Item..."
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                    </div>
                </div>
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
                            <th v-if="activeTab === 'hp'" class="px-6 py-4 whitespace-nowrap">Kategori</th>
                            <th v-if="activeTab === 'hp'" class="px-6 py-4 whitespace-nowrap">Tujuan / Penerima</th>
                            <th class="px-6 py-4 whitespace-nowrap">Item</th>
                            <th class="px-6 py-4 whitespace-nowrap">Quantity / Info</th>
                            <th v-if="activeTab === 'non-hp'" class="px-6 py-4 whitespace-nowrap">Deskripsi</th>
                            <th v-if="activeTab === 'hp'" class="px-6 py-4 whitespace-nowrap">Admin / Inventory</th>
                            <th v-if="activeTab === 'non-hp'" class="px-6 py-4 whitespace-nowrap">Diinput Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="item in items" :key="item.id" class="hover:bg-surface-700/30 transition-colors">
                            <!-- Tanggal for both -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span v-if="activeTab === 'hp'" class="font-mono text-xs text-primary-400">
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
                                    <span
                                        class="px-2 py-1 rounded-lg text-xs font-medium border capitalize whitespace-nowrap"
                                        :class="getCategoryColor(item.category)">
                                        {{ getCategoryLabel(item.category) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Truck v-if="item.category === 'pindah_cabang'" :size="16"
                                            class="text-text-secondary" />
                                        <User v-else :size="16" class="text-text-secondary" />
                                        <span class="font-medium whitespace-nowrap max-w-[150px] truncate block">
                                            {{ item.category === 'pindah_cabang'
                                                ? (item.destination_branch ? item.destination_branch.name : '-')
                                                : (item.receiver_name || item.shopee_receiver || item.giveaway_receiver ||
                                                    item.recipient_name || '-')
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <!-- Check item.items (from StockOut) or standard items -->
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
                                        <span v-if="!(item.items || []).length"
                                            class="text-xs text-text-secondary">-</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-red-400">-{{ (item.items || []).length }} Unit</span>
                                </td>
                                <td class="px-6 py-4">
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

                            <!-- Non-HP Specific Columns (InventoryLog) -->
                            <template v-else>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-text-primary">{{ item.product?.name }}</span>
                                        <span class="text-xs text-text-secondary font-mono">{{ item.product?.sku
                                        }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="item.selling_price" class="font-mono text-emerald-400">
                                        {{ new Intl.NumberFormat('id-ID', {
                                            style: 'currency', currency: 'IDR'
                                        }).format(item.selling_price) }}
                                    </span>
                                    <span v-else class="text-text-secondary text-xs">-</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-red-400 font-bold mb-1">-{{ item.quantity }} Unit</span>
                                        <span class="text-xs text-text-secondary">Balance: {{ item.balance_after
                                        }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-text-secondary text-xs max-w-xs truncate">
                                    {{ item.description || '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2 text-xs">
                                        <User :size="12" />
                                        <span>{{ item.user ? item.user.name : '-' }}</span>
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
