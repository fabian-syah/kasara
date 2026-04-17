<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
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
    Search,
    ChevronLeft,
    ChevronRight,
    Store
} from "lucide-vue-next";

const toast = useToast();

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
        toast.error("Gagal memuat data OTW");
    } finally {
        isLoading.value = false;
    }
}

// Detail Modal State
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
    <div class="space-y-8 animate-in fade-in max-w-7xl mx-auto pb-24 px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-2 border-b border-surface-700/50">
            <div class="flex items-start gap-5">
                <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gradient-to-br from-purple-500/20 to-purple-600/10 rounded-3xl flex items-center justify-center border border-purple-500/20 shadow-xl shadow-purple-500/5 shrink-0">
                    <ArrowUpRight :size="32" class="text-purple-500" />
                </div>
                <div class="pt-1">
                    <h1 class="text-3xl lg:text-5xl font-black text-white tracking-tight leading-tight">
                        Menunggu <span class="text-purple-500">Konfirmasi</span> (OTW)
                    </h1>
                    <p class="text-text-secondary text-sm lg:text-base mt-2 max-w-xl">
                        Daftar barang yang telah Anda kirim dan saat ini sedang menunggu verifikasi oleh pihak penerima.
                    </p>
                </div>
            </div>

            <button @click="fetchOutgoing" :disabled="isLoading"
                class="btn btn-secondary gap-3 rounded-2xl h-[54px] px-6 text-base font-bold border border-surface-600 hover:border-purple-500/50 hover:bg-surface-750 transition-all shadow-lg active:scale-95 shrink-0 self-start lg:self-end">
                <RefreshCw :size="20" :class="{ 'animate-spin': isLoading }" />
                <span>{{ isLoading ? 'Memuat...' : 'Refresh Data' }}</span>
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-20 text-text-secondary">
            <Loader2 :size="40" class="animate-spin mx-auto mb-4" />
            <p>Memuat transfer keluar...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="transfers.length === 0" class="text-center py-20 bg-surface-800 rounded-3xl border border-surface-700">
            <div class="w-24 h-24 mx-auto bg-purple-500/10 rounded-full flex items-center justify-center mb-6">
                <Clock :size="48" class="text-purple-500/50" />
            </div>
            <h2 class="text-2xl font-black text-text-primary mb-2">Semua Sudah Diterima</h2>
            <p class="text-text-secondary max-w-xs mx-auto">Tidak ada pengiriman OTW yang tertunda untuk saat ini.</p>
        </div>

        <!-- Transfer Grid -->
        <div v-else class="space-y-6">
            <div class="flex items-center gap-3 px-2">
                <Clock :size="18" class="text-purple-500" />
                <p class="text-text-secondary font-bold text-sm uppercase tracking-widest">
                    {{ transfers.length }} Pengiriman Sedang Berlangsung
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                <div v-for="transfer in transfers" :key="transfer.id"
                    class="card hover:bg-surface-750 transition-all cursor-pointer group relative overflow-hidden border-l-4 border-l-purple-500 p-0 shadow-xl hover:shadow-purple-500/5 rounded-[2rem]"
                    @click="openDetailModal(transfer)">
                    
                    <div class="p-6 lg:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-5">
                                <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-surface-700/50 text-purple-500 group-hover:scale-110 transition-transform border border-surface-600/30">
                                    <Building2 :size="32" />
                                </div>
                                <div>
                                    <p class="font-black text-xl lg:text-2xl text-white group-hover:text-purple-400 transition-colors mb-1">
                                        {{ transfer.receipt_id }}
                                    </p>
                                    <p class="text-base text-text-secondary font-medium flex items-center gap-2">
                                        <Store :size="16" class="text-purple-500/70" />
                                        Tujuan: <span class="text-white font-bold">{{ transfer.destination?.name || transfer.receiver_name || 'Unknown' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 pt-6 border-t border-surface-700/50 flex items-center justify-between">
                            <div class="flex flex-col gap-1.5">
                                <p class="text-[10px] text-text-secondary uppercase font-black tracking-widest opacity-60">
                                    Tanggal Kirim
                                </p>
                                <p class="text-sm lg:text-base font-bold text-text-primary flex items-center gap-2">
                                    <Calendar :size="16" class="text-purple-500 opacity-70" />
                                    {{ formatDate(transfer.created_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xl lg:text-2xl font-black text-white">
                                    {{ (transfer.items?.length || 0) + (transfer.non_hp_items?.reduce((acc, i) => acc + i.quantity, 0) || 0) }}
                                    <span class="text-xs font-bold text-text-secondary uppercase ml-1">Unit</span>
                                </p>
                                <span class="px-3 py-1 rounded-lg bg-purple-500/10 text-purple-500 text-[10px] font-black uppercase tracking-[0.2em] shadow-sm border border-purple-500/20">
                                    SEDANG DIKIRIM
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal (Read Only) -->
        <div v-if="showModal && selectedTransfer"
            class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-2 sm:p-4 backdrop-blur-md"
            @click.self="closeModal">
            <div
                class="bg-surface-800 rounded-[2.5rem] w-full max-w-4xl max-h-[95vh] flex flex-col border border-surface-700 shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] animate-in zoom-in-95 duration-300 overflow-hidden">
                <!-- Modal Header -->
                <div
                    class="px-8 py-8 border-b border-surface-700 flex justify-between items-start bg-surface-800/80 backdrop-blur-xl z-20">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2.5 bg-purple-500/10 rounded-2xl border border-purple-500/20">
                                <ArrowUpRight :size="20" class="text-purple-500" />
                            </div>
                            <h2 class="text-3xl font-black text-white tracking-tight">Detail Pengiriman</h2>
                        </div>
                        <div class="flex items-center gap-3 text-base text-text-secondary mt-1 ml-0.5">
                            <span class="font-bold text-white">{{ selectedTransfer.receipt_id }}</span>
                            <span class="opacity-30">•</span>
                            <span class="capitalize font-black tracking-widest text-xs px-2.5 py-1 rounded-lg border text-purple-500 border-purple-500/20 bg-purple-500/5">
                                Menunggu Penerimaan
                            </span>
                        </div>
                    </div>
                    <button @click="closeModal" class="p-3 bg-surface-700 hover:bg-surface-600 rounded-2xl text-text-secondary hover:text-white transition-all shadow-lg active:scale-90">
                        <X :size="24" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 sm:p-12 overflow-y-auto flex-1 space-y-12 custom-scrollbar bg-gradient-to-b from-surface-800 via-surface-800 to-surface-900/40">
                    <!-- High Level Info Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-purple-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Dikirim Oleh</p>
                            <p class="text-xl font-bold text-white flex items-center gap-3">
                                <div class="p-2 bg-purple-500/10 rounded-lg"><User :size="20" class="text-purple-500" /></div>
                                {{ (selectedTransfer.inventory_user?.name || selectedTransfer.inventoryUser?.name) || selectedTransfer.user?.name || 'Unknown' }}
                            </p>
                        </div>
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-purple-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Waktu Kirim</p>
                            <p class="text-xl font-bold text-white flex items-center gap-3">
                                <div class="p-2 bg-purple-500/10 rounded-lg"><Calendar :size="20" class="text-purple-500" /></div>
                                {{ formatDate(selectedTransfer.created_at) }}
                            </p>
                        </div>
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-purple-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Cabang Tujuan</p>
                            <p class="text-xl font-bold text-white flex items-center gap-3">
                                <div class="p-2 bg-purple-500/10 rounded-lg"><Store :size="20" class="text-purple-500" /></div>
                                {{ selectedTransfer.destination?.name || selectedTransfer.receiver_name || 'Unknown' }}
                            </p>
                        </div>
                    </div>

                    <!-- Items Detail Sections -->
                    <div class="space-y-12">
                        <!-- HP Items -->
                        <div v-if="selectedTransfer.items && selectedTransfer.items.length > 0">
                            <h3 class="font-black text-white text-xl flex items-center gap-4 uppercase tracking-[0.1em] mb-8">
                                <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center border border-purple-500/20">
                                    <Smartphone :size="20" class="text-purple-500" />
                                </div>
                                Daftar Barang HP <span class="text-text-secondary opacity-40 ml-2">({{ selectedTransfer.items.length }})</span>
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.items" :key="item.id"
                                    class="p-6 rounded-[2rem] border border-surface-700 bg-surface-800/80 shadow-md hover:border-purple-500/30 transition-all group">
                                    <div class="space-y-2 text-left">
                                        <p class="font-black text-lg text-white group-hover:text-purple-400 transition-colors">
                                            <span v-if="getBrandName(item)" class="text-purple-500/70 mr-1">[{{ getBrandName(item) }}]</span>
                                            {{ item.product?.name }}
                                        </p>
                                        <p class="text-xs font-mono font-black text-text-secondary tracking-[0.2em] pt-1 opacity-70">
                                            {{ item.imei }}
                                        </p>
                                        <div class="flex items-center gap-2 pt-2">
                                            <span v-if="item.ram || item.storage" class="px-2 py-0.5 rounded-md bg-purple-500/10 text-purple-400 text-[10px] font-black border border-purple-500/10 uppercase">
                                                {{ item.ram }}/{{ item.storage }}
                                            </span>
                                            <span v-if="item.condition" class="px-2 py-0.5 rounded-md bg-surface-700 text-text-secondary text-[10px] font-black border border-surface-600 uppercase tracking-widest">
                                                {{ formatCondition(item.condition) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Non-HP Items Section -->
                        <div v-if="selectedTransfer.non_hp_items && selectedTransfer.non_hp_items.length > 0">
                            <h3 class="font-black text-white text-xl flex items-center gap-4 uppercase tracking-[0.1em] mb-8">
                                <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center border border-orange-500/20">
                                    <Package :size="20" class="text-orange-500" />
                                </div>
                                Barang Aksesoris <span class="text-text-secondary opacity-40 ml-2">({{ selectedTransfer.non_hp_items.length }})</span>
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                                    class="p-8 rounded-[2.5rem] border border-surface-700 bg-surface-800 shadow-xl hover:border-orange-500/30 transition-all group">
                                    <div class="flex justify-between items-center gap-4">
                                        <div class="flex items-center gap-5">
                                            <div class="w-14 h-14 bg-surface-700/50 rounded-2xl flex items-center justify-center border border-surface-600/30 text-orange-500">
                                                <Package :size="24" />
                                            </div>
                                            <div>
                                                <p class="font-black text-xl text-white leading-tight group-hover:text-orange-400 transition-colors">
                                                    <span v-if="getBrandName(item)" class="text-orange-500/70 mr-1">[{{ getBrandName(item) }}]</span>
                                                    {{ item.product_name || item.product?.name }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="px-5 py-2.5 bg-surface-900 rounded-2xl border border-surface-700 shadow-inner shrink-0">
                                            <p class="text-2xl font-black text-white">{{ item.quantity }} <span class="text-[10px] font-bold text-text-secondary uppercase block leading-none">Unit</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Notes -->
                    <div v-if="selectedTransfer.transfer_notes" class="bg-amber-500/5 border border-amber-500/20 p-8 rounded-[2.5rem] backdrop-blur-sm">
                        <div class="flex items-center gap-3 mb-4">
                            <Clock :size="20" class="text-amber-500/50" />
                            <p class="text-[10px] uppercase font-black text-amber-500 tracking-[0.4em]">Catatan Pengiriman</p>
                        </div>
                        <p class="text-lg font-medium text-white/90 italic leading-relaxed pl-8">"{{ selectedTransfer.transfer_notes }}"</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-8 py-8 border-t border-surface-700 bg-surface-800/90 backdrop-blur-xl z-20 text-center">
                    <p class="text-sm text-text-secondary/60 mb-6 font-medium italic flex items-center justify-center gap-3 bg-surface-900/50 py-3 rounded-full border border-surface-700/30">
                        <Clock :size="16" class="text-amber-500/60 transition-pulse animate-pulse" />
                        Silakan hubungi cabang tujuan untuk mempercepat konfirmasi penerimaan.
                    </p>
                    <button @click="closeModal"
                        class="w-full h-16 bg-surface-700 hover:bg-surface-600 text-white font-black text-lg rounded-2xl active:scale-[0.98] transition-all border border-surface-600 shadow-xl">
                        TUTUP DETAIL
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.btn {
    @apply transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-primary;
}

.card {
    @apply bg-surface-800 rounded-xl p-5 border border-surface-700;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    @apply bg-surface-700 rounded-full border-4 border-transparent bg-clip-padding;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    @apply bg-surface-600;
}
</style>
