<script setup>
import { ref, onMounted, computed, watch } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useRouter } from "vue-router";
import PasswordModal from "../../components/modals/PasswordModal.vue";
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
    Store,
    ShoppingCart,
    Search,
    ChevronLeft,
    ChevronRight,
    FileText
} from "lucide-vue-next";

const toast = useToast();
const router = useRouter();

// State
const isLoading = ref(true);
const isSubmitting = ref(false);
const transfers = ref([]);

// Modal State
const showModal = ref(false);
const selectedTransfer = ref(null);
const form = ref({
    accepted_items: [], // HP IDs
    non_hp_quantities: {} // { id: qty }
});

const showPasswordModal = ref(false);
const pinCallback = ref(null);
const rejectionNotes = ref({}); // { itemId: string }
const nonHpRejectionNotes = ref({}); // { itemId: string }

function handleVerifyPin(callback) {
    pinCallback.value = callback;
    showPasswordModal.value = true;
}

function onPinVerified(pin) {
    showPasswordModal.value = false;
    if (pinCallback.value) {
        pinCallback.value(pin);
        pinCallback.value = null;
    }
}

// Inventory Accounts
const inventoryAccounts = ref([]);
const selectedInventoryAccount = ref("");

// Fetch Inventory Accounts
async function fetchInventoryAccounts() {
    try {
        const response = await api.get('/inventory/my-accounts');
        inventoryAccounts.value = response.data || [];
        if (inventoryAccounts.value.length > 0) {
            selectedInventoryAccount.value = inventoryAccounts.value[0].id;
        }
    } catch (e) {
        console.error("Failed to fetch inventory accounts", e);
    }
}

// Fetch pending transfers
async function fetchPending() {
    isLoading.value = true;
    try {
        const response = await api.get('/transfers/pending');
        transfers.value = response.data.data || response.data || [];
    } catch (e) {
        console.error(e);
    } finally {
        isLoading.value = false;
    }
}

// Open Confirm Modal
function openConfirmModal(transfer) {
    selectedTransfer.value = transfer;

    if (inventoryAccounts.value.length > 0 && !selectedInventoryAccount.value) {
        selectedInventoryAccount.value = inventoryAccounts.value[0].id;
    }

    const accepted = [];
    if (transfer.items) {
        transfer.items.forEach(item => accepted.push(item.id));
    }

    const quantities = {};
    if (transfer.non_hp_items) {
        transfer.non_hp_items.forEach(item => {
            quantities[item.id] = item.quantity;
        });
    }

    form.value = {
        accepted_items: accepted,
        non_hp_quantities: quantities,
        items_rejection: {},
        non_hp_rejection_notes: {}
    };

    showModal.value = true;
}

// Close Modal
function closeModal() {
    showModal.value = false;
    selectedTransfer.value = null;
}

// Submit Confirmation
async function submitConfirmation(verifiedPin = null) {
    if (!selectedTransfer.value) return;

    const pin = typeof verifiedPin === 'string' ? verifiedPin : null;

    const selectedAccount = inventoryAccounts.value.find(acc => acc.id === selectedInventoryAccount.value);
    if (!pin && selectedAccount) {
        handleVerifyPin((vPin) => submitConfirmation(vPin));
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            items: form.value.accepted_items,
            items_rejection: form.value.items_rejection,
            non_hp_items: form.value.non_hp_quantities,
            non_hp_rejection_notes: form.value.non_hp_rejection_notes,
            inventory_user_id: selectedInventoryAccount.value,
            transaction_pin: pin
        };

        const response = await api.post(`/transfers/${selectedTransfer.value.id}/confirm`, payload);
        toast.success(response.data.message || "Transfer berhasil dikonfirmasi!");

        transfers.value = transfers.value.filter(t => t.id !== selectedTransfer.value.id);
        closeModal();
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mengkonfirmasi transfer");
    } finally {
        isSubmitting.value = false;
    }
}

function toggleHpItem(id) {
    const idx = form.value.accepted_items.indexOf(id);
    if (idx === -1) {
        form.value.accepted_items.push(id);
    } else {
        form.value.accepted_items.splice(idx, 1);
    }
}

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

onMounted(() => {
    fetchPending();
    fetchInventoryAccounts();
});
</script>

<template>
    <div class="space-y-8 animate-in fade-in max-w-7xl mx-auto pb-24 px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-2 border-b border-surface-700/50">
            <div class="flex items-start gap-5">
                <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gradient-to-br from-blue-500/20 to-blue-600/10 rounded-3xl flex items-center justify-center border border-blue-500/20 shadow-xl shadow-blue-500/5 shrink-0">
                    <ArrowDownRight :size="32" class="text-blue-500" />
                </div>
                <div class="pt-1">
                    <h1 class="text-3xl lg:text-5xl font-black text-white tracking-tight leading-tight">
                        Konfirmasi <span class="text-blue-500">Masuk</span> (OTW)
                    </h1>
                    <p class="text-text-secondary text-sm lg:text-base mt-2 max-w-xl">
                        Daftar barang yang sedang dalam perjalanan menuju lokasi Anda. Silakan verifikasi barang saat tiba.
                    </p>
                </div>
            </div>

            <button @click="fetchPending" :disabled="isLoading"
                class="btn btn-secondary gap-3 rounded-2xl h-[54px] px-6 text-base font-bold border border-surface-600 hover:border-blue-500/50 hover:bg-surface-750 transition-all shadow-lg active:scale-95 shrink-0 self-start lg:self-end">
                <RefreshCw :size="20" :class="{ 'animate-spin': isLoading }" />
                <span>{{ isLoading ? 'Memuat...' : 'Refresh Data' }}</span>
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-20 text-text-secondary">
            <Loader2 :size="40" class="animate-spin mx-auto mb-4" />
            <p>Memuat transfer masuk...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="transfers.length === 0" class="text-center py-20 bg-surface-800 rounded-3xl border border-surface-700">
            <div class="w-24 h-24 mx-auto bg-green-500/10 rounded-full flex items-center justify-center mb-6">
                <CheckCircle2 :size="48" class="text-green-500" />
            </div>
            <h2 class="text-2xl font-black text-text-primary mb-2">Semua Aman!</h2>
            <p class="text-text-secondary max-w-xs mx-auto">Tidak ada barang OTW yang perlu dikonfirmasi saat ini.</p>
        </div>

        <!-- Transfer Grid -->
        <div v-else class="space-y-6">
            <div class="flex items-center gap-3 px-2">
                <Clock :size="18" class="text-amber-500" />
                <p class="text-text-secondary font-bold text-sm uppercase tracking-widest">
                    {{ transfers.length }} Transfer Menunggu Konfirmasi
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                <div v-for="transfer in transfers" :key="transfer.id"
                    class="card hover:bg-surface-750 transition-all cursor-pointer group relative overflow-hidden border-l-4 border-l-blue-500 p-0 shadow-xl hover:shadow-blue-500/5 rounded-[2rem]"
                    @click="openConfirmModal(transfer)">
                    
                    <div class="p-6 lg:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-5">
                                <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-surface-700/50 text-blue-500 group-hover:scale-110 transition-transform border border-surface-600/30">
                                    <Building2 :size="32" />
                                </div>
                                <div>
                                    <p class="font-black text-xl lg:text-2xl text-white group-hover:text-blue-400 transition-colors mb-1">
                                        {{ transfer.receipt_id }}
                                    </p>
                                    <p class="text-base text-text-secondary font-medium flex items-center gap-2">
                                        <User :size="16" class="text-blue-500/70" />
                                        Dari: <span class="text-white font-bold">{{ (transfer.inventory_user?.name || transfer.inventoryUser?.name) || transfer.user?.name || 'Unknown' }}</span>
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
                            <div class="text-right">
                                <p class="text-xl lg:text-2xl font-black text-white">
                                    {{ (transfer.items?.length || 0) + (transfer.non_hp_items?.reduce((acc, i) => acc + i.quantity, 0) || 0) }}
                                    <span class="text-xs font-bold text-text-secondary uppercase ml-1">Unit</span>
                                </p>
                                <span class="px-3 py-1 rounded-lg bg-amber-500/10 text-amber-500 text-[10px] font-black uppercase tracking-[0.2em] shadow-sm border border-amber-500/20">
                                    PENDING
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
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
                            <div class="p-2.5 bg-blue-500/10 rounded-2xl border border-blue-500/20">
                                <ArrowDownRight :size="20" class="text-blue-500" />
                            </div>
                            <h2 class="text-3xl font-black text-white tracking-tight">Konfirmasi Terima</h2>
                        </div>
                        <div class="flex items-center gap-3 text-base text-text-secondary mt-1 ml-0.5">
                            <span class="font-bold text-white">{{ selectedTransfer.receipt_id }}</span>
                            <span class="opacity-30">•</span>
                            <span class="capitalize font-black tracking-widest text-xs px-2.5 py-1 rounded-lg border text-amber-500 border-amber-500/20 bg-amber-500/5">
                                Verifikasi Unit
                            </span>
                        </div>
                    </div>
                    <button @click="closeModal" class="p-3 bg-surface-700 hover:bg-surface-600 rounded-2xl text-text-secondary hover:text-white transition-all shadow-lg active:scale-90">
                        <X :size="24" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 sm:p-12 overflow-y-auto flex-1 space-y-12 custom-scrollbar bg-gradient-to-b from-surface-800 via-surface-800 to-surface-900/40">
                    
                    <!-- Account Selection Card -->
                    <div v-if="inventoryAccounts.length > 0"
                        class="bg-blue-500/5 p-8 rounded-[2.5rem] border border-blue-500/20 backdrop-blur-sm shadow-inner group transition-all hover:bg-blue-500/10">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-3 bg-blue-500/10 rounded-2xl border border-blue-500/20 group-hover:scale-110 transition-transform">
                                <User :size="24" class="text-blue-500" />
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-white uppercase tracking-tight">Akun Verifikator</h3>
                                <p class="text-sm text-text-secondary font-medium italic opacity-70">Pilih identitas yang akan memproses stok ini</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <select v-model="selectedInventoryAccount"
                                class="w-full bg-surface-900/80 border-2 border-surface-700 rounded-2xl px-6 py-4 text-lg font-bold text-white focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all appearance-none cursor-pointer shadow-xl">
                                <option value="" disabled>Pilih Akun Inventory...</option>
                                <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                                    {{ acc.full_name }} — ({{ acc.code_id }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Items Detail Sections -->
                    <div class="space-y-16">
                        <!-- HP Items -->
                        <div v-if="selectedTransfer.items && selectedTransfer.items.length > 0">
                            <div class="flex items-center justify-between mb-8">
                                <h3 class="font-black text-white text-xl flex items-center gap-4 uppercase tracking-[0.1em]">
                                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center border border-blue-500/20 shadow-lg shadow-blue-500/5">
                                        <Smartphone :size="20" class="text-blue-500" />
                                    </div>
                                    Unit Barang HP <span class="text-text-secondary opacity-40 ml-2">({{ selectedTransfer.items.length }})</span>
                                </h3>
                                <div class="flex items-center gap-2 text-[10px] font-black text-text-secondary bg-surface-700/50 px-4 py-2 rounded-full border border-surface-600">
                                    <AlertTriangle :size="14" class="text-amber-500" /> TAP UNTUK TOLAK
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.items" :key="item.id"
                                    class="relative group cursor-pointer overflow-hidden p-6 rounded-[2rem] border-2 transition-all duration-300 shadow-lg"
                                    :class="form.accepted_items.includes(item.id)
                                        ? 'bg-blue-500/5 border-blue-500/30 ring-1 ring-blue-500/20'
                                        : 'bg-red-500/5 border-red-500/30 grayscale hover:grayscale-0'"
                                    @click="toggleHpItem(item.id)">
                                    
                                    <div class="flex items-start justify-between">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <p class="font-black text-xl text-white tracking-tight group-hover:text-blue-400 transition-colors">
                                                    <span v-if="getBrandName(item)" class="text-blue-500/70 mr-1">[{{ getBrandName(item) }}]</span>
                                                    {{ item.product?.name }}
                                                </p>
                                            </div>
                                            <p class="text-xs font-mono font-black text-text-secondary tracking-[0.2em] pt-1 opacity-70 group-hover:opacity-100 transition-opacity">
                                                {{ item.imei }}
                                            </p>
                                            <div class="flex items-center gap-2 pt-2">
                                                <span v-if="item.ram || item.storage" class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-400 text-[10px] font-black border border-blue-500/10 uppercase">
                                                    {{ item.ram }}/{{ item.storage }}
                                                </span>
                                                <span v-if="item.condition" class="px-2 py-0.5 rounded-md bg-surface-700 text-text-secondary text-[10px] font-black border border-surface-600 uppercase tracking-widest">
                                                    {{ item.condition }}
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="shrink-0 w-12 h-12 rounded-2xl flex items-center justify-center transition-all shadow-inner"
                                            :class="form.accepted_items.includes(item.id) ? 'bg-green-500 text-white' : 'bg-red-500 text-white rotate-45'">
                                            <CheckCircle2 v-if="form.accepted_items.includes(item.id)" :size="24" />
                                            <X v-else :size="24" />
                                        </div>
                                    </div>

                                    <!-- Rejection Note -->
                                    <div v-if="!form.accepted_items.includes(item.id)"
                                        class="mt-6 pt-6 border-t border-red-500/20 animate-in slide-in-from-top-4 duration-300" @click.stop>
                                        <p class="text-[10px] font-black text-red-400 uppercase tracking-[0.3em] mb-2 px-1">Alasan Penolakan</p>
                                        <textarea v-model="form.items_rejection[item.id]"
                                            placeholder="Jelaskan kenapa unit ditolak (misal: IMEI beda, pecah, dsb)..."
                                            class="w-full bg-surface-900/50 border border-red-500/20 rounded-2xl px-4 py-3 text-sm text-white focus:outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10 transition-all min-h-[80px] shadow-inner font-medium"></textarea>
                                    </div>
                                    
                                    <!-- Status Badge -->
                                    <div class="absolute top-2 right-14" v-if="!form.accepted_items.includes(item.id)">
                                        <span class="text-[8px] font-black text-red-500 bg-red-500/10 px-2 py-0.5 rounded border border-red-500/20 uppercase tracking-widest">Reject</span>
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
                            
                            <div class="grid grid-cols-1 gap-6">
                                <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                                    class="p-8 rounded-[2.5rem] border border-surface-700 bg-surface-800/50 shadow-xl hover:border-orange-500/30 transition-all group">
                                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
                                        <div class="flex items-center gap-5">
                                            <div class="w-14 h-14 bg-surface-700/50 rounded-2xl flex items-center justify-center border border-surface-600/30 text-orange-500">
                                                <Package :size="24" />
                                            </div>
                                            <div>
                                                <p class="font-black text-xl text-white leading-tight group-hover:text-orange-400 transition-colors">
                                                    <span v-if="getBrandName(item)" class="text-orange-500/70 mr-1">[{{ getBrandName(item) }}]</span>
                                                    {{ item.product?.name }}
                                                </p>
                                                <p class="text-sm font-bold text-text-secondary mt-1 tracking-tight">Total Dikirim: <span class="text-white">{{ item.quantity }} Unit</span></p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-1.5 p-2 bg-surface-900 rounded-3xl border border-surface-700 shadow-inner w-full sm:w-auto overflow-hidden">
                                            <div class="px-6 py-1.5 font-black text-xs text-text-secondary uppercase tracking-[0.15em] border-r border-surface-700 shrink-0">QUANTITY RECEIVED</div>
                                            <input type="number" v-model="form.non_hp_quantities[item.id]"
                                                class="bg-transparent border-none w-24 text-center text-2xl font-black text-orange-500 focus:ring-0 focus:outline-none" 
                                                min="0" :max="item.quantity" />
                                            <div class="px-4 py-1.5 font-bold text-text-secondary grow text-right">UNIT</div>
                                        </div>
                                    </div>

                                    <!-- Rejection Note for Non-HP -->
                                    <div v-if="form.non_hp_quantities[item.id] < item.quantity"
                                        class="mt-8 pt-8 border-t border-red-500/20 animate-in slide-in-from-top-4 duration-300">
                                        <p class="text-[10px] text-red-400 mb-3 flex items-center gap-2 font-black uppercase tracking-[0.3em] px-2">
                                            <AlertTriangle :size="14" />
                                            {{ item.quantity - form.non_hp_quantities[item.id] }} unit dikembalikan / ditolak
                                        </p>
                                        <textarea v-model="form.non_hp_rejection_notes[item.id]"
                                            placeholder="Jelaskan alasan pengurangan unit..."
                                            class="w-full bg-surface-900/50 border border-red-500/20 rounded-[1.5rem] px-6 py-4 text-sm text-white focus:outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10 transition-all min-h-[100px] shadow-inner font-medium"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-8 py-8 border-t border-surface-700 bg-surface-800/90 backdrop-blur-xl z-20">
                    <button @click="submitConfirmation()" :disabled="isSubmitting"
                        class="w-full h-16 bg-blue-600 hover:bg-blue-500 shadow-xl shadow-blue-500/20 text-white font-black text-lg rounded-2xl active:scale-[0.98] transition-all flex items-center justify-center gap-4 disabled:opacity-50">
                        <Loader2 v-if="isSubmitting" :size="24" class="animate-spin" />
                        {{ isSubmitting ? 'MEMPROSES VERIFIKASI...' : 'TERIMA & INTEGRASIKAN KE STOK' }}
                        <CheckCircle2 v-if="!isSubmitting" :size="24" />
                    </button>
                    <p class="text-[10px] font-black text-text-secondary/40 text-center uppercase tracking-[0.4em] mt-5">Verified Transaction Protocol v2.4</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Modal -->
    <PasswordModal :show="showPasswordModal" @close="showPasswordModal = false" @success="onPinVerified" />
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
