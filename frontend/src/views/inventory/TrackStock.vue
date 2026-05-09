<script setup>
import { ref } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import {
    Search,
    Package,
    Loader2,
    Building2,
    AlertTriangle,
    RotateCcw,
    ShoppingBag,
    Calendar,
    User,
    Smartphone,
    ArrowDownRight,
    ArrowUpRight,
    MapPin,
    DollarSign,
    Box
} from "lucide-vue-next";

const toast = useToast();

// State
const query = ref("");
const isLoading = ref(false);
const results = ref([]);
const hasSearched = ref(false);

// Category styling for stock out
// Di dalam <script setup>
const categoryIcons = {
    pindah_cabang: Building2,
    kesalahan_input: AlertTriangle,
    retur: RotateCcw,
    shopee: ShoppingBag,
    orderan_online: ShoppingBag,
    penjualan_offline: ShoppingBag,
    penjualan: ShoppingBag,
    bundling: ShoppingBag,
    tukar_unit: ShoppingBag,
    tukar_tambah: ShoppingBag,
    downgrade: ShoppingBag,
    angkat_barang: Box,
    brand_ambassador: User,
    event_sponsorship: Calendar,
    keluar: Box,
};

const categoryLabels = {
    pindah_cabang: 'Pindah Cabang',
    kesalahan_input: 'Kesalahan Input',
    retur: 'Retur',
    shopee: 'Shopee',
    orderan_online: 'Orderan Online',
    penjualan_offline: 'Penjualan Offline',
    penjualan: 'Penjualan',
    bundling: 'Bundling',
    tukar_unit: 'Tukar Unit',
    tukar_tambah: 'Tukar Tambah',
    downgrade: 'Downgrade',
    angkat_barang: 'Angkat Barang',
    brand_ambassador: 'Brand Ambassador',
    event_sponsorship: 'Event / Sponsorship',
    keluar: 'Keluar',
};

// Search function
async function search() {
    if (query.value.length < 3) {
        toast.error("Minimal 3 karakter untuk mencari");
        return;
    }

    isLoading.value = true;
    hasSearched.value = true;

    try {
        const response = await api.get('/track', { params: { q: query.value } });
        // Filter out 'barang_masuk' stock_out records - internal records from stock-in process
        const data = response.data.data || [];
        results.value = data.filter(r => !(r.type === 'stock_out' && r.category === 'barang_masuk'));
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mencari");
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}

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

// Format currency
function formatCurrency(value) {
    if (!value) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
}
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center py-8">
            <div class="w-20 h-20 mx-auto bg-primary-500/20 rounded-3xl flex items-center justify-center mb-4">
                <Search :size="36" class="text-primary-500" />
            </div>
            <h1 class="text-3xl font-bold text-text-primary">Lacak & History IMEI</h1>
            <p class="text-text-secondary mt-2">Cek riwayat perpindahan, login, dan status barang berdasarkan IMEI/Resi
            </p>
        </div>

        <!-- Search Box -->
        <div class="card p-3 sm:p-4">
            <form @submit.prevent="search" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                    <input v-model="query" type="text" placeholder="Ketik IMEI, Resi, atau Kode..."
                        class="input pl-12 h-14 text-base sm:text-lg" />
                </div>
                <button type="submit" :disabled="isLoading || query.length < 3"
                    class="btn btn-primary h-14 px-8 rounded-2xl font-bold disabled:opacity-30">
                    <Loader2 v-if="isLoading" :size="20" class="animate-spin" />
                    <span v-else>Cari Barang</span>
                </button>
            </form>
        </div>

        <!-- Results -->
        <div v-if="hasSearched" class="space-y-4">
            <div v-if="isLoading" class="text-center py-12 text-text-secondary">
                <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                Mencari.....
            </div>

            <div v-else-if="results.length === 0" class="text-center py-12 text-text-secondary">
                <Package :size="48" class="mx-auto mb-2 opacity-50" />
                <p>Tidak ditemukan hasil untuk "{{ query }}"</p>
            </div>

            <div v-else class="space-y-4">
                <p class="text-text-secondary text-sm">Ditemukan {{ results.length }} hasil</p>

                <!-- TRACKING Result Flow -->
                <div class="relative space-y-12 pb-8">
                    <!-- Vertical Timeline Line -->
                    <div class="absolute left-6 top-6 bottom-6 w-0.5 bg-surface-700 -z-10"></div>

                    <template v-for="result in results">
                        <!-- STOCK IN / MASUK (Pendaftaran atau Penerimaan) -->
                        <div v-if="result.type === 'stock_in'" :key="'in-' + result.id"
                            class="card p-6 border-l-4 transition-all relative"
                            :class="result.is_return_transfer ? 'border-l-amber-500 bg-amber-500/5' : (result.is_arrival ? 'border-l-indigo-500 bg-indigo-500/5' : 'border-l-green-500')">
                            <!-- Header -->
                            <div class="flex flex-col sm:flex-row items-start justify-between gap-4 mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                        :class="result.is_return_transfer ? 'bg-amber-500/20 text-amber-500' : (result.is_arrival ? 'bg-indigo-500/20 text-indigo-500' : 'bg-green-500/20 text-green-500')">
                                        <ArrowUpRight v-if="!result.is_arrival && !result.is_return_transfer" :size="24" />
                                        <MapPin v-else :size="24" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span v-if="result.is_return_transfer"
                                                class="text-amber-400 text-[10px] font-bold bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded uppercase tracking-wider">TERIMA BALIK TRANSFER</span>
                                            <span v-else-if="!result.is_arrival"
                                                class="text-green-400 text-[10px] font-bold bg-green-500/10 border border-green-500/20 px-2 py-0.5 rounded uppercase tracking-wider">MASUK
                                                (STOK)</span>
                                            <span v-else
                                                class="text-indigo-400 text-[10px] font-bold bg-indigo-500/10 border border-indigo-500/20 px-2 py-0.5 rounded uppercase tracking-wider">MASUK
                                                (TRANSFER)</span>
                                            <p class="font-bold text-text-primary text-base truncate">{{
                                                result.product_name }}</p>
                                        </div>
                                        <p class="text-sm text-text-secondary font-mono tracking-tight">{{ result.imei
                                            }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 text-text-secondary text-xs sm:text-sm bg-surface-900/50 px-3 py-1.5 rounded-lg border border-surface-700/50">
                                    <Calendar :size="14" />
                                    {{ formatDate(result.created_at) }}
                                </div>
                            </div>

                            <!-- Details Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <Smartphone :size="12" /> Kondisi
                                    </p>
                                    <p class="text-text-primary capitalize">{{ result.condition || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <Box :size="12" /> Status
                                    </p>
                                    <span class="px-2 py-0.5 rounded text-xs font-bold capitalize" :class="{
                                        'bg-green-500/20 text-green-500': result.status === 'available',
                                        'bg-amber-500/20 text-amber-500': result.status === 'sold',
                                        'bg-blue-500/20 text-blue-500': result.status === 'transfer',
                                        'bg-red-500/20 text-red-500': result.status === 'deleted'
                                    }">
                                        {{ result.status }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <MapPin :size="12" /> Lokasi
                                    </p>
                                    <p class="text-text-primary">{{ result.placement_name || result.placement_type + '#'
                                        +
                                        result.placement_id }}</p>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <DollarSign :size="12" /> Harga Jual
                                    </p>
                                    <p class="text-text-primary font-bold">{{ formatCurrency(result.selling_price) }}
                                    </p>
                                </div>
                                <div v-if="result.distributor">
                                    <p class="text-text-secondary text-xs">Distributor</p>
                                    <p class="text-text-primary">{{ result.distributor }}</p>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <User :size="12" /> {{ result.is_return_transfer ? 'Diterima Balik oleh' : (result.is_arrival ? 'Diterima oleh' : 'Diinput oleh') }}
                                    </p>
                                    <p class="text-text-primary">{{ result.input_by || '-' }}</p>
                                </div>
                                <div v-if="result.storage && (!result.is_arrival || result.is_return_transfer)">
                                    <p class="text-text-secondary text-xs">Storage / Kapasitas</p>
                                    <p class="text-text-primary">{{ result.storage || '-' }}</p>
                                </div>
                                <div v-if="result.rejected_by">
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <User :size="12" /> Ditolak oleh
                                    </p>
                                    <p class="text-text-primary">{{ result.rejected_by || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- STOCK OUT Result -->
                        <div v-else-if="result.type === 'stock_out'" :key="'out-' + result.id"
                            class="card p-6 border-l-4 hover:bg-surface-700/30 transition-all" :class="{
                                'border-l-blue-500': result.category === 'pindah_cabang',
                                'border-l-amber-500': result.category === 'kesalahan_input',
                                'border-l-purple-500': result.category === 'retur' || result.category === 'keluar',
                                'border-l-pink-500': result.category === 'brand_ambassador',
                                'border-l-cyan-500': result.category === 'event_sponsorship',
                                'border-l-[#EE4D2D]': ['shopee', 'orderan_online'].includes(result.category),
                                'border-l-indigo-500': ['angkat_barang', 'refund'].includes(result.category),
                                'border-l-emerald-500': ['penjualan', 'penjualan_offline', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'].includes(result.category),
                            }">

                            <div class="flex flex-col sm:flex-row items-start justify-between gap-4 mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" :class="{
                                        'bg-blue-500/20 text-blue-500': result.category === 'pindah_cabang',
                                        'bg-amber-500/20 text-amber-500': result.category === 'kesalahan_input',
                                        'bg-purple-500/20 text-purple-500': result.category === 'retur' || result.category === 'keluar',
                                        'bg-pink-500/20 text-pink-500': result.category === 'brand_ambassador',
                                        'bg-cyan-500/20 text-cyan-500': result.category === 'event_sponsorship',
                                        'bg-[#EE4D2D]/20 text-[#EE4D2D]': ['shopee', 'orderan_online'].includes(result.category),
                                        'bg-indigo-500/20 text-indigo-500': ['angkat_barang', 'refund'].includes(result.category),
                                        'bg-emerald-500/20 text-emerald-500': ['penjualan', 'penjualan_offline', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'].includes(result.category),
                                    }">
                                        <component :is="categoryIcons[result.category]" :size="24" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span v-if="['angkat_barang', 'refund'].includes(result.category)"
                                                class="text-blue-400 text-[10px] font-bold bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 rounded uppercase tracking-wider">MASUK (AKTIVITAS)</span>
                                            <span v-else
                                                class="text-red-400 text-[10px] font-bold bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded uppercase tracking-wider">KELUAR</span>
                                            <p class="font-bold text-text-primary text-base truncate">{{ result.id }}
                                            </p>
                                            <span v-if="result.category === 'pindah_cabang'"
                                                class="text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider"
                                                :class="{
                                                    'bg-yellow-500/20 text-yellow-500': result.status === 'pending',
                                                    'bg-green-500/20 text-green-500': result.status === 'received',
                                                    'bg-red-500/20 text-red-500': result.status === 'rejected'
                                                }">
                                                {{ result.status === 'pending' ? 'Menunggu' : (result.status ===
                                                    'rejected'
                                                    ? 'Ditolak' : 'Selesai') }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-text-secondary">{{ categoryLabels[result.category] }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 text-text-secondary text-xs sm:text-sm bg-surface-900/50 px-3 py-1.5 rounded-lg border border-surface-700/50">
                                    <Calendar :size="14" />
                                    {{ formatDate(result.created_at) }}
                                </div>
                            </div>

                            <!-- ITEMS LIST -->
                            <div class="mt-4 border-t border-surface-600 pt-4">
                                <p class="text-text-secondary text-xs mb-2 font-bold uppercase tracking-wider">Barang
                                    ({{
                                        result.items.length }})</p>
                                <div class="space-y-2">
                                    <div v-for="(item, idx) in result.items" :key="idx"
                                        class="flex items-center gap-3 p-2 bg-surface-700/30 rounded-lg">
                                        <component :is="item.type === 'bundle' ? Package : (item.type === 'non-hp' ? Box : Smartphone)" :size="16"
                                            class="text-text-secondary" />
                                        <div class="flex-1">
                                            <p class="text-text-primary text-sm font-medium" :class="{'text-primary-400 font-black': item.type === 'bundle'}">{{ item.product_name }}</p>
                                            <p class="text-text-secondary text-xs"
                                                v-if="item.imei && item.imei !== '-'">{{
                                                    item.imei }}</p>
                                            <p class="text-text-secondary text-xs" v-else>Qty: {{ item.quantity }}</p>
                                        </div>
                                        <div v-if="item.tracking_no" class="text-xs bg-surface-600 px-2 py-1 rounded">
                                            {{ item.tracking_no }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Details based on category -->
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                <!-- Pindah Cabang -->
                                <template v-if="result.category === 'pindah_cabang'">
                                    <div>
                                        <p class="text-text-secondary text-xs">Cabang Tujuan</p>
                                        <p class="text-text-primary">{{ result.destination?.name ||
                                            result.destination_branch?.name || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-text-secondary text-xs">Penerima</p>
                                        <p class="text-text-primary">{{ result.receiver_name || '-' }}</p>
                                    </div>
                                </template>

                                <!-- Retur / Sales -->
                                <template
                                    v-if="result.category === 'retur' || ['penjualan', 'penjualan_offline', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'].includes(result.category)">
                                    <div>
                                        <p class="text-text-secondary text-xs">Customer</p>
                                        <p class="text-text-primary uppercase">{{ result.customer_name || '-' }}</p>
                                    </div>
                                    <div v-if="result.customer_wa">
                                        <p class="text-text-secondary text-xs">WhatsApp</p>
                                        <p class="text-text-primary">{{ result.customer_wa }}</p>
                                    </div>
                                    <div v-if="result.transaction_pin">
                                        <p class="text-text-secondary text-xs">PIN Transaksi</p>
                                        <p class="text-text-primary font-mono">{{ result.transaction_pin }}</p>
                                    </div>
                                    <div v-if="result.notes" class="col-span-full">
                                        <p class="text-text-secondary text-xs">Keterangan / Notes</p>
                                        <p class="text-text-primary italic">{{ result.notes }}</p>
                                    </div>
                                </template>

                                <!-- Shopee (Per-Item) -->
                                <template v-if="['shopee', 'orderan_online'].includes(result.category)">
                                    <!-- Per-item data if available -->
                                    <template v-if="result.shopee_items_data?.length > 0">
                                        <div class="col-span-full space-y-2">
                                            <p class="text-text-secondary text-xs uppercase font-bold">Detail Penerima
                                                ({{
                                                    result.shopee_items_data.length }})</p>
                                            <div v-for="(shopeeItem, idx) in result.shopee_items_data" :key="idx"
                                                class="bg-surface-700/50 rounded-lg px-3 py-2 text-sm flex flex-wrap gap-x-6 gap-y-1">
                                                <span class="text-primary-400 font-bold">#{{ idx + 1 }}</span>
                                                <span>
                                                    <span class="text-text-secondary text-xs">Penerima:</span>
                                                    <span class="text-text-primary ml-1">{{ shopeeItem.receiver || '-'
                                                        }}</span>
                                                </span>
                                                <span>
                                                    <span class="text-text-secondary text-xs">No. Resi:</span>
                                                    <span class="text-text-primary font-mono ml-1">{{
                                                        shopeeItem.tracking_no
                                                        || result.shopee_tracking_no || '-' }}</span>
                                                </span>
                                                <span v-if="shopeeItem.phone">
                                                    <span class="text-text-secondary text-xs">WA:</span>
                                                    <span class="text-text-primary ml-1">{{ shopeeItem.phone }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Legacy single item fallback -->
                                    <template v-else>
                                        <div>
                                            <p class="text-text-secondary text-xs">Penerima</p>
                                            <p class="text-text-primary">{{ result.shopee_receiver || '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-text-secondary text-xs">No. Resi Shopee</p>
                                            <p class="text-text-primary font-mono">{{ result.shopee_tracking_no || '-'
                                                }}
                                            </p>
                                        </div>
                                    </template>
                                </template>

                                <!-- Brand Ambassador -->
                                <template v-if="result.category === 'brand_ambassador'">
                                    <div>
                                        <p class="text-text-secondary text-xs">Nama BA</p>
                                        <p class="text-text-primary uppercase">{{ result.ba_name || '-' }}</p>
                                    </div>
                                    <div v-if="result.ba_phone">
                                        <p class="text-text-secondary text-xs">No. WA</p>
                                        <p class="text-text-primary">{{ result.ba_phone }}</p>
                                    </div>
                                    <div v-if="result.ba_social_media">
                                        <p class="text-text-secondary text-xs">Sosial Media</p>
                                        <p class="text-text-primary font-mono text-xs">{{ result.ba_social_media }}</p>
                                    </div>
                                    <div v-if="result.ba_notes" class="col-span-full">
                                        <p class="text-text-secondary text-xs">Keterangan / Notes</p>
                                        <p class="text-text-primary italic">{{ result.ba_notes }}</p>
                                    </div>
                                </template>

                                <!-- Event / Sponsorship -->
                                <template v-if="result.category === 'event_sponsorship'">
                                    <div>
                                        <p class="text-text-secondary text-xs">Nama Event</p>
                                        <p class="text-text-primary">{{ result.event_name || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-text-secondary text-xs">Penanggung Jawab</p>
                                        <p class="text-text-primary uppercase">{{ result.event_receiver || '-' }}</p>
                                    </div>
                                    <div v-if="result.event_phone">
                                        <p class="text-text-secondary text-xs">No. WA</p>
                                        <p class="text-text-primary">{{ result.event_phone }}</p>
                                    </div>
                                    <div v-if="result.event_doc" class="col-span-full">
                                        <p class="text-text-secondary text-xs">Link Dokumen</p>
                                        <a :href="result.event_doc" target="_blank" class="text-primary-500 hover:underline break-all text-xs">{{ result.event_doc }}</a>
                                    </div>
                                    <div v-if="result.event_notes" class="col-span-full">
                                        <p class="text-text-secondary text-xs">Catatan Event</p>
                                        <p class="text-text-primary italic">{{ result.event_notes }}</p>
                                    </div>
                                </template>

                                <!-- Keluar -->
                                <template v-if="result.category === 'keluar'">
                                    <div>
                                        <p class="text-text-secondary text-xs">Sub Kategori</p>
                                        <p class="text-text-primary uppercase font-bold">{{ result.sub_category || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-text-secondary text-xs">Penerima</p>
                                        <p class="text-text-primary uppercase">{{ result.receiver_name || '-' }}</p>
                                    </div>
                                    <div v-if="result.notes" class="col-span-full">
                                        <p class="text-text-secondary text-xs">Keterangan / Notes</p>
                                        <p class="text-text-primary italic">{{ result.notes }}</p>
                                    </div>
                                </template>

                                <!-- Kesalahan Input -->
                                <template v-if="result.category === 'kesalahan_input'">
                                    <div class="col-span-full">
                                        <p class="text-text-secondary text-xs">Alasan</p>
                                        <p class="text-text-primary">{{ result.deletion_reason }}</p>
                                    </div>
                                </template>

                                <!-- Admin -->
                                <div>
                                    <p class="text-text-secondary text-xs">Diproses oleh</p>
                                    <p class="text-text-primary flex items-center gap-1">
                                        <User :size="12" />
                                        {{ result.processed_by || '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.input {
    @apply w-full border border-surface-700 rounded-xl px-4 py-3 bg-surface-800 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-500;
}

.btn {
    @apply font-bold transition-all duration-300 disabled:opacity-20 disabled:cursor-not-allowed flex items-center justify-center rounded-xl;
}

.btn-primary {
    @apply bg-primary-600 hover:bg-primary-500 text-white;
}

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700;
}
</style>
