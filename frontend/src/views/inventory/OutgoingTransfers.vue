<script setup>
import { ref, onMounted } from "vue";
import api from "../../api/axios";
import {
    Package,
    Loader2,
    ArrowUpRight,
    Calendar,
    User,
    Smartphone,
    Clock,
    Building2,
    RefreshCw,
    X,
    ShoppingCart,
    Truck,
    Warehouse,
    Store
} from "lucide-vue-next";

// State
const isLoading = ref(true);
const transfers = ref([]);

// Fetch outgoing transfers
async function fetchOutgoing() {
    isLoading.value = true;
    try {
        const response = await api.get('/transfers/outgoing');
        transfers.value = response.data.data || response.data || [];
    } catch (e) {
        console.error("Failed to fetch outgoing transfers", e);
    } finally {
        isLoading.value = false;
    }
}

// Modal State (for detail view only)
const showModal = ref(false);
const selectedTransfer = ref(null);

function openDetailModal(transfer) {
    selectedTransfer.value = transfer;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    selectedTransfer.value = null;
}

// Helpers
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

function formatCondition(condition) {
    if (!condition) return '';
    const map = {
        'new': 'Baru',
        'second': 'Second',
        'ex_ibox': 'Ex iBox',
        'refurbished': 'Refurbished'
    };
    return map[condition.toLowerCase()] || condition;
}

function getBrandName(item) {
    const product = item?.product;
    if (!product) return '';
    const brand = product.brand_relation || product.brandRelation || product.brand;
    if (brand && typeof brand === 'object') {
        return brand.name || '';
    }
    return brand || '';
}

function formatCapacity(ram, storage) {
    if (!ram && !storage) return '';
    if (ram && storage) {
        const r = /^\d+$/.test(ram) ? ram : ram.replace(/GB/gi, '');
        const s = /^\d+$/.test(storage) ? storage : storage.replace(/GB/gi, '');
        return `${r}/${s}GB`;
    }
    const val = storage || ram;
    if (/^\d+$/.test(val)) return val + 'GB';
    return val;
}

onMounted(() => {
    fetchOutgoing();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-6xl mx-auto pb-24">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-3">
                    <div class="w-12 h-12 bg-purple-500/20 rounded-2xl flex items-center justify-center">
                        <ArrowUpRight :size="24" class="text-purple-500" />
                    </div>
                    Menunggu Konfirmasi (OTW)
                </h1>
                <p class="text-text-secondary mt-1">
                    Daftar barang yang dikirim keluar dan belum dikonfirmasi oleh penerima
                </p>
            </div>
            <button @click="fetchOutgoing" :disabled="isLoading" class="btn btn-secondary gap-2 rounded-xl h-10 px-4">
                <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
                Refresh
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-20 text-text-secondary">
            <Loader2 :size="40" class="animate-spin mx-auto mb-4" />
            <p>Memuat data transfer keluar...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="transfers.length === 0" class="text-center py-20">
            <div class="w-24 h-24 mx-auto bg-surface-700/50 rounded-3xl flex items-center justify-center mb-6">
                <Clock :size="48" class="text-text-secondary" />
            </div>
            <h2 class="text-xl font-bold text-text-primary mb-2">Tidak Ada Transfer Menunggu</h2>
            <p class="text-text-secondary">Semua barang yang dikirim sudah diterima oleh tujuan.</p>
        </div>

        <!-- Transfer List -->
        <div v-else class="space-y-4">
            <p class="text-text-secondary text-sm">
                <Clock :size="14" class="inline mr-1" />
                {{ transfers.length }} transfer belum dikonfirmasi oleh penerima
            </p>

            <div v-for="transfer in transfers" :key="transfer.id"
                class="card border-l-4 border-l-purple-500 hover:bg-surface-700/30 transition-all cursor-pointer group"
                @click="openDetailModal(transfer)">
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-purple-500/20 text-purple-500">
                            <Building2 :size="24" />
                        </div>
                        <div>
                            <p class="font-bold text-text-primary text-lg group-hover:text-purple-400 transition-colors">
                                {{ transfer.receipt_id }}
                            </p>
                            <p class="text-sm text-text-secondary flex items-center gap-1">
                                <User :size="12" />
                                Tujuan: <span class="text-text-primary font-medium">{{ transfer.destination?.name || transfer.receiver_name || 'Unknown' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-2 text-text-secondary text-sm justify-end">
                            <Calendar :size="14" />
                            {{ formatDate(transfer.created_at) }}
                        </div>
                        <span class="inline-block mt-2 px-3 py-1 rounded-full bg-purple-500/20 text-purple-500 text-xs font-bold uppercase tracking-wider">
                            Sedang Dikirim
                        </span>
                    </div>
                </div>

                <!-- Items Preview -->
                <div class="sm:pl-[64px] mt-2 sm:mt-0">
                    <div class="flex gap-8">
                        <div v-if="transfer.items && transfer.items.length > 0">
                            <p class="text-[10px] uppercase font-black text-text-secondary mb-2 tracking-widest">Barang HP ({{ transfer.items.length }})</p>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="item in transfer.items.slice(0, 5)" :key="item.id" 
                                     class="flex items-center gap-2 bg-surface-700/50 border border-surface-600 px-2 py-1 rounded-lg">
                                    <Smartphone :size="10" class="text-purple-400" />
                                    <span v-if="getBrandName(item)" class="text-[10px] text-purple-400 mr-1">[{{ getBrandName(item) }}]</span>
                                    <span class="text-[11px] text-text-primary font-mono">{{ item.imei.slice(-4) }}</span>
                                </div>
                                <span v-if="transfer.items.length > 5" class="text-[11px] text-text-secondary self-center font-bold px-2">
                                    +{{ transfer.items.length - 5 }} lainnya
                                </span>
                            </div>
                        </div>
                        <div v-if="transfer.non_hp_items && transfer.non_hp_items.length > 0">
                            <p class="text-[10px] uppercase font-black text-text-secondary mb-2 tracking-widest">Aksesoris/Non-HP ({{ transfer.non_hp_items.length }})</p>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="item in transfer.non_hp_items.slice(0, 3)" :key="item.id"
                                     class="flex items-center gap-2 bg-surface-700/50 border border-surface-600 px-2 py-1 rounded-lg">
                                    <Package :size="10" class="text-orange-400" />
                                    <span v-if="getBrandName(item)" class="text-[10px] text-orange-400 mr-1">[{{ getBrandName(item) }}]</span>
                                    <span class="text-[11px] text-text-primary">{{ item.product_name || item.product?.name }}</span>
                                    <span class="text-[10px] bg-orange-500/20 text-orange-400 px-1 rounded">{{ item.quantity }}x</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal (Read Only) -->
        <div v-if="showModal && selectedTransfer"
            class="fixed inset-0 bg-black/60 dark:bg-black/90 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 transition-all duration-300">
            <div
                class="bg-white dark:bg-surface-800 w-full max-w-2xl h-[95vh] sm:h-auto sm:max-h-[85vh] flex flex-col border-t sm:border border-surface-200 dark:border-surface-700 rounded-t-3xl sm:rounded-2xl shadow-2xl animate-in slide-in-from-bottom sm:slide-in-from-bottom-0 sm:zoom-in duration-300 overflow-hidden">
                
                <!-- Modal Header -->
                <div class="p-5 sm:p-6 border-b border-surface-200 dark:border-surface-700 flex justify-between items-center bg-white dark:bg-surface-800 z-20 sticky top-0">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-500 text-[10px] font-bold uppercase">OTW</span>
                            <span class="text-text-secondary text-sm font-medium">{{ selectedTransfer.receipt_id }}</span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-bold text-text-primary">Detail Pengiriman</h2>
                    </div>
                    <button @click="closeModal"
                        class="w-10 h-10 rounded-full flex items-center justify-center bg-surface-100 dark:bg-white/10 text-text-secondary hover:text-text-primary transition-all font-bold">
                        <X :size="24" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto flex-1 space-y-8 bg-surface-900/10">
                    
                    <!-- Info Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-surface-800/50 p-4 rounded-2xl border border-surface-700">
                            <p class="text-[10px] uppercase font-black text-text-secondary mb-3 tracking-widest">Informasi Pengiriman</p>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-500">
                                        <User :size="16" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Pengirim</p>
                                        <p class="text-sm font-bold text-text-primary">{{ selectedTransfer.user?.name || 'Unknown' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-500">
                                        <Building2 :size="16" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Tujuan</p>
                                        <p class="text-sm font-bold text-text-primary">{{ selectedTransfer.destination?.name || selectedTransfer.receiver_name || 'Unknown' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-surface-800/50 p-4 rounded-2xl border border-surface-700">
                            <p class="text-[10px] uppercase font-black text-text-secondary mb-3 tracking-widest">Status & Waktu</p>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center text-green-500">
                                        <Calendar :size="16" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Waktu Kirim</p>
                                        <p class="text-sm font-bold text-text-primary">{{ formatDate(selectedTransfer.created_at) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-500">
                                        <Clock :size="16" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Status</p>
                                        <p class="text-sm font-bold text-amber-500 uppercase tracking-tighter">BELUM DIKONFIRMASI</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4">
                        <!-- HP Items -->
                        <div v-if="selectedTransfer.items && selectedTransfer.items.length > 0">
                            <h3 class="font-bold text-text-primary mb-3 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <Smartphone :size="16" class="text-purple-500" /> Daftar HP
                            </h3>
                            <div class="grid grid-cols-1 gap-2">
                                <div v-for="item in selectedTransfer.items" :key="item.id"
                                    class="flex items-center justify-between p-3 rounded-xl border border-surface-700 bg-surface-800/30">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded flex items-center justify-center bg-surface-700 text-text-secondary">
                                            <Smartphone :size="14" />
                                        </div>
                                        <div>
                                            <p class="font-bold text-xs text-text-primary uppercase flex items-center flex-wrap gap-1">
                                                <span v-if="getBrandName(item)" class="text-purple-400">[{{ getBrandName(item) }}]</span>
                                                <span>{{ item.product?.name }}</span>
                                                <span v-if="item.storage || item.ram" class="text-purple-400 ml-1">
                                                    {{ formatCapacity(item.ram, item.storage) }}
                                                </span>
                                            </p>
                                            <p class="text-[10px] font-mono text-text-secondary mt-0.5 tracking-tighter">{{ item.imei }}</p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] px-2 py-0.5 bg-surface-700 rounded text-text-secondary uppercase">
                                        {{ formatCondition(item.condition) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Non-HP Items -->
                        <div v-if="selectedTransfer.non_hp_items && selectedTransfer.non_hp_items.length > 0">
                            <h3 class="font-bold text-text-primary mb-3 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <Package :size="16" class="text-orange-500" /> Daftar Aksesoris/Lainnya
                            </h3>
                            <div class="grid grid-cols-1 gap-2">
                                <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                                    class="bg-surface-800/30 p-3 rounded-xl border border-surface-700 flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded flex items-center justify-center bg-surface-700 text-orange-400">
                                            <Package :size="14" />
                                        </div>
                                        <p class="font-bold text-xs text-text-primary">
                                            <span v-if="getBrandName(item)" class="text-orange-400 mr-1">[{{ getBrandName(item) }}]</span>
                                            {{ item.product_name || item.product?.name }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-bold text-orange-400">{{ item.quantity }} Unit</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Notes -->
                    <div v-if="selectedTransfer.transfer_notes" class="bg-amber-500/5 border border-amber-500/20 p-4 rounded-2xl">
                        <p class="text-[10px] uppercase font-black text-amber-500 mb-2 tracking-widest">Catatan Pengiriman</p>
                        <p class="text-sm text-text-primary italic">"{{ selectedTransfer.transfer_notes }}"</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-5 border-t border-surface-700 bg-surface-800 text-center">
                    <p class="text-xs text-text-secondary mb-4 italic flex items-center justify-center gap-2">
                        <Clock :size="12" />
                        Silakan hubungi cabang tujuan untuk mempercepat konfirmasi.
                    </p>
                    <button @click="closeModal"
                        class="w-full h-11 bg-surface-700 hover:bg-surface-600 text-text-primary font-bold rounded-xl transition-all">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.btn {
    @apply transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center font-bold;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-primary border border-surface-600;
}

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700 shadow-sm;
}
</style>
