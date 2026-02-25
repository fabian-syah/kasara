<script setup>
import { ref, onMounted, computed, watch } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useRouter } from "vue-router";
import {
    Package,
    Loader2,
    ArrowDownRight,
    Calendar,
    User,
    Smartphone,
    CheckCircle2,
    Clock,
    Building2,
    RefreshCw,
    X,
    AlertTriangle,
    Warehouse,
    Store,
    ShoppingCart,
    Truck,
    Search,
    ChevronLeft,
    ChevronRight,
    FileText
} from "lucide-vue-next";

const toast = useToast();
const router = useRouter();

// State
const isLoading = ref(true);
const transfers = ref({
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0
});
const searchQuery = ref("");
const currentPage = ref(1);

// Detail Modal State
const showDetailModal = ref(false);
const selectedTransfer = ref(null);

// Fetch History
async function fetchHistory(page = 1) {
    isLoading.value = true;
    try {
        const response = await api.get('/transfers/history', {
            params: {
                page: page,
                q: searchQuery.value,
                type: 'outgoing'
            }
        });
        transfers.value = response.data;
        currentPage.value = page;
    } catch (e) {
        console.error(e);
        toast.error("Gagal memuat riwayat transfer");
    } finally {
        isLoading.value = false;
    }
}

// Watch Search
let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchHistory(1);
    }, 500);
});

// Format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function openDetail(transfer) {
    selectedTransfer.value = transfer;
    showDetailModal.value = true;
}

function closeDetail() {
    showDetailModal.value = false;
    selectedTransfer.value = null;
}

onMounted(() => fetchHistory(1));
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-6xl mx-auto pb-24">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-3">
                    <div class="w-12 h-12 bg-green-500/20 rounded-2xl flex items-center justify-center">
                        <FileText :size="24" class="text-green-500" />
                    </div>
                    Riwayat Transfer Keluar
                </h1>
                <p class="text-text-secondary mt-1">
                    Daftar transfer barang yang dikirim ke cabang atau lokasi lain
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative">
                    <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
                    <input v-model="searchQuery" type="text" placeholder="Cari No. Resi..."
                        class="bg-surface-800 border border-surface-600 rounded-xl pl-10 pr-4 py-2 text-sm text-white focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 w-64" />
                </div>
                <button @click="fetchHistory(currentPage)" :disabled="isLoading"
                    class="btn btn-secondary gap-2 rounded-xl h-10 px-4">
                    <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading && transfers.data.length === 0" class="text-center py-20 text-text-secondary">
            <Loader2 :size="40" class="animate-spin mx-auto mb-4" />
            <p>Memuat riwayat...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="transfers.data.length === 0"
            class="text-center py-20 bg-surface-800 rounded-2xl border border-surface-700">
            <div
                class="w-16 h-16 mx-auto bg-surface-700 rounded-2xl flex items-center justify-center mb-4 text-text-secondary">
                <FileText :size="32" />
            </div>
            <h2 class="text-lg font-bold text-text-primary mb-1">Belum ada riwayat</h2>
            <p class="text-text-secondary text-sm">Transfer yang sudah dikonfirmasi akan muncul di sini</p>
        </div>

        <!-- List -->
        <div v-else class="space-y-4">
            <div v-for="transfer in transfers.data" :key="transfer.id"
                class="card hover:bg-surface-750 transition-all cursor-pointer group relative overflow-hidden"
                @click="openDetail(transfer)">

                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center bg-surface-700 text-text-secondary">
                            <Building2 :size="20" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-text-primary group-hover:text-blue-400 transition-colors">
                                    {{ transfer.receipt_id }}
                                </p>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                    :class="transfer.status === 'confirmed' || transfer.status === 'received' ? 'bg-green-500/20 text-green-500' : 'bg-amber-500/20 text-amber-500'">
                                    {{ transfer.status }}
                                </span>
                            </div>
                            <p class="text-xs text-text-secondary mt-0.5">
                                Ke: <span class="text-text-primary font-medium">
                                    {{ transfer.destination?.name || transfer.destination_branch?.name || 'Unknown' }}
                                </span>
                                <span class="mx-1">•</span>
                                {{ formatDate(transfer.created_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- Items Summary -->
                    <div class="text-right">
                        <p class="text-sm font-bold text-green-500 mb-0.5" v-if="transfer.selling_price > 0">
                            Rp {{ Number(transfer.selling_price || 0).toLocaleString('id-ID') }}
                        </p>
                        <p class="text-sm font-medium text-text-primary">
                            Total: {{(transfer.items?.length || 0) + (transfer.non_hp_items?.reduce((acc, i) => acc +
                                i.quantity, 0) || 0)}} Unit
                        </p>
                        <p class="text-xs text-text-secondary mt-1" v-if="transfer.confirmed_by">
                            Diterima oleh: {{ transfer.confirmed_by?.full_name || transfer.confirmed_by?.name ||
                                transfer.confirmed_by?.username || 'System' }}
                        </p>
                        <p class="text-xs text-text-secondary text-amber-500 mt-1" v-else>
                            Menunggu Konfirmasi
                        </p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between pt-4 border-t border-surface-700/50">
                <p class="text-sm text-text-secondary">
                    Halaman {{ transfers.current_page }} dari {{ transfers.last_page }}
                </p>
                <div class="flex gap-2">
                    <button @click="fetchHistory(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                        class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-50 text-text-secondary">
                        <ChevronLeft :size="20" />
                    </button>
                    <button @click="fetchHistory(currentPage + 1)"
                        :disabled="currentPage === transfers.last_page || isLoading"
                        class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-50 text-text-secondary">
                        <ChevronRight :size="20" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetailModal && selectedTransfer"
            class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
            @click.self="closeDetail">
            <div
                class="bg-surface-800 rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col border border-surface-700 shadow-2xl animate-in zoom-in duration-200">
                <!-- Modal Header -->
                <div
                    class="p-6 border-b border-surface-700 flex justify-between items-center bg-surface-800 rounded-t-2xl z-10">
                    <div>
                        <h2 class="text-xl font-bold text-white">Detail Transfer</h2>
                        <div class="flex items-center gap-2 text-sm text-text-secondary mt-1">
                            <span>{{ selectedTransfer.receipt_id }}</span>
                            <span>•</span>
                            <span class="capitalize font-bold"
                                :class="selectedTransfer.status === 'confirmed' || selectedTransfer.status === 'received' ? 'text-green-500' : 'text-amber-500'">
                                {{ selectedTransfer.status }}
                            </span>
                        </div>
                    </div>
                    <button @click="closeDetail" class="text-text-secondary hover:text-white transition-colors">
                        <X :size="24" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto flex-1 space-y-6">
                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 gap-4 bg-surface-900/50 p-4 rounded-xl border border-surface-700">
                        <div>
                            <p class="text-xs text-text-secondary mb-1">Pengirim / Kurir</p>
                            <p class="font-medium text-text-primary">{{ selectedTransfer.inventoryUser?.full_name ||
                                selectedTransfer.inventoryUser?.name || selectedTransfer.user?.name || 'Unknown' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text-secondary mb-1">Dikirim Tanggal</p>
                            <p class="font-medium text-text-primary">{{ formatDate(selectedTransfer.created_at) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text-secondary mb-1">Tujuan</p>
                            <p class="font-medium text-text-primary">
                                {{ selectedTransfer.destination?.name || selectedTransfer.destination_branch?.name ||
                                    'Unknown' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-text-secondary mb-1">Total Barang</p>
                            <p class="font-medium text-text-primary">
                                {{(selectedTransfer.items?.length || 0) + (selectedTransfer.non_hp_items?.reduce((acc,
                                    i) => acc + i.quantity, 0) || 0)}} Unit
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-text-secondary mb-1">Total Harga Jual</p>
                            <p class="font-bold text-green-500">
                                Rp {{ Number(selectedTransfer.selling_price || 0).toLocaleString('id-ID') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-text-secondary mb-1">Kontak Tujuan</p>
                            <p class="font-medium text-text-primary">
                                {{ selectedTransfer.receiver_name || '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-text-secondary mb-1">Dikonfirmasi Oleh</p>
                            <p class="font-medium text-text-primary"
                                :class="{ 'text-amber-500': !selectedTransfer.confirmed_by }">
                                {{ selectedTransfer.confirmed_by?.full_name || selectedTransfer.confirmed_by?.name ||
                                    selectedTransfer.confirmed_by?.username || 'Belum Diterima' }}
                            </p>
                        </div>
                        <div class="col-span-2" v-if="selectedTransfer.transfer_notes || selectedTransfer.notes">
                            <p class="text-xs text-text-secondary mb-1">Catatan</p>
                            <p class="font-medium text-text-primary whitespace-pre-wrap">{{
                                selectedTransfer.transfer_notes || selectedTransfer.notes }}</p>
                        </div>
                    </div>

                </div>

                <!-- HP Items (Accepted) -->
                <div v-if="selectedTransfer.items && selectedTransfer.items.some(i => i.status !== 'in_transit' && i.pivot?.status !== 'rejected')"
                    class="mt-4">
                    <h3
                        class="font-bold text-text-primary mb-3 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <Smartphone :size="16" class="text-blue-500" /> Barang HP (Diterima)
                    </h3>
                    <div class="space-y-2">
                        <div v-for="item in selectedTransfer.items.filter(i => i.status !== 'in_transit' && i.pivot?.status !== 'rejected')"
                            :key="item.id"
                            class="flex items-center justify-between p-3 rounded-xl border border-surface-700 bg-surface-800/50">
                            <div>
                                <p class="font-bold text-sm text-text-primary">{{ item.product?.name }}</p>
                                <p class="text-xs font-mono text-text-secondary">{{ item.imei }}</p>
                            </div>
                            <span
                                class="text-xs font-bold text-green-500 bg-green-500/10 px-2 py-1 rounded">DITERIMA</span>
                        </div>
                    </div>
                </div>

                <!-- IN TRANSIT ITEMS (HP) -->
                <div v-if="selectedTransfer.items && selectedTransfer.items.some(i => i.status === 'in_transit')"
                    class="mt-4">
                    <h3 class="font-bold text-amber-500 mb-3 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <Clock :size="16" /> Menunggu Konfirmasi (HP)
                    </h3>
                    <div class="space-y-2">
                        <div v-for="item in selectedTransfer.items.filter(i => i.status === 'in_transit')"
                            :key="item.id"
                            class="flex items-center justify-between p-3 rounded-xl border border-amber-500/30 bg-amber-500/10">
                            <div>
                                <p class="font-bold text-sm text-text-primary">{{ item.product?.name }}</p>
                                <p class="text-xs font-mono text-text-secondary">{{ item.imei }}</p>
                            </div>
                            <span class="text-xs font-bold text-amber-500">PENDING</span>
                        </div>
                    </div>
                </div>

                <!-- REJECTED ITEMS (HP) -->
                <div v-if="selectedTransfer.items && selectedTransfer.items.some(i => i.pivot?.status === 'rejected')"
                    class="mt-4">
                    <h3 class="font-bold text-red-500 mb-3 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <AlertTriangle :size="16" /> Barang Ditolak
                    </h3>
                    <div class="space-y-2">
                        <div v-for="item in selectedTransfer.items.filter(i => i.pivot?.status === 'rejected')"
                            :key="item.id"
                            class="flex items-center justify-between p-3 rounded-xl border border-red-500/30 bg-red-500/10">
                            <div>
                                <p class="font-bold text-sm text-text-primary">{{ item.product?.name }}</p>
                                <p class="text-xs font-mono text-text-secondary">{{ item.imei }}</p>
                            </div>
                            <span class="text-xs font-bold text-red-500">DITOLAK</span>
                        </div>
                    </div>
                </div>

                <!-- Non-HP Items -->
                <div v-if="selectedTransfer.non_hp_items && selectedTransfer.non_hp_items.length > 0">
                    <h3
                        class="font-bold text-text-primary mb-3 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <Package :size="16" class="text-orange-500" /> Barang Non-HP
                    </h3>
                    <div class="space-y-2">
                        <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                            class="flex items-center justify-between p-3 rounded-xl border border-surface-700 bg-surface-800/50">
                            <div>
                                <p class="font-bold text-sm text-text-primary">{{ item.product_name ||
                                    item.product?.name }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-bold text-text-primary">{{ item.quantity }} Unit</span>
                                <p class="text-xs font-bold text-amber-500"
                                    v-if="selectedTransfer.status === 'pending' || selectedTransfer.status === 'in_transit'">
                                    PENDING</p>
                                <p class="text-xs font-bold text-green-500" v-else>DITERIMA</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
    @apply bg-surface-800 border border-surface-600 rounded-lg text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all;
}

.btn {
    @apply transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-primary;
}

.card {
    @apply bg-surface-800 rounded-xl p-5 border border-surface-700;
}
</style>
