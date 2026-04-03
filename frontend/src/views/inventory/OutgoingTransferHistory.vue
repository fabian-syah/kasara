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

function getBrandName(item) {
    const product = item?.product;
    if (!product) return '';
    const brand = product.brand_relation || product.brandRelation || product.brand;
    if (brand && typeof brand === 'object') {
        return brand.name || '';
    }
    return brand || '';
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
    <div class="space-y-8 animate-in fade-in max-w-7xl mx-auto pb-24 px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-2 border-b border-surface-700/50">
            <div class="flex items-start gap-5">
                <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gradient-to-br from-blue-500/20 to-blue-600/10 rounded-3xl flex items-center justify-center border border-blue-500/20 shadow-xl shadow-blue-500/5 shrink-0">
                    <FileText :size="32" class="text-blue-500" />
                </div>
                <div class="pt-1">
                    <h1 class="text-3xl lg:text-5xl font-black text-white tracking-tight leading-tight">
                        Riwayat <span class="text-blue-500">Transfer</span> Keluar
                    </h1>
                    <p class="text-text-secondary text-sm lg:text-base mt-2 max-w-xl">
                        Monitor dan kelola seluruh riwayat pengiriman barang antar cabang dengan detail lengkap dan status real-time.
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 w-full lg:w-auto">
                <div class="relative group flex-1 sm:w-80">
                    <Search :size="20" class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-blue-500 transition-colors" />
                    <input v-model="searchQuery" type="text" placeholder="Cari No. Resi atau Cabang..."
                        class="w-full bg-surface-800 border border-surface-600 rounded-2xl pl-12 pr-4 py-3.5 text-base text-white focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all placeholder:text-text-secondary/50 shadow-inner" />
                </div>
                <button @click="fetchHistory(currentPage)" :disabled="isLoading"
                    class="btn btn-secondary gap-3 rounded-2xl h-[54px] px-6 text-base font-bold border border-surface-600 hover:border-blue-500/50 hover:bg-surface-750 transition-all shadow-lg active:scale-95 shrink-0">
                    <RefreshCw :size="20" :class="{ 'animate-spin': isLoading }" />
                    <span class="sm:hidden lg:block">{{ isLoading ? 'Memuat...' : 'Refresh' }}</span>
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

        <!-- List Section -->
        <div v-else class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                <div v-for="transfer in transfers.data" :key="transfer.id"
                    class="card hover:bg-surface-750 transition-all cursor-pointer group relative overflow-hidden border-surface-700/50 hover:border-blue-500/30 p-0 shadow-xl hover:shadow-blue-500/5 rounded-[2rem]"
                    @click="openDetail(transfer)">
                    
                    <div class="p-6 lg:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-5">
                                <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-surface-700/50 text-blue-500 group-hover:scale-110 transition-transform border border-surface-600/30">
                                    <Building2 :size="32" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <p class="font-black text-xl lg:text-2xl text-white group-hover:text-blue-400 transition-colors">
                                            {{ transfer.receipt_id }}
                                        </p>
                                        <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-[0.2em] shadow-sm border"
                                            :class="transfer.status === 'confirmed' || transfer.status === 'received' ? 'bg-green-500/10 text-green-500 border-green-500/20' : 'bg-amber-500/10 text-amber-500 border-amber-500/20'">
                                            {{ transfer.status }}
                                        </span>
                                    </div>
                                    <p class="text-base text-text-secondary font-medium">
                                        Tujuan: <span class="text-white font-bold">
                                            {{ transfer.destination?.name || transfer.destination_branch?.name || 'Unknown' }}
                                        </span>
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
                                    <Calendar :size="16" class="text-blue-500 opacity-70" />
                                    {{ formatDate(transfer.created_at) }}
                                </p>
                            </div>
                            <div class="text-right space-y-1">
                                <p class="text-xl lg:text-2xl font-black text-green-400" v-if="transfer.selling_price > 0">
                                    Rp {{ Number(transfer.selling_price || 0).toLocaleString('id-ID') }}
                                </p>
                                <p class="text-sm font-black text-white/70 uppercase tracking-tighter">
                                    {{ (transfer.items?.length || 0) + (transfer.non_hp_items?.reduce((acc, i) => acc + i.quantity, 0) || 0) }} Unit Barang
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hover Effect Bar -->
                    <div class="h-1.5 w-full bg-surface-700 group-hover:bg-blue-500 transition-colors opacity-30"></div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-10 border-t border-surface-700/50">
                <p class="text-base text-text-secondary font-medium order-2 sm:order-1">
                    Menampilkan halaman <span class="text-white font-black">{{ transfers.current_page }}</span> dari <span class="text-white font-black">{{ transfers.last_page }}</span>
                </p>
                <div class="flex gap-4 order-1 sm:order-2 w-full sm:w-auto">
                    <button @click="fetchHistory(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                        class="flex-1 sm:flex-none p-4 rounded-2xl bg-surface-800 border border-surface-700 hover:border-blue-500/50 hover:bg-surface-700 disabled:opacity-30 text-text-secondary transition-all active:scale-95">
                        <ChevronLeft :size="24" />
                    </button>
                    <button @click="fetchHistory(currentPage + 1)"
                        :disabled="currentPage === transfers.last_page || isLoading"
                        class="flex-1 sm:flex-none p-4 rounded-2xl bg-surface-800 border border-surface-700 hover:border-blue-500/50 hover:bg-surface-700 disabled:opacity-30 text-text-secondary transition-all active:scale-95">
                        <ChevronRight :size="24" />
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDetailModal && selectedTransfer"
            class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-2 sm:p-4 backdrop-blur-md"
            @click.self="closeDetail">
            <div
                class="bg-surface-800 rounded-[2.5rem] w-full max-w-4xl max-h-[95vh] flex flex-col border border-surface-700 shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] animate-in zoom-in-95 duration-300 overflow-hidden">
                <!-- Modal Header -->
                <div
                    class="px-8 py-8 border-b border-surface-700 flex justify-between items-start bg-surface-800/80 backdrop-blur-xl z-20">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2.5 bg-blue-500/10 rounded-2xl border border-blue-500/20">
                                <FileText :size="20" class="text-blue-500" />
                            </div>
                            <h2 class="text-3xl font-black text-white tracking-tight">Detail Transfer</h2>
                        </div>
                        <div class="flex items-center gap-3 text-base text-text-secondary mt-1 ml-0.5">
                            <span class="font-bold text-white">{{ selectedTransfer.receipt_id }}</span>
                            <span class="opacity-30">•</span>
                            <span class="capitalize font-black tracking-widest text-xs px-2.5 py-1 rounded-lg border"
                                :class="selectedTransfer.status === 'confirmed' || selectedTransfer.status === 'received' ? 'text-green-500 border-green-500/20 bg-green-500/5' : 'text-amber-500 border-amber-500/20 bg-amber-500/5'">
                                {{ selectedTransfer.status }}
                            </span>
                        </div>
                    </div>
                    <button @click="closeDetail" class="p-3 bg-surface-700 hover:bg-surface-600 rounded-2xl text-text-secondary hover:text-white transition-all shadow-lg active:scale-90">
                        <X :size="24" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 sm:p-12 overflow-y-auto flex-1 space-y-12 custom-scrollbar bg-gradient-to-b from-surface-800 via-surface-800 to-surface-900/40">
                    <!-- High Level Info Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-blue-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Pengirim / Kurir</p>
                            <p class="text-xl font-bold text-white flex items-center gap-3">
                                <div class="p-2 bg-blue-500/10 rounded-lg"><User :size="20" class="text-blue-500" /></div>
                                {{ selectedTransfer.inventoryUser?.full_name || selectedTransfer.inventoryUser?.name || selectedTransfer.user?.name || 'Unknown' }}
                            </p>
                        </div>
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-blue-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Dikirim Tanggal</p>
                            <p class="text-xl font-bold text-white flex items-center gap-3">
                                <div class="p-2 bg-blue-500/10 rounded-lg"><Calendar :size="20" class="text-blue-500" /></div>
                                {{ formatDate(selectedTransfer.created_at) }}
                            </p>
                        </div>
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-blue-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Lokasi Tujuan</p>
                            <p class="text-xl font-bold text-white flex items-center gap-3">
                                <div class="p-2 bg-blue-500/10 rounded-lg"><Store :size="20" class="text-blue-500" /></div>
                                {{ selectedTransfer.destination?.name || selectedTransfer.destination_branch?.name || 'Unknown' }}
                            </p>
                        </div>
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-blue-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Total Muatan</p>
                            <p class="text-xl font-bold text-white flex items-center gap-3">
                                <div class="p-2 bg-blue-500/10 rounded-lg"><Package :size="20" class="text-blue-500" /></div>
                                {{ (selectedTransfer.items?.length || 0) + (selectedTransfer.non_hp_items?.reduce((acc, i) => acc + i.quantity, 0) || 0) }} Unit
                            </p>
                        </div>
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-green-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Estimasi Nilai Jual</p>
                            <p class="text-2xl font-black text-green-400 flex items-center gap-3">
                                <div class="p-2 bg-green-500/10 rounded-lg"><ShoppingCart :size="20" class="text-green-500" /></div>
                                Rp {{ Number(selectedTransfer.selling_price || 0).toLocaleString('id-ID') }}
                            </p>
                        </div>
                        <div class="space-y-2 bg-surface-750/30 p-6 sm:p-7 rounded-[2rem] border border-surface-700/50 shadow-sm shadow-black/20 hover:border-blue-500/20 transition-colors group">
                            <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.3em] opacity-50 group-hover:opacity-100 transition-opacity">Status Konfirmasi</p>
                            <p class="text-xl font-bold flex items-center gap-3" :class="selectedTransfer.confirmed_by ? 'text-white' : 'text-amber-500'">
                                <div class="p-2 bg-surface-700 rounded-lg"><CheckCircle2 :size="20" /></div>
                                {{ selectedTransfer.confirmed_by?.full_name || selectedTransfer.confirmed_by?.name || selectedTransfer.confirmed_by?.username || 'Belum Konfirmasi' }}
                            </p>
                        </div>
                        
                        <!-- Notes Section -->
                        <div class="sm:col-span-2 lg:col-span-3 space-y-3 bg-surface-750/20 p-8 rounded-[2.5rem] border border-surface-700/40 backdrop-blur-sm shadow-inner" v-if="selectedTransfer.transfer_notes || selectedTransfer.notes">
                            <div class="flex items-center gap-3">
                                <AlertTriangle :size="20" class="text-amber-500/50" />
                                <p class="text-[10px] text-text-secondary font-black uppercase tracking-[0.4em] opacity-60">Catatan Khusus Pengiriman</p>
                            </div>
                            <p class="text-lg font-medium text-white/90 italic whitespace-pre-wrap leading-relaxed pl-8">
                                "{{ selectedTransfer.transfer_notes || selectedTransfer.notes }}"
                            </p>
                        </div>
                    </div>

                    <!-- Items Detail Sections -->
                    <div class="space-y-12">
                        <!-- HP Items (Accepted) -->
                        <div v-if="selectedTransfer.items && selectedTransfer.items.some(i => i.status !== 'in_transit' && i.pivot?.status !== 'rejected')">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-black text-white text-xl flex items-center gap-4 uppercase tracking-[0.1em]">
                                    <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center border border-green-500/20 shadow-lg shadow-green-500/5">
                                        <Smartphone :size="20" class="text-green-500" />
                                    </div>
                                    Unit HP <span class="text-green-500/50">(Diterima)</span>
                                </h3>
                                <span class="px-4 py-1.5 rounded-full bg-green-500/10 text-green-500 text-[10px] font-black tracking-[0.2em] border border-green-500/20">SUCCESS</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.items.filter(i => i.status !== 'in_transit' && i.pivot?.status !== 'rejected')"
                                    :key="item.id"
                                    class="flex items-center justify-between p-5 rounded-[1.5rem] border border-surface-700 bg-surface-800/80 shadow-md hover:border-blue-500/30 transition-all group">
                                    <div class="space-y-1.5 text-left">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-black text-lg text-white group-hover:text-blue-400 transition-colors">
                                                <span v-if="getBrandName(item)" class="text-blue-500 opacity-80 mr-1">[{{ getBrandName(item) }}]</span>
                                                {{ item.product?.name }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span v-if="item.ram || item.storage" class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-400 text-[10px] font-bold border border-blue-500/20">
                                                {{ item.ram }}/{{ item.storage }}
                                            </span>
                                            <span v-if="item.condition" class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-500 text-[10px] font-black border border-amber-500/20 uppercase">
                                                {{ item.condition }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-mono font-bold text-text-secondary tracking-widest pt-1">{{ item.imei }}</p>
                                    </div>
                                    <CheckCircle2 :size="24" class="text-green-500 shrink-0 opacity-40" />
                                </div>
                            </div>
                        </div>

                        <!-- HP Items (Pending/In Transit) -->
                        <div v-if="selectedTransfer.items && selectedTransfer.items.some(i => i.status === 'in_transit')">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-black text-white text-xl flex items-center gap-4 uppercase tracking-[0.1em]">
                                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center border border-amber-500/20">
                                        <Clock :size="20" class="text-amber-500" />
                                    </div>
                                    Unit HP <span class="text-amber-500/50">(Pending)</span>
                                </h3>
                                <span class="px-4 py-1.5 rounded-full bg-amber-500/10 text-amber-500 text-[10px] font-black tracking-[0.2em] border border-amber-500/20">IN TRANSIT</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.items.filter(i => i.status === 'in_transit')"
                                    :key="item.id"
                                    class="flex items-center justify-between p-5 rounded-[1.5rem] border border-amber-500/20 bg-amber-500/5 shadow-md">
                                    <div class="space-y-1.5 text-left">
                                        <p class="font-black text-lg text-white">
                                            <span v-if="getBrandName(item)" class="text-amber-500 opacity-80 mr-1">[{{ getBrandName(item) }}]</span>
                                            {{ item.product?.name }}
                                        </p>
                                        <div class="flex items-center gap-3">
                                            <span v-if="item.ram || item.storage" class="px-2 py-0.5 rounded-md bg-surface-900/60 text-blue-400 text-[10px] font-bold border border-surface-700">
                                                {{ item.ram }}/{{ item.storage }}
                                            </span>
                                            <span v-if="item.condition" class="px-2 py-0.5 rounded-md bg-surface-900/60 text-amber-500 text-[10px] font-black border border-surface-700 uppercase">
                                                {{ item.condition }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-mono font-bold text-text-secondary tracking-widest pt-1 opacity-60">{{ item.imei }}</p>
                                    </div>
                                    <div class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></div>
                                </div>
                            </div>
                        </div>

                        <!-- HP Items (Rejected) -->
                        <div v-if="selectedTransfer.items && selectedTransfer.items.some(i => i.pivot?.status === 'rejected')">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-black text-white text-xl flex items-center gap-4 uppercase tracking-[0.1em]">
                                    <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center border border-red-500/20">
                                        <AlertTriangle :size="20" class="text-red-500" />
                                    </div>
                                    Unit HP <span class="text-red-500/50">(Ditolak)</span>
                                </h3>
                                <span class="px-4 py-1.5 rounded-full bg-red-500/10 text-red-500 text-[10px] font-black tracking-[0.2em] border border-red-500/20">REJECTED</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.items.filter(i => i.pivot?.status === 'rejected')"
                                    :key="item.id"
                                    class="flex items-center justify-between p-5 rounded-[1.5rem] border border-red-500/20 bg-red-500/5">
                                    <div class="space-y-1.5 text-left">
                                        <p class="font-black text-lg text-white/70">
                                            <span v-if="getBrandName(item)" class="text-red-500 opacity-60 mr-1">[{{ getBrandName(item) }}]</span>
                                            {{ item.product?.name }}
                                        </p>
                                        <p class="text-xs font-mono font-bold text-text-secondary tracking-widest">{{ item.imei }}</p>
                                    </div>
                                    <X :size="24" class="text-red-500" />
                                </div>
                            </div>
                        </div>

                        <!-- Non-HP Items Section -->
                        <div v-if="selectedTransfer.non_hp_items && selectedTransfer.non_hp_items.length > 0">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-black text-white text-xl flex items-center gap-4 uppercase tracking-[0.1em]">
                                    <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center border border-orange-500/20">
                                        <Package :size="20" class="text-orange-500" />
                                    </div>
                                    Barang Aksesoris <span class="text-orange-500/50">& Non-HP</span>
                                </h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                                    class="flex items-center justify-between p-6 rounded-[2rem] border border-surface-700 bg-surface-800 shadow-lg hover:border-orange-500/30 transition-all">
                                    <div class="text-left">
                                        <p class="font-black text-lg text-white">
                                            <span v-if="getBrandName(item)" class="text-orange-500 opacity-80 mr-1">[{{ getBrandName(item) }}]</span>
                                            {{ item.product_name || item.product?.name }}
                                        </p>
                                    </div>
                                    <div class="text-right flex flex-col items-end gap-1">
                                        <span class="text-xl font-black text-white px-4 py-1 bg-surface-700 rounded-xl border border-surface-600 shadow-inner">
                                            {{ item.quantity }} <span class="text-xs font-bold opacity-40">UNIT</span>
                                        </span>
                                        <p class="text-[10px] font-black tracking-widest uppercase"
                                            :class="selectedTransfer.status === 'pending' || selectedTransfer.status === 'in_transit' ? 'text-amber-500' : 'text-green-500'">
                                            {{ selectedTransfer.status === 'pending' || selectedTransfer.status === 'in_transit' ? 'PENDING' : 'RECEIVED' }}
                                        </p>
                                    </div>
                                </div>
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
