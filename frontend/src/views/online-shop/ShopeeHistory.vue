<script setup>
import { ref, onMounted, watch, computed } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import {
    Search,
    Loader2,
    Calendar,
    User,
    Smartphone,
    MapPin,
    Package,
    ChevronLeft,
    ChevronRight,
    ShoppingBag,
    Trash2
} from "lucide-vue-next";
import { useAuthStore } from "../../store/auth";
import CancelSaleModal from "../../components/modals/CancelSaleModal.vue";

const toast = useToast();

// State
const isLoading = ref(false);
const history = ref([]);
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 15
});

const search = ref("");
const historyFilter = ref("all"); // orderan_online, cancel_penjualan, all
let searchTimeout = null;

const filteredHistory = computed(() => {
    if (historyFilter.value === 'all') return history.value;
    if (historyFilter.value === 'orderan_online') {
        return history.value.filter(item => item.category === 'orderan_online' || item.category === 'shopee');
    }
    return history.value.filter(item => item.category === historyFilter.value);
});

// Cancellation logic
const showCancelModal = ref(false);
const selectedSaleForCancel = ref(null);

const getLogicalDate = () => {
    const now = new Date();
    if (now.getHours() < 5) now.setDate(now.getDate() - 1);
    return now;
};

const canCancel = (date) => {
    const role = (useAuthStore().userRole || '').toLowerCase();
    if (role === 'super_admin' || role === 'owner') return true;
    if (!date) return false;
    const itemDate = new Date(date);
    if (isNaN(itemDate.getTime())) return false;
    
    const logicalNow = getLogicalDate();
    logicalNow.setHours(0, 0, 0, 0);
    
    const compareDate = new Date(itemDate);
    compareDate.setHours(0, 0, 0, 0);
    
    const msPerDay = 24 * 60 * 60 * 1000;
    const diffDays = Math.round((logicalNow.getTime() - compareDate.getTime()) / msPerDay);
    return diffDays <= 5;
};


const handleCancelSale = (item) => {
    selectedSaleForCancel.value = item;
    showCancelModal.value = true;
};

// Fetch history
const fetchHistory = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get("/stock-outs/shopee-history", {
            params: {
                page,
                q: search.value
            }
        });

        const data = response.data;
        history.value = data.data;
        pagination.value = {
            currentPage: data.current_page,
            lastPage: data.last_page,
            total: data.total,
            perPage: data.per_page
        };
    } catch (e) {
        toast.error("Gagal memuat history Online");
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

// Search handler with debounce
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchHistory(1);
    }, 500);
};

watch(search, handleSearch);

// Formatters
const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit"
    });
};

const formatAddress = (stockOut, detailAddress) => {
    const parts = [];
    if (detailAddress) parts.push(detailAddress);

    // Add regions if available
    if (stockOut.shopee_village) parts.push(stockOut.shopee_village);
    if (stockOut.shopee_district) parts.push(stockOut.shopee_district);
    if (stockOut.shopee_city) parts.push(stockOut.shopee_city);
    if (stockOut.shopee_province) parts.push(stockOut.shopee_province);
    if (stockOut.shopee_postal_code) parts.push(stockOut.shopee_postal_code);

    return parts.join(', ');
};

const formatCurrency = (value) => {
    if (!value && value !== 0) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

// Initial load
onMounted(() => {
    fetchHistory();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                    <ShoppingBag class="text-primary-500" />
                    History Orderan Online
                </h1>
                <p class="text-text-secondary mt-1">Riwayat pengeluaran stok untuk pesanan Online</p>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex bg-surface-800 p-1 rounded-xl border border-surface-700">
                    <button v-for="btn in [
                        { id: 'all', label: 'Semua' },
                        { id: 'orderan_online', label: 'Online' },
                        { id: 'cancel_penjualan', label: 'Dibatalkan' }
                    ]" :key="btn.id" @click="historyFilter = btn.id"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="historyFilter === btn.id ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        {{ btn.label }}
                    </button>
                </div>

                <div class="relative w-full md:w-64">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                    <input v-model="search" type="text" placeholder="Cari Resi / Nama..."
                        class="pl-10 w-full bg-surface-800 border-surface-700 rounded-xl focus:ring-primary-500 focus:border-primary-500 transition-all text-text-primary h-10" />
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="card overflow-hidden">
            <div v-if="isLoading" class="p-12 text-center text-text-secondary">
                <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                Memuat data...
            </div>

            <div v-else-if="history.length === 0" class="p-12 text-center text-text-secondary">
                <Package :size="48" class="mx-auto mb-2 opacity-50" />
                <p>Belum ada data history Orderan Online</p>
            </div>

            <div v-else class="divide-y divide-surface-700">
                <div v-for="item in filteredHistory" :key="item.id" class="p-4 hover:bg-surface-700/30 transition-colors">
                    <div class="flex flex-col gap-4">
                        <!-- Header Row -->
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    class="font-mono font-bold text-primary-400 bg-primary-500/10 px-2 py-0.5 rounded text-sm">
                                    {{ item.receipt_id }}
                                </span>
                                <span class="text-xs text-text-secondary flex items-center gap-1">
                                    <Calendar :size="12" />
                                    {{ formatDate(item.created_at) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="item.selling_price" class="text-sm font-bold text-emerald-400">
                                    {{ formatCurrency(item.selling_price) }}
                                </span>
                                <span class="text-xs bg-surface-700 px-2 py-0.5 rounded text-text-secondary">
                                    {{ item.user?.name || 'Unknown' }}
                                </span>
                                <button v-if="item.category !== 'cancel_penjualan' && canCancel(item.created_at)" 
                                    @click="handleCancelSale(item)"
                                    class="p-1.5 text-red-500 hover:bg-red-500/10 rounded-lg transition-colors ml-2"
                                    title="Batalkan Penjualan">
                                    <Trash2 :size="16" />
                                </button>
                                <span v-else-if="item.category === 'cancel_penjualan'" class="text-[10px] font-bold text-red-400 uppercase ml-2">
                                    Dibatalkan
                                </span>
                            </div>
                        </div>

                        <!-- Penerima & Resi Info -->
                        <div class="bg-surface-900/50 rounded-lg p-3 border border-surface-700/50 text-sm">
                            <div class="flex flex-wrap gap-x-6 gap-y-1 items-center">
                                <span class="font-medium text-text-primary flex items-center gap-1">
                                    <User :size="12" class="text-text-secondary" />
                                    {{ item.shopee_receiver || '-' }}
                                </span>
                                <span v-if="item.shopee_phone" class="text-text-secondary text-xs">
                                    {{ item.shopee_phone }}
                                </span>
                                <span
                                    class="font-mono bg-surface-800 px-1.5 rounded text-xs text-text-primary border border-surface-700">
                                    {{ item.shopee_items_data?.[0]?.tracking_no || item.shopee_tracking_no || '-' }}
                                </span>
                            </div>
                            <div v-if="item.shopee_address"
                                class="text-xs text-text-secondary mt-1 flex items-start gap-1">
                                <MapPin :size="10" class="mt-0.5 shrink-0" />
                                <span>{{ formatAddress(item, item.shopee_address) }}</span>
                            </div>
                        </div>

                        <!-- HP Items (IMEI) -->
                        <div v-if="item.items && item.items.length > 0" class="space-y-1">
                            <p class="text-[10px] uppercase font-bold text-text-secondary">Unit HP (IMEI)</p>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="prod in item.items" :key="prod.id"
                                    class="flex items-center gap-2 text-xs bg-surface-700/50 px-3 py-1.5 rounded-lg text-text-primary border border-surface-600/50">
                                    <Smartphone :size="12" class="text-primary-400" />
                                    <span class="font-medium">{{ prod.product?.name }}</span>
                                    <span class="text-text-secondary font-mono text-[10px]">{{ prod.imei }}</span>
                                    <span v-if="prod.selling_price" class="text-emerald-400 font-bold ml-1">
                                        {{ formatCurrency(prod.selling_price) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Non-HP Items -->
                        <div v-if="item.non_hp_items && item.non_hp_items.length > 0" class="space-y-1">
                            <p class="text-[10px] uppercase font-bold text-text-secondary">Non HP / Aksesoris</p>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="(nhItem, idx) in item.non_hp_items" :key="idx"
                                    class="flex items-center gap-2 text-xs bg-surface-700/50 px-3 py-1.5 rounded-lg text-text-primary border border-surface-600/50">
                                    <Package :size="12" class="text-amber-400" />
                                    <span class="font-medium">{{ nhItem.product_name || 'Unknown' }}</span>
                                    <span class="text-text-secondary">×{{ nhItem.quantity }}</span>
                                    <span v-if="nhItem.selling_price" class="text-emerald-400 font-bold ml-1">
                                        @ {{ formatCurrency(nhItem.selling_price) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory User -->
                        <div v-if="item.inventory_user" class="flex items-center gap-1 text-xs text-text-secondary">
                            <User :size="12" class="text-primary-400" />
                            <span>Akun Inventory: <strong class="text-white">{{ item.inventory_user.full_name ||
                                item.inventory_user.name }}</strong></span>
                        </div>

                        <!-- Cancel Reason -->
                        <div v-if="item.category === 'cancel_penjualan' && item.cancel_reason"
                            class="p-2 rounded-lg bg-red-500/10 border border-red-500/20 text-xs mt-1">
                            <p class="text-red-400 font-bold mb-0.5">Alasan Pembatalan:</p>
                            <p class="text-text-primary">{{ item.cancel_reason }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-surface-700 flex items-center justify-between" v-if="pagination.total > 0">
                <p class="text-sm text-text-secondary">
                    Total {{ pagination.total }} data
                </p>
                <div class="flex gap-2">
                    <button @click="fetchHistory(pagination.currentPage - 1)" :disabled="pagination.currentPage <= 1"
                        class="btn-icon">
                        <ChevronLeft :size="18" />
                    </button>
                    <span
                        class="flex items-center px-4 text-sm font-medium text-text-primary bg-surface-800 rounded-lg">
                        {{ pagination.currentPage }} / {{ pagination.lastPage }}
                    </span>
                    <button @click="fetchHistory(pagination.currentPage + 1)"
                        :disabled="pagination.currentPage >= pagination.lastPage" class="btn-icon">
                        <ChevronRight :size="18" />
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Cancel Sale Modal -->
        <CancelSaleModal :show="showCancelModal" :sale="selectedSaleForCancel" @close="showCancelModal = false" @success="fetchHistory" />
    </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
    @apply bg-surface-800 rounded-xl border border-surface-700;
}

.btn-icon {
    @apply w-9 h-9 flex items-center justify-center rounded-lg bg-surface-800 hover:bg-surface-700 text-text-secondary hover:text-text-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed border border-surface-700;
}
</style>
