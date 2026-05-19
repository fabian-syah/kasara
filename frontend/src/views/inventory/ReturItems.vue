<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../store/auth';
import api from '../../api/axios';
import {
    ArrowDownRight,
    Package,
    Loader2,
    Smartphone,
    User,
    Calendar,
    FileText,
    CheckCircle,
    AlertTriangle,
    Search,
    RefreshCw,
    Image,
    X,
    UserCircle,
    MessageSquare,
    Warehouse,
    ChevronRight,
    Tag,
    HardDrive,
    DollarSign,
    Shield,
    Phone,
    StickyNote,
    Hash,
    MapPin
} from 'lucide-vue-next';

const toast = useToast();
const authStore = useAuthStore();

// State
const returItems = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
let searchTimer = null;

// Detail modal state
const showDetail = ref(false);
const selectedItem = ref(null);

// Inventory Account Selection
const inventoryAccounts = ref([]);
const selectedInventoryAccount = ref('');
const isLoadingAccounts = ref(false);

// Accepting state
const isAccepting = ref(false);

// Fetch inventory accounts (gudang role)
async function fetchInventoryAccounts() {
    isLoadingAccounts.value = true;
    try {
        const response = await api.get('/inventory/my-accounts');
        inventoryAccounts.value = response.data || [];
        if (inventoryAccounts.value.length > 0) {
            selectedInventoryAccount.value = inventoryAccounts.value[0].id;
        }
    } catch (e) {
        console.error("Failed to fetch inventory accounts", e);
    } finally {
        isLoadingAccounts.value = false;
    }
}

// Fetch returned items (status = 'service' = retur items)
async function fetchReturItems() {
    isLoading.value = true;
    try {
        const search = searchQuery.value.trim();
        const response = await api.get('/inventory', {
            params: {
                status: 'service',
                type: 'hp',
                per_page: search ? -1 : 50,
                search: search || undefined
            }
        });
        returItems.value = response.data.data || response.data;
    } catch (e) {
        toast.error("Gagal memuat barang retur");
        console.error(e);
    } finally {
        isLoading.value = false;
    }
}

function handleSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchReturItems, 350);
}

function clearSearch() {
    searchQuery.value = '';
    fetchReturItems();
}

// Filtered items
const filteredItems = computed(() => {
    if (!searchQuery.value) return returItems.value;
    const q = searchQuery.value.toLowerCase();
    return returItems.value.filter(item =>
        item.imei?.toLowerCase().includes(q) ||
        item.product?.name?.toLowerCase().includes(q) ||
        item.sku?.toLowerCase().includes(q) ||
        item.retur_data?.customer_name?.toLowerCase().includes(q) ||
        item.retur_data?.receipt_id?.toLowerCase().includes(q)
    );
});

// Open detail view
function openDetail(item) {
    selectedItem.value = item;
    showDetail.value = true;
}

function closeDetail() {
    showDetail.value = false;
    selectedItem.value = null;
}

// Accept return
async function acceptReturn() {
    if (!selectedItem.value) return;
    if (!selectedInventoryAccount.value) {
        toast.error("Pilih akun inventory terlebih dahulu");
        return;
    }

    isAccepting.value = true;
    try {
        await api.patch(`/inventory/${selectedItem.value.id}/status`, {
            status: 'available',
            inventory_user_id: selectedInventoryAccount.value
        });
        toast.success("Barang berhasil diterima ke gudang");
        closeDetail();
        fetchReturItems();
    } catch (e) {
        toast.error("Gagal menerima barang");
    } finally {
        isAccepting.value = false;
    }
}

// Get selected account name
const selectedAccountName = computed(() => {
    const acc = inventoryAccounts.value.find(a => a.id === selectedInventoryAccount.value);
    return acc ? (acc.full_name || acc.name) : '';
});

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

// Format currency
function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}

// Condition label
function conditionLabel(cond) {
    if (!cond) return '-';
    if (cond === 'new') return 'Baru';
    if (cond === 'second') return 'Second';
    if (cond === 'ex_ibox') return 'Ex iBox';
    return cond;
}

function conditionClass(cond) {
    if (cond === 'new') return 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
    if (cond === 'ex_ibox') return 'bg-purple-500/15 text-purple-400 border-purple-500/30';
    return 'bg-amber-500/15 text-amber-400 border-amber-500/30';
}

onMounted(() => {
    fetchReturItems();
    fetchInventoryAccounts();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-6xl mx-auto pb-20">
        <!-- Header -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                    <ArrowDownRight :size="28" class="text-amber-500" /> Retur Masuk
                </h1>
                <p class="text-text-secondary mt-1">Daftar barang retur yang masuk ke gudang</p>
            </div>
            <button @click="fetchReturItems" class="btn btn-secondary h-12 px-4 rounded-xl">
                <RefreshCw :size="18" :class="{ 'animate-spin': isLoading }" />
            </button>
        </div>

        <div class="relative">
            <Search :size="18" class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" />
            <input
                v-model="searchQuery"
                @input="handleSearchInput"
                class="input h-12 pl-11 pr-11"
                placeholder="Cari IMEI atau nama barang retur..."
            />
            <button
                v-if="searchQuery"
                @click="clearSearch"
                class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg bg-surface-700 hover:bg-surface-600 flex items-center justify-center text-text-secondary"
            >
                <X :size="16" />
            </button>
        </div>

        <!-- Stats -->
        <div class="card bg-amber-500/10 border-amber-500/30">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-amber-500/20 flex items-center justify-center">
                    <Package :size="24" class="text-amber-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-text-primary">{{ filteredItems.length }}</p>
                    <p class="text-text-secondary text-sm">Barang Retur Menunggu</p>
                </div>
            </div>
        </div>

        <!-- Items List -->
        <div class="space-y-3">
            <div v-if="isLoading" class="text-center py-12 text-text-secondary">
                <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                Memuat barang retur...
            </div>
            <div v-else-if="filteredItems.length === 0" class="text-center py-12 text-text-secondary">
                <Package :size="48" class="mx-auto mb-2 opacity-50" />
                Tidak ada barang retur yang menunggu
            </div>

            <!-- Item Card - Clickable -->
            <div v-else v-for="item in filteredItems" :key="item.id" @click="openDetail(item)"
                class="card p-4 border-l-4 border-l-amber-500 hover:bg-surface-700/50 transition-all cursor-pointer group">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4 flex-1 min-w-0">
                        <!-- Proof Image Thumbnail -->
                        <div
                            class="w-14 h-14 rounded-xl bg-surface-700 flex items-center justify-center shrink-0 overflow-hidden">
                            <img v-if="item.retur_data?.proof_image" :src="item.retur_data.proof_image" alt="Foto Retur"
                                class="w-full h-full object-cover" />
                            <Package v-else :size="24" class="text-text-secondary" />
                        </div>

                        <!-- Basic Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <h3 class="font-bold text-text-primary truncate">{{ item.product?.name || 'Produk' }}
                                </h3>
                                <span :class="conditionClass(item.condition)"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border shrink-0">
                                    {{ conditionLabel(item.condition) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-text-secondary">
                                <span class="font-mono">{{ item.imei }}</span>
                                <span v-if="item.storage" class="text-text-secondary/70">{{ item.storage }}</span>
                            </div>
                            <div class="flex items-center gap-3 mt-1 text-xs">
                                <span v-if="item.retur_data?.receipt_id" class="text-amber-400 font-semibold">{{
                                    item.retur_data.receipt_id }}</span>
                                <span v-if="item.retur_data?.customer_name" class="text-text-secondary">
                                    <UserCircle :size="10" class="inline mr-0.5" />{{ item.retur_data.customer_name }}
                                </span>
                                <span v-if="item.retur_data?.created_at" class="text-text-secondary/60">
                                    {{ formatDate(item.retur_data.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Arrow -->
                    <ChevronRight :size="20"
                        class="text-text-secondary/50 group-hover:text-amber-500 transition-colors shrink-0" />
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetail && selectedItem"
            class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
            @click.self="closeDetail">
            <div
                class="bg-surface-800 rounded-2xl w-full max-w-2xl border border-surface-700 shadow-2xl animate-in zoom-in duration-200 max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="sticky top-0 bg-surface-800 p-5 border-b border-surface-700 z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-white">Detail Retur</h2>
                            <p v-if="selectedItem.retur_data?.receipt_id"
                                class="text-amber-400 text-sm font-mono mt-0.5">
                                {{ selectedItem.retur_data.receipt_id }}
                            </p>
                        </div>
                        <button @click="closeDetail"
                            class="w-10 h-10 rounded-xl bg-surface-700 hover:bg-surface-600 flex items-center justify-center transition-colors">
                            <X :size="20" class="text-text-secondary" />
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-5">
                    <!-- Proof Image -->
                    <div v-if="selectedItem.retur_data?.proof_image"
                        class="rounded-xl overflow-hidden border border-surface-600">
                        <img :src="selectedItem.retur_data.proof_image" alt="Foto Bukti Retur"
                            class="w-full max-h-72 object-contain bg-black/30" />
                        <div class="px-4 py-2 bg-surface-700/50 text-xs text-text-secondary flex items-center gap-1">
                            <Image :size="12" />
                            Foto Bukti Retur
                        </div>
                    </div>

                    <!-- Product Info Section -->
                    <div class="bg-surface-700/30 rounded-xl p-4 border border-surface-600 space-y-3">
                        <h4 class="text-xs font-bold text-text-secondary uppercase tracking-wider">Informasi Barang</h4>

                        <div class="grid grid-cols-2 gap-3">
                            <!-- Brand -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <Tag :size="10" /> Brand
                                </p>
                                <p class="text-sm font-semibold text-text-primary">
                                    {{ selectedItem.product?.brand || '-' }}
                                </p>
                            </div>

                            <!-- Type/Name -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <Smartphone :size="10" /> Produk
                                </p>
                                <p class="text-sm font-semibold text-text-primary">
                                    {{ selectedItem.product?.name || '-' }}
                                </p>
                            </div>

                            <!-- IMEI -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <Hash :size="10" /> IMEI
                                </p>
                                <p class="text-sm font-mono text-blue-400">
                                    {{ selectedItem.imei || '-' }}
                                </p>
                            </div>

                            <!-- Storage -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <HardDrive :size="10" /> Storage
                                </p>
                                <p class="text-sm font-semibold text-text-primary">
                                    {{ selectedItem.ram && selectedItem.storage ? `${selectedItem.ram} /
                                    ${selectedItem.storage}` : selectedItem.storage || '-' }}
                                </p>
                            </div>

                            <!-- Kondisi -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <Shield :size="10" /> Kondisi
                                </p>
                                <span :class="conditionClass(selectedItem.condition)"
                                    class="inline-block px-2 py-0.5 rounded-md text-xs font-bold border">
                                    {{ conditionLabel(selectedItem.condition) }}
                                </span>
                            </div>

                            <!-- Harga Jual -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <DollarSign :size="10" /> Harga Jual
                                </p>
                                <p class="text-sm font-bold text-emerald-400">
                                    {{ formatCurrency(selectedItem.selling_price ||
                                        selectedItem.retur_data?.selling_price) }}
                                </p>
                            </div>

                            <!-- Lokasi Asal -->
                            <div class="col-span-2 space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <MapPin :size="10" /> Lokasi Asal
                                </p>
                                <p class="text-sm text-text-primary">
                                    {{ selectedItem.placement_name || '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Retur Info Section -->
                    <div v-if="selectedItem.retur_data"
                        class="bg-amber-500/5 rounded-xl p-4 border border-amber-500/20 space-y-3">
                        <h4 class="text-xs font-bold text-amber-400 uppercase tracking-wider">Informasi Retur</h4>

                        <div class="grid grid-cols-2 gap-3">
                            <!-- Customer -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <UserCircle :size="10" /> Customer
                                </p>
                                <p class="text-sm font-semibold text-text-primary">
                                    {{ selectedItem.retur_data.customer_name || '-' }}
                                </p>
                            </div>

                            <!-- Phone -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <Phone :size="10" /> No. Telepon
                                </p>
                                <p class="text-sm text-text-primary">
                                    {{ selectedItem.retur_data.customer_phone || '-' }}
                                </p>
                            </div>

                            <!-- Petugas -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <User :size="10" /> Petugas
                                </p>
                                <p class="text-sm text-text-primary">
                                    {{ selectedItem.retur_data.retur_officer || '-' }}
                                </p>
                            </div>

                            <!-- Seal -->
                            <div class="space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <Shield :size="10" /> Seal
                                </p>
                                <p class="text-sm text-text-primary">
                                    {{ selectedItem.retur_data.retur_seal || '-' }}
                                </p>
                            </div>

                            <!-- Kendala -->
                            <div class="col-span-2 space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <AlertTriangle :size="10" /> Kendala / Alasan Retur
                                </p>
                                <p class="text-sm text-amber-300">
                                    {{ selectedItem.retur_data.retur_issue || '-' }}
                                </p>
                            </div>

                            <!-- Catatan -->
                            <div v-if="selectedItem.retur_data.notes" class="col-span-2 space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <StickyNote :size="10" /> Catatan
                                </p>
                                <p class="text-sm text-text-primary">
                                    {{ selectedItem.retur_data.notes }}
                                </p>
                            </div>

                            <!-- Tanggal Retur -->
                            <div class="col-span-2 space-y-0.5">
                                <p class="text-[10px] text-text-secondary uppercase flex items-center gap-1">
                                    <Calendar :size="10" /> Tanggal Retur
                                </p>
                                <p class="text-sm text-text-primary">
                                    {{ formatDate(selectedItem.retur_data.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Accept Section -->
                    <div class="bg-blue-500/5 rounded-xl p-4 border border-blue-500/20 space-y-3">
                        <h4 class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1">
                            <Warehouse :size="12" /> Terima Barang ke Gudang
                        </h4>
                        <select v-model="selectedInventoryAccount"
                            class="w-full bg-surface-800 border border-surface-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm">
                            <option value="" disabled>Pilih Akun Inventory</option>
                            <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.full_name || acc.name }} {{ acc.code_id ? `(${acc.code_id})` : '' }}
                            </option>
                        </select>
                        <p class="text-[10px] text-text-secondary">
                            Barang retur akan tercatat diterima oleh akun ini dan statusnya berubah menjadi tersedia.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="sticky bottom-0 bg-surface-800 p-5 border-t border-surface-700 flex gap-3">
                    <button @click="closeDetail" :disabled="isAccepting"
                        class="btn btn-secondary flex-1 h-12 rounded-xl font-bold">
                        Tutup
                    </button>
                    <button @click="acceptReturn" :disabled="isAccepting || !selectedInventoryAccount"
                        class="btn bg-green-600 hover:bg-green-700 text-white flex-1 h-12 rounded-xl font-bold disabled:opacity-30 disabled:cursor-not-allowed">
                        <Loader2 v-if="isAccepting" :size="18" class="animate-spin mr-2" />
                        <CheckCircle v-else :size="18" class="mr-2" />
                        {{ isAccepting ? 'Memproses...' : 'Terima Barang' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700;
}

.input {
    @apply w-full border border-surface-700 rounded-xl px-4 py-3 bg-surface-800 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-500;
}

.btn {
    @apply inline-flex items-center justify-center font-semibold transition-all;
}

.btn-secondary {
    @apply bg-surface-700 text-text-primary hover:bg-surface-600;
}
</style>
