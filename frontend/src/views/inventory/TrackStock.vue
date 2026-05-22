<script setup>
import { ref } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import {
    Search, Package, Loader2, Building2, AlertTriangle, RotateCcw,
    ShoppingBag, Calendar, User, Smartphone, ArrowUpRight, MapPin,
    DollarSign, Box, Printer, Eye, X, Gift, Hash
} from "lucide-vue-next";
import ReceiptModal from "../../components/modals/ReceiptModal.vue";

const toast = useToast();
const query = ref("");
const isLoading = ref(false);
const results = ref([]);
const hasSearched = ref(false);
const showReceiptModal = ref(false);
const currentReceiptData = ref(null);
const activeImage = ref(null);

const printableCategories = [
    'penjualan', 'penjualan_offline', 'penjualan_store', 'bundling',
    'tukar_unit', 'tukar_tambah', 'downgrade', 'retur', 'keluar',
    'pindah_cabang', 'kesalahan_input', 'brand_ambassador',
    'event_sponsorship', 'giveaway_customer', 'inventaris', 'hilang',
];

const canPrintReceipt = (result) => {
    return result.type === 'stock_out' && result.order_no && printableCategories.includes(result.category);
};

const aksiBuktiCategories = [
    'penjualan_store', 'refund', 'angkat_barang',
    'tukar_tambah', 'tukar_unit', 'downgrade',
];

const showAksiBukti = (result) => {
    if (result.type !== 'stock_out') return false;
    if (!aksiBuktiCategories.includes(result.category)) return false;
    const isNotaSearch = result.id && query.value && result.id.toLowerCase() === query.value.toLowerCase();
    if (!isNotaSearch) return false;
    return (result.proof_images && result.proof_images.length > 0) || canPrintReceipt(result);
};

const openReceipt = (result) => {
    currentReceiptData.value = { ...result, items: result.raw_items || result.items };
    showReceiptModal.value = true;
};

const categoryIcons = {
    pindah_cabang: Building2, kesalahan_input: AlertTriangle, retur: RotateCcw,
    shopee: ShoppingBag, orderan_online: ShoppingBag, penjualan_offline: ShoppingBag,
    penjualan: ShoppingBag, penjualan_store: ShoppingBag, bundling: ShoppingBag,
    tukar_unit: ShoppingBag, tukar_tambah: ShoppingBag, downgrade: ShoppingBag,
    angkat_barang: Box, brand_ambassador: User, event_sponsorship: Calendar,
    keluar: Box, giveaway_customer: Gift, inventaris: Box, hilang: AlertTriangle,
    refund: RotateCcw,
};

const categoryLabels = {
    pindah_cabang: 'Pindah Cabang', kesalahan_input: 'Kesalahan Input', retur: 'Retur',
    shopee: 'Shopee', orderan_online: 'Orderan Online', penjualan_offline: 'Penjualan Offline',
    penjualan: 'Penjualan', penjualan_store: 'Penjualan Store', bundling: 'Bundling',
    tukar_unit: 'Tukar Unit', tukar_tambah: 'Tukar Tambah', downgrade: 'Downgrade',
    angkat_barang: 'Angkat Barang', brand_ambassador: 'Brand Ambassador',
    event_sponsorship: 'Event / Sponsorship', keluar: 'Keluar',
    giveaway_customer: 'Giveaway Customer', inventaris: 'Inventaris', hilang: 'Hilang',
    refund: 'Refund',
};

async function search() {
    if (query.value.length < 3) { toast.error("Minimal 3 karakter"); return; }
    isLoading.value = true;
    hasSearched.value = true;
    try {
        const response = await api.get('/track', { params: { q: query.value } });
        const data = response.data.data || [];
        results.value = data.filter(r => !(r.type === 'stock_out' && r.category === 'barang_masuk'));
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mencari");
        results.value = [];
    } finally { isLoading.value = false; }
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function formatCurrency(value) {
    if (!value) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
}

function getCategoryColor(category) {
    if (category === 'pindah_cabang') return 'blue';
    if (['kesalahan_input', 'hilang'].includes(category)) return 'amber';
    if (['retur', 'keluar', 'giveaway_customer', 'inventaris'].includes(category)) return 'purple';
    if (category === 'brand_ambassador') return 'pink';
    if (category === 'event_sponsorship') return 'cyan';
    if (['shopee', 'orderan_online'].includes(category)) return 'orange';
    if (['angkat_barang', 'refund'].includes(category)) return 'indigo';
    return 'emerald';
}
</script>

<template>
    <div class="track-page">
        <!-- Header -->
        <div class="text-center pt-6 pb-6 sm:pt-8 sm:pb-8">
            <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto bg-primary-500/10 rounded-2xl flex items-center justify-center mb-3 border border-primary-500/20">
                <Search :size="24" class="text-primary-500 sm:w-7 sm:h-7" />
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-text-primary">Lacak & History IMEI</h1>
            <p class="text-text-secondary mt-1.5 text-xs sm:text-sm">Cek riwayat perpindahan dan status barang</p>
        </div>

        <!-- Search -->
        <div class="sticky top-0 z-30 bg-surface-900/95 backdrop-blur-md pb-4 -mx-4 px-4 sm:mx-0 sm:px-0 sm:static sm:bg-transparent sm:backdrop-blur-none pt-2 sm:pt-0">
            <form @submit.prevent="search" class="flex gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-surface-500" :size="16" />
                    <input v-model="query" type="text" placeholder="IMEI, Resi, atau Kode Nota..."
                        class="w-full bg-surface-800 border border-surface-700 rounded-xl pl-9 sm:pl-10 pr-3 h-11 sm:h-12 text-sm text-text-primary placeholder:text-surface-500 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all" />
                </div>
                <button type="submit" :disabled="isLoading || query.length < 3"
                    class="h-11 sm:h-12 px-4 sm:px-6 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-sm font-semibold disabled:opacity-30 disabled:cursor-not-allowed transition-all shrink-0 flex items-center gap-2">
                    <Loader2 v-if="isLoading" :size="16" class="animate-spin" />
                    <template v-else>
                        <Search :size="16" class="sm:hidden" />
                        <span class="hidden sm:inline">Cari</span>
                    </template>
                </button>
            </form>
        </div>

        <!-- Results -->
        <div v-if="hasSearched" class="mt-4 sm:mt-6">
            <!-- Loading -->
            <div v-if="isLoading" class="flex flex-col items-center py-16">
                <Loader2 :size="28" class="animate-spin text-primary-500 mb-3" />
                <p class="text-text-secondary text-sm">Mencari...</p>
            </div>

            <!-- Empty -->
            <div v-else-if="results.length === 0" class="flex flex-col items-center py-16">
                <Package :size="40" class="text-surface-600 mb-3" />
                <p class="text-text-primary font-medium text-sm">Tidak ditemukan</p>
                <p class="text-text-secondary text-xs mt-1">Coba kata kunci lain</p>
            </div>

            <!-- Results List -->
            <div v-else>
                <p class="text-xs text-text-secondary mb-3 sm:mb-4">
                    Ditemukan <span class="text-text-primary font-semibold">{{ results.length }}</span> hasil
                </p>

                <div class="space-y-2.5 sm:space-y-3">
                    <template v-for="(result, rIdx) in results" :key="result.type + '-' + result.id + '-' + rIdx">

                        <!-- ══ STOCK IN ══ -->
                        <div v-if="result.type === 'stock_in'" class="card-track overflow-hidden">
                            <!-- Top bar color -->
                            <div class="h-0.5"
                                :class="result.is_retur_rejection ? 'bg-red-500' : (result.is_return_transfer ? 'bg-amber-500' : (result.is_arrival ? 'bg-indigo-500' : 'bg-green-500'))"></div>
                            
                            <!-- Header row -->
                            <div class="p-3.5 sm:p-4">
                                <div class="flex items-center gap-2.5 sm:gap-3">
                                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg flex items-center justify-center shrink-0"
                                        :class="result.is_retur_rejection ? 'bg-red-500/10 text-red-400' : (result.is_return_transfer ? 'bg-amber-500/10 text-amber-400' : (result.is_arrival ? 'bg-indigo-500/10 text-indigo-400' : 'bg-green-500/10 text-green-400'))">
                                        <ArrowUpRight v-if="!result.is_arrival && !result.is_return_transfer" :size="18" />
                                        <MapPin v-else :size="18" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span v-if="result.is_retur_rejection" class="badge-xs bg-red-500/10 text-red-400 border-red-500/20">RETUR DITOLAK</span>
                                            <span v-else-if="result.is_return_transfer" class="badge-xs bg-amber-500/10 text-amber-400 border-amber-500/20">TERIMA BALIK</span>
                                            <span v-else-if="!result.is_arrival" class="badge-xs bg-green-500/10 text-green-400 border-green-500/20">MASUK</span>
                                            <span v-else class="badge-xs bg-indigo-500/10 text-indigo-400 border-indigo-500/20">TRANSFER MASUK</span>
                                        </div>
                                        <p class="text-sm font-semibold text-text-primary mt-0.5 truncate">{{ result.product_name }}</p>
                                        <p class="text-[11px] text-text-secondary font-mono">{{ result.imei }}</p>
                                    </div>
                                    <div class="text-[10px] sm:text-xs text-text-secondary text-right shrink-0 whitespace-nowrap">
                                        {{ formatDate(result.created_at) }}
                                    </div>
                                </div>

                                <!-- Details -->
                                <div class="mt-3 pt-3 border-t border-surface-700/50 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-2.5">
                                    <div v-if="result.id && isNaN(result.id)">
                                        <p class="text-[10px] text-text-secondary">No. Transaksi</p>
                                        <p class="text-xs text-text-primary font-mono">{{ result.id }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Kondisi</p>
                                        <p class="text-xs text-text-primary capitalize">{{ result.condition || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Status</p>
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold capitalize" :class="{
                                            'bg-green-500/10 text-green-400': result.status === 'available',
                                            'bg-amber-500/10 text-amber-400': result.status === 'sold',
                                            'bg-blue-500/10 text-blue-400': result.status === 'transfer',
                                            'bg-red-500/10 text-red-400': result.status === 'deleted'
                                        }">{{ result.status }}</span>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Lokasi</p>
                                        <p class="text-xs text-text-primary">{{ result.placement_name || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Harga Jual</p>
                                        <p class="text-xs text-text-primary font-semibold">{{ formatCurrency(result.selling_price) }}</p>
                                    </div>
                                    <div v-if="result.distributor || result.supplier_name">
                                        <p class="text-[10px] text-text-secondary">Distributor</p>
                                        <p class="text-xs text-text-primary">{{ result.distributor || result.supplier_name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">{{ result.is_arrival ? 'Diterima oleh' : 'Diinput oleh' }}</p>
                                        <p class="text-xs text-text-primary">{{ result.input_by || '-' }}</p>
                                    </div>
                                    <div v-if="result.storage && (!result.is_arrival || result.is_return_transfer)">
                                        <p class="text-[10px] text-text-secondary">Storage</p>
                                        <p class="text-xs text-text-primary">{{ result.storage }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ STOCK OUT ══ -->
                        <div v-else-if="result.type === 'stock_out'" class="card-track overflow-hidden">
                            <!-- Top bar color -->
                            <div class="h-0.5" :class="{
                                'bg-blue-500': result.category === 'pindah_cabang',
                                'bg-amber-500': ['kesalahan_input', 'hilang'].includes(result.category),
                                'bg-purple-500': ['retur', 'keluar', 'giveaway_customer', 'inventaris'].includes(result.category),
                                'bg-pink-500': result.category === 'brand_ambassador',
                                'bg-cyan-500': result.category === 'event_sponsorship',
                                'bg-[#EE4D2D]': ['shopee', 'orderan_online'].includes(result.category),
                                'bg-indigo-500': ['angkat_barang', 'refund'].includes(result.category),
                                'bg-emerald-500': ['penjualan', 'penjualan_store', 'penjualan_offline', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'].includes(result.category),
                            }"></div>

                            <!-- Header -->
                            <div class="p-3.5 sm:p-4">
                                <div class="flex items-center gap-2.5 sm:gap-3">
                                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg flex items-center justify-center shrink-0" :class="{
                                        'bg-blue-500/10 text-blue-400': result.category === 'pindah_cabang',
                                        'bg-amber-500/10 text-amber-400': ['kesalahan_input', 'hilang'].includes(result.category),
                                        'bg-purple-500/10 text-purple-400': ['retur', 'keluar', 'giveaway_customer', 'inventaris'].includes(result.category),
                                        'bg-pink-500/10 text-pink-400': result.category === 'brand_ambassador',
                                        'bg-cyan-500/10 text-cyan-400': result.category === 'event_sponsorship',
                                        'bg-[#EE4D2D]/10 text-[#EE4D2D]': ['shopee', 'orderan_online'].includes(result.category),
                                        'bg-indigo-500/10 text-indigo-400': ['angkat_barang', 'refund'].includes(result.category),
                                        'bg-emerald-500/10 text-emerald-400': ['penjualan', 'penjualan_store', 'penjualan_offline', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'].includes(result.category),
                                    }">
                                        <component :is="categoryIcons[result.category]" :size="18" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span v-if="['angkat_barang', 'refund'].includes(result.category)"
                                                class="badge-xs bg-indigo-500/10 text-indigo-400 border-indigo-500/20">MASUK</span>
                                            <span v-else class="badge-xs bg-red-500/10 text-red-400 border-red-500/20">KELUAR</span>
                                            <span class="text-[10px] text-text-secondary">{{ categoryLabels[result.category] }}</span>
                                            <span v-if="result.category === 'pindah_cabang'" class="badge-xs" :class="{
                                                'bg-yellow-500/10 text-yellow-400 border-yellow-500/20': result.status === 'pending',
                                                'bg-green-500/10 text-green-400 border-green-500/20': result.status === 'received',
                                                'bg-red-500/10 text-red-400 border-red-500/20': result.status === 'rejected'
                                            }">{{ result.status === 'pending' ? 'Menunggu' : (result.status === 'rejected' ? 'Ditolak' : 'Selesai') }}</span>
                                        </div>
                                        <p class="text-sm font-semibold text-text-primary mt-0.5 truncate font-mono">{{ result.id }}</p>
                                    </div>
                                    <div class="text-[10px] sm:text-xs text-text-secondary text-right shrink-0 whitespace-nowrap">
                                        {{ formatDate(result.created_at) }}
                                    </div>
                                </div>

                                <!-- Items -->
                                <div v-if="result.items && result.items.length > 0" class="mt-3 pt-3 border-t border-surface-700/50">
                                    <p class="text-[10px] text-text-secondary font-semibold uppercase tracking-wider mb-2">Barang ({{ result.items.length }})</p>
                                    <div class="space-y-1.5">
                                        <div v-for="(item, idx) in result.items" :key="idx"
                                            class="flex items-center gap-2.5 px-2.5 py-2 bg-surface-800/50 rounded-lg">
                                            <component :is="item.type === 'bundle' ? Package : (item.type === 'non-hp' ? Box : Smartphone)" :size="14" class="text-surface-500 shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs text-text-primary truncate" :class="{'text-primary-400 font-bold': item.type === 'bundle'}">{{ item.product_name }}</p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span v-if="item.imei && item.imei !== '-'" class="text-[10px] text-text-secondary font-mono">{{ item.imei }}</span>
                                                    <span v-else class="text-[10px] text-text-secondary">Qty: {{ item.quantity }}</span>
                                                    <span v-if="item.distributor_name || item.supplier_name" class="text-[9px] px-1.5 py-0.5 rounded bg-surface-700 text-primary-400 font-medium">{{ item.distributor_name || item.supplier_name }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Category-specific details -->
                                <div class="mt-3 pt-3 border-t border-surface-700/50 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2.5">
                                    <!-- Pindah Cabang -->
                                    <template v-if="result.category === 'pindah_cabang'">
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Cabang Tujuan</p>
                                            <p class="text-xs text-text-primary">{{ result.destination?.name || '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Penerima</p>
                                            <p class="text-xs text-text-primary">{{ result.receiver_name || '-' }}</p>
                                        </div>
                                    </template>

                                    <!-- Sales / Retur -->
                                    <template v-if="['retur', 'penjualan', 'penjualan_store', 'penjualan_offline', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'refund'].includes(result.category)">
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Customer</p>
                                            <p class="text-xs text-text-primary uppercase">{{ result.customer_name || '-' }}</p>
                                        </div>
                                        <div v-if="result.customer_wa">
                                            <p class="text-[10px] text-text-secondary">WhatsApp</p>
                                            <p class="text-xs text-text-primary">{{ result.customer_wa }}</p>
                                        </div>

                                        <div v-if="result.notes" class="col-span-full">
                                            <p class="text-[10px] text-text-secondary">Keterangan</p>
                                            <p class="text-xs text-text-primary italic">{{ result.notes }}</p>
                                        </div>
                                    </template>

                                    <!-- Shopee -->
                                    <template v-if="['shopee', 'orderan_online'].includes(result.category)">
                                        <template v-if="result.shopee_items_data?.length > 0">
                                            <div class="col-span-full space-y-1.5">
                                                <p class="text-[10px] text-text-secondary uppercase font-semibold">Detail Penerima</p>
                                                <div v-for="(si, idx) in result.shopee_items_data" :key="idx"
                                                    class="bg-surface-800/50 rounded-lg px-3 py-2 text-xs flex flex-wrap gap-x-4 gap-y-1">
                                                    <span><span class="text-text-secondary">Penerima:</span> <span class="text-text-primary">{{ si.receiver || '-' }}</span></span>
                                                    <span><span class="text-text-secondary">Resi:</span> <span class="text-text-primary font-mono">{{ si.tracking_no || result.shopee_tracking_no || '-' }}</span></span>
                                                    <span v-if="si.phone"><span class="text-text-secondary">WA:</span> <span class="text-text-primary">{{ si.phone }}</span></span>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div>
                                                <p class="text-[10px] text-text-secondary">Penerima</p>
                                                <p class="text-xs text-text-primary">{{ result.shopee_receiver || '-' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[10px] text-text-secondary">No. Resi</p>
                                                <p class="text-xs text-text-primary font-mono">{{ result.shopee_tracking_no || '-' }}</p>
                                            </div>
                                        </template>
                                    </template>

                                    <!-- Brand Ambassador -->
                                    <template v-if="result.category === 'brand_ambassador'">
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Nama BA</p>
                                            <p class="text-xs text-text-primary uppercase">{{ result.ba_name || '-' }}</p>
                                        </div>
                                        <div v-if="result.ba_phone">
                                            <p class="text-[10px] text-text-secondary">No. WA</p>
                                            <p class="text-xs text-text-primary">{{ result.ba_phone }}</p>
                                        </div>
                                        <div v-if="result.ba_notes" class="col-span-full">
                                            <p class="text-[10px] text-text-secondary">Catatan</p>
                                            <p class="text-xs text-text-primary italic">{{ result.ba_notes }}</p>
                                        </div>
                                    </template>

                                    <!-- Event / Sponsorship -->
                                    <template v-if="result.category === 'event_sponsorship'">
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Nama Event</p>
                                            <p class="text-xs text-text-primary">{{ result.event_name || '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Penanggung Jawab</p>
                                            <p class="text-xs text-text-primary uppercase">{{ result.event_receiver || '-' }}</p>
                                        </div>
                                        <div v-if="result.event_doc" class="col-span-full">
                                            <p class="text-[10px] text-text-secondary">Dokumen</p>
                                            <a :href="result.event_doc" target="_blank" class="text-xs text-primary-400 hover:underline break-all">{{ result.event_doc }}</a>
                                        </div>
                                    </template>

                                    <!-- Keluar -->
                                    <template v-if="result.category === 'keluar'">
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Sub Kategori</p>
                                            <p class="text-xs text-text-primary uppercase font-semibold">{{ result.sub_category || '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Penerima</p>
                                            <p class="text-xs text-text-primary uppercase">{{ result.receiver_name || result.notes || '-' }}</p>
                                        </div>
                                    </template>

                                    <!-- Kesalahan Input -->
                                    <template v-if="result.category === 'kesalahan_input'">
                                        <div class="col-span-full">
                                            <p class="text-[10px] text-text-secondary">Alasan</p>
                                            <p class="text-xs text-text-primary">{{ result.deletion_reason }}</p>
                                        </div>
                                    </template>

                                    <!-- Giveaway -->
                                    <template v-if="result.category === 'giveaway_customer'">
                                        <div>
                                            <p class="text-[10px] text-text-secondary">Penerima</p>
                                            <p class="text-xs text-text-primary uppercase">{{ result.giveaway_receiver || '-' }}</p>
                                        </div>
                                        <div v-if="result.giveaway_phone">
                                            <p class="text-[10px] text-text-secondary">No. WA</p>
                                            <p class="text-xs text-text-primary">{{ result.giveaway_phone }}</p>
                                        </div>
                                        <div v-if="result.giveaway_address" class="col-span-full">
                                            <p class="text-[10px] text-text-secondary">Alamat</p>
                                            <p class="text-xs text-text-primary">{{ result.giveaway_address }}</p>
                                        </div>
                                    </template>

                                    <!-- Hilang / Inventaris -->
                                    <template v-if="['hilang', 'inventaris'].includes(result.category)">
                                        <div v-if="result.person_in_charge">
                                            <p class="text-[10px] text-text-secondary">Penanggung Jawab</p>
                                            <p class="text-xs text-text-primary uppercase">{{ result.person_in_charge }}</p>
                                        </div>
                                        <div v-if="result.sub_category">
                                            <p class="text-[10px] text-text-secondary">Sub Kategori</p>
                                            <p class="text-xs text-text-primary uppercase">{{ result.sub_category }}</p>
                                        </div>
                                        <div v-if="result.loss_chronology" class="col-span-full">
                                            <p class="text-[10px] text-text-secondary">Kronologi</p>
                                            <p class="text-xs text-text-primary">{{ result.loss_chronology }}</p>
                                        </div>
                                        <div v-if="result.notes" class="col-span-full">
                                            <p class="text-[10px] text-text-secondary">Keterangan</p>
                                            <p class="text-xs text-text-primary italic">{{ result.notes }}</p>
                                        </div>
                                    </template>

                                    <!-- Lokasi & Admin (always shown) -->
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Lokasi Asal</p>
                                        <p class="text-xs text-text-primary">{{ result.source_name || result.branch?.name || result.online_shop?.name || result.warehouse?.name || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-text-secondary">Diproses oleh</p>
                                        <p class="text-xs text-text-primary">{{ result.processed_by || '-' }}</p>
                                    </div>
                                </div>

                                <!-- Aksi & Bukti -->
                                <div v-if="showAksiBukti(result)" class="mt-3 pt-3 border-t border-surface-700/50 flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] text-text-secondary font-semibold uppercase tracking-wider">Aksi & Bukti:</span>
                                    <template v-if="result.proof_images && result.proof_images.length > 0">
                                        <button v-for="(img, idx) in result.proof_images" :key="idx"
                                            @click="activeImage = img"
                                            class="flex items-center gap-1.5 bg-surface-700/50 hover:bg-surface-700 text-text-primary border border-surface-600 px-2.5 py-1.5 rounded-lg text-[11px] font-medium transition-all active:scale-95">
                                            <Eye :size="12" class="text-primary-400" />
                                            Bukti {{ idx + 1 }}
                                        </button>
                                    </template>
                                    <button v-if="canPrintReceipt(result)" @click="openReceipt(result)"
                                        class="flex items-center gap-1.5 bg-primary-600/10 hover:bg-primary-600/20 text-primary-400 border border-primary-500/30 px-2.5 py-1.5 rounded-lg text-[11px] font-medium transition-all active:scale-95">
                                        <Printer :size="12" />
                                        Print Nota
                                    </button>
                                </div>
                            </div>
                        </div>

                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <ReceiptModal :is-open="showReceiptModal" :transaction="currentReceiptData" :auto-send="false" @close="showReceiptModal = false" />

    <!-- Image Modal -->
    <div v-if="activeImage" class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm" @click.self="activeImage = null">
        <div class="relative w-full max-w-3xl">
            <button @click="activeImage = null" class="absolute -top-12 right-0 p-2 bg-white/10 hover:bg-white/20 text-white rounded-full transition-all">
                <X :size="20" />
            </button>
            <img :src="activeImage" alt="Bukti" class="w-full max-h-[80vh] object-contain rounded-xl" />
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.track-page {
    @apply max-w-3xl mx-auto px-4 sm:px-0 pb-12;
}

.card-track {
    @apply bg-surface-800 border border-surface-700 rounded-xl transition-all;
}

.badge-xs {
    @apply inline-flex px-1.5 py-0.5 rounded text-[9px] sm:text-[10px] font-bold uppercase tracking-wider border;
}
</style>
