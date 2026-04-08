<script setup>
import { ref, onMounted, computed, watch } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useAuthStore } from "../../store/auth";
import {
    Search,
    Loader2,
    Calendar,
    User,
    Package,
    Smartphone,
    ShoppingBag,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Download,
    Trash2
} from "lucide-vue-next";
import CancelSaleModal from "../../components/modals/CancelSaleModal.vue";
import { formatNumber, parseCurrency } from "../../utils/formatters";

const toast = useToast();

// State
const isLoading = ref(false);
const rawHistory = ref([]);
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 20
});

const search = ref("");
const filterType = ref("all"); // yesterday, today, this_month, all
let searchTimeout = null;

// Cancellation logic
const showCancelModal = ref(false);
const selectedSaleForCancel = ref(null);

const canCancel = (date) => {
    const role = (useAuthStore().userRole || '').toLowerCase();
    if (role === 'super_admin' || role === 'owner') return true;
    if (!date) return false;
    const itemDate = new Date(date);
    if (isNaN(itemDate.getTime())) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    itemDate.setHours(0, 0, 0, 0);
    const msPerDay = 24 * 60 * 60 * 1000;
    const diffDays = Math.round((today.getTime() - itemDate.getTime()) / msPerDay);
    return diffDays <= 5;
};

const handleCancelSale = (order) => {
    selectedSaleForCancel.value = order;
    showCancelModal.value = true;
};

// Flattened data for table
const groupedSales = computed(() => {
    return rawHistory.value.map(order => {
        const items = [];
        const shopeeData = Array.isArray(order.shopee_items_data) ? order.shopee_items_data : [];

        // HP Items
        if (order.items && order.items.length > 0) {
            order.items.forEach(item => {
                const detailFromJSON = shopeeData.find(si => si.product_detail_id === item.id);
                let price = parseCurrency(detailFromJSON?.selling_price || item.selling_price || 0);

                items.push({
                    brand: item.product?.brand || '-',
                    type: item.product?.name || '-',
                    kapasitas: item.storage || '-',
                    identifier: item.imei,
                    is_hp: true,
                    price: price
                });
            });
        }

        // Non-HP Items
        if (order.non_hp_items && order.non_hp_items.length > 0) {
            order.non_hp_items.forEach(item => {
                let price = parseCurrency(item.selling_price || 0);

                items.push({
                    brand: '-',
                    type: item.product_name || '-',
                    kapasitas: '-',
                    identifier: `${item.quantity} Pcs`,
                    is_hp: false,
                    price: price
                });
            });
        }

        return {
            id: order.id,
            receipt_id: order.receipt_id,
            customer_name: order.shopee_receiver || order.customer_name || '-',
            items: items,
            petugas: order.inventory_user?.name || order.inventory_user?.full_name || '-',
            notes: order.notes || order.shopee_notes || '-',
            created_at: order.created_at,
            total_price: items.reduce((sum, i) => sum + i.price, 0) || order.selling_price || 0,
            category: order.category,
            status: order.category === 'cancel_penjualan' ? 'cancelled' : order.status
        };
    });
});

// Fetch data
const fetchData = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get("/stock-outs/shopee-history", {
            params: {
                page,
                q: search.value,
                per_page: 20,
                // Add filter params
                date: filterType.value === 'today' ? new Date().toISOString().split('T')[0]
                    : (filterType.value === 'yesterday' ? new Date(Date.now() - 86400000).toISOString().split('T')[0] : null),
                month: filterType.value === 'this_month' ? new Date().getMonth() + 1 : null,
                year: filterType.value === 'this_month' ? new Date().getFullYear() : null
            }
        });

        const data = response.data;
        rawHistory.value = data.data;
        pagination.value = {
            currentPage: data.current_page,
            lastPage: data.last_page,
            total: data.total, // This is total orders, not total items
            perPage: data.per_page
        };
    } catch (e) {
        toast.error("Gagal memuat data penjualan online");
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

// Search handling
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchData(1);
    }, 500);
};

watch(search, handleSearch);

// Formatters
const formatDate = (dateString, showTime = true) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    const datePart = date.toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });

    if (showTime) {
        const timePart = date.toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
        return `${datePart} ${timePart}`;
    }
    return datePart;
};

const formatCurrency = (value) => {
    if (!value && value !== 0) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in duration-500">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                    <ShoppingBag class="text-primary-500" />
                    Penjualan Online
                </h1>
                <p class="text-text-secondary mt-1">Daftar transaksi penjualan online terperinci</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Date Filters -->
                <div class="flex bg-surface-800 p-1 rounded-xl border border-surface-700">
                    <button v-for="btn in [
                        { id: 'today', label: 'Hari Ini' },
                        { id: 'yesterday', label: 'Kemarin' },
                        { id: 'this_month', label: 'Bulan Ini' },
                        { id: 'all', label: 'Semua' }
                    ]" :key="btn.id" @click="filterType = btn.id; fetchData(1)"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="filterType === btn.id ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        {{ btn.label }}
                    </button>
                </div>

                <!-- Search -->
                <div class="relative w-full md:w-64">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                    <input v-model="search" type="text" placeholder="Cari..."
                        class="pl-10 w-full bg-surface-800 border-surface-700 rounded-xl focus:ring-primary-500 focus:border-primary-500 transition-all text-text-primary h-11" />
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card overflow-hidden border-surface-700/50 shadow-xl shadow-black/20">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-text-secondary uppercase bg-surface-700/50">
                        <tr>
                            <th class="px-6 py-4 font-bold">No. Nota</th>
                            <th class="px-6 py-4 font-bold text-center">Tgl</th>
                            <th class="px-6 py-4 font-bold">Customer</th>
                            <th class="px-6 py-4 font-bold">Brand</th>
                            <th class="px-6 py-4 font-bold">Tipe</th>
                            <th class="px-6 py-4 font-bold text-center">Kapasitas</th>
                            <th class="px-6 py-4 font-bold">IMEI / Qty</th>
                            <th class="px-6 py-4 font-bold">Harga</th>
                            <th class="px-6 py-4 font-bold">Petugas</th>
                            <th class="px-6 py-4 font-bold">Catatan</th>
                            <th class="px-6 py-4 font-bold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700">
                        <tr v-if="isLoading">
                            <td colspan="11" class="px-6 py-12 text-center text-text-secondary">
                                <Loader2 :size="32" class="animate-spin mx-auto mb-2 opacity-50" />
                                <span class="animate-pulse">Memuat data...</span>
                            </td>
                        </tr>
                        <tr v-else-if="groupedSales.length === 0">
                            <td colspan="11" class="px-6 py-12 text-center text-text-secondary">
                                <ClipboardList :size="48" class="mx-auto mb-2 opacity-20" />
                                <p>Tidak ada data penjualan ditemukan</p>
                            </td>
                        </tr>
                        <tr v-for="order in groupedSales" :key="order.id"
                            class="hover:bg-surface-700/30 transition-colors group border-b border-surface-700">
                            <td class="px-6 py-4 whitespace-nowrap align-top">
                                <span
                                    class="font-mono font-bold text-primary-400 bg-primary-500/10 px-2 py-1 rounded text-xs border border-primary-500/20">
                                    {{ order.receipt_id }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-center text-[10px] text-text-secondary align-top font-mono">
                                {{ formatDate(order.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-text-primary align-top">
                                {{ order.customer_name }}
                            </td>
                            <td class="px-6 py-4 text-text-secondary space-y-6">
                                <div v-for="(item, idx) in order.items" :key="idx"
                                    class="flex items-start min-h-[24px]">
                                    {{ item.brand }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-text-primary font-semibold space-y-6">
                                <div v-for="(item, idx) in order.items" :key="idx"
                                    class="flex items-start min-h-[24px]">
                                    {{ item.type }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center space-y-6">
                                <div v-for="(item, idx) in order.items" :key="idx"
                                    class="flex items-center justify-center min-h-[24px]">
                                    <span v-if="item.kapasitas !== '-'"
                                        class="px-2 py-0.5 rounded bg-surface-700 text-[10px] text-text-primary border border-surface-600">
                                        {{ item.kapasitas }}
                                    </span>
                                    <span v-else class="text-text-secondary">-</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 space-y-6">
                                <div v-for="(item, idx) in order.items" :key="idx"
                                    class="flex items-start gap-2 min-h-[24px]">
                                    <component :is="item.is_hp ? Smartphone : Package" :size="12"
                                        :class="item.is_hp ? 'text-blue-400' : 'text-amber-400'" class="mt-1" />
                                    <span class="font-mono text-[11px] whitespace-nowrap"
                                        :class="item.is_hp ? 'text-text-primary' : 'text-text-secondary'">
                                        {{ item.identifier }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right align-top space-y-6">
                                <div v-for="(item, idx) in order.items" :key="idx"
                                    class="flex items-center justify-end font-bold text-emerald-400 text-xs min-h-[24px]">
                                    {{ formatCurrency(item.price) }}
                                </div>
                                <div v-if="order.items.length > 1" class="pt-2 border-t border-surface-700/50 mt-2">
                                    <div
                                        class="text-[9px] text-text-secondary leading-none mb-0.5 uppercase tracking-tighter">
                                        Total Receipt</div>
                                    <div class="font-black text-emerald-400 text-sm">
                                        {{ formatCurrency(order.total_price) }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-6 h-6 rounded-full bg-surface-700 flex items-center justify-center text-[10px] text-primary-400 font-bold border border-surface-600">
                                        {{ order.petugas.substring(0, 1).toUpperCase() }}
                                    </div>
                                    <span class="text-text-secondary text-xs truncate max-w-[80px]">{{ order.petugas
                                        }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <p class="text-[11px] text-text-secondary leading-relaxed max-w-[150px]"
                                    :title="order.notes">
                                    {{ order.notes }}
                                </p>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <button v-if="order.category !== 'cancel_penjualan' && canCancel(order.created_at)" 
                                    @click="handleCancelSale(order)"
                                    class="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-colors"
                                    title="Batalkan Penjualan">
                                    <Trash2 :size="18" />
                                </button>
                                <span v-else-if="order.category === 'cancel_penjualan'" class="text-[10px] font-bold text-red-400 uppercase">
                                    Dibatalkan
                                </span>
                                <span v-else class="text-text-secondary opacity-30 italic text-[10px]">Locked</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer / Pagination Info -->
            <div
                class="p-4 border-t border-surface-700 bg-surface-800/50 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-sm text-text-secondary">
                    Menampilkan <span class="text-text-primary font-bold">{{ groupedSales.length }}</span> baris data
                </div>
                <div class="flex items-center gap-3">
                    <button @click="fetchData(pagination.currentPage - 1)"
                        :disabled="pagination.currentPage <= 1 || isLoading" class="btn-nav">
                        <ChevronLeft :size="18" />
                    </button>
                    <div class="flex items-center gap-1">
                        <span
                            class="px-3 py-1 bg-surface-700 rounded-lg text-sm font-bold text-primary-400 border border-surface-600 shadow-inner">
                            {{ pagination.currentPage }}
                        </span>
                        <span class="text-text-secondary text-xs px-1">dari</span>
                        <span class="text-text-secondary font-medium text-sm">{{ pagination.lastPage }}</span>
                    </div>
                    <button @click="fetchData(pagination.currentPage + 1)"
                        :disabled="pagination.currentPage >= pagination.lastPage || isLoading" class="btn-nav">
                        <ChevronRight :size="18" />
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Cancel Sale Modal -->
        <CancelSaleModal :show="showCancelModal" :sale="selectedSaleForCancel" @close="showCancelModal = false" @success="fetchData" />
    </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
    @apply bg-surface-800 rounded-2xl border border-surface-700;
}

.custom-scrollbar::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: theme('colors.surface.700');
    border-radius: 9999px;
    border: 3px solid transparent;
}

.btn-nav {
    @apply w-10 h-10 flex items-center justify-center rounded-xl bg-surface-700 border border-surface-600 text-text-secondary hover:text-primary-400 hover:border-primary-500/50 transition-all disabled:opacity-30 disabled:cursor-not-allowed shadow-lg active:scale-95;
}
</style>
