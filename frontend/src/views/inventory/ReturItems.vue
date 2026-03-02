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
    Warehouse
} from 'lucide-vue-next';

const toast = useToast();
const authStore = useAuthStore();

// State
const returItems = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');

// Lightbox state
const showLightbox = ref(false);
const lightboxImage = ref(null);
const lightboxItem = ref(null);

// Inventory Account Selection
const inventoryAccounts = ref([]);
const selectedInventoryAccount = ref('');
const isLoadingAccounts = ref(false);

// Confirmation modal state
const showConfirmModal = ref(false);
const confirmItem = ref(null);
const isAccepting = ref(false);

// Fetch inventory accounts (gudang role)
async function fetchInventoryAccounts() {
    isLoadingAccounts.value = true;
    try {
        const response = await api.get('/inventory/my-accounts');
        inventoryAccounts.value = response.data || [];
        // Auto-select first if available
        if (inventoryAccounts.value.length > 0) {
            selectedInventoryAccount.value = inventoryAccounts.value[0].id;
        }
    } catch (e) {
        console.error("Failed to fetch inventory accounts", e);
    } finally {
        isLoadingAccounts.value = false;
    }
}

// Fetch returned items (status = 'returned', placement_type = 'warehouse')
async function fetchReturItems() {
    isLoading.value = true;
    try {
        const response = await api.get('/inventory', {
            params: {
                status: 'returned',
                placement_type: 'warehouse'
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

// Filtered items
const filteredItems = computed(() => {
    if (!searchQuery.value) return returItems.value;
    const q = searchQuery.value.toLowerCase();
    return returItems.value.filter(item =>
        item.imei?.toLowerCase().includes(q) ||
        item.product?.name?.toLowerCase().includes(q) ||
        item.sku?.toLowerCase().includes(q) ||
        item.customer_name?.toLowerCase().includes(q)
    );
});

// Open confirm modal
function openConfirmModal(item) {
    confirmItem.value = item;
    showConfirmModal.value = true;
}

function closeConfirmModal() {
    showConfirmModal.value = false;
    confirmItem.value = null;
}

// Accept return (change status to 'available') with inventory account
async function acceptReturn() {
    if (!confirmItem.value) return;
    if (!selectedInventoryAccount.value) {
        toast.error("Pilih akun inventory terlebih dahulu");
        return;
    }

    isAccepting.value = true;
    try {
        await api.patch(`/inventory/${confirmItem.value.id}/status`, {
            status: 'available',
            inventory_user_id: selectedInventoryAccount.value
        });
        toast.success("Barang berhasil diterima ke gudang");
        closeConfirmModal();
        closeLightbox();
        fetchReturItems();
    } catch (e) {
        toast.error("Gagal menerima barang");
    } finally {
        isAccepting.value = false;
    }
}

// Lightbox functions
function openLightbox(item) {
    lightboxItem.value = item;
    lightboxImage.value = item.proof_image;
    showLightbox.value = true;
}

function closeLightbox() {
    showLightbox.value = false;
    lightboxItem.value = null;
    lightboxImage.value = null;
}

// Get selected account name
const selectedAccountName = computed(() => {
    const acc = inventoryAccounts.value.find(a => a.id === selectedInventoryAccount.value);
    return acc ? (acc.full_name || acc.name) : '';
});

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

        <!-- Account Selection -->
        <div class="card bg-blue-500/5 border-blue-500/20">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                    <Warehouse :size="20" class="text-blue-500" />
                </div>
                <div>
                    <p class="font-bold text-text-primary text-sm">Akun Penerima</p>
                    <p class="text-xs text-text-secondary">Pilih akun inventory gudang yang menerima barang retur</p>
                </div>
            </div>
            <select v-model="selectedInventoryAccount"
                class="w-full bg-surface-800 border border-surface-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                <option value="" disabled>Pilih Akun Inventory</option>
                <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                    {{ acc.full_name || acc.name }} {{ acc.code_id ? `(${acc.code_id})` : '' }}
                </option>
            </select>
        </div>

        <!-- Search -->
        <div class="card">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input v-model="searchQuery" type="text" placeholder="Cari IMEI, produk, SKU, customer..."
                    class="input pl-10" />
            </div>
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
        <div class="space-y-4">
            <div v-if="isLoading" class="text-center py-12 text-text-secondary">
                <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                Memuat barang retur...
            </div>
            <div v-else-if="filteredItems.length === 0" class="text-center py-12 text-text-secondary">
                <Package :size="48" class="mx-auto mb-2 opacity-50" />
                Tidak ada barang retur yang menunggu
            </div>
            <div v-else v-for="item in filteredItems" :key="item.id"
                class="card p-5 border-l-4 border-l-amber-500 hover:bg-surface-700/50 transition-colors">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <!-- Product Image/Proof Thumbnail -->
                        <div @click="item.proof_image && openLightbox(item)"
                            class="w-20 h-20 rounded-xl bg-surface-700 flex items-center justify-center shrink-0 overflow-hidden relative group"
                            :class="{ 'cursor-pointer hover:ring-2 hover:ring-amber-500': item.proof_image }">
                            <img v-if="item.proof_image" :src="item.proof_image" alt="Foto Bukti"
                                class="w-full h-full object-cover" />
                            <Package v-else :size="28" class="text-text-secondary" />
                            <!-- Hover overlay -->
                            <div v-if="item.proof_image"
                                class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <Image :size="20" class="text-white" />
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="space-y-2 flex-1">
                            <div>
                                <h3 class="font-bold text-text-primary">{{ item.product?.name || 'Produk' }}</h3>
                                <p class="text-xs text-text-secondary">SKU: {{ item.sku || '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-text-secondary">
                                <Smartphone :size="14" />
                                <span class="font-mono">{{ item.imei }}</span>
                            </div>

                            <!-- Return Details -->
                            <div v-if="item.customer_name || item.retur_issue"
                                class="mt-2 p-3 rounded-lg bg-surface-700/50 space-y-1">
                                <div v-if="item.customer_name"
                                    class="flex items-center gap-2 text-xs text-text-secondary">
                                    <UserCircle :size="12" />
                                    <span>Customer: <strong class="text-text-primary">{{ item.customer_name
                                    }}</strong></span>
                                </div>
                                <div v-if="item.retur_issue" class="flex items-start gap-2 text-xs text-text-secondary">
                                    <MessageSquare :size="12" class="shrink-0 mt-0.5" />
                                    <span>Kendala: <span class="text-amber-400">{{ item.retur_issue }}</span></span>
                                </div>
                                <div v-if="item.retur_officer"
                                    class="flex items-center gap-2 text-xs text-text-secondary">
                                    <User :size="12" />
                                    <span>Petugas: {{ item.retur_officer }}</span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 text-xs">
                                <span class="flex items-center gap-1 text-text-secondary">
                                    <Calendar :size="12" />
                                    {{ new Date(item.updated_at).toLocaleDateString('id-ID') }}
                                </span>
                                <span v-if="item.ram && item.storage"
                                    class="px-2 py-0.5 rounded bg-surface-700 text-text-secondary">
                                    {{ item.ram }}/{{ item.storage }}
                                </span>
                                <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-400">
                                    {{ item.condition || 'Second' }}
                                </span>
                                <span v-if="item.proof_image"
                                    class="px-2 py-0.5 rounded bg-green-500/20 text-green-400 flex items-center gap-1">
                                    <Image :size="10" />
                                    Ada Foto
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action -->
                    <button @click="openConfirmModal(item)"
                        :disabled="!selectedInventoryAccount"
                        class="btn bg-green-600 hover:bg-green-700 text-white px-4 h-10 rounded-xl text-sm font-medium shrink-0 disabled:opacity-30 disabled:cursor-not-allowed">
                        <CheckCircle :size="16" class="mr-1" />
                        Terima
                    </button>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div v-if="showConfirmModal && confirmItem"
            class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
            <div
                class="bg-surface-800 rounded-2xl w-full max-w-lg border border-surface-700 shadow-2xl animate-in zoom-in duration-200">
                <!-- Modal Header -->
                <div class="p-6 border-b border-surface-700">
                    <h2 class="text-xl font-bold text-white">Konfirmasi Terima Retur</h2>
                    <p class="text-text-secondary text-sm mt-1">Pastikan akun yang menerima sudah benar</p>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-5">
                    <!-- Item Info -->
                    <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600">
                        <h3 class="font-bold text-text-primary mb-2">{{ confirmItem.product?.name || 'Produk' }}</h3>
                        <div class="flex items-center gap-2 text-sm text-text-secondary mb-1">
                            <Smartphone :size="14" />
                            <span class="font-mono">{{ confirmItem.imei }}</span>
                        </div>
                        <div v-if="confirmItem.customer_name" class="text-xs text-text-secondary mt-1">
                            Customer: <span class="text-text-primary font-medium">{{ confirmItem.customer_name }}</span>
                        </div>
                    </div>

                    <!-- Account Selection Confirm -->
                    <div class="bg-blue-500/10 p-4 rounded-xl border border-blue-500/20">
                        <label class="block text-sm font-medium text-text-primary mb-2 flex items-center gap-2">
                            <Warehouse :size="16" class="text-blue-500" />
                            Diterima oleh Akun:
                        </label>
                        <select v-model="selectedInventoryAccount"
                            class="w-full bg-surface-800 border border-surface-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="" disabled>Pilih Akun Inventory</option>
                            <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.full_name || acc.name }} {{ acc.code_id ? `(${acc.code_id})` : '' }}
                            </option>
                        </select>
                        <p class="text-xs text-text-secondary mt-2">
                            Barang retur akan tercatat diterima oleh akun ini.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t border-surface-700 flex gap-3">
                    <button @click="closeConfirmModal" :disabled="isAccepting"
                        class="btn btn-secondary flex-1 h-12 rounded-xl font-bold">
                        Batal
                    </button>
                    <button @click="acceptReturn" :disabled="isAccepting || !selectedInventoryAccount"
                        class="btn bg-green-600 hover:bg-green-700 text-white flex-1 h-12 rounded-xl font-bold disabled:opacity-30">
                        <Loader2 v-if="isAccepting" :size="18" class="animate-spin mr-2" />
                        <CheckCircle v-else :size="18" class="mr-2" />
                        {{ isAccepting ? 'Memproses...' : 'Terima Barang' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Lightbox Modal -->
        <div v-if="showLightbox" class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
            @click.self="closeLightbox">
            <div class="relative max-w-4xl w-full animate-in zoom-in duration-200">
                <!-- Close Button -->
                <button @click="closeLightbox"
                    class="absolute -top-12 right-0 text-white/70 hover:text-white transition-colors">
                    <X :size="32" />
                </button>

                <!-- Image -->
                <div class="bg-surface-800 rounded-2xl overflow-hidden">
                    <img :src="lightboxImage" alt="Foto Bukti Retur" class="w-full max-h-[70vh] object-contain" />

                    <!-- Item Details -->
                    <div v-if="lightboxItem" class="p-6 border-t border-surface-700">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-2">
                                <h3 class="font-bold text-text-primary text-lg">
                                    {{ lightboxItem.product?.name || 'Produk' }}
                                </h3>
                                <p class="font-mono text-sm text-text-secondary">IMEI: {{ lightboxItem.imei }}</p>
                                <div v-if="lightboxItem.customer_name" class="flex items-center gap-2 text-sm">
                                    <UserCircle :size="16" class="text-text-secondary" />
                                    <span class="text-text-secondary">Customer:</span>
                                    <span class="text-text-primary font-medium">{{ lightboxItem.customer_name }}</span>
                                </div>
                                <div v-if="lightboxItem.retur_issue" class="text-sm">
                                    <span class="text-text-secondary">Kendala:</span>
                                    <span class="text-amber-400 ml-2">{{ lightboxItem.retur_issue }}</span>
                                </div>
                            </div>
                            <button @click="openConfirmModal(lightboxItem)"
                                :disabled="!selectedInventoryAccount"
                                class="btn bg-green-600 hover:bg-green-700 text-white px-6 h-12 rounded-xl font-bold shrink-0 disabled:opacity-30">
                                <CheckCircle :size="18" class="mr-2" />
                                Terima Barang
                            </button>
                        </div>
                    </div>
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
